# SPRINT-111R — Foundation Financial Command Boundary

## Director Sprint Opening

Sprint:
SPRINT-111R — Foundation Financial Command Boundary

Goal:
Close direct client writes to foundation financial fact tables so operational money can be changed only through audited database commands.

This sprint does not add product UX. It hardens the database boundary behind the operational journal accepted in SPRINT-110R.

Required files read:
- `docs/MVP_PORT_IMPLEMENTATION_PLAN.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-101R-foundation-financial-command-evidence.md`
- `FinDesk v2.0/sprints/SPRINT-110R-foundation-operational-journal-mvp-parity.md`
- `supabase/migrations/20260820143100_foundation_core.sql`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `supabase/migrations/20260820161000_operational_entry_security_invoker.sql`
- `supabase/migrations/20260821142000_operational_entry_update_void_commands.sql`
- `supabase/tests/foundation_operational_entry_command_smoke.sql`

Model mode:
Use `GPT-5.5 high` or strongest high-reasoning mode. This sprint touches financial integrity and RLS.

Agents assigned:
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

Agent:
Financial Logic Engine Agent

Scope:
Financial invariant review for `transactions` and `ledger_entries`.

Files to read:
- `docs/MVP_PORT_IMPLEMENTATION_PLAN.md`
- `FinDesk v2.0/sprints/SPRINT-101R-foundation-financial-command-evidence.md`
- `FinDesk v2.0/sprints/SPRINT-110R-foundation-operational-journal-mvp-parity.md`
- `supabase/migrations/20260820143100_foundation_core.sql`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `supabase/migrations/20260821142000_operational_entry_update_void_commands.sql`

What to check:
- Whether `ledger.write` users can bypass RPC with direct table DML.
- What financial facts can be corrupted by direct insert/update/delete.
- Which operations must remain available.

What to change if allowed:
- No file changes; report only.

What not to touch:
- UI.
- reports.
- production data.

Report required:
Accept/reject, invariants, required blocks, required smoke tests.

Agent:
QA, Audit, and Acceptance Agent

Scope:
Acceptance evidence for DB command boundary.

Files to read:
- `package.json`
- `scripts/foundation_rls_smoke.cjs`
- `scripts/foundation_operational_entry_command_smoke.cjs`
- `supabase/tests/foundation_operational_entry_command_smoke.sql`
- `supabase/migrations`

What to check:
- Direct insert/update/delete denial on financial fact tables.
- RPC create/update/void still works.
- Catalog evidence shows no mutable policies on financial fact tables.

What to change if allowed:
- No file changes; report only.

What not to touch:
- secrets/env.
- product UI.
- production data.

Report required:
Acceptance checks, expected results, residual risks.

Expected reports:
- Financial Logic Engine report.
- QA/Audit report.

Exit criteria:
- `transactions` exposes no direct insert/update/delete policy to authenticated clients.
- `ledger_entries` exposes no direct insert/update/delete policy to authenticated clients.
- `create_operational_entry`, `update_operational_entry`, and `void_operational_entry` continue to work.
- Direct DML behavior smoke proves direct insert/update/delete cannot mutate financial fact tables.
- Catalog smoke proves only SELECT policies remain for financial fact tables.
- Existing RLS smoke still passes.
- Web build/typecheck still pass.

Risks:
- SECURITY DEFINER commands must keep explicit `auth.uid()` and permission checks.
- Audit spoofing through `approval_events` remains an adjacent hardening topic.
- Future report/correction commands must be added as explicit commands, not by reopening table writes.

## Director Final Handoff

Sprint:
SPRINT-111R — Foundation Financial Command Boundary

Status:
Accepted.

Agents assigned:
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

Agent reports received:
- Financial Logic Engine Agent: rejected the previous state because `transactions_write` and `ledger_entries_write` policies allowed direct `insert/update/delete` for `ledger.write` roles; required RPC-only writes.
- QA, Audit, and Acceptance Agent: rejected acceptance until direct DML behavior and catalog checks existed; required RPC happy path to continue working after hardening.

Accepted work:
- Added `20260821161000_financial_command_boundary.sql`.
- Dropped broad and split write policies for `transactions`.
- Dropped broad and split write policies for `ledger_entries`.
- Made operational command functions `SECURITY DEFINER` while preserving explicit auth/permission checks inside the functions.
- Kept public command surface through authenticated RPC execution grants.
- Extended operational command smoke to assert direct insert/update/delete cannot mutate `transactions` or `ledger_entries`.
- Added dedicated financial boundary catalog smoke.

Rejected or deferred work:
- `approval_events` audit-spoofing hardening is deferred.
- Report/correction command boundary is deferred until those commands are ported.
- Browser QA is not required for this DB-only sprint.

Files changed:
- `package.json`
- `scripts/foundation_financial_command_boundary_smoke.cjs`
- `supabase/migrations/20260821161000_financial_command_boundary.sql`
- `supabase/tests/foundation_financial_command_boundary_smoke.sql`
- `supabase/tests/foundation_operational_entry_command_smoke.sql`
- `FinDesk v2.0/sprints/SPRINT-111R-foundation-financial-command-boundary.md`

Tests or checks:
- `npm run check:foundation:sql` — passed
- `npm run smoke:foundation:rls` — passed
- `npm run smoke:foundation:operational-entry` — passed
- `npm run smoke:foundation:financial-boundary` — passed
- `npm run typecheck:web` — passed
- `npm run build:web` — passed
- `git diff --check` — passed

Catalog evidence:
After applying the migration, `pg_policies` for `public.transactions` and `public.ledger_entries` contains only:
- `transactions_read` / `SELECT`
- `ledger_entries_read` / `SELECT`

Risks:
- Future financial commands must not reintroduce table write policies.
- SECURITY DEFINER functions are acceptable here only because they explicitly check `auth.uid()` and workspace permissions before writes.
- Service-role/admin migration paths can still write directly; that is expected and not a client boundary.

Next sprint:
SPRINT-112R — Foundation Operational UX Manual Browser QA, or SPRINT-112R — Report/Archive Command Port, depending on whether product inspection or report parity is prioritized.

Paste-to-next-director prompt:
Continue from SPRINT-111R. The foundation financial fact tables `transactions` and `ledger_entries` no longer expose direct authenticated insert/update/delete policies. Operational create/update/void RPC commands were made SECURITY DEFINER with explicit auth and permission checks, and smoke tests prove RPC works while direct DML is blocked. Do not reopen direct table writes; add future report/correction/employee settlement writes as explicit audited commands.
