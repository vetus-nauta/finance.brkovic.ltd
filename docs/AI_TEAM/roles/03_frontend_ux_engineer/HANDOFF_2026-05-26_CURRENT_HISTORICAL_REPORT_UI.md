# Frontend/UX Handoff: Current And Historical Report Actions

Date: 2026-05-26

## Owner

Frontend UX Engineer

## Current State

Backend/Data and QA confirmed the backend contract:

- current export actions remain current open-period truth;
- historical final report actions read immutable snapshot by `report_id`;
- current export after finalization shows carryover and current-period entries, not old finalized income;
- historical export remains `1000 / 600 / 400` after later current-period entries.

Product Finance Architect approved labels:

- `Текущий период`
- `Экспорт текущего периода`
- `Закрытые финальные отчеты`
- `Экспорт финального отчета`

## Task

Wire or confirm the user-facing UI for current vs historical report actions.

## Required UX Meaning

Current period:

- user opens current live money as `Текущий период`;
- current export uses existing current endpoints:
  - `ledger_group_excel`
  - `ledger_group_google_sheet`;
- carryover is labeled as `Переходящий остаток из финального отчета`;
- old finalized `1000` must not look like current income.

Closed final reports:

- user opens history as `Закрытые финальные отчеты`;
- selected closed report uses historical endpoints:
  - `ledger_group_final_report_list`
  - `ledger_group_final_report_detail`
  - `ledger_group_final_report_excel`
  - `ledger_group_final_report_google_sheet`;
- selected closed report export is labeled `Экспорт финального отчета`;
- old report shows `1000 / 600 / 400` as historical truth.

## Candidate Files

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`

## Guardrails

- Do not change backend/API behavior.
- Do not change financial formulas.
- Do not merge current export and historical export into one ambiguous button.
- Do not add broad redesign beyond MVP wiring.
- Keep mobile compact and avoid dense tables for field work.

## Output

Write full findings, implementation notes, and any QA handoff to:

- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

Send only a short report to the CEO / Project Director chat.
