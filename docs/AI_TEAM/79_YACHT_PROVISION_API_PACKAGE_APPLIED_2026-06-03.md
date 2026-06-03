# Yacht Provision API Package Applied - 2026-06-03

Status: local implementation complete.

Source package:

- `https://drive.google.com/file/d/15f78Qt6NNA8nuh4u3I_T6Y9aoo4yncz4/view?usp=sharing`

Downloaded file name:

- `yacht_provision_api_full_package.zip`

## Director Decision

The package defines a deterministic provisioning calculator, not an AI feature and not a pricing feature.

OpenAI must not calculate quantities directly for users. The provisioning list is calculated by local rules:

```text
quantity = base_quantity + per_person_per_day * people_count * days
```

Then the system applies:

- profile multiplier;
- meal plan multiplier;
- filters;
- rounding;
- restock warnings.

OpenAI can later help refresh price/source intelligence in the background, but it must not replace this deterministic quantity engine.

## Package Content Applied

Copied into the project:

- `app/data/yacht_provisioning/categories.json`;
- `app/data/yacht_provisioning/filters.json`;
- `app/data/yacht_provisioning/provision_catalog.json`;
- `app/data/yacht_provisioning/request.schema.json`;
- `app/data/yacht_provisioning/response.schema.json`.

Catalog summary:

- 15 categories;
- 133 provisioning items;
- profiles: `light`, `balanced`, `onboard_full`, `charter_comfort`;
- meal plans: `breakfast_only`, `breakfast_lunch`, `breakfast_onboard_lunch_light_dinner_mixed`, `full_onboard`;
- rounding types: `ceil_integer`, `ceil_kg_0_5`, `ceil_liter`, `ceil_pack_4`, `ceil_pack_6`, `ceil_pack_10`, `ceil_pack_24`, `manual`.

## Implemented Locally

Added:

- `app/yacht_provisioning.php`;
- API action in `public/api.php`:
  - `yacht_provision_calculate`.

Current endpoint in this app architecture:

```text
POST /api.php?action=yacht_provision_calculate
```

The package spec names:

```text
POST /api/provisioning/calculate
```

The app currently uses action-based `api.php`, so the package endpoint is mapped into the existing API architecture instead of adding a new routing layer.

## Request

Example:

```json
{
  "people_count": 8,
  "days": 7,
  "profile": "balanced",
  "meal_plan": "breakfast_onboard_lunch_light_dinner_mixed",
  "filters": {
    "include_alcohol": false,
    "include_bbq": true,
    "include_children": false,
    "include_household": true,
    "include_hygiene": true,
    "route_restock_possible": true
  },
  "language": "ru"
}
```

## Response Behavior

Returns:

- `ok`;
- `meta`;
- `warnings`;
- `summary`;
- grouped `categories`;
- item-level:
  - `item_key`;
  - `title`;
  - `quantity`;
  - `unit`;
  - `unit_label`;
  - `display_quantity`;
  - `note`;
  - `priority`;
  - `perishable`;
  - `optional`;
  - `route_restock_recommended`;
  - `filters`.

## Filters Implemented

- include categories;
- exclude categories;
- household on/off;
- hygiene on/off;
- alcohol on/off;
- BBQ on/off;
- children on/off;
- dietary:
  - `no_pork`;
  - `no_seafood`;
- storage value validation;
- route restock possible;
- perishable only;
- long-storage only.

## Calculation Notes

- Quantity rules are deterministic.
- Manual rounding returns a manual display value instead of a fake zero.
- Pack rounding keeps the raw unit key but displays human text, for example:
  - `132 бут. 1.5 л (22 уп. x 6)`.
- Water summary uses package rule:
  - people x days x water liters per person plus safety reserve.
- Large groups receive a fridge/restock warning.
- Perishable route-restock items receive a route warning when restock is possible.

## OpenAI Alignment

For the next OpenAI key task:

- key must stay server-side only;
- no direct UI button should trigger OpenAI;
- OpenAI worker can refresh food/fuel price snapshots;
- quantity calculation stays local and deterministic;
- price publication must remain a controlled step;
- no archived/printed order can be changed by AI refresh.

## Local Verification

Passed:

- local HTTP calculation for 8 people / 7 days:
  - `ok: true`;
  - 13 categories;
  - 125 items;
  - estimated water: 212 liters.
- invalid `people_count: 0` returns:
  - `INVALID_PEOPLE_COUNT`.
- local HTTP calculation for 30 people / 14 days:
  - `ok: true`;
  - 15 categories;
  - 133 items;
  - large-group warnings present.
- `git diff --check` passed for provisioning files.

Not run:

- `php -l`, because PHP CLI is not available in the current shell;
- authenticated browser UI integration;
- production deployment.
