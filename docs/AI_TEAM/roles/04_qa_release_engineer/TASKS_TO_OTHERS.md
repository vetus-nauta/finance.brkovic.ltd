# Tasks To Others: QA Release Engineer

## To Project Director / Deploy Owner - Live Report Records Recheck Passed

Date: 2026-05-28
From role: QA Release Engineer
Priority: P0 release routing
Context: recheck after Frontend/UX fix passed on run `20260528RECORDSRECHECK04`.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Recheck P0 Live Report Records Page After Frontend UX Fix`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_recheck_20260528/live_records_recheck_result.json`
- screenshots listed in `FINDINGS.md`

Result:

- previous Frontend/UX P0 is closed for the checked local frontend assets;
- admin sees employee Live Report card in ordinary records page on mobile/tablet/desktop;
- stream gate no longer overlays records page in QA recheck; Director smoke also confirmed `otr-stream-gate-open` does not remain on `390x844`;
- clicks on employee card work from the ordinary list without force/direct-open fallback;
- proof buttons, inline image/PDF viewer, close action, and no-download open link passed;
- base employee isolation passed.

Request:

- decide production deploy route for the Frontend/UX fix;
- after deploy, run a short production smoke against deployed asset version to confirm the same records-list behavior on `https://finance.brkovic.ltd`.

Next owner: Project Director / Deploy Owner.

## To Frontend/UX - P0 Live Report Records Page After Proof Links Hotfix

Date: 2026-05-28
From role: QA Release Engineer
Priority: P0
Context: production QA run `20260528LIVEPROOFLINKSQA01` confirms Backend/Data proof access works, but records-page UI is not release-clean.
Latest deployed viewer hotfix is acknowledged: asset version `20260528-proof-viewer1`, smoke `prod-proof-viewer-20260528154804`.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - P0 Production Live Report Records Proof Links QA`
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_proof_links_20260528/live_records_proof_links_result.json`
- screenshots listed in `FINDINGS.md`

P0 request:

- make the group admin records page discover employee Live Report cards with proofs; checked failure fixture: `group_id=31`, `tape_id=95`, `capture_id=151`, `file_id=20/21`;
- verify on mobile `390x844`, tablet `820x1180`, desktop `1440x900`;
- keep each row with proof showing visible proof controls/links without requiring direct function/API open.

Additional Frontend/UX cleanup:

- fix `otrStreamGate` covering/restoring over records list state;
- fix mobile overflow/clipping for long card titles, proof labels, and the primary card-modal action;
- remove or split the proof viewer `download` attribute from the `Открыть` new-tab link so Safari/PWA behavior is clearly "open" rather than "download";
- recheck inline image/PDF viewer with real receipt/photo content, not only synthetic tiny proof bodies.

No Backend/Data task opened:

- owner/admin file list and file download passed on production API;
- anonymous proof download denied with HTTP `401`;
- proof responses were inline and non-empty.

Next owner: Frontend/UX.

## To Project Director / Deploy Owner / Database Migration Owner - Candidate 34 Deploy Preflight

Date: 2026-05-28
From role: QA Release Engineer
Priority: P0 before upload
Context: deploy-preflight sprint QA update for candidate 34 is complete. Local PASS remains valid, but production is still NO-GO.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/DEPLOY_READINESS_CHECKLIST_2026-05-28.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Deploy Preflight Sprint QA Update For Candidate 34`

Required deploy bundle:

- `app/groups.php`;
- `app/ledger.php`;
- `app/on_the_go.php`;
- `public/api.php`;
- `public/app.php`;
- `public/assets/app.js`;
- `public/assets/app.css`;
- `public/assets/i18n.js`;
- `deploy/on_the_go_foundation.sql`;
- `deploy/on_the_go_sessions_runtime.sql`.

Verification-only:

- `scripts/local-smoke.php`.

Requests:

- freeze the exact upload list before touching production;
- complete DB preflight for scanner columns;
- record DB/files backup and rollback references;
- run PHP lint or approved HTTP/API replacement smoke;
- after upload, run production smoke from the updated checklist;
- keep scanner device-ready claim blocked until real-device/PWA camera QA passes or CEO approves narrower release wording.

QA rerun decision:

- QA does not need another local recheck now if candidate 34 file bundle is unchanged;
- QA does need a production smoke after DB preflight, backup, upload, and SQL/application.

Next owner: Project Director / Deploy Owner / Database Migration Owner.

## To Project Director / Deploy Owner - After Formal Local Recheck

Date: 2026-05-28
From role: QA Release Engineer
Priority: P0 for release routing
Context: formal local recheck after Frontend/UX leftovers fix and Backend/Data `group_delete` hardening passed on run `20260528LOCALLEFTOVERS01`.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/local_leftovers_recheck_20260528/SUMMARY.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` section `Verification 2026-05-28 - Formal Local Recheck After Frontend Leftovers And Group Delete Hardening`

Request:

- decide whether this local PASS is enough to include the leftovers/group-delete patch in the next deploy bundle;
- keep real-device scanner/PWA camera gate separate from this local recheck;
- if production deployment is selected, execute the deploy-readiness checklist before upload.

No new tasks opened:

- no Backend/Data blocker from `group_delete`;
- no Frontend/UX blocker from static/HTTP leftovers check.

Known limitation:

- browser screenshot overlap acceptance was not run in this shell because browser automation modules were unavailable.

Next owner: Project Director / Deploy Owner.

## To Project Director / Deploy Owner / Database Migration Owner - Limited Scanner Release Deploy Gate

Date: 2026-05-28
From role: QA/Deploy Release Engineer
Priority: P0 before next production deploy
Context: business-MVP product gate, production base rights, and local Receipt Scanner file-input gate are closed, but production scanner deploy readiness is not closed.

Evidence:

- `docs/AI_TEAM/roles/04_qa_release_engineer/DEPLOY_READINESS_CHECKLIST_2026-05-28.md`

Request:

- freeze the exact selected file bundle before upload;
- complete real-device scanner QA or explicitly approve a release boundary without camera/PWA scanner claim;
- prove PHP CLI smoke availability or approve the HTTP/API replacement smoke;
- complete scanner DB migration preflight for proof role/bundle/hash/metadata columns;
- record production DB/files backup and rollback procedure before upload.

Required file-bundle decision:

- candidate runtime files: `app/on_the_go.php`, `app/ledger.php`, `public/api.php`, `public/app.php`, `public/assets/app.js`, `public/assets/app.css`, `deploy/on_the_go_foundation.sql`, `deploy/on_the_go_sessions_runtime.sql`;
- candidate verification-only file: `scripts/local-smoke.php`;
- exclude docs, temporary files, test artifacts, reset helpers, and unrelated dirty-tree changes unless explicitly added by Deploy Owner.

Production smoke required after deploy:

- app shell and assets;
- auth/current_user;
- scanner/Field Combat save, upload, retry, refresh, final package proof chain;
- current vs historical report/export split;
- closed group package and authorized proof download;
- default base employee group isolation and personal FinDesk availability.

Stop condition:

- if any P0 item remains open, QA position is production NO-GO for this limited release.

Next owner: Project Director / Deploy Owner / Database Migration Owner.

## To Project Director - Receipt Scanner Device/PWA Follow-up

Date: 2026-05-28
From role: QA Release Engineer
Priority: P1 before device-level scanner acceptance
Context: local browser/HTTP Receipt Scanner QA passed on run `20260528RSQA01`. Headless Chromium verified file-input scanner flow on `390x844`, `820x1180`, and `1440x900`, including `scanner_original` and `scanner_cleaned_pdf` idempotency.

Request: decide whether to schedule real-device QA for physical camera capture and installed PWA behavior before production release wording includes mobile scanner acceptance.

Required real-device coverage:

- iPhone Safari PWA installed/standalone camera capture;
- Android Chrome camera capture;
- camera permission denied/retry path;
- camera capture followed by refresh/return;
- repeated save/retry confirms no duplicate `scanner_original`, no duplicate `scanner_cleaned_pdf`, and no duplicate money row.

Current QA routing:

- no new Backend/Data task from local run;
- no new Frontend/UX task from local run;
- full release ready was not declared.

## To Project Director / QA Release Engineer - Receipt Scanner QA Execution

Date: 2026-05-28
From role: QA Release Engineer
Priority: P0 before Receipt Scanner release acceptance
Context: Receipt Scanner QA matrix is prepared in `FINDINGS.md`. Current product context treats `scan` as proof/photo/file attachment, not automatic OCR or edge detection, unless a later Product/Backend/Frontend handoff explicitly changes scope.

Request: schedule and execute the Receipt Scanner QA matrix on the selected target build without changing runtime code during QA.

Required coverage:

- iPhone Safari PWA installed/standalone;
- Android Chrome;
- desktop upload with existing JPG/PNG/PDF receipt;
- poor light / low contrast;
- glare / reflection;
- crumpled receipt;
- busy background behind receipt;
- refresh before upload;
- refresh after upload and before submit/include/finalize;
- offline before upload;
- offline during upload, then retry;
- original proof availability from the card/report;
- printable/PDF final report availability;
- no duplicate money rows after retry, refresh, back button, or repeated click;
- archive/final report proof access by explicit `report_id`.

Acceptance criteria:

- every matrix row `RS-01` through `RS-14` in `FINDINGS.md` is marked PASS, FAIL, or BLOCKED with device/browser, run stamp, group_id, report_id, tape/card id, row id, proof id, and evidence pointer;
- original receipt proof remains available after save, review, finalization, archive/package open, and authorized proof download;
- printable/PDF final report path remains available and references the proof context clearly enough for audit;
- refresh before upload preserves the typed money draft and does not show a phantom proof;
- refresh after upload preserves the proof or visible retry-needed state on the same row/card;
- offline/retry keeps the same draft/row identity and does not create duplicate money rows;
- unauthorized proof/archive access is denied;
- if scanner/OCR behavior is added later, it cannot mutate accounting truth without explicit user confirmation.

P0 stop criteria:

- lost typed money fact after visible save;
- lost original receipt/proof after upload/save success;
- duplicate money row after retry/refresh/offline/repeated click;
- authorized reviewer cannot access final/archive proof by `report_id`;
- unauthorized user can access receipt proof or final archive;
- silent OCR/accounting mutation without user confirmation.

Next routing:

- Frontend/PWA owns capture UI, device/PWA install behavior, visible state, refresh recovery, and retry UX failures.
- Backend/Data owns proof persistence, idempotency, duplicate row prevention, final report/package proof metadata, and authorization failures.
- Project Director owns target build/device window and release decision after QA evidence is posted.

## To Frontend/PWA QA Task

Date: 2026-05-27
From role: Web Designer
Priority: P2
Context: visual branding alignment task completed in `public/index.php`, `public/app.php`, `public/assets/app.css`.

Request: run local/browser visual QA for branding:

- `index.php` hero/logo area and brand pill on mobile/tablet/desktop;
- `app.php` brand pill and top app shell on mobile/tablet/desktop;
- favicon/icon metadata availability.

Acceptance:

- `390x844`: логотип и иконка без смещения/обрезки;
- `820x1180`: логотип и иконка без смещения/перескакивания;
- `1440x900`: логотип и иконка визуально стабильны;
- favicon links for `16x16`, `32x32`, `192x192`, `512x512`, `apple-touch` are valid in markup and load;
- mobile one-hand entry screens still usable.

Evidence:

- screenshots in QA artifacts:
  - `/tmp/findesk-web-designer-20260527/index-mobile390x844.png`
  - `/tmp/findesk-web-designer-20260527/app-mobile390x844.png`
  - `/tmp/findesk-web-designer-20260527/index-tablet820x1180.png`
  - `/tmp/findesk-web-designer-20260527/app-tablet820x1180.png`
  - `/tmp/findesk-web-designer-20260527/index-desktop1440x900.png`
  - `/tmp/findesk-web-designer-20260527/app-desktop1440x900.png`
- exact details in FINDINGS.

Result (2026-05-27): PASS. Browser matrix completed on all 3 viewports; no blocking visual regressions in logo/brand or auth-entry screens; favicon/icon links are present and load.

Evidence pointer:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/web_designer_branding_20260527/SUMMARY.md`

Afterpass: report result to Project Director in short format from `SHORT_REPORT_TEMPLATE.md`.

## No New Backend/Data Task

Date: 2026-05-27
From role: QA Release Engineer
Context: production rerun default base employee rights after backend `message_unread` alias hotfix. Fresh fixture `group_id=20`, `report_id=194`, base employee `user_id=59`, run stamp `20260527212947`.

Result: PASS. `message_unread` returns HTTP `200`, `ok=true`, `unread_count=0`; base employee remains denied from group exports, final reports/package/export, group messages, money management, role management, and other members' money; own operational capture and personal FinDesk use remain available.

Next owner: Project Director.

## To Backend Data Engineer

Date: 2026-05-27
Priority: P0 production rights blocker
Context: QA production hotfix recheck passed participant-control, but default base employee rights recheck is blocked. Fixture: `group_id=18`, `report_id=184`, base employee `user_id=54`, run stamp `20260527211338`. Evidence folder: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/`.

Request: fix `message_unread` so default base employees get a safe non-leaking response instead of HTTP `500`.

Actual:

- base employee is correctly denied `message_list` and `message_send`;
- `message_unread` returns `server_error` / HTTP `500`;
- error points to SQL syntax near alias `current_role`.

Expected:

- base employee `message_unread` returns `ok=true`;
- `unread_count=0`;
- no messages from the group are exposed to base employee;
- manager/admin unread flow remains working;
- no financial formulas are changed.

After fix: return to QA Release Engineer for production rights slice rerun.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 production rights recheck
Context: Default invited employee rights were hardened and deployed. Director production fixture `group_id=10`, employee user `27`, stamp `20260527210337` passed.

Request: independently recheck that a default invited `base` employee has only operational capture/self-control in the group and no group data access.

Acceptance criteria:

- invite without manual role escalation creates `access_level=base`;
- employee can save own On the Go / Field Combat operational rows in the group;
- employee starting cash/self-control does not include admin/group cash;
- employee cannot open group report export, final reports, archive/package, group messages, money management, role management, or other participants' money;
- employee sees only own operational cards/accountable data;
- manager/admin access still works.

Short report format:

- Role;
- Task;
- Status;
- Evidence pointer;
- Blocker;
- Next owner.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 production recheck
Context: Product Finance confirmed the correct participant-control equation for the multi-employee scenario. Backend/Project Director patched and deployed runtime. Local HTTP fixture `group_id=223`, `report_id=499` passed. Director production smoke fixture `group_id=9`, `report_id=84` passed: `admin_cash_left=568`, employee positive remaining `184`, employee reimbursement due `36`, employee net `148`, balance `716`.

Request: rerun, inspect, or reproduce the production scenario as QA Release Engineer:

- admin receives `1000`;
- admin issues `135`, `94`, `117`;
- admin spends `20`, `45`, `17`, `4`;
- employee 1 spends `6`, `9`, `43`, `10`;
- employee 2 spends `12`, `23`, `41`, `54`;
- employee 3 spends nothing;
- finalize, export, save package/archive.

Acceptance criteria:

- final detail/package/export/print show expenses `284` and balance `716`;
- admin physical cash left is `568`;
- employee 1 remaining is `67`;
- employee 2 remaining is `-36` and reimbursement due is `36`;
- employee 3 remaining is `117`;
- headline/package/accountable/export surfaces show the equation `568 + 67 - 36 + 117 = 716` or equivalent first-class fields;
- employee 2 overrun is not audit-only;
- archive/package remains accessible by `report_id`.

Short report format:

- Role;
- Task;
- Status;
- Evidence pointer;
- Blocker;
- Next owner.

## To Backend Data Engineer / Product Finance Architect

Date: 2026-05-27
Priority: P0 production acceptance blocker
Context: Production multi-employee QA completed on `https://finance.brkovic.ltd` with `group_id=8`, `report_id=66`. Required control is `admin cash left 568`, employee 1 `67`, employee 2 overrun `-36` / reimbursement due `36`, employee 3 `117`, and group balance `716`.
Request: fix or explicitly redefine the final report/package financial-control representation so employee overrun participates in the headline participant-control equation.
Evidence: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/`.
Actual: final detail/package/export headline totals show `admin_cash_left=532`, `employee_cash_left/accountable_money_left=184`, `cash_balance=716`. Employee 2 overrun `36` is preserved only in package audit refs as `expected_remaining=-36.00`, `difference_amount=36.00`.
Expected: final package/detail/export must preserve the requested control equation `568 + 67 - 36 + 117 = 716`, or otherwise expose equivalent first-class fields for admin cash, positive employee remainders, and employee reimbursement due.
Acceptance criteria:
- total expenses remains `284`;
- group net balance remains `716`;
- admin cash left is shown as `568` for this scenario, or Product Finance updates the accepted control model before release;
- employee 1 remaining `67` remains visible;
- employee 2 overrun/reimbursement `36` is visible outside raw audit refs in final package/detail/export;
- employee 3 no-spend remaining `117` remains visible without a fake expense;
- package participant/accountable sections make the multi-employee control understandable without reconstructing it from audit JSON.

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 production acceptance scenario after MVP deploy
Context: CEO requested a physical production QA run with one admin, three employees, accountable cash, individual spends, saved exports, final group report, and archive verification.
Request: execute `TASK_CARD_PRODUCTION_MULTI_EMPLOYEE_2026-05-27.md`.
Acceptance criteria: one full report in the QA role folder; one short Russian report to Project Director only; expected totals `EUR 284` expenses and `EUR 716` net group balance; employee 2 overrun `EUR 36` and employee 3 no-spend remainder `EUR 117` must not be lost.

## To Project Director / Deploy Owner

Date: 2026-05-27

Priority: P0 before SEO/PWA release acceptance

Context: local non-visual SEO/PWA QA after Frontend/PWA SEO implementation passed the machine-checkable checks, but full release acceptance is still blocked. Production deploy remains NO-GO under existing DB/backup/rollback controls, production smoke was not executed, and this shell has no Playwright/Puppeteer/browser runtime for the required mobile visual overlap check.

Request: provide the missing release controls and a browser-backed smoke path before accepting SEO/PWA for production.

Acceptance criteria:

- database backup evidence is recorded without secrets;
- files/storage backup evidence is recorded without secrets;
- rollback owner and rollback procedure are recorded without secrets;
- deploy package includes or proves byte-equivalent current `public/index.php`, `public/robots.txt`, `public/sitemap.xml`, `public/manifest.webmanifest`, `public/service-worker.js`, public app/asset files, icons, and brand/social images;
- browser-backed mobile `390 x 844` check confirms public landing and install modal/path have no blocking overlap;
- production SEO/PWA smoke records HTTP 200, raw meta, JSON-LD parse, robots/sitemap boundary, manifest/install fields, app `noindex,nofollow`, service-worker update behavior, and referenced brand/social/PWA asset fetches.

## No New Frontend/PWA SEO Remediation Task

Date: 2026-05-27

From role: QA Release Engineer / SEO QA

Context: local non-visual SEO/PWA QA after Frontend/PWA SEO implementation.

Result: no new application-code defect is opened from the machine-checkable local checks. Public root HTTP/meta/JSON-LD, robots/sitemap/noindex boundary, manifest, brand/social/icon assets, service-worker syntax/cache sanity, `node --check`, and `git diff --check` passed. Remaining blockers are environment/release controls: no browser runtime for mobile visual overlap proof, no `php` CLI for lint, and production deploy NO-GO until DB/backup/rollback/package/smoke evidence exists.

## To Frontend/PWA SEO Engineer

Date: 2026-05-27

Priority: P0 for the new public SEO work

Context: QA prepared the SEO/PWA checklist for the public FinDesk surface. SEO belongs to the public PWA entry, while `/app.php`, `/api.php`, and `/storage/` must remain outside the indexable surface.

Request: after implementing the SEO/PWA public changes, run the local checklist in `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md` and provide evidence before production deploy.

Acceptance criteria:

- `/` returns HTTP 200 locally and renders a non-empty public FinDesk page;
- title, description, canonical, viewport, manifest link, OG/Twitter tags, and brand asset links are correct in raw HTML;
- canonical is `https://finance.brkovic.ltd/` and does not point to `/app.php`, localhost, staging, API, storage, or `/index.php`;
- parseable JSON-LD exists on the public root unless Project Director explicitly removes JSON-LD from scope;
- JSON-LD contains public FinDesk identity only and exposes no app/user/report/API/storage data;
- manifest is valid JSON, start URL remains `/app.php`, icons fetch successfully, and install flow is not broken;
- `robots.txt` allows `/` and disallows `/app.php`, `/api.php`, and `/storage/`;
- sitemap contains public canonical URLs only and excludes app/API/storage/test/local/staging URLs;
- `/app.php` keeps `noindex,nofollow`;
- mobile `390 x 844` public page and install path are usable without blank screen or blocking overlap;
- service-worker/cache versioning is handled if public assets, manifest, index, or icons changed;
- social preview and brand assets exist and fetch successfully.

## To Project Director / Deploy Owner

Date: 2026-05-27

Priority: P0 before SEO/PWA production acceptance

Context: production smoke runbook already exists for the 100% MVP. QA added SEO/PWA smoke additions in `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md`; production smoke has not been executed.

Request: when the SEO/PWA package is deployed, include the SEO/PWA additions in production smoke and stop on any SEO/PWA stop criterion.

Acceptance criteria:

- production HTTP 200 is recorded for `/`, SEO files, manifest, service worker, `/app.php`, and referenced social/PWA assets;
- production raw HTML confirms title, description, and canonical `https://finance.brkovic.ltd/`;
- production JSON-LD exists and parses successfully;
- production `robots.txt` allows public root and blocks `/app.php`, `/api.php`, `/storage/`;
- production `sitemap.xml` is valid, public-only, and excludes app/API/storage;
- production manifest is valid and installable with working icons;
- `/app.php` remains `noindex,nofollow` and is not listed as an SEO URL;
- mobile `390 x 844` public page is not blank and app entry remains usable;
- service-worker/cache update is verified for returning browser/PWA clients;
- OG/Twitter image, brand mark, favicon, Apple touch icon, and manifest icons return HTTP 200;
- production smoke is stopped and routed to rollback/hold review if app is indexed accidentally, API/storage are open to crawl, canonical is wrong, public page is empty, manifest is broken, or brand assets are missing.

## To Project Director / Deploy Owner

Date: 2026-05-27
Priority: P0 before CEO production use
Context: QA prepared the production smoke plan for 100% MVP. Production smoke has not been executed and production upload was not performed by QA. Business-MVP product QA is passed locally, but deploy readiness remains separate.
Request: before CEO uses production, choose the deploy package mode, upload only the approved package, provide production URL, and run or route the production smoke checklist recorded in `FINDINGS.md`.
Acceptance criteria:
- deploy package mode is recorded: full current working-tree bundle or narrow MVP runtime bundle;
- exact uploaded file/artifact list is recorded;
- pre-deploy file backup and database backup evidence is recorded without secrets;
- rollback owner/procedure is recorded without secrets;
- production app shell/session passes;
- mobile `390 x 844` reaches the MVP money loop without blocking overlap;
- Field Combat saved draft survives refresh/return without silent submit/include/finalize or duplicate money row in the exercised retry path;
- current period report/export remains separate from selected historical final report/export;
- one closed final report opens by explicit `report_id`;
- one `Закрытый групповой отчет` opens as a full archive package with print/PDF and package sections;
- authorized package proof access works for a safe smoke proof, or Project Director explicitly holds CEO production use until a safe proof fixture exists;
- group messages remain group-scoped;
- Business Desk proforma preview/print is reachable and does not mutate operational ledger/report totals;
- Travel and Advanced remain reachable without interfering with the core money loop;
- no unresolved P0 production smoke failure remains.

## No New Backend/Data Or Frontend/UX Remediation Task

Date: 2026-05-27
From role: QA Release Engineer
Context: production smoke plan for 100% MVP.
Result: no new application-code defect is opened from planning alone. Existing product QA evidence remains PASS; production smoke execution may open new tasks only if the deployed production package fails the checklist.

## No New Cross-Role Tasks

Date: 2026-05-26
From role: QA Release Engineer
Context: Field Combat proof retry duplicate-money recheck after Frontend/UX fix.
Result: PASS on mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`. Evidence run `20260526929348`: groups `218/219/220`, original rows `176/178/180`, previous `next_tape_id` cards `252/258/264`. Proof retry attached proof to the original saved rows and did not create duplicate money rows. No `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` request was observed. The previous Frontend/UX P0 task is accepted fixed by QA. No new Backend/Data or Frontend/UX task is opened from this recheck.

## To Backend Data Engineer

Date: 2026-05-23
Priority: P0
Context: automated regression.
Request: expose or document a repeatable test fixture for final report fixation and carryover.
Acceptance criteria: QA can verify old report and open period in one predictable run.

## To Frontend UX Engineer

Date: 2026-05-23
Priority: P1
Context: visual QA.
Request: provide target viewports for desktop/tablet/mobile checks.
Acceptance criteria: QA can repeat layout review consistently.

## No New Cross-Role Tasks

Date: 2026-05-26
From role: QA Release Engineer
Context: instant field capture verification in Live Report.
Result: no new Backend/Data, Frontend/UX, Product Finance, or Chief Auditor task was opened from this slice. Cash/card quick capture, saved-card reopen, delete correctness, proof picker path, `Подотчет` navigation, review gate, physical-cash separation, and cash sequence guard passed the recorded QA checks.

## To Backend Data Engineer

Date: 2026-05-26
Priority: P0
Context: historical finalized report backend QA before Frontend/UX handoff. New `report_snapshot` final reports pass historical detail/export, but current open-period export is not stable when post-finalization income and a current included Live Report coexist.
Evidence: QA group `192`, final report `348`, current income ledger entry `84`, current Live Report tape `175`. After finalization and later current entries, `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, and `ledger_group_final_report_excel` still correctly return the historical `1000 / 600 / 400` snapshot. However current `ledger_group_google_sheet` omits the later current income `current income 20260526153406-combo`; `ledger_group_open_received_funds` returns `entries: [{"id":175}]` instead of the current income row, while `open_period.live_included` reports `cards=1`, `cash_expense=25`, `records=1`.
Request: fix current open-period export so it preserves all post-finalization received-funds rows while also adding current included Live Report aggregates. Verify the fix against `EUR 1000 income -> EUR 600 cash Live Report expense -> finalize -> EUR 400 carryover -> EUR 50 current income -> EUR 25 current included Live Report expense`.
Acceptance criteria: current export contains carryover `400`, current income `50`, current Live Report expense `25`, no old finalized `1000` as current income, and selected historical final report/export remains unchanged at `1000 / 600 / 400`.

Recheck result: accepted fixed on 2026-05-26 by QA/Release. Fresh evidence: group `195`, final report `371`, current income ledger entry `90`, current Live Report tape `184`. `ledger_group_open_received_funds.entries` returned the current income ledger row, current export contained carryover `400`, current income `50`, current Live Report expense `25`, and excluded the old finalized income. Historical detail/export remained `1000 / 600 / 400`. No new Backend/Data task is opened for this combo regression.

## To Backend Data Engineer

Date: 2026-05-26
Priority: P1
Context: legacy finalization fallback remains unproven through API because QA does not have an accessible old finalization without `details.report_snapshot`.
Request: provide a repeatable local fixture or accessible `report_id` + member email for a `ledger_group_report_finalized` audit row that lacks `report_snapshot`.
Acceptance criteria: QA can call `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, and `ledger_group_final_report_excel` for that accessible legacy report and record `historical_snapshot_missing` through the public backend contract.

## No New Cross-Role Tasks

Date: 2026-05-26
From role: QA Release Engineer
Context: combo regression recheck after Backend/Data fix for `1000 income -> 600 Live Report expense -> finalize -> carryover 400 -> current income 50 -> current Live Report expense 25`.
Result: recheck passed. The previous P0 combo blocker is accepted fixed for the backend contract check; no new Backend/Data or Frontend/UX task is opened from this recheck. The separate P1 legacy `historical_snapshot_missing` fixture request remains open.

## No New Cross-Role Tasks

Date: 2026-05-26
From role: QA Release Engineer
Context: current/historical report UI QA on desktop/tablet/mobile for `1000 income -> 600 Live Report expense -> finalized report -> carryover 400 -> current income 50 -> current Live Report expense 25`.
Result: UI separation passed. `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, selected `report_id`, and `Экспорт финального отчета` are visible and reachable on mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`. Current export stayed open-period truth (`400 / 50 / 25`, no old finalized income as current income). Historical export stayed `1000 / 600 / 400` and excluded later current entries. Evidence group `200`, report `406`, current income entry `100`, current Live Report tape `199`. No new Backend/Data or Frontend/UX task is opened from this UI QA pass. The separate P1 legacy `historical_snapshot_missing` fixture request remains open.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P0
Context: Field Combat UI browser/HTTP no-data-loss QA. After a successful autosave in `Живой отчет -> Наличные`, browser refresh/return switches the UI to a new empty draft instead of restoring the saved typed row. Backend recovery by the original `client_draft_id` still returns the row, so the durable backend draft exists but the UI strands it.
Evidence: QA run `20260526264416`; mobile group `204` original draft `8` -> UI-current empty draft `14`; tablet group `205` original draft `16` -> UI-current empty draft `20`; desktop group `206` original draft `22` -> UI-current empty draft `27`. Screenshots are under `/tmp/findesk-field-combat-20260526264416-*-refresh-gate.png` and `/tmp/findesk-field-combat-20260526264416-*-after-stream-choice-empty.png`.
Request: fix Field Combat stream return/recovery so a saved active draft is restored after browser refresh/module return and the existing `client_draft_id` is not replaced by a new empty draft when the user re-enters the same cash/card stream.
Acceptance criteria:
- after `Сохранено`, refresh/return restores the typed row in UI on mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`;
- localStorage keeps or resolves to the durable active `client_draft_id` instead of overwriting it with a new empty draft;
- reselecting the same stream must not call `resetSimpleDraftIdentity` or autosave an empty replacement when a recoverable active draft exists;
- autosave still does not submit/include/finalize;
- QA can continue the remaining proof failure/retry, idempotent retry, and cash/card separation checks after the fix.

Recheck result: partially accepted on 2026-05-26 by QA/Release. Fresh run `20260526109674` confirms the old empty-draft recovery blocker is fixed on mobile/tablet/desktop. The task remains blocked by a new P0 in proof retry: after failed upload and refresh, retry duplicates the same cash money row into the previous `next_tape_id`.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P0
Context: Field Combat draft recovery identity recheck after the empty-draft fix. The old recovery identity blocker is fixed, but proof retry after a failed upload now duplicates the same typed cash row into a second cash draft card. Backend idempotency for the original `client_operation_id` passed; the duplicate appears when the UI retries after refresh with the same `client_draft_id` but a localStorage `tape_id` that has moved to the previous `next_tape_id`.
Evidence: QA run `20260526109674`. Mobile group `210`: original tape `227` row `167` plus retry tape `226` row `168`. Tablet group `211`: original tape `232` row `170` plus retry tape `231` row `171`. Desktop group `212`: original tape `237` row `173` plus retry tape `236` row `174`. Original rows have no file; retry rows have `files_count=1`. All are `cash_out 25`, `reportable=0`. Screenshots are listed in `FINDINGS.md`.
Request: keep proof retry attached to the original saved row/card after upload failure. A failed proof upload must not move the active draft context to `next_tape_id` or resubmit the same money fact into a second draft card on retry.
Acceptance criteria:
- after forced proof upload failure, refresh/return restores the same typed row and retry-needed proof state for the original tape/card;
- retry upload attaches proof to the original capture/card, or otherwise resolves the original pending proof without creating a second cash row/card;
- the same `-25 Durable autosave row` appears exactly once across visible draft cards after retry;
- original `client_operation_id` retry remains idempotent;
- no submit/include/finalize action occurs;
- recheck covers mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.

## No New Cross-Role Tasks

Date: 2026-05-27
From role: QA Release Engineer
Context: Closed group report package QA for `Закрытый групповой отчет`.
Result: QA passed on fresh fixture group `222`, report `454`. Package opens by report id through `ledger_group_final_report_package`; UI is not summary-only; proofs download through package metadata; cash/card split, accountable carryover, print/PDF, short-table Excel/Google, and immutability after current activity passed on mobile/tablet/desktop. No new Backend/Data or Frontend/UX task is opened. Next owner: Chief Auditor.

## No New Cross-Role Tasks

Date: 2026-05-27
From role: QA Release Engineer
Context: Business MVP residual surface QA.
Result: QA passed on run `20260527968710` with anchor group `222`, report `454`. Group messages send/list/unread/mark-read are reachable and group-scoped; closed package message refs remain understandable; Business Desk/proforma create/list/open/print works and does not mutate operational ledger formulas; Travel/Trip remains visibly staged; Advanced remains reachable; mobile/tablet/desktop navigation reached the required MVP surfaces without blocking overlap in the checked path. No new Backend/Data or Frontend/UX task is opened. Next owner: Project Director.

## No New Cross-Role Tasks

Date: 2026-05-27
From role: QA Release Engineer
Context: Final Business MVP QA evidence pack after residual surface PASS.
Result: PASS for QA evidence routing to the final Chief Auditor gate. Foundation/current-historical reports, Field Combat no-data-loss, Closed group report package, and residual surface QA are all recorded as passed in `FINDINGS.md`. No new Backend/Data, Frontend/UX, Product Finance, or Chief Auditor remediation task is opened from QA. Remaining items are non-blocking/P1 or separate pre-production deploy gates: package-wide downloadable file export beyond browser print/PDF, first-class report-linked message schema, legacy snapshot/package fallback fixtures, same-second cutoff hardening unless reproduced, exact downloaded-current-export wording if Product requires it, deploy package, production smoke, dirty-tree deploy selection, backup, and rollback. Next owner: Project Director.

## From Backend Data Engineer

Date: 2026-05-27
Priority: P0 recheck
Context: production base employee rights QA found `message_unread` HTTP `500` for a base employee. Backend fixed and deployed the SQL alias collision in `app/messages.php`.

Request: rerun production default base employee rights QA.

Evidence from director smoke:

- production group id: `19`
- production base employee user id: `57`
- `message_unread`: HTTP `200`, `ok=true`, `unread_count=0`
- `message_list`: `access_denied`
- `message_send`: `access_denied`

Acceptance criteria:

- close the P0 only if the full base rights slice passes;
- keep participant-control PASS accepted unless a regression appears;
- write full evidence to the QA folder and return only the short role report to Project Director.

---

## To QA Release Engineer

Date: 2026-05-28
Priority: P1 before scanner deploy
Status: local frontend prototype exists; backend storage still pending
Context: Receipt Scanner now has a local browser prototype in Live Report.

Request: when Project Director selects a test build, run scanner-specific browser QA.

Acceptance criteria:

- Mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
- iPhone Safari PWA and Android Chrome real-device check.
- `Скан` opens the scanner modal.
- Image capture/selection works.
- Four-corner touch dragging works.
- Cleaned PDF attaches through the existing proof path.
- Saving with scanner PDF does not create duplicate money rows.
- Failed upload/retry keeps the same money row target.
- Archive/final report proof access remains blocked until Backend/Data implements original+PDF storage.

Update: Backend/Data local implementation now exists and Project Director API smoke passed on user `536`, tape `294`, capture `201`, original file `16`, PDF file `17`, bundle `scanner-bundle-api-20260528`. A follow-up idempotency recheck passed on user `541`, tape `302`, capture `205`, original file `24`, PDF file `25`, bundle `scanner-api-bundle-20260528080559`; repeated original and repeated PDF uploads both returned `idempotent=true`. QA still needs independent browser/device scenario before scanner release.

CLI smoke note: `scripts/local-smoke.php` now includes scanner original/PDF/idempotent/file-list assertions. Run it where PHP CLI is available before browser/device QA.

---

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 before production scanner deploy
Status: local browser/HTTP scanner QA passed; Chief Auditor approved local slice only
Context: Production scanner deploy still needs real-device PWA/camera evidence.

Request: run the real-device scanner gate in `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`.

Required short report:

Role / Task / Status / Evidence pointer / Blocker / Next owner.

Acceptance summary:

- iPhone Safari browser mode;
- iPhone installed PWA mode;
- Android Chrome browser mode;
- Android installed PWA if available;
- physical camera or device picker returns receipt image;
- crop handles usable by touch;
- cleaned PDF attaches;
- original and cleaned PDF remain linked;
- refresh/return and retry do not duplicate money rows;
- final closed package exposes both original and cleaned PDF.
