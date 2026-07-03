# Sprint 02 Handoff To Sprint 03 Director

Use this as the starting context for the next Director.

## Paste-To-New-Director-Chat

```text
Ты директор FinDesk v2.0 Sprint 03 — Financial Logic Engine.

Репозиторий: vetus-nauta/finance.brkovic.ltd
Каноническая локальная папка для Codex:
C:\Users\Vetus Nauta\Мой диск\FOR CODEX\Интернет-проекты\06-finance.brkovic.ltd

Важно:
- Работать только в этой Google Drive-синхронизированной папке.
- Не использовать старый checkout:
  C:\Users\Vetus Nauta\Documents\finance.brkovic.ltd
- Перед любыми действиями выполнить `git status --short --branch` именно в канонической папке.
- Ты директор: сначала прочитай Sprint 02 handoff, Sprint 02 final report и Sprint 03 contract.
- Истина продукта = только `FinDesk v2.0/`.
- Старый FinDesk в корне repo = infrastructure donor/archive only.
- Не расширять legacy `public/api.php`.
- Не использовать старые deploy SQL/tables/formulas/categories/reports.

Sprint 03 contract:
- File: `FinDesk v2.0/sprints/SPRINT-03-financial-logic-engine.md`
- Goal: deterministic financial logic on top of clean Sprint 02 foundation.
- Scope: parser, strict plus/minus rule, invalid visible rows, cash balance recalculation, card expense logic, approved card-to-cash model, commercial income category, other fallback, category suggestion engine v1, fixture tests.
- Forbidden: no UX build, no full import, no charts, no forecast, no old calculations.

Sprint 02 result:
- DB gate decided: MariaDB-compatible v2 schema is the deployable target.
- PostgreSQL-style `FinDesk v2.0/sql/clean-core-schema.sql` remains logical source only.
- Clean MariaDB migrations added:
  - `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
  - `FinDesk v2.0/sql/002-seed-mvp-categories.sql`
  - `FinDesk v2.0/sql/003-seed-default-workspace-flows.sql`
- Clean v2 namespace added:
  - `app/v2/`
  - `public/v2-api.php`
- Legacy `public/api.php` was not modified.
- Sprint 02 office:
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/00-DIRECTOR-LOG.md`
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/01-DATA-BACKEND-CORE-REPORT.md`
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/02-CLEAN-V2-RUNTIME-API-REPORT.md`
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/03-QA-AUDIT-ACCEPTANCE-REPORT.md`
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/10-DIRECTOR-FINAL-SPRINT-REPORT.md`
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/11-HANDOFF-TO-SPRINT-03.md`
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/12-OPENING-WORD-TO-SPRINT-03-DIRECTOR.md`
  - `FinDesk v2.0/sprints/SPRINT-02-OFFICE/v2-api-smoke.php`

Implemented API:
- `GET /workspaces`
- `POST /workspaces`
- `GET /workspaces/{workspaceId}`
- `GET /workspaces/{workspaceId}/flows`
- `POST /workspaces/{workspaceId}/flows`
- `GET /workspaces/{workspaceId}/entries?year=YYYY&month=M`
- `POST /workspaces/{workspaceId}/entries`
- `PATCH /entries/{entryId}`
- `GET /workspaces/{workspaceId}/categories`

Sprint 03 must preserve:
- Operational journal is source of truth.
- Summaries are generated later, not manually maintained.
- Cash/Card are funding flows, not categories.
- Card-to-cash approved model:
  Card: `-1000 снял с карты`
  Cash: `+1000 снял с карты`
  Both rows are valid and counted in their own flows.
- A counted row must begin with `+` or `-`.
- No-sign rows remain visible, status `unrecognized`, not counted.
- Commercial income category is separate from opening balance, private top-up, debt return, correction, and card-to-cash.
- Other fallback must remain visible and reviewable.

Open gates before production/staging deploy:
- Run PHP lint/smoke on a PHP-enabled environment.
- Apply MariaDB migrations to a clean staging DB.
- Confirm production backup/restore owner.
- Confirm migration channel.
- Commit no secrets.

Current git state after Sprint 02 director work:
- branch: `main...origin/main`
- Sprint 01 office docs are still untracked.
- Sprint 02 files are untracked.
- No staging/commit was performed.
```

## Director Note

Sprint 03 is the first logic sprint. Its danger is not missing code; its danger is reviving old FinDesk calculations by accident. Treat old code as archive/donor only. Implement deterministic financial logic over `app/v2`, the MariaDB clean schema, and the v2 contracts.

## Required Read Order

```text
FinDesk v2.0/sprints/SPRINT-02-OFFICE/11-HANDOFF-TO-SPRINT-03.md
FinDesk v2.0/sprints/SPRINT-02-OFFICE/10-DIRECTOR-FINAL-SPRINT-REPORT.md
FinDesk v2.0/sprints/SPRINT-02-OFFICE/03-QA-AUDIT-ACCEPTANCE-REPORT.md
FinDesk v2.0/sprints/SPRINT-03-financial-logic-engine.md
FinDesk v2.0/FULL_SPEC.md
FinDesk v2.0/03-parsing-and-rules-engine.md
FinDesk v2.0/14-calculation-contract.md
FinDesk v2.0/15-test-fixtures.md
FinDesk v2.0/23-legacy-isolation-rule.md
```

## Sprint 02 Files Changed

```text
FinDesk v2.0/sql/001-clean-core-mariadb.sql
FinDesk v2.0/sql/002-seed-mvp-categories.sql
FinDesk v2.0/sql/003-seed-default-workspace-flows.sql
app/v2/Database.php
app/v2/Support.php
app/v2/Repository.php
app/v2/Api.php
public/v2-api.php
FinDesk v2.0/sprints/SPRINT-02-OFFICE/
```

## Sprint 03 Exit Criteria

- All calculation fixtures pass.
- Cash/Card logic matches contract.
- Commercial income is separate.
- No-sign rows are visible but not counted.
- Other expenses queue works at data level.
- No old calculations are reused.

