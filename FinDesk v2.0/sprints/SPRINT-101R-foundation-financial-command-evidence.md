# SPRINT-101R — Foundation Financial Command Evidence

## Director Sprint Opening

Sprint:
SPRINT-101R — Foundation Financial Command Evidence

Goal:
Prove that the new `brkovic.app` foundation writes operational entries through a safe financial command, preserving the accepted MVP rule that the operational journal is the source of truth.

This sprint does not invent new product behavior. It ports and verifies the existing MVP invariants on the Supabase/PostgreSQL foundation.

Required files read:
- `docs/SOURCE_OF_TRUTH.md`
- `docs/ARCHITECTURE.md`
- `docs/MIGRATION_MAP.md`
- `docs/MVP_PORT_IMPLEMENTATION_PLAN.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `supabase/migrations/20260820143100_foundation_core.sql`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`

Model mode:
Use `GPT-5.5 high` or strongest high-reasoning mode for this sprint because it touches financial integrity, RLS, audit, and future multi-user correctness.

Agents assigned:
- Financial Logic Engine Agent
- Data and Backend Core Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

Agent:
Financial Logic Engine Agent

Scope:
Operational-entry invariants.

Files to read:
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`

What to check:
- Counted rows always create a ledger entry.
- No-sign rows remain visible and non-counted.
- Cash/Card rules are preserved.
- Manual card income is blocked.
- Reports remain downstream of operational entries.

What to change if allowed:
- Only tests or narrow command defects.

What not to touch:
- UI redesign.
- report UX.
- old PHP runtime.

Report required:
Financial invariant acceptance or rejection, with exact failing scenario if rejected.

Agent:
Data and Backend Core Agent

Scope:
Supabase RPC, RLS, row numbering, audit evidence.

Files to read:
- `docs/RLS_MODEL.md`
- `docs/RLS_TEST_PLAN.md`
- `supabase/migrations/20260820143100_foundation_core.sql`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `scripts/foundation_rls_smoke.cjs`

What to check:
- RPC is executable only by authenticated users.
- Permission check uses workspace membership.
- Row numbering is protected from concurrent writes.
- Audit event is inserted.
- Web action does not bypass the RPC for counted ledger writes.

What to change if allowed:
- Add missing smoke coverage.
- Add a new migration only if a schema defect is proven.

What not to touch:
- Existing applied migration text unless this is local-only and not applied.
- Secrets and env files.
- Atlas or MySQL legacy scripts.

Report required:
Backend evidence list and remaining risks.

Agent:
QA, Audit, and Acceptance Agent

Scope:
Acceptance smoke and regression guard.

Files to read:
- `FinDesk v2.0/15-test-fixtures.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `scripts/foundation_rls_smoke.cjs`
- `package.json`
- `apps/web/package.json`

What to check:
- Build/typecheck.
- SQL lint/check.
- RPC smoke scenarios:
  - cash expense
  - cash income
  - card expense
  - manual card income rejected
  - no-sign row saved as review/non-counted
  - unauthenticated/unauthorized write rejected

What to change if allowed:
- Add or tighten smoke tests.

What not to touch:
- Product UI.
- migration data.
- production deploy state.

Report required:
Pass/fail table and commands used.

Expected reports:
- Financial invariant report.
- Backend/RLS evidence report.
- QA command evidence report.

Exit criteria:
- `createOperationalEntry` in Next calls `create_operational_entry` RPC.
- Counted entries create both `transactions` and `ledger_entries`.
- No-sign entries create visible transaction without counted ledger.
- Manual card income through manual input is rejected.
- Row numbers remain sequential under the command.
- An audit/approval event is created for each attempt that saves.
- Required smoke tests pass.

Risks:
- Supabase remote and local schema may differ if migrations were applied manually.
- Existing smoke may not cover all financial edge cases.
- This sprint is blocked if agent reports cannot be collected.

## Director Notes

SPRINT-100B completed the first mobile/tablet operational UX slice. It explicitly deferred financial command proof to this sprint.

Do not start report/archive/employee-money implementation until this command evidence is accepted.

## Director Final Handoff

Sprint:
SPRINT-101R — Foundation Financial Command Evidence

Status:
Accepted as bounded command-core evidence. Not accepted as full reporting/monthly-summary DoD.

Agents assigned:
- Financial Logic Engine Agent
- Data and Backend Core Agent
- QA, Audit, and Acceptance Agent

Agent reports received:
- Financial Logic Engine Agent: accepted the RPC insert-path for counted/non-counted rows and manual card-income guard; rejected broader report/balance/category DoD as out of this slice.
- Data and Backend Core Agent: accepted authenticated RPC, membership permission check, row lock, audit event, and web-action RPC usage; flagged direct table write policies for `ledger.write` roles as a hardening risk.
- QA, Audit, and Acceptance Agent: accepted typecheck/build/SQL/RLS smoke; rejected previous coverage as incomplete for operational-entry command scenarios.

Accepted work:
- Added an explicit operational-entry command smoke test.
- Proved cash expense creates transaction + ledger entry.
- Proved cash income creates transaction + ledger entry.
- Proved card expense creates transaction + ledger entry on card account.
- Proved manual card income through manual input is rejected.
- Proved no-sign row is saved as visible `needs_review` transaction and creates no ledger entry.
- Proved unauthorized employee role cannot use the RPC for operational ledger write.
- Proved audit/approval event is created for saved command rows.
- Confirmed web action calls `create_operational_entry` RPC rather than doing manual counted inserts.

Rejected or deferred work:
- Monthly summary generation is not accepted in this sprint.
- Live cash balance read model is not accepted in this sprint.
- Category materialization for manual rows is not accepted in this sprint.
- Direct table write lockdown for users with `ledger.write` is deferred to a dedicated hardening sprint because it changes database policy shape.
- Concurrent multi-client row numbering stress test is deferred; RPC uses `pg_advisory_xact_lock` and unique `(workspace_id, row_no)`, but stress evidence is still needed.

Files changed:
- `docs/MVP_PORT_IMPLEMENTATION_PLAN.md`
- `FinDesk v2.0/sprints/SPRINT-101R-foundation-financial-command-evidence.md`
- `package.json`
- `scripts/foundation_operational_entry_command_smoke.cjs`
- `supabase/tests/foundation_operational_entry_command_smoke.sql`

Tests or checks:
- `npm run typecheck:web` — passed
- `npm run build:web` — passed
- `npm run check:foundation:sql` — passed
- `npm run smoke:foundation:rls` — passed
- `npm run smoke:foundation:operational-entry` — passed
- `git diff --check` — passed

Risks:
- Direct `transactions` / `ledger_entries` writes remain possible for roles with `ledger.write` through direct Supabase table access. This does not affect the current web action, but it is not command-only at the DB boundary yet.
- Reports, balances, and category summaries still need their own parity gates.
- This sprint proves command behavior on current schema, not full Claudia Z migration arithmetic.

Next sprint:
SPRINT-102R — Operational Journal MVP Parity, preceded or paired with a narrow DB hardening decision on direct ledger table writes if command-only enforcement is required before wider UI work.

Paste-to-next-director prompt:
Continue from SPRINT-101R. The foundation operational-entry RPC has smoke evidence for cash expense, cash income, card expense, manual card-income rejection, no-sign review row, unauthorized rejection, and audit event creation. Do not treat this as full report/balance/category acceptance. The next product sprint must port the proven MVP operational journal behavior without inventing a new UX, while preserving the command path and deciding whether direct ledger table writes must be locked down before broader multi-user use.
