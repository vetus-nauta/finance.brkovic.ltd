# Product Compass

## One Sentence

FinDesk is a finance control web app for live field reports, group cash, accountable money, report review, final export, archive, and audit.

## MVP Finish Line

MVP ends when the product proves the money tree for a non-accountant:

```text
fast capture -> review -> current open period -> historical finalized report -> export -> archive/proof
```

The detailed MVP gate lives in:

```text
docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md
```

No new feature belongs in MVP after those gates pass unless it fixes a P0 blocker.

## Main Invariant

At any moment the administrator must understand:

1. how much money entered the group;
2. how much was spent;
3. who spent it;
4. how much physical cash the administrator holds;
5. how much physical cash each employee holds;
6. what is included in the report;
7. what is still being checked;
8. where the evidence for each number lives.

## Director Product Decision 2026-05-26

FinDesk must be understandable to a non-accountant. The first screen and the main language of the product must answer one human question:

```text
Где сейчас деньги и почему я могу этому верить?
```

The product is no longer allowed to present the money flow primarily as accounting machinery. The operational layer must present money as a map of clear places and states:

1. `Получено` - money entered the group or period.
2. `В кассе администратора` - physical cash held by the administrator.
3. `У сотрудников` - accountable cash physically held by employees.
4. `Потрачено наличными` - cash expenses with proof.
5. `Потрачено картой` - card expenses with proof, separate from physical cash.
6. `На проверке` - reports and proofs not yet accepted into the final package.
7. `В финальном отчете` - accepted and fixed numbers.
8. `В архиве` - history and cleanup, not money mutation.

Every amount shown to the user must carry four meanings:

```text
who holds or spent it
where it physically or logically is
what changed it
where the proof is
```

Action language for non-accountants:

- `Получили деньги`
- `Передали сотруднику`
- `Потратили наличными`
- `Потратили картой`
- `Вернули остаток`
- `Отправили на проверку`
- `Приняли в отчет`
- `Закрыли отчет`

Terms like ledger, journal, accounting section, state machine, snapshot, and API are internal/supporting terms. They can exist in audit, developer, or advanced layers, but they must not be the primary way a normal user understands money.

## Instant Capture Principle

FinDesk must support people who are physically moving, carrying cash, buying things, receiving money, handing money to employees, and collecting proofs under pressure.

The field workflow must follow this rule:

```text
Сначала поймать факт денег, потом спокойно разобрать и проверить.
```

Instant capture is not a full accounting form. It is a fast record that preserves the event before it is forgotten:

1. `Что произошло` - received money, spent cash, spent card, handed money to employee, returned balance.
2. `Сколько` - amount.
3. `Кто` - administrator or employee.
4. `Чем подтверждается` - photo, receipt, comment, timestamp, attachment.
5. `Что требует проверки` - missing category, unclear proof, unclear cash/card source, needs manager review.

The product must allow incomplete but honest field records. A quick record can be marked as `Черновик` or `На проверке`, but it must not silently become a final report number until reviewed.

Mobile capture rules:

- one-hand use;
- minimal required fields;
- large tap targets;
- camera/receipt action near the amount;
- save without forcing perfect categorization;
- visible status after save: draft, needs proof, on review, accepted, returned;
- no dense tables during field entry.

## Money Tree

```text
Money source
  -> administrator physical cash
    -> administrator live cash report
    -> accountable money issued to employees
      -> employee cash pocket
        -> employee expenses
        -> employee remaining cash
        -> return or carry forward
  -> FinDesk review
  -> final report snapshot/export
  -> archive cleanup
  -> journal/audit trail
```

## Cash/Card Rule

`Живой отчет` has two parallel streams:

```text
cash -> physical cash on hand
card -> card spending only
```

Card rules:

- no `cash_received`;
- no influence on `cash_left`;
- no influence on `available_cash_balance`;
- creates only noncash/card expense;
- appears only as card spending in consolidated reports.

Cash rules:

- cash is physical money;
- cash cards carry remainder forward;
- employee cash is accountable money until returned or carried forward;
- issuing money is not an expense.

## Product Layers

```text
Живые отчеты
  fast mobile capture
Проверка отчетов / FinDesk
  accept, return, include, prepare package
Деньги / Подотчеты
  physical cash, issued money, employee responsibility
Сводка отчета
  final numbers, Excel/Google export, fixation
Архив
  cleanup and history, not accounting mutation
Журнал учета
  black-box recovery/audit trail
AI / Analytics
  checks, risk flags, spending analysis
```

## UX Principle

Mobile and tablet screens must stay compact. Do not put full administration, settings, analytics, and finance tables into one phone screen. Use menu-based switching between small screens.
