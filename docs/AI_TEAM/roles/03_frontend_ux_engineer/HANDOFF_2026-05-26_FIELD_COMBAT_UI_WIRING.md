# Handoff: Field Combat UI Autosave And Proof State Wiring

Date: 2026-05-26

From role: Project Director

To role: Frontend UX Engineer

Priority: P0

## Context

Backend/Data implemented durable Field Combat draft/sync/proof-state support.

Business MVP release gate still waits for UI wiring and QA evidence.

Backend evidence:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- section: `Durable Field Combat Draft/Proof Implementation 2026-05-26`
- fixture: `group_id=202`, `draft_id=1`, `tape_id=202`, `session_id=142`, `capture_id=160`

## Read First

- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/11_MOBILE_MULTITASKING_RESEARCH.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/REPORTING_RULES.md`

## Backend Contract To Use

Actions:

- `on_the_go_field_draft_save`
- `on_the_go_field_recover`
- `on_the_go_proof_state_begin`
- `on_the_go_proof_state_fail`
- `on_the_go_proof_state_list`
- `on_the_go_signed_sync` with `client_operation_id`
- `on_the_go_upload_file` with `client_upload_id`, `client_draft_id`, and `draft_id`

## Task

Wire Field Combat capture UI to durable backend autosave and proof-state endpoints.

Required behavior:

1. Create and reuse a stable `client_draft_id` for the open field session.
2. Autosave typed rows, selected stream, group, cash base, and session identity through `on_the_go_field_draft_save`.
3. Recover open draft via `on_the_go_field_recover` on refresh/module switch/return.
4. Show saved/pending/failed/retry state in ordinary language.
5. Before proof upload, call `on_the_go_proof_state_begin` with `client_upload_id`.
6. If upload fails or is interrupted in a detectable way, call `on_the_go_proof_state_fail`.
7. Never show proof as fully saved unless backend state is `uploaded`.
8. Send stable `client_operation_id` to `on_the_go_signed_sync` so retry is idempotent.
9. Keep submit/include/finalize as deliberate actions separate from autosave.

## Constraints

- Do not change backend/API contract unless Backend/Data owns it.
- Do not change financial formulas.
- Do not move required Field Combat behavior into `Advanced`.
- Do not clear selected proof or typed row on failed upload/save.

## Output

Update:

- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

If code changes are made, record exact changed files and verification evidence.

## Acceptance Criteria

- Type row -> autosave -> refresh/module switch/return -> row restores.
- Proof upload failure remains visible after refresh as `failed` or `retry_needed`.
- UI distinguishes saved, pending, failed, retry, submitted, and closed.
- Autosave does not submit/include/finalize.
- QA receives exact verification instructions.
- Short report only is sent to Project Director chat.
