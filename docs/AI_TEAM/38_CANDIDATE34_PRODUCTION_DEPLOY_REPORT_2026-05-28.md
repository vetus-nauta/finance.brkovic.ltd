# Candidate 34 Production Deploy Report

Date: 2026-05-28
Owner: Project Director / Deploy Owner
Status: deployed; production smoke passed

## Runtime Scope

Uploaded as a limited scanner/UX/backend package:

- `app/auth.php`
- `app/advances.php`
- `app/groups.php`
- `app/ledger.php`
- `app/messages.php`
- `app/on_the_go.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`

Not uploaded:

- full dirty worktree;
- local-only `public/reset-local.php`;
- local scripts except deploy operator tools kept in local backups;
- docs to production;
- test-results.

## Backup Evidence

Production DB backup before scanner/runtime SQL:

- archive: `backups/prod-db-before-candidate34-20260528T135737Z/findesk-db-before-candidate34-20260528T135737Z.sql.gz`;
- checksum: `c439ae9b49e3394fcd18272134aae22abcd1bc37d53954e0af8048e58e92d738`;
- gzip verification: passed;
- stored evidence: `preflight-before.json`, `apply-result.json`, `preflight-after.json`, `production-smoke.json`.

Production files/storage backup before upload:

- backup id: `prod-files-before-candidate34-20260528T135752Z`;
- archive: `backups/prod-files-before-candidate34-20260528T135752Z.tgz`;
- checksum: `5097fbc9beb0affbf109d82a82c299de26985fc558801ece66ab4ad4d51d1c26`;
- files downloaded: `134`;
- bytes downloaded before archive: `14757250`;
- download errors: `0`.

Rollback source:

- restore runtime files from the file backup archive;
- restore DB from the DB backup archive if scanner/runtime SQL must be rolled back.

## DB Preflight And SQL

Production DB:

- engine: `11.4.10-MariaDB-cll-lve-log`;
- version comment: `MariaDB Server`;
- syntax probe for `ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS`: passed.

Before SQL, missing runtime scanner columns/indexes:

- `on_the_go_files.proof_role`;
- `on_the_go_files.proof_bundle_id`;
- `on_the_go_files.source_file_id`;
- `on_the_go_files.file_hash_sha256`;
- `on_the_go_files.metadata_json`;
- `on_the_go_upload_states.proof_role`;
- `on_the_go_upload_states.proof_bundle_id`;
- `on_the_go_upload_states.file_hash_sha256`;
- `on_the_go_upload_states.metadata_json`;
- `on_the_go_files.idx_otr_files_bundle`;
- `on_the_go_files.idx_otr_files_role`.

Applied:

- `deploy/on_the_go_sessions_runtime.sql`.

After SQL:

- missing tables: none;
- missing columns: none;
- missing indexes: none.

## Production Smoke

Public/version smoke:

- `https://finance.brkovic.ltd/app.php`: HTTP `200`;
- login fallback H1 now contains `FinDesk access code`;
- old fallback H1 `FinDesk sign-in code` is absent;
- asset version `20260528-frontend-residual1` is present;
- `/api.php?action=current_user` returns `{"ok":true,"user":null}`;
- `assets/app.js` and `assets/app.css` return HTTP `200`.

Business/API smoke:

- run id: `prod-candidate34-20260528140302`;
- admin user id: `63`;
- member user id: `64`;
- soft-delete test group id: `23`;
- package group id: `24`;
- tape id: `82`;
- capture id: `143`;
- final report id: `218`;
- scanner bundle id: `c34-scanner-20260528140302`.

Passed checks:

- login-code authentication through production API;
- base member denied from group delete with `admin_required`;
- admin `group_delete` soft-archives a test group;
- repeated `group_delete` is idempotent with `already_deleted`;
- Field Combat draft save/recover survives server roundtrip;
- proof pending/fail/retry state persists;
- signed sync is idempotent by `client_operation_id`;
- scanner original and cleaned PDF are stored in one `proof_bundle_id`;
- scanner cleaned PDF retry is idempotent by `client_upload_id`;
- current Google/Excel export contains `1000.00 / 600.00`;
- finalized historical report detail contains `1000.00 / 600.00 / 400.00`;
- historical Excel export by `report_id` returns the finalized report;
- closed group final report package includes scanner original and cleaned PDF proof chain.

Smoke note:

- production SMTP rejected `example.test`, so the smoke used the temporary protected DB-gate to read the locally logged code and verify the login-code path;
- this proves auth-code creation/verification, not real mailbox deliverability.

## Temporary Gate Cleanup

Temporary production DB-gate:

- uploaded only for DB backup/preflight/SQL/smoke support;
- removed after smoke;
- post-removal HTTP check returned `404`.

## Remaining Limits

- Real-device scanner/PWA camera behavior is still not claimed as device-ready.
- Production browser visual matrix was not run from this shell.
- Candidate 34 is a limited production release of already local-passed scanner file-input, UX leftovers, and backend soft-delete/runtime support.
- Smoke test data remains in production under the ids listed above for audit traceability.
