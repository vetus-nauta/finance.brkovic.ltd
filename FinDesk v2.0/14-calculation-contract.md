# 14 — Calculation Contract

## Purpose

All agents must calculate FinDesk numbers the same way.

The operational journal is source of truth. Reports are generated.

## Core fields

Each counted entry has:

```text
flow
sign
amount
direction
entry_type
category
status
date
```

Entries with status `unrecognized`, `excluded`, or `info` do not affect arithmetic.

## Cash balance

Cash has live balance.

```text
cash_now = opening_cash + cash_income - cash_expense + corrections
```

## Opening balance

Opening balance sets the starting point.

It is not external income and not commercial income.

## Card expense

Default MVP card mode does not reconcile bank balance.

```text
card_expense = sum(card entries where direction = out and counted = true)
```

## Card to cash

Approved model:

```text
Card: -1000 снял с карты
Cash: +1000 снял с карты
```

The card record is a card expense.
The cash record is cash income.
Both records are valid.
Both may use category `cash_topup_from_card`.

Do not neutralize, hide, or delete either side.

## Commercial income

```text
commercial_income = sum(counted income entries where category = commercial_income)
```

Commercial income is not opening balance, private top-up, debt return, correction, or card-to-cash top-up.

## Other expenses

```text
other_expenses = sum(counted entries where category = other)
```

Other expenses must be visible in review queue.

## Lower accounting

Lower accounting is a reporting/view layer for:

```text
debt
loan
credit
return
accountable cash
private settlement / money movement
guest cash issued
```

Lower-accounting rows remain physical money movements:

```text
cash_now and ending_cash still follow flow/sign/entry_type/status
card_expense still follows card out counted rows
```

Lower-accounting rows must not be silently removed from balances.

Layer 1 category totals exclude lower-accounting rows so settlement/control rows do not become operational expense categories.

Lower-accounting settlement workflow:

```text
direction = out -> issued amount
direction = in -> returned amount
open_amount = max(issued_total - returned_total, 0)
```

Counterparty resolution:

```text
actor_name / actor_id if present
semantic source_actor if present
known text alias if present
Unassigned if unresolved
```

Settlement statuses:

```text
open = issued_total > 0 and returned_total = 0
partial = issued_total > returned_total and returned_total > 0
closed = issued_total = returned_total and both sides exist
needs_actor = counterparty unresolved
review = returned without issue or returned more than issued
```

`needs_actor` and `review` rows must not auto-close.

## Monthly summary

Monthly summary must include:

```text
opening_cash
external_cash_income
commercial_income
cash_expense
card_expense
cash_topup_from_card_card_side
cash_topup_from_card_cash_side
other_expenses
ending_cash
comment
```

## Ending cash

```text
ending_cash = opening_cash + counted_cash_income - counted_cash_expense + corrections
```

## Closed month edit

If a closed month is edited, the system must require one of:

```text
create correction
recalculate chain
cancel
```

No silent recalculation of closed periods.
