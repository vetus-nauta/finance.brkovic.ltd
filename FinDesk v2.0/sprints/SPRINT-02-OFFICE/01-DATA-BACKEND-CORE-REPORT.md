# Backend Core Report

Subagent: Data/Backend Core Agent Sprint 02

Scope: `FinDesk v2.0/sql/` plus this Sprint 02 report. Legacy `public/api.php` and old root FinDesk tables were not touched.

Files read:

- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/02-data-model.md`
- `FinDesk v2.0/06-dictionaries-and-localization.md`
- `FinDesk v2.0/07-mvp-scope-and-acceptance.md`
- `FinDesk v2.0/08-codex-implementation-brief.md`
- `FinDesk v2.0/10-director-and-subagents.md`
- `FinDesk v2.0/11-build-phases.md`
- `FinDesk v2.0/12-agent-work-protocol.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/agents/02-DATA-BACKEND-CORE-READ-FIRST.md`
- `FinDesk v2.0/sql/clean-core-schema.sql`
- `FinDesk v2.0/sprints/SPRINT-02-clean-core-foundation.md`

Findings:

- Existing `clean-core-schema.sql` is a useful logical source, but it uses PostgreSQL-only `uuid`, `jsonb`, `timestamptz`, and `gen_random_uuid()`.
- Sprint 02 target is MariaDB/PDO MySQL-compatible, so a separate executable migration is needed.
- Categories can be seeded globally with `workspace_id NULL`; flows must be seeded per workspace.
- Cash and Card are funding flows, not categories. `cash_topup_from_card` remains a movement category usable by both sides of the approved Card-to-Cash pair.

Changes made:

- Added MariaDB clean core schema with the required tables: `workspaces`, `workspace_members`, `flows`, `categories`, `category_rules`, `actors`, `attachments`, `monthly_closures`, `import_sources`, `import_rows`, `audit_log`, and `entries`.
- UUID strategy is MariaDB-safe: `CHAR(36)` ids with `DEFAULT (UUID())`, no PostgreSQL extensions.
- JSON-compatible fields use `LONGTEXT` plus `JSON_VALID(...)` checks, matching MariaDB's JSON implementation style.
- Added idempotent MVP category seed using all category codes from `FULL_SPEC.md`.
- Added idempotent default workspace flow seed with required `Cash` (`type='cash'`, `has_live_balance=1`) and `Card` (`type='card'`, `has_live_balance=0`), plus optional `Assistant Journal`.

Infrastructure reusable:

- MariaDB/PDO MySQL execution path can apply these SQL files.
- Old auth/user ids may be stored as `CHAR(36)` in `created_by`, `performed_by`, `closed_by`, and `workspace_members.user_id` without coupling v2 schema to old user tables yet.

Infrastructure unsafe:

- Legacy finance tables and deploy SQL names such as `ledger_*`, `on_the_go_*`, `cash_advances`, and `findesk_*` were not used and should remain outside the v2 schema.
- Old root FinDesk business logic, reports, categories, and cash/card interpretation remain unsafe as v2 product truth.

Tables/migrations needed:

- Created `001-clean-core-mariadb.sql`.
- Created `002-seed-mvp-categories.sql`.
- Created `003-seed-default-workspace-flows.sql`.

API needed:

- Minimal Sprint 02 API still needs implementation outside this agent handoff: workspace create/read, flow read/create, entry create/read/update, category read, and audit append hooks.

Risks:

- `DEFAULT (UUID())`, generated columns, and `CHECK` constraints assume MariaDB 11.4+ as directed.
- Flow seed creates a deterministic Sprint 02 acceptance workspace if `@findesk_seed_workspace_id` is not set. For a real workspace, set `@findesk_seed_workspace_id` before running the seed file.
- `workspace_members.user_id` is intentionally not FK-bound until the auth donor decision is finalized.

Commands to verify:

```bash
mysql --host="$DB_HOST" --user="$DB_USER" --password --database="$DB_NAME" < "FinDesk v2.0/sql/001-clean-core-mariadb.sql"
mysql --host="$DB_HOST" --user="$DB_USER" --password --database="$DB_NAME" < "FinDesk v2.0/sql/002-seed-mvp-categories.sql"
mysql --host="$DB_HOST" --user="$DB_USER" --password --database="$DB_NAME" < "FinDesk v2.0/sql/003-seed-default-workspace-flows.sql"
```

Optional real-workspace flow seed:

```bash
mysql --host="$DB_HOST" --user="$DB_USER" --password --database="$DB_NAME" \
  --init-command="SET @findesk_seed_workspace_id = 'replace-with-existing-workspace-uuid'" \
  < "FinDesk v2.0/sql/003-seed-default-workspace-flows.sql"
```

Acceptance queries:

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
  'workspaces', 'workspace_members', 'flows', 'categories', 'category_rules',
  'actors', 'attachments', 'monthly_closures', 'import_sources',
  'import_rows', 'audit_log', 'entries'
)
ORDER BY table_name;

SELECT code, direction
FROM categories
WHERE workspace_id IS NULL
ORDER BY sort_order;

SELECT name, type, has_live_balance, is_default
FROM flows
WHERE workspace_id = '20000000-0000-0000-0000-000000000100'
ORDER BY name;
```

Recommended next action:

- Run the migrations against a clean MariaDB-compatible database, then implement the minimal API over this schema without reusing legacy finance logic.

Files touched:

- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `FinDesk v2.0/sql/002-seed-mvp-categories.sql`
- `FinDesk v2.0/sql/003-seed-default-workspace-flows.sql`
- `FinDesk v2.0/sprints/SPRINT-02-OFFICE/01-DATA-BACKEND-CORE-REPORT.md`
