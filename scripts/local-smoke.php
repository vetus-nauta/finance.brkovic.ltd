<?php

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:18888', '/');
$root = dirname(__DIR__);
$logPath = $root . '/storage/logs/auth_codes.log';

function smoke_fail(string $message, array $context = []): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    if ($context) {
        fwrite(STDERR, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
    exit(1);
}

function smoke_pass(string $message): void
{
    echo "PASS: {$message}\n";
}

function smoke_api(string $baseUrl, string $action, array $payload = [], string $cookieFile = ''): array
{
    $ch = curl_init($baseUrl . '/api.php?action=' . rawurlencode($action));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($cookieFile !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        smoke_fail("empty response from {$action}", ['curl_error' => $error, 'status' => $status]);
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        smoke_fail("bad JSON from {$action}", ['status' => $status, 'body' => substr($body, 0, 500)]);
    }

    $json['_http_status'] = $status;
    return $json;
}

function smoke_http_get(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 15,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $body === '' || $status < 200 || $status >= 300) {
        smoke_fail('HTTP GET failed', ['url' => $url, 'status' => $status, 'curl_error' => $error]);
    }

    return (string)$body;
}

function smoke_latest_code(string $logPath, string $email): string
{
    if (!is_file($logPath)) {
        smoke_fail('auth code log not found', ['path' => $logPath]);
    }

    $lines = array_reverse(file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    foreach ($lines as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (($parts[1] ?? '') === $email && preg_match('/^\d{6}$/', $parts[2] ?? '')) {
            return $parts[2];
        }
    }

    smoke_fail('auth code not found for email', ['email' => $email]);
}

function smoke_login(string $baseUrl, string $email, string $cookieFile, string $logPath): array
{
    $requested = smoke_api($baseUrl, 'request_code', ['email' => $email], $cookieFile);
    if (empty($requested['ok'])) {
        smoke_fail('request_code failed', $requested);
    }

    $code = smoke_latest_code($logPath, $email);
    $verified = smoke_api($baseUrl, 'verify_code', ['email' => $email, 'code' => $code], $cookieFile);
    if (empty($verified['ok']) || empty($verified['user'])) {
        smoke_fail('verify_code failed', $verified);
    }

    return $verified['user'];
}

$stamp = date('Ymd-His');
$adminEmail = "admin-{$stamp}@example.test";
$memberEmail = "member-{$stamp}@example.test";
$workerEmail = "worker-{$stamp}@example.test";
$adminCookie = tempnam(sys_get_temp_dir(), 'finance-admin-cookie-');
$memberCookie = tempnam(sys_get_temp_dir(), 'finance-member-cookie-');
$workerCookie = tempnam(sys_get_temp_dir(), 'finance-worker-cookie-');

$current = smoke_api($baseUrl, 'current_user');
if (($current['ok'] ?? false) !== true || array_key_exists('user', $current) === false) {
    smoke_fail('current_user public shape failed', $current);
}
smoke_pass('current_user endpoint responds');

$appHtml = smoke_http_get($baseUrl . '/app.php');
foreach (['moduleMoney', 'advanceGroupSelect', 'advanceIssuePanel', 'advanceList'] as $marker) {
    if (strpos($appHtml, $marker) === false) {
        smoke_fail('Step 4 money UI marker missing in app.php', ['marker' => $marker]);
    }
}
$appJs = smoke_http_get($baseUrl . '/assets/app.js');
foreach (['advance_create', 'advance_submit', 'advance_accept', 'qlLoadAdvances'] as $marker) {
    if (strpos($appJs, $marker) === false) {
        smoke_fail('Step 4 money UI marker missing in app.js', ['marker' => $marker]);
    }
}
smoke_pass('Step 4 accountable money UI assets are served');

$admin = smoke_login($baseUrl, $adminEmail, $adminCookie, $logPath);
smoke_pass('admin login by 6-digit code');

$groupName = 'Smoke Group ' . $stamp;
$groupResponse = smoke_api($baseUrl, 'group_create', ['name' => $groupName], $adminCookie);
if (empty($groupResponse['ok']) || empty($groupResponse['group']['id'])) {
    smoke_fail('group_create failed', $groupResponse);
}
$groupId = (int)$groupResponse['group']['id'];
smoke_pass('admin creates group');

$wrongInviteResponse = smoke_api($baseUrl, 'group_invite_create', [
    'group_id' => $groupId,
    'channel' => 'copy',
    'invited_email' => 'wrong-' . $memberEmail,
    'access_level' => 'base',
], $adminCookie);
if (empty($wrongInviteResponse['ok']) || empty($wrongInviteResponse['invite']['url'])) {
    smoke_fail('email-bound invite failed', $wrongInviteResponse);
}
parse_str((string)parse_url($wrongInviteResponse['invite']['url'], PHP_URL_QUERY), $wrongInviteQuery);
$wrongInviteToken = (string)($wrongInviteQuery['invite'] ?? '');

$inviteResponse = smoke_api($baseUrl, 'group_invite_create', [
    'group_id' => $groupId,
    'channel' => 'copy',
    'invited_email' => $memberEmail,
    'access_level' => 'base',
], $adminCookie);
if (empty($inviteResponse['ok']) || empty($inviteResponse['invite']['url'])) {
    smoke_fail('group_invite_create failed', $inviteResponse);
}
if (($inviteResponse['invite']['access_level'] ?? '') !== 'base') {
    smoke_fail('invite access level failed', $inviteResponse);
}
parse_str((string)parse_url($inviteResponse['invite']['url'], PHP_URL_QUERY), $inviteQuery);
$inviteToken = (string)($inviteQuery['invite'] ?? '');
if ($inviteToken === '') {
    smoke_fail('invite token missing', $inviteResponse);
}
smoke_pass('admin creates invite');

$member = smoke_login($baseUrl, $memberEmail, $memberCookie, $logPath);
smoke_pass('member login by 6-digit code');

$wrongJoinResponse = smoke_api($baseUrl, 'group_join', ['token' => $wrongInviteToken], $memberCookie);
if (($wrongJoinResponse['error'] ?? '') !== 'invite_email_mismatch') {
    smoke_fail('invite email mismatch guard failed', $wrongJoinResponse);
}
smoke_pass('email-bound invite rejects wrong user');

$joinResponse = smoke_api($baseUrl, 'group_join', ['token' => $inviteToken], $memberCookie);
if (empty($joinResponse['ok']) || (int)($joinResponse['group']['id'] ?? 0) !== $groupId) {
    smoke_fail('group_join failed', $joinResponse);
}
if (($joinResponse['group']['access_level'] ?? '') !== 'base') {
    smoke_fail('base invite did not create base membership', $joinResponse);
}
smoke_pass('member joins group by base invite');

$baseMemberList = smoke_api($baseUrl, 'group_members', ['group_id' => $groupId], $memberCookie);
if (empty($baseMemberList['ok']) || count($baseMemberList['members'] ?? []) !== 1) {
    smoke_fail('base member visibility failed', $baseMemberList);
}
smoke_pass('base member sees only own membership');

$membersResponse = smoke_api($baseUrl, 'group_members', ['group_id' => $groupId], $adminCookie);
if (empty($membersResponse['ok']) || count($membersResponse['members'] ?? []) < 2) {
    smoke_fail('group_members failed', $membersResponse);
}
smoke_pass('admin sees group members');

$messageResponse = smoke_api($baseUrl, 'message_send', [
    'group_id' => $groupId,
    'message_text' => 'Smoke hello from member',
], $memberCookie);
if (empty($messageResponse['ok'])) {
    smoke_fail('message_send failed', $messageResponse);
}
$unreadResponse = smoke_api($baseUrl, 'message_unread', [], $adminCookie);
if (empty($unreadResponse['ok']) || (int)($unreadResponse['unread_count'] ?? 0) < 1) {
    smoke_fail('message_unread failed', $unreadResponse);
}
smoke_pass('group messages and unread work');

$baseGroupLedgerDenied = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $groupId,
    'entry_type' => 'expense',
    'money_type' => 'cash',
    'amount' => '12.34',
    'purpose' => 'Smoke denied fuel',
], $memberCookie);
if (($baseGroupLedgerDenied['error'] ?? '') !== 'access_denied') {
    smoke_fail('base group ledger denial failed', $baseGroupLedgerDenied);
}
smoke_pass('base member cannot write direct group ledger');

$basePersonalLedger = smoke_api($baseUrl, 'ledger_create', [
    'entry_type' => 'income',
    'money_type' => 'cash',
    'amount' => '10',
    'purpose' => 'Smoke base personal income',
], $memberCookie);
if (empty($basePersonalLedger['ok'])) {
    smoke_fail('base personal full profile failed', $basePersonalLedger);
}
smoke_pass('base member keeps full personal profile');

$accessUpdate = smoke_api($baseUrl, 'group_member_access_update', [
    'group_id' => $groupId,
    'user_id' => (int)$member['id'],
    'access_level' => 'manager',
], $adminCookie);
if (empty($accessUpdate['ok']) || ($accessUpdate['member']['access_level'] ?? '') !== 'manager') {
    smoke_fail('admin member access update failed', $accessUpdate);
}
smoke_pass('admin promotes member to manager');

$managerMemberList = smoke_api($baseUrl, 'group_members', ['group_id' => $groupId], $memberCookie);
if (empty($managerMemberList['ok']) || count($managerMemberList['members'] ?? []) < 2) {
    smoke_fail('manager member visibility failed', $managerMemberList);
}
smoke_pass('manager sees group members');

$ledgerResponse = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $groupId,
    'entry_type' => 'expense',
    'money_type' => 'cash',
    'amount' => '12.34',
    'purpose' => 'Smoke fuel',
], $memberCookie);
if (empty($ledgerResponse['ok'])) {
    smoke_fail('manager ledger_create failed', $ledgerResponse);
}
$adminLedger = smoke_api($baseUrl, 'ledger_list', ['group_id' => $groupId], $adminCookie);
if (empty($adminLedger['ok']) || count($adminLedger['entries'] ?? []) < 1) {
    smoke_fail('admin ledger_list failed', $adminLedger);
}
smoke_pass('manager group ledger write and admin visibility work');

$advancedUpdate = smoke_api($baseUrl, 'group_member_access_update', [
    'group_id' => $groupId,
    'user_id' => (int)$member['id'],
    'access_level' => 'advanced',
], $adminCookie);
if (empty($advancedUpdate['ok']) || ($advancedUpdate['member']['access_level'] ?? '') !== 'advanced') {
    smoke_fail('admin advanced access update failed', $advancedUpdate);
}
$advancedInvite = smoke_api($baseUrl, 'group_invite_create', [
    'group_id' => $groupId,
    'channel' => 'copy',
    'access_level' => 'base',
], $memberCookie);
if (empty($advancedInvite['ok'])) {
    smoke_fail('advanced member admin capability failed', $advancedInvite);
}
smoke_pass('advanced member can manage invites');

$workerInviteResponse = smoke_api($baseUrl, 'group_invite_create', [
    'group_id' => $groupId,
    'channel' => 'copy',
    'invited_email' => $workerEmail,
    'access_level' => 'base',
], $adminCookie);
if (empty($workerInviteResponse['ok']) || empty($workerInviteResponse['invite']['url'])) {
    smoke_fail('worker invite failed', $workerInviteResponse);
}
parse_str((string)parse_url($workerInviteResponse['invite']['url'], PHP_URL_QUERY), $workerInviteQuery);
$workerInviteToken = (string)($workerInviteQuery['invite'] ?? '');
if ($workerInviteToken === '') {
    smoke_fail('worker invite token missing', $workerInviteResponse);
}
$worker = smoke_login($baseUrl, $workerEmail, $workerCookie, $logPath);
$workerJoin = smoke_api($baseUrl, 'group_join', ['token' => $workerInviteToken], $workerCookie);
if (empty($workerJoin['ok']) || ($workerJoin['group']['access_level'] ?? '') !== 'base') {
    smoke_fail('worker base join failed', $workerJoin);
}
smoke_pass('second base member joins for advance flow');

$workerLedgerDenied = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $groupId,
    'entry_type' => 'expense',
    'money_type' => 'cash',
    'amount' => '9.50',
    'purpose' => 'Smoke worker denied direct ledger',
], $workerCookie);
if (($workerLedgerDenied['error'] ?? '') !== 'access_denied') {
    smoke_fail('worker base direct ledger denial failed', $workerLedgerDenied);
}
smoke_pass('advance worker cannot write direct group ledger');

$advanceCreate = smoke_api($baseUrl, 'advance_create', [
    'group_id' => $groupId,
    'assigned_to_user_id' => (int)$worker['id'],
    'amount' => '100',
    'title' => 'Smoke accountable cash',
], $adminCookie);
if (empty($advanceCreate['ok']) || empty($advanceCreate['advance']['id']) || empty($advanceCreate['advance']['on_the_go_tape_id'])) {
    smoke_fail('advance_create failed', $advanceCreate);
}
if (($advanceCreate['advance']['status'] ?? '') !== 'issued') {
    smoke_fail('advance initial status failed', $advanceCreate);
}
$advanceId = (int)$advanceCreate['advance']['id'];
$advanceTapeId = (int)$advanceCreate['advance']['on_the_go_tape_id'];
smoke_pass('admin issues accountable money without ledger expense');

$workerAdvances = smoke_api($baseUrl, 'advance_list', ['group_id' => $groupId], $workerCookie);
if (empty($workerAdvances['ok']) || count($workerAdvances['advances'] ?? []) !== 1) {
    smoke_fail('worker advance list failed', $workerAdvances);
}
if (round((float)($workerAdvances['advances'][0]['summary']['cash_in'] ?? 0), 2) !== 100.00) {
    smoke_fail('worker advance received summary failed', $workerAdvances);
}
smoke_pass('base worker sees received/spent/remaining for own advance');

$advanceCapture = smoke_api($baseUrl, 'on_the_go_create', [
    'tape_id' => $advanceTapeId,
    'capture_type' => 'cash_out',
    'amount' => '40',
    'description' => 'Smoke advance provision',
], $workerCookie);
if (empty($advanceCapture['ok']) || empty($advanceCapture['capture']['id'])) {
    smoke_fail('advance on_the_go_create failed', $advanceCapture);
}

$workerAdvancesAfterSpend = smoke_api($baseUrl, 'advance_list', ['group_id' => $groupId], $workerCookie);
if (empty($workerAdvancesAfterSpend['ok']) || round((float)($workerAdvancesAfterSpend['advances'][0]['summary']['cash_left'] ?? 0), 2) !== 60.00) {
    smoke_fail('advance remaining summary failed', $workerAdvancesAfterSpend);
}
smoke_pass('advance summary updates from On the Go capture');

$advanceSubmit = smoke_api($baseUrl, 'advance_submit', [
    'id' => $advanceId,
    'actual_remaining' => '60',
    'note' => 'Smoke checked cash in pocket',
], $workerCookie);
if (empty($advanceSubmit['ok']) || ($advanceSubmit['advance']['status'] ?? '') !== 'submitted') {
    smoke_fail('advance_submit failed', $advanceSubmit);
}
if (round((float)($advanceSubmit['advance']['difference_amount'] ?? 1), 2) !== 0.00) {
    smoke_fail('advance difference calculation failed', $advanceSubmit);
}
smoke_pass('base worker submits advance for moderation');

$adminAdvanceList = smoke_api($baseUrl, 'advance_list', ['group_id' => $groupId], $adminCookie);
$adminSawSubmittedAdvance = false;
foreach ($adminAdvanceList['advances'] ?? [] as $advanceRow) {
    if ((int)($advanceRow['id'] ?? 0) === $advanceId && ($advanceRow['status'] ?? '') === 'submitted') {
        $adminSawSubmittedAdvance = true;
        break;
    }
}
if (empty($adminAdvanceList['ok']) || !$adminSawSubmittedAdvance) {
    smoke_fail('admin submitted advance visibility failed', $adminAdvanceList);
}
smoke_pass('admin sees submitted advance red-line candidate');

$advanceAccept = smoke_api($baseUrl, 'advance_accept', [
    'id' => $advanceId,
    'note' => 'Smoke accepted',
], $adminCookie);
if (empty($advanceAccept['ok']) || ($advanceAccept['advance']['status'] ?? '') !== 'accepted' || (int)($advanceAccept['entries_created'] ?? 0) !== 1) {
    smoke_fail('advance_accept failed', $advanceAccept);
}
smoke_pass('admin accepts advance and expands expenses into group ledger');

$adminLedgerAfterAdvance = smoke_api($baseUrl, 'ledger_list', ['group_id' => $groupId, 'limit' => 300], $adminCookie);
$foundAdvanceLedgerEntry = false;
foreach ($adminLedgerAfterAdvance['entries'] ?? [] as $entry) {
    if (($entry['purpose'] ?? '') === 'Smoke advance provision' && ($entry['owner_email'] ?? '') === $workerEmail) {
        $foundAdvanceLedgerEntry = true;
        break;
    }
}
if (empty($adminLedgerAfterAdvance['ok']) || !$foundAdvanceLedgerEntry) {
    smoke_fail('accepted advance ledger entry missing', $adminLedgerAfterAdvance);
}
smoke_pass('accepted advance appears in group ledger with source user');

$advanceReturnCreate = smoke_api($baseUrl, 'advance_create', [
    'group_id' => $groupId,
    'assigned_to_user_id' => (int)$worker['id'],
    'amount' => '50',
    'title' => 'Smoke return correction',
], $adminCookie);
if (empty($advanceReturnCreate['ok']) || empty($advanceReturnCreate['advance']['id'])) {
    smoke_fail('advance return fixture create failed', $advanceReturnCreate);
}
$advanceReturnId = (int)$advanceReturnCreate['advance']['id'];

$advanceDiscrepancy = smoke_api($baseUrl, 'advance_submit', [
    'id' => $advanceReturnId,
    'actual_remaining' => '45',
    'note' => 'Smoke mismatch',
], $workerCookie);
if (empty($advanceDiscrepancy['ok']) || ($advanceDiscrepancy['advance']['status'] ?? '') !== 'discrepancy') {
    smoke_fail('advance discrepancy submit failed', $advanceDiscrepancy);
}

$advanceReturn = smoke_api($baseUrl, 'advance_return', [
    'id' => $advanceReturnId,
    'note' => 'Smoke return for correction',
], $adminCookie);
if (empty($advanceReturn['ok']) || ($advanceReturn['advance']['status'] ?? '') !== 'returned') {
    smoke_fail('advance_return failed', $advanceReturn);
}
smoke_pass('admin returns mismatched advance for correction');

$personalLedger = smoke_api($baseUrl, 'ledger_create', [
    'entry_type' => 'income',
    'money_type' => 'cash',
    'amount' => '50',
    'purpose' => 'Smoke personal income',
], $adminCookie);
if (empty($personalLedger['ok']) || empty($personalLedger['entry']['id'])) {
    smoke_fail('personal ledger_create failed', $personalLedger);
}
$personalEntryId = (int)$personalLedger['entry']['id'];
$updated = smoke_api($baseUrl, 'ledger_update', [
    'id' => $personalEntryId,
    'amount' => '51',
    'purpose' => 'Smoke personal income updated',
], $adminCookie);
if (empty($updated['ok'])) {
    smoke_fail('ledger_update failed', $updated);
}
$deleted = smoke_api($baseUrl, 'ledger_delete', ['id' => $personalEntryId], $adminCookie);
if (empty($deleted['ok'])) {
    smoke_fail('ledger_delete failed', $deleted);
}
smoke_pass('personal ledger update/delete work');

$tapeResponse = smoke_api($baseUrl, 'on_the_go_tape_create', [
    'title' => 'Smoke Pocket',
    'cash_received' => '100',
], $memberCookie);
if (empty($tapeResponse['ok']) || empty($tapeResponse['tape']['id'])) {
    smoke_fail('on_the_go_tape_create failed', $tapeResponse);
}
$tapeId = (int)$tapeResponse['tape']['id'];
$captureResponse = smoke_api($baseUrl, 'on_the_go_create', [
    'tape_id' => $tapeId,
    'capture_type' => 'cash_out',
    'amount' => '25',
    'description' => 'Smoke cash out',
], $memberCookie);
if (empty($captureResponse['ok'])) {
    smoke_fail('on_the_go_create failed', $captureResponse);
}
$onTheGoList = smoke_api($baseUrl, 'on_the_go_list', ['tape_id' => $tapeId], $memberCookie);
if (empty($onTheGoList['ok']) || count($onTheGoList['items'] ?? []) < 1) {
    smoke_fail('on_the_go_list failed', $onTheGoList);
}
smoke_pass('On the Go tape/capture/list work');

@unlink($adminCookie);
@unlink($memberCookie);
@unlink($workerCookie);

echo "OK: local smoke completed for {$baseUrl}\n";
