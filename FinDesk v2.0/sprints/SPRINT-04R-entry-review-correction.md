# SPRINT-04R — Operational Entry Review And Correction Workflow

Director: Codex Director, FinDesk v2.0

Status: Accepted

## Director Sprint Opening

Sprint:

```text
SPRINT-04R — Operational Entry Review And Correction Workflow
```

Goal:

- Add a minimal operational review layer inside the accepted v2 input window.
- Let the user select a saved entry, inspect how the system read it, and correct its category.
- Prove Other review resolution and closed-month mutation guard in browser and API smoke.
- Do not start dashboard, reports, imports, analytics, bank reconciliation, final parser claims, or financial formula work.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-03R-browser-ui-acceptance.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_http_api_smoke.php`

Agents assigned:

- Financial Logic Engine Agent
- Frontend UX/Interaction Agent
- Backend/API Harness Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- Financial Logic: define what entry correction may and may not change.
- Frontend UX: define the minimal operational review UI and responsive constraints.
- Backend/API: verify existing routes and safe disposable test coverage.
- QA/Audit: define acceptance blockers and required evidence.

Expected reports:

- ACCEPT/BLOCK for implementation.
- Required UI fields and flows.
- API route and closed-month constraints.
- Browser/API test evidence requirements.

Exit criteria:

- Saved entry can be selected from the operational feed.
- Detail/review panel shows operational fields:
  - `raw_text`
  - `date`
  - `flow`
  - `sign`
  - `amount`
  - `direction`
  - `entry_type`
  - `category`
  - `actor`
  - `status`
  - `balance_after`
  - `notes`
  - `matched_rules`
- Category can be corrected through v2 API only.
- `other_review` entry is visually reviewable and can be resolved by explicit category correction.
- Other review queue/count updates after correction.
- Closed-month category mutation returns visible 409 decision guard and does not mutate the entry.
- SPRINT-03R browser protections remain green.
- Existing v2 smoke/fixture gates remain green.

Risks:

- Accidentally changing financial formulas or parser semantics.
- Treating Other review as non-counted.
- Silently mutating closed months.
- Expanding into dashboard/report/import/analytics.
- Using old FinDesk UI or legacy `/api.php`.
- Testing static DOM instead of real UI/API behavior.

## Agent Reports

Financial Logic Engine Agent: Faraday

```text
ACCEPT
Category correction must not change amount, sign, flow, direction, entry_type, parser semantics, or formulas.
Other review remains counted.
Closed-month mutations must not silently change history.
```

Frontend UX/Interaction Agent: Beauvoir

```text
ACCEPT
Add a third operational panel: Write -> Details -> Check.
Feed rows must be selectable and review rows highlighted.
Category correction must refresh feed, structured check, detail panel, summary, and Other review count.
Mobile must keep horizontal panel navigation and internal vertical scroll.
```

Backend/API Harness Agent: Singer

```text
ACCEPT
Existing APIs support category correction and Other queue.
PATCH /api/entries/:id/category is the safe category correction route.
Closed-month guard already returns 409 closed_month_requires_decision.
Full month close/open and correction execution are out of scope.
```

QA, Audit, and Acceptance Agent: Meitner

```text
ACCEPT
Sprint may proceed only as v2 operational entry review/correction.
Block production DB, reports/dashboard/imports, old UI logic, formula changes, and static-only browser tests.
Required gates include browser smoke, UI smoke, API smoke, fixture runner, DB smoke, and static core smoke.
```

## Implementation Report

Branch:

```text
findesk-v2-sprint-04r-entry-review-correction
```

Files changed:

- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `app/v2/Repository.php`
- `scripts/v2_operational_ui_smoke.sh`
- `scripts/v2_operational_browser_smoke.sh`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_http_api_smoke.sh`
- `scripts/v2_http_api_smoke.php`

Implemented:

- Added a third operational panel: `Write -> Details -> Check`.
- Feed rows are selectable and expose `data-v2-entry-select`.
- Selected rows are highlighted.
- `other_review` rows are highlighted.
- Detail panel shows raw text and structured operational fields.
- Detail panel includes category selector and save action.
- Categories are loaded through `GET /api/workspaces/:workspaceId/categories`.
- Category save uses `PATCH /api/entries/:entryId/category`.
- Other review summary count can jump to the first review item.
- Category correction refreshes entries, Other queue, summary, feed, detail, and structured check.
- Closed-month 409 is surfaced in the status line and detail error area.
- Asset query strings were updated to `sprint04r` for deployment cache safety.

Backend behavior:

- `updateEntryCategory()` remains narrow.
- It updates `category_id`.
- If an entry is `other_review` with category `other` and the new category is not `other`, status becomes `recognized`.
- No amount, sign, flow, direction, entry type, parser behavior, or formula changes were made.
- Closed-month guard still runs before mutation.

Smoke/test coverage added:

- UI static smoke now requires detail/category/review hooks and category route marker.
- Browser smoke now proves:
  - entry selection opens details;
  - detail panel shows required operational fields;
  - Other review row is highlighted;
  - category correction sends exactly one PATCH;
  - Other review count drops after correction;
  - structured check reflects corrected category;
  - closed-month category PATCH returns 409;
  - no optimistic mutation appears after closed-month rejection;
  - mobile reaches Details and Check horizontally.
- Browser closed-month smoke is date-agnostic: it reads the current UI date and closes that year/month in the disposable DB.
- HTTP API smoke now proves:
  - Other review category correction empties the queue;
  - category correction changes `other_review` to `recognized`;
  - closed-month category patch returns 409;
  - closed-month delete returns 409;
  - rejected closed-month mutation leaves entry unchanged.

## Verification

Commands run:

```text
php -l public/v2.php
php -l app/v2/Repository.php
php -l scripts/v2_http_api_smoke.php
bash -n scripts/v2_operational_browser_smoke.sh
bash -n scripts/v2_http_api_smoke.sh
bash -n scripts/v2_operational_ui_smoke.sh
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2:ui
npm run smoke:v2
npm run smoke:v2:browser
npm run smoke:v2:http
npm run test:v2:fixtures
npm run smoke:v2:db
```

Results:

```text
php syntax checks: OK
bash syntax checks: OK
node syntax check: OK
npm run smoke:v2:ui: OK
npm run smoke:v2: OK
npm run smoke:v2:browser: OK
npm run smoke:v2:http: OK
npm run test:v2:fixtures: PASS (12), BLOCKED / NOT_IMPLEMENTED (0)
npm run smoke:v2:db: OK
```

Browser smoke output includes:

```text
Entry detail selection: OK
Other review category correction: OK
Closed-month category guard: OK
FinDesk v2 browser UI smoke: OK
```

## Final QA Acceptance

Final QA acceptance agent: Aristotle

Verdict:

```text
ACCEPT
```

QA accepted evidence:

- Details/review UI is present.
- Detail fields include required operational fields plus `notes` and `matched_rules`.
- Feed rows are selectable and highlighted.
- Category correction uses `PATCH /api/entries/:id/category`.
- Backend resolution is narrow and does not rewrite formulas/parser behavior.
- Closed-month guard runs before mutation.
- Browser smoke proves selection, Other resolution/count drop, and non-optimistic closed-month rejection.
- HTTP smoke proves Other resolution and closed-month category/delete 409.
- Smoke scripts use disposable local DB and `--skip-networking`.
- UI/API isolation remains enforced.

Residual risks:

- Mobile coverage is Chromium emulation, not real phone/keyboard QA.
- Screenshots are generated but not visual-diffed.
- Closed-month correction decision execution is not implemented.
- Full entry edit/rewrite is not implemented; this sprint only supports category correction.

## Final Handoff For Next Director

SPRINT-04R is accepted as the operational entry review/category correction layer.

The accepted product surface remains:

```text
write record -> select/review record -> correct category if needed -> verify structured result
```

Next recommended implementation sprint:

```text
SPRINT-05R — Closed-Month Correction Decision Workflow
```

Recommended SPRINT-05R goals:

- Keep the UI operational-entry-first.
- Add explicit closed-month decision UI for:
  - create correction
  - recalculate chain
  - cancel
- Add backend route(s) only if required by the existing closed-month contract.
- Browser-test that cancel leaves data unchanged.
- Browser-test correction path without silent historical mutation.
- Keep reports, dashboard, imports, analytics, bank reconciliation, and final parser claims out of scope.
