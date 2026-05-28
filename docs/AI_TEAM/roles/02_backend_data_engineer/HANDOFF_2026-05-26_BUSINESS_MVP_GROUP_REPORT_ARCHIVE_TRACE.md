# Handoff: Business MVP Group Report / Archive / Common Pot Backend Trace

Date: 2026-05-26

From: Project Director

To: Backend Data Engineer

Priority: P0

## Read First

- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/04_TASK_BOARD.md`
- `docs/AI_TEAM/05_DECISIONS.md`
- `docs/AI_TEAM/roles/05_chief_auditor/FIELD_COMBAT_NO_DATA_LOSS_GATE_2026-05-26.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

Chief Auditor approved the Field Combat no-data-loss foundation gate. This closes the active Field Combat P0, but not the full business MVP.

The next business-MVP block is the group money loop:

```text
participant reports -> accepted reports -> consolidated group report -> saved/printed/exported group report -> archive
```

The group report must preserve:

- common group pot;
- physical cash/card separation;
- participant responsibility;
- accountable money;
- proof/evidence for each number;
- immutable closed reports after new current activity starts.

## Task

Trace current backend/API/data coverage for business-MVP group report consolidation, archive/export, participant/common pot, and group scope.

Do not implement a patch in this pass. First answer whether the current backend can support the flow, where it can, where it cannot, and what exact P0 implementation task is needed next.

## Required Trace

Map existing code and API behavior for:

- participant/user reports inside a group;
- accepted/included Live Reports;
- finalized participant reports;
- consolidated group report source;
- group report save/finalize identity;
- group report print/export endpoints;
- archive listing/opening for participant reports and group reports;
- common group pot calculation;
- physical cash vs card/noncash separation;
- accountable money and returned balance;
- group messages scope if messages are tied to report review/return;
- default personal/group scope in report/export/archive endpoints;
- permission/role boundary for who submits, reviews, accepts, finalizes, exports, and archives.

## Acceptance Questions

Answer directly in `FINDINGS.md`:

- Can several participant reports currently be consolidated into one group report?
- Can that group report be saved as immutable historical truth?
- Can that group report be printed/exported?
- Can archive reopen the closed group report and all linked participant reports/proofs?
- Does the common pot preserve who holds/spent money?
- Does card spending stay out of physical cash?
- Are group messages available in the group/report context, or only as a separate module?
- What is missing for business MVP, ranked P0/P1/P2?

## Boundaries

- Do not change financial formulas.
- Do not change backend/API in this trace.
- Do not change frontend/UX.
- Do not use production destructive actions.
- If database inspection is needed, document exact read-only queries or API calls used.
- Keep credentials out of files and reports.

## Output

Update:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: Backend/Data
Task: Business MVP group report/archive/common pot backend trace
Status: DONE / BLOCKED
Evidence pointer: `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
Blocker: ...
Next owner: Project Director / Product Finance Architect / Frontend UX Engineer / QA Release Engineer
