# 31 — Operational Input Window Contract

## Purpose

The first working screen of FinDesk v2.0 is an operational input window.

It is not an analytical dashboard.

It is not the final summary report.

It is the daily money-writing surface.

## Core concept

The user writes money records in a simple live table:

```text
+1000 снял с карты
-250 рыба
-60 Netflix
+5000 charter deposit
```

This first table gives the user confidence that the record is saved correctly and can always be checked.

## Two views of the same operational data

The same current-month records have two presentations.

### View A — Writing view

This is the default view.

It feels like a clean financial notebook.

The user writes income and expenses in sequence.

Vertical scroll stays inside the page and moves through the history of current-month records.

### View B — Structured check view

Horizontal movement reveals the structured version of the same records.

It shows parsed fields and current control numbers:

```text
date
flow
sign
amount
category
actor
status
balance_after
```

This is not the monthly analytical report.

This is operational verification.

## UX rule

The user must always be able to answer:

```text
What did I write?
How did the system read it?
What is the current result?
```

## Mobile behavior

Phone and iPad mini:

```text
vertical scroll = history of operational records
horizontal scroll/swipe = structured check view and current figures
```

## Desktop and large tablet behavior

Desktop and iPad 11+ may show both writing view and structured check view in one workspace layout.

But the logic is the same:

```text
write first
verify immediately
then summarize later
```

## Forbidden

Do not replace the first screen with:

- dashboard cards;
- final analytics;
- complex accounting table;
- empty form;
- separate report-first UX;
- hidden structured parsing.

## Relation to reports

Operational input window is before reports.

Reports are generated later from the operational entries.

The operational table is the source of truth.

## Acceptance

The first screen is acceptable only if:

- user can write records quickly;
- current-month history is visible;
- history scroll is inside the screen;
- structured parsed view is reachable horizontally or side-by-side;
- current figures are visible near the operational records;
- no report screen is required to verify basic correctness.
