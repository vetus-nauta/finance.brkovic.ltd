# Sprint 18 - Director Continuation Gate

## Goal

Close the three-sprint director pass after Sprint 16 evidence audit and Sprint 17 API/schema gap ledger.

## Director rule

The next sprint must implement the highest-risk non-visual gap first. Visual work stays deferred until arithmetic and API behavior are proved.

## Accepted state

- Sprint 16 closed as implementation evidence audit.
- Sprint 17 closed as API/schema gap ledger.
- PHP syntax checks passed for current v2 PHP files.
- v2 remains the only product truth.
- Old branch and routes44 remain excluded.

## Not accepted as done

- Reports engine.
- Month closure behavior.
- Legacy import workflow.
- Attachment endpoints.
- Parse preview route.
- Automated fixture runner.
- Visible journal UX.

## Next sprint

Sprint 19 - Financial Logic and Fixture Runner Implementation.

## Sprint 19 recommended scope

- executable parser for `+`, `-`, and no-sign rows;
- fixture runner for `15-test-fixtures.md`;
- cash/card calculation proof;
- card-to-cash non-duplicate rule;
- commercial income separation;
- Other review status;
- no visual UI changes.

## Visible-change bypass

All visible changes remain deferred.

## Status

Completed as director continuation gate.

## Handoff

Continue with Sprint 19. Build executable financial logic before reports, import, attachments, or UI polish.
