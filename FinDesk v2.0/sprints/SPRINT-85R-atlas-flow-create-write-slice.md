# SPRINT-85R - Atlas Flow Create Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas flow write slice

## Director Opening

SPRINT-85R extends Atlas write parity for workspace flow creation:

- `POST /api/workspaces/:workspaceId/flows`

This sprint does not enable production cutover and does not implement imports, attachments, dictionary-training writes, internet-reference writes, category-rule writes, or FTP deployment.

## Contract

PHP route creates a flow with:

- `name`
- `type`: `cash`, `card`, `assistant_journal`, `accountable`
- `has_live_balance`
- `is_default`
- `opening_balance`

Response:

- `{ ok: true, flow }`

Audit:

- `entity_type`: `flow`
- `action`: `create`

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added `createFlow()`.
  - Reused `createDefaultFlow()` and `flowRow()`.
  - Added writer-access guard.
  - Added route allowlist and dispatch for `POST /flows`.
  - Added audit action `flow/create`.
- `scripts/v2_atlas_write_smoke.js`
  - Added viewer rejection smoke.
  - Added owner flow-create smoke.
  - Added flow list readback assertion.
  - Added invalid flow type rejection smoke.
  - Added flow audit assertion.
  - Added route to write-smoke evidence.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `35/45` to `36/45`.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run check:atlas`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_write_smoke.js scripts/v2_atlas_cutover_gate.js`

Expected blocked:

- `npm run gate:v2:atlas-cutover:strict` exited with code `2`

Write-smoke evidence:

- supported write routes: `36`
- temporary workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- extra flow created:
  - name `Atlas extra card smoke`
  - type `card`
  - live balance `false`
  - default `false`
  - opening balance `0.00`
- viewer flow creation rejected with `workspace_read_only`
- invalid type rejected with `invalid_type`
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
  - `v2_dictionary_training_decisions`: `111`
  - `v2_internet_reference_lookups`: `0`
  - Claudia Z cash now: `3893.00`
  - August ending cash: `3893.00`

Gate result:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `36`
- Atlas write supported: `36`
- unsupported reads: `0`
- unsupported writes: `9`
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
- `POST /api/workspaces/:workspaceId/category-rules`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-85R is accepted locally.

Atlas now supports explicit workspace flow creation with writer authorization, validation, audit, and cleanup-safe smoke coverage.

Next safe sprint:

- SPRINT-86R - Category rule or dictionary-training write slice.
