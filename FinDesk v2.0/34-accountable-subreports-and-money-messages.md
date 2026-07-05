# 34 — Accountable Subreports and Money Messages

## Purpose

FinDesk v2.0 must support simple accountable money flow between users without turning the product into a complex approval system.

The product works through accounts.

Any user can send a period report to another user.

For group/workspace accounting, this report becomes a separate subreport attached to the administrator or financier report.

## Core idea

Money transfer between administrator and employee is created through a message.

The message is not just text.

It creates linked operational records after confirmation.

## Main case: admin gives cash to employee

Administrator sends message:

```text
I gave you 500 EUR for expenses.
```

Employee confirms.

After confirmation, system creates two linked records:

Admin side:

```text
-500 to Employee
```

Employee side:

```text
+500 from Admin
```

Both records are highlighted as accountable transfer records.

They are linked by one transfer id.

## Why this is correct

This keeps the product simple.

No complex buttons, links, hidden ledgers, or separate cash-transfer module are needed.

The user sends a message. The other user confirms. FinDesk creates the arithmetic records.

## Accountable balance

Until the employee submits a report or returns money, the administrator report shows:

```text
Cash with employees: X
```

Employee report shows:

```text
Received from admin: X
Spent: Y
Returned: Z
Balance on hand: X - Y - Z
```

The administrator can still submit their own report while the employee has money on hand.

The admin report must clearly show how much remains with employees.

## Employee period report

Any user can send a report for a selected period.

Example:

```text
Employee sends report for July 1-31.
```

The report attaches to the administrator/financier period report as a subreport.

The employee subreport includes:

```text
opening accountable balance
money received from admin
expenses
money returned to admin
closing accountable balance
attachments
comments
```

## Return of money

Return works the same way through message and confirmation.

Employee sends message:

```text
I returned 120 EUR to you.
```

Administrator confirms.

System creates two linked records:

Employee side:

```text
-120 returned to Admin
```

Admin side:

```text
+120 returned from Employee
```

Both records are linked by one transfer id.

## Partial return

If employee returns only part of the money, the remaining accountable balance stays open.

Example:

```text
Received: 500
Spent: 250
Returned: 100
Still on hand: 150
```

## Period logic

Transfers and reports must be period-aware.

If money was issued in one period and report is submitted in another period, the balance carries forward.

No silent reset at month boundary.

## Operational table rule

These records must appear in the operational input window.

They are not hidden system-only records.

The user must see:

```text
message status
linked plus/minus records
counterparty
confirmation status
accountable balance impact
```

## Statuses

```text
message_sent
confirmed
rejected
cancelled
reported
partially_returned
closed
```

## Minimal implementation sequence

### Step 1 — Data model

Add accountable transfer entity:

```text
id
workspace_id
from_user_id
to_user_id
amount
currency
message_text
status
created_at
confirmed_at
cancelled_at
linked_admin_entry_id
linked_employee_entry_id
period_id nullable
```

Add subreport entity:

```text
id
workspace_id
owner_user_id
submitted_to_user_id
period_start
period_end
status
opening_accountable_balance
received_total
spent_total
returned_total
closing_accountable_balance
parent_report_id nullable
created_at
submitted_at
accepted_at
```

### Step 2 — Message confirmation

Build message flow:

```text
sender creates money message
receiver confirms or rejects
on confirm, system creates linked records
```

### Step 3 — Operational records

Records must appear in both users' operational journals.

Admin gives money:

```text
admin: - amount to employee
employee: + amount from admin
```

Employee returns money:

```text
employee: - amount returned to admin
admin: + amount returned from employee
```

### Step 4 — Accountable balance calculation

For each employee:

```text
on_hand = received_from_admin - employee_expenses - returned_to_admin
```

For admin:

```text
cash_with_employees = sum(open employee on_hand balances)
```

### Step 5 — Employee subreport

User selects period and sends report.

System generates subreport from that user's operational records.

Subreport attaches to admin/financier report.

### Step 6 — Admin report integration

Admin period report shows:

```text
admin own cash
cash with employees
employee subreports attached
employee balances still open
```

### Step 7 — Closure rules

Employee accountable balance closes only when:

```text
all received money is either spent in accepted report or returned
```

Otherwise the balance remains open and carries forward.

## UX rule

Do not create many buttons.

Primary interaction is:

```text
message -> confirmation -> linked records -> report/subreport
```

## Director gate

Do not approve group/accountable work unless:

- transfer creates linked records on both sides;
- confirmation is required before arithmetic counts;
- employee report attaches as subreport;
- admin report shows money with employees;
- partial return and carry-forward work;
- all records remain visible in operational input window.
