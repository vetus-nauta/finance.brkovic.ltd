# Field Combat No-Data-Loss Gate

Date: 2026-05-26
Role: Chief Auditor
Decision: approved for Field Combat no-data-loss foundation only.

## Scope

This gate covers the active `Живой отчет` Field Combat foundation:

- durable draft save and recovery after refresh/return;
- proof failed/retry state after refresh/return;
- no duplicate money rows on proof retry;
- idempotent save retry;
- cash/card separation;
- deliberate submit/include/finalize boundaries;
- visible ordinary-language save/retry state for a non-accountant in movement.

This gate does not approve full business MVP. Group report consolidation, archive, participant/common pot, messages, production deployment, and broader business MVP scope remain separate gates.

## Evidence Reviewed

Product:

- Product Finance Architect accepted Field Combat Mode as MVP foundation.
- `Advanced` remains non-MVP staging, not deletion.

Backend/Data:

- Durable draft/proof backend patch implemented.
- APIs include `on_the_go_field_draft_save`, `on_the_go_field_recover`, `on_the_go_proof_state_begin`, `on_the_go_proof_state_fail`, and `on_the_go_proof_state_list`.
- Backend fixture: `group_id=202`, `draft_id=1`, `tape_id=202`, `session_id=142`, `capture_id=160`.
- Backend verified draft recovery, proof state persistence, idempotent `client_operation_id`, uploaded proof state, and draft card state.

Frontend/UX:

- Autosave/recovery/proof-state behavior wired in the active `Живой отчет` editor.
- Empty-draft recovery identity blocker from QA run `20260526264416` fixed.
- Proof retry duplicate-money blocker from QA run `20260526109674` fixed.
- Last two frontend fixes did not change backend/API behavior or financial formulas.

QA:

- Old empty-draft recovery blocker found in run `20260526264416`.
- Recovery identity recheck passed in run `20260526109674`.
- Proof retry duplicate-money blocker found in run `20260526109674`.
- Final proof retry recheck passed in run `20260526929348`.
- Final QA groups: `218/219/220`.
- Original rows `176/178/180` stayed exactly once and received proof files.
- Previous `next_tape_id` cards `252/258/264` stayed clean.
- Repeated `client_operation_id` remained idempotent.
- No `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` request was observed.
- Card stream sanity check passed with `noncash_out`, `reportable=0`, and zero physical-cash effect.

## Audit Finding

The verified Field Combat behavior preserves the money fact after the app has reached visible saved/retry state. The final QA recheck proves that refresh/return and failed proof retry no longer lose the active money row or duplicate it into the next tape.

The proof retry now attaches proof to the original saved capture. The original cash rows remained single and received proof files. Previous `next_tape_id` cards stayed clean. Autosave and proof retry did not submit, include, or finalize a report.

## Remaining Risk

Purely local unsent typing before the first successful autosave request remains outside backend recovery. This is acceptable for this gate because Frontend/UX now triggers early autosave and shows visible sync state, and QA evidence starts after visible `Сохранено` / retry-needed states.

This remaining point stays as a regression/watch item, not a P0 blocker for this Field Combat no-data-loss gate.

## Assigned Follow-Up

- Project Director: route next business MVP work; Field Combat no-data-loss foundation is no longer the active P0 blocker.
- Product/Backend/Frontend/QA: keep group report consolidation, archive, participant/common pot, messages, production deployment, and broader business MVP proof as separate gates.
- Frontend UX Engineer + QA Release Engineer: keep early-autosave/visible-sync behavior under regression watch.
