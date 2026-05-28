# Proof Links Hotfix Production Report

Date: 2026-05-28
Owner: Project Director / Backend/Data / Frontend/UX
Status: deployed; production smoke passed

## Question Answered

Saved PDFs and scanned files are stored under:

- `storage/documents/on-the-go/<year>/...`

The database link is:

- table: `on_the_go_files`;
- record link: `on_the_go_files.capture_id -> on_the_go_captures.id`;
- public API link: `/api.php?action=on_the_go_file_download&id=<file_id>`.

For scanner artifacts:

- original image/file and cleaned PDF share one `proof_bundle_id`;
- cleaned PDF stores `source_file_id` pointing back to the original file.

## Problem

The backend already attached files to the money row, but the Live Report card UI showed only `вложений N` instead of visible file links.

For a user, this looked like the PDF disappeared.

## Patch

Backend:

- `on_the_go_file_list` now uses visible capture access, not owner-only access;
- `on_the_go_file_download` now allows a permitted group admin/manager to open employee proof files linked to visible report rows.

Frontend:

- each Live Report card row now loads its files and renders direct links under the row;
- links open `/api.php?action=on_the_go_file_download&id=<file_id>`;
- asset version bumped to `20260528-proof-links1`.

## Backup Evidence

Production files/storage backup before hotfix:

- backup id: `prod-files-before-proof-links-hotfix-20260528T153541Z`;
- archive: `backups/prod-files-before-proof-links-hotfix-20260528T153541Z.tgz`;
- checksum: `f5658008282bd3c80d0f5a587a0ecebafec94f6334ada9df6d2b3884aefd8971`;
- files downloaded: `145`;
- bytes downloaded before archive: `14819840`;
- errors: `0`.

No DB migration was required.

## Production Smoke

Run id:

- `prod-proof-links-20260528153719`

Fixture:

- admin user id: `66`;
- member user id: `67`;
- group id: `26`;
- tape id: `87`;
- capture id: `145`;
- file id: `9`;
- download URL: `/api.php?action=on_the_go_file_download&id=9`.

Checks passed:

- member uploaded a PDF proof linked to one money row;
- admin card detail returned `files_count=1`;
- admin `on_the_go_file_list` returned a `download_url`;
- `download_url` returned PDF bytes;
- production `app.php` serves asset version `20260528-proof-links1`;
- temporary DB-gate was removed and returned `404`.

## Control

Financial formulas were not changed.

This hotfix changes visibility/access to proof links for permitted viewers and improves UI display of already-linked proof files.
