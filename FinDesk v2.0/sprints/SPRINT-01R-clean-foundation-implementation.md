# SPRINT-01R — Clean Foundation Implementation

## Goal

Create a GitHub-proven clean FinDesk v2.0 foundation before any UI work starts.

This sprint must prove the repository baseline, safe infrastructure reuse, database/runtime direction, minimal API foundation, parser/fixture-runner path, and legacy isolation.

## Depends On

- `START_HERE_DIRECTOR.md`
- `README.md`
- `FULL_SPEC.md`
- `27-reset.md`
- `29-reset-gate-sprint.md`
- `31-operational-input-window-contract.md`
- `32-director-addendum-operational-window.md`
- `33-director-agent-orchestration-protocol.md`
- `21-sprint-plan.md`
- `22-sprint-handoff-protocol.md`
- `23-legacy-isolation-rule.md`
- `02-data-model.md`
- `03-parsing-and-rules-engine.md`
- `14-calculation-contract.md`
- `15-test-fixtures.md`
- `16-api-contract.md`
- `20-definition-of-done.md`
- `sprints/SPRINT-00R-reset-gate.md`

## Director Rule

The Director coordinates. The Director does not write implementation code.

The sprint cannot close unless all assigned agents report and QA accepts reproducible evidence.

## Required Agents

- Data and Backend Core Agent
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

Optional if needed:

- Localization and Linguistic Rules Agent for parser keyword/language review.

## Scope

1. Reconcile repository state:
   - confirm current `origin/main`;
   - list local uncommitted candidate files;
   - decide what is accepted for review and what is rejected.
2. Confirm runtime database target:
   - current committed baseline includes `sql/clean-core-schema.sql`;
   - existing runtime donor appears to be PDO MySQL/MariaDB;
   - target must be decided and documented before schema work is accepted.
3. Prove clean data foundation:
   - workspaces;
   - workspace members;
   - flows;
   - entries;
   - categories;
   - category rules;
   - actors;
   - audit log.
4. Prove minimal API foundation:
   - workspaces;
   - flows;
   - entries;
   - categories;
   - audit evidence.
5. Prepare parser and fixture-runner gate:
   - rows beginning with `+` or `-` are counted candidates;
   - rows without `+` or `-` remain visible and uncounted;
   - Cash/Card remain funding flows;
   - card-to-cash remains two valid records;
   - fixture runner must be ready before UI sprint.
6. Preserve operational input window principle:
   - first surface will be operational input, not dashboard/report;
   - this sprint does not build UI.

## Explicitly Out Of Scope

- Dashboard UI.
- Final report UI.
- Full monthly summary/report generation.
- Legacy import.
- Attachments upload.
- Assistant workflow.
- Production deploy.
- Direct production DB patching.

## Forbidden

- Do not accept Sprint 16/18 as completed implementation.
- Do not accept Drive reports as saved implementation.
- Do not use old FinDesk finance logic as product truth.
- Do not start UI before base/API/parser/fixture runner proof.
- Do not commit secrets.
- Do not change financial formulas without explicit Director decision.

## Required Outputs

1. Director Sprint Opening.
2. Data/Backend Core Agent report.
3. Financial Logic Engine Agent report.
4. QA/Audit report.
5. Files changed list.
6. Runtime DB target decision.
7. API route status.
8. Parser/fixture-runner status.
9. Legacy isolation confirmation.
10. Final handoff for the next Director.

## Exit Criteria

`SPRINT-01R` is complete only if:

- clean foundation files are in GitHub;
- runtime DB target is decided;
- schema/API foundation is reproducibly checked;
- parser/fixture-runner path is proved or the sprint is explicitly blocked;
- old FinDesk product logic is not reused;
- local uncommitted candidate files are either accepted through review/test/commit or rejected;
- QA accepts the evidence;
- final handoff is written.

If these are not met, the sprint status is `Blocked`, not completed.

## Next Sprint If Complete

```text
SPRINT-02R — Parser, Fixtures, and Operational Entry Semantics
```

## UI Gate

No UX/UI sprint may start until the clean foundation, API, parser, and fixture runner have passed QA.
