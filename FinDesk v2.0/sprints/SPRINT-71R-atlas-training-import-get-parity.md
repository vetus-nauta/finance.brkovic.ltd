# SPRINT-71R — Atlas Training / Import GET Parity

Date: 2026-08-13
Director: Codex
Status: Accepted as local read-only parity slice

## Director Opening

SPRINT-71R closes the remaining Atlas read surface for FinDesk v2.0 without enabling production cutover and without moving write operations.

Scope is deliberately limited to six GET routes:

- `GET /api/workspaces/:workspaceId/dictionary-review-queue`
- `GET /api/workspaces/:workspaceId/raw-history`
- `GET /api/workspaces/:workspaceId/dictionary-training-decisions`
- `GET /api/workspaces/:workspaceId/assistant-settings`
- `GET /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups`
- `GET /api/workspaces/:workspaceId/imports/:importId/review`

No POST/PATCH/DELETE behavior is accepted in this sprint.

## Agent Reports

### Backend Runtime Agent

Accepted direction:

- Finish training/import GET readback after SPRINT-70R.
- Keep Hall/accountable/write behavior out of scope.
- Preserve PHP response wrappers: `queue`, `history`, `decisions`, `settings`, `lookups`, `review`.
- Treat raw-history and import-review as read-only projections.

Implementation decisions:

- Added Atlas row mappers for dictionary decisions, assistant settings, and internet reference lookups.
- Added read models for raw history, dictionary review queue, and import review.
- Removed duplicate dictionary helper definitions in the Atlas sidecar so one canonical dictionary rule set is used.
- Assistant settings return PHP-compatible defaults when no workspace settings row exists.
- Internet reference lookup list does not expose Atlas credentials or raw connection material.
- Import review is a read-only report from migrated import source/row collections; accept/import remains blocked.

### QA Acceptance Agent

Acceptance criteria:

- Final GET support must become `36 / 36`.
- Writes must remain unsupported: `45 / 45`.
- `cutover_allowed` must remain `false`.
- Strict gate must still exit as blocked.
- Internet lookup data must not expose secrets.
- Import rows may include source review data for the local read model, but smoke/gate must not print raw financial rows.

Accepted evidence:

- Claudia Z fixture stayed stable:
  - entries: `279`
  - August entries: `39`
  - cash now: `3893.00`
  - August ending cash: `3893.00`
  - other review: `3`
- Training/import readback:
  - dictionary review groups: `20`
  - dictionary review rows: `3338`
  - dictionary review rows with money: `2508`
  - raw history sources: `57`
  - raw history rows: `3338`
  - dictionary training decisions: `111`
  - assistant settings provider: `stub`
  - internet reference lookups: `0`
  - import review rows: `29`

## Files Changed

- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`
- `public/v2-api.php`

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `php -l public/v2-api.php`
- `npm run check:atlas`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run gate:v2:atlas-cutover:strict` blocked as expected
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js public/v2-api.php`

Gate result:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `36`
- unsupported reads: `0`
- unsupported writes: `45`
- shadow gateway available: `true`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_not_implemented`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-71R is accepted as a local Atlas read-parity milestone.

FinDesk v2.0 is now Atlas-backed for the full GET route surface in the read-only sidecar/shadow model.

Production runtime is not yet accepted as Atlas runtime because every write route is still blocked. The next work must be a write repository plan, starting with the lowest-risk write slice and an idempotency/audit/rollback contract.
