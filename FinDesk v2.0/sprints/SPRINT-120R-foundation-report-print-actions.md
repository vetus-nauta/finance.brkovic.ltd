# SPRINT-120R — Foundation Report Print Actions

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

Standalone HTML reports and packages are useful only if a normal user can act
on them immediately. The next transfer step is a small export toolbar for
printing/PDF and category expansion.

Goal:

- keep report and package HTML views clean;
- add visible actions for print/PDF;
- add expand/collapse for categories;
- hide the toolbar in printed output.

## Implemented

Updated:

- `apps/web/src/app/workspaces/[workspaceId]/reports/[reportId]/route.ts`;
- `apps/web/src/app/workspaces/[workspaceId]/report-packages/[packageId]/route.ts`.

Both standalone HTML documents now include:

- `Раскрыть`;
- `Свернуть`;
- `Печать / PDF`.

The toolbar is excluded from print output.

## Verification

Passed:

```bash
npm run typecheck:web
npm run build:web
git diff --check
```

## Acceptance

SPRINT-120R is accepted.

Report HTML and package HTML now have basic user-facing export actions.

## Deferred

Next:

- server-side generated PDF files;
- persistent send status and recipient metadata;
- correction command for returned reports.
