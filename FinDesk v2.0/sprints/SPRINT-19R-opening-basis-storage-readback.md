# SPRINT-19R — Opening Basis And Storage Snapshot Readback

Director: Codex Director, FinDesk v2.0

Status: Open / implementation slice in progress

## Director Sprint Opening

Sprint:

```text
SPRINT-19R — Opening Basis And Storage Snapshot Readback
```

Goal:

- Continue from accepted SPRINT-18R narrow slice.
- Make `opening_cash` source explanation honest by separating non-entry opening balance basis from operational source entries.
- Add first Storage snapshot readback UX using the existing Summary style.
- Strengthen snapshot readback/revision evidence.
- Keep operational entries as financial source of truth.

Explicit non-goals:

- Do not change financial formulas.
- Do not change parser/import/deploy behavior.
- Do not open Forecast.
- Do not implement final Sending or Printing.
- Do not turn stored snapshot numbers into editable truth.

Agents assigned:

- Financial Logic Engine Agent
- Data and Backend Core Agent
- UX/Frontend Agent
- QA, Audit, and Acceptance Agent

Agent opening tasks:

```text
Financial Logic Engine Agent:
Check opening_cash basis boundaries, cash/card separation, source-truth rules, and formula preservation.
```

```text
Data and Backend Core Agent:
Check source_trace.basis shape, snapshot readback/list behavior, revision and immutability smoke expectations.
```

```text
UX/Frontend Agent:
Check source overlay basis rows and Storage first readback surface across existing Summary layout.
```

```text
QA, Audit, and Acceptance Agent:
Check acceptance evidence, security gates, responsive screenshots, and remaining blockers.
```

Exit criteria:

- Agent reports are received and recorded.
- `opening_cash` source overlay shows both opening balance basis and prior operational entries.
- Layer 1 summary payload stores non-entry basis separately from source entry IDs.
- Snapshot readback preserves basis/source IDs and exposes a first Storage UI state.
- Snapshot revision behavior is smoke-tested without mutating prior snapshots.
- Existing full v2 gate remains green.

Known blockers that may remain after this slice:

- Full Summary acceptance still needs complete phone/iPad Summary-specific source and Storage screenshots.
- Sending, Printing, and Forecast remain blocked.

## Agent Reports

Financial Logic Engine Agent:

- Decision: ACCEPT narrow slice, BLOCK full acceptance.
- Keep `source_trace.totals.opening_cash` as operational entry IDs.
- Add sibling non-entry basis, not a fake operational row.
- Required basis values: flow opening balance, prior cash delta, prior entry IDs, computed opening cash, period start.
- Snapshot readback may display generated values, source IDs, hash, status, and opening basis, but cannot make final numbers editable.

Data and Backend Core Agent:

- Decision: ACCEPT narrow backend slice, BLOCK full Layer 1 / Storage acceptance.
- Recommended `source_trace.basis.opening_cash` as typed non-entry evidence.
- Snapshot list/readback may use existing list route for first Storage slice.
- Required revision smoke: v1 unchanged after correction/recalculation, v2 creates new version/hash with correction evidence.
- Concurrency note: `MAX(version)+1` is acceptable for smoke but not production-hardened under concurrent saves.

UX/Frontend Agent:

- Decision: BLOCK until visible UI exists.
- Required: opening cash overlay must show non-entry basis and operational entries.
- Required: Storage must show backend-driven snapshot readback, not placeholder/client-only state.
- Required screenshots remain broader than this slice for full acceptance.

QA, Audit, and Acceptance Agent:

- Decision: ACCEPT narrow opening, BLOCK full acceptance until evidence exists.
- Required gates: opening basis, Storage readback, immutability/revision, Summary screenshots across required devices, full regression gate.
- Forecast, Sending, and Printing final behavior remain blocked.

## Implementation Evidence

Files changed in this slice:

- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

Implemented:

- `source_trace.basis.opening_cash` as typed non-entry evidence:
  - `type`
  - `flow_id`
  - `flow_name`
  - `flow_opening_balance`
  - `prior_cash_delta`
  - `prior_entry_ids`
  - `total`
  - `period_start`
- Source overlay renders basis rows plus operational source entries.
- Storage tab has backend-driven snapshot readback and `Save snapshot`.
- Snapshot v1/v2 revision smoke verifies old snapshot remains unchanged and new snapshot records changed hash/version/correction evidence.

Evidence commands:

```text
php -l app/v2/Api.php
php -l app/v2/Repository.php
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
- `test-results/v2-browser-smoke/desktop-layer1-summary-storage-readback.png`
- `test-results/v2-browser-smoke/layout-metrics.json`
- `test-results/v2-manual-responsive/manual-responsive-report.json`

Director acceptance:

- ACCEPT SPRINT-19R narrow implementation slice.
- BLOCK full Layer 1 Summary / Storage acceptance.
- BLOCK final Sending, Printing, and Forecast.

Remaining required work:

- Add Summary-specific phone/iPad mini/iPad 11 screenshots for Information, source overlay with opening basis, and Storage readback.
- Add API contract documentation for Layer 1 routes.
- Harden snapshot version creation for concurrent saves before production-grade Storage acceptance.
