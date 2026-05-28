# Backend/Data Handoff: Current Export Combo Regression

Date: 2026-05-26

## QA Result

Historical finalized report snapshot/export works for new finalizations.

Pass evidence:

- QA user: `qa-historical-final-20260526153331-diag@example.test`
- group_id: `191`
- report_id: `342`
- card_id: `171`
- historical totals: `income=1000`, `expense=600`, `cash_balance=400`, `admin_cash_left=400`

## Blocker

Current open-period export fails when post-finalization income and a current included Live Report coexist.

Fail evidence:

- QA user: `qa-combo-20260526153406-combo@example.test`
- group_id: `192`
- report_id: `348`
- current income ledger entry: `84`
- current Live Report tape: `175`

Actual:

- historical detail/export remains correct: `1000 / 600 / 400`;
- current export shows carryover `400`;
- current export shows current Live Report expense `25`;
- current export does not show current income `50`;
- `ledger_group_open_received_funds` returns `entries: [{"id":175}]` instead of the current income row.

Expected:

- current export contains carryover `400`;
- current export contains current income `50`;
- current export contains current Live Report expense `25`;
- current export does not show old finalized income `1000` as current income;
- selected historical final report/export remains unchanged at `1000 / 600 / 400`.

## Likely Code Area

`app/ledger.php::ql_ledger_group_open_received_funds`.

Check the by-reference loop:

```php
foreach ($rows as &$row) {
```

and later reuse of `$row` in another loop. In PHP, failing to `unset($row)` after a by-reference foreach can overwrite the last element of `$rows`.

## Output

Write fix result and QA handoff to:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

Do not change UX code or financial formulas.
