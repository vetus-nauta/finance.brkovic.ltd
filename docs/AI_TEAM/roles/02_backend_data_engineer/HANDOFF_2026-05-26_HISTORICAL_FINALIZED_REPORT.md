# Backend/Data Handoff: Historical Finalized Report

Date: 2026-05-26

## Product Contract

Product Finance Architect confirmed release requires two report truths:

- `Текущий период` / `Экспорт текущего периода`;
- `Закрытые финальные отчеты` / `Экспорт финального отчета`.

Current open-period export can keep switching to `Переходящий остаток` after finalization.

Historical finalized report/export must remain a first-class immutable product object.

## Scenario

```text
EUR 1000 income -> EUR 600 expense -> EUR 400 carryover
```

Required behavior:

- current export after finalization shows `EUR 400` as carryover and does not show old `EUR 1000` as current income;
- selected historical final report/export shows `EUR 1000` received, `EUR 600` spent, and `EUR 400` carried forward;
- later current-period entries do not change the selected historical final report/export.

## Suggested Implementation Direction

- Use `app/ledger.php::ql_ledger_group_finalize_report` as the moment to store an immutable snapshot.
- Reuse the pre-archive snapshot from `ql_ledger_group_export_snapshot`.
- Expose list/detail/export access by finalization identity.
- Keep the default `ledger_group_excel` and `ledger_group_google_sheet` behavior for current open-period export unless an explicit historical finalization id or separate historical action is requested.
- For old finalizations that do not contain a snapshot, return an explicit limitation such as `historical_snapshot_missing`; do not pretend a reconstructed current view is immutable.

## Candidate Files

- `app/ledger.php`
- `public/api.php`
- `scripts/local-smoke.php`

## Output

Write implementation result, limitations, and QA instructions to:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

Do not change UX code or financial formulas silently.
