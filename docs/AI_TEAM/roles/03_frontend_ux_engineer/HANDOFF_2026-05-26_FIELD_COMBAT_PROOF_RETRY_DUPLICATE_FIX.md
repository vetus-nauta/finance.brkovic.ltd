# Handoff: Field Combat Proof Retry Duplicate-Money Fix

Date: 2026-05-26

From: Project Director

To: Frontend UX Engineer

Priority: P0

## Read First

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`

## QA Evidence

QA run: `20260526109674`

Groups: `210`, `211`, `212`

Viewports: `390x844`, `820x1180`, `1440x900`

What passed:

- old empty-draft recovery blocker is fixed;
- typed cash row returns after refresh/module return/same-stream reselection;
- original `client_operation_id` retry returns `idempotent=true`;
- no submit/include/finalize request was observed;
- card/cash separation sub-check passed.

What is blocked:

- after forced proof upload failure, refresh, and proof retry, the same cash row is duplicated into a second cash draft card;
- localStorage keeps the same `client_draft_id`, but `tape_id` resolves to the previous `next_tape_id`;
- retry saves the same money fact into the new tape instead of attaching proof to the original row/card.

Evidence:

- mobile group `210`: original tape `227` row `167`; retry tape `226` row `168`;
- tablet group `211`: original tape `232` row `170`; retry tape `231` row `171`;
- desktop group `212`: original tape `237` row `173`; retry tape `236` row `174`.

## Task

Fix Field Combat proof retry so a failed proof upload does not duplicate the money row.

The UI must keep the retry context attached to the original saved row/card after upload failure. Proof retry must attach proof to the original pending proof/capture/card, or otherwise resolve the original pending proof, without saving the same cash fact into `next_tape_id`.

## Required Behavior

- After first successful money save, keep proof retry context pinned to the original `tape_id`, `session_id`, `client_draft_id`, `client_operation_id`, and saved row/capture context.
- Do not promote `next_tape_id` to the active retry context while an unresolved proof state exists for the original row/card.
- Do not call money-row save again into `next_tape_id` during proof retry.
- If retry needs to replay signed sync, it must reuse the original idempotent operation and original saved context, not create a second cash row/card.
- A new `next_tape_id` may become active only after the current money fact is fully resolved or the user deliberately starts a new entry.
- Preserve the fixed refresh/return draft recovery behavior.
- Preserve card/cash separation.
- Autosave must never submit/include/finalize.

## Boundaries

- Do not change financial formulas.
- Do not change backend/API unless Backend/Data owns a separate task.
- Do not change broader UX flow outside this Field Combat proof retry bug.
- Keep full findings in the Frontend/UX role folder.
- Chief chat receives only the short report template.

## Acceptance Criteria

- Forced proof failure keeps visible retry-needed proof state after refresh/return.
- Proof retry attaches or resolves proof for the original saved row/card.
- The same `-25 Durable autosave row ...` appears exactly once across visible draft cards after retry.
- Original `client_operation_id` remains idempotent.
- No second cash row/card is created in `next_tape_id`.
- No submit/include/finalize action occurs.
- QA can rerun on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.

## Report Back

Use one short report only:

Role: Frontend/UX
Task: Field Combat proof retry duplicate-money fix
Status: fixed / blocked
Files changed: ...
Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
Blocker: ...
Next owner: QA Release Engineer
