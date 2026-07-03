# 16 — API Contract

## Purpose

Backend and frontend agents must connect through a small, explicit API.

Do not create broad APIs before the clean core works.

## Workspaces

```text
GET /api/workspaces
POST /api/workspaces
GET /api/workspaces/:id
PATCH /api/workspaces/:id
```

## Flows

```text
GET /api/workspaces/:workspaceId/flows
POST /api/workspaces/:workspaceId/flows
```

MVP default flows:

```text
Cash
Card
```

## Entries

```text
GET /api/workspaces/:workspaceId/entries?year=2026&month=7
POST /api/workspaces/:workspaceId/entries
PATCH /api/entries/:entryId
DELETE /api/entries/:entryId
```

### POST entry request

```json
{
  "flow_id": "uuid",
  "date": "2026-07-03",
  "raw_text": "-250 рыба"
}
```

## Parse preview

```text
POST /api/parse-entry-preview
```

Used to preview how a note will be parsed without saving.

## Categories

```text
GET /api/workspaces/:workspaceId/categories
PATCH /api/entries/:entryId/category
POST /api/workspaces/:workspaceId/category-rules
```

## Reports

```text
GET /api/workspaces/:workspaceId/reports/monthly?year=2026&month=7
GET /api/workspaces/:workspaceId/reports/category-matrix?year=2026
GET /api/workspaces/:workspaceId/reports/other-review
```

Reports are generated from entries.

## Month closure

```text
POST /api/workspaces/:workspaceId/months/:year/:month/close
POST /api/workspaces/:workspaceId/months/:year/:month/reopen
POST /api/workspaces/:workspaceId/months/:year/:month/correction
```

Editing a closed month must require explicit mode: correction or recalculation.

## Import

```text
POST /api/workspaces/:workspaceId/imports/excel
GET /api/workspaces/:workspaceId/imports/:importId/review
POST /api/workspaces/:workspaceId/imports/:importId/accept
```

## Attachments

```text
POST /api/entries/:entryId/attachments
GET /api/entries/:entryId/attachments
DELETE /api/attachments/:attachmentId
```

## API rule

All API responses that affect money must return enough data for the UI to update without guessing.
