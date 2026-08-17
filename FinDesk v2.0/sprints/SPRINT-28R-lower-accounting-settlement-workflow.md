# SPRINT-28R — Lower Accounting Settlement Workflow

## Director Sprint Opening

Sprint:
SPRINT-28R — Lower Accounting Settlement Workflow

Goal:
Turn the lower-accounting block from a flat reporting bucket into a settlement workflow that shows who received money, who returned money, what remains open, and which rows need human review.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/36-layer1-summary-and-forecast.md`
- `FinDesk v2.0/sprints/SPRINT-27R-lower-accounting-block.md`
- `app/v2/Repository.php`
- `public/assets/v2/app.js`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_ui_smoke.sh`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:

- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

Exit criteria:

- Physical cash/card balances remain controlled only by entry primitives.
- Lower-accounting entries expose settlement counterparty, direction, and effect.
- Layer 1 lower accounting exposes settlement rows by counterparty.
- Statuses include `open`, `partial`, `closed`, `needs_actor`, and `review`.
- Source trace for a settlement row opens only lower-accounting rows for that settlement.
- Operational rows from the same person do not enter settlement chains.
- Fixture, HTTP, UI, and browser evidence pass.

## Agent Reports Received

### Financial Logic Engine Agent

Accepted.

Contract:

- `direction=out` means money issued/paid out and increases open accountable amount.
- `direction=in` means money returned/settled and decreases open accountable amount.
- Counterparty is resolved from actor fields, semantic `source_actor`, or known text aliases.
- Unresolved rows are `needs_actor` and must not auto-close.
- Return without issue and over-return are `review`.
- Lower accounting remains a derived reporting layer over `v2_entries`.

### QA, Audit, and Acceptance Agent

Accepted.

Requested evidence:

- Fixture and HTTP cases for open, partial, and closed settlements.
- Negative control where a normal operational row from the same person is excluded from settlement source trace.
- Static UI markers for the settlement workflow.
- Browser smoke that opens Layer 1 Summary and drills into a counterparty source trace.

## Implemented

Backend:

- Added settlement fields to lower-accounting entries:
  - `settlement_counterparty`
  - `settlement_effect`
  - `settlement_direction`
- Added `blocks.lower_accounting.settlements`.
- Added settlement grouping by counterparty with issued, returned, open, over-returned, status, review reason, entry count, and source ids.

Frontend:

- Added Layer 1 lower-accounting settlement table.
- Added settlement source buttons for drilldown.
- Added UI markers:
  - `data-v2-settlement-workflow`
  - `data-v2-settlement-status`
  - `data-v2-settlement-source`

Tests:

- Extended fixture coverage for partial, closed, and unresolved settlement rows.
- Extended HTTP smoke with:
  - `Вова`: issued 200, returned 50, open 150, status partial
  - `Женя`: issued 300, open 300, status open
  - `Данил`: issued 120, returned 120, open 0, status closed
  - `Unassigned`: status needs_actor
  - `Вова купил кабель`: remains operational and is excluded from Vova settlement source trace
- Extended browser smoke to open Summary, verify settlement rows, and drill into Vova source trace.

## Verification

Commands run:

```bash
php -l app/v2/Repository.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_http_api_smoke.php
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
```

Results:

```text
php -l: OK
node --check: OK
npm run test:v2:fixtures: PASS (20)
npm run smoke:v2:http: OK
npm run smoke:v2:ui: OK
npm run smoke:v2:browser: OK
```

Browser screenshots:

```text
test-results/v2-browser-smoke
```

## Files Changed

- `app/v2/Repository.php`
- `public/assets/v2/app.js`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_ui_smoke.sh`
- `scripts/v2_operational_browser_smoke.cjs`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/36-layer1-summary-and-forecast.md`

## Acceptance

ACCEPTED locally.

Lower accounting now has a minimal settlement workflow suitable for the first MVP phase: rows remain operational-entry based, balances remain physically correct, and the summary can show open/partial/closed accountable money with source proof.

## Risks

- Counterparty resolution is still heuristic when no actor field exists.
- Grouping is by resolved counterparty for MVP. A later sprint may split by accounting type/currency if real data shows mixed chains under the same person.
- `needs_actor` rows require manual correction before they can be trusted as settlement chains.

## Next Sprint

Use the Claudia Z historical corpus to improve actor/counterparty extraction without creating broad new categories.
