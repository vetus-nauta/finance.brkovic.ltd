# SPRINT-00R — Reset Gate and Repository Baseline

## Director Sprint Opening

Sprint: `SPRINT-00R — Reset Gate and Repository Baseline`

Goal: confirm the clean FinDesk v2.0 GitHub base, reject non-GitHub implementation claims, assign reset/audit agents, and name the next implementation sprint.

Required files read:

- `FinDesk v2.0/START_HERE_DIRECTOR.md`
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/27-reset.md`
- `FinDesk v2.0/26-recovery-audit-after-windows-crash.md`
- `FinDesk v2.0/29-reset-gate-sprint.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/32-director-addendum-operational-window.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/21-sprint-plan.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/agents/00-DIRECTOR-READ-FIRST.md`

Agents assigned:

- Data and Backend Core Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- Data and Backend Core Agent: verify active base, backend implementation evidence, committed GitHub state vs local candidate files, backend risks, and next sprint backend scope.
- QA, Audit, and Acceptance Agent: verify reset gate acceptance, accepted/rejected evidence, blockers, required next checks, and whether UI work is allowed.

Expected reports:

- Backend Reset Gate Report
- QA Reset Gate Report

Exit criteria:

- Required GitHub files are present and readable.
- Active base is confirmed.
- Google Drive Sprint 09-18 is archive-only context.
- Old FinDesk is confirmed as infrastructure donor only.
- Missing implementation is treated as absent unless present in committed GitHub evidence.
- Required reset/audit agents report.
- Next implementation sprint is named.
- Final handoff is written for the next Director.

Risks:

- Local uncommitted candidate implementation can be confused with accepted GitHub evidence.
- Google Drive reports can be mistaken for saved implementation.
- UI pressure can start before base/API/parser/fixture runner proof.

## Director Reset Gate Report

Status: Blocked as committed branch evidence / reset audit completed in working tree

Date: 2026-07-05

Current committed HEAD:

```text
26f70a0 Update director start file
```

Active base:

```text
vetus-nauta/finance.brkovic.ltd
FinDesk v2.0/
```

Archive context:

Google Drive Sprint 09-18 materials are archive/recovery context only. They are not accepted as completed implementation unless matching files exist in GitHub and pass current checks.

Old FinDesk status:

Old FinDesk may donate infrastructure only: runtime config pattern, DB connection method, auth/session shell, deployment shell, upload base, logging, and safe generic helpers after explicit review.

Old FinDesk product logic is rejected as v2 truth: finance tables, entities, categories, calculations, dashboards, reports, UX decisions, import assumptions, and documentation discipline.

Operational window principle:

The first working surface is the operational input window:

- vertical scroll is operational record history;
- right/structured zone is immediate parsed verification and current figures;
- reports are generated later from operational entries;
- dashboard/report-first UI is forbidden.

This principle does not authorize starting UI before base/API/parser/fixture runner proof.

## Agent Reports Received

### Data and Backend Core Agent

Accepted as read-only reset/backend report.

Summary:

- Confirmed active repository is `/home/alexey/GitHub/finance.brkovic.ltd`, branch `main`, aligned with `origin/main`.
- Confirmed committed `HEAD` contains the authoritative `FinDesk v2.0/` planning/spec package, reset docs, orchestration docs, Sprint 01-08 contracts, schemas, and committed `FinDesk v2.0/sql/clean-core-schema.sql`.
- Confirmed committed `HEAD` does not contain `app/v2/*`, `public/v2-api.php`, `FinDesk v2.0/sql/001-clean-core-mariadb.sql`, or `scripts/v2_clean_core_static_smoke.php`.
- Confirmed local candidate files appear coherent but are local candidate evidence only.
- Identified DB target split: committed schema is PostgreSQL-style, while local candidate implementation is MariaDB/MySQL with `v2_*` tables.

### QA, Audit, and Acceptance Agent

Accepted as read-only reset/QA report.

Summary:

- Confirmed current repo and branch are correct.
- Confirmed all requested reset/orchestration/spec files exist and are tracked.
- Confirmed active base is `FinDesk v2.0/`.
- Confirmed Google Drive Sprint 09-18 is archive/recovery context only.
- Confirmed old FinDesk may be used only as infrastructure donor, not product logic.
- Confirmed next named sprint from `29` is `SPRINT-01R — Clean Foundation Implementation`.
- Set gate status as blocked/not closable from current branch evidence until final handoff and agent evidence are accepted as branch evidence.
- Confirmed UI remains blocked.

## Accepted Evidence

- `FinDesk v2.0/` in `vetus-nauta/finance.brkovic.ltd` is the authoritative project base.
- `START_HERE_DIRECTOR.md`, `README.md`, `FULL_SPEC.md`, `27-reset.md`, `26-recovery-audit-after-windows-crash.md`, `29-reset-gate-sprint.md`, `31`, `32`, `33`, sprint plan, handoff protocol, legacy isolation rule, and Director read-first file are present in GitHub.
- Current committed GitHub truth is planning/spec/reset/orchestration evidence, not v2 runtime implementation.
- Reset gate next sprint name from `29-reset-gate-sprint.md` is:

```text
SPRINT-01R — Clean Foundation Implementation
```

## Rejected Evidence

Rejected as implementation proof:

- Google Drive Sprint 09-18 claims without matching GitHub files.
- Sprint 16/18 continuation as if implementation is complete.
- Local uncommitted candidate files:
  - `FinDesk v2.0/sprints/SPRINT-00R-reset-gate.md`
  - `FinDesk v2.0/sprints/SPRINT-01R-clean-foundation-implementation.md`
  - `app/v2/*`
  - `public/v2-api.php`
  - `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
  - `scripts/v2_clean_core_static_smoke.php`
  - modified `package.json`
- Any old FinDesk finance logic, tables, categories, reports, dashboards, calculations, or UX decisions.

Local uncommitted candidate files may be inspected in `SPRINT-01R`, but they are not accepted until reviewed, tested, committed, and accepted by assigned agents.

## Checks

- `git fetch --all --prune`
- `git status --short --branch`
- `git log --oneline --decorate --max-count=6`
- Direct reads of required reset files from `FinDesk v2.0/`
- Agent reports collected through the orchestration protocol

No application code was written by the Director during this reset gate.

## Blockers

- Worktree is dirty; key sprint and implementation candidate files are untracked.
- This `SPRINT-00R` report itself is working-tree evidence until committed or otherwise accepted.
- Clean foundation/API/parser/fixture runner are not proved.
- Runtime DB target is unresolved.
- UI remains blocked.

## Risks

- Local uncommitted v2 implementation can be lost or mistakenly treated as accepted.
- Committed SQL baseline is PostgreSQL-oriented, while the runtime donor appears to be PDO MySQL/MariaDB.
- Parser and fixture runner are not proved.
- API/runtime DB smoke is not proved.
- UI pressure can bypass the operational journal foundation.

## Next Sprint

```text
SPRINT-01R — Clean Foundation Implementation
```

SPRINT-01R must be run through assigned agents. It must not begin UI work.

## Director Final Handoff

Sprint: `SPRINT-00R — Reset Gate and Repository Baseline`

Status: Blocked as committed branch evidence; reset/audit work completed in working tree

Agents assigned:

- Data and Backend Core Agent
- QA, Audit, and Acceptance Agent

Agent reports received:

- Backend Reset Gate Report: accepted
- QA Reset Gate Report: accepted

Accepted work:

- Active GitHub base confirmed.
- Archive-only status of Drive Sprint 09-18 confirmed.
- Old FinDesk donor-only rule confirmed.
- Operational input window principle confirmed.
- Next sprint named.

Rejected work:

- Sprint 16/18 as completed implementation.
- Local uncommitted v2 candidate implementation as accepted evidence.
- Old FinDesk product logic as v2 truth.

Files changed:

- `FinDesk v2.0/sprints/SPRINT-00R-reset-gate.md`
- `FinDesk v2.0/sprints/SPRINT-01R-clean-foundation-implementation.md`

Tests or checks:

- GitHub file presence/read checks.
- Agent orchestration completed.
- Backend agent ran local static candidate checks, but those remain candidate-only.
- No runtime DB/API tests; this was a reset/audit sprint.

Risks:

- Candidate local implementation remains unaccepted.
- Foundation/runtime DB target unresolved.
- Parser/fixture runner not proved.
- UI must remain blocked.

What must not be touched:

- Financial formulas without explicit Director decision.
- Old FinDesk finance logic.
- Report/dashboard UI before operational journal foundation.
- Production database or secrets.

Next sprint:

```text
SPRINT-01R — Clean Foundation Implementation
```

Paste-to-next-director prompt:

```text
You are the Director for FinDesk v2.0 SPRINT-01R — Clean Foundation Implementation.

Source of truth is only GitHub files in vetus-nauta/finance.brkovic.ltd / FinDesk v2.0/.

Read first:
- START_HERE_DIRECTOR.md
- README.md
- FULL_SPEC.md
- 27-reset.md
- 29-reset-gate-sprint.md
- 31-operational-input-window-contract.md
- 32-director-addendum-operational-window.md
- 33-director-agent-orchestration-protocol.md
- 21-sprint-plan.md
- 22-sprint-handoff-protocol.md
- 23-legacy-isolation-rule.md
- sprints/SPRINT-00R-reset-gate.md
- sprints/SPRINT-01R-clean-foundation-implementation.md

Assign agents before work:
- Data and Backend Core Agent
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

Do not write implementation as Director.
Do not start UI.
Do not use old FinDesk product logic.
Do not accept local uncommitted candidate files until agents review, tests pass, and changes are committed or otherwise explicitly accepted as branch evidence.

SPRINT-01R must prove clean foundation, API/runtime direction, parser/fixture-runner path, and legacy isolation before any UX sprint can start.
```
