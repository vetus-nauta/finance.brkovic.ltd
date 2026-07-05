# SPRINT-10R — MVP Release Candidate Gate and Deployment Readiness

Status: Planned

## Director Sprint Opening

Sprint:
SPRINT-10R — MVP Release Candidate Gate and Deployment Readiness

Goal:
Prove that FinDesk v2.0 Clean Core MVP satisfies the Definition of Done from a clean setup and is ready for a real release-candidate decision. Fix only release-blocking gaps found during verification.

Required files to read:
- `FinDesk v2.0/START_HERE_DIRECTOR.md`
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/21-sprint-plan.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-09R-month-closure-api-operational-controls.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

Agents required:
- Data and Backend Core Agent
- Financial Logic Engine Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent
- Security and Privacy Agent, if available

Agent tasks:
- Data and Backend Core Agent: verify clean setup, schema, API contracts, auth/session integration, storage paths, role checks, and deployment-readiness gaps.
- Financial Logic Engine Agent: re-check MVP formulas and correction/month-close invariants against `14-calculation-contract.md` and `20-definition-of-done.md`.
- Frontend Performance and Interaction Agent: verify operational journal ergonomics across desktop, iPad, and phone; no body scroll regressions; input remains reachable.
- QA, Audit, and Acceptance Agent: run full acceptance from clean state, map every DoD bullet to evidence, and reject any unverified claim.
- Security and Privacy Agent: inspect secrets handling, attachment storage exposure, auth boundaries, and deploy configuration risks.

Exit criteria:
- Full v2 smoke suite passes from clean setup.
- DoD checklist is mapped to concrete files/tests/screenshots.
- No old FinDesk business logic is used as product truth.
- No financial formula changes are made without a separate explicit decision.
- Operational journal remains the first working surface.
- Release-blocking gaps are either fixed or the sprint is rejected.
- Final MVP readiness report is written with remaining non-MVP backlog clearly separated.

Allowed fixes:
- Test reliability and acceptance evidence.
- Deploy/readme/handoff accuracy.
- Auth, role, storage, or route hardening required for MVP safety.
- Small UI regressions discovered by browser acceptance.

Forbidden:
- Dashboard-first UI.
- New financial formulas.
- Report snapshots, PDF export, forecasting, bank reconciliation, or analytics expansion.
- Importing old FinDesk product logic.
- Treating Google Drive Sprint 09-18 as proof of implementation.

Expected tests/checks:
- `npm run smoke:v2`
- `npm run test:v2:fixtures`
- `npm run smoke:v2:http`
- `bash scripts/v2_disposable_db_smoke.sh`
- `npm run smoke:v2:ui`
- `npm run smoke:v2:browser`
- Any deploy/auth/storage checks required by the agents.

Director notes:
- SPRINT-09R left MVP around 90-95% complete by functional surface, but MVP is not done until SPRINT-10R maps the Definition of Done to evidence and accepts a release candidate.
- Keep the sprint as a gate. If a large missing feature is discovered, reject the sprint and open a focused recovery sprint instead of silently expanding scope.
