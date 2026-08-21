# SPRINT-117R — Foundation Report Package Command

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

The old MVP behavior allowed the user to combine closed report periods before
sending a larger report to the principal. In the foundation architecture this
must be command-owned and auditable, not a direct client write.

Goal:

- create report packages only through an RPC command;
- preserve the selected report order;
- reject missing or foreign snapshots;
- expose the package action in the reports UI;
- keep existing immutable report snapshots as the source of truth.

## Implemented

Added:

- `supabase/migrations/20260821193000_report_package_command.sql`;
- `supabase/tests/foundation_report_package_command_smoke.sql`;
- `scripts/foundation_report_package_command_smoke.cjs`.

Updated:

- `package.json`;
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`;
- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

The new command:

- requires authenticated user context;
- requires active workspace membership and `reports.manage`;
- creates `report_packages`;
- creates ordered `report_package_items`;
- deduplicates repeated selected snapshots while preserving first position;
- writes `approval_events.report_package_created`;
- keeps report tables protected from direct mutable client access.

The reports UI now supports:

- selecting saved reports;
- naming a package;
- creating the package through the server action;
- seeing saved packages above the report list.

## Verification

Passed:

```bash
npm run smoke:foundation:report-package
npm run typecheck:web
npm run build:web
git diff --check
```

The migration was applied to the connected Supabase foundation database.

## Acceptance

SPRINT-117R is accepted.

Saved report snapshots can now be combined into a command-owned package.

## Deferred

Next:

- standalone HTML route for a report package;
- package print/PDF;
- send/accept/return-for-revision status lifecycle.
