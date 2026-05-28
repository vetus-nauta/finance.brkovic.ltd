# Handoff: Closed Group Report Package QA

Date: 2026-05-27

From: Project Director

To: QA Release Engineer

Priority: P0

## Read First

- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

Backend/Data implemented the closed group report package source.

Frontend/UX implemented the package UI.

User-facing object: `Закрытый групповой отчет`.

Backend fixture:

- `group_id=221`
- `report_id=441`
- proof id: `proof-441-on_the_go_capture-12`
- advance id: `65`

## Task

Verify the `Закрытый групповой отчет` package as a user-facing archive object.

This QA must prove the user opens one closed report case, not a summary-only export and not a manual stitch of technical endpoints.

## Required Checks

- Run browser/HTTP QA on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.
- Open the closed package by `report_id` from closed reports/archive UI.
- Confirm the visible object is labeled `Закрытый групповой отчет`.
- Confirm the package view loads through `ledger_group_final_report_package`.
- Confirm the view is not summary-only.
- Confirm visible sections:
  - group summary;
  - participant reports / `Отчет участника`;
  - captures / money rows;
  - proofs near the numbers they prove;
  - accountable/advance state;
  - report-context messages or backend-provided message references;
  - audit/finalization references.
- Confirm proof links use package proof metadata/download URL and authorized reviewer download works.
- Confirm card/noncash spending is visibly separate from physical cash.
- Confirm accountable/open remaining employee cash is responsibility/carryover, not expense.
- Confirm Excel/Google actions are labeled as short tables, not full package exports.
- Confirm package print/PDF action is available and includes package sections.
- Confirm legacy/no-package fallback, if encountered, clearly warns that it is not the new closed group report package.
- Confirm mobile/tablet/desktop layout has no blocking overlap or unreachable package actions.

## Data Integrity Checks

- Use Backend fixture `group_id=221`, `report_id=441` if still available.
- If QA creates a fresh fixture, record group/report/proof/advance ids.
- Verify later current-period activity does not mutate the selected closed package.
- Verify the package does not rely on owner-only Live Report proof download.

## Acceptance Decision

PASS only if a non-accountant can open one closed package and answer:

- how much the group received;
- who held or spent money;
- what was cash vs card/noncash;
- what remains accountable/open responsibility;
- what proof supports each number;
- who/finalization/audit references are visible;
- where print/PDF and short table exports are.

BLOCKED if:

- package view is summary-only;
- participant reports/proofs/accountable state are missing;
- authorized proof download fails;
- card spending reduces physical cash;
- accountable issue is shown as expense;
- closed package mutates after later current activity;
- mobile/tablet/desktop UI makes package unusable.

## Output

Update:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: QA/Release
Task: Closed group report package QA
Status: PASS / BLOCKED
Evidence pointer: `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
Blocker: ...
Next owner: Chief Auditor / Frontend UX Engineer / Backend Data Engineer / Project Director
