# SPRINT-86R - Atlas Category Rule Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas category-rule write slice

## Director Opening

SPRINT-86R extends Atlas write parity for explicit dictionary category-rule creation:

- `POST /api/workspaces/:workspaceId/category-rules`

This sprint does not enable production cutover and does not implement raw-history conversion, dictionary-training decisions, internet-reference writes, imports, attachments, or FTP deployment.

## Agents

- Director: Codex
- QA / Contract Inspector: Banach

Banach performed read-only contract inspection against PHP/MySQL behavior and confirmed validation, response shape, access control, audit, and cleanup requirements.

## Contract

Request fields:

- `category_code`: required active system/workspace category code
- `pattern`: required string
- `pattern_type`: `keyword`, `phrase`, `regex`, `supplier`, `role`; default `keyword`
- `language`: `ru`, `en`, `it`, `es`, `de`, `bcms`, `multi`; default `multi`
- `weight`: integer, default `10`
- `negative_weight`: integer, default `0`
- `requires_any`: optional list of strings
- `excludes_any`: optional list of strings

Response:

- `{ ok: true, category_rule }`

Audit:

- `entity_type`: `category_rule`
- `action`: `create`
- `before_json`: `null`
- `after_json`: returned rule payload

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added `optionalStringListInput()`.
  - Added `categoryRuleRow()`.
  - Added `createCategoryRule()` with Mongo transaction.
  - Added writer-access guard and route dispatch for `POST /category-rules`.
  - Reused active global/workspace category resolution via `categoryByCode()`.
  - Added audit action `category_rule/create`.
- `scripts/v2_atlas_write_smoke.js`
  - Added `v2_category_rules` to protected finance snapshot.
  - Added disposable workspace cleanup for category rules and dictionary decisions.
  - Added viewer rejection smoke.
  - Added owner create smoke with list normalization checks.
  - Added invalid category, invalid enum, and invalid list rejection checks.
  - Added category-rule audit assertion.
  - Added route to write-smoke evidence.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `36/45` to `37/45`.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_write_smoke.js scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run check:atlas`

Expected blocked:

- `npm run gate:v2:atlas-cutover:strict` exited with code `2`

Write-smoke evidence:

- supported write routes: `37`
- temporary workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- category rule created:
  - category `media_comms`
  - pattern `atlas dictionary smoke`
  - pattern type `phrase`
  - language `ru`
  - weight `20`
  - negative weight `1`
  - `requires_any`: `["связь"]`
  - `excludes_any`: `["мой", "личный"]`
- viewer category-rule creation rejected with `workspace_read_only`
- invalid category rejected with `unknown_category`
- invalid pattern type rejected with `invalid_pattern_type`
- invalid list rejected with `invalid_requires_any`
- Claudia Z finance snapshot unchanged:
  - `v2_entries`: `1638`
  - `v2_flows`: `52`
  - `v2_monthly_closures`: `4`
  - `v2_report_batches`: `8`
  - `v2_report_batch_entries`: `629`
  - `v2_report_batch_html_snapshots`: `17`
  - `v2_report_packages`: `0`
  - `v2_report_package_items`: `0`
  - `v2_report_versions`: `0`
  - `v2_report_snapshots`: `0`
  - `v2_import_sources`: `60`
  - `v2_import_rows`: `3507`
  - `v2_category_rules`: `85`
  - `v2_dictionary_training_decisions`: `111`
  - `v2_internet_reference_lookups`: `0`
  - Claudia Z cash now: `3893.00`
  - August ending cash: `3893.00`

Gate result:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `36`
- Atlas write supported: `37`
- unsupported reads: `0`
- unsupported writes: `8`
- cutover allowed: `false`

Remaining unsupported writes:

- `POST /api/workspaces/:workspaceId/raw-history/convert`
- `POST /api/workspaces/:workspaceId/dictionary-training-decisions`
- `POST /api/workspaces/:workspaceId/dictionary-training-internet-reference`
- `PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId`
- `POST /api/workspaces/:workspaceId/imports/excel`
- `POST /api/workspaces/:workspaceId/imports/:importId/accept`
- `POST /api/entries/:entryId/attachments`
- `DELETE /api/attachments/:attachmentId`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-86R is accepted locally.

Atlas now supports direct category-rule creation with writer authorization, PHP-compatible validation, audit, protected smoke coverage, and no Claudia Z finance mutation.

Next safe sprint:

- SPRINT-87R - Dictionary-training decision write slice.
