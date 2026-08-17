<?php

declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/v2/Repository.php';

header('Content-Type: application/json; charset=utf-8');

function finish(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$job = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string)($_GET['job'] ?? $_POST['job'] ?? ''));
$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'plan');
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if ($job === '' || !in_array($action, ['plan', 'commit'], true)) {
    finish(422, ['ok' => false, 'error' => 'invalid_job_or_action']);
}

$root = __DIR__;
$jobDir = $root . '/storage/production-audits/' . $job;
$tokenPath = $jobDir . '/token.txt';
$payloadPath = $jobDir . '/v2-payload.json';
if (!is_file($tokenPath) || !is_file($payloadPath)) {
    finish(404, ['ok' => false, 'error' => 'job_files_missing']);
}
if (!hash_equals(trim((string)file_get_contents($tokenPath)), $token)) {
    finish(403, ['ok' => false, 'error' => 'invalid_token']);
}

$raw = file_get_contents($payloadPath);
$payload = $raw === false ? null : json_decode($raw, true);
if (!is_array($payload) || !is_array($payload['tables'] ?? null)) {
    finish(422, ['ok' => false, 'error' => 'invalid_payload']);
}

$db = ql_db();
new FinDeskV2Repository($db);
$tables = [];
$rowTotal = 0;
foreach ($payload['tables'] as $tablePayload) {
    if (!is_array($tablePayload)) {
        finish(422, ['ok' => false, 'error' => 'invalid_table_payload']);
    }
    $name = (string)($tablePayload['name'] ?? '');
    if (preg_match('/^v2_[a-z0-9_]+$/', $name) !== 1) {
        finish(422, ['ok' => false, 'error' => 'invalid_table_name', 'table' => $name]);
    }
    $columns = $tablePayload['columns'] ?? [];
    $rows = $tablePayload['rows'] ?? [];
    if (!is_array($columns) || !is_array($rows)) {
        finish(422, ['ok' => false, 'error' => 'invalid_table_shape', 'table' => $name]);
    }
    foreach ($columns as $column) {
        if (!is_string($column) || preg_match('/^[a-zA-Z0-9_]+$/', $column) !== 1) {
            finish(422, ['ok' => false, 'error' => 'invalid_column_name', 'table' => $name]);
        }
    }
    $tables[] = [
        'name' => $name,
        'columns' => array_values($columns),
        'rows' => array_values($rows),
    ];
    $rowTotal += count($rows);
}

$existing = $db->query("SHOW TABLES LIKE 'v2\\_%'")->fetchAll(PDO::FETCH_COLUMN);
sort($existing, SORT_STRING);
$incoming = array_map(static fn (array $table): string => $table['name'], $tables);
sort($incoming, SORT_STRING);
if ($existing !== $incoming) {
    finish(409, [
        'ok' => false,
        'error' => 'table_set_mismatch',
        'missing_in_payload' => array_values(array_diff($existing, $incoming)),
        'missing_in_production' => array_values(array_diff($incoming, $existing)),
    ]);
}

$summary = [
    'ok' => true,
    'action' => $action,
    'job' => $job,
    'tables' => count($tables),
    'rows' => $rowTotal,
    'payload_sha256' => hash_file('sha256', $payloadPath),
    'checks_before' => productionChecks($db),
    'backup_path' => null,
    'imported' => null,
    'checks_after' => null,
];

if ($action === 'plan') {
    finish(200, $summary);
}

$backupPath = $jobDir . '/prod-before-import.json';
$backup = [
    'created_at' => date(DATE_ATOM),
    'tables' => [],
];
foreach ($existing as $tableName) {
    $quoted = '`' . str_replace('`', '``', (string)$tableName) . '`';
    $backup['tables'][] = [
        'name' => (string)$tableName,
        'rows' => $db->query("SELECT * FROM {$quoted}")->fetchAll(PDO::FETCH_ASSOC),
    ];
}
if (file_put_contents($backupPath, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    finish(500, ['ok' => false, 'error' => 'backup_write_failed']);
}
$summary['backup_path'] = 'storage/production-audits/' . $job . '/prod-before-import.json';

$imported = [];
$db->exec('SET FOREIGN_KEY_CHECKS=0');
$db->beginTransaction();
try {
    foreach (array_reverse($tables) as $table) {
        $db->exec('DELETE FROM `' . str_replace('`', '``', $table['name']) . '`');
    }
    foreach ($tables as $table) {
        $columns = $table['columns'];
        if ($columns === []) {
            continue;
        }
        $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO `' . str_replace('`', '``', $table['name']) . '` (' . implode(', ', $quotedColumns) . ') VALUES (' . $placeholders . ')';
        $stmt = $db->prepare($sql);
        foreach ($table['rows'] as $row) {
            if (!is_array($row)) {
                throw new RuntimeException('Invalid row in ' . $table['name']);
            }
            $values = [];
            foreach ($columns as $column) {
                $values[] = array_key_exists($column, $row) ? $row[$column] : null;
            }
            $stmt->execute($values);
        }
        $imported[$table['name']] = count($table['rows']);
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $db->exec('SET FOREIGN_KEY_CHECKS=1');
    finish(500, ['ok' => false, 'error' => 'import_failed', 'message' => $e->getMessage()]);
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');

$summary['imported'] = $imported;
$summary['checks_after'] = productionChecks($db);
finish(200, $summary);

function productionChecks(PDO $db): array
{
    $workspaceId = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
    $stmt = $db->prepare("
        SELECT COUNT(*) AS active_entries, MIN(date) AS first_date, MAX(date) AS last_date
        FROM v2_entries
        WHERE workspace_id = ? AND archived_at IS NULL
    ");
    $stmt->execute([$workspaceId]);
    $feed = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt = $db->prepare("
        SELECT f.opening_balance,
               COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0) AS net,
               f.opening_balance + COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0) AS cash_now,
               MAX(e.balance_after) AS latest_balance_after
        FROM v2_flows f
        LEFT JOIN v2_entries e ON e.flow_id = f.id AND e.archived_at IS NULL
        WHERE f.workspace_id = ? AND f.type = 'cash'
        GROUP BY f.id
    ");
    $stmt->execute([$workspaceId]);
    $cash = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM v2_entries
        WHERE workspace_id = ?
          AND archived_at IS NULL
          AND date BETWEEN '2025-04-01' AND '2025-12-31'
    ");
    $stmt->execute([$workspaceId]);

    return [
        'claudia_z_feed' => $feed,
        'claudia_z_cash' => $cash,
        'claudia_z_apr_dec_active' => (int)$stmt->fetchColumn(),
    ];
}
