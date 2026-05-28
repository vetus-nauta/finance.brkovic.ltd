# Handoff: Closed Group Report Package UI

Date: 2026-05-27

From: Project Director

To: Frontend UX Engineer

Priority: P0

## Read First

- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/04_TASK_BOARD.md`
- `docs/AI_TEAM/05_DECISIONS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

Backend/Data implemented the package source for `Закрытый групповой отчет`.

Backend actions:

- `ledger_group_final_report_package`
- `ledger_group_final_report_proof_download`

Backend evidence:

- HTTP fixture `group_id=221`, `report_id=441`;
- package includes group summary, participant report snapshots, captures, proof index/download access, accountable/advance state, messages/audit references, and export action metadata;
- formulas were not changed.

## Task

Expose `Закрытый групповой отчет` as one ordinary archive object in the UI.

The user must not manually stitch group final report detail, Live Report card detail, proof endpoints, advances, messages, and audit log. Opening the closed report must feel like opening one closed case.

## Required UI Behavior

- From closed reports/archive, open the selected `Закрытый групповой отчет` by `report_id`.
- Load the package through `ledger_group_final_report_package`.
- Show package sections:
  - group summary;
  - participant reports / `Отчет участника`;
  - captures and money rows;
  - proofs near the numbers they support;
  - accountable/advance state;
  - report-context messages or backend-provided message references;
  - audit/finalization references;
  - export/print actions bound to this package.
- Use ordinary labels, not endpoint/table names.
- Keep `Отчет участника` visibly distinct inside the group package.
- Show proof access/download through package proof metadata, not owner-only Live Report file flow.
- Keep general group chat separate from report-context messages.
- Clearly show if message context is audit-derived/unlinked rather than direct report chat.
- If a full package-wide file export is not possible with current backend, provide a print-friendly package view and write the exact Backend/Product follow-up in `TASKS_TO_OTHERS.md`.

## Boundaries

- Do not change financial formulas.
- Do not change backend/API.
- Do not rename the product object away from `Закрытый групповой отчет`.
- Do not collapse this into a summary-only export.
- Do not hide participant responsibility, accountable cash, proof status, or cash/card split.
- Keep full evidence in the Frontend/UX role folder.
- Chief chat receives only the short report template.

## Acceptance Criteria

- User opens one closed package by `report_id` from archive/closed reports.
- A non-accountant can answer who received, held, spent, returned, or still owes money.
- Card/noncash spending is visibly separate from physical cash.
- Accountable/open remaining employee cash is shown as responsibility/carryover, not expense.
- Proof links are reachable to authorized reviewers from the package view.
- The UI does not require knowledge of ledger/tape/capture/advance/file/message/audit endpoint names.
- Mobile layout remains readable and does not become a dense table.
- QA can run the multi-participant package scenario after this task.

## Output

Update:

- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: Frontend/UX
Task: Closed group report package UI
Status: done / blocked
Files changed: ...
Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
Blocker: ...
Next owner: QA Release Engineer / Project Director
