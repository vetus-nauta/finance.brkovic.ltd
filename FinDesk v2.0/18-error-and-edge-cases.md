# 18 — Error and Edge Cases

## Purpose

Agents must handle real user input, not perfect demo input.

## Entry input cases

### No sign

```text
250 рыба
```

Expected: visible unrecognized row, not counted.

### Sign without amount

```text
- рыба
```

Expected: unrecognized, not counted.

### Amount without description

```text
-250
```

Expected: counted only if product rule allows, but category likely `other_review`.

### Two amounts

```text
-250 рыба 3 кг
```

Expected: amount = first amount after sign. Later numbers remain description context.

### Decimal comma

```text
-250,50 рыба
```

Expected: amount = 250.50.

## Category ambiguity

### Commission

Can mean commercial income or expense depending on sign/context.

```text
+750 agency commission => commercial_income
-12 bank commission => admin/legal or other, not commercial_income
```

### Tender fuel

```text
-42 заправка тузика
```

Expected: primary fuel, tender-related marker if supported.

### Person name

Name is actor, not category.

## Cash/Card cases

### Card to Cash

```text
Card -1000 снял с карты
Cash +1000 снял с карты
```

Expected: both counted in their flows.

### Private top-up

```text
+5000 мои деньги
```

Expected: external/private income, not commercial income.

### Opening balance

```text
+15260 остаток
```

Expected: opening balance, not external income.

## Month edits

### Insert record in middle

Expected: following cash balances recalculate.

### Delete record in middle

Expected: following cash balances recalculate, audit remains.

### Edit closed month

Expected: correction/recalculate/cancel choice.

## Import cases

### File marked not sent/not complete

Expected: excluded by title marker.

### Final and non-final versions exist

Expected: final version preferred.

### Row date differs from filename date

Expected: row date wins.

### Empty rows

Expected: ignored or info, not counted.

### Source summary rows

Expected: not imported as financial entries.

## UI cases

### Keyboard opens on phone

Input must remain visible.

### Desktop wide monitor

App must use available width, not centered mobile column.

### iPad mini

Use mobile financial-notes behavior.

### iPad 11+

Use full workspace behavior.
