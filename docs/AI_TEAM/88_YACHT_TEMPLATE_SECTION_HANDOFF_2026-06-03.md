# Yacht Template Section Handoff - 2026-06-03

## Scope

This handoff covers only the Yacht template section.

Yacht is a FinDesk template, not the main FinDesk product and not a separate accounting engine.

Current product rule:

- FinDesk start page must stay general: solo work, team work, ready templates.
- Yacht is opened from ready templates.
- Bunkering is inside Yacht only.
- Bunkering must not appear as a primary FinDesk start button.

## Current Status

Status: local implementation package exists; production deployment not performed in this handoff.

Current local frontend asset/cache version:

```text
20260603-yacht-bunkering-inside1
```

Local start page:

```text
http://127.0.0.1:18889/app.php
```

Yacht entry path:

```text
Welcome Hall -> Готовые шаблоны -> Yacht
```

Top menu path:

```text
Меню -> Шаблоны -> Yacht
```

## What Was Built

### 1. Yacht Template MVP

Implemented:

- Product screen `Yacht Template`;
- Yacht profile form;
- quiet `Vetus Nauta` fallback when yacht logo is empty;
- captain/crew language layer;
- crew role presets:
  - captain;
  - first mate;
  - sailor;
  - stewardess;
  - cook;
  - mechanic;
  - custom title;
- normal FinDesk workspace creation from Yacht:
  - group name becomes `Yacht: <name>`;
  - the financial engine remains FinDesk, not Yacht-specific.

Yacht profile fields:

- yacht name;
- marina;
- berth;
- customer contact;
- registration number;
- model;
- hull number;
- year;
- length;
- beam;
- logo URL;
- engines;
- generators;
- watermaker;
- windlass;
- passerelle;
- custom fields.

Main code area:

```text
public/assets/app.js
public/assets/app.css
public/app.php
public/service-worker.js
```

Local state key:

```text
findesk_yacht_template_v1
```

### 2. Bunkering / Starter Package

Implemented:

- separate Yacht work-order calculator;
- internal Yacht button `Бункеровка`;
- internal button scrolls to `Бункеровка / стартовый пакет`;
- starter package table with:
  - enabled checkbox;
  - section;
  - item;
  - quantity;
  - unit;
  - price;
  - row total;
  - package total;
- work-order modes:
  - all;
  - food;
  - fuel;
  - technical;
- quick row buttons:
  - add food;
  - add fuel;
  - add technical;
- price visibility toggle for print;
- print mode prints the yacht work-order area only.

Important correction from 2026-06-03:

- direct `Бункеровка` was removed from the general FinDesk start page;
- direct `Бункеровка` was removed from the top menu;
- it stays only inside Yacht.

Report:

```text
docs/AI_TEAM/87_YACHT_BUNKERING_SCOPE_CORRECTION_LOCAL_2026-06-03.md
```

### 3. Price Zones And Price Engine

Implemented:

- local Yacht `Price Engine v1`;
- regions:
  - Europe baseline;
  - Adriatic / Balkans;
  - Western Mediterranean;
  - USA coastal states;
  - Asia marina hubs;
  - Caribbean islands;
- visible mode:
  - full price;
  - duty free;
- hidden mechanics:
  - source values;
  - averaging;
  - tax;
  - logistics;
  - markup;
  - duty-free discount;
- failed/unavailable source entries are ignored;
- user sees only the final visible price;
- prices are applied only by explicit user action.

Guardrail:

- prices are planning hints for Yacht orders;
- they are not FinDesk accounting facts;
- they do not affect Live Journal, reports, archives, admin balances or employee balances.

### 4. OpenAI Price Refresh Foundation

Implemented:

- local OpenAI provider:
  - `app/openai_provider.php`;
- local key installer:
  - `scripts/install_openai_key.sh`;
- key file path:
  - `storage/secrets/openai_api_key`;
- Node AI worker:
  - `scripts/yacht_price_ai_refresh.cjs`;
- PHP worker kept for PHP CLI environments:
  - `scripts/yacht_price_ai_refresh.php`;
- snapshot review helper:
  - `scripts/yacht_price_snapshot_review.cjs`;
- candidate builder:
  - `scripts/yacht_price_candidate_from_snapshot.cjs`;
- approval gate:
  - `scripts/yacht_price_candidate_gate.cjs`.

Current rule:

```text
AI snapshot -> candidate -> approval gate -> approved local catalog -> read-only API -> manual apply in Yacht
```

AI must not directly publish or overwrite Yacht UI prices.

### 5. Approved Price Bridge

Implemented:

- backend approved catalog reader:
  - `app/yacht_prices.php`;
- API action:
  - `yacht_price_approved_catalog`;
- frontend panel:
  - load approved prices;
  - apply approved prices manually;
  - show warnings and blocked items.

Current approved local fuel catalog:

- region: `adriatic_balkans`;
- family: `fuel`;
- status: `approved_local`;
- active pointer stored under ignored `storage/`;
- approved items:
  - `marine_diesel_liter`;
  - `gasoline_liter`;
- blocked:
  - `duty_free_marine_diesel_liter`.

Important:

- `storage/` is local-only and ignored by Git;
- approved catalog storage is not committed;
- production needs its own controlled storage setup.

### 6. Yacht Provisioning API Package

Implemented:

- package data:
  - `app/data/yacht_provisioning/categories.json`;
  - `app/data/yacht_provisioning/filters.json`;
  - `app/data/yacht_provisioning/provision_catalog.json`;
  - schemas;
- backend:
  - `app/yacht_provisioning.php`;
- API action:
  - `yacht_provision_calculate`.

Status:

- deterministic backend package is present;
- frontend UI integration into Yacht template is still open.

## What Must Not Be Done

Do not:

- move Bunkering back to the FinDesk start page;
- make Yacht a separate finance engine;
- write Yacht planning prices into Live Journal automatically;
- mix Yacht starter-package estimates with real accounting reports;
- show net/tax/markup/source mechanics to normal users;
- let AI publish prices directly;
- silently recalculate printed or locked orders;
- treat estimated duty-free values as verified supplier quotes.

## Where We Are

The Yacht section is a useful local template layer, but it is not yet a finished production-grade Yacht operations module.

Strong pieces:

- Yacht profile and crew language exist;
- bunkering/starter package table exists;
- print-friendly order area exists;
- price engine structure is correct;
- OpenAI price cycle is controlled and gated;
- approved price bridge exists;
- provisioning package backend exists.

Open gaps:

- browser visual QA;
- real mobile QA;
- server-side durable Yacht order storage;
- archive/history of printed Yacht orders;
- supplier/source registry by region;
- direct duty-free quote source path;
- frontend UI for provisioning calculator;
- production storage setup for approved catalogs;
- production deployment and smoke.

## Where We Go Next

Recommended Yacht-only sprint sequence:

### Sprint Y1 - Visual / Print QA

Goal: verify that Yacht profile, bunkering table, price controls and print order are readable and usable on desktop and mobile.

Acceptance:

- Yacht opens only through templates;
- internal `Бункеровка` scroll works;
- print layout contains yacht header, order rows and total;
- hidden-price print mode works;
- no old FinDesk screen appears as the Yacht primary UI.

### Sprint Y2 - Durable Yacht Order Storage

Goal: stop relying only on local browser state for serious Yacht orders.

Required:

- server-side order draft;
- printed/locked order snapshot;
- read-only archived order view;
- no silent price recalculation after print.

### Sprint Y3 - Provisioning UI

Goal: connect `app/yacht_provisioning.php` to the Yacht screen.

Required:

- people/days/profile form;
- filters;
- calculated starter list;
- ability to move calculated items into the starter package table.

### Sprint Y4 - Source Registry

Goal: define approved truth sources per region and product family.

Required:

- fuel sources;
- food/provision sources;
- source freshness;
- confidence;
- failure mode;
- fallback to last approved catalog.

### Sprint Y5 - Duty-Free Control

Goal: make duty-free operationally honest.

Required:

- explicit supplier quote path;
- estimated duty-free values clearly marked internally;
- blocked values stay blocked until source confidence is enough.

### Sprint Y6 - Production Readiness

Goal: prepare controlled production release for Yacht section only.

Required:

- backup plan;
- production storage path for approved catalogs;
- API smoke;
- frontend smoke;
- mobile smoke;
- rollback note.

## Report Index

Read in order:

```text
docs/AI_TEAM/74_YACHT_TEMPLATE_MVP_LOCAL_2026-06-03.md
docs/AI_TEAM/75_YACHT_BUNKERING_ORDER_LOCAL_2026-06-03.md
docs/AI_TEAM/76_YACHT_PRICE_ZONES_LOCAL_2026-06-03.md
docs/AI_TEAM/77_YACHT_PRICE_ENGINE_LOCAL_2026-06-03.md
docs/AI_TEAM/78_OPENAI_YACHT_PRICE_REFRESH_LOCAL_2026-06-03.md
docs/AI_TEAM/79_YACHT_PROVISION_API_PACKAGE_APPLIED_2026-06-03.md
docs/AI_TEAM/80_OPENAI_KEY_TERMINAL_INSTALL_READY_2026-06-03.md
docs/AI_TEAM/81_OPENAI_KEY_SMOKE_PASS_2026-06-03.md
docs/AI_TEAM/82_YACHT_AI_PRICE_CYCLE_LOCAL_2026-06-03.md
docs/AI_TEAM/83_YACHT_FUEL_PRICE_CANDIDATE_LOCAL_2026-06-03.md
docs/AI_TEAM/84_YACHT_PRICE_APPROVAL_GATE_LOCAL_2026-06-03.md
docs/AI_TEAM/85_YACHT_APPROVED_PRICE_BRIDGE_LOCAL_2026-06-03.md
docs/AI_TEAM/87_YACHT_BUNKERING_SCOPE_CORRECTION_LOCAL_2026-06-03.md
```
