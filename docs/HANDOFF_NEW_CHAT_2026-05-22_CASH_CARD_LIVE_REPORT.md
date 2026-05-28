# Handoff For New Chat: FinDesk Cash/Card Live Report Architecture

Date: 2026-05-22
Project: `finance.brkovic.ltd / FinDesk`
Local path: `/home/alexey/GitHub/finance.brkovic.ltd`
Local URL: `http://127.0.0.1:18889/reset-local.php`
Current branch: `main`
GitHub remote: `git@github.com:vetus-nauta/finance.brkovic.ltd.git`

## GitHub Sync Status

Remote was fetched on 2026-05-22.

```text
HEAD:        72b38e6
origin/main: 72b38e6
```

The local branch is synchronized with GitHub at commit `72b38e6`, but the current working tree contains many uncommitted local changes. These changes are important and must not be reset.

Do not run:

```text
git reset --hard
git checkout -- .
git clean -fd
git pull
```

unless the user explicitly asks and the working tree has been reviewed. A normal `git fetch origin --prune` is safe.

## Read First

Before continuing development, read these documents in this order:

1. `docs/HANDOFF_FULL_PRODUCT_2026-05-21.md`
2. `docs/USER_MESSAGES_DIGEST_2026-05-22.md`
3. `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`
4. `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md`
5. `docs/KNOWLEDGE_LIVE_REPORTS_ADVANCES_GROUPS_2026-05-21.md`
6. `docs/KNOWLEDGE_UX_CSS_GLASS_RULE_2026-05-21.md`
7. `docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md`
8. `docs/STEP2_GROUP_ACCESS_2026-05-20.md`
9. `docs/STEP3_ADVANCES_2026-05-20.md`

Reference images:

```text
docs/assets/iphone-notes-reference-list.png
docs/assets/iphone-notes-reference-note.png
```

## Product Essence

FinDesk is not a single flat ledger. It is a finance control system with several layers:

```text
Живой отчет
  -> fast mobile capture
FinDesk
  -> review, moderation, package preparation
Advanced
  -> money source, group cash, accountable money, audit, roles
Reports
  -> final clean report/export layer
Archive
  -> workspace cleanup only
Journal / black box
  -> automatic recovery/audit trail
```

The administrator's philosophy is the central invariant:

```text
At any moment I must know:
1. how much money was introduced into the group;
2. how much was spent and by whom;
3. how much cash I physically have;
4. how much cash each employee physically has;
5. what is already included in accounting;
6. what is still in review;
7. where the evidence for each number is stored.
```

## Key Financial Architecture

The money tree is:

```text
Money source
  -> administrator cash pool
    -> administrator Live Report
    -> accountable money issued to employees
      -> employee cash pocket / podotchet
        -> employee expenses
        -> employee remaining cash
        -> return or merge into admin/group report
  -> FinDesk review and inclusion
  -> final report/export
  -> archive only hides completed live cards from the working screen
  -> journal keeps recovery history
```

Important accounting rules:

- Issuing money to an employee is not an expense. It only changes the custodian.
- Employee podotchet is the employee's report for money issued by the administrator.
- Employee expense becomes group expense only after review/acceptance/inclusion.
- Remaining employee cash remains accountable money until returned or carried forward.
- Archive does not change money, FinDesk package, ledger or final reports.
- Deleting/cancelling a draft is allowed before FinDesk submission; submitted/included cards are locked and must be returned first.

## New Cash/Card Rule

The latest architecture splits `Живой отчет` into two parallel streams:

```text
cash -> Наличные
card -> Банковская карта
```

They are not two kinds of rows inside one cash report. They are different report streams with the same UX pattern.

### Cash Stream

Cash has a starting amount.

Formula:

```text
cash_left = cash_received + cash_in - cash_out
```

Meaning:

```text
How much physical cash should be on hand.
```

### Card Stream

Card starts from zero and goes negative only by expenses.

Formula:

```text
card_total = 0 - card_out
cash_delta = 0
cash_left = 0
```

Meaning:

```text
How much was spent from bank card.
```

Card never reduces:

- administrator physical cash;
- employee physical cash;
- cash advance base;
- next cash live-card base;
- `available_cash_balance`.

The only intersection is a separate consolidated-report cell:

```text
Потрачено с карты
```

## What Was Implemented In This Session

### Backend

Implemented first-stage cash/card separation.

Files touched:

```text
app/on_the_go.php
app/ledger.php
app/advances.php
deploy/on_the_go_sessions_runtime.sql
scripts/local-smoke.php
```

Main changes:

- Added `on_the_go_tapes.stream_type ENUM('cash','card') NOT NULL DEFAULT 'cash'`.
- Added index `idx_otr_tapes_stream (user_id, group_id, stream_type, status)`.
- Added helpers:
  - `ql_on_the_go_stream_type()`
  - `ql_on_the_go_tape_stream()`
  - `ql_on_the_go_capture_types_for_stream()`
  - `ql_on_the_go_capture_where_for_stream()`
  - `ql_on_the_go_capture_allowed_for_stream()`
- `ql_on_the_go_tape_create()` now accepts `stream_type`.
- `ql_on_the_go_active_tape_id()` now works per stream.
- `ql_on_the_go_signed_sync()` now respects stream:
  - `cash`: parses `+` as `cash_in`, `-` as `cash_out`;
  - `card`: parses only `-` and creates `noncash_out`; `+` lines are skipped/rejected by UI.
- `ql_on_the_go_card_summary()` now returns:
  - `stream_type`;
  - `cash_delta`;
  - `card_delta`;
  - stream-safe `cash_left`;
  - stream-safe `after_amount`.
- `ql_on_the_go_group_work_balance()` uses only cash ledger and cash live-report delta.
- `ledger_balance.available_cash_balance` uses `working_cards.cash_delta`, not total `balance_delta`.
- `ql_on_the_go_card_list()` can filter by `stream_type`.
- Accountable-money tapes created from advances are explicitly `stream_type = 'cash'`.

Local DB migration was applied:

```text
stream_type ok
idx_otr_tapes_stream ok
```

On another machine or after DB reset, make sure the migration in `deploy/on_the_go_sessions_runtime.sql` is applied.

### Frontend / UX

Implemented the new entry flow for Live Report.

Files touched:

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/index.php
public/service-worker.js
```

Main changes:

- Added start screen `#otrStreamGate`.
- User chooses:
  - `Наличные`;
  - `Карта`.
- The selected stream controls:
  - list filtering;
  - new card creation;
  - editor labels;
  - result summary;
  - parsing behavior;
  - color theme.
- Cash theme: soft green glass, cash/money-stack metaphor.
- Card theme: soft yellow glass, bank-card metaphor.
- Back button in report-card list now returns to the cash/card choice screen.
- The choice screen was rebuilt as a proper main screen:
  - centered glass shell;
  - header card;
  - two balanced large choice zones on desktop/tablet;
  - one-column choice on mobile.
- Cache bumped:
  - app assets: `20260522-65`;
  - service worker: `findesk-20260522-v93`.

## Current UX Behavior

Expected flow:

```text
Open Живой отчет
  -> choose Наличные or Карта
  -> see list for selected stream
  -> + creates new card in selected stream
  -> card opens as Notes-like editor
  -> save returns to list
  -> list back arrow returns to stream choice
```

Cash editor:

```text
-45 products
-67 fuel
+100 received
```

Card editor:

```text
-45 products
-67 fuel
```

Card editor must not accept income lines as meaningful card data.

## Verification Already Done

Commands run successfully:

```text
php -l app/on_the_go.php
php -l app/ledger.php
php -l app/advances.php
php -l public/app.php
php -l public/index.php
php -l scripts/local-smoke.php
node --check public/assets/app.js
git diff --check
php scripts/local-smoke.php http://127.0.0.1:18889
```

Smoke result:

```text
OK: local smoke completed for http://127.0.0.1:18889
```

The smoke test now includes a cash/card invariant:

```text
card stream:
  creates only noncash_out rows
  card_out = expense total
  cash_left = 0
  cash_delta = 0
  after_amount = -card_out
```

## Known Working Tree Status

As of this handoff, many files are modified or untracked. Some were modified before this session. Do not assume every dirty file belongs to the latest cash/card work.

Important current dirty files include:

```text
app/advances.php
app/auth.php
app/groups.php
app/ledger.php
app/on_the_go.php
deploy/on_the_go_sessions_runtime.sql
public/api.php
public/app.php
public/assets/app.css
public/assets/app.js
public/assets/i18n.js
public/index.php
public/service-worker.js
scripts/local-smoke.php
```

Untracked project docs/scripts include:

```text
docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md
docs/IPHONE_NOTES_UX_ALGORITHMS_2026-05-21.md
docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md
docs/KNOWLEDGE_LIVE_REPORTS_ADVANCES_GROUPS_2026-05-21.md
docs/KNOWLEDGE_UX_CSS_GLASS_RULE_2026-05-21.md
public/reset-local.php
scripts/start-local.sh
```

There is also `app/ai.php` untracked.

## Important Warnings

1. Do not continue random arithmetic fixes.

2. Do not mix cash/card rows in one cash remainder.

3. Do not let `card_out` reduce `available_cash_balance`.

4. Do not treat employee issued money as expense.

5. Do not archive as if archive means "included in final report". Archive only cleans the working Live Report screen.

6. Do not add broad CSS globally yet. The currently liked glass style is a reference and should be applied module by module later.

7. Do not make the employee UI administrative. Base employees mostly live in Live Report and their own archive/request-correction flow.

## What Still Needs Work

### Next Main Step

The next real step is to redesign `FinDesk` and `Advanced` around the new cash/card/accountable-money architecture.

Recommended order:

1. Finish visual/UX review of the new Live Report choice/list/editor flow on:
   - desktop;
   - tablet;
   - mobile.

2. Update FinDesk display so every card clearly shows:
   - stream: `Наличные` or `Карта`;
   - owner/employee;
   - status: draft/submitted/included/archived;
   - cash expense;
   - card expense;
   - cash remainder only where meaningful.

3. Rework FinDesk package summaries:
   - submitted reports;
   - included reports;
   - archive button/modal;
   - included package modal;
   - employee podotchet folders.

4. Rework Advanced dashboard around accountant-friendly buckets:

```text
Total introduced funds
Cash with administrator
Cash issued to employees
Open accountable cash
Submitted not included
Included in working package
Closed/final report total
Card expenses
Cash expenses
Physical cash expected now
```

5. Add a clean final report/export model:
   - cash expenses;
   - card expenses;
   - total expenses;
   - remaining cash;
   - employee attribution;
   - evidence links/attachments.

6. Keep improving the automatic journal/black-box:
   - every Live Report save/submit/include/return/archive/delete;
   - actor;
   - group;
   - stream;
   - before/after summary.

## Suggested First Task For The Next Chat

Start with a code/UX inspection, not new feature code:

```text
1. Read this handoff and the listed knowledge docs.
2. Run git status --short and confirm the dirty tree.
3. Run the local smoke test against http://127.0.0.1:18889.
4. Manually inspect Live Report at /reset-local.php:
   - cash choice -> list -> new card -> save -> list;
   - card choice -> list -> new card -> save -> list;
   - back arrow from list -> choice screen;
   - opening saved cash/card cards restores original rows.
5. Then start FinDesk/Advanced redesign from the summary buckets above.
```

## Prompt For New Chat

Copy this into the next chat:

```text
Проект: /home/alexey/GitHub/finance.brkovic.ltd

Сначала прочитай:
1. docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md
2. docs/USER_MESSAGES_DIGEST_2026-05-22.md
3. docs/HANDOFF_FULL_PRODUCT_2026-05-21.md
4. docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md
5. docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md
6. docs/KNOWLEDGE_LIVE_REPORTS_ADVANCES_GROUPS_2026-05-21.md
7. docs/KNOWLEDGE_UX_CSS_GLASS_RULE_2026-05-21.md

Важно:
- GitHub origin/main на 72b38e6, но рабочая папка содержит незакоммиченные изменения. Ничего не сбрасывать и не затирать.
- Не продолжать хаотичные фиксы цифр.
- Новая архитектура: Живой отчет разделен на два параллельных потока: cash/Наличные и card/Карта.
- card не имеет cash_received, не влияет на cash_left и available_cash_balance, создает только noncash_out.
- cash отвечает за физические деньги на руках.
- Следующий шаг: проверить UX Live Report на desktop/tablet/mobile, затем привести FinDesk и Advanced к новой архитектуре отчетов, подотчетов, cash/card и понятных бухгалтерских сумм.

Перед правками выполнить:
git status --short
git rev-parse --short HEAD
git rev-parse --short origin/main
php scripts/local-smoke.php http://127.0.0.1:18889
```
