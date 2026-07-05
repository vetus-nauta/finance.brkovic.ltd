<?php

declare(strict_types=1);

final class HttpSmokeResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $json,
        public readonly string $raw
    ) {
    }
}

function smokeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function smokeRequest(string $method, string $route, ?array $body = null, bool $authenticated = true): HttpSmokeResponse
{
    $base = rtrim((string)getenv('FINDESK_V2_HTTP_BASE'), '/');
    $cookieName = (string)getenv('FINDESK_V2_HTTP_COOKIE');
    $token = (string)getenv('FINDESK_V2_HTTP_TOKEN');

    smokeAssert($base !== '', 'Missing FINDESK_V2_HTTP_BASE');
    smokeAssert($cookieName !== '', 'Missing FINDESK_V2_HTTP_COOKIE');
    smokeAssert($token !== '', 'Missing FINDESK_V2_HTTP_TOKEN');

    $headers = ['Content-Type: application/json'];
    if ($authenticated) {
        $headers[] = 'Cookie: ' . $cookieName . '=' . $token;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $raw = file_get_contents($base . '/v2-api.php?route=' . rawurlencode($route), false, $context);
    smokeAssert($raw !== false, "HTTP request failed: {$method} {$route}");

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
            $status = (int)$match[1];
            break;
        }
    }

    $json = json_decode($raw, true);
    smokeAssert(is_array($json), "Invalid JSON for {$method} {$route}: {$raw}");

    return new HttpSmokeResponse($status, $json, $raw);
}

function expectOk(HttpSmokeResponse $response, string $label): array
{
    smokeAssert($response->status === 200, "{$label}: expected HTTP 200, got {$response->status}; {$response->raw}");
    smokeAssert(($response->json['ok'] ?? null) === true, "{$label}: expected ok=true; {$response->raw}");

    return $response->json;
}

function expectError(HttpSmokeResponse $response, int $status, string $error, string $label): void
{
    smokeAssert($response->status === $status, "{$label}: expected HTTP {$status}, got {$response->status}; {$response->raw}");
    smokeAssert(($response->json['ok'] ?? null) === false, "{$label}: expected ok=false; {$response->raw}");
    smokeAssert(($response->json['error'] ?? null) === $error, "{$label}: expected error {$error}; {$response->raw}");
}

expectError(smokeRequest('GET', '/api/workspaces', null, false), 401, 'not_authenticated', 'unauthenticated workspace list');

$createdWorkspace = expectOk(smokeRequest('POST', '/api/workspaces', [
    'name' => 'HTTP Smoke Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'ru',
]), 'create workspace')['workspace'];
$workspaceId = (string)$createdWorkspace['id'];
smokeAssert($workspaceId !== '', 'created workspace id is empty');

$workspaces = expectOk(smokeRequest('GET', '/api/workspaces'), 'list workspaces')['workspaces'];
smokeAssert(count($workspaces) === 1, 'workspace list should contain one workspace');
smokeAssert((string)$workspaces[0]['id'] === $workspaceId, 'workspace list id mismatch');

$flows = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/flows"), 'list flows')['flows'];
smokeAssert(count($flows) === 2, 'default flow count should be 2');
$cashFlow = null;
foreach ($flows as $flow) {
    if (($flow['type'] ?? '') === 'cash') {
        $cashFlow = $flow;
        break;
    }
}
smokeAssert(is_array($cashFlow), 'cash flow missing');

$categories = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/categories"), 'list categories')['categories'];
smokeAssert(count($categories) === 16, 'seeded category count should be 16');

$entry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-60 Netflix',
]), 'create entry')['entry'];
smokeAssert($entry['entry_type'] === 'cash_expense', 'entry type mismatch');
smokeAssert((float)$entry['amount'] === 60.0, 'entry amount mismatch');

$preview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+42 preview',
]), 'parse preview')['preview'];
smokeAssert($preview['will_save'] === false, 'preview must not save');
smokeAssert($preview['entry_type'] === 'cash_income', 'preview entry type mismatch');

$entriesAfterPreview = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/entries"), 'list entries after preview')['entries'];
smokeAssert(count($entriesAfterPreview) === 1, 'parse preview persisted an entry');

$patched = expectOk(smokeRequest('PATCH', '/api/entries/' . $entry['id'] . '/category', [
    'category_code' => 'media_comms',
]), 'patch entry category')['entry'];
smokeAssert($patched['category_code'] === 'media_comms', 'patched category mismatch');

$rule = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/category-rules", [
    'category_code' => 'media_comms',
    'pattern' => 'netflix',
    'weight' => 20,
]), 'create category rule')['category_rule'];
smokeAssert($rule['pattern'] === 'netflix', 'category rule pattern mismatch');

expectOk(smokeRequest('DELETE', '/api/entries/' . $entry['id']), 'delete entry');
$entriesAfterDelete = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/entries"), 'list entries after delete')['entries'];
smokeAssert(count($entriesAfterDelete) === 0, 'deleted entry is still visible');

echo "FinDesk v2 HTTP API smoke: OK\n";
echo "Workspace: {$workspaceId}\n";
echo "Flows: " . count($flows) . "\n";
echo "Categories: " . count($categories) . "\n";
