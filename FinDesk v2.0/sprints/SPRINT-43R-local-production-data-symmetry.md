# SPRINT-43R - Local / Production Data Symmetry

Date: 2026-08-10
Status: local sync applied

## Director opening

Goal: make the local FinDesk v2 data state match the production deployment data state so local manual QA, report work, and Claudia Z checks are performed against the same operational history as the live site.

Scope:
- production database snapshot export;
- local database backup before overwrite;
- local import from production snapshot;
- local schema re-application for current branch features;
- post-sync evidence.

Out of scope:
- production deployment;
- production data mutation;
- financial formula changes;
- parser/report/deploy behavior changes beyond existing local code.

## Assigned agents

Director:
- controls sequence and acceptance;
- prevents secrets from being written to repository docs;
- confirms rollback evidence exists before local overwrite.

Data Integrity Agent:
- validates snapshot markers;
- confirms Claudia Z month coverage after import;
- protects local backup before import.

Security / Operations Agent:
- uses temporary exporter only for snapshot creation;
- confirms temporary exporter is removed from production paths;
- keeps FTP/database credentials out of sprint evidence.

QA / Acceptance Agent:
- runs local schema and smoke checks;
- confirms local site process is reachable.

## Evidence

Local backup before sync:
- `storage/db-sync-backups/local-before-prod-sync-20260810-120330.sql`
- size: 4,521,993 bytes

Production snapshot imported locally:
- `storage/db-sync-backups/prod-snapshot-20260810-120835.sql`
- size: 4,144,286 bytes

Temporary production exporter:
- created only for snapshot export;
- uploaded to production root and public path;
- deleted from both production paths;
- local temporary exporter file deleted.

Snapshot markers:
- `CREATE TABLE v2_entries`: present
- `Claudia Z`: present
- `2026-08`: present
- `08.07.26`: present
- `v2_import_sources`: present

## Post-sync Claudia Z operational months

After importing the production snapshot into the local database and re-applying the local clean-core schema:

| Workspace | Month | Entries | Date range |
|---|---:|---:|---|
| Claudia Z | 2026-04 | 22 | 2026-04-20..2026-04-23 |
| Claudia Z | 2026-05 | 7 | 2026-05-14..2026-05-14 |
| Claudia Z | 2026-06 | 102 | 2026-06-06..2026-06-30 |
| Claudia Z | 2026-07 | 85 | 2026-07-31..2026-07-31 |
| Claudia Z | 2026-08 | 18 | 2026-08-09..2026-08-10 |

## Local schema note

The production snapshot was imported first. Then `scripts/v2_apply_clean_core_schema.php` was run locally so the current branch code keeps its required local feature tables, including report-fragment tables.

This means:
- operational data is synchronized from production;
- local schema may include current-branch tables not yet deployed;
- this is intentional for local QA of the current codebase.

## Verification

Commands completed:
- production exporter absence checked by FTP listing;
- local exporter file deletion checked;
- production snapshot imported into local database;
- `php scripts/v2_apply_clean_core_schema.php`;
- `php scripts/v2_clean_core_static_smoke.php`;
- local servers checked reachable on `127.0.0.1:18991` and `127.0.0.1:8098`.
- browser smoke opened local Claudia Z August screen.

Browser evidence:
- `test-results/v2-local-prod-sync-20260810-claudia-august-loaded.png`
- selected workspace: Claudia Z
- selected month: August 2026
- visible entries: 18
- ending cash: 6,942.00 EUR
- opening cash: 9,256.00 EUR

Result:
- local Claudia Z now includes August production data;
- local site can be used for the same data-level QA as production;
- production was not modified during this sync.

## Acceptance

Accepted for local data symmetry when:
- Claudia Z August appears locally;
- local backup path is known;
- production snapshot path is known;
- temporary exporter is removed;
- static smoke passes.

Current result: accepted for local data symmetry.
