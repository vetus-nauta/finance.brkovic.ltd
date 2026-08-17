# SPRINT-69R — Atlas Report Storage Detail GET Parity

Date: 2026-08-13
Status: accepted / seven report-storage GET handlers implemented, cutover still blocked

## Director Sprint Opening

Sprint:
SPRINT-69R — Atlas Report Storage Detail GET Parity

Goal:
Extend the Atlas read sidecar and `/v2-api.php` shadow allowlist from summary-level reads into stored report detail reads, category matrix, and other-review reporting, while keeping all writes blocked and production cutover rejected.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-68R-atlas-summary-get-parity-slice.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `server/findesk-v2-atlas-read-server.js`
- `public/v2-api.php`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Agents assigned:

- Backend Runtime Agent
- QA Acceptance Agent
- Director as implementation owner

Agent tasks:

- Backend Runtime Agent: inspect PHP response contracts, row shapes, and backing tables for target GET routes.
- QA Acceptance Agent: define route acceptance checks, hard stops, fixture caveats, and expected cutover gate count changes.
- Director: implement Atlas handlers, extend shadow allowlist, update smoke/gate, and decide sprint acceptance.

Exit criteria:

- Seven target GET handlers exist in Atlas sidecar.
- PHP shadow allowlist includes only matching GET routes, no writes.
- Runtime smoke asserts batches, batch detail, HTML snapshot list/detail, category matrix, other-review, and package detail domain behavior.
- Cutover gate increases supported Atlas reads from `15` to `22`.
- Strict cutover gate remains blocked.

## Agent Reports Received

### Backend Runtime Agent

Accepted route contracts:

- `GET /api/workspaces/:workspaceId/reports/batches`
  - returns `{ ok: true, reports }`
  - alias of operational fragments list
  - excludes `status = superseded`
  - clamps `limit` to `1..100`

- `GET /api/workspaces/:workspaceId/reports/batches/:batchId`
  - returns `{ ok: true, report }`
  - alias of operational fragment detail

- `GET /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots`
  - returns `{ ok: true, snapshots }`
  - validates fragment first
  - list rows do not include `html_content`

- `GET /api/workspaces/:workspaceId/reports/operational-fragments/:fragmentId/html-snapshots/:snapshotId`
  - returns `{ ok: true, snapshot }`
  - detail includes `html_content`
  - missing row returns `report_html_snapshot_not_found`

- `GET /api/workspaces/:workspaceId/reports/operational-packages/:packageId`
  - returns `{ ok: true, package }`
  - detail includes `items`, `fragments`, and `versions`

- `GET /api/workspaces/:workspaceId/reports/category-matrix`
  - returns `{ ok: true, matrix }`
  - rows seeded from active global/workspace categories
  - entries aggregated by month, flow type, direction, category

- `GET /api/workspaces/:workspaceId/reports/other-review`
  - returns `{ ok: true, report }`
  - mirrors `listOtherExpenseQueue()`
  - filters `status = other_review`, `entry_type = cash_expense`, category `other`

### QA Acceptance Agent

Expected gate effect:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `15 -> 22`
- unsupported reads: `21 -> 14`
- unsupported writes: `45`
- cutover remains `false`

Hard stops:

- Any write route routed to Atlas.
- Raw Node Atlas server exposed publicly.
- Secrets or Atlas URI exposed.
- `atlas_shadow` changing browser/API response instead of only comparing/logging.
- Claudia Z baseline drift.
- HTML snapshot list leaking `html_content`.
- HTML snapshot detail omitting `html_content`.
- Superseded operational fragments reappearing.
- Strict cutover gate passing before full GET parity/write/auth/deploy approval.

Fixture caveat:

- `v2_report_packages` currently has `0` rows. The package-detail route is implemented and returns domain 404 for missing package ids, but positive detail acceptance requires a future real package fixture.

## Implementation

Files changed:

- `server/findesk-v2-atlas-read-server.js`
- `public/v2-api.php`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Atlas sidecar additions:

- Added `reportBatchHtmlSnapshotRow`.
- Added `reportVersionRow`.
- Added package detail helpers:
  - `reportPackageItemRows`
  - `reportVersions`
  - `reportPackageDetail`
- Added `categoryMatrixReport`.
- Added handlers for the seven target GET routes.

Shadow gateway additions:

- `/v2-api.php` allowlist now includes the seven new GET route patterns.
- No POST/PATCH/DELETE routes were added to the shadow allowlist.

Smoke/gate additions:

- Runtime smoke now asserts:
  - report batches alias count
  - batch detail content hash
  - HTML snapshot list/detail behavior
  - category matrix `22` category rows
  - other-review count `3` and total `672.00`
  - package detail positive fixture when available, otherwise domain 404

- Cutover gate now:
  - counts Atlas read support as `22`
  - reports unsupported reads as `14`
  - keeps strict cutover blocked

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
git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js public/v2-api.php
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
  "report_batches": 1,
  "html_snapshots": 1,
  "layer1_ending_cash": 3893,
  "layer1_source_entries": 1,
  "layer1_snapshots": 0,
  "operational_packages": 0,
  "package_detail_positive_fixture": false,
  "category_matrix_rows": 22,
  "other_review_report_entries": 3,
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
- Atlas read supported: `22`
- unsupported reads: `14`
- unsupported writes: `45`

Strict cutover gate:

```bash
npm run gate:v2:atlas-cutover:strict
```

Result:

- exited with code `2`
- blocked as expected

## Director Acceptance

Accepted:

- Atlas read sidecar now covers the report-storage/detail GET slice.
- Shadow gateway remains read-only and response-passive.
- Claudia Z money baseline remains stable:
  - entries `279`
  - August entries `39`
  - cash now `3893.00`
  - August ending cash `3893.00`
  - other-review `3` entries / `672.00`
- Cutover remains blocked.

Not accepted for production cutover:

- Writes are still not implemented in Atlas runtime.
- `14` GET routes are still unsupported.
- `45` write routes are still unsupported.
- Package detail still lacks a positive real-data fixture because no packages exist in Atlas.
- FTP production cutover has not been explicitly authorized in this sprint.

## Next Sprint Recommendation

SPRINT-70R should cover the remaining hall/accountable/training read routes or create a controlled report-package fixture path before write migration.

Recommended target:

- `GET /api/workspaces/:workspaceId/invites`
- `GET /api/workspaces/:workspaceId/employee-mode`
- accountable read routes
- package-detail positive acceptance once at least one operational package exists
