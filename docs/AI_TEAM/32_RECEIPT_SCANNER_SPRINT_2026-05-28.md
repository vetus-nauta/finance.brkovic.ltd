# Receipt Scanner Sprint

Date: 2026-05-28
Owner: Project Director
Status: local browser/HTTP QA passed; Chief Auditor gate pending
Production: not deployed

## Sprint Goal

Make `Скан` in Live Report become a FinDesk-owned proof scanner, not a raw camera/file picker.

The sprint closes only when:

- original image/file is preserved;
- cleaned PDF is preserved;
- processing metadata is stored;
- original and PDF are linked to the same money row and final report package;
- retry does not duplicate money rows;
- Chief Auditor accepts the evidence chain.

## Slice 1 - Local Frontend Scanner

Status: implemented locally.

Files:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

Result:

- `Скан` opens `Скан чека в PDF`.
- User can capture/select an image.
- User can move four crop corners.
- Browser applies perspective correction and cleanup.
- Browser generates one-page PDF.
- Generated PDF enters the existing Live Report proof upload path.

## Slice 2 - Backend Evidence Chain

Status: implemented locally; authenticated API scenario passed; browser/device QA pending.

Files:

- `app/on_the_go.php`
- `public/api.php`
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `scripts/local-smoke.php`

Result:

- `on_the_go_files` supports `proof_role`, `proof_bundle_id`, `source_file_id`, `file_hash_sha256`, and `metadata_json`.
- `on_the_go_upload_states` supports `proof_role`, `proof_bundle_id`, `file_hash_sha256`, and `metadata_json`.
- Upload API accepts scanner metadata while staying backward-compatible with existing attachments.
- Upload retry with the same `client_upload_id` returns existing uploaded proof instead of duplicating the file.
- `scripts/local-smoke.php` now includes a reproducible scanner proof-chain check.

## Slice 3 - Frontend Original Plus PDF Upload

Status: implemented locally.

Files:

- `public/assets/app.js`

Result:

- Scanner keeps the selected original image in memory.
- On save, Live Report uploads original image as `scanner_original`.
- On save, Live Report uploads generated PDF as `scanner_cleaned_pdf`.
- Both artifacts are linked by `proof_bundle_id`.
- PDF is linked back to original through `source_file_id`.
- Existing normal attachments stay `attachment`.

## Slice 4 - Final Report Package Visibility

Status: implemented locally.

Files:

- `app/ledger.php`
- `public/assets/app.js`

Result:

- Closed group report package copies scanner proofs with role, bundle, hash, and metadata.
- UI labels proof roles as `Оригинал скана`, `Очищенный PDF`, or `Вложение`.

## Current Verification

Passed:

- `node --check public/assets/app.js`
- `node --check public/assets/i18n.js`
- `git diff --check` for touched runtime/docs files
- `curl http://127.0.0.1:18889/api.php?action=current_user`
- `curl -I http://127.0.0.1:18889/app.php`
- authenticated local API scanner storage smoke:
  - user id `536`;
  - tape id `294`;
  - capture id `201`;
  - original file id `16`, role `scanner_original`;
  - PDF file id `17`, role `scanner_cleaned_pdf`;
  - shared bundle `scanner-bundle-api-20260528`;
  - PDF `source_file_id=16`;
  - repeated PDF upload with same `client_upload_id` returned `idempotent=true`;
  - file list returned exactly two scanner artifacts for capture `201`.
- scanner original upload now uses a stable `client_upload_id` derived from `proof_bundle_id` and capture id; scripted smoke includes idempotent retry checks for both original and cleaned PDF.
- authenticated local API idempotency recheck after original-upload fix:
  - user id `541`;
  - tape id `302`;
  - capture id `205`;
  - bundle `scanner-api-bundle-20260528080559`;
  - original file id `24`, replay returned `idempotent=true`;
  - PDF file id `25`, replay returned `idempotent=true`;
  - file list returned exactly `scanner_original` and `scanner_cleaned_pdf`.
- QA Release local browser/HTTP file-input scanner path:
  - run id `20260528RSQA01`;
  - mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`;
  - one money row per viewport after retries;
  - two scanner artifacts per viewport: `scanner_original` and `scanner_cleaned_pdf`;
  - original replay, cleaned PDF replay, and signed sync replay returned idempotent results;
  - screenshots and result JSON are in `/tmp/findesk-receipt-scanner-20260528RSQA01/`.
- authenticated local API final-package scanner proof recheck:
  - user id `542`;
  - group id `226`;
  - tape id `303`;
  - capture id `206`;
  - report id `516`;
  - bundle `scanner-package-bundle-20260528080910`;
  - final report package contains `scanner_original` and `scanner_cleaned_pdf`;
  - cleaned PDF points to original through `derived_from_file_id`;
  - `scripts/local-smoke.php` now checks scanner proof roles and metadata inside the closed report package.
- scripted smoke coverage added to `scripts/local-smoke.php`; execution remains environment-blocked here because PHP CLI is unavailable.

Blocked / not run:

- PHP CLI lint is not available in this shell.
- `php scripts/local-smoke.php ...` cannot run in this shell because PHP CLI is unavailable.
- Physical camera and installed iPhone/Android PWA mode were not checked in headless Chromium.
- Chief Auditor scanner gate is pending.

## Remaining Sprint Gates

Backend/Data:

- Prove schema migration on a local/production-compatible DB.
- Local upload/file-list/final-package proof-chain passed through director HTTP/API rechecks.
- Production-compatible DB migration proof remains required before deploy.

Frontend/UX:

- Real-device check on iPhone Safari PWA and Android Chrome.
- Confirm touch corner handles are usable on a small screen.
- Confirm scanner modal does not trap the user.

QA Release:

- Local browser/HTTP file-input scanner path passed in run `20260528RSQA01`.
- Remaining device-level check: physical camera and installed iPhone/Android PWA mode.

Chief Auditor:

- Gate pending for local scanner evidence and explicit device limitation.

## Director Decision

This sprint is local implementation work only. Do not deploy until QA and Chief Auditor close the scanner gate.
