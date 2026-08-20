# SPRINT-107R — Smith Purchase Guard and Notes Polish

Date: 2026-08-20

## Objective

Fix Smith's category reasoning when a row describes buying a technical item, not performing service work.

The product rule is strict:
- service work means work, maintenance, repair, installation, diagnostics, or a service provider action;
- purchase wording means an item was bought, so a technical object belongs to `tech_parts` unless the row is ambiguous.

## Implemented

Database:
- Added migration `smith_purchase_guard`.
- `public.classify_foundation_entry(...)` now classifies purchase verbs paired with technical objects as `tech_parts`.
- Service wording still classifies real maintenance/service rows as `service_water`.

Deleted test data:
- Removed the test operational entry `-67 покупка ремня генератора`.
- Marked its source quick note and Smith proposals as `void`.

Application:
- Reduced visible Smith technical text in the notes confirmation panel.
- Notes screen now presents a clearer current-note workspace.
- Smith confirmation reads as a transfer step, not as a permanent technical window.

Dictionary:
- Updated dictionary guidance so purchase verbs do not trigger service work.

## Acceptance Evidence

Supabase classifier readback:
- `-67 покупка ремня генератора` -> `tech_parts`.
- `-100 обслуживание генератора` -> `service_water`.
- `-100 замена ремня генератора` -> `service_water`.

## Remaining Work

1. Full notes UX pass toward the intended simple note-taking model.
2. Browser smoke on desktop and mobile after the next UI pass.
