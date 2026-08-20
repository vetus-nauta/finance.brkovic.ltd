# SPRINT-105R — Smith Category Materialization

Date: 2026-08-20

## Objective

Make Smith's category decision usable by the product, not just visible in the review panel.

When a user checks a quick note with Smith, the user can now correct the proposed category before transfer. On transfer, that category is written into the ledger entry as structured data.

## Director Rule

Operational journal remains the source of truth.

Smith does not write money until the user explicitly selects rows and transfers them.

Category selection does not change sign, amount, account, date, or row number.

## Implemented

Database:
- Added `private.ensure_workspace_default_categories(workspace_id)`.
- Seeded default workspace categories for active workspaces.
- Updated `public.convert_smith_entry_proposals(...)` so converted entries receive:
  - `ledger_entries.category_id` when category direction is compatible;
  - metadata `category_code`;
  - metadata `category_source=smith_review`;
  - metadata `category_materialized`.

Application:
- Added shared `apps/web/src/lib/smith-categories.ts`.
- Smith review rows now have a category selector.
- Selected category is saved into the pending proposal before conversion.
- Possible duplicate rows remain unchecked by default.

Dictionary:
- `cash_topup_from_card` seed direction is now `neutral` for Supabase compatibility.
- Added `admin_debt` and `lower_accounting` as neutral accounting/reporting blocks.

## Acceptance Evidence

Local checks:
- `npm run check:foundation:sql` — PASS.
- `FinDesk v2.0/schemas/categories.seed.json` parse — PASS.
- `npm run typecheck:web` — PASS.

Supabase:
- Migration `smith_category_materialization` applied.
- Claudia Z category seed readback: `24` active categories.

## Remaining Work

1. Build summary/report queries from `ledger_entries.category_id`.
2. Exclude accounting-block categories from normal operational category totals.
3. Add browser smoke for notes: draft -> Smith -> category correction -> transfer -> ledger category readback.
