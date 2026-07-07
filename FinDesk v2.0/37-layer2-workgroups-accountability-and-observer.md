# 37 — Layer 2: Workgroups, Accountability, Service Cards, Observer

## Status

This is the consolidated Layer 2 strategy for FinDesk v2.0.

Layer 2 must not start before Layer 1 is audited and accepted.

Layer 1 must prove:

```text
accounts
workspaces
operational input window
entries
Cash/Card flows
parser for + / - / no-sign rows
summary screen
report calculation
print / send / storage basics
fixture or QA checks
```

If Layer 1 is not accepted, Layer 2 is blocked.

## Source of truth

Repository:

```text
vetus-nauta/finance.brkovic.ltd
```

Working folder:

```text
FinDesk v2.0/
```

This document consolidates the ideas from:

```text
34-accountable-subreports-and-money-messages.md
35-service-card-accountability.md
```

It also corrects terminology: do not build a rigid corporate role model too early.

## Core idea

Layer 2 adds work between accounts inside a workgroup.

Main pattern:

```text
workgroup member
-> financial message
-> confirmation
-> linked operational records or custody period
-> accountable balance
-> subreport
-> group lead report
-> observer snapshot
```

Do not create a heavy ERP.

Keep the user flow simple:

```text
write message
confirm
records appear
report is generated
```

## Terminology

Avoid hardcoded roles like:

```text
admin
employee
viewer
financier
```

Use neutral product terms:

```text
user
workgroup
workgroup creator / group lead
workgroup member
accountable holder
money holder
card holder
report sender
report receiver
external observer
```

Principle:

```text
A person is not permanently defined by role.
A confirmed event creates responsibility.
```

Examples:

```text
User receives money and confirms -> becomes accountable money holder for that amount.
User receives service card and confirms -> becomes card holder for custody period.
User submits report -> becomes report sender for that period.
```

## Entry hall / context selection

After login, user sees a hall:

```text
My personal FinDesk
Workgroup: Claudia Z
Workgroup: Charter July
Workgroup: Family Office
```

Personal FinDesk remains full personal access.

Workgroup context shows only the user's own scope unless the user is the group lead.

## Workgroup financial roster

When a user accepts a workgroup invite, the user becomes a workgroup member and appears in the financial roster for that workgroup.

Financial roster is not a global contact list.

It is used for:

```text
money transfer messages
money return messages
service card handover messages
service card return messages
report requests
subreport submission
finance reminders
```

A member is available as financial recipient only inside the accepted workgroup.

## Operational window remains primary

Layer 2 does not replace the operational input window.

All accountable events must be visible in the operational flow:

```text
pending money message
confirmed money transfer
money returned
card handed over
card returned
subreport sent
subreport accepted or returned for revision
open accountable balance
open card custody
```

Do not hide these events in settings or a separate heavy module.

## Cash accountability

### Money given

Group lead sends a message:

```text
I gave you 500 EUR for expenses.
```

Member confirms.

System creates two linked operational records:

```text
Group lead side: -500 to member
Member side: +500 from group lead
```

Both records share one transfer id.

No arithmetic before confirmation.

### Money returned

Member sends a message:

```text
I returned 120 EUR.
```

Group lead confirms.

System creates two linked operational records:

```text
Member side: -120 returned to group lead
Group lead side: +120 returned from member
```

Partial return is allowed.

Remaining balance stays open.

### Accountable balance

For member:

```text
on_hand = received_from_group_lead - accepted_expenses - returned_to_group_lead
```

For group lead:

```text
cash_with_members = sum(open member on_hand balances)
```

Group lead can submit own period report while members still have open balances, but report must clearly show cash with members.

## Member subreport

Any workgroup member can send a report for selected period.

The report becomes a subreport attached to the group lead period report.

Subreport includes:

```text
opening accountable balance
money received
expenses
money returned
closing accountable balance
attachments
comments
unresolved items
```

Member does not close the whole workgroup report.

Member submits their own subreport to the group lead.

## Service card accountability

A service card is not a cash transfer.

It is a payment instrument under custody.

### Card handover

Group lead sends message:

```text
I handed you service card Visa 1234 on 2026-07-05 14:30.
```

Member confirms.

System opens custody period:

```text
card: Visa 1234
holder: member
from: confirmed handover date/time
to: open until return
status: in_custody
```

No cash plus/minus entries are created by card handover.

### Card expenses

Card expenses inside custody period are assigned to the card holder's subreport.

If imported card transactions arrive later, match by card and date/time.

If exact time is missing on boundary day, mark for review.

### Card return

Member sends message:

```text
I returned service card Visa 1234 on 2026-07-08 18:00.
```

Group lead confirms.

System closes custody period.

### Boundary rules

```text
expense before handover -> group lead responsibility
expense inside custody -> card holder responsibility
expense after return -> group lead responsibility
same-day without time -> review
card not returned -> custody remains open
member can submit period report even if card custody remains open
```

## External observer

External observer is not a workgroup member.

Observer does not need an account.

Observer receives a private read-only link, for example via WhatsApp.

Observer sees only published submitted state.

Observer must not see:

```text
operational journal
current unsent entries
drafts
internal messages
confirmation workflow
working front
edit buttons
```

Two share modes:

```text
fixed_report = one specific report
latest_submitted_state = same link always shows latest submitted state
```

Layer 2 MVP priority:

```text
latest_submitted_state
```

Minimum safety:

```text
long private token
read-only
active / revoked
no operational data
latest snapshot only
```

Later:

```text
PIN
expiry
watermark
open log
download allowed toggle
```

## Minimal data entities

### workspace_members

```text
id
workspace_id
user_id
status: invited | accepted | suspended | removed
created_at
accepted_at
```

### financial_messages

```text
id
workspace_id
from_user_id
to_user_id
message_type
message_text
status: pending | confirmed | rejected | cancelled
created_at
confirmed_at
```

### accountable_transfers

```text
id
workspace_id
from_user_id
to_user_id
amount
currency
transfer_type: cash_given | cash_returned
message_id
status
linked_from_entry_id
linked_to_entry_id
created_at
updated_at
```

### accountable_subreports

```text
id
workspace_id
owner_user_id
submitted_to_user_id
period_start
period_end
status: draft | submitted | accepted | returned_for_revision | closed
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

### service_cards

```text
id
workspace_id
card_label
last4
currency
status: active | archived
created_at
archived_at
```

### card_custody_periods

```text
id
workspace_id
card_id
giver_user_id
holder_user_id
handover_message_id
return_message_id nullable
handover_confirmed_at
return_confirmed_at nullable
status: pending | in_custody | returned | cancelled
created_at
updated_at
```

### card_expense_assignments

```text
id
entry_id
card_id
custody_id nullable
responsible_user_id nullable
assignment_status: assigned | review | unassigned
review_reason nullable
created_at
updated_at
```

### observer_share_links

```text
id
workspace_id
created_by_user_id
share_type: latest_submitted_state | fixed_report
report_id nullable
token_hash
status: active | revoked
expires_at nullable
allow_pdf_download
show_subreports
show_cards_with_members
show_cash_with_members
created_at
last_opened_at
```

## Layer 2 implementation order

### L2 Sprint 00 — Layer 2 Gate

Goal: prove Layer 1 is implemented.

Agents:

```text
Director
Data and Backend Core Agent
Financial Logic Engine Agent
QA, Audit, and Acceptance Agent
```

Output:

```text
Layer 2 allowed / blocked
```

### L2 Sprint 01 — Workgroup Members and Financial Roster

Implement invite acceptance, member status, and workgroup recipient list.

Acceptance:

```text
accepted member appears as possible recipient for money/card/report messages
```

### L2 Sprint 02 — Financial Message Engine

Implement message status and confirmation logic.

Acceptance:

```text
pending message does not affect arithmetic
confirmed message creates action
```

### L2 Sprint 03 — Cash Accountability

Implement cash given, cash returned, linked entries, balance, partial return, carry-forward.

Acceptance:

```text
given 500
spent 250
returned 100
on hand 150
```

### L2 Sprint 04 — Member Subreport

Implement period subreport generation, submission, acceptance, return for revision, parent report attachment.

### L2 Sprint 05 — Service Card Custody

Implement service card registry, handover confirmation, custody period, return confirmation.

### L2 Sprint 06 — Card Expense Assignment

Assign card expenses to holder during custody period, with boundary review.

### L2 Sprint 07 — Operational Window Integration

Show all Layer 2 events in operational window without turning UI into dashboard.

### L2 Sprint 08 — Group Lead Report Integration

Group lead report shows own records, cash with members, member subreports, service cards with members, pending reports, open balances.

### L2 Sprint 09 — Observer Link

Implement read-only latest submitted state link.

### L2 Sprint 10 — Layer 2 QA Gate

End-to-end scenarios:

```text
invite -> accepted -> roster
cash message -> confirm -> linked records
partial return -> balance
period subreport -> group lead report
card handover -> custody
card expense -> holder report
card return -> custody closed
observer link -> latest submitted only
```

## Director and agents

Director must follow:

```text
START_HERE_DIRECTOR.md
33-director-agent-orchestration-protocol.md
```

Director must not work alone.

Every Layer 2 sprint must have assigned agents and reports.

## Acceptance of Layer 2

Layer 2 is accepted only if:

```text
1. Workgroup members are scoped per workspace.
2. Financial roster works only inside workgroup.
3. Money messages require confirmation.
4. Confirmed cash messages create linked records on both sides.
5. Accountable balances carry forward.
6. Subreports attach to group lead report.
7. Service card custody opens and closes by confirmation.
8. Card expenses during custody go to holder report.
9. Boundary card transactions go to review.
10. Observer link shows only latest submitted state.
11. Operational window remains the main working surface.
12. The product does not become ERP.
```

## Final rule

```text
message -> confirmation -> operational record or custody -> subreport -> group report -> observer snapshot
```
