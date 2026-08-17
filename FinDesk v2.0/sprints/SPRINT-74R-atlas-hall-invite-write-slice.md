# SPRINT-74R - Atlas Hall Invite Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas Hall invite write slice

## Director Opening

SPRINT-74R extends Atlas write work with the employee referral/join loop:

- `POST /api/workspaces/:workspaceId/invites`
- `POST /api/workspaces/:workspaceId/invites/:inviteId/revoke`
- `POST /api/workspace-invites/preview`
- `POST /api/workspace-invites/accept`

This sprint does not enable production cutover and does not move operational entries, reports, imports, month close/reopen, category decisions, attachments, accountable offers, accountable reports, or accountable settlements to Atlas writes.

## Agent Reports

### Hall Invite Contract Inspector - Kant

Contract accepted from PHP source:

- Create invite:
  - owner/admin only.
  - `role` is only `employee`.
  - `access_scope` is only `own_entries`.
  - email key may be `invited_email` or `email`.
  - email is required, lowercased, and validated.
  - `name` optional.
  - `expires_days` defaults to `7`, clamped `1..30`.
  - stores only `token_hash`; returns secret `token` only on create.
  - audit action: `workspace_invite/create`.
- Revoke invite:
  - owner/admin only.
  - only `pending` invite can be revoked.
  - audit action: `workspace_invite/revoke`.
- Preview invite:
  - authenticated user.
  - no audit.
  - rejects invalid, missing, accepted, revoked, or expired token.
  - returns `email_matches`.
- Accept invite:
  - authenticated user.
  - email must match when invite has `invited_email`.
  - creates employee workspace membership with `own_entries`.
  - rejects duplicate membership.
  - marks invite accepted.
  - audit action: `workspace_invite/accept`.

Hard stops:

- No money movement.
- No accountable offer/report creation.
- No operational entry mutation.
- Invite token remains secret only at creation time.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas write support for invite create/revoke/preview/accept.
  - Added token hashing, email normalization, pending/expired invite checks.
  - Added accepted employee membership creation in `v2_workspace_members`.
  - Added invite create/accept/revoke audit rows without token/url secrets.
  - Added actor-aware `handleApi(..., userId)` support for smoke and route parity.
  - Preserved `GET /api/workspace-invites/:token` by routing it through the same preview helper.
- `scripts/v2_atlas_write_smoke.js`
  - Added temporary user fixture for real invite accept.
  - Verifies create, preview, accept, repeat accept rejection, revoke, repeat revoke rejection, invite listing, audit, and cleanup.
  - Keeps existing workspace admin and assistant settings write smoke.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 4 to 8 supported write routes.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run check:atlas`
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
- Atlas write supported: `8`
- unsupported reads: `0`
- unsupported writes: `37`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-74R is accepted locally.

FinDesk v2.0 now has Atlas parity for all GET routes and controlled Atlas writes for workspace administration, Hall employee invites, invite preview/accept, and assistant settings.

Next safe sprint:

- SPRINT-75R - Accountable offer write slice:
  - `POST /api/workspaces/:workspaceId/accountable-offers`
  - `POST /api/accountable-offers/:offerId/accept`
