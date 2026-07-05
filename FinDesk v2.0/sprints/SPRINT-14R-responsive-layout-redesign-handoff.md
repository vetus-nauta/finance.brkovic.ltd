# SPRINT-14R — Responsive Layout Redesign Handoff

Status: Handoff / Next Director Start Point

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
