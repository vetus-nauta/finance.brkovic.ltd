# Handoff: Field Combat Mode Backend Persistence

Date: 2026-05-26

From role: Project Director

To role: Backend Data Engineer

Priority: P0

## Context

Product Finance Architect accepted:

- `Advanced` means non-MVP staging;
- Field Combat Mode is MVP foundation;
- business MVP cannot be complete until unfinished field-session no-data-loss behavior is proven.

## Read First

- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/REPORTING_RULES.md`

## Task

Map backend/API/storage support for Field Combat Mode.

Do not change financial formulas without Product Finance Architect and Chief Auditor.

Check:

1. Amount/note row persistence.
2. Photo/scan/proof file persistence.
3. Upload pending/failed/retry state.
4. Group and participant persistence.
5. Cash/card/accountable stream persistence.
6. Open card/session identity.
7. Recovery after refresh/module switch/return.
8. Recalculation from recovered state.
9. Deliberate submit/close boundary.
10. What is client-only and can be lost.

## Output

Update:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Acceptance Criteria

- Any possible money-fact loss before deliberate submit/close is marked P0.
- Any proof loss without visible failed/pending state is marked P0.
- QA receives exact reproducible checks.
- Frontend/UX receives exact persistence and sync-state constraints.
- Short report only is sent to Project Director chat.
