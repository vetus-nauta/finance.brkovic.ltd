<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';
require_once __DIR__ . '/../app/v2/ReportSpreadsheetExporter.php';

$user = ql_current_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not authenticated';
    exit;
}

$id = strtolower(trim((string)($_GET['id'] ?? '')));
if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $id) !== 1) {
    http_response_code(422);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid report id';
    exit;
}
$type = strtolower(trim((string)($_GET['type'] ?? 'fragment')));
$isPackage = $type === 'package';

try {
    $db = FinDeskV2Database::pdo();
    $stmt = $isPackage
        ? $db->prepare("
            SELECT workspace_id
            FROM v2_report_packages
            WHERE id = ?
              AND package_type = 'operational_fragment_package'
            LIMIT 1
        ")
        : $db->prepare("
            SELECT workspace_id
            FROM v2_report_batches
            WHERE id = ?
              AND batch_type = 'operational_fragment'
            LIMIT 1
        ");
    $stmt->execute([$id]);
    $workspaceId = $stmt->fetchColumn();
    if (!$workspaceId) {
        throw new FinDeskV2HttpError(404, $isPackage ? 'report_package_not_found' : 'report_fragment_not_found');
    }

    $repo = new FinDeskV2Repository($db);
    $autoPrint = (string)($_GET['print'] ?? '') === '1';
    $download = (string)($_GET['download'] ?? '') === '1';
    $format = strtolower(trim((string)($_GET['format'] ?? 'html')));
    if ($isPackage) {
        $report = $repo->getOperationalReportPackage((string)$workspaceId, $id, (int)$user['id']);
    } else {
        $report = $repo->getOperationalReportFragment((string)$workspaceId, $id, (int)$user['id']);
    }
    if (in_array($format, ['xlsx', 'table'], true)) {
        $workspace = $repo->getWorkspace((string)$workspaceId, (int)$user['id']);
        $reports = $isPackage ? ($report['fragments'] ?? []) : [$report];
        if (!is_array($reports) || $reports === []) {
            throw new FinDeskV2HttpError(422, 'report_table_has_no_fragments');
        }
        $tmpPath = tempnam(sys_get_temp_dir(), 'findesk-report-');
        if (!is_string($tmpPath) || $tmpPath === '') {
            throw new FinDeskV2HttpError(500, 'report_table_tempfile_failed');
        }
        $xlsxPath = $tmpPath . '.xlsx';
        @unlink($tmpPath);
        FinDeskV2ReportSpreadsheetExporter::writeOperationalSummaryXlsx(
            $xlsxPath,
            $workspace,
            $reports,
            (string)($report['title'] ?? 'Сводный отчет')
        );
        $fileName = FinDeskV2ReportSpreadsheetExporter::downloadFileName($workspace, $reports);
        $fallbackFileName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $fileName) ?: 'findesk-summary.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Disposition: attachment; filename="' . addslashes($fallbackFileName) . '"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('Content-Length: ' . (string)filesize($xlsxPath));
        readfile($xlsxPath);
        @unlink($xlsxPath);
        exit;
    }
    $html = $isPackage
        ? $repo->renderOperationalReportPackageHtml((string)$workspaceId, $id, (int)$user['id'], $autoPrint)
        : $repo->renderOperationalReportFragmentHtml((string)$workspaceId, $id, (int)$user['id'], $autoPrint);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store, max-age=0');
    if ($download) {
        $fileName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', strtolower((string)($report['title'] ?? 'findesk-report')));
        $fileName = trim((string)$fileName, '-.') ?: 'findesk-report';
        header('Content-Disposition: attachment; filename="' . $fileName . '.html"');
    }
    echo $html;
} catch (FinDeskV2HttpError $e) {
    http_response_code($e->status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Report unavailable';
}
