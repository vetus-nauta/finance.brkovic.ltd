# Step 6 - Captain Fin Brand, Modes and i18n Foundation - 2026-05-20

## Goal

Replace the temporary Quick Ledger product surface with Captain Fin and make the three product layers explicit:

- On the Go: minimal field/pocket mode.
- Captain Fin: middle manager/report layer.
- Advanced: organizer/admin layer.

## Implemented

- Product name changed to Captain Fin in app metadata, manifest, install copy, auth email copy and share-invite copy.
- Added the Captain Fin middle module between On the Go and Advanced.
- Added a three-card mode ladder for On the Go / Captain Fin / Advanced.
- Added a multilingual UI foundation in `public/assets/i18n.js`.
- Supported language groups:
  - Russian
  - English
  - German
  - Italian
  - Spanish
  - Serbian / Montenegrin / Croatian
  - Mandarin Chinese
- Language is detected from the browser/system language.
- User can override language manually from the top language strip or Settings.
- Added the reminder that if the app language does not match the system language, the user should choose it manually.
- Strengthened the light visual system with separate teal, blue, gold and coral accents.

## Scope Boundary

This step localizes the shell, auth surface, primary navigation, mode structure, Captain Fin module, Advanced/Premium headings and Settings.

The old dynamic forms still contain mixed legacy English/Russian strings in places. They should be migrated gradually to the new i18n dictionary as each module is polished.

## Verification

- PHP lint passed for changed PHP files.
- `node --check` passed for `public/assets/i18n.js` and `public/assets/app.js`.
- Local smoke passed against `http://127.0.0.1:18888`.
- Headless Chrome rendered the mobile login screen and applied Russian auto-detection.

