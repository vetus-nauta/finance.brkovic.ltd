# Sprint 13 - Dictionary, Categories, and Localization QA

## Goal

Close the category dictionary and language-sensitive parsing expectations without changing visible screens.

## Depends on

- Sprint 12 handoff
- `05-category-system.md`
- `15-test-fixtures.md`
- `17-responsive-ux-rules.md`

## Director rule

Category meaning must stay stable across Russian/English inputs, actor names, and ambiguous yacht operations.

## Agents

- Director
- Financial Logic Agent
- QA/Audit Agent
- UX Layout Agent as reviewer only

## Scope

- MVP category codes;
- rule priority for commercial income, cash top-up, tender/fuel ambiguity, and actors;
- Other fallback behavior;
- actor-vs-category separation;
- copy/dictionary risks;
- no visual implementation.

## Visible-change bypass

Any labels, menus, language toggle, or visible copy edits are deferred. This sprint closes the data and parsing meaning only.

## Forbidden

- no category changes that break fixture expectations;
- no person name as automatic category;
- no hiding Other review;
- no UI copy pass as required output.

## Exit criteria

- category semantics are stable;
- actor/category separation is explicit;
- Other fallback remains visible and reviewable;
- localization risks are recorded for later visual/copy work.

## Final handoff

Pass dictionary decisions and ambiguous parsing risks to Sprint 14.
