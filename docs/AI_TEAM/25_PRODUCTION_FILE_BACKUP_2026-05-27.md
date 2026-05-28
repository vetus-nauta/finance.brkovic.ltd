# Production File / Storage Backup

Date: 2026-05-27

Owner: Project Director

Status: completed by read-only FTP download.

## Backup Record

Backup id:

- `prod-files-before-mvp-20260527T185902Z`

Source:

- production FTP web tree `/finance.brkovic.ltd`

Local directory:

- `backups/prod-files-before-mvp-20260527T185902Z/finance.brkovic.ltd`

Archive:

- `backups/prod-files-before-mvp-20260527T185902Z.tgz`

Checksum:

- `b095d4c6c8cf35ac0fbc76657fd7653d4757596b58944c81a13325122b1c8823`

Result:

- files downloaded: `110`;
- bytes downloaded before archive: `12913690`;
- download errors: `0`.

## Scope

This backup covers files and storage visible in the production FTP tree under `/finance.brkovic.ltd`.

This backup does not cover the production database.

No production upload, edit, delete, or schema operation was performed during this backup step.

Credentials are not stored in this document.

## Gate Effect

This closes the production file/storage backup blocker for the MVP deploy gate.

Production upload remains NO-GO until:

- production DB backup is complete;
- production DB engine/schema preflight is complete;
- `deploy/on_the_go_sessions_runtime.sql` is applied or proven compatible/not needed;
- rollback owner and smoke owner are confirmed;
- production smoke is executed after controlled upload.
