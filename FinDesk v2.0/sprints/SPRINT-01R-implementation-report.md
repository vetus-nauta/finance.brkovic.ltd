# SPRINT-01R — Implementation Report

Sprint: `SPRINT-01R — Clean Foundation Implementation`

Director: Codex Director, FinDesk v2.0

Status: Blocked before completion / foundation candidate advanced

## Goal

Create a GitHub-proven clean FinDesk v2.0 foundation before any UI work starts.

## Agents Used

- Data and Backend Core Agent
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent
- Data/Backend Core implementation worker

## Files Changed

- `package.json`
- `FinDesk v2.0/sprints/SPRINT-00R-reset-gate.md`
- `FinDesk v2.0/sprints/SPRINT-01R-clean-foundation-implementation.md`
- `FinDesk v2.0/sprints/SPRINT-01R-implementation-report.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Support.php`
- `app/v2/Database.php`
- `app/v2/Repository.php`
- `app/v2/Api.php`
- `public/v2-api.php`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_disposable_db_smoke.sh`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_fixture_runner.sh`

## What Was Completed

- MariaDB/MySQL was selected as the SPRINT-01R runtime target because existing safe infrastructure donor `ql_db()` uses PDO MySQL.
- Clean v2 tables are isolated under `v2_*`.
- Separate v2 PHP module exists under `app/v2/`.
- Separate public v2 API entrypoint exists at `public/v2-api.php`.
- Static smoke exists as `npm run smoke:v2`.
- Disposable MariaDB repository smoke exists as `npm run smoke:v2:db`.
- Disposable MariaDB fixture runner exists as `npm run test:v2:fixtures`.
- No-sign rows are hard-locked as unrecognized and uncounted-looking:
  - `sign = null`
  - `amount = null`
  - `direction = none`
  - `entry_type = unrecognized`
  - `status = unrecognized`
- Manual Card `+` rows are blocked from becoming normal income.
- Card `+` with `source_type = correction` remains available as correction path.
- Foundation parse preview route exists:
  - `POST /api/parse-entry-preview`
  - `POST /api/workspaces/:workspaceId/parse-preview`
- Category reassignment route exists:
  - `PATCH /api/entries/:entryId/category`
- Minimal category rule route exists:
  - `POST /api/workspaces/:workspaceId/category-rules`
- A disposable DB smoke found and fixed one MariaDB schema issue: `row_number` needed quoting.
- The fixture runner encodes the supported subset of `15-test-fixtures.md` and reports unsupported fixture expectations as `BLOCKED / NOT_IMPLEMENTED`.

## Tests Run

```text
php -l app/v2/Support.php
php -l app/v2/Database.php
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l public/v2-api.php
php -l scripts/v2_clean_core_static_smoke.php
bash -n scripts/v2_disposable_db_smoke.sh
php -l scripts/v2_fixture_runner.php
bash -n scripts/v2_fixture_runner.sh
npm run smoke:v2
npm run smoke:v2:db
npm run test:v2:fixtures
```

## Tests Passed

```text
FinDesk v2 clean core static smoke: OK
Files: 6
Tables: 12
Route markers: 9
```

```text
FinDesk v2 disposable DB smoke: OK
Categories: 16
Audit rows: 9
```

The disposable DB smoke:

- creates a temporary MariaDB datadir/socket under `/tmp`;
- applies `FinDesk v2.0/sql/001-clean-core-mariadb.sql`;
- uses a temporary harness and socket PDO, not `config.local.php`;
- verifies workspace creation;
- verifies default Cash/Card flows;
- verifies category seed;
- verifies signed cash/card entries;
- verifies no-sign rows remain unrecognized and amount-null even if caller supplies amount/status;
- verifies manual Card `+` remains unrecognized and amount-null;
- verifies Card `+` correction path;
- verifies parse preview does not save;
- verifies category patch;
- verifies category rule creation;
- verifies delete hides entry from list;
- verifies audit rows.

The disposable fixture runner:

- creates a temporary MariaDB datadir/socket under `/tmp`;
- runs MariaDB with `--skip-networking`;
- applies `FinDesk v2.0/sql/001-clean-core-mariadb.sql`;
- uses a temporary harness and socket PDO, not `config.local.php`;
- passes supported fixture coverage:
  - basic signed cash income/expense normalization;
  - invalid no-sign row remains visible but unrecognized with null amount;
  - card expense does not create cash rows;
  - card-to-cash pair can be saved without duplicate/error status;
  - explicit `commercial_income` assignment and total;
  - manual Card `+` remains unrecognized/null amount;
  - Card `+` correction path;
  - parse preview does not persist rows.

Fixture runner result:

```text
FinDesk v2 fixture runner: PASS
PASS (7)
BLOCKED / NOT_IMPLEMENTED (9)
```

## Tests Not Yet Run

- Authenticated HTTP API smoke through `public/v2-api.php`.
- Full green fixture gate from `15-test-fixtures.md`; current runner is partial and reports 9 blocked/not implemented expectations.
- Cash balance chain recalculation.
- Closed-month correction/recalculate/cancel behavior.
- Full parser category assignment for examples such as Netflix/media_comms and unknown/other_review.

## Accepted Work

Accepted as local foundation candidate:

- MariaDB/MySQL runtime direction.
- `v2_*` isolation.
- v2 schema applies to a disposable MariaDB 10.11 database.
- Repository-level foundation behavior passes disposable DB smoke.
- Supported fixture-runner subset passes in disposable MariaDB.
- No old FinDesk product logic was used as v2 truth in candidate code.

## Rejected Work

Rejected as completion evidence:

- Old FinDesk UI/runtime as product surface.
- Google Drive Sprint 09-18 as saved implementation.
- Sprint 16/18 as completed implementation.
- Static smoke alone as proof that the core works.
- Any UI work before clean foundation/API/parser/fixture gate.

## Blockers

- Candidate files are still working-tree evidence until committed or otherwise accepted as branch evidence.
- Full fixture gate is not complete; the runner exists, but 9 expectations are explicitly blocked/not implemented.
- HTTP API/auth smoke is not implemented.
- Reports, imports, attachments, month closure, and UI are intentionally not implemented.

## Risks For Next Sprint

- Partial parser behavior in `Repository.php` must not become final parser truth before fixtures.
- Opening balance is still not a practical operational workflow.
- Full arithmetic/cash-now chain is not proved.
- Category auto-detection is not fixture-complete.

## What Must NOT Be Changed Next

- Do not start UI.
- Do not revive old FinDesk product logic.
- Do not change financial formulas without Director decision.
- Do not touch production DB or secrets.

## Recommended Next Director Focus

Continue inside `SPRINT-01R` until these gates pass:

1. Commit or otherwise formally accept candidate files as branch evidence.
2. Convert blocked fixture expectations into implemented behavior or explicitly move them to the next named sprint.
3. Add authenticated HTTP API smoke for `public/v2-api.php`.
4. Prove parser/category behavior needed before operational input UI.
5. Only then consider `SPRINT-02R — Parser, Fixtures, and Operational Entry Semantics` or a UX sprint.

## Handoff Summary

The old FinDesk screen remains rejected as product direction.

The new v2 foundation candidate is now materially stronger: it has clean `v2_*` schema, clean PHP module, static smoke, disposable MariaDB repository smoke, and a disposable partial fixture runner.

SPRINT-01R is not complete yet. The fixture runner is accepted only as progress: `PASS (7)` and `BLOCKED / NOT_IMPLEMENTED (9)`. UI remains blocked.

## Director Final Handoff

Sprint: `SPRINT-01R — Clean Foundation Implementation`

Status: Blocked before completion / foundation candidate advanced

Agents assigned:

- Data and Backend Core Agent
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent
- Data/Backend Core implementation worker
- QA fixture-runner reviewer

Agent reports received:

- Data and Backend Core: accepted MariaDB/MySQL v2 foundation direction, requested parse-preview/category routes/disposable DB smoke/branch evidence.
- Financial Logic Engine: accepted candidate shape conditionally, rejected full completion without fixture runner, status override hardening, balance proof, and card-plus handling.
- QA/Audit: accepted static and disposable DB smoke as progress, rejected sprint completion.
- Implementation worker: added route hardening, category endpoints, disposable DB smoke, and fixture runner path.
- QA fixture-runner reviewer: accepted `npm run test:v2:fixtures` as safe disposable progress, rejected full fixture completion because 9 expectations remain blocked/not implemented.

Accepted work:

- Clean `v2_*` schema candidate.
- Clean `app/v2/` repository/API candidate.
- Separate `public/v2-api.php` candidate.
- Static smoke: `npm run smoke:v2`.
- Disposable DB smoke: `npm run smoke:v2:db`.
- Partial disposable fixture runner: `npm run test:v2:fixtures`.

Rejected work:

- Old FinDesk UI/runtime as product surface.
- Google Drive Sprint 09-18 as implementation proof.
- Sprint 16/18 as completed implementation.
- UI work before clean foundation/API/parser/fixture gate.
- Claiming full fixture completion from the current partial runner.

Files changed:

- `package.json`
- `FinDesk v2.0/sprints/SPRINT-00R-reset-gate.md`
- `FinDesk v2.0/sprints/SPRINT-01R-clean-foundation-implementation.md`
- `FinDesk v2.0/sprints/SPRINT-01R-implementation-report.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/`
- `public/v2-api.php`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_disposable_db_smoke.sh`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_fixture_runner.sh`

Tests or checks:

- `php -l scripts/v2_fixture_runner.php`
- `bash -n scripts/v2_fixture_runner.sh`
- `npm run smoke:v2`
- `npm run smoke:v2:db`
- `npm run test:v2:fixtures`

Risks:

- Working tree is not committed; evidence is local until branch evidence exists.
- HTTP API/auth smoke is still missing.
- Balance chain, closed-month behavior, actor/category parser, Other review queue, and tender ambiguity are not implemented.
- Fixture runner exits zero when implemented behavior passes, even if blocked expectations remain; handoff language must preserve that distinction.

Next sprint:

Continue `SPRINT-01R` or open `SPRINT-02R — Parser, Balance Chain, and API Gate` only after Director decision. UI remains blocked.

Paste-to-next-director prompt:

```text
You are the new FinDesk v2.0 Director. Source of truth is GitHub working tree only. Do not use old chat memory, Google Drive Sprint 09-18, or old FinDesk product logic as implementation proof.

Start from:
- FinDesk v2.0/START_HERE_DIRECTOR.md
- FinDesk v2.0/sprints/SPRINT-00R-reset-gate.md
- FinDesk v2.0/sprints/SPRINT-01R-clean-foundation-implementation.md
- FinDesk v2.0/sprints/SPRINT-01R-implementation-report.md
- FinDesk v2.0/33-director-agent-orchestration-protocol.md

Current state:
- SPRINT-01R is blocked before completion but foundation candidate advanced.
- Static smoke, disposable DB smoke, and partial disposable fixture runner pass.
- Fixture runner output is PASS (7) and BLOCKED / NOT_IMPLEMENTED (9); do not claim full fixture completion.
- UI remains blocked.

Next required gates:
1. Create branch evidence or commit candidate files after review.
2. Add authenticated HTTP API smoke for public/v2-api.php.
3. Implement or explicitly defer blocked fixture expectations: balance chain, parser/category assignment, Other queue, tender metadata, actor extraction, closed-month workflow.
4. Re-run npm run smoke:v2, npm run smoke:v2:db, npm run test:v2:fixtures.
5. Do not start UI until the foundation/API/parser/fixture gate is accepted by QA.
```
