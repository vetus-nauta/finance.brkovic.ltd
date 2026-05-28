# Production Hotfix: Base Employee Message Unread

Date: 2026-05-27

Owner: Project Director / Backend Data Engineer

Status: deployed; director production API smoke passed; QA release recheck required.

## Trigger

QA Release Engineer rechecked the production hotfixes and accepted participant-control, but blocked default base employee rights because `message_unread` returned HTTP `500` for a base employee.

Evidence pointer:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_hotfix_recheck_2026-05-27/SUMMARY.md`

## Root Cause

The SQL alias `current_role` in `ql_message_unread()` collided with the production MySQL parser. Local MariaDB accepted the query, but production returned a syntax error.

## Fix

Changed only internal SQL result aliases in `app/messages.php`:

- `current_role` -> `member_role_for_scope`
- `current_access_level` -> `member_access_level_for_scope`
- `current_permissions_json` -> `member_permissions_json_for_scope`

Permission behavior was not expanded.

## Backup

Before upload, the production file was downloaded and archived.

- backup id: `prod-hotfix-before-message-unread-20260527T212247Z`
- archive: `backups/prod-hotfix-before-message-unread-20260527T212247Z.tgz`
- checksum: `910dfe9a79731f30d3fab4511f078ea39c47a9366020db1237d6e0d0ebf48891`

## Uploaded Checksums

Local and production file checksums matched after upload:

- `app/messages.php`: `75e8f9c61fbcb5f595bc8ffb56dd36b42fa8beee8b08cb88d6e99c33e815cb15`

## Verification

Local API smoke:

- group id: `225`
- admin user id: `533`
- base employee user id: `534`
- `message_unread`: `ok=true`, `unread_count=0`
- `message_list`: `access_denied`
- `message_send`: `access_denied`

Production API smoke:

- group id: `19`
- admin user id: `56`
- base employee user id: `57`
- `message_unread`: `ok=true`, `unread_count=0`
- `message_list`: `access_denied`
- `message_send`: `access_denied`

## Gate

Director smoke closes the backend defect. QA Release Engineer must rerun the default base employee rights slice and record the independent result in the QA folder.
