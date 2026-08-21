# SPRINT-113R — Foundation Report Snapshot Command

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

After SPRINT-111R and SPRINT-112R, financial facts and audit events are
command-owned. The next MVP transfer step is report creation: the user needs a
real report command, not a decorative reports screen.

Goal:

- create a saved report snapshot from operational entries for a selected period;
- close the included operational rows from ordinary edit/delete;
- keep report tables read-only for clients;
- keep generated report numbers derived from operational entries only.

## Implemented

Added migration:

- `supabase/migrations/20260821183000_report_snapshot_command.sql`

The migration:

- drops mutable direct policies on:
  - `period_closures`;
  - `report_snapshots`;
  - `report_packages`;
  - `report_package_items`;
- revokes direct `insert/update/delete` from client roles on those tables;
- adds `private.guard_report_locked_transactions`;
- blocks ordinary updates to `included_in_report` and `closed` transactions;
- adds `public.create_period_report_snapshot(...)` as a `SECURITY DEFINER` RPC.

The RPC:

- requires authenticated user;
- requires `reports.manage`;
- requires `period.close`;
- selects open/review operational transactions in the requested date range;
- computes totals from `ledger_entries`;
- stores source transaction ids in `report_snapshots.source_transaction_ids`;
- stores totals JSON with account/category breakdown;
- creates a `period_closures` row;
- marks included transactions as `included_in_report`;
- writes `approval_events.report_snapshot_created`.

## UI

Updated reports mode in:

- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`;
- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/globals.css`.

The reports screen now has:

- period start/end;
- optional title;
- `Создать отчет`;
- saved report cards with entry count, review count, income, expense, net total.

## Verification

Passed:

```bash
npm run smoke:foundation:report-snapshot
npm run smoke:foundation:operational-entry
npm run smoke:foundation:financial-boundary
npm run smoke:foundation:approval-audit-boundary
npm run smoke:foundation:rls
npm run check:foundation:sql
npm run typecheck:web
npm run build:web
git diff --check
```

Browser smoke:

- local app opened at `http://127.0.0.1:3000`;
- dev-login succeeded;
- workspace reports screen opened directly;
- report form rendered;
- screenshot saved:
  - `test-results/foundation-report-snapshot/reports-screen-direct.png`.

## Acceptance

SPRINT-113R is accepted as the first report creation slice for the foundation
MVP transfer.

## Deferred

Next slices:

- open saved report detail with expandable categories;
- generate exportable HTML/PDF/send package;
- report return-for-revision command;
- combine multiple snapshots into one package;
- correction flow for changing report-locked transactions.
