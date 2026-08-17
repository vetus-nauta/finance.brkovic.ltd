# SPRINT-77R - Atlas Accountable Report Review/Accept Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas accountable-report review/accept write slice

## Director Opening

SPRINT-77R extends Atlas write work with the admin review layer for submitted employee reports:

- `POST /api/accountable-reports/:reportId/review-preview`
- `POST /api/accountable-reports/:reportId/accept`

This sprint does not enable production cutover and does not move operational entry materialization, settlement cash resolution, imports, month close/reopen, category decisions, attachments, or report package writes to Atlas.

## Agent Reports

### Accountable Review Contract Inspector - Dirac

Contract accepted from PHP source:

- Review preview:
  - owner/admin only.
  - loads report and offer.
  - computes review plan without writes.
  - does not require report status to be `submitted`.
- Admin accept:
  - owner/admin only.
  - report must be `submitted`.
  - locks report and offer.
  - computes review plan.
  - updates report status to `accepted_by_admin`.
  - writes review totals and `settlement_status`.
  - keeps `no_financial_mutation = 1`.
  - updates report rows with review decisions.
  - creates one settlement row for the report.
  - returns `materialized_entries: []`.
  - audit action: `accountable_report/accept_by_admin`.

Settlement logic:

- `accepted_cash_expenses` includes only rows with payment method `cash`.
- `card`, `noncash`, and `own_funds` count as noncash.
- `expected_remaining = issued_amount - accepted_cash_expenses`.
- settlement status is:
  - `reimburse_due` when expected remaining is negative,
  - `return_due` when expected remaining is positive,
  - `closed` when it is zero.
- `actual_remaining` defaults to `max(expected_remaining, 0)`.
- `difference_amount = actual_remaining - expected_remaining`.
- A difference does not currently change status to `discrepancy`.

Hard stops:

- No operational entry creation.
- No flow balance mutation.
- No cash/card balance mutation.
- No report materialization links.
- No settlement cash resolution.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas write support for report review preview and admin accept.
  - Added admin guard matching PHP employee/non-admin masking.
  - Added accountable report review plan.
  - Added settlement creation on admin accept.
  - Added row review updates.
  - Added `accept_by_admin` audit payload with empty `materialized_entries`.
- `scripts/v2_atlas_write_smoke.js`
  - Extends the temporary employee/accountable fixture:
    - submitted employee report,
    - admin review preview,
    - admin accept with partial row adjustment,
    - settlement verification,
    - no materialized entries,
    - repeat admin accept rejection,
    - audit verification,
    - cleanup.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 12 to 14 supported write routes.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run gate:v2:atlas-cutover:strict` blocked as expected with exit code `2`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js scripts/v2_atlas_write_smoke.js package.json`

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
  - `PATCH /api/workspaces/:workspaceId/assistant-settings`
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
- Atlas write supported: `14`
- unsupported reads: `0`
- unsupported writes: `31`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-77R is accepted locally.

The Atlas sidecar now covers accountable flow through admin-accepted report and open/closed settlement calculation, while still blocking materialization into operational entries.

Next safe sprint:

- SPRINT-78R - Accountable materialization and settlement resolution decision gate.
