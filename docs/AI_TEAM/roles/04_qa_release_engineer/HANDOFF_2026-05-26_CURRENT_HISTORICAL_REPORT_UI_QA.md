# QA Handoff: Current And Historical Report UI

Date: 2026-05-26

## Owner

QA Release Engineer

## Current State

Frontend/UX implemented the MVP UI separation:

- `Текущий период`
- `Экспорт текущего периода`
- `Закрытые финальные отчеты`
- `Экспорт финального отчета`

Current export remains wired to:

- `ledger_group_excel` with `group_id`
- `ledger_group_google_sheet` with `group_id`

Historical export is wired to:

- `ledger_group_final_report_list`
- `ledger_group_final_report_detail`
- `ledger_group_final_report_excel` with `report_id`
- `ledger_group_final_report_google_sheet` with `report_id`

Frontend/UX verification:

- `node --check public/assets/app.js` passed.
- `git diff --check` passed.
- PHP lint was not available because CLI PHP is missing.

## QA Task

Verify the user-facing current/historical report flow on desktop/tablet/mobile.

Use a group with:

```text
1000 income -> 600 Live Report expense -> finalized report -> carryover 400 -> current income 50 -> current Live Report expense 25
```

Verify:

- current period UI is labeled `Текущий период`;
- current export action is labeled `Экспорт текущего периода`;
- current export stays current open-period truth and does not show old finalized income `1000` as current income;
- closed reports UI is labeled `Закрытые финальные отчеты`;
- selected historical report shows a `report_id`;
- historical export action is labeled `Экспорт финального отчета`;
- historical export remains `1000 / 600 / 400`;
- later current entries do not appear inside the historical report/export;
- mobile/tablet/desktop controls do not overlap and export actions are reachable.

## Output

Write full results to:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

Send only a short report to the CEO / Project Director chat.
