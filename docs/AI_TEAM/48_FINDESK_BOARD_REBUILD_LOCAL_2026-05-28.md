# FinDesk Board Rebuild Local Sprint - 2026-05-28

Owner: Project Director FinDesk
Status: local implementation complete; browser/manual QA pending

## Input

CEO requested a rebuilt FinDesk board:

- top balance row under the header: cash at administrator and cash at employees;
- FinDesk board as administrator card with employee cards;
- submitted fast-entry employee cards must glow orange;
- administrator can approve or return employee report;
- approved employee report becomes a small child card attached to administrator report;
- administrator can create and approve the summary report;
- finalized math must stay immutable and archived;
- employee cards show only received amount and remaining amount;
- other menu items move under one `Детали` menu;
- do not break architecture, formulas, or existing calculations.

Reference:

- Google Drive screenshot imported locally as `tmp/drive-input/findesk_board_reference.bin`.

## Role Task Cards

Product / Finance Architect:

- Read: `docs/AI_TEAM/04_TASK_BOARD.md`, `docs/AI_TEAM/05_DECISIONS.md`, reports `38-47`.
- Write: role status/findings only.
- Result: rule `20 cards` is treated as a UI working-list limit, not destructive deletion.

Backend / Data Engineer:

- Read: `app/on_the_go.php`, `app/ledger.php`, `app/advances.php`, `public/api.php`.
- Write: no runtime code in this sprint.
- Result: use existing endpoints `ledger_balance`, `on_the_go_card_list`, `advance_list`, `on_the_go_card_include/unsubmit`, `advance_accept/return`, `ledger_group_finalize_report`.

Frontend / UX Engineer:

- Read/write: `public/app.php`, `public/assets/app.js`, `public/assets/app.css`.
- Result: rebuild `#moduleCaptain` as the FinDesk board; no new backend route.

QA / Release Engineer:

- Read: changed frontend files and role status.
- Write: QA status/checklist.
- Result: local syntax/HTTP checks passed; browser/device QA remains required.

## Implemented Locally

- Asset version bumped to `20260528-findesk-board1`.
- Main nav now exposes `Живой отчет`, `FinDesk`, and one `Детали` menu for the rest.
- `#moduleCaptain` rebuilt into:
  - sticky top cash strip: `У админа`, `У сотрудников`;
  - administrator report card;
  - current admin fast-entry summary;
  - child report cards for included live cards and accepted advances;
  - employee participant strip;
  - employee cards with orange submitted state;
  - compact archive section.
- `renderCaptainAdvances()` now renders employee cards from existing live cards, advances, members, and included/accepted state.
- `ledger_balance` drives the top cash figures without changing formulas.
- `Создать и утвердить отчет` calls existing `ledger_group_finalize_report`.
- `Открыть`, `Распечатать`, `Дочерние`, `Открыть архив`, approve and return actions reuse existing handlers/API.

## Verification

Passed:

- `node --check public/assets/app.js`
- `node --check public/assets/i18n.js`
- `node --check public/service-worker.js`
- `git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/service-worker.js`
- `curl http://127.0.0.1:18889/app.php` returned `200`
- `curl http://127.0.0.1:18889/api.php?action=current_user` returned JSON `{"ok":true,"user":null}`
- HTML smoke found `Детали`, `FinDesk`, `captainAdminCashLeft`, `captainEmployeeCashLeft`, `captainParticipantStrip`, `data-captain-finalize-report`.

Blocked / pending:

- authenticated browser QA for approve/return/finalize flow;
- visual check at `390x844`, `820x1180`, `1440x900`;
- production deploy, because deploy credentials and DB-gate variables are not present in this shell.

## Decision

No destructive deletion for the `20 cards` rule in this sprint. It is a working-list display limit until CEO explicitly confirms a data-retention policy that does not conflict with no-data-loss and immutable archive requirements.
