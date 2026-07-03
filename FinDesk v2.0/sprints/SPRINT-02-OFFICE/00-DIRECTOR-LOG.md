# Sprint 02 Director Log

Sprint: Sprint 02 - Clean Core Foundation
Director: Codex Director, Sprint 02
Date: 2026-07-03

## Canonical Workspace

Work was performed only in:

```text
C:\Users\Vetus Nauta\Мой диск\FOR CODEX\Интернет-проекты\06-finance.brkovic.ltd
```

The old checkout in `C:\Users\Vetus Nauta\Documents\finance.brkovic.ltd` was not used as the working directory.

## Initial Git State

```text
## main...origin/main
?? "FinDesk v2.0/sprints/SPRINT-01-OFFICE/"
```

## Director Gate Decisions

- DB engine: MariaDB-compatible v2 schema by default, because production evidence is MariaDB 11.4 / PDO MySQL.
- Existing `FinDesk v2.0/sql/clean-core-schema.sql` remains a logical PostgreSQL-style source, not the deployable Sprint 02 migration.
- Migration discipline: clean v2 SQL only; no legacy deploy SQL and no legacy finance tables.
- Runtime/API discipline: clean `app/v2` namespace plus `public/v2-api.php`; legacy `public/api.php` is not extended.
- Product truth: only `FinDesk v2.0/`.

## Agents

- Data/Backend Core Agent: MariaDB schema, category seed, default flow seed.
- Clean v2 Runtime/API Agent: PHP namespace and minimal API.
- Director QA: static guard checks, contract review, final report and handoff.

## Verification Notes

Local PHP was not available in PATH on this machine, so PHP lint and runtime smoke must be run on a PHP-enabled machine or server.

