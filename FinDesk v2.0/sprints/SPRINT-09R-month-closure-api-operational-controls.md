# SPRINT-09R — Month Closure API and Operational Controls

Status: Accepted

## Director Sprint Opening

Sprint:
SPRINT-09R — Month Closure API and Operational Controls

Goal:
Implement the documented month close/reopen/correction API and the smallest operational control needed to close/reopen the current month, without changing financial formulas or making a dashboard-first UI.

Required files read:
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-08R-attachments-base.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`

Agents assigned:
- Financial Logic Engine Agent
- Data and Backend Core Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent

Agent tasks:
- Financial Logic Engine Agent: verify month close/reopen/correction semantics against the calculation contract and define no-formula-change boundaries.
- Data and Backend Core Agent: implement contract routes, repository methods, ownership/write checks, closure state, audit, and test hooks.
- Frontend Performance and Interaction Agent: implement the smallest current-month closure control inside the operational surface without dashboard/report expansion.
- QA, Audit, and Acceptance Agent: extend static, fixture, HTTP, DB, UI, and browser coverage and decide accept/reject.

Expected reports:
- Scope implemented or rejected.
- Files changed.
- Tests/checks run.
- Risks and explicit blockers.
- Accept/reject recommendation.

Exit criteria:
- `POST /api/workspaces/:workspaceId/months/:year/:month/close` exists and closes the month with comment and generated report context.
- `POST /api/workspaces/:workspaceId/months/:year/:month/reopen` exists and reopens the month.
- `POST /api/workspaces/:workspaceId/months/:year/:month/correction` exists and creates an explicit correction entry without silently mutating old entries.
- Operational UI shows current month open/closed state and can close/reopen current month.
- Existing closed-month edit decision workflow remains intact.
- Monthly report exposes closure state/comment consistently.
- Static, fixture, HTTP, DB, UI, and browser smokes pass.
- No old FinDesk product logic is reused.

Risks:
- Accidentally changing monthly formulas while adding closure controls.
- Treating correction as a broad manual editor instead of a strict explicit correction entry.
- Adding dashboard/report UI before the operational journal is complete.
- Reopening closed months without audit.
- Hiding Other review or closed-month decision behavior behind the new controls.

## Director Notes

- This sprint does not implement report snapshots, PDF export, forecasting, bank reconciliation, multi-month dashboard controls, or legacy month logic.
- The operational journal remains the source of truth; reports remain generated from entries.

## Director Final Handoff

Sprint:
SPRINT-09R — Month Closure API and Operational Controls

Status:
Completed / Accepted

Agents assigned:
- Financial Logic Engine Agent
- Data and Backend Core Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent

Agent reports received:
- Financial Logic Engine Agent: ACCEPT. Financial formulas still compute from entries; correction semantics are strict and do not mutate originals.
- Data and Backend Core Agent: ACCEPT. Previous blockers are fixed: writer role is required on core mutations and `updateEntry` blocks target closed-month moves.
- Frontend Performance and Interaction Agent: ACCEPT. Month Open/Closed control remains inside the operational summary strip and browser smoke covers close/reopen behavior.
- QA, Audit, and Acceptance Agent: ACCEPT. Static, fixture, HTTP, DB, UI, and browser coverage all support closure.

Accepted work:
- Added production month routes:
  - `POST /api/workspaces/:workspaceId/months/:year/:month/close`
  - `POST /api/workspaces/:workspaceId/months/:year/:month/reopen`
  - `POST /api/workspaces/:workspaceId/months/:year/:month/correction`
- Added repository methods for close, reopen, and strict correction entries.
- Preserved generated monthly report formulas; closure metadata is not a report snapshot replacement.
- Blocked ordinary create/update/category/delete in closed months.
- Blocked moving an open-month entry into a closed month through `updateEntry`.
- Hardened core mutation paths with writer-role checks.
- Added a minimal operational current-month Open/Closed control in the existing input surface.
- Extended fixture, HTTP, disposable DB, UI marker, and browser smoke coverage.

Rejected work:
- No dashboard-first UI.
- No old FinDesk product logic reuse.
- No formula changes.
- No report snapshots, PDF export, forecasting, bank reconciliation, or multi-month close dashboard.

Files changed:
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_http_api_smoke.sh`
- `scripts/v2_disposable_db_smoke.sh`
- `scripts/v2_operational_ui_smoke.sh`
- `scripts/v2_operational_browser_smoke.cjs`
- `FinDesk v2.0/sprints/SPRINT-09R-month-closure-api-operational-controls.md`
- `FinDesk v2.0/sprints/SPRINT-10R-mvp-release-candidate-gate.md`

Tests or checks:
- `php -l app/v2/Repository.php && php -l app/v2/Api.php && php -l scripts/v2_fixture_runner.php && php -l scripts/v2_http_api_smoke.php`
- `bash -n scripts/v2_http_api_smoke.sh && bash -n scripts/v2_disposable_db_smoke.sh && bash -n scripts/v2_operational_ui_smoke.sh`
- `node --check scripts/v2_operational_browser_smoke.cjs`
- `php scripts/v2_clean_core_static_smoke.php`
- `npm run test:v2:fixtures`
- `npm run smoke:v2:http`
- `bash scripts/v2_disposable_db_smoke.sh`
- Full acceptance command:
  `npm run smoke:v2 && npm run test:v2:fixtures && npm run smoke:v2:http && bash scripts/v2_disposable_db_smoke.sh && npm run smoke:v2:ui && npm run smoke:v2:browser`

Risks:
- Reopen provenance currently relies on audit log rather than dedicated `reopened_by` / `reopened_at` columns.
- Repeated close calls intentionally update close metadata; next sprint may decide whether idempotent close needs stricter semantics.
- MVP still needs a release-candidate gate from a clean checkout/deploy posture before being called done.

Next sprint:
SPRINT-10R — MVP Release Candidate Gate and Deployment Readiness

Paste-to-next-director prompt:
You are the next Director of FinDesk v2.0. Source of truth is only the GitHub files. Start with `FinDesk v2.0/START_HERE_DIRECTOR.md`, then read this SPRINT-09R handoff and `FinDesk v2.0/sprints/SPRINT-10R-mvp-release-candidate-gate.md`. Do not continue from old chat memory. Treat Google Drive Sprint 09-18 as archive context only. Do not add dashboard-first UI, do not change financial formulas, and do not reuse old FinDesk product logic. Run SPRINT-10R as an agent-orchestrated release-candidate gate: verify MVP Definition of Done from clean setup, close remaining deploy/auth/storage/test gaps, and produce the final MVP readiness report.
