# SPRINT-124R — Foundation Report HTML And Excel Export

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

Report delivery needs file formats suitable for a real user workflow. HTML is
the readable/sendable view. PDF can be produced from the same HTML through print.
Excel is needed for tabular review, reuse, and manual reconciliation.

Goal:

- keep the existing standalone HTML report/package views;
- add Excel download routes for report snapshots;
- add Excel download routes for report packages;
- expose Excel actions in the workspace UI and standalone HTML toolbar;
- avoid adding heavy spreadsheet dependencies at this MVP layer.

## Implemented

Added:

- `apps/web/src/lib/report-excel.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/reports/[reportId]/excel/route.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/report-packages/[packageId]/excel/route.ts`.

Updated:

- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/workspaces/[workspaceId]/reports/[reportId]/route.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/report-packages/[packageId]/route.ts`.

Excel format:

- SpreadsheetML `.xls`;
- opens in Excel and LibreOffice;
- no extra runtime dependency;
- generated from the same report snapshot/package data as HTML.

Report workbook sheets:

- `Итоги`;
- `Счета`;
- `Категории`;
- `Лента`;
- `История`.

Report package workbook sheets:

- `Итоги`;
- `Отчеты`;
- `Категории`;
- `Лента`;
- `История`.

The standalone HTML toolbar now includes:

- expand all;
- collapse all;
- Excel download;
- print/PDF.

## Verification

Passed:

```bash
npm run typecheck:web
npm run build:web
git diff --check
```

The Next build includes:

- `/workspaces/[workspaceId]/reports/[reportId]/excel`;
- `/workspaces/[workspaceId]/report-packages/[packageId]/excel`.

## Acceptance

SPRINT-124R is accepted.

Reports and report packages now have readable HTML/PDF workflow and downloadable
Excel workbooks.

## Deferred

Next:

- persistent file version registry;
- Supabase Storage or equivalent file object storage;
- sender/recipient delivery metadata;
- browser download smoke with a real authenticated session.
