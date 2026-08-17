#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d /tmp/findesk-v2-db-smoke.XXXXXX)"
DATA_DIR="${TMP_DIR}/data"
SOCKET="${TMP_DIR}/mariadb.sock"
PID_FILE="${TMP_DIR}/mariadb.pid"
LOG_FILE="${TMP_DIR}/mariadb.log"
DB_NAME="findesk_v2_test"

cleanup() {
    if [[ -S "${SOCKET}" ]]; then
        mariadb-admin --no-defaults --socket="${SOCKET}" -uroot shutdown >/dev/null 2>&1 || true
    elif [[ -f "${PID_FILE}" ]]; then
        kill "$(cat "${PID_FILE}")" >/dev/null 2>&1 || true
    fi

    if [[ -f "${PID_FILE}" ]]; then
        for _ in {1..50}; do
            kill -0 "$(cat "${PID_FILE}")" >/dev/null 2>&1 || break
            sleep 0.1
        done
    fi

    rm -rf "${TMP_DIR}"
}

trap cleanup EXIT

require_bin() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Missing required command: $1" >&2
        exit 1
    fi
}

require_bin mariadb-install-db
require_bin mariadbd
require_bin mariadb
require_bin mariadb-admin
require_bin php

echo "FinDesk v2 disposable DB smoke: setup ${TMP_DIR}"

mariadb-install-db \
    --no-defaults \
    --datadir="${DATA_DIR}" \
    --auth-root-authentication-method=normal \
    --skip-test-db \
    >/dev/null

mariadbd \
    --no-defaults \
    --datadir="${DATA_DIR}" \
    --socket="${SOCKET}" \
    --pid-file="${PID_FILE}" \
    --skip-networking \
    --log-error="${LOG_FILE}" \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci \
    >"${TMP_DIR}/mariadbd.out" 2>&1 &

for _ in {1..100}; do
    if mariadb --no-defaults --socket="${SOCKET}" -uroot -e "SELECT 1" >/dev/null 2>&1; then
        break
    fi
    if [[ -f "${PID_FILE}" ]] && ! kill -0 "$(cat "${PID_FILE}")" >/dev/null 2>&1; then
        echo "mariadbd exited during startup" >&2
        cat "${LOG_FILE}" >&2 || true
        exit 1
    fi
    sleep 0.1
done

if ! mariadb --no-defaults --socket="${SOCKET}" -uroot -e "SELECT 1" >/dev/null 2>&1; then
    echo "mariadbd did not become ready" >&2
    cat "${LOG_FILE}" >&2 || true
    exit 1
fi

mariadb --no-defaults --socket="${SOCKET}" -uroot \
    -e "CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

mariadb --no-defaults --socket="${SOCKET}" -uroot "${DB_NAME}" \
    < "${ROOT}/FinDesk v2.0/sql/001-clean-core-mariadb.sql"

HARNESS="${TMP_DIR}/harness"
mkdir -p "${HARNESS}/app/v2"
cp "${ROOT}/app/v2/Support.php" "${HARNESS}/app/v2/Support.php"
cp "${ROOT}/app/v2/InternetReferenceProvider.php" "${HARNESS}/app/v2/InternetReferenceProvider.php"
cp "${ROOT}/app/v2/LegacyExcelImporter.php" "${HARNESS}/app/v2/LegacyExcelImporter.php"
cp "${ROOT}/app/v2/Database.php" "${HARNESS}/app/v2/Database.php"
cp "${ROOT}/app/v2/Repository.php" "${HARNESS}/app/v2/Repository.php"

cat > "${HARNESS}/app/db.php" <<'PHP'
<?php

function ql_config(): array
{
    return [
        'db_socket' => getenv('FINDESK_V2_SMOKE_SOCKET') ?: '',
        'db_name' => getenv('FINDESK_V2_SMOKE_DB') ?: '',
    ];
}

function ql_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = ql_config();
    $dsn = 'mysql:unix_socket=' . $config['db_socket'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
PHP

cat > "${TMP_DIR}/repository_smoke.php" <<'PHP'
<?php

declare(strict_types=1);

require getenv('FINDESK_V2_SMOKE_HARNESS') . '/app/v2/Repository.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function byFlowType(array $flows, string $type): array
{
    foreach ($flows as $flow) {
        if ($flow['type'] === $type) {
            return $flow;
        }
    }

    throw new RuntimeException("Missing flow type: {$type}");
}

function expectRepoError(callable $callback, int $status, string $error): void
{
    try {
        $callback();
    } catch (FinDeskV2HttpError $e) {
        check($e->status === $status, "expected HTTP {$status}, got {$e->status}");
        check($e->getMessage() === $error, "expected error {$error}, got {$e->getMessage()}");
        return;
    }

    throw new RuntimeException("expected error {$error}");
}

$pdo = ql_db();
$repo = new FinDeskV2Repository($pdo);
$userId = 7001;

$workspace = $repo->createWorkspace([
    'name' => 'Disposable Smoke Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'en',
], $userId);

check($workspace['id'] !== '', 'workspace create failed');

$flows = $repo->listFlows($workspace['id'], $userId);
$cashFlow = byFlowType($flows, 'cash');
$cardFlow = byFlowType($flows, 'card');
check(count($flows) === 2, 'workspace should create default Cash/Card flows only');
check($cashFlow['name'] === 'Cash', 'default Cash flow missing');
check($cardFlow['name'] === 'Card', 'default Card flow missing');

$categories = $repo->listCategories($workspace['id'], $userId);
check(count($categories) >= 10, 'category seed count too low');

$cashEntry = $repo->createEntry($workspace['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '- 12.34 fuel',
], $userId);
check($cashEntry['sign'] === '-', 'cash entry sign mismatch');
check(abs($cashEntry['amount'] - 12.34) < 0.001, 'cash entry amount mismatch');
check($cashEntry['direction'] === 'out', 'cash entry direction mismatch');
check($cashEntry['entry_type'] === 'cash_expense', 'cash entry type mismatch');
check($cashEntry['status'] === 'recognized', 'cash entry status mismatch');

$viewerUserId = 7002;
$stmt = $pdo->prepare("INSERT INTO v2_workspace_members (id, workspace_id, user_id, role) VALUES (UUID(), ?, ?, 'viewer')");
$stmt->execute([$workspace['id'], $viewerUserId]);
check(count($repo->listFlows($workspace['id'], $viewerUserId)) === 2, 'viewer read access mismatch');
expectRepoError(fn () => $repo->createEntry($workspace['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+1 viewer write',
], $viewerUserId), 403, 'workspace_read_only');
expectRepoError(fn () => $repo->updateEntry($cashEntry['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-20 viewer edit',
], $viewerUserId), 403, 'workspace_read_only');
expectRepoError(fn () => $repo->updateEntryCategory($cashEntry['id'], ['category_code' => 'tech_parts'], $viewerUserId), 403, 'workspace_read_only');
expectRepoError(fn () => $repo->deleteEntry($cashEntry['id'], $viewerUserId), 403, 'workspace_read_only');
expectRepoError(fn () => $repo->closeMonth($workspace['id'], 2026, 7, [], $viewerUserId), 403, 'workspace_read_only');

$cardExpense = $repo->createEntry($workspace['id'], [
    'flow_id' => $cardFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '- 22.00 marina',
], $userId);
check($cardExpense['entry_type'] === 'card_expense', 'card expense type mismatch');
check(abs($cardExpense['amount'] - 22.00) < 0.001, 'card expense amount mismatch');
check($cardExpense['status'] === 'recognized', 'card expense status mismatch');

$noSign = $repo->createEntry($workspace['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => 'no sign supplied',
    'amount' => '999.99',
    'status' => 'recognized',
], $userId);
check($noSign['sign'] === null, 'no-sign sign should stay null');
check($noSign['amount'] === null, 'no-sign amount should stay null');
check($noSign['direction'] === 'none', 'no-sign direction mismatch');
check($noSign['entry_type'] === 'unrecognized', 'no-sign type mismatch');
check($noSign['status'] === 'unrecognized', 'no-sign status mismatch');

$cardPlusManual = $repo->createEntry($workspace['id'], [
    'flow_id' => $cardFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+ 100.00 manual card plus',
    'amount' => '500.00',
    'status' => 'recognized',
    'source_type' => 'manual',
], $userId);
check($cardPlusManual['sign'] === '+', 'card plus manual sign mismatch');
check($cardPlusManual['amount'] === null, 'card plus manual amount should stay null');
check($cardPlusManual['direction'] === 'none', 'card plus manual direction mismatch');
check($cardPlusManual['entry_type'] === 'unrecognized', 'card plus manual type mismatch');
check($cardPlusManual['status'] === 'unrecognized', 'card plus manual status mismatch');

$cardPlusCorrection = $repo->createEntry($workspace['id'], [
    'flow_id' => $cardFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '+ 10.00 card correction',
    'source_type' => 'correction',
], $userId);
check($cardPlusCorrection['sign'] === '+', 'card plus correction sign mismatch');
check(abs($cardPlusCorrection['amount'] - 10.00) < 0.001, 'card plus correction amount mismatch');
check($cardPlusCorrection['direction'] === 'in', 'card plus correction direction mismatch');
check($cardPlusCorrection['entry_type'] === 'correction', 'card plus correction type mismatch');
check($cardPlusCorrection['status'] === 'corrected', 'card plus correction status mismatch');

$countBeforePreview = count($repo->listEntries($workspace['id'], [], $userId));
$preview = $repo->previewEntryParse($workspace['id'], [
    'flow_id' => $cashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '- 1.00 preview only',
], $userId);
$countAfterPreview = count($repo->listEntries($workspace['id'], [], $userId));
check($preview['will_save'] === false, 'parse preview should declare will_save false');
check($preview['entry_type'] === 'cash_expense', 'parse preview normalization mismatch');
check($countBeforePreview === $countAfterPreview, 'parse preview saved an entry');

$patched = $repo->updateEntryCategory($cashEntry['id'], ['category_code' => 'fuel'], $userId);
check($patched['category_code'] === 'fuel', 'category patch did not apply fuel');

$rule = $repo->createCategoryRule($workspace['id'], [
    'category_code' => 'fuel',
    'pattern' => 'diesel',
    'pattern_type' => 'keyword',
    'language' => 'en',
    'weight' => 20,
    'requires_any' => ['fuel'],
    'excludes_any' => ['crew'],
], $userId);
check($rule['category_code'] === 'fuel', 'category rule category mismatch');
check($rule['pattern'] === 'diesel', 'category rule pattern mismatch');
check($rule['weight'] === 20, 'category rule weight mismatch');

$attachment = $repo->createEntryAttachment($cashEntry['id'], [
    'file_name' => 'db-smoke.png',
    'content_base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    'image_mode' => 'original',
], $userId);
check($attachment['entry_id'] === $cashEntry['id'], 'attachment entry mismatch');
check($attachment['mime_type'] === 'image/png', 'attachment MIME mismatch');
check(str_starts_with($attachment['file_url'], 'storage/v2/attachments/'), 'attachment path must be v2 storage');
check(is_file(getenv('FINDESK_V2_SMOKE_HARNESS') . '/' . $attachment['file_url']), 'attachment stored file missing');
$listedAttachments = $repo->listEntryAttachments($cashEntry['id'], $userId);
check(count($listedAttachments) === 1, 'attachment list count mismatch');
check($listedAttachments[0]['id'] === $attachment['id'], 'attachment list id mismatch');
$attachmentPath = getenv('FINDESK_V2_SMOKE_HARNESS') . '/' . $attachment['file_url'];
$deletedAttachment = $repo->deleteAttachment($attachment['id'], $userId);
check($deletedAttachment['deleted'] === true, 'attachment delete flag mismatch');
check($deletedAttachment['file_deleted'] === true, 'attachment file delete flag mismatch');
clearstatcache(true, $attachmentPath);
check(!is_file($attachmentPath), 'attachment file remained after delete');

$closureWorkspace = $repo->createWorkspace([
    'name' => 'Disposable Closure Workspace',
    'type' => 'yacht',
    'currency' => 'EUR',
    'locale' => 'en',
    'opening_cash' => '1000.00',
], $userId);
$closureCashFlow = byFlowType($repo->listFlows($closureWorkspace['id'], $userId), 'cash');
$closureEntry = $repo->createEntry($closureWorkspace['id'], [
    'flow_id' => $closureCashFlow['id'],
    'date' => '2026-07-05',
    'raw_text' => '-100 fuel',
], $userId);
$closedMonth = $repo->closeMonth($closureWorkspace['id'], 2026, 7, ['comment' => 'db smoke close'], $userId);
check($closedMonth['closure']['is_closed'] === true, 'db smoke month close flag mismatch');
check($closedMonth['report']['comment'] === 'db smoke close', 'db smoke month close comment mismatch');
$openMonthEntry = $repo->createEntry($closureWorkspace['id'], [
    'flow_id' => $closureCashFlow['id'],
    'date' => '2026-08-05',
    'raw_text' => '+50 open month topup',
], $userId);
try {
    $repo->createEntry($closureWorkspace['id'], [
        'flow_id' => $closureCashFlow['id'],
        'date' => '2026-07-06',
        'raw_text' => '+10 should be correction',
    ], $userId);
    throw new RuntimeException('closed month create was allowed');
} catch (FinDeskV2HttpError $e) {
    check($e->status === 409, 'closed month create should return 409');
}
try {
    $repo->updateEntry($openMonthEntry['id'], [
        'flow_id' => $closureCashFlow['id'],
        'date' => '2026-07-08',
        'raw_text' => '+50 moved into closed month',
    ], $userId);
    throw new RuntimeException('open-month entry move into closed month was allowed');
} catch (FinDeskV2HttpError $e) {
    check($e->status === 409, 'closed month target update should return 409');
}
$closureCorrection = $repo->createMonthCorrection($closureWorkspace['id'], 2026, 7, [
    'flow_id' => $closureCashFlow['id'],
    'date' => '2026-07-06',
    'raw_text' => '+10 db smoke correction',
    'reference_entry_id' => $closureEntry['id'],
], $userId);
check($closureCorrection['entry_type'] === 'correction', 'db smoke correction type mismatch');
check($closureCorrection['status'] === 'corrected', 'db smoke correction status mismatch');
$closureReport = $repo->getMonthlyReport($closureWorkspace['id'], ['year' => 2026, 'month' => 7], $userId);
check(abs($closureReport['corrections'] - 10.0) < 0.001, 'db smoke correction total mismatch');
check(abs($closureReport['ending_cash'] - 910.0) < 0.001, 'db smoke correction ending cash mismatch');
$reopenedMonth = $repo->reopenMonth($closureWorkspace['id'], 2026, 7, ['comment' => ''], $userId);
check($reopenedMonth['closure']['is_closed'] === false, 'db smoke month reopen flag mismatch');

$repo->deleteEntry($cardExpense['id'], $userId);
$remainingIds = array_column($repo->listEntries($workspace['id'], [], $userId), 'id');
check(!in_array($cardExpense['id'], $remainingIds, true), 'deleted entry still listed');

$auditCount = (int)$pdo->query('SELECT COUNT(*) FROM v2_audit_log')->fetchColumn();
check($auditCount >= 8, "audit row count too low: {$auditCount}");

echo "Repository smoke assertions: OK\n";
echo "Workspace: {$workspace['id']}\n";
echo "Categories: " . count($categories) . "\n";
echo "Audit rows: {$auditCount}\n";
PHP

FINDESK_V2_SMOKE_SOCKET="${SOCKET}" \
FINDESK_V2_SMOKE_DB="${DB_NAME}" \
FINDESK_V2_SMOKE_HARNESS="${HARNESS}" \
php "${TMP_DIR}/repository_smoke.php"

echo "FinDesk v2 disposable DB smoke: OK"
