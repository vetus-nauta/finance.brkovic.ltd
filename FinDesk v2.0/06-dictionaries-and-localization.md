# 06 — Dictionaries and Localization

## Principle

FinDesk v2.0 must be flexible without becoming random.

The engine uses dictionaries, weighted rules, language variants, suppliers, actor detection, and manual corrections.

No black-box AI is required for MVP.

## Languages

```text
RU — Russian
EN — English
IT — Italian
ES — Spanish
DE — German
BCMS / Local — Bosnian, Croatian, Montenegrin, Serbian
```

Use `BCMS / Local` as neutral product label.

## Dictionary levels

1. Category keywords.
2. Category phrases.
3. Supplier names.
4. Actor/people markers.
5. Role words.
6. Stop words.
7. Internal flow movement phrases.
8. Workspace-specific learned rules.

## Stop words

Do not create category rules from generic words:

```text
купил
оплатил
сегодня
вчера
лодка
boat
cash
card
money
paid
bought
for
на
для
```

## Actor recognition

A person name is not a category.

Examples:

```text
-500 Вова аванс
-87 Вова купил кабель
```

Actor is Вова. Category depends on context.

## Category set

```text
crew
commercial_income
dry_dock
berth
marina_ports
service_water
tech_parts
tender
fuel
provisions
interior
cleaning
media_comms
admin_legal
cash_topup_from_card
other
```

`commercial_income` is an income category, not an expense category. It covers rental, charter, brokerage/agency commissions, and similar business inflows.

## Category examples

### commercial_income

```text
аренда, чартер, комиссия, комиссионные, агентские, брокерские, прокат, оплата чартера, charter, rental, rent, commission, agency fee, brokerage, booking, commercial income, noleggio, charter fee, commissione, alquiler, chárter, comisión, provizija, najam, iznajmljivanje, zakup, Vermietung, Charter, Provision
```

Use this category for real income from commercial activity. Do not use it for opening balance, card-to-cash top-up, private replenishment, debt return, or correction unless the description clearly points to commercial revenue.

### cash_topup_from_card

```text
снял с карты, снятие с карты, банкомат, atm, cash withdrawal, card to cash, prelievo, retiro, Abhebung
```

### other

Fallback category. Must be highlighted and reviewed.

## Rule weighting

Each matched rule adds weight to a category.

Example:

```text
-42 заправка тузика
```

- `заправка` supports fuel.
- `тузик` supports tender.

Primary category can be fuel, with tender marker as metadata.

## Manual learning

When admin changes category, system can offer:

```text
Remember this rule?
```

But it must not create rules automatically from weak words.

Store learned rules as workspace-specific by default.

## Explainability

Every suggestion must be explainable:

```text
Suggested: fuel
Reason: matched `заправка`, `diesel`, `fuel`
Confidence: 0.87
```
