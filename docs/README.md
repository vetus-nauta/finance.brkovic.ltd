# Architecture Documentation Index

Date: 2026-08-20

Start here:

- `FOUNDATION_DIRECTOR_OPENING.md`
- `FOUNDATION_02_RUNBOOK.md`
- `SOURCE_OF_TRUTH.md`
- `CURRENT_STATE_AUDIT.md`
- `ARCHITECTURE.md`
- `ENVIRONMENT_MATRIX.md`
- `SECRETS_AND_ACCOUNTS.md`

Core foundation:

- `DATA_MODEL.md`
- `RLS_MODEL.md`
- `SECURITY_MODEL.md`
- `STORAGE_MODEL.md`
- `API_CONTRACTS.md`

Migration and operations:

- `MIGRATION_MAP.md`
- `MIGRATION_DRY_RUN_PLAN.md`
- `DEPLOYMENT.md`
- `BACKUP_RESTORE.md`
- `CUTOVER_PLAN.md`
- `ROLLBACK_PLAN.md`
- `RLS_TEST_PLAN.md`

Infrastructure:

- `FREE_TIER_INFRA.md`
- `INFRA_LIMITS.md`

Decisions:

- `DECISIONS/ADR-0001-postgres-supabase-foundation.md`
- `DECISIONS/ADR-0002-legacy-runtime-is-migration-source.md`

These documents define the clean `brkovic.app` foundation. They do not authorize production cutover, data migration, or deletion of the current PHP runtime.
