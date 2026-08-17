# SPRINT-27R — Lower Accounting Block

## Director Sprint Opening

Sprint:
SPRINT-27R — Lower Accounting Block

Goal:
Separate debt, loan, credit, return, accountable-cash, private settlement, and guest-cash-issued control rows from normal operational category totals while preserving physical cash/card balances.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/02-data-model.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/36-layer1-summary-and-forecast.md`
- `FinDesk v2.0/sprints/SPRINT-22R-semantic-markers-engine.md`
- `FinDesk v2.0/sprints/SPRINT-25R-claudia-z-data-application-gate.md`
- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_ui_smoke.sh`

Agents assigned:

- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent
- Data and Backend Core Agent role was handled by Director implementation against API/report code
- Frontend Performance and Interaction Agent role was handled by Director implementation against operational/check and Layer 1 UI

Agent tasks:

- Financial Logic Engine Agent: inspect current parser/report/balance behavior, define invariants and strict lower-accounting contract.
- QA, Audit, and Acceptance Agent: identify fixture, HTTP, UI, and browser acceptance coverage needed for lower accounting.

Exit criteria:

- Entry and parse-preview responses expose derived accounting fields.
- `debt_or_return`, `money_movement`, and `guest_cash_issued` route to `accounting_section=lower_accounting`.
- Physical `cash_expense`, `card_expense`, `external_cash_income`, and `ending_cash` remain unchanged by the lower-accounting view.
- Layer 1 summary exposes `blocks.lower_accounting` and source ids.
- Layer 1 category rows exclude lower-accounting entries.
- Browser/UI/static/fixture/HTTP evidence passes.

## Agent Reports Received

### Financial Logic Engine Agent

Accepted.

Findings:

- `guest_cash_issued` already exists as an expense category.
- `debt_or_return` currently exists as semantic metadata, not category.
- Cash balances are category-independent and must remain so.
- Monthly/Layer 1 physical totals must not silently drop real cash/card movements.

Accepted contract:

- Lower accounting is a reporting/view layer, not a new physical balance formula.
- Debt/loan/credit/return/accountable/private settlement rows are lower accounting.
- `guest_cash_issued` is also lower accounting because it is a guest-cash control row.
- Lower-accounting rows remain counted for physical balances but are excluded from operational category totals.

### QA, Audit, and Acceptance Agent

Accepted.

Requested coverage:

- Fixture scenario for lower accounting rows and negative controls.
- HTTP API checks for parse preview, entries, Layer 1 summary block, source trace, and category exclusion.
- UI hooks for lower accounting.
- Browser smoke to guard real rendering and layout regressions.

## Implemented

Backend:

- Added derived `accounting_section`, `accounting_type`, and `accounting_label` to entry and parse-preview responses.
- Added `blocks.lower_accounting` to Layer 1 summary.
- Added `source_trace.totals.lower_accounting_total`.
- Excluded lower-accounting rows from Layer 1 operational category rows.
- Preserved physical monthly totals and ending cash calculations.

Frontend:

- Added `accounting` column to structured check.
- Added lower-accounting row styling in operational journal/check.
- Added accounting line in entry details and parse preview.
- Added Layer 1 `Lower accounting` block with source trace action.

Tests:

- Added fixture `Lower accounting block`.
- Added HTTP lower-accounting workspace scenario.
- Added UI smoke hooks for lower-accounting block and accounting field.

## Verification

Commands run:

```bash
php -l app/v2/Repository.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_http_api_smoke.php
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
```

Results:

```text
php -l: OK
node --check: OK
npm run smoke:v2: OK
npm run test:v2:fixtures: PASS (20)
npm run smoke:v2:http: OK
npm run smoke:v2:ui: OK
npm run smoke:v2:browser: OK
```

## Files Changed

- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_ui_smoke.sh`
- `FinDesk v2.0/02-data-model.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/36-layer1-summary-and-forecast.md`

## Acceptance

ACCEPTED locally.

Lower accounting is now first-class in API/UI/reporting as a derived layer, while the operational journal remains the source of truth.

## Risks

- Monthly report still exposes physical `cash_expense` and `external_cash_income`; consumers must use Layer 1 lower-accounting block for management-view separation.
- Lower-accounting classification is derived from semantic markers/category at response time. If marker rules are changed later, historical view classification may change unless snapshots are used.

## Next Sprint

SPRINT-28R should decide whether lower accounting needs an explicit settlement workflow for open/closed accountable balances, not just reporting separation.
