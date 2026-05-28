# QA Release Engineer Findings

## Verification 2026-05-28 - Recheck P0 Live Report Records Page After Frontend UX Fix

Role: QA Release Engineer FinDesk.
Task: recheck P0 Live Report records page after Frontend/UX fix.
Status: PASS for checked recheck scope; previous Frontend/UX P0 is closed on local frontend assets with production API proxy.

Scope boundary:

- QA changed no runtime code, no backend/API, no UX implementation, no financial formulas, and no deploy files.
- QA wrote only to `docs/AI_TEAM/roles/04_qa_release_engineer/`.
- Local PHP runtime was unavailable in this shell, so QA used HTTP/browser fallback: current workspace `public/app.php`, `public/assets/app.js`, and `public/assets/app.css` served through a temporary Node static/proxy server; `/api.php` calls were proxied to `https://finance.brkovic.ltd` with fresh production QA sessions.
- This validates the local Frontend/UX fix against production Backend/Data, not a deployed production asset.

Run evidence:

- run_id: `20260528RECORDSRECHECK04`;
- artifact dir: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_recheck_20260528/`;
- result JSON: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_recheck_20260528/live_records_recheck_result.json`;
- runner: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_recheck_20260528/live_records_recheck_qa.mjs`;
- browser matrix: mobile `390x844`, tablet `820x1180`, desktop `1440x900`;
- browser: headless Chromium through temporary Playwright from `/tmp/findesk-pw`;
- result: `56` checks passed, `0` blockers, `0` defects.

Additional Frontend/UX Director smoke input:

- status: PASS;
- viewport: mobile `390x844`;
- group id: `244`;
- employee tape id: `332`;
- capture id: `217`;
- proof controls: `2`;
- result: admin sees employee card in ordinary list, opens card, proof viewer opens/closes, and `otr-stream-gate-open` does not remain.
- fix noted by Frontend/UX: `openCardsPanel()` now closes stream gate through `window.qlOtrSimpleHideStreamGate`.

Test fixture:

- admin user id: `81`;
- member/record owner user id: `82`;
- base employee negative-control user id: `83`;
- group id: `35`;
- tape/card id: `105`;
- capture/record id: `155`;
- proof bundle id: `qa-recheck-bundle-20260528RECORDSRECHECK04`;
- image file id: `28`;
- PDF file id: `29`.

API / isolation checks:

- admin `on_the_go_card_list` with `group_id=35` returned the employee Live Report card `tape_id=105`;
- base employee `on_the_go_card_list` with `group_id=35` did not expose employee card `tape_id=105`;
- base employee `on_the_go_card_detail` for `tape_id=105` returned `card_not_found`;
- base employee `on_the_go_file_list` for `capture_id=155` returned `capture_not_found`.

Browser recheck result:

- admin saw employee Live Report card with proof files in the ordinary records page/list on `390x844`, `820x1180`, and `1440x900`;
- card was opened by clicking the visible list control, not by direct-open fallback;
- stream gate did not cover the records list in the checked admin flows;
- clicks on the employee card worked through the ordinary visible list control without force/direct-open fallback;
- separate Director smoke also confirmed `otr-stream-gate-open` does not remain after opening records on mobile `390x844`;
- opened card detail showed the target record and exactly two proof buttons;
- image proof opened in inline proof viewer on all three viewports;
- PDF proof opened in inline proof viewer on all three viewports;
- proof viewer close worked for image and PDF on all three viewports;
- proof viewer `Открыть` link kept `target="_blank"` and `rel="noopener"` and no longer had a `download` attribute;
- no critical overlap/clipping was detected by automated scroll/client-size checks for card title, proof labels, or action buttons;
- base employee browser records page did not show the other employee card on all three viewports.

Screenshots:

- `admin-mobile390x844-records-list.png`
- `admin-mobile390x844-card-detail.png`
- `admin-mobile390x844-image-viewer.png`
- `admin-mobile390x844-pdf-viewer.png`
- `admin-tablet820x1180-records-list.png`
- `admin-tablet820x1180-card-detail.png`
- `admin-tablet820x1180-image-viewer.png`
- `admin-tablet820x1180-pdf-viewer.png`
- `admin-desktop1440x900-records-list.png`
- `admin-desktop1440x900-card-detail.png`
- `admin-desktop1440x900-image-viewer.png`
- `admin-desktop1440x900-pdf-viewer.png`
- `base-mobile390x844-records-list.png`
- `base-tablet820x1180-records-list.png`
- `base-desktop1440x900-records-list.png`

QA position:

- previous P0 `admin records list does not show employee target card` is closed for the checked local Frontend/UX fix;
- previous non-blocker `proof viewer new-tab link has download attribute` is closed for the checked local Frontend/UX fix;
- previous non-blocker mobile overflow/clipping is not reproduced in automated checks/screenshots;
- no new Backend/Data task is opened;
- next owner is Project Director / Deploy Owner for production deployment decision and post-deploy smoke.

## Verification 2026-05-28 - P0 Production Live Report Records Proof Links QA

Role: QA Release Engineer FinDesk.
Task: P0 full QA of production Live Report records page after proof-links hotfix.
Status: BLOCKED / P0 for Frontend/UX records-page release acceptance; Backend/Data proof access PASS on checked production fixture.

Scope boundary:

- QA changed no runtime code, no backend/API, no UX implementation, no financial formulas, and no deploy files.
- QA wrote only to `docs/AI_TEAM/roles/04_qa_release_engineer/`.
- Production target: `https://finance.brkovic.ltd`.
- Hotfix input: `docs/AI_TEAM/40_PROOF_LINKS_HOTFIX_PRODUCTION_2026-05-28.md`.
- Latest viewer hotfix input: `docs/AI_TEAM/41_PROOF_VIEWER_HOTFIX_PRODUCTION_2026-05-28.md`.
- Production asset version verified by HTTP during status update: `app.css?v=20260528-proof-viewer1`, `app.js?v=20260528-proof-viewer1`, `i18n.js?v=20260528-proof-viewer1`.

Run evidence:

- run_id: `20260528LIVEPROOFLINKSQA01`;
- artifact dir: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_proof_links_20260528/`;
- result JSON: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_proof_links_20260528/live_records_proof_links_result.json`;
- runner: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/live_records_proof_links_20260528/live_records_proof_links_qa.mjs`;
- tested production viewports: mobile `390x844`, tablet `820x1180`, desktop `1440x900`;
- browser: headless Chromium through temporary Playwright from `/tmp/findesk-pw`.

Test fixture:

- admin user id: `68`;
- member/record owner user id: `69`;
- group id: `31`;
- tape/card id: `95`;
- capture/record id: `151`;
- proof bundle id: `qa-proof-bundle-20260528LIVEPROOFLINKSQA01`;
- image file id: `20`;
- PDF file id: `21`;
- image download URL: `/api.php?action=on_the_go_file_download&id=20`;
- PDF download URL: `/api.php?action=on_the_go_file_download&id=21`.

Reference hotfix fixture also checked as input:

- prior hotfix run id: `prod-proof-links-20260528153719`;
- admin user id: `66`;
- member user id: `67`;
- group id: `26`;
- tape id: `87`;
- capture id: `145`;
- file id: `9`.

Reference proof-viewer hotfix smoke:

- run id: `prod-proof-viewer-20260528154804`;
- admin user id: `70`;
- member user id: `71`;
- group id: `28`;
- tape id: `91`;
- capture id: `148`;
- image file id: `14`;
- PDF file id: `15`;
- smoke result: production asset version `20260528-proof-viewer1`, viewer markers present, admin sees two proof links, download URLs return bytes.

API / access result:

- owner `on_the_go_card_detail` returned the target row with `files_count=2`;
- admin `on_the_go_card_detail` for the employee card returned the target row with `files_count=2`;
- owner `on_the_go_file_list` returned two visible proof links with `download_url`;
- admin `on_the_go_file_list` returned two visible employee proof links with `download_url`;
- owner opened both image and PDF through `on_the_go_file_download`;
- admin opened both employee image and employee PDF through `on_the_go_file_download`;
- both image and PDF responses were HTTP `200`, non-empty, and `Content-Disposition: inline`;
- anonymous direct proof download was denied with HTTP `401`;
- Backend/Data owner for this surface: PASS, no new blocker found.

Browser/UI result:

- owner and admin browser sessions authenticated on all three viewports;
- direct card detail opened on all three viewports for owner and admin;
- each target record rendered two proof controls in card detail;
- image proof opened in inline proof viewer on all three viewports for owner and admin;
- PDF proof opened in inline proof viewer iframe on all three viewports for owner and admin;
- proof viewer kept a new-tab escape link with `target="_blank"` and `rel="noopener"`;
- proof viewer close control worked in the checked flows.

P0 blocker:

- `Frontend/UX`: admin records list does not show the target employee Live Report card before direct-open fallback on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.
- Impact: group admin can access the employee proof files through permitted API/card detail, but the records page does not expose the employee card discoverably in the checked admin UI path.
- Evidence screenshots: `admin-mobile390x844-records-list.png`, `admin-tablet820x1180-records-list.png`, `admin-desktop1440x900-records-list.png`.
- Next owner: Frontend/UX.

Non-blocker defects:

- `Frontend/UX`: owner records-list state can be covered by `otrStreamGate` on mobile/tablet/desktop after programmatic records restore; the target card exists behind the gate and direct open works, but the page state is confusing and can intercept clicks. Evidence: `member-*-records-list.png`.
- `Frontend/UX`: mobile card title and proof labels overflow/truncate poorly for long card/file names. Evidence: `member-mobile390x844-card-detail.png`.
- `Frontend/UX`: mobile primary action in card modal is horizontally clipped (`Вернуть в редактирование`). Evidence: `member-mobile390x844-card-detail.png`.
- `Frontend/UX`: proof viewer new-tab link also has a `download` attribute on image/PDF viewer links. This weakens Safari/PWA "open without losing current page" intent because the same control is both open and download. Evidence in result JSON under `proof viewer new-tab link also has download attribute`.
- `Frontend/UX`: headless Chromium screenshots show blank viewer content for the tiny synthetic image/PDF proof bodies; network/API bytes and viewer element type were valid, but real receipt visual readability should be rechecked with real production receipt/photo content.

Screenshots:

- `member-mobile390x844-records-list.png`
- `member-mobile390x844-card-detail.png`
- `member-mobile390x844-image-viewer.png`
- `member-mobile390x844-pdf-viewer.png`
- `member-tablet820x1180-records-list.png`
- `member-tablet820x1180-card-detail.png`
- `member-tablet820x1180-image-viewer.png`
- `member-tablet820x1180-pdf-viewer.png`
- `member-desktop1440x900-records-list.png`
- `member-desktop1440x900-card-detail.png`
- `member-desktop1440x900-image-viewer.png`
- `member-desktop1440x900-pdf-viewer.png`
- `admin-mobile390x844-records-list.png`
- `admin-mobile390x844-card-detail.png`
- `admin-mobile390x844-image-viewer.png`
- `admin-mobile390x844-pdf-viewer.png`
- `admin-tablet820x1180-records-list.png`
- `admin-tablet820x1180-card-detail.png`
- `admin-tablet820x1180-image-viewer.png`
- `admin-tablet820x1180-pdf-viewer.png`
- `admin-desktop1440x900-records-list.png`
- `admin-desktop1440x900-card-detail.png`
- `admin-desktop1440x900-image-viewer.png`
- `admin-desktop1440x900-pdf-viewer.png`

QA position:

- proof-links hotfix backend/data behavior passes for owner and permitted group admin on the checked production fixture;
- records page is not release-clean because admin cannot discover the employee card from the checked records list UI;
- P0 next owner is Frontend/UX for admin records-list discoverability and mobile/card polish;
- Backend/Data has no current blocker from this QA run.

## Verification 2026-05-28 - Deploy Preflight Sprint QA Update For Candidate 34

Role: QA Release Engineer FinDesk.
Task: deploy-preflight sprint QA update for candidate 34.
Status: QA checklist updated; local PASS preserved; production remains NO-GO until deploy blockers close.

Scope boundary:

- QA changed no runtime code, no backend/API, no UX implementation, no financial formulas, no scripts, and no deploy SQL.
- QA wrote only to the allowed QA role documentation files.
- Working tree was already dirty; QA did not reset, checkout, clean, or revert.

Read inputs:

- `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`;
- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/local_leftovers_recheck_20260528/SUMMARY.md`;
- `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`.

Checklist correction:

- candidate 34 already includes `app/groups.php` and `public/assets/i18n.js`;
- QA deploy checklist previously missed them in the selected bundle section;
- checklist now includes both `app/groups.php` and `public/assets/i18n.js`;
- PHP lint targets now include `app/groups.php`;
- pre-upload local checks now explicitly include group soft archive and asset/version checks;
- post-upload production smoke now explicitly includes asset cache guard and `group_delete` soft archive.

Local PASS separated from production NO-GO:

- accepted local evidence remains run `20260528LOCALLEFTOVERS01`;
- accepted local scanner evidence remains run `20260528RSQA01`;
- candidate 34 local bundle is not marked production-ready by QA;
- production remains NO-GO until real-device scanner decision, PHP/smoke replacement, DB preflight, backup/rollback, upload, SQL, and production smoke are complete.

QA rerun decision:

- QA does not require another local recheck right now if candidate 34 file bundle is unchanged;
- next QA execution is after DB preflight, backup/rollback, production upload, and SQL/application;
- real-device scanner/PWA camera QA remains a separate required gate before any device-ready scanner claim.

Next owner:

- Project Director / Deploy Owner / Database Migration Owner.

## Verification 2026-05-28 - Formal Local Recheck After Frontend Leftovers And Group Delete Hardening

Role: QA Release Engineer FinDesk.
Task: formal local recheck after Frontend/UX leftovers fix and Backend/Data `group_delete` hardening.
Status: PASS for requested local checks; production deploy readiness still depends on separate deploy gate items.

Scope boundary:

- QA changed no runtime code, no backend/API, no UX implementation, no financial formulas, no scripts, and no deploy SQL.
- QA wrote only to QA role documentation and one QA artifact summary.
- Working tree was already dirty; QA did not reset, checkout, clean, or revert.

Evidence pointer:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/local_leftovers_recheck_20260528/SUMMARY.md`
- run_id: `20260528LOCALLEFTOVERS01`
- group_delete fixture: `group_id=233`, admin user `555`, base member user `556`

Baseline:

- `HEAD=72b38e6`;
- `origin/main=72b38e6`;
- local server `http://127.0.0.1:18889/api.php?action=current_user` returned HTTP `200`, `ok=true`;
- `node --check public/assets/app.js` passed;
- `node --check public/assets/i18n.js` passed;
- `app.php`, `app.css`, `app.js`, and `i18n.js` loaded through HTTP `200`.

Login/auth checks:

- local `app.php` fallback H1 is `FinDesk access code`;
- old `FinDesk sign-in code` was not present as the login H1 in the loaded `app.php`;
- `request_code` and `verify_code` both worked during the fresh admin/base-member API fixture setup.

Live Report UI leftovers check:

- browser automation libraries were not locally available in this QA shell (`playwright`, `@playwright/test`, and `puppeteer` were not installed), so QA did static/HTTP checks instead of screenshot overlap acceptance;
- `app.php` and current versioned frontend assets load over HTTP;
- CSS contains the mobile/card layout hardening selectors for Live Report cards and actions;
- no syntax break was found in `app.js` or `i18n.js`.

Scanner modal close check:

- modal `receiptScannerModal` is present;
- visible `Закрыть` button with `data-close-receipt-scanner` is present;
- top `×` close button with `data-close-receipt-scanner` is present;
- JavaScript handles `data-close-receipt-scanner`;
- JavaScript handles outside-modal click for `receiptScannerModal`;
- global Escape handler includes `data-close-receipt-scanner`.

Last module/work-zone persistence check:

- module-state code exists with `QL_MODULE_STATE_KEY`;
- `ontherun` is included in the allowed module state list;
- `qlSaveModuleState('ontherun', ...)` stores `screen`, `stream_type`, `tape_id`, and `archived_only`;
- `qlApplyModuleState()` routes restored `ontherun` state back through `qlSetModule()`;
- no syntax break was found.

Backend/API `group_delete` check:

- admin created fresh test group `group_id=233`;
- base member joined through invite with `access_level=base`;
- admin created one group ledger income row to prove financial evidence preservation;
- base member `group_delete` returned `admin_required`;
- admin `group_delete` returned `ok=true`, `status=archived`, `archive_mode=soft`;
- financial counters were preserved: `ledger_entries` stayed `1` before and after archive;
- repeated admin `group_delete` returned `already_deleted=true`;
- archived group no longer appeared in active `group_list` for admin or base member.

Scanner regression guard:

- previous local scanner file-input gate `20260528RSQA01` remains accepted;
- this recheck did not rerun the full scanner matrix;
- static/API checks found no obvious scanner syntax/API regression from the frontend leftovers and group-delete hardening patches.

Limitations:

- physical iPhone Safari PWA and Android Chrome/PWA camera behavior were not checked in this task;
- screenshot-level mobile overlap acceptance was not possible in this shell because browser automation libraries were unavailable;
- production deploy readiness is still governed by the separate deploy checklist and real-device scanner gate.

Current QA position:

- requested local recheck is PASS;
- no new Backend/Data blocker is opened for `group_delete`;
- no new Frontend/UX blocker is opened from static/HTTP leftovers check;
- release routing returns to Project Director / Deploy Owner for production gate decisions.

## Verification 2026-05-28 - Deploy Readiness Checklist For Limited Scanner/UX/Backend Release

Role: QA/Deploy Release Engineer FinDesk.
Task: prepare deploy-readiness checklist for the next limited production release after local scanner/UX/backend work.
Status: BLOCKED / NO-GO for production deploy until listed P0 readiness items are closed.

Scope boundary:

- QA changed no runtime code, no backend/API, no UX code, no scripts, and no deploy SQL.
- QA wrote only to QA role documentation.
- Working tree was already dirty; QA did not reset, checkout, clean, or revert.

Evidence pointer:

- `docs/AI_TEAM/roles/04_qa_release_engineer/DEPLOY_READINESS_CHECKLIST_2026-05-28.md`

Observed baseline:

- `HEAD=72b38e6`;
- `origin/main=72b38e6`;
- current shell has no `php` CLI;
- local `current_user` endpoint returned `{"ok":true,"user":null}`;
- `node --check public/assets/app.js` passed;
- `node --check public/assets/i18n.js` passed;
- `git diff --check` passed for selected scanner/UX/backend runtime files.

Already closed:

- business-MVP product gate is approved for the checked new-data path;
- production base rights rerun passed on run `20260527212947`, production `group_id=20`, `report_id=194`;
- local Receipt Scanner file-input gate passed on QA run `20260528RSQA01` and Chief Auditor approved it only for local browser/HTTP file-input scope.

Production deploy blockers:

- real-device scanner QA is still required for iPhone Safari/PWA and Android Chrome/PWA behavior;
- PHP CLI/smoke availability is not proven in this shell;
- production scanner DB migration preflight is required for the new proof role/bundle/hash/metadata columns;
- selected file bundle must be frozen before upload;
- DB/files backup and rollback procedure must be recorded before upload.

Current QA position:

- deploy-readiness checklist is prepared;
- production deploy remains NO-GO from QA until the P0 items in the checklist are closed or CEO explicitly accepts a narrower release boundary;
- next owner is Project Director / Deploy Owner / Database Migration Owner.

## Verification 2026-05-28 - Receipt Scanner Local Browser/HTTP QA

Role: QA Release Engineer FinDesk.
Task: Receipt Scanner local browser/HTTP QA after local sprint 2026-05-28.
Status: PASS for local browser/HTTP file-input scanner path; full release ready not declared.

Scope boundary:

- QA changed no runtime code, no backend/API, no UX code, no financial formulas, and no deploy schemas.
- QA wrote this result only to QA role docs.
- Browser run used temporary Playwright/Chromium and temporary fixtures under `/tmp`.
- Physical camera capture and installed iPhone/Android PWA mode were not available in headless Chromium; this is recorded as a device limitation, not a local browser/HTTP blocker. The file-input scanner path was tested instead.

Local server:

- `http://127.0.0.1:18889/api.php?action=current_user` returned HTTP `200`, `ok=true`.

Run evidence:

- run_id: `20260528RSQA01`;
- result JSON: `/tmp/findesk-receipt-scanner-20260528RSQA01/receipt-scanner-qa-result.json`;
- screenshots directory: `/tmp/findesk-receipt-scanner-20260528RSQA01/`;
- tested viewports: mobile `390x844`, tablet `820x1180`, desktop `1440x900`.

Verified UI behavior:

- Live Report opens scanner flow through `data-otr-attach="scan"`.
- Modal `Скан чека в PDF` opens on all three viewports.
- File-input image path loads a receipt image into preview/canvas.
- Crop handles render and can be moved.
- Cleanup slider and black-white toggle can be changed before attachment.
- `Прикрепить PDF` creates a PDF attachment state in the Live Report editor.
- Refresh/return after save can reopen the saved card and shows the saved money row.

Verified backend/evidence behavior:

| Viewport | user_id | tape_id | capture_id | proof_bundle_id | original_file_id | cleaned_file_id |
| --- | ---: | ---: | ---: | --- | ---: | ---: |
| mobile390x844 | 538 | 296 | 202 | `scan-7341b1d4-7f4f-494c-bd02-e62cf287390b` | 18 | 19 |
| tablet820x1180 | 539 | 298 | 203 | `scan-677715a4-620b-4966-9e68-54c9b03de414` | 20 | 21 |
| desktop1440x900 | 540 | 300 | 204 | `scan-015536f2-51c8-4fa1-99d0-a8217af93dd7` | 22 | 23 |

For each viewport:

- backend stored exactly two scanner artifacts in one `proof_bundle_id`;
- roles were exactly `scanner_original` and `scanner_cleaned_pdf`;
- `scanner_cleaned_pdf.source_file_id` pointed to the matching original file id;
- both artifacts had a 64-character `file_hash_sha256`;
- repeating `scanner_original` upload with stable `client_upload_id = proof_bundle_id + ':original:' + captureId` returned `idempotent=true`;
- repeating `scanner_cleaned_pdf` upload with the same cleaned PDF `client_upload_id` returned `idempotent=true`;
- replaying the same `on_the_go_signed_sync` operation returned `idempotent=true`;
- money row count for the tested fact remained exactly `1` after retry/replay;
- scanner file count for the bundle remained exactly `2`;
- no browser console/page errors were recorded.

Screenshots:

- `/tmp/findesk-receipt-scanner-20260528RSQA01/mobile390x844-scanner-modal.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/mobile390x844-after-attach.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/mobile390x844-after-refresh-return.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/tablet820x1180-scanner-modal.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/tablet820x1180-after-attach.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/tablet820x1180-after-refresh-return.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/desktop1440x900-scanner-modal.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/desktop1440x900-after-attach.png`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/desktop1440x900-after-refresh-return.png`

Current QA position:

- local Receipt Scanner browser/HTTP slice is accepted for file-input scanner path;
- no Backend/Data or Frontend/UX blocker is opened from this run;
- physical mobile camera and installed PWA behavior still need real-device QA before claiming device-level scanner acceptance;
- full release ready is not declared.

## Task Card 2026-05-28 - Receipt Scanner QA Matrix

Role: QA Release Engineer FinDesk.
Task: prepare the Receipt Scanner QA matrix/task card.
Status: PASS for matrix preparation; NOT EXECUTED.

Scope boundary:

- QA did not change runtime code.
- QA did not run the browser/device matrix in this task.
- Current product context treats `scan` as a proof/photo/file attachment path, not automatic OCR or edge detection, unless Product/Backend/Frontend explicitly deliver scanner/OCR behavior before execution.
- This matrix is not a release acceptance by itself; it is the required execution card for the Receipt Scanner slice.

Target surfaces:

- On the Go / Field Combat money card proof actions: media, scan, camera, and desktop file upload.
- Review/final report/archive proof surfaces: original proof access, printable/PDF final report path, and closed package proof download.

QA matrix:

| ID | Device / path | Receipt condition | Interruption / network | Expected result |
| --- | --- | --- | --- | --- |
| RS-01 | iPhone Safari PWA, installed/standalone | normal readable receipt | none | scan/camera action is reachable from the money row, original proof attaches to the intended row/card, save state is visible, and no duplicate money row appears. |
| RS-02 | Android Chrome | normal readable receipt | none | camera/scan/upload flow attaches the original proof to the intended row/card and preserves the typed amount/comment/stream. |
| RS-03 | Desktop upload | existing JPG/PNG/PDF receipt | none | file picker upload works without mobile-only assumptions; original proof is available from the card and later report surfaces. |
| RS-04 | iPhone Safari PWA | poor light / low contrast | none | upload is accepted or visibly rejected with a recoverable error; no silent money mutation or forced OCR accounting truth. |
| RS-05 | Android Chrome | glare / reflection | none | original proof is retained; unreadable content is not converted into accepted accounting data without user confirmation. |
| RS-06 | iPhone or Android | crumpled receipt | none | proof remains attached to the same money row/card; user can retry/replace proof without duplicating the row. |
| RS-07 | iPhone or Android | busy background behind receipt | none | original proof is retained and visible; background confusion does not create extra rows, amounts, or accepted report data. |
| RS-08 | Mobile PWA/browser | receipt selected but not uploaded yet | refresh before upload | typed money row/draft survives refresh, no phantom proof is shown, and returning to the card keeps the same draft identity. |
| RS-09 | Mobile PWA/browser | uploaded proof | refresh after upload, before submit/include/finalize | uploaded proof or retry-needed state survives refresh and remains linked to the original row/card. |
| RS-10 | Mobile PWA/browser | any receipt | offline before upload | typed data remains recoverable; upload is blocked or queued with clear state; no silent submit/include/finalize. |
| RS-11 | Mobile PWA/browser | any receipt | offline during upload, then retry | retry attaches proof to the original row/card and does not create a duplicate money row. |
| RS-12 | Desktop + mobile report review | accepted receipt proof | final report / print-PDF path | original proof and printable/PDF final report evidence are both available to an authorized reviewer. |
| RS-13 | API/browser row audit | same money fact and proof retry | repeated retry / back button / refresh | row count for the money fact remains one; idempotent retry does not create duplicate ledger/report rows. |
| RS-14 | Archive / closed final report package | accepted receipt proof | after finalization and later current-period activity | archive/final report opens by explicit `report_id`; authorized proof access works; unauthorized access is denied; closed package is not mutated by later activity. |

Must-record evidence when executed:

- device/browser and install mode, especially iPhone Safari PWA and Android Chrome;
- group_id, report_id, tape/card id, row id, proof id, and run stamp;
- before/after row counts around refresh and retry;
- screenshot or video pointers for mobile proof actions and final/archive proof access;
- original uploaded proof availability and printable/PDF final report availability;
- explicit PASS/FAIL/BLOCKED result for every RS row above.

P0 stop criteria:

- typed money fact lost after visible save;
- original receipt/proof lost after the UI claims upload or save succeeded;
- duplicate money row after upload retry, refresh, offline retry, or repeated click;
- final report/archive cannot open the authorized proof by `report_id`;
- unauthorized user can access the proof/archive;
- scanner/OCR, if later added, mutates accounting truth without user confirmation.

Current QA position:

- Receipt Scanner QA matrix is recorded and ready for execution.
- Release acceptance for Receipt Scanner remains pending a real device/browser run.
- Next owner: QA Release Engineer for execution after Project Director selects the target build/device window; failures route to Frontend/PWA for capture UX and Backend/Data for persistence, idempotency, proof access, or archive defects.

## Verification 2026-05-27 - Production Base Rights Rerun After Message Unread Hotfix

Role: QA Release Engineer FinDesk.
Task: production rerun default base employee rights after backend `message_unread` alias hotfix.
Status: PASS.

Production target: `https://finance.brkovic.ltd`.

Evidence folder:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_base_rights_rerun_2026-05-27/`

Run stamp: `20260527212947`.

Fresh production fixture:

- group_id `20`;
- report_id `194`;
- admin user_id `58`;
- base employee user_id `59`;
- other employee user_id `60`.

Scope boundary:

- participant-control PASS from the previous recheck was not reopened;
- QA did not change backend/API/UX/financial formulas;
- QA did not deploy;
- auth codes, cookies, FTP credentials, and passwords are not written into the evidence files.

Verified:

- default invite creates `access_level=base`;
- base permissions deny group reports, group ledger write, money management, moderation, and member management;
- base employee sees only self in `group_members`;
- base employee is denied current group export;
- base employee is denied final report list/detail/package/export;
- base employee is denied group message list and group message send;
- base employee is denied accountable money creation;
- base employee is denied role management;
- `message_unread` for base employee returns HTTP `200`, `ok=true`, `unread_count=0`;
- base employee can use personal FinDesk ledger/report;
- base employee can save own operational Field Combat / On the Go row in group context;
- base employee operational tape starts from own cash base `0`, not administrator group cash `1000`;
- base employee sees only own operational cards;
- base employee sees only own accountable data;
- base group self-control excludes administrator group cash;
- admin setup path remains working for group income, messages, accountable money, included card, finalization, member list, and final report list.

Artifacts:

- `production_base_rights_rerun.mjs`;
- `production_base_rights_rerun_evidence.json`;
- `production_base_rights_rerun_result.json`;
- `SUMMARY.md`.

Current QA position:

- default base employee rights slice is accepted by QA;
- previous participant-control PASS remains accepted;
- no blocker remains from this rerun.

## Verification 2026-05-27 - Production Hotfix Recheck: Participant Control + Base Rights

Role: QA Release Engineer FinDesk.
Task: formal production recheck after participant-control hotfix and default invited base employee rights hotfix.
Status: BLOCKED / P0.

Production target: `https://finance.brkovic.ltd`.

Evidence folder:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/`

Run stamp: `20260527211338`.

Scope boundary:

- QA did not change backend/API/UX/financial formulas.
- QA did not deploy.
- Auth codes, cookies, FTP credentials, and passwords are not written into the evidence files.

### Recheck A: Participant-Control Hotfix

Status: PASS.

Fresh production fixture:

- group_id `17`;
- report_id `176`.

Scenario:

- admin received `1000`;
- admin issued advances `135`, `94`, `117`;
- admin expenses were `20`, `45`, `17`, `4`;
- employee 1 expenses were `6`, `9`, `43`, `10`;
- employee 2 expenses were `12`, `23`, `41`, `54`;
- employee 3 spent `0`.

Verified:

- current export, final detail, closed package, Google/TSV export, and Excel export expose expense `284` and balance `716`;
- `admin_cash_left=568`;
- employee positive remaining total `184`;
- employee reimbursement due total `36`;
- employee net remaining `148`;
- signed employee rows include `67`, `-36`, and `117`;
- employee 2 reimbursement due `36` is visible outside raw audit refs;
- archive/package opens by `report_id`.

Artifacts:

- `participant_current_group_google_sheet.tsv`;
- `participant_current_group_report.xls`;
- `participant_final_report_detail.json`;
- `participant_closed_group_package.json`;
- `participant_final_report_google_sheet.tsv`;
- `participant_final_group_report.xls`;
- `participant_closed_group_package_print.html`.

### Recheck B: Default Invited Base Employee Rights

Status: BLOCKED / P0.

Fresh production fixture:

- group_id `18`;
- report_id `184`;
- base employee user_id `54`.

Passed before blocker:

- default invite creates `access_level=base`;
- joined employee has base permissions and cannot view group reports, write group ledger, or manage money;
- admin setup path still works enough to create income, send a group message, create accountable money, include a card, finalize a report, list members, and list final reports;
- base employee sees only self in `group_members`;
- base employee is denied current group export;
- base employee is denied final report list/detail/package/export;
- base employee is denied group message list and send;
- base employee is denied money management;
- base employee is denied role management.

Blocker:

- `message_unread` for the base employee returns `server_error` / HTTP `500`;
- error text points to SQL syntax near alias `current_role`;
- expected behavior: safe `ok=true`, `unread_count=0`, and no group messages exposed to base employee.

Failure artifact:

- `production_hotfix_recheck_failure.json`.

Current QA position:

- participant-control hotfix is accepted by QA;
- default base employee rights hotfix is not accepted until Backend/Data fixes `message_unread` and QA reruns the rights slice;
- release ready is not declared.

## Production Multi-Employee Money Flow - 2026-05-27

QA release position for this assignment: BLOCKED for the requested production scenario. Production code was not changed.

Artifact folder:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/`

Production run:

- URL: `https://finance.brkovic.ltd`
- stamp: `20260527201737`
- group_id: `8`
- report_id: `66`
- admin user id: `18`
- employee user ids: `19`, `20`, `21`

Flow executed:

- Created one admin QA account and three employee QA accounts using unique plus-address names.
- Created production QA group and joined all three employees.
- Entered admin received cash `EUR 1000`.
- Entered admin expenses `20`, `45`, `17`, `4` through an included admin Live Report card, because production finalization requires at least one included non-advance card.
- Issued accountable cash: employee 1 `EUR 135`, employee 2 `EUR 94`, employee 3 `EUR 117`.
- Employee 1 saved expenses `6`, `9`, `43`, `10`, submitted remaining `67`, and admin accepted.
- Employee 2 saved expenses `12`, `23`, `41`, `54`; submit produced visible `discrepancy` with `expected_remaining=-36.00`, `actual_remaining=0.00`, `difference_amount=36.00`; admin accepted.
- Employee 3 created no fake expense, submitted remaining `117`, and admin accepted.
- Finalized group report and opened closed group package/archive.
- Saved final detail/package JSON, current/final TSV exports, current/final Excel downloads, and printable package HTML.

Financial result:

- PASS: total expenses are `EUR 284`.
- PASS: group net balance is `EUR 716`.
- PASS: employee 1 remaining `EUR 67` is present as open accountable remainder.
- PASS: employee 3 no-spend remaining `EUR 117` is present as open accountable remainder; no fake employee 3 expense was created.
- PARTIAL: employee 2 overrun/reimbursement `EUR 36` is preserved in package audit refs as `status=discrepancy`, `expected_remaining=-36.00`, `difference_amount=36.00`, but it is not represented in headline package/detail participant balance totals.
- FAIL/P0: final detail, final package, and exports show `admin_cash_left=532`, not the required financial-control value `568`.

Evidence files:

- `SUMMARY.md`
- `retrieved_final_artifacts.json`
- `final_report_detail.json`
- `closed_group_package.json`
- `current_group_google_sheet.tsv`
- `final_report_google_sheet.tsv`
- `current_group_report.xls`
- `final_group_report.xls`
- `closed_group_package_print.html`

Blocker:

- The required equation is `568 + 67 - 36 + 117 = 716`. Production headline totals represent `532 + 184 = 716` and keep employee 2's `36` overrun only in audit refs. This fails the task-card stop criterion for `admin cash left EUR 568` and weakens the final package's multi-employee participant control.

Next owner: Backend Data Engineer / Product Finance Architect for final package/detail financial-control representation of employee overrun and admin cash-left semantics.

## SEO/PWA QA Checklist Prepared - 2026-05-27

QA release position for this assignment: PASS for checklist preparation. No application code was changed and no production smoke was executed.

Primary checklist:

- `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md`

Source files read:

- `public/index.php`
- `public/app.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `docs/AI_TEAM/20_LANGUAGE_POLICY_AUDIT.md`
- `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`

Current baseline observations from the inspected files:

- public root has title, description, canonical `https://finance.brkovic.ltd/`, manifest link, OG/Twitter image links, and visible FinDesk public content in `public/index.php`;
- current public root has no `application/ld+json` script found by source search, so JSON-LD parse presence is now recorded as a must-pass item after the SEO implementation unless Project Director removes it from scope;
- app boundary is present in `public/app.php` through `noindex,nofollow`;
- crawl boundary is present in `public/robots.txt`: `/` allowed, `/app.php`, `/api.php`, and `/storage/` disallowed, sitemap pointed to production canonical;
- sitemap currently contains only `https://finance.brkovic.ltd/`;
- manifest starts installed PWA at `/app.php` and references 192/512/maskable icons;
- service worker cache name is currently `findesk-20260522-v134` and activation removes old `findesk-*` caches;
- local social/PWA assets exist, including `brand-og.png` at `1200 x 630`, `brand-mark.png`, app icons, Apple touch icon, and favicons.

Prepared local QA checklist after Frontend/PWA SEO implementation:

- public HTTP 200 and non-empty visible public page;
- raw source checks for title, description, canonical, robots absence on public root, lang, viewport, manifest, OG/Twitter tags;
- JSON-LD presence and `JSON.parse` validation with no private app/user/API/storage data;
- PWA manifest/install checks, including icons and installed start URL `/app.php`;
- robots/sitemap/noindex boundary checks for public root vs `/app.php`, `/api.php`, and `/storage/`;
- mobile rendering at mandatory `390 x 844`, with recommended `360 x 800`, `820 x 1180`, and `1440 x 900`;
- service-worker/cache update checks after asset/manifest/public-index changes;
- social preview and brand asset existence checks.

Prepared production smoke additions for SEO/PWA:

- HTTP 200 for `/`, `/index.php` where reachable, `robots.txt`, `sitemap.xml`, manifest, service worker, `/app.php`, and referenced brand/PWA assets;
- title/description/canonical verification from production raw HTML;
- JSON-LD parse presence;
- robots and sitemap validation;
- manifest parse/installability checks;
- noindex app boundary and API/storage crawl boundary;
- mobile viewport `390 x 844`;
- service-worker cache update and returning-PWA behavior;
- social preview asset existence.

SEO/PWA stop criteria recorded:

- app indexed accidentally;
- API/storage open to crawl;
- canonical wrong;
- empty public page;
- broken manifest;
- missing brand/social/PWA assets.

Next owner: Frontend/PWA SEO Engineer for implementation and local evidence, then QA/Deploy Owner for post-deploy SEO/PWA smoke additions.

## Production Smoke Plan For 100% MVP - 2026-05-27

QA release position for this assignment: PASS for smoke-plan preparation. No production checks were executed, no production upload was performed, and no secrets are recorded here. This is a post-deploy verification plan for the Deploy Owner and Project Director to run after the selected package is uploaded.

Source documents read:

- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/14_PRODUCTION_DEPLOY_READINESS.md` was checked and was not present at planning time.

Scope basis:

- Business-MVP QA has passed for the local evidence package: foundation/current-historical reports, Field Combat no-data-loss, closed group report package, and residual surfaces.
- Production deploy readiness is separate from the business-MVP product gate.
- The deploy handoff warns not to upload the whole dirty worktree blindly. Deploy Owner must choose either the approved full current working-tree bundle or the narrow MVP runtime bundle before upload.
- Production smoke must prove only that the deployed 100% MVP works in production. It must not become a broad feature regression, schema migration exercise, or production data cleanup.

### Post-Deploy Production Smoke Checklist

Run order after deploy:

1. Record deploy evidence before browsing:
   - production URL/domain;
   - deploy timestamp and timezone;
   - package mode used: full current working-tree bundle or narrow MVP runtime bundle;
   - exact uploaded file list or deployment artifact id;
   - source revision/base evidence, including `HEAD=72b38e6` if that remains the selected baseline;
   - database backup id/location reference, without credentials;
   - file backup id/location reference, without credentials;
   - rollback owner and rollback command/procedure reference, without secrets.
2. Hard-load production in a fresh browser session:
   - app shell returns HTTP 200;
   - no blank screen;
   - public assets load: JS, CSS, favicon/manifest/service worker if included in deploy package;
   - console has no fatal boot error blocking login or navigation.
3. Authentication/session check:
   - smoke tester can sign in or resume an authorized session;
   - current user/session API returns the expected authenticated user shape;
   - logout or session-expiry behavior does not expose another user's data.
4. Mobile-first MVP path, browser smoke:
   - run at minimum on mobile viewport `390 x 844`;
   - if time allows, repeat navigation reachability on tablet `820 x 1180` and desktop `1440 x 900`;
   - primary entrances are reachable: `On the Go`, reports/group money, closed reports/archive, group messages, Business Desk, Travel staging, and Advanced staging.
5. Field Combat smoke:
   - open `On the Go` / `Живой отчет`;
   - open cash stream and confirm fast cash controls are visible;
   - create one small smoke draft row in the approved production smoke group/account;
   - wait for visible saved state;
   - refresh and return to the same stream;
   - confirm the draft row still exists and did not become a final/submitted/included row without action;
   - attach or retry a harmless proof only if the production smoke policy permits creating proof evidence;
   - confirm no duplicate money row after retry if proof retry is exercised.
6. Current period report/export smoke:
   - open the selected smoke group report area;
   - confirm `Текущий период` / current open period is visible;
   - confirm current export action is reachable and uses current-period behavior;
   - confirm current export does not appear merged with a historical final-report export.
7. Historical final report smoke:
   - open closed/final reports list;
   - select a known production-safe closed report or create/finalize a dedicated smoke report only if Deploy Owner approves production test data;
   - confirm the selected final report opens by `report_id`;
   - confirm historical export actions are reachable and labeled as final-report exports;
   - confirm later current activity does not mutate the selected final report if a new smoke finalization is created.
8. Closed group report package smoke:
   - open `Закрытый групповой отчет` for a known/smoke `report_id`;
   - confirm it is a full archive object, not summary-only;
   - visible sections must include summary, participant reports, money facts/proofs, group money rows, accountable/advance state, messages/audit refs, and print/PDF;
   - proof links must resolve for an authorized reviewer without exposing unauthorized access.
9. Group messages smoke:
   - send one harmless group-scoped smoke message if production smoke policy permits;
   - confirm list/unread/mark-read behavior works for the selected group;
   - confirm a non-member or unrelated group context cannot read the message, if a safe permission check account is available.
10. Business Desk / proforma smoke:
    - open Business Desk;
    - list existing proformas or create a dedicated smoke proforma if permitted;
    - open preview and trigger print/save-PDF flow with no financial mutation in the operational group report.
11. Travel / Advanced staging smoke:
    - confirm Travel / Trip with Friends remains visible as staged or non-core;
    - confirm Advanced remains reachable;
    - confirm neither staging surface changes current operational report totals during smoke.
12. Final browser health check:
    - no blocking overlap on mobile primary actions used in the smoke;
    - no fatal API error banner on the checked flows;
    - no stale-service-worker behavior after one hard reload.

### Minimum Browser Checks For 100% MVP On Production

Must run before CEO use:

| Surface | Minimum browser evidence | Must-pass condition |
| --- | --- | --- |
| App shell and login/session | Fresh browser opens production app; authorized smoke tester reaches app; session/current-user state is valid. | App is usable and scoped to the authenticated user. |
| Mobile primary navigation | Mobile `390 x 844` reaches On the Go, reports/group money, closed archive, messages, Business Desk, Travel staging, and Advanced. | No blocking overlap or unreachable primary action on the MVP path. |
| Field Combat | Create or recover one saved draft row after refresh in an approved smoke context. | Saved row survives refresh/return; no silent submit/include/finalize; no duplicate money row in exercised retry path. |
| Current period report/export | Open selected group current period and current export. | Current open period is visibly separate from historical final report; export action is reachable. |
| Historical final report | Open a known/smoke final report from closed reports. | Final report opens by explicit `report_id`; historical export is reachable and labeled as final report. |
| Closed group report package | Open one `Закрытый групповой отчет`. | Full package sections and print/PDF are visible; package is not summary-only. |
| Proof access | Open at least one package proof as authorized reviewer if safe test proof exists. | Authorized proof download/view works; no unauthorized proof exposure is observed. |
| Group messages | List and, if permitted, send one group smoke message. | Message remains group-scoped; unread/mark-read path works if checked. |
| Business Desk | Open Business Desk proforma list/preview/print flow. | Business document surface works and does not mutate operational report totals. |
| Travel / Advanced | Open staged Travel and Advanced. | They remain reachable and do not conflict with the ordinary money loop. |

Recommended viewports:

- Must-pass: mobile `390 x 844`.
- Strongly recommended before CEO use: tablet `820 x 1180` and desktop `1440 x 900` navigation reachability for app shell, reports/archive, Business Desk, and messages.

### Minimum API Checks For 100% MVP On Production

Use authenticated production-safe smoke sessions only. Do not log tokens, cookies, passwords, or private customer data.

Must run before CEO use:

| API area | Minimum check | Must-pass condition |
| --- | --- | --- |
| Session/current user | Current authenticated session endpoint or equivalent app bootstrap call. | Returns valid authenticated identity and no cross-user leakage. |
| Ledger current period | Current group report/open-period endpoint and current export endpoint for smoke group. | Current-period data is returned without server error and remains separate from final report endpoints. |
| Final report list/detail/export | `ledger_group_final_report_list`, selected final report detail, Google/Excel export or equivalent configured actions. | Selected `report_id` returns historical/final data; export actions do not mutate data. |
| Closed group package | `ledger_group_final_report_package` for selected `report_id`. | Package returns summary, participants, captures/proofs, money rows, accountable state, messages/audit refs. |
| Package proof | `ledger_group_final_report_proof_download` or equivalent package proof URL. | Authorized reviewer gets HTTP 200 for safe smoke proof; unauthorized access is rejected if a safe check is available. |
| Field Combat draft | Autosave/recover endpoint path used by browser smoke. | Saved draft recovers after refresh; duplicate row is not created in the checked retry path. |
| Messages | Message list/send/mark-read for smoke group, if production smoke policy permits mutation. | Group-scoped behavior holds; non-member check returns rejection if safely checked. |
| Business Desk | Proforma list/get/print-preview path or equivalent browser-backed API calls. | Works without changing operational ledger/report formulas. |

Production API smoke is passed only if all must-pass calls return expected success/rejection semantics and no 5xx appears in the checked MVP path.

### Rollback Evidence Criteria

Rollback is not considered ready until the Deploy Owner can show:

- pre-deploy file backup exists and is restorable;
- pre-deploy database backup exists and is restorable;
- uploaded package/artifact/file list is recorded;
- database migration or runtime SQL changes, if any, are listed with reverse/restore approach;
- rollback owner is named and reachable during the smoke window;
- rollback trigger threshold is agreed before smoke starts;
- rollback procedure has a timestamped dry-read or prior known-good reference;
- post-rollback verification checklist is defined: app loads, login/session works, current report opens, known closed report opens, and no fatal production error remains;
- evidence excludes secrets: no passwords, tokens, private keys, raw cookies, or full customer financial dumps.

Rollback must be triggered or the deploy must be held if any of these occur:

- app shell cannot load or login/session is broken;
- current user/session leaks another user's data;
- Field Combat loses a saved money fact after visible saved state;
- proof retry duplicates a money row in production smoke;
- current period and historical final report are merged or selected final report mutates after current activity;
- closed group report package cannot open by `report_id`;
- package proof access is broken for authorized reviewer or exposed to unauthorized user;
- group messages leak across groups;
- Business Desk/proforma smoke mutates operational ledger formulas;
- a production 5xx or fatal frontend boot error blocks the ordinary MVP money loop;
- backup/rollback evidence is missing or cannot be used.

### Must-Pass Before CEO Can Use Production

CEO production use is allowed only after these are all green:

1. Deploy Owner confirms package selection, uploaded file/artifact list, and backup/rollback evidence.
2. App shell, assets, and authenticated session pass on production.
3. Mobile `390 x 844` can reach the ordinary MVP money loop without blocking overlap.
4. Field Combat saved draft survives refresh/return and does not silently submit/include/finalize.
5. Current period report/export is reachable and visibly separate from closed final-report export.
6. At least one closed final report opens by explicit `report_id`.
7. At least one `Закрытый групповой отчет` opens as a full archive package with print/PDF and package sections.
8. Authorized package proof access works for a safe smoke proof, or proof access is explicitly blocked by missing safe fixture and Project Director accepts that as a hold.
9. Group messages remain group-scoped.
10. Business Desk proforma preview/print path is reachable and does not alter operational money reports.
11. Travel/Advanced staging remains reachable without interfering with the core money loop.
12. No unresolved P0 production smoke failure remains.

QA conclusion: production smoke can be executed after Deploy Owner uploads the selected package and provides production URL plus backup/rollback evidence. Until those are available, this document is a plan, not production smoke execution evidence.

## Final Business MVP QA Evidence Pack - 2026-05-27

QA release position for final Chief Auditor gate: PASS. Residual surface QA is recorded as passed; no QA blocker remains for Project Director to request the final Business MVP Chief Auditor gate. This is not a production deploy approval; deployment package, production smoke, dirty-tree selection, backup, and rollback remain separate pre-deploy gates.

| Gate / Surface | Evidence anchor | QA result | Remaining non-blocking gaps |
| --- | --- | --- | --- |
| Foundation/current-historical reports | Historical backend recheck group `195`, report `371`, current income entry `90`, current Live Report tape `184`: historical detail/export stayed `1000 / 600 / 400`; current export showed carryover `400`, current income `50`, current Live Report expense `25`, and excluded old finalized income. UI evidence group `200`, report `406`, current income entry `100`, current Live Report tape `199`: mobile/tablet/desktop labels and actions separated `Текущий период` from `Закрытые финальные отчеты`. | PASS. Current open period and selected historical final report are separated for the proven `1000 -> 600 -> 400 -> 50 -> 25` path. | Legacy finalizations without `report_snapshot` still need an accessible fixture if Product wants that fallback release-gated; current status is P1/non-blocking for new-data Business MVP. |
| Field Combat no-data-loss | Final proof retry recheck run `20260526929348`: mobile/tablet/desktop groups `218/219/220`; original cash rows `176/178/180` stayed exactly once and received proof files; previous `next_tape_id` cards `252/258/264` did not receive duplicate rows; original `client_operation_id` replay stayed idempotent; no `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` request was observed. | PASS. Draft recovery, proof retry, idempotency, cash/card separation, and no silent submit/include/finalize are accepted fixed for the no-data-loss gate. | None blocking. Broader production device smoke remains a deploy concern, not a Business MVP evidence blocker. |
| Closed group report package | Closed package QA run `20260527816949`, group `222`, report `454`, admin `520`, member `521`: package endpoint `ledger_group_final_report_package` returned summary, participant reports, captures/proofs, money rows, accountable state, message refs, and audit refs. Proof downloads passed through package URLs for cash, card, and accountable proof ids. UI opened `Закрытый групповой отчет #454` on mobile/tablet/desktop; print/PDF included package sections; later current activity did not mutate the package. | PASS. One new closed group report opens as an immutable archive object by `report_id`, with cash/card/accountable evidence and proof access. | Package-wide downloadable file export beyond browser print/PDF is P1/non-blocking. Legacy reports without `report_package` should keep warning/fallback instead of pretending to be new packages. |
| Residual surface QA | Residual run `20260527968710`, anchor group `222`, report `454`; admin/member/non-member sessions. Group messages send/list/unread/mark-read passed and non-member list returned `not_group_member`. Closed package message refs stayed understandable. Business Desk/proforma API/UI create/list/open/print passed, including `PF-2026-0005`, and did not mutate `ledger_report`. Travel / Trip with Friends remained visible as staged; Advanced remained reachable. Mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900` navigation reached the proven money loop and residual surfaces without blocking overlap. | PASS. Required residual product surfaces are reachable and do not contradict the approved money-core gates. | First-class report-linked message schema, full travel settlement, full Business Desk integration, and broader social chat archive remain P1/post-MVP unless Project Director changes scope. |
| Final Chief Auditor routing | Approved gates read from `13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`: Foundation MVP, Field Combat no-data-loss, Closed group report package. Residual surface QA now passed after the Director-assigned final readiness classification. | PASS for QA evidence pack. | P1/non-blocking: same-second finalization cutoff hardening unless reproduced, exact server-rendered wording inside downloaded current export if Product requires it, and the legacy snapshot/package fallbacks above. P0 before production deploy remains separate: deploy package, production smoke, dirty-tree deploy selection, backup, and rollback. |

Final QA conclusion: no Business MVP QA blocker remains after residual surface PASS. Next owner: Project Director for final Chief Auditor gate routing.

## Open Findings

- 2026-05-26 QA recheck `20260526929348`: Field Combat proof retry duplicate-money blocker is accepted fixed on mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.
- Recheck scope:
  - forced first `on_the_go_upload_file` failure;
  - refresh and recovery after failed proof;
  - proof retry with the same selected file;
  - duplicate-row count across visible cash draft cards;
  - original `client_operation_id` idempotency replay;
  - forbidden action watch for `on_the_go_card_submit`, `on_the_go_card_include`, and `ledger_group_finalize_report`;
  - card/cash separation sanity check after the proof retry flow.
- Baseline:
  - local server answered `HTTP/1.1 200 OK` at `http://127.0.0.1:18889/app.php` with `X-Powered-By: PHP/8.3.6`;
  - `node --check public/assets/app.js` passed;
  - CLI PHP smoke remains environment-blocked because `php` is unavailable in this shell.
- Browser/HTTP evidence:
  - mobile user `qa-field-combat-proof-20260526929348-mobile390x844@example.test`, group `218`, draft `118`, `client_draft_id=draft-3b51c040-8ba4-41b4-b436-7e0faa91181c`, original tape `253`, previous `next_tape_id=252`, original row `176`, operation `op-7eb4ac35-bafd-43c3-b1e6-19b4338a0acf`;
  - tablet user `qa-field-combat-proof-20260526929348-tablet820x1180@example.test`, group `219`, draft `133`, `client_draft_id=draft-24464ddd-e9ed-42d0-9a64-2293a4809e46`, original tape `259`, previous `next_tape_id=258`, original row `178`, operation `op-f0016391-05ce-442f-aafb-1b05f479cc40`;
  - desktop user `qa-field-combat-proof-20260526929348-desktop1440x900@example.test`, group `220`, draft `148`, `client_draft_id=draft-ac892374-2d18-486e-aa7a-48944ecaed71`, original tape `265`, previous `next_tape_id=264`, original row `180`, operation `op-9a6c8007-d2ef-4916-b077-7bda23e4713e`.
- Actual result:
  - old draft recovery still passed: after `Сохранено`, refresh/module return/same-stream reselection restored the same cash row and same `client_draft_id`;
  - first proof upload was forced to `{ok:false,error:"qa_forced_upload_failure"}` and UI showed retry-needed proof state;
  - after refresh, proof retry context restored on the original `tape_id`, `session_id`, `client_draft_id`, `client_operation_id`, and `capture_id`;
  - proof retry uploaded the proof to the original saved capture: rows `176`, `178`, and `180` each ended with `files_count=1`;
  - the same `-25 Durable autosave row 20260526929348 <viewport>` appeared exactly once across visible cash draft cards on each viewport;
  - previous `next_tape_id` cards `252`, `258`, and `264` did not receive the duplicate cash row;
  - the original `client_operation_id` replay stayed idempotent;
  - UI proof retry did not add a new `on_the_go_signed_sync` request after the explicit idempotency replay;
  - no submit/include/finalize request was observed;
  - card stream sanity check passed: card rows `177`, `179`, and `181` were `noncash_out`, `reportable=0`, with cash delta/left at zero.
- Evidence artifacts:
  - JSON: `/tmp/findesk-field-combat-proof-recheck-20260526929348.json`;
  - screenshots:
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-mobile390x844-cash-refresh-recovered.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-mobile390x844-proof-retry-needed.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-mobile390x844-proof-failure-recovered.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-mobile390x844-proof-uploaded.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-mobile390x844-card-separated.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-tablet820x1180-cash-refresh-recovered.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-tablet820x1180-proof-retry-needed.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-tablet820x1180-proof-failure-recovered.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-tablet820x1180-proof-uploaded.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-tablet820x1180-card-separated.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-desktop1440x900-cash-refresh-recovered.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-desktop1440x900-proof-retry-needed.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-desktop1440x900-proof-failure-recovered.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-desktop1440x900-proof-uploaded.png`;
    - `/tmp/findesk-field-combat-proof-recheck-20260526929348-desktop1440x900-card-separated.png`.
- Status: PASS for this P0 recheck. This accepts the Frontend/UX duplicate-money proof retry fix. It does not by itself declare full product release readiness.

- Latest known full CLI smoke from earlier handoff passed after branding changes; current shell smoke is blocked because CLI `php` is unavailable, while the local server remains reachable. This remains a separate environment/pre-deploy concern, not a final Business MVP QA blocker.
- Business MVP mobile/tablet/desktop review is now covered in the Field Combat, current/historical report UI, closed package, and residual surface runs. Broader production device smoke remains pre-deploy.
- The `€1000 income -> €600 expense -> €400 carryover` scenario is covered by the historical/current report evidence above, including the later `€50` current income and `€25` current Live Report expense continuation.
- 2026-05-26 director verification: `node --check public/assets/app.js` passed after the instant-capture UI slice.
- 2026-05-26 director verification: local server serves updated `app.php`, `assets/app.js`, and `assets/app.css`.
- 2026-05-26 director verification: Playwright Chromium was installed into the local cache and mobile screenshots were captured.
- 2026-05-26 director verification: cash quick capture strip is visible on mobile; `+ Получили` inserts a `+` line start.
- 2026-05-26 director verification: card quick capture strip is visible on mobile; cash-only quick buttons are hidden and `- Карта` inserts a `-` line start.
- 2026-05-26 QA verification: instant field capture slice passed browser/API checks below on mobile/tablet/desktop.
- 2026-05-26 QA verification: historical finalized report detail/export works for new snapshot finalizations; previous current open-period combo blocker is superseded by the recheck pass recorded below.
- 2026-05-26 QA verification: current/historical report UI separation passed desktop/tablet/mobile checks for the `1000 -> 600 -> 400 -> 50 -> 25` scenario.

## Verification 2026-05-26 - Instant Field Capture

Scenario: baseline before QA slice verification.
Device/viewport: shell + local HTTP server.
User/role: QA Release Engineer.
Setup: `/home/alexey/GitHub/finance.brkovic.ltd`, HEAD `72b38e6`, origin/main `72b38e6`, dirty working tree expected.
Steps: ran `pwd`, `git status --short`, `git rev-parse --short HEAD`, `git rev-parse --short origin/main`, `php scripts/local-smoke.php http://127.0.0.1:18889`, fallback `curl -I --max-time 3 http://127.0.0.1:18889`, and `node --check public/assets/app.js`.
Expected: smoke either passes or is recorded as blocked; local server must answer; JS syntax must pass.
Actual: CLI `php` is unavailable (`php: command not found`), so smoke is blocked by environment; local server answers `HTTP/1.1 200 OK`; `node --check public/assets/app.js` passed.
Evidence: server check repeated at 2026-05-26 16:21 Europe/Podgorica; response header `X-Powered-By: PHP/8.3.6`.
Status: blocked for CLI smoke, pass for server reachability and JS syntax.
Owner if failed: environment/tooling, not product behavior.

Scenario: cash stream quick capture save, reopen exact rows, edit, delete.
Device/viewport: mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
User/role: `qa.instant.20260526141856@example.test`, local QA user id `477`.
Setup: Live Report -> `Наличные`; created draft cards via UI.
Steps: opened cash stream, created a new card, used `+ Получили`, `- Наличные`, and `Фото`; saved; returned to list; reopened saved card; tapped pencil; deleted from inside the opened card.
Expected: cash quick buttons visible; card quick button hidden; photo opens proof picker; saved card opens in view mode with exact normalized rows; pencil enables editing without losing rows; delete removes the opened card and returns to list.
Actual: pass on all three viewports. Reopened rows matched exact strings:
- mobile card `146`: `+100 qa-20260526141856-mobile390x844-cash-received` and `-40 qa-20260526141856-mobile390x844-cash-cash-spent`;
- tablet card `149`: `+100 qa-20260526141856-tablet820x1180-cash-received` and `-40 qa-20260526141856-tablet820x1180-cash-cash-spent`;
- desktop card `151`: `+100 qa-20260526141856-desktop1440x900-cash-received` and `-40 qa-20260526141856-desktop1440x900-cash-cash-spent`.
Photo action set proof input to `accept="image/*"` and `capture="environment"`. Deleted cards returned `card_not_found` on detail API and disappeared from list.
Evidence screenshots: `/tmp/findesk-qa-20260526141856-mobile390x844-cash-editor.png`, `/tmp/findesk-qa-20260526141856-tablet820x1180-cash-editor.png`, `/tmp/findesk-qa-20260526141856-desktop1440x900-cash-editor.png`.
Status: pass.
Owner if failed: none.

## Verification 2026-05-27 - Local SEO/PWA QA After Frontend Implementation

Scenario: baseline scope and environment for public SEO/PWA QA.
Device/viewport: shell + existing local PHP server at `http://127.0.0.1:18889`.
User/role: QA Release Engineer / SEO QA.
Setup: read `docs/AI_TEAM/23_SEO_QA_CHECKLIST.md`, `docs/AI_TEAM/21_SEO_GROWTH_STRATEGY.md`, `docs/AI_TEAM/22_TECHNICAL_SEO_INFRA_CHECK.md`, `public/index.php`, `public/app.php`, `public/robots.txt`, `public/sitemap.xml`, `public/manifest.webmanifest`, and `public/service-worker.js`. No production smoke was executed. No application code was changed.
Steps: checked tool availability and local server reachability.
Expected: local public root is testable; PHP lint runs only if `php` exists; mobile visual check runs only if Playwright/browser exists.
Actual: local server `127.0.0.1:18889` responded. `php` CLI was not available in PATH, so PHP lint was environment-blocked. `playwright`, `playwright-core`, `@playwright/test`, `puppeteer`, `chromium`, `chromium-browser`, and `google-chrome` were not available, so the mobile `390 x 844` visual overlap check was environment-blocked. `node`, `npm`, `curl`, `xmllint`, and `file` were available.
Status: pass for shell/server baseline; blocked for PHP lint and browser-only visual QA by environment.

Scenario: local HTTP 200 and public root content.
Device/viewport: local HTTP server, raw HTTP/HTML checks.
Setup: `curl` against `http://127.0.0.1:18889`.
Steps: requested `/`, `/index.php`, `/robots.txt`, `/sitemap.xml`, `/manifest.webmanifest`, `/service-worker.js`, `/app.php`, and referenced brand/PWA assets. Parsed root HTML for visible public signals.
Expected: public root returns HTTP 200, does not redirect to `/app.php`, is not blank, shows FinDesk public content, and does not expose debug/private app output.
Actual: pass.
- `/` -> `200 text/html; charset=UTF-8`
- `/index.php` -> `200 text/html; charset=UTF-8`
- `/robots.txt` -> `200 text/plain; charset=UTF-8`
- `/sitemap.xml` -> `200 application/xml`
- `/manifest.webmanifest` -> `200 application/manifest+json`
- `/service-worker.js` -> `200 application/javascript`
- `/app.php` -> `200 text/html; charset=UTF-8`
- root HTML bytes: `7713`
- root HTML contains `FinDesk`, `Open Private App`, `Install Web App`, and `/app.php` CTA link
- root HTML did not contain stack traces, PHP warnings/notices, fatal errors, or API debug markers in this check
Status: pass.

Scenario: public raw HTML SEO/meta layer.
Device/viewport: source-level local file check.
Setup: `public/index.php`.
Steps: counted and validated title, description, canonical, language, viewport, manifest, PWA meta, OG/Twitter tags, and root robots boundary.
Expected: exactly one useful title/description/canonical, canonical exactly `https://finance.brkovic.ltd/`, root not noindexed, metadata present in raw HTML.
Actual: pass.
- `title_count=1`
- `description_count=1`
- `canonical_count=1`
- canonical is `https://finance.brkovic.ltd/`
- `<html lang="en">` present
- viewport present
- manifest link present
- theme color and Apple PWA meta present
- root `noindex` absent; root robots is indexable
- OG present: `og:title`, `og:description`, `og:url`, `og:image`
- Twitter present: `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`
Status: pass.

Scenario: JSON-LD presence, parse, canonical consistency, and privacy boundary.
Device/viewport: Node parse of `public/index.php`.
Setup: extracted `<script type="application/ld+json">` blocks from raw source.
Steps: parsed every JSON-LD block with `JSON.parse`; checked for public canonical identity and private route/data leakage markers.
Expected: JSON-LD exists, parses, identifies public FinDesk/brkovic.ltd identity, agrees with canonical, and does not expose app/API/storage/user/report internals.
Actual: pass.
- `jsonld_blocks=1`
- JSON parsed successfully
- JSON-LD contains `https://finance.brkovic.ltd/`
- private route refs found: `false` for `/app.php`, `/api.php`, `/storage/`
- private id/token refs found: `false` for `report_id`, `group_id`, invite/token/storage/API markers
- email-like refs found: `false`
- schema graph includes public `Organization`, `WebSite`, `SoftwareApplication`, and `WebApplication`
Status: pass.

Scenario: robots, sitemap, and app noindex boundary.
Device/viewport: local file + local HTTP checks.
Setup: `public/robots.txt`, `public/sitemap.xml`, `public/app.php`.
Steps: validated robots directives; parsed sitemap with `xmllint`; checked app source and served app HTML for `noindex,nofollow`.
Expected: robots allows `/`, blocks `/app.php`, `/api.php`, `/storage/`, sitemap is valid XML with public URL only, and app remains noindex.
Actual: pass.
- robots `Allow: /` present
- robots `Disallow: /app.php` present
- robots `Disallow: /api.php` present
- robots `Disallow: /storage/` present
- robots sitemap points to `https://finance.brkovic.ltd/sitemap.xml`
- `xmllint --noout public/sitemap.xml` passed
- served sitemap parsed; URL count `1`
- sitemap loc is only `https://finance.brkovic.ltd/`
- no `/app.php`, `/api.php`, `/storage/`, localhost, staging, or query-state URL in sitemap
- `public/app.php` source and served `/app.php` contain `<meta name="robots" content="noindex,nofollow">`
- served `/app.php` links `/manifest.webmanifest`
Status: pass.

Scenario: manifest JSON and install fields.
Device/viewport: local file + local HTTP checks.
Setup: `public/manifest.webmanifest`.
Steps: parsed manifest from disk and through local HTTP; checked install fields and required icons.
Expected: valid JSON, coherent install fields, app starts at `/app.php`, required icons present.
Actual: pass.
- manifest parsed from disk and from `http://127.0.0.1:18889/manifest.webmanifest`
- `id=/app.php`
- `start_url=/app.php`
- `scope=/`
- `display=standalone`
- `name=FinDesk by brkovic.ltd`
- `short_name=FinDesk`
- required icons present: `/assets/icon-192.png`, `/assets/icon-512.png`, `/assets/icon-maskable-512.png`
Status: pass.

Scenario: brand, social, favicon, Apple touch, and manifest icon assets.
Device/viewport: local file metadata + local HTTP checks.
Setup: `public/assets/*` and `public/favicon.ico`.
Steps: verified referenced assets exist, return HTTP 200, and have expected dimensions/types where `file` can report them.
Expected: OG/Twitter image, brand mark/logo, favicon, Apple touch icon, and manifest icons exist and fetch successfully.
Actual: pass.
- `/assets/brand-og.png` -> HTTP `200`, PNG `1200 x 630`
- `/assets/brand-mark.png` -> HTTP `200`, PNG `128 x 128`
- `/assets/brand-logo.png` exists, PNG `520 x 380`
- `/assets/apple-touch-icon.png` -> HTTP `200`, PNG `180 x 180`
- `/assets/icon-180.png` exists, PNG `180 x 180`
- `/assets/icon-192.png` -> HTTP `200`, PNG `192 x 192`
- `/assets/icon-512.png` -> HTTP `200`, PNG `512 x 512`
- `/assets/icon-maskable-512.png` -> HTTP `200`, PNG `512 x 512`
- `/assets/favicon-16x16.png` exists, PNG `16 x 16`
- `/assets/favicon-32x32.png` exists, PNG `32 x 32`
- `/assets/favicon.ico` and `/favicon.ico` exist as Windows icon resources
Status: pass.

Scenario: syntax, diff hygiene, service worker, and cache update sanity.
Device/viewport: shell checks.
Setup: `public/assets/app.js`, `public/service-worker.js`, current git diff.
Steps: ran required checks and inspected service-worker cache/version behavior.
Expected: JS syntax passes, git diff has no whitespace errors, service worker syntax is valid, cache name is sane and bumped when PWA/public assets changed, old caches are cleaned.
Actual: pass.
- `node --check public/assets/app.js` passed
- `node --check public/service-worker.js` passed
- `curl http://127.0.0.1:18889/service-worker.js | node --check` passed
- `git diff --check` passed
- current `CACHE_NAME=findesk-20260522-v134`
- cache name pattern is sane: `findesk-YYYYMMDD-vN`
- git diff shows cache bumped from `findesk-20260520-v10` to `findesk-20260522-v134`
- service worker has `self.skipWaiting()`, `self.clients.claim()`, and old `findesk-*` cache cleanup
- no `fetch` handler is present, so current service worker does not intercept/cache public HTML, robots, sitemap, manifest, API, or storage responses
Status: pass.

Scenario: mobile `390 x 844` visual overlap check.
Device/viewport: requested mobile browser viewport.
Setup: checked local browser automation/runtime availability.
Steps: attempted to locate Playwright/Puppeteer/browser binaries without installing new dependencies.
Expected: if Playwright/browser is available, open public landing at `390 x 844` and confirm no blocking overlap.
Actual: environment-blocked. No `playwright`, `playwright-core`, `@playwright/test`, `puppeteer`, `chromium`, `chromium-browser`, or `google-chrome` was available in this shell. No screenshot or browser overlap evidence was produced in this run.
Status: blocked by environment, not by observed product failure.
Owner if failed: Project Director / QA environment owner to provide browser runtime, or Deploy Owner to include this in approved production smoke.

Scenario: release classification for this local SEO/PWA check.
Device/viewport: QA release decision.
Setup: local checks above plus existing production NO-GO context from `22_TECHNICAL_SEO_INFRA_CHECK.md`.
Expected: separate local SEO/PWA implementation evidence from production release readiness.
Actual: machine-checkable local SEO/PWA checks passed. Full visual sign-off is incomplete because no browser/Playwright runtime is available here. Production deploy remains NO-GO under existing DB/backup/rollback controls and production smoke was intentionally not executed.
Status: BLOCKED for release/production acceptance; PASS for local non-visual SEO/PWA checks.
Owner if failed: Project Director / Deploy Owner for DB/files backup, package evidence, rollback controls, browser-backed smoke, and production SEO/PWA smoke after deploy is approved.

Scenario: card stream quick capture separation.
Device/viewport: mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
User/role: `qa.instant.20260526141856@example.test`, local QA user id `477`.
Setup: Live Report -> `Банковская карта`; created draft cards via UI.
Steps: confirmed quick strip contents; used `- Карта`; saved; reopened; verified API detail; deleted from inside opened card.
Expected: only `- Карта` and `Фото` visible from money quick actions; `+ Получили`, `- Наличные`, and `Подотчет` hidden; saved card restores exact row; only `noncash_out` rows created; physical cash deltas stay zero.
Actual: pass on all three viewports. Card cards `148`, `150`, `152` reopened exact `-60 ...-card-spent` rows, item type was only `noncash_out`, and each draft summary had `card_out=60`, `cash_left=0`, `cash_delta=0`, `reportable_count=0`. Deleted cards returned `card_not_found`.
Evidence screenshots: `/tmp/findesk-qa-20260526141856-mobile390x844-card-editor.png`, `/tmp/findesk-qa-20260526141856-tablet820x1180-card-editor.png`, `/tmp/findesk-qa-20260526141856-desktop1440x900-card-editor.png`.
Status: pass.
Owner if failed: none.

Scenario: `Подотчет` quick action opens accountable-money flow and does not create an expense.
Device/viewport: mobile `390 x 844`.
User/role: `qa.instant.20260526141856@example.test`, local QA user id `477`.
Setup: cash stream draft card `153` with zero rows.
Steps: clicked `Подотчет`; checked active module/screen; compared card detail before and after click.
Expected: app opens `Деньги -> Подотчеты`; no Live Report expense row is created.
Actual: `moduleMoney` opened with `data-advanced-current-screen="advances"`, menu label `Подотчеты`, `advanceList` visible; card item count stayed `0 -> 0`; cleanup delete succeeded.
Evidence: browser/API run `20260526141856`, card `153`.
Status: pass.
Owner if failed: none.

Scenario: quick capture does not enter final report without FinDesk review/acceptance.
Device/viewport: API verification with browser-authenticated QA user.
User/role: `qa.instant.20260526141856@example.test`, local QA user id `477`.
Setup: isolated QA group `184`, seeded `€1000` cash income, then created a cash quick card with `-35 qa-draft-not-final-20260526141856`.
Steps: checked group `ledger_report` after draft save, after submit to FinDesk, and after include/acceptance.
Expected: draft and submitted/on-review quick capture must not be in final report; accepted card may enter report.
Actual: pass. Report after draft: `income=1000`, `expense=0`, `records=1`. Report after submit: `income=1000`, `expense=0`, `records=1`. Report after include: `income=1000`, `cash_expense=35`, `expense=35`, `records=2`. Test card was un-included/unsubmitted/deleted; seed ledger entry `72` was deleted after evidence capture.
Evidence: browser/API run `20260526141856`, group `184`.
Status: pass.
Owner if failed: none.

Scenario: card stream does not affect physical cash.
Device/viewport: API verification with browser-authenticated QA user.
User/role: `qa.instant.20260526141856@example.test`, local QA user id `477`.
Setup: isolated QA group `184` with `€1000` cash income; created card stream quick card with `-60 qa-card-physical-cash-20260526141856`.
Steps: compared `ledger_balance.available_cash_balance` before card draft, after draft, after submit, and after include.
Expected: available physical cash remains `€1000`; card spending appears only as noncash/card expense.
Actual: pass. Available cash sequence was `[1000, 1000, 1000, 1000]`; after include `noncash_expense=60`, `cash_expense=0`, `available_cash_balance=1000`; card detail had `cash_delta=0`, `cash_left=0`, `card_out=60`, `reportable_count=1`. Test card was un-included/unsubmitted/deleted.
Evidence: browser/API run `20260526141856`, group `184`.
Status: pass.
Owner if failed: none.

Scenario: cash submit sequence guard.
Device/viewport: API + browser UI verification.
User/role: `qa.instant.20260526141856@example.test`, local QA user id `477`.
Setup: isolated QA group `184`; first cash card `156` submitted to FinDesk; next cash card `145` had draft row `-5 qa-seq-second-20260526141856`.
Steps: attempted to submit second card while first remained submitted/on review; triggered UI highlight for blocking card.
Expected: second card submit is blocked; first card stays submitted; second card stays draft; UI shows `Обработайте предыдущую запись в FinDesk.` and highlights the blocking first card.
Actual: pass. API returned `error=another_live_report_waits_findesk`, `blocking_card_id=156`, message `Обработайте предыдущую запись в FinDesk.`; states after blocked submit were first `submitted`, second `draft`; UI hint showed the same message and row `156` had attention highlight. Test cards were unsubmitted/deleted.
Evidence: browser/API run `20260526141856`, group `184`.
Status: pass.
Owner if failed: none.

## Verification 2026-05-26 - Historical Finalized Report Backend Contract

Scenario: baseline before historical finalized report backend QA.
Device/viewport: shell + local HTTP server.
User/role: QA Release Engineer.
Setup: `/home/alexey/GitHub/finance.brkovic.ltd`, HEAD `72b38e6`, origin/main `72b38e6`, dirty working tree expected.
Steps: ran `pwd`, `git status --short`, `git rev-parse --short HEAD`, `git rev-parse --short origin/main`, `curl -I --max-time 3 http://127.0.0.1:18889`, and `php scripts/local-smoke.php http://127.0.0.1:18889`.
Expected: local server responds; full smoke result is recorded.
Actual: local server returned `HTTP/1.1 200 OK` with `X-Powered-By: PHP/8.3.6`; CLI smoke is environment-blocked because `php` is unavailable in this shell (`php: command not found`).
Status: pass for server reachability, blocked for CLI smoke by environment/tooling.

Scenario: new historical finalization snapshot keeps the selected final report immutable.
Device/viewport: API verification against `http://127.0.0.1:18889`.
User/role: `qa-historical-final-20260526153331-diag@example.test`, local QA user id `487`.
Setup: isolated group `191`; created `EUR 1000` cash income `QA historical final income 20260526153331-diag`, Live Report cash card `171`, `EUR 600` cash expense `QA historical final expense 20260526153331-diag`, included card, then finalized report.
Steps: called `ledger_group_finalize_report`, `ledger_group_final_report_list`, `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, `ledger_group_final_report_excel`, `ledger_group_open_received_funds`, and current `ledger_group_google_sheet`; then added a later current-period cash income `EUR 50` and repeated historical/current checks.
Expected: finalization returns `report_id`; list shows it with `snapshot_available=true`; historical detail/export stays `income=1000`, `expense=600`, `cash_balance=400`, `admin_cash_left=400`; current export starts from carryover `400`, excludes the old final income/expense, and later current-period income does not mutate the selected historical report/export.
Actual: pass for this path. `ledger_group_finalize_report` returned `report_id=342`; final report list had `snapshot_available=true`; detail totals were `income=1000`, `expense=600`, `cash_balance=400`, `admin_cash_left=400`; historical Google Sheet and Excel both contained the old `1000 / 600 / 400` report and did not contain later current income. Current export after finalization contained `Переходящий остаток` `400.00` and did not contain the old income/expense purpose; after later income it contained the current income purpose and `50.00`.
Evidence: group `191`, report `342`, card `171`; historical Google Sheet returned `rows=2`; Excel returned HTTP `200` and `Content-Type: application/vnd.ms-excel; charset=utf-8`.
Status: pass.
Owner if failed: none.

Scenario: current open-period export after finalization when a later income and a current included Live Report coexist.
Device/viewport: API verification against `http://127.0.0.1:18889`.
User/role: `qa-combo-20260526153406-combo@example.test`, local QA user id `488`.
Setup: isolated group `192`; finalized the same `EUR 1000 income -> EUR 600 cash Live Report expense -> EUR 400 carryover` flow as report `348`; then added current-period cash income `EUR 50` with purpose `current income 20260526153406-combo` and included a current Live Report cash expense `EUR 25`.
Steps: repeated historical detail/export and current open-period export checks after the later current income plus current included Live Report.
Expected: selected historical report/export remains `1000 / 600 / 400`; current export remains the current open-period truth with carryover `400`, current income `50`, current Live Report expense `25`, and no old finalized income as current income.
Actual: historical report/export still passed immutability, but current export failed. `ledger_group_final_report_detail`, historical Google Sheet, and historical Excel still showed `1000 / 600 / 400` and excluded later current entries. However `ledger_group_google_sheet` omitted the current income purpose while still showing the current Live Report aggregate. `ledger_group_open_received_funds` returned `entries: [{"id":175}]` instead of the expected income row with purpose `current income 20260526153406-combo`; `open_period.live_included` returned `cards=1`, `cash_expense=25`, `records=1`.
Evidence: group `192`, report `348`, current income ledger entry `84`, current Live Report tape `175`; current export `has_current_income=false`, `has_current_live_aggregate=true`, `has_old_income=false`.
Status: fail, backend blocker before Frontend/UX handoff.
Owner if failed: Backend Data Engineer.

Scenario: old finalizations without `report_snapshot`.
Device/viewport: API/code-path review.
User/role: QA Release Engineer.
Setup: current QA-created reports all contain `details.report_snapshot`; no accessible legacy finalization fixture without `report_snapshot` was available through the API for this QA user.
Steps: checked new final reports through API and reviewed the exposed error branch in `app/ledger.php`; also confirmed a non-existent report id returns the separate `final_report_not_found` error.
Expected: if an accessible old finalization without `report_snapshot` exists, historical detail/export returns `historical_snapshot_missing`.
Actual: not fully executable from current accessible test data. The code path returns `historical_snapshot_missing` when `ql_ledger_group_final_report_for_user()` finds an accessible finalization row but `ql_ledger_group_final_report_snapshot()` is absent/invalid; no accessible legacy row was available to prove this through API.
Status: waiting for fixture/evidence, not marked pass.
Owner if failed: Backend Data Engineer to provide an accessible legacy finalization fixture if this fallback must be release-gated.

Scenario: current open-period export combo regression recheck after Backend/Data fix.
Device/viewport: API verification against `http://127.0.0.1:18889`.
User/role: `qa-combo-recheck-20260526155619@example.test`, local QA user id `491`.
Setup: isolated group `195`; created `EUR 1000` cash income `qa old finalized income 20260526155619`, Live Report cash card `182`, `EUR 600` cash expense `qa old finalized live expense 20260526155619`, included card, then finalized report `371`; after finalization created current income ledger entry `90` for `EUR 50` and current Live Report cash card `184` with `EUR 25` expense.
Steps: called `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, `ledger_group_final_report_excel`, `ledger_group_open_received_funds`, and current `ledger_group_google_sheet`.
Expected: selected historical final report/export remains `1000 / 600 / 400`; `ledger_group_open_received_funds.entries` returns the current income ledger row, not a Live Report tape row; current export contains carryover `400`, current income `50`, current Live Report expense `25`, and excludes the old finalized income as current income.
Actual: pass. Historical detail totals stayed `income=1000`, `expense=600`, `cash_balance=400`, `admin_cash_left=400`; historical Google Sheet returned `rows=2`; historical Excel returned HTTP `200` with `Content-Type: application/vnd.ms-excel; charset=utf-8`; neither historical export contained the later current income or current Live Report expense. `ledger_group_open_received_funds.entries` returned one current income row: `id=90`, `entry_type=income`, `money_type=cash`, `amount=50`, `purpose=qa current income 20260526155619`; no bare Live Report tape row was present. Current export returned `rows=3` and contained `Переходящий остаток` `400.00`, current income `50.00`, and current Live Report aggregate `Включенные живые отчеты текущего периода` `25.00`; it did not contain the old finalized income/expense purposes.
Evidence: group `195`, report `371`, finalized Live Report tape `182`, current income ledger entry `90`, current Live Report tape `184`.
Status: pass; previous combo regression blocker is accepted fixed for this backend contract check. Full release readiness is not declared here.
Owner if failed: none.

## Verification 2026-05-26 - Current And Historical Report UI

Scenario: UI separation between current open period and closed final reports.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
User/role: `qa-ui-report-20260526164242@example.test`, local QA user id `496`.
Setup: isolated group `200`; created `EUR 1000` cash income `qa ui old finalized income 20260526164242`, Live Report cash card `197`, `EUR 600` cash expense `qa ui old finalized live expense 20260526164242`, included card, finalized report `406`, then created current income ledger entry `100` for `EUR 50` and current Live Report cash card `199` with `EUR 25` expense.
Steps: verified backend baseline for historical detail/export and current export; opened the report UI; selected group `200`; checked labels, selected final report detail, current export preview, current Google export action, historical Google export action, current Excel URL, final Excel `report_id`, and control overlap/reachability on all three viewports.
Expected: current period UI is labeled `Текущий период`; current export action is labeled `Экспорт текущего периода`; current export stays open-period truth and does not show old finalized income `1000` as current income; closed reports UI is labeled `Закрытые финальные отчеты`; selected historical report shows `report_id`; historical export action is labeled `Экспорт финального отчета`; historical export remains `1000 / 600 / 400`; later current entries do not appear inside historical report/export; mobile/tablet/desktop controls do not overlap and export actions are reachable.
Actual: pass on all three viewports. UI labels were visible: `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, `Экспорт финального отчета: Excel`, `Экспорт финального отчета: Google`. Selected detail showed `report_id=406` and historical metrics `income=1000`, `expense=600`, `admin_cash_left=400`, `balance=400`. Current export preview showed open-period total `425`, carryover `400`, current income `50`, and current Live Report expense `25`; it did not show the old finalized income purpose. Current Google export returned `rows=3`, contained carryover `400`, current income `50`, current Live Report expense `25`, and did not contain the old finalized income. Historical Google export returned `rows=2`, contained `1000 / 600 / 400`, and did not contain current income/current Live Report expense. Current Excel action resolved to `/api.php?action=ledger_group_excel&group_id=200`; final Excel action carried `data-final-report-excel="406"`. DOM bounding-box checks found no overlap for the checked report controls or current export modal controls.
Evidence screenshots:
- `/tmp/findesk-ui-current-historical-20260526164242-mobile390x844-report.png`
- `/tmp/findesk-ui-current-historical-20260526164242-mobile390x844-current-export-modal.png`
- `/tmp/findesk-ui-current-historical-20260526164242-tablet820x1180-report.png`
- `/tmp/findesk-ui-current-historical-20260526164242-tablet820x1180-current-export-modal.png`
- `/tmp/findesk-ui-current-historical-20260526164242-desktop1440x900-report.png`
- `/tmp/findesk-ui-current-historical-20260526164242-desktop1440x900-current-export-modal.png`
Status: pass.
Owner if failed: none.

## Closed Findings

- 2026-05-26 Field Combat proof retry duplicate-money P0: rechecked after Frontend/UX fix and passed. Evidence run `20260526929348`, groups `218/219/220`, original cash rows `176/178/180`, previous `next_tape_id` cards `252/258/264` clean.
- 2026-05-26 current open-period export combo regression after historical finalization: rechecked after Backend/Data fix and passed. Evidence group `195`, report `371`, current income entry `90`, current Live Report tape `184`.
- 2026-05-26 current/historical report UI separation: desktop/tablet/mobile QA passed. Evidence group `200`, report `406`, current income entry `100`, current Live Report tape `199`.

## Verification 2026-05-26 - Field Combat UI No-Data-Loss

Scenario: baseline before Field Combat browser/HTTP QA.
Device/viewport: shell + local HTTP server + Playwright Chromium.
User/role: QA Release Engineer.
Setup: `/home/alexey/GitHub/finance.brkovic.ltd`, HEAD `72b38e6`, origin/main `72b38e6`, dirty working tree expected.
Steps: read `HANDOFF_2026-05-26_FIELD_COMBAT_UI_QA.md`, `12_FIELD_COMBAT_MODE.md`, Backend/Data and Frontend/UX handoff evidence, then checked local server reachability at `http://127.0.0.1:18889`.
Expected: local server answers; browser QA can exercise active `Живой отчет` simple editor through UI and HTTP APIs.
Actual: local server answered `HTTP/1.1 200 OK` with `X-Powered-By: PHP/8.3.6`; Playwright browser QA executed against the local app.
Status: pass for server/browser reachability.
Owner if failed: none.

Scenario: autosaved cash row must survive refresh/return without data loss.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
User/role: QA-created users:
- mobile: `qa-field-combat-loss-20260526264416-mobile390x844@example.test`, group `204`;
- tablet: `qa-field-combat-loss-20260526264416-tablet820x1180@example.test`, group `205`;
- desktop: `qa-field-combat-loss-20260526264416-desktop1440x900@example.test`, group `206`.
Setup: opened `Живой отчет` -> `Наличные`, typed a cash row, and waited for UI sync state `Сохранено`.
Steps: typed `-25 Durable autosave row 20260526264416 <viewport>`, waited for successful `on_the_go_field_draft_save`, recorded original `draft_id`, `client_draft_id`, `tape_id`, and `session_id`, refreshed the browser, returned through the visible cash-stream path, inspected the editor value, and compared direct backend recovery for the original draft versus the UI's current localStorage draft.
Expected: after `Сохранено`, refresh/return restores the typed row in the UI and keeps the same active draft/session identity; a user in motion must not lose the typed money fact or get switched to an empty draft. Autosave must not submit/include/finalize.
Actual: fail, P0. On all three viewports, the typed row was not restored in the editor after refresh/return. The original backend draft still contained the typed row, but the UI changed localStorage to a new empty `client_draft_id` and displayed an empty editor:
- mobile: original draft `8`, `client_draft_id=draft-dd896cdb-b811-4bfa-8354-adaac1150d26`, tape `206`, session `146`; after return localStorage pointed to `draft-c289b294-295e-4279-857e-c8acee6ccf32`, backend raw notes for the UI-current draft were empty, while direct recovery of the original draft returned `-25 Durable autosave row 20260526264416 mobile390x844`;
- tablet: original draft `16`, `client_draft_id=draft-1069c32f-8c1e-4a51-a3c7-ac3d0fb33530`, tape `208`, session `148`; after return localStorage pointed to `draft-ae87923b-a35a-41ad-a13f-7c20fbcfc814`, backend raw notes for the UI-current draft were empty, while direct recovery of the original draft returned `-25 Durable autosave row 20260526264416 tablet820x1180`;
- desktop: original draft `22`, `client_draft_id=draft-8001ff75-2878-41ee-b198-490685278d88`, tape `210`, session `150`; after return localStorage pointed to `draft-94d53166-f9dd-4ccf-844e-a49b0772db0b`, backend raw notes for the UI-current draft were empty, while direct recovery of the original draft returned `-25 Durable autosave row 20260526264416 desktop1440x900`.
No `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` action was observed during autosave in this blocker reproduction.
Evidence screenshots:
- `/tmp/findesk-field-combat-20260526264416-mobile390x844-refresh-gate.png`
- `/tmp/findesk-field-combat-20260526264416-mobile390x844-after-stream-choice-empty.png`
- `/tmp/findesk-field-combat-20260526264416-tablet820x1180-refresh-gate.png`
- `/tmp/findesk-field-combat-20260526264416-tablet820x1180-after-stream-choice-empty.png`
- `/tmp/findesk-field-combat-20260526264416-desktop1440x900-refresh-gate.png`
- `/tmp/findesk-field-combat-20260526264416-desktop1440x900-after-stream-choice-empty.png`
Status: fail, P0 blocker. Field Combat UI no-data-loss QA is blocked before accepting proof failure/retry, idempotent save retry, or cash/card separation for this slice.
Owner if failed: Frontend UX Engineer.

## Verification 2026-05-26 - Field Combat Draft Recovery Identity Recheck

Scenario: baseline before Field Combat draft recovery identity recheck.
Device/viewport: shell + local HTTP server + Playwright Chromium.
User/role: QA Release Engineer.
Setup: `/home/alexey/GitHub/finance.brkovic.ltd`, HEAD `72b38e6`, origin/main `72b38e6`, dirty working tree expected.
Steps: read `HANDOFF_2026-05-26_FIELD_COMBAT_DRAFT_RECOVERY_RECHECK.md`, Field Combat rules, Frontend/UX fix notes, and reporting template; ran `node --check public/assets/app.js`; checked `http://127.0.0.1:18889`.
Expected: JS syntax passes; local server answers; browser/HTTP QA can rerun the old P0 and continue proof/retry/idempotency/cash-card checks.
Actual: `node --check public/assets/app.js` passed; local server returned `HTTP/1.1 200 OK` with `X-Powered-By: PHP/8.3.6`.
Status: pass for baseline readiness.

Scenario: old P0 recovery identity after `Сохранено`, refresh, module return, and same-stream reselection.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
User/role: QA-created users:
- mobile: `qa-field-combat-recheck-20260526109674-mobile390x844@example.test`, group `210`;
- tablet: `qa-field-combat-recheck-20260526109674-tablet820x1180@example.test`, group `211`;
- desktop: `qa-field-combat-recheck-20260526109674-desktop1440x900@example.test`, group `212`.
Setup: opened `Живой отчет -> Наличные`, typed `-25 Durable autosave row 20260526109674 <viewport>`, and waited for visible `Сохранено`.
Steps: refreshed browser, returned to `На бегу`, switched to another module and back, then reselected the same cash stream through the stream gate. Compared localStorage context after each step.
Expected: the row returns in the editor and the UI keeps/resolves to the same durable active `client_draft_id`; no new empty draft replaces the old one.
Actual: pass for the old blocker. The typed row returned on all three viewports and `client_draft_id` stayed stable:
- mobile: cash draft `65`, `client_draft_id=draft-608eb828-25d8-453a-8ce6-66c1f37aa98a`, tape `227`, session `164`;
- tablet: cash draft `79`, `client_draft_id=draft-4e343d97-8e4a-4819-88a8-2fa2ad9ce193`, tape `232`, session `169`;
- desktop: cash draft `93`, `client_draft_id=draft-d90b2692-3fee-41c4-97c4-3c26f67cb80c`, tape `237`, session `174`.
No `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` action was observed during autosave/recovery.
Evidence screenshots:
- `/tmp/findesk-field-combat-recheck-20260526109674-mobile390x844-cash-refresh-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-mobile390x844-same-stream-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-tablet820x1180-cash-refresh-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-tablet820x1180-same-stream-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-desktop1440x900-cash-refresh-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-desktop1440x900-same-stream-recovered.png`
Status: pass for draft recovery identity.

Scenario: proof failure/retry and idempotent save retry after recovery.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
Setup: continued from the recovered cash draft in run `20260526109674`; selected a proof file and forced the first `on_the_go_upload_file` call to return `{ok:false,error:"qa_forced_upload_failure"}`.
Steps: confirmed pending proof state before deliberate save; clicked `Сохранить`; confirmed `retry_needed`; repeated the captured `on_the_go_signed_sync` payload with the same `client_operation_id`; refreshed and recovered the failed proof state; reselected the file and retried upload. Then inspected both the original tape and the retry tape.
Expected: failed proof remains visible as retry-needed; repeated `client_operation_id` is idempotent; retry does not duplicate the money row; proof is marked saved only after backend `uploaded`; autosave does not submit/include/finalize.
Actual: blocked, P0. The failure state and idempotency passed, but retry duplicated the money row into a second cash draft card:
- mobile: first save wrote cash row `id=167` to original tape `227` without file; retry wrote the same `EUR 25` cash row `id=168` to tape `226` with `files_count=1`;
- tablet: first save wrote row `id=170` to original tape `232` without file; retry wrote duplicate row `id=171` to tape `231` with `files_count=1`;
- desktop: first save wrote row `id=173` to original tape `237` without file; retry wrote duplicate row `id=174` to tape `236` with `files_count=1`.
The repeated original `client_operation_id` returned `idempotent=true` on all three viewports, and the original tape still had only one active row. However after refresh following proof failure, localStorage kept the same `client_draft_id` but resolved its `tape_id` to the `next_tape_id` (`226`, `231`, `236`), so the user retry saved the same typed fact into the next cash card instead of attaching proof to the original row/card.
Proof states: first failure returned `retry_needed`; retry upload returned backend `uploaded`.
Forbidden actions: no `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` request was observed. Rows stayed `reportable=0`, so no silent review/include/finalization occurred in this run.
Evidence screenshots:
- `/tmp/findesk-field-combat-recheck-20260526109674-mobile390x844-proof-retry-needed.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-mobile390x844-proof-failure-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-mobile390x844-proof-uploaded.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-tablet820x1180-proof-retry-needed.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-tablet820x1180-proof-failure-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-tablet820x1180-proof-uploaded.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-desktop1440x900-proof-retry-needed.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-desktop1440x900-proof-failure-recovered.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-desktop1440x900-proof-uploaded.png`
Status: fail, P0 blocker.
Owner if failed: Frontend UX Engineer.

Scenario: cash/card separation during the same recheck.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
Setup: after the cash proof retry check, opened `Живой отчет -> Банковская карта`, typed `-12 Durable card row 20260526109674 <viewport>`, waited for `Сохранено`, reselected the same card stream, then clicked `Сохранить`.
Expected: card stream exposes card expense action, hides cash income/cash expense quick actions, saves only `noncash_out`, keeps physical cash delta at zero, and does not become reportable without review/include.
Actual: pass for this sub-check. Card stream quick controls were separated on all viewports; saved card rows were `noncash_out`, `amount=12`, `reportable=0`; summaries showed `card_out=12`, `cash_delta=0`, `cash_left=0`.
- mobile: card draft `75`, tape `230`, row `169`;
- tablet: card draft `89`, tape `235`, row `172`;
- desktop: card draft `103`, tape `240`, row `175`.
Evidence screenshots:
- `/tmp/findesk-field-combat-recheck-20260526109674-mobile390x844-card-separated.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-tablet820x1180-card-separated.png`
- `/tmp/findesk-field-combat-recheck-20260526109674-desktop1440x900-card-separated.png`
Status: pass for cash/card separation, but the Field Combat no-data-loss slice remains blocked by proof retry duplicate-money P0.

## Verification 2026-05-27 - Closed Group Report Package QA

Scenario: baseline and fixture selection for `Закрытый групповой отчет`.
Device/viewport: shell + local HTTP server + Playwright Chromium.
User/role: QA Release Engineer.
Setup: `/home/alexey/GitHub/finance.brkovic.ltd`, HEAD `72b38e6`, origin/main `72b38e6`, dirty working tree expected.
Steps: read `HANDOFF_2026-05-27_CLOSED_GROUP_REPORT_PACKAGE_QA.md`, Product Finance Architect findings, Backend/Data findings, Frontend/UX findings, Business MVP scope, and short report rules; checked local server reachability. Backend fixture `group_id=221`, `report_id=441` was inspected first.
Expected: use backend fixture if it covers all required checks; otherwise create a fresh QA fixture and record new ids.
Actual: server returned `HTTP/1.1 200 OK`. Backend fixture `441` was available and package download worked, but it had `card_noncash_spent=0`, so it could not prove the required cash/card split. QA created a fresh package fixture with cash, card/noncash, accountable, proofs, group message, and later current-period activity.
Status: pass for baseline; fresh fixture used for acceptance.

Scenario: API integrity for one closed group report package by `report_id`.
Device/viewport: authenticated HTTP/API checks.
User/role: admin `qa-closed-package-admin-20260527816949@example.test`, member `qa-closed-package-member-20260527816949@example.test`.
Setup: group `222`, report `454`, admin user `520`, member user `521`. Fixture ids:
- base income ledger entry `104`;
- cash Live Report tape `272`, capture `184`, proof `proof-454-on_the_go_capture-13`;
- card Live Report tape `274`, capture `185`, proof `proof-454-on_the_go_capture-14`;
- accountable advance `67`, advance tape `276`, capture `186`, rollover advance `68`, rollover tape `273`, proof `proof-454-ledger_entry-1`;
- later current-period income `106` and current Live Report tape `277` created after finalization.
Steps: created group income `1000`, included cash Live Report expense `600` with proof, included card/noncash Live Report expense `70` with proof, issued accountable cash `100`, accepted accountable spend `40` with proof, kept open accountable remainder/carryover `60`, sent a group message before finalization, finalized the group report, then read `ledger_group_final_report_package` by `report_id=454`.
Expected: endpoint returns `package_type=group_final_report`; package is not summary-only; it contains group summary, participant reports, captures, proofs, money rows, accountable state, messages, and audit refs.
Actual: pass. `ledger_group_final_report_package` returned package version data for one closed report. Counts: participants `2`, captures `2`, money rows `4`, proofs `3`, accountable items `2`, report-context audit messages `5`, general group refs `1`, audit refs `8`. Summary was `received_money=1000`, `physical_cash_spent=640`, `card_noncash_spent=70`, `admin_cash_left=300`, `accountable_money_left=60`, `cash_balance=360`, `balance=290`.
Cash/card split passed: cash capture had `cash_effect=-600`, `card_effect=0`; card capture had `cash_effect=0`, `card_effect=-70`. Accountable passed: accepted advance spend had `accountable_effect=-40`; rollover/open accountable item showed `open_remaining_cash=60` and `carryover_responsibility=60`, not expense.
Status: pass.

Scenario: package proof metadata and authorized reviewer download.
Device/viewport: authenticated HTTP/API checks as group admin/reviewer.
Setup: same package `report_id=454`.
Steps: read package proof metadata and downloaded every package proof URL as admin.
Expected: proof links are package metadata URLs using `ledger_group_final_report_proof_download&report_id=454&proof_id=...`; authorized reviewer download returns HTTP 200 and does not rely on owner-only Live Report proof flow.
Actual: pass. Downloaded package proofs through package URLs:
- `proof-454-on_the_go_capture-13`, HTTP `200`, body preview `QA closed package cash proof 20260527816949`;
- `proof-454-on_the_go_capture-14`, HTTP `200`, body preview `QA closed package card proof 20260527816949`;
- `proof-454-ledger_entry-1`, HTTP `200`, body preview `QA closed package advance proof 20260527816949`.
Status: pass.

Scenario: short table exports and immutability after later current-period activity.
Device/viewport: authenticated HTTP/API checks.
Setup: same package `report_id=454`; after finalization, QA added current income `55` and a current included Live Report expense `25`.
Steps: called `ledger_group_final_report_google_sheet`, `ledger_group_final_report_excel`, then reread `ledger_group_final_report_package` after current-period activity and compared the package digest.
Expected: Excel/Google remain short final-report tables, not full package exports; later current-period activity does not mutate the selected closed package.
Actual: pass. Google returned `rows=4` and contained the final report table data, not full package section labels such as `Отчет участника` or `Подотчет и ответственность`. Excel returned HTTP `200` and also remained a short table, not full package output. After current-period income and current Live Report activity, the package digest for summary, participants, captures, money rows, proofs, accountable, messages, and audit refs stayed unchanged.
Status: pass.

Scenario: user-facing closed package UI on mobile/tablet/desktop.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
Setup: logged in as admin `qa-closed-package-admin-20260527816949@example.test`, selected group `222`, opened Reports/`Сводка отчета`, clicked `Закрытый групповой отчет #454` from the closed reports list.
Steps: confirmed UI opened the package through `ledger_group_final_report_package`, checked labels and package sections, checked proof links, checked print/PDF action, checked Excel/Google labels, and ran bounding-box reachability/overlap checks for package actions and proof links.
Expected: user opens one `Закрытый групповой отчет` object by report id, not summary-only; visible sections include summary, participant reports, money facts/proofs, money rows/proofs, accountable/advance state, messages, audit refs; proof links use package URLs; print/PDF includes package sections; Excel/Google are labeled as short tables; layout has no blocking overlap or unreachable actions.
Actual: pass on all three viewports. UI text included `Закрытый групповой отчет #454`, `Один архивный объект`, `Сводка закрытого отчета`, `Отчеты участников`, `Отчет участника`, `Денежные факты и доказательства`, `Строки группового отчета`, `Подотчет и ответственность`, `Сообщения по отчету`, `Общий чат группы без прямой связи с отчетом`, and `Аудит закрытия`. It showed cash `600`, card `70`, accountable spend `40`, open accountable remainder `60`, package proof filenames near rows, and the general group message marked `не привязано к report_id`.
Package API request count from UI was `3` per viewport; package proof links found in the rendered package were `7` per viewport because proofs are repeated near participant/capture/money rows. `Печать / PDF закрытого отчета` triggered browser print and print content contained the package sections. Excel/Google actions were labeled `Краткая таблица: Excel` and `Краткая таблица: Google`. Layout checks found no blocking overlap/unreachable package actions.
Evidence screenshots:
- `/tmp/findesk-closed-package-20260527816949-mobile390x844-package.png`
- `/tmp/findesk-closed-package-20260527816949-tablet820x1180-package.png`
- `/tmp/findesk-closed-package-20260527816949-desktop1440x900-package.png`
Status: pass.
Owner if failed: none.

Scenario: legacy/no-package fallback.
Device/viewport: QA scope review.
Setup: primary acceptance used fresh package `report_id=454`; legacy/no-package fallback was not encountered in this user flow.
Expected: if encountered, old reports without `report_package` must warn that the view is not the new closed group report package.
Actual: not encountered in the accepted package flow. Frontend code path contains the warning text for `historical_package_missing`; this pass did not use it as release evidence because the accepted flow opened a real package.
Status: not applicable for this run, no blocker.

## Verification 2026-05-27 - Business MVP Residual Surface QA

Scenario: baseline and scope for residual business-MVP surface.
Device/viewport: local HTTP server, authenticated API, Playwright Chromium.
User/role: QA Release Engineer.
Setup: `/home/alexey/GitHub/finance.brkovic.ltd`, HEAD `72b38e6`, origin/main `72b38e6`, dirty working tree expected. Read `HANDOFF_2026-05-27_BUSINESS_MVP_RESIDUAL_SURFACE_QA.md`, `13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`, `10_BUSINESS_MVP_SCOPE.md`, Chief Auditor gates for Field Combat and Closed Group Report Package, and `SHORT_REPORT_TEMPLATE.md`. Used existing accepted closed package anchor group `222`, report `454`. Residual QA run id: `20260527968710`.
Expected: verify required residual surfaces without changing backend/API, UX implementation, or financial formulas; do not reopen accepted money-core gates unless a regression is observed.
Actual: local server answered and the accepted closed package anchor was reachable. QA used admin `qa-closed-package-admin-20260527816949@example.test`, member `qa-closed-package-member-20260527816949@example.test`, and non-member `qa-residual-outsider-20260527968710@example.test`.
Status: pass for baseline.

Scenario: group messages send/list/unread/mark-read and group scope.
Device/viewport: authenticated HTTP/API plus browser UI on mobile/tablet/desktop.
Setup: group `222`, report `454`, member-to-admin message `QA residual scoped unread 20260527968710`.
Steps: member sent a group message; admin read `message_unread`; admin marked group `222` read; admin listed group `222` messages; non-member attempted to list group `222`; browser UI opened `Сотрудники и группы`, selected group `222`, sent a visible message from each viewport.
Expected: group messages are reachable, unread state is visible through API, mark-read clears the selected group unread item, messages stay group-scoped, non-member cannot list messages, and UI send/list remains usable.
Actual: pass. Member message id `152` appeared in admin unread before mark-read and was absent after `message_mark_read`. `message_list` returned only `group_id=222` rows for the selected group. Non-member `message_list` returned `not_group_member`. Browser UI send/list passed on all three viewports:
- mobile `390 x 844`: `QA residual browser message 20260527968710 mobile390x844`;
- tablet `820 x 1180`: `QA residual browser message 20260527968710 tablet820x1180`;
- desktop `1440 x 900`: `QA residual browser message 20260527968710 desktop1440x900`.
Status: pass.

Scenario: report-context message references inside `Закрытый групповой отчет`.
Device/viewport: authenticated HTTP/API plus browser UI on mobile/tablet/desktop.
Setup: closed package report `454` from group `222`.
Steps: read `ledger_group_final_report_package`; opened `Закрытый групповой отчет #454` from the report history UI on mobile/tablet/desktop.
Expected: report-context references remain understandable and general group discussion is clearly marked as not directly linked to the report id.
Actual: pass. Package message snapshot contained report-context refs `5`, general group refs `1`, and schema note `group_messages has group scope only; immutable report-context messages are audit-derived until message rows get report_id/tape_id/capture_id/advance_id links.` UI showed `Сообщения по отчету`, marked unlinked group discussion with `не привязано к report_id`, and kept `Аудит закрытия` visible.
Status: pass.

Scenario: Business Desk / proforma create/list/open/print and separation from operational money formulas.
Device/viewport: authenticated HTTP/API plus browser UI on mobile/tablet/desktop.
Setup: admin `qa-closed-package-admin-20260527816949@example.test`, group `222`, run `20260527968710`.
Steps: through API saved company profile, created client `5`, created proforma `5` (`PF-2026-0005`, total `283.81`), listed proformas, opened the proforma with item rows, and compared `ledger_report` for group `222` before/after. Through browser UI opened `Business Desk`, `Proformas`, created a proforma, opened preview, and triggered `Print / Save PDF` with a print stub on each viewport.
Expected: Business Desk remains reachable; company/client/proforma surface is preserved; create/list/open/print path works; proformas do not mutate or pollute group cash/report formulas.
Actual: pass. API proforma create/list/get worked. Browser UI created, listed, opened, and printed a proforma on all three viewports. Print content contained `PROFORMA` and the generated UI proforma title. `ledger_report` for group `222` was unchanged after API Business Desk operations and unchanged again after browser proforma operations. UI separation copy remained visible: `Ledger stays clean for fast daily entries.`
Status: pass.

Scenario: Travel / Trip with Friends and Advanced staging.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
Setup: same admin session.
Steps: opened `Premium`, checked `Поездка с друзьями`, clicked its staged action, then opened Advanced through `Открыть Advanced`.
Expected: Travel/Trip marker remains visible or clearly staged; it does not mix into ordinary business cash report; Advanced remains reachable as non-MVP staging and does not hide required MVP actions.
Actual: pass. `Поездка с друзьями` was visible with `Подготовлено`. Clicking the staged action showed `Группа людей, общая копилка, расходы поездки и выравнивание балансов по взносам.` `Advanced Mode` remained visible and `Открыть Advanced` opened the money/advanced surface. Advanced showed staging screens including `Подотчеты`, `AI-анализ`, and `AI-аудит`. No ledger/report formula mutation was observed.
Status: pass.

Scenario: final mobile/tablet/desktop navigation reachability across the business-MVP path.
Device/viewport: Playwright Chromium on mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`.
Setup: admin session, group `222`, report `454`.
Steps: on each viewport navigated through: On the Go field capture, report review/finalization area, closed group report package, group messages, Business Desk/proforma, Travel staging, and Advanced. The On the Go route used visible stream gate navigation: `Выберите отчет` -> `Наличные` -> new live-report card. Leaving the live-report editor used the visible back route to cards/gate, then the gate menu or main module menu for other modules. Ran center/bounding-box checks on primary actions used during the flow.
Expected: no blocking overlap or unreachable primary action; user can reach all required residual surfaces and proven money-loop entrances on mobile/tablet/desktop.
Actual: pass. Required actions were reachable on all three viewports: On the Go save, report review summary action, closed report row `454`, group message send, proforma create/print, Trip staged action, and Advanced open action. Browser observed expected API calls including `ledger_group_final_report_package`, `message_send`, `message_list`, `message_mark_read`, `proforma_create`, `proforma_get`, `ledger_report`, and `ledger_group_open_received_funds`. No residual surface created a financial contradiction with cash/card/accountable/report-package behavior.
Evidence screenshots:
- `/tmp/findesk-business-mvp-residual-20260527968710-mobile390x844-closed-package.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-mobile390x844-group-messages.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-mobile390x844-business-proforma.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-mobile390x844-travel-advanced.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-tablet820x1180-closed-package.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-tablet820x1180-group-messages.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-tablet820x1180-business-proforma.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-tablet820x1180-travel-advanced.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-desktop1440x900-closed-package.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-desktop1440x900-group-messages.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-desktop1440x900-business-proforma.png`
- `/tmp/findesk-business-mvp-residual-20260527968710-desktop1440x900-travel-advanced.png`
Status: pass.
Owner if failed: none.

## Verification 2026-05-27 - Web Designer Branding Visual QA

Scenario: logo/favicons visual alignment and availability.

Device/viewport: browser screenshots on `390x844`, `820x1180`, `1440x900` for `index.php` and `app.php`.
User/role: QA Release Engineer.
Setup: local server `http://127.0.0.1:18889`; updated branding files from Web Designer pass.
Steps:

- capture full-page screenshots for both pages on all three viewports;
- visually inspect logo lockup and brand pill positions for clipping/shift;
- verify favicon/icon links exist in page metadata for expected sizes.

Expected:

- logo/brand lockup is visually stable and centered/consistent;
- no obvious crop/shift across viewports;
- favicon metadata is consistent with available assets.

Actual:

- PASS for all checked sizes and routes.
- no blocking overlap or obvious crop on captured screenshots.

Evidence:

- `/tmp/findesk-web-designer-20260527/index-mobile390x844.png`
- `/tmp/findesk-web-designer-20260527/app-mobile390x844.png`
- `/tmp/findesk-web-designer-20260527/index-tablet820x1180.png`
- `/tmp/findesk-web-designer-20260527/app-tablet820x1180.png`
- `/tmp/findesk-web-designer-20260527/index-desktop1440x900.png`
- `/tmp/findesk-web-designer-20260527/app-desktop1440x900.png`

Status: pass.
Owner if failed: none.
