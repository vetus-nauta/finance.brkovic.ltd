<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/ReportSpreadsheetExporter.php';

function usage(): never
{
    fwrite(STDERR, implode(PHP_EOL, [
        'Usage:',
        '  php scripts/v2_export_operational_summary_xlsx.php --workspace-id=<uuid> --report-ids=<id1,id2> [--output=/path/file.xlsx]',
        '  php scripts/v2_export_operational_summary_xlsx.php --package-id=<uuid> [--output=/path/file.xlsx]',
        '  php scripts/v2_export_operational_summary_xlsx.php --workspace-id=<uuid> --latest=3 [--include-history-bridge=1]',
        '',
    ]));
    exit(2);
}

function optionValue(array $options, string $key, ?string $default = null): ?string
{
    $value = $options[$key] ?? $default;
    if (is_array($value)) {
        $value = end($value);
    }
    return $value === null ? null : trim((string)$value);
}

function requireUuid(string $value, string $key): string
{
    $value = strtolower(trim($value));
    if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value) !== 1) {
        throw new RuntimeException('Invalid ' . $key);
    }
    return $value;
}

function decodeReportRow(array $row): array
{
    $summary = json_decode((string)($row['summary_json'] ?? '{}'), true);
    $entrySnapshot = json_decode((string)($row['entry_snapshot_json'] ?? '[]'), true);
    return [
        'id' => (string)$row['id'],
        'workspace_id' => (string)$row['workspace_id'],
        'title' => (string)$row['title'],
        'status' => (string)$row['status'],
        'start_date' => (string)$row['start_date'],
        'end_date' => (string)$row['end_date'],
        'entry_count' => (int)$row['entry_count'],
        'summary' => is_array($summary) ? $summary : [],
        'entry_snapshot' => is_array($entrySnapshot) ? $entrySnapshot : [],
    ];
}

function workspaceById(PDO $db, string $workspaceId): array
{
    $stmt = $db->prepare("SELECT * FROM v2_workspaces WHERE id = ? LIMIT 1");
    $stmt->execute([$workspaceId]);
    $workspace = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$workspace) {
        throw new RuntimeException('Workspace not found: ' . $workspaceId);
    }
    return $workspace;
}

function reportsByIds(PDO $db, string $workspaceId, array $reportIds): array
{
    if ($reportIds === []) {
        throw new RuntimeException('Report ids are empty');
    }
    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
    $stmt = $db->prepare("
        SELECT *
        FROM v2_report_batches
        WHERE workspace_id = ?
          AND batch_type = 'operational_fragment'
          AND id IN ({$placeholders})
    ");
    $stmt->execute(array_merge([$workspaceId], $reportIds));
    $reports = array_map('decodeReportRow', $stmt->fetchAll(PDO::FETCH_ASSOC));
    if (count($reports) !== count($reportIds)) {
        throw new RuntimeException('Some reports were not found');
    }
    usort($reports, static fn (array $a, array $b): int => strcmp((string)$a['start_date'], (string)$b['start_date']));
    return $reports;
}

function latestReports(PDO $db, string $workspaceId, int $limit): array
{
    $limit = max(1, min(100, $limit));
    $stmt = $db->prepare("
        SELECT *
        FROM v2_report_batches
        WHERE workspace_id = ?
          AND batch_type = 'operational_fragment'
          AND status <> 'superseded'
        ORDER BY end_date DESC, generated_at DESC, created_at DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$workspaceId]);
    $reports = array_map('decodeReportRow', $stmt->fetchAll(PDO::FETCH_ASSOC));
    usort($reports, static fn (array $a, array $b): int => strcmp((string)$a['start_date'], (string)$b['start_date']));
    return $reports;
}

function historicalBridgeReport(PDO $db, string $workspaceId): ?array
{
    $stmt = $db->prepare("
        SELECT *
        FROM v2_report_batches
        WHERE workspace_id = ?
          AND title LIKE 'Исторический отчет-смычка%'
        ORDER BY generated_at DESC, created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$workspaceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? decodeReportRow($row) : null;
}

function packageFragments(PDO $db, string $packageId): array
{
    $stmt = $db->prepare("
        SELECT p.workspace_id, p.title, i.fragment_snapshot_json
        FROM v2_report_packages p
        INNER JOIN v2_report_package_items i ON i.package_id = p.id
        WHERE p.id = ?
          AND p.package_type = 'operational_fragment_package'
        ORDER BY i.item_order ASC
    ");
    $stmt->execute([$packageId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        throw new RuntimeException('Package not found or empty: ' . $packageId);
    }
    $fragments = [];
    foreach ($rows as $row) {
        $fragment = json_decode((string)$row['fragment_snapshot_json'], true);
        if (is_array($fragment)) {
            $fragments[] = $fragment;
        }
    }
    return [
        'workspace_id' => (string)$rows[0]['workspace_id'],
        'title' => (string)$rows[0]['title'],
        'reports' => $fragments,
    ];
}

$options = getopt('', [
    'workspace-id:',
    'report-ids:',
    'package-id:',
    'latest:',
    'include-history-bridge::',
    'output::',
    'title::',
]);

try {
    $db = ql_db();
    $packageId = optionValue($options, 'package-id');
    $title = optionValue($options, 'title');

    if ($packageId !== null && $packageId !== '') {
        $package = packageFragments($db, requireUuid($packageId, 'package-id'));
        $workspaceId = $package['workspace_id'];
        $reports = $package['reports'];
        $title = $title ?: $package['title'];
    } else {
        $workspaceId = requireUuid((string)optionValue($options, 'workspace-id', ''), 'workspace-id');
        $reportIdsRaw = optionValue($options, 'report-ids');
        if ($reportIdsRaw !== null && $reportIdsRaw !== '') {
            $reportIds = array_map(static fn (string $id): string => requireUuid($id, 'report-ids'), preg_split('/\s*,\s*/', $reportIdsRaw) ?: []);
            $reports = reportsByIds($db, $workspaceId, $reportIds);
        } else {
            $latest = (int)(optionValue($options, 'latest', '') ?? 0);
            if ($latest <= 0) {
                usage();
            }
            $reports = latestReports($db, $workspaceId, $latest);
            if ((optionValue($options, 'include-history-bridge', '0') ?? '0') !== '0') {
                $bridge = historicalBridgeReport($db, $workspaceId);
                if ($bridge !== null) {
                    $bridgeEnd = (string)$bridge['end_date'];
                    $reports = array_values(array_filter($reports, static fn (array $report): bool => strcmp((string)$report['start_date'], $bridgeEnd) > 0));
                    array_unshift($reports, $bridge);
                }
            }
        }
    }

    if ($reports === []) {
        throw new RuntimeException('No reports selected');
    }
    $workspace = workspaceById($db, $workspaceId);
    $outDir = dirname(__DIR__) . '/storage/production-audits/google-sheets';
    $output = optionValue($options, 'output') ?: ($outDir . '/findesk-operational-summary-' . date('Ymd-His') . '.xlsx');
    FinDeskV2ReportSpreadsheetExporter::writeOperationalSummaryXlsx($output, $workspace, $reports, $title);

    echo json_encode([
        'ok' => true,
        'path' => $output,
        'workspace_id' => $workspaceId,
        'reports' => array_map(static fn (array $report): array => [
            'id' => (string)($report['id'] ?? ''),
            'title' => (string)($report['title'] ?? ''),
            'period' => (string)($report['start_date'] ?? '') . ' - ' . (string)($report['end_date'] ?? ''),
        ], $reports),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
