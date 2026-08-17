# 39 — Hall, Roles, and Accountable Workflow Contract

## Purpose

FinDesk v2.0 must support one user account across many workspaces.

A user can be:

- owner/admin of their own workspace;
- admin/finance manager of a vessel workspace;
- limited employee/assistant inside another workspace;
- a solo user outside any team context.

Role limitations apply per workspace only. A limited employee in one workspace remains a full FinDesk user elsewhere.

## Product Principle

The Hall is the post-auth workspace and role router.

It is not a marketing page and not a dashboard-first replacement for the operational journal.

The Hall answers:

```text
Where am I working now?
What role do I have here?
What needs my action?
Can I open my own full workspace?
```

## Required Hall Areas

Admin / owner view:

```text
My workspaces
Employees / participants
Invitations
Money under report
Submitted reports
Open balances and overruns
Reports / storage
```

Employee / assistant view inside another workspace:

```text
Assigned workspace
My accountable offers
My simple operational report
Submit report
My open balance / overrun
Exit to my FinDesk
```

Solo / personal user view:

```text
My spaces
Create workspace
Invitations waiting
Spaces where I am employee
```

## Roles

MVP roles:

```text
owner
admin
finance
employee
viewer
```

Current v2 roles are:

```text
owner
admin
assistant
viewer
```

The MVP may map `finance` to `admin` and `employee` to a new restricted role, but the product language must be human:

```text
Владелец
Администратор
Финансист
Сотрудник
Только просмотр
```

## Invitation / Referral Flow

Admin creates an invitation:

```text
workspace
email or share link
role
optional name
optional accountable offer
expires_at
```

Employee opens referral link:

1. signs in by email code;
2. accepts invitation;
3. becomes a member of the workspace with the invited role;
4. lands in the limited employee mode for that workspace;
5. can leave that workspace context and open/create personal FinDesk spaces.

Invitation acceptance must be audited.

Security rules:

```text
store only token_hash, never raw token
invite is single-use
invite expires
authenticated email must match invited email
accepted user cannot choose their own role
admin can revoke pending invite
```

Employee invites must not be enabled until employee-scoped reads exist.

Current v2 membership checks allow broad workspace reads for any member. The `employee` role requires scoped visibility before production:

```text
workspace = full workspace visibility
own_entries = only rows created by current user
assigned_actor = only rows linked to assigned actor/member
none = Hall-only / pending access
```

## Accountable Offer

Admin can offer accountable cash:

```text
employee
amount
flow = cash/card/manual
currency
purpose/comment
created_by
status
```

Example:

```text
Admin offers Evgeniy 500 EUR cash under report.
Admin physically gives 500 EUR cash.
Employee accepts the offer.
```

This is not yet an operational expense category.

It creates an accountable position:

```text
issued_to_employee = 500
employee_reported_expenses = 0
employee_returned = 0
employee_overrun = 0
open_amount = 500
```

Accountable offer states:

```text
pending_offer
accepted_by_employee
active
submitted
discrepancy
accepted_by_admin
return_due
reimburse_due
closed
cancelled
rework
```

Pending offer must not affect cash, category totals, lower-accounting totals, or reports.

When employee accepts the offer and confirms the money received, the system creates or links a counted lower-accounting physical movement:

```text
flow = cash
sign = -
amount = offered_amount
direction = out
accounting_section = lower_accounting
accounting_type = accountable_issued
actor = employee
```

This reduces the admin workspace cash balance and creates the accountable issued amount. It is not an operational expense category.

## Employee Simple Operational Mode

The employee does not see the full workspace picture.

Visible:

```text
issued amount
spent amount
remaining / overrun
simple entry feed
new entry input
attachments if enabled later
submit report
```

Hidden:

```text
full workspace cash/card totals
other employees
full summary
dictionary training
workspace settings
admin report storage
```

## Submit / Accept Workflow

Employee submits their report.

Admin reviews and accepts into the common workspace ledger.

Until admin acceptance:

```text
employee entries are employee report draft/submitted data
they do not silently become common operational expenses
employee rows may use assistant_journal / assistant_pending state
```

On admin acceptance:

```text
accepted employee expenses become source entries or linked accepted lines in the common operational ledger
accepted rows become category expenses
cash-paid accepted rows must not reduce cash a second time if the advance issue already reduced cash
the accountable position is recalculated
audit log records the acceptance
```

## Settlement Logic

Definitions:

```text
issued = accountable amount accepted by employee
extra_cash_in = employee cash income inside the accountable report
accepted_cash_expenses = accepted employee cash-paid expenses
accepted_noncash_expenses = accepted employee card/noncash expenses
expected_remaining = issued + extra_cash_in - accepted_cash_expenses
actual_remaining = employee submitted physical cash count
difference = actual_remaining - expected_remaining
cash_to_return_or_rollover = max(actual_remaining, 0)
cash_overrun = max(-expected_remaining, 0)
total_accepted_expense = accepted_cash_expenses + accepted_noncash_expenses
```

If issued amount equals accepted expenses:

```text
issued = 500
accepted_expenses = 500
remaining = 0
overrun = 0
status = closed
```

If employee spent less:

```text
issued = 500
accepted_expenses = 420
remaining = 80
overrun = 0
status = return_due
```

If employee spent more:

```text
issued = 500
accepted_expenses = 560
remaining = 0
overrun = 60
status = reimburse_due
```

Returned cash reduces remaining.

Reimbursed cash closes overrun.

Noncash employee expenses are operational expenses after admin acceptance, but they do not reduce advance cash remaining. They create a settlement/reimbursement exposure if employee used their own funds.

## Report Money Position

For admin and owner reports, physically available money means the full live physical pool:

```text
physically_available_total = admin_cash + employee_held_cash
```

Where:

```text
admin_cash = current physical cash controlled by the workspace admin
employee_held_cash = unresolved accountable cash still held by employees
reimburse_due_to_employees = overrun obligation owed to employees
return_due_from_employees = cash the employee still must return or roll forward
```

Reimbursement due is not physically available money. It is shown next to the pool as an obligation, so the owner does not need to reconstruct the accountable arithmetic manually.

## Financial Guardrails

- Issuing accountable cash is not a category expense by itself.
- Offer creation alone is not a financial event.
- Employee acceptance of received cash is a lower-accounting cash movement.
- Employee accepted expenses become category expenses only after admin acceptance.
- Physical cash movement must remain visible in cash balance where applicable.
- Employee report acceptance must preserve source trace.
- Admin may accept the full submitted amount, a smaller amount, or reject rows for correction.
- Old sent/accepted reports must not mutate silently.
- Workflow state must not be hidden inside raw text, notes, or magic prefixes.
- Workflow state must not be collapsed into `v2_entries.status`.

## MVP Cut Line

MVP must include:

```text
Hall after auth
workspace role awareness
invite link creation
invite acceptance after email auth
limited employee workspace mode
admin accountable offer
employee accept offer
employee simple entries
employee submit report
admin accept report
remaining/overrun calculation
basic audit trail
manual QA screenshots
```

## Sprint Roadmap

### SPRINT-49R — Hall Shell and Workspace Role Router

Status:

```text
accepted as first implemented Hall layer
```

Scope:

```text
post-auth Hall
workspace tiles
workspace role labels
open workspace route
return to Hall
create workspace route
direct ?workspace= compatibility
```

### SPRINT-50R — Scoped Visibility and Employee Role Security Gate

Purpose:

```text
Build the permission boundary before employee invitations exist.
```

Required outcome:

```text
employee-scoped read/write rules are explicit and testable
admin/owner broad workspace access remains intact
assistant/current finance role remains unchanged
no employee invite can be enabled before this passes
```

### SPRINT-51R — Invite and Referral Onboarding

Purpose:

```text
Allow admin/owner to invite a person into one workspace through a single-use link.
```

Required outcome:

```text
token_hash storage
email-bound acceptance
expiry
audit
role assignment
safe Hall membership appearance after login
```

### SPRINT-52R — Employee Simple Operational Journal

Purpose:

```text
Give invited employee a limited workspace mode for personal accountable reporting.
```

Required outcome:

```text
employee sees only their assigned/accountable area
employee can enter simple rows
employee cannot see full operational journal, summary, training, or other employees
admin can still see source trace
```

### SPRINT-53R — Accountable Offer and Employee Submission

Purpose:

```text
Admin offers accountable cash; employee accepts and reports spending.
```

Required outcome:

```text
offer creation has no financial effect
employee acceptance records accountable cash movement
employee submitted rows are pending
unsubmitted remaining/overrun is visible
```

### SPRINT-54R — Admin Acceptance, Merge, and Settlement

Purpose:

```text
Admin accepts employee report into the main workspace without double-counting cash.
```

Required outcome:

```text
accepted rows become operational/category facts
remaining cash due and employee overrun are calculated
rejected rows return to employee correction
accepted reports are immutable without explicit correction flow
```

### SPRINT-55R — Role Workflow MVP Gate

Purpose:

```text
End-to-end manual QA and MVP polish for Hall, invite, employee reporting, and admin settlement.
```

Required outcome:

```text
desktop/tablet/mobile screenshots
negative permission tests
financial invariants
audit evidence
release candidate decision
```

Can be after MVP:

```text
full employee attachment package
automatic email delivery
multi-stage approvals
payroll integration
supplier internet lookup
advanced employee analytics
```

## Not Done If

- Employee can see the full Claudia Z financial picture.
- Employee role in one workspace limits their own personal FinDesk account.
- Accountable cash is silently treated as a normal expense category.
- Admin acceptance is skipped without explicit permission.
- Remaining/overrun balances are hidden.
- Invitation links can be reused forever or accepted by the wrong account without audit.
- Accepted employee rows reduce cash twice.
- Legacy `cash_advances` text markers are copied into v2 instead of structured state.
