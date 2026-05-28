# Handoff: Field Combat UI No-Data-Loss QA

Date: 2026-05-26

From role: Project Director

To role: QA Release Engineer

Priority: P0

## Context

Backend/Data implemented durable Field Combat backend APIs.

Frontend/UX wired the active `Живой отчет` simple editor to autosave and proof-state endpoints.

Business MVP remains blocked until QA proves the browser/HTTP no-data-loss behavior.

## Read First

- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/REPORTING_RULES.md`

## Evidence To Reference

Backend fixture:

- `group_id=202`
- `draft_id=1`
- `tape_id=202`
- `session_id=142`
- `capture_id=160`

Frontend evidence:

- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- section: `2026-05-26 Field Combat UI autosave/proof-state wiring`

## Task

Run browser/HTTP QA for Field Combat UI no-data-loss.

Minimum checks:

1. Type `-25 Durable autosave row` in `Записи`.
2. Wait for `Сохранено`.
3. Refresh page, return to `На бегу`, and confirm the row restores.
4. Switch to another module, return to `На бегу`, and confirm the unfinished row/session restores.
5. Select a proof file and confirm pending proof state appears before final save/upload.
6. Force or simulate upload failure/interruption; refresh/return; confirm proof remains visible as `failed` or `retry_needed`, not as saved.
7. Retry proof upload; confirm UI marks proof saved only after backend state is `uploaded`.
8. Deliberate `Сохранить` sends `client_operation_id`; retry does not duplicate rows.
9. Confirm autosave does not submit/include/finalize.
10. Confirm card/cash separation remains intact for cash and card streams.

## Viewports

Run at minimum:

- mobile `390 x 844`;
- tablet `820 x 1180`;
- desktop `1440 x 900`.

## Output

Update:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## Acceptance Criteria

- Any lost typed fact after `Сохранено` is P0.
- Any proof upload failure without persistent `failed` or `retry_needed` state is P0.
- Any duplicate money row after retry is P0.
- Any autosave that submits/includes/finalizes silently is P0.
- Short report only is sent to Project Director chat.
