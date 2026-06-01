# Backend Data Engineer Status

## Latest Status 2026-05-28: FinDesk Board Rebuild Data Mapping

Role: Backend/Data Engineer FinDesk
Task: map existing backend/API for rebuilt FinDesk board.
Status: DONE; no backend runtime change required in this sprint.

Evidence pointer:

- `docs/AI_TEAM/48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md`

Data contract:

- Top cash strip: `ledger_balance {group_id}`.
- Incoming live reports: `on_the_go_card_list {group_id, submitted_only:1, exclude_advances:1}`.
- Incoming accountable reports: `advance_list {group_id}`.
- Approve/return live reports: `on_the_go_card_include`, `on_the_go_card_unsubmit`.
- Approve/return advances: `advance_accept`, `advance_return`, `advance_unaccept`.
- Final summary report: `ledger_group_finalize_report`.

Blocker:

- none in backend mapping; authenticated QA must prove the rebuilt screen calls the endpoints correctly.

Next owner: Frontend/UX Engineer and QA Release Engineer.

## Latest Status 2026-05-28: Open Items Sprint Backend Slice

Role: Backend/Data Engineer FinDesk
Task: first-class message context links, package JSON export, and legacy package fallback.
Status: IMPLEMENTED locally; production deploy pending.

Evidence pointer:

- `docs/AI_TEAM/45_OPEN_ITEMS_SPRINT_LOCAL_2026-05-28.md`
- `docs/AI_TEAM/46_OPEN_ITEMS_SPRINT_DEPLOY_BLOCKED_2026-05-28.md`

Result:

- `group_messages` schema now supports `report_id`, `tape_id`, `capture_id`, and `advance_id`.
- Message API validates context ids against the selected group and returns `context_links`.
- Final report package builder includes direct linked group messages when present before finalization.
- New download action `ledger_group_final_report_package_export` exports full package JSON or legacy snapshot JSON fallback.

Verification:

- local `current_user` HTTP load passed;
- local message context API smoke passed;
- local package end-to-end smoke passed for linked message inclusion and JSON export;
- `git diff --check` passed;
- PHP CLI remains unavailable in this shell.

Blocker:

- production requires DB apply of `deploy/messages_foundation.sql` after backup/preflight.
- production deploy is currently blocked in this shell by missing FTP/DB-gate environment variables.

Next owner: Project Director / Deploy Owner.

## Latest Status 2026-05-28: Candidate 34 DB Deploy Preflight

Role: Backend/Data Engineer + Database Migration Owner FinDesk
Task: deploy-preflight sprint for candidate 34, DB scanner/group-delete migration safety.
Status: documented; runtime code unchanged; production DB-side NO-GO until production preflight is executed and recorded.

Evidence pointer:

- `docs/AI_TEAM/roles/02_backend_data_engineer/DEPLOY_PREFLIGHT_DB_CANDIDATE_34_2026-05-28.md`

Result:

- Read-only SQL prepared for production scanner schema and group-delete optional columns.
- Migration decision recorded:
  - no SQL apply if scanner schema is already present;
  - apply `deploy/on_the_go_sessions_runtime.sql` if existing runtime DB is missing scanner columns/upload-state columns;
  - apply `deploy/on_the_go_foundation.sql` first only if `on_the_go_files` foundation table is missing.
- `group_delete` requires no SQL migration for optional timestamp columns.

Blocker:

- Production DB preflight/backup/apply evidence has not been run in this chat.

Next owner:

- Project Director / Deploy Owner / Database Migration Owner.

## Latest Status 2026-05-28: Group Soft Archive API Hardening

Role: Backend/Data Engineer FinDesk
Task: safe backend/API removal of test groups without hard-deleting financial evidence.
Status: implemented locally; ready for QA Release Engineer recheck.

Evidence pointer:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Group Soft Archive API Hardening 2026-05-28`.
- Director HTTP smoke fixture: `group_id=228`.

Result:

- `group_delete` remains soft archive only.
- Base employee/non-admin receives `admin_required`.
- Admin/creator can archive a group without hard-deleting ledger/report/proof/message evidence.
- API response returns `archive_mode=soft` plus before/after evidence counters.
- Missing optional timestamp columns such as `groups.updated_at` and `group_invites.updated_at` no longer break the archive path.

Verification:

- HTTP/API smoke passed on local server: base denied, admin archived, evidence preserved, repeated owner call idempotent.
- `git diff --check app/groups.php public/api.php scripts/local-smoke.php`: PASS.
- `current_user` HTTP load check: PASS.
- CLI PHP smoke remains environment-blocked where PHP CLI is unavailable.

Blocker:

- Frontend delete/archive control is not implemented in this backend task.
- QA Release Engineer must run formal backend/API recheck before deploy routing.

Next owner:

- QA Release Engineer.

## Latest Status 2026-05-28: Receipt Scanner Storage/API Task Card

Role: Backend Data Engineer FinDesk
Task: define backend storage/API requirements for Receipt Scanner evidence.
Status: task card recorded; implementation pending; documentation only.

Evidence pointer:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Receipt Scanner Storage/API Task Card 2026-05-28`.

Result:

- Defined required storage for original receipt image/file and cleaned image/PDF derivatives.
- Defined scanner metadata requirements: corners, perspective/crop/rotation, filters, scanner version, and canonical metadata hash.
- Defined durable proof states for `pending`, `failed`, retry/retry-needed, and uploaded/ready.
- Defined links from receipt proof to capture, tape/session, report/final report, and archive/package.
- Recorded file size, MIME, privacy, private storage, auth download, and noindex/noarchive requirements.
- Compared frontend-only PDF generation with server-side generation.
- Recommended MVP path: client-side scanning/PDF generation plus authoritative backend storage of original, cleaned/PDF, metadata, states, links, and hashes.

Verification:

- Documentation-only pass.
- No runtime code changed.

Blocker:

- Receipt Scanner is not release-ready until backend storage/API, frontend upload wiring, and QA evidence are implemented and accepted.

Next owner:

- Project Director / Backend implementation owner for scheduling the storage/API implementation.
- Frontend UX Engineer after API shape is approved.
- QA Release Engineer after implementation.

## Latest Status 2026-05-27: Base Employee Rights Hotfix

Role: Backend Data Engineer FinDesk
Task: enforce default invited employee rights.
Status: implemented and deployed; director production smoke passed; waits for QA recheck.

Evidence pointer:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Base Employee Rights Hotfix 2026-05-27`.
- production hotfix record: `docs/AI_TEAM/29_PRODUCTION_HOTFIX_BASE_RIGHTS_2026-05-27.md`.
- local fixture: `group_id=224`, employee user `532`.
- production fixture: `group_id=10`, employee user `27`.

Result:

- Default `base` invite keeps group finance/report/message data closed.
- Base employee can still use operational field capture and own self-control.
- Base employee no longer receives full group working cash as initial tape base.

Verification:

- `git diff --check app/on_the_go.php app/messages.php public/assets/app.js public/app.php`: PASS.
- `node --check public/assets/app.js`: PASS.
- local rights smoke: PASS.
- production rights smoke: PASS.
- PHP CLI lint: environment-blocked, `php: command not found`.

Blocker:

- QA Release Engineer must record the formal production recheck.

## Latest Status 2026-05-27: Production Multi-Employee Participant-Control Patch

Role: Backend Data Engineer FinDesk
Task: production multi-employee money-flow blocker fix.
Status: implemented and deployed; director production smoke passed; waits for QA production recheck.

Evidence pointer:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Production Multi-Employee Participant-Control Patch 2026-05-27`.
- local HTTP fixture: `group_id=223`, `report_id=499`.
- production smoke fixture: `group_id=9`, `report_id=84`.
- production hotfix record: `docs/AI_TEAM/28_PRODUCTION_HOTFIX_PARTICIPANT_CONTROL_2026-05-27.md`.

Result:

- Product Finance decision applied: `admin_cash_left` is physical admin cash before explicit reimbursement payment.
- Final detail/package/export expose `admin_cash_left=568`, positive employee remainders `184`, employee reimbursement due `36`, employee net `148`, and signed participant rows for the tested scenario.
- Employee 2 `-36` / reimbursement due `36` is no longer audit-only in local runtime or director production smoke.

Verification:

- `git diff --check app/ledger.php public/assets/app.js`: PASS.
- `node --check public/assets/app.js`: PASS.
- local HTTP load check: PASS, `200 OK`.
- local HTTP multi-employee scenario: PASS.
- production file checksum verification after upload: PASS.
- production HTTP/API multi-employee scenario: PASS.
- PHP CLI lint: environment-blocked, `php: command not found`.

Blocker:

- QA Release Engineer must rerun or inspect the production multi-employee scenario and record the formal role gate.

## Latest Status 2026-05-27: Technical SEO / PWA Infra Check

Role: Backend / Infra SEO Engineer
Task: technical SEO / production infra check for PWA before production upload.
Status: completed as documentation/check; production deploy remains NO-GO.

Evidence pointer:
- `docs/AI_TEAM/22_TECHNICAL_SEO_INFRA_CHECK.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Technical SEO / PWA Infra Check 2026-05-27`

Result:
- Confirmed current robots/sitemap boundary excludes `/app.php`, `/api.php`, and `/storage/`.
- Confirmed `public/app.php` has `noindex,nofollow`.
- Confirmed canonical/sitemap consistency for the public root.
- Confirmed current service worker has no `fetch` handler, so stale SEO cache risk is low, but rollback smoke is still required because it claims clients and deletes old `findesk-*` caches.
- Documented required production headers for manifest, robots, sitemap, service worker, API, index/app shells, and assets.
- Documented Search Console/Bing verification as a later no-credentials owner task.
- Documented analytics boundary: no current analytics snippets found; future analytics must strip query strings and never collect finance data, invite tokens, credentials, API bodies, or storage URLs.
- Identified deploy package addendum: selected package does not list `public/robots.txt`, `public/sitemap.xml`, or `public/manifest.webmanifest`.

Verification:
- Manifest JSON parse passed.
- Sitemap XML validation passed with `xmllint`.
- `node --check public/service-worker.js` passed.
- `node --check public/assets/app.js` passed.

Boundary:
- no application code changed;
- no production action;
- no credentials documented;
- production NO-GO remains blocked by DB/backup/schema/rollback/smoke controls.

Next owner:
- Project Director / Deploy Owner for package/header decisions.
- QA Release Engineer after approved upload for SEO/PWA smoke.

## Latest Status 2026-05-27: Production Deploy Readiness Plan

Role: Backend Data Engineer
Task: Production deploy readiness plan for 100% MVP.
Status: BLOCKED for production deploy; plan completed.
Evidence pointer:
- `docs/AI_TEAM/14_PRODUCTION_DEPLOY_READINESS.md`

Result:
- Wrote deploy-readiness plan with backend/API/runtime SQL candidates, migration checklist, backup/rollback checklist, production smoke checklist, and dirty-tree risk list.
- Backend/API product readiness remains PASS from prior final readiness review.
- Production deploy remains blocked until exact deploy package selection, DB migration compatibility/application, backup/rollback, and production smoke are handled by explicit Project Director/CEO step.

Key deploy candidate finding:
- Current `public/api.php` requires untracked `app/ai.php`; a narrow deploy that uploads `public/api.php` but omits `app/ai.php` can break the API. Project Director/deploy owner must resolve this dependency deliberately.

Boundary:
- no application code changed;
- no production upload;
- no database change;
- no credentials documented.

Next owner:
- Project Director / Deploy Owner, then Database Migration Owner and QA Release Engineer.

## Latest Status 2026-05-27: Final Business MVP Backend/API Readiness Risk Check

Role: Backend Data Engineer
Task: Final Business MVP backend/API production-readiness risk check after QA residual surface PASS.
Status: Business-MVP product readiness PASS for backend/API; production deploy readiness BLOCKED by deploy controls.
Evidence pointer:
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Final Business MVP Backend/API Readiness Risk Check 2026-05-27`

Result:
- No known backend/API P0 remains for business-MVP product readiness after QA residual surface PASS run `20260527968710`, group `222`, report `454`.
- Accepted gates remain intact: Field Combat no-data-loss and Closed group report package.
- Backend/API review found no reason to reopen money formulas, Field Combat, closed package, messages, proforma, Travel staging, or Advanced reachability as product blockers.
- Production deploy is not ready yet: dirty-tree package selection, production DB migration compatibility/application, backup/rollback, and production smoke remain P0 before upload.

Verification:
- `git diff --check` passed.
- `node --check public/assets/app.js` passed.
- Local server responded at `http://127.0.0.1:18889/app.php`.
- `GET /api.php?action=current_user` returned `{"ok":true,"user":null}`.
- CLI PHP is unavailable in this shell, so `scripts/local-smoke.php` remains environment-blocked here.

Boundary:
- no backend/API code changed;
- no formulas changed;
- no UX changed;
- only backend role docs were edited.

Next owner:
- Project Director / deploy owner for deployment selection, DB migration confirmation, backup/rollback, and production smoke assignment.

## State

Hired. Initial office created.

## Latest Status 2026-05-27: Closed Group Report Archive Package

Role: Backend Data Engineer
Task: Immutable `Закрытый групповой отчет` package source implementation.
Status: implemented for new finalizations; downstream Frontend/UX and QA gate required.
Evidence pointer:
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Closed Group Report Archive Package Implementation 2026-05-27`

Result:
- Added `ledger_group_final_report_package` by `report_id`.
- New finalizations store immutable `audit_log.details.report_package`.
- Package includes group summary, participant report snapshots, captures, proof index/download access, accountable/advance state, messages/audit references, and export action metadata.
- Added `ledger_group_final_report_proof_download` so authorized reviewers can open package proof copies without relying on original file ownership.
- Extended `scripts/local-smoke.php` with package/proof/immutability checks.

Verification:
- CLI PHP remains unavailable, so CLI smoke is environment-blocked.
- HTTP load check passed.
- HTTP/API package fixture passed with `group_id=221`, `report_id=441`, `proof_id=proof-441-on_the_go_capture-12`, `advance_id=65`.
- `git diff --check` passed for touched backend/doc files.

Boundary:
- financial formulas were not changed;
- cash/card/accountable meaning preserved;
- no UX code changed.

Known follow-up:
- Frontend must open `Закрытый групповой отчет` through the new package endpoint.
- QA must run the full multi-participant package fixture.
- Product/Frontend should decide whether a separate package-wide print/export file is P0 after the package source.
- Report-context messages are audit-derived until message schema gets direct report/capture/advance links.

Next owner:
- Frontend UX Engineer and QA Release Engineer.

## Latest Status 2026-05-26: Business MVP Group Report Trace Result

Role: Backend Data Engineer
Task: Business MVP group report/archive/common pot backend trace.
Status: completed as trace; business MVP backend remains P0 blocked for the full archive/package contract.
Evidence pointer:
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Business MVP Group Report / Archive / Common Pot Backend Trace 2026-05-26`

Result:
- Current backend can create a group final report snapshot from multiple included non-advance Live Report cards and export that group snapshot by `report_id`.
- Current backend cannot yet open one immutable archive package containing the closed group report plus all linked participant reports, captures, proofs, accepted/open accountable money, and report-context messages.
- Card spend remains separated from physical cash in the checked backend paths.
- Group messages exist as a separate group thread, not as report-linked archive evidence.
- Group-scope endpoints still require explicit `group_id`; missing `group_id` can mean personal/assigned scope.

Boundary:
- no backend/API behavior change was made in this trace;
- no financial formula change was made;
- no UX change was made;
- no reset/checkout/clean was performed.

Next owner:
- Project Director / Product Finance Architect for P0 package decision and implementation assignment.
- Backend Implementation Queue after product decision.
- QA Release Engineer after implementation for multi-participant archive/package fixture.

## Latest Status 2026-05-26

Role: Backend Data Engineer
Task: Business MVP group report/archive/common pot backend trace.
Status: assigned by Project Director after Chief Auditor approved Field Combat no-data-loss gate.
Task file:
- `docs/AI_TEAM/roles/02_backend_data_engineer/HANDOFF_2026-05-26_BUSINESS_MVP_GROUP_REPORT_ARCHIVE_TRACE.md`

Boundary:
- no financial formula change;
- no UX change;
- no backend/API implementation patch during the trace unless Project Director issues a separate implementation task.

Next owner:
- Backend Data Engineer.

## Fixed Positions

- Card stream creates noncash/card expense only.
- Cash stream owns physical cash calculations.
- Historical rows must remain reproducible.
- Carryover after final report must not destroy old report data.

## Weak Spots To Inspect

- Export snapshot source selection when a finalized report exists.
- Open ledger/group scope selection and backend defaults.
- Submitted/included live report status transitions.
- Archive endpoint filters for employee-linked live reports.

## Next Work

1. Trace endpoints used by open accounting and report summary.
2. Add or update smoke checks for open carryover after report fixation.
3. Verify card stream has zero cash delta everywhere.
4. Document backend state machine for live reports and advances.

## Director Assignment 2026-05-26

Date: 2026-05-26
From role: Project Director
To role: Backend Data Engineer
Priority: P0
Context: backend review must follow Product Finance Architect terms. Current director baseline found `HEAD=72b38e6`, `origin/main=72b38e6`, dirty working tree, local web server responding at `http://127.0.0.1:18889`, and CLI `php` unavailable in this shell.
Request: verify final report fixation and carryover separation against the financial glossary, then trace endpoints/data sources for open accounting, report summary, archive, and export. Do not change UX or financial terms.
Acceptance criteria:
- Historical finalized report can still show old income and expenses.
- New open period starts from carryover, not from old income as new current income.
- Card stream has zero cash delta in API/data/export paths.
- Group report/export defaults and personal/group scope risks are documented.
- Any smoke-script change or test fixture request is recorded with QA coordination.
Files/screens involved:
- `app/ledger.php`
- `app/on_the_go.php`
- `app/advances.php`
- `public/api.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Director Command 2026-05-26: Data Truth For Money Map

Date: 2026-05-26
From role: Project Director
To role: Backend Data Engineer
Priority: P0
Context: the new product language requires every amount to map to a clear money place/state and proof source. Backend must show whether current data can support that without changing financial formulas prematurely.
Request: map existing data sources and endpoints to the human money states: `Получено`, `В кассе администратора`, `У сотрудников`, `Потрачено наличными`, `Потрачено картой`, `На проверке`, `В финальном отчете`, `В архиве`. Identify missing fields, ambiguous fields, and risks where one backend value can be shown with the wrong meaning.
Acceptance criteria:
- `FINDINGS.md` lists endpoint/data source for each money state.
- Any state that cannot be reliably derived is marked as a blocker or open question.
- Existing carryover, final report, archive, journal, and export behavior are mapped to the human money map.
- No database/API change is made without a separate implementation task.
Files/screens involved:
- `app/ledger.php`
- `app/on_the_go.php`
- `app/advances.php`
- `public/api.php`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Director Command 2026-05-26: Instant Capture Data Path

Date: 2026-05-26
From role: Project Director
To role: Backend Data Engineer
Priority: P0
Context: quick field capture must preserve events immediately without bypassing review, proof, and final report fixation.
Request: trace how current live report/on-the-go data stores quick records, attachments/proofs, draft/review/accepted states, cash/card stream, employee-linked reports, and final report inclusion. Identify whether current API can support `capture now, review before final` without data ambiguity.
Acceptance criteria:
- `FINDINGS.md` maps quick capture fields to storage/API endpoints.
- Pending quick records are clearly separated from accepted/final report numbers.
- Cash and card quick records keep separate cash effects.
- Missing backend support is listed as implementation tasks, not silently patched.
Files/screens involved:
- `app/on_the_go.php`
- `app/ledger.php`
- `app/advances.php`
- `public/api.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Director Assignment 2026-05-26: Carryover Export Archive Data Trace

Date: 2026-05-26
From role: Project Director
To role: Backend Data Engineer
Priority: P0
Context: QA and Chief Auditor approved only the instant field capture slice. Full release remains blocked because carryover, export, and archive are not yet proven. Backend must trace data sources before QA repeats the full `€1000 income -> €600 expense -> €400 carryover` flow.
Request: map the backend/API data path for final report fixation, open-period carryover, export source selection, archive listing, employee-linked live reports, card stream zero-cash effect, and group scope defaults. Do not change formulas or API behavior during this pass.
Acceptance criteria:
- `FINDINGS.md` states where historical finalized report data comes from.
- `FINDINGS.md` states where current open-period carryover comes from and why old income is not treated as new current income.
- `FINDINGS.md` states what export source is used when a finalized report exists.
- `FINDINGS.md` states how archive includes or can miss administrator and employee-linked live reports.
- `FINDINGS.md` states where card expenses are kept separate from physical cash.
- `TASKS_TO_OTHERS.md` gives QA a repeatable verification checklist or asks for a fixture if backend cannot provide one.
Files/screens involved:
- `app/ledger.php`
- `app/on_the_go.php`
- `app/advances.php`
- `public/api.php`
- `scripts/local-smoke.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Director Assignment 2026-05-26: Historical Finalized Report Source

Date: 2026-05-26
From role: Project Director
To role: Backend Data Engineer
Priority: P0
Context: Product Finance Architect confirmed release requires a dedicated historical finalized report/export action. Current open-period export may continue to switch to `Переходящий остаток`, but the selected closed final report must remain readable and exportable as an immutable product object.
Request: implement or prepare the implementation patch for a historical finalized report/export source selected by finalization identity. Preserve the existing current open-period carryover/export behavior.
Acceptance criteria:
- finalization stores or exposes a snapshot that can return the selected closed report's rows, totals, proof references, finalization date/time, and carryover as they were at finalization.
- `Текущий период` / current export still starts from carryover after finalization and does not show old income as current income.
- `Закрытые финальные отчеты` / historical export can return the old `EUR 1000 / EUR 600 / EUR 400` report after the current export has switched to open period.
- historical export is selected by finalization identity, not by reconstructing whichever current records are visible today.
- existing financial formulas are not changed silently; any formula or schema decision is documented for Product Finance Architect and Chief Auditor.
Files/screens involved:
- `app/ledger.php`
- `public/api.php`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Director Assignment 2026-05-26: Current Export Combo Regression

Date: 2026-05-26
From role: Project Director
To role: Backend Data Engineer
Priority: P0
Context: QA verified historical finalized report snapshot/export for new finalizations, but found a current open-period export regression when post-finalization income and a current included Live Report coexist. Evidence: QA group `192`, final report `348`, current income ledger entry `84`, current Live Report tape `175`. `ledger_group_open_received_funds.entries` returned `{"id":175}` instead of the current income row.
Request: fix current open-period export so it preserves all post-finalization income rows while also adding current included Live Report aggregates. Keep historical finalized report snapshot/export unchanged.
Acceptance criteria:
- `ledger_group_open_received_funds.entries` returns the current income row after finalization.
- Current export contains carryover `400`, current income `50`, current Live Report expense `25`, and no old finalized `1000` as current income.
- Selected historical final report/detail/export remains unchanged at `1000 / 600 / 400`.
- `git diff --check` is clean.
- CLI PHP smoke is run if available; if still unavailable, HTTP/API evidence is recorded.
Likely code area:
- `app/ledger.php::ql_ledger_group_open_received_funds`
- check the by-reference loop `foreach ($rows as &$row)` and later reuse of `$row`.
Files/screens involved:
- `app/ledger.php`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Director Assignment 2026-05-26: Field Combat Mode Backend Persistence

Date: 2026-05-26
From role: Project Director
To role: Backend Data Engineer
Priority: P0
Context: Product Finance Architect accepted `Advanced = non-MVP staging` and `Field Combat Mode = MVP foundation`. Business MVP cannot be called complete while unfinished field-session no-data-loss behavior is unproven.
Request: inspect backend/API/storage coverage for Field Combat Mode persistence, autosave, proof upload state, open-session identity, recovery, recalculation after recovery, group context, participant context, and report submission boundary. Do not change financial formulas without Product Finance Architect and Chief Auditor.
Acceptance criteria:
- `FINDINGS.md` states what survives refresh, navigation, phone lock/return, weak network, and partial proof upload.
- `FINDINGS.md` states what can still be client-only or losable.
- `FINDINGS.md` maps saved/pending/failed/retry states if they exist or marks the gap as P0.
- `FINDINGS.md` states whether recovered session totals are recalculated from persisted state.
- `TASKS_TO_OTHERS.md` gives Frontend/UX and QA exact follow-up tasks.
- CEO / Project Director chat receives only a short report.
Files/screens involved:
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/HANDOFF_2026-05-26_FIELD_COMBAT_MODE_BACKEND.md`
- `app/on_the_go.php`
- `app/ledger.php`
- `app/advances.php`
- `public/api.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `scripts/local-smoke.php`

Current status:
- Backend trace completed on 2026-05-26.
- Initial result: P0 blocked for full Field Combat Mode no-data-loss contract.
- Initial pass: saved rows/card/session/group/participant/stream/proof recover from backend after successful `on_the_go_signed_sync` / `on_the_go_upload_file`.
- Initial blocker: raw typed facts before successful save were client-only; proof upload pending/failed/retry state was not durable.
- Implementation update: backend/API patch added durable field draft, sync operation idempotency, durable proof upload state, recovery endpoints, migration SQL, and smoke coverage.
- Current backend status: ready for Frontend wiring and QA recheck; release claim remains blocked until UI calls the new autosave/proof-state endpoints and QA proves refresh/upload-failure recovery.
- Evidence pointer: `FINDINGS.md` sections `Field Combat Mode Backend Persistence Trace 2026-05-26` and `Durable Field Combat Draft/Proof Implementation 2026-05-26`.

## Status 2026-05-27: Production `message_unread` Alias Hotfix

Status: implemented and deployed.

Scope:

- `app/messages.php`

Verification:

- local API smoke passed on group `225`;
- production API smoke passed on group `19`;
- base employee receives safe `message_unread` response with `unread_count=0`;
- base employee remains denied for `message_list` and `message_send`.

Gate:

- Backend defect is closed by director smoke.
- QA Release Engineer must rerun the default base employee rights slice before the P0 gate is closed.

## Status 2026-05-28: Production Legacy May Report Hotfix

Status: implemented and deployed by Project Director.

Scope:

- production data hotfix for legacy `03.05.2026` report, tape `id=1`;
- `app/on_the_go.php` self-return hardening for locked personal cards without group.

Result:

- stale legacy report was archived out of working UI;
- remaining visible/reportable capture was archived and made non-reportable;
- personal no-group cards can be returned by their owner instead of creating a dead correction request.

Verification:

- production `/api.php?action=current_user`: HTTP 200, `ok=true`;
- production `/app.php`: HTTP 200;
- temporary production scripts were deleted after use.

Next owner:

- CEO/QA browser check: confirm the `03.05` report no longer appears in the working submitted/live report surface.

---

# Backend/Data Status

## Latest Status 2026-05-28 Receipt Scanner Storage Implementation

Role: Backend/Data Engineer FinDesk
Task: Receipt Scanner original+PDF+metadata storage.
Status: IMPLEMENTED locally; director API smoke and idempotency recheck passed; independent QA/browser evidence pending; not deployed.

Changed files:

- `app/on_the_go.php`
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Evidence

- Local scanner storage smoke: user `536`, tape `294`, capture `201`, original file `16`, PDF file `17`, bundle `scanner-bundle-api-20260528`, repeated PDF upload idempotent.
- Original/PDF idempotency recheck: user `541`, tape `302`, capture `205`, original file `24`, PDF file `25`, bundle `scanner-api-bundle-20260528080559`; repeated original and repeated PDF uploads both returned `idempotent=true`.
- Final package proof-chain recheck: user `542`, group `226`, tape `303`, capture `206`, report `516`, bundle `scanner-package-bundle-20260528080910`; closed report package contains `scanner_original` and `scanner_cleaned_pdf` with cleaned PDF derived from original.
- `scripts/local-smoke.php` has scanner proof-chain assertions; not executed here because PHP CLI is unavailable.

## Next Owner

QA Release Engineer for browser/device scenario, then Chief Auditor.
