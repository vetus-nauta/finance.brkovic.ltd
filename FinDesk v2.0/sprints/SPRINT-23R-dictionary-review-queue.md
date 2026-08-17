# SPRINT-23R — Dictionary Review Queue

## Director Opening

Build a read-only dictionary review queue for Claudia Z raw archive history.

The queue is for linguistic/category training only. It must not mutate operational accounting state.

## Assigned Agents

- Backend/API Agent — define authenticated GET route and repository read model.
- Frontend UX Agent — place the queue inside existing Layer 1 Summary style without creating a dashboard-first screen.
- QA, Audit, and Acceptance Agent — verify read-only behavior and regression safety.

## Implemented Scope

- Added `GET /api/workspaces/:workspaceId/dictionary-review-queue`.
- Added repository grouping over `v2_import_rows` joined through included `v2_import_sources`.
- If a selected workspace has a sibling named `Archive Raw History`, the queue reads that raw archive workspace.
- Added semantic grouping for owner funding, safe/cash location, debt/return, commercial income allowance, tender-related rows, category guesses, and needs-review rows.
- Added read-only UI block inside `Summary -> Information`.

## Non-Goals

- No category approval.
- No category rule creation.
- No operational entry creation from archive rows.
- No changes to parser, report arithmetic, deployment, or finance formulas.

## Acceptance

- `GET` returns queue groups and examples with source file/sheet/row provenance.
- `GET` does not change:
  - `v2_entries`
  - `v2_flows`
  - `v2_categories`
  - `v2_category_rules`
  - `v2_actors`
  - `v2_audit_log`
  - `v2_monthly_closures`
- Claudia Z current operational balance remains separate from raw archive history.
- Queue amounts are review metadata only and cannot be used as finance-report totals.

## Verification

Required:

```bash
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
```
