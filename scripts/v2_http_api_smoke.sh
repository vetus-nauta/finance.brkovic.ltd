#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d /tmp/findesk-v2-http-smoke.XXXXXX)"
DATA_DIR="${TMP_DIR}/data"
SOCKET="${TMP_DIR}/mariadb.sock"
PID_FILE="${TMP_DIR}/mariadb.pid"
LOG_FILE="${TMP_DIR}/mariadb.log"
SERVER_PID_FILE="${TMP_DIR}/php-server.pid"
DB_NAME="findesk_v2_http_smoke"
COOKIE_NAME="findesk_v2_http_smoke_session"
TOKEN="findesk-v2-http-smoke-token"
VIEWER_TOKEN="findesk-v2-http-viewer-token"

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

echo "FinDesk v2 HTTP API smoke: setup ${TMP_DIR}"

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
VIEWER_TOKEN_HASH="$(TOKEN="${VIEWER_TOKEN}" php -r 'echo hash("sha256", getenv("TOKEN"));')"
mariadb --no-defaults --socket="${SOCKET}" -uroot "${DB_NAME}" <<SQL
INSERT INTO users (id, email, display_name, preferred_language, timezone, status, last_login_at)
VALUES (19001, 'v2-http-smoke@example.test', 'V2 HTTP Smoke', 'en', 'UTC', 'active', NOW());
INSERT INTO users (id, email, display_name, preferred_language, timezone, status, last_login_at)
VALUES (19002, 'v2-http-viewer@example.test', 'V2 HTTP Viewer', 'en', 'UTC', 'active', NOW());

INSERT INTO sessions (user_id, session_token_hash, device_label, ip_address, user_agent, expires_at, last_seen_at)
VALUES (19001, '${TOKEN_HASH}', 'http-smoke', '127.0.0.1', 'FinDesk v2 HTTP smoke', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW());
INSERT INTO sessions (user_id, session_token_hash, device_label, ip_address, user_agent, expires_at, last_seen_at)
VALUES (19002, '${VIEWER_TOKEN_HASH}', 'http-smoke-viewer', '127.0.0.1', 'FinDesk v2 HTTP smoke viewer', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW());
SQL

HARNESS="${TMP_DIR}/harness"
mkdir -p "${HARNESS}/app/v2" "${HARNESS}/public" "${HARNESS}/storage/logs"
cp "${ROOT}/app/auth.php" "${HARNESS}/app/auth.php"
cp "${ROOT}/app/v2/Support.php" "${HARNESS}/app/v2/Support.php"
cp "${ROOT}/app/v2/LegacyExcelImporter.php" "${HARNESS}/app/v2/LegacyExcelImporter.php"
cp "${ROOT}/app/v2/Database.php" "${HARNESS}/app/v2/Database.php"
cp "${ROOT}/app/v2/Repository.php" "${HARNESS}/app/v2/Repository.php"
cp "${ROOT}/app/v2/Api.php" "${HARNESS}/app/v2/Api.php"
cp "${ROOT}/public/v2-api.php" "${HARNESS}/public/v2-api.php"

cat > "${HARNESS}/app/db.php" <<PHP
<?php

function ql_config(): array
{
    return [
        'db_socket' => getenv('FINDESK_V2_HTTP_SOCKET') ?: '',
        'db_name' => getenv('FINDESK_V2_HTTP_DB') ?: '',
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

PORT="$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr); if (!$s) { fwrite(STDERR, $errstr); exit(1); } $name = stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($name, ":"), 1);')"

FINDESK_V2_HTTP_SOCKET="${SOCKET}" \
FINDESK_V2_HTTP_DB="${DB_NAME}" \
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

FINDESK_V2_HTTP_BASE="http://127.0.0.1:${PORT}" \
FINDESK_V2_HTTP_COOKIE="${COOKIE_NAME}" \
FINDESK_V2_HTTP_TOKEN="${TOKEN}" \
FINDESK_V2_HTTP_VIEWER_TOKEN="${VIEWER_TOKEN}" \
FINDESK_V2_HTTP_SOCKET="${SOCKET}" \
FINDESK_V2_HTTP_DB="${DB_NAME}" \
FINDESK_V2_HTTP_HARNESS="${HARNESS}" \
php "${ROOT}/scripts/v2_http_api_smoke.php"
