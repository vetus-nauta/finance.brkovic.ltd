# SPRINT-88R - Atlas Raw History Conversion Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas raw-history conversion write slice

## Director Opening

SPRINT-88R extends Atlas write parity for gradual conversion of archived raw history into operational entries:

- `POST /api/workspaces/:workspaceId/raw-history/convert`

This sprint does not enable production cutover and does not implement internet-reference writes, Excel upload/import accept, attachments, or FTP deployment.

## Agents

- Director: Codex
- QA / Contract Inspector: Einstein

Einstein performed read-only inspection of PHP/MySQL behavior, request/response shape, row-level statuses, financial side effects, audit requirements, duplicate/idempotency behavior, and Atlas migration risks.

## Contract

Request:

- `mode`: `preview` or `commit`, default `preview`
- `limit`: default `25`, clamped to `1..100`
- `source_id`: optional import source filter

Response:

- `{ ok: true, conversion }`

Conversion result fields:

- `mode`
- `workspace_id`
- `workspace_name`
- `archive_workspace_id`
- `archive_workspace_name`
- `limit`
- `scanned`
- `convertible`
- `converted`
- `duplicates`
- `unrecognized`
- `skipped`
- `rows`

The route must run from an operational workspace, not from the archive raw-history workspace itself.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added `existingLegacyEntryKeys()`.
  - Added `flowsByType()`.
  - Added `updateLegacyImportRowStatus()`.
  - Extracted `createEntryInSession()` from `createEntry()`.
  - Added `convertRawHistoryBatch()` with preview/commit modes.
  - Added route allowlist and dispatch for `POST /raw-history/convert`.
  - Preserved row statuses:
    - `imported`
    - `duplicate_suspect`
    - `unrecognized`
    - `ignored`
    - `summary_ignored`
  - Added batch audit `raw_history/raw_history_batch_convert` when commit converts rows.
- `scripts/v2_atlas_write_smoke.js`
  - Added archive workspace fixture named `<workspace> Archive Raw History`.
  - Added raw import source/rows in the archive workspace.
  - Added archive-workspace rejection smoke.
  - Added viewer rejection smoke.
  - Added preview smoke.
  - Added commit smoke.
  - Added converted entry source linkage checks.
  - Added import-row status checks.
  - Added batch audit assertion.
  - Kept conversion fixture on card expense so cash-chain hardcoded smoke values remain stable.
  - Added cleanup for archive workspace fixture.
- `scripts/v2_atlas_runtime_smoke.js`
  - Moved unsupported write guard to internet-reference write.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `38/45` to `39/45`.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_write_smoke.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run check:atlas`

Expected blocked:

- `npm run gate:v2:atlas-cutover:strict` exited with code `2`

Write-smoke evidence:

- supported write routes: `39`
- temporary operational workspace cleaned: `true`
- temporary archive workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- raw conversion preview:
  - scanned `2`
  - convertible `1`
  - skipped `1`
  - converted `0`
- raw conversion commit:
  - convertible `1`
  - converted `1`
  - created one imported card entry
  - linked `v2_import_rows.entry_id`
  - marked summary row as `summary_ignored`
  - created `raw_history_batch_convert` audit
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
- Atlas write supported: `39`
- unsupported reads: `0`
- unsupported writes: `6`
- cutover allowed: `false`

Remaining unsupported writes:

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

## Director Risk Note

PHP/MySQL has no unique database key on `(workspace_id, source_row_id)` for converted entries. Current behavior relies on transactional candidate filtering and row status updates.

Before production cutover, Atlas should add or simulate an atomic concurrency guard for raw-history conversion so two simultaneous commit requests cannot import the same raw row twice.

## Director Decision

SPRINT-88R is accepted locally.

Atlas now supports raw-history preview and commit conversion with operational/archive workspace separation, row status updates, source linkage, imported entry creation, balance recalculation through existing entry logic, audit, and cleanup-safe smoke coverage.

Next safe sprint:

- SPRINT-89R - Internet-reference lookup and feedback write slice.
