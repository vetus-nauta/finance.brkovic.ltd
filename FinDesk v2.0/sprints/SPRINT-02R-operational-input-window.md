# SPRINT-02R — Operational Input Window UI

Director: Codex Director, FinDesk v2.0

Status: Accepted

## Director Sprint Opening

Sprint: `SPRINT-02R — Operational Input Window UI`

Goal: Build the first FinDesk v2.0 working screen: a fast operational money-writing surface backed by the accepted v2 API/foundation gate.

Required files read:

- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/32-director-addendum-operational-window.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-01R-implementation-report.md`
- `app/v2/Api.php`
- `public/v2-api.php`

Agents assigned:

- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- Financial Logic Engine reviewer
- QA, Audit, and Acceptance Agent

Agent tasks:

- UX: define the operational input window layout, mobile/desktop behavior, and anti-dashboard constraints.
- Frontend: define isolated v2 frontend implementation path and API integration risks.
- Financial Logic: verify the UI exposes operational truth without changing formulas or implying reports.
- QA: define acceptance checks for UI isolation, API usage, text overflow, and no old product logic.

Expected reports:

- ACCEPT/BLOCK for implementing the UI in this sprint.
- Required controls/states.
- Risks and must-not-do items.
- Verification commands.

Exit criteria:

- Separate v2 operational input page exists.
- User can create a workspace if none exists.
- User can write a record quickly.
- Current-month operational history is visible in a vertically scrolling records area.
- Structured parsed/check fields are visible side-by-side on desktop and reachable horizontally on mobile.
- Current figures from the v2 summary are visible near the records.
- No dashboard/report-first UI.
- No old FinDesk product logic reused.
- Static/UI smoke passes.
- Existing v2 gates continue to pass.

Risks:

- Accidentally reviving old FinDesk UI or dashboard composition.
- Turning the first screen into reports/analytics.
- Hiding parser/structured verification.
- Building UI against production DB during tests.
- Treating fixture-scoped parser rules as final parser intelligence.

## Scope

Build the actual first screen, not a landing page.

Allowed:

- New isolated v2 frontend files.
- Read/write through `public/v2-api.php`.
- Operational entry creation.
- Current-month entries list.
- Structured check view.
- Current summary numbers.
- Minimal empty/loading/error/auth states.

Not allowed:

- Dashboard cards as the primary screen.
- Monthly report UI.
- Analytics/reporting sprint.
- Import UI.
- Old `public/assets/app.js` or old FinDesk product logic as UI source.
- Production DB access in tests.

## Handoff Notes

SPRINT-01R branch evidence exists and passed:

```text
npm run smoke:v2
npm run smoke:v2:db
npm run smoke:v2:http
npm run test:v2:fixtures
```

Fixture result:

```text
PASS (12)
BLOCKED / NOT_IMPLEMENTED (0)
```

## Implementation Report

Branch:

```text
findesk-v2-sprint-02r-operational-input-window
```

Files added:

- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_ui_smoke.sh`

Files updated:

- `package.json`

Implemented surface:

- Isolated FinDesk v2 operational page at `public/v2.php`.
- Isolated v2-only CSS/JS under `public/assets/v2/`.
- Workspace selector and create-workspace state.
- Cash/Card flow selector.
- Current-month operational record feed.
- Bottom quick-entry form for raw money records.
- Parse preview action.
- Structured check panel showing the same records with:
  - `date`
  - `raw_text`
  - `flow`
  - `sign`
  - `amount`
  - `direction`
  - `entry_type`
  - `category`
  - `actor`
  - `status`
  - `balance_after`
- Current operational figures:
  - `cash_now`
  - `card_expense_total`
  - `opening_cash`
  - Other review queue count
- Mobile horizontal write/check movement with internal vertical record history.

API integration:

- UI calls only `public/v2-api.php`.
- Routes used:
  - `GET /api/workspaces`
  - `POST /api/workspaces`
  - `GET /api/workspaces/:id/flows`
  - `GET /api/workspaces/:id/entries`
  - `POST /api/workspaces/:id/entries`
  - `GET /api/workspaces/:id/summary`
  - `GET /api/workspaces/:id/other-expenses`
  - `POST /api/workspaces/:id/parse-preview`

Legacy isolation:

- No import of old `public/assets/app.js`.
- No import of old `public/assets/app.css`.
- No `api.php?action` legacy API wiring.
- No old FinDesk dashboard/report/product module revived.

## Verification

Commands run:

```text
php -l public/v2.php
bash -n scripts/v2_operational_ui_smoke.sh
npm run smoke:v2:ui
npm run smoke:v2
npm run smoke:v2:db
npm run smoke:v2:http
npm run test:v2:fixtures
```

Results:

```text
php -l public/v2.php: OK
bash -n scripts/v2_operational_ui_smoke.sh: OK
npm run smoke:v2:ui: OK
npm run smoke:v2: OK
npm run smoke:v2:db: OK on retry after transient local MariaDB bootstrap failure
npm run smoke:v2:http: OK
npm run test:v2:fixtures: PASS (12), BLOCKED / NOT_IMPLEMENTED (0)
```

Note:

- One `npm run smoke:v2:db` attempt and one `npm run test:v2:fixtures` attempt failed before app execution while bootstrapping a temporary local MariaDB datadir.
- Both retries passed without code changes.
- No production DB was used by the disposable DB smoke or fixture runner.

## Agent Acceptance

Final QA acceptance agent: `Fermat`

Verdict:

```text
ACCEPT
```

Evidence accepted by QA:

- `public/v2.php` loads isolated `/assets/v2/app.css` and `/assets/v2/app.js`.
- Operational-first UI exists: workspace controls, current figures, write/check surfaces, quick raw record input.
- API requests are built against `/v2-api.php`.
- Current-month feed requests entries with `year` and `month`.
- Structured check renders the same records and parsed fields.
- CSS provides viewport shell, internal vertical feed scroll, structured overflow, and mobile horizontal snap.
- `scripts/v2_operational_ui_smoke.sh` guards against legacy UI/product references and forbidden report/dashboard framing.

## Residual Risks

- UI smoke is static grep-based coverage, not browser-level authenticated automation.
- Real-device mobile keyboard behavior still needs visual/browser QA.
- Parser behavior remains fixture-scoped, not final parser intelligence.
- Closed-month correction/recalculate workflow is only surfaced as a blocked status message in this UI.
- Category edit/open-details and attachment UX are not completed in this sprint.

## Final Handoff For Next Director

SPRINT-02R is accepted as the first operational input window.

Do not treat this as a dashboard/reporting sprint. The product is still in operational-entry-first mode:

```text
write record -> verify how system read it -> see current operational figures
```

Next recommended implementation sprint:

```text
SPRINT-03R — Browser-Level Operational UI Acceptance
```

Recommended SPRINT-03R goals:

- Add browser-level smoke for `public/v2.php`.
- Prove authenticated create-workspace flow against disposable/local test DB.
- Prove saving `+1000 снял с карты` and `-250 рыба` through UI refreshes feed and structured check.
- Prove mobile write/check horizontal movement.
- Prove double-submit protection.
- Prove offline/draft preservation behavior.
- Add screenshot or DOM evidence for internal vertical scroll and no page/body scroll.

Do not start reports, dashboards, imports, analytics, bank reconciliation, or final parser claims before this browser-level acceptance is green.
