Subagent:
02-DATA-BACKEND-CORE - FinDesk v2.0 Sprint 01 Data and Backend Core Agent.

Scope:
Read-only Sprint 01 inventory of old FinDesk backend/runtime/database/auth/deploy parts as possible infrastructure donors for FinDesk v2.0.

No implementation code was written. No SQL was changed. No runtime config was changed. Real secret values were not copied into this report.

Files read:
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/21-sprint-plan.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/24-secrets-hosting-access-inventory.md`
- `FinDesk v2.0/10-director-and-subagents.md`
- `FinDesk v2.0/12-agent-work-protocol.md`
- `FinDesk v2.0/agents/00-DIRECTOR-READ-FIRST.md`
- `FinDesk v2.0/agents/02-DATA-BACKEND-CORE-READ-FIRST.md`
- `FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md`
- `FinDesk v2.0/02-data-model.md`
- `FinDesk v2.0/sql/clean-core-schema.sql`
- `FinDesk v2.0/08-codex-implementation-brief.md`
- `FinDesk v2.0/11-build-phases.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `README.md`
- `app/config.php`
- `app/config.local.example.php`
- `app/db.php`
- `app/auth.php`
- `app/openai_provider.php`
- `app/ai.php`
- `api.php`
- `public/api.php`
- `.htaccess`
- `package.json`
- `server/findesk-atlas-server.js`
- `scripts/atlas_connection_smoke.js`
- `scripts/cash_session_math_audit.js`
- `scripts/install_openai_key.sh`
- `scripts/yacht_price_ai_refresh.cjs`
- `deploy/README_MIGRATION.md`
- `deploy/run_sql_file.php`
- `deploy/test_db_connection.php`
- `deploy/test_php_mail.php`
- `deploy/create_temp_unread_message.php`
- `deploy/delete_temp_unread_message.php`
- `deploy/auth_foundation.sql`
- `deploy/groups_foundation.sql`
- `deploy/group_access_levels.sql`
- `deploy/group_workspace_type.sql`
- `deploy/group_trash_retention.sql`
- `deploy/ledger_foundation.sql`
- `deploy/categories_foundation.sql`
- `deploy/messages_foundation.sql`
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `deploy/advances_foundation.sql`
- `deploy/findesk_phase2_foundation.sql`
- `deploy/business_desk_foundation.sql`
- `docs/PREDEPLOY_CHECKLIST_2026-05-20.md`
- `docs/LIVE_DEPLOY_2026-05-20.md`
- `docs/CHECKPOINT_AFTER_HTTPS_ENABLED_20260503.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/17_DB_BACKUP_ROLLBACK_PLAN.md`
- `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`
- `docs/AI_TEAM/38_CANDIDATE34_PRODUCTION_DEPLOY_REPORT_2026-05-28.md`
- `docs/AI_TEAM/46_OPEN_ITEMS_SPRINT_DEPLOY_BLOCKED_2026-05-28.md`
- Attempted root `FULL_SPEC.md`; it does not exist. The authoritative file read was `FinDesk v2.0/FULL_SPEC.md`.

Findings:

Sprint 01 backend guardrail:
- Old FinDesk is not product truth for v2.0.
- Old finance tables, old categories, old reports, old calculations, old cash/card interpretation, old dashboards, and old docs must not be used as v2.0 core.
- Only infrastructure patterns can be donated after Director approval.

Backend/runtime classification:

| Part considered | Classification | Notes |
| --- | --- | --- |
| `app/config.php` | INFRASTRUCTURE_DONOR | Simple PHP array base config for app name, brand, `app_url`, and `storage_path`. No secret values found here. |
| `app/config.local.example.php` | INFRASTRUCTURE_DONOR | Good placeholder model for local/server-only DB, mail, OpenAI, and refresh settings. Keep real `app/config.local.php` out of repo. |
| `app/db.php` | INFRASTRUCTURE_DONOR | Provides `ql_config()` merge of base config plus `config.local.php`, and `ql_db()` singleton PDO connection. Pattern is useful; exact MySQL implementation must be reconciled with v2 DB decision. |
| `app/auth.php` JSON helpers (`ql_json`, `ql_input`) | GENERIC_HELPER | Small API response/input helpers can be reimplemented in clean namespace. |
| `app/auth.php` email-code auth/session/audit flow | INFRASTRUCTURE_DONOR | Useful auth shell: email code, hashed auth code, session token hash, httpOnly cookie, audit event. Not reusable as-is because it depends on old MySQL tables and old integer user ids. |
| `app/auth.php` group-scoped audit read | UNKNOWN_REQUIRES_DIRECTOR | Useful idea, but it reaches old `groups` membership logic and JSON details from old audit schema. |
| `app/openai_provider.php` | GENERIC_HELPER | Good secret resolution pattern: `${ENV_NAME}`, key file, `OPENAI_API_KEY`, disabled-by-default checks. AI feature itself is outside v2 core. |
| `app/ai.php` | UNSAFE_LEGACY_LOGIC | Runs analysis over old ledger/advances/report data and old risk/action concepts. Do not reuse for v2 finance logic. |
| `api.php` | INFRASTRUCTURE_DONOR | Thin root shim requiring `public/api.php`. Pattern can be kept only if new API namespace is explicit. |
| `public/api.php` routing shell | INFRASTRUCTURE_DONOR | Single action router with try/catch and JSON responses is a possible shell pattern. |
| `public/api.php` action list | UNSAFE_LEGACY_LOGIC | Mixed legacy actions for ledger, groups, messages, advances, reports, business desk, yacht provisioning/prices, AI. Do not make this v2 core API. |
| `.htaccess` | INFRASTRUCTURE_DONOR | Useful Apache private-path boundary: `Options -Indexes`, asset rewrites, `403` for `app`, `storage`, `deploy`, `cron`. Depends on Apache/.htaccess being honored. |
| `package.json` | UNKNOWN_REQUIRES_DIRECTOR | Npm scripts are Node/Mongo Atlas preview/support paths, not current PHP/MySQL production runtime and not v2 clean core. |
| `server/findesk-atlas-server.js` | UNSAFE_LEGACY_LOGIC | Large Node/Mongo preview server contains old product logic, yacht state/pricing, cash session parser/settlement. Do not reuse as v2 backend core. |
| `scripts/atlas_connection_smoke.js` | GENERIC_HELPER | Good Mongo URI masking/TLS/ping smoke pattern if a Mongo path is ever approved. Not part of current v2 clean SQL core. |
| `scripts/cash_session_math_audit.js` | UNSAFE_LEGACY_LOGIC | Tests old cash session parser/settlement, not approved v2 finance engine. |
| `scripts/install_openai_key.sh` | GENERIC_HELPER | Operator-local secret file pattern using `storage/secrets/openai_api_key` and restrictive permissions. |
| `scripts/yacht_price_ai_refresh.cjs` | UNSAFE_LEGACY_LOGIC | Yacht price refresh domain logic is outside v2 backend core. Only env/key-file pattern is generic. |

Deploy and SQL classification:

| Part considered | Classification | Notes |
| --- | --- | --- |
| `deploy/README_MIGRATION.md` | INFRASTRUCTURE_DONOR | Captures migration packaging principle: config/private paths separated, migration package includes code, DB dump, storage, invoices/reports/settings. |
| `deploy/run_sql_file.php` | UNKNOWN_REQUIRES_DIRECTOR | Generic SQL runner through `ql_db()`, but it executes arbitrary SQL and lists tables. Operator-only; do not use for v2 without Director-approved migration discipline. |
| `deploy/test_db_connection.php` | GENERIC_HELPER | Simple DB connectivity smoke using `ql_db()`. |
| `deploy/test_php_mail.php` | GENERIC_HELPER | Simple mail smoke. Operator-only because it sends mail and prints a generated test code. |
| `deploy/create_temp_unread_message.php` | UNSAFE_LEGACY_LOGIC | Mutates old users/groups/messages for smoke setup. Not a v2 donor. |
| `deploy/delete_temp_unread_message.php` | UNSAFE_LEGACY_LOGIC | Deletes old users/group message rows from temp ids. Not a v2 donor. |
| `deploy/auth_foundation.sql` | INFRASTRUCTURE_DONOR | Auth/session/audit table concepts are useful. DDL is MySQL/MariaDB BIGINT/ENUM and not directly compatible with v2 clean schema. |
| `deploy/groups_foundation.sql` | UNKNOWN_REQUIRES_DIRECTOR | Workspace/member/invite concepts overlap with v2, but old table names, roles, and access levels differ from v2 `workspaces`/`workspace_members`. |
| `deploy/group_access_levels.sql` | UNKNOWN_REQUIRES_DIRECTOR | Permission JSON concept may inform v2, but old `base/manager/advanced` model is not v2 role truth. |
| `deploy/group_workspace_type.sql` | UNSAFE_LEGACY_LOGIC | Old workspace type enum `team/yacht/home` conflicts with v2 `yacht/family/personal/business/trip/custom`. |
| `deploy/group_trash_retention.sql` | GENERIC_HELPER | Archive/status index idea is generic. Exact old table is not reusable. |
| `deploy/ledger_foundation.sql` | UNSAFE_LEGACY_LOGIC | Old ledger finance table with old income/expense and cash/noncash model. Do not reuse. |
| `deploy/categories_foundation.sql` | UNSAFE_LEGACY_LOGIC | Old category seed names and old category model are forbidden as v2 truth. |
| `deploy/messages_foundation.sql` | UNKNOWN_REQUIRES_DIRECTOR | Generic group message/read concept only; not in v2 MVP core entity list. |
| `deploy/on_the_go_foundation.sql` | UNSAFE_LEGACY_LOGIC | Old capture/proof model tied to legacy On the Go flow. |
| `deploy/on_the_go_sessions_runtime.sql` | UNSAFE_LEGACY_LOGIC | Old session/draft/upload state for legacy Field Combat/On the Go runtime. |
| `deploy/advances_foundation.sql` | UNSAFE_LEGACY_LOGIC | Old accountable-money/cash advance logic is not v2 core. |
| `deploy/findesk_phase2_foundation.sql` | UNSAFE_LEGACY_LOGIC | Old transfers, reports, protected actions, report snapshots. Do not reuse. |
| `deploy/business_desk_foundation.sql` | UNSAFE_LEGACY_LOGIC | Proforma/client/company module is outside v2 clean finance journal core. |

Env names needed:
- PHP config placeholders in `app/config.local.example.php`: `app_url`, `db_host`, `db_name`, `db_user`, `db_pass`, `session_cookie_name`.
- Mail placeholders: `mail.mode`, `mail.host`, `mail.port`, `mail.secure`, `mail.username`, `mail.password`, `mail.from_email`, `mail.from_name`.
- OpenAI placeholders/config keys: `openai.enabled`, `openai.api_key`, `openai.api_key_file`, `openai.model`, `openai.endpoint`, `openai.timeout_seconds`, `openai.max_output_tokens`, `openai.web_search_enabled`, `openai.web_search_tool`.
- OpenAI environment names: `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_API_BASE`.
- Node/Mongo support env names: `FINDESK_PORT`, `FINDESK_HOST`, `FINDESK_MONGO_DB`, `FINDESK_MONGO_URI_FILE`, `FINDESK_MONGO_URI`.
- Deploy/production smoke env names found in deploy docs/artifacts: `FINDESK_FTP_HOST`, `FINDESK_FTP_USER`, `FINDESK_FTP_PASS`, `FINDESK_FTP_ROOT`, `FINDESK_DB_GATE_URL`.
- v2 inventory baseline names from Sprint 01 spec that still need a final decision/mapping: `APP_URL`, `DATABASE_URL`, `FTP_HOST`, `FTP_USER`, `FTP_PASSWORD`, `SFTP_HOST`, `SFTP_USER`, `SSH_HOST`, `SSH_USER`, `DEPLOY_PATH`.

Where real secrets are expected to live:
- `app/config.local.php` for PHP DB/SMTP local production override.
- Server environment for `OPENAI_API_KEY` and possible future deploy/env variables.
- `storage/secrets/openai_api_key` for OpenAI key-file path.
- `storage/secrets/mongodb_uri` for Node/Mongo Atlas support scripts.
- No real FTP/SFTP/SSH/DB/API passwords should be committed.

DB connection method notes:
- Old PHP backend uses MySQL/MariaDB through PDO.
- DSN shape is `mysql:host=<db_host>;dbname=<db_name>;charset=utf8mb4`.
- Credentials are read from flat config keys: `db_host`, `db_name`, `db_user`, `db_pass`.
- PDO options: exception errors, associative fetch mode, emulated prepares disabled.
- Production docs reported MariaDB engine `11.4.10-MariaDB-cll-lve-log`.
- Important v2 conflict: `FinDesk v2.0/sql/clean-core-schema.sql` is PostgreSQL-style (`pgcrypto`, UUID defaults, `jsonb`, `timestamptz`). The old runtime is MySQL/MariaDB. Director must decide whether v2 uses PostgreSQL as specified or whether clean schema/migrations must be rewritten for MariaDB. Do not mix old MySQL tables with the v2 clean core.

Migration/deploy method notes:
- Old deploy method is manual/package-oriented, not a formal migration framework.
- Local migration order is documented in `docs/PREDEPLOY_CHECKLIST_2026-05-20.md`.
- `deploy/run_sql_file.php` imports a given SQL file through old `ql_db()` and then lists tables.
- Production deploy reports show a controlled narrow-runtime package flow: file backup, DB backup, DB preflight, temporary DB-gate for production backup/preflight/migration/smoke, selected file upload, smoke, DB-gate removal.
- FTP-based deploy is evidenced by reports and env variable names. SFTP/SSH variables are required by v2 inventory spec but no concrete old SFTP/SSH credential source was found.
- Deploy path from old docs: `/home/brkovic/finance.brkovic.ltd`.
- Live app path from old docs: `https://finance.brkovic.ltd/app.php`.
- The `.htaccess` deny rules help protect `app/`, `storage/`, `deploy/`, and `cron/` if Apache honors them; v2 should not rely on this alone.

Production access inventory:
- Hosting provider/control panel: Namecheap/cPanel inferred from HTTPS/SSL checkpoint.
- Production domain: `finance.brkovic.ltd`.
- Deployment method: manual FTP/package upload with backup/preflight/smoke gates; temporary DB-gate used in older production deployments.
- Deploy path: `/home/brkovic/finance.brkovic.ltd`.
- Runtime: PHP app on Apache-style hosting with `.htaccess`; MariaDB production DB; optional local/preview Node+Mongo Atlas path exists but is not authoritative v2 core.
- SSL/DNS notes: SSL installed for `finance.brkovic.ltd`; issuer recorded as Sectigo DV in old checkpoint; HTTP redirects to HTTPS. Namecheap SSL/cPanel path had a previous DCV placement issue; correct document root was `/home/brkovic/finance.brkovic.ltd`.
- Required secrets list: DB host/name/user/pass, SMTP host/port/secure/username/password/from fields, deploy FTP host/user/pass/root, DB gate URL, OpenAI key if AI is enabled, optional Mongo URI for Atlas support scripts.
- Missing access items: actual FTP/SFTP/SSH credentials, DB gate URL, confirmed current control panel URL/account owner, staging domain, DNS provider record inventory, current backup location/restore owner, and final v2 DB engine decision.

Auth/session donor notes:
- Good patterns: one-time email code, `password_hash` for auth code, attempt limit, expiry, session token generated with `random_bytes`, stored as SHA-256 hash, httpOnly SameSite cookie, revoke on logout, audit event.
- Rewrite required: old auth assumes MySQL integer `users`, `auth_codes`, `sessions`, `audit_log`. v2 clean schema uses UUID-style references and workspace roles.
- Security review needed before reuse: auth code is always appended to `storage/logs/auth_codes.log`; API catches can return exception messages; cookie `secure` depends on HTTPS server flag; no CSRF layer found for JSON actions; rate limiting is limited to code attempts, not request issuance.

Clean namespace readiness from backend side:
- Not ready to build v2 core directly on the old API namespace.
- Existing `public/api.php` is legacy action soup and must not become v2 clean API.
- Existing old SQL migrations must not be run for v2 clean core.
- `FinDesk v2.0/` has clean docs and schema, but no isolated runtime namespace/API module is present yet.
- Recommended clean backend namespace for Sprint 02: create a separate v2 module/API boundary after Director approval, for example `app/v2/` plus explicit v2 API entrypoint, or another approved clean namespace. It must use v2 entities only: `workspaces`, `workspace_members`, `flows`, `entries`, `categories`, `category_rules`, `actors`, `attachments`, `monthly_closures`, `import_sources`, `import_rows`, `audit_log`.
- DB engine decision is the key blocker: old donor runtime is MariaDB/PDO MySQL, while v2 schema is PostgreSQL-style.

Legacy documentation rejection note:
- Old docs under `docs/` and `docs/AI_TEAM/` were used only for infrastructure/deploy/access clues.
- They are rejected as product, financial, UX, category, report, calculation, and cash/card truth for v2.0.

Changes made:
- Created this read-only inventory report.
- No implementation code changed.
- No SQL changed.
- No runtime config changed.
- No secrets recorded.

Risks:
- Biggest backend risk: v2 clean schema appears PostgreSQL-oriented while production runtime is MariaDB/PDO MySQL. Continuing without a Director DB-engine decision can cause invalid migrations or accidental legacy-table reuse.
- Existing API entrypoint mixes auth shell with old finance/report/business/yacht/AI actions. Reusing it directly would revive legacy logic.
- Old auth can donate patterns, but it needs security hardening and clean-table rewrite before v2 use.
- Manual SQL runner and temporary DB-gate pattern are powerful but dangerous without strict operator controls.
- `.htaccess` protection is hosting-specific. If v2 runs under a different server or document root, private path protection must be revalidated.
- Old deploy docs show FTP variables were absent in at least one shell; current usable production credential source remains unproven.
- Old production smoke data and old docs include operational history. They must not become v2 test fixtures or product rules.

Recommended next action:
- Director decision required: choose v2 database engine and migration discipline before Sprint 02 backend implementation.
- If v2 stays with PostgreSQL-style clean schema, treat old PHP MySQL/PDO code as pattern-only and build a separate v2 DB layer.
- If production must stay on MariaDB, rewrite the v2 clean schema into an approved MariaDB migration set before any runtime code is written.
- Create a clean v2 backend namespace/API boundary only after Director approval; do not extend `public/api.php` with v2 core actions.
- Prepare `.env.example` or approved equivalent containing only variable names/placeholders from this report.
- Produce a final production access checklist for Director: provider/account owner, control panel URL, FTP/SFTP/SSH method, DB credentials storage, deploy path, staging path, DNS records, SSL renewal owner, backup/restore owner.

Files touched:
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/02-DATA-BACKEND-CORE-REPORT.md`
