#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MODE="${FINDESK_V2_PREFLIGHT_ENV:-local}"
BASE_URL="${FINDESK_V2_PRODUCTION_BASE_URL:-}"

failures=0
warnings=0

pass() {
    printf 'PASS %s\n' "$1"
}

warn() {
    warnings=$((warnings + 1))
    printf 'WARN %s\n' "$1" >&2
}

fail() {
    failures=$((failures + 1))
    printf 'FAIL %s\n' "$1" >&2
}

require_file() {
    local file="$1"
    if [[ -f "${ROOT}/${file}" ]]; then
        pass "${file} exists"
    else
        fail "${file} is missing"
    fi
}

assert_grep() {
    local pattern="$1"
    local file="$2"
    local label="$3"
    if grep -Eq "$pattern" "${ROOT}/${file}"; then
        pass "${label}"
    else
        fail "${label}"
    fi
}

assert_ignored_and_untracked() {
    local path="$1"
    if git -C "${ROOT}" check-ignore -q "$path"; then
        pass "${path} is ignored"
    else
        fail "${path} is not ignored"
    fi

    if [[ -z "$(git -C "${ROOT}" ls-files "$path")" ]]; then
        pass "${path} is untracked"
    else
        fail "${path} is tracked"
    fi
}

assert_not_public_http() {
    local path="$1"
    local url="${BASE_URL%/}${path}"
    local status

    status="$(curl -ksS -o /dev/null -w '%{http_code}' "$url")"
    case "$status" in
        401|403|404)
            pass "${path} is not publicly readable over HTTP (${status})"
            ;;
        000)
            fail "${path} could not be checked over HTTP"
            ;;
        *)
            fail "${path} returned public HTTP status ${status}"
            ;;
    esac
}

cd "${ROOT}"

require_file '.htaccess'
require_file 'app/config.php'
require_file 'app/auth.php'
require_file 'index.php'
require_file 'app.php'
require_file 'api.php'
require_file 'v2-report.php'
require_file 'public/index.php'
require_file 'public/app.php'
require_file 'public/api.php'
require_file 'public/v2.php'
require_file 'public/v2-api.php'
require_file 'public/v2-report.php'
require_file 'deploy/auth_foundation.sql'
require_file 'FinDesk v2.0/sql/001-clean-core-mariadb.sql'

assert_grep '^Options[[:space:]]+-Indexes$' '.htaccess' 'directory indexes are disabled'
assert_grep 'RedirectMatch[[:space:]]+403[[:space:]]+\^/\(app\|storage\|deploy\|cron\|backups\|zip-archives\|test-results\|node_modules\|tmp\)\(/\|\$\)' '.htaccess' 'root .htaccess blocks private/runtime directories'
assert_grep 'RedirectMatch[[:space:]]+403[[:space:]]+\(\^\|/\)\(\\\.git\|\\\.env\|\.\*\\\.sql\|\.\*\\\.bak\|\.\*\\\.tar\\\.gz\|\.\*\\\.zip\)\(/\|\$\)' '.htaccess' 'root .htaccess blocks deploy archives, SQL dumps, env files, and git metadata'
assert_grep "require __DIR__ \\. '/v2\\.php'" 'public/index.php' 'canonical root serves v2 UI'
assert_grep 'Location: /' 'public/app.php' 'legacy app.php redirects to canonical root'
assert_grep 'noindex,nofollow' 'public/v2.php' 'v2 screen is noindex,nofollow'

php <<'PHP'
<?php
$root = getcwd();
$config = require $root . '/app/config.php';
$errors = [];

if (!is_array($config)) {
    $errors[] = 'app/config.php does not return an array';
}
if (!str_starts_with((string)($config['app_url'] ?? ''), 'https://')) {
    $errors[] = 'app_url must start with https:// for production';
}
$storagePath = (string)($config['storage_path'] ?? '');
if ($storagePath === '') {
    $errors[] = 'storage_path is missing';
} else {
    $expected = $root . '/storage';
    if (rtrim($storagePath, '/\\') !== $expected) {
        $errors[] = 'storage_path must resolve to repository storage directory';
    }
    if (str_contains($storagePath, '/public/')) {
        $errors[] = 'storage_path must not be inside public/';
    }
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, "FAIL {$error}\n");
    }
    exit(1);
}

echo "PASS production app config shape\n";
PHP
if [[ $? -ne 0 ]]; then
    failures=$((failures + 1))
fi

assert_ignored_and_untracked 'app/config.local.php'
assert_ignored_and_untracked 'storage/logs/auth_codes.log'
assert_ignored_and_untracked 'storage/secrets/openai_api_key'
assert_ignored_and_untracked 'storage/v2/attachments/example.png'

if [[ -e "${ROOT}/storage/logs/auth_codes.log" ]]; then
    if [[ "${MODE}" == "production" ]]; then
        fail 'storage/logs/auth_codes.log exists in production mode; purge it before accepting deploy'
    else
        warn 'storage/logs/auth_codes.log exists locally; production deploy must purge/avoid this file'
    fi
else
    pass 'storage/logs/auth_codes.log is absent'
fi

if [[ -d "${ROOT}/public/storage" ]]; then
    fail 'public/storage directory exists'
else
    pass 'public/storage directory is absent'
fi

if rg -n 'storage/v2/attachments' public --glob '!assets/v2/app.js' >/tmp/findesk-v2-preflight-rg.$$; then
    cat /tmp/findesk-v2-preflight-rg.$$ >&2
    rm -f /tmp/findesk-v2-preflight-rg.$$
    fail 'public files expose v2 attachment storage paths outside app metadata handling'
else
    rm -f /tmp/findesk-v2-preflight-rg.$$
    pass 'public files do not expose direct v2 attachment routes'
fi

if [[ -n "${BASE_URL}" ]]; then
    if ! command -v curl >/dev/null 2>&1; then
        fail 'curl is required for live production URL checks'
    else
        assert_not_public_http '/storage/v2/attachments/preflight-deny-check.txt'
        assert_not_public_http '/storage/logs/auth_codes.log'
        assert_not_public_http '/app/config.php'
        assert_not_public_http '/deploy/auth_foundation.sql'
        assert_not_public_http '/backups/preflight-deny-check.sql'
        assert_not_public_http '/backup-db-before-findesk-v2-preflight.sql'
        assert_not_public_http '/backup-files-before-findesk-v2-preflight.tar.gz'
    fi
elif [[ "${MODE}" == "production" ]]; then
    fail 'FINDESK_V2_PRODUCTION_BASE_URL is required in production mode'
else
    warn 'FINDESK_V2_PRODUCTION_BASE_URL is not set; live HTTP deny checks were skipped'
fi

if [[ "${failures}" -gt 0 ]]; then
    printf 'FinDesk v2 production deploy preflight: FAIL (%d failure(s), %d warning(s))\n' "${failures}" "${warnings}" >&2
    exit 1
fi

printf 'FinDesk v2 production deploy preflight: OK (%d warning(s))\n' "${warnings}"
