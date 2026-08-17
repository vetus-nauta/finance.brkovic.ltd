# SPRINT-81R - Atlas Category Correction and Closed-Month Decision Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas operational category write slice

## Director Opening

SPRINT-81R extends Atlas write parity with the category correction routes already used by the FinDesk v2 UI:

- `PATCH /api/entries/:entryId/category`
- `POST /api/entries/:entryId/category/closed-month-decision`

This sprint does not enable production cutover and does not implement month close/reopen/correction, report creation/snapshot/package writes, imports, attachments, dictionary-training writes, internet-reference writes, flow creation, or FTP deployment.

## Agent Reports

### QA, Audit, and Acceptance Inspector - Lovelace

Read-only inspection accepted the slice with these required invariants:

- `PATCH /api/entries/:entryId/category` must be category-only and must not mutate `flow_id`, `date`, `raw_text`, `sign`, `amount`, `direction`, `entry_type`, `balance_after`, `created_seq`, or source fields.
- Category-only correction must not move cash/card balances.
- Closed-month category patch without explicit decision must return `409 closed_month_requires_decision`.
- A closed-month decision must not bypass accountable projection immutability.
- Mutating an entry must mark affected report fragments/packages as `requires_update`.
- Claudia Z finance snapshot must remain unchanged after smoke cleanup.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added Atlas allowlist and dispatch for category correction routes.
  - Added `updateEntryCategory()` wrapper for narrow category-only updates.
  - Category changes to a real category resolve `other_review` to `recognized`; category `other` keeps the row in review.
  - Added `decideClosedMonthEntryCategory()`.
  - `create_correction` records the explicit decision in audit and leaves the closed original entry unchanged.
  - `recalculate_chain` applies the same narrow category correction with explicit closed-month confirmation.
  - Accountable projection entries remain immutable through category routes.
- `scripts/v2_atlas_write_smoke.js`
  - Added category-only correction smoke.
  - Added closed-month category guard smoke.
  - Added `create_correction` no-mutation smoke.
  - Added `recalculate_chain` category mutation smoke.
  - Added projection immutability smoke for `/category`.
  - Added monthly-closure cleanup for temporary test workspaces.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `22/45` to `24/45`.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run check:atlas`

Expected blocked:

- `npm run gate:v2:atlas-cutover:strict` exited with code `2`

Write-smoke evidence:

- supported write routes: `24`
- temporary workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- category correction changed category without changing amount/balance.
- closed month category patch without decision returned `closed_month_requires_decision`.
- `create_correction` recorded the request and left the original category unchanged.
- `recalculate_chain` updated the category without changing amount/balance.
- projection category route rejected with `accountable_projection_entry_immutable`.
- Claudia Z finance snapshot unchanged:
  - `v2_entries`: `1638`
  - `v2_flows`: `52`
  - `v2_monthly_closures`: `4`
  - `v2_report_batches`: `8`
  - `v2_report_snapshots`: `0`
  - `v2_import_sources`: `60`
  - `v2_import_rows`: `3507`
  - `v2_dictionary_training_decisions`: `111`
  - `v2_internet_reference_lookups`: `0`
  - Claudia Z cash now: `3893.00`
  - August ending cash: `3893.00`

Gate result:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `36`
- Atlas write supported: `24`
- unsupported reads: `0`
- unsupported writes: `21`
- cutover allowed: `false`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-81R is accepted locally.

The Atlas sidecar now supports the UI category-correction path and the explicit closed-month category decision path without changing financial formulas, parser behavior, report logic, or Claudia Z data.

Next safe sprint:

- SPRINT-82R - Month close/reopen/correction write slice.
