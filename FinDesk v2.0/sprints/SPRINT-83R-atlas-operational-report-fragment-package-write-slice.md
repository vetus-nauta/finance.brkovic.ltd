# SPRINT-83R - Atlas Operational Report Fragment and Package Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas report write slice

## Director Opening

SPRINT-83R extends Atlas write parity for operational report creation and storage:

- `POST /api/workspaces/:workspaceId/reports/batch-preview`
- `POST /api/workspaces/:workspaceId/reports/batches`
- `POST /api/workspaces/:workspaceId/reports/operational-fragments`
- `POST /api/workspaces/:workspaceId/reports/operational-fragments/preview`
- `PATCH /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId`
- `POST /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots`
- `POST /api/workspaces/:workspaceId/reports/operational-packages`

This sprint does not enable production cutover and does not implement imports, attachments, dictionary-training writes, internet-reference writes, flow creation, layer1 snapshot writes, or FTP deployment.

## Agent Reports

### Report Storage and QA Inspector - Maxwell

Read-only inspection confirmed the PHP/MySQL contract and required Atlas parity:

- Preview must be read-only.
- Fragment creation must store report metadata, source entry ids, entry snapshots, content hash, row links, and HTML snapshot.
- Fragment creation must not mutate source entries or balances.
- Fragment updates must support status/title changes and rebuild from original entry ids.
- Explicit HTML snapshot creation must store immutable HTML content and expose `html_content` only on detail readback.
- Package creation must require at least two closed fragments.
- Package creation must store package items, fragment snapshots, HTML snapshot references, source entry ids, report version, content hash, and audit row.
- Production cutover must remain blocked until all writes are covered.

Director addition:

- Atlas package creation rejects overlapping source entries with `report_package_overlapping_fragments` to prevent double-counted package totals.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added operational fragment entry-id validation.
  - Added selected-fragment report builder using actual selected entries.
  - Opening cash is calculated before the first selected cash entry.
  - Ending cash is opening plus selected cash deltas.
  - Added category aggregation, cash/card totals, other-review block, lower-accounting block, source trace, and entry snapshots.
  - Added compact mobile-safe HTML generation with expandable categories and source rows.
  - Added HTML snapshot storage in `v2_report_batch_html_snapshots`.
  - Added operational fragment preview/create/update/rebuild routes.
  - Added explicit operational fragment HTML snapshot creation route.
  - Added operational package creation with package items, versions, snapshots, overlap guard, and audit.
  - Added Atlas allowlist and dispatch for the seven report write routes.
- `scripts/v2_atlas_write_smoke.js`
  - Added report collections to finance snapshot and fixture cleanup.
  - Added read-only viewer fixture for package write authorization smoke.
  - Added operational fragment preview smoke.
  - Added batch-preview and batch-create alias smoke.
  - Added closed fragment creation smoke.
  - Added active report-lock duplicate rejection smoke.
  - Added open-fragment package rejection smoke.
  - Added explicit HTML snapshot smoke and HTML readback assertions.
  - Added package creation smoke with item/snapshot/version/source-id assertions.
  - Added overlapping fragment package rejection smoke.
  - Added fragment status patch and rebuild smoke.
  - Added report audit action checks.
  - Added report-lock decision to test cleanup deletion.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `27/45` to `34/45`.

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

- supported write routes: `34`
- temporary workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- explicit fragment HTML snapshot hash length: `64`
- package created with two closed fragments, two items, one version, and HTML snapshot references
- package with open fragment rejected as `report_package_requires_closed_fragments`
- package with overlapping source entries rejected as `report_package_overlapping_fragments`
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
- Atlas write supported: `34`
- unsupported reads: `0`
- unsupported writes: `11`
- cutover allowed: `false`

Remaining unsupported writes:

- `POST /api/workspaces/:workspaceId/flows`
- `POST /api/workspaces/:workspaceId/reports/layer1-snapshots`
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

SPRINT-83R is accepted locally.

The Atlas sidecar now supports operational report fragment creation, report HTML snapshots, report status/rebuild updates, and package creation while preserving Claudia Z balances and source-entry traceability.

Next safe sprint:

- SPRINT-84R - Layer1 snapshot write slice, then flow creation or training/import writes.
