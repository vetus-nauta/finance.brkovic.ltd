# SPRINT-03R — Browser-Level Operational UI Acceptance

Director: Codex Director, FinDesk v2.0

Status: Accepted

## Director Sprint Opening

Sprint:

```text
SPRINT-03R — Browser-Level Operational UI Acceptance
```

Goal:

- Prove the SPRINT-02R operational input window works in a real browser against an authenticated disposable/local v2 DB.
- Do not add product scope.
- Do not start dashboard, reports, imports, analytics, bank reconciliation, or final parser claims.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/31-operational-input-window-contract.md`
- `FinDesk v2.0/32-director-addendum-operational-window.md`
- `FinDesk v2.0/sprints/SPRINT-02R-operational-input-window.md`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `public/v2-api.php`
- `app/auth.php`
- `app/v2/Api.php`
- `scripts/v2_http_api_smoke.sh`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_operational_ui_smoke.sh`

Agents assigned:

- Frontend Browser Acceptance Agent
- Backend/Auth Disposable Harness Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- Frontend Browser Acceptance: define browser smoke scope and tooling.
- Backend/Auth Disposable Harness: verify safe authenticated local test DB/server path.
- QA/Audit: define exit criteria and blockers.

Expected reports:

- ACCEPT/BLOCK for implementing browser-level smoke.
- Required browser assertions.
- Safe auth/disposable DB approach.
- Risks and sprint blockers.

Exit criteria:

- Browser smoke opens `public/v2.php`.
- Authenticated browser flow creates/selects workspace against disposable/local DB only.
- Browser saves `+1000 снял с карты`.
- Browser saves `-250 рыба`.
- Feed and structured check refresh after saves.
- Structured check includes `date`, `raw_text`, `flow`, `sign`, `amount`, `direction`, `entry_type`, `category`, `actor`, `status`, `balance_after`.
- Desktop evidence proves operational window layout and no page/body scroll takeover.
- Mobile evidence proves horizontal write/check movement and internal vertical record scroll.
- Double-submit protection is proven in browser.
- Offline draft preservation is proven in browser.
- Existing v2 gates continue to pass.

Risks:

- Accidentally testing only static DOM instead of real UI saves.
- Touching production DB.
- Depending on old FinDesk UI assets or legacy `/api.php`.
- Expanding into reports/dashboard/imports.
- Treating fixture parser behavior as final parser intelligence.
- Local MariaDB bootstrap can fail transiently before app code runs.

## Agent Reports

Frontend Browser Acceptance Agent: Russell

```text
ACCEPT
Use Playwright + PHP built-in server + disposable/local test DB.
Do not use jsdom as the primary acceptance tool.
Browser smoke must prove workspace creation, record saves, feed refresh, structured check, mobile horizontal movement, double-submit protection, and offline draft behavior.
```

Backend/Auth Disposable Harness Agent: McClintock

```text
ACCEPT
Extend the existing v2 HTTP smoke harness.
Seed a harness-only user/session directly in disposable MariaDB.
Set the same cookie in Playwright context.
Copy v2.php, v2-api.php, v2 assets, app/auth.php, and app/v2 into a temporary harness.
Serve only the temporary harness on 127.0.0.1.
```

QA, Audit, and Acceptance Agent: Gibbs

```text
ACCEPT
Sprint is acceptance-only.
Browser test must actually save records through UI.
Block dashboard/report/import/analytics/bank-reconciliation/final-parser scope.
Block production DB access.
Require DOM/screenshot/metric evidence for scroll and mobile horizontal movement.
```

## Implementation Report

Branch:

```text
findesk-v2-sprint-03r-browser-ui-acceptance
```

Files added:

- `scripts/v2_operational_browser_smoke.sh`
- `scripts/v2_operational_browser_smoke.cjs`

Files updated:

- `package.json`
- `package-lock.json`

New command:

```text
npm run smoke:v2:browser
```

Browser smoke implementation:

- Uses `playwright-core`.
- Uses system Chrome/Chromium through `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` or local browser discovery.
- Creates temporary MariaDB datadir under `/tmp`.
- Starts MariaDB with `--skip-networking`.
- Imports `deploy/auth_foundation.sql`.
- Imports `FinDesk v2.0/sql/001-clean-core-mariadb.sql`.
- Seeds harness-only user and session.
- Generates harness-local `app/db.php` with temp Unix socket DB config.
- Copies only v2 API/UI files into a temporary harness.
- Serves temporary harness via PHP built-in server on `127.0.0.1`.
- Runs browser assertions against `/v2.php`.
- Writes screenshots to ignored `test-results/v2-browser-smoke/`.

Browser assertions:

- Unauthenticated state returns `Not authenticated`.
- Authenticated user with no workspace sees create workspace form.
- Browser creates workspace through UI.
- Browser saves `+1000 снял с карты` through UI.
- Browser saves `-250 рыба` through UI.
- Feed contains saved records.
- Structured check contains saved records and required headers:
  - `date`
  - `raw_text`
  - `flow`
  - `sign`
  - `amount`
  - `direction`
  - `entry_type`
  - `category`
  - `actor`
  - `status`
  - `balance_after`
- Refresh preserves feed and structured check.
- Double-submit sends one entry POST and renders one row.
- Desktop metrics prove:
  - body/html overflow hidden
  - shell fits viewport
  - feed owns vertical scroll
  - write/check area is desktop grid
- Offline draft preservation works through localStorage and reload.
- Mobile metrics prove:
  - horizontal check view moves
  - check panel becomes visible
  - body overflow remains hidden
  - feed owns vertical scroll
  - horizontal area owns horizontal movement

## Verification

Commands run:

```text
bash -n scripts/v2_operational_browser_smoke.sh
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2:browser
npm run smoke:v2:ui
npm run smoke:v2
npm run smoke:v2:http
npm run test:v2:fixtures
npm run smoke:v2:db
```

Results:

```text
bash -n scripts/v2_operational_browser_smoke.sh: OK
node --check scripts/v2_operational_browser_smoke.cjs: OK
npm run smoke:v2:browser: OK
npm run smoke:v2:ui: OK
npm run smoke:v2: OK
npm run smoke:v2:http: OK
npm run test:v2:fixtures: PASS (12), BLOCKED / NOT_IMPLEMENTED (0)
npm run smoke:v2:db: OK
```

Browser smoke output:

```text
Unauthenticated state: OK
Workspace create/select: OK
Save records + structured check: OK
Refresh preserves feed/check: OK
Double-submit protection: OK
Offline draft preservation: OK
FinDesk v2 browser UI smoke: OK
```

Evidence:

- Desktop scroll metrics and mobile horizontal metrics are printed by the smoke.
- Screenshots are generated under `test-results/v2-browser-smoke/`.
- `test-results/` remains ignored and is not committed.

## Final QA Acceptance

Final QA acceptance agent: Maxwell

Verdict:

```text
ACCEPT
```

QA accepted evidence:

- Scope stayed acceptance-only.
- Browser smoke uses disposable local DB only.
- Temporary harness serves copied v2 files on localhost.
- Browser smoke launches real Chromium through Playwright.
- Browser smoke creates workspace and saves records through UI.
- Browser smoke verifies feed/check/headers/amounts.
- Browser smoke proves double-submit guard, offline draft preservation, desktop scroll metrics, and mobile horizontal movement.

## Residual Risks

- Test depends on local MariaDB tools.
- Test depends on system Chrome/Chromium unless `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` is supplied.
- Mobile coverage is Chromium emulation, not real device/keyboard QA.
- Double-submit proof is client/browser-level, not server idempotency.
- Screenshots are generated but not visually diffed.
- Parser behavior remains fixture-scoped, not final parser intelligence.

## Final Handoff For Next Director

SPRINT-03R is accepted as browser-level operational UI acceptance.

Do not interpret this as readiness for reports/dashboard/imports. The accepted product surface remains:

```text
write record -> verify how system read it -> see current operational figures
```

Next recommended implementation sprint:

```text
SPRINT-04R — Operational Entry Review And Correction Workflow
```

Recommended SPRINT-04R goals:

- Add targeted entry open/details affordance.
- Add category correction workflow for saved records.
- Add Other review workflow around `other_review` entries.
- Add browser coverage for category correction and Other review.
- Add closed-month handling coverage without silently mutating closed periods.

Do not start dashboard, reports, imports, analytics, bank reconciliation, or final parser claims before operational review/correction is solid.
