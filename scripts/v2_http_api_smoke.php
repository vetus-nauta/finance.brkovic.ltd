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

function smokeAssertAmount($actual, float $expected, string $message): void
{
    smokeAssert($actual !== null, "{$message}: expected {$expected}, got null");
    smokeAssert(abs((float)$actual - $expected) < 0.001, "{$message}: expected {$expected}, got {$actual}");
}

function smokeAssertEntryId(array $ids, string $entryId, string $message): void
{
    smokeAssert(in_array($entryId, array_map('strval', $ids), true), $message);
}

function smokeAssertNoEntryId(array $ids, string $entryId, string $message): void
{
    smokeAssert(!in_array($entryId, array_map('strval', $ids), true), $message);
}

function smokeSemanticMarkerHas(array $entry, string $marker, ?string $key = null, ?string $value = null): bool
{
    foreach ($entry['semantic_markers'] ?? [] as $item) {
        if (!is_array($item) || ($item['marker'] ?? null) !== $marker) {
            continue;
        }
        if ($key === null) {
            return true;
        }
        if (($item[$key] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

function smokeExampleMarkerHas(array $example, string $marker): bool
{
    foreach ($example['semantic_markers'] ?? [] as $item) {
        if (is_array($item) && ($item['marker'] ?? null) === $marker) {
            return true;
        }
    }

    return false;
}

function smokeSignalHas(array $entry, string $key, string $value): bool
{
    foreach ($entry['matched_signals'] ?? [] as $signal) {
        if (is_array($signal) && ($signal[$key] ?? null) === $value) {
            return true;
        }
    }

    return false;
}

function smokeBlockerHas(array $entry, string $blocker): bool
{
    return in_array($blocker, $entry['blockers'] ?? [], true);
}

function smokeXml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function smokeColumnName(int $index): string
{
    $name = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = intdiv($index - $mod, 26);
    }

    return $name;
}

function smokeCreateXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'findesk-v2-import-') . '.xlsx';
    $zip = new ZipArchive();
    smokeAssert($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'could not create xlsx fixture');

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="July" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');

    $sheet = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $rowIndex => $row) {
        $number = $rowIndex + 1;
        $sheet .= '<row r="' . $number . '">';
        foreach ($row as $columnIndex => $value) {
            if ((string)$value === '') {
                continue;
            }
            $ref = smokeColumnName($columnIndex) . $number;
            $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . smokeXml((string)$value) . '</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    return $path;
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
        VALUES (UUID(), ?, ?, ?, 1, 19001, NOW())
        ON DUPLICATE KEY UPDATE is_closed = 1, closed_by = VALUES(closed_by), closed_at = VALUES(closed_at)
    ");
    $stmt->execute([$workspaceId, $year, $month]);
}

function smokeCloseMonthWithComment(string $workspaceId, int $year, int $month, string $comment): void
{
    smokeCloseMonth($workspaceId, $year, $month);
    $stmt = smokeDb()->prepare("
        UPDATE v2_monthly_closures
        SET comment = ?
        WHERE workspace_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$comment, $workspaceId, $year, $month]);
}

function smokeAddWorkspaceMember(string $workspaceId, int $userId, string $role, string $accessScope = 'workspace', ?string $assignedActorId = null): void
{
    $stmt = smokeDb()->prepare("
        INSERT INTO v2_workspace_members (id, workspace_id, user_id, role, access_scope, assigned_actor_id)
        VALUES (UUID(), ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$workspaceId, $userId, $role, $accessScope, $assignedActorId]);
}

function smokeInsertEntryForUser(string $workspaceId, string $flowId, int $userId, string $rawText, string $amount): string
{
    $id = bin2hex(random_bytes(16));
    $id = substr($id, 0, 8) . '-' . substr($id, 8, 4) . '-' . substr($id, 12, 4) . '-' . substr($id, 16, 4) . '-' . substr($id, 20);
    $stmt = smokeDb()->prepare("
        INSERT INTO v2_entries (
            id, workspace_id, flow_id, created_by, date, raw_text, sign, amount,
            direction, entry_type, status, source_type, matched_rules_json
        )
        VALUES (?, ?, ?, ?, '2026-07-06', ?, '-', ?, 'out', 'cash_expense', 'recognized', 'manual', '[]')
    ");
    $stmt->execute([$id, $workspaceId, $flowId, $userId, $rawText, $amount]);

    return $id;
}

function smokeAuditCount(string $action, string $entryId): int
{
    $stmt = smokeDb()->prepare("SELECT COUNT(*) FROM v2_audit_log WHERE action = ? AND entity_id = ?");
    $stmt->execute([$action, $entryId]);

    return (int)$stmt->fetchColumn();
}

function smokeTableCounts(array $tables): array
{
    $db = smokeDb();
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = (int)$db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }

    return $counts;
}

function smokeStoragePath(string $fileUrl): string
{
    $harness = rtrim((string)getenv('FINDESK_V2_HTTP_HARNESS'), '/');
    smokeAssert($harness !== '', 'Missing FINDESK_V2_HTTP_HARNESS');

    return $harness . '/' . ltrim($fileUrl, '/');
}

function smokeRequest(
    string $method,
    string $route,
    ?array $body = null,
    bool $authenticated = true,
    ?string $tokenOverride = null,
    bool $v2RequestHeader = true
): HttpSmokeResponse
{
    $base = rtrim((string)getenv('FINDESK_V2_HTTP_BASE'), '/');
    $cookieName = (string)getenv('FINDESK_V2_HTTP_COOKIE');
    $token = $tokenOverride ?? (string)getenv('FINDESK_V2_HTTP_TOKEN');

    smokeAssert($base !== '', 'Missing FINDESK_V2_HTTP_BASE');
    smokeAssert($cookieName !== '', 'Missing FINDESK_V2_HTTP_COOKIE');
    smokeAssert($token !== '', 'Missing FINDESK_V2_HTTP_TOKEN');

    $headers = ['Content-Type: application/json'];
    if ($v2RequestHeader && in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
        $headers[] = 'X-FinDesk-V2-Request: fetch';
    }
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

    $routePath = $route;
    $routeQuery = '';
    if (str_contains($route, '?')) {
        [$routePath, $routeQuery] = explode('?', $route, 2);
    }

    $url = $base . '/v2-api.php?route=' . rawurlencode($routePath);
    if ($routeQuery !== '') {
        $url .= '&' . $routeQuery;
    }

    $raw = file_get_contents($url, false, $context);
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

function smokePageRequest(string $path, bool $authenticated = true, ?string $tokenOverride = null): HttpSmokeResponse
{
    $base = rtrim((string)getenv('FINDESK_V2_HTTP_BASE'), '/');
    $cookieName = (string)getenv('FINDESK_V2_HTTP_COOKIE');
    $token = $tokenOverride ?? (string)getenv('FINDESK_V2_HTTP_TOKEN');

    smokeAssert($base !== '', 'Missing FINDESK_V2_HTTP_BASE');
    smokeAssert($cookieName !== '', 'Missing FINDESK_V2_HTTP_COOKIE');
    smokeAssert($token !== '', 'Missing FINDESK_V2_HTTP_TOKEN');

    $headers = [];
    if ($authenticated) {
        $headers[] = 'Cookie: ' . $cookieName . '=' . $token;
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $raw = file_get_contents($base . $path, false, $context);
    smokeAssert($raw !== false, "HTTP page request failed: {$path}");

    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
            $status = (int)$match[1];
            break;
        }
    }

    return new HttpSmokeResponse($status, [], $raw);
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
expectError(smokeRequest('POST', '/api/workspaces', [
    'name' => 'Missing CSRF Header Workspace',
], true, null, false), 403, 'csrf_required', 'mutating request without v2 request header');

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

$viewerToken = (string)getenv('FINDESK_V2_HTTP_VIEWER_TOKEN');
smokeAssert($viewerToken !== '', 'Missing FINDESK_V2_HTTP_VIEWER_TOKEN');
$trashWorkspace = expectOk(smokeRequest('POST', '/api/workspaces', [
    'name' => 'HTTP Smoke Trash Workspace',
    'type' => 'custom',
    'currency' => 'EUR',
    'locale' => 'ru',
]), 'create trash workspace')['workspace'];
$trashWorkspaceId = (string)$trashWorkspace['id'];
smokeAddWorkspaceMember($trashWorkspaceId, 19002, 'viewer');
expectError(smokeRequest('DELETE', "/api/workspaces/{$trashWorkspaceId}", null, true, $viewerToken), 403, 'workspace_admin_required', 'viewer delete workspace blocked');
$deletedTrashWorkspace = expectOk(smokeRequest('DELETE', "/api/workspaces/{$trashWorkspaceId}"), 'delete workspace to trash')['workspace'];
smokeAssert(($deletedTrashWorkspace['archived'] ?? null) === true, 'deleted workspace archived flag mismatch');
smokeAssert(($deletedTrashWorkspace['trash_retention_days'] ?? null) === 60, 'deleted workspace retention mismatch');
expectError(smokeRequest('GET', "/api/workspaces/{$trashWorkspaceId}"), 404, 'workspace_not_found', 'deleted workspace hidden');
$workspacesAfterTrashDelete = expectOk(smokeRequest('GET', '/api/workspaces'), 'list workspaces after workspace delete')['workspaces'];
smokeAssert(count($workspacesAfterTrashDelete) === 1, 'deleted workspace must leave visible list');
smokeAssert((string)$workspacesAfterTrashDelete[0]['id'] === $workspaceId, 'remaining workspace id mismatch after delete');

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
smokeAssert(count($categories) === 22, 'seeded category count should be 22');
smokeAssert(in_array('non_commercial_income', array_map(static fn (array $category): string => (string)$category['code'], $categories), true), 'non-commercial income category missing');
smokeAssert(in_array('transport_expenses', array_map(static fn (array $category): string => (string)$category['code'], $categories), true), 'transport category missing');
smokeAssert(in_array('representation_expenses', array_map(static fn (array $category): string => (string)$category['code'], $categories), true), 'representation category missing');
smokeAssert(in_array('current_boat_expenses', array_map(static fn (array $category): string => (string)$category['code'], $categories), true), 'current boat expense category missing');
smokeAssert(in_array('guest_trip_support', array_map(static fn (array $category): string => (string)$category['code'], $categories), true), 'guest trip support category missing');
smokeAssert(in_array('guest_cash_issued', array_map(static fn (array $category): string => (string)$category['code'], $categories), true), 'guest cash issued category missing');

$entry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-60 Netflix',
]), 'create entry')['entry'];
smokeAssert($entry['entry_type'] === 'cash_expense', 'entry type mismatch');
smokeAssert((float)$entry['amount'] === 60.0, 'entry amount mismatch');

smokeAddWorkspaceMember($workspaceId, 19002, 'viewer');
$viewerWorkspaces = expectOk(smokeRequest('GET', '/api/workspaces', null, true, $viewerToken), 'viewer list workspaces')['workspaces'];
smokeAssert(count($viewerWorkspaces) === 1, 'viewer workspace list count mismatch');
smokeAssert((string)$viewerWorkspaces[0]['id'] === $workspaceId, 'viewer workspace id mismatch');
expectError(smokeRequest('PATCH', "/api/workspaces/{$workspaceId}", [
    'name' => 'Viewer Rename',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer update workspace');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/flows", [
    'name' => 'Viewer Cash',
    'type' => 'cash',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer create flow');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+10 viewer write',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer create entry');
expectError(smokeRequest('PATCH', '/api/entries/' . $entry['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-70 viewer edit',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer update entry');
expectError(smokeRequest('PATCH', '/api/entries/' . $entry['id'] . '/category', [
    'category_code' => 'fuel',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer update entry category');
expectError(smokeRequest('DELETE', '/api/entries/' . $entry['id'], null, true, $viewerToken), 403, 'workspace_read_only', 'viewer delete entry');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/category-rules", [
    'category_code' => 'media_comms',
    'pattern' => 'viewer-netflix',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer create category rule');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/months/2026/7/close", [
    'comment' => 'viewer close',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer close month');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/months/2026/7/correction", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+5 viewer correction',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer create month correction');

$employeeToken = (string)getenv('FINDESK_V2_HTTP_EMPLOYEE_TOKEN');
smokeAssert($employeeToken !== '', 'Missing FINDESK_V2_HTTP_EMPLOYEE_TOKEN');
smokeAddWorkspaceMember($workspaceId, 19003, 'employee', 'own_entries');
$employeeEntryId = smokeInsertEntryForUser($workspaceId, (string)$cashFlow['id'], 19003, '-25 employee lunch', '25.00');
$employeeWorkspaces = expectOk(smokeRequest('GET', '/api/workspaces', null, true, $employeeToken), 'employee list workspaces')['workspaces'];
smokeAssert(count($employeeWorkspaces) === 1, 'employee workspace list count mismatch');
smokeAssert(($employeeWorkspaces[0]['role'] ?? null) === 'employee', 'employee role mismatch');
smokeAssert(($employeeWorkspaces[0]['role_label'] ?? null) === 'Сотрудник', 'employee role label mismatch');
smokeAssert(($employeeWorkspaces[0]['access_scope'] ?? null) === 'own_entries', 'employee access scope mismatch');
smokeAssert(($employeeWorkspaces[0]['can_read_workspace'] ?? null) === false, 'employee must not read full workspace');
smokeAssert(($employeeWorkspaces[0]['can_write'] ?? null) === false, 'employee must not write through full operational API');

$employeeEntries = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/entries", null, true, $employeeToken), 'employee scoped entries')['entries'];
smokeAssert(count($employeeEntries) === 1, 'employee entries should return only own rows');
smokeAssert((string)$employeeEntries[0]['id'] === $employeeEntryId, 'employee scoped entry id mismatch');
smokeAssert((string)$employeeEntries[0]['raw_text'] === '-25 employee lunch', 'employee scoped entry text mismatch');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/flows", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee list flows blocked');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee summary blocked');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/categories", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee categories blocked');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/reports/monthly?year=2026&month=7", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee monthly report blocked');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/reports/layer1-summary?year=2026&month=7", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee layer1 report blocked');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/reports/category-matrix?year=2026", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee category matrix blocked');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/reports/layer1-source-entries?ids={$entry['id']}", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee layer1 source entries blocked');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-10 employee preview',
], true, $employeeToken), 403, 'workspace_read_only', 'employee parse preview blocked');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-10 employee write',
], true, $employeeToken), 403, 'workspace_read_only', 'employee create entry blocked');
expectError(smokeRequest('PATCH', '/api/entries/' . $entry['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-70 employee edit',
], true, $employeeToken), 404, 'entry_not_found', 'employee direct-object owner entry blocked');

$offerCountsBefore = smokeTableCounts(['v2_entries']);
$offerSummaryBefore = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary before accountable offer')['summary'];
$accountableOffer = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/accountable-offers", [
    'employee_user_id' => 19003,
    'amount' => '500.00',
    'currency' => 'EUR',
    'purpose' => 'Smoke accountable offer',
]), 'owner create accountable offer')['offer'];
smokeAssert(($accountableOffer['status'] ?? null) === 'pending_offer', 'accountable offer status mismatch');
smokeAssert(($accountableOffer['employee_user_id'] ?? null) === 19003, 'accountable offer employee mismatch');
smokeAssert(($accountableOffer['no_financial_mutation'] ?? null) === true, 'accountable offer must be marked non-financial');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/accountable-offers", [
    'employee_user_id' => 19003,
    'amount' => '100.00',
    'purpose' => 'Viewer blocked offer',
], true, $viewerToken), 403, 'workspace_admin_required', 'viewer create accountable offer blocked');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/accountable-offers", [
    'employee_user_id' => 19003,
    'amount' => '100.00',
    'purpose' => 'Employee blocked offer',
], true, $employeeToken), 403, 'workspace_admin_required', 'employee create accountable offer blocked');

$ownerOffers = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-offers"), 'owner list accountable offers')['offers'];
smokeAssert(count(array_filter($ownerOffers, static fn (array $offer): bool => (string)($offer['id'] ?? '') === (string)$accountableOffer['id'])) === 1, 'owner offer list should include accountable offer');
$employeeOffers = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-offers", null, true, $employeeToken), 'employee list own accountable offers')['offers'];
smokeAssert(count($employeeOffers) === 1, 'employee should see only own accountable offer');
smokeAssert((string)$employeeOffers[0]['id'] === (string)$accountableOffer['id'], 'employee own offer id mismatch');
$employeeMode = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/employee-mode", null, true, $employeeToken), 'employee workspace simple mode');
smokeAssert(($employeeMode['workspace']['role'] ?? null) === 'employee', 'employee mode role mismatch');
smokeAssert(count($employeeMode['offers'] ?? []) === 1, 'employee mode own offers mismatch');
smokeAssertAmount($employeeMode['summary']['pending_total'] ?? null, 500.0, 'employee mode pending offer total');

$acceptedAccountableOffer = expectOk(smokeRequest('POST', "/api/accountable-offers/{$accountableOffer['id']}/accept", [], true, $employeeToken), 'employee accept accountable offer')['offer'];
smokeAssert(($acceptedAccountableOffer['status'] ?? null) === 'accepted_by_employee', 'accepted accountable offer status mismatch');
smokeAssert(($acceptedAccountableOffer['accepted_by'] ?? null) === 19003, 'accepted accountable offer user mismatch');
expectError(smokeRequest('POST', "/api/accountable-offers/{$accountableOffer['id']}/accept", [], true, $employeeToken), 409, 'accountable_offer_not_pending', 'accepted accountable offer cannot be accepted twice');
$offerCountsAfterAccept = smokeTableCounts(['v2_entries']);
$offerSummaryAfterAccept = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary after accountable offer accept')['summary'];
smokeAssert($offerCountsAfterAccept['v2_entries'] === $offerCountsBefore['v2_entries'], 'accountable offer must not create operational entries');
smokeAssertAmount($offerSummaryAfterAccept['cash_now'], (float)$offerSummaryBefore['cash_now'], 'accountable offer accept must not change cash now');
smokeAssertAmount($offerSummaryAfterAccept['card_expense_total'], (float)$offerSummaryBefore['card_expense_total'], 'accountable offer accept must not change card expense total');

$accountableReportCountsBefore = smokeTableCounts(['v2_entries', 'v2_accountable_reports', 'v2_accountable_report_rows']);
$accountableReportSummaryBefore = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary before accountable report draft')['summary'];
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/accountable-reports", [
    'offer_id' => $accountableOffer['id'],
    'rows' => [[
        'date' => '2026-07-08',
        'description' => 'Owner blocked accountable draft',
        'amount' => '10.00',
    ]],
]), 403, 'employee_scope_required', 'owner cannot create employee accountable draft');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-reports", null, true, $viewerToken), 403, 'workspace_admin_required', 'viewer cannot list accountable reports');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/accountable-reports", [
    'offer_id' => $accountableOffer['id'],
    'title' => 'Smoke accountable expenses',
    'rows' => [[
        'date' => '2026-07-08',
        'description' => 'Taxi to marina',
        'amount' => '35.00',
        'category_code' => 'transport_expenses',
    ]],
], true, $employeeToken, false), 403, 'csrf_required', 'accountable report draft missing csrf');
$accountableDraft = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/accountable-reports", [
    'offer_id' => $accountableOffer['id'],
    'title' => 'Smoke accountable expenses',
    'rows' => [
        [
            'date' => '2026-07-08',
            'description' => 'Taxi to marina',
            'amount' => '35.00',
            'category_code' => 'transport_expenses',
            'notes' => 'receipt pending',
        ],
        [
            'expense_date' => '2026-07-09',
            'description' => 'Provisions for guest trip',
            'amount' => '42.50',
            'category_code' => 'provisions',
        ],
    ],
], true, $employeeToken), 'employee create accountable report draft')['report'];
smokeAssert(($accountableDraft['status'] ?? null) === 'draft', 'accountable draft status mismatch');
smokeAssert(($accountableDraft['offer_id'] ?? null) === $accountableOffer['id'], 'accountable draft offer mismatch');
smokeAssert(($accountableDraft['employee_user_id'] ?? null) === 19003, 'accountable draft employee mismatch');
smokeAssert(($accountableDraft['row_count'] ?? null) === 2, 'accountable draft row count mismatch');
smokeAssertAmount($accountableDraft['total_amount'] ?? null, 77.5, 'accountable draft total');
smokeAssert(($accountableDraft['rows'][0]['description'] ?? null) === 'Taxi to marina', 'accountable draft first row mismatch');
smokeAssert(($accountableDraft['no_financial_mutation'] ?? null) === true, 'accountable draft no mutation flag missing');
$ownerSubmittedReportsBefore = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-reports"), 'owner submitted accountable reports before submit')['reports'];
smokeAssert(count($ownerSubmittedReportsBefore) === 0, 'owner must not see employee accountable drafts as submitted');
$employeeDraftReports = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-reports?status=draft", null, true, $employeeToken), 'employee list draft accountable reports')['reports'];
smokeAssert(count($employeeDraftReports) === 1, 'employee should see own accountable draft');
$submittedAccountableReport = expectOk(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/submit", [], true, $employeeToken), 'employee submit accountable report')['report'];
smokeAssert(($submittedAccountableReport['status'] ?? null) === 'submitted', 'submitted accountable report status mismatch');
smokeAssert(($submittedAccountableReport['submitted_by'] ?? null) === 19003, 'submitted accountable report user mismatch');
expectError(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/submit", [], true, $employeeToken), 409, 'accountable_report_not_draft', 'accountable report cannot submit twice');
$ownerSubmittedReports = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-reports"), 'owner list submitted accountable reports')['reports'];
smokeAssert(count($ownerSubmittedReports) === 1, 'owner should see submitted accountable report');
smokeAssert((string)$ownerSubmittedReports[0]['id'] === (string)$accountableDraft['id'], 'owner submitted accountable report id mismatch');
$accountableReportCountsAfter = smokeTableCounts(['v2_entries', 'v2_accountable_reports', 'v2_accountable_report_rows']);
$accountableReportSummaryAfter = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary after accountable report submit')['summary'];
smokeAssert($accountableReportCountsAfter['v2_entries'] === $accountableReportCountsBefore['v2_entries'], 'accountable report must not create operational entries');
smokeAssert($accountableReportCountsAfter['v2_accountable_reports'] === $accountableReportCountsBefore['v2_accountable_reports'] + 1, 'accountable report should create one draft/report row');
smokeAssert($accountableReportCountsAfter['v2_accountable_report_rows'] === $accountableReportCountsBefore['v2_accountable_report_rows'] + 2, 'accountable report should create two report expense rows');
smokeAssertAmount($accountableReportSummaryAfter['cash_now'], (float)$accountableReportSummaryBefore['cash_now'], 'accountable report submit must not change cash now');
smokeAssertAmount($accountableReportSummaryAfter['card_expense_total'], (float)$accountableReportSummaryBefore['card_expense_total'], 'accountable report submit must not change card expense total');
$employeeModeAfterReport = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/employee-mode", null, true, $employeeToken), 'employee mode after accountable report submit');
smokeAssert(($employeeModeAfterReport['summary']['submitted_reports'] ?? null) === 1, 'employee mode submitted report count mismatch');

$accountableAcceptCountsBefore = smokeTableCounts(['v2_entries', 'v2_accountable_reports', 'v2_accountable_report_rows', 'v2_accountable_settlements']);
$accountableAcceptSummaryBefore = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary before accountable report admin accept')['summary'];
$accountableDetail = expectOk(smokeRequest('GET', "/api/accountable-reports/{$accountableDraft['id']}"), 'owner get accountable report detail')['report'];
smokeAssert(($accountableDetail['status'] ?? null) === 'submitted', 'accountable detail status mismatch');
smokeAssert(count($accountableDetail['rows'] ?? []) === 2, 'accountable detail rows mismatch');
expectError(smokeRequest('GET', "/api/accountable-reports/{$accountableDraft['id']}", null, true, $viewerToken), 403, 'workspace_admin_required', 'viewer cannot get accountable report detail');
expectError(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/accept", [], true, $employeeToken), 404, 'accountable_report_not_found', 'employee cannot admin-accept accountable report');
$accountablePreview = expectOk(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/review-preview", [
    'payment_method' => 'cash',
]), 'owner preview accountable report review')['preview'];
smokeAssertAmount($accountablePreview['accepted_total_amount'] ?? null, 77.5, 'accountable review preview accepted total');
smokeAssertAmount($accountablePreview['settlement']['issued_amount'] ?? null, 500.0, 'accountable review preview issued');
smokeAssertAmount($accountablePreview['settlement']['return_due_amount'] ?? null, 422.5, 'accountable review preview return due');
smokeAssert(($accountablePreview['settlement']['status'] ?? null) === 'return_due', 'accountable review preview status mismatch');
$accountableAccepted = expectOk(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/accept", [
    'payment_method' => 'cash',
    'review_note' => 'Smoke admin acceptance gate',
]), 'owner accept accountable report')['result'];
smokeAssert(($accountableAccepted['report']['status'] ?? null) === 'accepted_by_admin', 'accountable admin accept status mismatch');
smokeAssert(($accountableAccepted['report']['reviewed_by'] ?? null) === 19001, 'accountable admin accept reviewer mismatch');
smokeAssert(($accountableAccepted['report']['settlement_status'] ?? null) === 'return_due', 'accountable admin accept settlement status mismatch');
smokeAssertAmount($accountableAccepted['settlement']['return_due_amount'] ?? null, 422.5, 'accountable admin accept return due');
smokeAssert(count($accountableAccepted['materialized_entries'] ?? []) === 0, 'accountable admin accept must not materialize ledger entries in this gate sprint');
expectError(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/accept", [
    'payment_method' => 'cash',
]), 409, 'accountable_report_not_submitted', 'accountable admin accept cannot run twice');
$accountableAcceptCountsAfter = smokeTableCounts(['v2_entries', 'v2_accountable_reports', 'v2_accountable_report_rows', 'v2_accountable_settlements']);
$accountableAcceptSummaryAfter = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary after accountable report admin accept')['summary'];
smokeAssert($accountableAcceptCountsAfter['v2_entries'] === $accountableAcceptCountsBefore['v2_entries'], 'accountable admin accept gate must not create operational entries');
smokeAssert($accountableAcceptCountsAfter['v2_accountable_reports'] === $accountableAcceptCountsBefore['v2_accountable_reports'], 'accountable admin accept should update existing report only');
smokeAssert($accountableAcceptCountsAfter['v2_accountable_report_rows'] === $accountableAcceptCountsBefore['v2_accountable_report_rows'], 'accountable admin accept should update existing report rows only');
smokeAssert($accountableAcceptCountsAfter['v2_accountable_settlements'] === $accountableAcceptCountsBefore['v2_accountable_settlements'] + 1, 'accountable admin accept should create one settlement snapshot');
smokeAssertAmount($accountableAcceptSummaryAfter['cash_now'], (float)$accountableAcceptSummaryBefore['cash_now'], 'accountable admin accept gate must not change cash now');
smokeAssertAmount($accountableAcceptSummaryAfter['card_expense_total'], (float)$accountableAcceptSummaryBefore['card_expense_total'], 'accountable admin accept gate must not change card expense total');
$ownerSubmittedReportsAfterAccept = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-reports"), 'owner submitted accountable reports after accept')['reports'];
smokeAssert(count($ownerSubmittedReportsAfterAccept) === 0, 'accepted accountable report must leave submitted admin queue');
$accountableDashboardBeforeMaterialize = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-dashboard"), 'owner accountable dashboard before materialize')['dashboard'];
smokeAssert(($accountableDashboardBeforeMaterialize['summary']['policy'] ?? null) === 'cash_card_effect_none_read_model', 'accountable dashboard policy mismatch');
smokeAssertAmount($accountableDashboardBeforeMaterialize['summary']['cash_delta'] ?? null, 0.0, 'accountable dashboard cash delta');
smokeAssertAmount($accountableDashboardBeforeMaterialize['summary']['card_delta'] ?? null, 0.0, 'accountable dashboard card delta');
smokeAssertAmount($accountableDashboardBeforeMaterialize['summary']['issued_total'] ?? null, 500.0, 'accountable dashboard issued total before materialize');
smokeAssertAmount($accountableDashboardBeforeMaterialize['summary']['accepted_report_total'] ?? null, 77.5, 'accountable dashboard accepted report total before materialize');
smokeAssertAmount($accountableDashboardBeforeMaterialize['summary']['return_due_total'] ?? null, 422.5, 'accountable dashboard return due before materialize');
smokeAssertAmount($accountableDashboardBeforeMaterialize['summary']['not_materialized_total'] ?? null, 77.5, 'accountable dashboard not materialized before materialize');
smokeAssertAmount($accountableDashboardBeforeMaterialize['summary']['materialized_total'] ?? null, 0.0, 'accountable dashboard materialized before materialize');
smokeAssert(count($accountableDashboardBeforeMaterialize['employees'] ?? []) >= 1, 'accountable dashboard should list employee positions');
$dashboardEmployee = $accountableDashboardBeforeMaterialize['employees'][0] ?? [];
$dashboardReport = $dashboardEmployee['reports'][0] ?? [];
smokeAssert(!array_key_exists('rows', $dashboardReport), 'accountable dashboard report must not expose raw rows');
smokeAssert(($dashboardReport['no_financial_mutation'] ?? null) === true, 'accountable dashboard report no mutation flag mismatch');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-dashboard", null, true, $viewerToken), 403, 'workspace_admin_required', 'viewer accountable dashboard blocked');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-dashboard", null, true, $employeeToken), 403, 'workspace_scope_required', 'employee accountable dashboard blocked');

$accountableMaterializeCountsBefore = smokeTableCounts(['v2_entries', 'v2_flows', 'v2_accountable_report_entry_links']);
$accountableMaterializeSummaryBefore = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary before accountable report materialize')['summary'];
expectError(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/materialization-preview", [], true, $viewerToken), 403, 'workspace_admin_required', 'viewer cannot preview accountable materialization');
expectError(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/materialize", [], true, $employeeToken), 404, 'accountable_report_not_found', 'employee cannot materialize accountable report');
$materializationPreview = expectOk(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/materialization-preview"), 'owner preview accountable materialization')['preview'];
smokeAssert(($materializationPreview['eligible_row_count'] ?? null) === 2, 'accountable materialization eligible rows mismatch');
smokeAssert(($materializationPreview['policy'] ?? null) === 'cash_effect_none_category_projection', 'accountable materialization policy mismatch');
smokeAssertAmount($materializationPreview['projected_total_amount'] ?? null, 77.5, 'accountable materialization projected total');
smokeAssertAmount($materializationPreview['cash_delta'] ?? null, 0.0, 'accountable materialization cash delta');
smokeAssertAmount($materializationPreview['card_delta'] ?? null, 0.0, 'accountable materialization card delta');
$materialized = expectOk(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/materialize"), 'owner materialize accountable report')['result'];
smokeAssert(($materialized['materialization']['status'] ?? null) === 'materialized', 'accountable materialization status mismatch');
smokeAssert(($materialized['materialization']['entry_count'] ?? null) === 2, 'accountable materialization entry count mismatch');
smokeAssert(count($materialized['created_entries'] ?? []) === 2, 'accountable materialization created entries mismatch');
$createdProjection = $materialized['created_entries'][0];
smokeAssert(($createdProjection['flow']['type'] ?? null) === 'accountable', 'accountable projection flow type mismatch');
smokeAssert(($createdProjection['entry_type'] ?? null) === 'accountable_expense', 'accountable projection entry type mismatch');
smokeAssert(($createdProjection['source_type'] ?? null) === 'accountable_report', 'accountable projection source mismatch');
smokeAssert(($createdProjection['status'] ?? null) === 'accepted', 'accountable projection status mismatch');
expectError(smokeRequest('PATCH', '/api/entries/' . $createdProjection['id'], [
    'raw_text' => '-35 tampered projection',
]), 409, 'accountable_projection_entry_immutable', 'accountable projection entry cannot be edited directly');
expectError(smokeRequest('PATCH', '/api/entries/' . $createdProjection['id'] . '/category', [
    'category_code' => 'fuel',
]), 409, 'accountable_projection_entry_immutable', 'accountable projection entry category cannot be edited directly');
expectError(smokeRequest('DELETE', '/api/entries/' . $createdProjection['id']), 409, 'accountable_projection_entry_immutable', 'accountable projection entry cannot be deleted directly');
$materializedAgain = expectOk(smokeRequest('POST', "/api/accountable-reports/{$accountableDraft['id']}/materialize"), 'owner materialize accountable report idempotent retry')['result'];
smokeAssert(($materializedAgain['materialization']['entry_count'] ?? null) === 2, 'accountable materialization retry entry count mismatch');
smokeAssert(count($materializedAgain['created_entries'] ?? []) === 0, 'accountable materialization retry must not create duplicate entries');
$accountableMaterializeCountsAfter = smokeTableCounts(['v2_entries', 'v2_flows', 'v2_accountable_report_entry_links']);
$accountableMaterializeSummaryAfter = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary after accountable report materialize')['summary'];
smokeAssert($accountableMaterializeCountsAfter['v2_entries'] === $accountableMaterializeCountsBefore['v2_entries'] + 2, 'accountable materialization should create two ledger projection entries');
smokeAssert($accountableMaterializeCountsAfter['v2_flows'] === $accountableMaterializeCountsBefore['v2_flows'] + 1, 'accountable materialization should create one accountable projection flow');
smokeAssert($accountableMaterializeCountsAfter['v2_accountable_report_entry_links'] === $accountableMaterializeCountsBefore['v2_accountable_report_entry_links'] + 2, 'accountable materialization should create two entry links');
smokeAssertAmount($accountableMaterializeSummaryAfter['cash_now'], (float)$accountableMaterializeSummaryBefore['cash_now'], 'accountable materialization must not change cash now');
smokeAssertAmount($accountableMaterializeSummaryAfter['card_expense_total'], (float)$accountableMaterializeSummaryBefore['card_expense_total'], 'accountable materialization must not change card expense total');
$materializedReportDetail = expectOk(smokeRequest('GET', "/api/accountable-reports/{$accountableDraft['id']}"), 'owner get materialized accountable report detail')['report'];
smokeAssert(($materializedReportDetail['ledger_materialization_status'] ?? null) === 'materialized', 'accountable report ledger materialization readback mismatch');
smokeAssert(count(array_filter($materializedReportDetail['rows'] ?? [], static fn (array $row): bool => !empty($row['operational_entry_id']))) === 2, 'accountable report rows should link projection entries');
$accountableDashboardAfterMaterialize = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-dashboard"), 'owner accountable dashboard after materialize')['dashboard'];
smokeAssertAmount($accountableDashboardAfterMaterialize['summary']['materialized_total'] ?? null, 77.5, 'accountable dashboard materialized after materialize');
smokeAssertAmount($accountableDashboardAfterMaterialize['summary']['not_materialized_total'] ?? null, 0.0, 'accountable dashboard not materialized after materialize');

$inviteeToken = (string)getenv('FINDESK_V2_HTTP_INVITEE_TOKEN');
$wrongInviteeToken = (string)getenv('FINDESK_V2_HTTP_WRONG_INVITEE_TOKEN');
smokeAssert($inviteeToken !== '', 'Missing FINDESK_V2_HTTP_INVITEE_TOKEN');
smokeAssert($wrongInviteeToken !== '', 'Missing FINDESK_V2_HTTP_WRONG_INVITEE_TOKEN');

$invite = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/invites", [
    'email' => 'v2-http-invitee@example.test',
    'name' => 'Invitee Smoke',
    'role' => 'employee',
    'access_scope' => 'own_entries',
]), 'owner create employee invite')['invite'];
smokeAssert(($invite['role'] ?? null) === 'employee', 'invite role mismatch');
smokeAssert(($invite['access_scope'] ?? null) === 'own_entries', 'invite scope mismatch');
smokeAssert(!empty($invite['token']), 'invite create should return raw token once');
smokeAssert(str_contains((string)$invite['url'], '?invite='), 'invite url should contain invite parameter');
$inviteToken = (string)$invite['token'];
$db = smokeDb();
$rawStmt = $db->prepare("SELECT COUNT(*) FROM v2_workspace_invites WHERE token_hash = ?");
$rawStmt->execute([$inviteToken]);
smokeAssert((int)$rawStmt->fetchColumn() === 0, 'raw invite token must not be stored');
$hashStmt = $db->prepare("SELECT COUNT(*) FROM v2_workspace_invites WHERE token_hash = ?");
$hashStmt->execute([hash('sha256', $inviteToken)]);
smokeAssert((int)$hashStmt->fetchColumn() === 1, 'invite token hash should be stored');

$inviteList = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/invites"), 'owner list invites')['invites'];
smokeAssert(count($inviteList) >= 1, 'invite list should include created invite');
smokeAssert(!array_key_exists('token', $inviteList[0]), 'invite list must not expose raw token');
smokeAssert(!array_key_exists('url', $inviteList[0]), 'invite list must not expose invite url');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/invites", [
    'email' => 'viewer-invite@example.test',
], true, $viewerToken), 403, 'workspace_admin_required', 'viewer create invite blocked');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/invites", [
    'email' => 'employee-invite@example.test',
], true, $employeeToken), 403, 'workspace_admin_required', 'employee create invite blocked');
expectError(smokeRequest('POST', "/api/workspace-invites/accept", [
    'token' => $inviteToken,
], false), 401, 'not_authenticated', 'unauthenticated invite accept');

$invitePreview = expectOk(smokeRequest('POST', '/api/workspace-invites/preview', [
    'token' => $inviteToken,
], true, $inviteeToken), 'invitee preview invite');
smokeAssert(($invitePreview['workspace']['id'] ?? null) === $workspaceId, 'invite preview workspace mismatch');
smokeAssert(($invitePreview['email_matches'] ?? null) === true, 'invite preview email match mismatch');
expectError(smokeRequest('POST', '/api/workspace-invites/accept', [
    'token' => $inviteToken,
], true, $wrongInviteeToken), 403, 'invite_email_mismatch', 'wrong email invite accept blocked');

$acceptedInvite = expectOk(smokeRequest('POST', '/api/workspace-invites/accept', [
    'token' => $inviteToken,
], true, $inviteeToken), 'invitee accept invite');
smokeAssert(($acceptedInvite['workspace']['role'] ?? null) === 'employee', 'accepted invite workspace role mismatch');
smokeAssert(($acceptedInvite['workspace']['access_scope'] ?? null) === 'own_entries', 'accepted invite scope mismatch');
expectError(smokeRequest('POST', '/api/workspace-invites/accept', [
    'token' => $inviteToken,
], true, $inviteeToken), 409, 'invite_already_accepted', 'accepted invite cannot be reused');
$inviteeWorkspaces = expectOk(smokeRequest('GET', '/api/workspaces', null, true, $inviteeToken), 'invitee list workspaces after accept')['workspaces'];
smokeAssert(count($inviteeWorkspaces) === 1, 'invitee workspace list count mismatch');
smokeAssert(($inviteeWorkspaces[0]['role'] ?? null) === 'employee', 'invitee role mismatch');
smokeAssert(($inviteeWorkspaces[0]['can_read_workspace'] ?? null) === false, 'invitee must not read full workspace');
$inviteeOffers = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/accountable-offers", null, true, $inviteeToken), 'invitee own accountable offers only')['offers'];
smokeAssert(count($inviteeOffers) === 0, 'invitee must not see another employee accountable offer');
$inviteeMode = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/employee-mode", null, true, $inviteeToken), 'invitee employee mode');
smokeAssert(count($inviteeMode['offers'] ?? []) === 0, 'invitee employee mode must not leak other offers');
expectError(smokeRequest('GET', "/api/workspaces/{$workspaceId}/flows", null, true, $inviteeToken), 403, 'workspace_scope_required', 'invitee flows blocked');
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-10 invitee write',
], true, $inviteeToken), 403, 'workspace_read_only', 'invitee full entry write blocked');

$revokedInvite = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/invites", [
    'email' => 'v2-http-wrong-invitee@example.test',
]), 'owner create revoke invite')['invite'];
expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/invites/{$revokedInvite['id']}/revoke"), 'owner revoke invite');
expectError(smokeRequest('POST', '/api/workspace-invites/accept', [
    'token' => $revokedInvite['token'],
], true, $wrongInviteeToken), 409, 'invite_revoked', 'revoked invite blocked');

$expiredInvite = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/invites", [
    'email' => 'v2-http-wrong-invitee@example.test',
]), 'owner create expired invite')['invite'];
$expireStmt = smokeDb()->prepare("UPDATE v2_workspace_invites SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE id = ?");
$expireStmt->execute([$expiredInvite['id']]);
expectError(smokeRequest('POST', '/api/workspace-invites/accept', [
    'token' => $expiredInvite['token'],
], true, $wrongInviteeToken), 409, 'invite_expired', 'expired invite blocked');

expectError(smokeRequest('POST', '/api/entries/' . $entry['id'] . '/attachments', null, false), 401, 'not_authenticated', 'unauthenticated attachment upload');
expectError(smokeRequest('POST', '/api/entries/' . $entry['id'] . '/attachments', [
    'file_name' => '../receipt.png',
    'content_base64' => base64_encode('not an image'),
]), 422, 'invalid_file_name', 'attachment traversal filename');
expectError(smokeRequest('POST', '/api/entries/' . $entry['id'] . '/attachments', [
    'file_name' => 'receipt.png',
    'content_base64' => 'not-base64!',
]), 422, 'invalid_content_base64', 'attachment invalid base64');

$summaryBeforeAttachment = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary before attachment')['summary'];
$attachment = expectOk(smokeRequest('POST', '/api/entries/' . $entry['id'] . '/attachments', [
    'file_name' => 'receipt.png',
    'mime_type' => 'text/plain',
    'image_mode' => 'original',
    'content_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
]), 'create attachment')['attachment'];
smokeAssert((string)$attachment['entry_id'] === (string)$entry['id'], 'attachment entry id mismatch');
smokeAssert((string)$attachment['file_name'] === 'receipt.png', 'attachment file name mismatch');
smokeAssert((string)$attachment['mime_type'] === 'image/png', 'attachment MIME should be detected');
smokeAssert((int)$attachment['size_bytes'] > 0, 'attachment size missing');
smokeAssert(str_starts_with((string)$attachment['file_url'], 'storage/v2/attachments/'), 'attachment storage path is not v2 private storage');
$attachmentPath = smokeStoragePath((string)$attachment['file_url']);
smokeAssert(is_file($attachmentPath), 'attachment file missing on disk');
smokeAssert(smokeAuditCount('create', (string)$attachment['id']) === 1, 'attachment create audit missing');
$attachments = expectOk(smokeRequest('GET', '/api/entries/' . $entry['id'] . '/attachments'), 'list attachments')['attachments'];
smokeAssert(count($attachments) === 1, 'attachment list count mismatch');
smokeAssert((string)$attachments[0]['id'] === (string)$attachment['id'], 'attachment list id mismatch');
$deletedAttachment = expectOk(smokeRequest('DELETE', '/api/attachments/' . $attachment['id']), 'delete attachment')['attachment'];
smokeAssert(($deletedAttachment['deleted'] ?? null) === true, 'attachment deleted flag mismatch');
smokeAssert(($deletedAttachment['file_deleted'] ?? null) === true, 'attachment file deleted flag mismatch');
clearstatcache(true, $attachmentPath);
smokeAssert(!is_file($attachmentPath), 'attachment file remains after delete');
smokeAssert(smokeAuditCount('delete', (string)$attachment['id']) === 1, 'attachment delete audit missing');
$attachmentsAfterDelete = expectOk(smokeRequest('GET', '/api/entries/' . $entry['id'] . '/attachments'), 'list attachments after delete')['attachments'];
smokeAssert(count($attachmentsAfterDelete) === 0, 'attachment list should be empty after delete');
expectError(smokeRequest('DELETE', '/api/attachments/' . $attachment['id']), 404, 'attachment_not_found', 'delete missing attachment');
$summaryAfterAttachment = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/summary"), 'summary after attachment')['summary'];
smokeAssertAmount($summaryAfterAttachment['cash_now'], (float)$summaryBeforeAttachment['cash_now'], 'attachment should not change cash now');

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
$englishOtherEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-19 other expense manual queue',
]), 'create english other expense')['entry'];
smokeAssert($englishOtherEntry['status'] === 'other_review', 'english other expense status mismatch');
smokeAssert($englishOtherEntry['category_code'] === 'other', 'english other expense category mismatch');
$unknownOtherEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-21 unknown_expense manual queue',
]), 'create unknown_expense other expense')['entry'];
smokeAssert($unknownOtherEntry['status'] === 'other_review', 'unknown_expense other expense status mismatch');
smokeAssert($unknownOtherEntry['category_code'] === 'other', 'unknown_expense other expense category mismatch');
$cashIncomeUnknownEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+19 unknown_expense income boundary',
]), 'create cash income unknown_expense boundary')['entry'];
smokeAssert($cashIncomeUnknownEntry['status'] !== 'other_review', 'cash + unknown_expense should not enter other_review');
smokeAssert($cashIncomeUnknownEntry['category_code'] !== 'other', 'cash + unknown_expense should not become other');
expectOk(smokeRequest('DELETE', '/api/entries/' . $cashIncomeUnknownEntry['id']), 'archive cash income unknown_expense boundary');
$cardOtherEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cardFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-19 other expense card boundary',
]), 'create card other expense boundary')['entry'];
smokeAssert($cardOtherEntry['status'] !== 'other_review', 'card other expense should not enter cash other_review');
smokeAssert($cardOtherEntry['category_code'] !== 'other', 'card other expense should not become cash other');
expectOk(smokeRequest('DELETE', '/api/entries/' . $cardOtherEntry['id']), 'archive card other expense boundary');
$noSignMiscEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '19 misc no sign boundary',
]), 'create no-sign misc boundary')['entry'];
smokeAssert($noSignMiscEntry['status'] === 'unrecognized', 'no-sign misc should remain unrecognized');
smokeAssert($noSignMiscEntry['category_code'] === null, 'no-sign misc should not become other');

$otherQueue = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/other-expenses"), 'other expenses queue')['entries'];
smokeAssert(count($otherQueue) === 3, 'other expenses queue count mismatch: ' . json_encode(array_map(static fn (array $entry): array => [
    'raw_text' => $entry['raw_text'] ?? null,
    'entry_type' => $entry['entry_type'] ?? null,
    'status' => $entry['status'] ?? null,
    'category_code' => $entry['category_code'] ?? null,
], $otherQueue), JSON_UNESCAPED_UNICODE));
smokeAssert((string)$otherQueue[0]['id'] === (string)$otherEntry['id'], 'other expenses queue entry mismatch');
smokeAssert((string)$otherQueue[1]['id'] === (string)$englishOtherEntry['id'], 'other expenses queue english entry mismatch');
smokeAssert((string)$otherQueue[2]['id'] === (string)$unknownOtherEntry['id'], 'other expenses queue unknown_expense entry mismatch');

$resolvedOther = expectOk(smokeRequest('PATCH', '/api/entries/' . $otherEntry['id'] . '/category', [
    'category_code' => 'tech_parts',
]), 'resolve other expense category')['entry'];
smokeAssert($resolvedOther['category_code'] === 'tech_parts', 'resolved other category mismatch');
smokeAssert($resolvedOther['status'] === 'recognized', 'resolved other status mismatch');
$otherQueueAfterResolve = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/other-expenses"), 'other expenses queue after resolve')['entries'];
smokeAssert(count($otherQueueAfterResolve) === 2, 'other expenses queue should keep unresolved other entries after one correction');

$entriesBeforePreview = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/entries"), 'list entries before preview')['entries'];
$preview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+42 preview',
]), 'parse preview')['preview'];
smokeAssert($preview['will_save'] === false, 'preview must not save');
smokeAssert($preview['entry_type'] === 'cash_income', 'preview entry type mismatch');
smokeAssert(smokeSemanticMarkerHas($preview, 'owner_funding'), 'preview owner funding marker missing');

$safePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+6000 из сейфа',
]), 'safe parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($safePreview, 'cash_location_safe'), 'safe preview cash marker missing');
smokeAssert(smokeSemanticMarkerHas($safePreview, 'owner_funding'), 'safe preview owner marker missing');

$ownerPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+5000 от Александра',
]), 'owner funding parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($ownerPreview, 'owner_funding', 'source_actor', 'Александр'), 'owner preview source actor marker missing');
smokeAssert(($ownerPreview['category_code'] ?? null) === 'non_commercial_income', 'owner preview should become non-commercial income');
smokeAssert(($ownerPreview['review_reason'] ?? null) === null, 'owner preview review reason');
smokeAssertAmount($ownerPreview['confidence'] ?? null, 0.92, 'owner preview confidence');

$nataliaOwnerPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+5000 получил от Наталии',
]), 'Natalia owner funding parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($nataliaOwnerPreview, 'owner_funding', 'source_actor', 'Наталия'), 'Natalia owner preview source actor marker missing');

$actorExpensePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 ЛВ',
]), 'actor expense parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($actorExpensePreview, 'actor_context'), 'actor expense should expose actor context marker');
smokeAssert(!smokeSemanticMarkerHas($actorExpensePreview, 'owner_funding'), 'actor expense should not become owner funding');
smokeAssert(($actorExpensePreview['category_code'] ?? null) === 'guest_cash_issued', 'actor expense should become guest cash issued');
$actorTransferPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+100 передал ЛВ',
]), 'actor transfer parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($actorTransferPreview, 'actor_context'), 'actor transfer should expose actor context marker');
smokeAssert(!smokeSemanticMarkerHas($actorTransferPreview, 'owner_funding'), 'actor transfer should not become owner funding');
$guestCashExpensePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 передал ЛВ',
]), 'guest cash issued parse preview')['preview'];
smokeAssert(($guestCashExpensePreview['category_code'] ?? null) === 'guest_cash_issued', 'guest cash issued expense category mismatch');
$acceptedOwnerPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+100 принял',
]), 'accepted owner funding parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($acceptedOwnerPreview, 'owner_funding'), 'generic accepted income should become owner funding');

$carRentalPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+100 аренда авто',
]), 'car rental parse preview')['preview'];
smokeAssert(($carRentalPreview['category_code'] ?? null) !== 'commercial_income', 'car rental preview became commercial income');
$carRentalExpensePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 аренда авто',
]), 'car rental expense parse preview')['preview'];
smokeAssert(($carRentalExpensePreview['category_code'] ?? null) === 'transport_expenses', 'car rental expense should become transport expense');
$flowersPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-50 цветы',
]), 'flowers parse preview')['preview'];
smokeAssert(($flowersPreview['category_code'] ?? null) === 'provisions', 'flowers should become provisions');
$giftPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-50 подарок Алине',
]), 'gift parse preview')['preview'];
smokeAssert(($giftPreview['category_code'] ?? null) !== 'provisions', 'gift should not become provisions automatically');
smokeAssert(($giftPreview['category_code'] ?? null) === 'representation_expenses', 'gift should become representation expense');
$pharmacyPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-25 аптека',
]), 'pharmacy parse preview')['preview'];
smokeAssert(($pharmacyPreview['category_code'] ?? null) === 'provisions', 'pharmacy should become provisions');
$agentPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-50 агент',
]), 'agent parse preview')['preview'];
smokeAssert(($agentPreview['category_code'] ?? null) === 'current_boat_expenses', 'agent should become current boat expense');
smokeAssert(smokeSemanticMarkerHas($agentPreview, 'weak_dictionary_context'), 'agent should expose weak dictionary marker');
smokeAssert(($agentPreview['review_reason'] ?? null) === 'weak_only', 'agent should expose weak review reason');
smokeAssertAmount($agentPreview['confidence'] ?? null, 0.48, 'agent confidence');
smokeAssert(smokeSignalHas($agentPreview, 'marker', 'weak_dictionary_context'), 'agent should expose weak matched signal');
smokeAssert(($agentPreview['blockers'] ?? []) === [], 'agent should not expose blockers');
$agentMealPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-50 обед с агентом',
]), 'agent meal parse preview')['preview'];
smokeAssert(($agentMealPreview['category_code'] ?? null) === 'representation_expenses', 'meal with agent should become representation expense');
smokeAssert(!smokeSemanticMarkerHas($agentMealPreview, 'weak_dictionary_context'), 'meal with agent should not expose weak dictionary marker');
smokeAssert(($agentMealPreview['review_reason'] ?? null) === null, 'meal with agent should not need review');
smokeAssertAmount($agentMealPreview['confidence'] ?? null, 0.92, 'meal with agent confidence');
smokeAssert(($agentMealPreview['blockers'] ?? []) === [], 'meal with agent should not expose blockers');
$storePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-20 магазин сплит',
]), 'store parse preview')['preview'];
smokeAssert(($storePreview['category_code'] ?? null) === 'current_boat_expenses', 'store should become current boat expense');
$deliveryPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-15 доставка',
]), 'delivery parse preview')['preview'];
smokeAssert(($deliveryPreview['category_code'] ?? null) === 'transport_expenses', 'delivery should become transport expense');
smokeAssert(smokeSemanticMarkerHas($deliveryPreview, 'weak_dictionary_context'), 'delivery should expose weak dictionary marker');
smokeAssert(($deliveryPreview['review_reason'] ?? null) === 'weak_only', 'delivery should expose weak review reason');
smokeAssertAmount($deliveryPreview['confidence'] ?? null, 0.48, 'delivery confidence');
$deliveryPartPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-15 доставка фильтра',
]), 'delivery part parse preview')['preview'];
smokeAssert(($deliveryPartPreview['category_code'] ?? null) === 'tech_parts', 'delivery part should keep part category');
smokeAssert(smokeSemanticMarkerHas($deliveryPartPreview, 'mixed_dictionary_context'), 'delivery part should expose mixed dictionary marker');
smokeAssert(($deliveryPartPreview['review_reason'] ?? null) === 'mixed_context', 'delivery part should expose mixed review reason');
smokeAssertAmount($deliveryPartPreview['confidence'] ?? null, 0.64, 'delivery part confidence');
smokeAssert(smokeSignalHas($deliveryPartPreview, 'marker', 'mixed_dictionary_context'), 'delivery part should expose mixed matched signal');
$hotelPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-200 отели',
]), 'hotel parse preview')['preview'];
smokeAssert(($hotelPreview['category_code'] ?? null) === 'guest_trip_support', 'hotel should become guest trip support expense');
$musiciansPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-700 музыканты',
]), 'musicians parse preview')['preview'];
smokeAssert(($musiciansPreview['category_code'] ?? null) === 'guest_trip_support', 'musicians should become guest trip support');
$creditPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-1000 мой кредит',
]), 'credit parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($creditPreview, 'debt_or_return'), 'credit preview should expose debt/loan/credit marker');
smokeAssert(($creditPreview['accounting_section'] ?? null) === 'admin_debt', 'personal credit should route to administrator debt');
smokeAssert(($creditPreview['review_reason'] ?? null) === null, 'administrator debt preview review reason');
$personalFuelPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 Порше топливо',
]), 'personal fuel parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($personalFuelPreview, 'non_yacht_or_personal'), 'personal fuel should expose non-yacht marker');
smokeAssert(($personalFuelPreview['review_reason'] ?? null) === 'blocked_by_personal', 'personal fuel review reason');
smokeAssertAmount($personalFuelPreview['confidence'] ?? null, 0.20, 'personal fuel confidence');
smokeAssert(smokeBlockerHas($personalFuelPreview, 'non_yacht_or_personal'), 'personal fuel blocker');
$debtGaragePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-250 долг за гараж',
]), 'debt garage parse preview')['preview'];
smokeAssert(($debtGaragePreview['review_reason'] ?? null) === null, 'debt garage should not need review when berth context is clear');
smokeAssertAmount($debtGaragePreview['confidence'] ?? null, 0.92, 'debt garage confidence');
smokeAssert(!smokeBlockerHas($debtGaragePreview, 'debt_or_return'), 'debt garage should not be blocked when berth context is clear');
smokeAssert(($debtGaragePreview['accounting_section'] ?? null) === 'operational', 'debt garage should remain operational when it has concrete boat-expense context');
$customsDebtPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-150 долг таможне дьюти',
]), 'customs debt parse preview')['preview'];
smokeAssert(($customsDebtPreview['category_code'] ?? null) === 'admin_legal', 'customs debt should stay admin/legal operational category');
smokeAssert(($customsDebtPreview['review_reason'] ?? null) === null, 'customs debt should not need lower-accounting review');
smokeAssert(($customsDebtPreview['accounting_section'] ?? null) === 'operational', 'customs debt should remain operational');
$unclearCommercialPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+750 агентские',
]), 'unclear commercial parse preview')['preview'];
smokeAssert(($unclearCommercialPreview['category_code'] ?? null) !== 'commercial_income', 'unclear commercial should not become commercial income');
smokeAssert(($unclearCommercialPreview['review_reason'] ?? null) === 'commercial_income_unclear', 'unclear commercial review reason');
smokeAssertAmount($unclearCommercialPreview['confidence'] ?? null, 0.30, 'unclear commercial confidence');
smokeAssert(smokeBlockerHas($unclearCommercialPreview, 'missing_yacht_charter_phrase'), 'unclear commercial blocker');
$charterIncomePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+5525 ареда яхты',
]), 'charter income parse preview')['preview'];
smokeAssert(($charterIncomePreview['category_code'] ?? null) === 'commercial_income', 'charter income should become commercial income');
smokeAssert(($charterIncomePreview['review_reason'] ?? null) === null, 'charter income should not need review');
smokeAssertAmount($charterIncomePreview['confidence'] ?? null, 0.92, 'charter income confidence');
smokeAssert(($charterIncomePreview['blockers'] ?? []) === [], 'charter income blockers');

$guestCashPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 передал ЛВ',
]), 'guest cash parse preview')['preview'];
smokeAssert(($guestCashPreview['category_code'] ?? null) === 'guest_cash_issued', 'guest cash preview category mismatch');
smokeAssert(($guestCashPreview['accounting_section'] ?? null) === 'operational', 'guest cash preview should remain operational for manual correction');

$packageThreePreviews = [
    ['raw_text' => '-100 чаевые из кассы нам', 'category_code' => 'crew', 'label' => 'crew tips'],
    ['raw_text' => '-100 Докупка необходимого в поход', 'category_code' => 'provisions', 'label' => 'trip provisions'],
    ['raw_text' => '-100 шампанское', 'category_code' => 'provisions', 'label' => 'champagne provisions'],
    ['raw_text' => '-100 5 водка грей гус', 'category_code' => 'provisions', 'label' => 'vodka provisions'],
    ['raw_text' => '-100 за перешив подушек', 'category_code' => 'interior', 'label' => 'cushion rework'],
    ['raw_text' => '-100 перевозка гидроцикла', 'category_code' => 'transport_expenses', 'label' => 'jet ski transport'],
    ['raw_text' => '-100 переход Коринф канала', 'category_code' => 'marina_ports', 'label' => 'Corinth passage'],
    ['raw_text' => '-100 склад 07,08.09,10,11', 'category_code' => 'berth', 'label' => 'storage berth'],
    ['raw_text' => '-100 вода электричество', 'category_code' => 'berth', 'label' => 'water electricity berth'],
    ['raw_text' => '-100 доп муринг с северной стороны', 'category_code' => 'berth', 'label' => 'mooring berth'],
    ['raw_text' => '-100 левая кормовая лебедка', 'category_code' => 'tech_parts', 'label' => 'winch parts'],
    ['raw_text' => '-100 контролька кондея', 'category_code' => 'tech_parts', 'label' => 'ac control part'],
    ['raw_text' => '-100 материалы по тику', 'category_code' => 'tech_parts', 'label' => 'teak materials'],
    ['raw_text' => '-100 Безопастность плавания сет', 'category_code' => 'tech_parts', 'label' => 'safety navigation set'],
    ['raw_text' => '-100 блок управления туалетом', 'category_code' => 'tech_parts', 'label' => 'toilet control part'],
    ['raw_text' => '-100 огнетушители, тест систем пожара', 'category_code' => 'service_water', 'label' => 'fire system service'],
    ['raw_text' => '-100 блок сонос', 'category_code' => 'media_comms', 'label' => 'sonos media'],
    ['raw_text' => '-100 модем на лодку', 'category_code' => 'media_comms', 'label' => 'modem media'],
    ['raw_text' => '-100 продление сайта клаудии', 'category_code' => 'media_comms', 'label' => 'Claudia site media'],
    ['raw_text' => '-100 отправка таксы в траст компанию сша', 'category_code' => 'admin_legal', 'label' => 'trust company tax admin'],
    ['raw_text' => '-100 air serbia', 'category_code' => 'transport_expenses', 'label' => 'airline transport'],
    ['raw_text' => '-100 запрака авто', 'category_code' => 'transport_expenses', 'label' => 'car refuel transport'],
    ['raw_text' => '-100 заправка арендованной авто', 'category_code' => 'transport_expenses', 'label' => 'rental car refuel transport'],
    ['raw_text' => '-100 парковка сплит', 'category_code' => 'transport_expenses', 'label' => 'parking transport'],
    ['raw_text' => '-100 шкампи', 'category_code' => 'provisions', 'label' => 'seafood provisions'],
    ['raw_text' => '-100 лангустины', 'category_code' => 'provisions', 'label' => 'langoustines provisions'],
    ['raw_text' => '-100 вода', 'category_code' => 'provisions', 'label' => 'plain water provisions'],
    ['raw_text' => '-100 вода на лодку', 'category_code' => 'provisions', 'label' => 'boat water provisions'],
    ['raw_text' => '-100 кофемашина', 'category_code' => 'interior', 'label' => 'kitchen appliance interior'],
    ['raw_text' => '-100 блендер', 'category_code' => 'interior', 'label' => 'blender interior'],
    ['raw_text' => '-100 печка-микроволновка', 'category_code' => 'interior', 'label' => 'microwave oven interior'],
    ['raw_text' => '-100 платный годовой прогноз погоды', 'category_code' => 'media_comms', 'label' => 'weather subscription media'],
    ['raw_text' => '-100 сувениры наталья', 'category_code' => 'representation_expenses', 'label' => 'souvenirs representation'],
    ['raw_text' => '-100 подарки службам нг', 'category_code' => 'representation_expenses', 'label' => 'service gifts representation'],
    ['raw_text' => '-100 украшения др', 'category_code' => 'representation_expenses', 'label' => 'birthday decorations representation'],
    ['raw_text' => '-100 зарядка шефу', 'category_code' => 'guest_trip_support', 'label' => 'chef charger guest support'],
    ['raw_text' => '-100 продолжение тур регистрации Данил', 'category_code' => 'admin_legal', 'label' => 'actor registration admin'],
    ['raw_text' => '-100 обновление морских сертифиткатов', 'category_code' => 'admin_legal', 'label' => 'marine certificates admin'],
    ['raw_text' => '-100 печати лодки и фирмы', 'category_code' => 'admin_legal', 'label' => 'boat and company stamps admin'],
    ['raw_text' => '-100 просрочка нахождения в турции женя', 'category_code' => 'admin_legal', 'label' => 'overstay admin'],
    ['raw_text' => '-100 черные танки', 'category_code' => 'service_water', 'label' => 'black tanks service'],
    ['raw_text' => '-100 откачка грязных вод', 'category_code' => 'service_water', 'label' => 'dirty water pumpout service'],
    ['raw_text' => '-100 петля хододильник', 'category_code' => 'tech_parts', 'label' => 'fridge hinge part'],
    ['raw_text' => '-100 Батарея для старой рст', 'category_code' => 'tech_parts', 'label' => 'radio battery part'],
    ['raw_text' => '-100 маркеры цепи', 'category_code' => 'tech_parts', 'label' => 'chain markers part'],
    ['raw_text' => '-100 щетка для лодки', 'category_code' => 'cleaning', 'label' => 'boat brush cleaning'],
    ['raw_text' => '-100 моющее средство палуба', 'category_code' => 'cleaning', 'label' => 'deck detergent cleaning'],
    ['raw_text' => '-100 хоз товары', 'category_code' => 'current_boat_expenses', 'label' => 'household goods current boat'],
    ['raw_text' => '-100 инвентарь', 'category_code' => 'current_boat_expenses', 'label' => 'inventory current boat'],
    ['raw_text' => '-100 принтер на лодку', 'category_code' => 'current_boat_expenses', 'label' => 'boat printer current boat'],
    ['raw_text' => '-100 работник в помощь', 'category_code' => 'crew', 'label' => 'temporary worker crew'],
];
foreach ($packageThreePreviews as $previewCase) {
    $casePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
        'flow_id' => $cashFlow['id'],
        'date' => '2026-07-05',
        'raw_text' => $previewCase['raw_text'],
    ]), $previewCase['label'] . ' parse preview')['preview'];
    smokeAssert(($casePreview['category_code'] ?? null) === $previewCase['category_code'], $previewCase['label'] . ' category mismatch');
}
$actorRegistrationPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 продолжение тур регистрации Данил',
]), 'actor registration semantic marker parse preview')['preview'];
smokeAssert(smokeSemanticMarkerHas($actorRegistrationPreview, 'actor_context'), 'actor registration should keep actor context marker');
$nonYachtPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 Аудио система для РФ - задаток',
]), 'non-yacht personal parse preview')['preview'];
smokeAssert(($nonYachtPreview['category_code'] ?? null) === null, 'non-yacht/personal context should not force yacht category');
smokeAssert(smokeSemanticMarkerHas($nonYachtPreview, 'non_yacht_or_personal'), 'non-yacht/personal context marker missing');
$privateSettlementPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 оплатил свои нужды с карты, положил кеш',
]), 'private settlement parse preview')['preview'];
smokeAssert(($privateSettlementPreview['category_code'] ?? null) === null, 'private settlement should not force category');
smokeAssert(smokeSemanticMarkerHas($privateSettlementPreview, 'money_movement'), 'private settlement marker missing');
smokeAssert(($privateSettlementPreview['accounting_section'] ?? null) === 'lower_accounting', 'private settlement should route to lower accounting');
smokeAssert(($privateSettlementPreview['settlement_counterparty'] ?? null) === 'Private/self settlement', 'private settlement counterparty mismatch');
$accountTransferPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 превод со счета на карту',
]), 'account transfer parse preview')['preview'];
smokeAssert(($accountTransferPreview['category_code'] ?? null) === null, 'account transfer should not force category');
smokeAssert(smokeSemanticMarkerHas($accountTransferPreview, 'money_movement'), 'account transfer marker missing');
$cogimarPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 Цоги мар, стояли на якоре и устрицы',
]), 'Cogimar review override parse preview')['preview'];
smokeAssert(($cogimarPreview['category_code'] ?? null) === 'other', 'Cogimar local term should stay review category');
smokeAssert(($cogimarPreview['status'] ?? null) === 'other_review', 'Cogimar local term should stay other review');
$iphonePreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 айфон',
]), 'iPhone other review parse preview')['preview'];
smokeAssert(($iphonePreview['category_code'] ?? null) === 'guest_trip_support', 'iPhone should become guest trip support by owner decision');
smokeAssert(($iphonePreview['status'] ?? null) !== 'other_review', 'iPhone should not stay other review after owner decision');
$tabletPreview = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/parse-preview", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 планшет на лодку',
]), 'tablet other review parse preview')['preview'];
smokeAssert(($tabletPreview['category_code'] ?? null) === 'other', 'tablet should stay other category');
smokeAssert(($tabletPreview['status'] ?? null) === 'other_review', 'tablet should stay other review');

$entriesAfterPreview = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/entries"), 'list entries after preview')['entries'];
smokeAssert(count($entriesAfterPreview) === count($entriesBeforePreview), 'parse preview persisted an entry');

$persistedAgent = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-50 агент',
]), 'persist weak agent entry')['entry'];
smokeAssert(($persistedAgent['sign'] ?? null) === '-', 'persisted agent sign');
smokeAssertAmount($persistedAgent['amount'] ?? null, 50.0, 'persisted agent amount');
smokeAssert(($persistedAgent['entry_type'] ?? null) === 'cash_expense', 'persisted agent entry type');
smokeAssert(($persistedAgent['category_code'] ?? null) === 'current_boat_expenses', 'persisted agent category');
smokeAssert(smokeSemanticMarkerHas($persistedAgent, 'weak_dictionary_context'), 'persisted agent weak marker');
smokeAssert(($persistedAgent['review_reason'] ?? null) === 'weak_only', 'persisted agent review reason');
smokeAssertAmount($persistedAgent['confidence'] ?? null, 0.48, 'persisted agent confidence');
smokeAssert(($persistedAgent['blockers'] ?? []) === [], 'persisted agent blockers');
$persistedDeliveryPart = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-15 доставка фильтра',
]), 'persist mixed delivery part entry')['entry'];
smokeAssertAmount($persistedDeliveryPart['amount'] ?? null, 15.0, 'persisted delivery part amount');
smokeAssert(($persistedDeliveryPart['entry_type'] ?? null) === 'cash_expense', 'persisted delivery part entry type');
smokeAssert(($persistedDeliveryPart['category_code'] ?? null) === 'tech_parts', 'persisted delivery part category');
smokeAssert(smokeSemanticMarkerHas($persistedDeliveryPart, 'mixed_dictionary_context'), 'persisted delivery part mixed marker');
smokeAssert(($persistedDeliveryPart['review_reason'] ?? null) === 'mixed_context', 'persisted delivery part review reason');
smokeAssertAmount($persistedDeliveryPart['confidence'] ?? null, 0.64, 'persisted delivery part confidence');
smokeAssert(($persistedDeliveryPart['blockers'] ?? []) === [], 'persisted delivery part blockers');
$persistedAgentMeal = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-55 обед с агентом',
]), 'persist agent meal entry')['entry'];
smokeAssertAmount($persistedAgentMeal['amount'] ?? null, 55.0, 'persisted agent meal amount');
smokeAssert(($persistedAgentMeal['entry_type'] ?? null) === 'cash_expense', 'persisted agent meal entry type');
smokeAssert(($persistedAgentMeal['category_code'] ?? null) === 'representation_expenses', 'persisted agent meal category');
smokeAssert(!smokeSemanticMarkerHas($persistedAgentMeal, 'weak_dictionary_context'), 'persisted agent meal should not expose weak marker');
smokeAssert(($persistedAgentMeal['review_reason'] ?? null) === null, 'persisted agent meal review reason');
smokeAssertAmount($persistedAgentMeal['confidence'] ?? null, 0.92, 'persisted agent meal confidence');
smokeAssert(($persistedAgentMeal['blockers'] ?? []) === [], 'persisted agent meal blockers');

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

$recalcWorkspace = expectOk(smokeRequest('POST', '/api/workspaces', [
    'name' => 'HTTP Smoke Recalculation Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'ru',
    'opening_cash' => '1000.00',
]), 'create recalculation workspace')['workspace'];
$recalcWorkspaceId = (string)$recalcWorkspace['id'];
$recalcFlows = expectOk(smokeRequest('GET', "/api/workspaces/{$recalcWorkspaceId}/flows"), 'list recalculation flows')['flows'];
$recalcCashFlow = null;
foreach ($recalcFlows as $flow) {
    if (($flow['type'] ?? '') === 'cash') {
        $recalcCashFlow = $flow;
        break;
    }
}
smokeAssert(is_array($recalcCashFlow), 'recalculation cash flow missing');
$recalcFirst = expectOk(smokeRequest('POST', "/api/workspaces/{$recalcWorkspaceId}/entries", [
    'flow_id' => $recalcCashFlow['id'],
    'date' => '2026-07-01',
    'raw_text' => '-100 fuel',
]), 'create recalculation first entry')['entry'];
$recalcThird = expectOk(smokeRequest('POST', "/api/workspaces/{$recalcWorkspaceId}/entries", [
    'flow_id' => $recalcCashFlow['id'],
    'date' => '2026-07-03',
    'raw_text' => '-100 food',
]), 'create recalculation third entry')['entry'];
$recalcSecond = expectOk(smokeRequest('POST', "/api/workspaces/{$recalcWorkspaceId}/entries", [
    'flow_id' => $recalcCashFlow['id'],
    'date' => '2026-07-02',
    'raw_text' => '+500 topup',
]), 'create recalculation middle entry')['entry'];
$recalcEntries = expectOk(smokeRequest('GET', "/api/workspaces/{$recalcWorkspaceId}/entries"), 'list recalculation entries')['entries'];
$recalcById = [];
foreach ($recalcEntries as $candidate) {
    $recalcById[(string)$candidate['id']] = $candidate;
}
smokeAssertAmount($recalcById[(string)$recalcFirst['id']]['balance_after'], 900.0, 'recalculation first balance after insert');
smokeAssertAmount($recalcById[(string)$recalcSecond['id']]['balance_after'], 1400.0, 'recalculation second balance after insert');
smokeAssertAmount($recalcById[(string)$recalcThird['id']]['balance_after'], 1300.0, 'recalculation third balance after insert');
expectOk(smokeRequest('PATCH', '/api/entries/' . $recalcThird['id'], [
    'flow_id' => $recalcCashFlow['id'],
    'date' => '2026-07-03',
    'raw_text' => '-200 food corrected',
]), 'update recalculation third entry');
$recalcAfterUpdate = expectOk(smokeRequest('GET', "/api/workspaces/{$recalcWorkspaceId}/entries"), 'list recalculation entries after update')['entries'];
$recalcById = [];
foreach ($recalcAfterUpdate as $candidate) {
    $recalcById[(string)$candidate['id']] = $candidate;
}
smokeAssertAmount($recalcById[(string)$recalcThird['id']]['balance_after'], 1200.0, 'recalculation third balance after update');
smokeAssertAmount(expectOk(smokeRequest('GET', "/api/workspaces/{$recalcWorkspaceId}/summary"), 'recalculation summary after update')['summary']['cash_now'], 1200.0, 'recalculation cash now after update');
expectOk(smokeRequest('DELETE', '/api/entries/' . $recalcSecond['id']), 'delete recalculation middle entry');
$recalcAfterDelete = expectOk(smokeRequest('GET', "/api/workspaces/{$recalcWorkspaceId}/entries"), 'list recalculation entries after delete')['entries'];
$recalcById = [];
foreach ($recalcAfterDelete as $candidate) {
    $recalcById[(string)$candidate['id']] = $candidate;
}
smokeAssert(!isset($recalcById[(string)$recalcSecond['id']]), 'recalculation deleted middle entry still visible');
smokeAssertAmount($recalcById[(string)$recalcThird['id']]['balance_after'], 700.0, 'recalculation third balance after delete');
smokeAssertAmount(expectOk(smokeRequest('GET', "/api/workspaces/{$recalcWorkspaceId}/summary"), 'recalculation summary after delete')['summary']['cash_now'], 700.0, 'recalculation cash now after delete');

$reportWorkspace = expectOk(smokeRequest('POST', '/api/workspaces', [
    'name' => 'HTTP Smoke Report Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'ru',
    'opening_cash' => '1000.00',
]), 'create report workspace')['workspace'];
$reportWorkspaceId = (string)$reportWorkspace['id'];
$reportFlows = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/flows"), 'list report flows')['flows'];
$reportCashFlow = null;
$reportCardFlow = null;
foreach ($reportFlows as $flow) {
    if (($flow['type'] ?? '') === 'cash') {
        $reportCashFlow = $flow;
    }
    if (($flow['type'] ?? '') === 'card') {
        $reportCardFlow = $flow;
    }
}
smokeAssert(is_array($reportCashFlow), 'report cash flow missing');
smokeAssert(is_array($reportCardFlow), 'report card flow missing');

$reportPriorTopup = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-06-30',
    'raw_text' => '+200 prior month topup',
]), 'create report prior month cash income')['entry'];
$reportExternal = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-07-01',
    'raw_text' => '+300 private topup',
]), 'create report external cash income')['entry'];
$reportCommercial = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-07-02',
    'raw_text' => '+5000 charter deposit',
]), 'create report commercial income')['entry'];
$reportFuel = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-07-03',
    'raw_text' => '-200 fuel',
]), 'create report fuel expense')['entry'];
$reportOther = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-07-04',
    'raw_text' => '-50 какая-то штука',
]), 'create report other review')['entry'];
$reportCardTopup = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCardFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-1000 снял с карты',
    'category_code' => 'cash_topup_from_card',
]), 'create report card topup side')['entry'];
$reportCashTopup = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+1000 снял с карты',
    'category_code' => 'cash_topup_from_card',
]), 'create report cash topup side')['entry'];
$reportCardMedia = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCardFlow['id'],
    'date' => '2026-07-06',
    'raw_text' => '-60 Netflix',
]), 'create report card media expense')['entry'];
$reportInvalid = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-07-07',
    'raw_text' => '250 ignored no sign',
    'amount' => '250.00',
    'status' => 'recognized',
]), 'create report invalid no-sign row')['entry'];

smokeAssert($reportExternal['category_code'] === 'non_commercial_income', 'external cash income category mismatch');
smokeAssert($reportCommercial['category_code'] === 'commercial_income', 'commercial report category mismatch');
smokeAssert(($reportExternal['review_reason'] ?? null) === null, 'external report income review reason');
smokeAssert(($reportCommercial['review_reason'] ?? null) === null, 'commercial report income review reason');
smokeAssertAmount($reportExternal['confidence'] ?? null, 0.92, 'external report income confidence');
smokeAssertAmount($reportCommercial['confidence'] ?? null, 0.92, 'commercial report income confidence');
smokeAssert($reportFuel['category_code'] === 'fuel', 'fuel report category mismatch');
smokeAssert($reportOther['category_code'] === 'other' && $reportOther['status'] === 'other_review', 'other report row mismatch');
smokeAssert($reportCardTopup['category_code'] === 'cash_topup_from_card', 'card topup category mismatch');
smokeAssert($reportCashTopup['category_code'] === 'cash_topup_from_card', 'cash topup category mismatch');
smokeAssert($reportCardMedia['category_code'] === 'media_comms', 'card media category mismatch');
smokeAssert($reportInvalid['status'] === 'unrecognized' && $reportInvalid['amount'] === null, 'invalid no-sign row should not be counted');

$closedReportMonth = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/months/2026/7/close", [
    'comment' => 'report smoke closed month',
]), 'close report month');
smokeAssert(($closedReportMonth['closure']['is_closed'] ?? null) === true, 'close route closure flag mismatch');
smokeAssert(($closedReportMonth['report']['is_closed'] ?? null) === true, 'close route report flag mismatch');
smokeAssert(($closedReportMonth['report']['comment'] ?? null) === 'report smoke closed month', 'close route report comment mismatch');
smokeAssert(smokeAuditCount('month_close', (string)$closedReportMonth['closure']['id']) === 1, 'month close audit missing');
$monthlyReport = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/monthly?year=2026&month=7"), 'monthly report')['report'];
smokeAssert($monthlyReport['month_key'] === '2026-07', 'monthly report key mismatch');
smokeAssert($monthlyReport['is_closed'] === true, 'monthly report should expose closed month');
smokeAssert($monthlyReport['comment'] === 'report smoke closed month', 'monthly report comment mismatch');
smokeAssertAmount($monthlyReport['opening_cash'], 1200.0, 'monthly opening cash');
smokeAssertAmount($monthlyReport['external_cash_income'], 300.0, 'monthly external cash income');
smokeAssertAmount($monthlyReport['commercial_income'], 5000.0, 'monthly commercial income');
smokeAssertAmount($monthlyReport['cash_expense'], 250.0, 'monthly cash expense');
smokeAssertAmount($monthlyReport['card_expense'], 1060.0, 'monthly card expense');
smokeAssertAmount($monthlyReport['cash_topup_from_card_card_side'], 1000.0, 'monthly card topup side');
smokeAssertAmount($monthlyReport['cash_topup_from_card_cash_side'], 1000.0, 'monthly cash topup side');
smokeAssertAmount($monthlyReport['other_expenses'], 50.0, 'monthly other expenses');
smokeAssertAmount($monthlyReport['ending_cash'], 7250.0, 'monthly ending cash');
smokeAssert(($monthlyReport['counts']['entries'] ?? null) === 8, 'monthly entries count mismatch');
smokeAssert(($monthlyReport['counts']['counted'] ?? null) === 7, 'monthly counted count mismatch');
smokeAssert(($monthlyReport['counts']['unrecognized'] ?? null) === 1, 'monthly unrecognized count mismatch');
smokeAssert(($monthlyReport['counts']['other_review'] ?? null) === 1, 'monthly other_review count mismatch');

$layer1Summary = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-summary?year=2026&month=7"), 'layer1 summary report')['report'];
smokeAssert(($layer1Summary['header']['workspace']['id'] ?? null) === $reportWorkspaceId, 'layer1 workspace id mismatch');
smokeAssert(($layer1Summary['header']['period']['month_key'] ?? null) === '2026-07', 'layer1 period key mismatch');
smokeAssert(($layer1Summary['header']['currency'] ?? null) === 'EUR', 'layer1 currency mismatch');
smokeAssert(($layer1Summary['header']['status'] ?? null) === 'closed', 'layer1 status mismatch');
smokeAssert(($layer1Summary['header']['entries_count'] ?? null) === 8, 'layer1 entries count mismatch');
smokeAssert(($layer1Summary['header']['review_count'] ?? null) === 1, 'layer1 review count mismatch');
smokeAssertAmount($layer1Summary['totals']['opening_cash'], 1200.0, 'layer1 opening cash');
smokeAssertAmount($layer1Summary['totals']['cash_income'], 300.0, 'layer1 cash income');
smokeAssertAmount($layer1Summary['totals']['cash_expense'], 250.0, 'layer1 cash expense');
smokeAssertAmount($layer1Summary['totals']['card_expense'], 1060.0, 'layer1 card expense');
smokeAssertAmount($layer1Summary['totals']['commercial_income'], 5000.0, 'layer1 commercial income');
smokeAssertAmount($layer1Summary['totals']['other_review_total'], 50.0, 'layer1 other review total');
smokeAssertAmount($layer1Summary['totals']['corrections_total'], 0.0, 'layer1 corrections total');
smokeAssertAmount($layer1Summary['totals']['ending_cash'], 7250.0, 'layer1 ending cash');
smokeAssert(($layer1Summary['blocks']['card']['entries_count'] ?? null) === 2, 'layer1 card entry count mismatch');
smokeAssert(($layer1Summary['blocks']['other_review']['count'] ?? null) === 1, 'layer1 other review block count mismatch');
smokeAssert((string)($layer1Summary['blocks']['other_review']['entries'][0]['id'] ?? '') === (string)$reportOther['id'], 'layer1 other review entry mismatch');

$layer1TraceTotals = $layer1Summary['source_trace']['totals'] ?? [];
$layer1OpeningBasis = $layer1Summary['source_trace']['basis']['opening_cash'] ?? null;
smokeAssert(is_array($layer1OpeningBasis), 'layer1 opening cash basis missing');
smokeAssert(($layer1OpeningBasis['type'] ?? null) === 'cash_flow_opening_balance_plus_prior_entries', 'layer1 opening cash basis type mismatch');
smokeAssert((string)($layer1OpeningBasis['flow_id'] ?? '') === (string)$reportCashFlow['id'], 'layer1 opening cash basis flow mismatch');
smokeAssertAmount($layer1OpeningBasis['flow_opening_balance'] ?? null, 1000.0, 'layer1 opening cash basis flow opening balance');
smokeAssertAmount($layer1OpeningBasis['prior_cash_delta'] ?? null, 200.0, 'layer1 opening cash basis prior delta');
smokeAssertAmount($layer1OpeningBasis['total'] ?? null, 1200.0, 'layer1 opening cash basis total');
smokeAssertEntryId($layer1OpeningBasis['prior_entry_ids'] ?? [], (string)$reportPriorTopup['id'], 'layer1 opening cash basis prior entry missing');
smokeAssertEntryId($layer1TraceTotals['opening_cash'] ?? [], (string)$reportPriorTopup['id'], 'layer1 opening cash source missing prior topup');
smokeAssertEntryId($layer1TraceTotals['cash_income'] ?? [], (string)$reportExternal['id'], 'layer1 cash income source missing external entry');
smokeAssertEntryId($layer1TraceTotals['commercial_income'] ?? [], (string)$reportCommercial['id'], 'layer1 commercial source missing commercial entry');
smokeAssertEntryId($layer1TraceTotals['cash_expense'] ?? [], (string)$reportFuel['id'], 'layer1 cash expense source missing fuel entry');
smokeAssertEntryId($layer1TraceTotals['cash_expense'] ?? [], (string)$reportOther['id'], 'layer1 cash expense source missing other entry');
smokeAssertEntryId($layer1TraceTotals['card_expense'] ?? [], (string)$reportCardTopup['id'], 'layer1 card expense source missing topup entry');
smokeAssertEntryId($layer1TraceTotals['card_expense'] ?? [], (string)$reportCardMedia['id'], 'layer1 card expense source missing media entry');
smokeAssertEntryId($layer1TraceTotals['other_review_total'] ?? [], (string)$reportOther['id'], 'layer1 other review source missing other entry');
smokeAssert(count($layer1TraceTotals['corrections_total'] ?? []) === 0, 'layer1 corrections source should be empty before corrections');
smokeAssertEntryId($layer1TraceTotals['ending_cash'] ?? [], (string)$reportCashTopup['id'], 'layer1 ending cash source missing cash topup entry');

$layer1CategoryRows = [];
foreach ($layer1Summary['blocks']['categories']['rows'] as $row) {
    $layer1CategoryRows[$row['category_code']] = $row;
}
smokeAssert(isset($layer1CategoryRows['non_commercial_income'], $layer1CategoryRows['cash_topup_from_card'], $layer1CategoryRows['fuel'], $layer1CategoryRows['media_comms']), 'layer1 category rows missing expected categories');
smokeAssertAmount($layer1CategoryRows['non_commercial_income']['cash_total'], 300.0, 'layer1 category non-commercial income cash total');
smokeAssertAmount($layer1CategoryRows['cash_topup_from_card']['cash_total'], 1000.0, 'layer1 category topup cash total');
smokeAssertAmount($layer1CategoryRows['cash_topup_from_card']['card_total'], 1000.0, 'layer1 category topup card total');
smokeAssertAmount($layer1CategoryRows['cash_topup_from_card']['total'], 2000.0, 'layer1 category topup total');
smokeAssertAmount($layer1CategoryRows['fuel']['cash_total'], 200.0, 'layer1 category fuel cash total');
smokeAssertAmount($layer1CategoryRows['media_comms']['card_total'], 60.0, 'layer1 category media card total');
smokeAssertEntryId($layer1Summary['source_trace']['categories']['cash_topup_from_card'] ?? [], (string)$reportCardTopup['id'], 'layer1 category source missing card topup');
smokeAssertEntryId($layer1Summary['source_trace']['categories']['cash_topup_from_card'] ?? [], (string)$reportCashTopup['id'], 'layer1 category source missing cash topup');

$lowerWorkspace = expectOk(smokeRequest('POST', '/api/workspaces', [
    'name' => 'HTTP Lower Accounting Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'ru',
    'opening_cash' => '1000.00',
]), 'create lower accounting workspace')['workspace'];
$lowerWorkspaceId = (string)$lowerWorkspace['id'];
$lowerFlows = expectOk(smokeRequest('GET', "/api/workspaces/{$lowerWorkspaceId}/flows"), 'list lower accounting flows')['flows'];
$lowerCashFlow = null;
foreach ($lowerFlows as $flow) {
    if (($flow['type'] ?? '') === 'cash') {
        $lowerCashFlow = $flow;
    }
}
smokeAssert(is_array($lowerCashFlow), 'lower accounting cash flow missing');
$lowerCredit = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-1000 мой кредит',
]), 'create lower credit row')['entry'];
$lowerDebtGarage = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-250 долг за гараж',
]), 'create lower debt garage row')['entry'];
$lowerVovaIssue = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-200 Вова под отчет',
]), 'create lower Vova issue row')['entry'];
$lowerVovaReturn = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+50 Вова вернул остаток',
]), 'create lower Vova return row')['entry'];
$lowerAccountable = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-300 Женя под отчет',
]), 'create lower accountable row')['entry'];
$lowerDanilIssue = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-120 Данил под отчет',
]), 'create lower Danil issue row')['entry'];
$lowerDanilReturn = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+120 Данил вернул остаток',
]), 'create lower Danil return row')['entry'];
$lowerGuestCash = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 передал ЛВ',
]), 'create lower guest cash row')['entry'];
$lowerGarage = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 гараж',
]), 'create operational garage row')['entry'];
$lowerGift = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 подарок Алине',
]), 'create operational gift row')['entry'];
$lowerLawyer = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 адвокат',
]), 'create operational lawyer row')['entry'];
$lowerCable = expectOk(smokeRequest('POST', "/api/workspaces/{$lowerWorkspaceId}/entries", [
    'flow_id' => $lowerCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-80 Вова купил кабель',
]), 'create operational Vova cable row')['entry'];

foreach ([$lowerVovaIssue, $lowerAccountable, $lowerDanilIssue] as $lowerEntry) {
    smokeAssert(($lowerEntry['accounting_section'] ?? null) === 'lower_accounting', 'lower entry section mismatch: ' . ($lowerEntry['raw_text'] ?? ''));
}
smokeAssert(($lowerCredit['accounting_section'] ?? null) === 'admin_debt', 'personal credit should route to administrator debt');
foreach ([$lowerDebtGarage, $lowerVovaReturn, $lowerDanilReturn, $lowerGuestCash, $lowerGarage, $lowerGift, $lowerLawyer, $lowerCable] as $operationalEntry) {
    smokeAssert(($operationalEntry['accounting_section'] ?? null) === 'operational', 'operational control section mismatch: ' . ($operationalEntry['raw_text'] ?? ''));
}

$lowerMonthly = expectOk(smokeRequest('GET', "/api/workspaces/{$lowerWorkspaceId}/reports/monthly?year=2026&month=7"), 'lower monthly report')['report'];
smokeAssertAmount($lowerMonthly['cash_expense'], 2350.0, 'lower monthly physical cash expense');
smokeAssertAmount($lowerMonthly['external_cash_income'], 170.0, 'lower monthly physical cash income');
smokeAssertAmount($lowerMonthly['ending_cash'], -1180.0, 'lower monthly ending cash');

$lowerLayer1 = expectOk(smokeRequest('GET', "/api/workspaces/{$lowerWorkspaceId}/reports/layer1-summary?year=2026&month=7"), 'lower layer1 summary')['report'];
smokeAssert(($lowerLayer1['blocks']['lower_accounting']['count'] ?? null) === 3, 'lower layer1 block count');
smokeAssertAmount($lowerLayer1['blocks']['lower_accounting']['total'] ?? null, 620.0, 'lower layer1 block total');
foreach ([$lowerVovaIssue, $lowerAccountable, $lowerDanilIssue] as $lowerEntry) {
    smokeAssertEntryId($lowerLayer1['blocks']['lower_accounting']['source_entry_ids'] ?? [], (string)$lowerEntry['id'], 'lower layer1 source missing ' . ($lowerEntry['raw_text'] ?? ''));
}
smokeAssertAmount($lowerLayer1['blocks']['admin_debt']['total'] ?? null, 1000.0, 'admin debt total should include personal credit');
smokeAssertEntryId($lowerLayer1['blocks']['admin_debt']['source_entry_ids'] ?? [], (string)$lowerCredit['id'], 'admin debt source missing personal credit row');
$lowerCategoryRows = [];
foreach ($lowerLayer1['blocks']['categories']['rows'] as $row) {
    $lowerCategoryRows[$row['category_code']] = $row;
}
smokeAssertAmount($lowerCategoryRows['berth']['cash_total'] ?? null, 350.0, 'lower layer1 berth should keep concrete garage debt operational');
smokeAssertAmount($lowerCategoryRows['representation_expenses']['cash_total'] ?? null, 100.0, 'lower layer1 representation control');
smokeAssertAmount($lowerCategoryRows['admin_legal']['cash_total'] ?? null, 100.0, 'lower layer1 admin control');
smokeAssertAmount($lowerCategoryRows['tech_parts']['cash_total'] ?? null, 80.0, 'lower layer1 tech parts control should keep Vova cable operational');
smokeAssertAmount($lowerCategoryRows['guest_cash_issued']['cash_total'] ?? null, 100.0, 'lower layer1 guest cash should remain operational category row');
smokeAssertNoEntryId($lowerLayer1['source_trace']['totals']['lower_accounting_total'] ?? [], (string)$lowerCredit['id'], 'lower source trace should not include personal credit row');
smokeAssertEntryId($lowerLayer1['source_trace']['totals']['admin_debt_total'] ?? [], (string)$lowerCredit['id'], 'admin debt source trace missing personal credit row');
$lowerSettlements = [];
foreach (($lowerLayer1['blocks']['lower_accounting']['settlements']['by_counterparty'] ?? []) as $settlementRow) {
    $lowerSettlements[(string)$settlementRow['counterparty']] = $settlementRow;
}
smokeAssert(($lowerSettlements['Вова']['status'] ?? null) === 'open', 'Vova settlement should be open until return is explicitly linked');
smokeAssertAmount($lowerSettlements['Вова']['open_amount'] ?? null, 200.0, 'Vova settlement open amount');
smokeAssertEntryId($lowerSettlements['Вова']['source_entry_ids'] ?? [], (string)$lowerVovaIssue['id'], 'Vova settlement missing issue row');
smokeAssertNoEntryId($lowerSettlements['Вова']['source_entry_ids'] ?? [], (string)$lowerVovaReturn['id'], 'Vova operational return row should not auto-close lower accounting');
smokeAssertNoEntryId($lowerSettlements['Вова']['source_entry_ids'] ?? [], (string)$lowerCable['id'], 'Vova settlement should not include operational cable row');
smokeAssert(($lowerSettlements['Женя']['status'] ?? null) === 'open', 'Jenya settlement should be open');
smokeAssertAmount($lowerSettlements['Женя']['open_amount'] ?? null, 300.0, 'Jenya settlement open amount');
smokeAssert(($lowerSettlements['Данил']['status'] ?? null) === 'open', 'Danil settlement should be open until return is explicitly linked');
smokeAssertAmount($lowerSettlements['Данил']['open_amount'] ?? null, 120.0, 'Danil settlement open amount');
$vovaSettlementIds = implode(',', array_map('strval', $lowerSettlements['Вова']['source_entry_ids'] ?? []));
$vovaSettlementSource = expectOk(smokeRequest('GET', "/api/workspaces/{$lowerWorkspaceId}/reports/layer1-source-entries?ids={$vovaSettlementIds}"), 'Vova settlement source entries');
smokeAssert(count($vovaSettlementSource['entries'] ?? []) === 1, 'Vova settlement source entries count');
$vovaSettlementRaw = implode("\n", array_map(static fn (array $row): string => (string)($row['raw_text'] ?? ''), $vovaSettlementSource['entries']));
smokeAssert(str_contains($vovaSettlementRaw, '-200 Вова под отчет'), 'Vova settlement source missing issue raw text');
smokeAssert(!str_contains($vovaSettlementRaw, '+50 Вова вернул остаток'), 'Vova settlement source should not include operational return raw text');
smokeAssert(!str_contains($vovaSettlementRaw, '-80 Вова купил кабель'), 'Vova settlement source should exclude operational cable row');

expectError(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-source-entries?ids={$reportPriorTopup['id']}", null, false), 401, 'not_authenticated', 'unauthenticated layer1 source entries');
expectError(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-source-entries?ids=not-a-uuid"), 422, 'invalid_ids', 'invalid layer1 source ids');
$layer1SourceEntries = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-source-entries?ids={$reportPriorTopup['id']},{$reportFuel['id']},{$entry['id']}"), 'layer1 source entries');
smokeAssert(count($layer1SourceEntries['entries']) === 2, 'layer1 source entries should return only workspace scoped rows');
smokeAssert((string)$layer1SourceEntries['entries'][0]['id'] === (string)$reportPriorTopup['id'], 'layer1 source entries did not preserve request order for prior source');
smokeAssert((string)$layer1SourceEntries['entries'][0]['raw_text'] === '+200 prior month topup', 'layer1 source entries missing prior raw text');
smokeAssert((string)$layer1SourceEntries['entries'][1]['id'] === (string)$reportFuel['id'], 'layer1 source entries did not preserve request order for current source');
smokeAssertEntryId($layer1SourceEntries['missing_ids'] ?? [], (string)$entry['id'], 'layer1 source entries should hide cross-workspace id as missing');

smokeAddWorkspaceMember($reportWorkspaceId, 19002, 'viewer');
$viewerLayer1SourceEntries = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-source-entries?ids={$reportPriorTopup['id']}", null, true, $viewerToken), 'viewer layer1 source entries');
smokeAssert((string)$viewerLayer1SourceEntries['entries'][0]['id'] === (string)$reportPriorTopup['id'], 'viewer should read source entries');

$operationalFragmentOne = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/operational-fragments", [
    'title' => 'Smoke closed fragment fuel',
    'entry_ids' => [$reportFuel['id']],
    'closed_date' => '2026-07-08',
]), 'create closed operational fragment one')['fragment'];
$operationalFragmentTwo = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/operational-fragments", [
    'title' => 'Smoke closed fragment card media',
    'entry_ids' => [$reportCardMedia['id']],
    'closed_date' => '2026-07-08',
]), 'create closed operational fragment two')['fragment'];
$operationalOpenFragment = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/operational-fragments", [
    'title' => 'Smoke open fragment other review',
    'entry_ids' => [$reportOther['id']],
]), 'create open operational fragment')['fragment'];
smokeAssert(!empty($operationalFragmentOne['closed_at']) && !empty($operationalFragmentTwo['closed_at']), 'closed operational fragments should expose closed_at');

$fragmentHtmlSnapshot = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/operational-fragments/{$operationalFragmentOne['id']}/html-snapshots", [
    'comment' => 'explicit smoke html snapshot',
]), 'create operational fragment html snapshot')['snapshot'];
smokeAssert(($fragmentHtmlSnapshot['batch_id'] ?? null) === $operationalFragmentOne['id'], 'fragment html snapshot batch mismatch');
smokeAssert(($fragmentHtmlSnapshot['version'] ?? 0) >= 1, 'fragment html snapshot version mismatch');
smokeAssert(is_string($fragmentHtmlSnapshot['html_hash'] ?? null) && strlen((string)$fragmentHtmlSnapshot['html_hash']) === 64, 'fragment html snapshot hash mismatch');
$fragmentHtmlSnapshotReadback = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/operational-fragments/{$operationalFragmentOne['id']}/html-snapshots/{$fragmentHtmlSnapshot['id']}"), 'read operational fragment html snapshot')['snapshot'];
smokeAssert(str_contains((string)($fragmentHtmlSnapshotReadback['html_content'] ?? ''), '<!doctype html>'), 'fragment html snapshot content missing html document');

expectError(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/operational-packages", [
    'fragment_ids' => [$operationalFragmentOne['id'], $operationalOpenFragment['id']],
]), 422, 'report_package_requires_closed_fragments', 'operational package should require closed fragments');
expectError(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/operational-packages", [
    'fragment_ids' => [$operationalFragmentOne['id'], $operationalFragmentTwo['id']],
], true, $viewerToken), 403, 'workspace_read_only', 'viewer create operational package');
$operationalPackage = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/operational-packages", [
    'title' => 'Smoke operational package',
    'fragment_ids' => [$operationalFragmentOne['id'], $operationalFragmentTwo['id']],
    'comment' => 'closed fragment package smoke',
]), 'create operational package')['package'];
smokeAssert(($operationalPackage['package_type'] ?? null) === 'operational_fragment_package', 'operational package type mismatch');
smokeAssert(($operationalPackage['fragment_count'] ?? null) === 2, 'operational package fragment count mismatch');
smokeAssert(count($operationalPackage['items'] ?? []) === 2, 'operational package items missing');
smokeAssertEntryId($operationalPackage['source_entry_ids'] ?? [], (string)$reportFuel['id'], 'operational package source ids missing fuel');
smokeAssertEntryId($operationalPackage['source_entry_ids'] ?? [], (string)$reportCardMedia['id'], 'operational package source ids missing card media');
smokeAssert(!empty($operationalPackage['items'][0]['html_snapshot']['id'] ?? null), 'operational package item html snapshot missing');
$operationalPackages = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/operational-packages"), 'list operational packages')['packages'];
smokeAssert((string)($operationalPackages[0]['id'] ?? '') === (string)$operationalPackage['id'], 'operational package list latest mismatch');
$operationalPackageHtml = smokePageRequest('/v2-report.php?type=package&id=' . rawurlencode((string)$operationalPackage['id']));
smokeAssert($operationalPackageHtml->status === 200, "operational package html expected HTTP 200, got {$operationalPackageHtml->status}: " . substr($operationalPackageHtml->raw, 0, 1000));
smokeAssert(str_contains($operationalPackageHtml->raw, 'Smoke operational package'), 'operational package html missing title');
smokeAssert(str_contains($operationalPackageHtml->raw, 'Категории'), 'operational package html missing categories');
smokeAssert(str_contains($operationalPackageHtml->raw, 'Фрагменты'), 'operational package html missing fragments');
smokeAssert(str_contains($operationalPackageHtml->raw, 'Smoke closed fragment fuel'), 'operational package html missing fragment title');
smokeAssert(str_contains($operationalPackageHtml->raw, '-200 fuel'), 'operational package html missing source entry');

expectError(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-snapshots", [
    'year' => 2026,
    'month' => 7,
], true, null, false), 403, 'csrf_required', 'layer1 snapshot missing csrf');
expectError(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-snapshots", [
    'year' => 2026,
    'month' => 7,
], true, $viewerToken), 403, 'workspace_read_only', 'viewer create layer1 snapshot');
$layer1Snapshot = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-snapshots", [
    'year' => 2026,
    'month' => 7,
    'comment' => 'first immutable layer1 smoke snapshot',
]), 'create layer1 snapshot')['snapshot'];
smokeAssert(($layer1Snapshot['report_type'] ?? null) === 'layer1_summary', 'layer1 snapshot report type mismatch');
smokeAssert(($layer1Snapshot['status'] ?? null) === 'closed', 'layer1 snapshot should inherit closed period status');
smokeAssert(($layer1Snapshot['version'] ?? null) === 1, 'layer1 snapshot first version mismatch');
smokeAssert(($layer1Snapshot['summary']['header']['period']['month_key'] ?? null) === '2026-07', 'layer1 snapshot summary period mismatch');
smokeAssertAmount($layer1Snapshot['summary']['totals']['ending_cash'], 7250.0, 'layer1 snapshot ending cash');
smokeAssertAmount($layer1Snapshot['summary']['source_trace']['basis']['opening_cash']['flow_opening_balance'] ?? null, 1000.0, 'layer1 snapshot opening basis');
smokeAssertEntryId($layer1Snapshot['source_entry_ids'] ?? [], (string)$reportPriorTopup['id'], 'layer1 snapshot source ids missing prior source');
smokeAssertEntryId($layer1Snapshot['source_entry_ids'] ?? [], (string)$reportFuel['id'], 'layer1 snapshot source ids missing fuel source');
smokeAssert(!in_array((string)$reportCashFlow['id'], array_map('strval', $layer1Snapshot['source_entry_ids'] ?? []), true), 'layer1 snapshot source ids must not include opening basis flow id');
smokeAssert(is_string($layer1Snapshot['content_hash'] ?? null) && strlen((string)$layer1Snapshot['content_hash']) === 64, 'layer1 snapshot hash mismatch');
smokeAssert(smokeAuditCount('layer1_snapshot_create', (string)$layer1Snapshot['id']) === 1, 'layer1 snapshot audit missing');
$viewerSnapshots = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-snapshots?year=2026&month=7", null, true, $viewerToken), 'viewer list layer1 snapshots')['snapshots'];
smokeAssert(count($viewerSnapshots) === 1, 'viewer layer1 snapshot list count mismatch');
smokeAssert((string)$viewerSnapshots[0]['id'] === (string)$layer1Snapshot['id'], 'viewer layer1 snapshot id mismatch');

$reportMonthCorrection = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/months/2026/7/correction", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-07-08',
    'raw_text' => '+5 report snapshot correction',
    'reason' => 'Layer 1 snapshot revision smoke',
    'reference_entry_id' => $reportFuel['id'],
    'source_type' => 'manual',
    'status' => 'recognized',
    'entry_type' => 'cash_income',
]), 'layer1 snapshot correction')['entry'];
$snapshotsAfterCorrection = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-snapshots?year=2026&month=7"), 'layer1 snapshots after correction')['snapshots'];
smokeAssert(count($snapshotsAfterCorrection) === 1, 'layer1 snapshot v1 should remain single before v2 create');
smokeAssert((string)$snapshotsAfterCorrection[0]['id'] === (string)$layer1Snapshot['id'], 'layer1 snapshot v1 id changed after correction');
smokeAssert((string)$snapshotsAfterCorrection[0]['content_hash'] === (string)$layer1Snapshot['content_hash'], 'layer1 snapshot v1 hash changed after correction');
smokeAssertAmount($snapshotsAfterCorrection[0]['summary']['totals']['ending_cash'], 7250.0, 'layer1 snapshot v1 ending cash changed');
$layer1SnapshotV2 = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/reports/layer1-snapshots", [
    'year' => 2026,
    'month' => 7,
    'comment' => 'second immutable layer1 smoke snapshot after correction',
]), 'create layer1 snapshot v2')['snapshot'];
smokeAssert(($layer1SnapshotV2['version'] ?? null) === 2, 'layer1 snapshot second version mismatch');
smokeAssert((string)$layer1SnapshotV2['id'] !== (string)$layer1Snapshot['id'], 'layer1 snapshot v2 id should differ');
smokeAssert((string)$layer1SnapshotV2['content_hash'] !== (string)$layer1Snapshot['content_hash'], 'layer1 snapshot v2 hash should differ');
smokeAssertAmount($layer1SnapshotV2['summary']['totals']['corrections_total'], 5.0, 'layer1 snapshot v2 corrections total');
smokeAssertAmount($layer1SnapshotV2['summary']['totals']['ending_cash'], 7255.0, 'layer1 snapshot v2 ending cash');
smokeAssertEntryId($layer1SnapshotV2['correction_ids'] ?? [], (string)$reportMonthCorrection['id'], 'layer1 snapshot v2 correction ids missing correction');
smokeAssertEntryId($layer1SnapshotV2['source_entry_ids'] ?? [], (string)$reportMonthCorrection['id'], 'layer1 snapshot v2 source ids missing correction');

$reopenedReportMonth = expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/months/2026/7/reopen", [
    'comment' => '',
]), 'reopen report month');
smokeAssert(($reopenedReportMonth['closure']['is_closed'] ?? null) === false, 'reopen route closure flag mismatch');
smokeAssert(($reopenedReportMonth['report']['is_closed'] ?? null) === false, 'reopen route report flag mismatch');
smokeAssert(array_key_exists('comment', $reopenedReportMonth['report']) && $reopenedReportMonth['report']['comment'] === null, 'reopen should clear report comment');
smokeAssert(smokeAuditCount('month_reopen', (string)$closedReportMonth['closure']['id']) === 1, 'month reopen audit missing');

$categoryMatrix = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/category-matrix?year=2026"), 'category matrix report')['matrix'];
$matrixRows = [];
foreach ($categoryMatrix['rows'] as $row) {
    $matrixRows[$row['category_code']] = $row;
}
smokeAssert(isset($matrixRows['fuel'], $matrixRows['commercial_income'], $matrixRows['non_commercial_income'], $matrixRows['cash_topup_from_card'], $matrixRows['other'], $matrixRows['media_comms']), 'category matrix missing required rows');
smokeAssertAmount($matrixRows['fuel']['months']['7'], 200.0, 'matrix fuel July');
smokeAssertAmount($matrixRows['commercial_income']['months']['7'], 5000.0, 'matrix commercial July');
smokeAssertAmount($matrixRows['non_commercial_income']['months']['7'], 300.0, 'matrix non-commercial income July');
smokeAssertAmount($matrixRows['cash_topup_from_card']['months']['7'], 2000.0, 'matrix card-to-cash July');
smokeAssertAmount($matrixRows['other']['months']['7'], 50.0, 'matrix other July');
smokeAssertAmount($matrixRows['media_comms']['months']['7'], 60.0, 'matrix media July');
smokeAssert(($matrixRows['cash_topup_from_card']['breakdown']['7']['card:out'] ?? null) !== null, 'matrix topup card side missing');
smokeAssert(($matrixRows['cash_topup_from_card']['breakdown']['7']['cash:in'] ?? null) !== null, 'matrix topup cash side missing');

$otherReviewReport = expectOk(smokeRequest('GET', "/api/workspaces/{$reportWorkspaceId}/reports/other-review"), 'other review report')['report'];
smokeAssert($otherReviewReport['count'] === 1, 'other review report count mismatch');
smokeAssertAmount($otherReviewReport['total'], 50.0, 'other review report total');
smokeAssert((string)$otherReviewReport['entries'][0]['id'] === (string)$reportOther['id'], 'other review report entry mismatch');

$importWorkspace = expectOk(smokeRequest('POST', '/api/workspaces', [
    'name' => 'HTTP Smoke Import Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'ru',
    'opening_cash' => '1000.00',
]), 'create import workspace')['workspace'];
$importWorkspaceId = (string)$importWorkspace['id'];
$xlsxPath = smokeCreateXlsx([
    ['дата', 'Описание платежа', 'Приход КЕШ', 'Расход КЕШ', 'Исполнитель', 'Приход КАРТА', 'Расход КАРТА', 'Сводные данные'],
    ['', 'private topup', '300', '', '', '', '', ''],
    ['', 'fuel marina', '', '200', '', '', '', ''],
    ['2026-07-02', 'charter deposit', '5000', '', '', '', '', ''],
    ['2026-07-03', 'снял с карты', '', '', '', '', '1000', ''],
    ['', 'снял с карты', '1000', '', '', '', '', ''],
    ['2026-07-04', 'Netflix', '', '', '', '', '60', ''],
    ['2026-07-05', 'какая-то штука', '', '50', '', '', '', ''],
    ['2026-07-06', 'card refund', '', '', '', '25', '', ''],
    ['2026-07-07', 'информационная строка', '', '', '', '', '', ''],
    ['2026-07-08', 'ambiguous two money columns', '100', '50', '', '', '', ''],
    ['2026-07-01', 'fuel marina', '', '200', '', '', '', ''],
    ['2026-07-31', 'Сводные данные', '6300', '250', '', '25', '1060', 'summary'],
]);
$importUpload = expectOk(smokeRequest('POST', "/api/workspaces/{$importWorkspaceId}/imports/excel", [
    'file_name' => 'july-final-2026-07-01.xlsx',
    'file_id' => 'legacy-file-001',
    'file_url' => 'https://example.test/july-final.xlsx',
    'content_base64' => base64_encode((string)file_get_contents($xlsxPath)),
]), 'upload excel import')['import'];
@unlink($xlsxPath);
smokeAddWorkspaceMember($importWorkspaceId, 19002, 'viewer');
expectError(smokeRequest('POST', "/api/workspaces/{$importWorkspaceId}/imports/excel", [
    'file_name' => 'viewer.xlsx',
    'content_base64' => base64_encode('viewer import'),
], true, $viewerToken), 403, 'workspace_read_only', 'viewer upload excel import');
expectError(smokeRequest('POST', "/api/workspaces/{$importWorkspaceId}/imports/" . $importUpload['import_id'] . "/accept", [
    'decision' => 'accept',
], true, $viewerToken), 403, 'workspace_read_only', 'viewer accept excel import');
smokeAssert($importUpload['include_decision'] === 'included', 'import should be included');
smokeAssert($importUpload['sheets_scanned'] === 1, 'import sheets scanned mismatch');
smokeAssert($importUpload['rows_scanned'] === 12, 'import rows scanned mismatch');
smokeAssert($importUpload['rows_parsed'] === 9, 'import rows parsed mismatch');
smokeAssert($importUpload['entries_created'] === 0, 'entries should not be created before accept');
smokeAssert($importUpload['summary_rows_ignored'] === 1, 'summary row ignored mismatch');
smokeAssert($importUpload['rows_ignored'] === 1, 'info row ignored mismatch');
smokeAssert($importUpload['rows_unrecognized'] === 1, 'unrecognized import row should be reported');
smokeAssert(count($importUpload['duplicate_suspects']) === 1, 'duplicate suspect should be reported');
smokeAssertAmount($importUpload['source_summary_totals']['cash_income'], 6300.0, 'source summary cash income');
smokeAssertAmount($importUpload['source_summary_totals']['cash_expense'], 250.0, 'source summary cash expense');
smokeAssertAmount($importUpload['source_summary_totals']['card_income'], 25.0, 'source summary card income');
smokeAssertAmount($importUpload['source_summary_totals']['card_expense'], 1060.0, 'source summary card expense');

$dictionaryTables = ['v2_entries', 'v2_flows', 'v2_categories', 'v2_category_rules', 'v2_actors', 'v2_audit_log', 'v2_monthly_closures', 'v2_import_sources', 'v2_import_rows', 'v2_dictionary_training_decisions'];
$dictionaryCountsBefore = smokeTableCounts($dictionaryTables);
$dictionaryQueue = expectOk(smokeRequest('GET', "/api/workspaces/{$importWorkspaceId}/dictionary-review-queue?limit=20&examples=3"), 'dictionary review queue')['queue'];
$dictionaryCountsAfter = smokeTableCounts($dictionaryTables);
smokeAssert($dictionaryCountsBefore === $dictionaryCountsAfter, 'dictionary review queue must be read-only');
foreach (['POST', 'PATCH', 'DELETE'] as $method) {
    expectError(smokeRequest($method, "/api/workspaces/{$importWorkspaceId}/dictionary-review-queue", [
        'decision_type' => 'reject_training',
    ]), 404, 'route_not_found', "dictionary review queue {$method} should be unsupported");
}
smokeAssert($dictionaryCountsBefore === smokeTableCounts($dictionaryTables), 'dictionary review queue unsupported methods must stay read-only');
smokeAssert($dictionaryQueue['rows_total'] === 12, 'dictionary queue raw row count mismatch');
smokeAssert($dictionaryQueue['rows_with_money'] === 9, 'dictionary queue money row count mismatch');
smokeAssert($dictionaryQueue['rows_needs_review'] >= 2, 'dictionary queue review row count mismatch');
smokeAssert(count($dictionaryQueue['groups']) >= 3, 'dictionary queue group count mismatch');
$dictionaryGroupKeys = array_fill_keys(array_map(static fn (array $group): string => (string)$group['key'], $dictionaryQueue['groups']), true);
smokeAssert(isset($dictionaryGroupKeys['semantic:owner_funding']), 'dictionary queue missing owner funding group');
smokeAssert(isset($dictionaryGroupKeys['semantic:commercial_income_allowed']), 'dictionary queue missing commercial income group');
smokeAssert(isset($dictionaryGroupKeys['category:fuel']), 'dictionary queue missing fuel category guess group');

$dictionaryGuardWorkspace = expectOk(smokeRequest('POST', '/api/workspaces', [
    'name' => 'HTTP Smoke Dictionary Guard Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'ru',
    'opening_cash' => '0.00',
]), 'create dictionary guard workspace')['workspace'];
$dictionaryGuardWorkspaceId = (string)$dictionaryGuardWorkspace['id'];
smokeAddWorkspaceMember($dictionaryGuardWorkspaceId, 19002, 'viewer');
$dictionaryGuardXlsxPath = smokeCreateXlsx([
    ['дата', 'Описание платежа', 'Приход КЕШ', 'Расход КЕШ', 'Исполнитель', 'Приход КАРТА', 'Расход КАРТА', 'Сводные данные'],
    ['2026-07-01', 'brokerage', '100', '', '', '', '', ''],
    ['2026-07-01', 'agency fee', '100', '', '', '', '', ''],
    ['2026-07-01', 'вода', '', '10', '', '', '', ''],
    ['2026-07-01', 'вода электричество', '', '20', '', '', '', ''],
    ['2026-07-01', 'сим-карта и фрукты', '', '', '', '', '30', ''],
    ['2026-07-01', 'тендер остаток за зиму и сервис', '', '40', '', '', '', ''],
    ['2026-07-01', 'аренда авто', '', '100', '', '', '', ''],
    ['2026-07-01', 'аптека', '', '25', '', '', '', ''],
    ['2026-07-01', 'агент', '', '50', '', '', '', ''],
    ['2026-07-01', 'магазин сплит', '', '20', '', '', '', ''],
    ['2026-07-01', 'комиссии банков', '', '10', '', '', '', ''],
    ['2026-07-01', 'доставка', '', '15', '', '', '', ''],
    ['2026-07-01', 'доставка фильтра', '', '15', '', '', '', ''],
    ['2026-07-01', 'обед с агентом', '', '55', '', '', '', ''],
    ['2026-07-01', 'музыканты', '', '700', '', '', '', ''],
    ['2026-07-01', 'зарядка шефу', '', '35', '', '', '', ''],
    ['2026-07-01', 'передал ЛВ', '', '100', '', '', '', ''],
    ['2026-07-01', 'контролька кондея', '', '100', '', '', '', ''],
    ['2026-07-01', 'айфон', '', '100', '', '', '', ''],
    ['2026-07-01', 'мой кредит', '', '1000', '', '', '', ''],
    ['2026-07-01', 'забрал свои', '', '1000', '', '', '', ''],
    ['2026-07-01', 'инвентарь', '', '75', '', '', '', ''],
    ['2026-07-01', 'цветы', '', '50', '', '', '', ''],
    ['2026-07-01', 'Розы-подарок Алине', '', '50', '', '', '', ''],
    ['2026-07-01', 'украшения др', '', '50', '', '', '', ''],
    ['2026-07-01', 'ареда яхты', '5525', '', '', '', '', ''],
    ['2026-07-01', 'из сейфа', '6000', '', '', '', '', ''],
]);
expectOk(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/imports/excel", [
    'file_name' => 'dictionary-guard-2026-07-01.xlsx',
    'file_id' => 'dictionary-guard-file-001',
    'content_base64' => base64_encode((string)file_get_contents($dictionaryGuardXlsxPath)),
]), 'upload dictionary guard import');
@unlink($dictionaryGuardXlsxPath);
$dictionaryGuardQueue = expectOk(smokeRequest('GET', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-review-queue?limit=50&examples=10"), 'dictionary guard queue')['queue'];
$guardExamples = [];
foreach ($dictionaryGuardQueue['groups'] as $group) {
    foreach ($group['examples'] as $example) {
        $guardExamples[mb_strtolower((string)$example['description'])] = [
            'group' => $group,
            'example' => $example,
        ];
    }
}
foreach (['brokerage', 'agency fee', 'сим-карта и фрукты', 'тендер остаток за зиму и сервис'] as $description) {
    smokeAssert(isset($guardExamples[$description]), "dictionary guard missing {$description}");
    smokeAssert(($guardExamples[$description]['group']['needs_review'] ?? null) === true, "dictionary guard {$description} should remain review");
}
smokeAssert(($guardExamples['brokerage']['example']['review_reason'] ?? null) === 'commercial_income_unclear', 'dictionary guard brokerage review reason');
smokeAssert(smokeBlockerHas($guardExamples['brokerage']['example'], 'missing_yacht_charter_phrase'), 'dictionary guard brokerage blocker');
smokeAssert(isset($guardExamples['вода']), 'dictionary guard missing plain water');
smokeAssert(($guardExamples['вода']['example']['current_rule_guess'] ?? null) === 'provisions', 'dictionary guard plain water should be provisions');
smokeAssert(isset($guardExamples['аренда авто']), 'dictionary guard missing car rental');
smokeAssert(($guardExamples['аренда авто']['example']['current_rule_guess'] ?? null) === 'transport_expenses', 'dictionary guard car rental should be transport expense');
smokeAssert(isset($guardExamples['аптека']), 'dictionary guard missing pharmacy');
smokeAssert(($guardExamples['аптека']['example']['current_rule_guess'] ?? null) === 'provisions', 'dictionary guard pharmacy should be provisions');
smokeAssert(isset($guardExamples['агент']), 'dictionary guard missing agent');
smokeAssert(($guardExamples['агент']['example']['current_rule_guess'] ?? null) === 'current_boat_expenses', 'dictionary guard agent should be current boat expense');
smokeAssert(smokeExampleMarkerHas($guardExamples['агент']['example'], 'weak_dictionary_context'), 'dictionary guard agent should expose weak marker');
smokeAssert(($guardExamples['агент']['group']['needs_review'] ?? null) === true, 'dictionary guard agent weak row should need review');
smokeAssert(($guardExamples['агент']['example']['review_reason'] ?? null) === 'weak_only', 'dictionary guard agent review reason');
smokeAssertAmount($guardExamples['агент']['example']['confidence'] ?? null, 0.48, 'dictionary guard agent confidence');
smokeAssert(isset($guardExamples['магазин сплит']), 'dictionary guard missing store');
smokeAssert(($guardExamples['магазин сплит']['example']['current_rule_guess'] ?? null) === 'current_boat_expenses', 'dictionary guard store should be current boat expense');
smokeAssert(isset($guardExamples['комиссии банков']), 'dictionary guard missing bank commissions');
smokeAssert(($guardExamples['комиссии банков']['example']['current_rule_guess'] ?? null) === 'current_boat_expenses', 'dictionary guard bank commissions should be current boat expense');
smokeAssert(isset($guardExamples['доставка']), 'dictionary guard missing delivery');
smokeAssert(($guardExamples['доставка']['example']['current_rule_guess'] ?? null) === 'transport_expenses', 'dictionary guard delivery should be transport expense');
smokeAssert(smokeExampleMarkerHas($guardExamples['доставка']['example'], 'weak_dictionary_context'), 'dictionary guard delivery should expose weak marker');
smokeAssert(($guardExamples['доставка']['group']['needs_review'] ?? null) === true, 'dictionary guard delivery weak row should need review');
smokeAssert(($guardExamples['доставка']['example']['review_reason'] ?? null) === 'weak_only', 'dictionary guard delivery review reason');
smokeAssertAmount($guardExamples['доставка']['example']['confidence'] ?? null, 0.48, 'dictionary guard delivery confidence');
smokeAssert(isset($guardExamples['доставка фильтра']), 'dictionary guard missing delivery part');
smokeAssert(($guardExamples['доставка фильтра']['example']['current_rule_guess'] ?? null) === 'tech_parts', 'dictionary guard delivery part should keep part category');
smokeAssert(smokeExampleMarkerHas($guardExamples['доставка фильтра']['example'], 'mixed_dictionary_context'), 'dictionary guard delivery part should expose mixed marker');
smokeAssert(($guardExamples['доставка фильтра']['group']['needs_review'] ?? null) === true, 'dictionary guard delivery part mixed row should need review');
smokeAssert(($guardExamples['доставка фильтра']['example']['review_reason'] ?? null) === 'mixed_context', 'dictionary guard delivery part review reason');
smokeAssertAmount($guardExamples['доставка фильтра']['example']['confidence'] ?? null, 0.64, 'dictionary guard delivery part confidence');
smokeAssert(isset($guardExamples['вода электричество']), 'dictionary guard missing water electricity');
smokeAssert(($guardExamples['вода электричество']['example']['current_rule_guess'] ?? null) === 'berth', 'dictionary guard water electricity should be berth');
smokeAssert(isset($guardExamples['музыканты']), 'dictionary guard missing musicians');
smokeAssert(($guardExamples['музыканты']['example']['current_rule_guess'] ?? null) === 'guest_trip_support', 'dictionary guard musicians should be guest trip support');
smokeAssert(isset($guardExamples['зарядка шефу']), 'dictionary guard missing chef charger');
smokeAssert(($guardExamples['зарядка шефу']['example']['current_rule_guess'] ?? null) === 'guest_trip_support', 'dictionary guard chef charger should be guest trip support');
smokeAssert(isset($guardExamples['передал лв']), 'dictionary guard missing guest cash transfer');
smokeAssert(($guardExamples['передал лв']['example']['current_rule_guess'] ?? null) === 'guest_cash_issued', 'dictionary guard guest cash transfer should be guest cash issued');
smokeAssert(isset($guardExamples['контролька кондея']), 'dictionary guard missing AC control part');
smokeAssert(($guardExamples['контролька кондея']['example']['current_rule_guess'] ?? null) === 'tech_parts', 'dictionary guard AC control part should be tech parts');
smokeAssert(isset($guardExamples['айфон']), 'dictionary guard missing iPhone other review');
smokeAssert(($guardExamples['айфон']['example']['current_rule_guess'] ?? null) === 'guest_trip_support', 'dictionary guard iPhone should be guest trip support');
smokeAssert(isset($guardExamples['мой кредит']), 'dictionary guard missing credit');
smokeAssert(($guardExamples['мой кредит']['group']['key'] ?? null) === 'semantic:debt_or_return', 'dictionary guard credit should be debt/loan review block');
smokeAssert(($guardExamples['мой кредит']['example']['review_reason'] ?? null) === 'blocked_by_debt', 'dictionary guard credit review reason');
smokeAssert(smokeBlockerHas($guardExamples['мой кредит']['example'], 'debt_or_return'), 'dictionary guard credit blocker');
smokeAssert(isset($guardExamples['забрал свои']), 'dictionary guard missing own cash reimbursement');
smokeAssert(($guardExamples['забрал свои']['example']['current_rule_guess'] ?? null) === 'current_boat_expenses', 'dictionary guard own cash reimbursement should be current boat expense');
smokeAssert(isset($guardExamples['инвентарь']), 'dictionary guard missing generic inventory');
smokeAssert(($guardExamples['инвентарь']['example']['current_rule_guess'] ?? null) === 'current_boat_expenses', 'dictionary guard generic inventory should be current boat expense');
smokeAssert(isset($guardExamples['цветы']), 'dictionary guard missing flowers');
smokeAssert(($guardExamples['цветы']['example']['current_rule_guess'] ?? null) === 'provisions', 'dictionary guard flowers should become provisions');
smokeAssert(isset($guardExamples['розы-подарок алине']), 'dictionary guard missing named gift');
smokeAssert(($guardExamples['розы-подарок алине']['example']['current_rule_guess'] ?? null) === 'representation_expenses', 'dictionary guard named gift should become representation expense');
smokeAssert(isset($guardExamples['обед с агентом']), 'dictionary guard missing agent meal');
smokeAssert(($guardExamples['обед с агентом']['example']['current_rule_guess'] ?? null) === 'representation_expenses', 'dictionary guard agent meal should become representation expense');
smokeAssert(!smokeExampleMarkerHas($guardExamples['обед с агентом']['example'], 'weak_dictionary_context'), 'dictionary guard agent meal should not expose weak marker');
smokeAssert(($guardExamples['обед с агентом']['example']['review_reason'] ?? null) === null, 'dictionary guard agent meal review reason');
smokeAssertAmount($guardExamples['обед с агентом']['example']['confidence'] ?? null, 0.92, 'dictionary guard agent meal confidence');
smokeAssert(isset($guardExamples['украшения др']), 'dictionary guard missing birthday decorations');
smokeAssert(($guardExamples['украшения др']['example']['current_rule_guess'] ?? null) === 'representation_expenses', 'dictionary guard birthday decorations should be representation expense');
smokeAssert(($guardExamples['ареда яхты']['example']['current_rule_guess'] ?? null) === 'commercial_income', 'dictionary guard yacht rental should allow commercial income');
smokeAssert(($guardExamples['ареда яхты']['example']['review_reason'] ?? null) === null, 'dictionary guard yacht rental review reason');
smokeAssertAmount($guardExamples['ареда яхты']['example']['confidence'] ?? null, 0.92, 'dictionary guard yacht rental confidence');
smokeAssert(($guardExamples['из сейфа']['group']['key'] ?? null) === 'semantic:cash_location_safe', 'dictionary guard safe row should prioritize cash location group');

$trainingTables = ['v2_entries', 'v2_flows', 'v2_actors', 'v2_monthly_closures', 'v2_category_rules', 'v2_dictionary_training_decisions', 'v2_audit_log'];
$trainingCountsBefore = smokeTableCounts($trainingTables);
$agentTrainingDecision = expectOk(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['агент']['example']['source']['source_row_id'],
    'decision_type' => 'approve_existing_guess_local',
    'category_code' => 'current_boat_expenses',
    'pattern' => 'агент',
    'pattern_type' => 'keyword',
    'language' => 'ru',
    'weight' => 10,
    'requires_any' => ['лодка'],
    'excludes_any' => ['мой', 'личный', 'долг', 'кредит'],
    'note' => 'http smoke local approval',
]), 'dictionary training approve agent')['decision'];
smokeAssert(($agentTrainingDecision['decision_type'] ?? null) === 'approve_existing_guess_local', 'training approve decision type');
smokeAssert(($agentTrainingDecision['target_category_code'] ?? null) === 'current_boat_expenses', 'training approve target category');
smokeAssert(($agentTrainingDecision['review_reason'] ?? null) === 'weak_only', 'training approve review reason');
smokeAssert(($agentTrainingDecision['requires_any'] ?? []) === ['лодка'], 'training approve requires_any mismatch');
smokeAssert(($agentTrainingDecision['excludes_any'] ?? []) === ['мой', 'личный', 'долг', 'кредит'], 'training approve excludes_any mismatch');
smokeAssert(isset($agentTrainingDecision['category_rule']['id']), 'training approve should create local category rule');
smokeAssert(smokeAuditCount('create', (string)$agentTrainingDecision['id']) === 1, 'training decision create audit missing');
smokeAssert(smokeAuditCount('create', (string)$agentTrainingDecision['category_rule_id']) === 1, 'training category rule audit missing');

$duplicateAgentTrainingDecision = expectOk(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['агент']['example']['source']['source_row_id'],
    'decision_type' => 'approve_existing_guess_local',
    'category_code' => 'current_boat_expenses',
    'pattern' => 'агент',
    'pattern_type' => 'keyword',
    'language' => 'ru',
    'weight' => 10,
    'requires_any' => ['лодка'],
    'excludes_any' => ['мой', 'личный', 'долг', 'кредит'],
]), 'dictionary training duplicate approve agent')['decision'];
smokeAssert(($duplicateAgentTrainingDecision['id'] ?? null) === ($agentTrainingDecision['id'] ?? null), 'training duplicate should update same decision');
smokeAssert(($duplicateAgentTrainingDecision['category_rule_id'] ?? null) === ($agentTrainingDecision['category_rule_id'] ?? null), 'training duplicate should reuse local category rule');
$trainingCountsAfterDuplicate = smokeTableCounts($trainingTables);
smokeAssert($trainingCountsAfterDuplicate['v2_category_rules'] === $trainingCountsBefore['v2_category_rules'] + 1, 'training duplicate should not create second category rule');
smokeAssert($trainingCountsAfterDuplicate['v2_dictionary_training_decisions'] === $trainingCountsBefore['v2_dictionary_training_decisions'] + 1, 'training duplicate should not create second decision');

$deliveryTrainingReject = expectOk(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['доставка фильтра']['example']['source']['source_row_id'],
    'decision_type' => 'reject_training',
    'note' => 'http smoke reject mixed context',
]), 'dictionary training reject delivery part')['decision'];
smokeAssert(($deliveryTrainingReject['decision_type'] ?? null) === 'reject_training', 'training reject decision type');
smokeAssert(($deliveryTrainingReject['category_rule_id'] ?? null) === null, 'training reject should not create category rule');
smokeAssert(smokeTableCounts($trainingTables)['v2_category_rules'] === $trainingCountsBefore['v2_category_rules'] + 1, 'training reject should not create category rule');

expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['мой кредит']['example']['source']['source_row_id'],
    'decision_type' => 'approve_existing_guess_local',
    'category_code' => 'current_boat_expenses',
    'pattern' => 'кредит',
]), 422, 'dictionary_training_blocked', 'dictionary training blocked debt approval');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['brokerage']['example']['source']['source_row_id'],
    'decision_type' => 'approve_existing_guess_local',
    'category_code' => 'commercial_income',
    'pattern' => 'brokerage',
]), 422, 'dictionary_training_blocked', 'dictionary training blocked unclear commercial approval');

$universalCandidateDecision = expectOk(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['ареда яхты']['example']['source']['source_row_id'],
    'decision_type' => 'propose_universal_candidate',
    'category_code' => 'commercial_income',
    'pattern' => 'ареда яхты',
]), 'dictionary training universal candidate')['decision'];
smokeAssert(($universalCandidateDecision['decision_type'] ?? null) === 'propose_universal_candidate', 'universal candidate decision type');
smokeAssert(($universalCandidateDecision['category_rule_id'] ?? null) === null, 'universal candidate must not create category rule');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['ареда яхты']['example']['source']['source_row_id'],
    'decision_type' => 'promote_universal',
    'category_code' => 'commercial_income',
    'pattern' => 'ареда яхты',
]), 422, 'universal_promotion_not_supported', 'dictionary training universal promotion blocked');

expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['агент']['example']['source']['source_row_id'],
    'decision_type' => 'reject_training',
], true, null, false), 403, 'csrf_required', 'dictionary training missing csrf');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions", [
    'source_row_id' => $guardExamples['агент']['example']['source']['source_row_id'],
    'decision_type' => 'reject_training',
], true, $viewerToken), 403, 'workspace_read_only', 'dictionary training viewer write blocked');

$internetMutationTables = [
    'v2_entries',
    'v2_flows',
    'v2_actors',
    'v2_monthly_closures',
    'v2_category_rules',
    'v2_dictionary_training_decisions',
    'v2_import_sources',
    'v2_import_rows',
    'v2_report_snapshots',
    'v2_report_batches',
    'v2_report_batch_entries',
    'v2_report_batch_html_snapshots',
    'v2_report_versions',
    'v2_report_packages',
    'v2_report_package_items',
];
$internetSettings = expectOk(smokeRequest('GET', "/api/workspaces/{$dictionaryGuardWorkspaceId}/assistant-settings"), 'assistant settings defaults')['settings'];
smokeAssert(($internetSettings['mr_smith_enabled'] ?? null) === false, 'assistant settings default enabled mismatch');
smokeAssert(($internetSettings['internet_reference_mode'] ?? null) === 'per_request', 'assistant settings default mode mismatch');
smokeAssert(($internetSettings['provider_key'] ?? null) === 'stub', 'assistant settings default provider mismatch');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/assistant-settings", [
    'mr_smith_enabled' => true,
], true, $viewerToken), 403, 'workspace_admin_required', 'assistant settings viewer blocked');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/assistant-settings", [
    'provider_key' => 'web',
]), 422, 'invalid_provider_key', 'assistant settings invalid provider blocked');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/assistant-settings", [
    'provider_key' => 'allowlisted_http',
]), 422, 'invalid_provider_key', 'assistant settings allowlisted provider env gate blocked');
$disabledSettings = expectOk(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/assistant-settings", [
    'mr_smith_enabled' => false,
    'internet_reference_mode' => 'disabled',
    'provider_key' => 'stub',
    'retention_days' => 14,
]), 'assistant settings disabled')['settings'];
smokeAssert(($disabledSettings['internet_reference_mode'] ?? null) === 'disabled', 'assistant settings disabled mode mismatch');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
    'sanitized_query' => 'Marina Porto Montenegro',
]), 422, 'internet_reference_disabled', 'internet reference disabled mode blocks preview');
$perRequestSettings = expectOk(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/assistant-settings", [
    'mr_smith_enabled' => false,
    'internet_reference_mode' => 'per_request',
    'provider_key' => 'stub',
    'retention_days' => 30,
]), 'assistant settings per request')['settings'];
smokeAssert(($perRequestSettings['internet_reference_mode'] ?? null) === 'per_request', 'assistant settings per request mode mismatch');

$internetCountsBefore = smokeTableCounts($internetMutationTables);
$internetLookupCountBefore = smokeTableCounts(['v2_internet_reference_lookups'])['v2_internet_reference_lookups'];
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'sanitized_query' => 'Marina Porto Montenegro',
]), 422, 'internet_reference_consent_required', 'internet reference requires consent');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
]), 422, 'missing_sanitized_query', 'internet reference requires sanitized query');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
    'sanitized_query' => 'Marina Porto Montenegro',
    'raw_text' => '-50 Marina Porto Montenegro',
]), 422, 'unsafe_internet_reference_payload', 'internet reference rejects raw text');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
    'sanitized_query' => 'Marina Porto Montenegro',
], true, null, false), 403, 'csrf_required', 'internet reference missing csrf');
expectError(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
    'sanitized_query' => 'Marina Porto Montenegro',
], true, $viewerToken), 403, 'workspace_read_only', 'internet reference viewer blocked');
smokeAssert(smokeTableCounts(['v2_internet_reference_lookups'])['v2_internet_reference_lookups'] === $internetLookupCountBefore, 'failed internet reference requests must not write provenance rows');
$internetReference = expectOk(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
    'sanitized_query' => 'Marina Porto Montenegro 250',
    'source_row_id' => $guardExamples['агент']['example']['source']['source_row_id'],
]), 'internet reference beta stub')['reference'];
smokeAssert(isset($internetReference['lookup_id']), 'internet reference lookup id missing');
smokeAssert(($internetReference['workspace_id'] ?? null) === $dictionaryGuardWorkspaceId, 'internet reference workspace mismatch');
smokeAssert(($internetReference['source_row_id'] ?? null) === $guardExamples['агент']['example']['source']['source_row_id'], 'internet reference source row mismatch');
smokeAssert(($internetReference['sanitized_query'] ?? null) === 'Marina Porto Montenegro', 'internet reference sanitized query mismatch');
smokeAssert(($internetReference['provider_key'] ?? null) === 'stub', 'internet reference provider mismatch');
smokeAssert(($internetReference['result_status'] ?? null) === 'stub', 'internet reference status mismatch');
smokeAssert(($internetReference['consent_source'] ?? null) === 'request', 'internet reference consent source mismatch');
smokeAssert(($internetReference['matches'][0]['source_type'] ?? null) === 'stub', 'internet reference must remain stub');
smokeAssert(($internetReference['no_financial_mutation'] ?? null) === true, 'internet reference no mutation flag missing');
smokeAssert(smokeTableCounts($internetMutationTables) === $internetCountsBefore, 'internet reference stub must not mutate finance/training/import/report tables');
smokeAssert(smokeTableCounts(['v2_internet_reference_lookups'])['v2_internet_reference_lookups'] === $internetLookupCountBefore + 1, 'internet reference must write one provenance row');
$internetLookups = expectOk(smokeRequest('GET', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups?limit=5"), 'internet reference lookup list')['lookups'];
smokeAssert(count($internetLookups) >= 1, 'internet reference lookup readback missing rows');
smokeAssert(($internetLookups[0]['id'] ?? null) === ($internetReference['lookup_id'] ?? null), 'internet reference lookup id readback mismatch');
smokeAssert(($internetLookups[0]['query_hash'] ?? null) === ($internetReference['query_hash'] ?? null), 'internet reference query hash readback mismatch');
smokeAssert(($internetLookups[0]['provider_key'] ?? null) === 'stub', 'internet reference lookup provider readback mismatch');
smokeAssert(($internetLookups[0]['consent_source'] ?? null) === 'request', 'internet reference lookup consent source readback mismatch');
smokeAssert(($internetLookups[0]['matches'][0]['source_type'] ?? null) === 'stub', 'internet reference lookup matches readback mismatch');
smokeAssert(($internetLookups[0]['no_financial_mutation'] ?? null) === true, 'internet reference lookup no mutation readback mismatch');
smokeAssert(strtotime((string)($internetLookups[0]['retention_delete_after'] ?? '')) > time(), 'internet reference retention date missing');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups/{$internetReference['lookup_id']}", [
    'verdict' => 'useful',
], true, null, false), 403, 'csrf_required', 'internet reference feedback missing csrf');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups/{$internetReference['lookup_id']}", [
    'verdict' => 'useful',
], true, $viewerToken), 403, 'workspace_read_only', 'internet reference feedback viewer blocked');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups/{$internetReference['lookup_id']}", []), 422, 'missing_verdict', 'internet reference feedback requires verdict');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups/{$internetReference['lookup_id']}", [
    'verdict' => 'approved',
]), 422, 'invalid_verdict', 'internet reference feedback invalid verdict blocked');
expectError(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups/{$internetReference['lookup_id']}", [
    'verdict' => 'useful',
    'raw_text' => '-250 Marina Porto Montenegro',
]), 422, 'unsafe_internet_reference_feedback_payload', 'internet reference feedback unsafe payload blocked');
expectError(smokeRequest('PATCH', "/api/workspaces/{$importWorkspaceId}/dictionary-training-internet-reference/lookups/{$internetReference['lookup_id']}", [
    'verdict' => 'useful',
]), 404, 'internet_reference_lookup_not_found', 'internet reference feedback cross-workspace blocked');
$internetCountsBeforeFeedback = smokeTableCounts($internetMutationTables);
$internetLookupCountBeforeFeedback = smokeTableCounts(['v2_internet_reference_lookups'])['v2_internet_reference_lookups'];
$internetFeedback = expectOk(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups/{$internetReference['lookup_id']}", [
    'verdict' => 'useful',
    'match_index' => 0,
    'note' => 'http smoke evidence feedback',
]), 'internet reference feedback save')['lookup'];
smokeAssert(($internetFeedback['id'] ?? null) === ($internetReference['lookup_id'] ?? null), 'internet reference feedback lookup id mismatch');
smokeAssert(($internetFeedback['selected_match']['verdict'] ?? null) === 'useful', 'internet reference feedback verdict readback mismatch');
smokeAssert(($internetFeedback['selected_match']['no_financial_mutation'] ?? null) === true, 'internet reference feedback no finance flag missing');
smokeAssert(($internetFeedback['selected_match']['no_training_mutation'] ?? null) === true, 'internet reference feedback no training flag missing');
smokeAssert(smokeTableCounts($internetMutationTables) === $internetCountsBeforeFeedback, 'internet reference feedback must not mutate finance/training/import/report tables');
smokeAssert(smokeTableCounts(['v2_internet_reference_lookups'])['v2_internet_reference_lookups'] === $internetLookupCountBeforeFeedback, 'internet reference feedback must not create lookup rows');
$internetLookupsAfterFeedback = expectOk(smokeRequest('GET', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference/lookups?limit=5"), 'internet reference lookup feedback readback')['lookups'];
smokeAssert(($internetLookupsAfterFeedback[0]['selected_match']['verdict'] ?? null) === 'useful', 'internet reference feedback list readback mismatch');

$otherWorkspaceReference = expectOk(smokeRequest('POST', "/api/workspaces/{$importWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
    'sanitized_query' => 'Marina Porto Montenegro 250',
]), 'internet reference second workspace hash')['reference'];
smokeAssert(($otherWorkspaceReference['sanitized_query'] ?? null) === 'Marina Porto Montenegro', 'second workspace internet reference sanitized query mismatch');
smokeAssert(($otherWorkspaceReference['query_hash'] ?? null) !== ($internetReference['query_hash'] ?? null), 'internet reference query hash must be workspace scoped');
expectError(smokeRequest('POST', "/api/workspaces/{$importWorkspaceId}/dictionary-training-internet-reference", [
    'lookup_consent' => true,
    'sanitized_query' => 'Marina Porto Montenegro',
    'source_row_id' => $guardExamples['агент']['example']['source']['source_row_id'],
]), 404, 'dictionary_source_row_not_found', 'internet reference cross-workspace source row blocked');

$workspaceConsentSettings = expectOk(smokeRequest('PATCH', "/api/workspaces/{$dictionaryGuardWorkspaceId}/assistant-settings", [
    'mr_smith_enabled' => true,
    'internet_reference_mode' => 'workspace_enabled',
    'provider_key' => 'stub',
    'retention_days' => 30,
]), 'assistant settings workspace enabled')['settings'];
smokeAssert(($workspaceConsentSettings['mr_smith_enabled'] ?? null) === true, 'assistant settings workspace enabled flag mismatch');
$workspaceConsentReference = expectOk(smokeRequest('POST', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-internet-reference", [
    'sanitized_query' => 'Porto Montenegro',
]), 'internet reference workspace consent')['reference'];
smokeAssert(($workspaceConsentReference['consent_source'] ?? null) === 'workspace_setting', 'internet reference workspace consent source mismatch');

$trainingDecisions = expectOk(smokeRequest('GET', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-training-decisions?limit=10"), 'dictionary training list decisions')['decisions'];
smokeAssert(count($trainingDecisions) === 3, 'dictionary training list count mismatch');
$trainingCountsAfter = smokeTableCounts($trainingTables);
smokeAssert($trainingCountsAfter['v2_entries'] === $trainingCountsBefore['v2_entries'], 'dictionary training must not create entries');
smokeAssert($trainingCountsAfter['v2_flows'] === $trainingCountsBefore['v2_flows'], 'dictionary training must not create flows');
smokeAssert($trainingCountsAfter['v2_actors'] === $trainingCountsBefore['v2_actors'], 'dictionary training must not create actors');
smokeAssert($trainingCountsAfter['v2_monthly_closures'] === $trainingCountsBefore['v2_monthly_closures'], 'dictionary training must not create monthly closures');

$dictionaryGuardReviewOnly = expectOk(smokeRequest('GET', "/api/workspaces/{$dictionaryGuardWorkspaceId}/dictionary-review-queue?needs_review=1&limit=50&examples=10"), 'dictionary guard review-only queue')['queue'];
$reviewOnlyDescriptions = [];
foreach ($dictionaryGuardReviewOnly['groups'] as $group) {
    foreach ($group['examples'] as $example) {
        $reviewOnlyDescriptions[mb_strtolower((string)$example['description'])] = true;
    }
}
foreach (['агент', 'доставка', 'доставка фильтра'] as $description) {
    smokeAssert(isset($reviewOnlyDescriptions[$description]), "dictionary guard review-only missing {$description}");
}

$importId = (string)$importUpload['import_id'];
$reviewBeforeAccept = expectOk(smokeRequest('GET', "/api/workspaces/{$importWorkspaceId}/imports/{$importId}/review"), 'import review before accept')['review'];
smokeAssert($reviewBeforeAccept['entries_created'] === 0, 'review before accept should not have entries');
smokeAssert(count($reviewBeforeAccept['row_traces']) === 12, 'row trace count before accept mismatch');
smokeAssert($reviewBeforeAccept['rows_unrecognized'] === 1, 'review before accept unrecognized row mismatch');

$acceptedImport = expectOk(smokeRequest('POST', "/api/workspaces/{$importWorkspaceId}/imports/{$importId}/accept", [
    'decision' => 'accept',
]), 'accept excel import')['review'];
smokeAssert($acceptedImport['entries_created'] === 9, 'accepted import entry count mismatch');
smokeAssert($acceptedImport['rows_unrecognized'] === 1, 'accepted import unrecognized row mismatch');
smokeAssertAmount($acceptedImport['normalized_totals']['cash_income'], 6300.0, 'accepted normalized cash income');
smokeAssertAmount($acceptedImport['normalized_totals']['cash_expense'], 250.0, 'accepted normalized cash expense');
smokeAssertAmount($acceptedImport['normalized_totals']['card_income'], 25.0, 'accepted normalized card income');
smokeAssertAmount($acceptedImport['normalized_totals']['card_expense'], 1060.0, 'accepted normalized card expense');
smokeAssertAmount($acceptedImport['source_total_comparison']['cash_income'], 0.0, 'accepted source comparison cash income');
smokeAssertAmount($acceptedImport['source_total_comparison']['cash_expense'], 0.0, 'accepted source comparison cash expense');
smokeAssertAmount($acceptedImport['source_total_comparison']['card_income'], 0.0, 'accepted source comparison card income');
smokeAssertAmount($acceptedImport['source_total_comparison']['card_expense'], 0.0, 'accepted source comparison card expense');
smokeAssert(in_array('2026-07', $acceptedImport['months_covered'], true), 'accepted import missing month coverage');
$dateSources = array_fill_keys(array_map(static fn (array $trace): ?string => $trace['date_source'] ?? null, $acceptedImport['row_traces']), true);
smokeAssert(isset($dateSources['filename_date']), 'import review missing filename_date provenance');
smokeAssert(isset($dateSources['inherited_previous_row_date']), 'import review missing inherited date provenance');
smokeAssert(isset($dateSources['row_date']), 'import review missing row_date provenance');
$unrecognizedTrace = null;
foreach ($acceptedImport['row_traces'] as $trace) {
    if (($trace['parse_status'] ?? null) === 'unrecognized') {
        $unrecognizedTrace = $trace;
        break;
    }
}
smokeAssert(is_array($unrecognizedTrace), 'accepted import missing unrecognized trace');
smokeAssert(($unrecognizedTrace['parse_notes'] ?? null) === 'multiple money columns in one row', 'accepted import unrecognized notes mismatch');

$importEntries = expectOk(smokeRequest('GET', "/api/workspaces/{$importWorkspaceId}/entries?year=2026&month=7"), 'import entries after accept')['entries'];
smokeAssert(count($importEntries) === 9, 'import entries visible count mismatch');
$sourceLinked = 0;
$duplicateLinked = 0;
$cardIncomeLinked = 0;
foreach ($importEntries as $candidate) {
    if (($candidate['source_id'] ?? null) === $importId && ($candidate['source_row_id'] ?? null) !== null) {
        $sourceLinked++;
    }
    if (($candidate['status'] ?? '') === 'duplicate_suspect') {
        $duplicateLinked++;
    }
    if (($candidate['entry_type'] ?? '') === 'card_income' && ($candidate['source_type'] ?? '') === 'import') {
        $cardIncomeLinked++;
    }
}
smokeAssert($sourceLinked === 9, 'import entries missing source traceability');
smokeAssert($duplicateLinked === 1, 'duplicate suspect entry count mismatch');
smokeAssert($cardIncomeLinked === 1, 'import card income entry missing');

$importMonthly = expectOk(smokeRequest('GET', "/api/workspaces/{$importWorkspaceId}/reports/monthly?year=2026&month=7"), 'monthly report after import')['report'];
smokeAssert(count($importMonthly['source_files']) === 1, 'monthly source file trace missing');
smokeAssertAmount($importMonthly['opening_cash'], 1000.0, 'import monthly opening cash');
smokeAssertAmount($importMonthly['external_cash_income'], 300.0, 'import monthly external cash income');
smokeAssertAmount($importMonthly['commercial_income'], 5000.0, 'import monthly commercial income');
smokeAssertAmount($importMonthly['cash_topup_from_card_cash_side'], 1000.0, 'import monthly cash topup side');
smokeAssertAmount($importMonthly['cash_expense'], 250.0, 'import monthly cash expense');
smokeAssertAmount($importMonthly['card_expense'], 1060.0, 'import monthly card expense');
smokeAssertAmount($importMonthly['other_expenses'], 50.0, 'import monthly other expenses');
smokeAssertAmount($importMonthly['ending_cash'], 7050.0, 'import monthly ending cash');

$excludedImport = expectOk(smokeRequest('POST', "/api/workspaces/{$importWorkspaceId}/imports/excel", [
    'file_name' => 'draft legacy import.xlsx',
    'content_base64' => base64_encode('not parsed because excluded'),
]), 'excluded title marker import')['import'];
smokeAssert($excludedImport['include_decision'] === 'excluded_by_title_marker', 'excluded import decision mismatch');
smokeAssert($excludedImport['files_excluded'] === 1, 'excluded import count mismatch');
smokeAssert($excludedImport['rows_scanned'] === 0, 'excluded import should not parse rows');

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
$closedBaseMonth = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/months/2026/7/close", [
    'comment' => 'base smoke closed month',
]), 'close base month');
smokeAssert(($closedBaseMonth['closure']['is_closed'] ?? null) === true, 'base month close flag mismatch');
$openMonthEntry = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-08-05',
    'raw_text' => '+50 open month topup',
]), 'create open-month probe entry')['entry'];
expectError(smokeRequest('POST', "/api/workspaces/{$workspaceId}/entries", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+5 should be correction',
]), 409, 'closed_month_requires_decision', 'closed month entry create');
expectError(smokeRequest('PATCH', '/api/entries/' . $openMonthEntry['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-08',
    'raw_text' => '+50 moved into closed month',
]), 409, 'closed_month_requires_decision', 'open-month entry moved into closed month');
$monthCorrection = expectOk(smokeRequest('POST', "/api/workspaces/{$workspaceId}/months/2026/7/correction", [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+5 smoke month correction',
    'reason' => 'HTTP smoke correction',
    'reference_entry_id' => $closedEntry['id'],
    'source_type' => 'manual',
    'status' => 'recognized',
    'entry_type' => 'cash_income',
]), 'month correction route')['entry'];
smokeAssert($monthCorrection['entry_type'] === 'correction', 'month correction type mismatch');
smokeAssert($monthCorrection['status'] === 'corrected', 'month correction status mismatch');
smokeAssert($monthCorrection['source_type'] === 'correction', 'month correction source mismatch');
smokeAssert((float)$monthCorrection['amount'] === 5.0, 'month correction amount mismatch');
smokeAssert(smokeAuditCount('month_correction_create', $monthCorrection['id']) === 1, 'month correction audit missing');
$baseMonthlyAfterCorrection = expectOk(smokeRequest('GET', "/api/workspaces/{$workspaceId}/reports/monthly?year=2026&month=7"), 'base monthly after correction')['report'];
smokeAssertAmount($baseMonthlyAfterCorrection['corrections'], 5.0, 'month correction total mismatch');
smokeAssertAmount($baseMonthlyAfterCorrection['external_cash_income'], 0.0, 'month correction should not become external income');
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
