#!/usr/bin/env bash

set -Eeuo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PAGE="${ROOT}/public/v2.php"
CSS="${ROOT}/public/assets/v2/app.css"
JS="${ROOT}/public/assets/v2/app.js"

fail() {
    echo "FinDesk v2 operational UI smoke: FAIL" >&2
    echo "- $1" >&2
    exit 1
}

for file in "${PAGE}" "${CSS}" "${JS}"; do
    [[ -f "${file}" ]] || fail "Missing file: ${file#${ROOT}/}"
done

grep -q '/assets/v2/app.css' "${PAGE}" || fail "v2 page does not load isolated v2 CSS"
grep -q '/assets/v2/app.js' "${PAGE}" || fail "v2 page does not load isolated v2 JS"

if grep -Eq '/assets/app\\.(js|css)|public/assets/app\\.(js|css)|api\\.php\\?action|findesk_phase2|on_the_go|advances|ledger|business|groups|messages' "${PAGE}" "${CSS}" "${JS}"; then
    fail "v2 UI references legacy UI/product assets or modules"
fi

grep -q 'v2-api.php' "${JS}" || fail "v2 JS does not call v2-api.php"
if grep -q '/api.php' "${JS}"; then
    fail "v2 JS references legacy /api.php"
fi

for marker in \
    'data-v2-feed' \
    'data-v2-check-table' \
    'data-v2-entry-form' \
    'data-v2-summary' \
    'data-v2-workspace-select' \
    'data-v2-create-form' \
    'data-v2-entry-detail' \
    'data-v2-entry-detail-body' \
    'data-v2-category-select' \
    'data-v2-category-save' \
    'data-v2-category-error' \
    'data-v2-attachments' \
    'data-v2-attachment-form' \
    'data-v2-attachment-input' \
    'data-v2-attachment-upload' \
    'data-v2-attachment-list' \
    'data-v2-attachment-status' \
    'data-v2-other-review-jump' \
    'data-v2-month-state' \
    'data-v2-month-toggle' \
    'data-v2-selected-entry-id' \
    'data-v2-closed-month-decision' \
    'data-v2-closed-month-decision-from' \
    'data-v2-closed-month-decision-to' \
    'data-v2-closed-month-decision-action="create_correction"' \
    'data-v2-closed-month-decision-action="recalculate_chain"' \
    'data-v2-closed-month-decision-action="cancel"'
do
    grep -q "${marker}" "${PAGE}" || fail "Missing UI marker: ${marker}"
done

for marker in \
    'data-v2-attachment-item' \
    'data-v2-attachment-delete'
do
    grep -q "${marker}" "${JS}" || fail "Missing dynamic UI marker: ${marker}"
done

for route in \
    '/api/workspaces' \
    '/api/workspaces/' \
    '/flows' \
    '/entries' \
    '/category/closed-month-decision' \
    '/attachments' \
    '/api/attachments/' \
    '/summary' \
    '/categories' \
    '/parse-preview' \
    '/other-expenses' \
    '/reports/monthly' \
    '/months/'
do
    grep -q "${route}" "${JS}" || fail "Missing v2 API route marker in JS: ${route}"
done

for field in \
    'date' \
    'raw_text' \
    'flow' \
    'sign' \
    'amount' \
    'direction' \
    'entry_type' \
    'category' \
    'actor' \
    'status' \
    'balance_after'
do
    grep -q "${field}" "${JS}" || fail "Missing structured field marker: ${field}"
done

if grep -Eiq 'dashboard|analytics|monthly report|import ui|bank reconciliation|final parser' "${PAGE}" "${CSS}" "${JS}"; then
    fail "v2 UI contains forbidden product framing"
fi

grep -q '100dvh' "${CSS}" || fail "v2 CSS does not constrain shell to viewport height"
grep -q 'overflow-y: auto' "${CSS}" || fail "v2 CSS does not define internal vertical record scroll"
grep -q 'overflow-x: auto' "${CSS}" || fail "v2 CSS does not define horizontal structured movement"

echo "FinDesk v2 operational UI smoke: OK"
