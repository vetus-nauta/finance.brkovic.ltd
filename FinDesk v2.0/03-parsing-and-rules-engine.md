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

## Step 8.1 — Semantic markers

Semantic markers are explainable metadata, not categories.

They may be stored in `matched_rules` and exposed as `semantic_markers`.

Markers must not change:

```text
amount
sign
flow
direction
entry_type
category
status
balance_after
```

Markers may derive read-only accounting view fields:

```text
accounting_section
accounting_type
accounting_label
```

`debt_or_return` and `money_movement` derive `accounting_section=lower_accounting` unless the wording is explicitly personal to the administrator.

Administrator personal debt/expense wording such as `мой кредит`, `мой долг`, `для себя`, `себе`, `домой`, `с тему`, `temu`, and `мото навигатор` derives `accounting_section=admin_debt`.

`guest_cash_issued` keeps its category code for entry context, but also derives `accounting_section=lower_accounting` so guest cash handoff/control rows do not inflate operational category totals.

Current MVP markers:

```text
cash_location_safe
owner_funding
commercial_income_allowed
debt_or_return
tender_related
weak_dictionary_context
mixed_dictionary_context
```

Universal linguistic rule:

```text
strong phrase beats weak role word
specific object beats delivery/movement word
actor/role word alone does not prove category confidence
mixed category signals stay explainable for review/training
```

Examples:

```text
-50 обед с агентом => representation_expenses
-50 агент => current_boat_expenses + weak_dictionary_context
-15 доставка => transport_expenses + weak_dictionary_context
-15 доставка фильтра => tech_parts + mixed_dictionary_context
```

`weak_dictionary_context` and `mixed_dictionary_context` do not change arithmetic or entry primitives.

They exist to prevent automatic training from weak or mixed rows without human approval.

## Step 8.2 — Classification decision metadata

After category suggestion and semantic-marker extraction, the engine may attach a read-only classification decision:

```text
classification_decision.category_code
classification_decision.confidence
classification_decision.review_reason
classification_decision.matched_signals[]
classification_decision.blockers[]
```

This is an explainability layer over the existing parser result.

It must not change:

```text
amount
sign
flow
direction
entry_type
category
status
balance_after
report totals
```

Review-reason priority:

```text
blocked_by_personal
blocked_by_debt
private_money_movement
commercial_income_unclear
mixed_context
weak_only
other_review
no_category
```

Confidence bands:

```text
0.10 card income blocked by manual guard
0.20 personal/debt/private movement/no-category blocker
0.30 unclear commercial income or other_review
0.48 weak dictionary context
0.64 mixed dictionary context
0.92 strong accepted rule or explicit owner/commercial income
```

Examples:

```text
-50 агент => current_boat_expenses, confidence 0.48, review_reason weak_only
-15 доставка фильтра => tech_parts, confidence 0.64, review_reason mixed_context
-100 Порше топливо => blocker non_yacht_or_personal, confidence 0.20
-250 долг за гараж => lower_accounting marker, blocker debt_or_return, confidence 0.20
-87 я заказал с тему => admin_debt marker, personal administrator liability
+750 агентские => not commercial_income, blocker missing_yacht_charter_phrase, confidence 0.30
+5525 ареда яхты => commercial_income, confidence 0.92
```

Workspace rule for Claudia Z:

```text
сейф = cash context
```

`сейф` is not a category, not a separate flow, and not commercial income.

Income boundary:

```text
income + explicit yacht/charter revenue wording => may be commercial_income
cash income without yacht/charter revenue wording => non_commercial_income + owner_funding marker
agency/brokerage/commission income without yacht/charter wording => review, not commercial_income and not non_commercial_income
```

Allowed commercial wording examples:

```text
чартер
оплата чартера
аренда яхты
сдача яхты
charter
yacht rental
yacht booking
```

Negative examples:

```text
+5000 от Александра => non_commercial_income + owner_funding, not commercial_income
+6000 из сейфа => non_commercial_income + cash_location_safe + owner_funding, not commercial_income
+100 аренда авто => non_commercial_income + owner_funding, not commercial_income
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
