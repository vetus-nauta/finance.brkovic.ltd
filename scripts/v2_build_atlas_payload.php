<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

function payload_json_decode_or_keep(mixed $value): mixed
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

function payload_money_fields(string $table): array
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

function payload_json_fields(string $table): array
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
        'v2_report_batch_html_snapshots' => [],
        'v2_report_batches' => ['summary_json', 'source_trace_json', 'source_entry_ids_json', 'entry_snapshot_json'],
        'v2_report_package_items' => ['item_snapshot_json'],
        'v2_report_packages' => ['summary_json'],
        'v2_report_snapshots' => ['query_json', 'summary_json'],
        'v2_report_versions' => ['summary_json', 'source_trace_json'],
        'v2_workspace_assistant_settings' => ['settings_json'],
    ][$table] ?? [];
}

function payload_transform_row(string $table, array $row): array
{
    $document = $row;
    foreach (payload_money_fields($table) as $field) {
        if (array_key_exists($field, $document) && $document[$field] !== null) {
            $document[$field] = number_format((float)$document[$field], 2, '.', '');
        }
    }
    foreach (payload_json_fields($table) as $field) {
        if (array_key_exists($field, $document)) {
            $document[$field] = payload_json_decode_or_keep($document[$field]);
        }
    }
    if ($table === 'users') {
        unset($document['deleted_at']);
    }
    return $document;
}

function payload_order_clause(PDO $db, string $table): string
{
    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $columns = $db->query("SHOW COLUMNS FROM {$quoted}")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('id', $columns, true)) {
        return 'ORDER BY id';
    }
    if (in_array('created_seq', $columns, true)) {
        return 'ORDER BY created_seq';
    }
    return '';
}

function payload_hash(mixed $value): string
{
    return hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function payload_write_artifact(array $payload): string
{
    $dir = __DIR__ . '/../storage/production-audits/v2-atlas-payload-' . date('Ymd-His');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create payload dir: {$dir}");
    }
    $path = $dir . '/atlas-payload.json';
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    return $path;
}

$db = ql_db();
$tables = $db->query("SHOW TABLES LIKE 'v2\\_%'")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
array_unshift($tables, 'users');

$collections = [];
$manifest = [];
foreach ($tables as $table) {
    $quoted = '`' . str_replace('`', '``', (string)$table) . '`';
    $rows = $db->query("SELECT * FROM {$quoted} " . payload_order_clause($db, (string)$table))->fetchAll(PDO::FETCH_ASSOC);
    $documents = array_map(static fn (array $row): array => payload_transform_row((string)$table, $row), $rows);
    $collections[$table] = $documents;
    $manifest[$table] = [
        'count' => count($documents),
        'payload_hash' => payload_hash($documents),
        'money_fields_as_canonical_strings' => payload_money_fields((string)$table),
        'json_fields_decoded' => payload_json_fields((string)$table),
    ];
}

$payload = [
    'generated_at' => date(DATE_ATOM),
    'mode' => 'payload_build_no_atlas_writes',
    'format' => 'findesk_v2_atlas_payload_v1',
    'target_collections' => array_keys($collections),
    'excluded_runtime_tables' => ['sessions', 'auth_codes'],
    'money_policy' => [
        'target' => 'canonical decimal string in payload; commit tool may convert to Decimal128 only with parity tests',
        'forbidden' => ['JavaScript Number for persisted money'],
    ],
    'manifest' => $manifest,
    'collections' => $collections,
];

$path = payload_write_artifact($payload);
echo "Atlas payload artifact written: {$path}\n";
echo "Collections: " . count($collections) . "\n";
echo "Documents: " . array_sum(array_column($manifest, 'count')) . "\n";
echo "Payload hash: " . payload_hash($manifest) . "\n";
