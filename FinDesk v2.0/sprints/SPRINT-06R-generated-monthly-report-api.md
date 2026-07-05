# SPRINT-06R — Generated Monthly Summary And Report API

Director: Codex Director, FinDesk v2.0

Status: Accepted

## Director Sprint Opening

Sprint:

```text
SPRINT-06R — Generated Monthly Summary And Report API
```

Goal:

- Add generated report API routes required by MVP.
- Generate monthly summary and category matrix from operational entries.
- Add contract route for Other review report.
- Do not add dashboard UI, import workflow, attachments, bank reconciliation, or old FinDesk report logic.

Scope override:

- SPRINT-05R recommended a correction-request resolution surface.
- Director override: reports are a larger MVP blocker, so SPRINT-06R opens the generated report API first.
- The correction-request surface remains a known follow-up and must not be silently redefined here.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/07-mvp-scope-and-acceptance.md`
- `FinDesk v2.0/09-operational-and-summary-table-contract.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/sprints/SPRINT-05R-closed-month-decision-workflow.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_fixture_runner.php`

Agents assigned:

- Financial Logic Engine Agent
- Data/Backend Core Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- Financial Logic: define report formulas, counted statuses, category and correction boundaries.
- Backend/API: define minimal routes, response shapes, repository implementation plan, and disposable DB tests.
- QA/Audit: define exit criteria, evidence, blockers, and must-not-touch boundaries.

Expected reports:

- ACCEPT/BLOCK for opening and closing the sprint.
- Required report fields and formulas.
- Minimal API routes and response shapes.
- Required tests and acceptance blockers.

Exit criteria:

- `GET /api/workspaces/:workspaceId/reports/monthly?year=2026&month=7` exists.
- `GET /api/workspaces/:workspaceId/reports/category-matrix?year=2026` exists.
- `GET /api/workspaces/:workspaceId/reports/other-review` exists.
- Monthly report includes:
  - `opening_cash`
  - `external_cash_income`
  - `commercial_income`
  - `cash_expense`
  - `card_expense`
  - `cash_topup_from_card_card_side`
  - `cash_topup_from_card_cash_side`
  - `other_expenses`
  - `ending_cash`
  - `comment`
- Reports are generated from operational entries.
- Category matrix buckets entries by category and month.
- Other review report stays tied to visible `other_review` rows.
- Existing v2 gates remain green.

Risks:

- Accidentally using old FinDesk report/dashboard logic.
- Netting card-to-cash into zero instead of showing both sides.
- Treating opening cash as income.
- Mixing commercial income with private/external cash top-ups.
- Changing parser, category dictionary, import, attachments, or closed-month semantics as a side effect.

## Agent Reports

Financial Logic Engine Agent: Sagan

```text
BLOCK current code for closure; ACCEPT scope as a needed report/API sprint.
Counted statuses must be recognized, other_review, imported, accepted, corrected.
Monthly report must expose required fields, keep card-to-cash sides separate, keep commercial_income separate, and include explicit correction handling.
Category matrix must be generated from counted entries by category/month/flow/direction.
Other-review report must expose unresolved visible other_review rows.
```

Data/Backend Core Agent: Carver

```text
ACCEPT to open.
Add only contract routes for monthly report, category matrix, and other-review report.
Keep /summary as operational current figures.
Implement repository methods from v2 entries/flows/categories; no schema change required.
Extend static and disposable HTTP smoke evidence.
```

QA, Audit, and Acceptance Agent: Pasteur

```text
ACCEPT to open; BLOCK closure until routes, formulas, and disposable evidence exist.
Require monthly report fields, category matrix, other-review route, generated-from-entries proof, and existing gates green.
Do not touch dashboard UI, imports, attachments, parser semantics, category dictionaries, closed-month semantics, production DB, or old FinDesk report logic.
```

Final Financial Logic Acceptance: Sagan

```text
ACCEPT for financial logic closure.
Verified contract routes, centralized counted statuses, monthly report generation from v2 entries, card-to-cash split, commercial income isolation, opening/ending cash calculation, Other review report, and category matrix flow/direction breakdown.
```

Final QA/Audit Acceptance: Pasteur

```text
ACCEPT for closure.
Verified report routes, required monthly fields, generated-from-entries behavior, category matrix, Other review report, static route coverage, fixture coverage, disposable HTTP evidence, and clean scope boundaries.
```

## Implementation Report

Branch:

```text
findesk-v2-sprint-06r-generated-report-api
```

Files changed:

- `app/v2/Api.php`
- `app/v2/Repository.php`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `FinDesk v2.0/sprints/SPRINT-06R-generated-monthly-report-api.md`

Implemented:

- Added contract report routes:
  - `GET /api/workspaces/:workspaceId/reports/monthly?year=YYYY&month=M`
  - `GET /api/workspaces/:workspaceId/reports/category-matrix?year=YYYY`
  - `GET /api/workspaces/:workspaceId/reports/other-review`
- Kept existing `/summary` as operational current figures.
- Added generated monthly report from operational entries.
- Added category-by-month matrix with category totals and `flow:direction` breakdown.
- Added Other review report with count, total, and visible unresolved entries.
- Centralized counted statuses:
  - `recognized`
  - `other_review`
  - `imported`
  - `accepted`
  - `corrected`
- Extended static smoke to require report route markers.
- Extended fixture runner with generated monthly report coverage.
- Extended disposable HTTP smoke with report API coverage.

Monthly report fields implemented:

- `opening_cash`
- `discrepancy_with_previous`
- `external_cash_income`
- `commercial_income`
- `cash_expense`
- `card_expense`
- `cash_topup_from_card_card_side`
- `cash_topup_from_card_cash_side`
- `other_expenses`
- `corrections`
- `ending_cash`
- `comment`
- `is_closed`
- `counts`

Verification:

```text
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l scripts/v2_http_api_smoke.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_clean_core_static_smoke.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:db
npm run smoke:v2:ui
npm run smoke:v2:browser
```

Result:

```text
PASS
```

Fixture result:

```text
PASS (13)
BLOCKED / NOT_IMPLEMENTED (0)
```

## Residual Risks

- Reports are generated live from entries; closed-month snapshot persistence is not implemented in this sprint.
- Month close/reopen production routes remain out of scope.
- `create_correction` from SPRINT-05R remains audit-only and still needs a resolution surface.
- Import source traceability returns `source_files: []` until import workflow creates `source_id` links.
- No report UI was added; this sprint is API and test coverage only.
- Browser coverage is regression coverage for operational UI, not a report UI test.

## Isolated Handoff For Next Director

Recommended next sprint:

```text
SPRINT-07R — One-File Legacy Excel Import MVP
```

Reason:

- MVP still requires one legacy Excel import with traceability and import review evidence.
- Generated monthly reports now exist and can be used as the comparison target after import.

Starting point:

- Source of truth remains GitHub files only.
- Old FinDesk remains infrastructure donor only.
- Do not reuse old import/report product logic without explicit review.
- Keep the first working surface operational-entry-first; do not turn the product into dashboard-first UI.

Suggested scope:

- Implement one-file import only.
- Preserve `import_source_id`, sheet name, row number, raw row data, parse status, and entry link.
- Add include/exclude marker handling.
- Produce import review/report output.
- Compare imported cash/card totals against generated report API.

Must not touch:

- Financial formulas unless explicitly reopened.
- Parser semantics outside import row normalization needs.
- Dashboard/report UI.
- Attachments.
- Production DB or real secrets.
