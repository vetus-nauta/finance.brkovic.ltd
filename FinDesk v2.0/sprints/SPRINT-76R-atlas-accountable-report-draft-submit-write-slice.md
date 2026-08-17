# SPRINT-76R - Atlas Accountable Report Draft/Submit Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas accountable-report draft/submit write slice

## Director Opening

SPRINT-76R extends Atlas write work with the employee report submission path:

- `POST /api/workspaces/:workspaceId/accountable-reports`
- `POST /api/accountable-reports/:reportId/submit`

This sprint does not enable production cutover and does not move operational entries, report review/acceptance, report materialization, settlement resolution, imports, month close/reopen, category decisions, attachments, or report package writes to Atlas.

## Agent Reports

### Accountable Report Contract Inspector - Anscombe

Contract accepted from PHP source:

- Create draft report:
  - employee role only.
  - employee must have scoped write access.
  - requires `offer_id`.
  - offer must exist in same workspace, be visible to employee, and be `accepted_by_employee`.
  - `rows` required, non-empty, max `100`.
  - each row requires valid date, description, positive amount, valid currency.
  - optional row fields: `category_code`, `notes`, `receipt_note`.
  - report title from `title`, then `comment`, then default.
  - creates `v2_accountable_reports` with `status = draft`.
  - creates `v2_accountable_report_rows`.
  - audit action: `accountable_report/create_draft`.
- Submit report:
  - employee owner only.
  - report must be `draft`.
  - `row_count` must be at least `1`.
  - updates status to `submitted`, with `submitted_at` and `submitted_by`.
  - audit action: `accountable_report/submit`.

Hard stops:

- No ledger mutation.
- No cash/card balance mutation.
- No operational entry mutation.
- No admin review.
- No materialization into the operational journal.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas write support for accountable report draft creation and submit.
  - Added `YYYY-MM-DD` input validation.
  - Added accountable report row normalization.
  - Added employee-only report ownership guard.
  - Added report create/submit audit rows.
  - Reused existing Atlas read helpers for report response shape with rows and settlement.
- `scripts/v2_atlas_write_smoke.js`
  - Extends the temporary employee/accountable fixture:
    - creates accepted accountable offer,
    - creates draft accountable report with two rows,
    - submits report,
    - rejects repeat submit,
    - verifies employee list visibility,
    - verifies audit,
    - cleans all fixture rows.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 10 to 12 supported write routes.

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
- Atlas write supported: `12`
- unsupported reads: `0`
- unsupported writes: `33`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-76R is accepted locally.

The Atlas sidecar now covers the complete employee path up to submitted accountable report:

1. workspace admin creates employee invite,
2. employee previews and accepts invite,
3. admin creates accountable offer,
4. employee accepts offer,
5. employee creates draft report,
6. employee submits report.

Next safe sprint:

- SPRINT-77R - Accountable report review/acceptance write slice:
  - `POST /api/accountable-reports/:reportId/review-preview`
  - `POST /api/accountable-reports/:reportId/accept`
