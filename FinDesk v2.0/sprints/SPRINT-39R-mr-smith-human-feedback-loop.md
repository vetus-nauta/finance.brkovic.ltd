# SPRINT-39R — Mr. Smith Human Feedback Loop

## Director Sprint Opening

Sprint:
SPRINT-39R — Mr. Smith Human Feedback Loop

Date:
2026-07-09

Goal:
Allow a reviewer to mark local Mr. Smith evidence as useful, unclear, or not useful without creating dictionary training, category rules, or financial mutations.

Agents assigned:

- UX/Product Agent
- Security/API Agent

Exit criteria:

- Feedback is local-only.
- Feedback updates only `v2_internet_reference_lookups.selected_match_json`.
- Writer role and CSRF required.
- Viewer and cross-workspace lookup ids are blocked.
- Unsafe raw operational payload fields are rejected.
- Feedback does not call provider or external sources.
- Feedback does not create category rules, dictionary decisions, entries, imports, reports, balances, or closures.

## Implemented

API:

```text
PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId
```

Payload:

```json
{
  "verdict": "useful",
  "match_index": 0,
  "note": "optional short reviewer note"
}
```

Allowed verdicts:

```text
useful | unclear | not_useful
```

UI:

- Added `Useful`, `Unclear`, and `Not useful` buttons under evidence result.
- Saved feedback is shown as `Human feedback: ...`.
- Feedback does not touch decision controls.

## Agent Report

Security/API Agent:
ACCEPT with one correction.

Required:

- Require explicit verdict; do not silently default to unclear.
- Writer-only, CSRF-protected mutation.
- Cross-workspace lookup id returns not found.
- Reject raw row/amount/balance/report/import payload fields.
- Store bounded feedback JSON only.
- No audit row needed; provenance row is the boundary.
- No finance/training/import/report mutation.

Disposition:
Implemented; missing verdict returns `missing_verdict`.

## Verification

Planned:

```text
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l scripts/v2_http_api_smoke.php
php -l scripts/v2_clean_core_static_smoke.php
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2
npm run smoke:v2:mr-smith-provider
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Result:

```text
php -l app/v2/Api.php — PASS
php -l app/v2/Repository.php — PASS
php -l scripts/v2_http_api_smoke.php — PASS
php -l scripts/v2_clean_core_static_smoke.php — PASS
node --check public/assets/v2/app.js — PASS
node --check scripts/v2_operational_browser_smoke.cjs — PASS
npm run smoke:v2 — PASS
npm run smoke:v2:mr-smith-provider — PASS
npm run test:v2:fixtures — PASS
npm run smoke:v2:http — PASS
npm run smoke:v2:ui — PASS
npm run smoke:v2:browser — PASS
git diff --check — PASS
```

## Director Acceptance

Status:
ACCEPT.
