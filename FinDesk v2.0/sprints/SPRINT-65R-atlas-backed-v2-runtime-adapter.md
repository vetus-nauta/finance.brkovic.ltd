# SPRINT-65R — Atlas-backed v2 Runtime Adapter

Date: 2026-08-13
Status: accepted / first read-only Atlas runtime slice accepted locally

## Director Sprint Opening

Sprint:
SPRINT-65R — Atlas-backed v2 Runtime Adapter

Goal:
Create the first controlled FinDesk v2 runtime path that reads canonical v2 data from MongoDB Atlas, without switching production and without allowing writes through the new adapter.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-64R-atlas-backup-and-commit-tool-gate.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/assets/v2/app.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `server/findesk-v2-atlas-read-server.js`

Agents assigned:

- Backend Runtime Agent
- QA / Compatibility Agent
- Director as integration owner

Agent tasks:

- Backend Runtime Agent: map current PHP/MySQL v2 API routes and propose the minimal Atlas-backed runtime adapter path.
- QA / Compatibility Agent: define acceptance checks for Atlas read parity, Claudia Z arithmetic, report-fragment visibility, and write safety.
- Director: implement the controlled read-only runtime slice, run smoke checks, and document the remaining cutover boundary.

Expected reports:

- Runtime architecture recommendation.
- Required first-slice routes.
- QA acceptance list and hard stops.
- Local evidence from executable checks.

Exit criteria:

- Atlas-backed v2 read server exists.
- Server does not expose secrets.
- Server rejects non-GET writes.
- Workspace, flow, category, entries, summary, monthly report, other-review queue, and operational report fragment reads work for Claudia Z.
- Claudia Z cash and August monthly arithmetic match committed Atlas data.
- Active operational report fragments are visible without resurrecting superseded report versions.
- Production remains on current PHP/MySQL runtime until a separate write/cutover sprint.

Risks:

- This is not yet the final production runtime.
- Write routes are intentionally blocked.
- Browser UI is not yet routed to this adapter.
- If Atlas Network Access changes, local smoke can fail even when code is valid.
- Current PHP/MySQL remains the operational source for live edits until cutover.

## Agent Reports Received

### Backend Runtime Agent

Recommendation:

- Build a parallel Node Atlas API adapter first.
- Keep PHP/MySQL production runtime active until read/write parity is proven.
- Do not add PHP Mongo dependencies or replace repository internals in this sprint.

Mapped runtime:

- Browser calls `public/v2-api.php`.
- PHP route layer is `app/v2/Api.php`.
- Current repository is `app/v2/Repository.php` over PDO/MySQL.

First read-only slice:

- `GET /api/workspaces`
- `GET /api/workspaces/:id`
- `GET /api/workspaces/:id/flows`
- `GET /api/workspaces/:id/categories`
- `GET /api/workspaces/:id/entries`
- `GET /api/workspaces/:id/summary`
- `GET /api/workspaces/:id/reports/monthly`
- `GET /api/workspaces/:id/other-expenses`
- `GET /api/workspaces/:id/reports/operational-fragments`
- `GET /api/workspaces/:id/reports/operational-fragments/:fragmentId`

### QA / Compatibility Agent

Baseline from accepted Atlas payload:

- MySQL and Atlas parity mismatch count: `0`.
- Claudia Z active entries: `279`.
- Claudia Z August entries: `39`.
- Claudia Z cash opening: `8015.00`.
- Claudia Z current cash: `3893.00`.
- Claudia Z report batches: `5`, with only non-superseded batches visible in the normal API.

Hard stops:

- No production switch in this sprint.
- No write route through Atlas read server.
- No exposure of Mongo URI.
- No financial formula changes.
- No acceptance without Claudia Z arithmetic smoke.

## Implementation

Files added:

- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_runtime_smoke.js`

Files updated:

- `package.json`

New scripts:

```bash
npm run start:v2:atlas-read
npm run smoke:v2:atlas-runtime
```

Runtime behavior:

- Reads Mongo URI from `storage/secrets/mongodb_uri` or `FINDESK_MONGO_URI`.
- Uses database `finance_brkovic_ltd` by default.
- Binds local server to `127.0.0.1:18965`.
- Exposes read-only v2-compatible JSON responses.
- Rejects all non-GET requests with `atlas_read_server_is_read_only`.

## Evidence

Atlas connection:

```bash
npm run check:atlas
```

Result:

- DNS SRV ok.
- TLSv1.3 ok on all three Atlas shard hosts.
- Mongo ping ok.

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

Static checks:

```bash
node --check server/findesk-v2-atlas-read-server.js
node --check scripts/v2_atlas_runtime_smoke.js
git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js package.json
```

Result:

- All checks passed.

## Director Decision

SPRINT-65R first local slice is accepted as a read-only Atlas runtime adapter.

This does not authorize production cutover.

Current accepted boundary:

- Atlas contains canonical v2 data.
- A local read-only Atlas API slice can reproduce key Claudia Z workspace data.
- PHP/MySQL remains the active editable runtime.
- Next sprint must decide and implement the write/cutover path with rollback, browser QA, and production symmetry checks.

## Director Final Handoff

Sprint:
SPRINT-65R — Atlas-backed v2 Runtime Adapter

Status:
Accepted for local read-only runtime slice; not accepted for production cutover.

Agents assigned:

- Backend Runtime Agent
- QA / Compatibility Agent
- Director as integration owner

Agent reports received:

- Runtime architecture report received.
- QA acceptance report received.

Accepted work:

- Node Atlas read server.
- Runtime smoke script.
- Read-only guard.
- Claudia Z Atlas arithmetic evidence.

Rejected work:

- Production switching.
- Atlas write routes.
- Browser UI reroute to Atlas without a separate gate.

Files changed:

- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `package.json`
- `FinDesk v2.0/sprints/SPRINT-65R-atlas-backed-v2-runtime-adapter.md`

Tests or checks:

- `npm run check:atlas`
- `npm run smoke:v2:atlas-runtime`
- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js package.json`

Risks:

- Atlas-backed writes are not implemented.
- Browser still uses PHP/MySQL endpoint.
- A full API parity runner is still needed before cutover.
- Production deployment still needs FTP/server gate.

Next sprint:
SPRINT-66R — Atlas Runtime Cutover Plan and Write Gate

Paste-to-next-director prompt:

```text
You are the FinDesk v2 Director.
Source of truth is GitHub files, not chat memory.
Read SPRINT-64R and SPRINT-65R.
Atlas has canonical v2 data with parity mismatch_count=0.
SPRINT-65R added a local read-only Atlas API adapter and smoke evidence.
Do not switch production yet.
Next: design Atlas write runtime/cutover gate with rollback, browser QA, and full local/deploy symmetry.
```
