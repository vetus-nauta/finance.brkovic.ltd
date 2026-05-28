# Handoff: Field Combat Draft Recovery Identity Recheck

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

Frontend/UX fixed the Field Combat draft recovery identity regression found in QA run `20260526264416`.

Frontend root cause:

- the stream gate path reset the Field Combat identity before backend recovery;
- this replaced the durable `client_draft_id` with a new empty draft for the same stream.

Frontend fix summary:

- editor open tries stored draft recovery before showing stream gate;
- stream choice calls backend recovery for the stored `client_draft_id` before creating a new identity;
- same-stream recovery must not call `resetSimpleDraftIdentity(0)` while a recoverable draft exists;
- autosave/proof/idempotent save behavior should remain unchanged.

Director verification:

- `node --check public/assets/app.js` passed;
- `git diff --check` passed for Frontend/UX changed files.

## Task

Rerun the blocked browser/HTTP no-data-loss scenario from run `20260526264416`.

## Required Checks

- Test mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.
- Type `-25 Durable autosave row ...`.
- Wait for visible `Сохранено`.
- Refresh the browser and confirm the typed row returns in the editor.
- Switch to another module, return to `На бегу`, and confirm the same row/session returns.
- Confirm localStorage keeps or resolves to the durable active `client_draft_id` instead of a new empty draft.
- Confirm reselecting the same cash/card stream does not replace a recoverable active draft.
- Continue proof failure/retry check after recovery passes.
- Continue idempotent save retry check.
- Continue cash/card separation check.
- Confirm autosave does not submit, include, finalize, or silently move data into a final report.

## Acceptance Decision

PASS only if the old blocker is gone across all three viewports and the remaining proof/retry/idempotency/cash-card checks do not reveal a new P0.

BLOCKED if refresh/return still opens an empty editor, draft identity is replaced again, or autosave moves money into a final report without deliberate action.

## Report Back

Use one short report only:

Role: QA/Release
Task: Field Combat draft recovery identity recheck
Status: PASS / BLOCKED
Evidence pointer: `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
Blocker: ...
Next owner: Project Director / Frontend UX Engineer / Backend Data Engineer
