# SPRINT-51R — Employee Referral Invite and Workspace Join Gate

## Director Sprint Opening

Sprint:

```text
SPRINT-51R — Employee Referral Invite and Workspace Join Gate
```

Goal:

```text
Implement the first safe referral link flow: an owner/admin can invite a person into a workspace as employee, and an authenticated user can accept that invitation without gaining full workspace visibility.
```

Required files read:

```text
FinDesk v2.0/33-director-agent-orchestration-protocol.md
FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md
FinDesk v2.0/sprints/SPRINT-50R-scoped-visibility-employee-role-security-gate.md
app/v2/Api.php
app/v2/Repository.php
public/v2.php
public/assets/v2/app.js
public/assets/v2/app.css
scripts/v2_http_api_smoke.php
scripts/v2_http_api_smoke.sh
```

Agents assigned:

```text
Security/Roles Agent — Chandrasekhar
Backend Invite Agent — Curie
QA Acceptance Agent — Halley
Director — integration and acceptance
```

Exit criteria:

```text
1. Invitation tokens are stored only as hashes.
2. Owner/admin can create a single-use employee invite.
3. Viewer/employee cannot create invites.
4. Accept requires an authenticated user.
5. If invited_email is set, authenticated email must match.
6. Accepted membership is role employee, access_scope own_entries.
7. Duplicate membership is blocked safely.
8. Expired/revoked/accepted invite cannot be reused.
9. Invitation acceptance is audited.
10. Hall shows enough UI to create and accept an invite locally.
11. No accountable offers, employee report submission, email delivery, or settlement logic is implemented.
```

Guardrail:

```text
Do not implement accountable offers.
Do not create employee ledger write workflow.
Do not send emails.
Do not loosen SPRINT-50 employee scoped visibility.
```

## Agent Reports

Security/Roles Agent — Chandrasekhar:

```text
Status: completed, read-only.
Direction: raw token must be returned only once and never stored; accept must be authenticated, transactional, single-use, email-bound, and must create only employee/own_entries membership.
Risks called out: token in URL is only a browser bootstrap and should be posted to API in JSON body; expired status must be schema-supported; existing member must not be upgraded/downgraded by invite accept.
```

Backend Invite Agent — Curie:

```text
Status: completed.
Changed: app/v2/Repository.php, app/v2/Api.php, FinDesk v2.0/sql/001-clean-core-mariadb.sql, FinDesk v2.0/sql/clean-core-schema.sql.
Result: invite create/list/revoke/preview/accept backend exists; role/access are constrained to employee/own_entries; accept requires authenticated matching email; accepted/revoked/expired invites are blocked; create/accept/revoke are audited.
```

QA Acceptance Agent — Halley:

```text
Status: completed, read-only.
Acceptance required: owner/admin create invite; viewer/employee cannot create; unauthenticated accept is 401; wrong email is blocked; valid accept creates employee/own_entries membership; repeat/revoked/expired tokens fail; accepted employee still cannot read full workspace APIs.
```

## Director Integration

Files changed for SPRINT-51R:

```text
app/v2/Repository.php
app/v2/Api.php
FinDesk v2.0/sql/001-clean-core-mariadb.sql
FinDesk v2.0/sql/clean-core-schema.sql
public/v2.php
public/assets/v2/app.js
public/assets/v2/app.css
scripts/v2_http_api_smoke.sh
scripts/v2_http_api_smoke.php
FinDesk v2.0/sprints/SPRINT-51R-employee-referral-invite-join-gate.md
```

Implemented:

```text
1. v2_workspace_invites schema with hashed tokens, hint, email, employee role, own_entries scope, pending/accepted/revoked/expired status, expiry, accept/revoke metadata.
2. Owner/admin can create employee invite with required invited email.
3. Raw token/url are returned only in create response.
4. Invite list never returns token/url.
5. Invite preview/accept use POST body token.
6. Accept requires authenticated matching email.
7. Accept creates employee membership with access_scope own_entries.
8. Existing member, accepted token, revoked token, and expired token are blocked.
9. Hall has minimal invite UI: admin create button, one-time visible link, invite acceptance card for ?invite= token.
10. Employee scoped visibility from SPRINT-50 remains enforced after invite accept.
```

Not implemented by design:

```text
email sending
accountable offers
employee simple journal
employee report submission
admin acceptance of employee reports
settlement / remaining / overrun logic
```

Checks:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l scripts/v2_http_api_smoke.php
bash -n scripts/v2_http_api_smoke.sh
node --check public/assets/v2/app.js
git diff --check
npm run smoke:v2
bash scripts/v2_http_api_smoke.sh
node scripts/v2_report_fragment_browser_smoke.cjs
```

Evidence:

```text
npm run smoke:v2: OK
bash scripts/v2_http_api_smoke.sh: OK, includes invite create/list/token-hash/wrong-email/accept/reuse/revoke/expire/employee-scope checks.
node scripts/v2_report_fragment_browser_smoke.cjs: OK, screenshots saved in test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786383333271
```

## Director Acceptance

```text
SPRINT-51R accepted locally.
Production/deploy acceptance is not claimed.
Next sprint can implement employee simple workspace mode or accountable offer creation on top of the invite + scoped visibility boundary.
```
