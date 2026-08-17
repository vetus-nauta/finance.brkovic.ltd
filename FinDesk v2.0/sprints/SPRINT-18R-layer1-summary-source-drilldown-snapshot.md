# SPRINT-18R — Layer 1 Summary Source Drilldown And Snapshot Foundation

Director: Codex Director, FinDesk v2.0

Status: Open / awaiting agent reports

## Director Sprint Opening

Sprint:

```text
SPRINT-18R — Layer 1 Summary Source Drilldown And Snapshot Foundation
```

Goal:

- Continue from the accepted SPRINT-17R first slice.
- Make Layer 1 Summary source drilldown reliable across current-period and prior-period source entries.
- Add the foundation for stored report snapshots without turning snapshots into editable independent financial truth.
- Preserve the operational journal as source of truth.
- Keep Forecast, final Sending, final Printing, and full Storage acceptance blocked unless explicitly opened by this sprint's exit criteria.

Required files read:

- `FinDesk v2.0/START_HERE_DIRECTOR.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/36-layer1-summary-and-forecast.md`
- `FinDesk v2.0/sprints/SPRINT-17R-layer1-summary-screen-opening.md`
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/09-operational-and-summary-table-contract.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_operational_ui_smoke.sh`

Agents assigned:

- Financial Logic Engine Agent
- Data and Backend Core Agent
- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

```text
Agent:
Financial Logic Engine Agent
Scope:
Financial boundaries for source drilldown and stored report snapshots.
Files to read:
36-layer1-summary-and-forecast.md, SPRINT-17R-layer1-summary-screen-opening.md, 09-operational-and-summary-table-contract.md, 14-calculation-contract.md, app/v2/Repository.php, scripts/v2_http_api_smoke.php.
What to check:
Whether source-entry drilldown and snapshots preserve operational-entry truth, closed-period correction logic, cash/card separation, other/review visibility, and fact/forecast separation.
What to change if allowed:
No code changes in opening review.
What not to touch:
Parser, formulas, import behavior, deploy behavior, old FinDesk dashboard/report logic.
Report required:
ACCEPT/BLOCK, financial boundaries, snapshot rules, closed-period risks, required tests.
```

```text
Agent:
Data and Backend Core Agent
Scope:
API/schema foundation for source-entry fetch and stored report snapshots.
Files to read:
36-layer1-summary-and-forecast.md, SPRINT-17R-layer1-summary-screen-opening.md, 16-api-contract.md, app/v2/Api.php, app/v2/Repository.php, FinDesk v2.0/sql/001-clean-core-mariadb.sql, scripts/v2_http_api_smoke.php, scripts/v2_disposable_db_smoke.sh.
What to check:
Safe endpoint shape, source-entry ids across periods, snapshot schema/API, migrations, RBAC/CSRF constraints.
What to change if allowed:
No code changes in opening review.
What not to touch:
Production DB, parser, import, forecast variables, final print/send export.
Report required:
ACCEPT/BLOCK, proposed first backend slice, storage model, test plan.
```

```text
Agent:
iOS-Native UX Layout Agent / Frontend Performance and Interaction Agent
Scope:
UX for source drilldown and first Storage snapshot surface using existing Summary style.
Files to read:
36-layer1-summary-and-forecast.md, SPRINT-17R-layer1-summary-screen-opening.md, 04-responsive-layout-contract.md, public/v2.php, public/assets/v2/app.css, public/assets/v2/app.js, scripts/v2_operational_browser_smoke.cjs.
What to check:
Cross-period drilldown behavior, source overlay states, Summary-specific responsive requirements, first Storage tab UX, pending-state boundaries.
What to change if allowed:
No code changes in opening review.
What not to touch:
Decorative dashboard UI, body/page scroll, money entry behavior, operational row/edit regressions.
Report required:
ACCEPT/BLOCK, UX behavior, responsive evidence requirements, blocked UI scope.
```

```text
Agent:
QA, Audit, and Acceptance Agent
Scope:
Acceptance gates for source drilldown and snapshot foundation.
Files to read:
36-layer1-summary-and-forecast.md, SPRINT-17R-layer1-summary-screen-opening.md, 20-definition-of-done.md, 33-director-agent-orchestration-protocol.md, scripts/v2_http_api_smoke.php, scripts/v2_operational_browser_smoke.cjs, scripts/v2_operational_ui_smoke.sh, package.json.
What to check:
Required API/browser/static evidence, security checks, regression risks, what must remain blocked.
What to change if allowed:
No code changes in opening review.
What not to touch:
Acceptance without screenshots/metrics/source-id/snapshot evidence.
Report required:
ACCEPT/BLOCK, mandatory gates, smoke additions, residual blockers.
```

Expected reports:

- ACCEPT/BLOCK for opening implementation.
- Source-entry drilldown boundaries and endpoint shape.
- Snapshot storage rules and schema/API proposal.
- UX behavior for cross-period source trace and Storage first slice.
- QA evidence plan.

Exit criteria:

- All assigned agent reports are received.
- Source-entry drilldown works for every source id returned by Layer 1 summary, including prior-period opening cash ids.
- Source drilldown returns full operational entries from the backend, not client-side guessing from current month state.
- Stored report snapshot foundation exists and stores generated report numbers plus source entry ids without allowing direct final-number editing.
- Closed period does not change silently.
- Existing formulas, parser, imports, attachments, auth, deploy behavior, and operational UI stay green.
- Summary-specific browser evidence exists for desktop, phone, iPad mini, and iPad 11, or full Summary acceptance remains blocked.

Risks:

- Treating stored snapshots as editable accounting truth instead of generated audit snapshots.
- Fetching source entries only from current-month client state and losing opening_cash provenance.
- Expanding Storage into archive management before snapshot foundation is proven.
- Accidentally opening Forecast/Sending/Printing final behavior too early.
- Breaking operational journal layout while extending Summary.

## Agent Reports

Financial Logic Engine Agent:

- Decision: ACCEPT opening, BLOCK full acceptance.
- Boundaries confirmed: operational entries remain source of truth; `opening_cash`, cash/card, commercial, other review, corrections, and top-up sides must stay separated.
- Snapshot rule: stored snapshot may persist generated numbers, source IDs, correction IDs, attachment refs, comments, timestamps, and optional forecast snapshot later, but cannot become directly editable financial truth.
- Key risk: opening cash also has a non-entry opening-balance basis, so entry drilldown alone is not the complete explanation.

Data and Backend Core Agent:

- Decision: BLOCK full sprint, ACCEPT narrow backend foundation.
- Required first slice: `GET layer1-source-entries`, workspace-scoped UUID validation, max-id cap, request-order preservation, `missing_ids`, viewer read allowed, writer-only snapshot save with CSRF and audit.
- Storage model: additive immutable/versioned `v2_report_snapshots`; do not overload `v2_monthly_closures`.

iOS-Native UX Layout Agent / Frontend Performance and Interaction Agent:

- Decision: BLOCK full acceptance.
- Current UI gap: source overlay resolves only current `state.entries`, so prior-period opening cash source IDs degrade into placeholders.
- Required UX: source overlay must fetch exact source entries from backend; Summary responsive evidence across phone/iPad remains required before full acceptance.

QA, Audit, and Acceptance Agent:

- Decision: BLOCK full acceptance.
- Required evidence: every displayed traced total/category must open exact source rows; cross-period drilldown must work; snapshot writes must be authenticated, CSRF-protected, workspace-scoped, audited, and smoke-tested.
- Full Summary acceptance remains blocked without phone/iPad/desktop Summary screenshots and Storage/snapshot state evidence.

## Director Implementation Slice

Decision:

- Proceed with a narrow implementation slice.
- Do not open Forecast, Sending, Printing, or final Storage archive behavior.
- Keep full SPRINT-18R acceptance blocked until the remaining evidence exists.

Implementation scope:

- Add read-only source-entry drilldown endpoint for Layer 1 source traces.
- Add immutable/versioned Layer 1 snapshot table and API foundation.
- Update Summary source overlay to fetch missing source entries from the backend rather than fabricating placeholders.
- Extend HTTP/browser smoke coverage for prior-period opening cash drilldown, workspace scoping, viewer read, writer-only snapshot save, CSRF, audit, and snapshot readback.

Known residual blocker:

- `opening_cash` still needs a non-entry source basis row for the flow opening balance itself. This slice proves all source entry IDs can be fetched across periods; it does not yet make opening balance basis fully explainable.

## Implementation Evidence

Files changed in this slice:

- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

Implemented:

- `GET /api/workspaces/:workspaceId/reports/layer1-source-entries?ids=...`
- `GET /api/workspaces/:workspaceId/reports/layer1-snapshots`
- `POST /api/workspaces/:workspaceId/reports/layer1-snapshots`
- Additive `v2_report_snapshots` table.
- Backend snapshot creation from canonical Layer 1 report payload.
- Frontend source overlay fetches missing source entries from backend and no longer invents placeholder rows for unresolved IDs.

Evidence:

```text
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l public/v2-api.php
php -l scripts/v2_http_api_smoke.php
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2:http
npm run smoke:v2:browser
npm run smoke:v2 && npm run smoke:v2:auth && npm run test:v2:fixtures && npm run smoke:v2:http && npm run smoke:v2:db && npm run smoke:v2:ui && npm run smoke:v2:browser && npm run smoke:v2:manual-responsive && npm run smoke:v2:deploy
```

Result:

- PASS.
- Deploy preflight warning remains: `FINDESK_V2_PRODUCTION_BASE_URL` is not set, so live HTTP deny checks were skipped.

Screenshots / metrics:

- `test-results/v2-browser-smoke/desktop-layer1-summary-information.png`
- `test-results/v2-browser-smoke/desktop-layer1-summary-source-trace.png`
- `test-results/v2-browser-smoke/layout-metrics.json`
- `test-results/v2-manual-responsive/manual-responsive-report.json`
- `test-results/v2-manual-responsive/`

Director acceptance:

- ACCEPT SPRINT-18R narrow implementation slice.
- BLOCK full Layer 1 Summary acceptance.
- BLOCK final Storage, Sending, Printing, Forecast acceptance.

Remaining required work:

- Add explicit non-entry basis trace for cash flow opening balance.
- Add Summary-specific screenshots and source drilldown checks across phone, iPad mini, iPad 11 portrait/landscape.
- Add Storage UI readback for saved snapshots.
- Add snapshot immutability/revision tests after closed-period correction/recalculation.
- Update API contract documentation for Layer 1 routes.
