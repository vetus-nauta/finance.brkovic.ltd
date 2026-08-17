# SPRINT-54R — Admin Accountable Report Review and Acceptance Gate

## Director Sprint Opening

Sprint:

```text
SPRINT-54R — Admin Accountable Report Review and Acceptance Gate
```

Goal:

```text
Let owner/admin review submitted employee accountable reports, see issued/spent/remaining/overrun numbers, and make one explicit acceptance decision without hidden employee-side ledger mutation.
```

Agents assigned:

```text
Security/Roles Agent — Turing
Backend Accountable Settlement Agent — Singer
QA Acceptance Agent — Godel
Director — integration and acceptance
```

Exit criteria:

```text
1. Owner/admin can list submitted accountable reports from Hall/workspace context.
2. Owner/admin can see report rows and settlement preview: issued, spent, remaining, overrun.
3. Owner/admin can accept a submitted report exactly once.
4. Acceptance is audited.
5. Employee cannot accept/reopen/admin-review their own report.
6. Viewer cannot access admin report queue.
7. Another employee cannot see or mutate another employee report.
8. Duplicate acceptance is blocked.
9. Existing operational/report/category behavior remains green.
10. Any operational-ledger effect must be explicit, source-traced, and impossible to duplicate.
```

Guardrail:

```text
Do not silently change cash/card on employee submit.
Do not hide employee remaining/overrun.
Do not create duplicate common entries from the same employee report row.
Do not accept reports from draft/cancelled states.
Do not make employee scoped mode a backdoor into full workspace finance.
```

Director scope decision:

```text
This sprint is the acceptance gate. The safest MVP path is to store admin acceptance and settlement preview first, then only add common-ledger conversion if the code can prove idempotent source tracing per report row.
```

## Agent Reports

Security/Roles Agent — Turing:

```text
Admin acceptance must be a new explicit owner/admin action. Employee submit/list/open must not mutate the common ledger. Employee cannot accept, settle, close, or see another employee report. Accept must lock the report, require submitted state, block duplicate accept, write audit, and expose settlement numbers.
```

Backend Accountable Settlement Agent — Singer:

```text
Recommended endpoints: report detail, review preview, accept. Recommended state: submitted -> accepted_by_admin, with row review statuses and one settlement record per report. Do not overload v2_entries source_id. Ledger materialization should wait until accountable cash issue and no-double-cash semantics are explicitly modeled.
```

QA Acceptance Agent — Godel:

```text
HTTP smoke must prove owner/admin sees submitted reports, employee/viewer cannot accept, preview exposes issued/remaining/overrun, accept works once, duplicate accept is blocked, and create/submit/accept do not silently change v2_entries or cash/card summary in this gate sprint.
```

## Director Integration

Implemented:

```text
Backend/API:
- GET /api/accountable-reports/{report_id}
- POST /api/accountable-reports/{report_id}/review-preview
- POST /api/accountable-reports/{report_id}/accept

Schema:
- accountable report admin review fields;
- accountable report row review fields;
- v2_accountable_settlements settlement snapshot table;
- clean-core SQL and runtime schema guard synchronized.

UI:
- Hall workspace card has "Отчеты" for owner/admin;
- submitted employee reports can be loaded from Hall;
- owner/admin can accept a submitted report from Hall;
- accepted report leaves the submitted queue.
```

Explicitly deferred:

```text
Accepted employee report rows are not materialized into v2_entries in SPRINT-54R.
Physical accountable cash issue is not created automatically.
Return/reimbursement cash settlement actions are not created automatically.
PDF/HTML employee report storage is not part of this sprint.
```

Why:

```text
SPRINT-52R intentionally made accountable offer non-financial.
If SPRINT-54R created normal cash entries from employee cash rows now, cash could be reduced twice or appear as expense without the matching physical issue model.
The accepted result is therefore a controlled review/settlement gate, ready for a dedicated materialization sprint.
```

## Evidence

Commands:

```text
node --check public/assets/v2/app.js
php -l app/v2/Repository.php && php -l app/v2/Api.php && php -l scripts/v2_http_api_smoke.php
php scripts/v2_clean_core_static_smoke.php
bash scripts/v2_http_api_smoke.sh
npm run smoke:v2:browser
git diff --check
```

Passed locally:

```text
HTTP smoke confirms submitted report detail, admin preview, admin accept, employee/viewer denial, duplicate accept guard, one settlement snapshot, and no v2_entries/cash/card summary mutation.
Browser smoke confirms existing auth, Hall, operational, archive, summary, training, report, phone, iPad, and desktop layouts remain green.
Screenshots: test-results/v2-browser-smoke
```
