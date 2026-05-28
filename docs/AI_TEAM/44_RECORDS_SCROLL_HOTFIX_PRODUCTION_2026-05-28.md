# Records Scroll Hotfix Production Report - 2026-05-28

Role: Project Director / Frontend UX
Task: fix impossible scrolling in `Живые отчеты` records window when the card column is long.
Status: deployed and smoke-passed.

## Scope

Production hotfix files:

- `public/app.php`
- `public/assets/app.css`

No backend/API contract, database schema, proof storage, or financial formula changes were deployed.

## Root Cause

The records list used a grid container and the card group could be compressed into the visible panel height. With a long column, cards shrank instead of increasing `scrollHeight`, so the user could not scroll the records window normally.

## Fixed

- `Живые отчеты` panel now uses `grid-template-rows: auto minmax(0, 1fr)`.
- Records list is a block scroll container with `overflow-y: auto`, `overscroll-behavior: contain`, and iOS momentum scrolling.
- Multiple date/month groups get normal vertical spacing.
- Mobile cards keep a usable minimum height instead of shrinking into unreadable rows.
- Editor working area also has contained vertical scrolling for large content.
- Asset version bumped to `20260528-records-scroll1`.

## Pre-Deploy Evidence

- `node --check public/assets/app.js`: PASS.
- `git diff --check`: PASS.
- Local Playwright run: `local-scroll-20260528164027`.
- Local scroll evidence:
  - mobile `390x844`: list `clientHeight=621`, `scrollHeight=3827`, `scrollTop=3206`, first card `103.31px`;
  - desktop `1440x900`: list `clientHeight=635`, `scrollHeight=3815`, `scrollTop=3180`, first card `92px`.

## Backup

FTP backup before hotfix:

- directory: `backups/prod-files-before-records-scroll-hotfix-20260528T164100Z`
- archive: `backups/prod-files-before-records-scroll-hotfix-20260528T164100Z.tgz`
- files: `192`
- bytes: `49238057`
- SHA256: `784ab1082e95cbfb1e8044182121e83a50310b414cf43e63b5099c8ad02c0d98`

## Production Smoke

Run id: `prod-records-scroll-20260528164351`

Verified:

- production `app.php` contains asset version `20260528-records-scroll1`;
- production CSS contains records scroll markers;
- mobile `390x844`: list `clientHeight=621`, `scrollHeight=3183`, `scrollTop=2562`, first card `85.40px`;
- desktop `1440x900`: list `clientHeight=635`, `scrollHeight=3815`, `scrollTop=3180`, first card `92px`;
- public `current_user` API shape remained OK;
- temporary DB-gate was not used and stayed `404`.

## Result

Long records columns now scroll normally inside the records window on mobile and desktop.
