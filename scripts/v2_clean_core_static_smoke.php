<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$requiredFiles = [
    'app/v2/Support.php',
    'app/v2/InternetReferenceProvider.php',
    'app/v2/LegacyExcelImporter.php',
    'app/v2/Database.php',
    'app/v2/Repository.php',
    'app/v2/Api.php',
    'public/index.php',
    'public/app.php',
    'public/api.php',
    'public/v2.php',
    'public/v2-api.php',
    'public/v2-report.php',
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
    'v2_dictionary_training_decisions',
    'v2_workspace_assistant_settings',
    'v2_internet_reference_lookups',
    'v2_audit_log',
];

$requiredRoutes = [
    '/api/workspaces',
    '/api/workspaces/([a-f0-9-]{36})/flows',
    '/api/workspaces/([a-f0-9-]{36})/summary',
    '/api/workspaces/([a-f0-9-]{36})/reports/monthly',
    '/api/workspaces/([a-f0-9-]{36})/reports/layer1-summary',
    '/api/workspaces/([a-f0-9-]{36})/reports/category-matrix',
    '/api/workspaces/([a-f0-9-]{36})/reports/other-review',
    '/api/workspaces/([a-f0-9-]{36})/dictionary-review-queue',
    '/api/workspaces/([a-f0-9-]{36})/dictionary-training-decisions',
    '/api/workspaces/([a-f0-9-]{36})/assistant-settings',
    '/api/workspaces/([a-f0-9-]{36})/dictionary-training-internet-reference',
    '/api/workspaces/([a-f0-9-]{36})/dictionary-training-internet-reference/lookups',
    '/api/workspaces/([a-f0-9-]{36})/dictionary-training-internet-reference/lookups/([a-f0-9-]{36})',
    '/api/workspaces/([a-f0-9-]{36})/months/([0-9]{4})/([0-9]{1,2})/close',
    '/api/workspaces/([a-f0-9-]{36})/months/([0-9]{4})/([0-9]{1,2})/reopen',
    '/api/workspaces/([a-f0-9-]{36})/months/([0-9]{4})/([0-9]{1,2})/correction',
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

$publicIndex = file_get_contents($root . '/public/index.php') ?: '';
$publicApp = file_get_contents($root . '/public/app.php') ?: '';
$publicApi = file_get_contents($root . '/public/api.php') ?: '';

if (!str_contains($publicIndex, "require __DIR__ . '/v2.php'")) {
    $failures[] = 'public/index.php is not a v2 canonical entrypoint';
}
if (!str_contains($publicApp, "Location: /")) {
    $failures[] = 'public/app.php does not redirect to canonical root';
}
foreach (['request_code', 'verify_code', 'current_user', 'logout'] as $authAction) {
    if (!str_contains($publicApi, $authAction)) {
        $failures[] = "Auth bridge missing action: {$authAction}";
    }
}
if (preg_match('/ql_(ledger|group_|message_|company_|client_|proforma_|on_the_go|advance_|findesk_|yacht_|ai_)/', $publicApi) === 1) {
    $failures[] = 'public/api.php still exposes decommissioned v1 product actions';
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

foreach (['v2_workspaces', 'v2_flows', 'v2_entries', 'v2_categories', 'v2_attachments', 'v2_internet_reference_lookups', 'v2_audit_log', 'storage/v2/attachments'] as $marker) {
    if (!str_contains((string)$repo, $marker)) {
        $failures[] = "Repository does not reference clean table: {$marker}";
    }
}

$providerPath = $root . '/app/v2/InternetReferenceProvider.php';
$provider = is_file($providerPath) ? file_get_contents($providerPath) : '';
foreach ([
    'FINDESK_V2_MR_SMITH_ALLOWLIST_ENABLED',
    'FINDESK_V2_MR_SMITH_ALLOWED_DOMAINS',
    'FinDeskV2AllowlistedHttpInternetReferenceProvider',
    'MAX_BYTES',
    'TIMEOUT_SECONDS',
    'follow_location',
    'candidate_url_not_allowlisted',
    'unsafe_candidate_url',
    'FILTER_FLAG_NO_PRIV_RANGE',
    'FILTER_FLAG_NO_RES_RANGE',
] as $providerMarker) {
    if (!str_contains((string)$provider, $providerMarker)) {
        $failures[] = "Internet reference provider missing safety marker: {$providerMarker}";
    }
}
if (preg_match('/curl_|fsockopen|stream_socket_client|Guzzle|HttpClient/i', (string)$provider) === 1) {
    $failures[] = 'Internet reference provider contains unapproved network client markers';
}
if (preg_match('/file_get_contents\s*\(\s*[\'"]https?:/i', (string)$provider) === 1) {
    $failures[] = 'Internet reference provider contains literal remote fetch';
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
