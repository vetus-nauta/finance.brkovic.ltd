# SPRINT-68R — Atlas Summary GET Parity Slice

Date: 2026-08-13
Status: accepted / five more Atlas GET routes covered

## Director Sprint Opening

Sprint:
SPRINT-68R — Atlas Summary GET Parity Slice

Goal:
Expand the Atlas read sidecar and `/v2-api.php` shadow allowlist from the SPRINT-67 core GET slice into the first visible Summary/storage/detail-read routes, while keeping all writes blocked and production cutover rejected.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-67R-atlas-shadow-runtime-gateway.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `server/findesk-v2-atlas-read-server.js`
- `public/v2-api.php`
- `public/assets/v2/app.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Agents assigned:

- Backend Runtime Agent
- QA Acceptance Agent
- Director as implementation owner

Agent tasks:

- Backend Runtime Agent: inspect PHP response contracts and table/collection sources for the five target GET routes.
- QA Acceptance Agent: define acceptance checks, hard stops, and expected cutover gate count changes.
- Director: implement the five Atlas GET routes, extend shadow allowlist, update smokes and gate.

Expected reports:

- Route-by-route response keys.
- Collection sources.
- Read-only implementation advice.
- Acceptance command list.
- Expected route count changes.

Exit criteria:

- Five new GET routes work in Atlas sidecar.
- PHP shadow allowlist includes only matching GET routes, no writes.
- Runtime smoke asserts the five routes.
- Cutover gate increases supported Atlas reads from `10` to `15`.
- Strict cutover gate remains blocked.

## Agent Reports Received

### Backend Runtime Agent

Accepted contracts:

- `GET /api/workspaces/:workspaceId/reports/layer1-summary`
  - returns `{ ok: true, report }`
  - `report` includes `header`, `totals`, `money_position`, `blocks`, `source_trace`

- `GET /api/workspaces/:workspaceId/reports/layer1-source-entries`
  - returns `{ ok: true, entries, missing_ids }`
  - preserves requested id order
  - invalid ids are rejected with `invalid_ids`

- `GET /api/workspaces/:workspaceId/reports/layer1-snapshots`
  - returns `{ ok: true, snapshots }`
  - filters by optional `year` and `month`
  - sorts newest versions first

- `GET /api/workspaces/:workspaceId/reports/operational-packages`
  - returns `{ ok: true, packages }`
  - clamps `limit` to `1..100`
  - sorts newest first

- `GET /api/entries/:entryId/attachments`
  - returns `{ ok: true, attachments }`
  - metadata only
  - no file content read or exposed

### QA Acceptance Agent

Routes in scope only:

- `GET /api/workspaces/:workspaceId/reports/layer1-summary`
- `GET /api/workspaces/:workspaceId/reports/layer1-source-entries`
- `GET /api/workspaces/:workspaceId/reports/layer1-snapshots`
- `GET /api/workspaces/:workspaceId/reports/operational-packages`
- `GET /api/entries/:entryId/attachments`

Expected gate effect:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `15`
- unsupported reads: `21`
- unsupported writes: `45`
- cutover remains `false`

Hard stops:

- Any write route routed to Atlas.
- Raw Node Atlas server exposed publicly.
- Secrets or file contents exposed.
- `atlas_shadow` changing browser response.
- Claudia Z baseline drift.
- Superseded operational fragments reappearing.
- Strict cutover gate passing before full GET parity/write/auth/deploy approval.

## Implementation

Files changed:

- `server/findesk-v2-atlas-read-server.js`
- `public/v2-api.php`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Atlas sidecar additions:

- Added `reportSnapshotRow`.
- Added `reportPackageRow`.
- Added `attachmentRow`.
- Added `layer1SummaryReport`.
- Added `layer1-source-entries` id parsing with max `150`, dedupe, invalid-id rejection.
- Added handlers for the five accepted GET routes.

Shadow gateway additions:

- `/v2-api.php` allowlist now includes the five new GET patterns.
- No POST/PATCH/DELETE routes were added to the shadow allowlist.

Smoke/gate additions:

- `scripts/v2_atlas_runtime_smoke.js` now asserts:
  - layer1 ending cash `3893`
  - source entry lookup returns one requested entry
  - snapshots response is an array
  - operational packages response is an array
  - attachments response is an array

- `scripts/v2_atlas_cutover_gate.js` now:
  - counts Atlas read support as `15`
  - asserts layer1 ending cash
  - keeps cutover blocked

## Evidence

Atlas connection:

```bash
npm run check:atlas
```

Result:

- DNS SRV ok.
- TLSv1.3 ok on all three shard hosts.
- Mongo ping ok.

Static checks:

```bash
php -l public/v2-api.php
node --check server/findesk-v2-atlas-read-server.js
node --check scripts/v2_atlas_runtime_smoke.js
node --check scripts/v2_atlas_cutover_gate.js
git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js public/v2-api.php package.json
```

Result:

- All checks passed.

Atlas runtime smoke:

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
  "other_expenses": 3,
  "layer1_ending_cash": 3893,
  "layer1_source_entries": 1,
  "layer1_snapshots": 0,
  "operational_packages": 0,
  "entry_attachments": 0
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
- Atlas read supported: `15`
- unsupported reads: `21`
- unsupported writes: `45`
- blockers remain:
  - `shadow_gateway_available_but_not_cutover`
  - `atlas_write_repository_not_implemented`
  - `full_get_route_parity_not_implemented`
  - `ftp_production_cutover_not_authorized`

Strict gate:

```bash
npm run gate:v2:atlas-cutover:strict
```

Result:

- exits blocked as expected.

## Director Decision

SPRINT-68R is accepted for the five-route Atlas GET parity slice.

Production cutover remains rejected.

The site is still not fully "on Atlas":

- Atlas data is migrated and readable.
- Shadow coverage is broader.
- User-facing runtime remains PHP/MySQL.
- Writes remain PHP/MySQL.
- Full local/production symmetry is not yet accepted.

## Director Final Handoff

Sprint:
SPRINT-68R — Atlas Summary GET Parity Slice

Status:
Accepted for five additional GET routes; not accepted for cutover.

Agents assigned:

- Backend Runtime Agent
- QA Acceptance Agent
- Director as implementation owner

Agent reports received:

- Backend response contract report received.
- QA acceptance report received.

Accepted work:

- Five new Atlas GET handlers.
- Shadow allowlist expansion.
- Runtime smoke expansion.
- Cutover gate count update.

Rejected work:

- Operational package detail route.
- HTML snapshot routes.
- Any Atlas write route.
- Production switch.

Files changed:

- `server/findesk-v2-atlas-read-server.js`
- `public/v2-api.php`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`
- `FinDesk v2.0/sprints/SPRINT-68R-atlas-summary-get-parity-slice.md`

Tests or checks:

- `npm run check:atlas`
- `php -l public/v2-api.php`
- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run gate:v2:atlas-cutover:strict` blocked as expected
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js public/v2-api.php package.json`

Risks:

- `layer1-summary` is calculation-heavy and still needs shadow hash observation against PHP/MySQL before `atlas_read`.
- Current sidecar user id remains smoke-only.
- Report snapshot/package/attachment collections are empty in current Claudia Z baseline, so non-empty evidence still needs future data.

Next sprint:
SPRINT-69R — Atlas Report Storage Detail GET Parity

Paste-to-next-director prompt:

```text
You are the FinDesk v2 Director.
Source of truth is GitHub files.
Read SPRINT-67R and SPRINT-68R.
Atlas GET support is now 15/36 reads. Cutover is still blocked.
Next: add report batches alias/detail, operational fragment HTML snapshot list/get, operational package detail, category-matrix, other-review, then run shadow observations.
Do not switch production and do not add writes.
```
