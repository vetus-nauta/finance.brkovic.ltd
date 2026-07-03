# 25 — Final Repository Audit

## Status

READY FOR SPRINT 01 in the correct repository: `vetus-nauta/finance.brkovic.ltd`.

This is the authoritative FinDesk v2.0 package location.

The earlier package in `vetus-nauta/Revoyacht` was created by mistake and is not authoritative.

## Transfer status

The package has now been transferred step by step into this repository.

It includes:

```text
README.md
FULL_SPEC.md
01-product-logic.md
02-data-model.md
03-parsing-and-rules-engine.md
04-responsive-layout-contract.md
05-import-and-legacy-data.md
06-dictionaries-and-localization.md
07-mvp-scope-and-acceptance.md
08-codex-implementation-brief.md
09-operational-and-summary-table-contract.md
10-director-and-subagents.md
11-build-phases.md
12-agent-work-protocol.md
13-state-machine.md
14-calculation-contract.md
15-test-fixtures.md
16-api-contract.md
17-screen-registry.md
18-error-and-edge-cases.md
19-legacy-import-acceptance.md
20-definition-of-done.md
21-sprint-plan.md
22-sprint-handoff-protocol.md
23-legacy-isolation-rule.md
24-secrets-hosting-access-inventory.md
25-final-repository-audit.md
agents/
schemas/
sql/
sprints/
```

## Confirmed

- The folder is now inside the old FinDesk repository.
- Old FinDesk is defined as infrastructure donor only.
- Old docs/logic/tables/calculations are explicitly rejected as v2.0 truth.
- Sprint 01 starts with cleanup and infrastructure donor extraction.
- Sprint 01 must inventory hosting, FTP/SFTP/SSH, DB, env, deployment, domain/DNS/SSL.
- Real secrets must not be committed.
- Clean core schema and category seed are present.
- Director READ FIRST exists.
- Subagent READ FIRST files are present.
- Sprint files 01–08 are present.

## Next action

Start Sprint 01 with a new Director.

Director must read:

```text
FinDesk v2.0/README.md
FinDesk v2.0/FULL_SPEC.md
FinDesk v2.0/21-sprint-plan.md
FinDesk v2.0/22-sprint-handoff-protocol.md
FinDesk v2.0/23-legacy-isolation-rule.md
FinDesk v2.0/24-secrets-hosting-access-inventory.md
FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md
FinDesk v2.0/agents/00-DIRECTOR-READ-FIRST.md
```

No more planning documents are needed before Sprint 01.
