# Handoff: Closed Group Report Archive Package Implementation

Date: 2026-05-26

From: Project Director

To: Backend Implementation Queue

Priority: P0

## Read First

- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/04_TASK_BOARD.md`
- `docs/AI_TEAM/05_DECISIONS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

Product Finance Architect accepted the package contract.

User-facing object: `Закрытый групповой отчет`.

Business MVP requires one immutable package source by `report_id`. A group summary/export alone is not enough.

Current backend can create a group final report snapshot/export for prepared rows and totals, but does not yet expose the full closed package containing participant reports, captures, proofs, accountable state, report-context messages, and audit references.

## Task

Implement the backend/API source for `Закрытый групповой отчет` by `report_id`.

The package must let Frontend/UX open one closed report object without manually stitching group final report detail, Live Report card detail, proof file endpoints, advance endpoints, messages, and audit log.

## Required Package Contents

Implement or extend the package source so it returns:

- group report identity: `report_id`, group identity/name, closed period or finalization scope, finalization time, finalizer/reviewer, status;
- group financial summary: received money, physical cash spent, card/noncash spending, admin/group cash left, accountable money, returned cash, discrepancy, carryover;
- participant report snapshots with immutable participant report identity inside the package;
- included captures/money rows with amount, type, participant, cash/card effect, accountable effect, comments/category/source references, and timestamps where available;
- proof index and authorized reviewer proof metadata/download access that does not depend on original file ownership;
- accountable/advance state frozen at finalization: accepted spend, returned cash, open remaining cash, discrepancy, carryover responsibility by participant;
- report-context messages or immutable message references for missing proof question, return for clarification, participant clarification/reply, proof-added/updated review note, and acceptance note;
- audit/finalization references without requiring a separate journal screen;
- export/print identity or enough metadata for Frontend/UX to bind export/print actions to the same package.

## Backend Boundaries

- Do not change financial formulas.
- Card spending must stay noncash and must not reduce physical cash.
- Issuing money to employee remains accountable movement, not expense.
- Open remaining employee cash stays responsibility/carryover, not expense.
- Later current-period entries must not mutate the closed package.
- Later advance changes must not rewrite the closed package.
- General group chat changes after finalization must not rewrite report-context messages already captured in the package.
- Keep credentials out of files and reports.

## Suggested API Shape

Use existing naming style. Proposed action:

- `ledger_group_final_report_package`

Input:

- `report_id`

Expected output:

- `ok: true`
- `package_type: group_final_report`
- `report_id`
- `group`
- `finalization`
- `summary`
- `participants`
- `captures`
- `proofs`
- `accountable`
- `messages`
- `audit_refs`
- `exports`

If the implementation uses different names, document the contract clearly in `FINDINGS.md`.

## Smoke / Fixture

Extend backend smoke or HTTP fixture for:

- group receives money into common pot;
- participant A cash expense with proof;
- participant B card/noncash expense with proof;
- participant C accountable/advance path with accepted spend and returned or open remaining cash;
- report-context message or message reference around missing proof / clarification / acceptance if current message schema can support it;
- finalization into `Закрытый групповой отчет`;
- package read by `report_id`;
- proof access as authorized reviewer;
- later current-period activity that does not mutate the package.

## Acceptance Criteria

- One backend/API source opens the package by `report_id`.
- Package contains group summary, participant reports, captures, proofs, accountable/advance state, report-context messages or accepted MVP references, and audit refs.
- Authorized reviewer can inspect/download proofs for included participant reports.
- Closed package is immutable after later current-period activity.
- No formula regression in cash/card/accountable handling.
- If current message schema cannot support report-context messages cleanly, document the minimum implementation and exact P0/P1 follow-up instead of silently omitting it.

## Output

Update:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: Backend/Data
Task: Closed group report archive package implementation
Status: implemented / blocked
Files changed: ...
Evidence pointer: `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
Blocker: ...
Next owner: Frontend UX Engineer / QA Release Engineer / Project Director
