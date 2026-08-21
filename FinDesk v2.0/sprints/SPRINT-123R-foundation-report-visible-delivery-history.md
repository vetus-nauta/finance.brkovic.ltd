# SPRINT-123R — Foundation Report Visible Delivery History

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

SPRINT-122R added command-owned report delivery transitions and audit events.
Those events must be visible to the user, not only stored in the database.

Goal:

- read report/package `approval_events` through the existing RLS boundary;
- show report history in the workspace report detail;
- show last package event in package cards;
- include history in standalone HTML reports and report packages;
- keep this as a presentation/data-read layer only.

## Implemented

Updated:

- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/workspaces/[workspaceId]/reports/[reportId]/route.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/report-packages/[packageId]/route.ts`;
- `apps/web/src/app/globals.css`.

The workspace data loader now attaches approval event summaries to:

- report snapshots;
- report packages;
- standalone report documents;
- standalone report package documents.

The UI now shows:

- report detail history with date, action, and note;
- latest package action in saved package cards;
- history table in standalone report HTML;
- package history and per-report history in standalone package HTML.

## Verification

Passed:

```bash
npm run typecheck:web
npm run build:web
git diff --check
```

## Acceptance

SPRINT-123R is accepted.

Report delivery/audit state is now visible in the product surface.

## Deferred

Next:

- generated PDF/export storage;
- sender/recipient names in history;
- browser QA against real Supabase data;
- production deployment after a larger coherent batch.
