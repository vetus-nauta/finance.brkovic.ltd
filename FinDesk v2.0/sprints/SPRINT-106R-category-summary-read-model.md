# SPRINT-106R — Category Summary Read Model

Date: 2026-08-20

## Objective

Make the reports screen read real materialized ledger categories, so Smith's decision becomes visible in product summaries.

The operational journal remains the source of truth. The summary is a read model over `ledger_entries`; it does not create corrections, totals, or synthetic money.

## Director Roles

- Product Director: separates user-facing summary from technical classifier metadata.
- Financial Logic Inspector: verifies that category summary does not mutate ledger amounts, signs, dates, or row order.
- UX Inspector: keeps the summary compact and readable with numeric columns sized for data.
- QA Inspector: checks SQL, TypeScript, build, and whitespace safety.

## Implemented

Application:
- Added `WorkspaceCategorySummary` to `apps/web/src/lib/workspace-data.ts`.
- Reports screen now shows categorized totals grouped as:
  - operational categories;
  - accounting blocks;
  - money movements;
  - uncategorized rows.
- Accounting blocks and money movements are not mixed into ordinary category rows.
- Summary rows show category, flow type, amount, row count, and review count.

Database:
- Added backfill migration for older Smith-classified ledger rows where category code existed in metadata but `ledger_entries.category_id` was still empty.
- Migration only links category IDs and writes category metadata; it does not change money.

## Acceptance Gates

- `npm run check:foundation:sql`
- `npm run typecheck:web`
- `npm run build:web`
- `git diff --check`
- Supabase migration applied to remote project

## Remaining Work

1. Browser smoke: quick note -> Smith -> category correction -> transfer -> reports summary.
2. Add period filters for reports summary after the base read model is stable.
3. Add report export using the same read model, not a separate hand-built calculation.
