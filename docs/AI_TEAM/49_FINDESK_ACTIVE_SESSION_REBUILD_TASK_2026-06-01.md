# FinDesk Active Session Rebuild Task - 2026-06-01

Owner: Project Director FinDesk
Status: task card opened; implementation not complete

## Why This Replaces The Old Board Task

The older board rebuild task in `48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md` is no longer the main target by itself.

CEO clarified a stricter product model on 2026-06-01:

- FinDesk must work around active sessions, not around mixed old cards and residual surfaces.
- The page must stop looking like a layered legacy board with new blocks added on top.
- The operational model is now more important than the cosmetic board rebuild.

So `48` remains useful as a completed intermediate UI rebuild, but the current product task is this new active-session task.

## Input From CEO

The intended FinDesk behavior is:

1. All work happens inside an active session. Nothing else should dominate the screen.
2. Employees can submit only one report per active session. Administrator also submits only one own report.
3. Then the administrator creates one common summary report, saves/sends it, and archives one summary object.
4. Several active sessions may exist in parallel for different directions (`работа`, `дом`, etc.), but they must not mix.
5. No old carryovers, old sessions, old fragments, or stale values should pollute the active session screen.
6. FinDesk main page must be light:
   - first card-button is the administrator card;
   - then participant cards;
   - on the outside of each card: only name and remaining money;
   - inside each card: full-page working surface.
7. Administrator card must show:
   - money received;
   - admin remaining cash;
   - total remaining cash at employees;
   - admin own submitted quick-report card;
   - ability to edit, return, reopen, or delete with explicit confirmation.
8. Employee cards must show:
   - how much the employee received;
   - how much remains;
   - whether issued money is still waiting for employee confirmation;
   - employee submitted report state.
9. Money issue must be confirmed by the employee:
   - before confirmation, red marker;
   - after confirmation, state `Подписано`;
   - button becomes non-clickable after confirmation.
10. Administrator can:
   - return employee report to live records;
   - approve employee report;
   - attach approved employee report under the admin report;
   - finalize one common report and archive it as one immutable object.
11. History must stay honest, but the active screen must show only the most relevant current items.
12. User mode must support owner/app-admin vs employee participation context, with remembered mode switch.

## Product Target

FinDesk should become an active-session control surface with these rules:

- session-first;
- one participant report per session;
- one admin report per session;
- one final summary report per session;
- one immutable archived summary object per closed session;
- separate active groups/sessions without cross-noise;
- current money truth visible without accounting clutter.

## UX Target

Visual/interaction target:

- card-button outside;
- full-page work area inside;
- no heavy nested cards;
- no old residual data on the main surface;
- no fixed decorative or pseudo-dashboard clutter;
- mobile first;
- predictable back navigation one step at a time.

## Required Functional Model

### 1. Active Session Layer

- FinDesk must load and display one chosen active session context.
- Session switch is allowed, but the visible money/report state must belong only to the chosen session.
- Archived sessions and old objects must stay outside the active operational surface.

### 2. Report Constraint Layer

- one employee -> one submitted report in the active session;
- one admin -> one submitted report in the active session;
- one summary report by admin after review;
- after finalization, the working cards leave the active review surface and the result enters archive.

### 3. Money Confirmation Layer

- administrator records issue amount to a participant;
- participant sees pending amount;
- participant confirms receipt;
- pending state is red;
- signed state is green / `Подписано`;
- signed action cannot be clicked again.

### 4. Review Layer

- employee report can be returned;
- employee report can be approved;
- approved child reports are attached under the admin report as included children;
- summary report finalization freezes arithmetic and archive identity.

### 5. History Layer

- active screen shows only the current card state plus short recent history;
- optionally expand last few records;
- full log remains stored separately and honestly.

## Boundary

This task does **not** authorize:

- financial formula rewrite;
- archive semantics rewrite;
- destructive log deletion;
- mixing multiple sessions into one visible money pile;
- blind replacement of backend contracts without Backend/Data ownership.

This task **does** authorize:

- restructuring `#moduleCaptain` around active-session UX;
- revising screen state, visibility, and navigation model;
- adding session-oriented frontend state;
- backend gap analysis for session identity and one-report constraints;
- product clarification of archive/session/report semantics where current code is too loose.

## Role Task Cards

### Product / Finance Architect

Read:

- `docs/AI_TEAM/04_TASK_BOARD.md`
- `docs/AI_TEAM/05_DECISIONS.md`
- `docs/AI_TEAM/48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md`
- this file

Write:

- own `FINDINGS.md`
- own `STATUS.md`
- own `TASKS_TO_OTHERS.md`

Output:

- define session object, session close, report uniqueness, summary immutability, and archive meaning;
- define whether `employee`, `participant`, and `owner mode` are distinct user-facing concepts or one vocabulary set.

### Backend / Data Engineer

Read:

- `app/ledger.php`
- `app/on_the_go.php`
- `app/messages.php`
- `public/api.php`
- this file

Write:

- own role docs first;
- runtime code only after explicit implementation slice is approved.

Output:

- map which existing endpoints already support active-session behavior;
- identify missing backend identity for active session, one-report-per-session rule, issue-confirmation, and session archive grouping;
- list minimal backend changes, if any, before frontend implementation.

### Frontend / UX Engineer

Read:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `docs/AI_TEAM/48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md`
- this file

Write:

- own role docs first;
- runtime code after task slice approval.

Output:

- redesign `#moduleCaptain` around session-first card-button UX;
- define main screen, card-open screen, session switch, participant confirmation state, short history expand, and back behavior.

### QA / Release Engineer

Read:

- this file
- changed role docs and runtime files once implementation starts

Write:

- own `STATUS.md`
- own `FINDINGS.md`

Output:

- create active-session QA matrix:
  - one session;
  - several parallel sessions;
  - one employee / several employees;
  - pending issue confirmation;
  - signed issue confirmation;
  - employee return / approve flow;
  - one summary finalization;
  - no stale old-session data on active screen.

## Initial Acceptance Criteria

This task card is considered ready for implementation only when:

1. Product defines active-session semantics.
2. Backend maps current support vs missing support.
3. Frontend provides the target UX structure.
4. QA defines the acceptance matrix.

Implementation is considered complete only when:

1. active session screen shows only current session truth;
2. outside card shows only name and remaining money;
3. inside card opens a full-page work surface;
4. one-report-per-session behavior is enforced or clearly blocked at the right layer;
5. issue confirmation state is visible and honest;
6. final summary report becomes one immutable archive object;
7. mobile browser QA passes without overlap, sticky conflicts, or mixed stale data.

## Current State

At the time of this task card:

- production frontend is at `20260601-findesk-mobilefit2`;
- current FinDesk already uses card-button direction and mobile fit hardening;
- but the active-session model itself is not yet fully formalized or implemented as the main contract.

So the next work should treat this as a product/architecture sprint, not just another CSS pass.
