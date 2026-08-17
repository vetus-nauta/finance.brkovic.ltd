# SPRINT-44R - Local / Production v2 Runtime Symmetry

Date: 2026-08-10
Status: accepted for FinDesk v2 runtime symmetry

## Director opening

Goal: bring the local FinDesk v2 runtime and production FinDesk v2 runtime to the same operational baseline before continuing local-first development and deploying only larger completed tasks.

This sprint follows SPRINT-43R, where the local database was synchronized from production data.

## Scope

Included:
- FinDesk v2 production schema apply for current branch runtime needs.
- FinDesk v2 runtime file upload.
- Production file hash verification against local files.
- Production live preflight.
- Production manual walkthrough with real session.
- Test-script updates required by the accepted product behavior.

Excluded:
- broad upload of legacy/root product files outside FinDesk v2;
- `app/config.local.php`;
- local secrets, storage logs, backups, `node_modules`, test output;
- production data rewrites beyond temporary smoke entries that were deleted.

## Assigned agents

Release / Ops Agent:
- applied production schema through a one-time checker/applier;
- removed temporary production tools after use;
- uploaded only the v2 runtime payload.

Data Integrity Agent:
- confirmed production has `v2_report_batches` and `v2_report_batch_entries`;
- confirmed Claudia Z local month counts after SPRINT-43R remain intact.

QA / Acceptance Agent:
- ran local smoke/fixture/browser gates;
- ran production preflight;
- ran production manual walkthrough;
- confirmed no production test markers remain.

Product / UX Agent:
- corrected smoke expectations to match accepted UX:
  - first row click selects/focuses, not edit/delete preview;
  - lower accounting is limited to explicit credit/accountable rows;
  - contextual debt/return/guest-cash rows remain operational and editable;
  - summary source checks must use the selected period filter.

## Runtime payload

Uploaded and hash-verified:

- `.htaccess`
- `v2.php`
- `v2-api.php`
- `public/v2.php`
- `public/v2-api.php`
- `public/v2-report.php`
- `public/assets/v2/app.css`
- `public/assets/v2/app.js`
- `app/v2/Api.php`
- `app/v2/Database.php`
- `app/v2/InternetReferenceProvider.php`
- `app/v2/LegacyExcelImporter.php`
- `app/v2/Repository.php`
- `app/v2/Support.php`

Production backup before v2 upload:
- `storage/production-audits/prod-before-v2-sync-20260810-123541`

Upload log:
- `storage/production-audits/prod-v2-sync-20260810-123541.ftp.log`
- result: `backup_ok=14 backup_missing=0 upload_ok=14 upload_fail=0`

Hash verification:
- `storage/production-audits/prod-v2-hash-verify-20260810-123724.txt`
- result: `hash_ok=14 hash_mismatch=0`

## Schema evidence

Production schema applier:
- one-time file created for schema apply;
- schema apply result: `applied=17 skipped=23`;
- one-time file removed from production after use.

Production DB table check:
- `v2_entries`: present
- `v2_workspaces`: present
- `v2_report_batches`: present
- `v2_report_batch_entries`: present
- one-time checker removed from production after use.

## Local checks

Passed:
- `npm run smoke:v2`
- `npm run smoke:v2:deploy`
- `php -l app/v2/Api.php app/v2/Repository.php app/v2/InternetReferenceProvider.php public/v2.php public/v2-api.php public/v2-report.php v2.php v2-api.php`
- `node scripts/v2_report_fragment_browser_smoke.cjs`
- `npm run smoke:v2:http`
- `npm run test:v2:fixtures`
- `node --check scripts/v2_manual_production_walkthrough.cjs scripts/v2_report_fragment_browser_smoke.cjs`
- `php -l scripts/v2_fixture_runner.php scripts/v2_http_api_smoke.php`

Test-script corrections:
- `scripts/v2_report_fragment_browser_smoke.cjs`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_manual_production_walkthrough.cjs`

## Production live checks

Production preflight:
- `FINDESK_V2_PRODUCTION_BASE_URL=https://finance.brkovic.ltd npm run smoke:v2:deploy`
- result: OK with one local warning about ignored local auth log.

Production HTTP:
- `https://finance.brkovic.ltd/v2.php`: OK
- cachebuster `20260810-operational-fragment-b`: present
- report selection marker: present
- private paths `/storage`, `/app/config.php`, `/deploy`, `/backups`: 403

Production report route:
- `https://finance.brkovic.ltd/v2-report.php?id=missing-sync-check`: 404, route alive.

Production manual walkthrough:
- result: PASS
- evidence directory: `test-results/v2-production-manual-walkthrough/PROD_MANUAL_WALKTHROUGH_1786366012344`
- completed steps: 18
- covered:
  - Claudia Z selection;
  - current August 2026 screen;
  - Summary tabs: Information, Sending, Printing, Storage;
  - Training transition;
  - Operational return;
  - current-month create/edit/delete;
  - archive-month open;
  - archive create/edit/delete;
  - return to current month;
  - postflight clean.

Production cleanup:
- search for `PROD_MANUAL_WALKTHROUGH_` in Claudia Z July/August returned zero entries.

## Acceptance

Accepted because:
- local data was synchronized from production in SPRINT-43R;
- v2 runtime files on production match local hashes exactly;
- production DB has the required v2 report-fragment tables;
- production live preflight passes;
- production manual walkthrough passes;
- no test rows remain.

Next discipline:
- continue work locally;
- deploy only completed logical task groups;
- every future deploy must include:
  - local gate,
  - production backup,
  - v2 payload manifest,
  - hash verification,
  - production smoke,
  - sprint evidence.
