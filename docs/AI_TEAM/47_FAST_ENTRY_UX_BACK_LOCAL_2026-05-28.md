# Fast Entry UX + Browser Back Local Patch - 2026-05-28

Owner: Project Director FinDesk
Status: local frontend patch complete; QA/browser visual pass pending

## Input

CEO reported problems on the fast-entry screen:

- no visible way to open saved photos, scans, and PDF proofs;
- edit button overlaps the final amount;
- decorative shadow/card appears in the lower-right corner;
- Scan and Photo controls feel outdated;
- fixed expense preview scroll adds no value on this screen;
- `Нал` sounds too familiar; use `Наличные`;
- browser Back must move one app step back, not jump to the beginning.

## Frontend/UX Task Card

Read:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`

Write:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`

Scope:

- keep backend/storage/report/proof logic unchanged;
- simplify only the fast-entry surface;
- preserve existing save/autosave/proof upload paths;
- implement app-step browser history without changing URLs outside `/app.php`.

## Implemented Locally

- `#otrStreamSwitchBtn` now says `Наличные`.
- Edit/finish action moved out of the amount metrics area into the bottom action row.
- Floating proof actions now use modern text buttons: `Фото`, `Скан`, `Файл`.
- Decorative lower-right pseudo-card on `#otrSimpleCard` is disabled.
- Fixed expense preview panel is hidden in editor mode.
- Added `Открыть сохраненные файлы` button for saved proofs; it opens the existing card/proof viewer flow.
- Added app-step browser history for module tabs, mode-open buttons, Advanced sub-screens, and key On-the-Go editor/cards/stream transitions.

## Verification

Passed:

- `node --check public/assets/app.js`
- `curl -I http://127.0.0.1:18889/app.php`
- HTML smoke found `Фото`, `Скан`, `Файл`, `Наличные`, and `#otrSimpleProofsBtn`
- `git diff --check -- public/app.php public/assets/app.js public/assets/app.css`

## Remaining QA

- Browser visual check on mobile `390x844`: no amount overlap, no lower-right shadow, proof buttons fit.
- Browser Back manual check: editor -> cards -> previous app step, and module tabs step back correctly.
- Proof viewer check: saved image/PDF opens from fast-entry saved-files button.
