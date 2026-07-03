# 03 — Parsing and Rules Engine

## Purpose

The engine converts a short human financial note into a structured entry.

It must be rules-first, explainable, and trainable by dictionaries and manual corrections.

It must not rely on black-box AI for MVP.

## Input examples

```text
-250 рыба
+1000 снял с карты
-60 Netflix
-42 заправка тузика
-500 Вова аванс
250 продукты
```

## Step 1 — Normalize text

- trim spaces;
- convert repeated spaces to one space;
- preserve original raw text;
- lowercase for matching;
- normalize comma decimal separator;
- keep Cyrillic and Latin text.

## Step 2 — Validate sign

A counted record must start with `+` or `-`.

If no sign:

```text
status = unrecognized
entry_type = unrecognized
direction = none
amount = null
```

The row remains visible but does not affect arithmetic.

## Step 3 — Extract amount

Supported:

```text
-250 рыба
-250.50 рыба
-250,50 рыба
+1000 снял с карты
```

The first numeric value after sign is the amount.

## Step 4 — Extract description

Description is the text after sign and amount.

## Step 5 — Determine flow

The active UI tab sets default flow:

```text
Cash
Card
Assistants
```

Same text can produce different flow results depending on active tab.

## Step 6 — Determine entry type

Cash:

```text
+ => cash_income
- => cash_expense
```

Card default:

```text
- => card_expense
+ => card_income only if explicitly allowed or used for correction
```

Special phrases:

```text
остаток, opening balance, balance brought forward => opening_balance
информационная строка, не считается, info => info
корректировка, correction => correction
```

## Step 7 — Category suggestion

Category is determined by rule weights.

Rules can be keyword, phrase, regex, supplier, role, language-specific, or workspace-specific.

If confidence is low:

```text
category = other
status = other_review
```

## Step 8 — Actor detection

Actor is detected separately from category.

Names alone must not force a category.

Examples:

```text
-500 Вова аванс => actor Вова, category by context
-87 Вова купил кабель => actor Вова, category tech_parts
```

## Step 9 — Attachments

Entry may have attachments added during or after entry creation.

Attachment does not change arithmetic.

## Step 10 — Balance calculation

Cash-like flows with live balance calculate `balance_after` sequentially.

If records are inserted between existing records, following balances must recalculate.

Card default flow does not need `balance_after` unless card balance mode is enabled.

## Training without AI

Manual category changes may create rules only after user confirmation.

Never create rules from generic words like bought, paid, today, boat, cash, card.

## Required parser output

```json
{
  "raw_text": "-250 рыба",
  "sign": "-",
  "amount": 250,
  "description": "рыба",
  "flow": "cash",
  "direction": "out",
  "entry_type": "cash_expense",
  "category_code": "provisions",
  "status": "recognized",
  "actor": null,
  "confidence": 0.91,
  "matched_rules": ["рыба -> provisions"]
}
```
