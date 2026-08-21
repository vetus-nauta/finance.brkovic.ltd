# SPRINT-118R — Foundation Report Package HTML

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

SPRINT-117R added command-owned report packages. The next MVP transfer step is
to open a package as a clean sendable document, not only as a saved database
object.

Goal:

- keep report snapshots immutable;
- load package items in the saved user order;
- render one standalone HTML document for the package;
- show package totals and then every included report;
- keep category drilldown available inside each report.

## Implemented

Updated:

- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`.

Added:

- `apps/web/src/app/workspaces/[workspaceId]/report-packages/[packageId]/route.ts`.

The new route:

- validates current workspace membership;
- loads the requested report package;
- loads included snapshots by package item order;
- renders package totals;
- renders each included report with category drilldown and source rows;
- returns standalone `text/html`.

The reports UI now shows an `HTML` action on saved packages.

## Verification

Passed:

```bash
npm run typecheck:web
npm run build:web
npm run smoke:foundation:report-package
git diff --check
```

## Acceptance

SPRINT-118R is accepted.

Report packages now have a clean HTML view suitable as the base for sending,
printing, and PDF.

## Deferred

Next:

- package print/PDF route;
- package send/accept lifecycle;
- return-for-revision command for a report snapshot or package.
