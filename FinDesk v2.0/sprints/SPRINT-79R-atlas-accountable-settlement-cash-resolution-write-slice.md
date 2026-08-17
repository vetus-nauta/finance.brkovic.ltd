# SPRINT-79R - Atlas Accountable Settlement Cash Resolution Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas accountable settlement cash-resolution write slice

## Director Opening

SPRINT-79R extends Atlas write work with physical cash resolution for employee accountable settlements:

- `POST /api/accountable-settlements/:settlementId/cash-resolve`

This sprint does not enable production cutover and does not implement general operational entry CRUD, imports, month close/reopen, dictionary decisions, attachments, report package writes, or FTP cutover.

## Agent Reports

### Accountable Settlement Contract Inspector - Carver

Contract accepted from PHP source:

- Route returns `['ok' => true, 'result' => repo->resolveAccountableSettlementWithCashMovement(...)]`.
- Route is settlement-id based, so workspace access is enforced after settlement lookup.
- User must be owner/admin of the settlement workspace.
- If settlement is already resolved, endpoint returns existing settlement with `entry: null`.
- Only settlement statuses `return_due` and `reimburse_due` are resolvable.
- `return_due` creates a cash-in entry for `return_due_amount`.
- `reimburse_due` creates a cash-out entry for `reimburse_due_amount`.
- `date` is optional and defaults to today's date.
- `raw_text` is optional; if it does not start with signed amount, the endpoint prefixes the expected signed amount.
- `note` / `resolution_note` are optional.
- Settlement cash entry must be balance-only: cash flow, no category, exact expected amount and direction.
- Successful first resolve writes:
  - entry audit action `create`,
  - settlement audit action `resolve_physical_cash`.
- Closed month requires explicit `closed_month_decision = recalculate_chain`.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas write support for settlement cash resolution.
  - Added cash-flow lookup for workspace.
  - Added narrow internal balance-only cash-entry creation for settlement resolution.
  - Added cash-flow `balance_after` recalculation for settlement cash entries.
  - Added optional date behavior matching PHP.
  - Added closed-month `recalculate_chain` confirmation behavior.
  - Added settlement update and audit action `resolve_physical_cash`.
  - Kept general operational entry write routes blocked.
- `scripts/v2_atlas_write_smoke.js`
  - Extends accountable fixture with physical settlement resolution.
  - Verifies employee cannot resolve settlement.
  - Verifies wrong direction is rejected.
  - Verifies first resolve creates one cash entry and updates balance.
  - Verifies repeat resolve returns `entry: null`.
  - Verifies settlement audit.
  - Verifies fixture cleanup restores Claudia Z finance snapshot.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 16 to 17 supported write routes.

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
  - `POST /api/accountable-settlements/:settlementId/cash-resolve`
  - `PATCH /api/workspaces/:workspaceId/assistant-settings`
- settlement resolve amount: `359.50`
- settlement entry direction: `in`
- settlement entry balance after: `482.95`
- repeat settlement resolve entry: `null`
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
- Atlas write supported: `17`
- unsupported reads: `0`
- unsupported writes: `28`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-79R is accepted locally.

The Atlas sidecar now supports the full employee accountable chain through offer, employee acceptance, employee report, admin review, projection materialization, and physical cash settlement resolution.

Next safe sprint:

- SPRINT-80R - Operational entry parse/create/edit/delete write slice opening.
