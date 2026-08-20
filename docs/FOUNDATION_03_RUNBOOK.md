# Foundation-03 Runbook

Date: 2026-08-20

## Sprint Name

Foundation-03: Seed Scenario And RLS Smoke

## Objective

Prove that the first Supabase/Postgres foundation protects workspace data at the database boundary before any application scaffold depends on it.

## Implemented

- Added rollback-safe RLS smoke scenario in `supabase/tests/foundation_rls_smoke.sql`.
- Added Node runner `scripts/foundation_rls_smoke.cjs`.
- Added npm command `smoke:foundation:rls`.
- Added migration `20260820143400_rls_authenticated_policy_scope.sql`.
- Restricted implicit public RLS policies to explicit `authenticated` scope.
- Granted authenticated users execute access to private RLS helper functions while keeping helpers outside exposed API schemas.
- Strengthened `check:foundation:sql` to detect duplicate table columns.

## Smoke Scenario

The smoke test creates temporary rows inside one transaction and rolls them back:

- owner user
- employee user
- outsider user
- organization
- yacht workspace
- active owner membership
- active employee own-report membership
- cash account
- operational transaction and ledger entry
- employee cash advance
- employee expense report and item
- employee quick note

## Verified Access Rules

- Owner can see workspace operational transactions and expense reports.
- Employee cannot see the full operational ledger.
- Employee can see own cash advance.
- Employee can see own expense report and expense item.
- Employee can see own quick note.
- Employee cannot insert an operational transaction directly.
- Outsider cannot see workspace, transactions, cash advances, or quick notes.
- Smoke fixture is fully rolled back; no test rows remain.

## Commands

Run:

```bash
npm run smoke:foundation:rls
```

Expected output:

```text
foundation rls smoke ok
```

Additional gates:

```bash
npm run check:foundation:sql
npx supabase db push --db-url "$SUPABASE_DB_POOLER_URL" --skip-vault --dry-run
```

## Acceptance Gate

Foundation-03 is acceptable only when:

- RLS smoke passes against the connected dev/staging Supabase project.
- The smoke scenario leaves no fixture rows behind.
- Supabase security advisor returns no lints.
- Remote migration dry-run reports no pending migrations.
- GitHub branch contains the smoke test, runner, migration, and documentation.

Current residual advisory note:

- Supabase performance advisor still reports `unused_index` INFO items because the dev/staging schema is empty after rollback. This is expected until real seed/scenario traffic uses the indexes.

## Next Sprint Candidate

Foundation-04: Application Scaffold And Supabase Client Boundary

Expected outputs:

- clean `apps/web` scaffold
- environment variable mapping
- server/client Supabase boundary
- first login shell
- first workspace selection shell
- no legacy PHP/V2 UI code in the new app path
