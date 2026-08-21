# 35 — Service Card Accountability

## Purpose

FinDesk v2.0 must support non-cash accountability when an administrator gives a service card to an employee.

This must work through the same simple message and confirmation logic as cash accountability.

## Core idea

A service card is a payment instrument.

When the administrator gives a card to an employee, the card becomes the employee's responsibility from the confirmed handover time until the card is returned and the employee report is submitted.

## Main case: admin gives service card to employee

Administrator sends a message:

```text
I handed you service card Visa 1234 on 2026-07-05 at 14:30.
```

Employee confirms.

After confirmation, FinDesk creates a card custody period:

```text
card_id: Visa 1234
holder_user: employee
from: confirmed handover date/time
to: open until return
status: in_employee_custody
```

## Accounting meaning

The administrator has not given cash.

No cash transfer is created.

Instead, the responsibility for all expenses on that card during the custody period belongs to the employee.

The employee must explain and report those card expenses.

## Employee responsibility

During the custody period, card spending is attached to the employee accountable report.

Employee period report must include:

```text
service card received
custody period
card expenses during custody
attachments/receipts
comments
card returned or still in custody
```

## Admin report

Administrator report must show:

```text
service cards with employees
card holder
custody start date/time
card expenses pending employee report
card returned status
```

## Card return

Return works by message and confirmation.

Employee sends message:

```text
I returned service card Visa 1234 on 2026-07-08 at 18:00.
```

Administrator confirms.

System closes the custody period:

```text
status: returned
returned_at: confirmed return date/time
```

## Expense assignment

Any card expense dated inside the confirmed custody period is assigned to the employee's card subreport.

If imported bank/card transactions arrive later, FinDesk matches them by card and transaction date/time.

If exact time is not available, use date and require review for boundary-day transactions.

## Boundary cases

### Expense before handover

Expense before confirmed handover remains administrator responsibility unless manually assigned.

### Expense after return

Expense after confirmed return remains administrator responsibility unless manually assigned.

### Same-day boundary

If card handover or return happened during a day and transaction has only date without time, mark it for review.

### Card still not returned

If card is not returned, the custody period remains open.

Admin report shows card still with employee.

### Employee submits report before return

Employee can submit card expense report for selected period, while card custody may remain open.

Next period carries forward custody state.

## Data model draft

Add service card entity:

```text
id
workspace_id
card_label
last4
currency
status
created_at
archived_at
```

Add card custody entity:

```text
id
workspace_id
card_id
admin_user_id
holder_user_id
handover_message_id
return_message_id nullable
handover_confirmed_at
return_confirmed_at nullable
status
created_at
updated_at
```

Add card expense assignment fields:

```text
entry_id
card_id
custody_id nullable
responsible_user_id nullable
assignment_status
review_reason nullable
```

## Operational input window rule

Card handover and return must be visible in the operational window.

They are not hidden admin settings.

The user must see:

```text
card handed to employee
confirmed or pending
card returned or still open
expenses assigned to employee
review boundary items
```

## Minimal implementation sequence

### Step 1 — Card registry

Create service card list:

```text
card label
last4
currency
active/archived
```

### Step 2 — Card handover message

Admin sends card handover message.

Employee confirms or rejects.

### Step 3 — Custody period

On confirmation, system opens custody period for the employee.

### Step 4 — Card expense records

Card expenses are recorded or imported as usual.

If expense falls inside custody period, assign to employee responsibility.

### Step 5 — Employee card subreport

Employee report includes card expenses made during custody.

### Step 6 — Card return message

Employee sends return message.

Admin confirms.

System closes custody period.

### Step 7 — Admin report integration

Admin report shows:

```text
cash with employees
service cards with employees
pending employee cash reports
pending employee card reports
```

## UX rule

Do not create a complicated card-control module first.

Use the same pattern:

```text
message -> confirmation -> custody period -> expense responsibility -> subreport
```

## Director gate

Do not approve service card accountability unless:

- card handover requires confirmation;
- card return requires confirmation;
- custody period is visible;
- card expenses during custody are assigned to employee report;
- boundary transactions are reviewed;
- admin report shows cards with employees;
- operational window shows handover, return, and responsibility state.
