# Proof Viewer Hotfix Production Report

Date: 2026-05-28
Owner: Project Director / Frontend/UX / Backend/Data
Status: deployed; production smoke passed

## Problem

The record row had proof links after the previous hotfix, but mobile/PWA viewing could still be poor:

- links opened through a new browser/PWA context;
- PDF/photo viewing was not an in-app action;
- users could reasonably experience this as “photo/PDF cannot be viewed.”

## Patch

Frontend:

- added an in-app `proofViewerModal`;
- image proofs render inside the modal as an image;
- PDF proofs render inside the modal as an iframe/document frame;
- fallback keeps a direct `Открыть` link;
- row-level proof controls are now buttons that open the viewer without leaving the current card;
- asset version bumped to `20260528-proof-viewer1`.

Backend:

- retained the proof visibility/access change from `40_PROOF_LINKS_HOTFIX_PRODUCTION_2026-05-28.md`;
- no DB migration required.

## Backup Evidence

Production files/storage backup before hotfix:

- backup id: `prod-files-before-proof-viewer-hotfix-20260528T154611Z`;
- archive: `backups/prod-files-before-proof-viewer-hotfix-20260528T154611Z.tgz`;
- checksum: `6844ac266619212830e2d769b6b8db67b7db01efe3aeec909362151f49c533d2`;
- files downloaded: `149`;
- bytes downloaded before archive: `19233208`;
- errors: `0`.

No DB backup was required because no schema or data migration was performed.

## Production Smoke

Run id:

- `prod-proof-viewer-20260528154804`

Fixture:

- admin user id: `70`;
- member user id: `71`;
- group id: `28`;
- tape id: `91`;
- capture id: `148`;
- image file id: `14`;
- PDF file id: `15`.

Checks passed:

- production `app.php` serves asset version `20260528-proof-viewer1`;
- production JS contains proof viewer markers;
- production CSS contains proof viewer markers;
- one capture row has both image original and cleaned PDF;
- admin sees both proof links for the employee row;
- both `download_url` values return file bytes.

Temporary DB-gate:

- uploaded only to read smoke login codes from the protected auth log;
- removed after smoke;
- post-removal HTTP check returned `404`.

## Remaining QA

QA Release Engineer has a separate P0 task for full records-page QA across mobile/tablet/desktop and small UI defects.

This hotfix closes the technical proof-viewing path, but does not replace the broader visual QA pass.
