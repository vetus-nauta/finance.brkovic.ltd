# QA Release Engineer Status

## Latest Status 2026-05-28 - FinDesk Board Rebuild QA Card

Role: QA Release Engineer FinDesk

Task: verify rebuilt FinDesk board after local frontend patch.

Status: QUEUED; syntax/HTTP smoke passed, authenticated browser flow pending.

Evidence:

- `docs/AI_TEAM/48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md`

Passed locally:

- `node --check public/assets/app.js`
- `node --check public/assets/i18n.js`
- `node --check public/service-worker.js`
- `git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/service-worker.js`
- `/app.php` returned `200`
- `current_user` returned valid JSON

Required QA:

- mobile `390x844`, tablet `820x1180`, desktop `1440x900`;
- top cash strip does not cover controls and shows admin/employee cash;
- submitted employee fast report glows orange;
- approve attaches employee report as child card;
- return restores employee report to editable fast-entry state;
- `Создать и утвердить отчет` creates final report and active cards leave the working board;
- archive and final report package remain accessible;
- browser Back still moves one app step.

Blocker:

- Browser automation is unavailable in this shell (`playwright` and Chromium are absent), so visual and authenticated flow QA must be manual or run in the existing production QA harness.

## Latest Status 2026-05-28 - Fast Entry UX QA Card

Role: QA Release Engineer FinDesk

Task: verify fast-entry UX cleanup and browser Back behavior after local patch.

Status: QUEUED; local syntax/HTTP checks already passed by Project Director.

Evidence:

- `docs/AI_TEAM/47_FAST_ENTRY_UX_BACK_LOCAL_2026-05-28.md`

Required checks:

- mobile `390x844`: no final amount overlap by edit/finish control;
- no lower-right decorative pseudo-card on fast-entry editor;
- `Фото`, `Скан`, `Файл`, `Наличные`, and saved-files button fit without text clipping;
- saved image/PDF proof opens from `Открыть сохраненные файлы`;
- browser Back moves one app step back across editor/cards/module transitions.

Next owner: QA Release Engineer when browser automation or manual device check is available.

## Latest Status 2026-05-28 - Open Items Sprint Local Verification

Role: QA Release Engineer FinDesk

Task: local verification for open-items sprint candidate `20260528-open-sprint1`.

Status: PASS for local syntax/API/fallback checks; production smoke pending.

Evidence:

- `docs/AI_TEAM/45_OPEN_ITEMS_SPRINT_LOCAL_2026-05-28.md`
- `docs/AI_TEAM/46_OPEN_ITEMS_SPRINT_DEPLOY_BLOCKED_2026-05-28.md`

Result:

- JS/service-worker syntax checks passed.
- local `app.php` and `current_user` HTTP checks passed.
- local message context smoke passed.
- invalid message context failed closed with `invalid_message_context`.
- local package end-to-end smoke passed: linked message entered `package.messages.report_context` and JSON package export returned attachment for `report_id=587`.
- unsupported language `fr-FR` fell back to English with `fallback_applied=true`.

Limitations:

- PHP CLI is unavailable.
- Real-device scanner/PWA camera behavior is not checked by this local pass.
- Full package JSON export is production-smoked after deploy with an authenticated final report fixture.
- Production smoke is blocked in this shell until FTP/DB-gate deploy variables are available.

Next owner: Project Director / Deploy Owner.

## Latest Status 2026-05-28 - Recheck P0 Live Report Records Page After Frontend UX Fix

Role: QA Release Engineer FinDesk

Task: recheck P0 Live Report records page after Frontend/UX fix.

Status: PASS for checked recheck scope; previous Frontend/UX P0 closed on local frontend assets with production API proxy.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Recheck P0 Live Report Records Page After Frontend UX Fix`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_recheck_20260528/live_records_recheck_result.json`
- run_id `20260528RECORDSRECHECK04`
- screenshots listed in `FINDINGS.md`

Fixture:

- admin user id `81`;
- member user id `82`;
- base negative-control user id `83`;
- group id `35`;
- tape id `105`;
- capture id `155`;
- image file id `28`;
- PDF file id `29`.

Result:

- admin sees the employee Live Report card in the ordinary records page on `390x844`, `820x1180`, and `1440x900`;
- admin opens the card from the visible list control and sees the record plus two proof buttons;
- stream gate does not remain over records page; clicks work without force/direct-open fallback;
- image and PDF open inline and close cleanly;
- proof viewer open link has no `download` attribute;
- base employee does not see another employee's card/detail/proof files;
- no critical overlap/clipping found in checked card title, proof labels, or action buttons.

Additional Director smoke input:

- PASS on `390x844`, `group_id=244`, employee `tape_id=332`, `capture_id=217`, proof controls `2`;
- admin saw card in ordinary list, opened card, proof viewer opened/closed, `otr-stream-gate-open` did not remain.

Limitation:

- local PHP runtime was unavailable; QA used local frontend assets through a temporary Node proxy with production API sessions.

Blocker: none.

Next owner: Project Director / Deploy Owner for production deploy decision and post-deploy smoke.

## Latest Status 2026-05-28 - P0 Production Live Report Records Proof Links QA

Role: QA Release Engineer FinDesk

Task: P0 full QA of production Live Report records page after proof-links hotfix.

Status: BLOCKED / P0 for Frontend/UX records-page release acceptance; Backend/Data proof access PASS.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - P0 Production Live Report Records Proof Links QA`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_proof_links_20260528/live_records_proof_links_result.json`
- run_id `20260528LIVEPROOFLINKSQA01`
- viewer hotfix smoke `prod-proof-viewer-20260528154804`
- production asset version verified: `20260528-proof-viewer1`
- screenshots listed in `FINDINGS.md`

Fixture:

- admin user id `68`;
- member user id `69`;
- group id `31`;
- tape id `95`;
- capture id `151`;
- image file id `20`;
- PDF file id `21`.

Result:

- owner and group admin can list and download both image and PDF proof files through production API;
- downloads returned inline HTTP `200` for owner and admin;
- anonymous proof download returned HTTP `401`;
- browser checks ran on `390x844`, `820x1180`, and `1440x900`;
- direct card detail rendered two proof controls and opened image/PDF viewer for owner and admin on all checked viewports.
- full browser matrix was not blocked by environment.

Blocker:

- Frontend/UX P0: admin records list does not show the employee target card on any checked viewport before direct-open fallback.

Non-blocker:

- owner records-list restore can be covered by stream gate;
- mobile card title/proof labels/action button have overflow/clipping issues;
- proof viewer `Открыть` link also has `download` attribute.

Next owner: Frontend/UX. Backend/Data has no blocker from this run.

## Latest Status 2026-05-28 - Deploy Preflight Sprint QA Update For Candidate 34

Role: QA Release Engineer FinDesk

Task: deploy-preflight sprint QA update for candidate 34.

Status: checklist updated; local PASS remains valid; production NO-GO remains.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/DEPLOY_READINESS_CHECKLIST_2026-05-28.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Deploy Preflight Sprint QA Update For Candidate 34`
- candidate file: `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`

Result:

- candidate 34 includes `app/groups.php` and `public/assets/i18n.js`;
- checklist now includes both files in the selected bundle;
- pre-upload local checks now explicitly cover `group_delete` soft archive and asset/version checks;
- post-upload production smoke now explicitly covers asset cache guard and group delete/test-group soft archive;
- no runtime code was changed by QA.

QA rerun decision:

- no additional local recheck is required now if candidate 34 remains unchanged;
- QA must run again after DB preflight, backup/rollback, production upload, and SQL/application;
- real-device scanner/PWA camera QA remains separate before scanner is called device-ready.

Blocker:

- production NO-GO remains until deploy checklist P0 items are closed.

Next owner: Project Director / Deploy Owner / Database Migration Owner.

## Latest Status 2026-05-28 - Formal Local Recheck After Frontend Leftovers And Group Delete Hardening

Role: QA Release Engineer FinDesk

Task: formal local recheck after Frontend/UX leftovers fix and Backend/Data `group_delete` hardening.

Status: PASS for requested local checks; production deploy readiness is not declared here.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Formal Local Recheck After Frontend Leftovers And Group Delete Hardening`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/local_leftovers_recheck_20260528/SUMMARY.md`
- run_id `20260528LOCALLEFTOVERS01`
- group_delete fixture `group_id=233`

Result:

- local server responded HTTP `200`, `ok=true`;
- `node --check public/assets/app.js` passed;
- `node --check public/assets/i18n.js` passed;
- login fallback H1 is `FinDesk access code`, not old `FinDesk sign-in code`;
- auth request/verify flow worked during fresh fixture setup;
- scanner modal has explicit `Закрыть`, top `×`, outside-click close, and Escape close wiring;
- `ontherun` module/work-zone persistence code exists and did not syntax-break the app;
- `group_delete` passed: base member denied with `admin_required`, admin soft-archived own group, evidence counters preserved, repeated delete returned `already_deleted`.

Limitations:

- browser screenshot overlap acceptance was not run because local browser automation modules were unavailable in this shell;
- real-device scanner/PWA camera QA remains separate.

Next owner: Project Director / Deploy Owner.

## Latest Status 2026-05-28 - Deploy Readiness Checklist

Role: QA/Deploy Release Engineer FinDesk

Task: prepare deploy-readiness checklist for the next limited production release after local scanner/UX/backend work.

Status: BLOCKED / NO-GO for production deploy until P0 readiness items are closed.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/DEPLOY_READINESS_CHECKLIST_2026-05-28.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Deploy Readiness Checklist For Limited Scanner/UX/Backend Release`

Closed inputs:

- business-MVP product gate approved for checked new-data path;
- production base rights rerun PASS on `20260527212947`, `group_id=20`, `report_id=194`;
- local Receipt Scanner file-input QA PASS on `20260528RSQA01`, with Chief Auditor approval limited to local browser/HTTP file-input scope.

Blockers:

- real-device scanner QA is still required before production scanner deploy is treated as device-ready;
- PHP CLI/smoke is unavailable in this shell and must be available or replaced by approved HTTP/API smoke;
- scanner DB migration preflight must prove required columns before runtime upload;
- selected file bundle must be frozen;
- production DB/files backup and rollback procedure must be recorded.

Next owner: Project Director / Deploy Owner / Database Migration Owner.

## Latest Status 2026-05-28 - Receipt Scanner Local Browser/HTTP QA

Role: QA Release Engineer FinDesk

Task: Receipt Scanner local browser/HTTP QA after local sprint 2026-05-28.

Status: PASS for local browser/HTTP file-input scanner path; full release ready not declared.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Receipt Scanner Local Browser/HTTP QA`
- run_id `20260528RSQA01`
- result JSON `/tmp/findesk-receipt-scanner-20260528RSQA01/receipt-scanner-qa-result.json`
- screenshots `/tmp/findesk-receipt-scanner-20260528RSQA01/*`

Result:

- local server `127.0.0.1:18889` responded;
- scanner modal `Скан чека в PDF` opened on mobile `390x844`, tablet `820x1180`, desktop `1440x900`;
- image preview/canvas, crop handle movement, cleanup slider, and black-white toggle were checked;
- `Прикрепить PDF` created the PDF attachment state;
- save produced one money row and two linked proof artifacts per viewport: `scanner_original` and `scanner_cleaned_pdf`;
- both proof artifacts shared one `proof_bundle_id`; cleaned PDF linked to original via `source_file_id`; both had `file_hash_sha256`;
- repeated `scanner_original`, repeated `scanner_cleaned_pdf`, and repeated signed sync were idempotent;
- refresh/return after save reopened the saved row with proof evidence still available through API.

Blocker:

- None for local browser/HTTP file-input scanner path.
- Limitation: physical camera capture and installed iPhone/Android PWA mode were not available in headless Chromium and still need real-device QA.

Next owner: Project Director for device/PWA camera QA decision and release routing.

## Latest Status 2026-05-28 - Receipt Scanner QA Matrix Task Card

Role: QA Release Engineer FinDesk

Task: Receipt Scanner QA matrix task card.

Status: PASS for matrix preparation; execution pending.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Task Card 2026-05-28 - Receipt Scanner QA Matrix`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md` section `To Project Director / QA Release Engineer - Receipt Scanner QA Execution`

Result:

- QA matrix now covers iPhone Safari PWA, Android Chrome, desktop upload, poor light, glare, crumpled receipt, busy background, refresh before upload, refresh after upload, offline/retry, original proof plus PDF/final-report availability, duplicate-money-row protection, and archive/final report proof access.
- P0 stop criteria are recorded for lost typed money facts, lost original proof, duplicate money rows, missing authorized archive proof access, unauthorized proof access, and unconfirmed OCR/accounting mutation.
- QA changed only role documentation files and did not change runtime code.

Blocker:

- None for matrix preparation. Execution still needs the assigned target build/device window and real browser/device evidence.

Next owner: QA Release Engineer for execution; Frontend/PWA and Backend/Data if execution finds capture, persistence, retry/idempotency, proof-access, or archive defects.

## Latest Status 2026-05-27 - Web Designer Branding QA

Role: QA Release Engineer FinDesk

Task: Branding visual acceptance for `public/index.php` and `public/app.php` after Web Designer task.

Status: PASS.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/web_designer_branding_20260527/SUMMARY.md`
- `/tmp/findesk-web-designer-20260527/index-mobile390x844.png`
- `/tmp/findesk-web-designer-20260527/app-mobile390x844.png`
- `/tmp/findesk-web-designer-20260527/index-tablet820x1180.png`
- `/tmp/findesk-web-designer-20260527/app-tablet820x1180.png`
- `/tmp/findesk-web-designer-20260527/index-desktop1440x900.png`
- `/tmp/findesk-web-designer-20260527/app-desktop1440x900.png`

Result:

- logo/favicons pass on local browser matrix (`390x844`, `820x1180`, `1440x900`) with no blocking overlap/crop in captured areas;
- required icon sizes are present in metadata and rendered from tested routes.

Next owner: Project Director.

## Latest Status 2026-05-27 - Production Base Rights Rerun

Role: QA Release Engineer FinDesk

Task: production rerun default base employee rights after backend `message_unread` alias hotfix.

Status: PASS.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_base_rights_rerun_2026-05-27/SUMMARY.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_base_rights_rerun_2026-05-27/production_base_rights_rerun_result.json`

Result:

- fresh production fixture `group_id=20`, `report_id=194`, base employee `user_id=59`;
- `message_unread` returned HTTP `200`, `ok=true`, `unread_count=0`;
- base employee remained denied from group exports, final reports/package/export, group messages, money management, role management, and other members' money;
- base employee could still use personal FinDesk and own operational Field Combat / On the Go row;
- previous participant-control PASS remains accepted and was not reopened;
- QA did not change backend/API/UX/financial formulas and did not deploy.

Next owner: Project Director.

## Latest Status 2026-05-27 - Production Hotfix Recheck

Role: QA Release Engineer FinDesk

Task: production recheck after participant-control hotfix and default invited base employee rights hotfix.

Status: BLOCKED / P0.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/SUMMARY.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/production_hotfix_recheck_failure.json`

Result:

- participant-control production recheck passed on fresh fixture `group_id=17`, `report_id=176`;
- default base employee rights recheck is blocked on `message_unread` HTTP `500` for base employee fixture `group_id=18`, `report_id=184`, `base user_id=54`;
- QA did not change backend/API/UX/financial formulas and did not deploy.

Next owner: Backend Data Engineer.

## State

Hired. Initial office created. Final Business MVP QA evidence pack passed on 2026-05-27 after residual surface QA. Production smoke runbook for 100% MVP is prepared in `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`. SEO/PWA QA checklist for public FinDesk work is prepared in `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md`. Production HTTP/API smoke passed after Deploy Owner upload. Latest task: production multi-employee physical QA scenario in `TASK_CARD_PRODUCTION_MULTI_EMPLOYEE_2026-05-27.md` is BLOCKED by financial-control mismatch.

## Latest Status 2026-05-27

Role: QA Release Engineer FinDesk

Task: Production multi-employee money-flow QA.

Status: BLOCKED.

Evidence to read:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/SUMMARY.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/retrieved_final_artifacts.json`

Evidence:

- Production run completed through final report/archive: `group_id=8`, `report_id=66`.
- Artifacts saved under `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/`.
- Total expenses passed at `EUR 284`; group net balance passed at `EUR 716`.
- Employee 1 remaining `EUR 67` and employee 3 remaining `EUR 117` are present as open accountable remainders.
- Employee 2 overrun is visible in package audit refs as `expected_remaining=-36.00`, `difference_amount=36.00`.
- Blocker: final detail/package/export headline totals show `admin_cash_left=532`, not required `568`.

Next owner:

- Backend Data Engineer / Product Finance Architect.

## Latest Status 2026-05-27

Role: QA Release Engineer / SEO QA

Task: Local SEO/PWA QA after Frontend/PWA SEO implementation.

Status: BLOCKED for release/production acceptance; PASS for local non-visual SEO/PWA checks.

Evidence to read:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md`
- `docs/AI_TEAM/22_TECHNICAL_SEO_INFRA_CHECK.md`

Evidence:

- Existing local PHP server `http://127.0.0.1:18889` returned HTTP `200` for `/`, `/index.php`, `/robots.txt`, `/sitemap.xml`, `/manifest.webmanifest`, `/service-worker.js`, `/app.php`, OG/brand assets, Apple touch icon, and manifest icons.
- Public root raw HTML passed title, description, canonical `https://finance.brkovic.ltd/`, viewport, manifest, theme/Apple PWA meta, OG, Twitter, and root indexability checks.
- JSON-LD exists, parses successfully, references the public canonical identity, and did not expose `/app.php`, `/api.php`, `/storage/`, report/group ids, invite/token markers, or emails in the checked graph.
- `robots.txt` allows `/` and blocks `/app.php`, `/api.php`, `/storage/`; sitemap is valid XML and contains only `https://finance.brkovic.ltd/`; `/app.php` keeps `noindex,nofollow`.
- Manifest JSON parses locally and over HTTP; `id` and `start_url` remain `/app.php`, `scope=/`, `display=standalone`, required icons are present.
- Brand/social/icon assets exist and key referenced assets return HTTP `200`; `brand-og.png` is `1200 x 630`, manifest icons are `192 x 192`, `512 x 512`, and maskable `512 x 512`.
- `node --check public/assets/app.js`, `node --check public/service-worker.js`, served service-worker syntax check, and `git diff --check` passed.
- Service worker cache name is `findesk-20260522-v134`, bumped from `findesk-20260520-v10`, cleans old `findesk-*` caches, claims clients, and has no `fetch` handler.

Blocked / not executed:

- PHP lint was not executed because `php` CLI is not available in PATH, even though an existing local PHP server is running.
- Mobile `390 x 844` browser overlap check was not executed because Playwright/Puppeteer/browser binaries were not available in this shell.
- Production smoke was not executed. Production deploy remains NO-GO until DB/files backup, rollback controls, package evidence, and approved production smoke are provided.

Next owner:

- Project Director / Deploy Owner for DB/backup/rollback/package controls and browser-backed production SEO/PWA smoke.

Short report for Project Director (RU):

Роль: QA Release Engineer / SEO QA
Задача: локальная SEO/PWA QA проверка после Frontend/PWA SEO implementation.
Статус: BLOCKED для release/production acceptance; local non-visual SEO/PWA checks PASS.
Доказательство: `FINDINGS.md` обновлен; локальный сервер `127.0.0.1:18889` отдал `200` для public root, SEO files, manifest, service worker, app page и ключевых assets; meta/JSON-LD/robots/sitemap/manifest/SW checks прошли.
Блокер: production NO-GO до DB/files backup и rollback controls; mobile visual 390x844 не выполнен из-за отсутствия Playwright/browser; PHP lint не выполнен из-за отсутствия `php` CLI.
Следующий владелец: Project Director / Deploy Owner.

## Previous Status 2026-05-27

Role: QA SEO Release Engineer

Task: Prepare SEO/PWA QA checklist for new public SEO work and production smoke additions.

Status: PASS for checklist preparation; production smoke not executed; application code not changed.

Evidence to read:

- `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

Evidence:

- Read `public/index.php`, `public/app.php`, `public/robots.txt`, `public/sitemap.xml`, `public/manifest.webmanifest`, `public/service-worker.js`, `docs/AI_TEAM/20_LANGUAGE_POLICY_AUDIT.md`, and `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`.
- Prepared local after-implementation checklist for public HTTP 200, visible public page, title/description/canonical, JSON-LD parse presence, PWA manifest/install, robots/sitemap/noindex boundary, mobile rendering, service-worker cache update, and social preview assets.
- Prepared production smoke additions for SEO/PWA: HTTP 200, title/description/canonical, JSON-LD parse, robots, sitemap, manifest, noindex app boundary, mobile viewport, service-worker cache update, and social/brand asset existence.
- Recorded stop criteria: app indexed accidentally, API/storage open to crawl, canonical wrong, empty public page, broken manifest, missing brand/social/PWA assets.

Blocker:

- none for QA checklist preparation. SEO/PWA execution is pending Frontend/PWA SEO implementation; production smoke remains pending Deploy Owner production upload, URL, package evidence, backup evidence, and rollback evidence.

Next owner:

- Frontend/PWA SEO Engineer, then QA Release Engineer / Deploy Owner.

Short report for Project Director (RU):

Роль: QA SEO Release Engineer
Задача: подготовить SEO/PWA QA checklist для публичной SEO-работы и production smoke additions.
Статус: PASS для подготовки; production smoke не выполнялся; application code не менялся.
Доказательство: создан `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md`; в `FINDINGS.md` зафиксированы local checklist, production smoke additions и stop criteria для public SEO/PWA boundary.
Блокер: нет для подготовки; execution ждет Frontend/PWA SEO implementation и отдельный production deploy/smoke пакет.
Следующий владелец: Frontend/PWA SEO Engineer, затем QA Release Engineer / Deploy Owner.

## Latest Status 2026-05-27

Role: QA Production Smoke Coordinator
Task: Prepare production smoke runbook for 100% MVP after deploy.
Status: PASS for runbook preparation; production smoke not executed.
Evidence to read:
- `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`
- `docs/AI_TEAM/15_100_PERCENT_MVP_CONTROL.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`

Evidence:
- Created pre-smoke prerequisites covering production URL, smoke window, package selection, uploaded file/artifact evidence, safe smoke data, database backup, files backup, rollback owner, and rollback procedure.
- Defined exact must-pass post-deploy smoke order: deploy evidence, app shell/assets, authentication and `current_user`, mobile navigation, Field Capture no-data-loss, current report/export, historical final report list/detail/export, closed group package, group messages, Business Desk/proforma, Travel/Advanced staging, and final health check.
- Defined minimum browser matrix for mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.
- Defined minimum API paths for `current_user`, autosave/recover/proof retry, current report/export, final report list/detail/export, closed package, proof access, messages, Business Desk/proforma, and Travel/Advanced staging.
- Defined stop criteria and rollback triggers for production app load/session failure, data leakage, no-data-loss regression, duplicate proof retry money rows, current/final report merge, closed package/proof failure, message leakage, Business Desk ledger mutation, staging-surface interference, repeated 5xx, or missing backup/rollback evidence.
- Added Russian short report format for Project Director after smoke execution.

Blocker:
- none for runbook preparation. Production smoke execution is blocked until Deploy Owner provides production upload, production URL, selected package/file-list evidence, database backup evidence, files backup evidence, and rollback evidence.

Next owner:
- Project Director / Deploy Owner.

Short report for Project Director (RU):

Роль: QA Production Smoke Coordinator
Задача: подготовить production smoke runbook для 100% MVP после deploy.
Статус: PASS для подготовки runbook; production smoke не выполнялся.
Доказательство: создан `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`; в `STATUS.md` зафиксированы prerequisites, must-pass browser/API paths, stop criteria, rollback triggers и формат отчета после smoke.
Блокер: execution pending: production upload, production URL, selected package/file-list evidence, database/files backup evidence, rollback evidence.
Следующий владелец: Project Director / Deploy Owner.

## Latest Status 2026-05-27

Role: QA Release Engineer
Task: Production smoke plan for 100% MVP.
Status: PASS for plan preparation; production smoke not executed.
Evidence to read:
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`

Evidence:
- Prepared post-deploy production smoke checklist.
- Defined minimum browser checks for app shell/session, mobile navigation, Field Combat, current report/export, historical final report, closed group report package, proof access, messages, Business Desk, Travel, and Advanced.
- Defined minimum API checks for session, current report/export, final report list/detail/export, closed package, package proof, Field Combat draft recovery, messages, and Business Desk.
- Defined rollback evidence criteria and rollback trigger conditions.
- Defined must-pass checks before CEO can use production.
- `docs/AI_TEAM/14_PRODUCTION_DEPLOY_READINESS.md` was checked and was not present at planning time.

Blocker:
- none for QA plan preparation. Production smoke execution is pending Deploy Owner upload, production URL, package selection, and backup/rollback evidence.

Next owner:
- Project Director / Deploy Owner.

## Previous Status 2026-05-27

Role: QA Release Engineer
Task: Final Business MVP QA evidence pack.
Status: PASS.
Evidence to read:
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`

Evidence:
- Foundation/current-historical report evidence remains PASS: historical report/export `1000 / 600 / 400` and current open period `400 / 50 / 25` are separated in backend and UI evidence.
- Field Combat no-data-loss evidence remains PASS: final recheck run `20260526929348` kept original rows `176/178/180` exactly once, attached proof to originals, and kept previous `next_tape_id` cards `252/258/264` clean.
- Closed group report package evidence remains PASS: group `222`, report `454`, package endpoint/UI/proofs/print/immutability passed on mobile/tablet/desktop.
- Residual surface QA remains PASS: run `20260527968710`, group `222`, report `454`, messages, Business Desk/proforma, Travel staging, Advanced, and navigation reachability passed.
- Compact matrix is recorded in `FINDINGS.md` under `Final Business MVP QA Evidence Pack - 2026-05-27`.

Blocker:
- none from QA for the final Business MVP Chief Auditor gate. P1/non-blocking gaps and separate pre-production deploy gates remain recorded.

Next owner:
- Project Director.

## Previous Status 2026-05-27

Role: QA Release Engineer
Task: `Закрытый групповой отчет` package UI/API verification.
Status: PASS.
Evidence to read:
- `docs/AI_TEAM/roles/04_qa_release_engineer/HANDOFF_2026-05-27_CLOSED_GROUP_REPORT_PACKAGE_QA.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`

Evidence:
- fresh QA fixture `group_id=222`, `report_id=454`;
- admin `520`, member `521`;
- proof downloads passed through package URLs;
- mobile/tablet/desktop screenshots under `/tmp/findesk-closed-package-20260527816949-*`;
- UI is not summary-only and includes summary, participant reports, captures/proofs, money rows, accountable/advance state, messages, and audit refs;
- later current-period activity did not mutate the closed package.

Blocker:
- none from QA for this package pass. Full business MVP remains blocked until Chief Auditor gate.

Next owner:
- Chief Auditor.

## Previous Status 2026-05-26

Role: QA Release Engineer
Task: Field Combat proof retry duplicate-money recheck.
Status: PASS.
Evidence to read:
- `docs/AI_TEAM/roles/04_qa_release_engineer/HANDOFF_2026-05-26_FIELD_COMBAT_PROOF_RETRY_RECHECK.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`

Evidence:
- run id `20260526929348`;
- mobile/tablet/desktop groups `218/219/220`;
- original rows `176/178/180` stayed exactly once and received proof files;
- previous `next_tape_id` cards `252/258/264` did not receive duplicate money rows;
- no submit/include/finalize requests observed.

Blocker:
- none for this P0 recheck. Full product release readiness is still outside this single gate.

Next owner:
- Project Director.

## Fixed Positions

- Smoke test is required before and after risky work.
- Release cannot pass if numbers are technically correct but misleading.
- Manual device review is mandatory before release.

## Weak Spots To Inspect

- Personal/group scope confusion.
- Zero totals when wrong scope is selected.
- Old income appearing in current open period.
- Archive filters hiding employee reports.
- Text/table fit in exported spreadsheet.

## Next Work

1. Create `TEST_PLAN.md` for release candidate.
2. Run baseline smoke and record result.
3. Create manual scenario checklist for cash/card/advance/final report.
4. Add release evidence screenshots later if needed.

## Start Files

- `BEHAVIOR.md`
- `HANDOFF_2026-05-26.md`

## Director Assignment 2026-05-26

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: release readiness depends on evidence, not only code review. Current director baseline could not execute `php scripts/local-smoke.php http://127.0.0.1:18889` because CLI `php` is not available in this shell, while the local server responds `200 OK` at `http://127.0.0.1:18889`.
Request: create the first release test plan and formalize the `€1000 income -> €600 expense -> €400 carryover` scenario for cash, card, advances, final report fixation, archive, export, and device review.
Acceptance criteria:
- Baseline smoke status is recorded as passed, failed, or blocked with exact reason.
- Manual scenario lists setup, steps, expected result, actual result, and evidence needed.
- Desktop/tablet/mobile viewport targets are recorded.
- Release remains blocked until Product Finance Architect, Backend/Data, Frontend/UX, QA, and Chief Auditor evidence is complete.
Files/screens involved:
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`
- `scripts/local-smoke.php`
- `public/app.php`
- `public/index.php`
- `public/assets/app.css`

## Director Command 2026-05-26: Ordinary-Person Verification

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: QA must verify not only arithmetic, but whether a normal person can trace where money is and why each number is trustworthy.
Request: build a verification checklist for the human money map. The checklist must prove the `€1000 income -> €600 expense -> €400 carryover` scenario across administrator cash, employee accountable cash, cash expense, card expense, review, final report, archive, export, and proof links.
Acceptance criteria:
- `FINDINGS.md` records a QA checklist with expected and actual results.
- Each step asks: who holds or spent the money, where it is, what changed it, and where the proof is.
- Confusing but technically correct output is recorded as a release issue.
- Smoke remains blocked if CLI `php` is unavailable; server reachability is recorded separately.
Files/screens involved:
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`
- `scripts/local-smoke.php`
- `public/app.php`
- `public/assets/app.js`

## Director Command 2026-05-26: Field Movement QA

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: instant capture must be tested as a real movement scenario, not as a desktop accounting form.
Request: create a mobile-first QA scenario where a user receives money, records a cash purchase, records a card purchase, hands cash to an employee, adds a receipt later, submits for review, and verifies that final report numbers change only after acceptance.
Acceptance criteria:
- `FINDINGS.md` contains step-by-step mobile scenario.
- Scenario verifies save speed, incomplete record handling, proof attachment, review status, and final report inclusion.
- Scenario checks that card spending never changes physical cash.
- Scenario records failures where the user must stop moving to fill unnecessary fields.
Files/screens involved:
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`
- `public/app.php`
- `public/assets/app.js`
- `scripts/local-smoke.php`

## Verification Request 2026-05-26

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: first practical slice adds quick Live Report actions for people in movement.
Request: verify the new quick action strip on mobile/tablet/desktop and confirm that old Live Report requirements still hold.
Acceptance criteria:
- `node --check public/assets/app.js` passes.
- cash stream quick buttons insert cash income/expense line starts.
- card stream quick button inserts a card expense line start and does not allow card income.
- photo action opens the same proof picker path as the attachment controls.
- `Подотчет` opens the accountable-money screen and does not create an expense.
- saved card still opens with its exact rows in view mode.
- delete still removes the intended card and returns to list.
- cash submit sequence guard still blocks out-of-order submission.
Files/screens involved:
- `public/app.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `docs/AI_TEAM/06_CEO_IDEAS_REGISTRY.md`

## QA Update 2026-05-26: Instant Field Capture

Baseline:

- Working directory confirmed: `/home/alexey/GitHub/finance.brkovic.ltd`.
- HEAD and origin/main confirmed: `72b38e6`.
- Working tree is dirty as expected; no reset/checkout/clean was run.
- `php scripts/local-smoke.php http://127.0.0.1:18889` is blocked because CLI `php` is not available in this shell.
- Fallback server check passed: `curl -I --max-time 3 http://127.0.0.1:18889` returned `HTTP/1.1 200 OK`.
- `node --check public/assets/app.js` passed before and after browser QA.

Verified:

- Mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
- Cash stream quick buttons: `+ Получили`, `- Наличные`, `Фото`, `Подотчет`.
- Card stream quick buttons: only `- Карта` and `Фото`; cash income and cash expense actions hidden.
- Saved cash/card cards reopened in view mode with exact normalized rows.
- Pencil edit mode preserved rows and enabled editing.
- Delete from inside the opened draft card removed that exact card and returned to the list.
- `Фото` opened the same camera/proof picker path (`accept=image/*`, `capture=environment`).
- `Подотчет` opened `Деньги -> Подотчеты` and did not create a Live Report expense row.
- Card stream created only `noncash_out`, kept `cash_delta=0`, and did not change physical cash.
- Draft and submitted quick capture stayed out of final `ledger_report`; included/accepted card entered the report.
- Cash sequence guard blocked the second card while the first card waited in FinDesk and showed `Обработайте предыдущую запись в FinDesk.`

Evidence:

- Findings recorded in `FINDINGS.md`, run id `20260526141856`, QA user `qa.instant.20260526141856@example.test`, local user id `477`.
- Screenshots captured under `/tmp/findesk-qa-20260526141856-*.png`.
- Temporary QA cards and seed ledger entry were cleaned up after evidence capture.

Current release position:

- This assigned instant-capture slice passes QA evidence checks.
- Full release remains blocked until broader manual product QA is complete, especially the full `€1000 income -> €600 expense -> €400 carryover` scenario, export readability, archive behavior, and Chief Auditor gate.

## Director Assignment 2026-05-26: Historical Finalized Report Backend QA

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: Backend/Data implemented a separate historical finalized report/export source. New finalizations store `report_snapshot` in `audit_log.details`, return `report_id`, and expose `ledger_group_final_report_list`, `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, and `ledger_group_final_report_excel`. Current export must remain current open-period truth.
Request: verify the backend contract before Frontend/UX wires user-facing controls.
Acceptance criteria:
- `FINDINGS.md` records baseline commands and environment-blocked CLI PHP if still unavailable.
- QA creates a new `EUR 1000 income -> EUR 600 cash expense -> EUR 400 carryover` finalization and records `group_id` and `report_id`.
- Historical detail/export for the selected `report_id` returns `1000 / 600 / 400`.
- Current export after finalization starts from `400` carryover and does not show old `1000` as current income.
- A later current-period entry does not mutate the selected historical final report/export.
- Old finalizations without `report_snapshot`, if present, return `historical_snapshot_missing`.
Files/screens involved:
- `app/ledger.php`
- `public/api.php`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## Director Assignment 2026-05-26: Current Export Combo Recheck

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: Backend/Data fixed the current open-period export regression by releasing a PHP by-reference loop variable in `ql_ledger_group_open_received_funds()`. Backend/Data fixture: `group_id=194`, `report_id=364`, current income entry `88`, current Live Report tape `181`.
Request: rerun the combined regression scenario independently and decide whether the backend handoff can proceed to Frontend/UX.
Acceptance criteria:
- Historical final report detail/export remains `1000 / 600 / 400`.
- `ledger_group_open_received_funds.entries` returns the current income ledger row, not a Live Report tape row.
- Current export contains carryover `400`, current income `50`, and current Live Report expense `25`.
- Current export does not contain old finalized income `1000` as current income.
- Same-second cutoff edge is recorded separately if reproduced; it does not close this P0 unless it affects normal QA flow.
Files/screens involved:
- `app/ledger.php`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## Director Assignment 2026-05-26: Current And Historical Report UI QA

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: Frontend/UX implemented user-facing separation for current open-period report/export and historical finalized report/export. Current export remains on `group_id` endpoints, historical export uses explicit `report_id` endpoints.
Request: verify the user-facing flow on desktop/tablet/mobile before Chief Auditor MVP gate.
Acceptance criteria:
- `Текущий период` is visible and understandable as current open-period truth.
- `Экспорт текущего периода` uses current export behavior and does not imply old finalized income is current income.
- `Закрытые финальные отчеты` lists/selects historical reports by `report_id`.
- `Экспорт финального отчета` uses historical export by selected `report_id`.
- Scenario `1000 / 600 / 400`, then current income `50` and Live Report expense `25`, remains understandable from UI.
- No mobile text overlap, unreachable historical export action, or ambiguous merged export button.
- Full QA evidence is written in the QA folder; CEO / Project Director chat receives only a short report.
Files/screens involved:
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## Director Assignment 2026-05-26: Field Combat Mode QA Plan

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0 after Backend/Data and Frontend/UX outputs
Context: Product Finance Architect accepted Field Combat Mode as MVP foundation. Full no-data-loss evidence is still required before business MVP can be called complete.
Request: prepare the QA plan for Field Combat Mode and execute only after Backend/Data persistence mapping and Frontend/UX mobile behavior are posted.
Acceptance criteria:
- `FINDINGS.md` records a test plan for refresh, module switch, phone lock/return simulation, weak/offline-like network, partial proof upload failure, retry, recovery, recalculation, and deliberate submit.
- Any lost money fact, lost proof without visible failure, or silent finalization is marked P0.
- Evidence distinguishes MVP Field Combat Mode from `Advanced` non-MVP features.
- CEO / Project Director chat receives only a short report.
Files/screens involved:
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/HANDOFF_2026-05-26_FIELD_COMBAT_MODE_QA.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

## Director Assignment 2026-05-26: Field Combat UI No-Data-Loss QA

Date: 2026-05-26
From role: Project Director
To role: QA Release Engineer
Priority: P0
Context: Backend/Data implemented durable Field Combat APIs and Frontend/UX wired the active `Живой отчет` simple editor to autosave/proof-state endpoints. Business MVP remains blocked until browser/HTTP no-data-loss behavior is proven.
Request: run Field Combat UI QA for refresh recovery, module-switch recovery, proof failure/retry, idempotent save retry, and no silent submit/include/finalize.
Acceptance criteria:
- lost typed fact after `Сохранено` is P0;
- proof failure without persistent `failed` or `retry_needed` state is P0;
- duplicate row after retry is P0;
- autosave submit/include/finalize is P0;
- evidence covers mobile/tablet/desktop.
Files/screens involved:
- `docs/AI_TEAM/roles/04_qa_release_engineer/HANDOFF_2026-05-26_FIELD_COMBAT_UI_QA.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

## QA Update 2026-05-26: Field Combat UI No-Data-Loss

Current slice status: blocked, P0.

Browser/HTTP evidence:

- Playwright Chromium covered mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.
- Evidence run id: `20260526264416`.
- QA groups: mobile `204`, tablet `205`, desktop `206`.
- Original durable drafts remained recoverable by backend, but the UI returned to a new empty draft after browser refresh/return.

Blocker:

- After typing `-25 Durable autosave row ...` and seeing `Сохранено`, refresh/return through the visible cash-stream path showed an empty editor.
- The UI replaced the stored `client_draft_id` with a new empty draft, while direct `on_the_go_field_recover` by the original `client_draft_id` still returned the typed row.
- This violates the handoff rule: lost typed fact after `Сохранено` is P0.

Not accepted yet:

- proof upload failure/retry;
- idempotent save retry;
- full cash/card stream separation for this Field Combat no-data-loss slice.

Current release position:

- Field Combat UI browser/HTTP no-data-loss QA is not accepted.
- Full release remains blocked; no release-ready statement is made.

## QA Update 2026-05-26: Field Combat Draft Recovery Identity Recheck

Current slice status: blocked, P0.

Browser/HTTP evidence:

- Playwright Chromium covered mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.
- Evidence run id: `20260526109674`.
- QA groups: mobile `210`, tablet `211`, desktop `212`.
- `node --check public/assets/app.js` passed; local server answered `HTTP/1.1 200 OK`.

Accepted in this recheck:

- Old QA run `20260526264416` blocker is fixed: after `Сохранено`, refresh/return restores the typed cash row and keeps the original durable `client_draft_id` on all three viewports.
- Module switch/return also restores the same row/session.
- Same cash-stream reselection does not replace the recoverable active draft.
- Forced proof upload failure persists as `retry_needed`.
- Repeating the original `client_operation_id` returns idempotent response.
- Card stream separation passed: `noncash_out`, `card_out=12`, `cash_delta=0`, `cash_left=0`.
- No submit/include/finalize action was observed; rows stayed `reportable=0`.

Blocker:

- Proof retry duplicates the same cash money fact into a second draft card after refresh.
- Mobile: original tape `227` row `167` plus retry tape `226` row `168`.
- Tablet: original tape `232` row `170` plus retry tape `231` row `171`.
- Desktop: original tape `237` row `173` plus retry tape `236` row `174`.
- After proof failure refresh, localStorage kept the same `client_draft_id` but changed `tape_id` to the previous `next_tape_id`, so retry saved `-25` again instead of attaching proof to the original row/card.

Current release position:

- Field Combat draft recovery identity recheck is not accepted because duplicate money row after retry is P0.
- Full release remains blocked; no release-ready statement is made.

## QA Update 2026-05-27: Closed Group Report Package QA

Current slice status: pass.

Browser/HTTP evidence:

- Playwright Chromium covered mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.
- Evidence run id: `20260527816949`.
- Fresh QA package fixture: group `222`, report `454`, admin `qa-closed-package-admin-20260527816949@example.test`, member `qa-closed-package-member-20260527816949@example.test`.
- Screenshots are listed in `FINDINGS.md`.

Verified:

- package opens by `report_id` through `ledger_group_final_report_package`;
- UI object is labeled `Закрытый групповой отчет #454` and `Один архивный объект`;
- package is not summary-only: summary, participant reports, captures/proofs, money rows, accountable/advance state, report-context/general message refs, and audit refs are visible;
- package proof downloads work through `ledger_group_final_report_proof_download` for authorized reviewer;
- cash/card split holds: physical cash spent `640`, card/noncash spent `70`, and card rows do not reduce physical cash;
- accountable remains responsibility/carryover: accepted spend `40`, open remaining `60`;
- print/PDF action includes package sections;
- Excel/Google are labeled and verified as short final-report tables, not full package exports;
- later current-period activity did not mutate the closed package;
- mobile/tablet/desktop layout had no blocking overlap or unreachable package actions.

Current release position:

- Closed group report package QA slice passes.
- Full release still requires Chief Auditor gate; no full release-ready statement is made here.

## QA Update 2026-05-27: Business MVP Residual Surface QA

Current slice status: pass.

Browser/HTTP evidence:

- Evidence run id: `20260527968710`.
- Anchor package: group `222`, report `454`.
- Users: admin `qa-closed-package-admin-20260527816949@example.test`, member `qa-closed-package-member-20260527816949@example.test`, non-member `qa-residual-outsider-20260527968710@example.test`.
- Playwright Chromium covered mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.
- Screenshots are listed in `FINDINGS.md`.

Verified:

- group message send/list/unread/mark-read works and is group-scoped; non-member access returns `not_group_member`;
- `Закрытый групповой отчет #454` still presents report-context message refs and clearly marks unlinked group discussion;
- Business Desk is reachable; company/client/proforma create/list/open/print works;
- Business Desk/proforma operations did not change group `222` operational `ledger_report`;
- Travel / Trip with Friends remains visible as `Подготовлено` staging;
- Advanced remains reachable as non-MVP staging;
- On the Go, report review/finalization area, closed package, group messages, Business Desk/proforma, Travel, and Advanced are reachable on mobile/tablet/desktop without blocking overlap in the checked path.

Current release position:

- Business MVP residual surface QA slice passes.
- Next owner is Project Director for final routing; no full release-ready statement is made here.
