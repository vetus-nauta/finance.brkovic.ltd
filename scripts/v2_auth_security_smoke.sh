#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d /tmp/findesk-v2-auth-smoke.XXXXXX)"
DATA_DIR="${TMP_DIR}/data"
SOCKET="${TMP_DIR}/mariadb.sock"
PID_FILE="${TMP_DIR}/mariadb.pid"
LOG_FILE="${TMP_DIR}/mariadb.log"
SERVER_PID_FILE="${TMP_DIR}/php-server.pid"
DB_NAME="findesk_v2_auth_smoke"

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

echo "FinDesk v2 auth security smoke: setup ${TMP_DIR}"

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

HARNESS="${TMP_DIR}/harness"
mkdir -p "${HARNESS}/app" "${HARNESS}/public" "${HARNESS}/storage/logs"
cp "${ROOT}/app/auth.php" "${HARNESS}/app/auth.php"
cp "${ROOT}/public/api.php" "${HARNESS}/public/api.php"

cat > "${HARNESS}/app/db.php" <<'PHP'
<?php

function ql_config(): array
{
    return [
        'db_socket' => getenv('FINDESK_V2_AUTH_SOCKET') ?: '',
        'db_name' => getenv('FINDESK_V2_AUTH_DB') ?: '',
        'app_url' => 'https://finance.brkovic.ltd',
        'session_cookie_name' => 'findesk_v2_auth_smoke_session',
        'mail' => [
            'mode' => 'log',
        ],
    ];
}

function ql_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('mysql:unix_socket=' . ql_config()['db_socket'] . ';dbname=' . ql_config()['db_name'] . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
PHP

cat > "${HARNESS}/app/ledger.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/groups.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/messages.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/business.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/on_the_go.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/advances.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/findesk_phase2.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/yacht_provisioning.php" <<'PHP'
<?php
PHP
cat > "${HARNESS}/app/yacht_prices.php" <<'PHP'
<?php
PHP

PORT="$(php -r '$s = stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr); if (!$s) { fwrite(STDERR, $errstr); exit(1); } $name = stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($name, ":"), 1);')"

FINDESK_V2_AUTH_SOCKET="${SOCKET}" \
FINDESK_V2_AUTH_DB="${DB_NAME}" \
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

FINDESK_V2_AUTH_BASE="http://127.0.0.1:${PORT}" \
FINDESK_V2_AUTH_SOCKET="${SOCKET}" \
FINDESK_V2_AUTH_DB="${DB_NAME}" \
FINDESK_V2_AUTH_HARNESS="${HARNESS}" \
php "${ROOT}/scripts/v2_auth_security_smoke.php"
