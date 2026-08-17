# SPRINT-47R — Report Storage, Collapse, and Package Reports

Date: 2026-08-10
Status: local implementation accepted by Director after agent reports and smoke checks

## Director opening

Goal: turn selected operational report fragments into a durable workflow:

- created report fragments are visible as one collapsed row in the operational journal;
- the user can expand a collapsed report row to see original rows;
- each report fragment stores HTML snapshots / versions;
- several closed report fragments can be combined into one package report;
- package HTML preserves fragment boundaries and category drilldown to source operational rows;
- public report HTML supports both single fragments and package reports.

Non-goals:

- no changes to financial formulas;
- no parser behavior changes;
- no deploy in this sprint;
- no dashboard-first UX.

## Agents

Frontend Performance and Interaction Agent:

- implemented collapsed report rows in journal and structured check;
- added report package selection from collapsed report rows;
- added package creation flow from selected closed reports;
- kept journal expand behavior and made structured report rows open reports directly.

Backend/Data Agent:

- added fragment HTML snapshot storage;
- added package tables and package API endpoints;
- added immutable package snapshots from closed fragments;
- added package HTML file writing.

QA, Audit, and Acceptance Agent:

- accepted the report storage collapse behavior inside the FinDesk v2 sprint chain;
- old `docs/AI_TEAM` acceptance paths are retired and are not product truth;
- flagged immutable snapshot, duplicate source, permission isolation, and collapse clarity risks.

Director finalization:

- removed duplicate/conflicting package implementation paths;
- normalized report versions to `report_id` / `report_type`;
- connected public `v2-report.php?type=package&id=...`;
- added package HTML category drilldown with original source rows;
- added visible package storage list in the `Хранение` summary tab;
- updated HTTP smoke to verify package HTML route;
- updated browser smoke to match collapsed report row UX.

## Files

Backend/API:

- `app/v2/Repository.php`
- `app/v2/Api.php`
- `public/v2-report.php`

Frontend:

- `public/assets/v2/app.js`
- `public/v2.php`

Schema:

- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `FinDesk v2.0/sql/clean-core-schema.sql`

Tests:

- `scripts/v2_http_api_smoke.php`
- `scripts/v2_http_api_smoke.sh`
- `scripts/v2_report_fragment_browser_smoke.cjs`

## Acceptance evidence

Passed locally:

- `php -l app/v2/Repository.php`
- `php -l app/v2/Api.php`
- `php -l public/v2-report.php`
- `php -l scripts/v2_http_api_smoke.php`
- `node --check public/assets/v2/app.js`
- `node --check scripts/v2_report_fragment_browser_smoke.cjs`
- `npm run schema:v2:apply`
- `php scripts/v2_clean_core_static_smoke.php`
- `bash scripts/v2_http_api_smoke.sh`
- `node scripts/v2_report_fragment_browser_smoke.cjs`
- `git diff --check`

Browser evidence:

- latest report fragment smoke screenshots:
  `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786376427619`

## Remaining product notes

- Package sending / PDF delivery can now be built on top of stored package HTML.
- Report package cancellation/version superseding is intentionally not exposed yet.
- Deployment remains pending; this sprint is local-only until the user approves a deploy gate.
