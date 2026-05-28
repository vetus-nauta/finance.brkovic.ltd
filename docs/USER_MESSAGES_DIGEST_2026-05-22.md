# User Messages Digest For New Chat

Date: 2026-05-22
Project: `finance.brkovic.ltd / FinDesk`
Purpose: preserve the user's actual intent, terminology and unresolved requirements for the next chat.

This is not a formal specification. It is a distilled reading guide from the user's messages during the long Live Report / FinDesk architecture work.

## Core User Frustration

The project got stuck because previous work kept patching calculations and UI fragments without preserving the main product idea.

The user repeatedly rejected:

- chaotic arithmetic fixes;
- screens that look like unrelated UI layers pasted together;
- desktop layouts that are just stretched mobile blocks;
- buttons with excessive direct text;
- unclear state transitions;
- missing ability to reopen and edit the exact saved card;
- deletion that does not delete the intended card;
- cards that open empty instead of with their saved rows;
- FinDesk/Advanced totals that are not obviously tied to real money on hand.

User's recurring theme:

```text
I must always understand where the money physically is, what was spent, what remains, and who is responsible for it.
```

## User's Product Philosophy

The product is not just a ledger. It is an operational control tool.

The user wants:

- speed;
- simplicity;
- mobile-first work in stores/field conditions;
- functional audit trail;
- no loss or distortion of numbers;
- easy visual control before handing final information upward.

The user described the control chain like:

```text
оператор -> старший смены -> начальник отдела -> директор
```

Meaning: each stage checks, prepares and passes reliable information to the next level.

## Naming And Product Layers

The old name "На бегу" felt weak/temporary. The user moved toward:

```text
Живой отчет
```

The product layers:

```text
Живой отчет
  -> fast mobile operational report
FinDesk
  -> review / moderation / package preparation
Advanced
  -> money source / issue money / roles / audit / AI / global arithmetic
Reports
  -> final clean output / export / printable report
Archive
  -> working-screen cleanup, not accounting action
Journal
  -> black-box recovery log
```

## iPhone Notes UX Requirement

The user wanted Live Report to behave like iPhone Notes:

- mobile version equals one desktop screen split into two mobile screens;
- screen 1: list of saved cards, like Notes list;
- screen 2: editor of the selected card, like opened note;
- tap saved card -> open that exact card with original rows;
- edit/erase rows;
- return to list;
- `+` creates a new note/card;
- completed/submitted notes become visually dimmed/closed;
- archive clears the working screen but preserves data.

Reference screenshots:

```text
docs/assets/iphone-notes-reference-list.png
docs/assets/iphone-notes-reference-note.png
```

## Mobile-First Requirement

The user said:

```text
с ноутбуком никто не бегает по магазинам
```

Required in Live Report:

- mobile-first layout;
- photo capture;
- document scan;
- choose from media library;
- fast signed-line input;
- no bulky admin UX inside the employee flow.

Desktop/tablet/mobile must be separate layout modes:

```text
desktop
tablet
mobile
```

because the same block arrangement does not work equally across device classes.

## Visual/UX Style Requirement

The user liked the current glass-like Live Report style after several iterations, but rejected earlier versions as:

- "простыня";
- no professional layout;
- no user-friendly interface;
- no product look;
- too much technical text.

Current visual rule captured separately:

```text
docs/KNOWLEDGE_UX_CSS_GLASS_RULE_2026-05-21.md
```

User asked:

- use iOS 26-like glass;
- make blocks visually separated;
- balance button sizes and colors;
- reduce direct button text;
- use icons/tooltips where possible;
- on mobile, explain through native-feeling hints, not clutter;
- do not use technical labels like "1 карточка";
- do not show noisy row counts when they do not help.

Important: this style should not yet be blindly applied to the whole project. It is a reference for later module-by-module redesign.

## Live Report Editing Requirement

Critical bug/requirement from user:

Saved card must reopen with its exact saved rows.

User repeated several times:

```text
при нажатии на созданную карточку открывается пустая карточка
не открывает текущую сохраненную карточку
результаты вычислений не работают после сохранения
```

This was a core failure point. New chat must recheck it manually after any Live Report change.

Expected behavior:

```text
tap saved card
  -> open working area with this card
  -> rows are restored
  -> view mode first
  -> small round pencil in note area
  -> tap pencil to edit
  -> pencil becomes check/fix button
  -> save/fix updates same card
```

## Delete Requirement

User insisted:

- delete happens from inside the card;
- after deletion the user returns to saved-report list;
- the deleted card must disappear;
- no hidden/overlaid card should remain physically stuck.

This was fixed once, but next chat must avoid reintroducing the old bug.

## Submit / FinDesk Sequential Rule

The user clarified an anti-error rule:

Because cash cards pass their remainder forward, cash Live Reports must be sent to FinDesk in order.

User meaning:

```text
You cannot submit two cash reports out of order,
because each next card's base depends on the previous card's remainder.
```

Important nuance from user:

- if user tries to submit the second card while the first is waiting;
- do not auto-submit the first card;
- do not silently fail;
- highlight the blocking first card;
- show a clear hint:

```text
Обработайте предыдущую запись в FinDesk.
```

This sequential guard is mainly for cash. Card stream later became independent because it starts from zero and does not carry cash base.

## Cash / Card Architecture From User's Final Clarification

The user fixed a major architectural confusion:

Before starting Live Report, there must be a choice:

```text
Наличные
Банковская карта
```

New rule:

- same interface;
- different ids/streams;
- different color themes;
- cash greenish;
- bank card yellowish;
- cash has money-stack metaphor;
- card has bank-card metaphor;
- streams never mix.

Card stream:

- no incoming amount;
- starts at zero;
- only card expenses;
- from zero into negative;
- same for all participants.

Cash stream:

- incoming cash;
- expenses;
- cash remainder.

Only intersection:

```text
separate cell in consolidated report:
Потрачено с карты
```

Advanced fully noncash transfers and complex bank integrations stay as stubs for first phase.

Implemented first stage:

```text
docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md
```

## Administrator / Employee Money Logic

User's full model:

### Administrator

The administrator has a pool of money entered into the system.

Admin uses their own Live Report.

When the admin submits a cash card into report:

```text
remaining cash becomes base for next cash card
```

Admin can add more money in Advanced:

```text
available cash = previous remaining cash + added money
```

### Employee

The employee does not have FinDesk access unless granted.

The employee receives a specific amount from admin.

That issued amount:

- is deducted/reserved from admin available cash;
- appears as employee accountable money;
- is not a group expense yet.

Employee uses Live Report/podotchet:

```text
issued amount
  - expenses
  = remaining employee cash
```

Employee submits podotchet to admin.

Admin reviews and approves merge into common report.

If employee still has cash:

```text
it remains in employee accountable money
or returns to admin pool depending on action
```

New employee card starts from the remaining amount they actually have.

## Groups And Roles

User model:

- admin creates groups;
- admin invites employees by email into a specific group;
- employee can later adopt more of the product if system offers it;
- but inside a specific group they only have rights given by that group's admin.

Roles/levels:

```text
base
manager
advanced/admin
```

Base employee should mostly see:

- their Live Report;
- their own submitted/archived reports;
- request correction/return where allowed.

Admin sees:

- groups;
- employees;
- issued money;
- submitted podotchets;
- FinDesk;
- Advanced;
- archive folders by employee;
- journal.

## FinDesk Semantics

User repeatedly corrected FinDesk layer confusion.

Correct model:

```text
Submitted reports
  -> reports waiting for FinDesk review
Included in report
  -> reports accepted into the working package for final report/export
Archive
  -> only clears Live Report working screens
Reports page
  -> stores final beautiful report/export packages
```

Important:

- "Included in report" is not archive.
- Archive is a button/modal, not a numbered workflow card replacing accounting states.
- The included package should show how many cards are included and allow viewing/returning them.
- Returning from included package goes back to FinDesk/review, not to a random layer.
- Admin does not need "request return"; admin can return directly.
- Employee uses "Запросить исправление" from their own Live Report card when their submitted/included card is locked.

Unresolved / still needs serious work:

- FinDesk UI still needs full redesign under the new architecture.
- FinDesk modal button semantics need continued cleanup.
- Included package needs a clean modal/list.
- Archive access/folders need clearer user/admin behavior.
- Final Reports page needs a real model for saved/exported final packages.

## Archive Requirement

User's archive model:

- archive is a cleanup action for completed Live Report cards;
- archive does not change calculations;
- archive does not send data to final report;
- archive must not bypass FinDesk preparation;
- archive modal should show archived cards;
- all users should have access to their own archive;
- admin should see own archive and employee archive folders in the group.

For employees:

- after admin includes their report, employee can no longer edit it;
- they can clear working screen by moving completed cards to archive;
- they can request correction/return if something needs fixing.

For admin:

- included own cards also should not be editable until returned from included/review state.

Unresolved:

- archive modal/folders need more UX work;
- archive in FinDesk and Live Report must remain separate from final Reports page.

## Journal / Black Box Requirement

User requested a robust automatic server-side log:

```text
как автомат калашникова
```

Meaning:

- simple;
- reliable;
- automatic;
- saved in a safe server folder;
- records all Live Report actions sequentially;
- includes dates, actors, stream/type, statuses;
- useful for manual recovery after failure.

Current work introduced/expanded journal behavior, but it needs continued hardening.

Desired events:

- live report saved;
- submitted;
- included;
- returned;
- archived;
- deleted/cancelled;
- advance issued;
- employee report accepted/returned;
- before/after summary.

Open question:

- whether journal should be row/table-based, append-only JSONL/CSV, or both.

User wants a visually usable admin log button as well.

## Advanced Page Concerns

User asked to rethink the entire Advanced page like a good accountant.

The page should always answer:

```text
How much money was introduced?
How much is physically with me?
How much is physically with employees?
How much has been spent in closed reports?
How much is in open reports?
How much is card spending?
What is the expected physical cash right now?
```

The user was worried about unexpected sums like:

```text
к 60 еще 3315
```

Meaning: there are still unclear totals on Advanced, and they must be audited.

Unresolved:

- Advanced dashboard needs a full redesign around accounting buckets;
- old totals may still be confusing;
- cash/card/accountable-money split must be visible.

## Reports Page

User said the reports page was not done.

Correct role of Reports:

- final beautiful Excel/report document;
- saved final package;
- not the same as Live Report archive;
- not the same as FinDesk included working package.

Unresolved:

- define final report lifecycle;
- define printable/exportable Excel structure;
- separate cash expenses, card expenses, total expenses, remaining cash, employee attribution.

## What Has Been Implemented Recently

Implemented in latest session:

- `Живой отчет` cash/card selection screen;
- `stream_type = cash|card`;
- card stream does not affect cash;
- card creates `noncash_out`;
- cash/card list filtering;
- cash/card new card creation;
- green/yellow themes;
- main choice screen visually rebuilt;
- list back arrow returns to cash/card choice;
- smoke test for card/cash invariants.

Verified:

```text
php scripts/local-smoke.php http://127.0.0.1:18889
```

passed.

## What Is Not Yet Implemented / Next Front

Highest priority unresolved work:

1. Manually verify the new Live Report UX on real desktop/tablet/mobile sizes.

2. Rework FinDesk under the new architecture:
   - no noisy numbered workflow cards;
   - modes hidden in menu/modal;
   - equal card sizing and spacing;
   - stream label on cards;
   - submitted/included/archive semantics clean and separate.

3. Rework Advanced accounting dashboard:
   - total introduced funds;
   - administrator physical cash;
   - employee physical cash/accountable money;
   - open submitted reports;
   - included working package;
   - closed reports;
   - card expenses separately;
   - expected physical cash now.

4. Build final Reports model:
   - final report/export package;
   - beautiful Excel;
   - saved report history;
   - not mixed with archive.

5. Harden the automatic journal:
   - append-only;
   - safe server folder;
   - readable admin UI;
   - recovery-friendly data.

6. Polish employee archive/request-correction flow:
   - employee sees own archive;
   - employee requests correction on locked card;
   - admin directly returns cards without "request" button.

7. Continue removing unclear text/buttons:
   - avoid technical labels;
   - reduce button text;
   - use icons/tooltips where possible;
   - keep mobile interaction obvious.

## User Tone / Important Working Instruction

The user wants direct, decisive work, not endless explanations.

But the user also wants the model to be understood before coding.

Best approach for next chat:

1. Read the handoff docs.
2. Repeat back the architecture briefly.
3. Inspect current code and UI.
4. Make focused changes.
5. Run smoke/lint.
6. Explain exactly what changed and what remains.

Do not pretend everything is solved. The latest implemented piece is only the first stage: cash/card Live Report separation. The bigger FinDesk/Advanced redesign still remains.
