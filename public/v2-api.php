<?php

declare(strict_types=1);

$apiPath = is_file(__DIR__ . '/../app/v2/Api.php') ? __DIR__ . '/../app/v2/Api.php' : __DIR__ . '/app/v2/Api.php';
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '')));
$shouldRefreshOpcache = getenv('FINDESK_V2_RESET_OPCACHE_PER_REQUEST') === '1'
    || str_starts_with($host, '127.0.0.1')
    || str_starts_with($host, 'localhost');
if ($shouldRefreshOpcache && function_exists('opcache_invalidate')) {
    opcache_invalidate($apiPath, true);
    opcache_invalidate(dirname($apiPath) . '/Repository.php', true);
}
if ($shouldRefreshOpcache && function_exists('opcache_reset')) {
    opcache_reset();
}
require_once $apiPath;

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$route = (string)($_GET['route'] ?? ($_SERVER['PATH_INFO'] ?? ''));

if (in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
    $requestHeader = trim((string)($_SERVER['HTTP_X_FINDESK_V2_REQUEST'] ?? ''));
    if ($requestHeader !== 'fetch') {
        ql_json(['ok' => false, 'error' => 'csrf_required'], 403);
    }
}

if ($route === '') {
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $marker = '/v2-api.php';
    $position = strpos($path, $marker);
    $route = $position === false ? '/api' : substr($path, $position + strlen($marker));
}

$contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$input = str_contains(strtolower($contentType), 'multipart/form-data') ? $_POST : ql_input();
if (isset($_FILES['file']) && is_array($_FILES['file'])) {
    $input['file'] = $_FILES['file'];
}

try {
    $normalizedRoute = FinDeskV2Support::normalizeRoute($route);
    findesk_v2_proxy_atlas_runtime($method, $normalizedRoute, $_GET, $input, $contentType, $host);
    if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/raw-history/convert$#i', $normalizedRoute, $match) === 1 && $method === 'POST') {
        $user = ql_current_user();
        if (!$user) {
            throw new FinDeskV2HttpError(401, 'not_authenticated');
        }
        $repo = new FinDeskV2Repository(FinDeskV2Database::pdo());
        try {
            ql_json(['ok' => true, 'conversion' => $repo->convertRawHistoryBatch($match[1], $input, (int)$user['id'])]);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    $api = new FinDeskV2Api();
    $response = $api->handle($method, $route, $input, $_GET);
    findesk_v2_shadow_atlas_read($method, $normalizedRoute, $_GET, $response);
    ql_json($response);
} catch (FinDeskV2HttpError $e) {
    $payload = json_decode($e->getMessage(), true);
    if (!is_array($payload)) {
        $payload = ['error' => $e->getMessage()];
    }
    ql_json(array_merge(['ok' => false], $payload), $e->status);
} catch (Throwable $e) {
    ql_json(['ok' => false, 'error' => 'v2_internal_error'], 500);
}

function findesk_v2_proxy_atlas_runtime(
    string $method,
    string $route,
    array $query,
    array $input,
    string $contentType,
    string $host
): void {
    $runtime = (string)(getenv('FINDESK_V2_RUNTIME') ?: getenv('FINDESK_V2_RUNTIME_MODE') ?: 'mysql');
    if (!in_array($runtime, ['atlas_read', 'atlas_write'], true)) {
        return;
    }

    $localOnly = (string)(getenv('FINDESK_V2_ATLAS_PROXY_LOCAL_ONLY') ?: '1');
    $isLocalHost = str_starts_with($host, '127.0.0.1')
        || str_starts_with($host, 'localhost')
        || $host === '';
    if ($localOnly !== '0' && !$isLocalHost) {
        ql_json(['ok' => false, 'error' => 'atlas_proxy_local_only'], 503);
    }

    if ($runtime === 'atlas_read' && $method !== 'GET') {
        ql_json(['ok' => false, 'error' => 'atlas_runtime_write_not_enabled'], 405);
    }

    $payload = $method === 'GET' ? [] : findesk_v2_atlas_proxy_payload($input, $contentType);
    $response = findesk_v2_atlas_proxy_request($method, $route, $query, $payload);
    ql_json($response['payload'], $response['status']);
}

function findesk_v2_atlas_proxy_payload(array $input, string $contentType): array
{
    if (str_contains(strtolower($contentType), 'multipart/form-data')
        && isset($input['file'])
        && is_array($input['file'])
    ) {
        $file = $input['file'];
        $tmpName = (string)($file['tmp_name'] ?? '');
        $error = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($error === UPLOAD_ERR_OK && $tmpName !== '' && is_file($tmpName)) {
            $input['file_name'] = (string)($file['name'] ?? 'attachment');
            $input['content_base64'] = base64_encode((string)file_get_contents($tmpName));
        }
        unset($input['file']);
    }

    return $input;
}

function findesk_v2_atlas_proxy_request(string $method, string $route, array $query, array $payload): array
{
    $baseUrl = rtrim((string)(getenv('FINDESK_V2_ATLAS_READ_URL') ?: getenv('FINDESK_V2_ATLAS_READ_BASE_URL') ?: 'http://127.0.0.1:18965'), '/');
    if ($baseUrl === '') {
        return [
            'status' => 503,
            'payload' => ['ok' => false, 'error' => 'atlas_proxy_base_url_missing'],
        ];
    }

    $proxyQuery = $query;
    unset($proxyQuery['route']);
    $proxyQuery = array_merge(['route' => $route], $proxyQuery);
    $url = $baseUrl . '/api?' . http_build_query($proxyQuery);
    $body = $method === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        return [
            'status' => 500,
            'payload' => ['ok' => false, 'error' => 'atlas_proxy_encode_failed'],
        ];
    }

    $timeoutMs = max(100, min(120000, (int)(getenv('FINDESK_V2_ATLAS_PROXY_TIMEOUT_MS') ?: 3000)));
    $headers = "Accept: application/json\r\nX-FinDesk-V2-Proxy: atlas\r\n";
    if ($method !== 'GET') {
        $headers .= "Content-Type: application/json\r\n";
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => $timeoutMs / 1000,
            'ignore_errors' => true,
            'header' => $headers,
            'content' => $body,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    $status = findesk_v2_atlas_proxy_status($http_response_header ?? []);
    if (!is_string($raw) || $raw === '') {
        return [
            'status' => 502,
            'payload' => ['ok' => false, 'error' => 'atlas_proxy_empty_response'],
        ];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [
            'status' => 502,
            'payload' => ['ok' => false, 'error' => 'atlas_proxy_invalid_json'],
        ];
    }

    return [
        'status' => $status,
        'payload' => $decoded,
    ];
}

function findesk_v2_atlas_proxy_status(array $headers): int
{
    $status = 200;
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+([0-9]{3})#', (string)$header, $match) === 1) {
            $status = (int)$match[1];
        }
    }
    return max(100, min(599, $status));
}

function findesk_v2_shadow_atlas_read(string $method, string $route, array $query, array $mysqlResponse): void
{
    $runtime = (string)(getenv('FINDESK_V2_RUNTIME') ?: getenv('FINDESK_V2_RUNTIME_MODE') ?: 'mysql');
    if ($runtime !== 'atlas_shadow') {
        return;
    }
    if ($method !== 'GET' || !findesk_v2_atlas_shadow_supported($route)) {
        return;
    }

    $baseUrl = rtrim((string)(getenv('FINDESK_V2_ATLAS_READ_URL') ?: getenv('FINDESK_V2_ATLAS_READ_BASE_URL') ?: 'http://127.0.0.1:18965'), '/');
    if ($baseUrl === '') {
        return;
    }

    $shadowQuery = $query;
    unset($shadowQuery['route']);
    $shadowQuery = array_merge(['route' => $route], $shadowQuery);
    $url = $baseUrl . '/api?' . http_build_query($shadowQuery);
    $startedAt = microtime(true);
    $atlasPayload = null;
    $status = 'error';
    $error = null;

    try {
        $timeoutMs = max(50, min(1500, (int)(getenv('FINDESK_V2_ATLAS_SHADOW_TIMEOUT_MS') ?: 300)));
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutMs / 1000,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nX-FinDesk-V2-Shadow: atlas\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            $error = 'atlas_shadow_empty_response';
        } else {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $error = 'atlas_shadow_invalid_json';
            } else {
                $atlasPayload = $decoded;
                $status = 'ok';
            }
        }
    } catch (Throwable $e) {
        $error = 'atlas_shadow_request_failed';
    }

    $mysqlHash = findesk_v2_payload_hash($mysqlResponse);
    $atlasHash = is_array($atlasPayload) ? findesk_v2_payload_hash($atlasPayload) : null;
    $routeInfo = findesk_v2_route_log_info($route);
    findesk_v2_shadow_log([
        'checked_at' => gmdate('c'),
        'mode' => $runtime,
        'method' => $method,
        'route_template' => $routeInfo['template'],
        'workspace_hash' => $routeInfo['workspace_hash'],
        'query_keys' => array_values(array_diff(array_keys($query), ['route'])),
        'status' => $status,
        'match' => $atlasHash !== null && hash_equals($mysqlHash, $atlasHash),
        'mysql_digest' => substr($mysqlHash, 0, 16),
        'atlas_digest' => is_string($atlasHash) ? substr($atlasHash, 0, 16) : null,
        'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
        'error' => $error,
    ]);
}

function findesk_v2_atlas_shadow_supported(string $route): bool
{
    $patterns = [
        '#^/api/workspaces$#',
        '#^/api/workspaces/[a-f0-9-]{36}$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/flows$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/invites$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/employee-mode$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/accountable-dashboard$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/accountable-offers$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/accountable-reports$#i',
        '#^/api/accountable-reports/[a-f0-9-]{36}$#i',
        '#^/api/accountable-reports/[a-f0-9-]{36}/materialization$#i',
        '#^/api/workspace-invites/[a-f0-9]{48}$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/categories$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/entries$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/summary$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/monthly$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/layer1-summary$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/layer1-source-entries$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/layer1-snapshots$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/other-expenses$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/batches$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/batches/[a-f0-9-]{36}$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/operational-fragments$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/operational-fragments/[a-f0-9-]{36}$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/operational-fragments/[a-f0-9-]{36}/html-snapshots$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/operational-fragments/[a-f0-9-]{36}/html-snapshots/[a-f0-9-]{36}$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/operational-packages$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/operational-packages/[a-f0-9-]{36}$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/category-matrix$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/reports/other-review$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/dictionary-review-queue$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/raw-history$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/dictionary-training-decisions$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/assistant-settings$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/dictionary-training-internet-reference/lookups$#i',
        '#^/api/workspaces/[a-f0-9-]{36}/imports/[a-f0-9-]{36}/review$#i',
        '#^/api/entries/[a-f0-9-]{36}/attachments$#i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $route) === 1) {
            return true;
        }
    }
    return false;
}

function findesk_v2_payload_hash(array $payload): string
{
    return hash('sha256', json_encode(findesk_v2_canonicalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function findesk_v2_route_log_info(string $route): array
{
    $workspaceHash = null;
    $template = preg_replace_callback(
        '#/api/workspaces/([a-f0-9-]{36})#i',
        static function (array $match) use (&$workspaceHash): string {
            $workspaceHash = substr(hash('sha256', strtolower($match[1])), 0, 16);
            return '/api/workspaces/:workspaceId';
        },
        $route
    );
    $template = preg_replace('#/[a-f0-9-]{36}(?=$|/)#i', '/:id', (string)$template);
    return [
        'template' => $template,
        'workspace_hash' => $workspaceHash,
    ];
}

function findesk_v2_canonicalize(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (findesk_v2_array_is_list($value)) {
        return array_map('findesk_v2_canonicalize', $value);
    }
    ksort($value);
    foreach ($value as $key => $item) {
        $value[$key] = findesk_v2_canonicalize($item);
    }
    return $value;
}

function findesk_v2_array_is_list(array $value): bool
{
    $index = 0;
    foreach ($value as $key => $_) {
        if ($key !== $index) {
            return false;
        }
        $index++;
    }
    return true;
}

function findesk_v2_shadow_log(array $record): void
{
    $logFile = (string)(getenv('FINDESK_V2_ATLAS_LOG_FILE') ?: dirname(__DIR__) . '/storage/logs/v2-atlas-routing.jsonl');
    if (!str_starts_with($logFile, '/')) {
        $logFile = dirname(__DIR__) . '/' . ltrim($logFile, '/');
    }
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }
    @file_put_contents(
        $logFile,
        json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
