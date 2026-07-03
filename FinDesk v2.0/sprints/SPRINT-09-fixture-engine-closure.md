# Sprint 09 - Fixture and Engine Closure

## Goal

Close the deterministic finance engine as a non-visual product gate after the original Sprint 08 release-candidate pass.

## Depends on

- `15-test-fixtures.md`
- `20-definition-of-done.md`
- Sprint 08 release-candidate contract
- v2 clean core files only

## Director rule

This sprint uses `FinDesk v2.0/` as the only product truth. Old FinDesk calculations, dashboards, and routes are not valid sources.

## Agents

- Director
- Financial Logic Agent
- Backend Core Agent
- QA/Audit Agent

## Scope

- verify all fixture expectations remain canonical;
- confirm parser/counting rules for `+`, `-`, and no-sign rows;
- confirm Cash/Card separation;
- confirm card-to-cash two-record model;
- confirm commercial income is separate from opening balance;
- confirm Other review remains visible and counted as expense;
- record gaps as recovery items without changing UI.

## Visible-change bypass

If a check requires visible layout work, skip the visible change and record it for a later UX sprint. Do not block this sprint on appearance.

## Forbidden

- no redesign;
- no routes44 work;
- no reuse of old finance formulas;
- no hidden arithmetic changes without fixtures;
- no accepting fixture drift as product behavior.

## Exit criteria

- fixture list is treated as contract;
- arithmetic gates are defined;
- no-sign rows remain visible but uncounted;
- card-to-cash is explicitly not a duplicate/error;
- next sprint receives only non-visual engine risks.

## Final handoff

Pass confirmed fixture contract, open arithmetic risks, and any skipped visual work to Sprint 10.
