# Step 2 - Group Access Foundation - 2026-05-20

## Goal

Add group-scoped access levels without limiting the user's personal account.

One email account can have:

- full personal/private finance access;
- `base` access in one work group;
- `manager` access in another group;
- `advanced` access in a group the user administers.

## Access Levels

```text
base
  On the Go / pocket mode.
  Can capture own facts, send messages, keep full personal profile.
  Cannot write directly into group ledger.

manager
  Middle layer.
  Can use Captain Fin/moderation layer later.
  Can write group ledger and see group reports/members.

advanced
  Organizer/admin layer.
  Can manage members, invites, group rules and full group data.
```

## Database Changes

New migration:

```text
deploy/group_access_levels.sql
```

Added to `group_members`:

```text
access_level ENUM('base','manager','advanced')
permissions_json JSON
invited_by
invite_id
```

Added to `group_invites`:

```text
invited_email
access_level ENUM('base','manager','advanced')
permissions_json JSON
```

`groups_foundation.sql` was also updated so fresh installs have the same fields.

## Backend Changes

Invites now carry the intended group access:

```text
group_invite_create:
  invited_email
  access_level
```

Join flow:

- token is still the secret;
- if `invited_email` is set, the logged-in email must match;
- membership is created with the invite's `access_level`;
- `advanced` membership maps to internal `role = admin`;
- `base` and `manager` map to internal `role = member`.

New API:

```text
group_member_access_update
```

Only an advanced/admin group member can change another member's access level.

## Access Enforcement

Group ledger now uses group-scoped rights:

- `base`: cannot create direct group ledger rows;
- `manager`: can write group ledger and see group reports;
- `advanced`: can write, see all group reports and manage members.

Personal ledger remains available to the same user even if that user is only `base` inside a work group.

## UI Foundation

The group invite panel now has:

- optional employee email;
- access level selector: `base`, `manager`, `advanced`.

Members list displays access level and, for advanced users, allows changing member access.

This is still foundation UI, not final facade.

## Step 1 + Step 2 Smoke

The local smoke script was expanded:

```bash
php scripts/local-smoke.php http://127.0.0.1:18888
```

Current checked flow:

```text
current_user endpoint responds
admin login by 6-digit code
admin creates group
admin creates invite
member login by 6-digit code
email-bound invite rejects wrong user
member joins group by base invite
base member sees only own membership
admin sees group members
group messages and unread work
base member cannot write direct group ledger
base member keeps full personal profile
admin promotes member to manager
manager sees group members
manager group ledger write and admin visibility work
advanced member can manage invites
personal ledger update/delete work
On the Go tape/capture/list work
```

## Known Next Step

Step 3 should add the `advance / accountable money` object:

```text
admin issued money -> base user pocket session -> submit for moderation -> manager/admin accepts -> expenses enter common report
```

This must not be modeled as a normal expense at issue time.

