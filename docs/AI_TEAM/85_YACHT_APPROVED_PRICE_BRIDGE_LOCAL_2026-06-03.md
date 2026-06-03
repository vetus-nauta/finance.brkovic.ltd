# Yacht Approved Price Bridge - 2026-06-03

Status: local read-only API and frontend bridge implemented.

Asset version:

- `20260603-yacht-approved-bridge1`

## Purpose

Approved Yacht price catalogs must be usable by the Yacht order screen, but not silently published or applied.

The bridge follows the approved flow:

```text
AI snapshot
  -> candidate
  -> approval gate
  -> active approved storage catalog
  -> read-only API
  -> manual apply in Yacht draft
```

No archived or printed order is recalculated.

## Backend

Added:

- `app/yacht_prices.php`

API action:

```text
POST /api.php?action=yacht_price_approved_catalog
```

Request:

```json
{
  "region": "adriatic_balkans",
  "family": "fuel"
}
```

Behavior:

- requires authenticated user;
- reads only:
  - `storage/yacht-price-approved/active-<region>-<family>.json`;
- does not write;
- returns approved prices, warnings, policy, source pointers and blocked items.

Unauthorized smoke:

- returns `401 not_authenticated`.

Authenticated smoke:

- `ok: true`;
- status: `approved_local`;
- region: `adriatic_balkans`;
- family: `fuel`;
- prices:
  - `marine_diesel_liter`;
  - `gasoline_liter`;
- blocked:
  - `duty_free_marine_diesel_liter`;
- warnings: `3`.

## Frontend

Changed:

- `public/assets/app.js`;
- `public/assets/app.css`;
- `public/app.php`;
- `public/service-worker.js`.

Yacht screen now has:

- approved price status panel;
- `Загрузить approved`;
- `Подставить approved`;
- visible reviewed date/source state;
- warning/blocked summary;
- price lock message after print.

Approved prices are not applied automatically.

Current item mapping:

| Yacht row text | Approved price key |
|---|---|
| `Дизель` / diesel | `marine_diesel_liter` |
| `Бензин` / gasoline / petrol | `gasoline_liter` |
| duty-free diesel wording | `duty_free_marine_diesel_liter` |

Blocked approved items are not applied.

## Price Lock

When the Yacht order is printed:

- `price_locked_at` is set;
- `price_snapshot` is stored in local Yacht state;
- automatic reference price apply is blocked;
- approved price apply is blocked.

To work with fresh prices after printing:

- use `Новая копия с новыми ценами`;
- or reset to `Базовый пакет`.

This protects already printed order numbers from silent catalog refreshes.

## Verification

Passed:

- `node --check public/assets/app.js`;
- `node --check public/service-worker.js`;
- `git diff --check`;
- local app asset version visible in HTML;
- unauthorized API returns `401`;
- authenticated API returns approved catalog;
- old asset marker `yacht-price-engine1` no longer found in app/service worker.

Not run:

- browser visual QA;
- real mobile QA;
- production deployment.

## Next

- Browser QA of Yacht approved panel and buttons.
- Add a server-side immutable order document store if Yacht orders must be archived beyond local state.
- Add explicit supplier quote path for duty-free fuel.
