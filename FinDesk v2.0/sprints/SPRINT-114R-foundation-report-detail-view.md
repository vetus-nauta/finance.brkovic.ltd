# SPRINT-114R — Foundation Report Detail View

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

SPRINT-113R created report snapshots from operational entries. The next MVP
transfer step is user-readable report review: a saved report must open as a
clear summary, not remain a hidden database row.

Goal:

- read saved snapshot totals without recalculating them client-side;
- show report account totals;
- show category totals in expandable rows;
- keep the view compact and non-technical;
- avoid creating test data in Claudia Z just for screenshots.

## Implemented

Updated:

- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

The reports screen now supports:

- selecting a saved report by URL query `report=...`;
- defaulting to the newest saved report when reports exist;
- report total cards:
  - income;
  - expense;
  - net result;
  - review count;
- account summary rows;
- expandable category rows.

The detail view reads from `report_snapshots.totals`. It does not invent or
recalculate independent financial figures.

## Verification

Passed:

```bash
npm run typecheck:web
npm run build:web
git diff --check
```

Note:

- live foundation currently has no saved report snapshots, so no report detail
  screenshot was generated without creating extra test data;
- SPRINT-113R SQL smoke already proves snapshot creation and stored totals.

## Acceptance

SPRINT-114R is accepted as the first readable saved-report view.

## Deferred

Next:

- expand a category down to its original operational rows;
- export the opened report as standalone HTML;
- create report packages from multiple snapshots;
- return report for revision with visible status.
