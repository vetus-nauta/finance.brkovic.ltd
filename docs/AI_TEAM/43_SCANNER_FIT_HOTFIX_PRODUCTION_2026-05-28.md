# Scanner Fit Hotfix Production Report - 2026-05-28

Role: Project Director / Frontend UX
Task: fix mobile scanner modal geometry after Live Report notes-style editor audit.
Status: deployed and smoke-passed.

## Scope

Production hotfix files:

- `public/app.php`
- `public/assets/app.css`

No backend/API contract, database schema, proof storage, or financial formula changes were deployed.

## Fixed

- Receipt scanner modal no longer overflows a phone viewport.
- On mobile, scanner modal uses exact viewport width/height with `box-sizing: border-box`.
- Scanner stage height is reduced to a usable phone size.
- Scanner action buttons use a tighter two-row layout so `Переснять`, `Отмена`, and `Прикрепить PDF` remain reachable.
- Asset version bumped to `20260528-scanner-fit1`.

## Pre-Deploy Evidence

- `node --check public/assets/app.js`: PASS.
- `git diff --check`: PASS.
- Local Playwright run: `local-notes-ui-20260528162458`.
- Local viewport coverage:
  - mobile `390x844`: notes field `578px`, scanner modal `390x844`, scanner stage `368x415.64`;
  - tablet `820x1180`: notes field `990px`, scanner modal `720x900`;
  - desktop `1440x900`: notes field `710px`, scanner modal `720x813.98`.

## Backup

FTP backup before hotfix:

- directory: `backups/prod-files-before-scanner-fit-hotfix-20260528T162700Z`
- archive: `backups/prod-files-before-scanner-fit-hotfix-20260528T162700Z.tgz`
- files: `179`
- bytes: `19278773`
- SHA256: `95b874d6b0b29230582e0933bedd01b67e531529acb4b9c3fd94376e389571a2`

## Production Smoke

Run id: `prod-scanner-fit-20260528162815`

Verified on production mobile viewport `390x844`:

- production `app.php` contains asset version `20260528-scanner-fit1`;
- production CSS contains scanner fit markers;
- authenticated test user opened `Живой отчет`;
- notes input height: `578px`;
- scanner modal geometry: `390x844`, right `390`, bottom `844`;
- scanner stage geometry: `368x415.64`, right `379`, bottom `499.83`;
- scanner close/retake/attach controls were reachable;
- temporary DB-gate was removed after smoke and returned `404`;
- public `current_user` API shape remained OK.

## Result

Mobile scanner modal overflow is closed for the browser smoke path. Real-device iPhone Safari/PWA camera behavior remains a separate device gate.
