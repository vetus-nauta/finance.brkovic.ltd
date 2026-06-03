# A. Usov Final QA Scenario - 2026-06-03

Status: local QA passed.

Scope:

- admin account: `a.usov@mail.com`;
- local group: `FinDesk A. Usov Final QA 2026-06-03`;
- group id: `275`;
- finalized report id: `8`;
- production deploy: not performed.

## What Was Done

- Old same-prefix local QA scenario was soft-archived before the clean run.
- New group was created for `a.usov@mail.com`.
- Five employee users were invited and joined through the normal invite flow.
- Admin entered the common cash receipt: `65,765 EUR`.
- Admin issued money to five employees through FinDesk transfers:
  - Employee 1: `4,000 EUR`;
  - Employee 2: `3,000 EUR`;
  - Employee 3: `700 EUR`;
  - Employee 4: `7,000 EUR`;
  - Employee 5: `300 EUR`.
- Each employee confirmed the issued money before using Live Journal.
- Each employee spent most of the issued money and submitted one journal.
- Admin spent most of admin-held money and submitted one journal.
- Admin attached all six journals and finalized one common report.
- Archive export API confirmed the report is present in the archive package.

## Final Accounting

Cash summary:

- received: `65,765 EUR`;
- issued internally to employees: `15,000 EUR`;
- spent by admin: `47,000 EUR`;
- spent by employees: `13,600 EUR`;
- total spent: `60,600 EUR`;
- final remaining: `5,165 EUR`.

Remaining by participant after finalization:

- Admin `a.usov@mail.com`: `3,765 EUR`;
- Employee 1: `380 EUR`;
- Employee 2: `270 EUR`;
- Employee 3: `60 EUR`;
- Employee 4: `650 EUR`;
- Employee 5: `40 EUR`.

## QA Checks

Passed:

- pending transfer blocks employee Live Journal before confirmation;
- confirmed transfer creates employee journal base amount;
- six journals became ready for report assembly;
- six journals were attached to the common report;
- final report status is `finalized`;
- final report totals are balanced;
- archive export contains report id `8`;
- new active Live Journals are empty after finalization;
- all participant carry-forward balances are correct.

Active journals after finalization:

- Admin tape `464`: records `0`, remaining `3,765 EUR`;
- Employee 1 tape `476`: records `0`, remaining `380 EUR`;
- Employee 2 tape `478`: records `0`, remaining `270 EUR`;
- Employee 3 tape `480`: records `0`, remaining `60 EUR`;
- Employee 4 tape `482`: records `0`, remaining `650 EUR`;
- Employee 5 tape `484`: records `0`, remaining `40 EUR`.

## Engineering Notes

Two local fixes were required:

- `app/findesk_phase2.php`: report summary now treats confirmed employee transfers as internal issued money. Internal issue is not counted as a final expense and is removed from duplicated received/remaining totals.
- `app/on_the_go.php`: old empty group-journal auto-sync no longer overwrites empty FinDesk Phase 2 journals after transfers or reports exist in the group.

This was necessary because the old group balance layer did not understand the new FinDesk transfer/report lifecycle.

## Gate

Local API QA is complete for this scenario.

Still not covered:

- real-device physical mobile QA;
- production deployment;
- visual print layout QA in browser.
