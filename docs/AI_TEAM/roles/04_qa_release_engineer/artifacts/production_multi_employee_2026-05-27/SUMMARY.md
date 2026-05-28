# Production Multi-Employee QA Summary - 2026-05-27

Status: BLOCKED for this scenario.

Production URL: `https://finance.brkovic.ltd`

Final successful production run:

- stamp: `20260527201737`
- group_id: `8`
- report_id: `66`
- admin user id: `18`
- employee user ids: `19`, `20`, `21`
- final package file: `closed_group_package.json`
- final report detail file: `final_report_detail.json`
- exports: `current_group_report.xls`, `final_group_report.xls`, `current_group_google_sheet.tsv`, `final_report_google_sheet.tsv`, `closed_group_package_print.html`

Expected financial control:

- total expenses: `EUR 284`
- group net balance: `EUR 716`
- admin cash left: `EUR 568`
- employee 1 remaining: `EUR 67`
- employee 2 overrun / reimbursement due: `EUR -36` / `EUR 36`
- employee 3 remaining: `EUR 117`

Production result:

- final detail totals: `income=1000`, `expense=284`, `cash_expense=284`, `admin_cash_left=532`, `employee_cash_left=184`, `cash_balance=716`, `balance=716`
- package summary: `received_money=1000`, `physical_cash_spent=284`, `admin_cash_left=532`, `accountable_money_left=184`, `cash_balance=716`, `balance=716`
- employee 1 remaining is present as open accountable `67`
- employee 3 no-spend remaining is present as open accountable `117`
- employee 2 overrun is visible in package audit refs as submitted advance with `status=discrepancy`, `expected_remaining=-36.00`, `actual_remaining=0.00`, `difference_amount=36.00`

Blocker:

- P0 financial-control mismatch: package/detail/export headline totals show `admin_cash_left=532` instead of the required `568`.
- The participant control equation `568 + 67 - 36 + 117 = 716` is not represented by the headline package/detail totals. Production package instead represents `532 + 184 = 716` and keeps the `36` overrun only in audit refs.
- Package `participants` contains the included admin Live Report participant only; employee money rows/accountable/audit refs are present, but employee participant report sections are not first-class package participants.
