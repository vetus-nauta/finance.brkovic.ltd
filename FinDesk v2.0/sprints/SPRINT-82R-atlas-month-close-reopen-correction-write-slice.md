# SPRINT-82R - Atlas Month Close, Reopen, and Correction Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas month write slice

## Director Opening

SPRINT-82R extends Atlas write parity with the month control routes:

- `POST /api/workspaces/:workspaceId/months/:year/:month/close`
- `POST /api/workspaces/:workspaceId/months/:year/:month/reopen`
- `POST /api/workspaces/:workspaceId/months/:year/:month/correction`

This sprint does not enable production cutover and does not implement report creation/snapshot/package writes, imports, attachments, dictionary-training writes, internet-reference writes, flow creation, or FTP deployment.

## Agent Reports

### Financial Logic and QA Inspector - Heisenberg

Read-only inspection accepted the month write slice with these invariants:

- Month close requires writer access and valid year/month.
- Month close upserts one `v2_monthly_closures` row per workspace/year/month.
- Closing stores opening and closing balances from the generated monthly report.
- Month reopen requires an existing closed month and reuses the same closure row.
- Month correction must force `entry_type=correction`, `source_type=correction`, `status=corrected`.
- Month correction must not mutate the referenced original entry.
- Month correction must affect `corrections` and ending cash, but must not become external income, commercial income, or cash expense.
- All meaningful actions must be audited.
- Production cutover must remain blocked until all write routes are covered.

QA note accepted by Director:

- Legacy PHP currently allows `/months/.../correction` without requiring the month to already be closed.
- Atlas keeps that parity in this sprint. A stricter "corrections only for closed months" rule is a separate product decision, not a silent cutover change.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added `monthClosureRow()` response mapper.
  - Added month/year validation.
  - Added strict signed amount validation for correction rows.
  - Added `closeMonth()`.
  - Added `reopenMonth()`.
  - Added `createMonthCorrection()`.
  - Added Atlas allowlist and dispatch for the three month write routes.
  - Month close returns `{ closure, report }`.
  - Month reopen returns `{ closure, report }`.
  - Month correction returns `{ entry }`.
- `scripts/v2_atlas_write_smoke.js`
  - Added API-driven month close smoke.
  - Added closed-month write guard smoke.
  - Added correction row smoke with forced correction semantics.
  - Added monthly report correction total and ending cash assertions.
  - Added month reopen smoke.
  - Added correction cleanup before fixture cleanup.
  - Added the three month routes to write-smoke evidence.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `24/45` to `27/45`.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run check:atlas`

Expected blocked:

- `npm run gate:v2:atlas-cutover:strict` exited with code `2`

Write-smoke evidence:

- supported write routes: `27`
- temporary workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- month close created a closed closure and returned a closed monthly report.
- month close captured closing balance `470.95`.
- closed-month category patch without decision returned `closed_month_requires_decision`.
- month correction created `entry_type=correction`, `status=corrected`, `source_type=correction`.
- month correction `+5.00` moved cash ending balance to `475.95`.
- monthly correction total became `5.00`.
- month correction did not become external cash income.
- month reopen returned open closure/report and cleared report comment.
- Claudia Z finance snapshot unchanged:
  - `v2_entries`: `1638`
  - `v2_flows`: `52`
  - `v2_monthly_closures`: `4`
  - `v2_report_batches`: `8`
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
- Atlas write supported: `27`
- unsupported reads: `0`
- unsupported writes: `18`
- cutover allowed: `false`

Remaining unsupported writes:

- `POST /api/workspaces/:workspaceId/flows`
- report snapshot/batch/fragment/package writes
- raw-history conversion
- dictionary-training writes
- internet-reference writes
- import writes
- attachment writes
- category-rule writes

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-82R is accepted locally.

The Atlas sidecar now supports month close, month reopen, and explicit month correction writes while preserving the operational journal as source of truth and keeping Claudia Z unchanged after smoke cleanup.

Next safe sprint:

- SPRINT-83R - Report fragment and report package write slice.
