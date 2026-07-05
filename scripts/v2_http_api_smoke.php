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

function smokeDb(): PDO
{
    $socket = (string)getenv('FINDESK_V2_HTTP_SOCKET');
    $dbName = (string)getenv('FINDESK_V2_HTTP_DB');
    smokeAssert($socket !== '', 'Missing FINDESK_V2_HTTP_SOCKET');
    smokeAssert($dbName !== '', 'Missing FINDESK_V2_HTTP_DB');

    return new PDO('mysql:unix_socket=' . $socket . ';dbname=' . $dbName . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function smokeCloseMonth(string $workspaceId, int $year, int $month): void
{
    $stmt = smokeDb()->prepare("
        INSERT INTO v2_monthly_closures (id, workspace_id, year, month, is_closed, closed_by, closed_at)
        VALUES ('00000000-0000-4000-8000-000000000409', ?, ?, ?, 1, 19001, NOW())
        ON DUPLICATE KEY UPDATE is_closed = 1, closed_by = VALUES(closed_by), closed_at = VALUES(closed_at)
    ");
    $stmt->execute([$workspaceId, $year, $month]);
}

function smokeAuditCount(string $action, string $entryId): int
{
    $stmt = smokeDb()->prepare("SELECT COUNT(*) FROM v2_audit_log WHERE action = ? AND entity_id = ?");
    $stmt->execute([$action, $entryId]);

    return (int)$stmt->fetchColumn();
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
$cardFlow = null;
foreach ($flows as $flow) {
    if (($flow['type'] ?? '') === 'cash') {
        $cashFlow = $flow;
    }
    if (($flow['type'] ?? '') === 'card') {
        $cardFlow = $flow;
    }
}
smokeAssert(is_array($cashFlow), 'cash flow missing');
smokeAssert(is_array($cardFlow), 'card flow missing');

$summaryBefore = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary before entries')['summary'];
smokeAssert((float)$summaryBefore['card_expense_total'] === 0.0, 'initial card expense total mismatch');

$categories = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/categories"), 'list categories')['categories'];
smokeAssert(count($categories) === 16, 'seeded category count should be 16');

$entry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-60 Netflix',
]), 'create entry')['entry'];
smokeAssert($entry['entry_type'] === 'cash_expense', 'entry type mismatch');
smokeAssert((float)$entry['amount'] === 60.0, 'entry amount mismatch');

$cardEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cardFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-25 card smoke',
]), 'create card entry')['entry'];
smokeAssert($cardEntry['entry_type'] === 'card_expense', 'card entry type mismatch');
smokeAssert($cardEntry['balance_after'] === null, 'card entry should not have balance_after');

$summaryAfterCard = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary after card entry')['summary'];
smokeAssert((float)$summaryAfterCard['card_expense_total'] === 25.0, 'card expense total after card entry mismatch');

$otherEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-180 какая-то штука',
]), 'create other expense')['entry'];
smokeAssert($otherEntry['status'] === 'other_review', 'other expense status mismatch');
smokeAssert($otherEntry['category_code'] === 'other', 'other expense category mismatch');

$otherQueue = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/other-expenses"), 'other expenses queue')['entries'];
smokeAssert(count($otherQueue) === 1, 'other expenses queue count mismatch');
smokeAssert((string)$otherQueue[0]['id'] === (string)$otherEntry['id'], 'other expenses queue entry mismatch');

$resolvedOther = expectOk(smokeRequest('PATCH', '/api/entries/' . $otherEntry['id'] . '/category', [
    'category_code' => 'tech_parts',
]), 'resolve other expense category')['entry'];
smokeAssert($resolvedOther['category_code'] === 'tech_parts', 'resolved other category mismatch');
smokeAssert($resolvedOther['status'] === 'recognized', 'resolved other status mismatch');
$otherQueueAfterResolve = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/other-expenses"), 'other expenses queue after resolve')['entries'];
smokeAssert(count($otherQueueAfterResolve) === 0, 'other expenses queue should be empty after category correction');

$preview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+42 preview',
]), 'parse preview')['preview'];
smokeAssert($preview['will_save'] === false, 'preview must not save');
smokeAssert($preview['entry_type'] === 'cash_income', 'preview entry type mismatch');

$entriesAfterPreview = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/entries"), 'list entries after preview')['entries'];
smokeAssert(count($entriesAfterPreview) === 3, 'parse preview persisted an entry');

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
$remainingIds = array_map(static fn (array $entry): string => (string)$entry['id'], $entriesAfterDelete);
smokeAssert(!in_array((string)$entry['id'], $remainingIds, true), 'deleted cash entry is still visible');
smokeAssert(in_array((string)$cardEntry['id'], $remainingIds, true), 'card entry disappeared unexpectedly');

$closedEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-33 Netflix closed month',
]), 'create closed-month probe entry')['entry'];
smokeAssert($closedEntry['category_code'] === 'media_comms', 'closed probe initial category mismatch');
smokeCloseMonth($workspaceId, 2026, 7);
expectError(smokeRequest('PATCH', '/api/entries/' . $closedEntry['id'] . '/category', [
    'category_code' => 'fuel',
]), 409, 'closed_month_requires_decision', 'closed month category patch');
expectError(smokeRequest('DELETE', '/api/entries/' . $closedEntry['id']), 409, 'closed_month_requires_decision', 'closed month delete');
$cancelDecision = expectOk(smokeRequest('POST', '/api/entries/' . $closedEntry['id'] . '/category/closed-month-decision', [
    'decision' => 'cancel',
    'category_code' => 'fuel',
]), 'closed month category cancel');
smokeAssert($cancelDecision['decision'] === 'cancel', 'cancel decision mismatch');
smokeAssert($cancelDecision['changed'] === false, 'cancel should not mutate entry');
smokeAssert($cancelDecision['entry']['category_code'] === 'media_comms', 'cancel changed category');
smokeAssert(smokeAuditCount('closed_month_category_cancel', $closedEntry['id']) >= 1, 'cancel audit missing');

$correctionDecision = expectOk(smokeRequest('POST', '/api/entries/' . $closedEntry['id'] . '/category/closed-month-decision', [
    'decision' => 'create_correction',
    'category_code' => 'fuel',
    'reason' => 'smoke correction request',
]), 'closed month category create correction');
smokeAssert($correctionDecision['decision'] === 'create_correction', 'create correction decision mismatch');
smokeAssert($correctionDecision['changed'] === false, 'create correction should not mutate category-only entry');
smokeAssert(($correctionDecision['requires_followup'] ?? null) === true, 'create correction should declare follow-up required');
smokeAssert($correctionDecision['entry']['category_code'] === 'media_comms', 'create correction changed original category');
smokeAssert(smokeAuditCount('closed_month_category_correction_requested', $closedEntry['id']) >= 1, 'create correction audit missing');

$recalculateDecision = expectOk(smokeRequest('POST', '/api/entries/' . $closedEntry['id'] . '/category/closed-month-decision', [
    'decision' => 'recalculate_chain',
    'category_code' => 'fuel',
]), 'closed month category recalculate chain');
smokeAssert($recalculateDecision['decision'] === 'recalculate_chain', 'recalculate decision mismatch');
smokeAssert($recalculateDecision['changed'] === true, 'recalculate should mutate explicit category choice');
smokeAssert($recalculateDecision['entry']['category_code'] === 'fuel', 'recalculate did not change category');
smokeAssert((float)$recalculateDecision['entry']['amount'] === 33.0, 'recalculate changed amount');
smokeAssert($recalculateDecision['entry']['raw_text'] === '-33 Netflix closed month', 'recalculate changed raw_text');
smokeAssert(smokeAuditCount('closed_month_category_recalculate', $closedEntry['id']) >= 1, 'recalculate audit missing');

$entriesAfterClosedRejection = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/entries"), 'list entries after closed-month rejection')['entries'];
$closedAfterRejection = null;
foreach ($entriesAfterClosedRejection as $candidate) {
    if ((string)$candidate['id'] === (string)$closedEntry['id']) {
        $closedAfterRejection = $candidate;
        break;
    }
}
smokeAssert(is_array($closedAfterRejection), 'closed probe disappeared after rejected mutation');
smokeAssert($closedAfterRejection['category_code'] === 'fuel', 'closed probe category did not reflect explicit recalculate decision');

echo "FinDesk v2 HTTP API smoke: OK\n";
echo "Workspace: {$workspaceId}\n";
echo "Flows: " . count($flows) . "\n";
echo "Categories: " . count($categories) . "\n";
