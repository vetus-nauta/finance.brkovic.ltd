# Legacy Import and Archive Agent Report

Subagent:
Legacy Import and Archive Agent / FinDesk v2.0 Sprint 01.

Scope:
Inventory old FinDesk documentation, deploy SQL, archive artifacts, screenshots, and report exports as archive/import sources only. Old docs, old finance logic, old database entities, old reports, old category logic, old dashboard logic, old UX decisions, and old calculations are rejected as FinDesk v2.0 product truth.

Files read:
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/05-import-and-legacy-data.md`
- `FinDesk v2.0/09-operational-and-summary-table-contract.md`
- `FinDesk v2.0/10-director-and-subagents.md`
- `FinDesk v2.0/12-agent-work-protocol.md`
- `FinDesk v2.0/15-test-fixtures.md`
- `FinDesk v2.0/19-legacy-import-acceptance.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/21-sprint-plan.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/24-secrets-hosting-access-inventory.md`
- `FinDesk v2.0/agents/00-DIRECTOR-READ-FIRST.md`
- `FinDesk v2.0/agents/06-LEGACY-IMPORT-ARCHIVE-READ-FIRST.md`
- `FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md`
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/00-DIRECTOR-LOG.md`
- `README.md`
- `.htaccess`
- `app/config.php`
- `app/config.local.example.php`
- `app/db.php`
- `deploy/README_MIGRATION.md`
- `deploy/run_sql_file.php`
- `deploy/test_db_connection.php`
- deploy SQL inventory via `deploy/*.sql`
- `docs/HANDOFF_2026-05-20.md`
- `docs/LIVE_DEPLOY_2026-05-20.md`
- `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md`
- `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md`
- `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`
- `docs/AI_TEAM/17_DB_BACKUP_ROLLBACK_PLAN.md`
- `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`
- `docs/AI_TEAM/19_PRODUCTION_GO_NO_GO_2026-05-27.md`
- `docs/AI_TEAM/25_PRODUCTION_FILE_BACKUP_2026-05-27.md`
- `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`
- `docs/AI_TEAM/36_DB_PREFLIGHT_READONLY_SQL_2026-05-28.sql`
- `docs/AI_TEAM/50_FINDESK_PRODUCT_REBUILD_TZ_2026-06-02.md`
- `docs/AI_TEAM/91_UNIVERSAL_CASH_SESSION_ENGINE_2026-06-07.md`
- `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/SUMMARY.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/final_report_google_sheet.tsv`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/SUMMARY.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/participant_final_report_google_sheet.tsv`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/participant_final_report_detail.json`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_base_rights_rerun_2026-05-27/SUMMARY.md`
- File inventories/searches: `rg --files`, `rg --files docs`, `rg --files docs/AI_TEAM`, `rg --files docs/AI_TEAM/roles/04_qa_release_engineer/artifacts`, marker searches for production/deploy/import/final/draft/test/secret terms.

Findings:

1. Legacy documentation rejection

The old documentation corpus is large and must be treated as rejected product authority:

- `docs/` contains 351 files.
- `docs/AI_TEAM/` contains 318 files.
- These files include old product architecture, old sprint discipline, old role reports, old handoff prompts, old QA gates, old deploy decisions, old UX decisions, old finance trees, old cash/card interpretations, and old report/export concepts.

Rejected legacy documentation list:

- Root legacy project docs:
  - `README.md`
  - `docs/HANDOFF_2026-05-20.md`
  - `docs/HANDOFF_FULL_PRODUCT_2026-05-21.md`
  - `docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md`
  - `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md`
  - `docs/USER_MESSAGES_DIGEST_2026-05-22.md`
- Old finance/product architecture docs:
  - `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`
  - `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md`
  - `docs/KNOWLEDGE_LIVE_REPORTS_ADVANCES_GROUPS_2026-05-21.md`
  - `docs/IPHONE_NOTES_UX_ALGORITHMS_2026-05-21.md`
  - `docs/KNOWLEDGE_UX_CSS_GLASS_RULE_2026-05-21.md`
- Old step/checkpoint/predeploy docs:
  - `docs/STEP*.md`
  - `docs/CHECKPOINT*.md`
  - `docs/PREDEPLOY_CHECKLIST_2026-05-20.md`
  - `docs/LIVE_DEPLOY_2026-05-20.md`
- AI_TEAM corpus:
  - `docs/AI_TEAM/00_START_HERE.md` through `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md`
  - `docs/AI_TEAM/50_FINDESK_PRODUCT_REBUILD_TZ_2026-06-02.md`
  - `docs/AI_TEAM/91_UNIVERSAL_CASH_SESSION_ENGINE_2026-06-07.md`
  - `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md`
  - `docs/AI_TEAM/roles/**/*.md`
  - `docs/AI_TEAM/*PRODUCT_BIBLE*`
  - `docs/AI_TEAM/*PHASE*`
  - `docs/AI_TEAM/*SPRINT*`
  - `docs/AI_TEAM/*PRODUCTION*`
  - `docs/AI_TEAM/*DEPLOY*`
  - `docs/AI_TEAM/*HANDOFF*`

Reason for rejection: these documents describe old FinDesk / On the Go / Advanced / Field Combat / cash session / group report models. Some vocabulary overlaps with v2.0, but v2.0 authority is only `FinDesk v2.0/`. These legacy docs may be read only as archive context, import-source locator hints, deploy history, or risk evidence.

2. Candidate import source locations

Primary candidate archive/import fixtures found in repository:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/`
  - Best candidate set because `SUMMARY.md` marks participant-control as PASS.
  - Candidate data files:
    - `participant_final_report_detail.json`
    - `participant_closed_group_package.json`
    - `participant_final_report_google_sheet.tsv`
    - `participant_final_group_report.xls`
    - `participant_closed_group_package_print.html`
  - The JSON detail contains row-like trace fields such as `source_type`, `source_id`, `ledger_entry_id`, `capture_id`, `tape_id`, `date`, `owner`, `entry_type`, `money_type`, `amount`, `cash_before`, `cash_change`, `cash_after`, and `balance_after`.
  - Use only as an import traceability fixture or legacy archive sample. Do not inherit old totals/formulas/categories.

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/`
  - Contains `final_report_detail.json`, `closed_group_package.json`, `final_report_google_sheet.tsv`, `final_group_report.xls`, current exports, and print HTML.
  - `SUMMARY.md` marks the scenario BLOCKED due to a financial-control mismatch.
  - Use as negative fixture / discrepancy fixture only, not as accepted source truth.

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_base_rights_rerun_2026-05-27/`
  - `SUMMARY.md` marks permissions rerun PASS.
  - Files are useful for access-control archive evidence, not financial import truth.
  - `base_denied_*` XLS files should be excluded from finance import because they are denial artifacts.

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_recheck_20260528/`
  - Contains screenshots, result JSON, failure JSON, and QA runner.
  - Useful for proof/access/storage behavior evidence, not row import truth.

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_proof_links_20260528/`
  - Contains screenshots, proof-link result/failure JSON, and binary proof placeholders.
  - Useful for proof-link/archive-access evidence, not finance row import truth.

- `docs/assets/iphone-notes-reference-list.png` and `docs/assets/iphone-notes-reference-note.png`
  - Visual references only. Not import sources and not v2 UX authority.

No real external legacy archive folder matching `Buhgalteriya`, `ARHIV`, `Oplata kartoy`, or `Oplata moimi dengami` was found in this repository. The first true one-file import candidate is therefore blocked until Director provides an actual old Excel/Google Sheet source or a repo path to the historical accounting archive.

3. Deploy SQL classification

The old deploy SQL files are not import sources and must not become v2.0 schema truth.

Observed old tables/entities include:

- `users`, `auth_codes`, `sessions`, `user_settings`, `audit_log`
- `groups`, `group_members`, `group_invites`, `invite_share_events`
- `ledger_entries`, `entry_files`, `ledger_categories`
- `on_the_go_captures`, `on_the_go_files`, `on_the_go_tapes`, `on_the_go_sessions`
- `on_the_go_field_drafts`, `on_the_go_field_sync_ops`, `on_the_go_upload_states`
- `cash_advances`
- `group_messages`, `group_message_reads`
- `findesk_workspace_preferences`, `findesk_transfers`, `findesk_reports`, `findesk_report_items`, `findesk_protected_actions`
- `company_profiles`, `clients`, `proformas`, `proforma_items`

Classification:

- `deploy/auth_foundation.sql`, `app/db.php`, `app/config.php`, `app/config.local.example.php`: possible `INFRASTRUCTURE_DONOR` for PHP config/PDO/session pattern only.
- `deploy/run_sql_file.php`, `deploy/test_db_connection.php`, `deploy/README_MIGRATION.md`: possible `INFRASTRUCTURE_DONOR` for migration/test workflow only.
- `deploy/ledger_foundation.sql`, `deploy/categories_foundation.sql`, `deploy/findesk_phase2_foundation.sql`, `deploy/on_the_go_*.sql`, `deploy/advances_foundation.sql`: `UNSAFE_LEGACY_LOGIC` for v2.0 finance truth; may be kept only as old-schema archaeology and migration-risk evidence.

4. Exclude markers and final-version priority

Required v2.0 title exclude markers:

- `не отправлял`
- `не отправлено`
- `не готово`
- `не закончен`
- `не закончено`
- `не полный`
- `неполный`
- `черновик`
- `draft`
- `test`

Additional observed archive-risk markers for Sprint 01 triage:

- `failure`
- `blocked`
- `base_denied`
- `local`
- `preview_not_final`
- `current` when a matching `final` file exists
- `hotfix_recheck_failure`
- QA `SUMMARY.md` status `BLOCKED / P0`

Final-version priority rules for this repository:

- Prefer files with `final` over `current` for the same report/run.
- Prefer accepted/PASS recheck artifacts over BLOCKED/failure artifacts.
- Prefer machine-readable JSON/TSV with row traceability over screenshots/HTML print.
- Use `final_report_detail.json` and `closed_group_package.json` for traceability review before XLS.
- Keep `current_*` exports only as comparison/control artifacts.
- Never use final-looking old docs as product truth. `final`, `accepted`, `PASS`, and `production` mean old-system state only.
- For any future real Excel import, row date has priority over filename date.

5. Archive cleanup boundaries

- Do not delete excluded files. Record the include decision and reason.
- Do not move or rewrite `docs/`, `docs/AI_TEAM/`, deploy SQL, artifacts, screenshots, or reports during Sprint 01.
- Do not normalize old docs into v2 docs.
- Do not import all years or all artifacts before one-file import acceptance passes.
- Do not use screenshots as financial data sources.
- Do not import `failure`, `blocked`, `base_denied`, `draft`, `test`, or `preview_not_final` files into accepted financial entries.
- Do not treat old `ledger_entries`, `cash_advances`, `on_the_go_*`, `findesk_*`, report package JSON, or old categories as v2 entities.
- Preserve original source path, file name, sheet/report name, row number if available, raw row JSON, normalized entry id, and parse status.
- If cleanup is later approved, use quarantine/archive metadata; do not destructive-delete history.

6. Production access clues for backend/security inventory

These clues should be handed to Backend/Security inventory without secret values:

- Production domain: `https://finance.brkovic.ltd`.
- Live app path: `/app.php`.
- Legacy server path/reference: `/home/brkovic/finance.brkovic.ltd`.
- Production FTP tree referenced as `/finance.brkovic.ltd`.
- Production deploy method in old docs: controlled narrow runtime package uploaded over FTP; `deploy/on_the_go_sessions_runtime.sql` applied separately as DB migration input.
- FTP port `21` was reported reachable from the old deployment environment.
- DB port `3306` was reported not reachable externally from that old environment.
- Production DB engine reported in old deploy docs: MariaDB `11.4.10-MariaDB-cll-lve-log`.
- Runtime config pattern:
  - base `app/config.php`
  - private override `app/config.local.php`
  - example file `app/config.local.example.php`
  - DB via PDO MySQL DSN from `db_host`, `db_name`, `db_user`, `db_pass`
  - SMTP config under `mail`
  - OpenAI config placeholders under `openai`
- Private runtime paths:
  - `storage/`
  - `app/config.local.php`
  - production backups
  - production DB dumps
- `.htaccess` denies direct `/app`, `/storage`, `/deploy`, and `/cron` directory access, disables indexes, and rewrites public assets/service worker/manifest/robots/sitemap/favicon.
- Old deploy docs reference file/storage backups, DB backups, checksums, release package IDs, and smoke IDs. Treat these as audit references only; do not copy backup contents into v2 reports.

Missing access/security items:

- Hosting provider not identified from repo evidence.
- Control panel URL not identified.
- Account owner not identified.
- Production server IP not recorded in reviewed files.
- SSH/SFTP host and port not confirmed.
- Domain registrar/DNS provider not identified.
- SSL certificate owner/renewal path not identified.
- Real credential storage location not confirmed.
- Production `app/config.local.php` is intentionally absent and must remain absent from repo.

7. Main risks

- Final-looking legacy docs can accidentally override v2.0 because they are polished, detailed, and later-dated. This is the highest Sprint 01 archive risk.
- Old artifacts contain test account identifiers and financial fixture data. Import experiments must run in an isolated test context and redact personal identifiers in public reports.
- Old `final_*` report artifacts may have internally inconsistent formulas; one production multi-employee artifact is explicitly BLOCKED. It is useful as a negative fixture, not as truth.
- Old deploy SQL is behind/evolved against production state per legacy handoff. It must not be applied or copied into v2.0 schema work.
- Old Cash/Card docs conflict with v2.0 in places: v2.0 Cash/Card are funding flows, and card-to-cash is two valid entries, not an error or neutral transfer.
- No real historical accounting archive was found in repo, so Sprint 05 one-file import cannot start from a true old Excel source yet.

8. Blockers

- Missing actual legacy Excel/Google Sheet source archive for first one-file import.
- Missing secure production access inventory items: hosting/control panel, SSH/SFTP, registrar/DNS, credential owner/storage.
- No Director approval for full archive scan.
- Binary XLS files were inventoried as candidates, but their workbook internals were not parsed in this Sprint 01 report.
- No tests were run because this task is inventory/report-only and explicitly forbids implementation/import code.

Changes made:
- Created this report only.
- No implementation code changed.
- No SQL changed.
- No legacy docs or artifacts changed.
- No import/archive files changed.

Risks:
- The old documentation corpus is broad enough that future agents may cite it accidentally. Director should explicitly tell Sprint 02+ agents to ignore `docs/` and `docs/AI_TEAM/` as product authority.
- Accepted/PASS production artifacts may still encode old product semantics. They are useful for import traceability testing, not for formulas.
- Artifact JSON/TSV may expose personal/test identifiers. Any future importer test should redact owner/email fields in public output.
- Production/deploy clues are incomplete without secure external access confirmation.

Recommended next action:
- Director should accept `docs/` and `docs/AI_TEAM/` as rejected legacy documentation corpus for v2.0 truth.
- Backend/Security inventory agent should extract infrastructure-only details from `README.md`, `app/db.php`, `app/config.local.example.php`, `.htaccess`, `deploy/README_MIGRATION.md`, `docs/AI_TEAM/17_DB_BACKUP_ROLLBACK_PLAN.md`, `docs/AI_TEAM/19_PRODUCTION_GO_NO_GO_2026-05-27.md`, `docs/AI_TEAM/25_PRODUCTION_FILE_BACKUP_2026-05-27.md`, and `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`.
- Director should provide or locate one real old Excel/Google Sheet accounting source for Sprint 05 one-file import acceptance.
- Future import work should start with `production_hotfix_recheck_2026-05-27` JSON/TSV as a traceability fixture and `production_multi_employee_2026-05-27` as a negative/discrepancy fixture, not as product truth.
- Add an explicit Sprint 01 handoff line: old docs are archive-only; old report artifacts are import fixtures only; deploy SQL is infrastructure/schema archaeology only.

Files touched:
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/06-LEGACY-IMPORT-ARCHIVE-REPORT.md`
