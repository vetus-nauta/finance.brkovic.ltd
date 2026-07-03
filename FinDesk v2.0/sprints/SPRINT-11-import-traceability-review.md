# Sprint 11 - Import Traceability and Legacy Source Review

## Goal

Close the legacy import path as a traceable, review-first pipeline without making old FinDesk logic authoritative.

## Depends on

- Sprint 10 handoff
- `14-legacy-import-rules.md`
- `16-api-contract.md`
- `20-definition-of-done.md`

## Director rule

Legacy files are data sources only. They do not define product logic, category truth, or UI behavior.

## Agents

- Director
- Import Agent
- Financial Logic Agent
- QA/Audit Agent

## Scope

- source file and row traceability;
- included/excluded/unrecognized import report;
- cash/card mapping checks;
- excluded title markers;
- totals comparison;
- duplicate-suspect review;
- safe failure states for ambiguous rows.

## Visible-change bypass

Any import review screen change is skipped. This sprint accepts only import contracts, traceability rules, and test expectations.

## Forbidden

- no direct trusted import into final money state without review;
- no use of old categories as final category truth;
- no silent exclusion of rows;
- no UI redesign.

## Exit criteria

- import rows can be traced to source;
- excluded/unrecognized rows remain visible in reports;
- totals comparison is required;
- old logic remains rejected.

## Final handoff

Pass import evidence, traceability risks, and unresolved mapping cases to Sprint 12.
