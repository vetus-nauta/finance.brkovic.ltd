<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/v2/Repository.php';

$db = FinDeskV2Database::pdo();
$repo = new FinDeskV2Repository($db);

$userId = 1;
$workspaceId = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
$startDate = '2025-04-10';
$endDate = '2026-06-15';
$closedDate = '2026-06-15';
$title = 'Исторический отчет-смычка · период 2025-04-10 - 2026-06-15';

function v2_bridge_json($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

$stamp = date('Ymd-His');
$backupDir = __DIR__ . '/../storage/production-audits/history-bridge-report-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    throw new RuntimeException('backup_dir_create_failed');
}

$backup = [];
foreach (['v2_report_batches', 'v2_report_batch_entries', 'v2_report_batch_html_snapshots', 'v2_audit_log'] as $table) {
    $backup[$table] = $db->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
}
file_put_contents($backupDir . '/before.json', v2_bridge_json($backup) . PHP_EOL);

$existing = $db->prepare("
    SELECT id
    FROM v2_report_batches
    WHERE workspace_id = ?
      AND batch_type = 'operational_fragment'
      AND title = ?
      AND status <> 'superseded'
    LIMIT 1
");
$existing->execute([$workspaceId, $title]);
$batchId = $existing->fetchColumn();

if (!$batchId) {
    $entryStmt = $db->prepare("
        SELECT id
        FROM v2_entries
        WHERE workspace_id = ?
          AND archived_at IS NULL
          AND date BETWEEN ? AND ?
        ORDER BY date, created_seq
    ");
    $entryStmt->execute([$workspaceId, $startDate, $endDate]);
    $entryIds = array_map('strval', $entryStmt->fetchAll(PDO::FETCH_COLUMN));

    if (count($entryIds) === 0) {
        throw new RuntimeException('history_bridge_entries_not_found');
    }

    $created = $repo->createOperationalReportFragment($workspaceId, [
        'entry_ids' => $entryIds,
        'title' => $title,
        'status' => 'created',
        'closed_date' => $closedDate,
        'allow_locked_entries' => true,
    ], $userId);
    $batchId = (string)$created['id'];
}

$report = $repo->getOperationalReportFragment($workspaceId, (string)$batchId, $userId);

echo v2_bridge_json([
    'ok' => true,
    'report_id' => (string)$batchId,
    'title' => $report['title'],
    'status' => $report['status'],
    'start_date' => $report['start_date'],
    'end_date' => $report['end_date'],
    'entry_count' => $report['entry_count'],
    'closed_at' => $report['closed_at'],
    'html_filename' => $report['html_filename'],
    'content_hash' => $report['content_hash'],
    'backup' => substr($backupDir, strlen(dirname(__DIR__)) + 1) . '/before.json',
]) . PHP_EOL;
