# SPRINT-110R — Foundation Operational Journal MVP Parity

## Director Sprint Opening

Sprint:
SPRINT-110R — Foundation Operational Journal MVP Parity

Goal:
Port the proven MVP operational journal behavior into the new `brkovic.app` Supabase/Next foundation without inventing a new product flow.

This sprint covers the narrow operational journal slice: visible journal/structure rows, active account switching, selected row continuity, and audited edit/delete commands. Reports, monthly closing, employee settlement, and full Claudia Z historical parity remain separate gates.

Required files read:
- `docs/MVP_PORT_IMPLEMENTATION_PLAN.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-101R-foundation-financial-command-evidence.md`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/app/workspaces/[workspaceId]/SyncedLedgerTable.tsx`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/lib/workspace-data.ts`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`

Model mode:
Use `GPT-5.5 high` or strongest high-reasoning mode. This sprint touches financial write paths and UX parity.

Agents assigned:
- iOS-Native UX Layout Agent
- Financial Logic Engine Agent as reviewer
- QA, Audit, and Acceptance Agent

Agent tasks:

Agent:
iOS-Native UX Layout Agent

Scope:
Operational journal row selection and responsive parity.

Files to read:
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `apps/web/src/app/workspaces/[workspaceId]/SyncedLedgerTable.tsx`
- `apps/web/src/app/globals.css`

What to check:
- Operational journal stays the main visible working field.
- Structure check mirrors the same row.
- Mobile remains journal-first.
- Row selection is explicit, not cursor-noisy.
- Edit mode is visible and can return to a new-entry state.

What to change if allowed:
- Narrow layout/state changes inside this component and global styles only.

What not to touch:
- Financial formulas.
- Report/archive behavior.
- Smith note conversion logic.

Report required:
UX acceptance or rejection with concrete missing controls.

Agent:
Financial Logic Engine Agent

Scope:
Edit/delete command invariants.

Files to read:
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `supabase/migrations/20260821142000_operational_entry_update_void_commands.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/lib/workspace-data.ts`

What to check:
- Edit does not create duplicate financial facts.
- Delete is a void command, not a silent hard-delete transaction.
- Voided entries disappear from live journal and summaries.
- Manual card income remains blocked.
- No-sign rows stay visible/non-counted after edit.

What to change if allowed:
- Only narrow command or read-model defects.

What not to touch:
- Parser category logic.
- Report snapshots.
- Legacy PHP runtime.

Report required:
Financial invariant acceptance or rejection.

Agent:
QA, Audit, and Acceptance Agent

Scope:
Build, RLS, command smoke, and audit evidence.

Files to read:
- `package.json`
- `apps/web/package.json`
- `scripts/foundation_rls_smoke.cjs`
- `scripts/foundation_operational_entry_command_smoke.cjs`
- `supabase/tests/foundation_operational_entry_command_smoke.sql`

What to check:
- Typecheck/build.
- SQL RLS scan.
- Create/update/void command smoke.
- Unauthorized write rejection.
- No whitespace or generated-file pollution.

What to change if allowed:
- Tighten smoke tests.

What not to touch:
- Product copy unless needed for acceptance.
- Env/secrets.
- Production data.

Report required:
Pass/fail commands with residual risk.

Expected reports:
- UX layout report.
- Financial command report.
- QA evidence report.

Exit criteria:
- User can click a row in journal or structure and edit the selected transaction.
- User can return from edit mode to a new-entry input.
- User can void/delete an operational entry through an audited command.
- Voided rows do not remain in active journal or category summary.
- Update preserves row number and updates exactly one live ledger entry for counted rows.
- No-sign rows can be updated and remain non-counted/review rows.
- Manual card income remains rejected.
- Required build/smoke checks pass.

Risks:
- Closed-period confirmation is not implemented in this foundation slice.
- Full row keyboard navigation is not implemented in this foundation slice.
- Report/archive UX parity is not accepted here.
- Direct table write lockdown remains a separate DB hardening decision.

## Director Final Handoff

Sprint:
SPRINT-110R — Foundation Operational Journal MVP Parity

Status:
Accepted as a bounded operational journal edit/delete slice. Not accepted as full operational MVP parity.

Agents assigned:
- iOS-Native UX Layout Agent
- Financial Logic Engine Agent as reviewer
- QA, Audit, and Acceptance Agent

Agent reports received:
- iOS-Native UX Layout Agent: accepted the side-by-side journal/structure model and mobile journal-first default; required explicit row selection and new-entry return before accepting edit UX.
- Financial Logic Engine Agent: accepted edit/delete only through audited RPC commands; rejected direct table update/delete and hard financial deletion.
- QA, Audit, and Acceptance Agent: accepted build/typecheck/RLS/command smoke after disk space was cleared; flagged closed-period guard and keyboard navigation as deferred.

Accepted work:
- Added `update_operational_entry` RPC.
- Added `void_operational_entry` RPC.
- Added audit events for `operational_entry_updated` and `operational_entry_voided`.
- Extended operational-entry smoke to cover update and void.
- Connected the workspace page to update/delete server actions.
- Made journal and structure rows clickable to the same selected transaction.
- Added selected-row visual state.
- Added edit mode, delete action, and return to new-entry mode.
- Excluded voided transactions from live journal counts, active entries, and category summary.

Rejected or deferred work:
- Closed-month edit confirmation.
- Full keyboard movement across journal/structure/buttons.
- Report snapshot creation and archive UX.
- Employee issued-cash settlement.
- Smith note conversion parity.
- Direct table write lockdown.

Files changed:
- `apps/web/src/app/globals.css`
- `apps/web/src/app/workspaces/[workspaceId]/SyncedLedgerTable.tsx`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/lib/workspace-data.ts`
- `supabase/migrations/20260821142000_operational_entry_update_void_commands.sql`
- `supabase/tests/foundation_operational_entry_command_smoke.sql`
- `FinDesk v2.0/sprints/SPRINT-110R-foundation-operational-journal-mvp-parity.md`

Tests or checks:
- `npm run typecheck:web` — passed
- `npm run build:web` — passed
- `npm run check:foundation:sql` — passed
- `npm run smoke:foundation:rls` — passed
- `npm run smoke:foundation:operational-entry` — passed
- `git diff --check` — passed

Operational note:
The first verification attempt hit `ENOSPC` because the local disk was full. Only generated/cache artifacts were cleared: Next build output, test results, temporary files, and npm cache/logs. Source, env files, and data were not removed.

Risks:
- This is still foundation parity, not the old MVP fully ported.
- The UI is functional but still needs manual browser QA on real mobile/tablet sizes.
- Closed report periods must not become freely editable without the dedicated confirmation flow.

Next sprint:
SPRINT-111R — Foundation Report/Archive UX Parity or DB Command Boundary Hardening, depending on whether product flow or security boundary is prioritized next.

Paste-to-next-director prompt:
Continue from SPRINT-110R. The foundation operational journal now supports audited create/update/void commands and selected-row edit UX. Build, SQL scan, RLS smoke, and operational-entry command smoke pass. Do not treat this as full MVP completion: report/archive parity, closed-period confirmation, keyboard navigation, employee settlement, Smith note parity, and command-only direct-table lockdown remain open gates.
