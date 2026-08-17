# SPRINT-57R — Hall Accountable Control Center

## Director Sprint Opening

Sprint:

```text
SPRINT-57R — Hall Accountable Control Center
```

Goal:

```text
Make Hall show a human admin control center for employee money under report:
who has money, what is waiting for review, what must be returned, what must be reimbursed,
and which accepted reports are already included in category accounting.
```

Agents assigned:

```text
Product/UX Reviewer — Wegener
Security/Finance Reviewer — Faraday
QA Acceptance Reviewer — Peirce
Director — integration, implementation, acceptance
```

## Scope

```text
Add an admin-only accountable dashboard read model.
Keep employee/report materialization cash-card isolated.
Expose a compact Hall section named Под отчет.
Keep raw report row detail out of the dashboard payload.
Refresh dashboard and report queue after accept/materialize actions.
```

## Implementation

Files:

```text
app/v2/Api.php
app/v2/Repository.php
public/assets/v2/app.js
public/assets/v2/app.css
scripts/v2_http_api_smoke.php
scripts/v2_operational_browser_smoke.cjs
```

API:

```text
GET /api/workspaces/{workspace}/accountable-dashboard
```

Rules:

```text
Owner/admin only.
Offer-rooted issued totals: pending offers do not count as issued.
Reports and settlements are compact facts, not raw employee expense rows.
cash_delta = 0, card_delta = 0.
Policy: cash_card_effect_none_read_model.
```

Hall UI:

```text
Оферта -> Выдать под отчет
Отчеты сотрудников -> Под отчет
Dashboard KPIs: Выдано, На проверке, К возврату, К возмещению
Permanent note: Без изменения кассы и карты
Employee rows show open position, review count, not-in-accounting count, in-accounting count.
```

## Agent Reports

Product/UX Reviewer — Wegener:

```text
Accepted direction. Admin view must answer: у кого деньги/хвост и что надо сделать.
Use human statuses and a single Hall section Под отчет.
```

Security/Finance Reviewer — Faraday:

```text
Accepted with guardrails. Do not use the wide report list as aggregate.
Use a separate admin-only read model, count issued amount from offers only once,
keep settlements/report facts separate, and preserve cash/card no-mutation.
```

QA Acceptance Reviewer — Peirce:

```text
Accepted criteria. Dashboard must cover offers + reports + four visible totals,
role/security checks, no cash/card mutation, and existing accept/materialize/idempotency flow.
```

## Evidence

Commands:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
php scripts/v2_clean_core_static_smoke.php
bash scripts/v2_http_api_smoke.sh
npm run smoke:v2:browser
git diff --check
```

Result:

```text
PASS — PHP syntax.
PASS — JS syntax.
PASS — clean core static smoke.
PASS — HTTP API smoke with accountable dashboard assertions.
PASS — browser UI smoke with Hall Под отчет path.
PASS — whitespace check.
```

## Director Acceptance

```text
SPRINT-57R accepted locally.
Hall now has a compact admin control center for employee accountable money.
The flow remains financially safe: dashboard and materialization do not mutate cash/card balances.
```
