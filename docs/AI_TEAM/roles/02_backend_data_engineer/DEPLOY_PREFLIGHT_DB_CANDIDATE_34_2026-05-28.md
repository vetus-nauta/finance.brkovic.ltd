# Deploy Preflight DB: Candidate 34

Date: 2026-05-28
Role: Backend/Data Engineer + Database Migration Owner FinDesk
Task: deploy-preflight sprint for candidate 34, DB scanner/group-delete migration safety
Status: production DB-side NO-GO until this read-only preflight is run on production and backup/rollback is recorded

## Boundary

- Runtime code was not changed in this task.
- No production DB command was run in this task.
- This document is the DB-side preflight and apply decision for candidate `34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28`.

## Read-Only Production Preflight SQL

Run these queries on the production database selected by the production connection. They do not change data or schema.

```sql
SELECT
  DATABASE() AS database_name,
  VERSION() AS server_version,
  @@version_comment AS version_comment;
```

```sql
SELECT
  TABLE_NAME,
  ENGINE,
  TABLE_COLLATION
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'on_the_go_files',
    'on_the_go_upload_states',
    'groups',
    'group_members',
    'group_invites'
  )
ORDER BY TABLE_NAME;
```

```sql
SELECT
  required.table_name,
  required.column_name,
  CASE WHEN c.COLUMN_NAME IS NULL THEN 'MISSING' ELSE 'OK' END AS preflight_state,
  c.COLUMN_TYPE,
  c.IS_NULLABLE,
  c.COLUMN_DEFAULT,
  c.EXTRA,
  c.ORDINAL_POSITION
FROM (
  SELECT 'on_the_go_files' AS table_name, 'proof_role' AS column_name UNION ALL
  SELECT 'on_the_go_files', 'proof_bundle_id' UNION ALL
  SELECT 'on_the_go_files', 'source_file_id' UNION ALL
  SELECT 'on_the_go_files', 'file_hash_sha256' UNION ALL
  SELECT 'on_the_go_files', 'metadata_json' UNION ALL
  SELECT 'on_the_go_upload_states', 'proof_role' UNION ALL
  SELECT 'on_the_go_upload_states', 'proof_bundle_id' UNION ALL
  SELECT 'on_the_go_upload_states', 'file_hash_sha256' UNION ALL
  SELECT 'on_the_go_upload_states', 'metadata_json'
) AS required
LEFT JOIN INFORMATION_SCHEMA.COLUMNS c
  ON c.TABLE_SCHEMA = DATABASE()
 AND c.TABLE_NAME = required.table_name
 AND c.COLUMN_NAME = required.column_name
ORDER BY required.table_name, required.column_name;
```

```sql
SELECT
  required.table_name,
  required.index_name,
  required.expected_columns,
  CASE WHEN s.INDEX_NAME IS NULL THEN 'MISSING' ELSE 'OK' END AS preflight_state,
  GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS actual_columns
FROM (
  SELECT 'on_the_go_files' AS table_name, 'idx_otr_files_bundle' AS index_name, 'proof_bundle_id' AS expected_columns UNION ALL
  SELECT 'on_the_go_files', 'idx_otr_files_role', 'proof_role' UNION ALL
  SELECT 'on_the_go_upload_states', 'uniq_otr_upload_state_user_client', 'user_id,client_upload_id' UNION ALL
  SELECT 'on_the_go_upload_states', 'idx_otr_upload_state_draft', 'draft_id' UNION ALL
  SELECT 'on_the_go_upload_states', 'idx_otr_upload_state_capture', 'capture_id' UNION ALL
  SELECT 'on_the_go_upload_states', 'idx_otr_upload_state_user_status', 'user_id,status,updated_at'
) AS required
LEFT JOIN INFORMATION_SCHEMA.STATISTICS s
  ON s.TABLE_SCHEMA = DATABASE()
 AND s.TABLE_NAME = required.table_name
 AND s.INDEX_NAME = required.index_name
GROUP BY required.table_name, required.index_name, required.expected_columns
ORDER BY required.table_name, required.index_name;
```

```sql
SELECT
  required.table_name,
  required.column_name,
  required.requirement,
  CASE WHEN c.COLUMN_NAME IS NULL THEN 'MISSING' ELSE 'OK' END AS preflight_state,
  c.COLUMN_TYPE,
  c.IS_NULLABLE,
  c.COLUMN_DEFAULT
FROM (
  SELECT 'groups' AS table_name, 'id' AS column_name, 'required for group_delete' AS requirement UNION ALL
  SELECT 'groups', 'name', 'required for group_delete response/audit' UNION ALL
  SELECT 'groups', 'created_by', 'required for creator/idempotent access' UNION ALL
  SELECT 'groups', 'status', 'required for soft archive' UNION ALL
  SELECT 'groups', 'updated_at', 'optional; group_delete writes only if present' UNION ALL
  SELECT 'groups', 'archived_at', 'optional; group_delete writes only if present' UNION ALL
  SELECT 'group_members', 'group_id', 'required for membership closure' UNION ALL
  SELECT 'group_members', 'user_id', 'required for admin check' UNION ALL
  SELECT 'group_members', 'status', 'required for membership closure' UNION ALL
  SELECT 'group_members', 'left_at', 'optional; group_delete writes only if present' UNION ALL
  SELECT 'group_members', 'updated_at', 'optional; group_delete writes only if present' UNION ALL
  SELECT 'group_invites', 'group_id', 'required for invite revocation' UNION ALL
  SELECT 'group_invites', 'status', 'required for invite revocation' UNION ALL
  SELECT 'group_invites', 'revoked_at', 'optional; group_delete writes only if present' UNION ALL
  SELECT 'group_invites', 'updated_at', 'optional; group_delete writes only if present'
) AS required
LEFT JOIN INFORMATION_SCHEMA.COLUMNS c
  ON c.TABLE_SCHEMA = DATABASE()
 AND c.TABLE_NAME = required.table_name
 AND c.COLUMN_NAME = required.column_name
ORDER BY required.table_name, required.column_name;
```

## Expected Scanner Schema

Required for candidate 34 scanner storage:

- `on_the_go_files.proof_role` as `VARCHAR(40) NOT NULL DEFAULT 'attachment'`
- `on_the_go_files.proof_bundle_id` as `VARCHAR(120) NULL`
- `on_the_go_files.source_file_id` as `BIGINT UNSIGNED NULL`
- `on_the_go_files.file_hash_sha256` as `CHAR(64) NULL`
- `on_the_go_files.metadata_json` as `MEDIUMTEXT NULL`
- `on_the_go_files.idx_otr_files_bundle(proof_bundle_id)`
- `on_the_go_files.idx_otr_files_role(proof_role)`
- `on_the_go_upload_states.proof_role` as `VARCHAR(40) NOT NULL DEFAULT 'attachment'`
- `on_the_go_upload_states.proof_bundle_id` as `VARCHAR(120) NULL`
- `on_the_go_upload_states.file_hash_sha256` as `CHAR(64) NULL`
- `on_the_go_upload_states.metadata_json` as `MEDIUMTEXT NULL`
- `on_the_go_upload_states.uniq_otr_upload_state_user_client(user_id, client_upload_id)`
- `on_the_go_upload_states.idx_otr_upload_state_draft(draft_id)`
- `on_the_go_upload_states.idx_otr_upload_state_capture(capture_id)`
- `on_the_go_upload_states.idx_otr_upload_state_user_status(user_id, status, updated_at)`

## Migration / Apply Decision

Candidate SQL files:

- `deploy/on_the_go_foundation.sql`
  - creates `on_the_go_captures` and `on_the_go_files` if absent;
  - includes scanner ALTERs for `on_the_go_files`.
- `deploy/on_the_go_sessions_runtime.sql`
  - creates runtime tape/session/draft/upload-state tables if absent;
  - includes ALTERs for existing `on_the_go_captures`, `on_the_go_tapes`, `on_the_go_files`, and `on_the_go_upload_states`;
  - includes all current scanner proof columns for `on_the_go_files` and `on_the_go_upload_states`.

Apply decision:

1. If every scanner table, column, and index above is `OK`, do not apply SQL.
2. If `on_the_go_files` is missing, apply `deploy/on_the_go_foundation.sql` first, then `deploy/on_the_go_sessions_runtime.sql`.
3. If `on_the_go_files` exists but scanner columns/indexes or `on_the_go_upload_states` runtime columns are missing, `deploy/on_the_go_sessions_runtime.sql` is the candidate apply file.
4. Run any apply only after production DB backup and rollback owner are recorded.
5. Record before/after output from the read-only preflight SQL.

## Engine Compatibility Risk

The candidate SQL uses:

- `ADD COLUMN IF NOT EXISTS`
- `ADD KEY IF NOT EXISTS`

This syntax is compatible with MariaDB ALTER TABLE syntax for conditional columns/keys. MariaDB documents `ADD COLUMN [IF NOT EXISTS]` and `ADD {INDEX|KEY} [IF NOT EXISTS]` in ALTER TABLE. MySQL compatibility remains a deploy risk because MySQL ALTER TABLE support for these exact conditional clauses is not portable across hosting versions. Check `VERSION()` and `@@version_comment` before applying.

Operational rule:

- If production is MariaDB and the preflight shows missing scanner schema, candidate SQL can be applied after backup.
- If production is MySQL or an unknown fork, do not apply the candidate SQL blindly. Stop and prepare missing-only DDL from the read-only preflight result, or run the SQL first on a byte-equivalent staging clone.

Reference:

- MariaDB ALTER TABLE documentation: https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table
- MySQL ALTER TABLE documentation: https://dev.mysql.com/doc/refman/8.0/en/alter-table.html

## Group Delete DB Decision

`app/groups.php::ql_group_delete()` does not require a SQL migration for optional timestamp columns.

Confirmed from code:

- required group columns: `groups.id`, `groups.name`, `groups.created_by`, `groups.status`;
- required membership columns: `group_members.group_id`, `group_members.user_id`, `group_members.status`;
- required invite columns: `group_invites.group_id`, `group_invites.status`;
- optional columns are checked through `INFORMATION_SCHEMA.COLUMNS` before use:
  - `groups.archived_at`
  - `groups.updated_at`
  - `group_members.left_at`
  - `group_members.updated_at`
  - `group_invites.revoked_at`
  - `group_invites.updated_at`

Therefore, missing `groups.updated_at`, `groups.archived_at`, `group_members.left_at`, `group_invites.revoked_at`, or `group_invites.updated_at` does not block `group_delete`.

## DB-Side Production Decision

Current DB-side status: **NO-GO until production preflight is executed and recorded**.

No code-level DB blocker was found in the candidate. DB-side can move to **GO** only when:

1. production preflight confirms required scanner schema is present, or candidate SQL is applied successfully after backup;
2. production engine compatibility is confirmed;
3. before/after schema evidence is recorded;
4. backup/rollback owner is recorded.
