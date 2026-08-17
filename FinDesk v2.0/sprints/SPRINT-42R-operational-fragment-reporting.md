# SPRINT-42R — Operational Fragment Reporting

Date: 2026-08-10
Status: implemented locally, not production-accepted

## Director Opening

Goal: from the operational journal, allow the user to select a continuous row fragment and create a sendable report snapshot that looks like the Layer 1 summary, while protecting the selected operational rows from accidental later edits.

This is not month closing. It is a report-fragment close/lock over selected operational entries.

## Agent Assignments

- Product / UX Layout Agent: define the row-range workflow inside the operational journal without turning it into a separate dashboard.
- Backend / Data Integrity Agent: design persistent report batches, entry snapshots, HTML artifact, and edit guard.
- QA / Acceptance Agent: verify that preview does not mutate data, create locks selected rows, and later edits require explicit confirmation.

## Product Decisions

- Primary MVP send format: HTML, because it preserves expandable category/source behavior and can later be rendered to PDF from the same snapshot.
- Selection is flow-local and row-contiguous: first click selects start, second click selects end.
- The twin summary opens as a modal working layer over the operational journal.
- Created fragments protect entries with an explicit report lock. Future edit/delete/category changes require a report-fragment recalculation decision.
- Financial formulas, parser behavior, monthly close behavior, and deployment behavior are unchanged.

## Implemented Files

- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2-report.php`
- `public/v2.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `scripts/v2_report_fragment_browser_smoke.cjs`

## API Surface

- `POST /api/workspaces/{workspace}/reports/operational-fragments/preview`
- `GET /api/workspaces/{workspace}/reports/operational-fragments`
- `POST /api/workspaces/{workspace}/reports/operational-fragments`
- `GET /api/workspaces/{workspace}/reports/operational-fragments/{id}`
- Compatibility aliases remain through `/reports/batch-preview` and `/reports/batches`.

## Acceptance Evidence

Local schema:

- `php scripts/v2_apply_clean_core_schema.php`
- Result: `FinDesk v2 clean-core schema apply: OK`

Static checks:

- `node --check public/assets/v2/app.js`
- `php -l app/v2/Repository.php`
- `php -l app/v2/Api.php`
- `php -l public/v2-report.php`
- `php scripts/v2_clean_core_static_smoke.php`

Backend smoke:

- Temporary workspace created.
- Three entries created.
- Operational fragment preview returned `can_create`.
- Operational fragment created with HTML URL.
- Edit without `report_fragment_decision` rejected with `report_fragment_requires_decision`.
- Edit with `report_fragment_decision=recalculate_fragment` accepted.
- Temporary workspace and temporary HTML were removed.

Browser smoke:

- Script: `node scripts/v2_report_fragment_browser_smoke.cjs`
- Server: `http://127.0.0.1:18991`
- Result: OK
- Evidence screenshots:
  - `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786362580332/01-operational-ready.png`
  - `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786362580332/02-fragment-preview.png`
  - `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786362580332/03-fragment-created.png`
  - `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786362580332/04-locked-rows.png`

## Remaining Gate

Production acceptance is not granted in this sprint. Before deploy acceptance:

- run the same browser smoke on the deployment target;
- manually verify on Claudia Z using a non-final or test fragment;
- decide the final sending workflow: downloadable HTML file, email attachment, or saved internal link;
- add optional PDF export only after HTML snapshot behavior is accepted.
