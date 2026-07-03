# Sprint 17 - API and Schema Gap Ledger

## Goal

Turn the Sprint 16 evidence audit into a clear backend gap ledger between the v2 schema, API contract, and implemented routes.

## Director rule

The schema can be ahead of the API, but the product must not pretend an API exists until the route is implemented and tested.

## Implemented API evidence

Implemented foundation routes:

```text
GET /workspaces
POST /workspaces
GET /workspaces/{workspaceId}
GET /workspaces/{workspaceId}/flows
POST /workspaces/{workspaceId}/flows
GET /workspaces/{workspaceId}/entries
POST /workspaces/{workspaceId}/entries
PATCH /entries/{entryId}
GET /workspaces/{workspaceId}/categories
```

## Schema evidence ahead of API

The MariaDB schema already contains tables for:

```text
import_sources
import_rows
attachments
monthly_closures
audit_log
category_rules
actors
```

## Gap ledger

```text
Reports: contract exists, API route not implemented.
Month closure: schema exists, API route not implemented.
Import: schema exists, API route not implemented.
Attachments: schema exists, API route not implemented.
Parse preview: contract exists, API route not implemented.
Category rules write API: schema exists, API route not implemented.
Delete entry: contract exists, API route not implemented.
```

## Accepted decisions

- Keep the current clean foundation as valid Sprint 02 evidence.
- Treat report/import/attachment/month features as pending implementation.
- Do not route UI work through old `public/app.php` or routes44 as v2 truth.
- Continue from v2 API and schema only.

## Visible-change bypass

No visible UI changes were made.

## Status

Completed as gap-ledger closure.

## Handoff

Sprint 18 must close the director continuation gate and name Sprint 19 implementation focus.
