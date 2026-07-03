# 21 — Sprint Plan

## Rule

FinDesk v2.0 is delivered through complete, closed sprints.

Each sprint has:

- its own Director;
- its own agent group;
- fixed goal;
- 100% completion rule;
- final sprint report;
- clean handoff to the next Director.

A sprint is not complete until its exit criteria are met.

## Sprints

```text
Sprint 01 — Legacy cleanup and infrastructure donor extraction
Sprint 02 — Clean core foundation
Sprint 03 — Financial logic engine
Sprint 04 — Summary, reports, and month control
Sprint 05 — Legacy import MVP
Sprint 06 — Main journal UX shell
Sprint 07 — Integration hardening and attachments
Sprint 08 — QA, acceptance, and MVP release candidate
```

## Sprint 01

Goal: clean old FinDesk and keep only safe infrastructure for the new MVP.

Scope:

- inspect old FinDesk project;
- identify secrets/env/config needed by v2.0;
- inventory hosting, FTP/SFTP/SSH, database, deployment, domain/DNS/SSL;
- identify infrastructure donors;
- isolate old business logic;
- reject old documentation as product truth;
- prepare clean v2.0 namespace/module.

Forbidden:

- no reuse of old finance tables;
- no reuse of old finance entities;
- no reuse of old calculations;
- no old dashboard logic;
- no UX build;
- no parser build;
- no reports build;
- no real secrets committed.

Exit criteria:

- donor report complete;
- secrets/env inventory complete without values;
- production access inventory complete;
- old logic isolated;
- clean v2.0 area ready;
- final sprint report filed.

## Sprint 02

Goal: create clean technical foundation.

- schema;
- workspaces;
- members;
- flows;
- entries;
- category seed;
- minimal API;
- audit log base.

## Sprint 03

Goal: deterministic financial logic.

- parser;
- plus/minus rule;
- cash balance;
- card expenses;
- card-to-cash model;
- commercial income;
- other expenses;
- category suggestions;
- fixtures.

## Sprint 04

Goal: generated reports and month control.

- monthly summary;
- category matrix;
- yearly base;
- Other review;
- month close;
- correction/recalculate/cancel.

## Sprint 05

Goal: one-file legacy import MVP.

- old cash/card column mapping;
- source traceability;
- exclude markers;
- import review;
- totals comparison.

## Sprint 06

Goal: main journal UX shell.

- notes-style current month feed;
- input bar;
- Cash/Card switch;
- detail drawer;
- mobile notes system;
- horizontal structured view;
- desktop/iPad full workspace.

## Sprint 07

Goal: integration hardening.

- auth if safe;
- roles;
- attachments;
- category reassignment;
- audit log;
- integration tests.

## Sprint 08

Goal: release candidate.

- all fixtures;
- arithmetic tests;
- parser tests;
- import tests;
- responsive tests;
- Definition of Done;
- release candidate report.
