# Open Items Sprint Deploy Attempt - 2026-05-28

Owner: Project Director FinDesk
Status: production deploy blocked in current shell

## Candidate

Local candidate: `20260528-open-sprint1`.

Evidence:

- local sprint report: `docs/AI_TEAM/45_OPEN_ITEMS_SPRINT_LOCAL_2026-05-28.md`;
- last known Git commit before sprint: `459c751`;
- production before this attempt: `20260528-records-scroll1`.

## Local Result

The open-items sprint is implemented and locally verified:

- first-class group message context fields and API `context_links`;
- final-report package direct linked messages;
- package JSON export plus legacy JSON snapshot fallback;
- explicit language fallback state;
- scanner camera/file boundary hardening;
- app/service-worker asset version `20260528-open-sprint1`.

Local message-context smoke after applying the local `messages_foundation.sql` migration:

- `group_id=247`;
- `tape_id=349`;
- `message_send` returned `context_links.tape_id=349`;
- invalid `report_id` failed closed with `invalid_message_context`.

Local package end-to-end smoke:

- `group_id=248`;
- `tape_id=350`;
- `message_id=161`;
- `report_id=587`;
- package `messages.report_context` included the linked message;
- JSON export returned attachment `findesk-final-report-package-587.json`.

## Deploy Blocker

Production deploy did not run because this shell has no deploy channel variables:

- `FINDESK_FTP_HOST`: absent;
- `FINDESK_FTP_USER`: absent;
- `FINDESK_FTP_PASS`: absent;
- `FINDESK_FTP_ROOT`: absent;
- `FINDESK_DB_GATE_URL`: absent.

The repo contains deploy scripts that expect these variables, but no usable credential source was found in the current shell or project tree. No FTP backup, upload, DB-gate apply, production smoke, or production file change was executed.

## Ready Deploy Steps

When deploy variables are available, run only the selected flow:

1. FTP backup of production tree.
2. Upload temporary DB-gate.
3. DB preflight/backup through the gate.
4. Apply `deploy/messages_foundation.sql`.
5. Upload selected candidate files.
6. Run `backups/deploy-tools/prod_candidate34_smoke.py` against production.
7. Remove DB-gate and verify it returns `404`.
8. Record production report and update role statuses.

## Open Boundaries

- Real-device scanner/PWA camera gate remains open until iPhone/Android evidence exists.
- JSON package export is complete for package data and proof links; ZIP proof-binary export is still a separate future task.
