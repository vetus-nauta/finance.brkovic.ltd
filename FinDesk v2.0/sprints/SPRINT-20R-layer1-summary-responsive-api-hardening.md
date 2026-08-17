# SPRINT-20R — Layer 1 Summary Responsive Evidence and API Hardening

## Director Sprint Opening

Sprint:
SPRINT-20R — Layer 1 Summary Responsive Evidence and API Hardening

Goal:
Close the remaining SPRINT-19R acceptance gaps for Layer 1 Summary without changing financial formulas, parser behavior, import behavior, or deployment behavior.

Required files read:
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/36-layer1-summary-and-forecast.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/sprints/SPRINT-19R-opening-basis-storage-readback.md`
- `app/v2/Repository.php`
- `app/v2/Api.php`
- `public/v2-api.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_manual_responsive_walkthrough.cjs`

Agents assigned:
- QA, Audit, and Acceptance Agent
- Data / Backend Core Agent
- Frontend Performance and Interaction Agent
- Financial Logic Engine Agent as reviewer

Agent tasks:
- QA: define Summary-specific responsive evidence matrix and BLOCK/ACCEPT rules.
- Backend: inspect Layer 1 report/snapshot API contract and snapshot version hardening risks.
- Frontend: inspect Summary tabs, source overlay, Storage readback, and responsive/performance risks.
- Financial Logic: verify that evidence/storage changes do not mix non-entry basis ids with operational entry ids and do not alter formulas.

Expected reports:
- QA acceptance matrix.
- Backend API/storage hardening report.
- Frontend responsive risk report.
- Financial invariant report.

Exit criteria:
- Summary-specific screenshots and JSON metrics exist for Information, opening cash source overlay, and Storage readback across required device classes.
- No body/page scroll or viewport overhang in Summary states.
- Source overlay shows non-entry opening basis plus prior operational entry and remains reachable.
- Storage readback is backend-driven and responsive.
- API contract documents Layer 1 Summary routes.
- Snapshot versions are protected by unique version semantics and controlled retry on duplicate version conflict.
- `snapshot.source_entry_ids` contains only operational entry ids.
- Full v2 smoke gate remains green.

Risks:
- Phone Summary may overhang because category/source rows are wider than the viewport.
- Storage repeated snapshots may make mobile action/header layout unstable.
- Snapshot source ids can accidentally mix non-entry UUIDs unless flattened from explicit entry buckets only.
- Sending, Printing, and Forecast remain placeholders and must not be accepted as final behavior.

## Agent Reports

QA, Audit, and Acceptance Agent:
- Decision: BLOCK full Layer 1 responsive acceptance before this sprint.
- Required device evidence: phone portrait, reduced phone, phone landscape, iPad mini portrait/landscape, iPad 11 portrait/landscape, desktop 1365x820 and 1440x900.
- Required states: Information, opening cash source overlay, Storage readback.
- Required metrics: body/html overflow hidden, no page overhang, document scroll top zero, Summary screen within viewport, active Summary scroll owns vertical scroll, overlay close reachable.

Data / Backend Core Agent:
- Decision: ACCEPT narrow snapshot readback direction, BLOCK production-grade Storage until API contract and concurrency hardening are addressed.
- Existing unique key `uq_v2_report_snapshot_version` protects against duplicate stored versions.
- Required hardening: duplicate-version conflict must not surface as generic `v2_internal_error`; retry or controlled conflict is required.
- Required contract: document `layer1-summary`, `layer1-source-entries`, and `layer1-snapshots` GET/POST.

Frontend Performance and Interaction Agent:
- Decision: ACCEPT narrow direction, BLOCK full UX acceptance until responsive evidence passes.
- Risks: category/source tables may be wider than phone viewport; overlay body and close button must stay reachable; Storage action wrapping and repeated snapshot rows require screenshots/metrics.
- Required gates: manual responsive Summary evidence plus existing browser/UI/http gates.

Financial Logic Engine Agent:
- Decision: BLOCK until snapshot source ids are proven to contain only operational entries.
- Finding: recursive source id flattening could collect non-entry UUIDs such as `source_trace.basis.opening_cash.flow_id`.
- Required invariant: `source_entry_ids` must include real operational entry ids only; opening basis remains in `source_trace.basis`.
- Required invariant: Layer 1 totals continue to equal monthly report totals.

## Implementation

Files changed:
- `FinDesk v2.0/16-api-contract.md`
- `app/v2/Repository.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_manual_responsive_walkthrough.cjs`
- `FinDesk v2.0/sprints/SPRINT-20R-layer1-summary-responsive-api-hardening.md`

Implemented:
- Added Layer 1 Summary routes and snapshot/source rules to the API contract.
- Added retry handling for duplicate `uq_v2_report_snapshot_version` conflicts during snapshot creation.
- Changed snapshot source id flattening to collect only explicit operational entry buckets:
  - `source_trace.totals.*`
  - `source_trace.categories.*`
  - `source_trace.basis.opening_cash.prior_entry_ids`
- Added HTTP smoke assertion that opening basis `flow_id` is not stored in `snapshot.source_entry_ids`.
- Extended manual responsive walkthrough to capture Summary states on the required viewport matrix:
  - Information
  - opening cash source trace
  - Storage readback
- Added Summary metrics for viewport fit, scroll ownership, document scroll, overlay body scroll, and close button reachability.

## Acceptance Status

Director status:
ACCEPT SPRINT-20R.

Accepted scope:
- Layer 1 Summary responsive evidence for Information, opening cash source overlay, and Storage readback.
- Layer 1 API contract documentation.
- Snapshot version conflict retry hardening.
- Snapshot operational source id invariant.

Rejected / not accepted:
- Sending final behavior.
- Printing final behavior.
- Forecast behavior.
- Production deployment without live production evidence.

Evidence:
- `test-results/v2-manual-responsive/manual-responsive-report.json`
- 27 Summary screenshots in `test-results/v2-manual-responsive/`
- `test-results/v2-browser-smoke/desktop-layer1-summary-information.png`
- `test-results/v2-browser-smoke/desktop-layer1-summary-source-trace.png`
- `test-results/v2-browser-smoke/desktop-layer1-summary-storage-readback.png`

Responsive evidence matrix:
- Desktop 1365x820
- Desktop 1440x900
- iPad 11 portrait 834x1194
- iPad 11 landscape 1194x834
- iPad mini portrait 768x1024
- iPad mini landscape 1024x768
- Phone portrait 390x844
- Phone reduced 360x640
- Phone landscape 844x390

Checks passed:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l public/v2-api.php
node --check scripts/v2_manual_responsive_walkthrough.cjs
node --check scripts/v2_operational_browser_smoke.cjs
node --check public/assets/v2/app.js
git diff --check
npm run smoke:v2:http
npm run smoke:v2:browser
npm run smoke:v2:manual-responsive
npm run smoke:v2 && npm run smoke:v2:auth && npm run test:v2:fixtures && npm run smoke:v2:http && npm run smoke:v2:db && npm run smoke:v2:ui && npm run smoke:v2:browser && npm run smoke:v2:manual-responsive && npm run smoke:v2:deploy
```

Full gate result:
PASS.

Deploy preflight warning:
`FINDESK_V2_PRODUCTION_BASE_URL` is not set, so live HTTP deny checks were skipped.

Director final decision:
SPRINT-20R is accepted as a Layer 1 Summary responsive/API/storage hardening sprint.

Next sprint:
SPRINT-21R must choose one narrow product slice:
- Sending package real output, or
- Printing view real output, or
- Forecast opening slice.

Rule for next sprint:
Do not treat Sending, Printing, or Forecast placeholders as accepted product behavior.
