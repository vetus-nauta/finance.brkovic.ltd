# 20 — Definition of Done

## Product done means

FinDesk v2.0 Clean Core MVP is done only when the core works correctly, not when the UI looks nice.

## Foundation done

- Clean schema applied.
- Workspace works.
- Cash/Card flows work.
- Entries can be created/read/updated.
- Categories seed loaded.
- Audit log exists for important changes.
- Old FinDesk business logic has not been reused.

## Logic done

- `+/-` parser works.
- No-sign rows are visible but not counted.
- Cash balance recalculates after insert/edit/delete.
- Card expense is counted separately.
- Card-to-cash approved model works.
- Commercial income is separate from opening balance and private top-up.
- Other expenses fallback works.
- Monthly summary is generated from entries.
- Closed month edit requires explicit correction/recalculation/cancel.

## Import done

- One legacy Excel file imports.
- Cash/card columns map correctly.
- Source file and row traceability exists.
- Excluded title markers work.
- Import report lists included/excluded/unrecognized rows.

## UX done

- Current month feed is always visible while entering.
- Input remains reachable.
- No body/page scroll.
- Desktop uses full monitor space.
- iPad 11+ uses full workspace layout.
- iPad mini uses mobile financial-notes system.
- Phone vertical scrolls notes.
- Phone horizontal swipe reveals structured rows.
- Detail panel/drawer does not hide critical context.

## QA done

- All fixtures from `15-test-fixtures.md` pass.
- Arithmetic tests pass.
- Parser tests pass.
- Responsive tests pass.
- Import test passes.
- No regression in Cash/Card model.

## Not done if

- UI is nice but arithmetic is uncertain.
- Dashboard exists but journal is not source of truth.
- Desktop looks like a stretched phone.
- Phone input disappears under keyboard.
- Other expenses are hidden.
- Commercial income is mixed with opening balance.
- Old FinDesk logic is silently reused.
