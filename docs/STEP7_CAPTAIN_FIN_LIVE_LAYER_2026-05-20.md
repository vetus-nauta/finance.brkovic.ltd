# Step 7 - Captain Fin Live Middle Layer - 2026-05-20

## Goal

Turn the Captain Fin middle layer from a static placeholder into a working bridge between On the Go and Advanced.

## Implemented

- Captain Fin now loads the current On the Go report summary:
  - given
  - spent
  - left
  - records count
- Captain Fin now loads active group accountable-money records:
  - submitted
  - discrepancy
  - returned
  - issued
- Accepted and closed records are hidden from the middle-layer review list.
- The panel reuses existing APIs:
  - `on_the_go_tape_list`
  - `group_list`
  - `advance_list`
- No database changes were needed.
- The module refreshes when opened and when the app language changes.
- Cache versions were bumped to `20260520-08`.

## Product Meaning

On the Go remains the minimal field input.

Captain Fin is now the manager layer:

- see what is currently being prepared;
- see submitted or pending accountable records;
- move into groups/reports without mixing the UI with Advanced controls.

Advanced remains the organizer/admin layer for issuing and moderating money.

## Verification

- PHP lint passed for `public/app.php`.
- `node --check` passed for `public/assets/i18n.js` and `public/assets/app.js`.
- Local smoke passed against `http://127.0.0.1:18888`.

