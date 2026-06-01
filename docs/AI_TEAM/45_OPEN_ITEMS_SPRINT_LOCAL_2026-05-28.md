# Open Items Sprint Local Report - 2026-05-28

Owner: Project Director FinDesk
Status: local implementation complete; production deploy pending

## Scope

This sprint targeted the remaining open items from the 2026-05-28 director intake:

- real-device scanner/PWA camera gate;
- language/PWA audit;
- legacy package fixtures/fallbacks;
- first-class message links to reports/captures/advances;
- package-wide archive export.

## Local Changes

Runtime files changed:

- `app/messages.php`
- `app/ledger.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/i18n.js`
- `public/service-worker.js`
- `deploy/messages_foundation.sql`

Deploy helper coverage verified:

- `backups/deploy-tools/findesk_db_gate_candidate34_20260528.php`
- `backups/deploy-tools/prod_candidate34_ftp_ops.py`
- `backups/deploy-tools/prod_candidate34_smoke.py`

## Implemented

1. First-class group message context links:
   - `group_messages` now has deployable columns for `report_id`, `tape_id`, `capture_id`, and `advance_id`.
   - `message_send`, `message_get`, `message_list`, and `message_unread` return `context_links`.
   - Context ids are validated against the selected group before insert.
   - New group final report packages include direct linked messages by `report_id`, `tape_id`, `capture_id`, and `advance_id` when those links exist before finalization.

2. Package-wide archive export:
   - new action: `ledger_group_final_report_package_export`;
   - exports a full JSON package for new `report_package` reports;
   - exports a legacy JSON historical snapshot when the immutable package is missing but `report_snapshot` exists;
   - frontend package view now has `Скачать пакет JSON`.

3. Legacy package fallback:
   - legacy final reports without `report_package` keep the warning UI;
   - legacy export now has a machine-readable JSON fallback instead of only screen detail.

4. Language/PWA fallback audit:
   - `public/assets/i18n.js` now exposes `window.QL_LANGUAGE_STATE`;
   - unsupported system languages explicitly resolve to English with `fallback_applied=true`;
   - service-worker cache and app asset versions are bumped to `20260528-open-sprint1`.

5. Scanner/PWA camera gate hardening:
   - scanner modal keeps `accept=image/*` and `capture=environment` before every camera/file click;
   - modal text now states the real boundary: camera opens only where browser/PWA permits capture, otherwise user chooses a photo.

## Local Verification

Passed:

- `node --check public/assets/app.js`
- `node --check public/assets/i18n.js`
- `node --check public/service-worker.js`
- `python3 -m py_compile backups/deploy-tools/prod_candidate34_ftp_ops.py backups/deploy-tools/prod_candidate34_smoke.py`
- `curl http://127.0.0.1:18889/api.php?action=current_user` returned `{"ok":true,"user":null}`
- `curl -I http://127.0.0.1:18889/app.php` returned `HTTP/1.1 200 OK`
- `git diff --check`

Local API smoke:

- local `deploy/messages_foundation.sql` migration applied through a temporary localhost-only endpoint, then the endpoint was removed;
- local schema now has `group_messages.report_id`, `tape_id`, `capture_id`, `advance_id` plus all four context indexes;
- auth code login passed for a fresh `sprint-recheck-*@example.test` user;
- group fixture `group_id=247` created;
- `on_the_go_field_draft_save` created `tape_id=349`;
- `message_send` with `tape_id=349` passed and returned `context_links.tape_id=349`;
- invalid `report_id` context correctly returned `invalid_message_context`;
- `message_list` returned the saved message with `context_links.tape_id=349`.

Language fallback smoke:

- Node VM with `navigator.languages=['fr-FR']` returned:
  - `language=en`;
  - `fallback_applied=true`;
  - supported languages `en,ru,de,it,es,sr,zh`.

Package export route smoke:

- invalid report request returned controlled `final_report_not_found` instead of PHP/runtime failure.

Package end-to-end smoke:

- fixture `group_id=248`;
- linked card `tape_id=350`;
- linked message `message_id=161`;
- finalized report `report_id=587`;
- `ledger_group_final_report_package` included the linked group message in `package.messages.report_context`;
- JSON package export returned `HTTP 200`, attachment filename `findesk-final-report-package-587.json`, and included the linked message text.

## Boundary

Real physical device scanner/PWA camera readiness is still not proven locally. This sprint hardens and labels the path, but the gate in `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md` remains factual until iPhone/Android device evidence exists.

The package export implemented here is JSON package export with authorized proof download links, not a ZIP bundle of proof binaries.

## Production Candidate

Candidate asset version: `20260528-open-sprint1`.

Selected production files:

- `app/ledger.php`
- `app/messages.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/i18n.js`
- `public/service-worker.js`
- `deploy/messages_foundation.sql`

Deploy helpers include `deploy/messages_foundation.sql` in DB apply and smoke:

- asset version;
- language fallback markers;
- first-class message link to tape/capture;
- closed package direct message inclusion;
- package JSON export.
