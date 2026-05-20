# Quick Ledger — Checkpoint After OTR-1 “На бегу”

Date: 2026-05-03  
Project path: `/home/brkovic/finance.brkovic.ltd`  
Live app: `https://finance.brkovic.ltd/app.php`

## Status

This checkpoint records the working state after adding the first “На бегу” / On The Go foundation.

## Product logic fixed at this point

“На бегу” is not normal accounting. It is a fast pending capture mode.

Main rules:

- Records from “На бегу” do not enter reports automatically.
- Records from “На бегу” do not affect normal ledger totals.
- Records stay in pending state with the visible red marker “Разобрать”.
- User can later review, correct, sort and move records into the normal Ledger.
- Capture first, structure later.

## Implemented in OTR-1

### UI

A new module tab was added:

- `На бегу`

Inside the module there are three large cards:

- `Денег поступило`
- `Траты cash`
- `Траты безнал`

Each card supports:

- amount input;
- optional description/signature;
- photo/document file selector;
- save button.

The module includes a simple journal/feed:

- entries are shown with the red marker `Разобрать`;
- photos/documents are not opened in the feed;
- file presence is shown only as an attachment marker.

### Calculation

The only calculation in this mode is operational cash check:

- cash received;
- cash spent;
- expected cash remaining.

Non-cash spending is recorded but does not reduce the expected cash remainder.

### Backend

New tables:

- `on_the_go_captures`
- `on_the_go_files`

New PHP file:

- `app/on_the_go.php`

New API actions:

- `on_the_go_create`
- `on_the_go_list`
- `on_the_go_upload_file`

### Recognition foundation

Database fields were reserved for future receipt/photo recognition:

- `recognition_status`
- `recognized_amount`
- `recognized_currency`
- `recognized_date`
- `recognized_vendor`
- `recognized_text`
- `recognition_confidence`
- `recognition_error`

Important: recognition is not active yet. Future recognition must work as a suggestion only, not as automatic accounting truth.

## Files changed in this stage

- `deploy/on_the_go_foundation.sql`
- `app/on_the_go.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `public/index.php` only for asset version bump

## Current asset version

`20260503-25`

## Confirmed working

Manual app check confirmed:

- `На бегу` tab appears.
- Three cards appear.
- Entries save successfully.
- Initial `server_error` was fixed by requiring `app/on_the_go.php` in `public/api.php`.
- Journal shows saved entries.
- Operational cash logic works.
- Entries remain marked `Разобрать`.
- On The Go records are separate from normal ledger/report logic.

## Confirmed technical checks

- `on_the_go_captures: YES`
- `on_the_go_files: YES`
- `php -l app/on_the_go.php` passed.
- `php -l public/api.php` passed.
- `php -l public/app.php` passed.
- `https://finance.brkovic.ltd/app.php` returns HTTP 200.
- Unauthenticated API requests return `not_authenticated`, not `server_error`.

## Known small cleanup

`public/api.php` has slightly uneven indentation around the newly inserted actions. It does not break execution and can be cleaned later during code hygiene.

## Next recommended stage

OTR-2 — review workflow:

- open a pending “Разобрать” record;
- edit amount/description/type;
- choose personal/group;
- choose section;
- convert into normal ledger entry;
- keep original attachment;
- mark capture as reviewed;
- archive/delete pending record safely if needed.

Keep reports clean until the user explicitly confirms the record.
