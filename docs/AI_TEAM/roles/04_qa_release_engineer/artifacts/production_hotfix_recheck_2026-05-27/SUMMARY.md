# Production Hotfix Recheck Summary

Date: 2026-05-27

Role: QA Release Engineer FinDesk

Task: production recheck after participant-control and default invited base employee rights hotfixes.

Status: BLOCKED / P0

Production target: `https://finance.brkovic.ltd`

Run stamp: `20260527211338`

## Recheck A: Participant Control

Status: PASS

Fresh production fixture:

- group_id: `17`
- report_id: `176`

Verified:

- scenario `1000` received; advances `135 / 94 / 117`; admin expenses `20 / 45 / 17 / 4`; employee 1 expenses `6 / 9 / 43 / 10`; employee 2 expenses `12 / 23 / 41 / 54`; employee 3 no spend;
- current export, final detail, closed package, Google/TSV export, and Excel export expose expenses `284` and balance `716`;
- `admin_cash_left=568`;
- employee positive remaining `184`;
- employee reimbursement due `36`;
- employee net remaining `148`;
- employee rows include signed `67`, `-36`, `117`;
- employee 2 reimbursement due `36` is visible outside raw audit refs;
- archive/package opens by `report_id`.

Saved artifacts:

- `participant_current_group_google_sheet.tsv`
- `participant_current_group_report.xls`
- `participant_final_report_detail.json`
- `participant_closed_group_package.json`
- `participant_final_report_google_sheet.tsv`
- `participant_final_group_report.xls`
- `participant_closed_group_package_print.html`

## Recheck B: Default Base Employee Rights

Status: BLOCKED / P0

Fresh production fixture:

- group_id: `18`
- report_id: `184`
- base employee user_id: `54`

Passed before blocker:

- default invite creates `access_level=base`;
- joined employee has base permissions: no group report view, no group ledger write, no money management;
- admin can still create income, send group message, create accountable money, include a card, finalize a report, list members, and list final reports;
- base employee sees only self in `group_members`;
- base employee is denied current group export;
- base employee is denied final report list/detail/package/export;
- base employee is denied group message list and send;
- base employee is denied money management;
- base employee is denied role management.

Blocker:

- `message_unread` for the base employee returns `server_error` / HTTP `500`.
- Error points to SQL syntax near alias `current_role`.
- Expected: safe response with `ok=true`, `unread_count=0`, and no group messages exposed to base employee.

Failure artifact:

- `production_hotfix_recheck_failure.json`

## Release Position

Participant-control hotfix is accepted by this QA recheck.

Default base employee rights hotfix is not accepted until Backend/Data fixes `message_unread` and QA reruns the rights slice.

No backend/API/UX/financial formulas were changed by QA.
