# Deploy Readiness Checklist - Limited Scanner/UX/Backend Release

Date: 2026-05-28
Role: QA/Deploy Release Engineer FinDesk
Status: BLOCKED / NO-GO for production deploy until P0 items below are closed.

## Candidate 34 QA Update

Date: 2026-05-28
Candidate: `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`

Candidate 34 runtime file list includes the two files that must not be missed in this release:

- `app/groups.php`;
- `public/assets/i18n.js`.

Checklist correction made:

- the selected file bundle below now includes `app/groups.php`;
- the selected file bundle below now includes `public/assets/i18n.js`;
- PHP lint targets now include `app/groups.php`;
- local and production smoke explicitly include `group_delete` soft archive and asset/version checks.

QA decision for candidate 34:

- local PASS remains accepted from run `20260528LOCALLEFTOVERS01`;
- no additional local recheck is required now if the candidate 34 file bundle remains unchanged;
- next QA execution is required after DB preflight, backup, upload, and SQL/application on production;
- real-device scanner/PWA camera QA remains separate and required before device-ready scanner wording.

## Scope Boundary

This checklist prepares the next limited production release after local Receipt Scanner, Live Report UX, and backend proof-chain work.

QA changed no runtime code in this task:

- no `app/` code changes;
- no `public/` code changes;
- no `scripts/` code changes;
- no `deploy/` SQL changes.

Observed baseline:

- `HEAD=72b38e6`;
- `origin/main=72b38e6`;
- working tree is dirty as expected;
- `php` CLI is not available in the current shell;
- local server `http://127.0.0.1:18889/api.php?action=current_user` returned `{"ok":true,"user":null}`;
- `node --check public/assets/app.js` passed;
- `node --check public/assets/i18n.js` passed;
- `git diff --check` passed for the selected local scanner/UX/backend runtime files.

## Already Closed

### 1. Business MVP Product Gate

Status: closed for the checked new-data path.

Evidence:

- Chief Auditor full business-MVP product gate approved on 2026-05-27;
- residual surface QA run `20260527968710`;
- accepted package anchor `group_id=222`, `report_id=454`;
- prior Field Combat no-data-loss and closed group package gates are recorded in Chief Auditor files.

Boundary:

- this is product readiness, not automatic production deploy approval;
- production deploy still requires package selection, DB preflight, backup, rollback, and production smoke.

### 2. Production Base Rights

Status: closed by QA rerun after backend hotfix.

Evidence:

- run stamp `20260527212947`;
- production fixture `group_id=20`, `report_id=194`;
- base employee `user_id=59`;
- `message_unread` returned HTTP `200`, `ok=true`, `unread_count=0`;
- base employee could use personal FinDesk and own operational capture;
- base employee remained denied from group reports, final packages/exports, group messages, money management, role management, and other members' money.

### 3. Local Scanner File-Input Gate

Status: closed for local browser/HTTP file-input path only.

Evidence:

- QA run `20260528RSQA01`;
- tested viewports: `390x844`, `820x1180`, `1440x900`;
- scanner artifacts stored as linked `scanner_original` and `scanner_cleaned_pdf` in one `proof_bundle_id`;
- original/PDF upload replay and signed sync replay were idempotent;
- final package recheck `group_id=226`, `report_id=516`;
- Chief Auditor approved only the local browser/HTTP file-input scanner slice.

Boundary:

- physical camera capture is not accepted yet;
- installed iPhone/Android PWA behavior is not accepted yet;
- production device behavior is not accepted yet.

## P0 Blockers Before Next Production Deploy

### P0-1. Real-Device Scanner QA

Owner: QA Release Engineer / CEO physical device check.

Required before scanner is production-ready:

- iPhone Safari browser mode;
- iPhone installed PWA from home screen;
- Android Chrome browser mode;
- Android installed PWA if available, or explicit limitation recorded;
- physical camera capture or device file picker return;
- finger crop handle usability;
- attach cleaned PDF;
- save, refresh, reopen;
- one money row only after retry;
- final package contains both original source and cleaned PDF.

Evidence card:

- `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`

### P0-2. PHP CLI And Smoke Availability

Owner: Deploy Owner / QA Release Engineer.

Current shell result:

- `php` is not found.

Required before deploy:

- record where PHP CLI is available: local, CI, or production preflight host;
- run `php -l` for selected changed PHP files;
- run or formally replace `php scripts/local-smoke.php http://127.0.0.1:18889`;
- if PHP CLI is unavailable everywhere, record the exact approved HTTP/API replacement smoke before upload.

Minimum PHP lint targets if included in bundle:

- `app/on_the_go.php`;
- `app/ledger.php`;
- `app/groups.php`;
- `public/api.php`;
- `public/app.php`.

### P0-3. DB Migration Preflight For Scanner Columns

Owner: Database Migration Owner / Backend Data Engineer.

Required scanner columns must be present or safely added before runtime files that depend on them are deployed:

- `on_the_go_files.proof_role`;
- `on_the_go_files.proof_bundle_id`;
- `on_the_go_files.source_file_id`;
- `on_the_go_files.file_hash_sha256`;
- `on_the_go_files.metadata_json`;
- `on_the_go_upload_states.proof_role`;
- `on_the_go_upload_states.proof_bundle_id`;
- `on_the_go_upload_states.file_hash_sha256`;
- `on_the_go_upload_states.metadata_json`.

Preflight must record:

- current production column state before SQL;
- exact SQL artifact used;
- whether each statement is already satisfied, applied, or skipped;
- no destructive table rebuild;
- no data loss;
- post-SQL verification result.

Candidate SQL artifacts to review:

- `deploy/on_the_go_foundation.sql`;
- `deploy/on_the_go_sessions_runtime.sql`.

### P0-4. Selected File Bundle

Owner: Project Director / Deploy Owner.

The next deploy must name the exact uploaded files before upload.

Candidate runtime files for this limited release:

- `app/groups.php`;
- `app/on_the_go.php`;
- `app/ledger.php`;
- `public/api.php`;
- `public/app.php`;
- `public/assets/app.js`;
- `public/assets/app.css`;
- `public/assets/i18n.js`;
- `deploy/on_the_go_foundation.sql`;
- `deploy/on_the_go_sessions_runtime.sql`.

Candidate verification-only file:

- `scripts/local-smoke.php`.

Do not upload by broad dirty-tree copy without an explicit file list. Do not upload docs, test artifacts, local reset helpers, temporary files, or unrelated SEO/brand changes unless Deploy Owner explicitly adds them to the release bundle.

### P0-5. Backup And Rollback

Owner: Deploy Owner / Database Migration Owner.

Required before upload:

- production files backup reference;
- production database backup reference;
- rollback owner named;
- rollback procedure recorded without secrets;
- rollback verification checklist: app loads, login works, current report opens, known closed report opens, scanner proof endpoints do not fatal;
- migration rollback/restore approach documented.

## Local Checks Required After Frontend/Backend Branches

Run after the selected local branch set is complete and before production packaging:

1. Baseline:
   - `git status --short`;
   - `git rev-parse --short HEAD`;
   - `git rev-parse --short origin/main`.
2. Whitespace/syntax:
   - `git diff --check -- <selected files>`;
   - `node --check public/assets/app.js`;
   - `node --check public/assets/i18n.js`;
   - `php -l app/on_the_go.php`;
   - `php -l app/ledger.php`;
   - `php -l app/groups.php`;
   - `php -l public/api.php`;
   - `php -l public/app.php`.
3. Local HTTP health:
   - `curl http://127.0.0.1:18889/api.php?action=current_user`;
   - open `/app.php` and confirm HTTP `200`.
4. Local functional smoke:
   - `php scripts/local-smoke.php http://127.0.0.1:18889`, or approved HTTP/API replacement if PHP CLI remains blocked.
5. Scanner proof-chain smoke:
   - create one Live Report money row;
   - attach scanner original and cleaned PDF in one proof bundle;
   - retry upload with same `client_upload_id`;
   - confirm idempotent response and no duplicate money row;
   - confirm file list returns exactly `scanner_original` and `scanner_cleaned_pdf`;
   - finalize a group report and confirm package proof metadata keeps source-to-derivative link.
6. Browser/responsive QA:
   - mobile `390x844`;
   - tablet `820x1180`;
   - desktop `1440x900`;
   - no scanner modal overlap;
   - Live Report note area remains usable;
   - intermediate share/print/export windows have a visible exit path.
7. Group soft archive QA:
   - admin creates a test group;
   - base employee joins by invite;
   - base employee `group_delete` returns `admin_required`;
   - admin `group_delete` returns soft archive status;
   - evidence counters remain preserved;
   - repeated admin delete returns `already_deleted=true`;
   - archived group disappears from active group list.
8. Asset/version checks:
   - `public/assets/app.css` loads with the candidate version query;
   - `public/assets/app.js` loads with the candidate version query;
   - `public/assets/i18n.js` loads with the candidate version query;
   - login fallback wording is current, not old sign-in copy.
9. Real-device QA:
   - execute `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`.

## Production Smoke Required After Limited Deploy

Run only after P0 preflight, backup, upload, SQL, and rollback readiness are recorded.

### Core HTTP Endpoints

- `GET /`;
- `GET /app.php`;
- `GET /manifest.webmanifest`;
- `GET /service-worker.js`;
- selected CSS/JS/icon assets;
- `GET /api.php?action=current_user`.

### Auth And Entry

- login/code delivery screen shows current FinDesk wording, not old project naming;
- authenticated `current_user` returns expected identity;
- logout or session-expiry does not expose previous user data.

### Asset Version And Cache Guard

- production `app.php` references the candidate versions of `app.css`, `app.js`, and `i18n.js`;
- each referenced asset returns HTTP `200`;
- Safari/Chrome hard refresh or private window does not show old login fallback wording.

### Scanner / Field Capture API Path

Use ordinary UI where possible and authenticated API observation where needed:

- `on_the_go_field_draft_save`;
- `on_the_go_field_recover`;
- `on_the_go_signed_sync`;
- `on_the_go_proof_state_begin`;
- `on_the_go_proof_state_fail` if recoverable failure can be exercised;
- `on_the_go_proof_state_list`;
- `on_the_go_upload_file`;
- `on_the_go_file_list`.

Must pass:

- one money row after save/retry/refresh;
- original source and cleaned PDF in one proof bundle;
- cleaned PDF links to original source;
- both files have hashes;
- retry is idempotent;
- no silent submit/include/finalize.

### Current And Historical Report Path

- current/open period opens and is labeled as current;
- current export remains current-period truth;
- historical final report list opens;
- final report detail opens by explicit `report_id`;
- final report export is reachable by `report_id`;
- current period activity does not mutate selected historical report.

### Closed Group Report Package

- `ledger_group_final_report_package` opens by explicit `report_id`;
- package includes summary, participant reports, captures/proofs, money rows, accountable state, messages/audit refs;
- authorized proof download works;
- unauthorized proof access is denied if safe permission fixture is available;
- print/PDF path is reachable;
- package exposes both `scanner_original` and `scanner_cleaned_pdf` for scanner fixture.

### Rights And Group Isolation

- admin/manager can access group package and exports;
- default base employee remains restricted to own operational capture/self-control in group context;
- base employee cannot access group final reports/package/export, group messages, money management, role management, or other participants' money;
- base employee can still use personal FinDesk normally outside group data.

### Group Delete / Test Group Soft Archive

- admin creates a production smoke test group;
- base employee joins as `access_level=base`;
- base employee cannot delete/archive the group and receives `admin_required`;
- admin soft-archives the test group;
- ledger/live-report/proof/final-report evidence counters are not deleted;
- repeated admin archive is idempotent and returns `already_deleted=true`;
- archived test group is not shown in normal active `group_list`.

### Final Production Acceptance Output

Production smoke report must record:

- production URL;
- deploy timestamp and timezone;
- selected file bundle;
- DB backup reference;
- files backup reference;
- SQL preflight/apply result;
- rollback owner and rollback reference;
- smoke user/account policy;
- group_id, report_id, tape/capture ids, proof file ids, and proof bundle id for scanner scenario;
- PASS/FAIL/BLOCKED for every smoke section above.

## Go / No-Go

Current decision: NO-GO for the next production deploy until:

- real-device scanner QA is complete or CEO explicitly accepts a limited release without camera/PWA scanner claim;
- PHP CLI/smoke replacement is documented;
- scanner DB migration preflight is complete;
- selected file bundle is frozen;
- production DB/files backup and rollback plan are recorded.
