# SPRINT-75R - Atlas Accountable Offer Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas accountable-offer write slice

## Director Opening

SPRINT-75R extends Atlas write work with the first accountable workflow mutation:

- `POST /api/workspaces/:workspaceId/accountable-offers`
- `POST /api/accountable-offers/:offerId/accept`

This sprint does not enable production cutover and does not move operational entries, reports, imports, month close/reopen, category decisions, attachments, accountable reports, accountable review/materialization, or accountable settlements to Atlas writes.

## Agent Reports

### Accountable Offer Contract Inspector - Aristotle

Contract accepted from PHP source:

- Create accountable offer:
  - owner/admin only.
  - target must be exactly one of `employee_user_id` or email-like field: `employee_email`, `invited_email`, `email`.
  - `employee_user_id` must be a positive active employee workspace member.
  - email target is normalized and may resolve to an existing employee user id.
  - `amount` is required and must be positive.
  - `currency` defaults to workspace currency and must be three uppercase letters.
  - `purpose` or `comment` is required; stored as `purpose`.
  - creates `v2_accountable_offers` row with `status = pending_offer`.
  - `no_financial_mutation` remains true.
  - audit action: `accountable_offer/create`.
- Accept accountable offer:
  - employee-only.
  - offer must be visible to that employee by `employee_user_id` or matching email.
  - non-visible offer returns `accountable_offer_not_found`.
  - status must be `pending_offer`.
  - marks offer `accepted_by_employee`.
  - fills `employee_user_id` when offer was email-targeted.
  - audit action: `accountable_offer/accept_by_employee`.

Hard stops:

- No ledger mutation.
- No cash/card balance mutation.
- No operational entry mutation.
- No accountable report creation or materialization.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas write support for accountable offer create and employee accept.
  - Added employee target resolution by user id or email.
  - Added employee-only visibility guard for accept.
  - Added validation for amount, currency, purpose/comment, and target ambiguity.
  - Added `accountable_offer` audit rows for create and accept.
  - Kept `no_financial_mutation` true.
- `scripts/v2_atlas_write_smoke.js`
  - Extends the temporary workspace + invite employee fixture:
    - creates employee invite,
    - accepts invite as temporary employee,
    - creates accountable offer as owner,
    - accepts accountable offer as employee,
    - rejects repeat accept,
    - verifies employee list visibility,
    - verifies audit,
    - cleans all fixture rows.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 8 to 10 supported write routes.

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
- Atlas write supported: `10`
- unsupported reads: `0`
- unsupported writes: `35`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-75R is accepted locally.

FinDesk v2.0 now has Atlas parity for all GET routes and controlled Atlas writes for workspace administration, Hall employee invites, invite preview/accept, accountable offer create/accept, and assistant settings.

Next safe sprint:

- SPRINT-76R - Accountable report draft/submit write slice:
  - `POST /api/workspaces/:workspaceId/accountable-reports`
  - `POST /api/accountable-reports/:reportId/submit`
