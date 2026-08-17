# SPRINT-38R — Mr. Smith Evidence UI Workflow

## Director Sprint Opening

Sprint:
SPRINT-38R — Mr. Smith Evidence UI Workflow

Date:
2026-07-09

Goal:
Turn the Mr. Smith beta panel into a clear human-reviewed evidence workflow: sanitized query, public source URL, explicit consent, and evidence-only result display.

Agents assigned:

- UX/Product Agent
- Security/API Agent

Exit criteria:

- Lookup runs only on explicit click.
- UI uses canonical `sanitized_query`, `candidate_url`, and `lookup_consent`.
- Reviewer must enter a public source URL and consent before preview.
- Result copy says reference evidence only, not classification.
- Existing dictionary decision buttons remain separate.
- Stub-default behavior remains intact.

## Implemented

- Added `Public source URL` input to Mr. Smith panel.
- Added explicit consent checkbox.
- Button copy changed to `Preview evidence` / `Checking source...`.
- Request payload now uses canonical API fields.
- Result copy now says `Reference: ...`.
- Result metadata says `no financial or training mutation`.
- Browser smoke checks missing URL guard, consent, preview result, and no accidental training decision during evidence preview.

## Agent Report

UX/Product Agent:
ACCEPT with gates.

Required:

- Do not add auto-run lookup.
- Do not infer URLs.
- Do not use classification wording.
- Do not populate category/pattern/rules from evidence.
- Keep panel responsive and compact.

Disposition:
Implemented.

## Verification

Combined SPRINT-38R/39R verification:

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
