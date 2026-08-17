# SPRINT-50R — Scoped Visibility and Employee Role Security Gate

## Director Sprint Opening

Sprint:

```text
SPRINT-50R — Scoped Visibility and Employee Role Security Gate
```

Goal:

```text
Build the backend permission boundary required before employee invitations and accountable reports can exist.
```

Required files read:

```text
FinDesk v2.0/33-director-agent-orchestration-protocol.md
FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md
FinDesk v2.0/sql/001-clean-core-mariadb.sql
app/v2/Api.php
app/v2/Repository.php
public/v2-api.php
scripts/v2_http_api_smoke.php
scripts/v2_report_fragment_browser_smoke.cjs
```

Agents assigned:

```text
Security/Roles Agent — Hume
Backend Access-Control Agent — Carver
QA Acceptance Agent — Tesla
Director — integration and acceptance
```

Agent tasks:

```text
Agent: Security/Roles Agent
Scope: Endpoint and repository permission risk audit.
Files to read: app/v2/Repository.php, app/v2/Api.php, FinDesk v2.0/sql/001-clean-core-mariadb.sql.
What to check: where a restricted workspace member can read full ledger, summary, reports, training, import, attachments, or mutate data.
What to change if allowed: no code changes.
What not to touch: formulas, parser, reports generation, deploy.
Report required: P0/P1 gaps, recommended SPRINT-50R implementation, negative tests.

Agent: Backend Access-Control Agent
Scope: Minimal scoped visibility backend implementation.
Files to read: app/v2/Repository.php, app/v2/Api.php, public/v2-api.php, FinDesk v2.0/sql/001-clean-core-mariadb.sql.
What to check: current role enum, membership checks, read/write guards.
What to change if allowed: restricted to backend access-control if safe.
What not to touch: invitations, offers, employee UI, financial formulas, parser.
Report required: changed files or patch plan, risks, checks.

Agent: QA Acceptance Agent
Scope: Employee role security acceptance.
Files to read: scripts/v2_http_api_smoke.php, scripts/v2_report_fragment_browser_smoke.cjs, app/v2/Repository.php, app/v2/Api.php.
What to check: how to seed owner + employee member and verify employee receives 403 or restricted own data.
What to change if allowed: no code changes.
What not to touch: product logic.
Report required: concrete tests and commands.
```

Expected reports:

```text
3 agent reports before sprint acceptance.
```

Exit criteria:

```text
1. Workspace member role enum supports employee without enabling invitations.
2. Workspace list/get includes role metadata and access scope.
3. owner/admin/assistant/current finance role keeps existing full workspace behavior.
4. viewer remains read-only.
5. employee can see Hall workspace metadata but cannot access full workspace ledger, summary, reports, training, import, month closing, settings, or category training.
6. employee entries endpoint cannot expose entries created by other users.
7. employee cannot create normal operational entries through the full workspace API in this sprint.
8. Existing owner/admin smoke tests remain green.
9. No invitation, accountable offer, employee simple journal, report merge, or settlement formula is implemented.
```

Risks:

```text
1. Role enum migration can break existing databases if not backward-compatible.
2. Blocking too broadly may break current owner/admin flows.
3. Allowing employee writes through normal entries API would prematurely bypass future admin acceptance.
4. Report/source endpoints are high-risk because they can leak full source traces.
```

## Implementation Guardrail

SPRINT-50R must not implement:

```text
invite links
accountable offers
employee simple journal UI
admin report acceptance
remaining/overrun settlement
email delivery
```

## Agent Reports

Security/Roles Agent — Hume:

```text
Status: completed.
Finding: previous membership checks were too broad for employee invitations because getWorkspace/list membership could unlock full ledger, summary, reports, dictionary training, import review, raw history, source traces, and attachments.
Required direction: introduce explicit role/scope policy; employee must be fail-closed from full workspace reads and writes; direct entry/attachment access must be row-scoped.
Negative tests requested: employee full reports/settings/dictionary/import APIs return 403; employee entries cannot expose owner/admin rows; direct object entry lookup must not leak.
```

Backend Access-Control Agent — Carver:

```text
Status: completed.
Changed: app/v2/Repository.php and app/v2/Api.php.
Result: added role/access policy for owner/admin/assistant/finance/viewer/employee; employee defaults to own_entries; full operational write API remains blocked for employee; full workspace picture routes are guarded; entries endpoint is scoped; direct entry and attachment lookup are row-level checked.
Risk noted: assigned_actor scope depends on assigned_actor_id; employee simple-entry UI/offers/invites are intentionally not implemented in this sprint.
```

QA Acceptance Agent — Tesla:

```text
Status: completed.
Acceptance plan: seed owner + employee; verify employee Hall tile metadata; verify employee cannot read flows, summary, monthly/layer1/category reports, source entries, dictionary queue, raw history, assistant settings; verify employee entries read only own rows; verify employee cannot mutate workspace, flows, entries, month close, report fragments/packages, category rules.
```

## Director Integration

Files changed for SPRINT-50R:

```text
app/v2/Repository.php
app/v2/Api.php
FinDesk v2.0/sql/001-clean-core-mariadb.sql
FinDesk v2.0/sql/clean-core-schema.sql
scripts/v2_http_api_smoke.sh
scripts/v2_http_api_smoke.php
FinDesk v2.0/sprints/SPRINT-50R-scoped-visibility-employee-role-security-gate.md
```

Implemented:

```text
1. Workspace membership schema now supports finance and employee roles.
2. Workspace membership schema now supports access_scope: workspace, own_entries, assigned_actor, none.
3. Workspace list/get returns role label, access scope, can_read_workspace, can_read_entries, can_write, can_admin.
4. employee role defaults to own_entries when scope is missing or unsafe.
5. employee can see workspace presence in Hall metadata but cannot read full workspace finance.
6. employee GET entries returns only rows created by the employee, or assigned actor rows when assigned_actor_id is configured.
7. employee full workspace APIs are blocked with workspace_scope_required.
8. employee full operational writes and parse preview are blocked with workspace_read_only.
9. direct entry and attachment lookup enforce row-level visibility.
10. viewer remains full-workspace read-only.
```

Not implemented by design:

```text
invite links
accountable offers
employee simple journal UI
admin report acceptance
remaining/overrun settlement
email delivery
```

Checks:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l public/v2-api.php
php -l scripts/v2_http_api_smoke.php
bash -n scripts/v2_http_api_smoke.sh
git diff --check
npm run smoke:v2
bash scripts/v2_http_api_smoke.sh
node scripts/v2_report_fragment_browser_smoke.cjs
```

Evidence:

```text
npm run smoke:v2: OK
bash scripts/v2_http_api_smoke.sh: OK, includes employee scoped visibility and full-workspace denial checks.
node scripts/v2_report_fragment_browser_smoke.cjs: OK, screenshots saved in test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786381988633
```

## Director Acceptance

```text
SPRINT-50R accepted locally.
Production/deploy acceptance is not claimed in this sprint.
Next sprint can start employee invitation and accountable offer contracts on top of this permission boundary.
```
