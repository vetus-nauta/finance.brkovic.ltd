# SPRINT-10R — MVP Release Candidate Gate and Deployment Readiness

Status: Accepted

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

## Active Director Opening

Director:
Codex

Status:
Open

Agents assigned:
- Data and Backend Core Agent: Descartes
- Financial Logic Engine Agent: Arendt
- Frontend Performance and Interaction Agent: Chandrasekhar
- QA, Audit, and Acceptance Agent: Ramanujan
- Security and Privacy Agent: Franklin, if available

Director constraints:
- Director does not write implementation code.
- Agents audit first; fixes require concrete release blockers.
- Source of truth is GitHub files only.
- Old FinDesk remains infrastructure donor only.
- Operational journal remains the first working surface.
- No financial formula changes without separate explicit decision.

## Gate Findings and Fixes

Initial agent verdicts:
- Financial Logic Engine Agent: ACCEPT.
- Data and Backend Core Agent: REJECT due plaintext login-code logging.
- Frontend Performance and Interaction Agent: REJECT due missing iPad mini/iPad 11 and keyboard/input evidence.
- QA, Audit, and Acceptance Agent: REJECT due missing positive full-entry update/recalculation, unrecognized import row, tablet, vertical-scroll, and keyboard/input evidence.
- Security and Privacy Agent: REJECT due plaintext login-code logging and HTTPS cookie `Secure` risk.

Release-blocking fixes applied:
- `app/auth.php` now logs auth codes only in explicit local-dev mail log mode.
- `app/auth.php` no longer treats `REMOTE_ADDR=127.0.0.1` alone as local-dev, avoiding reverse-proxy false local.
- `app/auth.php` now forces secure cookies when configured `app_url` is HTTPS.
- Added `smoke:v2:auth` to prove production-like auth does not write plaintext auth-code logs and does set `Secure`, `HttpOnly`, and `SameSite=Lax`.
- Fixed `app/v2/Repository.php` `updateEntry()` so derived `amount` is recalculated from changed `raw_text` unless PATCH explicitly supplies `amount`.
- Extended fixture and HTTP smoke coverage for authorized full-entry update/delete balance recalculation.
- Extended import fixture and HTTP smoke coverage for unrecognized import rows and row traces.
- Extended browser smoke coverage for iPad mini portrait/landscape, iPad 11 portrait/landscape, phone vertical feed scroll, and reduced-height input/submit reachability.

Current green gate command:
```bash
npm run smoke:v2 &&
npm run smoke:v2:auth &&
npm run test:v2:fixtures &&
npm run smoke:v2:http &&
bash scripts/v2_disposable_db_smoke.sh &&
npm run smoke:v2:ui &&
npm run smoke:v2:browser
```

## Director Final Handoff

Sprint:
SPRINT-10R — MVP Release Candidate Gate and Deployment Readiness

Status:
Completed / Accepted

Agents assigned:
- Data and Backend Core Agent: Descartes
- Financial Logic Engine Agent: Arendt
- Frontend Performance and Interaction Agent: Chandrasekhar
- QA, Audit, and Acceptance Agent: Ramanujan
- Security and Privacy Agent: Franklin

Agent reports received:
- Data and Backend Core Agent: ACCEPT after auth logging and update recalculation fixes.
- Financial Logic Engine Agent: ACCEPT after `updateEntry` recalculation sanity review.
- Frontend Performance and Interaction Agent: ACCEPT after iPad mini/iPad 11 and keyboard/input evidence.
- QA, Audit, and Acceptance Agent: ACCEPT after full gate rerun and revised DoD evidence matrix.
- Security and Privacy Agent: ACCEPT after auth-code logging and secure-cookie fixes.

Accepted work:
- FinDesk v2.0 Clean Core MVP release-candidate gate passed from disposable clean test setup.
- Auth code logging is restricted to explicit local-dev mail log mode only.
- HTTPS `app_url` forces `Secure` session cookies.
- `updateEntry()` recalculates derived amount from changed `raw_text` unless PATCH explicitly supplies `amount`.
- Fixture and HTTP coverage now prove authorized full-entry update/delete balance recalculation.
- Legacy import coverage now proves unrecognized rows are reported with row trace and parse notes.
- Browser coverage now proves:
  - desktop full workspace grid;
  - phone horizontal structured check;
  - phone vertical feed scroll;
  - reduced-height input/submit reachability;
  - iPad mini portrait/landscape mobile financial-notes mode;
  - iPad 11 portrait/landscape full workspace grid.
- Operational journal remains the first working surface.
- Old FinDesk product logic remains rejected as source of truth.

Rejected work:
- Dashboard-first UI remains forbidden.
- Report snapshots, PDF export, forecasting, bank reconciliation, and analytics expansion remain out of MVP.
- No financial formula changes were accepted.
- No Google Drive Sprint 09-18 implementation claim was treated as proof without GitHub files/tests.

Files changed:
- `FinDesk v2.0/sprints/SPRINT-10R-mvp-release-candidate-gate.md`
- `app/auth.php`
- `app/v2/Repository.php`
- `package.json`
- `public/assets/v2/app.css`
- `scripts/v2_auth_security_smoke.php`
- `scripts/v2_auth_security_smoke.sh`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_browser_smoke.cjs`

Tests or checks:
- `php -l app/auth.php`
- `php -l scripts/v2_auth_security_smoke.php`
- `php -l scripts/v2_fixture_runner.php`
- `php -l scripts/v2_http_api_smoke.php`
- `node --check scripts/v2_operational_browser_smoke.cjs`
- `bash -n scripts/v2_auth_security_smoke.sh`
- `bash -n scripts/v2_http_api_smoke.sh`
- `bash -n scripts/v2_disposable_db_smoke.sh`
- `bash -n scripts/v2_operational_ui_smoke.sh`
- `npm run smoke:v2`
- `npm run smoke:v2:auth`
- `npm run test:v2:fixtures`
- `npm run smoke:v2:http`
- `bash scripts/v2_disposable_db_smoke.sh`
- `npm run smoke:v2:ui`
- `npm run smoke:v2:browser`
- Full final gate command:
  `npm run smoke:v2 && npm run smoke:v2:auth && npm run test:v2:fixtures && npm run smoke:v2:http && bash scripts/v2_disposable_db_smoke.sh && npm run smoke:v2:ui && npm run smoke:v2:browser`

Risks:
- Production deployment still needs real server smoke after deploy, especially Apache/private storage behavior for `/storage/v2/attachments/...`.
- Existing production `storage/logs/auth_codes.log`, if present from older runtime, must be purged during deploy cleanup.
- `updateEntry()` still allows explicit `amount` override through API when provided; UI does not send it. Consider tightening in a future API-hardening sprint if public API misuse becomes a concern.
- Closed-month full edit/delete decisions remain narrow: explicit decision execution currently covers category correction/recalculate/cancel, while broader old-entry edit decisions are future product scope.

What must not be touched next:
- Do not replace the operational journal with dashboard/report-first UX.
- Do not change financial formulas without a separate financial decision sprint.
- Do not reuse old FinDesk finance logic, categories, dashboards, or reports as product truth.
- Do not treat Google Drive Sprint 09-18 archive docs as implementation proof.

Next sprint:
SPRINT-11R — Production Deployment Smoke and Rollback Gate

Paste-to-next-director prompt:
You are the next Director of FinDesk v2.0. Source of truth is only GitHub files. Start with `FinDesk v2.0/START_HERE_DIRECTOR.md`, then read this SPRINT-10R handoff. SPRINT-10R accepted the Clean Core MVP as a release candidate from disposable clean setup, but not yet as a production deployment. Run the next sprint as a deployment smoke and rollback gate: verify production config without exposing secrets, apply clean v2 schema safely, purge any old plaintext `storage/logs/auth_codes.log`, verify `/storage/v2/attachments/...` is not publicly readable, run authenticated production-safe v2 smokes, capture rollback steps, and do not add product features.
