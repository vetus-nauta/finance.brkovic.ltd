# Production Multi-Employee Financial Control

Date: 2026-05-27

Owner: Product Finance Architect

Status: expected financial control for QA scenario.

## Scenario

- Admin receives `EUR 1000`.
- Admin issues accountable cash:
  - employee 1: `EUR 135`;
  - employee 2: `EUR 94`;
  - employee 3: `EUR 117`.
- Admin spends: `20 + 45 + 17 + 4 = EUR 86`.
- Employee 1 spends: `6 + 9 + 43 + 10 = EUR 68`.
- Employee 2 spends: `12 + 23 + 41 + 54 = EUR 130`.
- Employee 3 spends: `EUR 0`.

## Expected Totals

Admin:

- received: `EUR 1000`;
- issued to employees: `EUR 346`;
- own expenses: `EUR 86`;
- admin cash left: `1000 - 346 - 86 = EUR 568`.

Employees:

- employee 1: received `EUR 135`, spent `EUR 68`, remaining `EUR 67`;
- employee 2: received `EUR 94`, spent `EUR 130`, overrun `EUR -36` / reimbursement due `EUR 36`;
- employee 3: received `EUR 117`, spent `EUR 0`, remaining `EUR 117`.

Group:

- total received: `EUR 1000`;
- total expenses: `86 + 68 + 130 = EUR 284`;
- net group balance: `1000 - 284 = EUR 716`;
- participant balance control: `568 + 67 - 36 + 117 = EUR 716`.

## Acceptance Meaning

The final report and archive must preserve all four dimensions:

- cash received by admin;
- accountable cash issued to each employee;
- spent money by admin and each employee;
- remaining/responsibility by holder, including employee 2 overrun and employee 3 no-spend remainder.

Employee 3 must not get fake zero expense. The product may show the `EUR 117` as open accountable remainder/carryover or as returned/closed cash only if the UI explicitly performs that action.

Employee 2 overrun must not disappear after submit/accept/finalization. It must remain visible as overrun/reimbursement/discrepancy in at least the UI, final package, export, or QA must mark it as a P0 product issue.

## P0 Criteria

- Total expenses are not `EUR 284`.
- Net group balance is not `EUR 716`.
- Admin cash left is not `EUR 568`, unless employee 3 return-cash action was explicitly performed and recorded.
- Employee 1 remaining is not `EUR 67`.
- Employee 2 overrun/reimbursement `EUR 36` is lost, inverted, or hidden.
- Employee 3 remaining `EUR 117` is lost or converted into a fake expense.
- UI, Excel/export, final package, and archive disagree on any money value.
- Submitted cards do not move to archive/final package as expected.
- The report cannot be saved/exported/printed by the available MVP path.
