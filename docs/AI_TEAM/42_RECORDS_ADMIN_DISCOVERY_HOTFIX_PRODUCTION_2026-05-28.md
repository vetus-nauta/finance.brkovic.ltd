# Records Admin Discovery Hotfix Production Report - 2026-05-28

Role: Project Director / Deploy Owner
Task: deploy P0 Live Report records page fix after QA recheck.
Status: deployed and smoke-passed.

## Scope

Production hotfix files:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

No backend/API contract, database schema, or financial formula changes were deployed in this hotfix.

## Fixed

- Group admins now open `Живые отчеты` records page in the selected/active group scope, so employee submitted cards are discoverable in the normal records list.
- Opening the records page closes the intermediate stream gate, so the gate no longer overlays the list or intercepts clicks.
- Row proof viewer `Открыть` no longer forces download.
- Mobile overflow hardening added for long card titles, proof labels, and modal action buttons.
- Asset version bumped to `20260528-records-admin1`.

## Pre-Deploy Evidence

- `node --check public/assets/app.js`: PASS.
- `git diff --check`: PASS.
- Local API smoke: group `235`, admin tape `307`, employee tape `308`; admin sees both, base employee sees only own.
- Local Playwright mobile smoke: group `244`, employee tape `332`, capture `217`, proof controls `2`.
- QA Release Engineer recheck: PASS, run `20260528RECORDSRECHECK04`.

## Backup

FTP backup before hotfix:

- directory: `backups/prod-files-before-records-admin-hotfix-20260528T161340Z`
- archive: `backups/prod-files-before-records-admin-hotfix-20260528T161340Z.tgz`
- files: `176`
- bytes: `19270942`
- SHA256: `0ee8c7728ddc15760303f297bd3bc705f7cea556315b832daee93ab28173ca71`

## Production Smoke

Run id: `prod-records-hotfix-20260528161828`

Fixture:

- group_id: `36`
- admin_user_id: `84`
- member_user_id: `85`
- admin_tape_id: `111`
- employee_tape_id: `112`
- capture_id: `157`
- image_file_id: `30`
- pdf_file_id: `31`
- proof_controls: `2`

Verified:

- production `app.php` contains asset version `20260528-records-admin1`;
- production `app.js` contains `cardsGroupScope`, `qlOtrSimpleHideStreamGate`, and proof viewer `removeAttribute('download')`;
- group admin API list with `group_id` includes both admin and employee cards;
- base employee API list with `group_id` includes own card and does not include admin card;
- mobile browser path opens records page, finds employee card, opens card, shows proof controls, opens/closes proof viewer;
- stream gate is not left open over the records page;
- `current_user` public API shape remains OK.

## Gate Cleanup

Temporary DB-gate:

- uploaded for smoke only;
- removed after smoke;
- public URL returned `404` after removal.

## Result

P0 records-page blocker is closed on production for this hotfix scope. Broader real-device scanner/PWA camera gate remains separate.
