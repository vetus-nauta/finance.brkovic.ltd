# SPRINT-56R — Hall Accountable Materialization UI

## Director Sprint Opening

Sprint:

```text
SPRINT-56R — Hall Accountable Materialization UI
```

Goal:

```text
Make the accepted employee report materialization path visible and usable in Hall UI:
Принять отчет -> Включить в учет -> Уже в учете.
```

Agents assigned:

```text
UX/Localization Reviewer — Hooke
QA Acceptance Reviewer — Gibbs
Director — implementation, integration, final acceptance
```

## Scope

```text
Expose accepted employee reports in Hall without changing the older default submitted-report API contract.
Add a Hall-specific report list status filter.
Add a human confirmation before materialization.
Keep the backend cash-effect-none policy visible to the admin.
```

## Implementation

Files:

```text
app/v2/Repository.php
public/assets/v2/app.js
public/assets/v2/app.css
scripts/v2_operational_browser_smoke.cjs
```

Behavior:

```text
GET /api/workspaces/{workspace}/accountable-reports?status=hall_open returns submitted and accepted_by_admin reports for Hall.
Submitted reports show Принять отчет.
Accepted, not materialized reports show Включить в учет.
Materialized reports show disabled Уже в учете.
The confirmation says that cash/card balances do not change.
The UI reloads the Hall queue after accept/materialize.
```

UX wording:

```text
submitted -> На проверке
accepted_by_admin -> Принят
not_materialized -> Не включен в учет
materialized -> Уже в учете
Hall button -> Отчеты сотрудников
No-mutation copy -> Без изменения кассы и карты
```

## Agent Reports

UX/Localization Reviewer — Hooke:

```text
Accepted. Use human labels, separate report acceptance from accounting inclusion, and explicitly state cash/card no-mutation.
```

QA Acceptance Reviewer — Gibbs:

```text
Accepted. Backend smoke already covers idempotency and no cash/card mutation; add browser evidence for Hall button, confirmation, and final already-in-accounting state.
```

## Evidence

Commands:

```text
php -l app/v2/Repository.php
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
PASS — HTTP API smoke.
PASS — browser UI smoke, including Hall accountable materialization UI.
PASS — whitespace check.
```

## Director Acceptance

```text
SPRINT-56R accepted locally.
The employee report inclusion path is now visible in Hall and can be checked by hand:
accepted report -> preview/confirmation -> included in accounting -> no cash/card mutation.
```
