# SPRINT-55R — Accountable Report Ledger Projection

## Director Sprint Opening

Sprint:

```text
SPRINT-55R — Accountable Report Ledger Projection
```

Goal:

```text
After admin acceptance, explicitly project accepted employee report rows into the common operational/category layer without hidden cash/card mutation and without duplicate projection.
```

Agents assigned:

```text
Security/Roles Agent — Kepler
Backend Materialization Agent — Galileo
QA Acceptance Agent — Ptolemy
Director — integration and acceptance
```

Scope decision:

```text
Materialization is a separate owner/admin-only action after accepted_by_admin.
It creates cash-neutral accountable projection entries, not physical cash movements.
The entries are visible for categories/reports/source trace, but they do not change cash_now or card_expense_total.
Physical cash issue, cash return, and reimbursement remain separate explicit actions.
```

Exit criteria:

```text
1. Admin accept remains review-only.
2. Owner/admin can explicitly materialize an accepted_by_admin report.
3. Employee/viewer cannot materialize.
4. Draft/submitted/cancelled reports cannot materialize.
5. Accepted/adjusted rows create exactly one linked projection entry each.
6. Rejected rows create no entry.
7. Duplicate materialization creates no duplicate entries.
8. Projection entries carry accountable report source trace through a link table and matched rules.
9. Category totals include projected accepted rows.
10. cash_now and card_expense_total remain unchanged by projection.
```

Guardrail:

```text
Do not overload v2_entries.source_id with accountable report ids.
Do not create cash/card flow movement from employee report projection.
Do not hide that this is cash-effect-none projection.
Do not allow normal employee scoped APIs to materialize.
```

## Agent Reports

Security/Roles Agent — Kepler:

```text
Accepted. Materialization must be owner/admin-only after accepted_by_admin.
Employee-owned submitted reports must remain invisible to employee materialization APIs.
Projection entries must not be mutable through normal entry update/category/delete endpoints.
```

Backend Materialization Agent — Galileo:

```text
Accepted. Use a dedicated accountable flow and link table.
Do not reuse v2_entries.source_id because it is an import source FK in the clean core.
Use report row idempotency so retry creates zero duplicate entries.
```

QA Acceptance Agent — Ptolemy:

```text
Accepted with tests. Admin accept remains review-only.
Materialization creates exactly one projection entry per accepted/adjusted row.
Summary cash/card totals must remain unchanged.
```

## Implementation

Files:

```text
app/v2/Api.php
app/v2/Repository.php
FinDesk v2.0/sql/001-clean-core-mariadb.sql
FinDesk v2.0/sql/clean-core-schema.sql
scripts/v2_http_api_smoke.php
```

API:

```text
GET  /api/accountable-reports/{id}/materialization
POST /api/accountable-reports/{id}/materialization-preview
POST /api/accountable-reports/{id}/materialize
```

Data model:

```text
v2_flows.type now includes accountable.
v2_entries.entry_type now includes accountable_expense.
v2_entries.source_type now includes accountable_report.
v2_accountable_reports has ledger_materialization_* readback fields.
v2_accountable_report_rows has operational_entry_id.
v2_accountable_report_entry_links stores report-row-to-entry projection links and idempotency keys.
```

Behavior:

```text
Admin accept creates settlement snapshot only.
Materialize is an explicit second action.
Projection entries use accountable flow, source_type accountable_report, entry_type accountable_expense, status accepted.
Projection entries are included in category/source reports through normal accepted-entry reporting.
Projection entries have cash_effect none and do not change cash_now/card_expense_total.
Normal entry update/category/delete rejects projection entries with accountable_projection_entry_immutable.
```

## Evidence

Commands:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l scripts/v2_http_api_smoke.php
node --check public/assets/v2/app.js
php scripts/v2_clean_core_static_smoke.php
bash scripts/v2_http_api_smoke.sh
git diff --check
```

Result:

```text
PASS — PHP syntax.
PASS — JS syntax.
PASS — clean core static smoke.
PASS — HTTP API smoke, including materialization idempotency and immutable projection guards.
PASS — whitespace check.
```

## Director Acceptance

```text
SPRINT-55R accepted locally.
The employee-report-to-ledger bridge is now safe enough for MVP integration:
accepted employee report rows can be projected into the common category layer without silently changing physical cash/card balances.
```
