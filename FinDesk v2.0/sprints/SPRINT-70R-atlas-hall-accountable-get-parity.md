# SPRINT-70R — Atlas Hall and Accountable GET Parity

Date: 2026-08-13
Status: accepted / Hall, employee, accountable read routes covered; cutover still blocked

## Director Sprint Opening

Sprint:
SPRINT-70R — Atlas Hall and Accountable GET Parity

Goal:
Extend the Atlas read sidecar and `/v2-api.php` shadow allowlist into the Hall / employee / accountable read model, without enabling writes or production cutover.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-69R-atlas-report-storage-detail-get-parity.md`
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

- Backend Runtime Agent: inspect remaining unsupported GET routes and recommend the next safe slice.
- QA Acceptance Agent: define acceptance checks and hard stops for Hall / employee / accountable GET parity.
- Director: choose implementation slice, implement handlers, update smokes/gate, and record decision.

## Director Decision

Backend Runtime Agent recommended taking training/import reads first because those routes have lower role and invite-token risk.

Director accepted the risk but chose Hall / accountable for SPRINT-70R because the current product priority is the role model:

- Hall workspace visibility.
- Employee simple mode.
- Admin-issued accountable offers.
- Employee reports.
- Admin review/materialization read model.

Mitigation:

- Read-only only.
- No write routes added.
- No raw invite tokens or token hashes exposed.
- Accountable tests run on an active synthetic `тест 12.08.26` fixture, not Claudia Z production workspace.
- Claudia Z remains financial baseline only.

## Routes Implemented

Atlas sidecar now supports:

- `GET /api/workspaces/:workspaceId/invites`
- `GET /api/workspaces/:workspaceId/employee-mode`
- `GET /api/workspaces/:workspaceId/accountable-dashboard`
- `GET /api/workspaces/:workspaceId/accountable-offers`
- `GET /api/workspaces/:workspaceId/accountable-reports`
- `GET /api/accountable-reports/:reportId`
- `GET /api/accountable-reports/:reportId/materialization`
- `GET /api/workspace-invites/:token`

## Agent Reports Received

### Backend Runtime Agent

Remaining unsupported GET routes after SPRINT-69:

- Hall/accountable:
  - `/invites`
  - `/employee-mode`
  - `/accountable-dashboard`
  - `/accountable-offers`
  - `/accountable-reports`
  - `/accountable-reports/:reportId`
  - `/accountable-reports/:reportId/materialization`
  - `/workspace-invites/:token`

- Training/import:
  - `/dictionary-review-queue`
  - `/raw-history`
  - `/dictionary-training-decisions`
  - `/assistant-settings`
  - `/dictionary-training-internet-reference/lookups`
  - `/imports/:importId/review`

Recommendation:

- Prefer training/import first for lower risk.
- Hall/accountable requires role-aware fixtures and invite-token discipline.

### QA Acceptance Agent

Expected gate effect if eight Hall/accountable GET routes are added:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `22 -> 30`
- unsupported reads: `14 -> 6`
- unsupported writes: `45`
- cutover remains `false`

Hard stops:

- Any POST/PATCH/DELETE route marked Atlas-supported.
- Raw invite token, token hash, Atlas URI, credential, or secret logged/exposed.
- Shadow gateway changing browser response instead of only comparing/logging.
- Accountable report detail omitting `rows`.
- Settlement/materialization silently faked as empty when records exist.
- Strict cutover gate passing.

Fixture caveat:

- Positive `/workspace-invites/:token` cannot be accepted from migrated Atlas data because raw tokens are not stored, only hashes/hints.
- Current sprint only verifies negative domain behavior for an unknown 48-hex token.

## Implementation

Files changed:

- `server/findesk-v2-atlas-read-server.js`
- `public/v2-api.php`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Atlas sidecar additions:

- Role/access helpers:
  - `workspaceAccessFromMember`
  - `workspaceAccess`
  - `requireWorkspaceAdmin`
  - `userEmail`

- Row mappers:
  - `workspaceInviteRow`
  - `accountableOfferRow`
  - `accountableReportRow`
  - `accountableReportDataRow`
  - `accountableSettlementRow`
  - `accountableDashboardOfferRow`
  - `accountableDashboardReportRow`

- Accountable helpers:
  - `listAccountableOffers`
  - `listAccountableReports`
  - `accountableReportDetail`
  - `accountableReportEntryLinks`
  - `accountableMaterializationResult`
  - `accountableDashboard`

Shadow gateway additions:

- `/v2-api.php` allowlist now includes the eight new GET route patterns.
- No POST/PATCH/DELETE routes were added to shadow.

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
  "accountable_invites": 3,
  "accountable_offers": 3,
  "accountable_reports": 3,
  "accountable_materialization_links": 1,
  "invite_token_positive_fixture": false,
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
- Atlas read supported: `30`
- unsupported reads: `6`
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

- Hall/accountable GET read slice is implemented in Atlas sidecar.
- Invite list does not expose raw token or token hash.
- Admin `employee-mode` correctly returns `employee_mode_not_required`, not fake employee data.
- Accountable dashboard sees the active fixture:
  - `3` invites
  - `3` offers
  - `3` reports
  - `3` employees
  - materialization link count `1` for sampled report
- Claudia Z baseline remains stable:
  - entries `279`
  - August entries `39`
  - cash now `3893.00`
  - August ending cash `3893.00`

Not accepted for production cutover:

- `6` GET routes remain unsupported.
- `45` write routes remain unsupported.
- Positive invite-token preview requires a fresh invite token from the write path.
- Employee-mode positive read requires running the sidecar under an employee user fixture.
- FTP production cutover has not been authorized in this sprint.

## Remaining GET Routes

Next read-only sprint should cover:

- `GET /api/workspaces/:workspaceId/dictionary-review-queue`
- `GET /api/workspaces/:workspaceId/raw-history`
- `GET /api/workspaces/:workspaceId/dictionary-training-decisions`
- `GET /api/workspaces/:workspaceId/assistant-settings`
- `GET /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups`
- `GET /api/workspaces/:workspaceId/imports/:importId/review`
