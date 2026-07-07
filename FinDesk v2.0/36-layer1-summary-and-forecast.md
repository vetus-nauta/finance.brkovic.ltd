# 36 — Layer 1 Summary Screen and Forecast

## Purpose

This document defines the second screen of Layer 1.

The first screen is the operational input window.

The second screen is the generated period summary based only on operational entries.

It answers:

```text
What is the result for the period?
Which entries created this result?
Can it be sent, printed, and stored?
What is the preliminary expense plan for next year?
```

## Source of truth

All totals are generated from operational entries.

The summary screen does not keep independent financial numbers.

Allowed manual inputs:

```text
comment
explanation
correction entry
forecast variable
```

## Main tabs

Only four tabs in Layer 1:

```text
Information
Sending
Printing
Storage
```

## Information tab

Shows the current period result.

Header:

```text
workspace
period
currency
status
generated_at
entries_count
review_count
```

Main totals:

```text
opening_cash
cash_income
cash_expense
card_expense
commercial_income
other_review_total
corrections_total
ending_cash
```

Cash block:

```text
Opening cash
+ Cash income
- Cash expenses
+/- Corrections
= Ending cash
```

Card block:

```text
Card expenses total
Card entries count
Card expenses by category
Card entries in review
```

Category block:

```text
Category | Cash | Card | Total | Entry count | Review
```

Other / Review block must always be visible when it exists.

Every total must open the source operational entries that created it.

Formula:

```text
summary explains journal
journal proves summary
```

## Sending tab

Prepares the summary for sending.

Layer 1 MVP options:

```text
copy text summary
browser print / PDF if available
basic file export if available
```

Sending package includes:

```text
workspace
period
status
main totals
cash summary
card summary
category totals
other/review list
comment
generated_at
```

If review items exist, the report is marked as draft/preliminary.

## Printing tab

Creates printable view using the same data.

Print structure:

```text
Title
Workspace
Period
Generated at
Status
Summary
Cash
Card
Categories
Other / Review
Comments
Source trace
```

Print options stay minimal:

```text
show entry details
show attachments list
show source trace
show next-year preliminary plan
```

## Storage tab

Stores report states.

Open period:

```text
summary = live
```

Saved/closed period:

```text
summary snapshot is stored
```

Stored report must keep:

```text
period
status
generated_at
closed_at
summary numbers
comments
source_entry_ids
correction_ids
attachment_refs
forecast_snapshot if saved
```

A stored report must know which operational entries created it.

## Next-year preliminary plan

The summary screen includes a preliminary plan for next year.

This is not a heavy budgeting module.

It is a forecast from actual data.

Rule:

```text
If no explicit variables are set, use actual past data as the forecast base.
```

No automatic inflation, price growth, seasonality, or special event assumptions unless the user explicitly sets them.

Forecast table:

```text
Category | Actual base | Average per month | Forecast type | Method | Next year estimate | Variable/comment
```

If full previous year exists:

```text
next_year_estimate = previous_year_actual
```

If only several months exist:

```text
monthly_average = actual_expenses_for_available_months / number_of_months
next_year_estimate = monthly_average * 12
```

## Forecast filters

Each forecast line must have a type.

Minimum types:

```text
regular_monthly
regular_yearly
operational_from_history
seasonal
planned_one_time
exclude_from_forecast
review
```

Extended types:

```text
emergency_unplanned
capex_major_work
```

Meaning:

```text
regular_monthly = repeats monthly, amount * 12
regular_yearly = repeats yearly, use yearly amount
operational_from_history = based on past operational experience
seasonal = linked to active season months
planned_one_time = known future one-time item
emergency_unplanned = not repeated automatically, possible reserve only
capex_major_work = large work, shown separately
exclude_from_forecast = fact stays, forecast ignores it
review = needs manual decision
```

Forecast statuses:

```text
auto_from_fact
manual_adjusted
excluded
one_time
needs_review
```

Manual variables:

```text
percentage change
fixed amount
exclude category
one-time amount
new planned expense
```

Forecast never changes actual period totals.

Formula:

```text
Fact separately.
Forecast separately.
```

## Implementation order

1. Report calculation from operational entries.
2. Category grouping.
3. Other / Review block.
4. Information tab UI.
5. Print view.
6. Save draft and close period snapshot.
7. Sending package.
8. Storage list.
9. Forecast filters and variables.

## Agent responsibilities

Director must assign agents.

Data and Backend Core Agent:

```text
report calculation
report API
snapshot storage
source trace
forecast data model
```

Financial Logic Engine Agent:

```text
cash/card formulas
opening balance
commercial income
other review
closed period logic
forecast methods
forecast filters
```

UX Layout Agent:

```text
summary screen layout
four tabs only
mobile/desktop behavior
print view
no dashboard sprawl
```

QA Agent:

```text
formula tests
drill-down checks
print checks
storage checks
forecast checks
review state checks
```

## Acceptance criteria

Accepted only if:

```text
1. All totals are generated from operational entries.
2. Final totals are not edited directly.
3. Tabs are Information, Sending, Printing, Storage.
4. Cash and Card remain separate flows.
5. commercial_income is not mixed with opening balance.
6. Other / Review is visible.
7. Every total has drill-down to source records.
8. Print view uses the same data.
9. Stored report keeps source entry ids.
10. Closed period does not change silently.
11. Forecast is separated from facts.
12. Forecast uses facts unless variables are explicit.
13. Forecast lines have type and status.
14. Screen does not become a decorative dashboard.
```

## Final rule

```text
Operational entries -> generated summary -> send / print / store -> preliminary next-year plan
```
