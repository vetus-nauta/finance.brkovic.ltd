<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

function audit_rows(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return array_map('audit_cast_row', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function audit_cast_row(array $row): array
{
    foreach ($row as $key => $value) {
        if (is_string($value) && is_numeric($value)) {
            $row[$key] = str_contains($value, '.') ? (float)$value : (int)$value;
        }
    }
    return $row;
}

function audit_mask_email(?string $email): ?string
{
    if ($email === null || $email === '') {
        return $email;
    }
    [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($domain === '') {
        return '<masked>';
    }
    return mb_substr($name, 0, 2) . '***@' . $domain;
}

function audit_write_artifact(array $audit): string
{
    $dir = __DIR__ . '/../storage/production-audits/v2-persistence-foundation-' . date('Ymd-His');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create audit dir: {$dir}");
    }

    $path = $dir . '/mysql-model-audit.json';
    file_put_contents($path, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    return $path;
}

$db = ql_db();
$tables = $db->query("SHOW TABLES LIKE 'v2\\_%'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

$tableCounts = [];
$columns = [];
$indexes = [];
foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', (string)$table) . '`';
    $tableCounts[$table] = (int)$db->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
    $columns[$table] = audit_rows($db, "SHOW COLUMNS FROM {$quoted}");
    $indexes[$table] = audit_rows($db, "SHOW INDEX FROM {$quoted}");
}

$workspaceSummary = audit_rows($db, "
    SELECT
        w.id,
        w.name,
        w.type,
        w.currency,
        w.locale,
        CASE WHEN w.archived_at IS NULL THEN 'active' ELSE 'archived' END AS lifecycle,
        COUNT(DISTINCT m.id) AS members_count,
        COUNT(DISTINCT f.id) AS flows_count,
        COUNT(DISTINCT e.id) AS active_entries_count,
        COUNT(DISTINCT b.id) AS report_batches_count,
        MIN(e.date) AS first_entry_date,
        MAX(e.date) AS last_entry_date
    FROM v2_workspaces w
    LEFT JOIN v2_workspace_members m ON m.workspace_id = w.id
    LEFT JOIN v2_flows f ON f.workspace_id = w.id
    LEFT JOIN v2_entries e ON e.workspace_id = w.id AND e.archived_at IS NULL
    LEFT JOIN v2_report_batches b ON b.workspace_id = w.id
    GROUP BY w.id
    ORDER BY w.archived_at IS NOT NULL, w.name
");

$flowSummary = audit_rows($db, "
    SELECT
        w.name AS workspace_name,
        f.workspace_id,
        f.id AS flow_id,
        f.name,
        f.type,
        f.has_live_balance,
        f.opening_balance,
        COUNT(e.id) AS active_entries_count,
        COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN e.direction = 'out' THEN e.amount ELSE 0 END), 0) AS total_out,
        MAX(e.balance_after) AS latest_balance_after
    FROM v2_flows f
    INNER JOIN v2_workspaces w ON w.id = f.workspace_id
    LEFT JOIN v2_entries e ON e.flow_id = f.id AND e.archived_at IS NULL
    GROUP BY f.id
    ORDER BY w.name, f.type, f.name
");

$entryDistribution = audit_rows($db, "
    SELECT
        w.name AS workspace_name,
        DATE_FORMAT(e.date, '%Y-%m') AS month_key,
        f.type AS flow_type,
        e.entry_type,
        e.direction,
        e.status,
        COUNT(*) AS entries_count,
        COALESCE(SUM(e.amount), 0) AS total_amount
    FROM v2_entries e
    INNER JOIN v2_workspaces w ON w.id = e.workspace_id
    INNER JOIN v2_flows f ON f.id = e.flow_id
    WHERE e.archived_at IS NULL
    GROUP BY w.id, DATE_FORMAT(e.date, '%Y-%m'), f.type, e.entry_type, e.direction, e.status
    ORDER BY w.name, month_key, f.type, e.entry_type, e.status
");

$reportSummary = audit_rows($db, "
    SELECT
        w.name AS workspace_name,
        b.workspace_id,
        b.status,
        COUNT(*) AS report_count,
        COALESCE(SUM(b.entry_count), 0) AS total_report_entries,
        MIN(b.start_date) AS first_start_date,
        MAX(b.end_date) AS last_end_date
    FROM v2_report_batches b
    INNER JOIN v2_workspaces w ON w.id = b.workspace_id
    GROUP BY b.workspace_id, b.status
    ORDER BY w.name, b.status
");

$userSummary = audit_rows($db, "
    SELECT id, email, display_name, status, created_at
    FROM users
    ORDER BY id
");
foreach ($userSummary as &$user) {
    $user['email'] = audit_mask_email((string)($user['email'] ?? ''));
}
unset($user);

$audit = [
    'generated_at' => date(DATE_ATOM),
    'source' => 'local_mysql_mariadb',
    'scope' => 'read_only_v2_model',
    'tables_count' => count($tables),
    'table_counts' => $tableCounts,
    'workspaces' => $workspaceSummary,
    'users' => $userSummary,
    'flows' => $flowSummary,
    'entry_distribution' => $entryDistribution,
    'report_batches' => $reportSummary,
    'columns' => $columns,
    'indexes' => $indexes,
];

$path = audit_write_artifact($audit);

echo "MySQL v2 model audit written: {$path}\n";
echo "Tables: " . count($tables) . "\n";
echo "Entries: " . ($tableCounts['v2_entries'] ?? 0) . "\n";
echo "Workspaces: " . ($tableCounts['v2_workspaces'] ?? 0) . "\n";
echo "Report batches: " . ($tableCounts['v2_report_batches'] ?? 0) . "\n";
