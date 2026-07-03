# 01 — Product Logic

## Mission

FinDesk v2.0 is a lightweight financial journal. The user writes short financial notes. The system converts them into structured operational records, monthly reports, yearly summaries, categories, charts, and forecast.

The product must feel like financial notes, not accounting software.

## Base logic: two tables

FinDesk v2.0 is based on two conceptual tables.

### 1. Operational table / live journal

This is the live sequence of entries. This is where the user works every day.

Example:

```text
+15260 остаток с последнего отчета
-250 рыба
-42 заправка тузика
-60 Netflix
+1000 снял с карты
```

Every row becomes a normalized entry with date, raw text, sign, amount, flow, direction, description, category, actor, status, balance, attachments, and source metadata.

The operational table is the source of truth.

### 2. Summary table / grouped report

The summary table is generated from operational entries.

It groups by month, year, workspace, flow, category, actor, source, and status.

It must support the existing grouped-report logic:

- month;
- source files;
- opening balance;
- discrepancy with previous balance;
- external income;
- expenses;
- ending balance;
- comments;
- category-by-month matrix;
- plan/fact blocks where needed.

The summary table is generated. It can have manual comments and locks, but numeric values come from entries.

## Universal product

FinDesk is general. It is not yacht-only.

Workspaces can be Yacht, Family, Personal, Business, Charter / Trip, or Custom.

Claudia Z is only the first real case and training source, not the hardcoded model.

## Core model

- Workspace = project or space.
- Flow = funding source, for example Cash or Card.
- Entry = one financial record.
- Category = meaning of the operation.
- Actor = person or party involved.
- Report = generated view from entries.
- Import source = Excel, Google Sheet, legacy database, Drive file, or manual input.

Cash/Card are funding flows, not categories.

Category answers: what was the money for?
Flow answers: where did the money move from or to?

## Cash and Card

Cash has a live balance:

```text
Cash balance = opening cash + cash income - cash expenses + corrections
```

Card in MVP is a funding flow with expenses by default. It does not need bank-balance reconciliation unless the user enables card balance mode later.

Default Card logic:

```text
Card funded amount = sum(card expenses)
```

## Cash/Card withdrawal rule

Withdrawing money from Card into Cash uses two records:

```text
Card: -1000 снял с карты
Cash: +1000 снял с карты
```

The card record is a card expense. The cash record is a cash income.

This is correct because Cash and Card are separate funding flows. The system may connect both records with category `cash_topup_from_card`, but it must preserve both flow facts.

## Invalid entry rule

A counted record must start with `+` or `-`.

Valid:

```text
-250 рыба
+1000 снял с карты
```

Invalid:

```text
250 рыба
Вова аванс 500
```

Invalid entries remain visible, are red and underlined, do not affect arithmetic, and show a correction hint.

## Month closure

Summary is live by default. A month can be closed.

If a closed month is edited, the system must ask whether to create a correction, recalculate the chain, or cancel.

## Assistants

Assistants keep their own journals. Assistant records do not enter the main flow automatically.

Admin can review, edit, accept, reject, and attach assistant records to the main flow.

## MVP reports

1. Monthly operational summary.
2. Category-by-month matrix.
3. Yearly summary.
4. Other expenses review queue.
