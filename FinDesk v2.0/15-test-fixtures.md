# 15 — Test Fixtures

## Purpose

Agents must test the same examples and expected outputs.

No agent may claim the core works unless these fixtures pass.

## Fixture 1 — Basic cash

Opening cash: 1000
Active flow: Cash

Input:

```text
+500 пополнение
-250 рыба
```

Expected:

```text
cash income: 500
cash expense: 250
cash now: 1250
```

## Fixture 2 — Invalid no-sign row

Input:

```text
250 рыба
```

Expected:

```text
status: unrecognized
entry_type: unrecognized
amount: null
counted: false
visible_in_feed: true
```

## Fixture 3 — Card expense

Active flow: Card

Input:

```text
-60 Netflix
```

Expected:

```text
flow: card
direction: out
entry_type: card_expense
category: media_comms
card_expense_total: +60
cash_now: unchanged
```

## Fixture 4 — Card to cash

Input:

```text
Card: -1000 снял с карты
Cash: +1000 снял с карты
```

Expected:

```text
card record: counted card expense 1000
cash record: counted cash income 1000
category: cash_topup_from_card
cash increases by 1000
system must not mark as duplicate or error
```

## Fixture 5 — Commercial income

Active flow: Cash

Input:

```text
+5000 charter deposit
+750 агентские
```

Expected:

```text
both direction: in
both entry_type: cash_income
category: commercial_income
commercial_income total: 5750
opening balance: unchanged
```

## Fixture 6 — Other expenses

Active flow: Cash

Input:

```text
-180 какая-то штука
```

Expected:

```text
category: other
status: other_review
visible in Other expenses queue
counted as cash expense
```

## Fixture 7 — Tender fuel ambiguity

Input:

```text
-42 заправка тузика
```

Expected:

```text
primary category: fuel
metadata/secondary marker: tender_related if supported
status: recognized
```

## Fixture 8 — Person is actor, not category

Input:

```text
-500 Вова аванс
-87 Вова купил кабель
+120 Вова вернул остаток
```

Expected:

```text
row 1 actor: Вова, likely category crew or accountable money
row 2 actor: Вова, category tech_parts
row 3 actor: Вова, cash income / return context, not crew by name alone
```

## Fixture 9 — Month insertion recalculation

Opening cash: 1000
Existing records:

```text
01.07 -100 fuel -> balance 900
03.07 -100 food -> balance 800
```

Insert between them:

```text
02.07 +500 top-up
```

Expected balances:

```text
01.07 balance 900
02.07 balance 1400
03.07 balance 1300
```

## Fixture 10 — Closed month protection

Given month status: closed

Action:

```text
edit an entry amount
```

Expected system prompt:

```text
Create correction
Recalculate chain
Cancel
```

No silent recalculation.
