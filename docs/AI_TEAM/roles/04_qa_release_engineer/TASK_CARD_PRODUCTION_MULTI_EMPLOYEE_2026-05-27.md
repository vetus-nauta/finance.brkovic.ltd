# QA Task Card: Production Multi-Employee Money Flow

Date: 2026-05-27

Owner: QA Release Engineer

Priority: P0 production acceptance scenario after MVP deploy.

## Read First

- `docs/AI_TEAM/roles/01_product_finance_architect/PRODUCTION_MULTI_EMPLOYEE_FINANCIAL_CONTROL_2026-05-27.md`
- `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`
- `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Production Scenario

Use production `https://finance.brkovic.ltd`.

Create a new QA group with:

- one admin;
- three employees.

Use a unique timestamp in group/user/display names.

Do not use real client/customer data.

## Money Inputs

Admin:

- receives `EUR 1000`;
- spends `EUR 20`, `45`, `17`, `4`.

Accountable cash issued by admin:

- employee 1: `EUR 135`;
- employee 2: `EUR 94`;
- employee 3: `EUR 117`.

Employee expenses:

- employee 1: `EUR 6`, `9`, `43`, `10`;
- employee 2: `EUR 12`, `23`, `41`, `54`;
- employee 3: no expenses.

## Expected Financial Control

- total received: `EUR 1000`;
- admin issued to employees: `EUR 346`;
- admin own expenses: `EUR 86`;
- employee 1 expenses: `EUR 68`;
- employee 2 expenses: `EUR 130`;
- employee 3 expenses: `EUR 0`;
- total expenses: `EUR 284`;
- admin cash left before any employee cash return: `EUR 568`;
- employee 1 remaining: `EUR 67`;
- employee 2 overrun: `EUR -36` / reimbursement due `EUR 36`;
- employee 3 remaining: `EUR 117`;
- group net balance: `EUR 716`;
- control equation: `568 + 67 - 36 + 117 = 716`.

## Required QA Flow

1. Create admin and three employee accounts/sessions.
2. Create group and add all employees.
3. Enter admin received cash `EUR 1000`.
4. Issue accountable cash to employees: `135`, `94`, `117`.
5. Enter admin own expenses.
6. Enter employee 1 expenses in their accountable/Live Report flow.
7. Enter employee 2 expenses in their accountable/Live Report flow.
8. Do not create fake expense for employee 3.
9. Submit/check/accept employee reports where the MVP flow supports it.
10. Verify employee 2 overrun is visible and not lost after submit/accept.
11. Verify employee 3 no-spend remainder is visible as accountable remainder/carryover or explicitly returned cash if QA performs return-cash action.
12. Save/export:
    - admin/current report;
    - employee 1 report;
    - employee 2 report;
    - employee 3 no-spend/accountable state;
    - final group report;
    - closed group package/archive.
13. Finalize/save the group report.
14. Verify submitted cards/reports are in archive/final package.
15. Download Excel/print/PDF where available.

If MVP does not provide separate printable employee reports, save one full closed group report/package and list exactly where each employee section appears.

## Artifacts

Store full QA evidence in this role folder, not in the Project Director chat.

Recommended local artifact folder:

- `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/`

Save:

- downloaded Excel files or print/PDF outputs;
- screenshots only if useful;
- ids: admin user, employee users, group id, advance ids, tape/card ids, final report id, package proof/export references;
- a full `FINDINGS.md` section with step-by-step pass/fail.

## Stop / P0 Criteria

- total expenses differ from `EUR 284`;
- group net balance differs from `EUR 716`;
- employee 2 overrun `EUR 36` is hidden, sign-flipped, or lost after accept/finalization;
- employee 3 `EUR 117` is lost or turned into fake expense;
- admin/employee/group exports disagree;
- submitted cards do not appear in archive/final package;
- report/export/save/print path is unavailable for the accepted MVP alternative;
- cross-user access exposes another employee report without group/reviewer rights.

## Short Report Back

Use one short report only:

```text
Роль:
Задача:
Статус:
Обновлены файлы:
- <path>
Доказательство:
- group_id=<id>; report_id=<id>; artifacts=<path>; Details: see FINDINGS.md
Блокер:
Следующий владелец:
```
