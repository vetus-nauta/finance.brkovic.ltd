# Handoff: Field Combat No-Data-Loss Gate

Date: 2026-05-26

From: Project Director

To: Chief Auditor

Priority: P0

## Read First

- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

Field Combat Mode is the business-MVP foundation: write, photo/scan proof, automatic save, recovery after refresh/return, and no data loss before deliberate submit/close.

Backend/Data, Frontend/UX, and QA evidence now exists for this slice.

## Evidence To Review

Product:

- Product Finance Architect accepted `Field Combat Mode = MVP foundation`.
- `Advanced` remains non-MVP staging, not deletion.

Backend/Data:

- durable draft/sync/proof-state backend patch implemented;
- APIs include `on_the_go_field_draft_save`, `on_the_go_field_recover`, `on_the_go_proof_state_begin`, `on_the_go_proof_state_fail`, `on_the_go_proof_state_list`;
- backend evidence fixture: `group_id=202`, `draft_id=1`, `tape_id=202`, `session_id=142`, `capture_id=160`.

Frontend/UX:

- wired autosave/recovery/proof-state behavior in the active `Живой отчет` editor;
- fixed empty-draft recovery identity blocker;
- fixed proof retry duplicate-money blocker;
- no backend/API or financial formulas changed in the last two frontend fixes.

QA:

- old empty-draft recovery blocker found in run `20260526264416`;
- recovery identity recheck passed in run `20260526109674`;
- proof retry duplicate-money blocker found in run `20260526109674`;
- final proof retry recheck passed in run `20260526929348`;
- groups `218/219/220`;
- original rows `176/178/180` stayed single and received proof files;
- previous `next_tape_id` cards `252/258/264` stayed clean;
- no `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` request was observed.

## Task

Decide the Field Combat no-data-loss gate: approved, blocked, or waiting for more evidence.

Review whether the verified Field Combat behavior preserves:

- typed money facts after refresh/return;
- proof failed/retry state after refresh/return;
- no duplicate money rows on proof retry;
- idempotent save retry;
- cash/card separation;
- deliberate submit/include/finalize boundaries;
- visible ordinary-language save/retry state for a non-accountant in movement.

## Boundaries

- Do not declare full business MVP ready from this gate.
- This gate covers Field Combat no-data-loss foundation only.
- Group report consolidation, archive, participant/common pot, and messages remain separate business-MVP work.
- If blocked, assign the exact role owner.

## Output

Update:

- `docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md`
- `docs/AI_TEAM/roles/05_chief_auditor/RISKS.md`
- `docs/AI_TEAM/roles/05_chief_auditor/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: Chief Auditor
Task: Field Combat no-data-loss gate
Status: approved / blocked / waiting
Evidence pointer: `docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md`
Blocker: ...
Next owner: Project Director
