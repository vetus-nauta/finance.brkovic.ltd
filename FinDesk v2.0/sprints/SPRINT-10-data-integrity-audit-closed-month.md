# Sprint 10 - Data Integrity, Audit Log, and Closed Month Control

## Goal

Close the non-visual integrity rules around money-changing operations, auditability, and closed months.

## Depends on

- Sprint 09 handoff
- `16-api-contract.md`
- `20-definition-of-done.md`

## Director rule

Money state must be explainable from entries and audit records. Reports are generated output, not source of truth.

## Agents

- Director
- Backend Core Agent
- Financial Logic Agent
- Security/Privacy Agent
- QA/Audit Agent

## Scope

- audit log coverage for create/update/delete and category changes;
- closed month modes: correction, recalculate chain, cancel;
- prevention of silent recalculation in closed periods;
- duplicate-suspect status as review state, not automatic deletion;
- API response sufficiency for money-changing operations;
- data-level checks only.

## Visible-change bypass

Any modal, drawer, prompt, or visual flow required by closed-month control is deferred. This sprint records the backend/data contract first.

## Forbidden

- no silent closed-month mutation;
- no deleting financial history as a fix;
- no front-end guessing of balances;
- no broad API expansion beyond the clean contract.

## Exit criteria

- closed-month decision paths are documented;
- audit log requirements are explicit;
- money API responses have no required guessing;
- visible prompt work is separated from data integrity work.

## Final handoff

Pass integrity decisions, audit requirements, and closed-month risks to Sprint 11.
