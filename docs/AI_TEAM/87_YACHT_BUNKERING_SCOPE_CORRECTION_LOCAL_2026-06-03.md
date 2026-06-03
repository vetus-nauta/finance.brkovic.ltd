# Yacht Bunkering Scope Correction - Local Report 2026-06-03

## Status

Local correction completed. Production deploy not performed.

## Product Decision

FinDesk is a financial program first. Bunkering is not a universal start path and should not appear as a primary start button.

Correct placement:

- FinDesk start remains general: solo work, team work, ready templates.
- Yacht remains one ready template.
- Bunkering lives inside the Yacht template.

## Changed

- Removed pre-auth `Бункеровка` start button.
- Removed `Бункеровка` from Welcome Hall start paths.
- Removed `Бункеровка` from the top `Шаблоны` menu.
- Removed separate `Бункеровка` card from the Templates screen.
- Added internal Yacht button `Бункеровка`, which scrolls to the bunkering/start package section inside Yacht.
- Bumped frontend asset/cache version to `20260603-yacht-bunkering-inside1`.

## Not Changed

- Yacht calculation logic.
- Approved price bridge.
- Bunkering/start package table.
- Print flow.

## Files

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/service-worker.js`
- `docs/AI_TEAM/04_TASK_BOARD.md`

## QA Required

- Start page must not show `Бункеровка`.
- Welcome Hall must not show `Бункеровка` as a main product path.
- Top menu must show `Шаблоны -> Yacht`, not direct `Бункеровка`.
- Yacht screen must show an internal `Бункеровка` button.
- Internal Yacht button must scroll to `Бункеровка / стартовый пакет`.
