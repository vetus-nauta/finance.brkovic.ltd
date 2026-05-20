# Quick Ledger — Checkpoint After OTR-2C / OTR-2D / LEDGER-2A

Date: 2026-05-03  
Project path: `/home/brkovic/finance.brkovic.ltd`  
Live app: `https://finance.brkovic.ltd/app.php`

## Status

This checkpoint records the working state after closing the first full “On the Go → Ledger” cycle.

## Confirmed working

### On the Go / pending capture

- `On the Go` module exists.
- Three large capture cards exist:
  - `Cash received`
  - `Cash spent`
  - `Card / non-cash spent`
- Records from On the Go are pending by default.
- Pending records are not included in normal Ledger reports automatically.
- Pending records carry review logic.
- Operational cash check works:
  - cash received;
  - cash spent;
  - expected cash left.
- Journal/feed works.

### Review modal

- Review modal opens from On the Go journal.
- Record can be edited before final decision.
- Attachments are visible in the Review modal.
- Attachments can be opened/downloaded.
- New attachments can be uploaded.
- Attachments can be deleted from pending record.
- Modal was compacted in OTR-2D:
  - internal scrolling;
  - smaller fields;
  - compact attachment panel;
  - compact Move to Ledger panel;
  - better mobile/desktop fit.

### Convert to Ledger

- `Convert to Ledger` works.
- User can choose:
  - Personal / Group;
  - Group, when group mode is selected;
  - Section;
  - Ledger type: Income / Expense;
  - Money type: Cash / Non-cash.
- If no section is selected, the system creates or uses default section:
  - `On the Go`
- Auto-prefill rules work:
  - Cash received → Income + Cash
  - Cash spent → Expense + Cash
  - Card / non-cash spent → Expense + Non-cash
- After conversion:
  - pending record leaves On the Go review list;
  - normal Ledger entry is created;
  - attachments are copied into normal Ledger files;
  - On the Go capture is marked reviewed.

### Ledger details

- Main Ledger now has a `Details` action for entries.
- Entry details modal opens.
- Modal shows:
  - amount;
  - income/expense;
  - cash/non-cash;
  - section;
  - owner;
  - date;
  - purpose;
  - note;
  - file count.
- Entries converted from On the Go show marker:
  - `Converted from On the Go`
- Attachments in normal Ledger entry are visible.
- Attachments can be opened/downloaded from Ledger details.

## Backend / API added

### On the Go

- `ql_on_the_go_convert_to_ledger()`
- `ql_otr_ensure_on_the_go_category()`
- `on_the_go_convert_to_ledger`

### Ledger details

- `ql_ledger_visible_entry()`
- `ql_ledger_detail()`
- `ql_ledger_file_rows()`
- `ql_ledger_file_list()`
- `ql_ledger_file_download()`
- `ledger_detail`
- `ledger_file_list`
- `ledger_file_download`

## Current asset version

- `20260503-31`

## Files changed in this checkpoint range

- `app/on_the_go.php`
- `app/ledger.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/index.php` only for asset version bump

## Important product logic preserved

“On the Go” is not normal accounting.  
It is a fast pending capture mode.

Rules:

- Capture first, structure later.
- Nothing enters reports automatically from On the Go.
- User must review before converting.
- Default sorting fallback is `On the Go`.
- Recognition/OCR remains future suggestion-only logic, not accounting truth.

## Known next work

### LEDGER-2B / future

- Improve Ledger Details visual polish if needed.
- Add better edit flow from details modal.
- Possibly allow replacing/deleting Ledger attachments later.
- Add clear audit/source fields instead of relying only on note text.
- Add OCR/photo recognition later as suggestion only.
- Add No Stress mode as separate simplified capture workspace.
- Continue i18n work so English/Russian/etc. are controlled by translation layer rather than hardcoded mixed UI.
- Continue module separation and mobile polish.

## Verification at checkpoint

Expected checks:

- PHP lint clean:
  - `app/on_the_go.php`
  - `app/ledger.php`
  - `public/api.php`
  - `public/app.php`
- Live app returns HTTP 200.
- User confirmed in UI:
  - Convert to Ledger works.
  - Ledger Details works.
  - Attachments open from Ledger Details.
