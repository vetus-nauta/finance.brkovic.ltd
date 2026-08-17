<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

$workspaceId = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
$userId = 1;

$db = ql_db();
$repo = new FinDeskV2Repository($db);

$artifactDir = dirname(__DIR__) . '/storage/imports/claudia-z-reconciliation';
if (!is_dir($artifactDir) && !mkdir($artifactDir, 0775, true) && !is_dir($artifactDir)) {
    throw new RuntimeException('Cannot create artifact directory: ' . $artifactDir);
}

$stamp = date('Ymd-His');
$backup = [
    'generated_at' => date('c'),
    'workspace_id' => $workspaceId,
    'tables' => [],
];

foreach ([
    'v2_report_batches',
    'v2_report_batch_entries',
    'v2_report_batch_html_snapshots',
    'v2_report_packages',
    'v2_report_package_items',
    'v2_report_versions',
] as $table) {
    if ($table === 'v2_report_batch_entries') {
        $stmt = $db->prepare("
            SELECT r.*
            FROM v2_report_batch_entries r
            INNER JOIN v2_report_batches b ON b.id = r.batch_id
            WHERE b.workspace_id = ?
            ORDER BY r.batch_id, r.row_number
        ");
    } elseif ($table === 'v2_report_package_items') {
        $stmt = $db->prepare("
            SELECT i.*
            FROM v2_report_package_items i
            INNER JOIN v2_report_packages p ON p.id = i.package_id
            WHERE p.workspace_id = ?
            ORDER BY i.package_id, i.item_order
        ");
    } else {
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE workspace_id = ? ORDER BY created_at, id");
    }
    $stmt->execute([$workspaceId]);
    $backup['tables'][$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$backupPath = $artifactDir . '/report-refresh-backup-' . $stamp . '.json';
file_put_contents($backupPath, FinDeskV2Support::jsonEncode($backup) . PHP_EOL);

$stmt = $db->prepare("
    SELECT id, title, status, start_date, end_date, content_hash
    FROM v2_report_batches
    WHERE workspace_id = ?
      AND batch_type = 'operational_fragment'
      AND status <> 'superseded'
    ORDER BY start_date ASC, end_date ASC, created_at ASC
");
$stmt->execute([$workspaceId]);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
foreach ($batches as $batch) {
    $before = $repo->getOperationalReportFragment($workspaceId, (string)$batch['id'], $userId);
    $sourceEntryIds = array_values(array_map('strval', $before['source_entry_ids'] ?? []));
    $missingEntryIds = [];
    if ($sourceEntryIds !== []) {
        $found = [];
        foreach (array_chunk($sourceEntryIds, 500) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            $entryStmt = $db->prepare("
                SELECT id
                FROM v2_entries
                WHERE workspace_id = ?
                  AND id IN ({$placeholders})
            ");
            $entryStmt->execute(array_merge([$workspaceId], $chunk));
            foreach ($entryStmt->fetchAll(PDO::FETCH_COLUMN) as $entryId) {
                $found[(string)$entryId] = true;
            }
        }
        foreach ($sourceEntryIds as $entryId) {
            if (!isset($found[$entryId])) {
                $missingEntryIds[] = $entryId;
            }
        }
    }
    if ($missingEntryIds !== []) {
        $results[] = [
            'id' => (string)$before['id'],
            'title' => (string)$before['title'],
            'status_before' => (string)$before['status'],
            'status_after' => (string)$before['status'],
            'period' => (string)$before['start_date'] . ' - ' . (string)$before['end_date'],
            'entry_count' => (int)$before['entry_count'],
            'skipped' => true,
            'skip_reason' => 'source_entries_not_found',
            'missing_entry_count' => count($missingEntryIds),
            'missing_entry_sample' => array_slice($missingEntryIds, 0, 10),
        ];
        continue;
    }
    $after = $repo->updateOperationalReportFragment($workspaceId, (string)$batch['id'], [
        'title' => (string)$before['title'],
        'status' => (string)$before['status'],
        'rebuild_from_entries' => true,
    ], $userId);
    $summary = is_array($after['summary'] ?? null) ? $after['summary'] : [];
    $adminDebt = is_array($summary['blocks']['admin_debt'] ?? null) ? $summary['blocks']['admin_debt'] : [];
    $results[] = [
        'id' => (string)$after['id'],
        'title' => (string)$after['title'],
        'status_before' => (string)$before['status'],
        'status_after' => (string)$after['status'],
        'period' => (string)$after['start_date'] . ' - ' . (string)$after['end_date'],
        'entry_count' => (int)$after['entry_count'],
        'content_hash_before' => (string)$before['content_hash'],
        'content_hash_after' => (string)$after['content_hash'],
        'admin_debt_total' => $summary['totals']['admin_debt_total'] ?? null,
        'admin_debt_opening' => $adminDebt['opening_total'] ?? null,
        'admin_debt_increased' => $adminDebt['increased_total'] ?? null,
        'admin_debt_returned' => $adminDebt['returned_total'] ?? null,
        'admin_debt_count' => $adminDebt['count'] ?? null,
    ];
}

$snapshotStmt = $db->prepare("
    SELECT batch_id, MAX(version) AS latest_version, COUNT(*) AS snapshot_count
    FROM v2_report_batch_html_snapshots
    WHERE workspace_id = ?
    GROUP BY batch_id
");
$snapshotStmt->execute([$workspaceId]);
$snapshots = [];
foreach ($snapshotStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $snapshots[(string)$row['batch_id']] = [
        'latest_version' => (int)$row['latest_version'],
        'snapshot_count' => (int)$row['snapshot_count'],
    ];
}

$artifact = [
    'generated_at' => date('c'),
    'workspace_id' => $workspaceId,
    'backup_path' => str_replace(dirname(__DIR__) . '/', '', $backupPath),
    'refreshed_count' => count(array_filter($results, static fn (array $row): bool => empty($row['skipped']))),
    'skipped_count' => count(array_filter($results, static fn (array $row): bool => !empty($row['skipped']))),
    'reports' => array_map(static function (array $row) use ($snapshots): array {
        $row['html_snapshot'] = $snapshots[$row['id']] ?? null;
        return $row;
    }, $results),
];

$artifactPath = $artifactDir . '/report-refresh-result-' . $stamp . '.json';
file_put_contents($artifactPath, FinDeskV2Support::jsonEncode($artifact) . PHP_EOL);

echo 'backup=' . str_replace(dirname(__DIR__) . '/', '', $backupPath) . PHP_EOL;
echo 'artifact=' . str_replace(dirname(__DIR__) . '/', '', $artifactPath) . PHP_EOL;
echo 'refreshed=' . (string)$artifact['refreshed_count'] . PHP_EOL;
echo 'skipped=' . (string)$artifact['skipped_count'] . PHP_EOL;
foreach ($artifact['reports'] as $row) {
    if (!empty($row['skipped'])) {
        echo sprintf(
            "%s | %s | %s | skipped=%s missing=%s\n",
            $row['id'],
            $row['period'],
            $row['title'],
            (string)$row['skip_reason'],
            (string)$row['missing_entry_count']
        );
        continue;
    }
    echo sprintf(
        "%s | %s | %s | debt=%s | html_v=%s\n",
        $row['id'],
        $row['period'],
        $row['title'],
        (string)($row['admin_debt_total'] ?? ''),
        (string)($row['html_snapshot']['latest_version'] ?? '')
    );
}
