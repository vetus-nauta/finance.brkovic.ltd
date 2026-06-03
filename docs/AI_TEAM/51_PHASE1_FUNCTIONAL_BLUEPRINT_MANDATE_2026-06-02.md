# FinDesk Phase 1 Functional Blueprint Mandate - 2026-06-02

Owner: Project Director FinDesk
Status: current product beacon
Priority: P0

## Source

Primary product source for the next phase:

- Google Drive folder: `https://drive.google.com/drive/folders/1y_ZPDA9mXGcAEU_9EthB_IamluMmVS90?usp=sharing`
- functional package read locally on 2026-06-02

Key package files:

1. `index.html`
2. `docs/00_HANDOFF.md`
3. `docs/01_SCREEN_MAP.md`
4. `docs/02_LIVE_JOURNAL_CLEANUP.md`
5. `docs/03_SUBAGENT_ROLES.md`

## Decision

This package is now the main beacon for all next FinDesk actions.

It replaces the previous habit of patching the mixed FinDesk surface on top of legacy UI.

Phase 1 means:

- rebuild the user flow;
- keep the foundation;
- delay final styling;
- stop screen clutter;
- separate screens by purpose.

## What Phase 1 Is

Phase 1 is a functional cleanup and screen map rebuild.

It is not:

- a visual redesign sprint;
- a backend rewrite sprint;
- a database rewrite sprint;
- a production polish sprint;
- another layer on top of the rejected mixed FinDesk screen.

## What Must Be Preserved

Keep unless a direct requirement proves otherwise:

- authentication;
- email + six-digit code login;
- existing users and sessions;
- backend/API foundation;
- database foundation;
- attachment/proof foundation;
- PWA foundation;
- manifest;
- service worker.

Hard rule:

- do not destroy data;
- do not remove auth;
- do not rebuild the DB blindly.

## Product Definition

FinDesk is not ERP and not accounting software.

FinDesk is a compact money movement tool.

Core idea:

```text
Деньги исчезают тихо.
Потратил — запиши.
Получил — запиши.
```

The user must always understand:

1. how much cash is physically available;
2. how much is on card/account;
3. what was received;
4. what was spent;
5. what remains now.

## Required Screen Order

Phase 1 rebuild is driven by this screen map:

1. Welcome Hall
2. Solo Workspace
3. Live Journal
4. Create Team Workspace
5. Team Cash Pool Setup
6. Team Workspace
7. Admin Card
8. Employee Card
9. Group Report Assembly
10. Reports

## Immediate Product Priority

Start with `Live Journal`.

It is the first screen that must be cleaned before broader FinDesk rebuild.

Live Journal must show only:

1. person/context;
2. last fixation amount and date;
3. current remaining amount;
4. live records list;
5. one attachment button;
6. one input line with `+- amount and note`;
7. `Зафиксировать журнал`.

Live Journal must not show:

- reports;
- archive;
- analytics;
- charts;
- categories;
- employee cards;
- group totals;
- dashboard widgets;
- many filters;
- accounting tables;
- decorative office metaphors.

## Mandatory Alignment Patch 2026-06-02

This patch is mandatory before any next implementation sprint continues.

### 1. Transfer Offer / Acceptance Flow

Explicit lifecycle:

```text
Admin issues money
        ↓
Pending Transfer
        ↓
Employee confirms
        ↓
Money becomes active
```

Rules:

- employee cannot use `Live Journal` while transfer is pending;
- money is not active before confirmation;
- admin may edit or delete unresolved transfer;
- issue, confirmation, edit, and cancellation must be logged.

### 2. Card / Non-Cash Final Definition

Confirm:

- `Card` and `Cash` are separate streams;
- `Card` has its own `Live Journal`;
- `Card` has its own reports;
- `Card` may be assigned to an employee;
- `Card` appears as a separate section in final reports.

Warning rule:

- card warning must **not** appear when opening or entering a normal card journal;
- card warning appears **only** when administrator manually enters a card balance instead of the default `0`.

### 3. Team Workspace Clarification

`Team Workspace` is the people screen.

Primary visible objects:

- name;
- position;
- state.

Allowed user-facing states:

- `No records`
- `Live Journal`
- `Ready Report`

Do not show technical states on the people-first surface:

- `Attached`
- `Locked`
- `Archived`

### 4. Employee Card Final Layout

Top section:

- name
- position
- issued
- remaining

Then only:

- `Open Live Journal`
- `My Journals`

Remove from the primary employee card layout:

- last fixation
- delta
- spent
- analytics

### 5. Live Journal Final Cleanup

Main object of the screen:

- records feed

Not:

- balances
- menus
- reports
- dashboards

Approved MVP input pattern:

```text
📎  ± Amount and note...

-120 Fuel
+500 Cash received
-85 Food
```

### 6. Final Report Structure

Final report structure is:

```text
Report
   ↓
Cash Section

Card / Non-Cash Section

Total
```

`Cash` and `Card` remain separated until final report composition.

## What We Will Do

1. lock this package as the Phase 1 beacon;
2. assign short technical cards to roles;
3. prepare a local functional prototype first;
4. rebuild the flow screen by screen;
5. validate the flow against this mandatory alignment patch before visual polish;
6. only then decide what reaches production.

## What We Will Not Do

1. no more patching the rejected mixed FinDesk screen;
2. no style-first redesign;
3. no auth removal;
4. no destructive DB work;
5. no menu bloat;
6. no card-in-card-in-card rebuild;
7. no production deploy of half-finished Phase 1 screens.

## Production Boundary

Current production may contain FinDesk work that is already rejected as a product outcome.

Control:

- do not treat current production screen shape as the product source of truth;
- use this Phase 1 mandate as the source of truth instead;
- Phase 1 rebuild must be approved locally before any next deployment.
