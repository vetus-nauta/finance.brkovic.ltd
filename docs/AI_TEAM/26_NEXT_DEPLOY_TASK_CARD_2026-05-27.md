# Next Deploy Task Card

Date: 2026-05-27

Owner: Project Director / Deploy Owner / Database Migration Owner

Status: completed.

## Task

Complete production DB backup and DB schema preflight for the FinDesk MVP runtime artifact.

## Read First

- `docs/AI_TEAM/19_PRODUCTION_GO_NO_GO_2026-05-27.md`
- `docs/AI_TEAM/24_MVP_RUNTIME_ARTIFACT_2026-05-27.md`
- `docs/AI_TEAM/25_PRODUCTION_FILE_BACKUP_2026-05-27.md`
- `deploy/on_the_go_sessions_runtime.sql`
- `docs/AI_TEAM/17_DB_BACKUP_ROLLBACK_PLAN.md`
- `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`

## Current State

- Product MVP gate: GO.
- Runtime artifact: built, hardened, and deployed.
- Production files/storage backup: completed.
- Production DB backup: completed.
- Production DB schema preflight: completed.
- Production runtime SQL: applied and verified.
- Production upload: completed.
- Production smoke: passed.
- Current shell cannot complete this task directly: `php`, `mysql`, and `mariadb` CLIs are unavailable, and production DB port `3306` times out from here.

## Required Evidence

1. Production DB backup created and restorable source recorded without secrets.
2. Production DB engine/version recorded without secrets.
3. Schema compatibility checked for `deploy/on_the_go_sessions_runtime.sql`.
4. Runtime SQL applied or proven already present/compatible.
5. Rollback owner and smoke owner confirmed.

Completion record:

- `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`

## Stop Rule

This card is complete.

Do not store credentials in reports.
