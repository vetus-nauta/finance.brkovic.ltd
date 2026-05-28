# Tasks To Others: Frontend UX Engineer

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 before next deploy approval
Status: ready after package selection/upload
Context: Frontend/UX locally closed the current residual pack: login cache/copy, On-the-go refresh state, mobile card overlap, scanner modal exit, and Notes-style editor safety.

Request: run focused browser/PWA smoke for this frontend residual pack.

Acceptance criteria:

- Login/code screen shows current FinDesk access-code wording after normal reload, hard reload, and returning PWA session.
- On refresh, the app returns to the last safe working zone: stream gate, live-report card list, archive card list, or open editor; it must not reset to generic `Живые отчеты` when a more precise state exists.
- On phone around `390 x 844`, live-report card row text and action buttons do not overlap.
- Receipt scanner modal has an obvious `Закрыть` path and Escape/backdrop close does not corrupt the current live report.
- Notes-style field editor keeps a large usable note area, visible `Было / Приход / Расход / Стало`, and reachable paperclip/scan/camera controls.
- No duplicate money rows, no proof retry regression, and no backend/API/formula changes are introduced.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

---

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 before SEO/PWA sign-off
Status: ready after deployment to production/staging origin
Context: Public FinDesk landing received primary technical SEO/PWA metadata. Private `app.php` remains noindex and was not edited.

Request: run production-origin SEO/PWA smoke.

Acceptance criteria:

- Verify `/` returns 200 and contains canonical `https://finance.brkovic.ltd/`.
- Verify title, description, OG/Twitter image metadata, and JSON-LD are visible in rendered HTML.
- Verify `public/app.php` still emits `noindex,nofollow`.
- Verify `robots.txt` blocks `/app/`, `/app.php`, `/api/`, `/api.php`, `/storage/` and exposes the sitemap.
- Verify `sitemap.xml` contains only `https://finance.brkovic.ltd/`.
- Verify `manifest.webmanifest` loads, parses, keeps `start_url` as `/app.php`, and icons return 200.
- Verify PWA install CTA still opens install instructions and native prompt where supported.
- Verify `/assets/brand-og.png`, manifest icons, favicon files, CSS, JS, and service worker return 200 on production.

## To Project Director / Content Owner

Date: 2026-05-27
Priority: P1
Status: needs content decision
Context: English remains the only real public landing language. No fake localized pages were created.

Request: decide whether public multilingual landing pages are required and provide real localized copy if yes.

Acceptance criteria:

- Provide approved localized landing content before any `hreflang` or localized sitemap URLs are added.
- Provide real app screenshot assets if manifest `screenshots` should be enabled.
- Confirm whether public pricing, organization URL, privacy policy URL, or support/contact URL should be added to structured data later.

## To Deploy Owner

Date: 2026-05-27
Priority: P0 for production hygiene
Status: confirm during deployment
Context: Robots blocks private paths, but server routing and web-root configuration still control actual exposure.

Request: confirm private runtime paths are not exposed beyond intended public endpoints.

Acceptance criteria:

- Confirm web root points to `public/` or otherwise prevents direct exposure of backend `app/` files.
- Confirm `/storage/` is not publicly listable.
- Confirm production response for `/app.php` remains noindex.
- Do not deploy local reset/test support files unless Project Director explicitly approves.

---

# Tasks To Others: Frontend UX Engineer

## To Project Director

Date: 2026-05-27
Priority: P0 before production deploy
Status: needs owner decision
Context: Frontend/UX prepared the production package sanity checklist. No application code was changed.

Request: choose the production deploy mode and exact file bundle before any upload.

Acceptance criteria:

- Decide whether production uses a full current working-tree bundle or a narrow selected MVP runtime bundle.
- Do not upload `public/reset-local.php`, `scripts/start-local.sh`, `test-results/`, local config/secrets, or unrelated untracked/generated files unless explicitly approved.
- Confirm whether untracked or staging-adjacent files such as `app/ai.php` and `public/assets/brand-logo.png` belong in production.
- Confirm whether `deploy/on_the_go_sessions_runtime.sql` is required for the production database before frontend smoke.
- Ensure file and database backup plus rollback copy exist before upload.
- Hand QA Release Engineer the exact uploaded file list and production URL.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

---

## To Backend/Data Engineer

Date: 2026-05-28
Priority: P1 / required before scanner release gate
Status: Frontend local prototype exists
Context: Receipt Scanner can now generate a cleaned one-page PDF in browser and pass it through the existing Live Report proof upload path.

Request: implement first-class scanner proof storage/API.

Acceptance criteria:

- Store original image/file and cleaned PDF as linked artifacts.
- Store processing metadata: corners, cleanup level, monochrome flag, generation version, source hash, PDF hash.
- Link scanner artifacts to capture/tape/draft/report package/final report/archive.
- Preserve proof retry/no-data-loss semantics.
- Existing single-file proof upload must not lose backward compatibility.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

## To QA Release Engineer

Date: 2026-05-28
Priority: P1 before deploy package selection
Status: ready after Backend/Data storage decision or for local UX smoke
Context: Frontend scanner prototype is local only.

Request: prepare/run real-device scanner smoke when a target build is selected.

Acceptance criteria:

- `Скан` opens scanner modal from Live Report.
- iPhone Safari PWA and Android Chrome can capture/select image.
- Dragging all four corners works by touch.
- Cleanup slider and black-and-white toggle produce a readable proof.
- `Прикрепить PDF` produces a PDF proof and does not duplicate money rows.
- Refresh/retry does not lose money row or proof state.
- Archive/final report proof access is verified only after Backend/Data storage is implemented.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

---

## To Project Director

Date: 2026-05-28
Priority: P0 if receipt scanning is required for the next live-report UX slice
Status: needs scope decision
Context: Frontend/UX recorded a dedicated Receipt Scanner UI task card. No runtime code was changed.

Request: approve the receipt scanner MVP scope before implementation starts.

Acceptance criteria:

- Confirm the scanner opens as a dedicated screen/sheet from the open `Живой отчет`.
- Confirm source support: camera capture and file picker for image/PDF.
- Confirm MVP includes auto frame where possible, manual corner handles, perspective correction, cleanup, and preview modes for original/cleaned/PDF.
- Confirm required action labels: `Переснять`, `Готово`, `Прикрепить`.
- Decide whether OCR/text extraction is out of scope for the first scanner release.
- Confirm that refresh-state preservation is required for active report/card/scanner draft.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

---

## To Backend/Data Architect

Date: 2026-05-28
Priority: P0 after Project Director approves scanner scope
Status: waiting for product scope
Context: Receipt scanner output must attach back to the active live report item without mutating report totals or creating duplicate rows.

Request: define the scanner proof attachment/storage contract.

Acceptance criteria:

- Define whether scanner output stores original image, cleaned image, generated PDF, or all three.
- Define attachment metadata fields for source type, corner points, perspective/cleanup version, preview type, and live report/card linkage.
- Define draft persistence rules so refresh recovery can restore scanner state without submitting/finalizing a report.
- Confirm API behavior for replacing a pending scan versus adding another proof.
- Confirm file size/type limits and mobile upload retry behavior.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

---

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 when scanner implementation enters a deploy package
Status: blocked until implementation exists
Context: Receipt scanner UX must be proven on mobile browsers, especially iPhone Safari and Android Chrome.

Request: prepare focused scanner smoke for the future implementation.

Acceptance criteria:

- On iPhone Safari and Android Chrome, open scanner from an active live report and return without losing report context.
- Verify camera capture and file picker sources.
- Verify auto frame, manual corner correction, perspective preview, cleanup, and original/cleaned/PDF preview switching.
- Verify `Переснять`, `Готово`, and `Прикрепить` stay reachable and do not overlap the document preview.
- Refresh during camera/file selection, corner edit, preview, and confirmed-but-not-attached states; scanner must restore safely.
- Attaching a scan must affect only the intended proof attachment and must not duplicate money rows or submit/finalize the live report.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

---

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 if the Notes-style Live Report editor enters the next deploy package
Status: ready for local/production browser check after Project Director package selection
Context: CEO reported that the open Live Report page is visually crooked and not light enough for field work.

Request: verify the updated open Live Report editor on mobile/tablet/desktop.

Acceptance criteria:

- On mobile `390 x 844`, the note input is the primary visible area and is not squeezed by summary/dashboard blocks.
- `Было / Приход / Расход / Стало` remain visible and readable.
- Quick cash/card/podotchet controls are reachable without overlapping the note text or proof buttons.
- Paperclip, scan, and camera actions trigger the intended file input modes.
- Camera action opens image capture/selection with `capture=environment` where the browser supports it.
- Scan action is treated as current document proof picker, not as OCR/edge-detection scanner.
- Existing autosave, refresh recovery, proof retry, cash/card separation, and submit flow do not regress.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

---

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 before declaring production MVP fixed
Status: ready after upload
Context: Production MVP hotfix pack addresses the six live defects reported by CEO.

Request: run focused browser smoke on production after the selected files are uploaded.

Acceptance criteria:

- Login/code screen shows current FinDesk copy after normal reload and hard reload; no stale old form from PWA cache.
- Submitted/locked card with `return_requested_at` is not stuck on a dead `Запрошено` button:
  - admin/moderator can return it to edit;
  - owner sees waiting/open state instead of a dead action.
- Live report card rows do not overlap title/preview/action buttons on mobile, tablet, or desktop.
- Invite/share generated action block can be closed without leaving the group page.
- Refresh preserves the active module and exact On-the-go screen: stream gate, cards list/archive, or editor.
- Group admin can remove a test group from the working list using the visible group delete action.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

## To QA Release Engineer

Date: 2026-05-27
Priority: P0 after production upload
Status: ready after Project Director package selection
Context: Business-MVP product evidence is passed, but production frontend smoke still needs to prove the uploaded package and browser cache behavior.

Request: run post-deploy frontend smoke on production using the checklist in `FINDINGS.md`.

Acceptance criteria:

- Cover phone `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`.
- Verify `/`, `/app.php`, `app.css`, `i18n.js`, `app.js`, `manifest.webmanifest`, `service-worker.js`, favicons, manifest icons, `brand-mark.png`, and `brand-og.png` return 200 and are not stale.
- Verify service worker update/activation and old `findesk-*` cache cleanup in a fresh browser and a returning browser.
- Smoke On the Go autosave/recovery/proof retry without duplicate rows or silent submit/include/finalize.
- Smoke FinDesk current period, closed report archive/package by `report_id`, proof access, print/PDF, and immutability after later current activity.
- Smoke group messages send/list/unread/mark-read and non-member denial.
- Smoke Business Desk/proforma create/list/open/print and confirm ledger/report totals do not mutate.
- Smoke Travel/Trip with Friends staging and Advanced reachability without ledger mutation.
- Record console/network errors, screenshots, production report/group ids, and final PASS/BLOCKED result in QA role docs.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`

## P1 / Post-MVP Follow-Ups

These are not production deploy blockers unless Project Director changes scope:

- Product Finance Architect + Backend/Data: decide whether package-wide downloadable archive export beyond browser print/PDF is required.
- Product Finance Architect + Backend/Data: define first-class report/capture/advance message links beyond current audit-derived refs and marked unlinked group discussion.
- Product Finance Architect: decide whether full Travel settlement or full Business Desk/invoicing integration is launch-critical.
- Frontend/UX after deploy: refine first-screen density, Advanced organization, and visual polish only after the production MVP smoke is stable.

---

## To QA Release Engineer

Date: 2026-05-27
Priority: P1 after selected package upload
Status: local smoke done; full smoke remains pending on deployed package
Context: Login/request-code/verify-code visible copy was cleaned up locally for current FinDesk branding.

Request: smoke the sign-in code flow in browser.

Acceptance criteria:

- Login screen shows FinDesk-branded title, concise email/code instructions, and no old development wording.
- Request-code path shows user-facing send/success/failure messages, not raw `Error:` strings.
- Verify-code path preserves email + 6-digit code behavior and opens FinDesk after success.
- Email body, if delivered during smoke, mentions FinDesk sign-in code and 10-minute expiry.
- No production data is modified beyond the normal test login/session path.

Evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
# Frontend/UX Tasks To Others - 2026-05-28 Live Report Records Admin Discovery Hotfix

## To QA Release Engineer

Task: recheck P0 records page after admin group-scope fix.

Required checks:

- mobile `390x844`, tablet `820x1180`, desktop `1440x900`;
- group admin sees employee Live Report card with proofs in the normal records page, without direct-open fallback;
- opening that card shows money rows and proof buttons;
- image and PDF proof buttons open the in-app viewer;
- close controls work;
- base employee still cannot see another user's group records;
- no critical overlap/clipping for long card title, proof labels, or action buttons.

Evidence:

- local smoke fixture: group `235`, admin tape `307`, employee tape `308`;
- local Playwright mobile fixture: group `244`, employee tape `332`, capture `217`, proof controls `2`;
- changed files: `public/app.php`, `public/assets/app.js`, `public/assets/app.css`.

Short report back to Project Director only: role, task, status, evidence pointer, blocker, next owner.

---
