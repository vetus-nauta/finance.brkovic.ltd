# SPRINT-84R - Atlas Layer1 Snapshot Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas Layer1 snapshot write slice

## Director Opening

SPRINT-84R extends Atlas write parity for immutable Layer 1 summary snapshots:

- `POST /api/workspaces/:workspaceId/reports/layer1-snapshots`

This sprint does not enable production cutover and does not implement imports, attachments, dictionary-training writes, internet-reference writes, flow creation, category-rule writes, or FTP deployment.

## Agent Reports

### Layer1 Snapshot Inspector - Fermat

Read-only inspection confirmed the PHP/MySQL contract:

- POST returns `{ ok: true, snapshot }`.
- Writer access is required; viewer POST must reject with `workspace_read_only`.
- `year` and `month` default to current date and must be valid integers.
- `status` is `draft`, `stored`, or `closed`.
- Default status is `closed` only when the generated Layer 1 report says the month is closed; otherwise it is `stored`.
- Explicit `closed` on an open month must reject with `month_not_closed`.
- Version increments per workspace/report/year/month.
- Snapshot is immutable: later corrections must not mutate v1.
- Snapshot stores summary, source trace, source entry ids, correction ids, attachment refs, content hash, and audit action `layer1_snapshot_create`.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added `flattenSourceEntryIds()`.
  - Added `attachmentRefsForEntryIds()`.
  - Added `nextReportSnapshotVersion()`.
  - Added `closedAtForMonth()`.
  - Added `createLayer1SummarySnapshot()`.
  - Added Atlas allowlist and dispatch for `POST /reports/layer1-snapshots`.
  - Snapshot creation reuses existing `layer1SummaryReport()`; no financial formulas were changed.
- `scripts/v2_atlas_write_smoke.js`
  - Added `v2_report_snapshots` cleanup for disposable workspace.
  - Added viewer rejection smoke.
  - Added closed-month snapshot v1 smoke.
  - Added viewer list readback smoke.
  - Added correction immutability smoke: v1 id/hash/ending cash remain unchanged.
  - Added snapshot v2 smoke with new id/hash, incremented version, correction id/source id, and corrected ending cash.
  - Added route to write-smoke evidence.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `34/45` to `35/45`.

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

- supported write routes: `35`
- temporary workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- Layer1 snapshot v1:
  - status `closed`
  - version `1`
  - month key `2026-08`
  - ending cash `470.95`
  - content hash length `64`
- Layer1 snapshot v2 after correction:
  - version `2`
  - new id
  - new content hash
  - corrections total `5.00`
  - ending cash `475.95`
  - correction id included in `correction_ids` and `source_entry_ids`
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
- Atlas write supported: `35`
- unsupported reads: `0`
- unsupported writes: `10`
- cutover allowed: `false`

Remaining unsupported writes:

- `POST /api/workspaces/:workspaceId/flows`
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

SPRINT-84R is accepted locally.

Atlas now supports immutable Layer 1 summary snapshots while preserving source-entry traceability and snapshot version history.

Next safe sprint:

- SPRINT-85R - Flow creation write slice.
