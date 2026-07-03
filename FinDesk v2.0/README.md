# FinDesk v2.0 — Clean Core MVP

This is the authoritative FinDesk v2.0 package.

The old FinDesk project lives in this repository (`finance.brkovic.ltd`) and may be used only as an infrastructure donor. Do not use old FinDesk documentation, finance logic, database tables, entities, dashboards, categories, reports, calculations, or project discipline as truth for v2.0.

A previous draft of this package was accidentally placed in `vetus-nauta/Revoyacht`. That copy is non-authoritative. This folder is the correct working location.

## Core model

FinDesk v2.0 is built around two table/logical layers:

1. Operational table / live journal — current sequence of financial notes.
2. Summary table / grouped report — generated monthly/yearly summaries from journal entries.

The operational journal is the source of truth. Summary reports are generated, not manually maintained.

## Product principle

One screen = one logic.

The main screen is a current-month notes-style financial feed. The user writes simple records like:

```text
+1000 снял с карты
-250 рыба
-60 Netflix
+5000 charter deposit
```

The system parses, categorizes, calculates, and reports.

The user must not feel that they are writing into an empty form. The current month feed remains visible while entering new records.

## Cash/Card rule

Cash and Card are separate funding flows.

Card-to-cash withdrawal is represented by two records:

```text
Card: -1000 снял с карты
Cash: +1000 снял с карты
```

Both records are valid. Card side is card expense. Cash side is cash income. This is not an error and must not be hidden or neutralized.

## Mandatory read order for Sprint 01

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

## Folder contents

```text
FinDesk v2.0/
├── README.md
├── FULL_SPEC.md
├── 21-sprint-plan.md
├── 22-sprint-handoff-protocol.md
├── 23-legacy-isolation-rule.md
├── 24-secrets-hosting-access-inventory.md
├── 25-final-repository-audit.md
├── agents/
├── schemas/
├── sql/
└── sprints/
```

## Development rule

Start with Sprint 01. Do not write application code before the Director finishes the Sprint 01 plan.

Sprint 01 is not product building. It is cleanup, inventory, and infrastructure donor extraction.
