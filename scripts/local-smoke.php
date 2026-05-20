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
$adminCookie = tempnam(sys_get_temp_dir(), 'finance-admin-cookie-');
$memberCookie = tempnam(sys_get_temp_dir(), 'finance-member-cookie-');

$current = smoke_api($baseUrl, 'current_user');
if (($current['ok'] ?? false) !== true || array_key_exists('user', $current) === false) {
    smoke_fail('current_user public shape failed', $current);
}
smoke_pass('current_user endpoint responds');

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

echo "OK: local smoke completed for {$baseUrl}\n";
