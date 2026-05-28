# Handoff: Field Combat Draft Recovery Identity Fix

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

QA run: `20260526264416`

Viewports: `390x844`, `820x1180`, `1440x900`

Groups: `204`, `205`, `206`

Observed failure:

- user types `-25 Durable autosave row ...`;
- UI shows `Сохранено`;
- refresh/return opens an empty editor;
- backend still returns the old draft by the original `client_draft_id`;
- UI localStorage is replaced with a new empty draft.

Draft identity evidence:

- mobile: original draft `8`, empty draft `14`;
- tablet: original draft `16`, empty draft `20`;
- desktop: original draft `22`, empty draft `27`.

## Task

Fix Field Combat client draft recovery identity.

The UI must restore the saved active durable draft after refresh, module switch, and return to the same cash/card stream. It must not replace the existing `client_draft_id` with a new empty draft while a recoverable active draft exists.

## Required Behavior

- Keep the durable active `client_draft_id` in localStorage for the same user, group, report, and stream.
- On editor open or stream return, call recovery for the stored `client_draft_id` before creating a new draft identity.
- Do not call `resetSimpleDraftIdentity` for the same stream while the stored draft is recoverable.
- Do not autosave an empty replacement over a previously typed active draft.
- Create a fresh draft identity only after the previous draft is deliberately submitted/closed or backend recovery proves it cannot be used.
- Preserve existing autosave, proof-state, upload-state, and `client_operation_id` behavior.
- Autosave must never submit, include, finalize, or silently move data into a final report.

## Boundaries

- Do not change financial formulas.
- Do not change backend/API unless Backend/Data owns a separate task.
- Do not change broader UX flow outside this Field Combat recovery bug.
- Keep full evidence in the Frontend/UX role folder.
- Chief chat receives only the short report template.

## Acceptance Criteria

- After `Сохранено`, browser refresh restores the typed row on mobile `390x844`.
- After `Сохранено`, module switch/return restores the typed row on tablet `820x1180`.
- After `Сохранено`, refresh/return restores the typed row on desktop `1440x900`.
- Re-entering the same cash/card stream preserves the stored durable draft identity or resolves it to the same active backend draft.
- No empty draft replaces a recoverable active draft.
- QA can continue proof failure/retry, idempotent save retry, and cash/card separation checks.

## Report Back

Use one short report only:

Role: Frontend/UX
Task: Field Combat draft recovery identity fix
Status: fixed / blocked
Files changed: ...
Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
Blocker: ...
Next owner: QA Release Engineer
