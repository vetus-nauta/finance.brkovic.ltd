# SPRINT-48R — Hall, Roles, and Accountable Workflow Opening

## Director Sprint Opening

Sprint:

```text
SPRINT-48R — Hall, Roles, and Accountable Workflow Opening
```

Goal:

```text
Design and begin the FinDesk v2.0 post-auth Hall, workspace roles, referral invitation, employee limited workspace mode, and accountable-money report workflow.
```

Required files read:

```text
FinDesk v2.0/START_HERE_DIRECTOR.md
FinDesk v2.0/20-definition-of-done.md
FinDesk v2.0/31-operational-input-window-contract.md
FinDesk v2.0/33-director-agent-orchestration-protocol.md
FinDesk v2.0/14-calculation-contract.md
FinDesk v2.0/16-api-contract.md
FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md
public/v2.php
public/assets/v2/app.js
app/v2/Repository.php
app/v2/Api.php
app/auth.php
```

Retired context:

```text
Old v1 files such as app/advances.php, app/ai.php, public/assets/i18n.js, and scripts/local-smoke.php were used only as historical discovery material before v2 canonicalization.
They are no longer product truth and must not be read as active implementation sources.
```

Agents assigned:

```text
Product / Hall UX Agent — Banach
Accountable Workflow Domain Agent — Archimedes
Security / Roles / Access Agent — Aristotle
Financial Logic Engine Agent — Feynman
QA / Acceptance Agent — Avicenna
Director — integration and acceptance
```

Agent tasks:

```text
Agent: Product / Hall UX Agent
Scope: Post-auth Hall and role-based entry UX.
Files to read: public/v2.php, public/assets/v2/app.js, FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md.
What to check: current auth landing, screen router, workspace selector, employee limited mode needs.
What to change if allowed: no code changes in opening report.
What not to touch: financial formulas, parser, deploy.
Report required: current state, desired UX flow, screens/components, MVP cut line, risks.

Agent: Accountable Workflow Domain Agent
Scope: FinDesk v2 accountable money and employee report model.
Files to read: FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md, FinDesk v2.0/14-calculation-contract.md, app/auth.php, app/v2/Repository.php, app/v2/Api.php.
What to check: issued money, accepted employee reports, operational ledger inclusion, retired v1 assumptions that must not leak into v2.
What to change if allowed: no code changes in opening report.
What not to touch: v2 formulas, parser, deploy.
Report required: v2 gaps, recommended v2 domain model, legacy risks.

Agent: Security / Roles / Access Agent
Scope: Invitation, membership, role isolation, limited employee visibility.
Files to read: app/auth.php, app/v2/Repository.php, app/v2/Api.php, public/v2-api.php, FinDesk v2.0/sql/001-clean-core-mariadb.sql.
What to check: current auth/session/CSRF, workspace membership, writer/admin guards.
What to change if allowed: no code changes in opening report.
What not to touch: secrets, deploy, production data.
Report required: tables/endpoints needed, permission rules, threat model, MVP acceptance.

Agent: Financial Logic Engine Agent
Scope: Accountable offer, employee expenses, admin acceptance, remaining/overrun settlement.
Files to read: FinDesk v2.0/14-calculation-contract.md, FinDesk v2.0/20-definition-of-done.md, FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md, app/v2/Repository.php.
What to check: what is a cash movement, what is an expense, lower-accounting relation.
What to change if allowed: no code changes in opening report.
What not to touch: existing parser/report/deploy behavior.
Report required: ledger events, statuses, formulas, edge cases, MVP tests.

Agent: QA / Acceptance Agent
Scope: Manual and automated acceptance for Hall/accountable workflow.
Files to read: FinDesk v2.0/20-definition-of-done.md, public/v2.php, public/assets/v2/app.js, scripts/*smoke*.
What to check: browser scenarios and negative permission tests.
What to change if allowed: no code changes in opening report.
What not to touch: app logic.
Report required: acceptance scenarios, screenshots, smoke tests, blockers.
```

Expected reports:

```text
Five agent reports must be received before SPRINT-48R can be accepted.
```

Exit criteria:

```text
1. Product contract is written.
2. Hall/accountable workflow is split into implementation sprints.
3. Agent reports are received and reconciled.
4. Backend/API/UI/QA work scopes are defined.
5. No financial formula, parser, report, or deploy behavior is changed during opening.
```

Risks:

```text
1. Employee-limited role may accidentally expose full workspace totals.
2. Accountable cash may be mistaken for a normal expense.
3. Old FinDesk advances logic may not fit clean-core v2 data model.
4. Invitation tokens need security and expiry before production.
5. Hall must not turn the operational journal into a dashboard-first product.
```

## Proposed Sprint Sequence

```text
SPRINT-48R — Opening / architecture / reports from agents
SPRINT-49R — Hall shell and workspace role router
SPRINT-50R — Invitation/referral membership backend and UI
SPRINT-51R — Accountable offers and employee simple mode
SPRINT-52R — Employee submit report and admin acceptance
SPRINT-53R — Settlement balances, remaining/overrun, report inclusion
SPRINT-54R — Full QA, security pass, responsive acceptance
```

## Initial Product Decision

FinDesk v2.0 should not always open directly into the operational journal after login.

After authentication:

```text
If the user has exactly one workspace and a remembered workspace preference, fast-open may enter the last used work area.
Otherwise open Hall.
```

The Hall remains the source for:

```text
role switching
workspace switching
invitations
employee report tasks
open accountable balances
creating a personal workspace
```

## Agent Reports Received

```text
Product / Hall UX Agent — Banach — received
Legacy Advances / Employee Reports Agent — Archimedes — received
Security / Roles / Access Agent — Aristotle — received
Financial Logic Engine Agent — Feynman — received
QA / Acceptance Agent — Avicenna — received
```

## Reconciled Findings

### Product / UX

`17-screen-registry.md` already defines Hall as the choose/create workspace screen.

Current v2 bypasses Hall after auth and opens the preferred workspace directly. This must change.

Hall must remain a router, not a dashboard:

```text
workspace tiles
role context
invitation tasks
employee accountable tasks
create/open personal workspace
```

### Legacy

Old FinDesk has a mature accountable-money workflow:

```text
issue money without ledger expense
employee simple report
submit actual cash left
admin accept into group ledger
return/rework/unaccept/cancel
```

But v2 must not port old text-marker logic or old table assumptions blindly. v2 needs structured workflow state and source ids.

### Security

Current v2 has workspace membership and writer/admin guards, but all members can still read broad workspace data. Therefore:

```text
employee invitations are blocked until employee-scoped read paths exist
```

Required security model:

```text
token hash only
email-bound acceptance
expires/revokes/single-use
owner/admin member management
no final-owner removal
audit all changes
stronger CSRF for member/invite endpoints
```

### Financial Logic

Accountable offer creation is not financial movement.

Employee confirmation of received cash creates a lower-accounting physical cash movement.

Admin acceptance of submitted employee rows creates category expenses, but must not reduce cash twice when the advance already reduced cash.

Core formulas:

```text
expected_remaining = issued + extra_cash_in - accepted_cash_expenses
difference = actual_remaining - expected_remaining
cash_to_return_or_rollover = max(actual_remaining, 0)
cash_overrun = max(-expected_remaining, 0)
total_accepted_expense = accepted_cash_expenses + accepted_noncash_expenses
```

### QA

Full acceptance is blocked because v2 currently lacks:

```text
Hall
invite/referral UI and API
employee limited view
accountable offer
employee submit report
admin accept report
remaining/overrun settlement actions
browser smoke for this workflow
```

## Director Decision

SPRINT-48R is accepted as an architecture/opening sprint only.

Implementation must continue in the following order:

```text
1. Hall shell and workspace role router
2. scoped member visibility and employee read restrictions
3. invitation/referral backend
4. invitation/referral UI
5. accountable offer backend
6. employee simple operational mode
7. employee submit report
8. admin acceptance and settlement
9. full QA/security/responsive gate
```

## Director Final Handoff

Sprint:

```text
SPRINT-48R — Hall, Roles, and Accountable Workflow Opening
```

Status:

```text
Accepted as architecture/opening sprint.
Implementation not yet accepted.
```

Agents assigned:

```text
Banach, Archimedes, Aristotle, Feynman, Avicenna
```

Agent reports received:

```text
5 / 5
```

Accepted work:

```text
Product contract created.
Sprint opening created.
Agent findings reconciled.
Implementation sequence defined.
P0 security blocker identified: employee-scoped reads.
```

Rejected work:

```text
No implementation accepted yet.
No legacy code port accepted.
No employee role may be enabled before scoped visibility exists.
```

Files changed:

```text
FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md
FinDesk v2.0/sprints/SPRINT-48R-hall-roles-accountable-workflow-opening.md
```

Tests or checks:

```text
Documentation sprint only. No runtime checks required.
```

Risks:

```text
Employee data leakage if broad workspace reads remain.
Double cash reduction if accepted employee expenses are posted incorrectly.
Invitation token misuse if email-bound acceptance is not enforced.
Hall scope creep into dashboard.
```

Next sprint:

```text
SPRINT-49R — Hall Shell and Workspace Role Router
```

Paste-to-next-director prompt:

```text
You are the next Director for FinDesk v2.0. Source of truth is repository files only.
Read:
FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md
FinDesk v2.0/sprints/SPRINT-48R-hall-roles-accountable-workflow-opening.md
FinDesk v2.0/17-screen-registry.md
FinDesk v2.0/33-director-agent-orchestration-protocol.md
public/v2.php
public/assets/v2/app.js
app/v2/Repository.php
app/v2/Api.php

Open SPRINT-49R. Goal: implement Hall shell and workspace role router without enabling employee invitations yet. Hall is a router, not dashboard. Operational Journal remains first working screen inside a selected workspace.
```
