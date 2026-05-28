# Production Deploy Report

Date: 2026-05-27

Owner: Project Director / Deploy Owner

Status: deployed; production HTTP/API smoke passed.

## Final Runtime Artifact

Release id:

- `findesk-mvp-runtime-20260527T192800Z`

Artifact:

- `backups/findesk-mvp-runtime-20260527T192800Z/findesk-mvp-runtime-20260527T192800Z.tar.gz`

Checksum:

- `c4e1a79d1bd8091aa21bd7ac21c685c95f1389d62bf572d0ba98b481ccb4f7f4`

Files:

- `31` files, listed in `backups/findesk-mvp-runtime-20260527T192800Z/file-list.txt`.

The final artifact supersedes `findesk-mvp-runtime-20260527T185423Z`.

## Backup Evidence

Production files/storage backup:

- backup id: `prod-files-before-mvp-20260527T185902Z`;
- archive: `backups/prod-files-before-mvp-20260527T185902Z.tgz`;
- checksum: `b095d4c6c8cf35ac0fbc76657fd7653d4757596b58944c81a13325122b1c8823`.

Production DB backup before runtime migration:

- archive: `backups/prod-db-before-mvp-20260527T191800Z/findesk-db-before-mvp-20260527T191800Z.sql.gz`;
- checksum: `97141a46924adcebd4fdcaeeecf87429d089a5147558c4ad2e72aa535b97cb3f`;
- gzip/checksum verification: passed.

Production DB backup before schema hardening:

- archive: `backups/prod-db-before-mvp-hardening-20260527T192400Z/findesk-db-before-hardening-20260527T192400Z.sql.gz`;
- checksum: `352face59681afaae1d278bc8a02140991473efbff1f78256f39d7bbbe87f50f`;
- gzip/checksum verification: passed.

## DB Migration Evidence

Production DB engine:

- `11.4.10-MariaDB-cll-lve-log`

Initial preflight:

- syntax probe for `ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS`: passed;
- missing before migration: `on_the_go_field_drafts`, `on_the_go_field_sync_ops`, `on_the_go_upload_states`, `on_the_go_tapes.stream_type`, `on_the_go_tapes.idx_otr_tapes_stream`.

Applied migrations:

- `deploy/advances_foundation.sql`;
- `deploy/on_the_go_sessions_runtime.sql`.

Hardening finding during production smoke:

- `on_the_go_tapes.archived_at` was required by the deployed code and missing from the older production table.

Hardening action:

- `deploy/on_the_go_sessions_runtime.sql` was extended idempotently to cover the full runtime `on_the_go_tapes` column set;
- the updated migration was applied;
- final preflight returned no missing tables, columns, or indexes.

## Uploaded Runtime Scope

Uploaded:

- the final narrow runtime package, not the full dirty worktree;
- `.htaccess` routing supplement for `/favicon.ico`;
- updated `app/auth.php` with no-store JSON API headers;
- updated `deploy/on_the_go_sessions_runtime.sql` with schema hardening.

Not uploaded:

- `docs/`;
- `scripts/`;
- `public/reset-local.php`;
- `test-results/`;
- local backups;
- local config files.

Temporary DB-gate script:

- uploaded only for backup/preflight/migration;
- removed after use;
- production check returned `404`.

## Production Smoke Evidence

Technical HTTP/PWA/private-boundary smoke:

- `/`: `200`, canonical present, no-store headers present;
- `/app.php`: `200`, `noindex,nofollow`, no-store headers present;
- `/api.php?action=current_user`: `200`, JSON ok, no-store headers present;
- `/robots.txt`: `200`, private app/API/storage paths disallowed;
- `/sitemap.xml`: `200`, XML valid, public root only;
- `/manifest.webmanifest`: `200`, valid JSON, `start_url=/app.php`;
- `/service-worker.js`: `200`, non-empty uploaded script;
- key JS/CSS/brand/PWA assets: `200`;
- `/storage/`: `403`;
- `/deploy/`: `403`;
- temporary DB-gate script: `404`.

Business MVP API smoke:

- smoke id: `20260527192655`;
- smoke user: `prod-mvp-smoke-20260527192655@example.test`;
- user id: `5`;
- group id: `4`;
- ledger entry id: `11`;
- Field Combat draft id: `2`;
- proof states: `1`;
- included Live Report tape id: `6`;
- next tape id: `7`;
- message id: `4`;
- proforma id: `5`;
- final report id: `20`;
- finalized cards: `1`;
- package type: `group_final_report`.

Passed production paths:

- login-code authentication;
- current user/session bootstrap;
- group creation;
- current-period ledger income and export;
- Field Combat durable draft save and recover;
- proof failed/retry state persistence;
- included Live Report card;
- group message send/list;
- Business Desk proforma create/get;
- final report creation by `report_id`;
- closed group report package by `report_id`;
- final report export reachability;
- open-period carryover endpoint reachability.

## Remaining Non-Blocking Follow-Up

- Full browser visual matrix on production was not executed from this shell because no browser automation runtime is available here.
- Product mobile/tablet/desktop QA remains covered by prior role evidence; production HTTP/API smoke confirms the deployed files and endpoints are live.
- Dedicated smoke data remains in production under the smoke user/group ids listed above.
