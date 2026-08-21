# SPRINT-119R — Foundation Report Return For Revision

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

The MVP workflow needs a clear way to return a report for revision without
making the report disappear and without unlocking report rows through ordinary
editing.

Goal:

- keep returned reports visible;
- change report status only through an audited command;
- keep source rows report-locked;
- update period closure status consistently;
- expose the action in the report detail UI.

## Implemented

Added:

- `supabase/migrations/20260821194500_report_revision_command.sql`;
- `supabase/tests/foundation_report_revision_command_smoke.sql`;
- `scripts/foundation_report_revision_command_smoke.cjs`.

Updated:

- `package.json`;
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

The new `return_report_snapshot_for_revision` command:

- requires authenticated user context;
- requires `reports.manage`;
- updates `report_snapshots.status` to `returned_for_revision`;
- updates linked `period_closures.status` to `returned_for_revision`;
- stores return metadata;
- writes `approval_events.report_snapshot_returned_for_revision`;
- does not unlock included source transactions for ordinary update.

The report detail UI now has a compact return-for-revision form.

## Verification

Passed:

```bash
npm run smoke:foundation:report-revision
npm run typecheck:web
npm run build:web
git diff --check
```

The migration was applied to the connected Supabase foundation database.

## Acceptance

SPRINT-119R is accepted.

Returned reports remain visible and auditable; source rows stay protected.

## Deferred

Next:

- correction command for report-locked source rows;
- package send/accept lifecycle;
- print/PDF export actions.
