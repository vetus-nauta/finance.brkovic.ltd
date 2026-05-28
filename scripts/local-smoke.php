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

function smoke_http_get_auth(string $url, string $cookieFile): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $body === '' || $status < 200 || $status >= 300) {
        smoke_fail('authenticated HTTP GET failed', ['url' => $url, 'status' => $status, 'curl_error' => $error, 'body' => substr((string)$body, 0, 500)]);
    }

    return (string)$body;
}

function smoke_api_multipart(string $baseUrl, string $action, array $fields, string $filePath, string $fileName, string $mime, string $cookieFile): array
{
    $ch = curl_init($baseUrl . '/api.php?action=' . rawurlencode($action));
    $postFields = $fields;
    $postFields['file'] = new CURLFile($filePath, $mime, $fileName);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        smoke_fail("empty multipart response from {$action}", ['curl_error' => $error, 'status' => $status]);
    }

    $json = json_decode($body, true);
    if (!is_array($json)) {
        smoke_fail("bad multipart JSON from {$action}", ['status' => $status, 'body' => substr($body, 0, 500)]);
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
foreach (['moduleCaptain', 'captain-workflow', 'captainArchivePack', 'captainSubmittedList', 'moduleMoney', 'advanceGroupSelect', 'advanceIssuePanel', 'advanceList', 'modulePremium', 'premiumTripFriends', 'premiumAdvancedMode'] as $marker) {
    if (strpos($appHtml, $marker) === false) {
        smoke_fail('Step 4/5 UI marker missing in app.php', ['marker' => $marker]);
    }
}
$appJs = smoke_http_get($baseUrl . '/assets/app.js');
foreach (['advance_create', 'advance_submit', 'advance_accept', 'advance_unaccept', 'qlLoadAdvances', 'qlLoadCaptainFin', 'qlPremiumOpen'] as $marker) {
    if (strpos($appJs, $marker) === false) {
        smoke_fail('Step 4/5 UI marker missing in app.js', ['marker' => $marker]);
    }
}
smoke_pass('Step 4/5/6/7 FinDesk, advanced money and premium UI assets are served');

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

$deleteGroupResponse = smoke_api($baseUrl, 'group_create', ['name' => 'Smoke Delete Test Group ' . $stamp], $adminCookie);
if (empty($deleteGroupResponse['ok']) || empty($deleteGroupResponse['group']['id'])) {
    smoke_fail('delete test group_create failed', $deleteGroupResponse);
}
$deleteGroupId = (int)$deleteGroupResponse['group']['id'];
$deleteInviteResponse = smoke_api($baseUrl, 'group_invite_create', [
    'group_id' => $deleteGroupId,
    'channel' => 'copy',
    'invited_email' => $memberEmail,
    'access_level' => 'base',
], $adminCookie);
if (empty($deleteInviteResponse['ok']) || empty($deleteInviteResponse['invite']['url'])) {
    smoke_fail('delete group invite failed', $deleteInviteResponse);
}
parse_str((string)parse_url($deleteInviteResponse['invite']['url'], PHP_URL_QUERY), $deleteInviteQuery);
$deleteJoinResponse = smoke_api($baseUrl, 'group_join', ['token' => (string)($deleteInviteQuery['invite'] ?? '')], $memberCookie);
if (empty($deleteJoinResponse['ok']) || (int)($deleteJoinResponse['group']['id'] ?? 0) !== $deleteGroupId) {
    smoke_fail('delete group member join failed', $deleteJoinResponse);
}
$deleteGroupFunding = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $deleteGroupId,
    'entry_type' => 'income',
    'money_type' => 'cash',
    'amount' => '5',
    'purpose' => 'Smoke preserved group evidence',
], $adminCookie);
if (empty($deleteGroupFunding['ok'])) {
    smoke_fail('delete group funding fixture failed', $deleteGroupFunding);
}
$baseDeleteDenied = smoke_api($baseUrl, 'group_delete', ['group_id' => $deleteGroupId], $memberCookie);
if (($baseDeleteDenied['error'] ?? '') !== 'admin_required') {
    smoke_fail('base member group_delete denial failed', $baseDeleteDenied);
}
$adminDeleteArchive = smoke_api($baseUrl, 'group_delete', ['group_id' => $deleteGroupId], $adminCookie);
if (
    empty($adminDeleteArchive['ok'])
    || ($adminDeleteArchive['group']['status'] ?? '') !== 'archived'
    || ($adminDeleteArchive['archive_mode'] ?? '') !== 'soft'
    || empty($adminDeleteArchive['financial_evidence']['preserved'])
    || (int)($adminDeleteArchive['financial_evidence']['before']['ledger_entries'] ?? 0) !== 1
    || (int)($adminDeleteArchive['financial_evidence']['after']['ledger_entries'] ?? 0) !== 1
    || (int)($adminDeleteArchive['members_archived'] ?? 0) < 2
) {
    smoke_fail('admin soft group archive failed', $adminDeleteArchive);
}
$adminDeleteAgain = smoke_api($baseUrl, 'group_delete', ['group_id' => $deleteGroupId], $adminCookie);
if (empty($adminDeleteAgain['ok']) || empty($adminDeleteAgain['already_deleted'])) {
    smoke_fail('soft group archive idempotency failed', $adminDeleteAgain);
}
$adminGroupsAfterDelete = smoke_api($baseUrl, 'group_list', [], $adminCookie);
$memberGroupsAfterDelete = smoke_api($baseUrl, 'group_list', [], $memberCookie);
foreach ([$adminGroupsAfterDelete, $memberGroupsAfterDelete] as $groupListAfterDelete) {
    foreach ($groupListAfterDelete['groups'] ?? [] as $row) {
        if ((int)($row['id'] ?? 0) === $deleteGroupId) {
            smoke_fail('archived group still visible in active group list', $groupListAfterDelete);
        }
    }
}
$archivedGroupLedgerDenied = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $deleteGroupId,
    'entry_type' => 'expense',
    'money_type' => 'cash',
    'amount' => '1',
    'purpose' => 'Smoke denied archived group ledger',
], $adminCookie);
if (($archivedGroupLedgerDenied['error'] ?? '') !== 'access_denied') {
    smoke_fail('archived group ledger write guard failed', $archivedGroupLedgerDenied);
}
smoke_pass('admin soft-archives test group while base member is denied and evidence is preserved');

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

$groupFunding = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $groupId,
    'entry_type' => 'income',
    'money_type' => 'cash',
    'amount' => '1000',
    'purpose' => 'Smoke group funding',
], $adminCookie);
if (empty($groupFunding['ok'])) {
    smoke_fail('admin group funding failed', $groupFunding);
}

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

$advanceReturnCreate = smoke_api($baseUrl, 'advance_create', [
    'group_id' => $groupId,
    'assigned_to_user_id' => (int)$worker['id'],
    'amount' => '50',
    'title' => 'Smoke return correction',
], $adminCookie);
if (empty($advanceReturnCreate['ok']) || empty($advanceReturnCreate['advance']['id']) || empty($advanceReturnCreate['advance']['on_the_go_tape_id'])) {
    smoke_fail('advance return fixture create failed', $advanceReturnCreate);
}
$advanceReturnId = (int)$advanceReturnCreate['advance']['id'];
$advanceReturnTapeId = (int)$advanceReturnCreate['advance']['on_the_go_tape_id'];

$balanceAfterOpenAdvances = smoke_api($baseUrl, 'ledger_balance', ['group_id' => $groupId], $adminCookie);
if (
    empty($balanceAfterOpenAdvances['ok'])
    || round((float)($balanceAfterOpenAdvances['summary']['accountable_issued_open'] ?? 0), 2) !== 150.00
    || round((float)($balanceAfterOpenAdvances['summary']['available_cash_balance'] ?? 0), 2) !== 837.66
) {
    smoke_fail('available cash after open advances failed', $balanceAfterOpenAdvances);
}

$advanceCardBeforeSpend = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $advanceTapeId], $workerCookie);
if (empty($advanceCardBeforeSpend['ok']) || round((float)($advanceCardBeforeSpend['card']['cash_received'] ?? 0), 2) !== 100.00) {
    smoke_fail('advance live report base amount missing', $advanceCardBeforeSpend);
}

$advanceCapture = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $advanceTapeId,
    'cash_received' => '999',
    'notes' => '-40 Smoke advance provision',
    'replace_tape' => 1,
    'start_next' => 1,
], $workerCookie);
if (empty($advanceCapture['ok']) || (int)($advanceCapture['tape_id'] ?? 0) !== $advanceTapeId || (int)($advanceCapture['synced_count'] ?? 0) !== 1) {
    smoke_fail('advance live report signed sync failed', $advanceCapture);
}

$workerAdvancesAfterSpend = smoke_api($baseUrl, 'advance_list', ['group_id' => $groupId], $workerCookie);
$spentAdvanceRow = null;
foreach ($workerAdvancesAfterSpend['advances'] ?? [] as $advanceRow) {
    if ((int)($advanceRow['id'] ?? 0) === $advanceId) {
        $spentAdvanceRow = $advanceRow;
        break;
    }
}
if (
    empty($workerAdvancesAfterSpend['ok'])
    || !$spentAdvanceRow
    || round((float)($spentAdvanceRow['summary']['cash_in'] ?? 0), 2) !== 100.00
    || round((float)($spentAdvanceRow['summary']['cash_left'] ?? 0), 2) !== 60.00
) {
    smoke_fail('advance remaining summary failed', $workerAdvancesAfterSpend);
}
$untouchedAdvanceCard = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $advanceReturnTapeId], $workerCookie);
if (
    empty($untouchedAdvanceCard['ok'])
    || round((float)($untouchedAdvanceCard['card']['cash_received'] ?? 0), 2) !== 50.00
    || count($untouchedAdvanceCard['items'] ?? []) !== 0
) {
    smoke_fail('next live report reused another advance tape', $untouchedAdvanceCard);
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
if ((int)($advanceAccept['rollover_advance_id'] ?? 0) <= 0 || (int)($advanceAccept['rollover_tape_id'] ?? 0) <= 0) {
    smoke_fail('advance remaining rollover missing', $advanceAccept);
}
smoke_pass('admin accepts advance, expands expenses and reserves remaining cash');

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

$rolloverAdvanceId = (int)($advanceAccept['rollover_advance_id'] ?? 0);
$advanceCashReturn = smoke_api($baseUrl, 'advance_return_cash', [
    'id' => $rolloverAdvanceId,
    'note' => 'Smoke returned remaining cash',
], $adminCookie);
if (
    empty($advanceCashReturn['ok'])
    || empty($advanceCashReturn['cash_returned'])
    || round((float)($advanceCashReturn['amount_returned'] ?? 0), 2) !== 60.00
) {
    smoke_fail('advance cash remainder return failed', $advanceCashReturn);
}
smoke_pass('admin returns unused advance remainder to cash pool');

$balanceAfterAcceptedAdvance = smoke_api($baseUrl, 'ledger_balance', ['group_id' => $groupId], $adminCookie);
if (
    empty($balanceAfterAcceptedAdvance['ok'])
    || round((float)($balanceAfterAcceptedAdvance['summary']['accountable_issued_open'] ?? 0), 2) !== 50.00
    || round((float)($balanceAfterAcceptedAdvance['summary']['accountable_cash_left_open'] ?? 0), 2) !== 50.00
    || round((float)($balanceAfterAcceptedAdvance['summary']['available_cash_balance'] ?? 0), 2) !== 897.66
) {
    smoke_fail('available cash after accepted advance failed', $balanceAfterAcceptedAdvance);
}

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

$liveTape = smoke_api($baseUrl, 'on_the_go_tape_create', [
    'title' => 'Smoke Live Report',
    'cash_received' => '600',
], $memberCookie);
if (empty($liveTape['ok']) || empty($liveTape['tape']['id'])) {
    smoke_fail('live report tape create failed', $liveTape);
}
$liveTapeId = (int)$liveTape['tape']['id'];

$liveSave = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $liveTapeId,
    'cash_received' => '600',
    'notes' => "-45 продукты\n-67 топливо\n+100 получил от руководителя",
    'replace_tape' => 1,
    'start_next' => 1,
], $memberCookie);
if (empty($liveSave['ok']) || (int)($liveSave['tape_id'] ?? 0) !== $liveTapeId || (int)($liveSave['synced_count'] ?? 0) !== 3) {
    smoke_fail('live report signed save failed', $liveSave);
}
if (empty($liveSave['next_tape_id']) || (int)$liveSave['next_tape_id'] === $liveTapeId) {
    smoke_fail('live report next tape failed', $liveSave);
}

$liveCards = smoke_api($baseUrl, 'on_the_go_card_list', ['limit' => 80], $memberCookie);
$liveCard = null;
foreach ($liveCards['cards'] ?? [] as $card) {
    if ((int)($card['id'] ?? 0) === $liveTapeId) {
        $liveCard = $card;
        break;
    }
}
if (empty($liveCards['ok']) || !$liveCard) {
    smoke_fail('live report saved card missing from list', $liveCards);
}
if ((int)($liveCard['summary']['records_count'] ?? 0) !== 3) {
    smoke_fail('live report list summary records failed', $liveCard);
}
if (round((float)($liveCard['summary']['after_amount'] ?? 0), 2) !== 588.00) {
    smoke_fail('live report list summary arithmetic failed', $liveCard);
}

$liveDetail = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $liveTapeId], $memberCookie);
if (empty($liveDetail['ok']) || (int)($liveDetail['card']['id'] ?? 0) !== $liveTapeId || count($liveDetail['items'] ?? []) !== 3) {
    smoke_fail('live report card detail failed', $liveDetail);
}
$liveDescriptions = array_map(static fn($item) => (string)($item['description'] ?? ''), $liveDetail['items']);
if (!in_array('продукты', $liveDescriptions, true) || !in_array('топливо', $liveDescriptions, true)) {
    smoke_fail('live report detail descriptions failed', $liveDetail);
}

$cardStreamTape = smoke_api($baseUrl, 'on_the_go_tape_create', [
    'stream_type' => 'card',
    'title' => 'Smoke Card Live Report',
    'cash_received' => '999',
], $memberCookie);
if (empty($cardStreamTape['ok']) || empty($cardStreamTape['tape']['id']) || ($cardStreamTape['tape']['stream_type'] ?? '') !== 'card') {
    smoke_fail('card stream tape create failed', $cardStreamTape);
}
$cardStreamTapeId = (int)$cardStreamTape['tape']['id'];
if (round((float)($cardStreamTape['tape']['cash_received'] ?? 0), 2) !== 0.00) {
    smoke_fail('card stream must not keep cash base', $cardStreamTape);
}
$cardStreamSave = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $cardStreamTapeId,
    'stream_type' => 'card',
    'cash_received' => '999',
    'notes' => "-12 card smoke\n-8 fuel card",
    'replace_tape' => 1,
    'start_next' => 0,
], $memberCookie);
if (empty($cardStreamSave['ok']) || (int)($cardStreamSave['synced_count'] ?? 0) !== 2) {
    smoke_fail('card stream signed sync failed', $cardStreamSave);
}
$cardStreamDetail = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $cardStreamTapeId], $memberCookie);
if (empty($cardStreamDetail['ok'])
    || ($cardStreamDetail['card']['stream_type'] ?? '') !== 'card'
    || round((float)($cardStreamDetail['card']['summary']['card_out'] ?? 0), 2) !== 20.00
    || round((float)($cardStreamDetail['card']['summary']['cash_left'] ?? 0), 2) !== 0.00
    || round((float)($cardStreamDetail['card']['summary']['cash_delta'] ?? 0), 2) !== 0.00
    || round((float)($cardStreamDetail['card']['summary']['after_amount'] ?? 0), 2) !== -20.00
) {
    smoke_fail('card stream arithmetic failed', $cardStreamDetail);
}
$cardStreamTypes = array_unique(array_map(static fn($item) => (string)($item['capture_type'] ?? ''), $cardStreamDetail['items'] ?? []));
if ($cardStreamTypes !== ['noncash_out']) {
    smoke_fail('card stream must create only noncash rows', ['types' => $cardStreamTypes, 'detail' => $cardStreamDetail]);
}
$cardOnlyList = smoke_api($baseUrl, 'on_the_go_card_list', ['stream_type' => 'card', 'limit' => 80], $memberCookie);
$cardOnlyFound = false;
foreach ($cardOnlyList['cards'] ?? [] as $card) {
    if ((int)($card['id'] ?? 0) === $cardStreamTapeId) {
        $cardOnlyFound = true;
        break;
    }
}
if (empty($cardOnlyList['ok']) || !$cardOnlyFound) {
    smoke_fail('card stream list filter failed', $cardOnlyList);
}
$cardStreamDelete = smoke_api($baseUrl, 'on_the_go_card_delete', ['id' => $cardStreamTapeId], $memberCookie);
if (empty($cardStreamDelete['ok']) || empty($cardStreamDelete['deleted'])) {
    smoke_fail('card stream cleanup failed', $cardStreamDelete);
}
smoke_pass('Live Report cash/card streams stay separated');

$fieldDraftId = 'smoke-draft-' . $stamp;
$fieldOperationId = 'smoke-op-' . $stamp;
$fieldUploadId = 'smoke-upload-fail-' . $stamp;
$fieldDraftSave = smoke_api($baseUrl, 'on_the_go_field_draft_save', [
    'client_draft_id' => $fieldDraftId,
    'client_operation_id' => 'smoke-draft-save-' . $stamp,
    'stream_type' => 'cash',
    'cash_received' => '100',
    'notes' => '-25 Smoke durable field draft',
], $memberCookie);
if (empty($fieldDraftSave['ok'])
    || empty($fieldDraftSave['draft']['id'])
    || ($fieldDraftSave['draft']['raw_notes'] ?? '') !== '-25 Smoke durable field draft'
    || round((float)($fieldDraftSave['draft']['draft_totals']['cash_left'] ?? 0), 2) !== 75.00
) {
    smoke_fail('field combat durable draft save failed', $fieldDraftSave);
}
$fieldTapeId = (int)($fieldDraftSave['tape_id'] ?? 0);
$fieldRecover = smoke_api($baseUrl, 'on_the_go_field_recover', ['client_draft_id' => $fieldDraftId], $memberCookie);
if (empty($fieldRecover['ok'])
    || ($fieldRecover['draft']['raw_notes'] ?? '') !== '-25 Smoke durable field draft'
    || (int)($fieldRecover['tape_id'] ?? 0) !== $fieldTapeId
) {
    smoke_fail('field combat durable recovery failed', $fieldRecover);
}
$fieldProofPending = smoke_api($baseUrl, 'on_the_go_proof_state_begin', [
    'client_draft_id' => $fieldDraftId,
    'client_upload_id' => $fieldUploadId,
    'original_name' => 'smoke-proof.jpg',
    'mime_type' => 'image/jpeg',
    'size_bytes' => 123,
], $memberCookie);
if (empty($fieldProofPending['ok']) || ($fieldProofPending['proof_state']['status'] ?? '') !== 'pending') {
    smoke_fail('field combat proof pending state failed', $fieldProofPending);
}
$fieldProofFailed = smoke_api($baseUrl, 'on_the_go_proof_state_fail', [
    'client_upload_id' => $fieldUploadId,
    'status' => 'retry_needed',
    'last_error' => 'smoke_upload_interrupted',
], $memberCookie);
if (empty($fieldProofFailed['ok'])
    || ($fieldProofFailed['proof_state']['status'] ?? '') !== 'retry_needed'
    || (int)($fieldProofFailed['proof_state']['retry_count'] ?? 0) < 1
) {
    smoke_fail('field combat proof retry state failed', $fieldProofFailed);
}
$fieldRecoverAfterProof = smoke_api($baseUrl, 'on_the_go_field_recover', ['client_draft_id' => $fieldDraftId], $memberCookie);
$foundRetryProof = false;
foreach ($fieldRecoverAfterProof['proof_states'] ?? [] as $proofState) {
    if (($proofState['client_upload_id'] ?? '') === $fieldUploadId && ($proofState['status'] ?? '') === 'retry_needed') {
        $foundRetryProof = true;
        break;
    }
}
if (empty($fieldRecoverAfterProof['ok']) || !$foundRetryProof) {
    smoke_fail('field combat proof retry recovery failed', $fieldRecoverAfterProof);
}
$fieldSync = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'client_operation_id' => $fieldOperationId,
    'tape_id' => $fieldTapeId,
    'stream_type' => 'cash',
    'cash_received' => '100',
    'notes' => '-25 Smoke durable field draft',
    'replace_tape' => 1,
    'start_next' => 0,
], $memberCookie);
$fieldSyncReplay = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'client_operation_id' => $fieldOperationId,
    'tape_id' => $fieldTapeId,
    'stream_type' => 'cash',
    'cash_received' => '100',
    'notes' => '-25 Smoke durable field draft',
    'replace_tape' => 1,
    'start_next' => 0,
], $memberCookie);
if (empty($fieldSync['ok'])
    || (int)($fieldSync['synced_count'] ?? 0) !== 1
    || empty($fieldSyncReplay['ok'])
    || empty($fieldSyncReplay['idempotent'])
) {
    smoke_fail('field combat idempotent sync failed', ['first' => $fieldSync, 'second' => $fieldSyncReplay]);
}
$fieldCaptureId = (int)($fieldSync['items'][0]['id'] ?? 0);
if ($fieldCaptureId <= 0) {
    smoke_fail('field combat scanner capture missing', $fieldSync);
}
$scannerBundleId = 'smoke-scanner-bundle-' . $stamp;
$scannerOriginalTemp = tempnam(sys_get_temp_dir(), 'findesk-scanner-original-');
$scannerPdfTemp = tempnam(sys_get_temp_dir(), 'findesk-scanner-pdf-');
file_put_contents($scannerOriginalTemp, 'Smoke scanner original source');
file_put_contents($scannerPdfTemp, "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF\n");
$scannerOriginalUploadId = 'smoke-scanner-original-' . $stamp;
$scannerOriginal = smoke_api_multipart($baseUrl, 'on_the_go_upload_file', [
    'capture_id' => $fieldCaptureId,
    'client_upload_id' => $scannerOriginalUploadId,
    'proof_role' => 'scanner_original',
    'proof_bundle_id' => $scannerBundleId,
    'metadata_json' => json_encode(['artifact_role' => 'scanner_original', 'smoke' => true], JSON_UNESCAPED_SLASHES),
], $scannerOriginalTemp, 'smoke-scanner-original.jpg', 'image/jpeg', $memberCookie);
if (empty($scannerOriginal['ok'])
    || ($scannerOriginal['file']['proof_role'] ?? '') !== 'scanner_original'
    || ($scannerOriginal['file']['proof_bundle_id'] ?? '') !== $scannerBundleId
    || empty($scannerOriginal['file']['file_hash_sha256'])
) {
    @unlink($scannerOriginalTemp);
    @unlink($scannerPdfTemp);
    smoke_fail('scanner original upload failed', $scannerOriginal);
}
$scannerOriginalFileId = (int)($scannerOriginal['file']['id'] ?? 0);
$scannerOriginalReplay = smoke_api_multipart($baseUrl, 'on_the_go_upload_file', [
    'capture_id' => $fieldCaptureId,
    'client_upload_id' => $scannerOriginalUploadId,
    'proof_role' => 'scanner_original',
    'proof_bundle_id' => $scannerBundleId,
    'metadata_json' => json_encode(['artifact_role' => 'scanner_original', 'smoke' => 'retry'], JSON_UNESCAPED_SLASHES),
], $scannerOriginalTemp, 'smoke-scanner-original.jpg', 'image/jpeg', $memberCookie);
if (empty($scannerOriginalReplay['ok']) || empty($scannerOriginalReplay['idempotent'])) {
    @unlink($scannerOriginalTemp);
    @unlink($scannerPdfTemp);
    smoke_fail('scanner original idempotent retry failed', $scannerOriginalReplay);
}
$scannerPdfUploadId = 'smoke-scanner-pdf-' . $stamp;
$scannerPdf = smoke_api_multipart($baseUrl, 'on_the_go_upload_file', [
    'capture_id' => $fieldCaptureId,
    'client_upload_id' => $scannerPdfUploadId,
    'client_draft_id' => $fieldDraftId,
    'proof_role' => 'scanner_cleaned_pdf',
    'proof_bundle_id' => $scannerBundleId,
    'source_file_id' => $scannerOriginalFileId,
    'metadata_json' => json_encode(['artifact_role' => 'scanner_cleaned_pdf', 'source_file_id' => $scannerOriginalFileId, 'smoke' => true], JSON_UNESCAPED_SLASHES),
], $scannerPdfTemp, 'smoke-scanner-cleaned.pdf', 'application/pdf', $memberCookie);
if (empty($scannerPdf['ok'])
    || ($scannerPdf['file']['proof_role'] ?? '') !== 'scanner_cleaned_pdf'
    || ($scannerPdf['file']['proof_bundle_id'] ?? '') !== $scannerBundleId
    || (int)($scannerPdf['file']['source_file_id'] ?? 0) !== $scannerOriginalFileId
    || empty($scannerPdf['file']['file_hash_sha256'])
    || ($scannerPdf['proof_state']['status'] ?? '') !== 'uploaded'
) {
    @unlink($scannerOriginalTemp);
    @unlink($scannerPdfTemp);
    smoke_fail('scanner cleaned PDF upload failed', $scannerPdf);
}
$scannerPdfReplay = smoke_api_multipart($baseUrl, 'on_the_go_upload_file', [
    'capture_id' => $fieldCaptureId,
    'client_upload_id' => $scannerPdfUploadId,
    'client_draft_id' => $fieldDraftId,
    'proof_role' => 'scanner_cleaned_pdf',
    'proof_bundle_id' => $scannerBundleId,
    'source_file_id' => $scannerOriginalFileId,
    'metadata_json' => json_encode(['artifact_role' => 'scanner_cleaned_pdf', 'source_file_id' => $scannerOriginalFileId, 'smoke' => 'retry'], JSON_UNESCAPED_SLASHES),
], $scannerPdfTemp, 'smoke-scanner-cleaned.pdf', 'application/pdf', $memberCookie);
@unlink($scannerOriginalTemp);
@unlink($scannerPdfTemp);
if (empty($scannerPdfReplay['ok']) || empty($scannerPdfReplay['idempotent'])) {
    smoke_fail('scanner cleaned PDF idempotent retry failed', $scannerPdfReplay);
}
$scannerFiles = smoke_api($baseUrl, 'on_the_go_file_list', ['capture_id' => $fieldCaptureId], $memberCookie);
$scannerRoles = [];
foreach ($scannerFiles['files'] ?? [] as $file) {
    if (($file['proof_bundle_id'] ?? '') === $scannerBundleId) {
        $scannerRoles[] = (string)($file['proof_role'] ?? '');
    }
}
sort($scannerRoles);
if (empty($scannerFiles['ok']) || $scannerRoles !== ['scanner_cleaned_pdf', 'scanner_original']) {
    smoke_fail('scanner proof chain file list failed', ['roles' => $scannerRoles, 'files' => $scannerFiles]);
}
smoke_pass('Receipt Scanner stores original and cleaned PDF as one proof bundle');
$fieldDelete = smoke_api($baseUrl, 'on_the_go_card_delete', ['id' => $fieldTapeId], $memberCookie);
if (empty($fieldDelete['ok']) || empty($fieldDelete['deleted'])) {
    smoke_fail('field combat cleanup failed', $fieldDelete);
}
smoke_pass('Field Combat durable draft/proof retry/idempotent sync work');

$liveDelete = smoke_api($baseUrl, 'on_the_go_card_delete', ['id' => $liveTapeId], $memberCookie);
if (empty($liveDelete['ok']) || empty($liveDelete['deleted']) || (int)($liveDelete['tape_id'] ?? 0) !== $liveTapeId) {
    smoke_fail('live report delete failed', $liveDelete);
}
$liveDetailAfterDelete = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $liveTapeId], $memberCookie);
if (($liveDetailAfterDelete['error'] ?? '') !== 'card_not_found') {
    smoke_fail('live report deleted card still opens', $liveDetailAfterDelete);
}

$findeskTape = smoke_api($baseUrl, 'on_the_go_tape_create', [
    'group_id' => $groupId,
    'title' => 'Smoke FinDesk Transfer',
    'cash_received' => '300',
], $memberCookie);
if (empty($findeskTape['ok']) || empty($findeskTape['tape']['id'])) {
    smoke_fail('findesk transfer tape create failed', $findeskTape);
}
$findeskTapeId = (int)$findeskTape['tape']['id'];
$findeskSave = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $findeskTapeId,
    'group_id' => $groupId,
    'cash_received' => '300',
    'notes' => '-10 Smoke transferred to FinDesk',
    'replace_tape' => 1,
    'start_next' => 0,
], $memberCookie);
if (empty($findeskSave['ok']) || (int)($findeskSave['tape_id'] ?? 0) !== $findeskTapeId) {
    smoke_fail('findesk transfer save failed', $findeskSave);
}
$findeskSubmit = smoke_api($baseUrl, 'on_the_go_card_submit', [
    'id' => $findeskTapeId,
    'group_id' => $groupId,
], $memberCookie);
if (empty($findeskSubmit['ok']) || ($findeskSubmit['card']['card_state'] ?? '') !== 'submitted') {
    smoke_fail('findesk transfer submit failed', $findeskSubmit);
}
$balanceAfterFindeskSubmit = smoke_api($baseUrl, 'ledger_balance', ['group_id' => $groupId], $adminCookie);
if (empty($balanceAfterFindeskSubmit['ok'])
    || round((float)($balanceAfterFindeskSubmit['summary']['available_cash_balance'] ?? 0), 2) !== 892.66
    || (int)($balanceAfterFindeskSubmit['working_cards']['submitted_records'] ?? 0) < 1
) {
    smoke_fail('findesk submitted live report not reflected in working balance', $balanceAfterFindeskSubmit);
}
$captainTapeList = smoke_api($baseUrl, 'on_the_go_tape_list', ['group_id' => $groupId], $adminCookie);
$captainActive = null;
foreach (($captainTapeList['tapes'] ?? []) as $candidate) {
    if ((int)($candidate['id'] ?? 0) === (int)($captainTapeList['active_tape_id'] ?? 0)) {
        $captainActive = $candidate;
        break;
    }
}
if (empty($captainTapeList['ok'])
    || !$captainActive
    || round((float)($captainActive['cash_received'] ?? 0), 2) !== 892.66
    || round((float)($captainActive['summary']['cash_left'] ?? 0), 2) !== 892.66
) {
    smoke_fail('findesk current card ignores submitted live reports', $captainTapeList);
}
$nextFindeskTapeId = (int)($findeskSubmit['next_tape_id'] ?? 0);
if ($nextFindeskTapeId <= 0) {
    smoke_fail('findesk transfer did not seed next sequential card', $findeskSubmit);
}
$nextFindeskSave = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $nextFindeskTapeId,
    'group_id' => $groupId,
    'cash_received' => '290',
    'notes' => '-5 Smoke second card waits',
    'replace_tape' => 1,
    'start_next' => 0,
], $memberCookie);
if (empty($nextFindeskSave['ok']) || (int)($nextFindeskSave['tape_id'] ?? 0) !== $nextFindeskTapeId) {
    smoke_fail('findesk sequential guard second save failed', $nextFindeskSave);
}
$blockedSecondSubmit = smoke_api($baseUrl, 'on_the_go_card_submit', [
    'id' => $nextFindeskTapeId,
    'group_id' => $groupId,
], $memberCookie);
if (!in_array(($blockedSecondSubmit['error'] ?? ''), ['previous_live_report_waits_findesk', 'another_live_report_waits_findesk'], true)
    || (int)($blockedSecondSubmit['blocking_card_id'] ?? 0) !== $findeskTapeId
) {
    smoke_fail('findesk sequential submit guard failed', $blockedSecondSubmit);
}
$firstAfterBlocked = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $findeskTapeId], $memberCookie);
$secondAfterBlocked = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $nextFindeskTapeId], $memberCookie);
if (($firstAfterBlocked['card']['card_state'] ?? '') !== 'submitted'
    || ($secondAfterBlocked['card']['card_state'] ?? '') !== 'draft'
) {
    smoke_fail('findesk sequential guard changed wrong card state', [
        'first' => $firstAfterBlocked,
        'second' => $secondAfterBlocked,
    ]);
}
$blockedSecondDelete = smoke_api($baseUrl, 'on_the_go_card_delete', ['id' => $nextFindeskTapeId], $memberCookie);
if (empty($blockedSecondDelete['ok']) || empty($blockedSecondDelete['deleted'])) {
    smoke_fail('findesk sequential guard cleanup failed', $blockedSecondDelete);
}
$findeskDelete = smoke_api($baseUrl, 'on_the_go_card_delete', ['id' => $findeskTapeId], $memberCookie);
if (($findeskDelete['error'] ?? '') !== 'card_not_deletable_after_findesk') {
    smoke_fail('submitted live report delete guard failed', $findeskDelete);
}
smoke_pass('Live Report save/list/detail/delete work');

$finalGroupResponse = smoke_api($baseUrl, 'group_create', ['name' => 'Smoke Final Report Group ' . $stamp], $adminCookie);
if (empty($finalGroupResponse['ok']) || empty($finalGroupResponse['group']['id'])) {
    smoke_fail('final report group_create failed', $finalGroupResponse);
}
$finalGroupId = (int)$finalGroupResponse['group']['id'];
$finalIncome = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $finalGroupId,
    'entry_type' => 'income',
    'money_type' => 'cash',
    'amount' => '1000',
    'purpose' => 'Smoke historical final income',
], $adminCookie);
if (empty($finalIncome['ok'])) {
    smoke_fail('final report income create failed', $finalIncome);
}
$finalTape = smoke_api($baseUrl, 'on_the_go_tape_create', [
    'group_id' => $finalGroupId,
    'title' => 'Smoke historical final card',
    'cash_received' => '1000',
], $adminCookie);
if (empty($finalTape['ok']) || empty($finalTape['tape']['id'])) {
    smoke_fail('final report live card create failed', $finalTape);
}
$finalTapeId = (int)$finalTape['tape']['id'];
$finalSave = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $finalTapeId,
    'group_id' => $finalGroupId,
    'cash_received' => '1000',
    'notes' => '-600 Smoke historical final expense',
    'replace_tape' => 1,
    'start_next' => 0,
], $adminCookie);
if (empty($finalSave['ok']) || (int)($finalSave['synced_count'] ?? 0) !== 1) {
    smoke_fail('final report live card save failed', $finalSave);
}
$finalCardForProof = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $finalTapeId], $adminCookie);
if (empty($finalCardForProof['ok']) || empty($finalCardForProof['items'][0]['id'])) {
    smoke_fail('final report proof capture lookup failed', $finalCardForProof);
}
$finalCaptureId = (int)$finalCardForProof['items'][0]['id'];
$proofTemp = tempnam(sys_get_temp_dir(), 'findesk-package-proof-');
file_put_contents($proofTemp, 'Smoke final package proof');
$finalProofUpload = smoke_api_multipart($baseUrl, 'on_the_go_upload_file', [
    'capture_id' => $finalCaptureId,
    'client_upload_id' => 'smoke-final-package-proof-' . $stamp,
], $proofTemp, 'smoke-final-package-proof.txt', 'text/plain', $adminCookie);
@unlink($proofTemp);
if (empty($finalProofUpload['ok'])) {
    smoke_fail('final report proof upload failed', $finalProofUpload);
}
$finalScannerBundleId = 'smoke-final-scanner-bundle-' . $stamp;
$finalScannerOriginalTemp = tempnam(sys_get_temp_dir(), 'findesk-final-scanner-original-');
$finalScannerPdfTemp = tempnam(sys_get_temp_dir(), 'findesk-final-scanner-pdf-');
file_put_contents($finalScannerOriginalTemp, 'Smoke final scanner original');
file_put_contents($finalScannerPdfTemp, "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF\n");
$finalScannerOriginal = smoke_api_multipart($baseUrl, 'on_the_go_upload_file', [
    'capture_id' => $finalCaptureId,
    'client_upload_id' => $finalScannerBundleId . ':original:' . $finalCaptureId,
    'proof_role' => 'scanner_original',
    'proof_bundle_id' => $finalScannerBundleId,
    'metadata_json' => json_encode(['artifact_role' => 'scanner_original', 'smoke' => 'final_package'], JSON_UNESCAPED_SLASHES),
], $finalScannerOriginalTemp, 'smoke-final-scanner-original.jpg', 'image/jpeg', $adminCookie);
if (empty($finalScannerOriginal['ok'])
    || ($finalScannerOriginal['file']['proof_role'] ?? '') !== 'scanner_original'
    || empty($finalScannerOriginal['file']['file_hash_sha256'])
) {
    @unlink($finalScannerOriginalTemp);
    @unlink($finalScannerPdfTemp);
    smoke_fail('final package scanner original upload failed', $finalScannerOriginal);
}
$finalScannerOriginalFileId = (int)($finalScannerOriginal['file']['id'] ?? 0);
$finalScannerPdf = smoke_api_multipart($baseUrl, 'on_the_go_upload_file', [
    'capture_id' => $finalCaptureId,
    'client_upload_id' => 'smoke-final-scanner-pdf-' . $stamp,
    'proof_role' => 'scanner_cleaned_pdf',
    'proof_bundle_id' => $finalScannerBundleId,
    'source_file_id' => $finalScannerOriginalFileId,
    'metadata_json' => json_encode(['artifact_role' => 'scanner_cleaned_pdf', 'source_file_id' => $finalScannerOriginalFileId, 'smoke' => 'final_package'], JSON_UNESCAPED_SLASHES),
], $finalScannerPdfTemp, 'smoke-final-scanner-cleaned.pdf', 'application/pdf', $adminCookie);
@unlink($finalScannerOriginalTemp);
@unlink($finalScannerPdfTemp);
if (empty($finalScannerPdf['ok'])
    || ($finalScannerPdf['file']['proof_role'] ?? '') !== 'scanner_cleaned_pdf'
    || (int)($finalScannerPdf['file']['source_file_id'] ?? 0) !== $finalScannerOriginalFileId
    || empty($finalScannerPdf['file']['file_hash_sha256'])
) {
    smoke_fail('final package scanner cleaned PDF upload failed', $finalScannerPdf);
}
$finalInclude = smoke_api($baseUrl, 'on_the_go_card_include', [
    'id' => $finalTapeId,
    'group_id' => $finalGroupId,
], $adminCookie);
if (empty($finalInclude['ok']) || ($finalInclude['card']['card_state'] ?? '') !== 'included') {
    smoke_fail('final report card include failed', $finalInclude);
}
$finalBeforeExport = smoke_api($baseUrl, 'ledger_group_google_sheet', ['group_id' => $finalGroupId], $adminCookie);
if (empty($finalBeforeExport['ok'])
    || strpos((string)($finalBeforeExport['tsv'] ?? ''), 'Smoke historical final income') === false
    || strpos((string)($finalBeforeExport['tsv'] ?? ''), 'Smoke historical final expense') === false
    || strpos((string)($finalBeforeExport['tsv'] ?? ''), '1000.00') === false
    || strpos((string)($finalBeforeExport['tsv'] ?? ''), '600.00') === false
) {
    smoke_fail('final report pre-final export truth failed', $finalBeforeExport);
}
$finalizeReport = smoke_api($baseUrl, 'ledger_group_finalize_report', ['group_id' => $finalGroupId], $adminCookie);
if (empty($finalizeReport['ok']) || (int)($finalizeReport['finalized'] ?? 0) < 1 || (int)($finalizeReport['report_id'] ?? 0) <= 0) {
    smoke_fail('final report finalize failed', $finalizeReport);
}
$finalReportId = (int)$finalizeReport['report_id'];
$finalReportList = smoke_api($baseUrl, 'ledger_group_final_report_list', ['group_id' => $finalGroupId], $adminCookie);
$foundFinalReport = false;
foreach ($finalReportList['reports'] ?? [] as $reportRow) {
    if ((int)($reportRow['id'] ?? 0) === $finalReportId && !empty($reportRow['snapshot_available']) && !empty($reportRow['package_available'])) {
        $foundFinalReport = true;
        break;
    }
}
if (empty($finalReportList['ok']) || !$foundFinalReport) {
    smoke_fail('final report list missing snapshot/package report', $finalReportList);
}
$finalReportDetail = smoke_api($baseUrl, 'ledger_group_final_report_detail', ['report_id' => $finalReportId], $adminCookie);
if (empty($finalReportDetail['ok'])
    || round((float)($finalReportDetail['snapshot']['totals']['income'] ?? 0), 2) !== 1000.00
    || round((float)($finalReportDetail['snapshot']['totals']['expense'] ?? 0), 2) !== 600.00
    || round((float)($finalReportDetail['snapshot']['totals']['cash_balance'] ?? 0), 2) !== 400.00
) {
    smoke_fail('final report historical snapshot totals failed', $finalReportDetail);
}
$finalPackage = smoke_api($baseUrl, 'ledger_group_final_report_package', ['report_id' => $finalReportId], $adminCookie);
if (empty($finalPackage['ok'])
    || ($finalPackage['package_type'] ?? '') !== 'group_final_report'
    || (int)($finalPackage['package']['report_id'] ?? 0) !== $finalReportId
    || count($finalPackage['package']['participants'] ?? []) < 1
    || count($finalPackage['package']['captures'] ?? []) < 1
    || count($finalPackage['package']['proofs'] ?? []) < 1
    || count($finalPackage['package']['audit_refs'] ?? []) < 1
    || !isset($finalPackage['package']['accountable'], $finalPackage['package']['messages'])
    || round((float)($finalPackage['package']['summary']['received_money'] ?? 0), 2) !== 1000.00
    || round((float)($finalPackage['package']['summary']['physical_cash_spent'] ?? 0), 2) !== 600.00
) {
    smoke_fail('final report package detail failed', $finalPackage);
}
$finalScannerPackageRoles = [];
$finalScannerPackageCleaned = null;
foreach ($finalPackage['package']['proofs'] ?? [] as $proofRow) {
    if (($proofRow['proof_bundle_id'] ?? '') !== $finalScannerBundleId) {
        continue;
    }
    $finalScannerPackageRoles[] = (string)($proofRow['proof_role'] ?? '');
    if (($proofRow['proof_role'] ?? '') === 'scanner_cleaned_pdf') {
        $finalScannerPackageCleaned = $proofRow;
    }
}
sort($finalScannerPackageRoles);
if ($finalScannerPackageRoles !== ['scanner_cleaned_pdf', 'scanner_original']
    || !$finalScannerPackageCleaned
    || (int)($finalScannerPackageCleaned['derived_from_file_id'] ?? 0) !== $finalScannerOriginalFileId
    || empty($finalScannerPackageCleaned['file_hash_sha256'])
) {
    smoke_fail('final report package scanner proof metadata failed', [
        'roles' => $finalScannerPackageRoles,
        'cleaned' => $finalScannerPackageCleaned,
        'bundle' => $finalScannerBundleId,
    ]);
}
$packageProofUrl = '';
foreach ($finalPackage['package']['proofs'] ?? [] as $proofRow) {
    if (!empty($proofRow['download_url'])) {
        $packageProofUrl = (string)$proofRow['download_url'];
        break;
    }
}
if ($packageProofUrl === '') {
    smoke_fail('final report package proof download url missing', $finalPackage['package']['proofs'] ?? []);
}
$proofBody = smoke_http_get_auth($baseUrl . $packageProofUrl, $adminCookie);
if (strpos($proofBody, 'Smoke final package proof') === false) {
    smoke_fail('final report package proof download failed', ['url' => $packageProofUrl, 'body' => substr($proofBody, 0, 200)]);
}
$historicalExport = smoke_api($baseUrl, 'ledger_group_final_report_google_sheet', ['report_id' => $finalReportId], $adminCookie);
if (empty($historicalExport['ok'])
    || strpos((string)($historicalExport['tsv'] ?? ''), 'Smoke historical final income') === false
    || strpos((string)($historicalExport['tsv'] ?? ''), 'Smoke historical final expense') === false
    || strpos((string)($historicalExport['tsv'] ?? ''), '1000.00') === false
    || strpos((string)($historicalExport['tsv'] ?? ''), '600.00') === false
) {
    smoke_fail('final report historical export failed', $historicalExport);
}
sleep(1);
$currentIncome = smoke_api($baseUrl, 'ledger_create', [
    'group_id' => $finalGroupId,
    'entry_type' => 'income',
    'money_type' => 'cash',
    'amount' => '50',
    'purpose' => 'Smoke current period income',
], $adminCookie);
if (empty($currentIncome['ok'])) {
    smoke_fail('current period income create failed', $currentIncome);
}
$currentTape = smoke_api($baseUrl, 'on_the_go_tape_create', [
    'group_id' => $finalGroupId,
    'title' => 'Smoke current period live card',
    'cash_received' => '0',
], $adminCookie);
if (empty($currentTape['ok']) || empty($currentTape['tape']['id'])) {
    smoke_fail('current period live card create failed', $currentTape);
}
$currentTapeId = (int)$currentTape['tape']['id'];
$currentSave = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $currentTapeId,
    'group_id' => $finalGroupId,
    'cash_received' => '0',
    'notes' => '-25 Smoke current period live expense',
    'replace_tape' => 1,
    'start_next' => 0,
], $adminCookie);
if (empty($currentSave['ok']) || (int)($currentSave['synced_count'] ?? 0) !== 1) {
    smoke_fail('current period live card save failed', $currentSave);
}
$currentInclude = smoke_api($baseUrl, 'on_the_go_card_include', [
    'id' => $currentTapeId,
    'group_id' => $finalGroupId,
], $adminCookie);
if (empty($currentInclude['ok']) || ($currentInclude['card']['card_state'] ?? '') !== 'included') {
    smoke_fail('current period live card include failed', $currentInclude);
}
$openCarryover = smoke_api($baseUrl, 'ledger_group_open_received_funds', ['group_id' => $finalGroupId], $adminCookie);
$foundCarryover400 = false;
$foundCurrentIncome50 = false;
foreach ($openCarryover['carryovers'] ?? [] as $row) {
    if (round((float)($row['amount'] ?? 0), 2) === 400.00) {
        $foundCarryover400 = true;
    }
}
foreach ($openCarryover['entries'] ?? [] as $row) {
    if (($row['purpose'] ?? '') === 'Smoke historical final income') {
        smoke_fail('open period includes finalized income as current income', $openCarryover);
    }
    if (($row['purpose'] ?? '') === 'Smoke current period income'
        && round((float)($row['amount'] ?? 0), 2) === 50.00
    ) {
        $foundCurrentIncome50 = true;
    }
}
if (empty($openCarryover['ok']) || !$foundCarryover400 || !$foundCurrentIncome50) {
    smoke_fail('open period carryover after final report failed', $openCarryover);
}
$currentAfterFinalExport = smoke_api($baseUrl, 'ledger_group_google_sheet', ['group_id' => $finalGroupId], $adminCookie);
if (empty($currentAfterFinalExport['ok'])
    || strpos((string)($currentAfterFinalExport['tsv'] ?? ''), 'Переходящий остаток') === false
    || strpos((string)($currentAfterFinalExport['tsv'] ?? ''), '400.00') === false
    || strpos((string)($currentAfterFinalExport['tsv'] ?? ''), 'Smoke current period income') === false
    || strpos((string)($currentAfterFinalExport['tsv'] ?? ''), '50.00') === false
    || strpos((string)($currentAfterFinalExport['tsv'] ?? ''), 'Включенные живые отчеты текущего периода') === false
    || strpos((string)($currentAfterFinalExport['tsv'] ?? ''), '25.00') === false
    || strpos((string)($currentAfterFinalExport['tsv'] ?? ''), 'Smoke historical final income') !== false
) {
    smoke_fail('current open-period export after finalization failed', $currentAfterFinalExport);
}
$finalPackageAfterCurrent = smoke_api($baseUrl, 'ledger_group_final_report_package', ['report_id' => $finalReportId], $adminCookie);
if (empty($finalPackageAfterCurrent['ok'])
    || json_encode($finalPackageAfterCurrent['package']['summary'] ?? []) !== json_encode($finalPackage['package']['summary'] ?? [])
    || count($finalPackageAfterCurrent['package']['proofs'] ?? []) !== count($finalPackage['package']['proofs'] ?? [])
) {
    smoke_fail('final report package mutated after current activity', [
        'before' => $finalPackage['package']['summary'] ?? [],
        'after' => $finalPackageAfterCurrent['package']['summary'] ?? [],
    ]);
}
smoke_pass('final report package/snapshot/export and open carryover stay separated');

$unacceptGroupResponse = smoke_api($baseUrl, 'group_create', ['name' => 'Smoke Unaccept Group ' . $stamp], $adminCookie);
if (empty($unacceptGroupResponse['ok']) || empty($unacceptGroupResponse['group']['id'])) {
    smoke_fail('unaccept group_create failed', $unacceptGroupResponse);
}
$unacceptGroupId = (int)$unacceptGroupResponse['group']['id'];
$unacceptInvite = smoke_api($baseUrl, 'group_invite_create', [
    'group_id' => $unacceptGroupId,
    'channel' => 'copy',
    'invited_email' => $workerEmail,
    'access_level' => 'base',
], $adminCookie);
if (empty($unacceptInvite['ok']) || empty($unacceptInvite['invite']['url'])) {
    smoke_fail('unaccept invite failed', $unacceptInvite);
}
parse_str((string)parse_url($unacceptInvite['invite']['url'], PHP_URL_QUERY), $unacceptInviteQuery);
$unacceptJoin = smoke_api($baseUrl, 'group_join', ['token' => (string)($unacceptInviteQuery['invite'] ?? '')], $workerCookie);
if (empty($unacceptJoin['ok']) || (int)($unacceptJoin['group']['id'] ?? 0) !== $unacceptGroupId) {
    smoke_fail('unaccept worker join failed', $unacceptJoin);
}
$unacceptCreate = smoke_api($baseUrl, 'advance_create', [
    'group_id' => $unacceptGroupId,
    'assigned_to_user_id' => (int)$worker['id'],
    'amount' => '80',
    'title' => 'Smoke unaccept accountable cash',
], $adminCookie);
if (empty($unacceptCreate['ok']) || empty($unacceptCreate['advance']['id']) || empty($unacceptCreate['advance']['on_the_go_tape_id'])) {
    smoke_fail('unaccept advance_create failed', $unacceptCreate);
}
$unacceptAdvanceId = (int)$unacceptCreate['advance']['id'];
$unacceptTapeId = (int)$unacceptCreate['advance']['on_the_go_tape_id'];
$unacceptSave = smoke_api($baseUrl, 'on_the_go_signed_sync', [
    'tape_id' => $unacceptTapeId,
    'cash_received' => '80',
    'notes' => '-20 Smoke unaccept rollback',
    'replace_tape' => 1,
    'start_next' => 1,
], $workerCookie);
if (empty($unacceptSave['ok']) || (int)($unacceptSave['synced_count'] ?? 0) !== 1) {
    smoke_fail('unaccept signed sync failed', $unacceptSave);
}
$unacceptSubmit = smoke_api($baseUrl, 'advance_submit', [
    'id' => $unacceptAdvanceId,
    'actual_remaining' => '60',
    'note' => 'Smoke unaccept submitted',
], $workerCookie);
if (empty($unacceptSubmit['ok']) || ($unacceptSubmit['advance']['status'] ?? '') !== 'submitted') {
    smoke_fail('unaccept advance_submit failed', $unacceptSubmit);
}
$unacceptAccept = smoke_api($baseUrl, 'advance_accept', [
    'id' => $unacceptAdvanceId,
    'note' => 'Smoke unaccept accepted',
], $adminCookie);
if (empty($unacceptAccept['ok']) || ($unacceptAccept['advance']['status'] ?? '') !== 'accepted') {
    smoke_fail('unaccept advance_accept failed', $unacceptAccept);
}
$unacceptRollback = smoke_api($baseUrl, 'advance_unaccept', [
    'id' => $unacceptAdvanceId,
    'note' => 'Smoke rollback accepted advance',
], $adminCookie);
if (empty($unacceptRollback['ok']) || ($unacceptRollback['advance']['status'] ?? '') !== 'returned') {
    smoke_fail('advance_unaccept failed', $unacceptRollback);
}
$unacceptList = smoke_api($baseUrl, 'advance_list', ['group_id' => $unacceptGroupId], $adminCookie);
$foundReturnedUnaccept = false;
$foundAcceptedUnaccept = false;
foreach ($unacceptList['advances'] ?? [] as $advanceRow) {
    if ((int)($advanceRow['id'] ?? 0) !== $unacceptAdvanceId) {
        continue;
    }
    $foundReturnedUnaccept = ($advanceRow['status'] ?? '') === 'returned';
    $foundAcceptedUnaccept = ($advanceRow['status'] ?? '') === 'accepted';
}
if (empty($unacceptList['ok']) || !$foundReturnedUnaccept || $foundAcceptedUnaccept || (int)($unacceptList['totals']['accepted_count'] ?? 0) !== 0) {
    smoke_fail('advance_unaccept did not remove accepted package count', $unacceptList);
}
$unacceptLedger = smoke_api($baseUrl, 'ledger_list', ['group_id' => $unacceptGroupId, 'limit' => 80], $adminCookie);
foreach ($unacceptLedger['entries'] ?? [] as $entry) {
    if (($entry['purpose'] ?? '') === 'Smoke unaccept rollback') {
        smoke_fail('advance_unaccept left accepted ledger row active', $unacceptLedger);
    }
}
$unacceptCardDetail = smoke_api($baseUrl, 'on_the_go_card_detail', ['id' => $unacceptTapeId], $workerCookie);
if (empty($unacceptCardDetail['ok']) || count($unacceptCardDetail['items'] ?? []) !== 1) {
    smoke_fail('advance_unaccept did not reopen live report card', $unacceptCardDetail);
}
smoke_pass('admin can return accepted advance from working package');

@unlink($adminCookie);
@unlink($memberCookie);
@unlink($workerCookie);

echo "OK: local smoke completed for {$baseUrl}\n";
