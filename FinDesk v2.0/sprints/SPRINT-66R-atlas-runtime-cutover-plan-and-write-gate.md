# SPRINT-66R — Atlas Runtime Cutover Plan and Write Gate

Date: 2026-08-13
Status: accepted / cutover blocked by explicit runtime gate

## Director Sprint Opening

Sprint:
SPRINT-66R — Atlas Runtime Cutover Plan and Write Gate

Goal:
Prevent accidental production switching to an incomplete Atlas runtime, enumerate the full FinDesk v2 API surface, and create an executable gate that proves why the current safe state is `blocked`.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-64R-atlas-backup-and-commit-tool-gate.md`
- `FinDesk v2.0/sprints/SPRINT-65R-atlas-backed-v2-runtime-adapter.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2-api.php`
- `public/assets/v2/app.js`
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_commit_payload.js`
- `app/auth.php`

Agents assigned:

- Backend Runtime Agent
- QA / Security / Acceptance Agent
- Director as gate implementation owner

Agent tasks:

- Backend Runtime Agent: audit all PHP/MySQL v2 routes and recommend the safest Atlas cutover architecture.
- QA / Security / Acceptance Agent: define exact acceptance checks, rollback path, auth/security risks, and release blockers.
- Director: implement a no-write Atlas cutover gate and document the accepted boundary.

Expected reports:

- Route groups: covered reads, missing reads, writes, preview-like writes.
- Runtime architecture recommendation.
- Hard blockers.
- Exact smoke/parity/security checks.
- Rollback plan.

Exit criteria:

- A gate script exists and is executable.
- Gate proves the SPRINT-65 Atlas read slice still matches Claudia Z baseline.
- Gate enumerates current route surface.
- Gate blocks production cutover until missing GET routes and all write routes are handled.
- No production deploy or write reroute is performed.

Risks:

- Current browser still calls `/v2-api.php`.
- PHP/MySQL remains the live write runtime.
- Auth/session remains MySQL-backed.
- Atlas server currently uses a fixed local user id and is not production auth-ready.
- Writes include side effects outside database collections: attachments and report HTML snapshots.

## Agent Reports Received

### Backend Runtime Agent

Decision:

- Keep `/v2-api.php` as the browser public endpoint because it already owns session, auth, CSRF, and the frontend hardcodes that path.
- Add an explicit runtime router later instead of exposing the Node Atlas server directly.

Recommended runtime modes:

- `mysql`: current default; all reads and writes remain PHP/MySQL.
- `atlas_shadow`: PHP/MySQL serves responses; proven safe GETs are mirrored to Atlas and compared/logged.
- `atlas_read`: only parity-proven GETs are served from Atlas; non-GET remains blocked.
- `atlas_write`: only after freeze, backup, parity, browser QA, and rollback gate.

Covered Atlas read slice from SPRINT-65:

- `GET /api/workspaces`
- `GET /api/workspaces/:workspaceId`
- `GET /api/workspaces/:workspaceId/flows`
- `GET /api/workspaces/:workspaceId/categories`
- `GET /api/workspaces/:workspaceId/entries`
- `GET /api/workspaces/:workspaceId/summary`
- `GET /api/workspaces/:workspaceId/reports/monthly`
- `GET /api/workspaces/:workspaceId/other-expenses`
- `GET /api/workspaces/:workspaceId/reports/operational-fragments`
- `GET /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId`

Hard blockers:

- Atlas service is intentionally read-only.
- Auth/session/role parity is not production-ready.
- Many current UI GET routes are still absent.
- All write routes are absent.
- MySQL remains live editable, so Atlas writes would create split brain without a single-writer switch.

### QA / Security / Acceptance Agent

Required checks before any future cutover:

```bash
npm run check:atlas
npm run audit:v2:claudia-z
npm run parity:v2:mysql
npm run parity:v2:atlas
node scripts/v2_compare_parity_exports.js <fresh-mysql-export.json> <fresh-atlas-export.json>
npm run smoke:v2:atlas-runtime
npm run smoke:v2:auth
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
FINDESK_V2_PREFLIGHT_ENV=production FINDESK_V2_PRODUCTION_BASE_URL=https://finance.brkovic.ltd npm run smoke:v2:deploy
```

Security blockers:

- Do not expose Mongo URI in logs or HTTP errors.
- Do not expose raw Node Atlas server publicly.
- Preserve CSRF behavior for mutating browser/API requests.
- Resolve auth split-brain before production write cutover.
- Keep rollback artifacts before any switch.

Rollback rule:

- Cutover must be reversible by routing/config.
- PHP/MySQL runtime must remain intact until Atlas write runtime is proven.
- If Atlas writes happen during a failed test window, reconcile from the cutover write log; do not blindly restore over newer data.

## Implementation

Files added:

- `scripts/v2_atlas_cutover_gate.js`

Files updated:

- `package.json`

New scripts:

```bash
npm run gate:v2:atlas-cutover
npm run gate:v2:atlas-cutover:strict
```

Gate behavior:

- `gate:v2:atlas-cutover` expects the current safe state to be blocked and exits cleanly when blocked.
- `gate:v2:atlas-cutover:strict` exits non-zero while cutover is blocked.
- The gate checks Claudia Z Atlas baseline and write guard.
- The gate enumerates 81 current v2 API routes.
- The gate reports unsupported GET routes and all unsupported write routes.

## Evidence

Runtime smoke:

```bash
npm run smoke:v2:atlas-runtime
```

Result:

```json
{
  "ok": true,
  "workspaces": 5,
  "flows": 2,
  "categories": 22,
  "entries": 279,
  "august_entries": 39,
  "cash_now": 3893,
  "august_ending_cash": 3893,
  "active_fragments": 1,
  "all_fragment_batches": 5,
  "other_expenses": 3
}
```

Cutover gate:

```bash
npm run gate:v2:atlas-cutover
```

Result:

- `cutover_allowed`: `false`
- route surface: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `10`
- unsupported reads: `26`
- unsupported writes: `45`
- blockers:
  - `browser_still_points_to_php_mysql_v2_api`
  - `atlas_write_repository_not_implemented`
  - `full_get_route_parity_not_implemented`
  - `ftp_production_cutover_not_authorized`

Static checks:

```bash
node --check scripts/v2_atlas_cutover_gate.js
node --check scripts/v2_atlas_runtime_smoke.js
node --check server/findesk-v2-atlas-read-server.js
git diff --check -- scripts/v2_atlas_cutover_gate.js scripts/v2_atlas_runtime_smoke.js server/findesk-v2-atlas-read-server.js package.json
```

Result:

- All checks passed.

## Director Decision

SPRINT-66R is accepted as a cutover guard sprint.

Production Atlas cutover is explicitly rejected at this stage.

The correct current state:

- Atlas data is canonical and parity-proven.
- Local Atlas read slice works for core Claudia Z data.
- Full product runtime still belongs to PHP/MySQL.
- Any future direct-address deploy must keep `/v2-api.php` on MySQL until SPRINT-67+ expands Atlas GET parity and then SPRINT-68+ implements Atlas write runtime.

## Director Final Handoff

Sprint:
SPRINT-66R — Atlas Runtime Cutover Plan and Write Gate

Status:
Accepted as blocked gate; not accepted for production cutover.

Agents assigned:

- Backend Runtime Agent
- QA / Security / Acceptance Agent
- Director as gate implementation owner

Agent reports received:

- Backend route and architecture report received.
- QA/security/rollback report received.

Accepted work:

- Route surface inventory.
- Cutover gate script.
- Package scripts.
- Blocked-state evidence.

Rejected work:

- Atlas write cutover.
- Browser reroute to Atlas.
- Raw Node exposure.
- Production deploy as Atlas runtime.

Files changed:

- `scripts/v2_atlas_cutover_gate.js`
- `package.json`
- `FinDesk v2.0/sprints/SPRINT-66R-atlas-runtime-cutover-plan-and-write-gate.md`

Tests or checks:

- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `node --check server/findesk-v2-atlas-read-server.js`
- `git diff --check -- scripts/v2_atlas_cutover_gate.js scripts/v2_atlas_runtime_smoke.js server/findesk-v2-atlas-read-server.js package.json`

Risks:

- Route inventory is currently maintained in the gate script and must be updated when `app/v2/Api.php` changes.
- Atlas write runtime still needs implementation.
- Full browser parity against Atlas is not available yet.

Next sprint:
SPRINT-67R — Atlas GET Parity Expansion and Shadow Runtime Router

Paste-to-next-director prompt:

```text
You are the FinDesk v2 Director.
Source of truth is GitHub files.
Read SPRINT-64R, SPRINT-65R, and SPRINT-66R.
Atlas has canonical v2 data, and a local read-only Atlas slice passes Claudia Z smoke.
SPRINT-66R added a cutover gate that blocks production switching: 81 routes, 36 reads, 45 writes, only 10 Atlas reads covered.
Do not switch production.
Next: implement atlas_shadow / atlas_read routing behind /v2-api.php and expand GET parity before any write work.
```
