# SPRINT-108R — Merge Parts and Service Category

Date: 2026-08-20

## Objective

Reduce category noise by merging technical parts and service work into one user-facing maintenance category.

User-facing category:
- `tech_parts` — `Запчасти и сервис`

Legacy category:
- `service_water` is hidden and treated as an alias merged into `tech_parts`.

## Director Decision

This is a product simplification, not a financial formula change.

The user does not need to decide whether a row is a purchased part or service labor when the practical meaning is normal yacht maintenance. Dry dock and tender remain separate because they are operationally distinct.

## Implemented

Database:
- Updated `private.ensure_workspace_default_categories(...)` so new workspaces seed `tech_parts` as `Запчасти и сервис`.
- Existing `service_water` categories are marked inactive with `legacy_alias_to=tech_parts`.
- Existing ledger entries linked to `service_water` are relinked to `tech_parts`.
- Existing pending Smith proposals with `service_water` are moved to `tech_parts`.
- `public.classify_foundation_entry(...)` now returns `tech_parts` for service/maintenance wording.

Application:
- Removed `service_water` from the visible Smith category selector.
- Renamed `tech_parts` to `Запчасти и сервис`.

Dictionary:
- Updated seed and linguistic documentation to one visible maintenance category.

## Acceptance Evidence

Expected classifier behavior:
- `-67 покупка ремня генератора` -> `tech_parts`.
- `-100 обслуживание генератора` -> `tech_parts`.
- `-100 замена ремня генератора` -> `tech_parts`.

## Remaining Work

1. Browser smoke after the next notes UX pass.
2. Keep monitoring whether dry-dock and tender rows need clearer separation from general maintenance.
