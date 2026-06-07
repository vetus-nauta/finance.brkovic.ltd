# Personal Journal / Records / Reports Discipline

Date: 2026-06-07
Status: product logic lock, implementation pending
Scope: Universal Cash Session personal-mode behavior first; later reused for Yacht/Home/Family/Road where applicable.

## Core Principle

ЖЗ is not a list page and not a report page.

The product model is:

```text
Reports / Учет -> Records / Записи -> active record card -> ЖЗ editor
```

- `ЖЗ` edits one active record only.
- `Записи` manages active/saved record cards.
- `Отчеты` manages accounting/report containers.
- `Архив` stores closed reports/sessions.

## Strict ЖЗ Line Rule

Accepted into calculation:

- `+500` or `+500 text` -> income / поступило;
- `-300` or `-300 text` -> expense / расход.

Not accepted into calculation:

- `300` without sign;
- `=300`;
- `_300`;
- any other non-matching text.

Rejected/current invalid line behavior:

- show the exact line in red;
- show a short explanation that the line is not accepted into calculation;
- keep the line as a note/draft text, but do not include it in totals.

## ЖЗ Page

The ЖЗ page must remain a working editor.

It shows:

- active record/report context;
- one running balance inside the current ЖЗ;
- accepted line count;
- ignored/not-in-calculation line count;
- current-line warning;
- save/autosave status;
- attachment button.

It does not show:

- a full list of records below the editor;
- archive;
- report management;
- participant/team settings;
- settlement policy switches.

Primary button behavior:

- if no accepted lines exist: `К записям`;
- if accepted lines exist: `Зафиксировать и к записям`.

## Records / Записи Page

Records page is the card-management page.

It must always show summary values for the currently selected report/accounting context:

- `Входящая сумма` - report/account opening amount;
- `Поступило` - sum of accepted `+` lines attached to the selected report/account;
- `Остаток` - calculated from the selected report/account context.

If the user scroll-selects a report/account:

- record cards attached to that report/account are primary/active visually;
- unrelated cards are visually dimmed;
- page totals are calculated for the selected report/account.

If cards are not attached to any report/account:

- the records page must still show a `Без учета` / unattached context in the horizontal/scroll selector;
- unattached cards become the first visual layer when that context is selected;
- totals are calculated without report opening income;
- practically this means the unattached context shows the general expense/minus movement without a report incoming amount.

The Records page includes:

- `Новая запись` above the cards;
- active draft card with date/time if the user started writing but did not fix the record;
- saved/fixed record cards;
- visual dimming for cards outside the selected report/account context;
- ability to assign/reassign a record card to a report/account.

The Records page must not be placed under the ЖЗ editor.

## Draft / Autosave Discipline

When a user starts typing in ЖЗ:

- an active draft record card is created/updated;
- it has date/time;
- it survives leaving the page;
- it appears on `Записи`;
- leaving without pressing save must not lose data.

If a user starts a new record while an unbound/unfinished draft exists:

- show a modal with choices:
  - continue in the same report/account;
  - create a new report/account;
  - continue without report/account.

## Reports / Отчеты Page

Reports page manages report/account containers.

It includes:

- create report;
- start report;
- fix/lock report;
- print;
- save;
- send to archive.

Starting a report asks for:

- report title;
- opening/incoming amount.

After start:

- the first ЖЗ opened from that flow is attached to the started report;
- any record can later be attached/reassigned to another report through a selector.

Fixed reports are shown as simple entries:

- title;
- fixed date;
- view;
- delete;
- restore/unfix.

## Attachment / Paperclip Discipline

ЖЗ has a paperclip button.

The modal contains:

- take photo;
- choose from gallery;
- view saved attachments.

Attachment must bind to the active record/JЗ context and later to a line where applicable.

## Mobile-First Rule

- primary work must fit one screen;
- when content overflows, use internal scroll areas without visible scrollbar where practical;
- avoid long accounting pages;
- preserve consistent behavior across desktop, tablet and phone.

## Implementation Order

1. Fix strict parser and warning behavior.
2. Remove duplicated personal/journal titles.
3. Add active draft record autosave card.
4. Rebuild Records page around report/account context selector.
5. Add report/account create-start-fix flow.
6. Add attachment modal.
7. Apply the same behavior discipline to Yacht/Home/Family/Road presets where applicable.
