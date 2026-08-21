# SPRINT-122R — Foundation Report Send And Accept Lifecycle

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

Created reports and report packages need a controlled delivery lifecycle. The UI
must not silently imply that a report has been sent or accepted. The status
change must be a command, permission-gated, and auditable.

Goal:

- add command-owned `created/returned_for_revision -> sent -> accepted` flow for
  report snapshots;
- add command-owned `created -> sent -> accepted` flow for report packages;
- write audit events for every delivery transition;
- expose compact user-facing actions in the report UI;
- keep report/package creation separate from status transitions.

## Implemented

Added:

- `supabase/migrations/20260821201500_report_send_accept_commands.sql`;
- `supabase/tests/foundation_report_send_accept_smoke.sql`;
- `scripts/foundation_report_send_accept_smoke.cjs`.

Updated:

- `package.json`;
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

The new commands:

- `set_report_snapshot_delivery_status`;
- `set_report_package_delivery_status`.

They:

- require authenticated user context;
- require `reports.manage`;
- block invalid status transitions;
- are idempotent when the target status is already set;
- write `approval_events` for sent and accepted transitions.

The reports UI now shows:

- `Отправить` for created reports and returned reports;
- `Принять` for sent reports;
- `Отправить` for created report packages;
- `Принять` for sent report packages.

The package builder was separated from saved package cards so command forms do
not nest inside the package creation form.

## Verification

Passed:

```bash
npm run smoke:foundation:report-send-accept
npm run typecheck:web
npm run build:web
git diff --check
```

The migration was applied to the connected Supabase foundation database.

## Acceptance

SPRINT-122R is accepted.

Reports and report packages now have a command-owned send/accept lifecycle with
audit trail and UI actions.

## Deferred

Next:

- PDF/export command generation;
- persisted report file storage;
- visible delivery history in report detail;
- manual QA in browser after deployment.
