# SPRINT-80R - Atlas Operational Entry Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas operational entry write slice

## Director Opening

SPRINT-80R extends Atlas write work with the core operational journal write routes:

- `POST /api/workspaces/:workspaceId/parse-preview`
- `POST /api/parse-entry-preview`
- `POST /api/workspaces/:workspaceId/entries`
- `PATCH /api/entries/:entryId`
- `DELETE /api/entries/:entryId`

This sprint does not enable production cutover and does not implement category-only updates, closed-month category decisions, attachments, month close/reopen/correction, report package writes, imports, dictionary training writes, internet-reference writes, or FTP cutover.

## Agent Reports

### Operational Entry Contract Inspector - Hubble

Contract accepted from PHP source:

- Create route whitelists only `flow_id`, `date`, `raw_text`, `category_code`, `amount`, `closed_month_decision`.
- Preview requires `flow_id` and `raw_text`.
- `/api/parse-entry-preview` also requires `workspace_id`.
- Create/update/delete require workspace entry writer.
- Employee scoped mode does not own these general mutation routes.
- Leading signed amount drives `sign`, `amount`, `direction`, and `entry_type`.
- Cash `+/-` maps to `cash_income/cash_expense`; card `-` maps to `card_expense`; card `+` is only valid for correction/import.
- Create/update/delete recalculate flow balance.
- Live balance is only written for cash flows with `has_live_balance`.
- Accountable projection entries with `source_type=accountable_report` are immutable.
- Closed months require `closed_month_decision = recalculate_chain`.
- Locked report fragments require `report_fragment_decision = recalculate_fragment`.
- Mutating an entry marks affected operational fragments and packages as `requires_update`.
- Audit actions are `entry/create`, `entry/update`, `entry/delete`, plus `operational_reports_require_update` where applicable.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas preview route support.
  - Added Atlas entry create/update/delete route support.
  - Added operational entry normalization for sign, amount, direction, entry type, category guess, semantic markers, status, confidence, and preview payload.
  - Added single-entry readback with flow/category/actor/report-lock enrichment.
  - Added cash-flow balance recalculation.
  - Added closed-month and report-fragment mutation guards.
  - Added accountable projection immutability guard.
  - Added report/package `requires_update` marking for mutated entries.
  - Added entry audit actions.
- `scripts/v2_atlas_write_smoke.js`
  - Added parse preview via both routes.
  - Added create/update/delete of a temporary cash entry.
  - Verified balance movement and restoration after delete.
  - Verified accountable projection entry immutability.
  - Replaced previous entry-write blocked assertions with real CRUD checks.
- `scripts/v2_atlas_runtime_smoke.js`
  - Moved unsupported-write guard to dictionary-training write route.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 17 to 22 supported write routes.
  - Moved unsupported-write guard to dictionary-training write route.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run gate:v2:atlas-cutover:strict` blocked as expected with exit code `2`
- `npm run check:atlas`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_write_smoke.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js`

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
  - `POST /api/accountable-settlements/:settlementId/cash-resolve`
  - `PATCH /api/workspaces/:workspaceId/assistant-settings`
  - `POST /api/workspaces/:workspaceId/entries`
  - `POST /api/workspaces/:workspaceId/parse-preview`
  - `POST /api/parse-entry-preview`
  - `PATCH /api/entries/:entryId`
  - `DELETE /api/entries/:entryId`
- temporary entry create balance after: `472.95`
- temporary entry update balance after: `470.95`
- temporary entry delete restored workspace cash: `482.95`
- accountable projection edit rejected: `accountable_projection_entry_immutable`
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
- Atlas write supported: `22`
- unsupported reads: `0`
- unsupported writes: `23`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-80R is accepted locally.

The Atlas sidecar now supports backend write operations for the main operational journal CRUD path while preserving balance recalculation, report locks, month locks, and accountable projection immutability.

Next safe sprint:

- SPRINT-81R - Category correction and closed-month category decision write slice.
