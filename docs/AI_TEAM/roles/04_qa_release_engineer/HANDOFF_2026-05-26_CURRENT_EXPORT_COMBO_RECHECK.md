# QA Handoff: Current Export Combo Recheck

Date: 2026-05-26

## Backend Fix

Backend/Data fixed a PHP by-reference overwrite in:

```text
app/ledger.php::ql_ledger_group_open_received_funds
```

Patch:

```php
unset($row);
```

after:

```php
foreach ($rows as &$row)
```

## Backend Fixture

- group_id: `194`
- report_id: `364`
- old finalized income entry: `87`
- old finalized tape: `179`
- current income entry: `88`
- current Live Report tape: `181`

Backend/Data reported:

- historical detail/export remained `1000 / 600 / 400`;
- current export contains carryover `400`;
- current export contains current income `50`;
- current export contains current Live Report expense `25`;
- current export does not contain old finalized income `1000`;
- `ledger_group_open_received_funds.entries` returns current income row `id=88`, `amount=50`.

## QA Task

Rerun the combined scenario independently:

```text
1000 income -> 600 Live Report expense -> finalize -> carryover 400 -> current income 50 -> current Live Report expense 25
```

Verify:

- historical final report detail/export stays `1000 / 600 / 400`;
- current export contains carryover `400`, current income `50`, and current Live Report expense `25`;
- current export does not show old finalized income `1000` as current income;
- `ledger_group_open_received_funds.entries` returns the current income ledger row, not a Live Report tape row.

## Output

Write results to:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

Do not change backend/API, formulas, or UX code.
