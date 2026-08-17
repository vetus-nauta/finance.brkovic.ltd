# SPRINT-49R — Hall Shell and Workspace Role Router

## Director Sprint Opening

Sprint:

```text
SPRINT-49R — Hall Shell and Workspace Role Router
```

Goal:

```text
Implement the minimal post-auth Hall shell for FinDesk v2.0: workspace tiles, create workspace route, open selected workspace route, role labels, and safe return from workspace to Hall.
```

Required files read:

```text
FinDesk v2.0/17-screen-registry.md
FinDesk v2.0/33-director-agent-orchestration-protocol.md
FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md
FinDesk v2.0/sprints/SPRINT-48R-hall-roles-accountable-workflow-opening.md
public/v2.php
public/assets/v2/app.js
public/assets/v2/app.css
app/v2/Repository.php
app/v2/Api.php
```

Agents assigned:

```text
Frontend Hall Shell Agent — Euler
Backend Role Metadata Agent — Averroes
QA Acceptance Agent — Kant
Director — integration and acceptance
```

Agent tasks:

```text
Agent: Frontend Hall Shell Agent
Scope: Minimal Hall screen and role router UI.
Files to read: public/v2.php, public/assets/v2/app.js, public/assets/v2/app.css.
What to check: current auth/create/workspace visibility, screen router, workspace select.
What to change if allowed: minimal Hall shell only.
What not to touch: invitations, employee entries, accountable offers, financial formulas, parser, reports, deploy.
Report required: changed files, behavior, risks, checks.

Agent: Backend Role Metadata Agent
Scope: Workspace list/get metadata for Hall tiles.
Files to read: app/v2/Repository.php, app/v2/Api.php, FinDesk v2.0/sql/001-clean-core-mariadb.sql.
What to check: whether workspace responses include role/type enough for Hall.
What to change if allowed: minimal response metadata only.
What not to touch: employee role enablement, scoped reads, invitation tables, formulas, parser.
Report required: API shape, changed files, risks, checks.

Agent: QA Acceptance Agent
Scope: Hall shell acceptance.
Files to read: public/v2.php, public/assets/v2/app.js, scripts/v2_http_api_smoke.php, scripts/v2_report_fragment_browser_smoke.cjs.
What to check: auth lands in Hall, opening workspace enters journal, create workspace still works, existing smokes remain green.
What to change if allowed: no code changes.
Report required: acceptance checklist, manual screenshots, smoke additions, risks.
```

Expected reports:

```text
3 agent reports before sprint acceptance.
```

Exit criteria:

```text
1. Authenticated user with workspaces lands in Hall, not directly in Operational Journal.
2. User can open a workspace from Hall and reach Operational Journal.
3. User can return to Hall from workspace.
4. User can create a workspace if none exists or from Hall.
5. Hall does not show reports/charts/finance input.
6. Existing entry/report smoke tests remain green.
7. No employee invitation or limited employee data mode is enabled in this sprint.
```

Risks:

```text
1. Hall may accidentally become dashboard-first.
2. Existing smoke tests may assume auto-open workspace.
3. Workspace role metadata may be missing or inconsistent.
4. Mobile header may become overcrowded if Hall button is added poorly.
```

## Implementation Guardrail

SPRINT-49R must not implement:

```text
invite links
employee role
accountable offers
employee simple journal
admin acceptance
settlement formulas
```

Those belong to later sprints after scoped visibility is designed and tested.

## Agent Reports Received

```text
Frontend Hall Shell Agent — received.
Backend Role Metadata Agent — received.
QA Acceptance Agent — received.
```

### Frontend Hall Shell Agent

Result:

```text
Implemented minimal Hall shell/router in public/v2.php, public/assets/v2/app.js, public/assets/v2/app.css.
Authenticated users land in Hall by default.
Hall renders workspace tiles.
Open workspace enters existing operational UI.
Create workspace opens existing create form.
Direct workspace routes may still bypass Hall.
No invite, employee, accountable offer, formula, parser, report, or deploy behavior was added.
```

Risk noted:

```text
Role labels need backend role metadata; otherwise UI falls back to generic participant labels.
```

### Backend Role Metadata Agent

Result:

```text
Workspace list/get responses now include role metadata:
role
role_label
membership_status
access_scope
can_write
can_admin
```

Guardrail:

```text
No employee-scoped read mode was enabled.
membership_status is currently derived as active because membership lifecycle schema does not exist yet.
access_scope remains workspace for current broad-access roles.
```

### QA Acceptance Agent

Required selectors:

```text
[data-v2-hall]
[data-v2-hall-workspace-list]
[data-v2-hall-workspace-tile]
[data-v2-hall-workspace-open]
[data-v2-hall-create-open]
[data-v2-hall-role]
```

Negative acceptance:

```text
Hall must not show workspace journal, summary, input, report controls, or training controls before workspace selection.
Direct ?workspace={id} route must still open workspace for existing smoke coverage.
```

## Director Integration

Changed files:

```text
public/v2.php
public/assets/v2/app.js
public/assets/v2/app.css
app/v2/Repository.php
```

Behavior accepted:

```text
Default authenticated route opens Hall.
Hall is a workspace router, not a dashboard.
Workspace opening uses the shared openWorkspace path.
Workspace selector changes also use the shared openWorkspace path.
Create workspace from Hall uses the existing create form.
Create back returns to Hall when workspaces exist.
Direct ?workspace= route still opens a workspace.
```

## Verification Evidence

Static checks:

```text
node --check public/assets/v2/app.js
php -l public/v2.php
php -l app/v2/Repository.php
git diff --check
npm run smoke:v2
bash scripts/v2_http_api_smoke.sh
node scripts/v2_report_fragment_browser_smoke.cjs
```

Manual browser smoke:

```text
test-results/v2-hall-shell-smoke/desktop-01-hall.png
test-results/v2-hall-shell-smoke/desktop-02-workspace.png
test-results/v2-hall-shell-smoke/desktop-03-return-hall.png
test-results/v2-hall-shell-smoke/desktop-04-create-route.png
test-results/v2-hall-shell-smoke/mobile-01-hall.png
test-results/v2-hall-shell-smoke/mobile-02-workspace.png
test-results/v2-hall-shell-smoke/mobile-03-return-hall.png
test-results/v2-hall-shell-smoke/mobile-04-create-route.png
```

Existing report browser smoke evidence:

```text
test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786380832138
```

## Director Acceptance

Status:

```text
Accepted as first implemented Hall layer.
```

Not accepted in this sprint:

```text
invite links
employee role
scoped employee reads
accountable offers
employee simple journal
admin acceptance
settlement formulas
```

Next sprint:

```text
SPRINT-50R — Scoped Visibility and Employee Role Security Gate
```
