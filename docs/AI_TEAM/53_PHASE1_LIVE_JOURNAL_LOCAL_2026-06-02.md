# Phase 1 Live Journal Local Slice - 2026-06-02

Owner: Project Director FinDesk
Status: local only
Priority: P0

## Goal

Start Phase 1 from the correct first screen:

- `Live Journal`

This slice does not deploy anything.
It only rebuilds the first working screen locally.

## Files Changed

- `public/app.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `app/on_the_go.php`

## What Changed

1. `Live Journal` now opens as the default first working screen after stream choice.
2. The editor is now the primary local screen, not the cards list.
3. The main screen was reduced to:
   - stream/context;
   - start journal / last fixation area;
   - current remaining;
   - record count;
   - one input area;
   - one attachment entry point;
   - fix journal action.
4. Quick capture strip was removed from the first level.
5. The editor action text was changed from `Готово` to `Зафиксировать журнал`.
6. Attachment controls were reduced to one visible `Скрепка` entry point with inner options.
7. Backend now exposes previous fixed report info for the open card:
   - `last_fixed_amount`
   - `last_fixed_at`

## What Was Not Changed

- auth;
- PWA foundation;
- manifest;
- service worker;
- file/proof upload foundation;
- report pipeline foundation;
- group/final-report archive foundation.

## Local Checks

Passed:

```bash
node --check public/assets/app.js
git diff --check public/app.php public/assets/app.css public/assets/app.js app/on_the_go.php
```

Browser check passed locally:

- after login and stream choice, `Live Journal` opens first;
- cards list does not open by default;
- no quick capture strip on the first level;
- one attachment menu is visible;
- primary action label is `Зафиксировать журнал`.

Evidence screenshot:

- `test-results/phase1-live-journal-mobile.png`

## Next Step

Continue Phase 1 with:

1. final cleanup of the first-screen copy and spacing after CEO review;
2. then `Team Workspace`;
3. then `Admin Card`;
4. then `Employee Card`;
5. then `Group Report Assembly`.
