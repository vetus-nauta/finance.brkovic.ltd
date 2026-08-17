# SPRINT-67R — Atlas Shadow Runtime Gateway

Date: 2026-08-13
Status: accepted / shadow gateway foundation only

## Director Sprint Opening

Sprint:
SPRINT-67R — Atlas Shadow Runtime Gateway

Goal:
Add a safe `atlas_shadow` foundation behind the existing `/v2-api.php` browser endpoint, so Atlas GET parity can be observed without changing the user-facing PHP/MySQL response and without exposing the Node Atlas sidecar publicly.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-65R-atlas-backed-v2-runtime-adapter.md`
- `FinDesk v2.0/sprints/SPRINT-66R-atlas-runtime-cutover-plan-and-write-gate.md`
- `public/v2-api.php`
- `app/v2/Api.php`
- `public/assets/v2/app.js`
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_cutover_gate.js`

Agents assigned:

- Backend Runtime Agent
- QA Acceptance Agent
- Director as gateway integration owner

Agent tasks:

- Backend Runtime Agent: define the safest `atlas_shadow` / future `atlas_read` routing shape, env flags, timeout behavior, and no-secret logging rules.
- QA Acceptance Agent: define smallest valuable next GET parity routes, acceptance checks, and hard stops.
- Director: implement the disabled-by-default shadow gateway hook and update gate evidence.

Expected reports:

- Runtime mode recommendation.
- Shadow logging contract.
- Timeout and sidecar safety requirements.
- GET expansion priority.
- Acceptance commands and hard stops.

Exit criteria:

- `/v2-api.php` remains the only browser endpoint.
- Default runtime remains PHP/MySQL.
- `atlas_shadow` returns the MySQL response to the user.
- Shadow is GET-only and allowlisted.
- Shadow logs digests and route templates only, not secrets or payloads.
- Cutover remains blocked.

Risks:

- Shadow comparison is not yet zero-diff evidence for new routes.
- Node sidecar still uses fixed local user id for smoke only.
- `atlas_read` is not implemented.
- All writes still belong to PHP/MySQL.

## Agent Reports Received

### Backend Runtime Agent

Recommendation:

- Keep `/v2-api.php` as the only browser endpoint.
- Do not expose `server/findesk-v2-atlas-read-server.js` publicly.
- Add PHP runtime routing behind `/v2-api.php`.
- Use modes:
  - `FINDESK_V2_RUNTIME=mysql`
  - `FINDESK_V2_RUNTIME=atlas_shadow`
  - future `FINDESK_V2_RUNTIME=atlas_read`

Shadow behavior:

- Always return PHP/MySQL payload to the browser.
- Atlas failures, invalid JSON, timeouts, and mismatches are telemetry only.
- Log JSON lines only.
- Do not log Mongo URI, cookies, invite tokens, request bodies, raw entries, filenames, full payloads, or stack traces.

Recommended future env flags:

```bash
FINDESK_V2_RUNTIME=mysql|atlas_shadow|atlas_read
FINDESK_V2_ATLAS_READ_URL=http://127.0.0.1:18965
FINDESK_V2_ATLAS_SHADOW_TIMEOUT_MS=300
FINDESK_V2_ATLAS_READ_TIMEOUT_MS=1200
FINDESK_V2_ATLAS_LOG_FILE=storage/logs/v2-atlas-routing.jsonl
```

Backend blocker:

- Before real routing, PHP must pass authenticated user context to the sidecar over trusted loopback; fixed local `USER_ID` is only acceptable for smoke.

### QA Acceptance Agent

Smallest next GET parity additions:

1. `GET /api/workspaces/:workspaceId/reports/layer1-summary`
2. `GET /api/workspaces/:workspaceId/reports/layer1-source-entries`
3. `GET /api/workspaces/:workspaceId/reports/layer1-snapshots`
4. `GET /api/workspaces/:workspaceId/reports/operational-packages`
5. `GET /api/entries/:entryId/attachments`

Reason:

- These cover visible Summary/storage/source-trace/detail-read gaps.
- They avoid writes, invite tokens, and role-heavy accountable workflows.

Hard stops:

- Any non-GET route sent to Atlas.
- Raw Node Atlas server exposed publicly.
- Mongo URI in logs, browser response, HTTP error, or frontend bundle.
- Claudia Z baseline drift without intentional data refresh.
- Superseded report fragments reappearing in normal reads.
- `gate:v2:atlas-cutover:strict` passing before write runtime exists.

## Implementation

Files changed:

- `public/v2-api.php`
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_cutover_gate.js`

Behavior added:

- `public/v2-api.php` now supports disabled-by-default shadow mode:

```bash
FINDESK_V2_RUNTIME=atlas_shadow
FINDESK_V2_ATLAS_READ_URL=http://127.0.0.1:18965
FINDESK_V2_ATLAS_SHADOW_TIMEOUT_MS=300
FINDESK_V2_ATLAS_LOG_FILE=storage/logs/v2-atlas-routing.jsonl
```

- Shadow calls only the SPRINT-65 allowlisted GET routes.
- Browser still receives the PHP/MySQL response.
- Shadow logs only:
  - mode
  - method
  - route template
  - workspace hash
  - query key names
  - match flag
  - short response digests
  - duration
  - generic error code

Sidecar hardening:

- Atlas read server connection timeouts are now env-configurable:
  - `FINDESK_V2_ATLAS_SERVER_SELECTION_TIMEOUT_MS`
  - `FINDESK_V2_ATLAS_CONNECT_TIMEOUT_MS`

Gate update:

- `scripts/v2_atlas_cutover_gate.js` now asserts that the shadow gateway hook exists.
- Gate still blocks cutover.

## Evidence

Syntax and static checks:

```bash
php -l public/v2-api.php
node --check server/findesk-v2-atlas-read-server.js
node --check scripts/v2_atlas_cutover_gate.js
git diff --check -- public/v2-api.php server/findesk-v2-atlas-read-server.js scripts/v2_atlas_cutover_gate.js package.json
```

Result:

- All checks passed.
- Local PHP version checked: `8.3.6`.

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
  "other_expenses": 3
}
```

Cutover gate:

```bash
npm run gate:v2:atlas-cutover
```

Result:

- `cutover_allowed`: `false`
- `shadow_gateway_available`: `true`
- route surface: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `10`
- unsupported reads: `26`
- unsupported writes: `45`
- blockers:
  - `shadow_gateway_available_but_not_cutover`
  - `atlas_write_repository_not_implemented`
  - `full_get_route_parity_not_implemented`
  - `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-67R is accepted as the shadow gateway foundation.

Production cutover remains rejected.

The product is now safer than before:

- We can enable `atlas_shadow` locally or in a controlled environment.
- User-facing responses still come from PHP/MySQL.
- Atlas comparison is telemetry only.
- There is no route for accidental writes into Atlas runtime.

## Director Final Handoff

Sprint:
SPRINT-67R — Atlas Shadow Runtime Gateway

Status:
Accepted as shadow foundation; not accepted for `atlas_read` or write cutover.

Agents assigned:

- Backend Runtime Agent
- QA Acceptance Agent
- Director as gateway integration owner

Agent reports received:

- Runtime architecture report received.
- QA acceptance report received.

Accepted work:

- Disabled-by-default shadow hook behind `/v2-api.php`.
- No-secret digest logging contract.
- Short Atlas shadow timeout.
- Sidecar timeout envs.
- Cutover gate recognizes shadow availability.

Rejected work:

- `atlas_read` mode.
- Any write path to Atlas.
- Public exposure of Node Atlas sidecar.
- New complex report GET route rewrites before shadow logging is observed.

Files changed:

- `public/v2-api.php`
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_cutover_gate.js`
- `FinDesk v2.0/sprints/SPRINT-67R-atlas-shadow-runtime-gateway.md`

Tests or checks:

- `php -l public/v2-api.php`
- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `git diff --check -- public/v2-api.php server/findesk-v2-atlas-read-server.js scripts/v2_atlas_cutover_gate.js package.json`

Risks:

- Shadow hook currently runs before `ql_json`, so a shadow call can add up to the configured timeout to supported GET routes when enabled.
- Fixed local sidecar user id remains unsuitable for production routing.
- Shadow mismatch logs are digest-level; detailed diff tooling is still needed.

Next sprint:
SPRINT-68R — Atlas Summary GET Parity Slice

Paste-to-next-director prompt:

```text
You are the FinDesk v2 Director.
Source of truth is GitHub files.
Read SPRINT-65R, SPRINT-66R, and SPRINT-67R.
Atlas has canonical v2 data and a local read-only adapter.
SPRINT-67R added disabled-by-default atlas_shadow behind /v2-api.php.
Do not switch production.
Next: add first new GET parity slice for layer1-summary, layer1-source-entries, layer1-snapshots, operational-packages, and entry attachments, then observe atlas_shadow logs.
```
