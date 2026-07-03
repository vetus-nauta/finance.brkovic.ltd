# Sprint 05 — Legacy Import MVP

## Goal

Import one legacy Excel or Sheet file safely into normalized entries.

## Depends on

Sprint 04 final report.

## Director rule

Use a new Director. Start from project docs and Sprint 04 handoff only.

## Agents

- Legacy Import and Archive Agent
- Financial Logic Engine Agent
- Data and Backend Core Agent
- QA/Audit Agent

## Scope

- one-file import adapter;
- old cash/card column mapping;
- source row traceability;
- include/exclude title markers;
- final-version priority;
- import review report;
- source totals comparison.

## Forbidden

- no full history import;
- no silent duplicate deletion;
- no trusting filename date over row date;
- no UI expansion beyond import review needs.

## Exit criteria

- one legacy file imports;
- normalized entries are created;
- source rows are traceable;
- excluded rows/files are reported;
- duplicate suspects are marked;
- QA approves import report.

## Handoff to Sprint 06

Include import adapter status, mapping problems, accuracy risks, test file used, and files changed.
