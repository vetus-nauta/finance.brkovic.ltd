# Handoff: Immutable Group Report Archive Package Contract

Date: 2026-05-26

From: Project Director

To: Product Finance Architect

Priority: P0

## Read First

- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md`
- `docs/AI_TEAM/04_TASK_BOARD.md`
- `docs/AI_TEAM/05_DECISIONS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

Backend/Data traced the next business-MVP block and blocked full business MVP.

Current backend can partially produce a group final report snapshot/export from included non-advance Live Report cards, but it does not provide one immutable archive package that a normal manager can open later and trust.

Backend blocker:

- no single closed package by `report_id`;
- linked participant reports are not frozen as first-class report artifacts;
- proofs are stored separately and owner-scoped;
- accountable/advance state is not frozen as part of the package;
- group messages are group-scoped only, not report-linked;
- archive currently requires stitching multiple endpoints by hand.

## Task

Define the product and financial contract for the immutable group report archive package.

The contract must tell Backend/Data exactly what a closed group report package must contain for business MVP, what can remain post-MVP, and how ordinary users should understand it.

## Required Product Decisions

Answer directly in `FINDINGS.md`:

- What is the user-facing object name: `Групповой отчет`, `Закрытый групповой отчет`, `Архивный пакет`, or another term?
- What must be inside the immutable package at MVP close?
- Should each participant report have its own immutable identity inside the group package?
- Must proof files be directly visible/downloadable to authorized reviewers from the package?
- How should accountable money/advance state appear: accepted spend, returned cash, open remaining cash, discrepancy, carryover responsibility?
- Are report-context messages part of the immutable package for MVP, or only linked group discussion?
- Which message events must be preserved: missing proof question, return for clarification, acceptance note?
- What does print/export include at MVP: group summary only, participant breakdown, proof index, messages index, audit references?
- What must archive open in one click for a non-accountant?
- What remains post-MVP/Advanced without deleting product memory?

## Minimum Money Tree To Preserve

The package must let a normal person answer:

- how much money the group received;
- who held or spent the money;
- what stayed in physical cash;
- what was card/noncash spending;
- what was accountable money;
- what was returned or carried forward;
- what proof supports each number;
- who accepted the report;
- when it became final;
- where the closed package lives in archive.

## Boundaries

- Do not change financial formulas.
- Do not change backend/API.
- Do not change frontend/UX.
- Do not declare full business MVP ready.
- Keep credentials out of files and reports.

## Output

Update:

- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/STATUS.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: Product Finance Architect
Task: Immutable group report archive package contract
Status: DONE / BLOCKED
Evidence pointer: `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
Blocker: ...
Next owner: Backend Implementation Queue / Project Director
