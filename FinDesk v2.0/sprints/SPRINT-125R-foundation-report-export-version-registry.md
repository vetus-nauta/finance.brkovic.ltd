# SPRINT-125R — Foundation Report Export Version Registry

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

After HTML and Excel export were added, FinDesk needs a user-visible registry of
saved file versions. The first foundation layer must record the fact that a
report file version was created without pretending that generated files are
already permanent binary objects.

Goal:

- record HTML, Excel, and PDF export versions in the document registry;
- link each saved version to a report snapshot or report package;
- keep exports generated from immutable report/package data;
- expose saved versions inside the report UI;
- audit every version creation through `approval_events`.

## Implemented

Added:

- `supabase/migrations/20260821204500_report_export_version_command.sql`;
- `supabase/tests/foundation_report_export_version_smoke.sql`;
- `scripts/foundation_report_export_version_smoke.cjs`;
- `npm run smoke:foundation:report-export-version`.

Updated:

- `apps/web/src/lib/workspace-data.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`;
- `apps/web/src/app/globals.css`.

Command:

- `public.create_report_export_version(...)`.

The command:

- requires authenticated workspace access;
- requires `reports.manage`;
- requires `documents.write`;
- accepts only `report_snapshot` and `report_package`;
- accepts only `html`, `xls`, and `pdf`;
- creates or reuses a stable `documents` row per entity and format;
- creates sequential `document_versions`;
- creates a `document_links` relation;
- writes `report_export_version_created` to `approval_events`.

## Product Boundary

This sprint records export versions, filenames, formats, version numbers, and
download paths. It does not store binary files yet.

For MVP correctness, the download still opens the generated HTML view or Excel
route from immutable report data. PDF is represented as a saved PDF intent and
will use the HTML print/PDF flow until object storage is added.

## Verification

Passed:

```bash
npm run smoke:foundation:report-export-version
npm run typecheck:web
npm run build:web
git diff --check
```

## Acceptance

SPRINT-125R is accepted.

Reports and report packages now have a foundation export version registry and a
visible file history in the workspace UI.

## Deferred

Next:

- write generated files to Supabase Storage;
- add signed download links for stored files;
- add PDF generation as a true stored file;
- add sender/recipient metadata for delivery;
- add browser smoke for saving versions from an authenticated session.
