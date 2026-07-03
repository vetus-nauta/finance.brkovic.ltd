# Clean v2 Runtime/API Report

Subagent: Clean v2 Runtime/API Agent Sprint 02
Director review date: 2026-07-03

## Scope

Created a clean v2 PHP runtime/API namespace without extending the legacy API.

## Files Added

- `app/v2/Database.php`
- `app/v2/Support.php`
- `app/v2/Repository.php`
- `app/v2/Api.php`
- `public/v2-api.php`
- `FinDesk v2.0/sprints/SPRINT-02-OFFICE/v2-api-smoke.php`

## API Entry

Primary Sprint 02 entry point:

```text
public/v2-api.php
```

Route options:

```text
GET /public/v2-api.php?path=/workspaces
POST /public/v2-api.php?path=/workspaces
GET /public/v2-api.php?path=/workspaces/{workspaceId}
GET /public/v2-api.php?path=/workspaces/{workspaceId}/flows
POST /public/v2-api.php?path=/workspaces/{workspaceId}/flows
GET /public/v2-api.php?path=/workspaces/{workspaceId}/entries&year=2026&month=7
POST /public/v2-api.php?path=/workspaces/{workspaceId}/entries
PATCH /public/v2-api.php?path=/entries/{entryId}
GET /public/v2-api.php?path=/workspaces/{workspaceId}/categories
```

The handler also supports `PATH_INFO` when the host allows it.

## Implemented Foundation

- Workspace list/create/get.
- Cash/Card flow list/create.
- Entry list/create/update.
- Category list with workspace seed from `FinDesk v2.0/schemas/categories.seed.json`.
- Audit log writes for mutating actions.

## Explicitly Not Implemented

- Parser.
- Reports.
- Import.
- UI.
- Charts.
- Forecasts.
- Assistant workflow.
- Legacy action router.

## Cash/Card Contract

Flows are funding flows. The API accepts explicit `flow_id`, `date`, and `raw_text` for entries and does not infer old `cash/noncash` semantics.

## Known Runtime Requirement

The runtime expects `app/config.local.php` or equivalent private config to provide MariaDB credentials through the existing private override pattern. No secrets were added to the repo.

