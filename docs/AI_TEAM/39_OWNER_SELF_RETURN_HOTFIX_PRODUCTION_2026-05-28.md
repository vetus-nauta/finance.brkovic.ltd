# Owner Self-Return Hotfix Production Report

Date: 2026-05-28
Owner: Backend/Data + Project Director
Status: deployed; production smoke passed

## Problem

Legacy submitted Live Report cards could be stuck when the card owner no longer had an active group moderator scope, for example after a test group was soft-archived.

Observed user-facing symptom:

- owner/admin sees a submitted card;
- action routes to `Запросить исправление`;
- request is recorded, but there may be no active administrator left to process it.

## Patch

Changed `app/on_the_go.php`:

- a card owner may directly return their own `submitted` card to draft;
- included/final-report cards are not opened by this owner shortcut;
- public card payload now exposes `can_return=true` for own submitted card, including grouped cards, so UI shows direct return instead of request-only state.

Financial formulas were not changed.

## Backup Evidence

Production files/storage backup before hotfix upload:

- backup id: `prod-files-before-owner-self-return-hotfix-20260528T140737Z`;
- archive: `backups/prod-files-before-owner-self-return-hotfix-20260528T140737Z.tgz`;
- checksum: `951f5d1e584fff40849d2bd1be4583fe10c074f450f81b0c843e4700cd64bf3d`;
- files downloaded: `143`;
- bytes downloaded before archive: `14814529`;
- errors: `0`.

No DB migration was required.

## Production Smoke

Run id:

- `prod-owner-self-return-20260528140915`

Fixture:

- admin user id: `65`;
- group id: `25`;
- tape id: `84`.

Checks passed:

- created owner/admin group card;
- saved money row and submitted card to FinDesk;
- soft-archived the group;
- reopened submitted card as the owner;
- `can_return=true`;
- `can_request_return=false`;
- `on_the_go_card_unsubmit` returned the card to `draft`.

Temporary DB-gate:

- uploaded only to read the smoke login code from the protected auth log;
- removed after smoke;
- post-removal HTTP check returned `404`.

## Release Control

This hotfix closes the stuck submitted-card path where the owner is the only practical actor.

It does not alter:

- included final-report cards;
- historical finalized report snapshots;
- financial formulas;
- group final package/archive behavior.
