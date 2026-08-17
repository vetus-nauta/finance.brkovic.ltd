# SPRINT-25R — Claudia Z Data Application Gate

## Director Opening

Apply Claudia Z data in the correct v2 architecture:

- `Claudia Z` is the current operational workspace.
- `Claudia Z Archive Raw History` is raw history for dictionary learning and review only.
- Historical raw history must not create live operational entries.
- Current operational balance must rebuild from the accepted balance chain and end at `15262.00` cash.

## Agent Assignments

- QA, Audit, and Acceptance Agent — verify split-workspace acceptance, evidence, and non-mutation guarantees.
- Financial Logic Engine Agent — verify opening cash, carry-row protection, current-chain arithmetic, and archive raw-only behavior.
- Director — run the local rebuild gate, add machine guards, and collect evidence.

## Implemented Scope

- Rebuilt `Claudia Z` with `--mode=current`.
- Rebuilt `Claudia Z Archive Raw History` with `--mode=archive`.
- Re-exported Claudia Z dictionary corpus from the archive workspace.
- Rebuilt SPRINT-24R dictionary candidate review from the fresh corpus.
- Added importer hard guards:
  - current mode must include exactly the three current-chain files;
  - cash opening/balance must be `2870.00 -> 15262.00`;
  - current import must create exactly `126` operational rows;
  - per-file cash income/expense/net must match the accepted chain;
  - `4205.00` carry in `15.06.2026.xlsx` must not import as operational income;
  - archive mode must create zero operational entries and zero balances.

## Current Operational Chain

Source: `FinDesk v2.0/37-claudia-z-current-operational-balance-chain.json`

```text
opening cash: 2870.00
14.05.26+сервис.xlsx: +50520.00 -39676.00 = +10844.00
06.06.xlsm: +5000.00 -14509.00 = -9509.00
15.06.2026.xlsx: +24400.00 -13343.00 = +11057.00
ending cash: 15262.00
```

The `4205.00` value in `15.06.2026.xlsx` is opening/carry context, not operational income.

## Evidence

Commands:

```bash
php scripts/v2_import_claudia_z_local.php "/home/alexey/GoogleDrive/Claudia Z/Бухгалтерия/Бухгалтерия" storage/imports/claudia-z-archive --reset --mode=archive
php scripts/v2_import_claudia_z_local.php "/home/alexey/GoogleDrive/Claudia Z/Бухгалтерия/Бухгалтерия" storage/imports/claudia-z-current --reset --mode=current
php scripts/v2_export_claudia_z_dictionary_corpus.php storage/imports/claudia-z-dictionary
php scripts/v2_prepare_claudia_z_dictionary_review.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
```

Current import:

```text
files_seen=90
files_included=3
files_excluded=87
files_review=0
rows_scanned=169
rows_created=126
rows_unrecognized=5
rows_other_review=3
cash opening=2870.00
cash net=12392.00
cash balance=15262.00
card balance=0.00
```

Archive import:

```text
files_seen=90
files_included=57
files_excluded=29
files_review=4
rows_scanned=3338
rows_created=0
cash balance=0.00
card balance=0.00
```

Dictionary corpus:

```text
rows_total=3338
unique_descriptions=1192
candidates=189
discussion_required=0
```

Action counts:

```text
ignore_or_balance_context=50
existing_category_candidate=48
semantic_only=91
```

Regression gates:

```text
npm run smoke:v2: OK
npm run test:v2:fixtures: PASS (19)
npm run smoke:v2:http: OK
```

## Agent Reports

Financial Logic Engine Agent: ACCEPT.

- Opening cash is stored as cash flow opening balance, not an entry.
- Current mode is limited to the three accepted chain files.
- Chain arithmetic reaches `15262.00`.
- Archive mode is raw-only and does not create operational entries.
- Risks were converted into importer hard guards.

QA, Audit, and Acceptance Agent: PASS with notes.

- Current and archive workspaces are separated.
- Archive is a training corpus, not a historical financial balance.
- Dictionary review queue remains read-only.
- Archive still has `4` files requiring manual title/source review; they are not part of current operational balance.

## Residual Review Items

Current operational `other_review` rows after import:

```text
2026-04-23 | -40.00 | Алесей адвокат
2026-06-06 | -100.00 | мото навигатор
2026-06-15 | -532.00 | совместный поход cogimar
```

Current unrecognized import rows are blank/no-money chronology rows:

```text
06.06.xlsm: Хронология rows 48, 49, 50
15.06.2026.xlsx: Хронология rows 33, 48
```

Archive files left for manual review:

```text
05.10.22-1.xls
24.05.22редакт.xls
30.06.22-подготовка поход.xls
примерный расход по лодке до 04.24.xlsx
```

## Acceptance

ACCEPTED for local Data Application Gate.

Next implementation sprint should move from data correctness to UI behavior:

- current workspace opens at the latest operational row;
- archive month picker opens closed historical months safely;
- closed-month edit confirmation is clear;
- lower accounting block separates debt/loan/return/accountable rows from expenses;
- browser screenshots confirm desktop/tablet/mobile behavior.
