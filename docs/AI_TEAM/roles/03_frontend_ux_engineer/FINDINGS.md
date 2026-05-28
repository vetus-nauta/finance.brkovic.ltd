# Frontend UX Engineer Findings

## Frontend Residual Cleanup Before Deploy Package

Date: 2026-05-28
Role: Frontend/UX Engineer FinDesk
Status: IMPLEMENTED locally; no backend/API/financial formulas changed.

## Scope

- Checked the CEO-reported frontend residuals against current local code.
- Kept changes inside the approved frontend scope:
  - `public/app.php`
  - `public/assets/app.js`
  - `public/assets/app.css`
  - Frontend/UX role docs.
- Did not change backend modules, API routes, deploy SQL, or formulas.

## Findings

- Login/code-request visible copy was already mostly updated locally, but the production stale-copy risk remains if browser/PWA cache keeps old `app.js`, `i18n.js`, or `app.css` query strings.
- On-the-go state restore existed, but blank repeated `qlSetModule('ontherun')` calls could still overwrite a more precise visible state with a generic on-the-go state.
- Mobile report-card rows still had a narrow combined text/actions row on very small screens, which could recreate the title/button overlap reported by CEO.
- Receipt scanner modal had the corner close button, but not an obvious bottom `Закрыть` action near `Переснять / Прикрепить PDF`.
- Mobile Notes-style editor was already implemented; this pass preserves the large note surface and makes the editor container scroll safely on small screens.

## Changes Made

- `public/app.php`
  - Bumped `app.css`, `i18n.js`, and `app.js` query strings to `20260528-frontend-residual1`.
  - Updated login fallback copy to current FinDesk access-code wording.
  - Added explicit scanner modal `Закрыть` button.

- `public/assets/app.js`
  - Improved `qlSaveModuleState('ontherun')` so it infers the visible safe workspace: stream gate, card list, archive card list, or editor.
  - Preserves current stream and open card id when module state is saved without explicit on-the-go options.
  - Extended Escape handling to close the top visible modal/panel through existing close buttons where possible.

- `public/assets/app.css`
  - Added narrow-phone card-list layout where text and action buttons stack instead of competing in the same row.
  - Compacted the mobile live-report card header to avoid heading/action overlap.
  - Kept the Notes-like editor large while allowing safe vertical scrolling on small screens.
  - Adjusted scanner action grid for the new `Закрыть` action.

## Verification

- `node --check public/assets/app.js`: PASS.
- `git diff --check -- public/app.php public/assets/app.js public/assets/app.css docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`: PASS.

## Blocker

No local syntax blocker. QA must still verify real browser behavior after package selection, especially Safari/PWA cache, On-the-go refresh recovery, card row overlap, scanner modal exit, and Notes-style mobile editor.

## Next Owner

QA Release Engineer.

---

## Technical SEO / PWA Primary Layer

Date: 2026-05-27
Role: Frontend / PWA SEO Engineer
Status: DONE with one environment blocker: PHP CLI is not installed in this shell.
Scope: public landing and PWA metadata only. No backend/API/financial formulas changed. `public/app.php` was analyzed only.

## Inputs Read

- `public/index.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/app.php` only to confirm private app indexing boundary
- `public/assets/app.js` only for required sanity check
- `public/assets/app.css` only to reuse existing landing classes without CSS edits
- existing Frontend UX role docs
- `git status --short`

## Findings

- Public landing `/` is the only page promoted for indexing.
- Private app boundary is correct: `public/app.php` contains `<meta name="robots" content="noindex,nofollow">`.
- Existing landing had canonical and OG basics, but no JSON-LD and still had a `meta keywords` block that looked like outdated keyword stuffing.
- Existing `robots.txt` blocked `/app.php`, `/api.php`, and `/storage/`; it did not explicitly block `/app/` or `/api/`.
- Existing `sitemap.xml` listed only `/`, but without `lastmod`, `changefreq`, or `priority`.
- Existing manifest already had install-critical fields and categories. It was safe to add language/dir and app shortcuts without changing `id`, `start_url`, `scope`, `display`, or icon paths.
- No real product screenshot asset was identified for manifest `screenshots`. I did not reuse `brand-og.png` as a fake screenshot.

## Changes Made

- `public/index.php`
  - Reworked title and description around the real public/private product boundary.
  - Removed `meta keywords`.
  - Added explicit public `robots` meta for the landing.
  - Kept canonical as `https://finance.brkovic.ltd/`.
  - Expanded OG/Twitter metadata, including image dimensions/type and Twitter title/description.
  - Added JSON-LD `@graph` for `Organization`, `WebSite`, and `SoftwareApplication`/`WebApplication`.
  - Reworked visible copy into concise product sections: On the Go, FinDesk, Advanced, Installable PWA.
  - Added explicit privacy/noindex boundary copy for private app/API/storage.
  - Kept install CTA and platform install buttons.

- `public/robots.txt`
  - Kept sitemap open.
  - Kept `/app.php`, `/api.php`, and `/storage/` blocked.
  - Added `/app/` and `/api/` blocks for hygiene if those paths are ever web-exposed.

- `public/sitemap.xml`
  - Kept only the public root URL.
  - Added `lastmod` = `2026-05-27`, `changefreq` = `weekly`, `priority` = `1.0`.

- `public/manifest.webmanifest`
  - Preserved install identity and launch behavior: `id`, `start_url`, `scope`, `display`, and icons unchanged.
  - Improved `description`.
  - Added `lang` and `dir`.
  - Kept existing categories.
  - Added safe shortcuts for opening the private app and public install help.

## Verification

- `node --check public/assets/app.js`: PASS.
- Manifest JSON parse via Node: PASS.
- JSON-LD parse from `public/index.php` via Node: PASS.
- `php -l public/index.php`: BLOCKED because `php` command is not installed in this shell.
- `git diff --check`: PASS.

## Residual Risk

- Live production HTTP headers and crawler behavior were not tested from the network.
- PWA install behavior still needs browser smoke on real production origin.
- Manifest screenshots remain absent until a real app screenshot asset is provided.
- Multilingual public landing remains P1: no fake localized pages were created.

## Next Owner

QA Release Engineer for production SEO/PWA smoke. Project Director / Content Owner for real localized landing content and real manifest screenshots if those become required.

---

# Frontend UX Engineer Findings

## Frontend Production Package Sanity Checklist

Date: 2026-05-27
Role: Frontend UX Engineer
Status: PASS for checklist readiness; not a production deploy approval.
Scope: docs-only frontend package and smoke checklist. No application code changed.

## Inputs Read

- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `git status --short`
- frontend package surface: `public/app.php`, `public/index.php`, `public/assets/app.js`, `public/assets/app.css`, `public/assets/i18n.js`, `public/service-worker.js`, `public/manifest.webmanifest`, frontend asset files, and API route references.

## Decision

Frontend/UX does not see a new frontend P0 blocker in the package surface. The blocker remains procedural: production must not upload the dirty working tree blindly. Project Director must choose the deploy bundle, and QA Release Engineer must run production smoke after upload.

The frontend production package should be treated as a selected bundle, not as `git status` wholesale. Current dirty tree includes broad modified application files, modified favicon/PWA/brand assets, untracked docs, untracked `app/ai.php`, untracked `public/reset-local.php`, untracked `scripts/start-local.sh`, and `test-results/`. Local reset/test/support files must stay out of production unless Project Director explicitly approves them.

## Frontend File Candidates That Appear MVP-Relevant

### Primary frontend shell and assets

- `public/app.php` - authenticated app shell, module menu, On the Go, FinDesk report/archive, group messages, Business Desk/proforma, Premium/Travel marker, Advanced entry, manifest/favicon links, and versioned `app.css`, `i18n.js`, `app.js` tags.
- `public/index.php` - public landing/open-app/install shell, PWA/brand/favicons, and versioned frontend asset tags.
- `public/assets/app.js` - main frontend behavior for service worker registration, auth, module routing, On the Go field capture/recovery/proof UI, final report/package UI, group messages, Business Desk/proforma UI, Travel staging marker, Advanced routing, export/print actions.
- `public/assets/app.css` - responsive layout, mobile controls, report package styling, message/proforma/Advanced surfaces, and print/PDF styles.
- `public/assets/i18n.js` - language labels for app shell, On the Go/FinDesk/Advanced/Premium/Travel staging copy, settings/install copy.

### PWA, cache, favicon, and brand assets

- `public/service-worker.js` - production-only service worker registration target; current cache name is `findesk-20260522-v134`.
- `public/manifest.webmanifest` - app id/start URL/scope/icons.
- `public/favicon.ico` and `public/assets/favicon.ico`.
- `public/assets/favicon-16x16.png`, `favicon-32x32.png`, `favicon-48x48.png`, `favicon-64x64.png`.
- `public/assets/apple-touch-icon.png`, `icon-180.png`, `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`.
- `public/assets/brand-mark.png` and `public/assets/brand-og.png` are referenced by app/index markup.
- `public/assets/brand-logo.png` exists untracked and was not found in current app/index/CSS references; include only if Project Director confirms an external use.

### Frontend-facing route/API dependencies

These are not frontend-owned files, but the selected package must stay consistent with the deployed frontend:

- `public/api.php` - routes UI actions for On the Go, final report packages, messages, and proformas.
- `app/auth.php` - session/login checks.
- `app/groups.php` - group selection/access used by money, messages, and reports.
- `app/on_the_go.php` - On the Go capture, autosave/recovery, proof retry, and card/report state.
- `app/ledger.php` - current period, final report package, archive/proof/export behavior.
- `app/messages.php` - group message send/list/unread/mark-read.
- `app/business.php` - Business Desk/proforma create/list/open data.
- `app/advances.php` - accountable money and Advanced money surfaces used by the MVP route.
- `deploy/on_the_go_sessions_runtime.sql` - production DB migration candidate only if production does not already have the required On the Go session/proof-state runtime schema.

### Explicit production exclusions unless separately approved

- `public/reset-local.php`
- `scripts/start-local.sh`
- `test-results/`
- local config/secrets such as `app/config.local.php`
- broad untracked/generated docs outside the release docs package
- `app/ai.php` unless Advanced AI behavior is explicitly selected for production

## Cache, Service Worker, Favicon, And Asset Risks

- `public/app.php` and `public/index.php` send no-store headers, but linked frontend files still use `?v=20260522-106`. If `app.js`, `app.css`, `i18n.js`, or brand assets changed without a version bump, production browser/CDN caches can serve stale frontend code or stale images.
- `public/service-worker.js` registers only off localhost. It has install/activate cleanup but no fetch handler in the inspected file. Production smoke must still verify the active service worker script is current, old `findesk-*` caches are removed, and a hard reload/private window sees the uploaded assets.
- Service worker update is not tied to the query string on `app.js`; an old active worker/client can survive a deploy until update/reload. QA should test first load, hard reload, and a returning installed/PWA session.
- Favicon/icon files are modified in the dirty tree. Local file inspection shows expected dimensions: 16, 32, 48, 64, 180, 192, and 512 PNG variants plus ICO files. Production smoke must verify all referenced icon URLs return 200 and render with expected content type.
- `brand-mark.png` and `brand-og.png` are referenced with `?v=20260522-106`. If new brand images are deployed, cache invalidation/versioning must be checked.
- `manifest.webmanifest` references `/assets/icon-192.png`, `/assets/icon-512.png`, and `/assets/icon-maskable-512.png`; installed app smoke must verify manifest loads and icon fetches work from production.
- Root `/favicon.ico` and `/assets/favicon.ico` both exist and are modified. Production smoke should verify browser tab favicon and direct URL fetches for both.

## Post-Deploy Frontend Smoke Checklist

Run smoke on production after the selected package is uploaded. Minimum viewport coverage: phone `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`. Use a fresh/private browser first, then a returning browser with existing service worker/cache. For mobile paths, prefer real mobile Safari/Chrome or device emulation plus one actual phone if available.

### Global load and cache

- Open `/` and `/app.php`; both load without blank screen or console errors that block initialization.
- Confirm `app.css`, `i18n.js`, `app.js`, `manifest.webmanifest`, `service-worker.js`, `brand-mark.png`, `brand-og.png`, favicon files, and manifest icons return HTTP 200.
- In DevTools Application panel, confirm the active service worker is current and old `findesk-*` caches are not serving stale JS/CSS.
- Confirm browser tab favicon, app icon/manifest preview, and public OG image URL resolve to the deployed assets.
- Confirm login/session check reaches the authenticated app and module menu on phone.

### On the Go

- On phone, open `Живые отчеты` / On the Go.
- Verify cash stream quick controls are visible and tappable: `+ Получили`, `- Наличные`, `Подотчет`, `Фото`.
- Verify card stream hides cash-only money actions and keeps card spending visually separate.
- Type a draft cash row, wait for visible saved state, refresh, and return to the same stream; the row must survive and not submit/include/finalize itself.
- Attach or retry a proof/photo after a visible failure/retry state; it must attach to the original row and not create duplicate money rows.
- Submit only through the visible FinDesk review boundary; no draft should enter final report before review/acceptance.

### FinDesk reports and archive

- Navigate from mobile menu to report summary / `Сводка отчета`.
- Verify `Текущий период` is labeled separately from `Закрытые финальные отчеты`.
- Verify current period export preview/action is reachable and represents open-period truth, including carryover plus new current entries only.
- Open a newly finalized report from the closed report list by `report_id`.
- Verify `Закрытый групповой отчет #...` opens with summary, participant reports, money rows, accountable state, message/audit references, proof links, and print/PDF action.
- Verify proof links open/download for authorized user and remain denied for unauthorized/non-member user.
- Add later current-period activity and re-open the closed report; closed totals/package must not mutate.

### Group messages

- In the group workspace on phone, send a group message.
- Verify sender sees it in the list and another group member sees unread state.
- Mark the message read and verify unread clears.
- Verify a non-member cannot list the group messages and receives a permission error.
- In the closed report package, verify report-context messages/audit references remain understandable and unlinked group discussion is not presented as immutable report-linked evidence.

### Business Desk / proforma

- Open `Бизнес` / Business Desk from the mobile menu.
- Switch to `Proformas`.
- Create a simple proforma with item, price, VAT/discount fields as needed.
- Confirm the proforma appears in list, opens in preview, and `Print / Save PDF` opens the print path.
- Recheck group ledger/report totals after proforma operations; Business Desk documents must not mutate operational money reports unless explicitly connected later.

### Travel / Advanced staging

- Open `Premium`.
- Verify `Поездка с друзьями` / Trip with Friends remains visible as staged/prepared product memory, not mixed into ordinary business cash reports.
- Verify `Advanced Mode` and `Открыть Advanced` are reachable.
- Open Advanced and verify `Деньги`, `Подотчеты`, `AI-анализ`, and `AI-аудит` staging/navigation remain visible without blocking the proven MVP money path.
- Confirm Travel/Advanced staging actions do not mutate ledger totals during smoke.

## Browser Verification After Deploy

QA should explicitly verify in the browser:

- responsive layout has no blocking overlap for the mobile menu, quick capture strip, proof retry state, report package actions, messages, proforma print controls, and Advanced tabs;
- primary buttons are reachable above mobile browser chrome and inside modals;
- text does not overflow buttons/cards in phone/tablet/desktop paths;
- print/PDF dialogs open for closed report package and proforma preview;
- downloads/exports use the selected current or final report context, especially `report_id` for final report exports;
- no stale UI copy from an older bundle remains after hard reload and service worker update;
- console has no initialization errors during login, module switching, package open, message send, proforma create/open/print, and Advanced open;
- network tab shows no 404/500 for frontend assets, manifest icons, service worker, package/proof endpoints, message endpoints, or proforma endpoints.

## Verification Done By This Role

- `node --check public/assets/app.js` passed on 2026-05-27.
- `file` inspection confirmed expected local dimensions for favicon/app icon/brand image files.
- Docs-only update; no application code or assets were changed by this role.
- CLI PHP smoke was not run here; previous QA notes say CLI `php` is unavailable in this shell while local HTTP server has been reachable.

## Blocker

None for the Frontend/UX checklist itself.

Production deploy is still blocked until Project Director selects the package, deployment owner backs up files/database and uploads only the selected bundle, and QA Release Engineer completes production smoke.

## Next Owner

Project Director for deploy selection and production go/no-go. QA Release Engineer for post-deploy frontend smoke.

---

# Frontend UX Findings - 2026-05-27 Login Code Copy

Role: Frontend/UX Engineer FinDesk
Task: Update stale login/code-request visible copy to current FinDesk / brkovic.ltd wording.

## Scope

- Reviewed visible login/request-code/verify-code strings in `public/app.php`, `public/assets/app.js`, `public/assets/i18n.js`, and `app/auth.php`.
- Replaced generic or development-style auth UI messages with concise FinDesk sign-in copy.
- Kept the login-code meaning intact: email check, 6-digit code, expiry in email, verify/open FinDesk, resend by requesting a new code.
- No backend/API flow, verification logic, financial formulas, deploy files, or production data changed.

## Files Changed

- `public/app.php`
- `public/assets/app.js`
- `public/assets/i18n.js`
- `app/auth.php`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

## Verification

- `node --check public/assets/app.js`: PASS.
- `node --check public/assets/i18n.js`: PASS.
- `git diff --check -- public/app.php public/assets/app.js public/assets/i18n.js app/auth.php docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`: PASS.
- Follow-up grep found remaining raw `Error:` strings outside the login/request-code/verify-code scope; they were left unchanged.

### 2026-05-27 Recheck — Sign-In Copy Refinement

- Updated fallback and i18n auth labels:
  - `Sign in to FinDesk`, `send sign-in code`, Russian wording for explicit login-code flow.
  - `auth.signInLead` and `auth.message.codeSent/localCode` wording cleaned in EN/RU.
- Verified no syntax regressions in `public/assets/app.js` and `public/assets/i18n.js`.
- Task remains non-breaking and frontend-only; auth behavior and backend formula logic unchanged.
- Browser recheck result: PASS (local, 390x844).
  Artifacts: `/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/03_frontend_ux_engineer/artifacts/login-signin-20260527-390x844.png`.

---

# Frontend UX Findings - 2026-05-28 Production MVP Hotfix Pack

Role: Frontend/UX Engineer FinDesk
Task: Fix reported production MVP UI defects before the next deploy.

## Scope

- Removed stale login-code fallback copy and bumped asset query strings/cache version so returning PWA browsers load the new auth UI.
- Added an explicit `Закрыть` action to the generated invite/share block.
- Added a visible active-group `Удалить группу` action for group admins; API behavior remains archive-from-working-list.
- Preserved exact On-the-go screen state across refresh for stream gate, card list/archive, and editor.
- Replaced dead `Запрошено` behavior:
  - admins/moderators now get a direct `Вернуть` action for locked cards;
  - owners see `Ожидает` and can open the card instead of pressing a disabled button.
- Tightened live report card typography/layout so title/preview text does not sit on action buttons on small screens.

## Files Changed

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`
- `public/service-worker.js`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

## Verification

- `node --check public/assets/app.js`: PASS.
- `git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/assets/i18n.js public/service-worker.js`: PASS.
- `curl -I http://127.0.0.1:18889/app.php`: HTTP 200, no-store headers present.
- PHP CLI lint not run: `php` command is not installed in this environment.

## Blocker

No frontend syntax blocker found locally. Production browser smoke is required because reported defects were observed on the live PWA.

---

# Frontend UX Findings - 2026-05-28 Live Report Notes-Style Editor Cleanup

Role: Frontend/UX Engineer FinDesk
Task: Make the open Live Report editor lighter, more readable, and closer to the iPhone Notes working model while preserving FinDesk money context.

## Scope

- Reworked the open `Живой отчет` editor locally only; no production upload was made for this task.
- Moved the key money context into the note surface as compact metrics: `Было`, `Приход`, `Расход`, `Стало`.
- Increased the primary textarea height and made it the dominant working area on mobile/tablet/desktop.
- Reduced side-panel weight in editor mode so the screen behaves more like a field note than a dashboard.
- Added floating proof actions inside the note surface:
  - paperclip for file/media attachment;
  - scan action for image/PDF document picker;
  - blue camera action for fast receipt photo capture.
- Kept existing proof upload plumbing and autosave/proof-state behavior unchanged.

## Files Changed

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

## Verification

- `curl -I http://127.0.0.1:18889/app.php`: HTTP 200, no-store headers present.
- `node --check public/assets/app.js`: PASS.
- `node --check public/assets/i18n.js`: PASS.
- `git diff --check -- public/app.php public/assets/app.js public/assets/app.css`: PASS.
- Markup grep confirmed one `id="otrSimpleResult"` and the new `media`, `scan`, `camera` note actions.

## Known Limit

The current `scan` action is not true automatic document scanning/OCR. It opens the existing image/PDF proof path with camera capture enabled. Apple Notes-style edge detection, PDF cleanup, and OCR remain a separate Product/Backend/Frontend decision.

---

# Frontend UX Findings - 2026-05-28 Receipt Scanner Local Prototype

Role: Frontend/UX Engineer FinDesk
Task: Implement first local frontend-only Receipt Scanner slice.

## Scope Implemented

- Added a dedicated `Скан чека в PDF` modal launched from the Live Report `Скан` proof action.
- Added image capture/selection input for scanner source.
- Added canvas preview with draggable corner handles.
- Added perspective correction from the selected quadrilateral into a straightened receipt canvas.
- Added cleanup controls:
  - cleanup strength slider;
  - black-and-white receipt toggle.
- Added browser-side one-page PDF generation with the cleaned receipt image embedded.
- Wired the generated PDF into the existing Live Report proof upload path as `PDF-скан`.

## Files Changed

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `docs/AI_TEAM/31_RECEIPT_SCANNER_TASK_CARD_2026-05-28.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

## Verification

- `curl -I http://127.0.0.1:18889/app.php`: HTTP 200, no-store headers present.
- `curl http://127.0.0.1:18889/app.php`: scanner modal markup present.
- `node --check public/assets/app.js`: PASS.
- `node --check public/assets/i18n.js`: PASS.
- `git diff --check -- public/app.php public/assets/app.js public/assets/app.css`: PASS.

## Known Limits

- Backend schema/storage change now exists locally after the first prototype note above.
- Original photo and processing metadata are now wired for upload locally, but still need authenticated QA evidence.
- Automatic edge detection is not implemented; first slice uses manual draggable corners.
- Browser/device QA is required on real iPhone Safari PWA and Android Chrome before deploy.

## 2026-05-28 Sprint Update

- Scanner save path now uploads the original image as `scanner_original`.
- Scanner save path uploads the generated PDF as `scanner_cleaned_pdf`.
- Both files use the same `proof_bundle_id`.
- Original upload uses a stable `client_upload_id` derived from `proof_bundle_id` and capture id to avoid duplicate originals on retry.
- PDF upload sends `source_file_id` pointing to the uploaded original.
- Existing non-scanner attachments remain `attachment`.
- API recheck evidence after stable original upload id: user id `541`, tape id `302`, capture id `205`, original replay idempotent, PDF replay idempotent, file list has exactly the two scanner artifacts.

---

# Frontend UX Findings - 2026-05-28 Records Scroll Hotfix

Role: Frontend/UX Engineer FinDesk
Task: fix impossible scrolling in `Живые отчеты` records window.
Status: fixed and deployed to production.

## Finding

When the records column was long, the list did not become scrollable. The grid layout compressed card rows inside the visible panel height instead of increasing the list `scrollHeight`.

## Fix

- `public/assets/app.css`: records panel now uses `grid-template-rows: auto minmax(0, 1fr)`.
- `public/assets/app.css`: records list changed to a block internal scroll container.
- `public/assets/app.css`: records list has `overflow-y: auto`, `overscroll-behavior: contain`, and `-webkit-overflow-scrolling: touch`.
- `public/assets/app.css`: mobile card rows keep a usable minimum height.
- `public/app.php`: asset version bumped to `20260528-records-scroll1`.

## Verification

- `node --check public/assets/app.js`: PASS.
- `git diff --check`: PASS.
- Local Playwright run `local-scroll-20260528164027`:
  - mobile `390x844`: list `clientHeight=621`, `scrollHeight=3827`, `scrollTop=3206`;
  - desktop `1440x900`: list `clientHeight=635`, `scrollHeight=3815`, `scrollTop=3180`.
- Production smoke `prod-records-scroll-20260528164351`:
  - mobile `390x844`: list `clientHeight=621`, `scrollHeight=3183`, `scrollTop=2562`;
  - desktop `1440x900`: list `clientHeight=635`, `scrollHeight=3815`, `scrollTop=3180`.
- Production report: `docs/AI_TEAM/44_RECORDS_SCROLL_HOTFIX_PRODUCTION_2026-05-28.md`.

## Blocker

None for records-window scrolling.

## Next Owner

Project Director for the next CEO-reported UI issue.

---

# Frontend UX Findings - 2026-05-28 Scanner Fit Hotfix

Role: Frontend/UX Engineer FinDesk
Task: fix mobile scanner modal overflow after notes-style editor audit.
Status: fixed and deployed to production.

## Finding

Local Playwright audit of the notes-style `Живой отчет` editor found that the receipt scanner modal overflowed the `390x844` phone viewport. The modal card was rendered from `x=18` with `width=390`, right edge `408`, and the bottom attach button extended below the viewport.

## Fix

- `public/assets/app.css`: added `box-sizing: border-box` to `.receipt-scanner-modal-card`.
- `public/assets/app.css`: mobile `#receiptScannerModal` now uses zero outer padding and stretched placement.
- `public/assets/app.css`: mobile scanner modal uses `width: 100%`, `max-width: 100%`, and `overflow-y: auto`.
- `public/assets/app.css`: mobile scanner stage uses `clamp(260px, 42dvh, 420px)`.
- `public/assets/app.css`: mobile scanner actions use two columns with the primary attach button spanning both columns.
- `public/app.php`: asset version bumped to `20260528-scanner-fit1`.

## Verification

- `node --check public/assets/app.js`: PASS.
- `git diff --check`: PASS.
- Local Playwright run `local-notes-ui-20260528162458`:
  - mobile `390x844`: notes field `578px`, scanner modal `390x844`, scanner stage `368x415.64`;
  - tablet `820x1180`: notes field `990px`, scanner modal `720x900`;
  - desktop `1440x900`: notes field `710px`, scanner modal `720x813.98`.
- Production smoke `prod-scanner-fit-20260528162815`: mobile `390x844`, notes field `578px`, scanner modal `390x844`, scanner controls reachable.
- Production report: `docs/AI_TEAM/43_SCANNER_FIT_HOTFIX_PRODUCTION_2026-05-28.md`.

## Blocker

None for browser/modal geometry. Real-device iPhone Safari/PWA camera behavior remains a separate QA gate.

## Next Owner

QA Release Engineer for physical device scanner gate when device evidence is available.

---

# Frontend UX Findings - 2026-05-28 Receipt Scanner UI Task Card

Role: Frontend/UX Engineer FinDesk
Task: Define a dedicated receipt scanner screen launched from the live report.
Status: TASK CARD RECORDED; no runtime code changed in this pass.

## Product Intent

The current `scan` entry point must become a separate receipt/document scanner screen instead of a basic file picker inside the open live report editor. The scanner should support field use on mobile, keep the live report context intact, and return a clean attachment back to the current report row/card.

## UX Scope

- Entry point: from the open `Живой отчет` proof controls, open a dedicated scanner screen/sheet rather than replacing the report page silently.
- Capture sources:
  - camera capture for receipt/document photos;
  - file picker for existing images and PDFs.
- Scanner workspace:
  - auto-detected document frame/edges when possible;
  - visible manual corner handles for correction;
  - perspective correction preview after corners are adjusted;
  - cleanup/enhancement step for receipt readability;
  - preview modes for `Original`, `Cleaned`, and final `PDF`.
- Primary actions:
  - `Переснять` resets the current capture and opens camera/file selection again;
  - `Готово` confirms the cleaned/PDF result inside the scanner flow;
  - `Прикрепить` attaches the confirmed result back to the active live report item.
- State behavior:
  - active live report id, stream/card context, scanner step, selected source file, corner points, preview mode, and confirmed output must survive refresh/back-forward where browser storage allows it;
  - refresh must not submit a report, duplicate a money row, or lose the pending attachment state.

## Mobile-First Requirements

- Primary target: iPhone Safari and Android Chrome.
- The first useful action must be reachable without horizontal scroll on a phone viewport around `390 x 844`.
- Camera/file controls must respect mobile browser limitations: iOS Safari may not expose true live camera APIs consistently, so fallback to `<input type="file" accept="image/*,application/pdf" capture="environment">` remains required.
- Corner handles must be large enough for thumb input and must not sit under browser chrome or the app bottom actions.
- Preview/action footer must keep `Переснять`, `Готово`, and `Прикрепить` visible without overlapping the scanned document.

## Engineering Notes For Later Implementation

- Do not block the live report editor on advanced OCR. OCR can be a later enhancement; the first deliverable is reliable capture, correction, cleanup, preview, and attachment.
- Prefer progressive enhancement: use browser-native file/camera input as the baseline, then add edge detection/perspective cleanup where supported.
- Persist scanner draft state separately from submitted report state so recovery cannot mutate final reports or operational ledger data.
- Keep proof upload integration compatible with existing attachment/proof retry behavior.

## Acceptance Criteria

- Opening scanner from a live report keeps the user in that report context after cancel, refresh, or attach.
- User can choose camera or file as source on iPhone Safari and Android Chrome.
- Scanner shows an auto frame when available and allows manual corner adjustment.
- Perspective-corrected and cleaned preview is available before attachment.
- User can switch between original, cleaned, and PDF previews.
- `Переснять`, `Готово`, and `Прикрепить` are visually stable and reachable on phone.
- Refresh during scanner flow restores the same scanner state or a safe resumable state.
- Attaching creates/updates only the intended proof attachment; it must not create duplicate money rows or finalize/submit the live report.

## Files Changed

- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

## Blocker

Runtime implementation is intentionally not started in this pass. Product/Engineering must approve scanner scope, storage format, and image/PDF processing approach before code work.

## Next Owner

Project Director for scope approval, then Backend/Data Architect for attachment/storage contract, then Frontend/UX Engineer for implementation.

---

# Frontend UX Findings - 2026-05-28 Live Report Records Admin Discovery Hotfix

Role: Frontend/UX Engineer FinDesk
Task: fix P0 records page discoverability for employee Live Report cards with proofs.
Status: fixed, QA-passed, deployed to production.

## Problem

QA proved that direct card detail and proof API access worked, but the normal `Живые отчеты` records page did not expose an employee card to the group admin. The frontend loaded `on_the_go_card_list` without `group_id`, so the list stayed in personal scope.

## Fix

- `public/assets/app.js`: added group-scope resolution for the records page.
- `public/assets/app.js`: `loadCards()` now sends `group_id` when the active/selected group grants group report moderation/view access.
- `public/assets/app.js`: records header shows the group name with current stream/archive label.
- `public/assets/app.js`: proof viewer `Открыть` no longer sets a forced `download` attribute.
- `public/assets/app.js`: opening the records page now closes the intermediate stream gate so the gate cannot intercept clicks over the records list.
- `public/assets/app.css`: added overflow protection for long card titles, proof labels, and modal action buttons.
- `public/app.php`: bumped asset version to `20260528-records-admin1`.

## Verification

- `node --check public/assets/app.js`: PASS.
- `git diff --check`: PASS.
- Local HTTP API smoke: admin sees both admin and employee cards with `group_id`; base employee sees only own card in the same group scope.
- Smoke fixture: group `235`, admin tape `307`, employee tape `308`.
- Local Playwright mobile smoke `390x844`: PASS.
- Browser smoke fixture: group `244`, employee tape `332`, capture `217`, proof controls `2`; records page visible, card opens, proof viewer opens/closes, stream gate not left open.
- QA Release Engineer recheck: PASS, run `20260528RECORDSRECHECK04`.
- Production smoke: PASS, run `prod-records-hotfix-20260528161828`, group `36`, employee tape `112`, capture `157`, proof controls `2`.
- Production report: `docs/AI_TEAM/42_RECORDS_ADMIN_DISCOVERY_HOTFIX_PRODUCTION_2026-05-28.md`.

## Blocker

None for this records-page hotfix scope.

## Next Owner

Project Director for broader remaining MVP/UI backlog.
