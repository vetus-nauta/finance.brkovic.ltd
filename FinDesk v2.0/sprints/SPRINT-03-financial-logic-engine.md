# Sprint 03 — Financial Logic Engine

## Goal

Implement deterministic financial logic on top of the clean core.

## Depends on

Sprint 02 final report.

## Director rule

Use a new Director. Start from project docs and Sprint 02 handoff only.

## Agents

- Financial Logic Engine Agent
- Localization and Linguistic Rules Agent
- Data and Backend Core Agent
- QA/Audit Agent

## Scope

- parser;
- strict plus/minus rule;
- invalid visible rows;
- cash balance recalculation;
- card expense logic;
- approved card-to-cash model;
- commercial income category;
- other expenses fallback;
- category suggestion engine v1;
- fixture tests.

## Forbidden

- no UX build;
- no full import;
- no charts;
- no forecast;
- no old calculations.

## Exit criteria

- all calculation fixtures pass;
- Cash/Card logic matches contract;
- commercial income is separate;
- no-sign rows are visible but not counted;
- Other expenses queue works at data level.

## Handoff to Sprint 04

Include formulas implemented, parser behavior, failed edge cases, tests, and files changed.
