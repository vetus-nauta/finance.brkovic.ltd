# 07 — MVP Scope and Acceptance

## MVP name

FinDesk v2.0 Clean Core MVP

## What MVP is

A fixed-screen notes-style financial journal with Cash/Card flows, strict line parsing, current-month feed, auto category suggestions, manual correction, and generated monthly summary.

## What MVP is not

- Not full accounting.
- Not a dashboard-first product.
- Not a spreadsheet clone.
- Not a yacht-only app.
- Not an AI black box.
- Not a continuation of old FinDesk business logic.

## MVP must include

1. Workspace shell.
2. Fixed-screen journal layout.
3. Entry input with strict `+/-` rule.
4. Cash flow with live balance.
5. Card expense flow.
6. Card-to-cash pair logic.
7. Fixed MVP category list.
8. Attachments base.
9. Monthly summary.
10. Import of one legacy Excel file.

## Acceptance criteria

### Entry parsing

Given:

```text
-250 рыба
```

System creates a cash expense if Cash flow is active.

Given:

```text
250 рыба
```

System creates an unrecognized visible row that does not affect calculations.

### Cash balance

Given opening cash 1000 and records:

```text
+500 пополнение
-250 рыба
```

Cash now must be 1250.

### Card expense

Given Card flow and record:

```text
-60 Netflix
```

Card expense total must increase by 60.

### Card to Cash

Given:

```text
Card: -1000 снял с карты
Cash: +1000 снял с карты
```

Card expense must show 1000.
Cash income must show 1000.
Cash balance must increase by 1000.
The system must not treat this as an error.

### Category fallback

If the engine cannot classify safely, entry must be `other` and visible in Other expenses review queue.

### Layout

On desktop/tablet/phone, user must always have access to workspace, month, active flow, feed, input, cash now.

### Closed month

Editing a closed month must ask whether to create correction, recalculate chain, or cancel.

## Out of MVP

- Full multi-year archive import.
- Forecast engine.
- Advanced charts.
- PDF export.
- Assistant acceptance workflow.
- Full file scanning/OCR.
- Bank integration.
- Multi-currency accounting.
