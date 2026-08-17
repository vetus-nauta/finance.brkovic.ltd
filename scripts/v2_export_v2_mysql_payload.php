<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

$db = ql_db();
$stamp = $argv[1] ?? date('Ymd-His');
$outDir = dirname(__DIR__) . '/storage/production-audits/prod-db-sync-' . preg_replace('/[^a-zA-Z0-9._-]+/', '-', $stamp);
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    throw new RuntimeException("Cannot create {$outDir}");
}

$tables = array_values(array_filter(
    $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN),
    static fn ($table): bool => is_string($table) && str_starts_with($table, 'v2_')
));
sort($tables, SORT_STRING);

$payload = [
    'generated_at' => date(DATE_ATOM),
    'source' => 'local_mysql',
    'scope' => 'v2_tables_only',
    'tables' => [],
    'checks' => [],
];

foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $columns = $db->query("SHOW COLUMNS FROM {$quoted}")->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_map(static fn (array $column): string => (string)$column['Field'], $columns);
    $rows = $db->query("SELECT * FROM {$quoted}")->fetchAll(PDO::FETCH_ASSOC);
    $payload['tables'][] = [
        'name' => $table,
        'columns' => $columnNames,
        'row_count' => count($rows),
        'rows' => $rows,
    ];
}

$workspaceId = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
$stmt = $db->prepare("
    SELECT f.opening_balance,
           COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0) AS net,
           f.opening_balance + COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount WHEN e.direction = 'out' THEN -e.amount ELSE 0 END), 0) AS cash_now
    FROM v2_flows f
    LEFT JOIN v2_entries e ON e.flow_id = f.id AND e.archived_at IS NULL
    WHERE f.workspace_id = ? AND f.type = 'cash'
    GROUP BY f.id
");
$stmt->execute([$workspaceId]);
$payload['checks']['claudia_z_cash'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$stmt = $db->prepare("
    SELECT COUNT(*) AS active_entries, MIN(date) AS first_date, MAX(date) AS last_date
    FROM v2_entries
    WHERE workspace_id = ? AND archived_at IS NULL
");
$stmt->execute([$workspaceId]);
$payload['checks']['claudia_z_feed'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

$path = $outDir . '/v2-payload.json';
$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($path, $json) === false) {
    throw new RuntimeException('Cannot write payload.');
}

echo json_encode([
    'ok' => true,
    'path' => $path,
    'bytes' => filesize($path),
    'tables' => count($payload['tables']),
    'rows' => array_sum(array_column($payload['tables'], 'row_count')),
    'sha256' => hash_file('sha256', $path),
    'checks' => $payload['checks'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
