# Yacht Bunkering Order - 2026-06-03

Status: local implementation complete.

Asset version: `20260603-yacht-bunkering1`.

## Product Decision

`Bunkering / starter package` is a separate yacht work-order calculator.

It does not write to:

- Live Journal;
- FinDesk reports;
- archive accounting;
- team cash/card balances.

This keeps optimal Live Journal input untouched.

## Implemented Locally

- Added work-order modes:
  - `Все`;
  - `Еда`;
  - `Топливо`;
  - `Техника`.
- Mode tabs filter visible rows without deleting hidden rows.
- Package total always counts the whole enabled starter package.
- Section total shows the currently selected mode.
- Added local reference price selector:
  - `Адриатика / Черногория, пример`;
  - `Средиземноморье, пример`.
- Reference prices are disabled by default.
- User must enable `Использовать примерные цены` before applying the preset.
- Applied prices remain editable manually.
- Added quick row buttons:
  - add food row;
  - add fuel row;
  - add technical row.
- Fuel row uses quantity in liters and price per liter, then calculates total.

## Guardrail

Prices are approximate local hints for draft orders only.

They are not financial facts and are not included in FinDesk accounting unless the user later records a real money movement in Live Journal.

## QA

Passed:

- `node --check public/assets/app.js`;
- `node --check public/service-worker.js`;
- `git diff --check`;
- local `app.php` returns `200 OK`;
- asset version visible in local HTML.

Not run:

- browser visual print QA;
- real-device mobile QA;
- production deployment.
