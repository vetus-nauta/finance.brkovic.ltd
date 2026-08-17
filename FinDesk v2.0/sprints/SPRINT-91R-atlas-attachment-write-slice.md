# SPRINT-91R - Atlas Attachment Write Slice

Date: 2026-08-13

## Director Opening

Goal: close the final Atlas write parity gap for entry attachments.

Director discipline:
- Source of truth: repository files.
- No production deploy or FTP cutover was performed.
- Financial formulas and operational report math were not changed.
- Attachment files are storage side effects and must be cleaned during smoke.

## Agent Assignment

QA/Backend Inspector: Darwin

Scope:
- Read PHP/MySQL contract for:
  - `POST /api/entries/:entryId/attachments`
  - `DELETE /api/attachments/:attachmentId`
- Confirm payload, MIME/size constraints, auth, report-lock behavior, file side effects, cleanup, and audit expectations.

Inspector conclusion:
- Upload stores files under `storage/v2/attachments/{workspace_id}/{entry_id}/{attachmentId}.{ext}`.
- Allowed detected MIME: PDF, JPEG, PNG, WEBP.
- Max size: 8 MiB.
- Create/delete do not mutate entries or financial balances.
- Create/delete write attachment audit rows.
- Delete is not fully filesystem-transactional in PHP; missing file on delete is not fatal.

## Implemented

Files:
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_write_smoke.js`
- `scripts/v2_atlas_runtime_smoke.js`
- `scripts/v2_atlas_cutover_gate.js`

Atlas routes added:
- `POST /api/entries/:entryId/attachments`
- `DELETE /api/attachments/:attachmentId`

Behavior:
- Requires workspace writer.
- Uses existing entry visibility and report-lock guards.
- Rejects unsafe file names and invalid base64.
- Detects MIME by content signature, not client-supplied MIME.
- Allows `application/pdf`, `image/jpeg`, `image/png`, `image/webp`.
- Stores files under `storage/v2/attachments/...`.
- Cleans written file if DB transaction fails during create.
- Deletes file and attachment row on delete.
- Writes `attachment/create` and `attachment/delete` audit records.
- Does not change cash summary or entry amounts.

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

Strict gate:

```bash
npm run gate:v2:atlas-cutover:strict
```

Result: exit code `2`, expected because browser/production cutover is not authorized.

## Gate State

Route surface:
- Total routes: 81
- Reads: 36
- Writes: 45
- Atlas read supported: 36
- Atlas write supported: 45
- Unsupported reads: 0
- Unsupported writes: 0

Remaining blockers:
- `shadow_gateway_available_but_not_cutover`
- `ftp_production_cutover_not_authorized`

## Snapshot Integrity

Write smoke restored the disposable fixture and removed temporary attachment files.

Finance snapshot after smoke:
- `v2_entries`: 1638
- `v2_flows`: 52
- `v2_import_sources`: 60
- `v2_import_rows`: 3507
- `v2_category_rules`: 85
- `v2_dictionary_training_decisions`: 111
- `v2_internet_reference_lookups`: 0
- `v2_attachments`: 0
- Claudia Z cash now: `3893.00`
- Claudia Z August ending cash: `3893.00`

## Next Step

SPRINT-92R should not add more API parity routes. It should be a cutover readiness sprint:
- Browser/local site must be switched through the Atlas runtime path under controlled mode.
- Run manual/browser smoke against local Atlas mode.
- Produce deployment checklist.
- Production FTP cutover still requires explicit authorization.
