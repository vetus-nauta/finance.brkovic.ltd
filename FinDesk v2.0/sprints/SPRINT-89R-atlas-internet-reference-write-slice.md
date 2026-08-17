# SPRINT-89R - Atlas Internet Reference Write Slice

Date: 2026-08-13

## Director Opening

Goal: close the Mr. Smith dictionary internet-reference write gap in the Atlas runtime without enabling uncontrolled internet lookup or mutating finance/training data.

Director discipline:
- Source of truth: repository files, not prior chat memory.
- Production cutover remains blocked.
- No FTP deploy was performed.
- No financial formulas, parser behavior, report math, or import Excel behavior were changed.

## Agent Assignment

QA/Audit Inspector: Planck

Scope:
- Read PHP/MySQL contract for:
  - `POST /api/workspaces/:workspaceId/dictionary-training-internet-reference`
  - `PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId`
- Confirm payloads, envelopes, validations, persistence side effects, and negative cases.

Inspector conclusion:
- POST creates exactly one `v2_internet_reference_lookups` provenance row.
- PATCH updates only `selected_match_json`.
- No finance, training, import, or report tables mutate.
- PHP has no audit-log write for these routes; Atlas parity must not add synthetic audit.

## Implemented

Files:
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_write_smoke.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Atlas routes added:
- `POST /api/workspaces/:workspaceId/dictionary-training-internet-reference`
- `PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId`

Behavior:
- Requires workspace writer.
- Honors assistant settings:
  - `disabled` blocks lookup.
  - `per_request` requires explicit consent.
  - `workspace_enabled` allows workspace-level consent only when Mr. Smith is enabled.
- Rejects unsafe payload keys: raw text, raw row, amounts, balances, reports, entries, rows.
- Sanitizes lookup query by removing amounts/numbers/dates before persistence.
- Uses current safe stub provider only.
- Stores lookup provenance with workspace-scoped query hash, masked fields, retention date, and `no_financial_mutation`.
- Feedback accepts only `useful`, `unclear`, `not_useful`.
- Feedback records selected match and explicitly marks no finance/training mutation.
- Cross-workspace lookup/source-row access is blocked.

## Acceptance Evidence

Passed:

```bash
node --check server/findesk-v2-atlas-read-server.js
node --check scripts/v2_atlas_write_smoke.js
node --check scripts/v2_atlas_runtime_smoke.js
node --check scripts/v2_atlas_cutover_gate.js
git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_write_smoke.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js
npm run smoke:v2:atlas-write
npm run smoke:v2:atlas-runtime
npm run gate:v2:atlas-cutover
npm run check:atlas
```

Strict gate expected blocked:

```bash
npm run gate:v2:atlas-cutover:strict
```

Result: exit code `2`, expected while cutover is not complete.

## Gate State

Route surface:
- Total routes: 81
- Reads: 36
- Writes: 45
- Atlas read supported: 36
- Atlas write supported: 41
- Unsupported reads: 0
- Unsupported writes: 4

Remaining unsupported writes:
- `POST /api/workspaces/:workspaceId/imports/excel`
- `POST /api/workspaces/:workspaceId/imports/:importId/accept`
- `POST /api/entries/:entryId/attachments`
- `DELETE /api/attachments/:attachmentId`

Current blockers:
- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Snapshot Integrity

Write smoke restored the disposable fixture.

Finance snapshot after smoke:
- `v2_entries`: 1638
- `v2_flows`: 52
- `v2_import_sources`: 60
- `v2_import_rows`: 3507
- `v2_category_rules`: 85
- `v2_dictionary_training_decisions`: 111
- `v2_internet_reference_lookups`: 0
- Claudia Z cash now: `3893.00`
- Claudia Z August ending cash: `3893.00`

## Next Sprint

SPRINT-90R should close Excel import write parity:
- `POST /api/workspaces/:workspaceId/imports/excel`
- `POST /api/workspaces/:workspaceId/imports/:importId/accept`

Attachments should remain a separate final slice because they require file storage, URL/path security, cleanup, and rollback handling.
