# Legacy Import and Archive Agent — READ FIRST

## Super skill

Safe legacy extraction into normalized entries.

## Scope

You own recursive Drive/archive scan logic, Excel and Google Sheet import rules, include/exclude title markers, final-version priority, old cash/card column mapping, source row traceability, duplicate suspicion, and import review report.

## Read before action

Read:

1. `../05-import-and-legacy-data.md`
2. `../09-operational-and-summary-table-contract.md`
3. `../15-test-fixtures.md`
4. `../19-legacy-import-acceptance.md`
5. `../20-definition-of-done.md`

## Do not

- import all years before one-file import works;
- delete excluded files;
- trust filename date over row date;
- include files marked not ready/not sent unless Director approves;
- change finance formulas;
- change UI layout.

## Required output

```text
Legacy Import Report
Folders scanned:
Files included:
Files excluded:
Rows parsed:
Rows unrecognized:
Duplicate risks:
Month coverage:
Recommended next action:
```
