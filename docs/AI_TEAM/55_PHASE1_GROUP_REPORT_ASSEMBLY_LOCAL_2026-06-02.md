# Phase 1 Local Report — Group Report Assembly

Date: 2026-06-02
Scope: local only
Status: done for local review, not deployed

## Goal

Rebuild the report assembly layer inside `FinDesk` so the administrator sees one clear block for:

- admin journal status
- employee journal statuses
- attached / not attached state
- current group total
- actions:
  - save report
  - print / PDF
  - send report

without changing backend formulas or API contracts.

## Files changed

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

## What changed

### 1. Group Report Assembly block

Inside the administrator card, the old final section was rebuilt into a dedicated assembly block.

Now it contains:

- summary metrics
- status roster
- action row
- short status text

### 2. Summary metrics

The assembly block now shows:

- administrator journal status
- ready-to-assemble count
- attached count
- current group total

### 3. Employee status roster

The assembly area now lists employees with money or report state and shows:

- name
- current state
- attached / not attached status
- current remaining cash
- direct `Открыть` action

### 4. Action row

The assembly actions are now expressed as:

- `Сохранить общий отчет`
- `Печать / PDF`
- `Отправить`
- `Состав`

### 5. Send / print behavior

This local slice reuses existing reporting infrastructure:

- if there is already a finalized report id, print/send opens the final report package flow
- if there is no finalized report id yet, send first asks to save the report

No new API endpoint was introduced.

## Backend safety

The assembly screen still reuses current hooks:

- `ledger_group_finalize_report`
- `on_the_go_card_include`
- `on_the_go_card_uninclude`
- `on_the_go_card_unsubmit`
- `advance_accept`
- `advance_unaccept`
- `ledger_group_final_report_package`
- `ledger_group_final_report_google_sheet`

## Checks completed

- `node --check public/assets/app.js`
- `git diff --check public/app.php public/assets/app.css public/assets/app.js`
- local live API smoke:
  - create group
  - invite member
  - join group
  - issue money
  - read `ledger_balance`
  - call `ledger_group_finalize_report`

## Note about finalize smoke

The live API smoke confirmed current backend behavior:

- `ledger_group_finalize_report` works
- if there are no included live cards yet, it returns `finalized = 0`

This is expected for the current backend contract.

## Limits in this environment

Still not available in this shell:

- `playwright`
- system browser
- `php` CLI

So this step is validated by:

- syntax
- diff integrity
- live API behavior

but not by browser automation in this environment.

## Next step

Next local step:

- one joined runtime pass across:
  - Live Journal
  - Team Workspace
  - Admin Card
  - Employee Card
  - Group Report Assembly
- then visual tightening before any deploy
