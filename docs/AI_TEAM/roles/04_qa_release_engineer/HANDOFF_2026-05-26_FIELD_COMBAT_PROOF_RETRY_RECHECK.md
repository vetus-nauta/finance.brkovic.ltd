# Handoff: Field Combat Proof Retry Recheck

Date: 2026-05-26

From: Project Director

To: QA Release Engineer

Priority: P0

## Read First

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`

## Context

Frontend/UX fixed the proof retry duplicate-money blocker from QA run `20260526109674`.

Root cause reported by Frontend/UX:

- proof retry with a selected file reused full `saveSimpleOnTheGo({stayInEditor: true})`;
- after first money save, UI could promote `next_tape_id` into active draft context;
- retry could call signed money sync again and create a second cash row/card.

Fix summary:

- proof retry context is pinned to original `client_draft_id`, `draft_id`, `tape_id`, `session_id`, `client_operation_id`, and saved `capture_id`;
- failed upload keeps retry context attached to the original saved capture;
- after recovery, `failed` / `retry_needed` proof state restores the original retry context;
- proof retry uses proof-state/upload/list endpoints and must not call `on_the_go_signed_sync`;
- backend/API and financial formulas were not changed.

Director verification:

- `node --check public/assets/app.js` passed;
- `git diff --check` passed for Frontend/UX changed files.

## Task

Rerun forced proof upload failure + refresh + proof retry on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.

## Required Checks

- Confirm old draft recovery still passes.
- Type the cash row and wait for `Сохранено`.
- Select proof and force first upload failure.
- Confirm failed proof remains visible as retry-needed after refresh/return.
- Reselect the proof file and retry.
- Confirm retry attaches/resolves proof for the original saved row/card/tape.
- Confirm the same `-25 Durable autosave row ...` appears exactly once across visible draft cards after retry.
- Confirm no second cash row/card is created in previous `next_tape_id`.
- Confirm original `client_operation_id` remains idempotent.
- Confirm no `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` request occurs.
- Recheck cash/card separation if this flow touches stream context.

## Acceptance Decision

PASS only if the old recovery behavior still passes and proof retry does not duplicate the money row on all three viewports.

BLOCKED if proof retry creates a second cash row/card, loses failed proof state, or silently submits/includes/finalizes money.

## Report Back

Use one short report only:

Role: QA/Release
Task: Field Combat proof retry duplicate-money recheck
Status: PASS / BLOCKED
Evidence pointer: `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
Blocker: ...
Next owner: Project Director / Frontend UX Engineer / Backend Data Engineer
