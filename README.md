# finance.brkovic.ltd

Finance web app / PWA for `finance.brkovic.ltd`.

Current product name in the live codebase: **Quick Ledger**.

## Current Scope

- Email-code authentication
- Personal ledger
- Group ledger
- Group invites and members
- Group-scoped access levels: Base / Manager / Advanced
- Group messages
- Categories / sections
- Attachments for ledger entries
- Reports
- Business desk: company profile, clients, proformas
- PWA install flow for iOS / Android / desktop
- On the Go fast capture mode
- Accountable money / advances moderation bridge
- Money UI for issue / submit / accept / return

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
- email-bound invite and member join;
- group access levels;
- group members;
- group messages and unread state;
- Base member denial for direct group ledger;
- Manager group ledger write plus admin visibility;
- accountable money issue -> On the Go submit -> admin accept/return -> group ledger conversion;
- personal ledger update/delete;
- On the Go tape/capture/list.
- Step 4 Money UI assets served by `app.php` and `app.js`.

## Group Access Model

Access is scoped to a group membership, not to the global user account.

```text
base      = On the Go / limited work group mode
manager   = middle layer / Captain Fin and moderation later
advanced  = organizer/admin mode
```

A user can have full personal finance tools and still be `base` inside a specific work group.

## Accountable Money Flow

Step 3 adds the bridge between the fast employee mode and the group report:

```text
advanced/admin issues money
base employee records expenses in On the Go
base employee submits actual remaining cash
manager/admin accepts
expenses enter group ledger under the employee's name
```

Issuing money is stored as `cash_advances`; it is not written as a group expense until moderation accepts the submitted report.

## Deployment Notes

The repository intentionally does not include:

- `storage/`
- `app/config.local.php`
- logs
- FTP backup files
- zip archives

The current live FTP tree had many `.bak.*` files. This repository starts from the current active files only.

Before feature integration, dump and commit a sanitized database schema from the live database. The current `deploy/` SQL files are useful history, but `On the Go` runtime tables evolved beyond the original foundation file.
