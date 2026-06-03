# Phase 1 Kickoff Report - 2026-06-02

Owner: Project Director FinDesk
Status: kickoff complete

## What Was Fixed First

The project now has a new working beacon:

- `docs/AI_TEAM/51_PHASE1_FUNCTIONAL_BLUEPRINT_MANDATE_2026-06-02.md`

The office is now aligned to this rule:

- stop patching the rejected mixed FinDesk screen;
- preserve auth, backend, DB, PWA foundations;
- rebuild the flow screen by screen;
- start with `Live Journal`.

## Local Artifact Created

Local functional prototype:

- `docs/AI_TEAM/FINDESK_PHASE1_FUNCTIONAL_PROTOTYPE_2026-06-02.html`

This is not a production screen.
It is a local approval artifact for flow and screen structure.

## Short Research Outcome

### 1. Live Journal

Current Live Journal is overloaded.

Main finding:

- it still mixes tape/session/archive/review behavior into the first working screen.

What Phase 1 requires instead:

- person/context;
- last fixation;
- current remaining;
- live records;
- one input line `+- amount and note`;
- one attachment control;
- `Зафиксировать журнал`.

Files that will be touched in the first cleanup slice:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

Possible backend touch only if the current `close_session` path cannot safely stand in for `fix journal`:

- `app/on_the_go.php`
- `public/api.php`

### 2. Backend Reuse

Reuse level is high.

Can stay:

- auth;
- sessions;
- PWA;
- manifest;
- service worker;
- action router;
- groups;
- on-the-go foundation;
- advances;
- ledger final-report flow.

Main backend risks without DB change:

1. pending invited participant cards are derived from invites, not member rows;
2. cash-pool-fixed state is not a first-class backend object;
3. protected rollback for final reports is still audit-log based;
4. mobile draft/upload path depends on runtime schema being present.

### 3. Report Flow

Current pipeline already exists:

- quick notes / live input;
- saved card;
- submitted card;
- included card;
- final group report;
- immutable package/archive.

Main gap:

- the system is still card/tape-centered, not true active-session-centered.

This means Phase 1 can start without backend rewrite, but the final active-session model still needs a later architecture step.

## Next Sprint

### Sprint P1-A - Live Journal Cleanup Local

Goal:

- remove first-screen clutter from live work area;
- keep only the mandatory Phase 1 elements;
- prepare a safe local slice for approval.

Order:

1. simplify `public/app.php` live area;
2. simplify `public/assets/app.js` live rendering and actions;
3. keep proofs and drafts;
4. hide archive/review/session clutter from the first level;
5. keep production untouched until approval.

## Production Rule

Current production may contain a rejected FinDesk result.

Therefore:

- no more product decisions are to be derived from current production UI;
- the local Phase 1 prototype and this kickoff report are now the working source.
