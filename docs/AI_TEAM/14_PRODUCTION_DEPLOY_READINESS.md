# Production Deploy Readiness

Date: 2026-05-27

Owner: Backend/Data for deploy readiness plan. Project Director remains production decision owner.

Status: BLOCKED for production upload. Backend/API business-MVP product readiness is PASS, but production deploy readiness is blocked until deploy package selection, database migration compatibility/application, backup/rollback, and production smoke are completed by explicit Project Director/CEO step.

No production action was executed in this pass. No database change was made. No application code was changed. No credentials are included in this document.

## Inputs Read

- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `git status --short`

Baseline from the tree:

- `HEAD=72b38e6`
- `origin/main=72b38e6`
- working tree is dirty with broad modified and untracked files
- production deploy must not upload the whole tree blindly

## Deploy Decision Required

Project Director must choose one deploy mode before any upload:

1. Full dirty-tree deploy bundle.
   - Only valid if CEO/Project Director explicitly accepts all current dirty-tree runtime, asset, support, and advanced/AI surface changes as production scope.
   - This mode still requires database migration, backups, rollback copy, and production smoke.
2. Narrow MVP runtime bundle.
   - Preferred if the goal is only the accepted business-MVP money path.
   - This mode requires dependency-closure review because current runtime files are interdependent.
   - Do not use the older narrow list alone without checking the dependencies below.

## Backend/API/Runtime SQL Deploy Candidates

These are candidates for Project Director/deploy-owner review, not approval to upload.

Required or likely required backend/API candidates for the accepted MVP backend path:

- `public/api.php`
  - Routes the MVP API actions.
  - Current file also adds `audit_list` and `ai_analysis_run`.
  - Current file has `require_once __DIR__ . '/../app/ai.php';`; deploying this `public/api.php` without `app/ai.php` can fail before routing any request.
- `app/auth.php`
  - Loaded by `public/api.php`.
  - Current dirty diff adds audit helper APIs and local-dev code-return behavior guarded by local host/app URL detection.
  - Must be reviewed as an auth-surface change before production.
- `app/groups.php`
  - Loaded by `public/api.php`.
  - Current dirty diff adds audit write on group member access update.
- `app/on_the_go.php`
  - Field Combat durable draft/recovery, idempotent sync, proof state, cash/card stream logic, Live Report card submit/include/archive, session handling, and proof upload state.
  - Depends on `on_the_go_sessions`, `on_the_go_captures.session_id`, and `on_the_go_tapes.stream_type`.
- `app/ledger.php`
  - Current/historical report separation, group finalization, final report list/detail/export, `ledger_group_final_report_package`, `ledger_group_final_report_proof_download`, and `ledger_group_open_received_funds`.
  - Requires `app/on_the_go.php` in the current file.
- `app/advances.php`
  - Accountable/advance detail, totals, accept/unaccept/return/cancel behavior used by the closed package and residual MVP accountable path.
  - Uses On the Go session/tape state and audit helpers.
- `app/ai.php`
  - Untracked file.
  - Not business-MVP core, but it is required by the current `public/api.php`.
  - If the current `public/api.php` is deployed unchanged, this file must either be deployed as a dependency or the deploy package must use a reviewed API file that does not require it. Do not silently expose this as MVP scope.
- `deploy/on_the_go_sessions_runtime.sql`
  - Runtime migration to apply or prove already applied before PHP upload.
  - Do not treat this as a web file upload. Treat it as a controlled database migration.

Verification/support candidate, not production app runtime:

- `scripts/local-smoke.php`
  - Useful for staging/local smoke if CLI PHP is available.
  - Do not upload into the production web surface unless the deploy owner explicitly approves its location and access.

Frontend/runtime files from the accepted MVP path, for Frontend UX Engineer / Project Director selection:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`

Additional modified public runtime/assets that must not be included blindly:

- `public/index.php`
- `public/service-worker.js`
- `public/favicon.ico`
- `public/assets/apple-touch-icon.png`
- `public/assets/favicon-16x16.png`
- `public/assets/favicon-32x32.png`
- `public/assets/favicon-48x48.png`
- `public/assets/favicon-64x64.png`
- `public/assets/favicon.ico`
- `public/assets/icon-180.png`
- `public/assets/icon-192.png`
- `public/assets/icon-512.png`
- `public/assets/icon-maskable-512.png`
- `public/assets/brand-logo.png`
- `public/assets/brand-mark.png`
- `public/assets/brand-og.png`

## Database Migration Checklist

P0 rule: apply or prove required schema before deploying PHP that assumes it.

Primary MVP runtime migration:

- `deploy/on_the_go_sessions_runtime.sql`

Required compatibility checks before applying:

1. Record database engine and version with `SELECT VERSION();`.
2. Verify whether the engine supports:
   - `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`
   - `ALTER TABLE ... ADD KEY IF NOT EXISTS`
3. If either syntax is unsupported, stop. Assign DB owner/Backend Data to produce an engine-compatible idempotent migration before upload.
4. Confirm no credential values are written into deploy notes, command history snippets, screenshots, or smoke evidence.

Required schema checks before PHP upload:

- Table `on_the_go_tapes` exists.
- Table `on_the_go_sessions` exists.
- Table `on_the_go_captures` exists.
- Table `on_the_go_files` exists.
- Table `on_the_go_field_drafts` exists or will be created by the runtime migration/application path.
- Table `on_the_go_field_sync_ops` exists or will be created by the runtime migration/application path.
- Table `on_the_go_upload_states` exists or will be created by the runtime migration/application path.
- Column `on_the_go_captures.tape_id` exists.
- Column `on_the_go_captures.session_id` exists.
- Column `on_the_go_tapes.group_id` exists.
- Column `on_the_go_tapes.advance_id` exists.
- Column `on_the_go_tapes.stream_type` exists.
- Column `on_the_go_tapes.submitted_at` exists.
- Column `on_the_go_tapes.actual_remaining` exists.
- Column `on_the_go_tapes.difference_amount` exists.
- Index `idx_otr_captures_tape` exists or equivalent exists.
- Index `idx_otr_captures_session` exists or equivalent exists.
- Index `idx_otr_tapes_stream` exists or equivalent exists.

Foundation schema prerequisites to verify on production before this MVP deploy:

- `deploy/auth_foundation.sql` equivalent already applied for `users`, `sessions`, `auth_codes`, `audit_log`, and `user_settings`.
- `deploy/groups_foundation.sql` and `deploy/group_access_levels.sql` equivalents already applied for group membership and permissions.
- `deploy/ledger_foundation.sql` equivalent already applied for ledger tables and `ledger_entries.group_id`.
- `deploy/on_the_go_foundation.sql` equivalent already applied for `on_the_go_captures` and `on_the_go_files`.
- `deploy/advances_foundation.sql` equivalent already applied for `cash_advances` and the On the Go advance columns.
- `deploy/messages_foundation.sql` equivalent already applied for group messages.
- `deploy/business_desk_foundation.sql` equivalent already applied if residual Business Desk/proforma is in production smoke scope.

Application order:

1. Back up production database and production files first.
2. Confirm engine compatibility.
3. Apply required SQL in staging or a production-equivalent environment first.
4. Run schema verification queries.
5. Apply production SQL only after Project Director/CEO deploy step.
6. Upload selected PHP/assets only after schema is ready.
7. Run production smoke.

## Backup Checklist

Before upload or database migration:

- Record exact deploy decision, selected file list, approver, timestamp, and target environment.
- Back up current production application files for every selected path.
- Back up production database with a logical dump appropriate to the engine and data size.
- Back up production uploaded-document storage, especially:
  - On the Go proof files.
  - Ledger entry files.
  - Group final report package proof copies under report-owned storage.
- Preserve current production config separately without copying credentials into docs.
- Record backup artifact names/locations in deploy notes without secrets.
- Verify backups are readable and non-empty before proceeding.
- Capture checksums or file sizes for rollback confidence.

## Rollback Checklist

Rollback owner must be named before upload.

Preferred rollback order if smoke fails:

1. Stop further user writes or put the app into the agreed maintenance mode if available.
2. Restore previous production files from the file backup.
3. Clear PHP/opcache if enabled.
4. If public assets or `public/service-worker.js` were included, force browser-cache/service-worker rollback behavior per Frontend/Deploy owner instructions.
5. Rerun minimum app/session/API smoke.
6. Decide database rollback separately.

Database rollback rule:

- Additive schema changes may be left in place if old code tolerates them.
- Restoring the full database can erase production writes made after the backup. Do it only with explicit Project Director/CEO approval and a written data-loss decision.
- If new MVP data was written during failed smoke, record affected smoke group/report ids before any database restore.

## Production Smoke Checklist

Run only after explicit Project Director/CEO production smoke step. Use an approved smoke group/test account and avoid real client finance data.

Minimum backend/API smoke after upload:

1. App loads over the production URL.
2. `current_user`/session check works for anonymous and authenticated states as applicable.
3. Login flow works without exposing dev codes on production.
4. Field Combat mobile entry opens.
5. `on_the_go_field_draft_save` persists raw draft text, parsed rows, selected group, stream, tape id, and session id.
6. `on_the_go_field_recover` restores the same draft after refresh/navigation.
7. `on_the_go_signed_sync` with a repeated `client_operation_id` is idempotent and does not duplicate money rows.
8. Proof state APIs show pending/failed/retry/uploaded states:
   - `on_the_go_proof_state_begin`
   - `on_the_go_proof_state_fail`
   - `on_the_go_proof_state_list`
9. Proof upload retry attaches proof to the original saved capture and does not create duplicate money rows.
10. Cash stream keeps physical-cash effects; card stream stays noncash and does not reduce physical cash.
11. Current-period export opens:
   - `ledger_group_google_sheet`
   - `ledger_group_excel`
12. Create or use an approved smoke closed report and verify:
   - `ledger_group_final_report_list`
   - `ledger_group_final_report_detail`
   - `ledger_group_final_report_package`
   - `ledger_group_final_report_google_sheet`
   - `ledger_group_final_report_excel`
13. Package proof download works for an authorized reviewer through `ledger_group_final_report_proof_download`.
14. Historical/current separation remains true:
   - historical finalized report keeps `1000 / 600 / 400` or the approved equivalent smoke numbers;
   - current period starts from carryover `400` plus current entries only.
15. Advance/accountable smoke confirms accepted spend and open/returned remaining cash are represented in package/accountable state.
16. Group messages smoke confirms send/list/unread/mark-read remain group-scoped:
   - `message_send`
   - `message_list`
   - `message_unread`
   - `message_mark_read`
17. Business Desk/proforma smoke confirms create/list/open/print does not mutate ledger formulas:
   - `proforma_create`
   - `proforma_list`
   - `proforma_get`
   - compare `ledger_report` before/after.

Smoke evidence must record:

- deployed file list;
- SQL migration state and engine/version, without credentials;
- server URL/environment;
- smoke user/group/report/tape/capture/proof/advance ids;
- pass/fail per checklist item;
- rollback decision if any item fails.

## Dirty-Tree Risks

Current modified tracked files from `git status --short`:

- `app/advances.php`
- `app/auth.php`
- `app/groups.php`
- `app/ledger.php`
- `app/on_the_go.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `docs/HANDOFF_FULL_PRODUCT_2026-05-21.md`
- `docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md`
- `docs/STEP3_ADVANCES_2026-05-20.md`
- `public/api.php`
- `public/app.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `public/assets/apple-touch-icon.png`
- `public/assets/favicon-16x16.png`
- `public/assets/favicon-32x32.png`
- `public/assets/favicon-48x48.png`
- `public/assets/favicon-64x64.png`
- `public/assets/favicon.ico`
- `public/assets/i18n.js`
- `public/assets/icon-180.png`
- `public/assets/icon-192.png`
- `public/assets/icon-512.png`
- `public/assets/icon-maskable-512.png`
- `public/favicon.ico`
- `public/index.php`
- `public/service-worker.js`
- `scripts/local-smoke.php`

Current untracked files/directories from `git status --short --untracked-files=all` that must not be blindly deployed:

- `app/ai.php`
- `docs/AI_TEAM/`
- `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`
- `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md`
- `docs/IPHONE_NOTES_UX_ALGORITHMS_2026-05-21.md`
- `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md`
- `docs/KNOWLEDGE_LIVE_REPORTS_ADVANCES_GROUPS_2026-05-21.md`
- `docs/KNOWLEDGE_UX_CSS_GLASS_RULE_2026-05-21.md`
- `docs/USER_MESSAGES_DIGEST_2026-05-22.md`
- `public/assets/brand-logo.png`
- `public/assets/brand-mark.png`
- `public/assets/brand-og.png`
- `public/reset-local.php`
- `scripts/start-local.sh`
- `test-results/.last-run.json`

Files that must not be included in production without explicit deploy-owner approval:

- `public/reset-local.php`
- `scripts/start-local.sh`
- `test-results/`
- local docs and AI team work notes under `docs/AI_TEAM/` unless production documentation hosting is explicitly intended
- unreviewed advanced/AI surface `app/ai.php`, except as a dependency decision for current `public/api.php`
- `storage/reset-backups/` or any local database dumps/backups

## Current Blockers

- Dirty-tree deploy selection is not recorded.
- Exact production upload file list is not approved.
- Current API dependency closure is unresolved because `public/api.php` requires untracked `app/ai.php`.
- Production database engine compatibility with `deploy/on_the_go_sessions_runtime.sql` is not confirmed.
- Required schema presence/application is not confirmed.
- Production files, database, and uploaded proof/document storage backup plan is not recorded as complete.
- Rollback owner and rollback procedure are not confirmed for this release.
- Production smoke owner and evidence plan are not assigned/executed.

## Next Owner

- Project Director: choose deploy mode and exact file list; authorize or stop production action.
- Database Migration Owner / Deploy Owner: verify/apply schema with backup.
- QA Release Engineer: run production smoke after approved upload.
- Frontend UX Engineer: confirm frontend asset/service-worker/public-file deploy set if UI files are included.
