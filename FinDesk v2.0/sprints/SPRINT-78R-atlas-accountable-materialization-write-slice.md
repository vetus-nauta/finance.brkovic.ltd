# SPRINT-78R - Atlas Accountable Materialization Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas accountable materialization write slice

## Director Opening

SPRINT-78R extends Atlas write work with the projection-only materialization layer for accepted employee accountable reports:

- `POST /api/accountable-reports/:reportId/materialization-preview`
- `POST /api/accountable-reports/:reportId/materialize`

This sprint does not enable production cutover and does not implement cash settlement resolution, operational entry CRUD, imports, month close/reopen, dictionary decisions, attachments, or report package writes.

## Agent Reports

### Accountable Materialization Contract Inspector - Russell

Contract accepted from PHP source:

- Both routes load the report and require owner/admin access.
- Employee callers are masked with `accountable_report_not_found`.
- Report must be `accepted_by_admin`.
- Preview is read-only and returns policy `cash_effect_none_category_projection`.
- Eligible rows are `accepted` or `adjusted`, with accepted amount greater than zero, category, and payment method.
- Materialization creates projection entries in an `accountable` flow.
- Projection entries use `entry_type = accountable_expense`, `source_type = accountable_report`, status `accepted`, and `cash_effect = none`.
- Projection entries must not mutate cash/card balances.
- Each report row receives `operational_entry_id`.
- Each projected entry is linked through `v2_accountable_report_entry_links`.
- Existing links are skipped, so repeated materialization does not duplicate entries.
- Report ledger status becomes `materialized`.
- Audit action is `accountable_report/ledger_project`.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added materialization preview plan matching PHP.
  - Added category lookup with workspace-local category priority.
  - Added closed-month guard for projection dates.
  - Added accountable projection flow creation/readback.
  - Added projection entry creation and report-row link creation.
  - Added idempotency hashing and repeat-materialize no-duplicate behavior.
  - Added `ledger_project` audit.
  - Added POST route support for preview/materialize.
- `scripts/v2_atlas_write_smoke.js`
  - Added admin preview, employee-denied preview, materialize, readback, repeat-materialize, audit, and cleanup checks.
  - Cleanup now removes temporary workspace projection entries.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 14 to 16 supported write routes.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run gate:v2:atlas-cutover:strict` blocked as expected with exit code `2`
- `npm run check:atlas`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_write_smoke.js scripts/v2_atlas_cutover_gate.js`

Write-smoke evidence:

- write routes:
  - `POST /api/workspaces`
  - `PATCH /api/workspaces/:workspaceId`
  - `DELETE /api/workspaces/:workspaceId`
  - `POST /api/workspaces/:workspaceId/invites`
  - `POST /api/workspaces/:workspaceId/invites/:inviteId/revoke`
  - `POST /api/workspace-invites/preview`
  - `POST /api/workspace-invites/accept`
  - `POST /api/workspaces/:workspaceId/accountable-offers`
  - `POST /api/accountable-offers/:offerId/accept`
  - `POST /api/workspaces/:workspaceId/accountable-reports`
  - `POST /api/accountable-reports/:reportId/submit`
  - `POST /api/accountable-reports/:reportId/review-preview`
  - `POST /api/accountable-reports/:reportId/accept`
  - `POST /api/accountable-reports/:reportId/materialization-preview`
  - `POST /api/accountable-reports/:reportId/materialize`
  - `PATCH /api/workspaces/:workspaceId/assistant-settings`
- materialization created entries: `2`
- repeat materialization created entries: `0`
- materialization policy: `cash_effect_none_category_projection`
- cash/card delta: `0.00 / 0.00`
- restored: `true`
- audit created: `true`
- workspace fixture cleaned: `true`
- unsupported write guard: `ok`
- finance snapshot unchanged:
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
- Atlas write supported: `16`
- unsupported reads: `0`
- unsupported writes: `29`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-78R is accepted locally.

The Atlas sidecar now supports employee accountable flow through accepted report materialization into projection-only operational entries, without mutating cash/card balances.

Next safe sprint:

- SPRINT-79R - Accountable settlement cash resolution write slice.
