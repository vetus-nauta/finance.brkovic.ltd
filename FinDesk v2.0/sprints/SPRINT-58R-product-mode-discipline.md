# SPRINT-58R — Product Mode Discipline

## Director Sprint Opening

Sprint:
SPRINT-58R — Product Mode Discipline

Goal:
Turn the current FinDesk v2.0 shell from a technically correct tool into a product-clear workspace where every screen has one human task:

- Operational journal: write and verify current operational entries.
- Archive/report viewer: browse saved report documents.
- Report modal: inspect, send, print, cancel, or return a created report.
- Summary: answer period-result questions from operational entries.
- Training: help the dictionary learn using human language.
- Hall: choose/create/manage workspaces and roles.
- Employee mode: simple accountable-money reporting without full admin picture.

Required files read:

- `START_HERE_DIRECTOR.md`
- `33-director-agent-orchestration-protocol.md`
- `04-responsive-layout-contract.md`
- `20-definition-of-done.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `36-layer1-summary-and-forecast.md`

Agents assigned:

- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- Financial Logic Engine Agent as reviewer
- QA, Audit, and Acceptance Agent

Agent tasks:

### iOS-Native UX Layout Agent

Scope:
Product taxonomy and interaction model.

Files to read:

- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`

What to check:

- Whether screens have one clear user task.
- Whether archive/report/summary/training are visually and behaviorally distinct.
- Whether mobile is a financial-notes system, not a shrunken desktop.

What to change if allowed:
No code changes in this pass.

What not to touch:
Financial formulas, parser, report generation, deployment behavior.

Report required:
Screen taxonomy, top UX defects, first-pass acceptance criteria.

### Frontend Performance and Interaction Agent

Scope:
Bounded UX-state implementation.

Files to read:

- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`

What to check:

- Operational journal remains the default working screen.
- Archive/report viewer is not automatically substituted for the journal.
- Existing report rows open report modal without stealing journal behavior.
- Non-relevant technical controls are hidden in the wrong mode.

What to change if allowed:
Only frontend files listed above.

What not to touch:
Backend, DB schema, parser, report API, formulas, deployment.

Report required:
Changed files, before/after behavior, tests, residual risks.

### Financial Logic Engine Agent as reviewer

Scope:
Financial safety review.

Files to read:

- `20-definition-of-done.md`
- `31-operational-input-window-contract.md`
- `36-layer1-summary-and-forecast.md`
- `app/v2/Repository.php`
- `public/assets/v2/app.js`

What to check:

- Operational entries remain source of truth.
- Report snapshots do not become editable truth.
- Closed report/month edits remain protected.
- UI state changes cannot imply recalculation without explicit action.

What to change if allowed:
No code changes.

What not to touch:
All formulas, import mapping, parser behavior.

Report required:
Findings by severity, hard no-touch list, smoke checks.

### QA, Audit, and Acceptance Agent

Scope:
Browser and acceptance matrix.

Files to read:

- `20-definition-of-done.md`
- `04-responsive-layout-contract.md`
- `scripts/v2_report_fragment_browser_smoke.cjs`
- `public/v2.php`
- `public/assets/v2/app.js`

What to check:

- Journal does not disappear after report closure.
- Report rows open the report modal.
- Input remains visible in operational journal.
- Report/archive viewer, when present, does not show entry input.
- Desktop and mobile bounds remain inside viewport.

What to change if allowed:
No production code changes unless explicitly requested by Director.

What not to touch:
Real Claudia Z data.

Report required:
Tests run, screenshots, pass/fail list, product blockers.

Expected reports:

- UX taxonomy and defects.
- Frontend patch report.
- Finance safety report.
- QA acceptance report.

Exit criteria:

- Operational journal always remains available as the working surface.
- Closed report rows are shown as closed objects inside the journal, not as a replacement for the journal.
- Report/archive viewing is visually distinct and deliberate.
- Summary and training do not expose unnecessary service language in primary view.
- No financial/parser/report-generation behavior is changed.
- Browser smoke checks pass.

Risks:

- Overcorrecting the archive and hiding access to created report documents.
- Confusing report storage with operational journal history.
- Introducing mobile regressions while fixing desktop product clarity.
- Accidentally treating report snapshots as source-of-truth records.

## Agent Reports Received

### iOS-Native UX Layout Agent

Status:
Received.

Key findings:

- Archive/report viewer must become an explicit product role, not an automatic journal replacement.
- Operational journal must remain the daily working surface with input, history, and structured verification.
- Report modal should feel like a document workflow, not like another technical summary.
- Training and summary still expose too much service/internal language in primary view.
- Mobile needs a deliberate financial-notes product mode, not a shrunken desktop.

Accepted:
Screen taxonomy and P0/P1 UX defect list.

### Frontend Performance and Interaction Agent

Status:
Received.

Key implementation:

- Added explicit `Отчеты` rail control for report viewing.
- Operational journal no longer automatically becomes an archive/report viewer.
- In report-view mode, headings change to `Просмотр отчетов` and `Индекс отчетов`.
- Input and new-report controls are hidden in report-view mode.
- Journal remains the default operational screen.

Accepted:
Bounded frontend patch.

### Financial Logic Engine Agent as reviewer

Status:
Received.

Key findings:

- Operational entries remain the only source of truth.
- Reports, packages, snapshots, and summaries remain derived/readback surfaces.
- Product cleanup must not remove visible report locks or closed-month guards.
- Financial state transitions still require explicit confirmation.

Accepted:
Hard no-touch list and finance smoke criteria.

### QA, Audit, and Acceptance Agent

Status:
Received.

Key checks:

- Local site responded at `http://127.0.0.1:18889/v2.php`.
- Browser report-fragment smoke passed in QA run.
- Desktop 1440x900 stayed within viewport.
- Mobile 390x844 had no body scroll and input remained reachable.
- Real Claudia Z data was not modified.

Accepted:
QA matrix and screenshots.

## Director Acceptance

Status:
Accepted for first product-mode pass.

Accepted work:

- Product taxonomy for screen roles.
- Explicit report-view mode separate from operational journal.
- Operational journal remains available as working surface.
- Report context opens created report without replacing the journal.
- Smoke script updated to assert the new product behavior instead of the old collapsed-report-row behavior.

Rejected work:

- Automatic archive/report substitution based only on report-locked rows.
- Treating report snapshots/packages as editable source-of-truth surfaces.
- Hiding report-lock/closed-month safety context for visual cleanliness.

Files changed in this sprint:

- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_report_fragment_browser_smoke.cjs`
- `FinDesk v2.0/sprints/SPRINT-58R-product-mode-discipline.md`

Director checks:

- `php -l public/v2.php`
- `php -l app/v2/Repository.php`
- `php -l app/v2/Api.php`
- `node --check public/assets/v2/app.js`
- `node --check scripts/v2_report_fragment_browser_smoke.cjs`
- `git diff --check -- public/v2.php public/assets/v2/app.js public/assets/v2/app.css FinDesk v2.0/sprints/SPRINT-58R-product-mode-discipline.md`
- `FINDESK_V2_REPORT_FRAGMENT_BASE=http://127.0.0.1:18889 node scripts/v2_report_fragment_browser_smoke.cjs`

Director smoke evidence:

- `test-results/v2-report-fragment-browser/SPRINT_PROD_UX_01_DIRECTOR_E_1786554246`

Residual risks:

- `Просмотр отчетов` is still implemented inside the operational workspace shell, not yet as a fully independent top-level screen.
- Report viewer cards still need further document polish.
- Summary and training still need their own product-language cleanup passes.
- Structural-check report badge click can be polished further; stable report opening path is the report context button.

What must not be touched:

- Parser formulas.
- Cash/Card accounting semantics.
- Opening balance and commercial income separation.
- Other/review visibility.
- Closed-month backend guards.
- Report-lock backend guards.
- Import and deployment behavior.

Next sprint:
SPRINT-59R — Summary and Report Document Product Polish.

Paste-to-next-director prompt:

```text
You are the next Director for FinDesk v2.0.

Read:
- START_HERE_DIRECTOR.md
- 33-director-agent-orchestration-protocol.md
- 20-definition-of-done.md
- 31-operational-input-window-contract.md
- 32-director-addendum-operational-window.md
- 36-layer1-summary-and-forecast.md
- sprints/SPRINT-58R-product-mode-discipline.md

Current accepted state:
- Operational journal remains the default source-of-truth working screen.
- Report viewing is explicit through `Отчеты` / report context, not automatic replacement.
- Reports are derived documents, not editable truth.

Next task:
Run SPRINT-59R — Summary and Report Document Product Polish.
Goal:
- Make report modal/readback feel like a finished document workflow.
- Make Summary answer human period questions, not expose technical dashboard language.
- Keep all totals source-traceable to operational entries.

Assign:
- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- Financial Logic Engine Agent as reviewer
- QA, Audit, and Acceptance Agent

Do not change financial formulas, parser, import, deploy, or backend guards.
```
