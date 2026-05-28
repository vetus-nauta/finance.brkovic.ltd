# Backend/Data Handoff: Carryover Export Archive

Date: 2026-05-26

## Current State

QA Release Engineer passed the assigned instant field capture slice in run `20260526141856`.

Chief Auditor approved that slice only.

Full release is still blocked because carryover, export, archive, and final release gate are not proven.

## Backend/Data Task

Trace the backend/API truth path for:

- final report fixation;
- open-period carryover;
- historical report preservation;
- export source selection after a finalized report exists;
- archive listing and filters;
- employee-linked live reports;
- card stream zero physical-cash effect;
- group scope defaults.

## Key Scenario

Use the business scenario:

```text
€1000 income -> €600 expense -> €400 carryover
```

Expected meaning:

- the old report may still show `€1000` income and `€600` expense as historical truth;
- the new open period must not show old `€1000` as new current income;
- the new open period starts from `€400` carryover;
- card expense must not reduce physical cash;
- archive must preserve old evidence without mutating money.

## Output

Write findings to:

- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

Do not change formulas, backend/API behavior, UX, or database schema during this trace.
