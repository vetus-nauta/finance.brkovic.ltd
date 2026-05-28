# QA Handoff: Historical Finalized Report Backend

Date: 2026-05-26

## Current State

Backend/Data implemented separate historical finalized report/export APIs.

New actions:

- `ledger_group_final_report_list`
- `ledger_group_final_report_detail`
- `ledger_group_final_report_google_sheet`
- `ledger_group_final_report_excel`

New finalizations store `report_snapshot` in `audit_log.details` and return `report_id`.

Current export actions remain:

- `ledger_group_google_sheet`
- `ledger_group_excel`

Those current export actions must keep current open-period behavior.

## QA Scenario

```text
EUR 1000 income -> EUR 600 cash Live Report expense -> include -> finalize -> EUR 400 carryover
```

Verify:

- finalization returns `report_id`;
- historical detail/export by `report_id` shows `income=1000`, `expense=600`, `cash_balance=400`, `admin_cash_left=400`;
- current open-period export after finalization shows carryover `400` and does not show old `1000` as current income;
- later current-period entries do not change the selected historical final report/export;
- old finalizations without `report_snapshot`, if available, return `historical_snapshot_missing`.

## Output

Write results to:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

Do not change backend/API, formulas, or UX code.
