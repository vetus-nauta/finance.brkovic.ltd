# Full Product Handoff: finance.brkovic.ltd / FinDesk

Date: 2026-05-21  
Project path: `/home/alexey/GitHub/finance.brkovic.ltd`  
Local test URL: `http://127.0.0.1:18889/reset-local.php`  
GitHub repo: `finance.brkovic.ltd`

## Read This First

This is the main handoff for the next chat or another PC. The previous narrow handoff only described the last broken On-the-Go screen. This document describes the whole product idea: roles, entities, permissions, screen responsibilities, workflow logic and the next implementation task.

Important status:

- `origin/main` is at commit `2b3e245 Update FinDesk handoff docs`.
- The local working tree has many uncommitted changes. They are not fully reflected on GitHub yet.
- Do not run destructive git commands. Do not reset, checkout or discard local changes.
- The user is unhappy with the current On-the-Go / FinDesk flow because it became structurally confusing. The next work must fix structure first, then polish style.

## Product In One Sentence

`finance.brkovic.ltd` is a global finance web app where a user has a full private finance account, can join work groups with limited or advanced rights, can enter fast expense reports on the phone, and where managers/admins moderate those reports into clean group accounting and printable Excel/report packages.

## Product Layers

The product is not one flat ledger. It has three main operating layers plus a menu area.

### 1. On the Go / На бегу

Purpose: fast field mode for the person who records facts during the day.

Main idea:

- minimal screen;
- signed text input like Captain Fin: `-45 products`, `-67 fuel`, `+100 received`;
- photos/attachments;
- immediate result: received / spent / remaining;
- user checks the app balance against real cash in pocket;
- then the report can go to moderation.

For a base employee this screen must be almost everything they need. They should not see admin/business/advanced clutter inside this flow.

For an administrator using simplified mode, this screen is also available, but the admin's report is one layer higher. The top informational number should read like "Касса" for the current report, not "остаток основного слоя". Admin can still move to FinDesk/Advanced from navigation.

### 2. FinDesk

Purpose: middle manager/report layer. This is where the old local Captain Fin report logic belongs.

Main idea:

- saved report cards;
- open/edit/delete report cards;
- mark card as submitted/accepted;
- return card for correction;
- review employee reports;
- include accepted reports into the consolidated group report;
- build/print/export the professional Excel/report package;
- show submitted cards dimmed/marked, with ability to roll back the marker.

FinDesk is the replacement for the "middle layer" between На бегу and Advanced. It should be familiar to the previous Captain Fin desktop idea, but connected to users, groups and permissions.

### 3. Advanced

Purpose: organizer/admin layer.

Main idea:

- group-level money position;
- input received funds into the group/accounting base;
- issue accountable money to employees;
- see pending/reporting pipeline;
- audit what changed and who did it;
- AI analysis and structuring;
- rules/integrations/premium tools;
- global "было / стало" for the group or chosen context.

Important: Advanced "было / стало" is not strictly monthly. The user cares about the state before and after the next submitted/reporting operation. Monthly/year filters can come later as filters, not as the core meaning.

### 4. Menu

Purpose: hide secondary modules so the main screen does not become a landfill.

Menu includes:

- Ledger / Учет;
- Reports / Отчеты;
- Groups / Группы;
- Business / Бизнес;
- Premium;
- Settings / Настройки;
- messages/invites where appropriate.

The current main navigation target should stay compact:

```text
На бегу | FinDesk | Advanced | Меню
```

## Roles And Access Levels

Access is group-scoped. A person can have a full private account and still have limited rights inside a work group.

Example:

- user `a@example.com` has full private personal finance;
- in Group A the same user is `base`;
- in Group B the same user is `manager`;
- in Group C the same user is `advanced/admin`.

### Base / На бегу

For lower-level employees or simple field users.

Allowed:

- personal account remains fully available outside group restrictions;
- sees own On-the-Go workflow;
- records own signed `+ / -` rows;
- attaches photos/files to own rows/report;
- sees money issued to them by admin;
- sees simple analytics only: received, spent, remaining;
- submits report to group manager/admin for moderation;
- sends/receives group messages if enabled.

Not allowed inside the group:

- direct write to group ledger;
- member management;
- issue money;
- include reports into consolidated report;
- advanced audit/admin tools.

### Manager / FinDesk

For managers who check and assemble reports.

Allowed:

- can use FinDesk middle layer;
- can see assigned/submitted employee reports according to group permissions;
- can review rows and attachments;
- can return a report for correction;
- can accept/include report cards into the group package;
- can write group ledger rows where current permission allows it;
- can create clean summary/export packages.

Usually not allowed unless promoted/configured:

- change group owner/admin settings;
- issue major admin-level funds;
- manage all users/rules.

### Advanced / Admin / Organizer

For the group organizer/administrator.

Allowed:

- all manager rights;
- create/manage groups;
- invite employees;
- assign access level: `base`, `manager`, `advanced`;
- issue accountable money to employees;
- input received funds into group/accounting base;
- see global group position;
- see audit;
- run AI analysis;
- manage advanced/premium group tools;
- adjust group access and moderation rules.

## Core Entities

These are the product entities the next chat must keep separate. Do not flatten everything into one ledger table mentally.

### User

Real login identity.

Current auth model:

- email login;
- 6-digit code;
- browser-friendly code input should use `autocomplete="one-time-code"`;
- user has personal/private finance outside group permissions.

Files:

- `app/auth.php`
- `public/api.php` actions: `request_code`, `verify_code`, `current_user`, `logout`

### Group / Workspace

Shared finance area.

Files:

- `app/groups.php`
- `deploy/groups_foundation.sql`

Group contains members, invites, messages, group ledger, accountable money and reports.

### GroupMember

Connection between user and group.

Important fields:

- `group_id`
- `user_id`
- `role`
- `access_level`: `base`, `manager`, `advanced`
- `permissions_json`
- `invited_by`
- `invite_id`

The access level determines what the group opens for this user.

### Invite

Admin/advanced user sends an invite to an employee.

Important behavior:

- invite can bind to a specific email;
- invite carries intended access level;
- employee can later create/use full personal profile independently;
- the group still grants only the invited group rights.

Files/API:

- `group_invite_create`
- `group_join`
- `group_member_access_update`

### LedgerEntry

Normalized accounting row.

Use for stable finance/accounting entries. Do not prematurely turn every On-the-Go draft into ledger.

Files/API:

- `app/ledger.php`
- `ledger_create`
- `ledger_list`
- `ledger_balance`
- `ledger_work_position`
- `ledger_report`

### OnTheGoTape / Report Card

This is the user's report card/session from "На бегу".

It carries:

- owner user;
- optional group;
- base/cash received;
- signed rows;
- attachments;
- state: draft, submitted, included, archived/deleted/corrected;
- before/after position.

Files/API:

- `app/on_the_go.php`
- `on_the_go_signed_sync`
- `on_the_go_card_list`
- `on_the_go_card_detail`
- `on_the_go_card_submit`
- `on_the_go_card_include`
- `on_the_go_card_uninclude`
- `on_the_go_card_unsubmit`
- `on_the_go_card_delete`

Important user rule:

- report cards must be visible as cards;
- user can open, edit, delete a card;
- user can mark "сдал";
- submitted card changes color/marker;
- user/admin can roll this state back where allowed.

### OnTheGoCapture

Parsed or manually entered row inside an OnTheGoTape.

Example:

```text
-45 products
-67 fuel
+100 received
```

Rows need edit/delete buttons when a card is opened.

### CashAdvance / Accountable Money

Money issued by admin to an employee under report.

Important rule:

```text
money issued to an employee is not a group expense yet
```

It becomes group expense rows only after the employee submits the report and a manager/admin accepts/inserts the real expenses into the group report/ledger.

Files/API:

- `app/advances.php`
- `advance_create`
- `advance_list`
- `advance_detail`
- `advance_submit`
- `advance_accept`
- `advance_return`
- `advance_cancel`

Product meaning:

- admin sees "issued to employee" as a pending red line;
- after employee report is accepted, this red line expands into real expenses;
- source user identity is preserved.

### Attachment / File

Files/photos can belong to On-the-Go rows/reports and ledger entries.

Current storage paths include:

- `storage/documents/YYYY/MM` for ledger files;
- On-the-Go file storage via `on_the_go_files`.

Future requirement from user:

- saved report files and Excel reports should be stored on the server under the web app project path;
- submitted reports should duplicate to the specified Google Drive folder;
- yearly folders should be formed automatically.

### Message

Group messages/unread state.

Files/API:

- `app/messages.php`
- `message_send`
- `message_list`
- `message_unread`
- `message_mark_read`

Need keep group-scoped and permission-aware.

### Business

Business/proforma tools from the broader project.

Files/API:

- `app/business.php`
- `company_profile_get/save`
- `client_create/list`
- `proforma_create/list/get`

This is secondary menu functionality, not core On-the-Go/FinDesk workflow.

### AuditLog

Important state changes should be logged:

- auth;
- invite/join/access changes;
- AI run;
- future report submit/include/return/delete actions.

Files:

- `app/auth.php` `ql_audit_write`
- API: `audit_list`

### AI Analysis

Current local file:

- `app/ai.php`

Purpose:

- analyze group finance/report state;
- surface discrepancies;
- suggest actions;
- structure report data.

This should eventually use the user's configured OpenAI account/API setup, but current code is local analysis foundation, not final AI integration.

## Main Workflows

### Login

1. User enters email.
2. App sends 6-digit code.
3. Code input should be browser-friendly for autofill.
4. User logs in to their personal account.
5. Group access is layered on top, not replacing the personal account.

### Group Invite

1. Advanced/admin opens group/admin area.
2. Admin enters employee email.
3. Admin chooses access level:
   - `base` for On-the-Go employee;
   - `manager` for FinDesk moderator;
   - `advanced` for organizer/admin.
4. Invite is created with email and access level.
5. Employee logs in by email code and joins.
6. Employee can still create/use their own full personal finance profile, but this group opens only the rights assigned by the admin.

### Accountable Money

1. Admin receives/input group funds in Advanced.
2. Admin issues `500 EUR` to employee.
3. Employee sees this as received money in On the Go.
4. Employee records small rows:
   - `-45 products`
   - `-67 fuel`
   - `+100 received` if additional received funds happened in the field.
5. Employee checks remaining cash against real pocket cash.
6. Employee submits/marks report for moderation.
7. Manager/admin reviews.
8. If correct, manager/admin includes it into group report.
9. If wrong, manager/admin returns it for correction, and it must reopen in the original On-the-Go model for edits.

### On The Go To FinDesk

The intended flow after the latest user correction:

1. In On the Go, user edits one report card.
2. `Сохранить отчет` saves the card.
3. App should open a full-screen saved cards page, not an empty editor.
4. Saved cards page has a `+` button for new report.
5. Each card can be opened, edited, deleted.
6. For admin/allowed role, card can be moved/submitted toward FinDesk moderation.
7. FinDesk should not duplicate the same "current report" card if the intermediate page becomes the card list.

### Return For Correction

When a card/report is returned:

- it leaves the manager's submitted queue;
- it returns to the On-the-Go/card list in its original chronological position;
- it opens in the same simple report model;
- user/admin can edit rows, add missing rows/photos, or delete wrong rows;
- resubmission uses the same card, not a duplicate.

### Include In Report

When manager/admin includes a submitted card:

- the card moves into the accepted/included state;
- the card changes marker/color;
- it participates in the consolidated report and totals;
- it can still be opened to inspect row-level details;
- it can be removed from the report if the action is rolled back.

### Advanced Position

The user always needs to understand:

```text
было -> стало
```

Meaning:

- "было" is the amount before the relevant latest submitted/included/report operation;
- "стало" is the amount after it;
- this is not forced to a calendar month;
- monthly/year/date filters belong to Advanced reports later.

Avoid noisy "delta" where the user did not ask for it. For many cards, only the main result balance and two actions are enough.

## Screen Responsibilities

### On The Go Screen

Must show:

- current working report or cards list;
- "Касса" / received amount for this report;
- signed records field;
- photos/attachments;
- simple totals:
  - got/received;
  - spent;
  - remaining;
- save;
- after save, show cards/list rather than a blank new report editor.

Must not show:

- explanatory layer cards;
- admin analytics for base employee;
- big dashboard text;
- duplicate open buttons;
- broad menu clutter inside the work area.

### On-The-Go Intermediate Cards Page

This is the immediate next build task.

Visual reference:

- `docs/assets/iphone-notes-reference-list.png`
- `docs/assets/iphone-notes-reference-note.png`

Required behavior:

- full-screen list, like iPhone Notes list;
- title and compact cards;
- `+` button creates a new report;
- card opens a focused editor/detail page;
- back returns to list and preserves changes;
- delete requires confirmation;
- admin sees a FinDesk/approval action where appropriate;
- empty list should feel like a proper list state, not a broken white screen.

This page replaces the confusing "empty new tape after save" feeling.

### FinDesk Screen

Must show manager workflow, not random duplicated cards.

Core sections should become:

- submitted reports awaiting review;
- included/accepted reports;
- tools to open all reports and build summary/export.

The old first "Текущий отчет" column is now questionable. If On-The-Go intermediate page owns drafts/saved cards, FinDesk should not duplicate it.

Card in included report should be compact:

- title/person/date as needed;
- main result amount;
- two buttons:
  - open card;
  - remove from report.

Detailed rows/buttons belong inside modal/detail, not on every compact card.

### Advanced Screen

Should be split into screens on mobile:

- Overview: current position, was/now, issued/open/pending counts.
- Subreports/Advances: accountable money pipeline.
- AI: analysis and structuring.
- Audit: history of important actions.
- Team/Rules/Integrations can be side panels or menu-backed sections.

Must always explain numerically:

- what was before the last relevant operation;
- what is now;
- what funds are open/issued;
- what reports are waiting.

### Menu Screen

Keep secondary modules reachable but not dominant:

- Ledger;
- Reports;
- Groups;
- Business;
- Premium;
- Settings.

Premium currently is not gated and can remain visible for testing.

Premium planned features:

- Advanced Mode;
- Trip with Friends: group of people, shared pot, trip expenses, settlement/equalization;
- Report Studio: premium summaries, print and review packages.

## Current Implementation Map

Primary entry points:

```text
public/app.php
public/index.php
public/api.php
public/assets/app.js
public/assets/app.css
public/assets/i18n.js
public/service-worker.js
```

Backend modules:

```text
app/auth.php
app/db.php
app/groups.php
app/ledger.php
app/on_the_go.php
app/advances.php
app/messages.php
app/business.php
app/ai.php
```

Deployment/schema markers:

```text
deploy/auth_foundation.sql
deploy/ledger_foundation.sql
deploy/groups_foundation.sql
deploy/group_access_levels.sql
deploy/messages_foundation.sql
deploy/business_desk_foundation.sql
deploy/on_the_go_foundation.sql
deploy/on_the_go_sessions_runtime.sql
deploy/advances_foundation.sql
```

Existing docs worth reading after this:

```text
docs/HANDOFF_2026-05-20.md
docs/INTEGRATION_STRATEGY_2026-05-20.md
docs/STEP2_GROUP_ACCESS_2026-05-20.md
docs/STEP3_ADVANCES_2026-05-20.md
docs/STEP4_ADVANCE_UI_2026-05-20.md
docs/STEP6_BRAND_I18N_MODES_2026-05-20.md
docs/STEP7_CAPTAIN_FIN_LIVE_LAYER_2026-05-20.md
docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md
```

## Local Desktop / Launcher State

Local testing now uses port `18889`.

Launcher script:

```text
scripts/start-local.sh
```

Reset/cache clearing page:

```text
public/reset-local.php
```

The Plank item was pointed to:

```text
~/.local/share/applications/captain-fin.desktop
```

with:

```text
Exec=/home/alexey/GitHub/finance.brkovic.ltd/scripts/start-local.sh
Path=/home/alexey/GitHub/finance.brkovic.ltd
Icon=/home/alexey/GitHub/finance.brkovic.ltd/public/assets/icon-512.png
```

Cache notes:

- `app.php` and `index.php` currently send no-store headers;
- local service worker is disabled/unregistered on `127.0.0.1` and `localhost`;
- current service worker marker was bumped during debugging.

## Current Dirty Git State

At the time of this handoff, local files differ from GitHub.

Modified:

```text
app/advances.php
app/auth.php
app/groups.php
app/ledger.php
app/on_the_go.php
public/api.php
public/app.php
public/assets/app.css
public/assets/app.js
public/assets/i18n.js
public/index.php
public/service-worker.js
```

Untracked:

```text
app/ai.php
public/reset-local.php
scripts/start-local.sh
docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md
docs/assets/iphone-notes-reference-list.png
docs/assets/iphone-notes-reference-note.png
```

This full handoff file is also new until committed.

## Design Direction

User wants premium light iOS 26 glass feel, but style must not hide broken logic.

Design rules for next work:

- mobile, tablet and desktop must all be checked;
- mobile often splits one desktop screen into two screens;
- do not make a landing page inside the app;
- do not add explanatory cards about product layers in the work screen;
- keep copy short;
- align buttons/cards/text tightly;
- avoid duplicated buttons like "На бегу" and "Открыть На бегу";
- menu must overlay above cards, not behind them;
- compact cards should not expose every internal metric;
- row-level edit/delete belongs in opened card/detail;
- use accent colors by meaning:
  - green/teal: accepted/income/healthy;
  - red/coral: expense/error/pending issued money;
  - blue: primary action;
  - gold: advanced/admin accent.

## Language / i18n

Supported language foundation:

- Russian;
- English;
- German;
- Italian;
- Spanish;
- Serbian/Montenegrin/Croatian;
- Mandarin Chinese.

Rules:

- detect browser/system language;
- show language reminder once;
- user can close it;
- user choice persists in storage;
- do not break meaning when translating finance/report terms.

Files:

```text
public/assets/i18n.js
public/assets/app.js
```

## What Is Broken Conceptually Right Now

The current implementation drifted while trying to fix totals. The user specifically rejected this direction:

- after saving, the app creates a blank/new editor that looks like a broken empty page;
- drafts/cards and FinDesk stages are mixed;
- some report cards behave as if they live in separate arithmetic worlds;
- FinDesk first/second/third columns became confusing;
- submit/include flow sometimes bypasses the intended moderation stage;
- delete/edit/return states have shifted several times and need a stable state machine;
- Advanced numbers were interpreted too calendar-like instead of operation-like.

Do not keep adding random patches. Rebuild the flow model cleanly.

## Immediate Next Task

Build the proper On-the-Go intermediate cards page.

Use these references:

```text
docs/assets/iphone-notes-reference-list.png
docs/assets/iphone-notes-reference-note.png
```

Implementation intent:

1. On-The-Go has a list state and an editor state.
2. List state shows saved report cards.
3. `+` starts a new report.
4. Opening a card edits the same card.
5. Saving returns to list.
6. Deleting requires confirmation.
7. Returning for correction restores the card to this list in its original chronological position.
8. Admin can move/approve a card toward FinDesk from this list/detail.
9. FinDesk no longer needs a duplicated "current draft" column if this page owns drafts.
10. Only after this flow works should totals/Advanced/report export be polished.

## Suggested State Machine

Report card state should be explicit:

```text
draft
saved
submitted
included
returned
archived/deleted
```

Transitions:

```text
draft -> saved
saved -> submitted
submitted -> returned
returned -> saved
submitted -> included
included -> submitted/unincluded
saved/returned -> archived/deleted
```

Do not create duplicate cards for a transition. Move the same card through states.

## Verification Checklist

Before claiming done:

```bash
php -l app/on_the_go.php
php -l app/advances.php
php -l public/api.php
php -l public/app.php
node --check public/assets/app.js
node --check public/assets/i18n.js
git diff --check
```

Run local app:

```bash
./scripts/start-local.sh
```

Open:

```text
http://127.0.0.1:18889/reset-local.php
```

Manual smoke:

1. Login locally by code.
2. Open На бегу.
3. Create a report with:
   - `-45 продукты`
   - `-67 топливо`
   - `+100 получил от руководителя`
4. Save.
5. Confirm app shows cards list, not an empty broken page.
6. Open saved card.
7. Edit a row.
8. Delete a row with confirmation.
9. Return to list.
10. Create second card.
11. Ensure second card starts from the correct previous result where group context requires sequential cash position.
12. Submit/approve with admin rights.
13. Confirm FinDesk receives the card in the right stage.
14. Return for correction.
15. Confirm the same card returns to On-The-Go list in the original chronological position.
16. Include into report.
17. Confirm Advanced was/now position reflects the included/report operation.

## Do Not Forget

- The local desktop version of the older Captain Fin idea is not the same as the mobile PWA. Desktop can keep two columns; mobile should split list/editor into screens.
- Personal account rights and group role rights are separate.
- Issued accountable money is not an expense until accepted.
- Reports are cards/documents first, ledger rows second.
- The user wants clarity more than another decorative pass.
- GitHub does not yet include all local changes unless committed/pushed after this handoff.

## Short Intro For A New Chat

Use this if starting another chat:

```text
Начни с проекта /home/alexey/GitHub/finance.brkovic.ltd. Сначала прочитай docs/HANDOFF_FULL_PRODUCT_2026-05-21.md, потом docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md и docs/STEP2_GROUP_ACCESS_2026-05-20.md / docs/STEP3_ADVANCES_2026-05-20.md.

Суть продукта: finance.brkovic.ltd / FinDesk - глобальный finance web app с личным аккаунтом, группами, ролями base/manager/advanced, быстрым режимом "На бегу", средним слоем FinDesk для карточек отчетов/модерации/сводного Excel и Advanced-слоем администратора для денег, выдач, аудита и AI.

Текущая главная задача: не продолжать хаотично чинить цифры, а собрать правильную промежуточную страницу "На бегу" в стиле iPhone Notes: список сохраненных карточек, плюс для новой записи, открытие/редактирование/удаление карточки, возврат на исправление в исходную позицию, админское действие для передачи в FinDesk. Потом уже выравнивать FinDesk/Advanced расчеты.
```
