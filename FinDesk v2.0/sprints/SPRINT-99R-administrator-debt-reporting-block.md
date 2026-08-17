# SPRINT-99R — Administrator Debt Reporting Block

Date: 2026-08-17

## Objective

Separate administrator personal debt from operational yacht expenses and from lower/accountable money.

## Director Decision

`Задолженность администратора` is a liability/reporting block.

It is not:
- a yacht expense category;
- employee/guest accountable money;
- a lower-accounting settlement row;
- a reason to change physical cash/card arithmetic.

## Accepted Basis

Claudia Z administrator personal credit chain:

```text
taken: 7000.00 EUR
  7 issues x 1000.00 EUR
returned: 3500.00 EUR
  7 returns x 500.00 EUR
opening administrator debt: 3500.00 EUR
basis date: 2026-04-24
```

Opening basis is stored in `v2_workspace_liability_openings`.
The same breakdown is stored in `source_json` and exposed in `blocks.admin_debt.basis_breakdown` for UI and HTML report tooltips.

## Classification Rules

Administrator-personal phrases route to `accounting_section=admin_debt`, for example:

```text
мой кредит
моя часть кредита
кредит себе
последний кредит
мой долг
для себя
себе
домой
с тему / temu
мото навигатор
```

Generic business/yacht debt such as `долг таможне` must stay operational and may still be categorized as yacht/legal/current expense by context.

## Implementation

- Added `admin_debt` accounting section.
- Added `admin_debt_total` to Layer 1 summary totals and source trace.
- Added `blocks.admin_debt` to monthly summary, operational report fragments, and report packages.
- Added admin debt block to the summary UI and generated HTML reports.
- Expanded the admin debt tooltip into a dry arithmetic list: original 7 x 1000, returned 7 x 500, basis remainder, period movement, current remainder.
- Added `v2_workspace_liability_openings` to runtime and schema contracts.
- Preserved cash arithmetic: source entries still affect cash/card normally.

## Claudia Z Evidence

```text
2026-06 admin debt: 3600.00 EUR
  opening 3500.00 + -100.00 мото навигатор

2026-07 admin debt: 3687.00 EUR
  opening 3600.00 + -87 я заказал с тему

2026-08 admin debt: 3687.00 EUR
  opening 3687.00, no new movement
```

Full artifact:

```text
storage/imports/claudia-z-reconciliation/admin-debt-audit-20260817-103554.json
```

## QA

- `php -l app/v2/Repository.php` — PASS.
- `node --check public/assets/v2/app.js` — PASS.
- `git diff --check` — PASS.
- `php scripts/v2_claudia_z_reconciliation_audit.php` — PASS, cash diff `0`.
- Browser smoke: Hall → Claudia Z → Summary shows `Задолженность администратора`, `3 687,00 €`, and `переходящий остаток`.

Screenshot:

```text
test-results/v2-admin-debt-smoke/summary-admin-debt.png
```

## Acceptance

Accepted locally as an intermediate MVP reporting correction. It is ready for the next deploy/sync gate, but deployment is a separate action.
