# Live Deploy 2026-05-20

Live target: `https://finance.brkovic.ltd`

Deployed commit: `f487593 Package branded app icons`

## Backups

- Live files backup: `backups/live-finance-20260520-151135/files`
- Live database backup: `backups/live-finance-20260520-151135/live-db.sql`
- Deploy package backup: `backups/finance-brkovic-deploy-f487593-20260520.zip`

These backup paths are local and ignored by Git.

## Database

Applied live migrations:

- `deploy/group_access_levels.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `deploy/advances_foundation.sql`

Post-migration checks confirmed:

- `cash_advances` table exists.
- `group_members.access_level` exists.
- `group_invites.invited_email` exists.
- `on_the_go_tapes.group_id` exists.
- `on_the_go_tapes.advance_id` exists.

Live DB engine reported: `11.4.10-MariaDB-cll-lve-log`.

## FTP Upload

- Uploaded 74 tracked project files.
- Preserved live `app/config.local.php`.
- Preserved live `storage/`.
- Removed 227 obsolete `.bak` files from the active live tree after backup.

## Smoke Checks

- `GET /app.php`: HTTP 200.
- `GET /manifest.webmanifest`: HTTP 200, branded PWA manifest present.
- `GET /assets/app.css`: HTTP 200.
- `GET /assets/app.js`: HTTP 200.
- `GET /assets/icon-192.png`: HTTP 200.
- `POST /api.php?action=current_user`: `{"ok":true,"user":null}`.
- `POST /api.php?action=advance_list`: `not_authenticated`, no fatal error.
- `POST /api.php?action=group_list`: `not_authenticated`, no fatal error.
- `POST /api.php?action=on_the_go_tape_list`: `not_authenticated`, no fatal error.

