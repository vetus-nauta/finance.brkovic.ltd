# SPRINT-52R — Employee Simple Mode and Accountable Offer Skeleton

## Director Sprint Opening

Sprint:

```text
SPRINT-52R — Employee Simple Mode and Accountable Offer Skeleton
```

Goal:

```text
Give accepted employees a limited workspace mode and add a non-financial accountable offer skeleton that does not mutate cash, reports, categories, or lower accounting.
```

Agents assigned:

```text
Security/Roles Agent — Goodall
Backend Accountable Offer Agent — Pasteur
QA Acceptance Agent — Schrodinger
Director — integration and acceptance
```

Exit criteria:

```text
1. Employee opening a restricted workspace does not attempt to load full operational finance.
2. Employee has a simple limited screen with own role/workspace context.
3. Owner/admin can create a pending accountable offer for an employee member/email.
4. Viewer/employee cannot create offers.
5. Employee can list only own offers.
6. Employee can accept only own pending offer.
7. Offer creation/acceptance is audited.
8. Offers do not create ledger entries, change cash/card balances, category totals, reports, or lower accounting.
9. Existing full workspace smokes remain green.
```

Guardrail:

```text
Do not implement employee expense entry/report submission.
Do not create cash movement on offer creation or acceptance.
Do not implement reimbursement/remaining/overrun settlement.
Do not loosen employee scoped visibility.
```

## Agent Reports

Goodall — Security/Roles Agent:

```text
Scoped employee access must remain outside the full operational workspace.
Employee-mode GET access is allowed only for limited own workspace context/offers.
Creating offers stays owner/admin-only.
Employee must not see other employee offers or mutate workspace finance.
```

Pasteur — Backend Accountable Offer Agent:

```text
Added accountable offer storage and repository methods.
Offer create/accept writes audit trail and no ledger entries.
Owner/admin can create/list workspace offers.
Employee can list and accept own pending offers only.
Runtime schema guard and SQL schema files include accountable offers table.
```

Schrodinger — QA Acceptance Agent:

```text
Acceptance requires API smoke coverage for owner/admin create, employee deny/create, employee own list, accept once, second accept conflict, no balance mutation, and no cross-employee leakage.
Browser smoke must continue to prove existing operational, summary, dictionary, archive, report, responsive, and closed-month workflows.
```

## Director Integration

Implemented:

```text
Repository/API:
- GET /api/workspaces/{id}/employee-mode
- GET /api/workspaces/{id}/accountable-offers
- POST /api/workspaces/{id}/accountable-offers
- POST /api/accountable-offers/{id}/accept

UI:
- restricted employee workspace screen
- hall button for owner/admin accountable offer creation
- employee own pending/accepted offer cards
- employee accept action
- mobile/light-mode aware browser smoke route fixes

Schema:
- v2_accountable_offers/accountable_offers table in both clean-core SQL files
```

Files:

```text
app/v2/Repository.php
app/v2/Api.php
public/v2.php
public/assets/v2/app.js
public/assets/v2/app.css
scripts/v2_http_api_smoke.php
scripts/v2_operational_browser_smoke.cjs
FinDesk v2.0/sql/001-clean-core-mariadb.sql
FinDesk v2.0/sql/clean-core-schema.sql
```

## Evidence

Passed locally:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l scripts/v2_http_api_smoke.php
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
php scripts/v2_clean_core_static_smoke.php
git diff --check
bash scripts/v2_http_api_smoke.sh
npm run smoke:v2
node scripts/v2_report_fragment_browser_smoke.cjs
bash scripts/v2_operational_browser_smoke.sh
```

Browser evidence:

```text
Operational screenshots and layout metrics:
test-results/v2-browser-smoke

Report fragment screenshots:
test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786388250164
```

## Acceptance

Status:

```text
Accepted locally.
Production deployment is not part of this sprint acceptance.
Employee report submission, offer settlement, reimbursement/overrun logic, and real cash issue linkage remain future sprints.
```
