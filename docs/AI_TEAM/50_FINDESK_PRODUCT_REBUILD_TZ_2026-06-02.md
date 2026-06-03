# FinDesk Product Rebuild TZ - 2026-06-02

Owner: Project Director FinDesk
Status: active product rebuild brief
Priority: P0

## Diagnosis

Current local/production FinDesk is not a finished product surface.

Work was done, but the current result still behaves like a technical intermediate layer:

- legacy shell is still fighting the working screen;
- the primary route `Quick Notes -> Submit report -> FinDesk review` is not product-clear;
- the administrator card is not the real operational center;
- navigation is not coherent enough;
- wording is partly internal and unprofessional;
- the screen does not yet express one clean financial-session model.

This task replaces patch thinking with a product rebuild approach.

## Product Intent

FinDesk is not a card collection. It is an operational screen for one active financial session.

The user must feel one clear model:

1. One active session dominates the screen.
2. Administrator and participants are the main entities.
3. Outside view is minimal: card-button with only name and current remaining amount.
4. Inside view is operational: a full-page work surface for that role.
5. One participant -> one report per session.
6. One administrator -> one own report per session.
7. One final common report per session.
8. Honest confirmation states for money issue and report transfer.
9. Old logs, archives, and secondary tools do not pollute the active working surface.

## Product Language

UI wording must become formal, short, and trustworthy.

Use:

- `FinDesk`
- `Активная сессия`
- `У администратора`
- `У участников`
- `Передано`
- `Ожидает подтверждения`
- `Подписано`
- `Сданный отчет`
- `Вернуть на доработку`
- `Утвердить`
- `Итоговый отчет`
- `Детали`

Do not use colloquial/internal wording like:

- `у вадмина`
- random mixed internal phrases
- labels that sound like debug copy

## Product Structure

### 1. Top Product Rail

Thin permanent top rail:

- product name `FinDesk`;
- active session switch;
- compact menu button `Детали`;
- no heavy hero shell;
- no marketing-like wrapper.

### 2. Main Board

Immediately below:

- compact active session summary:
  - `У администратора`
  - `У участников`
  - session state

Main board:

- first card-button: `Администратор`
- then participant card-buttons

Outside every card:

- name
- remaining amount
- one state marker

Nothing else.

### 3. Administrator Workspace

Full-page internal screen:

- incoming money / received amounts;
- issued amounts to participants;
- own submitted quick-notes report;
- queue of submitted participant reports;
- actions: return / approve;
- approved child reports under the admin package;
- create final common report.

Administrator card must be clickable at all times.

### 4. Participant Workspace

Full-page internal screen:

- received amount;
- remaining amount;
- confirmation state:
  - pending
  - signed
- own single report for the active session;
- submission state.

Participant must never see administrator moderation actions on own screen.

### 5. Quick Notes Product Exit

`Quick Notes` is not a parallel universe.

It must have one explicit product exit:

- `Сдать отчет в FinDesk`

The path must be obvious, stable, and role-aware.

If the user is not allowed to submit, the reason must be explicit.

## Hard Product Rules

1. Do not stack new UI on top of legacy decorative shell.
2. Do not keep duplicate headers or duplicate scene titles.
3. Do not mix active session and archive on the same working level.
4. Do not expose debug/internal financial terms to the user.
5. Do not block the main route from quick notes to FinDesk review.
6. Do not make the administrator card passive.
7. Browser Back and in-app Back must move one step back, not eject the user into chaos.

## What Can Be Preserved

Keep and reuse if technically sound:

- existing financial calculations;
- existing API contracts where possible;
- session/report data sources;
- proof/photo/pdf handling foundation;
- role rights already proven safe.

Do not preserve legacy visual wrappers just because they exist.

## Rebuild Order

### Sprint 1 - Product Frame

- remove legacy shell dominance from FinDesk scene;
- build top product rail;
- rebuild main board;
- make administrator card the first-class entry point;
- restore clear `Quick Notes -> FinDesk` transfer path.

### Sprint 2 - Role Workspaces

- rebuild administrator internal workspace;
- rebuild participant internal workspace;
- clean wording;
- define confirmation states visually and behaviorally.

### Sprint 3 - Final Flow

- participant submit / admin review / approve / return;
- child attachment under admin package;
- final report creation;
- archive handoff logic in UI.

### Sprint 4 - QA / PWA / Release

- mobile runtime;
- browser back;
- PWA shell/menu;
- proof/photo/pdf visibility;
- release smoke.

## Role Technical Cards

### Product / UX Lead

Read:

- `docs/AI_TEAM/49_FINDESK_ACTIVE_SESSION_REBUILD_TASK_2026-06-01.md`
- this file
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

Write:

- `docs/AI_TEAM/roles/product/FINDINGS.md`
- `docs/AI_TEAM/roles/product/STATUS.md`

Deliver:

- target screen map;
- wording set;
- admin path;
- participant path;
- quick-notes handoff path.

Short report back:

- `model`
- `screen map`
- `blocking contradictions`

### Frontend Architecture Lead

Read:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- this file

Write:

- `docs/AI_TEAM/roles/frontend/FINDINGS.md`
- `docs/AI_TEAM/roles/frontend/STATUS.md`

Deliver:

- which legacy shells/wrappers must stop being the base;
- which reusable financial/state functions stay;
- target component split for new FinDesk surface.

Short report back:

- `throw away`
- `keep`
- `rebuild into`

### Frontend Implementation Lead

Read:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- role reports from product/frontend architecture

Write:

- same runtime files above only

Deliver:

- new top rail;
- new board;
- clickable admin card;
- participant/admin internal workspaces;
- product copy cleanup.

Short report back:

- `files changed`
- `what now works`
- `known gaps`

### Backend / Data Lead

Read:

- `app/on_the_go.php`
- `app/advances.php`
- `app/messages.php`
- `public/api.php`
- this file

Write:

- `docs/AI_TEAM/roles/backend/FINDINGS.md`
- `docs/AI_TEAM/roles/backend/STATUS.md`

Deliver:

- verify the true path from quick notes to FinDesk submission;
- identify missing backend constraints for one-report-per-session and final package assembly;
- do not rewrite formulas blindly.

Short report back:

- `working now`
- `missing contract`
- `safe backend slice`

### QA / Release Lead

Read:

- runtime files above
- this file

Write:

- `docs/AI_TEAM/roles/qa/FINDINGS.md`
- `docs/AI_TEAM/roles/qa/STATUS.md`

Deliver:

- product path matrix:
  - admin entry
  - participant entry
  - quick notes submit
  - back navigation
  - proof visibility
  - final package creation

Short report back:

- `P0 broken`
- `passes`
- `release blockers`

## Acceptance

This rebuild is acceptable only when:

1. FinDesk reads like a product, not a prototype.
2. The active session is visually dominant and logically isolated.
3. The administrator card is the center of control and is always clickable.
4. Participants see only participant functions.
5. Quick Notes clearly hands a report into FinDesk.
6. Back navigation is predictable.
7. Mobile first screen looks compact, formal, and usable.
