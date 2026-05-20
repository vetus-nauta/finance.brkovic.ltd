# Predeploy Checklist - finance.brkovic.ltd - 2026-05-20

## Freeze Scope

Do not rewrite these working flows before upload:

- email-code auth;
- groups and invites;
- group access levels: base / manager / advanced;
- personal and group ledger;
- On the Go capture/session flow;
- Money / accountable advances;
- advance submit / accept / return conversion.

Allowed before upload:

- visual spacing and text fixes;
- noindex/robots/service-worker version fixes;
- deployment docs;
- backup and rollback scripts.

## Local Baseline

Local app:

```bash
php -S 127.0.0.1:18888 -t public
```

Required checks:

```bash
rg --files -g '*.php' app public scripts deploy | xargs -n1 php -l
node --check public/assets/app.js
php scripts/local-smoke.php http://127.0.0.1:18888
git diff --check
```

Fresh migration order:

```text
deploy/auth_foundation.sql
deploy/ledger_foundation.sql
deploy/groups_foundation.sql
deploy/group_access_levels.sql
deploy/categories_foundation.sql
deploy/messages_foundation.sql
deploy/business_desk_foundation.sql
deploy/on_the_go_foundation.sql
deploy/on_the_go_sessions_runtime.sql
deploy/advances_foundation.sql
```

## Live Backup Before Upload

Create a dated backup before touching live files:

```text
live files -> backups/YYYY-MM-DD/finance.brkovic.ltd-files
live database schema+data -> backups/YYYY-MM-DD/finance.brkovic.ltd-db.sql
```

Also create a schema-only dump without data/secrets for repository comparison.

Do not commit:

```text
app/config.local.php
storage/
logs
database dumps with data
FTP backup archives
*.bak
```

## Upload Package

Upload active source only:

```text
app/
deploy/
public/
api.php
app.php
index.php
README.md
```

Skip:

```text
storage/
backups/
zip archives
.git/
app/config.local.php
local logs
old .bak files
```

Server config must be created/kept separately:

```text
app/config.local.php
```

## Database Update

Before running SQL on live:

1. Compare live schema with `deploy/*.sql`.
2. Apply only missing migrations.
3. Re-run app smoke manually after each schema-changing block.

New tables/fields currently expected by the app:

```text
on_the_go_tapes
on_the_go_sessions
cash_advances
on_the_go_tapes.group_id
on_the_go_tapes.advance_id
on_the_go_tapes.submitted_at
on_the_go_tapes.actual_remaining
on_the_go_tapes.difference_amount
```

## Post Upload Smoke

After upload:

- open `/app.php`;
- login by 6-digit code;
- create or open a test group;
- check base / manager / advanced access;
- create On the Go entry;
- issue accountable money;
- submit with actual remaining cash;
- accept into group ledger;
- return a mismatched advance;
- open Premium tab and confirm placeholders are visible.

## Rollback

If live breaks:

1. Put maintenance placeholder on `/app.php`.
2. Restore previous live files from dated backup.
3. Restore database backup only if schema/data got damaged.
4. Clear browser/service-worker cache if stale assets remain.
5. Record the failing file, migration and exact error before trying again.

## Current Non-Blocking Placeholders

Premium features are visible but not gated:

- Advanced Mode;
- Trip with Friends;
- Report Studio.

`Trip with Friends` is only a shell now. No backend balancing logic is active yet.
