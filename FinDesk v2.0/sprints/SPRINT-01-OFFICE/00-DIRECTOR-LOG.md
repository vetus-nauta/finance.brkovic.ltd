# FinDesk v2.0 Sprint 01 — Director Log

Date: 2026-07-03
Director: Codex Director, Sprint 01
Repository: `vetus-nauta/finance.brkovic.ltd`
Branch: `main`

## Operating Rule

Director does not write implementation code. Sprint 01 is cleanup, inventory, classification, and handoff only.

## Source Of Truth

Authoritative package:

```text
FinDesk v2.0/
```

Old FinDesk in this repository is infrastructure donor only. Old documentation, finance logic, tables, reports, categories, dashboards, calculations, and project discipline are rejected as FinDesk v2.0 truth.

## Required Director Reading Completed

- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/21-sprint-plan.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/24-secrets-hosting-access-inventory.md`
- `FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md`
- `FinDesk v2.0/agents/00-DIRECTOR-READ-FIRST.md`

## Sprint 01 Goal

Clean old FinDesk and keep only safe foundational infrastructure for FinDesk v2.0.

## Team

1. Financial Logic Engine Agent
2. Data and Backend Core Agent
3. iOS-Native UX Layout Agent
4. Frontend Performance and Interaction Agent
5. Localization and Linguistic Rules Agent
6. Legacy Import and Archive Agent
7. QA, Audit, and Acceptance Agent

## Output Files

Subagents must write their findings into this folder. Each subagent owns only its own report file and must not edit application code, SQL migrations, runtime config, or another agent report.

## Initial Repository Fact

The local folder initially contained only an empty `.git` directory with no remote and no commits. The Director resolved the GitHub source as `https://github.com/vetus-nauta/finance.brkovic.ltd`, added it as `origin`, fetched `main`, and switched the local checkout to track `origin/main`.

## Open Director Decisions

- No application code changes are approved in Sprint 01.
- No database migration changes are approved in Sprint 01.
- No old finance logic may be adopted.
- Real secrets must not be written into reports.
