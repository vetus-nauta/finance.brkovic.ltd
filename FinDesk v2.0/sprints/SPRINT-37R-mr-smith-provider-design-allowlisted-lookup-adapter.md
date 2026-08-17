# SPRINT-37R — Mr. Smith Provider Design and Allowlisted Lookup Adapter

## Director Sprint Opening

Sprint:
SPRINT-37R — Mr. Smith Provider Design and Allowlisted Lookup Adapter

Date:
2026-07-09

Goal:
Add the first real Mr. Smith provider adapter behind a server-side allowlist gate, without turning internet reference into autonomous training or accounting truth.

Source of truth:
GitHub files only.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-36R-mr-smith-provenance-consent-gate.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/02-data-model.md`
- `app/v2/InternetReferenceProvider.php`
- `app/v2/Repository.php`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_http_api_smoke.php`

Agents assigned:

- Security and Privacy Gate Agent
- Data/API Core Agent

Agent tasks:

- Security and Privacy Gate Agent: define adapter acceptance gates: disabled by default, explicit consent, sanitized query only, no raw rows, allowlist, timeout/size limits, provenance, no autonomous learning, no finance/training mutation.
- Data/API Core Agent: define smallest safe implementation path using existing endpoints, settings, and provenance table.

Exit criteria:

- Default provider remains `stub`.
- `allowlisted_http` is selectable only when server env gate is enabled and allowlist is non-empty.
- Adapter receives only `sanitized_query` and `candidate_url`.
- Adapter rejects non-HTTPS, non-allowlisted, localhost, IP literal, private/reserved/local targets, and redirects.
- Adapter stores bounded metadata only.
- Existing consent/provenance/no-mutation gates remain green.
- Failed validation/authorization/unsafe-url requests write no provenance row.

## Implemented

Provider:

- Added `FinDeskV2InternetReferenceProviderConfig`.
- Added gated `FinDeskV2AllowlistedHttpInternetReferenceProvider`.
- Added env flags:
  - `FINDESK_V2_MR_SMITH_ALLOWLIST_ENABLED`
  - `FINDESK_V2_MR_SMITH_ALLOWED_DOMAINS`
- Exact host allowlist only.
- Invalid allowlist entries are ignored.
- No implicit subdomain matching.
- HTTPS only.
- Redirects disabled.
- Response body capped at 16 KB.
- Transport timeout capped at 2 seconds.
- Localhost, IP literals, private/reserved/local addresses are rejected before fetch.
- Provider stores title/domain/source metadata only; it does not store raw HTML, cookies, headers, scraped body text, or provider payloads.

Repository/API:

- Settings provider validation now uses active provider keys.
- `allowlisted_http` is unavailable when the server env gate is off.
- `candidate_url` is accepted by preview request and passed only to the provider.
- Provider no longer receives workspace id, user id, source row id, query hash, raw rows, amounts, balances, entries, imports, reports, or snapshots.
- Provenance rows still remain local and workspace-scoped.

Tests:

- Added `scripts/v2_mr_smith_provider_smoke.php`.
- Added `npm run smoke:v2:mr-smith-provider`.
- Static smoke now requires allowlisted adapter safety markers instead of banning the provider file from containing any bounded fetch at all.
- HTTP smoke asserts `allowlisted_http` is rejected while the env gate is off.

## Agent Reports

### Security and Privacy Gate Agent

Initial status:
REJECT until real-adapter gates are implemented.

Required:

- Disabled by default.
- Explicit consent remains required.
- Adapter request may include sanitized query only, plus approved candidate URL.
- No raw rows, amounts, balances, reports, imports, entries, source snapshots, workspace/user ids, or source row ids can reach the external provider.
- HTTPS exact-host allowlist.
- No wildcard allowlist.
- No redirects or redirect escape.
- Reject local/private/reserved targets.
- Timeout and response size limits.
- Store bounded normalized metadata only.
- Preview must not create training decisions, category rules, or finance/report/import mutations.

Disposition:
Implemented as SPRINT-37 acceptance gates.

### Data/API Core Agent

Status:
ACCEPT smallest implementation path.

Recommended:

- Reuse existing settings and preview/readback endpoints.
- Keep `stub` as default.
- Add `allowlisted_http` only behind env gate and non-empty allowlist.
- Pass `candidate_url` into provider.
- Keep sanitized query required for hash/provenance.
- No schema change required.

Disposition:
Implemented without new endpoints or schema changes.

## Verification

Planned commands:

```text
php -l app/v2/InternetReferenceProvider.php
php -l app/v2/Repository.php
php -l scripts/v2_mr_smith_provider_smoke.php
php -l scripts/v2_clean_core_static_smoke.php
php -l scripts/v2_http_api_smoke.php
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
php -l app/v2/InternetReferenceProvider.php — PASS
php -l app/v2/Repository.php — PASS
php -l scripts/v2_mr_smith_provider_smoke.php — PASS
php -l scripts/v2_clean_core_static_smoke.php — PASS
php -l scripts/v2_http_api_smoke.php — PASS
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

Notes:

- This sprint does not complete autonomous learning.
- This sprint does not make web evidence a classification.
- This sprint only creates the safe adapter boundary required before future Mr. Smith product work.
