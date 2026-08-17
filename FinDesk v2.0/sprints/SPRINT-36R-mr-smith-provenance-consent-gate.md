# SPRINT-36R — Mr. Smith Provenance and Consent Gate

## Director Sprint Opening

Sprint:
SPRINT-36R — Mr. Smith Provenance and Consent Gate

Date:
2026-07-09

Goal:
Turn the SPRINT-35R Mr. Smith stub into a controlled provenance/consent foundation: workspace settings, local lookup provenance storage, and stub-only provider abstraction. No real internet lookup is allowed in this sprint.

Source of truth:
GitHub files only.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-35R-mr-smith-beta-reference-stub.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/02-data-model.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:

- QA, Audit, and Security Agent
- Data/API Core Agent

Agent tasks:

- QA, Audit, and Security Agent: define consent/provenance gates, privacy boundaries, table requirements, and smoke tests.
- Data/API Core Agent: define smallest safe implementation path for provenance storage and provider abstraction.

Expected reports:

- DB fields and API gates.
- No-network provider guard.
- Smoke tests for consent, settings, provenance row, no mutation, and workspace-scoped hash.
- Risks and rejected scope.

Exit criteria:

- Settings API exists and owner/admin controls mutations.
- Mr. Smith preview writes exactly one local provenance row on success.
- Failed preview writes no provenance row.
- Preview still performs no real internet lookup.
- Provider key is `stub` only.
- Finance/training/import/report tables are not mutated by preview.

Risks:

- Treating provenance as accounting truth.
- Allowing workspace-enabled consent too broadly.
- Provider abstraction accidentally receiving raw rows.
- Cross-workspace `source_row_id` leakage.
- Retention date existing without cleanup enforcement.

## Implemented

Schema:

- Added `v2_workspace_assistant_settings`.
- Added `v2_internet_reference_lookups`.
- Updated MariaDB runtime schema and clean-core schema contract.

API:

```text
GET /api/workspaces/:workspaceId/assistant-settings
PATCH /api/workspaces/:workspaceId/assistant-settings
POST /api/workspaces/:workspaceId/dictionary-training-internet-reference
GET /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups?limit=50
```

Provider:

- Added `app/v2/InternetReferenceProvider.php`.
- Added `FinDeskV2InternetReferenceProvider`.
- Added `FinDeskV2StubInternetReferenceProvider`.
- Stub provider performs no network request.
- Provider receives sanitized query and metadata only.

Settings behavior:

- Default mode: `per_request`.
- Default provider: `stub`.
- `PATCH` requires owner/admin.
- Viewer cannot mutate settings.
- Invalid provider is rejected.
- `disabled` mode blocks preview.
- `workspace_enabled` can provide consent source only with `mr_smith_enabled = true`.

Lookup behavior:

- Successful preview writes exactly one `v2_internet_reference_lookups` row.
- Failed preview writes no provenance row.
- Response includes:
  - `lookup_id`
  - `query_hash`
  - `provider_key`
  - `result_status`
  - `consent_source`
  - `no_financial_mutation`
- `source_row_id` is validated against the workspace dictionary/archive context.
- Query hash is workspace-scoped.

No mutation guarantee:

- No operational entry mutation.
- No actor mutation.
- No category mutation.
- No category rule mutation.
- No dictionary training decision mutation.
- No import mutation.
- No report mutation.
- No balance mutation.
- No monthly closure mutation.

UI:

- Mr. Smith result readback now shows local `lookup` id and query hash.
- Browser smoke asserts lookup readback appears after button click.

## Agent Reports

### QA, Audit, and Security Agent

Status:
ACCEPT as local provenance, consent-settings, and provider-abstraction gate.

Required:

- Provider key must be `stub` only.
- Result status must be `stub`.
- `no_financial_mutation` must be true.
- Query hash must include workspace scope.
- `source_row_id` must not authorize cross-workspace leakage.
- Successful preview may write exactly one provenance row and nothing else.
- Static guard must keep provider free of real network calls.

### Data/API Core Agent

Status:
ACCEPT as stub-only.

Required:

- Extend existing endpoint, not a financial workflow.
- Keep existing guards: writer, consent, sanitized query, unsafe payload rejection.
- Persist one provenance row per successful accepted lookup.
- Keep response backward compatible and add optional metadata.
- Do not write audit log for lookup preview; provenance table is the boundary.

## Verification

Planned commands:

```text
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
php -l app/v2/InternetReferenceProvider.php
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

Agents assigned:

- QA, Audit, and Security Agent
- Data/API Core Agent

Agent reports received:
YES.

Accepted work:

- Workspace assistant settings are modeled and exposed.
- Settings mutation is owner/admin only.
- Mr. Smith preview writes local provenance on success.
- Failed preview does not write provenance.
- Provider abstraction exists and is stub-only.
- Static guard checks provider for network-client markers.
- UI shows local lookup id/hash readback.

Rejected work:

- Real internet lookup.
- External provider credentials.
- Cross-workspace supplier cache.
- Automatic dictionary learning from provenance.
- Any financial/import/report/parser mutation.

Next sprint:
SPRINT-37R — Mr. Smith Provider Design and Allowlisted Lookup Adapter.
