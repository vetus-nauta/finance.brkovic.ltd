# Step 8 - FinDesk Product Name - 2026-05-20

## Goal

Replace the experimental Captain Fin name with a serious product name while keeping the current working logic stable.

## Decision

Product name: **FinDesk**.

Layer structure remains:

```text
On the Go = fast minimal employee mode
FinDesk   = middle manager/report layer
Advanced  = organizer/admin mode
```

## Implemented

- Updated live-facing app copy, manifest metadata, landing page, login email copy and group invite sharing copy.
- Updated all multilingual shell dictionaries to use FinDesk across RU / EN / DE / IT / ES / SR-MNE-HR / Mandarin.
- Updated visible app initials from `CF` to `FD`.
- Cache versions were bumped to `20260520-09`.
- Service worker cache name was moved to `findesk-20260520-v10`.

## Compatibility Notes

- Internal module identifiers such as `captain`, `moduleCaptain` and `qlLoadCaptainFin` are intentionally kept for now.
- The language storage key now uses `finDeskLanguage`, with fallback to the old `captainFinLanguage` key so existing users keep their language choice.
- The app emits both `findesk:languagechange` and the legacy `captainfin:languagechange` event during this transition.

## Verification Scope

- PHP lint for changed PHP entry points.
- JavaScript syntax check for `public/assets/i18n.js` and `public/assets/app.js`.
- Local smoke test against `http://127.0.0.1:18888`.
- Live deployment check should confirm that `/app.php` and `/manifest.webmanifest` show FinDesk and no visible Captain Fin copy.
