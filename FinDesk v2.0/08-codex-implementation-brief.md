# 08 — Codex Implementation Brief

## Task

Create FinDesk v2.0 Clean Core MVP inside the existing old FinDesk project without extending old finance business logic.

Use old FinDesk only as infrastructure donor if useful:

- auth;
- backend shell;
- database connection;
- admin shell;
- deployment config;
- file upload base;
- user/role scaffolding.

Do not reuse old dashboards, old calculations, or old business entities unless explicitly mapped to the clean core.

## Core principle

The operational journal is the source of truth. Summary reports are generated from it.

Build around:

```text
entries -> generated summaries
```

## Required tables

```text
workspaces
workspace_members
flows
entries
categories
category_rules
actors
attachments
monthly_closures
import_sources
import_rows
audit_log
```

## Required UI shell

```text
AppShell
 ├─ TopBar
 ├─ WorkspaceSummary
 ├─ MainArea
 │   ├─ LeftRail
 │   ├─ EventFeed
 │   └─ DetailPanel
 └─ InputBar
```

No body/page scroll. Event feed scrolls internally.

## Parser function

Implement deterministic parser:

```text
parseEntry(rawText, activeFlow, date, workspaceRules)
```

## Strict sign rule

If text does not start with `+` or `-`, create visible unrecognized row and exclude from arithmetic.

## Cash/Card rule

Support:

```text
Card: -1000 снял с карты
Cash: +1000 снял с карты
```

Card record remains card expense.
Cash record remains cash income.
Both can share category `cash_topup_from_card`.

Do not neutralize or hide either record.

## Category engine

Use seeded categories and rules.

If confidence is low, set category `other` and status `other_review`.

Manual reassignment must be possible.

## First importer

Implement minimal Excel import adapter for one old-format file:

- date;
- description;
- cash income;
- cash expense;
- actor/executor;
- card income;
- card expense.

## First acceptance demo

Demo should prove:

1. User can enter cash records.
2. User can enter card records.
3. Invalid no-sign rows are visible but not counted.
4. Cash balance recalculates.
5. Card expense totals work.
6. Card-to-cash pair works in approved logic.
7. Categories are suggested.
8. Other expenses are highlighted.
9. Monthly summary is generated.
10. One legacy Excel file can be mapped into normalized entries.

## Do not build yet

Do not start with charts, forecast, assistant workflow, full archive import, or PDF export.

Build clean core first.
