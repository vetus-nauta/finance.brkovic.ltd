# SPRINT-121R — Foundation Report Locked Correction

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

Returned reports need a safe correction mechanism. A report-locked source row
must not be edited directly because that breaks the audit trail and the original
report snapshot. The correction must become a new operational row linked to the
original.

Goal:

- keep report-locked rows immutable for ordinary update;
- create corrections only through a command;
- link correction row to original row;
- audit the correction;
- expose the workflow in the report UI when a report is returned for revision.

## Implemented

Added:

- `supabase/migrations/20260821200000_report_locked_correction_command.sql`;
- `supabase/tests/foundation_report_locked_correction_command_smoke.sql`;
- `scripts/foundation_report_locked_correction_command_smoke.cjs`.

Updated:

- `package.json`;
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

The new `create_report_locked_correction` command:

- requires authenticated user context;
- requires `ledger.correct` and `ledger.write`;
- accepts only `included_in_report` or `closed` original rows;
- creates a new operational transaction with `source_type = correction`;
- links original and correction in `corrections`;
- writes `approval_events.report_locked_correction_created`;
- leaves the original row unchanged.

The report detail UI now shows a correction form only when the selected report
has status `returned_for_revision`.

## Verification

Passed:

```bash
npm run smoke:foundation:report-correction
npm run typecheck:web
npm run build:web
git diff --check
```

The migration was applied to the connected Supabase foundation database.

## Acceptance

SPRINT-121R is accepted.

Closed report rows now have a proper correction path instead of direct mutation.

## Deferred

Next:

- report/package send and accept lifecycle;
- PDF file generation;
- correction visibility inside later report snapshots.
