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

## Recovered Sprint 09-15 Extension

This extension restores the numbered v2 sprint chain expected by project state. It does not replace Sprints 01-08 and does not claim visible UI implementation.

```text
Sprint 09 - Fixture and Engine Closure
Sprint 10 - Data Integrity, Audit Log, and Closed Month Control
Sprint 11 - Import Traceability and Legacy Source Review
Sprint 12 - Attachment Evidence Contract
Sprint 13 - Dictionary, Categories, and Localization QA
Sprint 14 - Staging, Deployment, and Secrets Readiness
Sprint 15 - Director Acceptance and Continuation Gate
```

## Sprint 09

Goal: close deterministic fixture and financial engine expectations as a non-visual gate.

## Sprint 10

Goal: close data integrity, audit log, and closed-month control expectations.

## Sprint 11

Goal: close legacy import traceability without making old logic authoritative.

## Sprint 12

Goal: close attachment evidence rules without changing the money source of truth.

## Sprint 13

Goal: close dictionary, category, and localization QA semantics.

## Sprint 14

Goal: close staging, deployment, and secrets readiness.

## Sprint 15

Goal: accept the recovered v2 sprint chain and hand off to Sprint 16.

## Director Sprint 16-18 Extension

This extension closes three director governance sprints after the recovered Sprint 09-15 chain.

```text
Sprint 16 - Implementation Evidence Audit and Gap Closure
Sprint 17 - API and Schema Gap Ledger
Sprint 18 - Director Continuation Gate
```

## Sprint 16

Goal: audit real repository implementation evidence and separate code proof from documentation-only claims.

## Sprint 17

Goal: lock the API/schema gap ledger and prevent unimplemented routes from being treated as complete.

## Sprint 18

Goal: close the director continuation gate and hand off to Sprint 19.
