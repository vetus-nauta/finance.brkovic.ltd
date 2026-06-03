# Yacht Price Zones - 2026-06-03

Status: local implementation complete.

Asset version: `20260603-yacht-price-zones1`.

## Product Decision

The yacht price catalog is a reference layer for draft work orders only.

It is not a source of accounting truth and does not update FinDesk journals, reports, archive, or balances.

## Implemented Locally

- Expanded the reference catalog into price zones:
  - `Европа, базовая зона`;
  - `Адриатика / Балканы`;
  - `Средиземноморье запад`;
  - `США / coastal states`;
  - `Азия / marina hubs`;
  - `Карибы / острова`.
- Added catalog version:
  - `2026-06-03-local-zones1`.
- Added manual button:
  - `Обновить справочник`.
- Manual refresh updates local catalog version/date metadata.
- Manual refresh does not overwrite work-order row prices.
- Applying selected-zone prices remains a separate explicit action:
  - `Подставить цены региона`.

## Why No External Auto Prices Yet

External price data must come from a controlled server-side source or approved provider.

Frontend scraping or hardcoded "current" prices would create false precision. For now, the catalog is a local planning aid.

## QA

Passed:

- `node --check public/assets/app.js`;
- `node --check public/service-worker.js`;
- `git diff --check`;
- local `app.php` returns `200 OK`;
- asset version visible in local HTML.

Not run:

- real external price provider integration;
- browser visual QA;
- production deployment.
