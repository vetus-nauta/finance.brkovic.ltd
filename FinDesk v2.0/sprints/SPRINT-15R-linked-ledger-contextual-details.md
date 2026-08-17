# SPRINT-15R — Linked Ledger + Contextual Details

Status: Accepted

## Director Sprint Opening

Sprint:
SPRINT-15R — Linked Ledger + Contextual Details

Goal:
Turn the operational journal and structured check into two linked row-by-row views of the same current-month entries, and move Entry details out of the permanent workspace grid into a contextual drawer/sheet opened from a selected row.

Required files read:
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/32-director-addendum-operational-window.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-14R-responsive-layout-redesign-handoff.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:
- iOS-Native UX Layout Agent: Kepler
- Frontend Performance and Interaction Agent: Lorentz
- QA, Audit, and Acceptance Agent: Parfit
- Financial Logic Engine Agent as reviewer: Dewey

Agent tasks:

Agent:
iOS-Native UX Layout Agent

Scope:
Validate the user-proposed UX architecture: Entry details as contextual drawer/sheet, and Operational journal plus Structured check as linked row views.

Files to read:
- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `20-definition-of-done.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `SPRINT-14R-responsive-layout-redesign-handoff.md`

What to check:
- Whether permanent Entry details wastes workspace.
- Whether linked row numbers improve user trust.
- Device-class behavior for desktop, iPad 11+, iPad mini, and phone.

What to change if allowed:
- Review only at opening stage.

What not to touch:
- Financial formulas, parser rules, reports, API semantics, import, schema, auth, deploy, storage.

Report required:
ACCEPT or REJECT with strict device-class recommendations and risks.

Agent:
Frontend Performance and Interaction Agent

Scope:
Define implementation plan for DOM, CSS, JavaScript, scroll ownership, contextual details, linked row selection, and desktop structured-focus behavior.

Files to read:
- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `20-definition-of-done.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_browser_smoke.cjs`

What to check:
- Current permanent 3-panel layout.
- Selection state and data hooks.
- Mobile Write/Check model.
- Browser smoke assumptions tied to old detail panel behavior.

What to change if allowed:
- Review only at opening stage.

What not to touch:
- Backend finance/API/parser/report/import/schema/deploy/auth/storage behavior.

Report required:
Implementation plan with file ownership, risks, and required test updates.

Agent:
QA, Audit, and Acceptance Agent

Scope:
Define acceptance matrix for linked ledger, contextual detail drawer/sheet, no page scroll, input reachability, and finance-safety evidence.

Files to read:
- `20-definition-of-done.md`
- `33-director-agent-orchestration-protocol.md`
- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `SPRINT-14R-responsive-layout-redesign-handoff.md`
- `scripts/v2_operational_browser_smoke.cjs`

What to check:
- Governance.
- Required screenshots and metrics.
- Row-linking proof.
- Detail interaction proof.
- Regression commands.

What to change if allowed:
- Review only at opening stage.

What not to touch:
- Product code at opening stage.

Report required:
ACCEPT/REJECT criteria, screenshots, metrics, full gate requirements, and rejection rules.

Agent:
Financial Logic Engine Agent as reviewer

Scope:
Define finance-safety boundaries for UI-only linked rows and contextual details.

Files to read:
- `14-calculation-contract.md`
- `16-api-contract.md`
- `20-definition-of-done.md`
- `SPRINT-14R-responsive-layout-redesign-handoff.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/assets/v2/app.js`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

What to check:
- Finance invariants.
- Off-limits files.
- Structured check required fields.
- Other review visibility.
- Closed-month behavior.

What to change if allowed:
- Review only unless a finance regression is found and explicitly scoped.

What not to touch:
- Formulas, parser, reports, API semantics, import, schema, auth, deploy, storage.

Report required:
ACCEPT/REJECT finance-safety verdict and verification list.

Expected reports:
- UX: device-class UX verdict.
- Frontend/Interaction: implementation plan and risks.
- QA: acceptance matrix and rejection rules.
- Finance: UI-only boundaries and no-regression requirements.

Exit criteria:
- Entry details is no longer a permanent workspace column.
- Entry details opens contextually from a journal row or structured check row.
- Entry details closes explicitly and by Escape where applicable.
- Closing Entry details preserves the selected row and draft input.
- Operational journal and Structured check share visible row numbers.
- Selecting a row in either surface highlights the same entry in both surfaces.
- Browser evidence proves the selected journal row and structured check row share the same entry id/raw text.
- Desktop and iPad 11+ show linked full workspace behavior.
- Desktop structured-check focus compacts the journal into a row-number/human-anchor rail and expands structured check.
- Phone and iPad mini keep only Write and Check as primary modes; details is contextual sheet/drawer, not a third primary panel.
- Phone/iPad mini vertical scroll remains inside the feed/history, while horizontal movement reveals structured check.
- `date`, `raw_text`, `flow`, `sign`, `amount`, `direction`, `entry_type`, `category`, `actor`, `status`, and `balance_after` remain visible in Structured check.
- Other review counter, row highlight, jump, category correction, and closed-month decision flows remain visible and working.
- Input and Save remain visible/clickable in reduced phone viewport.
- No body/page scroll or document overhang.
- No backend finance/API/parser/report/import/schema/auth/deploy/storage behavior changes.
- All assigned agents return final ACCEPT reports before sprint closure.

Risks:
- Pixel-based scroll synchronization can jitter if journal and structured rows have different heights.
- Entry details can become another hidden primary mode if drawer/sheet behavior is not disciplined.
- Structured check can overpower phone layouts if dense columns are treated as the writing surface.
- Moving detail markup can break category, attachment, and closed-month controls if data hooks change.
- Browser smoke currently encodes old detail-as-panel behavior and must be updated with the UI.

## Initial Agent Reports

iOS-Native UX Layout Agent:
ACCEPT with conditions. Entry details should stop competing as a third primary panel. Journal and Structured check should become two synchronized views of the same operational rows. Phone and iPad mini must have only Write/Check as primary modes; details opens as contextual sheet/drawer. Desktop and iPad 11+ use linked full workspace behavior. Shared row numbers and selected row state are mandatory.

Frontend Performance and Interaction Agent:
ACCEPT to implement. Current `v2.php` renders three permanent panels inside `.v2-horizontal`; this must become two panels plus contextual details outside the grid. `app.js` already has central `selectedEntryId` but structured rows are not selectable. Smoke tests must move with the UI because they currently assume details is a horizontal panel.

QA, Audit, and Acceptance Agent:
ACCEPT criteria defined. The sprint must prove linked entry ids/raw text/order, contextual details from journal and structured check, no page scroll, input reachability, structured finance fields, Other review, closed-month behavior, screenshots/metrics, and full regression gate.

Financial Logic Engine Agent:
ACCEPT only as UI-only work. Do not change `app/v2/Api.php`, `app/v2/Repository.php`, schema, parser/import files, auth/storage/deploy, calculation/API contracts, or HTTP smoke expected finance values. Structured check must still expose all required finance fields and Other review must remain visible.

## Implementation Work Packages

Package A — UI Markup and Interaction:
- Owner: Frontend worker.
- Files allowed: `public/v2.php`, `public/assets/v2/app.js`, `public/assets/v2/app.css`.
- Required output: contextual detail drawer/sheet, linked row numbers, structured check row selection, desktop structured-focus mode, mobile Write/Check only.

Package B — Browser Evidence:
- Owner: QA worker.
- Files allowed: `scripts/v2_operational_browser_smoke.cjs`.
- Required output: updated smoke assertions for linked rows, contextual detail open/close, mobile Write/Check, desktop structured-focus, layout metrics, screenshots.

Package C — Director Integration:
- Owner: Director.
- Files allowed: sprint handoff and final evidence notes.
- Required output: final report, agent reports, changed files, test evidence, accept/reject decision.

## Hard Boundaries

Do not change:
- `app/v2/Api.php`
- `app/v2/Repository.php`
- SQL/schema/migration files
- parser/import files
- auth/storage/deploy files
- calculation or API contracts
- `scripts/v2_http_api_smoke.php` expected finance values

Allowed UI files:
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_browser_smoke.cjs`
- this sprint handoff file

## Worker Agent Reports

Frontend Performance and Interaction Worker:
ACCEPT. Implemented the linked ledger UI in `public/v2.php`, `public/assets/v2/app.js`, and `public/assets/v2/app.css`.

Delivered:
- Entry details moved out of the permanent `.v2-horizontal` workspace and into a contextual dialog/drawer/sheet.
- Operational journal and Structured check now share visible row numbers and entry identity hooks.
- Journal rows and structured rows can both open the same contextual Entry details.
- Mobile and iPad mini primary modes are Write and Check only.
- Desktop and iPad 11+ keep a two-surface workspace.
- Desktop structured-check focus compacts journal width and gives Structured check the larger working area.
- Category correction, attachments, Other review, offline draft, and closed-month controls remain wired to the existing UI state.

QA, Audit, and Acceptance Worker:
ACCEPT. Updated `scripts/v2_operational_browser_smoke.cjs` to test the new architecture instead of the old permanent details panel.

Delivered evidence checks:
- Linked journal/check rows share `entry_id`, row number, and raw text.
- Selecting a journal row opens contextual details.
- Selecting a structured check row opens contextual details.
- Closing details preserves selected row and draft input.
- Entry details is not a permanent `.v2-horizontal` panel.
- Mobile/iPad mini expose Write and Check, without Details as a third primary mode.
- Desktop structured-check focus expands check and compacts the journal.
- Body/page scroll remains disabled; scroll ownership stays inside feed/check/detail containers.
- Structured finance fields remain visible in Structured check.
- Attachments, Other review fallback, refresh preservation, double-submit protection, offline draft, and closed-month category decisions still pass browser smoke.

Financial Logic Engine Reviewer:
ACCEPT. No finance/backend files were changed. Calculation, parser, report, API, import, schema, auth, deploy, and storage behavior remain outside the sprint changes.

## Final Implementation Notes

Changed files:
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_browser_smoke.cjs`
- `FinDesk v2.0/sprints/SPRINT-15R-linked-ledger-contextual-details.md`

Implemented behavior:
- Entry details is contextual, not a workspace column.
- Operational journal and Structured check operate as linked row views over the same entries.
- Clicking either journal or structured row opens the matching details context.
- Shared row numbering gives the user a stable Excel-like row identity between the two work areas.
- Desktop structured focus supports the user's intended flow: journal becomes a compact anchor surface while Structured check becomes the main inspection area.
- Mobile/iPad mini retain a vertical-first journal with horizontal access to Structured check.

Evidence files:
- `test-results/v2-browser-smoke/layout-metrics.json`
- `test-results/v2-browser-smoke/desktop-operational-window.png`
- `test-results/v2-browser-smoke/desktop-check-focus.png`
- `test-results/v2-browser-smoke/desktop-standard-1365x820.png`
- `test-results/v2-browser-smoke/desktop-standard-1440x900.png`
- `test-results/v2-browser-smoke/ipad-11-portrait.png`
- `test-results/v2-browser-smoke/ipad-11-landscape.png`
- `test-results/v2-browser-smoke/ipad-mini-portrait.png`
- `test-results/v2-browser-smoke/ipad-mini-landscape.png`
- `test-results/v2-browser-smoke/phone-portrait-layout-390x844.png`
- `test-results/v2-browser-smoke/phone-small-360x640.png`
- `test-results/v2-browser-smoke/phone-landscape-constrained-844x390.png`
- `test-results/v2-browser-smoke/mobile-structured-check.png`
- `test-results/v2-browser-smoke/mobile-reduced-viewport-fit.png`
- `test-results/v2-sprint-15r/full-gate.log`
- `test-results/v2-manual-responsive/manual-responsive-report.json`
- `test-results/v2-manual-responsive/contact-desktop-ipad11.jpg`
- `test-results/v2-manual-responsive/contact-ipad-mini.jpg`
- `test-results/v2-manual-responsive/contact-phone.jpg`

Verification:
- `php -l public/v2.php`: PASS
- `node --check public/assets/v2/app.js`: PASS
- `node --check scripts/v2_operational_browser_smoke.cjs`: PASS
- `git diff --check`: PASS
- `npm run smoke:v2`: PASS
- `npm run smoke:v2:auth`: PASS
- `npm run test:v2:fixtures`: PASS, 17 tests
- `npm run smoke:v2:http`: PASS
- `npm run smoke:v2:db`: PASS
- `npm run smoke:v2:ui`: PASS
- `npm run smoke:v2:browser`: PASS
- `npm run smoke:v2:deploy`: PASS with 2 known deployment warnings
- `scripts/v2_manual_responsive_walkthrough.sh`: PASS, 42 screenshots across desktop, iPad 11 portrait/landscape, iPad mini portrait/landscape, phone portrait/landscape

Manual responsive walkthrough:
- Desktop 1365x820: PASS for journal scroll, structured check internal scroll, structured-check focus, details from journal row, details from structured row.
- iPad 11 portrait 834x1194: PASS for full workspace grid, journal scroll, structured check internal scroll, structured-check focus, contextual details.
- iPad 11 landscape 1194x834: PASS for full workspace grid, journal scroll, structured check internal scroll, structured-check focus, contextual details.
- iPad mini portrait 768x1024: PASS for Write/Check tabs, journal scroll, horizontal structured check access, contextual details.
- iPad mini landscape 1024x768: PASS for Write/Check tabs, journal scroll, horizontal structured check access, contextual details.
- Phone portrait 390x844: PASS for Write/Check tabs, journal scroll, horizontal structured check access, contextual details, input and Save visibility.
- Phone landscape 844x390: PASS for Write/Check tabs, journal scroll, horizontal structured check access, contextual details, input and Save visibility.

Manual UX observations:
- iPad mini and phone Write view intentionally leaves a visible edge of Structured check as the horizontal linked surface. This is functional and contract-compliant, but may feel visually like an exposed second panel rather than a clean single-mode tab.
- Phone landscape details is functional and contained, but visually dense because the sheet height is only about 304px on an 844x390 viewport. Internal scrolling is expected there.

Deployment warnings observed:
- `storage/logs/auth_codes.log exists locally; production deploy must purge/avoid this file`
- `FINDESK_V2_PRODUCTION_BASE_URL is not set; live HTTP deny checks were skipped`

Director Decision:
ACCEPT SPRINT-15R as local implementation and browser-evidence accepted. This does not accept production deployment; SPRINT-11R production deployment remains blocked until live production evidence exists.
