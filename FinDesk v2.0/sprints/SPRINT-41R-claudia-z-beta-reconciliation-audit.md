# SPRINT-41R — Claudia Z Beta Reconciliation Audit

## Director Sprint Opening

Sprint:
SPRINT-41R — Claudia Z Beta Reconciliation Audit

Date:
2026-07-09

Goal:
Audit the real Claudia Z beta workspace after provisioning, before accepting the beta report as a usable MVP evidence layer.

Beta account:

```text
vetus.nauta@gmail.com
```

Workspace:

```text
Claudia Z
0d4faca6-3138-4ffe-9805-a6a29895b7ed
```

Archive/raw workspace:

```text
Claudia Z Archive Raw History
3bb2f598-540e-4878-9d92-aad24a7d12ac
```

Rules:

- Do not mutate Claudia Z entries during reconciliation.
- Do not accept archive/raw history as current operational balance.
- Do not treat Claudia Z wording as universal product truth.
- Identify review and reconciliation gaps explicitly.

Agents assigned:

- Data Reconciliation Agent
- Dictionary/Review QA Agent

## Implemented

Added reproducible audit script:

```text
scripts/v2_claudia_z_reconciliation_audit.php
npm run audit:v2:claudia-z
```

The audit reads:

- beta user/workspace membership;
- current workspace counters;
- archive/raw workspace counters;
- cash opening/current chain;
- `balance_after` mismatch count;
- entries by month/status/flow/type;
- current/archive import sources and rows;
- category totals;
- `other_review` examples;
- `unrecognized` examples;
- duplicate suspects;
- latest Layer 1 month and lower-accounting block.

Artifact:

```text
storage/imports/claudia-z-reconciliation/sprint-41-reconciliation-audit.json
```

## Audit Result

Command:

```text
php scripts/v2_claudia_z_reconciliation_audit.php
```

Summary:

```text
account=vetus.nauta@gmail.com user_id=79 role=owner
workspace=0d4faca6-3138-4ffe-9805-a6a29895b7ed entries=126 import_rows=169
archive=3bb2f598-540e-4878-9d92-aad24a7d12ac import_rows=3338
cash opening=2870 computed=15262 latest_balance_after=15262 diff=0 mismatches=0
latest_layer1_month=2026-06 review_count=2 lower_accounting_total=2077
flags=manual_review_items_present
```

Financial chain:

```text
opening_cash: 2870
computed_cash_now: 15262
latest_balance_after: 15262
difference: 0
balance_mismatch_count: 0
```

Interpretation:

- Current cash chain is internally consistent.
- Current balance is around 15k as expected.
- No `balance_after` drift was found.

Current operational workspace:

```text
entries_count: 126
import_sources_count: 3
import_rows_count: 169
months:
2026-04: 22 entries, 1 review
2026-05: 7 entries, 0 review
2026-06: 97 entries, 2 review
```

Current import rows:

```text
parsed: 126 rows linked to 126 entries
ignored: 28
summary_ignored: 10
unrecognized: 5
duplicate_suspect: 0
```

Archive/raw workspace:

```text
import_sources_count: 57
import_rows_count: 3338
parsed_raw: 2502
ignored: 29
summary_ignored: 349
unrecognized: 458
```

Interpretation:

- Current operational window is a filtered balance chain, not the full Claudia Z history.
- Archive/raw history is available as dictionary corpus and needs separate review before any broad import acceptance.

Latest Layer 1 month:

```text
month_key: 2026-06
status: open
entries_count: 97
review_count: 2
opening_cash: 13714
cash_income: 29400
cash_expense: 27852
ending_cash: 15262
other_review_total: 632
lower_accounting_total: 2077
```

Lower accounting:

```text
count: 3
issued_total: 2077
returned_total: 0
net_open_total: 2077
status: needs_actor
reason: counterparty_not_resolved
```

Interpretation:

- Lower accounting mechanics are visible.
- Counterparty assignment is not resolved yet and must be reviewed manually.

Review examples:

```text
2026-04-23 -40 Алесей адвокат
2026-06-06 -100 мото навигатор
2026-06-15 -532 совместный поход cogimar
```

Interpretation:

- `cogimar` remains manual review context as previously decided.
- `мото навигатор` likely needs category review, not automatic dictionary expansion.
- `Алесей адвокат` may be admin/legal or review depending on actual meaning.

Top category totals in current operational workspace:

```text
non_commercial_income cash income: 79920 / 9 entries
marina_ports: 28766 / 6 entries
crew: 18117 / 15 entries
service_water: 8267 / 5 entries
provisions: 3835 / 35 entries
fuel: 2919 / 9 entries
berth: 1629 / 3 entries
other: 672 / 3 entries
```

Interpretation:

- Owner/top-up cash income is labeled `non_commercial_income` / `Некоммерческие поступления`; it remains physical external cash income in formulas.
- `provisions` has many small rows and should be monitored for over-broad food/store matching.
- `other` is small but contains manual decisions.

## Agent Reports

### Data Reconciliation Agent

Status:
ACCEPT as read-only reconciliation.

Confirmed:

- `vetus.nauta@gmail.com` is active user `79`.
- User is owner on both current and archive workspaces.
- Current `Claudia Z`: `126` entries, `3` sources, `169` rows.
- Archive: `0` entries, `57` sources, `3338` rows.
- Opening cash `2870.00`, counted net `12392.00`, ending `15262.00`.
- Card current operational balance is `0.00`; the current beta chain is cash-only.
- Carry row `4205.00` in `15.06.2026.xlsx` is not imported as income.
- April/May/June generated cash chain:
  - `2026-04`: `2870 -> -8914`
  - `2026-05`: `-8914 -> 13714`
  - `2026-06`: `13714 -> 15262`
- Duplicate suspects: `0`.
- Other review: exactly `3` rows, total `672.00`.
- Lower accounting:
  - April `500.00`
  - June `2077.00`
  - physical cash totals unchanged
  - settlement status currently `needs_actor`
- Generated report source-entry drilldown resolves true entry ids; opening cash basis is not an entry id.

Follow-up:

- `v2_report_snapshots` is empty for Claudia Z; stored snapshot save/readback remains a separate acceptance action.

### Dictionary/Review QA Agent

Status:
ACCEPT audit with risks.

Confirmed:

- Archive dictionary queue scale:
  - `rows_total=3338`
  - `rows_with_money=2508`
  - `rows_needs_review=1250`
  - `groups_total=30`
- Dictionary artifact scale:
  - `1192` unique descriptions
  - `189` candidate descriptions
  - `semantic_only=91`
  - `ignore_or_balance_context=50`
  - `existing_category_candidate=48`
- Training approvals must remain blocked for debt, personal/private movement, unclear commercial income, and card income.
- Broad categories to watch: `provisions`, `tech_parts`, `crew`, `service_water`, `berth`.
- Keep manual review for:
  - `cogimar/цоги мар`
  - personal/non-yacht rows
  - debt/return/accountable rows
  - settlement/card-cash movement rows
  - weak actor-only rows
  - mixed rows such as SIM plus fruit or tender balance plus service

Risk:

- Counted cash-income rows with `category_id IS NULL`: `9` rows, total `79920.00`.
- These are owner/source funding style rows, but category reports can look incomplete unless the UI labels this clearly.

Resolved during sprint:

- Local beta DB was missing newer tables:
  - `v2_dictionary_training_decisions`
  - `v2_workspace_assistant_settings`
  - `v2_internet_reference_lookups`
- Ran clean-core schema sync.
- Post-check confirms all required tables exist.

## Acceptance

Accepted:

- Beta user/workspace access is correct.
- Current cash chain is internally consistent.
- Current operational balance is preserved.
- Archive/raw history is separated from current operational balance.
- Latest Layer 1 exposes review and lower-accounting issues instead of hiding them.

Not accepted as final:

- Archive/raw corpus classification.
- Lower-accounting counterparty resolution.
- Three current review rows.
- `no_category` income labeling for final user-facing reports.

## Verification

```text
php -l scripts/v2_claudia_z_reconciliation_audit.php — PASS
php scripts/v2_claudia_z_reconciliation_audit.php — PASS
php -l scripts/v2_apply_clean_core_schema.php — PASS
php scripts/v2_apply_clean_core_schema.php — PASS
node -e "JSON.parse(require('fs').readFileSync('package.json','utf8'))" — PASS
git diff --check — PASS
```

## Director Acceptance

Status:
ACCEPT as reconciliation audit gate.

Next:

- Resolve current review rows.
- Resolve lower-accounting counterparty for the 2077 open issued amount.
- User-facing label decided: `Некоммерческие поступления` (`non_commercial_income`).
- Run archive/raw dictionary cleanup as a separate sprint.

## Production Repair Addendum — 2026-08-09

Trigger:

- User reported a large discrepancy after month-close flow.
- Expected current operational transition/final balance was the accepted Claudia Z chain ending near `15260` (`15262.00` exact).

Finding:

- Local accepted chain was still correct:
  - `opening_cash=2870`
  - `entries=126`
  - `computed_cash_now=15262`
  - `balance_after` mismatches: `0`
- Production `Claudia Z` workspace had 17 historical rows from 2022/2024 inside the current operational workspace.
- Those rows belonged to archive/raw history, not to the current operational balance.
- Production cash flow opening was already correct (`2870`), but stale/historical rows pushed the visible final balance to `22433`.

Repair:

- Created local backup before mutation:
  - `storage/production-audits/claudia-z-prod-before-balance-recalc-20260809.json`
- Archived exactly 17 wrong current-workspace historical rows through the authenticated API, preserving audit behavior:
  - `storage/production-audits/claudia-z-prod-archived-wrong-current-history-20260809.json`
- Re-closed June 2026 through the normal month close API so `v2_monthly_closures` snapshot matches the repaired chain.
- Temporary maintenance endpoint used for dry-run investigation was removed from production and local tree.

Production verification after repair:

```text
summary.opening_cash=2870
summary.cash_now=15262
summary.card_expense_total=0
summary.latest_entry_date=2026-06-15
entries.all=126
entries.outside_2026=0
entries.last_balance_after=15262
monthly.2026-06.opening_cash=13714
monthly.2026-06.ending_cash=15262
monthly.2026-06.discrepancy_with_previous=0
monthly.2026-06.is_closed=true
layer1.2026-06.ending_cash=15262
```

Acceptance:

- Production now matches the accepted Claudia Z current operational balance chain.
- Historical rows remain excluded from the current workspace and must stay in archive/raw history only.

## Production Split Import Addendum — 2026-08-09

Trigger:

- User provided Google Drive report `Расходы 08.07.26.xlsx`.
- Source file:
  - `https://drive.google.com/file/d/13thCpBVXq4JD5n_c9cgxDVgEuZJV2nCr`
- Relevant sheet:
  - `Бюджет - Июнь`

Interpretation:

- Source row `15260 остаток с последнего отчета` is an opening/carry balance, not operational income.
- Existing accepted production chain before import ended at `15262`.
- The source file starts at `15260`, so the `-2` gap was preserved as an explicit transition correction instead of silently changing old history.
- Rows before first salary accrual were assigned to June.
- Rows from first salary through last salary were assigned to July.
- Rows after last salary through current report end were assigned to August.

Artifacts:

```text
storage/production-audits/claudia-z-prod-before-080726-split-import-20260809.json
storage/production-audits/claudia-z-080726-split-plan-20260809.json
storage/production-audits/claudia-z-080726-split-import-result-20260809.json
```

Applied split:

```text
created_count=106
transition_correction=-2
june_operational_rows=4
july_operational_rows=85
august_operational_rows=16
```

Production verification after split import:

```text
summary.opening_cash=2870
summary.cash_now=7037
summary.latest_entry_date=2026-08-09
entries.all=232
entries.last=-55 тур регистрация 15 дней

monthly.2026-06.opening_cash=13714
monthly.2026-06.ending_cash=14733
monthly.2026-06.entries=102
monthly.2026-06.corrections=-2
monthly.2026-06.discrepancy_with_previous=0
monthly.2026-06.is_closed=true

monthly.2026-07.opening_cash=14733
monthly.2026-07.ending_cash=9256
monthly.2026-07.entries=85
monthly.2026-07.discrepancy_with_previous=0

monthly.2026-08.opening_cash=9256
monthly.2026-08.ending_cash=7037
monthly.2026-08.entries=16
monthly.2026-08.discrepancy_with_previous=0
```

Acceptance:

- The Google Drive report was applied to the production Claudia Z workspace.
- The `15260` source balance is treated as a transition point, not as a new income row.
- The final cash balance now follows the source report through August current state: `7037`.

## Lower Accounting Archive Link Addendum — 2026-08-10

Trigger:

- User reported dangling lower-accounting participants `Вова` and `Евгения`.
- Goal: remove unclear open accountable-money tails by linking imported historical issues to their report evidence, without creating duplicate cash expenses.

Evidence:

- `Евгения`: issue row `06.06.xlsm` row 28, `-1762.00 у Евгении под отчет`.
- Closing evidence: `15.06.2026.xlsx` rows 88-99.
- Reported expense total: `1298.14`.
- Carryover balance: `463.86`.
- `Вова`: issue row `15.06.2026.xlsx` row 67, `-165.00 Володе под отчет`.
- Closing evidence: Google Drive file `Расходы 08.07.26.xlsx`, sheet `Бюджет - Июнь`, row 66, `-165 тик клинер и силер`.

Implemented:

- Added archive-only settlement links in:

```text
storage/imports/lower-accounting-archive-exceptions.json
```

- Stored report breakdown evidence on the archive exception, without creating duplicate operational cash/category rows.
- Hidden `closed_archive_exception` rows with zero open amount from the visible lower-accounting table.

Verification:

```text
2026-06 lower_accounting_total=0
Вова: issued=165, archive_closed=165, open=0, status=closed_archive_exception
Евгения: issued=1762, archive_closed=1762, open=0, status=closed_archive_exception
```

Acceptance:

- No open lower-accounting tail remains for Vova or Evgenia.
- Cash chain is not mutated.
- Category totals are not silently mutated by archive-only evidence.
- Archive evidence is preserved separately and must not train universal future rules.
