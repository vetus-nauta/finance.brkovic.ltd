# SPRINT-112R — Foundation Approval Audit Command Boundary

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

SPRINT-111R closed direct financial fact-table mutations for `transactions` and
`ledger_entries`. The remaining adjacent risk was `approval_events`: it stores
the proof trail for operational entries, quick-note conversion, and future
approval/report actions.

The sprint goal is to make `approval_events` command-owned:

- users may read audit events through RLS;
- users must not insert, update, or delete audit events directly;
- approved RPC commands must keep creating audit events;
- quick-note and Smith conversion flows must survive the boundary change.

## Agents

### Financial Logic Engine Agent — Rawls

Verdict before fix: Reject.

Findings:

- `approval_events_write` allowed broad direct insert/update/delete for
  `accountable.approve` and `reports.manage`.
- `approval_events_operational_entry_write` allowed direct spoofing of
  transaction audit events by `ledger.write`.
- `approval_events_quick_note_conversion_write` allowed direct spoofing of
  quick-note conversion events by `ledger.write`.
- `convert_quick_note_to_operational_entries` and
  `convert_smith_entry_proposals` had to keep audit inserts working after
  direct policies were removed.

### QA Audit Agent — Anscombe

Verdict before fix: Reject.

Required acceptance:

- `approval_events` exposes no mutable policies.
- `anon` and `authenticated` have no direct mutable privileges on
  `approval_events`.
- direct spoof insert/update/delete is rejected.
- operational create/update/void RPC commands still emit audit events.
- quick-note conversion still emits its audit event through the RPC path.

## Implemented

Added migration:

- `supabase/migrations/20260821172000_approval_event_command_boundary.sql`

The migration:

- drops all mutable `approval_events` write policies;
- revokes `insert`, `update`, and `delete` on `approval_events` from client
  roles;
- keeps `approval_events_read`;
- makes `convert_quick_note_to_operational_entries` `SECURITY DEFINER`;
- makes `convert_smith_entry_proposals` `SECURITY DEFINER`;
- keeps authenticated RPC execute grants only.

Added smoke:

- `supabase/tests/foundation_approval_audit_boundary_smoke.sql`
- `scripts/foundation_approval_audit_boundary_smoke.cjs`
- `npm run smoke:foundation:approval-audit-boundary`

Strengthened existing operational smoke:

- direct `approval_events` insert spoof must fail;
- direct `approval_events` update spoof must fail;
- direct `approval_events` delete spoof must fail;
- quick-note conversion RPC must still create two operational entries and one
  `quick_note_converted` audit event.

## Live Database Evidence

After migration, the live Supabase catalog showed:

- `approval_events` policy list contains only `approval_events_read` / `SELECT`;
- all public functions that insert into `approval_events` are
  `SECURITY DEFINER`:
  - `create_operational_entry`;
  - `update_operational_entry`;
  - `void_operational_entry`;
  - `convert_quick_note_to_operational_entries`;
  - `convert_smith_entry_proposals`.

## Verification

Passed:

```bash
npm run smoke:foundation:approval-audit-boundary
npm run smoke:foundation:operational-entry
npm run smoke:foundation:financial-boundary
npm run smoke:foundation:rls
npm run check:foundation:sql
npm run typecheck:web
npm run build:web
git diff --check
```

## Acceptance

SPRINT-112R is accepted.

`approval_events` is no longer a client-writable audit table. It is a readable
audit surface fed by command RPCs.

## Deferred

Future report/package/period-close commands must follow the same pattern:

- no direct mutable policies on audit/fact tables;
- explicit auth and workspace permission checks inside RPC;
- `SECURITY DEFINER` only for reviewed command functions;
- smoke tests for direct write denial and RPC happy path.
