# Handoff: Field Combat Mode QA Plan

Date: 2026-05-26

From role: Project Director

To role: QA Release Engineer

Priority: P0 after Backend/Data and Frontend/UX evidence

## Context

Product Finance Architect accepted Field Combat Mode as MVP foundation.

Business MVP remains blocked until no-data-loss behavior is proven.

## Read First

- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/REPORTING_RULES.md`

## Task

Prepare and later execute a Field Combat Mode QA plan.

Minimum checks:

1. Write row -> refresh -> row remains.
2. Write row -> switch module -> return -> row remains.
3. Write row -> lock/return simulation -> row remains if testable.
4. Add photo/proof -> interrupt upload -> pending/failed/retry state visible.
5. Bad network/offline-like save -> pending or failed state visible.
6. Retry upload/save -> data becomes saved without duplicate money rows.
7. Recovered session totals match saved facts.
8. Session does not submit, close, or finalize without deliberate action.

## Output

Update:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## Acceptance Criteria

- Any lost money fact is P0.
- Any lost proof without visible failure is P0.
- Any silent finalization is P0.
- Evidence identifies viewport/device conditions.
- Short report only is sent to Project Director chat.
