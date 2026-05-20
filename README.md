# finance.brkovic.ltd

Finance web app / PWA for `finance.brkovic.ltd`.

Current product name in the live codebase: **Quick Ledger**.

## Current Scope

- Email-code authentication
- Personal ledger
- Group ledger
- Group invites and members
- Group messages
- Categories / sections
- Attachments for ledger entries
- Reports
- Business desk: company profile, clients, proformas
- PWA install flow for iOS / Android / desktop
- On the Go fast capture mode

## Live Paths

- Live app: `https://finance.brkovic.ltd/app.php`
- Server path from legacy handoff: `/home/brkovic/finance.brkovic.ltd`

## Local Config

Do not commit `app/config.local.php`.

Create it from:

```bash
cp app/config.local.example.php app/config.local.php
```

Then fill DB and SMTP credentials locally/on server.

For localhost, use mail mode `log` so the 6-digit auth code is written to:

```text
storage/logs/auth_codes.log
```

## Local Smoke Test

After creating a local database and running migrations, start the app:

```bash
php -S 127.0.0.1:18888 -t public
```

Then run:

```bash
php scripts/local-smoke.php http://127.0.0.1:18888
```

The smoke test checks:

- public `current_user`;
- login by 6-digit email code in local log mode;
- group creation;
- invite and member join;
- group members;
- group messages and unread state;
- group ledger write plus admin visibility;
- personal ledger update/delete;
- On the Go tape/capture/list.

## Deployment Notes

The repository intentionally does not include:

- `storage/`
- `app/config.local.php`
- logs
- FTP backup files
- zip archives

The current live FTP tree had many `.bak.*` files. This repository starts from the current active files only.

Before feature integration, dump and commit a sanitized database schema from the live database. The current `deploy/` SQL files are useful history, but `On the Go` runtime tables evolved beyond the original foundation file.
