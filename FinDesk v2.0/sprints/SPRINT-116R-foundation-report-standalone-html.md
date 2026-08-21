# SPRINT-116R — Foundation Report Standalone HTML

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

SPRINT-115R restored the essential report behavior: a category can be expanded
to the original operational rows. The next transfer step is a clean report view
that can be opened separately for review, sending, printing, and later PDF
generation.

Goal:

- keep the operational journal as the source of truth;
- open saved report snapshots outside the noisy workspace UI;
- render summary first, then expandable categories and source rows;
- do not recalculate independent report totals.

## Implemented

Updated:

- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

Added:

- `apps/web/src/app/workspaces/[workspaceId]/reports/[reportId]/route.ts`.

The new route:

- checks the current authenticated user through workspace membership;
- loads the exact requested report snapshot, not only the newest reports list;
- renders a standalone HTML document;
- includes report totals, accounts, categories, and source operational rows;
- uses native expandable category blocks for mobile and desktop.

The workspace report detail now has an `HTML` action for opening the saved
report document in a separate tab.

## Verification

Passed:

```bash
npm run typecheck:web
npm run build:web
```

## Acceptance

SPRINT-116R is accepted.

Saved reports now have the first real sendable surface: a standalone HTML view
based on the saved report snapshot.

## Deferred

Next:

- print/PDF-specific route or print action;
- report package command for combining several saved report snapshots;
- return-for-revision command and status lifecycle.
