# SPRINT-115R — Foundation Report Source Row Drilldown

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

SPRINT-114R made saved reports readable at summary level. The next MVP transfer
step is category context: a user or principal must be able to expand a category
and see the original operational rows that formed it.

Goal:

- use the report snapshot source transaction ids;
- show original rows inside each category;
- do not recalculate independent report numbers;
- keep the UI compact and non-technical.

## Implemented

Updated:

- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

The report detail now loads:

- source transaction ids from `report_snapshots.source_transaction_ids`;
- original transaction rows;
- matching ledger rows;
- category labels from workspace categories.

Each expandable category now shows:

- row number;
- date;
- original raw text;
- amount;
- accepted/review status.

## Verification

Passed:

```bash
npm run typecheck:web
npm run build:web
git diff --check
```

## Acceptance

SPRINT-115R is accepted.

Saved reports now have the core MVP behavior: summary first, original rows on
expand.

## Deferred

Next:

- standalone HTML export of the opened report;
- PDF/print route;
- report package combining multiple saved snapshots;
- return-for-revision command.
