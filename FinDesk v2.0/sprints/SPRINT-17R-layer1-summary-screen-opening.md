# SPRINT-17R — Layer 1 Summary Screen Opening

Director: Codex Director, FinDesk v2.0

Status: First implementation slice accepted / full sprint acceptance blocked

## Director Sprint Opening

Sprint:

```text
SPRINT-17R — Layer 1 Summary Screen
```

Goal:

- Start the second Layer 1 screen defined by `36-layer1-summary-and-forecast.md`.
- Keep the first screen as the operational input window.
- Build the summary screen as a generated period summary based only on operational entries.
- Preserve one visual and interaction system with the operational screen.
- Deliver the first safe implementation slice without changing financial formulas, parser behavior, import behavior, deploy behavior, or old FinDesk boundaries.

Required files read:

- `FinDesk v2.0/START_HERE_DIRECTOR.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/36-layer1-summary-and-forecast.md`
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/09-operational-and-summary-table-contract.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/17-screen-registry.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/32-director-addendum-operational-window.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `app/v2/Api.php`
- `app/v2/Repository.php`
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
Layer 1 summary and forecast finance boundaries.
Files to read:
36-layer1-summary-and-forecast.md, 09-operational-and-summary-table-contract.md, 14-calculation-contract.md, app/v2/Repository.php, app/v2/Api.php, scripts/v2_fixture_runner.php.
What to check:
Existing report formulas, cash/card separation, commercial_income, other/review, correction logic, fact/forecast separation.
What to change if allowed:
No code changes in opening review.
What not to touch:
Parser, formula semantics, import behavior, deploy behavior, old FinDesk logic.
Report required:
ACCEPT/BLOCK, formula boundaries, existing API gaps, required acceptance tests.
```

```text
Agent:
Data and Backend Core Agent
Scope:
Report API, source trace, snapshot storage, forecast data model.
Files to read:
36-layer1-summary-and-forecast.md, 16-api-contract.md, 17-screen-registry.md, app/v2/Api.php, app/v2/Repository.php, FinDesk v2.0/sql/clean-core-schema.sql, scripts/v2_http_api_smoke.php.
What to check:
Existing support for Information, Sending, Printing, Storage, source_entry_ids, closed snapshots, forecast variables.
What to change if allowed:
No code changes in opening review.
What not to touch:
Production DB, parser, import, attachments beyond read-only source trace.
Report required:
ACCEPT/BLOCK, missing endpoints/fields/storage, safe first backend slice.
```

```text
Agent:
iOS-Native UX Layout Agent / Frontend Performance and Interaction Agent
Scope:
Summary screen layout and behavior using the operational screen design system.
Files to read:
36-layer1-summary-and-forecast.md, 04-responsive-layout-contract.md, 31-operational-input-window-contract.md, 32-director-addendum-operational-window.md, public/v2.php, public/assets/v2/app.css, public/assets/v2/app.js, scripts/v2_operational_browser_smoke.cjs.
What to check:
Navigation between operational and summary screens, four-tab model, mobile/iPad/desktop behavior, row/table interactions, modal drill-down, print view behavior.
What to change if allowed:
No code changes in opening review.
What not to touch:
Dashboard-first composition, decorative cards, body/page scroll, one-breakpoint mobile squeeze, unrelated operational regressions.
Report required:
ACCEPT/BLOCK, layout model, interaction contract, screenshot/metric requirements, first UI slice.
```

```text
Agent:
QA, Audit, and Acceptance Agent
Scope:
Acceptance gates and regression boundaries.
Files to read:
36-layer1-summary-and-forecast.md, 20-definition-of-done.md, 33-director-agent-orchestration-protocol.md, scripts/v2_operational_ui_smoke.sh, scripts/v2_operational_browser_smoke.cjs, scripts/v2_http_api_smoke.php, package.json.
What to check:
Which evidence is required before acceptance, what must block, what smoke tests need extension.
What to change if allowed:
No code changes in opening review.
What not to touch:
Release acceptance without screenshots/metrics/API evidence.
Report required:
ACCEPT/BLOCK, mandatory checks, security/regression risks, suggested smoke additions.
```

Expected reports:

- ACCEPT/BLOCK for opening implementation.
- Formula and data-source boundaries.
- Existing API and storage gap analysis.
- UX/navigation model that reuses operational screen behavior.
- QA gate plan with screenshots, metrics, and test files.

Exit criteria:

- `36-layer1-summary-and-forecast.md` exists in the working branch.
- Director Sprint Opening is recorded.
- All assigned agent reports are received.
- First implementation slice is explicitly accepted or blocked.
- No implementation is accepted unless:
  - all totals are generated from operational entries;
  - facts and forecast are separated;
  - tabs are exactly Information, Sending, Printing, Storage;
  - every displayed total has a source-entry drill-down plan;
  - mobile/iPad/desktop behavior is verified by screenshots/metrics;
  - no body/page scroll is introduced;
  - operational screen behavior does not regress.

Risks:

- Turning the second screen into a decorative dashboard.
- Adding independent report numbers that do not trace to entries.
- Mixing forecast with actuals.
- Treating storage snapshots as live mutable facts.
- Changing existing formulas or parser behavior while adding UI.
- Reusing old FinDesk report/dashboard logic.
- Expanding scope into heavy budgeting before Layer 1 summary is stable.

## Agent Reports

Financial Logic Engine Agent: Kant

```text
ACCEPT opening implementation with guardrails.
Existing monthly report API is a solid arithmetic base, but not the full Layer 1 Summary contract.
Must preserve operational entries as source of truth, counted/uncounted status boundaries, cash/card separation, card-to-cash two-sided model, commercial_income isolation, other_review visibility, and closed-month explicit correction/recalculation/cancel behavior.
Gaps: no complete period summary payload, no source entry ids per total, naming mismatch between current report fields and new contract, no period-scoped other-review, no stored snapshot, no forecast model.
Required tests: exact fixture arithmetic, source trace per total, category cash/card rows with counts/review, closed snapshot, and forecast fact/variable separation.
```

Data and Backend Core Agent: Mill

```text
ACCEPT safe first backend slice.
Existing support: monthly report, category matrix, other-review, entries, attachments, and close/reopen/correction routes.
Missing: generated_at/status/workspace/currency header in one payload, card entry counts, card review count, period category rows, source ids per total/category, sending package, print payload, report snapshot table, forecast variables/model.
Recommended first slice: GET /api/workspaces/:workspaceId/reports/layer1-summary?year=YYYY&month=M.
Payload must include header, totals, cash/card/category/other_review blocks, and source_trace entry ids.
Keep storage, sending, printing, and forecast out of the first backend slice until facts and source trace are stable.
```

iOS-Native UX Layout Agent / Frontend Performance and Interaction Agent: Meitner

```text
ACCEPT layout direction with constraints.
Second screen must reuse v2 shell/topbar/panel/detail-overlay grammar and fixed-viewport internal-scroll model.
Navigation should be a top-level Operational/Summary switch, not mixing Information/Sending/Printing/Storage into the operational Write/Check tabs.
Mobile/iPad mini should use mobile report system with internal ReportBody scroll and horizontal movement between report/source context.
iPad 11+ and desktop should use full workspace with report primary area and source-trace secondary area.
Information tab is the riskiest first UI slice and must prove source drill-down before decorative report presentation.
```

QA, Audit, and Acceptance Agent: Helmholtz

```text
BLOCK full sprint acceptance from current evidence.
Current smokes prove operational/report arithmetic, not the new Layer 1 Summary Screen.
Cannot accept without screenshots/metrics for the actual summary screen across desktop, phone, iPad mini, and iPad 11; API evidence for source ids per displayed total; stored snapshot evidence; browser drill-down evidence; print/send parity; and forecast fact/variable evidence.
Suggested additions: dedicated smoke:v2:summary, static summary markers, HTTP source-trace assertions, browser tab screenshots and drill-down checks.
```

## Director Decision After Agent Reports

Status:

```text
OPEN FOR FIRST IMPLEMENTATION SLICE / BLOCKED FOR FULL ACCEPTANCE
```

Approved first implementation slice:

```text
Layer 1 Information data foundation:
- add read-only Layer 1 summary API;
- reuse existing report arithmetic;
- add source_trace entry ids per total and category row;
- add period-scoped other/review block;
- add HTTP smoke evidence.
```

Not approved in this slice:

```text
- stored snapshots;
- forecast variables;
- sending/export routes;
- print payload routes;
- final acceptance of the full four-tab screen;
- dashboard-style UI.
```

## Implementation Report

Implemented:

- Added `FinDesk v2.0/36-layer1-summary-and-forecast.md` to the working branch from `origin/main`.
- Added read-only route:
  - `GET /api/workspaces/:workspaceId/reports/layer1-summary?year=YYYY&month=M`
- Added canonical Layer 1 summary payload:
  - `header`
  - `totals`
  - `blocks.cash`
  - `blocks.card`
  - `blocks.categories`
  - `blocks.other_review`
  - `source_trace`
- Added top-level Layer 1 navigation:
  - `Operational`
  - `Summary`
- Added Summary screen with exactly four tabs:
  - `Information`
  - `Sending`
  - `Printing`
  - `Storage`
- Implemented the first Information tab slice from server-provided values only.
- Added source trace overlay for summary totals.
- Kept Sending, Printing, and Storage as pending same-style panels.
- Extended browser smoke with Layer 1 Summary screenshot/metrics/source-trace evidence.

Files changed in this sprint slice:

- `FinDesk v2.0/36-layer1-summary-and-forecast.md`
- `FinDesk v2.0/sprints/SPRINT-17R-layer1-summary-screen-opening.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

Evidence:

- API smoke asserts Layer 1 route, header, totals, category cash/card rows, period-scoped other review, and source entry ids.
- Browser smoke captures:
  - `test-results/v2-browser-smoke/desktop-layer1-summary-information.png`
  - `test-results/v2-browser-smoke/desktop-layer1-summary-source-trace.png`
  - `test-results/v2-browser-smoke/layout-metrics.json`
- Layer 1 summary browser metrics confirmed:
  - shell fits viewport;
  - summary screen fits viewport;
  - report body owns internal vertical scroll;
  - body/html overflow remains hidden;
  - source trace opens from a displayed total.

Verification:

```text
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
php -l public/v2.php
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l scripts/v2_http_api_smoke.php
php -l scripts/v2_clean_core_static_smoke.php
php scripts/v2_clean_core_static_smoke.php
bash scripts/v2_http_api_smoke.sh
npm run smoke:v2
npm run smoke:v2:browser
npm run smoke:v2 && npm run smoke:v2:auth && npm run test:v2:fixtures && npm run smoke:v2:http && npm run smoke:v2:db && npm run smoke:v2:ui && npm run smoke:v2:browser && npm run smoke:v2:manual-responsive && npm run smoke:v2:deploy
```

Result:

```text
PASS
```

Deploy preflight result:

```text
PASS with 1 existing warning:
FINDESK_V2_PRODUCTION_BASE_URL is not set; live HTTP deny checks were skipped.
```

## Director Final Handoff

Sprint:

```text
SPRINT-17R — Layer 1 Summary Screen
```

Status:

```text
First implementation slice ACCEPTED.
Full Layer 1 Summary Screen remains BLOCKED.
```

Agents assigned:

- Financial Logic Engine Agent
- Data and Backend Core Agent
- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent

Agent reports received:

```text
4/4 opening reports received.
2 worker implementation reports received.
```

Accepted work:

- Read-only Layer 1 summary API with source trace.
- First same-style Summary UI shell.
- Information tab server-value rendering.
- Source trace overlay for displayed totals.
- Smoke and browser evidence for the first slice.

Rejected / blocked work:

- Full four-tab functional acceptance.
- Stored report snapshots.
- Forecast variables and forecast table.
- Sending package/export behavior.
- Print payload/view parity beyond scaffold.
- Production acceptance without live deny checks.

Risks:

- Opening cash source trace may point to entries outside the currently loaded operational month; later drill-down must fetch exact source entries across periods instead of relying only on current-month client state.
- Storage snapshots are not implemented; closed report data is still live-generated except existing closure metadata.
- Forecast is not implemented; fact/forecast separation is currently contract-only.
- Summary screen responsive evidence exists for desktop first slice; phone/iPad Summary-specific screenshots are still needed before full screen acceptance.

Next sprint:

```text
SPRINT-18R — Layer 1 Summary Source Drilldown and Snapshot Foundation
```

Recommended next scope:

- Add source-entry fetch support for arbitrary source ids and cross-period opening_cash drill-down.
- Add stored report snapshot schema/API for closed periods.
- Add Summary-specific responsive screenshots for phone, iPad mini, iPad 11 portrait/landscape, and desktop.
- Keep Sending, Printing, Storage, and Forecast blocked until source drill-down and snapshot foundation are accepted.

Paste-to-next-director prompt:

```text
You are the next Director for FinDesk v2.0. Source of truth is only GitHub files. Start with START_HERE_DIRECTOR.md, 33-director-agent-orchestration-protocol.md, 36-layer1-summary-and-forecast.md, and this SPRINT-17R handoff. SPRINT-17R accepted only the first Layer 1 Summary slice: read-only layer1-summary API, Information tab scaffold, source_trace ids, and desktop browser evidence. Full Layer 1 Summary Screen is still blocked. Do not implement Forecast/Sending/Printing/Storage as final features yet. Open SPRINT-18R for source drilldown and snapshot foundation. Assign Financial Logic, Data/Backend, UX/Frontend, and QA agents before work. Do not change parser/formulas/import/deploy behavior and do not make dashboard-first UI.
```
