# Cutover Plan

Date: 2026-08-20

Status: not approved, not scheduled.

## Phases

1. Freeze legacy schema and runtime snapshot.
2. Create sanitized dev dump.
3. Build PostgreSQL schema through migrations.
4. Import legacy data into staging.
5. Reconcile counts/totals/files.
6. Run RLS/security tests.
7. Run E2E critical workflows.
8. Put legacy production into maintenance/read-only window if needed.
9. Run final backup.
10. Run final migration.
11. Switch DNS/app routing only after acceptance.
12. Keep rollback window open.

## Explicit Owner Approval Required

Cutover cannot be started by Codex alone.
