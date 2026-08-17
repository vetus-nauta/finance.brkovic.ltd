# SPRINT-14R — Responsive Layout Redesign Handoff

Status: Accepted

## Why This Handoff Exists

The project direction changed after visible browser testing.

SPRINT-13R fixed a concrete viewport-fit bug, but the user correctly rejected continuing to micro-tune one compressed mobile layout. The next sprint must return to the original responsive-layout contract and design the screen system properly by device class.

Do not treat the current compact mobile CSS as the final UX architecture.

## Current Verified Project State

Repository:
`vetus-nauta/finance.brkovic.ltd`

Working folder:
`FinDesk v2.0/`

Current branch at handoff:
`findesk-v2-sprint-13r-viewport-fit`

Recent commits:
- `f6d4f35` — Improve FinDesk v2 compact mobile layout
- `6c20425` — Fix FinDesk v2 viewport fit on mobile
- `6205c8d` — Harden FinDesk v2 other review fallback
- `dd7b6d5` — Prepare FinDesk v2 sprint 11R deployment gate
- `494fb67` — Accept FinDesk v2 sprint 10R release candidate gate

Important branch note:
SPRINT-13R was created after SPRINT-12R, so it includes the SPRINT-12R parser fallback work. If PRs are not merged in order, verify branch ancestry before merging.

## What Is Accepted

SPRINT-10R:
Clean Core MVP release-candidate gate passed from disposable setup.

SPRINT-11R:
Production deployment is not accepted. Deployment gate/preflight/runbook exists, but live production evidence is still missing.

SPRINT-12R:
Other review fallback was fixed for explicit manual cash-negative strings such as `other expense` and `unknown_expense`, with fixture, HTTP, and browser evidence.

SPRINT-13R:
A narrow viewport-fit bug was fixed so the page no longer collapses inside a small browser viewport. Browser smoke now guards:
- shell bounds inside viewport;
- no body/html horizontal overhang;
- compact inputbar;
- visible workspace;
- reachable Save button.

## What Is Not Accepted

The current mobile layout is not the final responsive UX.

Do not continue by shrinking buttons and panels one-by-one.

Do not accept a mobile screen where the user mostly sees:
- topbar;
- summary cards;
- Cash/Card buttons;
- Write/Details/Check buttons;
- bottom input;
- and too little journal content.

Do not treat desktop, iPad, iPad mini, and phone as one CSS breakpoint problem.

## Source Of Truth For Next Sprint

Read first:
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/32-director-addendum-operational-window.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-10R-mvp-release-candidate-gate.md`
- `FinDesk v2.0/sprints/SPRINT-11R-production-deployment-smoke-rollback-gate.md`
- `FinDesk v2.0/sprints/SPRINT-12R-other-review-fallback-hardening.md`
- `FinDesk v2.0/sprints/SPRINT-13R-viewport-fit-inputbar-hardening.md`

Core responsive contract from `04-responsive-layout-contract.md`:
- page itself must not scroll;
- scroll only inside controlled internal areas;
- phone and iPad mini use mobile financial-notes system;
- iPad 11+ and desktop use full workspace system;
- vertical movement on mobile scrolls current-month notes;
- horizontal movement reveals structured/report-ready view;
- input remains reachable with keyboard open.

## User Direction For Next Director

The user explicitly changed the vector:

- Mobile must be adaptive, but mobile is vertical-first only.
- Phone must not be a compressed desktop/tablet workspace.
- iPad mini needs its own behavior, not blindly the same as phone.
- Regular iPad / iPad 11+ must support portrait and landscape properly.
- Desktop needs a separate good layout for a standard computer screen.
- Horizontal movement is needed where it belongs.
- Vertical scroll is needed where it belongs.
- Text may be smaller if that helps the actual work surface.
- The mobile input field must be visible and usable.
- The next sprint needs UX and QA agents, not just CSS tweaking.

## Required Agents For SPRINT-14R

Do not work alone.

Assign at minimum:
- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent
- Financial Logic Engine Agent as reviewer

Optional if available:
- Localization/Linguistic Agent for Russian/English label density and shorter mobile labels.

## Proposed Next Sprint

Sprint:
SPRINT-14R — Responsive Layout Architecture Redesign

Goal:
Replace the current breakpoint-tweaking approach with an explicit responsive layout architecture for:
- phone portrait;
- phone landscape if supported or explicitly constrained;
- iPad mini portrait;
- iPad mini landscape;
- iPad 11+ portrait;
- iPad 11+ landscape;
- desktop standard viewport.

## SPRINT-14R Director Opening Draft

Director Sprint Opening

Sprint:
SPRINT-14R — Responsive Layout Architecture Redesign

Goal:
Design and implement the correct responsive operational journal architecture from the original contract, without changing financial logic or product formulas.

Required files read:
- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `20-definition-of-done.md`
- `33-director-agent-orchestration-protocol.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:
- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent
- Financial Logic Engine Agent as reviewer

Agent tasks:
- iOS-Native UX Layout Agent: define device-class interaction model and ergonomic hierarchy for phone, iPad mini, iPad 11+, and desktop.
- Frontend Performance and Interaction Agent: implement layout changes with stable dimensions, no body scroll, and proper internal scroll containers.
- QA, Audit, and Acceptance Agent: define and run screenshot/metric acceptance for all target viewports.
- Financial Logic Engine Agent: verify that no financial formulas, parser rules, reports, or entry semantics changed.

Expected reports:
- Each agent must return ACCEPT or REJECT with screenshots/metrics or concrete blockers.

Exit criteria:
- Phone portrait shows the operational journal as the dominant surface, with vertical note history scroll and reachable input.
- Phone structured view is reachable by horizontal movement or explicit view switch without hiding the input unexpectedly.
- iPad mini portrait/landscape are intentionally designed and tested.
- iPad 11+ portrait/landscape use a full workspace system, not phone UI.
- Desktop standard viewport has a proper desktop workspace, not stretched mobile.
- No body/page scroll.
- Internal scroll containers are correct.
- Text and controls fit without becoming the whole screen.
- Browser smoke includes screenshots and metrics for every accepted device class.
- No finance/formula/parser/report changes.

Risks:
- Current CSS has accumulated tactical fixes from SPRINT-13R.
- Current mobile tabs/rail may need redesign, not more shrinking.
- The inputbar location/shape may differ by device class.
- Existing browser smoke must be updated to check visual hierarchy, not only “fits in viewport.”

## Active Director Sprint Opening

Director:
Codex

Status:
Accepted

Started from branch:
`findesk-v2-sprint-13r-viewport-fit`

Director Sprint Opening

Sprint:
SPRINT-14R — Responsive Layout Architecture Redesign

Goal:
Replace the tactical SPRINT-13R compact mobile layout with an explicit responsive operational-journal architecture by device class, while preserving FinDesk v2 financial logic, parser behavior, report behavior, deploy behavior, and the operational input window as the first working surface.

Required files read:
- `FinDesk v2.0/START_HERE_DIRECTOR.md`
- `FinDesk v2.0/sprints/SPRINT-14R-responsive-layout-redesign-handoff.md`
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/32-director-addendum-operational-window.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-10R-mvp-release-candidate-gate.md`
- `FinDesk v2.0/sprints/SPRINT-11R-production-deployment-smoke-rollback-gate.md`
- `FinDesk v2.0/sprints/SPRINT-12R-other-review-fallback-hardening.md`
- `FinDesk v2.0/sprints/SPRINT-13R-viewport-fit-inputbar-hardening.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:
- iOS-Native UX Layout Agent: Faraday
- Frontend Performance and Interaction Agent: Pauli
- QA, Audit, and Acceptance Agent: Pascal
- Financial Logic Engine Agent as reviewer: Sartre

Agent tasks:

Agent:
iOS-Native UX Layout Agent

Scope:
Define the device-class interaction model and ergonomic hierarchy for phone, iPad mini, iPad 11+, and desktop.

Files to read:
- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `20-definition-of-done.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`

What to check:
- Phone is vertical-first and the operational journal is the dominant visible working field.
- iPad mini is a deliberate mobile financial-notes system, not a blind phone clone.
- iPad 11+ portrait/landscape are full workspace systems.
- Desktop is a standard-monitor workspace, not stretched mobile.

What to change if allowed:
- UX architecture recommendations only at opening stage.

What not to touch:
- Financial formulas, parser rules, reports, API semantics, deploy behavior.

Report required:
ACCEPT or REJECT with device-class model, risks, and acceptance evidence required.

Agent:
Frontend Performance and Interaction Agent

Scope:
Own CSS/DOM interaction architecture, scroll containment, keyboard-safe input reachability, and browser-smoke metric updates during implementation.

Files to read:
- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `20-definition-of-done.md`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_operational_browser_smoke.cjs`

What to check:
- No body/page scroll.
- Internal scroll belongs to controlled containers.
- Input and Save remain visible in reduced/keyboard-like viewport.
- Horizontal structured view exists only where contractually useful.
- Full workspace grid applies to iPad 11+ and desktop.

What to change if allowed:
- Responsive layout CSS and view-switch mechanics only, with stable dimensions and no finance behavior changes.

What not to touch:
- Parser, formulas, reports, deploy/auth/storage, import, schema, month-close logic.

Report required:
ACCEPT or REJECT with implementation risks, smoke metrics, changed files, and screenshots after implementation.

Agent:
QA, Audit, and Acceptance Agent

Scope:
Define and enforce SPRINT-14R acceptance evidence.

Files to read:
- `04-responsive-layout-contract.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `20-definition-of-done.md`
- `33-director-agent-orchestration-protocol.md`
- SPRINT-10R through SPRINT-13R handoffs
- `scripts/v2_operational_browser_smoke.cjs`

What to check:
- Required agent reports exist before sprint closure.
- Every target viewport has screenshot and metric evidence.
- Browser smoke proves hierarchy and interaction, not only viewport fit.
- Existing MVP gates remain green.

What to change if allowed:
- Browser-smoke acceptance checks and evidence matrix only.

What not to touch:
- Product formulas, parser, reports, production deploy acceptance.

Report required:
ACCEPT or REJECT with viewport matrix, screenshots/metrics list, regression commands, and blockers.

Agent:
Financial Logic Engine Agent as reviewer

Scope:
Review responsive implementation for finance safety.

Files to read:
- `14-calculation-contract.md`
- `16-api-contract.md`
- `20-definition-of-done.md`
- SPRINT-10R, SPRINT-12R, SPRINT-13R handoffs
- `app/v2/Repository.php`
- `app/v2/Api.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

What to check:
- No formulas, parser semantics, report buckets, entry meanings, closed-month decisions, or Other review behavior changed.
- Structured verification still exposes date, raw text, flow, sign, amount, direction, entry type, category, actor, status, and `balance_after`.
- Other review row/counter/queue remain visible after layout changes.

What to change if allowed:
- Review only unless a finance regression is found and explicitly scoped.

What not to touch:
- Repository/API finance logic, schema, import, deploy, auth/storage.

Report required:
ACCEPT or REJECT with finance invariants, off-limits files, and regression evidence.

Expected reports:
- iOS-Native UX Layout Agent: device-class UX model and readiness verdict.
- Frontend Performance and Interaction Agent: current layout structure, implementation risk report, and later changed-files/evidence report.
- QA, Audit, and Acceptance Agent: acceptance matrix, viewport screenshot/metric requirements, and final gate verdict.
- Financial Logic Engine Agent: finance-safety invariants and final no-regression verdict.

Initial agent reports received:
- iOS-Native UX Layout Agent: ACCEPT to start, with the condition that implementation begins from explicit device-class architecture and not more shrinking. Current UI is rejected as final responsive UX.
- Frontend Performance and Interaction Agent: ACCEPT to start, but REJECT current UI as final SPRINT-14R target. Current skeleton is usable; current CSS and smoke evidence are tactical and insufficient.
- QA, Audit, and Acceptance Agent: REJECT current evidence. SPRINT-14R may be opened, but implementation and acceptance are blocked until this Director opening and agent tasks are recorded, then screenshots/metrics and final reports are collected.
- Financial Logic Engine Agent: ACCEPT to start from finance-safety perspective, with hard boundary that SPRINT-14R may change responsive layout and interaction only.

Exit criteria:
- Phone portrait shows operational journal/history as the dominant visible working field.
- Phone reduced/keyboard-like viewport keeps input and Save visible, with no body/page scroll.
- Phone structured check is reachable by horizontal movement or explicit view switch without making the writing surface disappear permanently.
- Phone landscape is either intentionally supported as mobile financial-notes system or explicitly constrained with evidence.
- iPad mini portrait and landscape are intentional mobile financial-notes layouts, not accidental phone compression.
- iPad 11+ portrait and landscape use a full workspace system.
- Desktop standard viewports use a distinct full workspace layout for computer screens.
- Vertical scroll exists only inside allowed internal containers, especially the current-month feed/history.
- Horizontal movement is used for structured/check view where needed, not as accidental overflow.
- Text and controls fit on all target viewports without overlap and without consuming the journal surface.
- Browser smoke includes screenshots and metrics for `390x844`, `390x520`, `360x640`, `768x1024`, `1024x768`, `834x1194`, `1194x834`, `1365x820`, and `1440x900`.
- Existing finance/API/browser regression gates remain green.
- Financial Logic Engine reviewer confirms no formula, parser, report, API semantic, import, schema, deploy, or closed-month logic changes.
- All assigned agents return final ACCEPT reports before sprint acceptance.

Risks:
- Current CSS contains tactical SPRINT-13R compression and can preserve the rejected UX if reused blindly.
- Current smoke tests prove fit and scroll more than journal dominance.
- The mobile `Details` panel can compete with the required two-view model of writing view and structured check view.
- Mobile summary strip can steal horizontal gesture space from structured view if not redesigned.
- Any change to `app.js` can accidentally alter API payloads or visibility of finance fields.
- SPRINT-11R production deployment remains blocked pending live evidence; SPRINT-14R must not claim production acceptance.

## SPRINT-14R Implementation Evidence

Implementation summary:
- `public/assets/v2/app.css` now defines explicit responsive behavior for phone portrait/small phone, phone landscape constrained mobile notes, iPad mini portrait/landscape mobile financial-notes mode, iPad 11 portrait 2+1 workspace, iPad 11 landscape full workspace, and desktop standard workspace.
- `public/v2.php` cache-busting version strings were updated so browser smoke loads the SPRINT-14R assets.
- `scripts/v2_operational_browser_smoke.cjs` now persists layout metrics, checks hierarchy and viewport behavior, and includes phone landscape evidence.
- No `app/v2/Repository.php`, `app/v2/Api.php`, parser, formula, report, import, schema, auth, deploy, or storage implementation files were changed.

Files changed:
- `FinDesk v2.0/sprints/SPRINT-14R-responsive-layout-redesign-handoff.md`
- `public/assets/v2/app.css`
- `public/v2.php`
- `scripts/v2_operational_browser_smoke.cjs`

Runtime acceptance evidence:
- Full gate log: `test-results/v2-sprint-14r/full-gate.log`
- Persisted layout metrics: `test-results/v2-browser-smoke/layout-metrics.json`
- Phone portrait: `test-results/v2-browser-smoke/phone-portrait-layout-390x844.png`
- Reduced phone/input reachability: `test-results/v2-browser-smoke/mobile-reduced-viewport-fit.png`
- Small phone: `test-results/v2-browser-smoke/phone-small-360x640.png`
- Phone landscape constrained: `test-results/v2-browser-smoke/phone-landscape-constrained-844x390.png`
- Phone structured check: `test-results/v2-browser-smoke/mobile-structured-check.png`
- iPad mini portrait: `test-results/v2-browser-smoke/ipad-mini-portrait.png`
- iPad mini landscape: `test-results/v2-browser-smoke/ipad-mini-landscape.png`
- iPad 11 portrait: `test-results/v2-browser-smoke/ipad-11-portrait.png`
- iPad 11 landscape: `test-results/v2-browser-smoke/ipad-11-landscape.png`
- Desktop standard 1365x820: `test-results/v2-browser-smoke/desktop-standard-1365x820.png`
- Desktop standard 1440x900: `test-results/v2-browser-smoke/desktop-standard-1440x900.png`

Full gate command saved to `test-results/v2-sprint-14r/full-gate.log`:

```bash
npm run smoke:v2 &&
npm run smoke:v2:auth &&
npm run test:v2:fixtures &&
npm run smoke:v2:http &&
npm run smoke:v2:db &&
npm run smoke:v2:ui &&
npm run smoke:v2:browser &&
npm run smoke:v2:deploy
```

Full gate result:
PASS. The saved log ends:

```text
SPRINT-14R full gate finished at 2026-07-05T22:00:07+02:00 with status 0
```

Browser-smoke metric highlights:
- Phone portrait `390x844`: `visibleJournalRatio=0.8644`, `bodyOverflow=hidden`, `htmlOverflow=hidden`, input and Save within viewport.
- Small phone `360x640`: `visibleJournalRatio=0.8095`, no document/body overhang, input and Save within viewport.
- Phone landscape constrained `844x390`: mobile financial-notes system remains active, `feed.height=160.96875`, `visibleJournalRatio=0.6338`, input and Save within viewport.
- iPad mini portrait: mobile financial-notes mode, `panelFlexBasis=88%`, `visibleJournalRatio=0.8593`.
- iPad mini landscape: mobile financial-notes mode, `panelFlexBasis=72%`, `visibleJournalRatio=0.7755`.
- iPad 11 portrait: full workspace grid with writing/detail above and structured check on a full lower row.
- iPad 11 landscape and desktop: full workspace grid, not mobile tabs.

Final agent reports received:
- iOS-Native UX Layout Agent: ACCEPT. Phone is journal-first with reachable input; mobile primary tabs are reduced to Write/Check; iPad mini uses intentional financial-notes behavior; iPad 11 portrait uses tablet workspace; desktop/iPad 11 landscape use full workspace.
- Frontend Performance and Interaction Agent: ACCEPT. No body/page scroll, internal scroll ownership, input reachability, mobile structured check, iPad mini behavior, iPad 11+/desktop workspace, and smoke metrics are accepted. Residual risk: iPad mini intentionally shows a partial next panel; structured check remains dense by design.
- Financial Logic Engine Agent: ACCEPT. Actual diff changes CSS, cache-busting, and browser smoke only. Parser, formulas, reports, API semantics, import, schema, deploy, and closed-month logic are unchanged. Browser smoke still asserts structured fields, Other review behavior, and closed-month decisions.
- QA, Audit, and Acceptance Agent: ACCEPT after re-review. Previous blockers were addressed by this implementation evidence section, `layout-metrics.json`, `full-gate.log`, and `phone-landscape-constrained-844x390.png`. QA verified required viewport screenshots, persisted metrics, final agent reports, full gate status 0, and no finance/API/parser/report/schema/import/deploy implementation changes.

Director closure status:
Accepted.

## Director Final Handoff

Sprint:
SPRINT-14R — Responsive Layout Architecture Redesign

Status:
Accepted

Agents assigned:
- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent
- Financial Logic Engine Agent as reviewer

Agent reports received:
- iOS-Native UX Layout Agent: ACCEPT.
- Frontend Performance and Interaction Agent: ACCEPT.
- Financial Logic Engine Agent as reviewer: ACCEPT.
- QA, Audit, and Acceptance Agent: initial REJECT for missing closure evidence; final ACCEPT after saved full-gate log, durable metrics JSON, final report record, and phone landscape evidence were added.

Accepted work:
- Phone portrait and small phone are vertical-first, journal-dominant mobile financial-notes layouts.
- Phone landscape is intentionally supported as a constrained mobile-notes layout with usable feed height, reachable input, and no body/page scroll.
- Mobile primary tabs are reduced to Write and Check; Details remains reachable through row selection but is not a primary mobile mode.
- iPad mini portrait and landscape use intentional mobile financial-notes behavior with internal horizontal movement.
- iPad 11 portrait uses a full tablet workspace with journal/detail above and structured check across the lower workspace.
- iPad 11 landscape and desktop standard viewports use full workspace layouts, not stretched phone UI.
- Summary strip no longer owns mobile horizontal scrolling; horizontal movement belongs to the operational workspace/structured check.
- Browser smoke now checks hierarchy, journal dominance, scroll containment, input reachability, phone landscape, and required viewport screenshots.
- Full regression gate passed and was saved to `test-results/v2-sprint-14r/full-gate.log`.

Rejected work:
- No production deployment acceptance.
- No financial formula changes.
- No parser/report/API/import/schema/deploy behavior changes.
- No dashboard-first UX.
- No old FinDesk product logic.
- No claim that SPRINT-13R compact mobile layout was final UX architecture.

Files changed:
- `FinDesk v2.0/sprints/SPRINT-14R-responsive-layout-redesign-handoff.md`
- `public/assets/v2/app.css`
- `public/v2.php`
- `scripts/v2_operational_browser_smoke.cjs`

Tests or checks:
- `php -l public/v2.php`
- `node --check scripts/v2_operational_browser_smoke.cjs`
- `git diff --check`
- `npm run smoke:v2`
- `npm run smoke:v2:auth`
- `npm run test:v2:fixtures`
- `npm run smoke:v2:http`
- `npm run smoke:v2:db`
- `npm run smoke:v2:ui`
- `npm run smoke:v2:browser`
- `npm run smoke:v2:deploy`
- Full saved gate:
  `npm run smoke:v2 && npm run smoke:v2:auth && npm run test:v2:fixtures && npm run smoke:v2:http && npm run smoke:v2:db && npm run smoke:v2:ui && npm run smoke:v2:browser && npm run smoke:v2:deploy`

Evidence:
- `test-results/v2-sprint-14r/full-gate.log`
- `test-results/v2-browser-smoke/layout-metrics.json`
- `test-results/v2-browser-smoke/phone-portrait-layout-390x844.png`
- `test-results/v2-browser-smoke/mobile-reduced-viewport-fit.png`
- `test-results/v2-browser-smoke/phone-small-360x640.png`
- `test-results/v2-browser-smoke/phone-landscape-constrained-844x390.png`
- `test-results/v2-browser-smoke/mobile-structured-check.png`
- `test-results/v2-browser-smoke/ipad-mini-portrait.png`
- `test-results/v2-browser-smoke/ipad-mini-landscape.png`
- `test-results/v2-browser-smoke/ipad-11-portrait.png`
- `test-results/v2-browser-smoke/ipad-11-landscape.png`
- `test-results/v2-browser-smoke/desktop-standard-1365x820.png`
- `test-results/v2-browser-smoke/desktop-standard-1440x900.png`

Risks:
- iPad mini intentionally exposes a partial next panel as a cue for horizontal movement; this is accepted but should be watched in real use.
- Structured check remains dense on phone because it is an inspection surface, not the writing surface.
- `test-results/.last-run.json` may remain stale from older runs; use `test-results/v2-sprint-14r/full-gate.log` for this sprint.
- SPRINT-11R production deployment remains rejected/blocked pending live production evidence.
- Local deploy preflight still warns that `storage/logs/auth_codes.log` exists locally and live production URL checks were skipped; this is not SPRINT-14R scope.

What must not be touched:
- Financial formulas.
- Parser semantics.
- Report generation semantics.
- API money semantics.
- Import/schema/deploy/auth/storage behavior.
- Operational journal as the source of truth.

Next sprint:
Return to SPRINT-11R live production deployment evidence unless another browser-visible MVP blocker appears.

Paste-to-next-director prompt:
You are the next Director of FinDesk v2.0. Source of truth is only GitHub files. SPRINT-14R accepted the responsive layout architecture redesign: phone is vertical-first with journal dominant and input reachable; phone landscape has constrained mobile-notes evidence; iPad mini has intentional mobile financial-notes behavior; iPad 11+ and desktop use full workspace layouts. Use `test-results/v2-sprint-14r/full-gate.log` and `test-results/v2-browser-smoke/layout-metrics.json` as SPRINT-14R evidence. Do not change financial formulas, parser/report/API/import/schema/deploy behavior, or make dashboard-first UX. Production deployment is still not accepted; return to SPRINT-11R live production deployment evidence unless a new browser-visible MVP blocker appears.

## Explicit Non-Goals

Do not:
- deploy to production;
- change financial formulas;
- add dashboard-first UX;
- add analytics/report-first screens;
- reuse old FinDesk product logic;
- broaden parser rules;
- accept one mobile breakpoint as final responsive architecture.

## Suggested Acceptance Viewports

Phone:
- `390x844`
- `390x520` reduced/keyboard-like
- `360x640`

iPad mini:
- `768x1024`
- `1024x768`

iPad 11+:
- `834x1194`
- `1194x834`

Desktop:
- `1365x820`
- `1440x900`

Optional:
- `1920x1080` only after standard desktop works.

## Current Evidence Artifacts

Existing screenshots from SPRINT-13R:
- `test-results/v2-browser-smoke/mobile-reduced-viewport-fit.png`
- `test-results/v2-browser-smoke/mobile-compact-readable-390x520.png`

Use them as evidence of the problem and temporary improvement, not as final target design.

## Paste-To-Next-Director Prompt

You are the next Director of FinDesk v2.0. Source of truth is only GitHub files. Start with `FinDesk v2.0/START_HERE_DIRECTOR.md`, then read `FinDesk v2.0/sprints/SPRINT-14R-responsive-layout-redesign-handoff.md`. Do not continue shrinking the current mobile layout one control at a time. The user changed the vector: build a proper responsive layout architecture from `04-responsive-layout-contract.md`, `31-operational-input-window-contract.md`, and `32-director-addendum-operational-window.md`. Assign UX, frontend, QA, and financial-review agents before implementation. Mobile must be vertical-first with the operational journal dominant and input visible. iPad mini, iPad 11+ portrait/landscape, and desktop need distinct tested layouts. No financial formulas, parser logic, report logic, deploy behavior, dashboard-first UX, or old FinDesk product logic changes.
