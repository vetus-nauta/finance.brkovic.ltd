# DB Backup / Runtime Migration / Rollback Plan

Date: 2026-05-27

Role: DB/Backup Release Engineer

Scope: production deploy controls for FinDesk 100% MVP.

Status: production deploy remains BLOCKED until this plan is accepted, production backups are completed, runtime schema is proven/applied, and production smoke passes.

## Director Short Report

Роль: DB/Backup Release Engineer

Задача: подготовить DB/runtime migration, backup, rollback и production verification plan для 100% MVP deploy.

Статус: план подготовлен; production action не выполнялся; deploy остается заблокирован до выполнения backup, preflight, migration и smoke.

Доказательство: inventory, preflight SQL, backup/rollback procedure, post-migration verification and drift risks documented in this file.

Блокер: нужно подтвердить production DB engine/version, schema compatibility, exact deploy bundle, production DB/files/storage backups, and runtime SQL execution on production/staging clone by deploy owner.

Следующий владелец: Project Director / Deploy Owner, then QA Release Engineer for production smoke.

## Guardrails

- No production DB connection was made during this report.
- No production migration was executed.
- No real credentials are included in this document.
- No application code was changed.
- Write scope was limited to `docs/AI_TEAM/17_DB_BACKUP_ROLLBACK_PLAN.md`.
- All commands below are operator templates. The deploy owner must replace placeholders on the production host using approved secure credential handling.

## Inputs Read

- `docs/AI_TEAM/15_100_PERCENT_MVP_CONTROL.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `deploy/on_the_go_sessions_runtime.sql`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- Supporting schema files were read for dependency inventory: `deploy/on_the_go_foundation.sql`, `deploy/advances_foundation.sql`, `deploy/ledger_foundation.sql`, `deploy/auth_foundation.sql`, `deploy/groups_foundation.sql`, `deploy/group_access_levels.sql`, `deploy/messages_foundation.sql`, `deploy/business_desk_foundation.sql`.
- Supporting runtime code was read for dependency verification only: `app/on_the_go.php`, `app/ledger.php`.

## Release DB Inventory

### Core foundation required before MVP runtime upload

These schema areas must exist before deploying PHP that depends on them:

- Auth/session/audit:
  - `users`
  - `auth_codes`
  - `sessions`
  - `user_settings`
  - `audit_log`
- Group runtime:
  - `groups`
  - `group_members`
  - `group_invites`
  - `invite_share_events`
  - `group_members.access_level`
  - `group_members.permissions_json`
  - `group_members.invited_by`
  - `group_members.invite_id`
  - `group_invites.invited_email`
  - `group_invites.access_level`
  - `group_invites.permissions_json`
- Ledger/runtime money tree:
  - `ledger_entries`
  - `ledger_entries.group_id`
  - `entry_files`
  - `audit_log.details` must be able to store final report JSON snapshots/packages.
- On the Go foundation:
  - `on_the_go_captures`
  - `on_the_go_files`
- Advances/accountable runtime:
  - `cash_advances`
  - `cash_advances.on_the_go_tape_id`
  - `cash_advances.expected_remaining`
  - `cash_advances.actual_remaining`
  - `cash_advances.difference_amount`
  - `cash_advances.submitted_at`
  - `cash_advances.accepted_at`
  - `cash_advances.returned_at`
- Group messages:
  - `group_messages`
  - `group_message_reads`
- Business Desk residual MVP:
  - `company_profiles`
  - `clients`
  - `proformas`
  - `proforma_items`

### Runtime migration from `deploy/on_the_go_sessions_runtime.sql`

The runtime SQL introduces or requires the following:

- `on_the_go_tapes`
  - Required by Field Combat tapes/cards, cash/card stream separation, group reports, finalization, advances linkage, and package proof trace.
  - Expected columns from the combined MVP schema:
    - `id`
    - `user_id`
    - `group_id`
    - `advance_id`
    - `stream_type`
    - `title`
    - `cash_received`
    - `currency`
    - `status`
    - `created_at`
    - `updated_at`
    - `closed_at`
    - `submitted_at`
    - `actual_remaining`
    - `difference_amount`
    - `archived_at`
  - Expected indexes:
    - primary key on `id`
    - `idx_otr_tapes_user_status`
    - `idx_otr_tapes_stream`
    - `idx_otr_tapes_created`
    - `idx_otr_tapes_group`
    - `idx_otr_tapes_advance`
  - Expected FK:
    - `user_id` references `users(id)`.

- `on_the_go_sessions`
  - Required by active/closed/archive Field Combat sessions and current working journal isolation.
  - Expected columns:
    - `id`
    - `user_id`
    - `tape_id`
    - `session_type`
    - `title`
    - `status`
    - `started_at`
    - `closed_at`
    - `archived_at`
    - `created_at`
    - `updated_at`
  - Expected indexes:
    - primary key on `id`
    - `idx_otr_sessions_user_tape`
    - `idx_otr_sessions_type_status`
  - Expected FKs:
    - `user_id` references `users(id)`
    - `tape_id` references `on_the_go_tapes(id)`.

- `on_the_go_captures`
  - Runtime SQL adds:
    - `tape_id BIGINT UNSIGNED DEFAULT NULL`
    - `session_id BIGINT UNSIGNED DEFAULT NULL`
    - `idx_otr_captures_tape`
    - `idx_otr_captures_session`
  - New MVP code writes new captures with both columns populated.
  - Legacy rows may remain with `NULL` values; see risk section.

- `on_the_go_field_drafts`
  - Required by durable Field Combat draft save/recover.
  - Important uniqueness:
    - `uniq_otr_field_draft_user_client (user_id, client_draft_id)`.

- `on_the_go_field_sync_ops`
  - Required by idempotent `on_the_go_signed_sync` via `client_operation_id`.
  - Important uniqueness:
    - `uniq_otr_field_sync_user_client (user_id, client_operation_id)`.

- `on_the_go_upload_states`
  - Required by proof upload begin/fail/retry/list state.
  - Important uniqueness:
    - `uniq_otr_upload_state_user_client (user_id, client_upload_id)`.

### Runtime file/storage inventory

The database deploy is not sufficient by itself. Production backup and verification must include:

- Application runtime paths selected by Project Director/deploy owner.
- `storage/documents/on-the-go/` for On the Go proof uploads.
- `storage/documents/group-final-reports/` for immutable final report proof copies.
- `storage/documents/YYYY/MM/` for ledger/advance proof copies.
- `storage/live-report-logs/` for append-only Field Combat journal/export files.
- `app/config.php` or equivalent production config must be backed up privately but never copied into docs or chat.

## Critical Schema Gap To Check

`deploy/on_the_go_sessions_runtime.sql` is not a complete reconciler for every possible pre-existing `on_the_go_tapes` table shape.

If `on_the_go_tapes` does not exist, the runtime SQL creates it with `group_id`, `advance_id`, and `stream_type`, but it does not create `submitted_at`, `actual_remaining`, or `difference_amount`.

If `on_the_go_tapes` already exists without `group_id`, `advance_id`, `submitted_at`, `actual_remaining`, or `difference_amount`, this runtime SQL only adds `stream_type`; it does not add the other columns. Those columns are introduced by `deploy/advances_foundation.sql`.

Therefore production preflight must prove one of these is true before PHP upload:

- `deploy/advances_foundation.sql` has already been applied and all required `on_the_go_tapes` columns exist; or
- an approved engine-compatible additive migration is prepared for the missing `on_the_go_tapes` columns; or
- production deploy is stopped.

## Preflight Before Applying SQL

### 1. Release freeze and ownership

- Confirm maintenance window or write freeze for production deploy.
- Confirm exact deploy mode:
  - narrow MVP runtime bundle; or
  - full dirty-tree bundle explicitly accepted by CEO/Project Director.
- Record deploy owner, database owner, rollback owner, and smoke owner.
- Confirm local/test/reset files are excluded unless explicitly approved:
  - `public/reset-local.php`
  - `scripts/start-local.sh`
  - `test-results/`
  - local backups/dumps
  - unrelated docs/work notes.

### 2. Production DB engine/version check

Run on production or staging clone using approved credentials storage:

```sql
SELECT
    VERSION() AS db_version,
    @@version_comment AS version_comment,
    @@sql_mode AS sql_mode,
    @@character_set_database AS database_charset,
    @@collation_database AS database_collation,
    @@max_allowed_packet AS max_allowed_packet;
```

Required decision:

- Verify `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` is supported.
- Verify `ALTER TABLE ... ADD KEY IF NOT EXISTS` is supported.
- If unsupported, stop and prepare an `information_schema`-driven idempotent migration for the exact engine. Do not test parser compatibility on production first.

### 3. Foundation table presence check

```sql
SELECT table_name, engine, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'users',
    'auth_codes',
    'sessions',
    'user_settings',
    'audit_log',
    'groups',
    'group_members',
    'group_invites',
    'invite_share_events',
    'ledger_entries',
    'entry_files',
    'on_the_go_captures',
    'on_the_go_files',
    'on_the_go_tapes',
    'on_the_go_sessions',
    'on_the_go_field_drafts',
    'on_the_go_field_sync_ops',
    'on_the_go_upload_states',
    'cash_advances',
    'group_messages',
    'group_message_reads',
    'company_profiles',
    'clients',
    'proformas',
    'proforma_items'
  )
ORDER BY table_name;
```

Expected:

- Required foundation tables exist before MVP upload.
- New runtime tables may be absent before migration, but must exist after migration.
- All FinDesk runtime tables should be InnoDB for FK consistency.

### 4. Required column compatibility check

```sql
SELECT table_name, column_name, column_type, is_nullable, column_default, extra
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'on_the_go_tapes' AND column_name IN (
      'id','user_id','group_id','advance_id','stream_type','title',
      'cash_received','currency','status','created_at','updated_at',
      'closed_at','submitted_at','actual_remaining','difference_amount','archived_at'
    ))
    OR (table_name = 'on_the_go_sessions' AND column_name IN (
      'id','user_id','tape_id','session_type','title','status',
      'started_at','closed_at','archived_at','created_at','updated_at'
    ))
    OR (table_name = 'on_the_go_captures' AND column_name IN (
      'id','user_id','tape_id','session_id','capture_type','amount',
      'currency','description','review_status','reportable','created_at','updated_at'
    ))
    OR (table_name = 'on_the_go_field_drafts' AND column_name IN (
      'id','user_id','group_id','participant_user_id','tape_id','session_id',
      'stream_type','client_draft_id','draft_status','sync_state','raw_notes',
      'parsed_rows_json','skipped_rows_json','cash_received','last_error',
      'last_operation_id','created_at','updated_at','closed_at','submitted_at'
    ))
    OR (table_name = 'on_the_go_field_sync_ops' AND column_name IN (
      'id','user_id','client_operation_id','tape_id','session_id','status',
      'response_json','last_error','created_at','updated_at'
    ))
    OR (table_name = 'on_the_go_upload_states' AND column_name IN (
      'id','user_id','draft_id','capture_id','client_upload_id','status',
      'original_name','storage_path','mime_type','size_bytes','last_error',
      'retry_count','created_at','updated_at','uploaded_at'
    ))
    OR (table_name = 'audit_log' AND column_name IN (
      'id','user_id','action','entity_type','entity_id','details','created_at'
    ))
    OR (table_name = 'ledger_entries' AND column_name IN (
      'id','user_id','group_id','entry_type','money_type','amount','deleted_at','created_at'
    ))
    OR (table_name = 'cash_advances' AND column_name IN (
      'id','group_id','assigned_to_user_id','on_the_go_tape_id','status',
      'expected_remaining','actual_remaining','difference_amount','submitted_at'
    ))
  )
ORDER BY table_name, ordinal_position;
```

Expected:

- Every required column listed in the inventory exists after migration.
- `stream_type` and `session_type` include both `cash` and `card`.
- status/sync enums include all values used by PHP:
  - `on_the_go_tapes.status`: `active`, `closed`, `archived`
  - `on_the_go_sessions.status`: `active`, `closed`, `archived`
  - `on_the_go_field_drafts.draft_status`: `active`, `submitted`, `closed`, `archived`
  - `on_the_go_field_drafts.sync_state`: `saved`, `pending`, `failed`, `retry_needed`
  - `on_the_go_upload_states.status`: `pending`, `uploaded`, `failed`, `retry_needed`.

### 5. Required index/constraint compatibility check

```sql
SELECT table_name, index_name, non_unique,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_in_index
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name IN (
    'on_the_go_tapes',
    'on_the_go_sessions',
    'on_the_go_captures',
    'on_the_go_field_drafts',
    'on_the_go_field_sync_ops',
    'on_the_go_upload_states'
  )
GROUP BY table_name, index_name, non_unique
ORDER BY table_name, index_name;
```

```sql
SELECT table_name, constraint_name, referenced_table_name
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name IN ('on_the_go_tapes', 'on_the_go_sessions')
ORDER BY table_name, constraint_name;
```

Expected:

- No index name collision with different columns.
- FK creation is not blocked by table engine mismatch or orphan data.

### 6. Legacy data risk check before migration/upload

Run only if relevant tables already exist:

```sql
SELECT COUNT(*) AS captures_without_tape_or_session
FROM on_the_go_captures
WHERE tape_id IS NULL OR session_id IS NULL;
```

```sql
SELECT COUNT(*) AS captures_with_missing_tape
FROM on_the_go_captures c
LEFT JOIN on_the_go_tapes t ON t.id = c.tape_id
WHERE c.tape_id IS NOT NULL
  AND t.id IS NULL;
```

```sql
SELECT COUNT(*) AS captures_with_missing_session
FROM on_the_go_captures c
LEFT JOIN on_the_go_sessions s ON s.id = c.session_id
WHERE c.session_id IS NOT NULL
  AND s.id IS NULL;
```

Expected:

- Missing references must be `0` before production smoke.
- `captures_without_tape_or_session` can be non-zero only if deploy owner accepts that legacy On the Go rows are outside the new Field Combat session path or an approved backfill has been prepared on staging first.

### 7. Storage preflight

On production host:

```sh
RELEASE_ID="$(date -u +%Y%m%dT%H%M%SZ)"
WEBROOT="/path/to/production/webroot"

test -d "$WEBROOT/storage/documents"
test -w "$WEBROOT/storage/documents"
mkdir -p "$WEBROOT/storage/documents/on-the-go"
mkdir -p "$WEBROOT/storage/documents/group-final-reports"
mkdir -p "$WEBROOT/storage/live-report-logs/append-only"
mkdir -p "$WEBROOT/storage/live-report-logs/exports"
test -w "$WEBROOT/storage/documents/on-the-go"
test -w "$WEBROOT/storage/documents/group-final-reports"
test -w "$WEBROOT/storage/live-report-logs/append-only"
test -w "$WEBROOT/storage/live-report-logs/exports"
```

Expected:

- Web runtime user can write proof files and journal/export files.
- Storage paths are not pointed at a local test directory.

### 8. SQL apply readiness

- Apply first to a staging clone or temporary restored copy of production.
- Do not rely on transaction rollback for DDL; MySQL/MariaDB DDL can auto-commit.
- If runtime SQL partially applies, stop and inventory actual schema state with `information_schema` before taking any next action.

## Backup Procedure

### Backup rules

- Do not write credentials into commands saved in repo, chat, or docs.
- Use an approved secure option file, vault-injected environment, or production host secret manager.
- Store backups outside the webroot.
- Protect backups with server-side permissions and encryption policy.
- Record:
  - release id
  - exact deploy file list
  - git/base revision if applicable
  - production webroot path
  - database name
  - backup file names
  - operator
  - timestamp.

### File backup

Template:

```sh
set -eu
RELEASE_ID="$(date -u +%Y%m%dT%H%M%SZ)"
WEBROOT="/path/to/production/webroot"
BACKUP_ROOT="/secure/backups/findesk/$RELEASE_ID"

mkdir -p "$BACKUP_ROOT"
cd "$WEBROOT"

find app public deploy scripts storage -type f -print | sort > "$BACKUP_ROOT/files-before.txt"
tar -czf "$BACKUP_ROOT/runtime-files-before.tgz" app public deploy scripts
tar -czf "$BACKUP_ROOT/storage-before.tgz" storage

sha256sum "$BACKUP_ROOT/runtime-files-before.tgz" > "$BACKUP_ROOT/runtime-files-before.tgz.sha256"
sha256sum "$BACKUP_ROOT/storage-before.tgz" > "$BACKUP_ROOT/storage-before.tgz.sha256"
```

Notes:

- If production config is inside `app/`, the tar will contain secrets. That is acceptable only for a private encrypted backup, never for repo/chat/docs.
- If storage is too large for a single tar, split storage backup by path:
  - `storage/documents/on-the-go`
  - `storage/documents/group-final-reports`
  - `storage/documents/YYYY/MM`
  - `storage/live-report-logs`.

### Database backup

Template using an approved secure option file:

```sh
set -eu
RELEASE_ID="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_ROOT="/secure/backups/findesk/$RELEASE_ID"
MYSQL_CNF="/secure/mysql/findesk-prod.cnf"
DB_NAME="findesk_production_database_name"

mkdir -p "$BACKUP_ROOT"

mysqldump \
  --defaults-extra-file="$MYSQL_CNF" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  "$DB_NAME" > "$BACKUP_ROOT/db-before.sql"

mysqldump \
  --defaults-extra-file="$MYSQL_CNF" \
  --no-data \
  --routines \
  --triggers \
  --events \
  "$DB_NAME" > "$BACKUP_ROOT/schema-before.sql"

gzip -9 "$BACKUP_ROOT/db-before.sql"
gzip -9 "$BACKUP_ROOT/schema-before.sql"
sha256sum "$BACKUP_ROOT/db-before.sql.gz" > "$BACKUP_ROOT/db-before.sql.gz.sha256"
sha256sum "$BACKUP_ROOT/schema-before.sql.gz" > "$BACKUP_ROOT/schema-before.sql.gz.sha256"
```

Verification:

```sh
test -s "$BACKUP_ROOT/db-before.sql.gz"
test -s "$BACKUP_ROOT/schema-before.sql.gz"
gzip -t "$BACKUP_ROOT/db-before.sql.gz"
gzip -t "$BACKUP_ROOT/schema-before.sql.gz"
sha256sum -c "$BACKUP_ROOT/db-before.sql.gz.sha256"
sha256sum -c "$BACKUP_ROOT/schema-before.sql.gz.sha256"
```

Recommended restore rehearsal on a non-production database:

```sh
RESTORE_DB="findesk_restore_check_$RELEASE_ID"
MYSQL_CNF="/secure/mysql/findesk-prod.cnf"

mysql --defaults-extra-file="$MYSQL_CNF" -e "CREATE DATABASE \`$RESTORE_DB\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c "$BACKUP_ROOT/db-before.sql.gz" | mysql --defaults-extra-file="$MYSQL_CNF" "$RESTORE_DB"
mysql --defaults-extra-file="$MYSQL_CNF" "$RESTORE_DB" -e "SHOW TABLES"
```

If the restore rehearsal cannot be performed on the production server, the deploy owner must explicitly accept that restore was not rehearsed and identify where the dump was verified.

## Migration Procedure

1. Complete file and DB backups.
2. Confirm backup verification checks passed.
3. Put application into agreed maintenance/write-freeze mode if available.
4. Run preflight schema and data checks.
5. If any required table/column/index is missing outside the approved runtime migration path, stop.
6. Apply approved SQL to staging clone first.
7. Apply `deploy/on_the_go_sessions_runtime.sql` on production only after clone success and approval.

Template:

```sh
set -eu
WEBROOT="/path/to/production/webroot"
MYSQL_CNF="/secure/mysql/findesk-prod.cnf"
DB_NAME="findesk_production_database_name"

mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" < "$WEBROOT/deploy/on_the_go_sessions_runtime.sql"
```

If preflight shows missing `on_the_go_tapes.submitted_at`, `actual_remaining`, or `difference_amount`, do not rely on `deploy/on_the_go_sessions_runtime.sql`. Apply the already approved foundation migration that owns those columns, or produce an engine-compatible additive patch through Project Director approval.

## Post-Migration Verification Queries

### Schema verification

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'on_the_go_tapes',
    'on_the_go_sessions',
    'on_the_go_field_drafts',
    'on_the_go_field_sync_ops',
    'on_the_go_upload_states'
  )
ORDER BY table_name;
```

```sql
SELECT table_name, column_name, column_type, is_nullable
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN (
    'on_the_go_tapes',
    'on_the_go_sessions',
    'on_the_go_captures',
    'on_the_go_field_drafts',
    'on_the_go_field_sync_ops',
    'on_the_go_upload_states'
  )
ORDER BY table_name, ordinal_position;
```

```sql
SELECT table_name, index_name,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_in_index
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name IN (
    'on_the_go_tapes',
    'on_the_go_sessions',
    'on_the_go_captures',
    'on_the_go_field_drafts',
    'on_the_go_field_sync_ops',
    'on_the_go_upload_states'
  )
GROUP BY table_name, index_name
ORDER BY table_name, index_name;
```

### Runtime data sanity

```sql
SELECT status, stream_type, COUNT(*) AS tapes
FROM on_the_go_tapes
GROUP BY status, stream_type
ORDER BY status, stream_type;
```

```sql
SELECT session_type, status, COUNT(*) AS sessions
FROM on_the_go_sessions
GROUP BY session_type, status
ORDER BY session_type, status;
```

```sql
SELECT COUNT(*) AS captures_without_tape_or_session
FROM on_the_go_captures
WHERE tape_id IS NULL OR session_id IS NULL;
```

```sql
SELECT COUNT(*) AS captures_with_missing_tape
FROM on_the_go_captures c
LEFT JOIN on_the_go_tapes t ON t.id = c.tape_id
WHERE c.tape_id IS NOT NULL
  AND t.id IS NULL;
```

```sql
SELECT COUNT(*) AS captures_with_missing_session
FROM on_the_go_captures c
LEFT JOIN on_the_go_sessions s ON s.id = c.session_id
WHERE c.session_id IS NOT NULL
  AND s.id IS NULL;
```

```sql
SELECT COUNT(*) AS duplicate_field_drafts
FROM (
    SELECT user_id, client_draft_id, COUNT(*) AS c
    FROM on_the_go_field_drafts
    GROUP BY user_id, client_draft_id
    HAVING COUNT(*) > 1
) x;
```

```sql
SELECT COUNT(*) AS duplicate_sync_ops
FROM (
    SELECT user_id, client_operation_id, COUNT(*) AS c
    FROM on_the_go_field_sync_ops
    GROUP BY user_id, client_operation_id
    HAVING COUNT(*) > 1
) x;
```

```sql
SELECT COUNT(*) AS duplicate_upload_states
FROM (
    SELECT user_id, client_upload_id, COUNT(*) AS c
    FROM on_the_go_upload_states
    GROUP BY user_id, client_upload_id
    HAVING COUNT(*) > 1
) x;
```

Expected:

- Missing tape/session references: `0`.
- Duplicate unique-key candidates: `0`.
- `captures_without_tape_or_session`: `0` for new MVP-created data; any legacy non-zero count requires documented acceptance or staged backfill.

## Production Smoke After Upload

Run after SQL and selected file upload.

Minimum checks:

- App loads over production HTTPS.
- `GET /api.php?action=current_user` returns valid JSON and does not expose dev login code behavior.
- Login flow works for an approved test user.
- On mobile viewport, Field Combat fast capture controls are visible.
- Field Combat draft save/recover works:
  - save draft with `client_draft_id`;
  - reload/recover by same id;
  - verify `on_the_go_field_drafts` row exists.
- Proof state works:
  - begin proof upload state;
  - mark failure/retry;
  - list proof state;
  - successful upload writes `on_the_go_files` and `on_the_go_upload_states`.
- Idempotent signed sync works:
  - submit once with `client_operation_id`;
  - submit same operation again;
  - verify no duplicate money rows/captures.
- Cash/card stream separation works:
  - cash stream accepts `cash_in`/`cash_out`;
  - card stream accepts `noncash_out`;
  - card spend does not reduce physical cash.
- Current period export is reachable:
  - `ledger_group_google_sheet`
  - `ledger_group_excel`.
- Closed final report package is reachable:
  - `ledger_group_final_report_list`
  - `ledger_group_final_report_detail`
  - `ledger_group_final_report_package`
  - `ledger_group_final_report_google_sheet`
  - `ledger_group_final_report_excel`
  - `ledger_group_final_report_proof_download`.
- Historical/current separation remains correct with the accepted `1000 / 600 / 400` scenario or Project Director-approved equivalent.
- Advance/accountable state appears in package.
- Group message send/list/unread/mark-read remains group-scoped.
- Business Desk proforma create/list/open/print does not mutate ledger report formulas.
- Storage writes are confirmed in:
  - `storage/documents/on-the-go/`
  - `storage/documents/group-final-reports/`
  - `storage/live-report-logs/`.

## Rollback Procedure

### Rollback principles

- File rollback and DB rollback are separate decisions.
- Prefer file rollback first when smoke fails after upload but DB writes are still valid/additive.
- Do not drop runtime tables/columns manually during an incident unless the database owner confirms no production writes depend on them.
- Full DB restore can erase production writes after the backup. It requires explicit Project Director/CEO approval.
- If DDL partially applied, first inventory schema state; do not rerun or reverse blindly.

### Scenario A: Migration fails before PHP upload

1. Keep production files unchanged.
2. Stop deploy.
3. Save migration error output privately.
4. Run `information_schema` inventory to determine partial DDL state.
5. If no data writes happened, choose one:
   - leave additive partial schema in place and prepare approved forward fix; or
   - restore DB from backup with Project Director approval.
6. Do not upload PHP that expects the failed schema.

### Scenario B: PHP upload fails or smoke fails before meaningful user writes

1. Restore runtime files from `runtime-files-before.tgz`.
2. Clear PHP opcache if enabled.
3. Invalidate/refresh service worker cache if deployed.
4. Keep DB additive migration in place unless Project Director approves DB restore.
5. Re-run minimum rollback smoke:
   - app loads;
   - `current_user` returns valid JSON;
   - login works;
   - existing ledger/group report screens load.

Template:

```sh
set -eu
WEBROOT="/path/to/production/webroot"
BACKUP_ROOT="/secure/backups/findesk/RELEASE_ID"

cd "$WEBROOT"
tar -xzf "$BACKUP_ROOT/runtime-files-before.tgz" -C "$WEBROOT"
```

### Scenario C: Smoke fails after users may have written new data

1. Freeze writes immediately.
2. Do not restore DB automatically.
3. Roll back runtime files first if old code can tolerate additive schema.
4. Preserve storage files written after deploy.
5. Export incident evidence:
   - affected API action;
   - relevant audit ids;
   - affected `on_the_go_tapes`, `on_the_go_sessions`, `on_the_go_captures`, field draft/sync/upload ids;
   - file paths in `storage/documents`.
6. Decide with Project Director:
   - forward hotfix; or
   - file rollback only; or
   - full DB + storage restore if data loss/corruption risk is higher than losing post-backup writes.

### Scenario D: Full DB restore is approved

Only execute during approved freeze.

Template:

```sh
set -eu
MYSQL_CNF="/secure/mysql/findesk-prod.cnf"
DB_NAME="findesk_production_database_name"
BACKUP_ROOT="/secure/backups/findesk/RELEASE_ID"

mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME" < /secure/admin/restore-prep-approved.sql
gunzip -c "$BACKUP_ROOT/db-before.sql.gz" | mysql --defaults-extra-file="$MYSQL_CNF" "$DB_NAME"
```

Requirements before running:

- Confirm exact restore target.
- Confirm current DB backup was taken immediately before restore attempt, even if broken.
- Confirm application is in maintenance/write-freeze mode.
- Confirm storage restore decision:
  - If restoring DB to pre-deploy state, restore storage to matching pre-deploy snapshot or reconcile orphan files.
  - If preserving storage, run orphan-file audit after DB restore.

## Risks If Tables Already Exist Or Columns Differ

- `CREATE TABLE IF NOT EXISTS` does not validate an existing table shape. It can silently leave a table incompatible with PHP.
- `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS` may be unsupported on the production engine/version.
- Existing `on_the_go_tapes` may be missing `group_id`, `advance_id`, `submitted_at`, `actual_remaining`, or `difference_amount`; `deploy/on_the_go_sessions_runtime.sql` alone may not fix that.
- Existing enum definitions may not include values required by current PHP, especially `card`, `archived`, `retry_needed`, and `uploaded`.
- Existing index names may exist with different column definitions, causing either migration failure or misleading verification.
- Existing tables may use MyISAM or different charset/collation, blocking FK creation or producing inconsistent comparisons.
- Existing orphan rows can block FK creation or cause runtime joins to drop data.
- Legacy `on_the_go_captures` rows with null `tape_id` or `session_id` are not backfilled by the runtime SQL; new session-based queries may ignore them.
- Existing unique-key candidate duplicates in field draft/sync/upload state tables can block unique index creation or break idempotency guarantees.
- `audit_log.details` stores final report snapshots/packages; large packages can be sensitive to `max_allowed_packet` and DB JSON/storage limits.
- Storage permissions can pass DB smoke but fail proof upload/final package proof copy.
- File rollback without DB rollback can leave new tables/columns in place; this is usually acceptable for additive migrations but must be recorded.
- DB rollback without storage rollback can leave orphan proof files; storage rollback without DB rollback can leave DB rows pointing to missing files.

## Stop Conditions

Stop production deploy if any of these occurs:

- Backup verification fails.
- Production DB engine/version cannot run the approved SQL syntax.
- Required foundation tables are absent.
- Required `on_the_go_tapes` columns are missing and no approved additive migration exists.
- Any post-migration missing tape/session reference appears for newly written test data.
- Proof storage path is not writable.
- Production smoke fails in money movement, idempotency, final report package, proof download, or historical/current separation.

## Final Classification

- Product/business MVP gate: approved by Chief Auditor per input context.
- DB/runtime production readiness: conditionally planned, not executed.
- Production deploy gate: BLOCKED until deploy owner completes backup, preflight, migration, upload, and smoke with recorded evidence.
