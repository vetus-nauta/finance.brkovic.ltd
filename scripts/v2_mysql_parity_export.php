<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

function parity_canonicalize(mixed $value): mixed
{
    if (is_array($value)) {
        $isList = array_keys($value) === range(0, count($value) - 1);
        if (!$isList) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = parity_canonicalize($item);
        }
    }
    return $value;
}

function parity_hash(mixed $value): string
{
    return hash('sha256', json_encode(parity_canonicalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function parity_table_key(array $row): string
{
    if (isset($row['id'])) {
        return (string)$row['id'];
    }
    if (isset($row['created_seq'])) {
        return (string)$row['created_seq'];
    }
    return parity_hash($row);
}

function parity_money_fields(string $table): array
{
    return [
        'v2_accountable_offers' => ['amount'],
        'v2_accountable_reports' => ['issued_amount', 'spent_amount', 'returned_amount', 'reimburse_amount'],
        'v2_accountable_report_rows' => ['amount'],
        'v2_accountable_settlements' => ['amount'],
        'v2_entries' => ['amount', 'balance_after'],
        'v2_flows' => ['opening_balance'],
    ][$table] ?? [];
}

function parity_json_fields(string $table): array
{
    return [
        'v2_accountable_reports' => ['summary_json'],
        'v2_categories' => ['name_json'],
        'v2_category_rules' => ['keywords_json', 'exclude_keywords_json'],
        'v2_dictionary_training_decisions' => ['decision_json'],
        'v2_entries' => ['matched_rules_json'],
        'v2_import_rows' => ['raw_json', 'normalized_json', 'parse_notes'],
        'v2_import_sources' => ['metadata_json'],
        'v2_internet_reference_lookups' => ['query_json', 'result_json', 'feedback_json'],
        'v2_report_batch_entries' => ['entry_snapshot_json'],
        'v2_report_batches' => ['summary_json', 'source_trace_json', 'source_entry_ids_json', 'entry_snapshot_json'],
        'v2_report_package_items' => ['item_snapshot_json'],
        'v2_report_packages' => ['summary_json'],
        'v2_report_snapshots' => ['query_json', 'summary_json'],
        'v2_report_versions' => ['summary_json', 'source_trace_json'],
        'v2_workspace_assistant_settings' => ['settings_json'],
    ][$table] ?? [];
}

function parity_json_decode_or_keep(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }
    $trimmed = trim($value);
    if ($trimmed === '' || !in_array($trimmed[0], ['{', '['], true)) {
        return $value;
    }
    $decoded = json_decode($trimmed, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

function parity_transform_row(string $table, array $row): array
{
    foreach (parity_money_fields($table) as $field) {
        if (array_key_exists($field, $row) && $row[$field] !== null) {
            $row[$field] = number_format((float)$row[$field], 2, '.', '');
        }
    }
    foreach (parity_json_fields($table) as $field) {
        if (array_key_exists($field, $row)) {
            $row[$field] = parity_json_decode_or_keep($row[$field]);
        }
    }
    if ($table === 'users') {
        unset($row['deleted_at']);
    }
    return $row;
}

function parity_order_clause(PDO $db, string $table): string
{
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $columns = $db->query("SHOW COLUMNS FROM {$quoted}")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('id', $columns, true)) {
        return 'ORDER BY id';
    }
    if (in_array('created_seq', $columns, true)) {
        return 'ORDER BY created_seq';
    }
    if (in_array('created_at', $columns, true)) {
        return 'ORDER BY created_at';
    }
    return '';
}

function parity_rows(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function parity_write_artifact(array $payload): string
{
    $dir = __DIR__ . '/../storage/production-audits/v2-parity-export-' . date('Ymd-His');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create parity export dir: {$dir}");
    }
    $path = $dir . '/mysql-parity-export.json';
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    return $path;
}

function parity_integrity_checks(PDO $db): array
{
    $checks = [
        'workspace_members_missing_workspace' => "
            SELECT COUNT(*) FROM v2_workspace_members m
            LEFT JOIN v2_workspaces w ON w.id = m.workspace_id
            WHERE w.id IS NULL
        ",
        'workspace_members_missing_user' => "
            SELECT COUNT(*) FROM v2_workspace_members m
            LEFT JOIN users u ON u.id = m.user_id
            WHERE u.id IS NULL
        ",
        'flows_missing_workspace' => "
            SELECT COUNT(*) FROM v2_flows f
            LEFT JOIN v2_workspaces w ON w.id = f.workspace_id
            WHERE w.id IS NULL
        ",
        'entries_missing_workspace' => "
            SELECT COUNT(*) FROM v2_entries e
            LEFT JOIN v2_workspaces w ON w.id = e.workspace_id
            WHERE w.id IS NULL
        ",
        'entries_missing_flow' => "
            SELECT COUNT(*) FROM v2_entries e
            LEFT JOIN v2_flows f ON f.id = e.flow_id
            WHERE f.id IS NULL
        ",
        'entries_missing_category' => "
            SELECT COUNT(*) FROM v2_entries e
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.category_id IS NOT NULL AND c.id IS NULL
        ",
        'entries_missing_actor' => "
            SELECT COUNT(*) FROM v2_entries e
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE e.actor_id IS NOT NULL AND a.id IS NULL
        ",
        'duplicate_created_seq' => "
            SELECT COALESCE(SUM(duplicate_count), 0)
            FROM (
                SELECT COUNT(*) - 1 AS duplicate_count
                FROM v2_entries
                GROUP BY created_seq
                HAVING COUNT(*) > 1
            ) d
        ",
        'report_batches_missing_from_entry' => "
            SELECT COUNT(*) FROM v2_report_batches b
            LEFT JOIN v2_entries e ON e.id = b.from_entry_id
            WHERE b.from_entry_id IS NOT NULL AND e.id IS NULL
        ",
        'report_batches_missing_to_entry' => "
            SELECT COUNT(*) FROM v2_report_batches b
            LEFT JOIN v2_entries e ON e.id = b.to_entry_id
            WHERE b.to_entry_id IS NOT NULL AND e.id IS NULL
        ",
        'report_batch_entries_missing_batch' => "
            SELECT COUNT(*) FROM v2_report_batch_entries r
            LEFT JOIN v2_report_batches b ON b.id = r.batch_id
            WHERE b.id IS NULL
        ",
        'report_batch_entries_missing_entry' => "
            SELECT COUNT(*) FROM v2_report_batch_entries r
            LEFT JOIN v2_entries e ON e.id = r.entry_id
            WHERE e.id IS NULL
        ",
        'accountable_reports_missing_workspace' => "
            SELECT COUNT(*) FROM v2_accountable_reports r
            LEFT JOIN v2_workspaces w ON w.id = r.workspace_id
            WHERE w.id IS NULL
        ",
        'accountable_reports_missing_offer' => "
            SELECT COUNT(*) FROM v2_accountable_reports r
            LEFT JOIN v2_accountable_offers o ON o.id = r.offer_id
            WHERE r.offer_id IS NOT NULL AND o.id IS NULL
        ",
        'accountable_report_rows_missing_report' => "
            SELECT COUNT(*) FROM v2_accountable_report_rows r
            LEFT JOIN v2_accountable_reports ar ON ar.id = r.report_id
            WHERE ar.id IS NULL
        ",
        'accountable_report_entry_links_missing_report' => "
            SELECT COUNT(*) FROM v2_accountable_report_entry_links l
            LEFT JOIN v2_accountable_reports r ON r.id = l.report_id
            WHERE r.id IS NULL
        ",
        'accountable_report_entry_links_missing_row' => "
            SELECT COUNT(*) FROM v2_accountable_report_entry_links l
            LEFT JOIN v2_accountable_report_rows r ON r.id = l.report_row_id
            WHERE r.id IS NULL
        ",
        'accountable_report_entry_links_missing_entry' => "
            SELECT COUNT(*) FROM v2_accountable_report_entry_links l
            LEFT JOIN v2_entries e ON e.id = l.entry_id
            WHERE e.id IS NULL
        ",
        'import_sources_missing_workspace' => "
            SELECT COUNT(*) FROM v2_import_sources s
            LEFT JOIN v2_workspaces w ON w.id = s.workspace_id
            WHERE w.id IS NULL
        ",
        'import_rows_missing_source' => "
            SELECT COUNT(*) FROM v2_import_rows r
            LEFT JOIN v2_import_sources s ON s.id = r.import_source_id
            WHERE s.id IS NULL
        ",
        'import_rows_missing_linked_entry' => "
            SELECT COUNT(*) FROM v2_import_rows r
            LEFT JOIN v2_entries e ON e.id = r.entry_id
            WHERE r.entry_id IS NOT NULL AND e.id IS NULL
        ",
    ];

    $result = [];
    foreach ($checks as $name => $sql) {
        $result[$name] = (int)$db->query($sql)->fetchColumn();
    }
    $result['total_issues'] = array_sum($result);
    return $result;
}

$db = ql_db();
$tables = $db->query("SHOW TABLES LIKE 'v2\\_%'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
array_unshift($tables, 'users');

$exports = [];
foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', (string)$table) . '`';
    $rows = $db->query("SELECT * FROM {$quoted} " . parity_order_clause($db, (string)$table))->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row = parity_transform_row((string)$table, $row);
        if ($table === 'users') {
            if (isset($row['email'])) {
                $row['email_hash'] = hash('sha256', strtolower((string)$row['email']));
                unset($row['email']);
            }
            unset($row['password_hash']);
        }
    }
    unset($row);

    $rowHashes = [];
    foreach ($rows as $row) {
        $rowHashes[] = [
            'key' => parity_table_key($row),
            'hash' => parity_hash($row),
        ];
    }

    $exports[$table] = [
        'count' => count($rows),
        'table_hash' => parity_hash($rowHashes),
        'rows' => $rowHashes,
    ];
}

$claudiaWorkspaceId = getenv('FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID') ?: '0d4faca6-3138-4ffe-9805-a6a29895b7ed';
$cashSummary = parity_rows($db, "
    SELECT
        f.id AS flow_id,
        f.opening_balance,
        COUNT(e.id) AS active_entries_count,
        COALESCE(SUM(CASE WHEN e.direction = 'in' THEN e.amount ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN e.direction = 'out' THEN e.amount ELSE 0 END), 0) AS total_out,
        MAX(e.balance_after) AS latest_balance_after,
        MIN(e.date) AS first_date,
        MAX(e.date) AS last_date
    FROM v2_flows f
    LEFT JOIN v2_entries e ON e.flow_id = f.id AND e.archived_at IS NULL
    WHERE f.workspace_id = ? AND f.type = 'cash' AND f.has_live_balance = 1
    GROUP BY f.id
    LIMIT 1
", [$claudiaWorkspaceId]);

$payload = [
    'generated_at' => date(DATE_ATOM),
    'source' => 'mysql',
    'format' => 'findesk_v2_parity_export_v1',
    'redaction' => [
        'users.email' => 'sha256_lowercase',
        'users.password_hash' => 'omitted',
        'sessions' => 'omitted',
        'auth_codes' => 'omitted',
    ],
    'table_count' => count($exports),
    'tables' => $exports,
    'business_summaries' => [
        'claudia_z_cash' => $cashSummary[0] ?? null,
    ],
    'integrity' => parity_integrity_checks($db),
];

$path = parity_write_artifact($payload);
echo "MySQL parity export written: {$path}\n";
echo "Exported tables: " . count($exports) . "\n";
echo "v2_entries hash: " . ($exports['v2_entries']['table_hash'] ?? 'missing') . "\n";
