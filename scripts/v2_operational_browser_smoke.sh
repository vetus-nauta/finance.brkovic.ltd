#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d /tmp/findesk-v2-browser-smoke.XXXXXX)"
DATA_DIR="${TMP_DIR}/data"
SOCKET="${TMP_DIR}/mariadb.sock"
PID_FILE="${TMP_DIR}/mariadb.pid"
LOG_FILE="${TMP_DIR}/mariadb.log"
SERVER_PID_FILE="${TMP_DIR}/php-server.pid"
DB_NAME="findesk_v2_browser_smoke"
COOKIE_NAME="findesk_v2_browser_smoke_session"
TOKEN="findesk-v2-browser-smoke-token"

cleanup() {
    if [[ -f "${SERVER_PID_FILE}" ]]; then
        kill "$(cat "${SERVER_PID_FILE}")" >/dev/null 2>&1 || true
    fi

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
require_bin node

CHROME_BIN="${PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH:-}"
if [[ -z "${CHROME_BIN}" ]]; then
    CHROME_BIN="$(command -v google-chrome || command -v google-chrome-stable || command -v chromium || command -v chromium-browser || true)"
fi

if [[ -z "${CHROME_BIN}" ]]; then
    echo "Missing browser: set PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH or install Chrome/Chromium" >&2
    exit 1
fi

node -e "require('playwright-core')" >/dev/null 2>&1 || {
    echo "Missing node dependency: run npm install" >&2
    exit 1
}

echo "FinDesk v2 browser UI smoke: setup ${TMP_DIR}"

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
    < "${ROOT}/deploy/auth_foundation.sql"

mariadb --no-defaults --socket="${SOCKET}" -uroot "${DB_NAME}" \
    < "${ROOT}/FinDesk v2.0/sql/001-clean-core-mariadb.sql"

TOKEN_HASH="$(TOKEN="${TOKEN}" php -r 'echo hash("sha256", getenv("TOKEN"));')"
mariadb --no-defaults --socket="${SOCKET}" -uroot "${DB_NAME}" <<SQL
INSERT INTO users (id, email, display_name, preferred_language, timezone, status, last_login_at)
VALUES (19101, 'v2-browser-smoke@example.test', 'V2 Browser Smoke', 'en', 'UTC', 'active', NOW());

INSERT INTO sessions (user_id, session_token_hash, device_label, ip_address, user_agent, expires_at, last_seen_at)
VALUES (19101, '${TOKEN_HASH}', 'browser-smoke', '127.0.0.1', 'FinDesk v2 browser smoke', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW());
SQL

HARNESS="${TMP_DIR}/harness"
mkdir -p "${HARNESS}/app/v2" "${HARNESS}/public/assets/v2" "${HARNESS}/storage/logs"
cp "${ROOT}/app/auth.php" "${HARNESS}/app/auth.php"
cp "${ROOT}/app/v2/Support.php" "${HARNESS}/app/v2/Support.php"
cp "${ROOT}/app/v2/InternetReferenceProvider.php" "${HARNESS}/app/v2/InternetReferenceProvider.php"
cp "${ROOT}/app/v2/LegacyExcelImporter.php" "${HARNESS}/app/v2/LegacyExcelImporter.php"
cp "${ROOT}/app/v2/Database.php" "${HARNESS}/app/v2/Database.php"
cp "${ROOT}/app/v2/Repository.php" "${HARNESS}/app/v2/Repository.php"
cp "${ROOT}/app/v2/Api.php" "${HARNESS}/app/v2/Api.php"
cp "${ROOT}/public/index.php" "${HARNESS}/public/index.php"
cp "${ROOT}/public/v2-api.php" "${HARNESS}/public/v2-api.php"
cp "${ROOT}/public/v2.php" "${HARNESS}/public/v2.php"
cp "${ROOT}/public/assets/v2/app.css" "${HARNESS}/public/assets/v2/app.css"
cp "${ROOT}/public/assets/v2/app.js" "${HARNESS}/public/assets/v2/app.js"
cp "${ROOT}/public/assets/v2/"*.svg "${HARNESS}/public/assets/v2/" 2>/dev/null || true

cat > "${HARNESS}/public/api.php" <<'PHP'
<?php

require_once __DIR__ . '/../app/auth.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'request_code') {
        $input = ql_input();
        ql_json(ql_issue_code((string)($input['email'] ?? '')));
    }
    if ($action === 'verify_code') {
        $input = ql_input();
        ql_json(ql_verify_code((string)($input['email'] ?? ''), (string)($input['code'] ?? '')));
    }
    if ($action === 'current_user') {
        ql_json(['ok' => true, 'user' => ql_current_user()]);
    }
    if ($action === 'logout') {
        ql_logout();
        ql_json(['ok' => true]);
    }

    ql_json(['ok' => false, 'error' => 'unknown_action'], 404);
} catch (Throwable $e) {
    ql_json(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()], 500);
}
PHP

cat > "${HARNESS}/app/db.php" <<PHP
<?php

function ql_config(): array
{
    return [
        'db_socket' => getenv('FINDESK_V2_BROWSER_SOCKET') ?: '',
        'db_name' => getenv('FINDESK_V2_BROWSER_DB') ?: '',
        'session_cookie_name' => '${COOKIE_NAME}',
        'app_url' => 'http://127.0.0.1',
    ];
}

function ql_db(): PDO
{
    static \$pdo = null;

    if (\$pdo instanceof PDO) {
        return \$pdo;
    }

    \$config = ql_config();
    \$dsn = 'mysql:unix_socket=' . \$config['db_socket'] . ';dbname=' . \$config['db_name'] . ';charset=utf8mb4';
    \$pdo = new PDO(\$dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return \$pdo;
}
PHP

cat > "${HARNESS}/app/config.local.php" <<'PHP'
<?php

return [
    'mail' => [
        'mode' => 'log',
    ],
];
PHP

PORT="$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr); if (!$s) { fwrite(STDERR, $errstr); exit(1); } $name = stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($name, ":"), 1);')"

FINDESK_V2_BROWSER_SOCKET="${SOCKET}" \
FINDESK_V2_BROWSER_DB="${DB_NAME}" \
php -S "127.0.0.1:${PORT}" -t "${HARNESS}/public" \
    >"${TMP_DIR}/php-server.log" 2>&1 &
echo "$!" > "${SERVER_PID_FILE}"

for _ in {1..100}; do
    if php -r "\$fp = @fsockopen('127.0.0.1', ${PORT}); if (\$fp) { fclose(\$fp); exit(0); } exit(1);" >/dev/null 2>&1; then
        break
    fi
    if ! kill -0 "$(cat "${SERVER_PID_FILE}")" >/dev/null 2>&1; then
        echo "PHP built-in server exited during startup" >&2
        cat "${TMP_DIR}/php-server.log" >&2 || true
        exit 1
    fi
    sleep 0.1
done

if ! kill -0 "$(cat "${SERVER_PID_FILE}")" >/dev/null 2>&1; then
    echo "PHP built-in server is not running" >&2
    cat "${TMP_DIR}/php-server.log" >&2 || true
    exit 1
fi

mkdir -p "${ROOT}/test-results/v2-browser-smoke"

FINDESK_V2_BROWSER_BASE="http://127.0.0.1:${PORT}" \
FINDESK_V2_BROWSER_COOKIE="${COOKIE_NAME}" \
FINDESK_V2_BROWSER_TOKEN="${TOKEN}" \
FINDESK_V2_BROWSER_CHROME="${CHROME_BIN}" \
FINDESK_V2_BROWSER_SOCKET="${SOCKET}" \
FINDESK_V2_BROWSER_DB="${DB_NAME}" \
FINDESK_V2_BROWSER_RESULTS="${ROOT}/test-results/v2-browser-smoke" \
node "${ROOT}/scripts/v2_operational_browser_smoke.cjs"
