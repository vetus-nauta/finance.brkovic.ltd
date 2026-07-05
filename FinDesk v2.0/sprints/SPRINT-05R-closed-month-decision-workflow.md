# SPRINT-05R — Closed-Month Correction Decision Workflow

Director: Codex Director, FinDesk v2.0

Status: Accepted

## Director Sprint Opening

Sprint:

```text
SPRINT-05R — Closed-Month Correction Decision Workflow
```

Goal:

- Add an explicit closed-month decision workflow for category correction.
- Keep the workflow inside the operational entry Details panel.
- Preserve the rule: closed months never mutate silently.
- Do not start reports, dashboard, imports, analytics, bank reconciliation, final parser claims, or broad edit behavior.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-04R-entry-review-correction.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/16-api-contract.md`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_http_api_smoke.php`

Agents assigned:

- Financial Logic Engine Agent
- Backend/API Agent
- Frontend UX/Interaction Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- Financial Logic: define safe meaning of `cancel`, `create_correction`, and `recalculate_chain`.
- Backend/API: define minimal explicit decision route and DB effects.
- Frontend UX: define inline Details-panel decision UI and hooks.
- QA/Audit: define acceptance blockers and required browser/API evidence.

Expected reports:

- ACCEPT/BLOCK for implementation.
- Required decision semantics.
- Route/payload/test recommendations.
- Browser/API acceptance evidence.

Exit criteria:

- Normal closed-month category PATCH still returns `409 closed_month_requires_decision`.
- Details panel shows explicit decisions:
  - `create_correction`
  - `recalculate_chain`
  - `cancel`
- `cancel` performs no server decision request from the UI and leaves persisted data unchanged.
- `create_correction` records the decision and leaves the original category-only closed entry unchanged.
- `recalculate_chain` explicitly applies the narrow category correction and refreshes derived data.
- Browser smoke proves the three decision paths.
- HTTP API smoke proves the three decision paths and audit rows.
- Existing v2 gates remain green.

Risks:

- Turning category correction into fake financial correction rows.
- Changing formulas, parser semantics, reports, imports, or category dictionaries.
- Mutating a closed month without explicit user choice.
- Optimistic UI mutation before server confirmation.
- Expanding into dashboard/report/month close admin screens.

## Agent Reports

Financial Logic Engine Agent: Nietzsche

```text
ACCEPT
create_correction for category-only must preserve the closed original entry and record the decision; do not invent reporting impact.
recalculate_chain may explicitly apply the same narrow category update as open-month correction and refresh derived data.
cancel must perform no mutation.
No formula, parser, card, cash, commercial income, or report behavior may change.
```

Backend/API Agent: Poincare

```text
ACCEPT
Add POST /api/entries/:entryId/category/closed-month-decision.
Payload: decision, category_code, optional reason.
cancel: no entry mutation, audit only.
create_correction: record/audit explicit category-only correction request, original unchanged.
recalculate_chain: apply category update and recalculate affected flow.
Keep normal guarded PATCH /category behavior unchanged.
```

Frontend UX/Interaction Agent: Epicurus

```text
ACCEPT
Add inline decision widget inside Details panel after 409.
Do not add a screen, modal wizard, report page, or dashboard.
Preserve requested category and original category.
Cancel hides the widget and resets selector.
create_correction and recalculate_chain call explicit decision route.
```

QA, Audit, and Acceptance Agent: Lagrange

```text
ACCEPT
Sprint may open as narrow closed-month workflow only.
Closure requires browser/API proof for cancel, create_correction, and recalculate_chain as implemented.
Block production DB, old UI/API, reports/dashboard/imports, formula/parser changes, and static-only evidence.
```

Final QA/Audit Agent: Popper

```text
ACCEPT
Verified explicit route, guarded normal PATCH behavior, narrow decision semantics, v2-only UI/API use, browser evidence for client-only cancel, HTTP evidence for all decisions and audit rows, and disposable DB usage.
No evidence of production DB use, old UI/API use, reports/dashboard/import work, or parser/formula changes.
```

## Implementation Report

Branch:

```text
findesk-v2-sprint-05r-closed-month-decision
```

Files changed:

- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_ui_smoke.sh`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_http_api_smoke.php`

Implemented:

- Added explicit `POST /api/entries/:entryId/category/closed-month-decision` route.
- Kept normal closed-month category `PATCH` guarded by `409 closed_month_requires_decision`.
- Added three explicit closed-month decisions:
  - `cancel`: UI-only cancellation, no decision POST, no persisted mutation.
  - `create_correction`: audit/request record only, original closed entry unchanged.
  - `recalculate_chain`: explicit narrow category update and affected flow recalculation.
- Added inline Details-panel decision widget with stable v2 smoke hooks.
- Extended browser and HTTP smokes to prove all three decision paths and audit rows.

Verification:

```text
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l scripts/v2_http_api_smoke.php
php -l public/v2.php
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2:ui
npm run smoke:v2
npm run smoke:v2:browser
npm run smoke:v2:http
npm run test:v2:fixtures
npm run smoke:v2:db
```

Result:

```text
PASS
```

## Residual Risks

- `create_correction` is intentionally audit-only for category-only closed-month changes; it does not create a financial correction row.
- There is no operator queue or resolution surface for recorded correction requests yet.
- `recalculate_chain` is limited to the affected flow balance; broader derived/reporting surfaces remain out of scope.
- Browser verification uses local Chromium automation rather than a visual regression suite.

## Isolated Handoff For Next Director

Recommended next sprint:

```text
SPRINT-06R — Closed-Month Correction Request Resolution Surface
```

Starting point:

- Treat this sprint as accepted only for an explicit closed-month category decision workflow.
- Do not treat `create_correction` as a completed correction-entry model.
- Source of truth remains GitHub files only.
- Old FinDesk remains an infrastructure donor only, not product logic truth.

Suggested scope:

- Define and implement a small operational surface for recorded `create_correction` requests.
- Keep it inside the v2 operational workflow, not dashboard/reporting.
- Decide whether correction requests are resolved as ledger notes, operator tasks, or future financial correction rows before changing any financial meaning.
- Preserve parser, formula, dashboard, report, import, and bank reconciliation boundaries unless a separate sprint explicitly opens them.

Required opening checks:

- Read `33-director-agent-orchestration-protocol.md` first.
- Assign agents before implementation.
- Reconfirm financial semantics before adding any correction object.
- Require browser/API evidence and disposable DB evidence before acceptance.
