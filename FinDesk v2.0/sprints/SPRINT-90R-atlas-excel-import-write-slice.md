# SPRINT-90R - Atlas Excel Import Write Slice

Date: 2026-08-13

## Director Opening

Goal: close Atlas write parity for legacy Excel import upload and accept while preserving the existing PHP/MySQL contract.

Director discipline:
- Source of truth: repository files.
- Production cutover remains blocked.
- No FTP deploy was performed.
- No financial formulas or report semantics were changed.

## Agent Assignment

Backend/QA Inspector: Lorentz

Scope:
- Read PHP/MySQL contract for:
  - `POST /api/workspaces/:workspaceId/imports/excel`
  - `POST /api/workspaces/:workspaceId/imports/:importId/accept`
- Confirm payloads, envelopes, parser rules, audits, file side effects, negative cases, and smoke expectations.

Inspector conclusion:
- Upload stores import source/rows and returns review; it must not create entries.
- Excluded title-marker files create only an excluded source.
- Accept creates entries from parsed rows, including duplicate rows as `duplicate_suspect`, updates import rows, accepts source, writes import audit and per-entry audits.
- Original XLSX bytes are not persisted.

## Implemented

Files:
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_write_smoke.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Atlas routes added:
- `POST /api/workspaces/:workspaceId/imports/excel`
- `POST /api/workspaces/:workspaceId/imports/:importId/accept`

Behavior:
- Requires workspace writer.
- Upload validates `.xlsx` file name and strict base64.
- Parses XLSX as zip/XML using the current runtime environment, without adding npm dependencies.
- Stores `v2_import_sources` and `v2_import_rows`.
- Keeps original XLSX bytes out of storage, matching PHP.
- Applies title-marker exclusion for drafts/test/incomplete files.
- Preserves date precedence: row date, inherited row date, filename date, file updated date.
- Accept creates source-linked entries, updates row statuses, recalculates cash balance through the existing entry creation path, and writes import/entry audit rows.
- Duplicate rows are created as `duplicate_suspect` entries but excluded from normalized import totals.

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
- Atlas write supported: 43
- Unsupported reads: 0
- Unsupported writes: 2

Remaining unsupported writes:
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

SPRINT-91R should close attachment write parity:
- `POST /api/entries/:entryId/attachments`
- `DELETE /api/attachments/:attachmentId`

This must include file path safety, MIME allowlist parity, cleanup on failed DB writes, delete rollback behavior, and storage backup/cutover notes.
