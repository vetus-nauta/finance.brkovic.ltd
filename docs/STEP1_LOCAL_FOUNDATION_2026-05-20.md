# Step 1 - Local Foundation Check - 2026-05-20

## Goal

Bring `finance.brkovic.ltd` to a reproducible localhost baseline before building roles, moderation, Captain Fin integration and final UI.

## Local Environment

Verified on this PC:

- PHP 8.3.6
- `pdo_mysql` and `mysqli` enabled
- MariaDB 10.11 running on `127.0.0.1:3306`
- Local app server: `http://127.0.0.1:18888`

## Local Database

Local database created:

```text
finance_brkovic_local
```

Local-only app config:

```text
app/config.local.php
```

This file is ignored by Git.

## Migrations Applied

Applied in this order:

```text
deploy/auth_foundation.sql
deploy/ledger_foundation.sql
deploy/groups_foundation.sql
deploy/categories_foundation.sql
deploy/messages_foundation.sql
deploy/business_desk_foundation.sql
deploy/on_the_go_foundation.sql
deploy/on_the_go_sessions_runtime.sql
```

Confirmed key tables:

```text
users
sessions
groups
group_members
group_invites
group_messages
group_message_reads
ledger_entries
entry_files
ledger_categories
on_the_go_captures
on_the_go_files
on_the_go_tapes
on_the_go_sessions
company_profiles
clients
proformas
```

## Fixes Made During Step 1

Local mail mode:

- added `mail.mode = log` support;
- localhost can test 6-digit email-code login without real SMTP.

Ledger runtime SQL:

- fixed `ledger_update`;
- fixed `ledger_delete`;
- both used `le.*` aliases inside `UPDATE ledger_entries`, which MariaDB rejects.

## Smoke Test

Reusable smoke script:

```text
scripts/local-smoke.php
```

Run:

```bash
php scripts/local-smoke.php http://127.0.0.1:18888
```

Current result:

```text
PASS: current_user endpoint responds
PASS: admin login by 6-digit code
PASS: admin creates group
PASS: admin creates invite
PASS: member login by 6-digit code
PASS: member joins group by invite
PASS: admin sees group members
PASS: group messages and unread work
PASS: group ledger write and admin visibility work
PASS: personal ledger update/delete work
PASS: On the Go tape/capture/list work
OK: local smoke completed for http://127.0.0.1:18888
```

## Important Architectural Finding

Current group permissions are still basic:

```text
admin
member
```

This is enough for the current MVP but not enough for the target product.

Next foundation step should add group-scoped access levels:

```text
base      = On the Go only
manager   = Captain Fin / moderation layer
advanced  = administrator / organizer layer
```

These levels must belong to `group_members`, not to the global user account. One user can have full personal access and limited access inside a work group.

