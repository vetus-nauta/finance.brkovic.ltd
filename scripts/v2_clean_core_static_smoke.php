<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/v2/Support.php',
    'app/v2/LegacyExcelImporter.php',
    'app/v2/Database.php',
    'app/v2/Repository.php',
    'app/v2/Api.php',
    'public/v2-api.php',
    'FinDesk v2.0/sql/001-clean-core-mariadb.sql',
];

$requiredTables = [
    'v2_workspaces',
    'v2_workspace_members',
    'v2_flows',
    'v2_entries',
    'v2_categories',
    'v2_category_rules',
    'v2_actors',
    'v2_attachments',
    'v2_monthly_closures',
    'v2_import_sources',
    'v2_import_rows',
    'v2_audit_log',
];

$requiredRoutes = [
    '/api/workspaces',
    '/api/workspaces/([a-f0-9-]{36})/flows',
    '/api/workspaces/([a-f0-9-]{36})/summary',
    '/api/workspaces/([a-f0-9-]{36})/reports/monthly',
    '/api/workspaces/([a-f0-9-]{36})/reports/category-matrix',
    '/api/workspaces/([a-f0-9-]{36})/reports/other-review',
    '/api/workspaces/([a-f0-9-]{36})/imports/excel',
    '/api/workspaces/([a-f0-9-]{36})/imports/([a-f0-9-]{36})/review',
    '/api/workspaces/([a-f0-9-]{36})/imports/([a-f0-9-]{36})/accept',
    '/api/workspaces/([a-f0-9-]{36})/other-expenses',
    '/api/workspaces/([a-f0-9-]{36})/entries',
    '/api/workspaces/([a-f0-9-]{36})/parse-preview',
    '/api/parse-entry-preview',
    '/api/entries/([a-f0-9-]{36})',
    '/api/entries/([a-f0-9-]{36})/attachments',
    '/api/attachments/([a-f0-9-]{36})',
    '/api/entries/([a-f0-9-]{36})/category',
    '/api/workspaces/([a-f0-9-]{36})/categories',
    '/api/workspaces/([a-f0-9-]{36})/category-rules',
];

$failures = [];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing file: {$file}";
    }
}

$sqlPath = $root . '/FinDesk v2.0/sql/001-clean-core-mariadb.sql';
$sql = is_file($sqlPath) ? file_get_contents($sqlPath) : '';

foreach ($requiredTables as $table) {
    if (!str_contains((string)$sql, "CREATE TABLE IF NOT EXISTS {$table}")) {
        $failures[] = "Missing table in SQL: {$table}";
    }
}

$apiPath = $root . '/app/v2/Api.php';
$api = is_file($apiPath) ? file_get_contents($apiPath) : '';

foreach ($requiredRoutes as $route) {
    if (!str_contains((string)$api, $route)) {
        $failures[] = "Missing API route marker: {$route}";
    }
}

$repoPath = $root . '/app/v2/Repository.php';
$repo = is_file($repoPath) ? file_get_contents($repoPath) : '';

foreach (['v2_workspaces', 'v2_flows', 'v2_entries', 'v2_categories', 'v2_attachments', 'v2_audit_log', 'storage/v2/attachments'] as $marker) {
    if (!str_contains((string)$repo, $marker)) {
        $failures[] = "Repository does not reference clean table: {$marker}";
    }
}

if ($failures) {
    echo "FinDesk v2 clean core static smoke: FAIL\n";
    foreach ($failures as $failure) {
        echo "- {$failure}\n";
    }
    exit(1);
}

echo "FinDesk v2 clean core static smoke: OK\n";
echo "Files: " . count($requiredFiles) . "\n";
echo "Tables: " . count($requiredTables) . "\n";
echo "Route markers: " . count($requiredRoutes) . "\n";
