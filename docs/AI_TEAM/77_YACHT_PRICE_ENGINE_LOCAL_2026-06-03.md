# Yacht Price Engine - 2026-06-03

Status: local implementation complete.

Asset version: `20260603-yacht-price-engine1`.

## Product Decision

Yacht provisioning prices are a planning layer for starter packages, store orders and bunkering drafts.

They are not accounting truth and do not affect FinDesk Live Journal, reports, archives, employee balances or admin balances.

The user sees only the final visible price. Net price, source averaging, tax, logistics, markup and duty-free discount stay behind the interface.

## Implemented Locally

- Replaced flat regional price presets with local `Price Engine v1`.
- Each region now has:
  - hidden source net values;
  - tax rate;
  - logistics rate;
  - markup rate;
  - duty-free discount policy for food and fuel.
- Added `Полная цена / Duty free` switch to Yacht order pricing.
- Food and fuel rows can receive duty-free discount when the switch is active.
- Non-food and non-fuel rows remain on full-price logic.
- Engine averages available source values for the row item.
- Failed/unavailable source entries are ignored in the averaging model.
- The final rounded price is written into the row only after explicit action:
  - `Подставить цены региона`.
- Manual catalog refresh still updates local catalog metadata only and does not overwrite order rows.
- Yacht crew roles are preserved when the yacht form is synchronized.

## Current Local Regions

- Europe baseline;
- Adriatic / Balkans;
- Western Mediterranean;
- USA coastal states;
- Asia marina hubs;
- Caribbean islands.

## Current Limits

The current source values are local planning fixtures, not verified external market prices.

Real prices require a backend source registry, scheduled updates and approved source contracts. Browser-side scraping or hardcoded "current" prices would create false precision and should not be used as production truth.

## Sprint Plan To 100 Percent Readiness

### Sprint 1 - Local Engine Foundation

Status: complete.

Goal: make the pricing logic structurally correct inside the Yacht template.

Result:

- final visible price only;
- hidden net/tax/logistics/markup model;
- full-price and duty-free modes;
- source averaging model;
- no connection to FinDesk accounting.

### Sprint 2 - Source Registry

Status: open.

Goal: define approved truth sources for each region and product family.

Required:

- source type: supplier feed, marina fuel quote, distributor price list, manual verified entry;
- product mapping for food, fuel and technical supplies;
- currency and unit normalization;
- source freshness date;
- source confidence score;
- failure state.

### Sprint 3 - Backend Catalog Storage

Status: open.

Goal: move price snapshots from frontend fixtures to server-side storage.

Required:

- catalog tables or JSON snapshot store;
- immutable historical snapshots for printed orders;
- current catalog endpoint;
- manual refresh endpoint;
- audit log for refresh runs and source failures.

### Sprint 4 - Scheduled Refresh

Status: open.

Goal: refresh prices automatically every one or two months without user action.

Required:

- cron or scheduled worker;
- per-region refresh cadence;
- source timeout handling;
- fallback to last good snapshot;
- no silent overwrite of already printed or archived orders.

### Sprint 5 - Outlier Control

Status: open.

Goal: average multiple sources safely.

Required:

- ignore failed sources;
- reject extreme outliers;
- show internal confidence level to admin only if needed;
- keep user-facing screen simple;
- log why a value was accepted or ignored.

### Sprint 6 - Duty-Free Policy

Status: open.

Goal: make duty-free legally and operationally controlled.

Required:

- region-level default discount;
- food and fuel separate rules;
- manual override by authorized role;
- visible order mode: full price or duty free;
- no tax/markup mechanics shown on customer-facing print if prices are hidden.

### Sprint 7 - QA And Print Readiness

Status: open.

Goal: make orders safe to print and archive.

Required:

- price snapshot stays fixed after print/archive;
- order total matches row totals;
- hidden-price print mode works;
- full-price and duty-free QA scenarios pass;
- source failure scenario passes;
- old Yacht orders remain readable.

## QA Checklist

Passed locally:

- `Price Engine v1` is present in `public/assets/app.js`;
- old flat preset references are removed;
- `Полная цена / Duty free` selector is rendered in Yacht order settings;
- source averaging function ignores unavailable source entries;
- Yacht crew roles are not dropped during form sync;
- asset version advanced to `20260603-yacht-price-engine1`.

Pending:

- browser visual QA;
- real provider research;
- backend storage;
- scheduled refresh;
- production deployment.
