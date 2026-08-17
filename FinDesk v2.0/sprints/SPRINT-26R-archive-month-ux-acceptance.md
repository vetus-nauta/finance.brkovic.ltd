# SPRINT-26R — Archive Month UX Acceptance

## Director Opening

Accept the operational archive-month behavior after Claudia Z data was split into:

- current operational workspace;
- raw archive history for dictionary learning;
- current-chain balance guard.

This sprint does not change finance formulas, parser behavior, import rules, or report arithmetic.

## Agent Assignments

- Frontend Interaction Agent — verify archive picker, current return, active latest row, and unsaved-edit guard behavior.
- QA, Audit, and Acceptance Agent — verify browser evidence and regression gates.
- Financial Logic Engine Agent — confirm no formula/import/parser/report mutation is introduced by UI acceptance.

## Implemented / Verified Scope

- Archive button opens a modal month picker.
- Picker defaults to the previous calendar month.
- Opening an archive month loads the standard operational journal/check workspace for that selected period.
- `Current` returns to the current period and the current feed does not contain archive rows.
- Current/Archive switching keeps body/page scroll locked to the app shell.
- Operational journal and structured check keep linked rows and row-number confidence.
- If a row is being edited and the user attempts to switch month:
  - `Cancel` keeps editing and does not switch period;
  - `Save` persists the edit and continues to the selected archive period;
  - `Discard` drops the draft and continues to the selected archive period.
- The current period opens with the latest operational entry active and a mirrored empty draft row at the bottom.

## Evidence

Updated browser smoke coverage:

```text
Archive month switching: OK
Archive unsaved edit guard: OK
```

The new browser scenario covers:

- edit a current row;
- request archive month load;
- assert unsaved modal is shown;
- cancel and verify draft is still present;
- request archive again;
- save from the modal and verify PATCH completes before archive switch;
- return current and verify saved row is present;
- edit another row;
- discard from the modal and verify the draft does not persist after returning current.

Regression gates:

```bash
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2:browser
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
```

Results:

```text
node --check: OK
npm run smoke:v2:browser: OK
npm run smoke:v2: OK
npm run test:v2:fixtures: PASS (19)
npm run smoke:v2:http: OK
```

## Files

- `scripts/v2_operational_browser_smoke.cjs`

## Acceptance

ACCEPTED for local Archive Month UX behavior.

Remaining product work is not in archive switching itself. Next sprint should implement the separate lower accounting block for debt/loan/return/accountable rows so those rows stop living only as semantic review context.
