# 22 — Sprint Handoff Protocol

## Purpose

Each sprint closes cleanly and passes only useful project state to the next sprint.

## What carries forward

Only:

- project docs in `FinDesk v2.0/`;
- current sprint file;
- previous sprint final report;
- open blockers;
- explicit decisions;
- files changed;
- test results.

## What does not carry forward

Do not carry:

- chat noise;
- rejected ideas;
- old speculation;
- old FinDesk business logic;
- unwritten assumptions.

## Final report template

```text
Sprint:
Director:
Status: Completed / Failed / Blocked
Goal:
Agents used:
Files changed:
What was completed:
Tests run:
Tests passed:
Tests failed:
Decisions made:
Blocked items:
Risks for next sprint:
What must NOT be changed next:
Recommended next Director focus:
Handoff summary:
```

## Fail rule

If a sprint is not 100% complete, it does not pass.

A failed sprint can produce a recovery sprint, but cannot pretend to be done.
