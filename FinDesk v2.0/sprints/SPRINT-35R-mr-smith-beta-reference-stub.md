# SPRINT-35R — Mr. Smith Beta Reference Stub

## Director Sprint Opening

Sprint:
SPRINT-35R — Mr. Smith Beta Reference Stub

Date:
2026-07-08

Goal:
Add a safe beta stub for `Mr. Smith`, the future internet/resource/supplier/store matching assistant, without performing real internet requests and without mutating accounting or dictionary state.

Source of truth:
GitHub files only.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-34R-dictionary-training-assistant-and-rule-constraints.md`
- `FinDesk v2.0/16-api-contract.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:

- QA, Audit, and Security Agent
- UX/Product Agent as reviewer

Agent tasks:

- QA, Audit, and Security Agent: define the minimum safe endpoint shape, consent rules, forbidden payload fields, and no-mutation checks.
- UX/Product Agent: confirm the Training UI presents Mr. Smith as beta/reference guidance only.

Expected reports:

- Endpoint acceptance gates.
- Browser smoke expectations.
- HTTP smoke expectations.
- Explicit rejection cases for any real internet lookup or autonomous learning.

Exit criteria:

- Endpoint requires explicit consent.
- Endpoint requires a sanitized query.
- Endpoint rejects raw/unsafe operational payload fields.
- Endpoint requires workspace writer permissions.
- Endpoint performs no real HTTP request.
- Endpoint writes no DB rows.
- Browser UI labels the feature as beta read-only reference preview.

Risks:

- Accidentally sending raw operational text outside the tenant boundary.
- Treating external reference metadata as category truth.
- Writing audit/category/training records from a preview action.
- Letting the stub drift into production web lookup before provenance storage exists.

## Implemented

API:

```text
POST /api/workspaces/:workspaceId/dictionary-training-internet-reference
```

Request accepted:

```json
{
  "lookup_consent": true,
  "sanitized_query": "Marina Porto Montenegro",
  "source_row_id": "optional source row id"
}
```

UI aliases accepted:

```json
{
  "consent": true,
  "query": "Marina Porto Montenegro"
}
```

Response:

```text
request_id
workspace_id
source_row_id
sanitized_query
query_hash
masked_fields
matches[0].source_type = stub
suggested_reviewer_question
no_financial_mutation = true
```

Behavior:

- Requires writer role.
- Requires explicit consent.
- Requires non-empty sanitized query.
- Rejects unsafe payload fields:
  - `raw_text`
  - `raw_row`
  - `source_snapshot`
  - `amount`
  - `balance`
  - `balance_after`
  - `report`
  - `entries`
  - `rows`
- Strips numeric amount-like fragments from the returned sanitized query.
- Returns a stub match only.
- Does not call the internet.
- Does not write audit rows.
- Does not write dictionary decisions.
- Does not write category rules.
- Does not mutate entries, flows, actors, imports, reports, balances, or closures.

UI:

- Added `Mr. Smith beta` preview block in Training detail.
- Shows sanitized query input.
- Button text: `Reference preview`.
- Result states that no external lookup was performed.
- Result includes query hash readback and `no financial mutation` text.

## Agent Reports

### QA, Audit, and Security Agent

Status:
ACCEPT for stub only.

Required:

- No real internet lookup.
- Consent required.
- Sanitized query required.
- Unsafe raw operational payload rejected.
- Writer permission required.
- Zero DB writes.
- Zero financial mutation.

Reject if:

- raw rows are sent externally;
- endpoint creates training decisions or category rules;
- endpoint writes audit state before provenance retention is designed;
- endpoint returns a final accounting category;
- endpoint caches across workspaces.

### UX/Product Agent

Status:
ACCEPT as beta read-only preview.

Required:

- Mr. Smith must be shown as reference preview, not classification.
- UI must keep reviewer decision buttons separate.
- No row selection, preview, filter, or search may trigger internet reference calls.

## Verification

Added to HTTP smoke:

- Missing consent returns `internet_reference_consent_required`.
- Missing query returns `missing_sanitized_query`.
- Unsafe raw payload returns `unsafe_internet_reference_payload`.
- Missing CSRF is blocked.
- Viewer role is blocked.
- Valid stub returns sanitized query, stub match, and `no_financial_mutation`.
- Table counts remain unchanged after valid stub call.

Added to browser smoke:

- Training detail shows `Mr. Smith beta`.
- Reference preview POSTs to the new endpoint only after button click.
- Result shows `No external lookup performed`.
- Result shows `no financial mutation`.

Commands:

```text
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l scripts/v2_http_api_smoke.php
php -l scripts/v2_clean_core_static_smoke.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Result:

```text
FinDesk v2 clean core static smoke: OK
FinDesk v2 fixture runner: PASS 21
FinDesk v2 HTTP API smoke: OK
FinDesk v2 operational UI smoke: OK
FinDesk v2 browser UI smoke: OK
```

## Director Final Handoff

Status:
ACCEPT.

Accepted work:

- Safe Mr. Smith beta stub exists.
- No real internet lookup is implemented.
- Consent and unsafe-payload gates are implemented.
- UI integrates preview as advisory read-only context.

Rejected work:

- Production internet provider.
- Autonomous web learning.
- External provenance persistence.
- Cross-workspace supplier cache.

Next sprint:
SPRINT-36R — Mr. Smith Provider Design and Provenance Storage Gate.
