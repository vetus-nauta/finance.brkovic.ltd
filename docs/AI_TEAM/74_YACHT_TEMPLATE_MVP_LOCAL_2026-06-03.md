# Yacht Template MVP - 2026-06-03

Status: local implementation complete.

Asset version: `20260603-yacht-template1`.

## Product Decision

Yacht is a template layer over FinDesk, not a separate finance engine.

It does not change:

- Live Journal input;
- Cash/Card separation;
- report assembly;
- archive logic;
- core FinDesk arithmetic.

It adds:

- yacht naming and profile;
- captain/crew language;
- crew role presets;
- yacht header for future work orders;
- separate bunkering / starter package calculator.

## Implemented Locally

- Added Product screen `Yacht Template`.
- `Templates -> Yacht` now opens the Yacht screen.
- Menu includes `Templates -> Yacht`.
- Yacht profile fields:
  - yacht name;
  - marina;
  - berth;
  - customer contact;
  - registration number;
  - model;
  - hull number;
  - length;
  - beam;
  - year;
  - logo URL;
  - engines;
  - generators;
  - watermaker;
  - windlass;
  - passerelle;
  - custom fields.
- If yacht logo is empty, the header shows quiet fallback `Vetus Nauta`.
- Crew role presets:
  - Captain;
  - First mate;
  - Sailor;
  - Stewardess;
  - Cook;
  - Mechanic;
  - Custom title.
- Yacht workspace creation button creates a normal FinDesk group named `Yacht: <name>`.
- Added separate `Bunkering / starter package` section.
- Starter package rows support:
  - enabled checkbox;
  - section;
  - item;
  - quantity;
  - unit;
  - approximate price;
  - row total;
  - package total.
- Prices can be hidden before printing.
- Print mode prints only the yacht work order area.

## Deferred

- Regional dynamic price library.
- Learning filters over historical text entries.
- Service master work orders from technical yacht profile fields.
- Backend persistence for yacht profile across devices.

## QA

Passed:

- `node --check public/assets/app.js`;
- `node --check public/service-worker.js`;
- `git diff --check`;
- local `app.php` returns `200 OK`;
- asset version visible in local HTML.

Not run:

- real-device mobile QA;
- browser visual print QA;
- production deployment.
