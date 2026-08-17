<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/v2/Repository.php';

$db = FinDeskV2Database::pdo();
$repo = new FinDeskV2Repository($db);

$userId = 1;
$claudiaWorkspaceId = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
$archiveWorkspaceId = '3bb2f598-540e-4878-9d92-aad24a7d12ac';
$title = 'Исторический отчет от 30.04.2025 · период 2025-04-10';
$entrySeqFrom = 17905;
$entrySeqTo = 17919;
$closedDate = '2025-04-30';

function v2_april_json($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function v2_april_private(object $object, string $method, array $args)
{
    $ref = new ReflectionMethod($object, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($object, $args);
}

function v2_april_category_id(PDO $db, string $workspaceId, string $code): string
{
    $stmt = $db->prepare("
        SELECT id
        FROM v2_categories
        WHERE code = ?
          AND is_active = 1
          AND (workspace_id IS NULL OR workspace_id = ?)
        ORDER BY workspace_id IS NULL ASC
        LIMIT 1
    ");
    $stmt->execute([$code, $workspaceId]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        throw new RuntimeException('unknown_category_' . $code);
    }

    return (string)$id;
}

$stamp = date('Ymd-His');
$backupDir = __DIR__ . '/../storage/production-audits/historical-april-2025-report-' . $stamp;
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}
$backup = [];
foreach (['v2_entries', 'v2_report_batches', 'v2_report_batch_entries', 'v2_report_batch_html_snapshots', 'v2_audit_log'] as $table) {
    $backup[$table] = $db->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
}
file_put_contents($backupDir . '/before.json', v2_april_json($backup));

$existing = $db->prepare("
    SELECT *
    FROM v2_report_batches
    WHERE workspace_id = ?
      AND title = ?
      AND batch_type = 'operational_fragment'
    LIMIT 1
");
$existing->execute([$claudiaWorkspaceId, $title]);
$existingReport = $existing->fetch(PDO::FETCH_ASSOC);

$db->beginTransaction();
try {
    $categoryMap = [
        17907 => 'tech_parts',
        17911 => 'transport_expenses',
        17916 => 'current_boat_expenses',
    ];
    $beforeCategories = [];
    $readBefore = $db->prepare("SELECT created_seq, id, raw_text, category_id FROM v2_entries WHERE created_seq = ? LIMIT 1");
    $updateCategory = $db->prepare("UPDATE v2_entries SET category_id = ?, updated_at = CURRENT_TIMESTAMP WHERE created_seq = ?");
    foreach ($categoryMap as $seq => $code) {
        $readBefore->execute([$seq]);
        $beforeCategories[] = $readBefore->fetch(PDO::FETCH_ASSOC);
        $updateCategory->execute([v2_april_category_id($db, $archiveWorkspaceId, $code), $seq]);
    }
    $db->prepare("
        INSERT INTO v2_audit_log (id, workspace_id, entity_type, entity_id, action, before_json, after_json, performed_by)
        VALUES (?, ?, 'entry_category', NULL, 'historical_april_2025_category_cleanup', ?, ?, ?)
    ")->execute([
        FinDeskV2Support::uuid(),
        $archiveWorkspaceId,
        FinDeskV2Support::jsonEncode($beforeCategories),
        FinDeskV2Support::jsonEncode($categoryMap),
        $userId,
    ]);
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    throw $e;
}

if (!$existingReport) {
    $entryStmt = $db->prepare("
        SELECT id
        FROM v2_entries
        WHERE workspace_id = ?
          AND created_seq BETWEEN ? AND ?
          AND archived_at IS NULL
        ORDER BY date, created_seq
    ");
    $entryStmt->execute([$archiveWorkspaceId, $entrySeqFrom, $entrySeqTo]);
    $entryIds = array_map('strval', $entryStmt->fetchAll(PDO::FETCH_COLUMN));
    if (count($entryIds) !== 15) {
        throw new RuntimeException('historical_april_entry_slice_not_found');
    }

    $created = $repo->createOperationalReportFragment($archiveWorkspaceId, [
        'entry_ids' => $entryIds,
        'title' => $title,
        'status' => 'created',
        'closed_date' => $closedDate,
        'allow_locked_entries' => true,
    ], $userId);

    $batchId = (string)$created['id'];
    $batch = $repo->getOperationalReportFragment($archiveWorkspaceId, $batchId, $userId);
    $summary = $batch['summary'];
    $sourceTrace = $batch['source_trace'];
    $summary['header']['workspace'] = [
        'id' => $claudiaWorkspaceId,
        'name' => 'Claudia Z',
        'type' => 'yacht',
    ];
    $summary['header']['historical_source_workspace'] = [
        'id' => $archiveWorkspaceId,
        'name' => 'Claudia Z Archive Raw History',
    ];
    $summary['header']['closed_date'] = $closedDate;
    $summary['source_trace']['historical_source_workspace'] = $summary['header']['historical_source_workspace'];
    $sourceTrace['historical_source_workspace'] = $summary['header']['historical_source_workspace'];

    $htmlFilename = 'storage/v2/report-batches/' . $claudiaWorkspaceId . '/' . $batchId . '.html';
    $contentPayload = [
        'report_type' => 'operational_fragment',
        'workspace_id' => $claudiaWorkspaceId,
        'batch_id' => $batchId,
        'title' => $title,
        'status' => $batch['status'],
        'closed_at' => $batch['closed_at'],
        'summary' => $summary,
        'source_entry_ids' => $batch['source_entry_ids'],
        'entry_snapshot' => $batch['entry_snapshot'],
    ];
    $contentHash = hash('sha256', FinDeskV2Support::jsonEncode($contentPayload));

    $db->prepare("
        UPDATE v2_report_batches
        SET workspace_id = ?,
            title = ?,
            html_filename = ?,
            summary_json = ?,
            source_trace_json = ?,
            content_hash = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
        LIMIT 1
    ")->execute([
        $claudiaWorkspaceId,
        $title,
        $htmlFilename,
        FinDeskV2Support::jsonEncode($summary),
        FinDeskV2Support::jsonEncode($sourceTrace),
        $contentHash,
        $batchId,
    ]);

    $db->prepare("DELETE FROM v2_report_batch_html_snapshots WHERE batch_id = ?")->execute([$batchId]);
    $batch = $repo->getOperationalReportFragment($claudiaWorkspaceId, $batchId, $userId);
    $workspace = $repo->getWorkspace($claudiaWorkspaceId, $userId);
    v2_april_private($repo, 'writeOperationalReportHtmlFile', [$batch, $workspace]);
    v2_april_private($repo, 'storeOperationalReportFragmentHtmlSnapshot', [
        $claudiaWorkspaceId,
        $batch,
        $workspace,
        $userId,
        'stored',
        'Historical April 2025 report attached to Claudia Z from archive source rows',
        false,
    ]);

    $db->prepare("
        INSERT INTO v2_audit_log (id, workspace_id, entity_type, entity_id, action, before_json, after_json, performed_by)
        VALUES (?, ?, 'report_batch', ?, 'historical_april_2025_attach_to_claudia_z', NULL, ?, ?)
    ")->execute([
        FinDeskV2Support::uuid(),
        $claudiaWorkspaceId,
        $batchId,
        FinDeskV2Support::jsonEncode([
            'title' => $title,
            'source_workspace_id' => $archiveWorkspaceId,
            'source_entry_created_seq' => [$entrySeqFrom, $entrySeqTo],
            'entry_ids' => $entryIds,
        ]),
        $userId,
    ]);
} else {
    $batchId = (string)$existingReport['id'];
}

$finalReport = $repo->getOperationalReportFragment($claudiaWorkspaceId, $batchId, $userId);
echo v2_april_json([
    'report_id' => $batchId,
    'report_workspace_id' => $claudiaWorkspaceId,
    'report_title' => $finalReport['title'],
    'report_dates' => [$finalReport['start_date'], $finalReport['end_date']],
    'report_entries' => $finalReport['entry_count'],
    'report_html' => $finalReport['html_filename'],
    'backup' => substr($backupDir, strlen(dirname(__DIR__)) + 1) . '/before.json',
]) . PHP_EOL;
