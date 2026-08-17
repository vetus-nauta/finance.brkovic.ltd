# SPRINT-53R — Employee Simple Report Submission Skeleton

## Director Sprint Opening

Sprint:

```text
SPRINT-53R — Employee Simple Report Submission Skeleton
```

Goal:

```text
Let a limited employee create simple accountable report rows for an accepted accountable offer and submit that report to the admin review queue without mutating the common operational ledger, balances, categories, reports, or lower accounting.
```

Agents assigned:

```text
Security/Roles Agent — Anscombe
Backend Accountable Report Agent — James
QA Acceptance Agent — Einstein
Director — integration and acceptance
```

Exit criteria:

```text
1. Employee can create a report only for their own accepted accountable offer.
2. Employee can assemble simple report rows before submit.
3. Employee can remove locally assembled rows before submit.
4. Employee can submit the report to admin review.
5. Owner/admin can list submitted employee reports for the workspace.
6. Other employees cannot see or mutate another employee report.
7. Viewer cannot see employee report admin queue.
8. Creating/submitting employee reports does not create v2_entries.
9. Creating/submitting employee reports does not change workspace cash/card summary.
10. Actions are audited.
```

Guardrail:

```text
Do not accept employee report into the common ledger.
Do not settle remaining/overrun amounts.
Do not reduce cash/card on report row create or submit.
Do not expose full workspace finance to employee.
Do not implement attachments in this sprint.
```

## Agent Reports

Security/Roles Agent — Anscombe:

```text
Employee report submission must stay storage-only. Employee can only work inside own scoped surface and own accepted offer. Submit must be a status transition, not a finance mutation. Owner/admin must receive submitted reports through an admin route, while employees remain isolated from other users and from full workspace finance.
```

Backend Accountable Report Agent — James:

```text
Added v2_accountable_reports and v2_accountable_report_rows. Added API endpoints for workspace accountable reports and submit action. Added smoke checks for CSRF, employee-only creation, submitted visibility, second-submit guard, and no v2_entries/summary mutation.
```

QA Acceptance Agent — Einstein:

```text
Acceptance requires employee own-offer create/submit, wrong-role denial, owner/admin submitted visibility, draft invisibility before submit, second-submit conflict, and before/after checks proving operational ledger and summary totals do not change.
```

## Director Integration

Implemented:

```text
Backend/API:
- GET /api/workspaces/{workspace_id}/accountable-reports
- POST /api/workspaces/{workspace_id}/accountable-reports
- POST /api/accountable-reports/{report_id}/submit

Employee UI:
- accepted accountable offer shows a simple "Мой отчет" form;
- employee adds date/sum/description rows locally;
- employee can remove a local row before submit;
- submit creates the report and immediately sends it to admin review;
- submitted report is shown back to employee as status/total/row count.
```

Deferred:

```text
Persistent employee draft editing is not accepted in this sprint.
Admin acceptance into the common ledger is not accepted in this sprint.
Settlement of employee remainder/overrun is not accepted in this sprint.
Attachments are not accepted in this sprint.
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

Result:

```text
All listed checks passed locally.
Full HTTP smoke confirms accountable report submit does not create v2_entries and does not change cash/card summary.
Browser smoke passed desktop, phone, iPad mini, iPad 11, summary, training, archive, report, and operational input checks.
Screenshots: test-results/v2-browser-smoke
```

## Director Acceptance

Status:

```text
SPRINT-53R accepted locally as employee simple report submission skeleton.
The implementation is deliberately storage-only and ready for the next sprint: admin review / acceptance / settlement gate.
```
