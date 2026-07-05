#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d /tmp/findesk-v2-fixtures.XXXXXX)"
DATA_DIR="${TMP_DIR}/data"
SOCKET="${TMP_DIR}/mariadb.sock"
PID_FILE="${TMP_DIR}/mariadb.pid"
LOG_FILE="${TMP_DIR}/mariadb.log"
DB_NAME="findesk_v2_fixtures"

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

echo "FinDesk v2 fixture runner: setup ${TMP_DIR}"

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
cp "${ROOT}/app/v2/LegacyExcelImporter.php" "${HARNESS}/app/v2/LegacyExcelImporter.php"
cp "${ROOT}/app/v2/Database.php" "${HARNESS}/app/v2/Database.php"
cp "${ROOT}/app/v2/Repository.php" "${HARNESS}/app/v2/Repository.php"

cat > "${HARNESS}/app/db.php" <<'PHP'
<?php

function ql_config(): array
{
    return [
        'db_socket' => getenv('FINDESK_V2_FIXTURE_SOCKET') ?: '',
        'db_name' => getenv('FINDESK_V2_FIXTURE_DB') ?: '',
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

FINDESK_V2_FIXTURE_SOCKET="${SOCKET}" \
FINDESK_V2_FIXTURE_DB="${DB_NAME}" \
FINDESK_V2_FIXTURE_HARNESS="${HARNESS}" \
php "${ROOT}/scripts/v2_fixture_runner.php"
