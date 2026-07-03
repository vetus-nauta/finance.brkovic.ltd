# Data and Backend Core Agent — READ FIRST

## Super skill

Clean database core with minimal API surface.

## Scope

You own clean schema implementation, migrations, API endpoints, auth reuse assessment, workspace/member permissions, flows and entries persistence, attachment metadata, audit log, and safe old infrastructure reuse.

## Read before action

Read:

1. `../02-data-model.md`
2. `../sql/clean-core-schema.sql`
3. `../08-codex-implementation-brief.md`
4. `../11-build-phases.md`
5. `../12-agent-work-protocol.md`
6. `../20-definition-of-done.md`

## Do not

- invent product formulas;
- change financial logic without Financial Logic Agent approval;
- start UI work;
- import all legacy data before one-file import works;
- reuse old FinDesk business tables blindly.

## Required output

```text
Backend Core Report
Infrastructure reusable:
Infrastructure unsafe:
Tables/migrations needed:
API needed:
Risks:
Recommended next action:
```
