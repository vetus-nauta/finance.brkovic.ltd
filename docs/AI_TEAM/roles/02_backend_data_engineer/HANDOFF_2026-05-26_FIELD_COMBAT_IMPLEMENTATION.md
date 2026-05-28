# Handoff: Field Combat Mode Durable Draft Implementation

Date: 2026-05-26

From role: Project Director

To role: Backend Implementation Queue / Backend Data Engineer

Priority: P0

## Context

Backend/Data traced Field Combat Mode and blocked the business MVP no-data-loss claim.

Evidence:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- section: `Field Combat Mode Backend Persistence Trace 2026-05-26`
- fixture: `group_id=201`, `cash_tape_id=200`, `cash_capture_id=158`, `card_tape_id=201`

Current pass:

- saved rows/cards/sessions recover after successful save;
- successful proof upload persists;
- totals recalculate from saved DB state.

P0 blockers:

- typed money facts before successful save are client-only and losable;
- proof upload pending/failed/retry state is not durable;
- no durable operation id/retry identity for field sync;
- backend cannot distinguish pending retry, failed proof, and lost-before-server.

## Read First

- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`
- `app/on_the_go.php`
- `public/api.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Task

Implement or prepare the backend patch for durable Field Combat draft/sync.

Required backend capabilities:

1. Create or reuse an open field session identity before final submit/close.
2. Persist raw draft note text, parsed rows, selected group, participant/user, stream, and tape/session identity before final save.
3. Add client-generated operation ids for row sync so retry is idempotent beyond the current 4-second duplicate guard.
4. Add durable proof upload state before file transfer completes:
   - pending;
   - uploaded;
   - failed;
   - retry-needed;
   - last error;
   - retry count;
   - client upload id.
5. Expose recovery endpoints returning:
   - saved draft text;
   - parsed rows;
   - proof states;
   - recalculated totals;
   - open session/tape identity.
6. Keep submit/include/finalization as deliberate separate actions.

## Constraints

- Do not change financial formulas without Product Finance Architect and Chief Auditor.
- Do not merge Field Combat autosave with final report acceptance.
- Do not make proof failure look like successful save.
- Do not include `Advanced` non-MVP features in the critical path.

## Output

Update:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

If code changes are made, record exact changed files and verification evidence.

## Acceptance Criteria

- Refresh before final submit does not lose typed money facts after first autosave.
- Failed proof upload remains visible after refresh and can be retried.
- Backend can distinguish saved, pending, failed, retry-needed, submitted, and closed states.
- Recovered totals match saved facts.
- QA receives exact reproducible checks.
- Short report only is sent to Project Director chat.
