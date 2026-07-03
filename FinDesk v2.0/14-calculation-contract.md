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
