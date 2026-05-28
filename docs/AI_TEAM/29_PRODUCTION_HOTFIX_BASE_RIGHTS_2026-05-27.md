# Production Hotfix: Base Employee Rights

Date: 2026-05-27

Owner: Project Director / Backend Data Engineer / Frontend UX Engineer

Status: deployed; director production API smoke passed; QA release recheck required.

## Product Rule

When an administrator invites a participant to a group, default `base` access means:

- the employee can use FinDesk normally as a personal user;
- inside the invited group, the employee gets only operational capture, accountable/self-control, and primary own-output data;
- the employee does not get group ledger, group reports, final reports, group export, archive, group messages, member list, role management, money management, or other participants' money data.

Managers/admins can still be granted broader group access explicitly.

## Scope

Uploaded only:

- `app/on_the_go.php`
- `app/messages.php`
- `public/app.php`
- `public/assets/app.js`

Not changed:

- database schema;
- production data;
- credentials/config;
- other runtime files.

## Backup

Before upload, replaced production files were downloaded and archived.

- backup id: `prod-hotfix-before-base-rights-20260527T210230Z`
- archive: `backups/prod-hotfix-before-base-rights-20260527T210230Z.tgz`
- checksum: `d6344267925c9742f4f6f21e3e4609942d53544fe2ea998a5eaf9904afe8d732`

## Uploaded Checksums

Local files were re-downloaded from production after upload and matched these checksums:

- `app/on_the_go.php`: `d2b19272ff04ff8440f758bd7897e9be9c03067d6152ab9db6bfdd3ca4dacdad`
- `app/messages.php`: `ba802c47daafa4e473c43754434f157010ceb30a3b4ca9333c1277411d652043`
- `public/app.php`: `00d6b389b73769542b65ff6c8bcef47030d664d10b940f5930c3448c325ef128`
- `public/assets/app.js`: `871a84dc19fb0ee8ba31d16827e97a9a03af66782d03269c8eb0fe9ceca02425`

## Production Smoke

HTTP load:

- `https://finance.brkovic.ltd/api.php?action=current_user`: `200`

Director production API scenario:

- stamp: `20260527210337`
- group id: `10`
- employee user id: `27`

Passed production checks:

- default invite created `access_level=base`;
- base permissions deny `can_view_group_reports`, `can_write_group_ledger`, and `can_manage_money`;
- base employee sees only self in `group_members`;
- base employee cannot export group report;
- base employee cannot list final group reports;
- base employee cannot read group messages;
- base employee cannot send group messages;
- base employee self-control balance does not include admin group cash `1000`;
- base field tape starts from own cash base `0`, not group cash `1000`;
- base field draft with another `participant_user_id` is forced back to self;
- base employee can save own operational field row;
- base employee sees only own operational cards.

## Gate

This hotfix closes the director/backend production smoke for default employee rights. It does not replace the QA Release Engineer gate. QA must record an independent production recheck in the QA folder.
