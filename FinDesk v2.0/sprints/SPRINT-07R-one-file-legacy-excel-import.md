# SPRINT-07R — One-File Legacy Excel Import MVP

Director: Codex Director, FinDesk v2.0

Status: Accepted

## Director Sprint Opening

Sprint:

```text
SPRINT-07R — One-File Legacy Excel Import MVP
```

Goal:

- Implement one-file legacy Excel import MVP.
- Preserve source traceability from file, sheet, row, raw data, and import row to normalized entry.
- Provide import review and accept routes.
- Compare imported totals against generated report API.
- Do not implement full archive import, dashboard UI, attachments, bank integration, OCR, or old FinDesk product logic.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/05-import-and-legacy-data.md`
- `FinDesk v2.0/07-mvp-scope-and-acceptance.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/19-legacy-import-acceptance.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/sprints/SPRINT-06R-generated-monthly-report-api.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2-api.php`
- `scripts/v2_http_api_smoke.php`

Agents assigned:

- Legacy Import and Archive Agent
- Financial Logic Engine Agent
- Data/Backend Core Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- Legacy Import: define one-file import semantics, mapping, traceability, review report, and exclusions.
- Financial Logic: define imported row effects on entries and reports.
- Backend/API: define minimal JSON/base64 `.xlsx` routes, repository methods, parser path, and tests.
- QA/Audit: define exit criteria, disposable evidence, blockers, and must-not-touch boundaries.

Expected reports:

- ACCEPT/BLOCK for opening and closing the sprint.
- Accepted input format and route behavior.
- Old cash/card mapping rules.
- Import review report shape.
- Required disposable DB evidence.

Exit criteria:

- `POST /api/workspaces/:workspaceId/imports/excel` accepts one `.xlsx` file as JSON/base64.
- `GET /api/workspaces/:workspaceId/imports/:importId/review` returns import review.
- `POST /api/workspaces/:workspaceId/imports/:importId/accept` creates normalized entries.
- Import report includes source file name, sheets scanned, rows scanned, rows parsed, entries created, rows ignored, rows unrecognized, summary rows ignored, cash/card totals, source total comparison, include/exclude decisions, and duplicate suspects.
- Imported entries preserve source file, sheet name, row number, raw row data, import row id, entry id, and parse status.
- Row date has priority over filename date.
- Exclude markers reject files by title without deleting anything.
- Generated monthly report reflects accepted imported entries.
- Existing v2 gates remain green.

Risks:

- Accidentally importing full archive instead of one file.
- Trusting filename date over row date.
- Silently deleting duplicates instead of marking suspects.
- Treating opening balance or info rows as income.
- Reusing old FinDesk import/report product logic as truth.
- Expanding into attachment/upload UI or dashboard/report UI.

## Agent Reports

Legacy Import and Archive Agent: Hegel

```text
ACCEPT to open.
One-file .xlsx import is valid MVP scope.
Require JSON/base64 upload, old cash/card mapping, source row traceability, include/exclude markers, import review report, duplicate suspects, and no full archive import.
Initial closure blockers: missing routes, missing source row persistence, missing adapter, missing tests.
```

Financial Logic Engine Agent: Hume

```text
ACCEPT to open; BLOCK closure until import behavior exists.
Imported rows must become operational entries first, then reports are generated from those entries.
Cash income/expense affects cash reports and ending cash.
Card expense affects card expense only.
Manual Card + remains blocked, but import Card + may become card_income and must stay outside cash income.
Opening/info/summary rows must not affect arithmetic.
Duplicate suspects must be marked, not deleted, and not counted until explicitly accepted later.
```

Data/Backend Core Agent: Ptolemy

```text
ACCEPT to open.
Use JSON/base64, not multipart, because v2-api.php currently reads JSON through ql_input().
.xlsx is feasible with native PHP ZipArchive/SimpleXML.
.xls binary is out of scope for MVP unless converted to .xlsx.
Implement only POST /imports/excel, GET /imports/:id/review, POST /imports/:id/accept.
```

QA, Audit, and Acceptance Agent: Kepler

```text
ACCEPT to open; BLOCK closure until real import behavior and disposable evidence exist.
Require import report fields, traceability, row-date priority, duplicate suspects, generated monthly report reflection, and existing gates green.
Block full archive import, dashboard/report UI, attachments, bank integration, OCR, production DB, formula drift, parser drift outside import normalization, and old FinDesk product logic.
```

## Implementation Candidate

Branch:

```text
findesk-v2-sprint-07r-one-file-excel-import
```

Files changed:

- `app/v2/Api.php`
- `app/v2/Repository.php`
- `app/v2/LegacyExcelImporter.php`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_disposable_db_smoke.sh`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_fixture_runner.sh`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_http_api_smoke.sh`
- `scripts/v2_operational_browser_smoke.sh`
- `FinDesk v2.0/sprints/SPRINT-07R-one-file-legacy-excel-import.md`

Implemented:

- `POST /api/workspaces/:workspaceId/imports/excel`
- `GET /api/workspaces/:workspaceId/imports/:importId/review`
- `POST /api/workspaces/:workspaceId/imports/:importId/accept`
- Native minimal `.xlsx` reader for one-file MVP import.
- JSON/base64 upload path.
- Include/exclude title marker handling.
- `v2_import_sources` and `v2_import_rows` population.
- Source traceability from imported entry to import source and import row.
- Import review report with:
  - source file name/id/url
  - included/excluded file decision
  - sheets scanned
  - rows scanned
  - rows parsed
  - entries created
  - rows ignored
  - rows unrecognized
  - summary rows ignored
  - cash/card source totals
  - normalized totals
  - source total comparison
  - duplicate suspects
  - months covered
  - row traces
- Accept path creating normalized operational entries.
- Import-only `card_income` exception while keeping manual Card `+` blocked.
- Duplicate suspects marked as `duplicate_suspect` and left uncounted.
- Sparse `.xlsx` row handling that preserves Excel column indexes.
- Date provenance in row traces:
  - `row_date`
  - `inherited_previous_row_date`
  - `filename_date`
  - `file_updated_date`

Verification:

```text
php -l app/v2/LegacyExcelImporter.php
php -l app/v2/Api.php
php -l app/v2/Repository.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_http_api_smoke.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:db
npm run smoke:v2:ui
npm run smoke:v2:browser
```

Result:

```text
PASS
```

Fixture result:

```text
PASS (14)
BLOCKED / NOT_IMPLEMENTED (0)
```

## Final Audit Status

Financial Logic final audit:

```text
ACCEPT for financial logic closure.
```

Import final audit initially blocked sparse `.xlsx` row handling:

```text
BLOCK: importer collapsed sparse cell indexes.
```

Fix applied:

```text
LegacyExcelImporter now preserves Excel column indexes.
Fixture and HTTP generators now skip empty cells, producing sparse test rows.
Fixtures and HTTP smoke pass after the fix.
```

QA final audit initially blocked sparse row handling and incomplete date provenance:

```text
BLOCK: sparse cells and date provenance.
```

Fix applied:

```text
Sparse cell indexes are preserved.
Date source is exposed in row traces.
Filename date fallback is implemented and tested.
Inherited previous row date and row date provenance are tested.
Fixtures and HTTP smoke pass after the fix.
```

Administrative status:

```text
Accepted after final Import and QA re-audit.
```

Final Import Acceptance Agent: Bernoulli

```text
ACCEPT
Verified import routes, one-file .xlsx JSON/base64 path, sparse XLSX column preservation, date priority/provenance, review totals, source comparison, duplicate suspects, source traceability, accept path, and generated report reflection.
```

Final QA/Audit Acceptance Agent: Goodall

```text
ACCEPT
Verified contract routes, .xlsx validation, base64 validation, excluded markers, source/row traceability, required review fields, date provenance, duplicate suspect handling, generated monthly report source trace, fixture/HTTP coverage, and scope boundaries.
```

## Residual Risks

- Only `.xlsx` is supported; binary `.xls` remains out of MVP.
- Native reader supports simple workbook/sheet/cell structures, not complex merged-cell-heavy layouts.
- Import review is API-only; no import review UI was added.
- Duplicate suspects are marked and uncounted; there is no duplicate-resolution UI yet.
- Imported `other` rows with status `imported` do not enter the current `other_review` queue; this needs a later review-workflow decision.
- Full archive traversal remains forbidden until one-file import is formally accepted.

## Isolated Handoff For Next Director

Recommended next sprint:

```text
SPRINT-08R — Attachments Base Or Import Review Resolution
```

Decision point:

- If MVP priority is strict checklist completion, implement attachments base.
- If operational safety is higher priority, implement import review/duplicate resolution surface.

Must not touch:

- Full archive import.
- Dashboard/report UI.
- Bank integration.
- OCR.
- Production DB/secrets.
- Financial formulas without explicit decision.
- Old FinDesk product logic as truth.
