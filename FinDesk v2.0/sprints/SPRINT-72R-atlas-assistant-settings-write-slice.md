# SPRINT-72R — Atlas Assistant Settings Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as first local Atlas write slice

## Director Opening

SPRINT-72R starts Atlas write work with the lowest-risk non-financial route:

- `PATCH /api/workspaces/:workspaceId/assistant-settings`

This sprint does not enable production cutover and does not move operational entries, reports, imports, months, category decisions, attachments, Hall invites, or accountable money flows.

## Agent Reports

### Backend Contract Inspector — Kuhn

Contract accepted from PHP source:

- Route response wrapper: `{ ok: true, settings }`.
- Access: owner/admin only via `requireWorkspaceOwnerAdmin`.
- Default GET settings when no row exists:
  - `mr_smith_enabled=false`
  - `internet_reference_mode=per_request`
  - `provider_key=stub`
  - `retention_days=30`
- Validation:
  - `internet_reference_mode`: `disabled | per_request | workspace_enabled`
  - `provider_key`: currently `stub`; `allowlisted_http` is not enabled in this local Atlas slice
  - `retention_days`: integer, clamped `1..365`
  - `mr_smith_enabled`: PHP-compatible boolean behavior
- Persistence:
  - Upsert `v2_workspace_assistant_settings` by `workspace_id`.
  - Set `updated_by`.
  - Read back after write.
  - Write audit row with entity `workspace_assistant_settings`, action `update`, before/after snapshots.

Hard stops:

- No general Atlas write runtime.
- No financial mutation.
- All other write routes stay blocked.

### QA Acceptance Inspector — Halley

Acceptance criteria:

- Exactly one write route is supported in Atlas sidecar.
- Write smoke must patch, read back, restore, and read back again.
- Audit row must be created.
- Financial collections and Claudia Z cash totals must remain unchanged.
- Invalid mode/provider/retention must be rejected.
- Operational entry writes and dictionary decision writes must stay blocked.
- Cutover remains `false`.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added single-route PATCH support for assistant settings.
  - Added Mongo transaction for settings upsert + audit insert.
  - Added JSON request body parsing for non-GET HTTP calls.
  - Kept all other writes blocked with `atlas_write_route_not_supported`.
- `scripts/v2_atlas_write_smoke.js`
  - New restore-smoke for assistant settings.
  - Verifies audit and no-finance-mutation snapshot.
- `scripts/v2_atlas_runtime_smoke.js`
  - Updated write guard expectation.
- `scripts/v2_atlas_cutover_gate.js`
  - Added `atlas_write_supported: 1`.
  - Unsupported writes now `44`.
  - Blocker is now `atlas_write_repository_incomplete`.
- `package.json`
  - Added `smoke:v2:atlas-write`.

## Verification

Passed:

- `npm run check:atlas`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run gate:v2:atlas-cutover:strict` blocked as expected
- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js scripts/v2_atlas_write_smoke.js package.json`

Write-smoke evidence:

- route: `PATCH /api/workspaces/:workspaceId/assistant-settings`
- workspace: `43a20c32-a9e6-4812-a556-6f1cb995147d`
- restored: `true`
- audit created: `true`
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
- Atlas write supported: `1`
- unsupported reads: `0`
- unsupported writes: `44`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-72R is accepted as the first Atlas write milestone.

FinDesk v2.0 is not yet on Atlas as a full runtime. The next safe write slices should continue with non-financial or reversible surfaces before moving to operational entries:

- workspace metadata PATCH / DELETE trash semantics,
- Hall invites create/revoke,
- then accountable offer/report writes,
- only after that operational entries and report/month close writes.
