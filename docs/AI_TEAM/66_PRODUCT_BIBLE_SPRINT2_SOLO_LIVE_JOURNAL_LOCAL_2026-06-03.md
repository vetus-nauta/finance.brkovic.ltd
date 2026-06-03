# FinDesk Product Bible Sprint 2 — Solo / Cash-Card / Live Journal Local Report — 2026-06-03

## Source

Highest source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Previous sprint:

```text
docs/AI_TEAM/65_PRODUCT_BIBLE_SPRINT1_WELCOME_SHELL_LOCAL_2026-06-03.md
```

## Goal

Create the first obvious product path:

```text
Welcome Hall
  -> Работаю один
  -> Cash / Card
  -> Live Journal
  -> Записать строку
  -> Зафиксировать журнал
```

This sprint rebuilds the user-facing journal surface without replacing the backend engine.

## Files Changed

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
```

## Done

### 1. Solo Path Is Real

Opening `Работаю один` now activates Solo workspace automatically.

This prevents the broken feeling where the user chooses Solo and then Live Journal still says that no workspace is selected.

### 2. Solo Workspace Uses Cash / Card Choice

Solo screen now directly presents:

```text
Cash
Card / Non-cash
```

The user does not need to pass through legacy On The Go screens.

### 3. Live Journal Rebuilt As Records Feed

Live Journal now has Product Bible structure:

```text
Header
Top service strip
Records Feed
Bottom work area
```

The main object is the records feed.

### 4. Approved MVP Input Pattern

Input is now one operational line:

```text
± Сумма и заметка...
```

Examples:

```text
-120 Топливо
+500 Получено
-45 Магазин
```

Pressing Enter or `Записать` saves the line.

### 5. Fix Journal Flow

When records exist, Live Journal shows:

```text
Зафиксировать журнал
```

For Solo, fixation uses existing engine behavior:

```text
on_the_go_signed_sync
replace_tape = 1
start_next = 1
```

The current journal closes and a new journal starts from the remaining amount.

### 6. Active Tape Is Preserved

Frontend now sends the current active `tape_id` into `on_the_go_signed_sync`.

Reason:

- without `tape_id`, backend can resolve another active tape;
- with `tape_id`, the visible journal and saved journal stay aligned.

### 7. Carry-Forward Amount Preserved

Frontend now sends current tape start amount instead of overwriting it with `0`.

This prevents the next journal after fixation from losing the previous remaining amount.

### 8. Cash / Card Separation Preserved

Cash:

- `+500 Received`
- `-120 Fuel`
- fixation creates next journal with start `380`.

Card:

- `-45 Store`
- cash remains `0`;
- next card journal starts at `0`.

### 9. Asset Version Updated

```text
20260603-live-journal1
```

Service worker cache:

```text
findesk-20260603-live-journal1
```

## Local Checks

Passed:

```bash
node --check public/assets/app.js
node --check public/service-worker.js
git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/service-worker.js
curl -I http://127.0.0.1:18889/app.php
curl -I http://127.0.0.1:18889/assets/app.js?v=20260603-live-journal1
curl -I http://127.0.0.1:18889/assets/app.css?v=20260603-live-journal1
```

Authenticated API smoke passed:

```text
Cash save:
saved tape_id=433 left=380

Cash fix:
fixed tape_id=433 next_tape_id=434 next_start=380.00

Carry-forward:
next start=380 left=350

Card fix:
card_out=45 cash_left=0 next_start=0.00
```

## Not Done Yet

Sprint 2 does not complete:

- visual browser QA;
- mobile keyboard QA;
- real attachment picker/viewer in the new Live Journal surface;
- edit/delete individual record UI;
- Team Workspace;
- Admin Card;
- Employee Card;
- pending transfer UI;
- Report Assembly;
- production deployment.

## Next Sprint

Sprint 3 target:

```text
Team Workspace = People Screen
Admin Card
Employee Card skeleton
Pending Transfer visible state
```

Definition of done:

```text
Welcome -> Работаю с людьми -> Team Workspace -> open Admin/Employee card
```

The screen must answer:

```text
С кем я сейчас работаю?
Кому выданы деньги?
Кто готовит или сдал журнал?
```
