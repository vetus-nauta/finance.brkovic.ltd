# SPRINT-73R - Atlas Workspace Admin Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas workspace-admin write slice

## Director Opening

SPRINT-73R extends Atlas write work with reversible Hall/workspace administration routes:

- `POST /api/workspaces`
- `PATCH /api/workspaces/:workspaceId`
- `DELETE /api/workspaces/:workspaceId`
- preserved from SPRINT-72R: `PATCH /api/workspaces/:workspaceId/assistant-settings`

This sprint does not enable production cutover and does not move operational entries, reports, imports, month close/reopen, category decisions, attachments, invites, accountable offers, accountable reports, or accountable settlements to Atlas writes.

## Agent Reports

### Backend Contract Inspector - Locke

Contract accepted from PHP repository behavior:

- `POST /api/workspaces` response wrapper: `{ ok: true, workspace }`.
- `PATCH /api/workspaces/:workspaceId` response wrapper: `{ ok: true, workspace }`.
- `DELETE /api/workspaces/:workspaceId` response wrapper: `{ ok: true, workspace }`.
- Workspace create:
  - `name` required, max 190.
  - `type`: `yacht | family | personal | business | trip | custom`.
  - `currency`: uppercase, default `EUR`.
  - `locale`: default `ru`.
  - creates owner membership for current user.
  - creates default Cash flow and Card flow.
  - Cash flow gets opening cash; Card flow starts at zero.
  - audit action: `workspace/create`.
- Workspace update:
  - allowed for workspace writer roles.
  - updates workspace metadata only.
  - audit action: `workspace/update`.
- Workspace delete:
  - owner/admin only.
  - soft delete via `archived_at`.
  - response states `trash_retention_days: 60`.
  - audit action: `workspace/delete_to_trash`.

Hard stops:

- No financial mutation in this sprint.
- No operational entry, report, month, import, dictionary, attachment, invite, or accountable write migration.
- Archived workspaces must not appear in workspace list and must read as not found.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas write support for workspace create/update/delete-to-trash.
  - Added Mongo transaction flow for workspace writes.
  - Added owner membership and default Cash/Card flow creation for new workspaces.
  - Added workspace create/update/delete audit rows.
  - Added transaction-aware workspace access helpers.
  - Kept unsupported write routes blocked with `atlas_write_route_not_supported`.
- `scripts/v2_atlas_write_smoke.js`
  - Added full workspace fixture smoke:
    - create workspace,
    - verify default flows,
    - patch metadata,
    - reject invalid type,
    - delete to trash,
    - verify archived workspace disappears,
    - verify audit actions,
    - clean test fixture.
  - Preserved assistant-settings restore smoke from SPRINT-72R.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write surface from 1 to 4 supported write routes.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
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

Runtime smoke evidence:

- workspaces: `5`
- entries: `279`
- august entries: `39`
- cash now: `3893`
- august ending cash: `3893`
- active fragments: `1`
- report batches: `1`
- html snapshots: `1`
- dictionary training decisions: `111`
- raw history rows: `3338`

Gate result:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `36`
- Atlas write supported: `4`
- unsupported reads: `0`
- unsupported writes: `41`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-73R is accepted locally.

Atlas now has full GET parity and four controlled write routes, but FinDesk v2.0 is not yet a full Atlas runtime. Production remains protected until all write routes are migrated, smoke-tested, and explicitly authorized for FTP/browser cutover.

Next safe sprint:

- SPRINT-74R - Hall invite write slice:
  - `POST /api/workspaces/:workspaceId/invites`
  - `POST /api/workspaces/:workspaceId/invites/:inviteId/revoke`
  - `POST /api/workspace-invites/preview`
  - `POST /api/workspace-invites/accept`
