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

function smokeAddWorkspaceMember(string $workspaceId, int $userId, string $role): void
{
    $stmt = smokeDb()->prepare("
        INSERT INTO v2_workspace_members (id, workspace_id, user_id, role)
        VALUES (UUID(), ?, ?, ?)
    ");
    $stmt->execute([$workspaceId, $userId, $role]);
}

function smokeAuditCount(string $action, string $entryId): int
{
    $stmt = smokeDb()->prepare("SELECT COUNT(*) FROM v2_audit_log WHERE action = ? AND entity_id = ?");
    $stmt->execute([$action, $entryId]);

    return (int)$stmt->fetchColumn();
}

function smokeStoragePath(string $fileUrl): string
{
    $harness = rtrim((string)getenv('FINDESK_V2_HTTP_HARNESS'), '/');
    smokeAssert($harness !== '', 'Missing FINDESK_V2_HTTP_HARNESS');

    return $harness . '/' . ltrim($fileUrl, '/');
}

function smokeRequest(string $method, string $route, ?array $body = null, bool $authenticated = true, ?string $tokenOverride = null): HttpSmokeResponse
{
    $base = rtrim((string)getenv('FINDESK_V2_HTTP_BASE'), '/');
    $cookieName = (string)getenv('FINDESK_V2_HTTP_COOKIE');
    $token = $tokenOverride ?? (string)getenv('FINDESK_V2_HTTP_TOKEN');

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

$viewerToken = (string)getenv('FINDESK_V2_HTTP_VIEWER_TOKEN');
smokeAssert($viewerToken !== '', 'Missing FINDESK_V2_HTTP_VIEWER_TOKEN');
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

expectOk(smokeRequest('POST', "/api/workspaces/{$reportWorkspaceId}/entries", [
    'flow_id' => $reportCashFlow['id'],
    'date' => '2026-06-30',
    'raw_text' => '+200 prior month topup',
]), 'create report prior month cash income');
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

smokeAssert($reportExternal['category_code'] === null, 'external cash income should not be commercial/topup category');
smokeAssert($reportCommercial['category_code'] === 'commercial_income', 'commercial report category mismatch');
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
smokeAssert(isset($matrixRows['fuel'], $matrixRows['commercial_income'], $matrixRows['cash_topup_from_card'], $matrixRows['other'], $matrixRows['media_comms']), 'category matrix missing required rows');
smokeAssertAmount($matrixRows['fuel']['months']['7'], 200.0, 'matrix fuel July');
smokeAssertAmount($matrixRows['commercial_income']['months']['7'], 5000.0, 'matrix commercial July');
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
