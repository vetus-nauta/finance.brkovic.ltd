#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP_DIR="$(mktemp -d /tmp/findesk-v2-atlas-proxy.XXXXXX)"
ATLAS_PID_FILE="${TMP_DIR}/atlas.pid"
PHP_PID_FILE="${TMP_DIR}/php.pid"
WORKSPACE_ID="${FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID:-0d4faca6-3138-4ffe-9805-a6a29895b7ed}"
CASH_FLOW_ID="${FINDESK_V2_CLAUDIA_Z_CASH_FLOW_ID:-c5c895ad-8f4a-4503-8ef7-6676ccc76d32}"

cleanup() {
    if [[ -f "${PHP_PID_FILE}" ]]; then
        kill "$(cat "${PHP_PID_FILE}")" >/dev/null 2>&1 || true
    fi
    if [[ -f "${ATLAS_PID_FILE}" ]]; then
        kill "$(cat "${ATLAS_PID_FILE}")" >/dev/null 2>&1 || true
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

free_port() {
    php -r '$s = stream_socket_server("tcp://127.0.0.1:0", $errno, $errstr); if (!$s) { fwrite(STDERR, $errstr); exit(1); } $name = stream_socket_get_name($s, false); fclose($s); echo substr(strrchr($name, ":"), 1);'
}

request_json() {
    local expected="$1"
    shift
    local output
    output="$(curl -sS -w $'\n%{http_code}' "$@")"
    local status="${output##*$'\n'}"
    local body="${output%$'\n'*}"
    if [[ "${status}" != "${expected}" ]]; then
        echo "Expected HTTP ${expected}, got ${status}" >&2
        echo "${body}" >&2
        exit 1
    fi
    printf '%s' "${body}"
}

assert_json() {
    local body="$1"
    local expression="$2"
    EXPRESSION="${expression}" node -e "
const fs = require('fs');
const data = JSON.parse(fs.readFileSync(0, 'utf8'));
const expression = process.env.EXPRESSION;
if (!eval(expression)) {
  console.error('Assertion failed: ' + expression);
  console.error(JSON.stringify(data, null, 2));
  process.exit(1);
}
" <<<"${body}"
}

start_php() {
    local mode="$1"
    local port="$2"
    FINDESK_V2_RUNTIME="${mode}" \
    FINDESK_V2_ATLAS_READ_BASE_URL="http://127.0.0.1:${ATLAS_PORT}" \
    php -S "127.0.0.1:${port}" -t "${ROOT}/public" \
        >"${TMP_DIR}/php-${mode}.log" 2>&1 &
    echo "$!" > "${PHP_PID_FILE}"

    for _ in {1..100}; do
        if php -r "\$fp = @fsockopen('127.0.0.1', ${port}); if (\$fp) { fclose(\$fp); exit(0); } exit(1);" >/dev/null 2>&1; then
            return
        fi
        sleep 0.1
    done

    echo "PHP server did not become ready" >&2
    cat "${TMP_DIR}/php-${mode}.log" >&2 || true
    exit 1
}

stop_php() {
    if [[ -f "${PHP_PID_FILE}" ]]; then
        kill "$(cat "${PHP_PID_FILE}")" >/dev/null 2>&1 || true
        rm -f "${PHP_PID_FILE}"
    fi
}

require_bin curl
require_bin node
require_bin php

ATLAS_PORT="$(free_port)"
FINDESK_V2_ATLAS_READ_PORT="${ATLAS_PORT}" \
node "${ROOT}/server/findesk-v2-atlas-read-server.js" \
    >"${TMP_DIR}/atlas.log" 2>&1 &
echo "$!" > "${ATLAS_PID_FILE}"

for _ in {1..150}; do
    if curl -fsS "http://127.0.0.1:${ATLAS_PORT}/api?route=/api/workspaces" >/dev/null 2>&1; then
        break
    fi
    sleep 0.2
done

if ! curl -fsS "http://127.0.0.1:${ATLAS_PORT}/api?route=/api/workspaces" >/dev/null 2>&1; then
    echo "Atlas sidecar did not become ready" >&2
    cat "${TMP_DIR}/atlas.log" >&2 || true
    exit 1
fi

PHP_PORT="$(free_port)"
start_php atlas_read "${PHP_PORT}"
READ_BASE="http://127.0.0.1:${PHP_PORT}/v2-api.php"

body="$(request_json 200 "${READ_BASE}?route=/api/workspaces")"
assert_json "${body}" "data.ok === true && Array.isArray(data.workspaces) && data.workspaces.length >= 1"

body="$(request_json 405 -X POST -H 'Content-Type: application/json' -H 'X-FinDesk-V2-Request: fetch' --data '{}' "${READ_BASE}?route=/api/parse-entry-preview")"
assert_json "${body}" "data.ok === false && data.error === 'atlas_runtime_write_not_enabled'"
stop_php

PHP_PORT="$(free_port)"
start_php atlas_write "${PHP_PORT}"
WRITE_BASE="http://127.0.0.1:${PHP_PORT}/v2-api.php"

body="$(request_json 200 "${WRITE_BASE}?route=/api/workspaces/${WORKSPACE_ID}/entries&year=2026&month=8")"
assert_json "${body}" "data.ok === true && Array.isArray(data.entries) && data.entries.length === 39"

body="$(request_json 200 -X POST -H 'Content-Type: application/json' -H 'X-FinDesk-V2-Request: fetch' --data '{"raw_text":"-1 proxy smoke","date":"2026-08-13","flow_id":"'"${CASH_FLOW_ID}"'"}' "${WRITE_BASE}?route=/api/workspaces/${WORKSPACE_ID}/parse-preview")"
assert_json "${body}" "data.ok === true && data.preview && data.preview.amount === 1 && data.preview.sign === '-' && data.preview.direction === 'out'"

echo "Atlas PHP proxy smoke ok"
