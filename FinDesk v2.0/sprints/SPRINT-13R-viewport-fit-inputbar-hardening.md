# SPRINT-13R — Viewport Fit and Mobile Inputbar Hardening

Status: Accepted

## Director Sprint Opening

Sprint:
SPRINT-13R — Viewport Fit and Mobile Inputbar Hardening

Goal:
Fix the visible-browser issue where FinDesk v2 did not remain usefully fixed inside the browser window on short/mobile viewports because the mobile inputbar consumed too much vertical space and collapsed the workspace.

Required files read:
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `public/assets/v2/app.css`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:
- Frontend Performance and Interaction Agent: Ohm
- QA, Audit, and Acceptance Agent: Mencius

Agent tasks:
- Frontend Performance and Interaction Agent: verify the CSS fix keeps mobile/short viewport input reachable without collapsing the workspace.
- QA, Audit, and Acceptance Agent: verify browser-smoke evidence protects the reported viewport-fit bug.

Exit criteria:
- Body/page scroll remains disabled.
- Mobile inputbar remains compact in reduced viewport.
- Workspace keeps usable visible height in reduced viewport.
- Input and Save stay inside the browser window.
- Desktop, iPad mini, iPad 11, phone horizontal, and phone feed tests remain green.

## Findings

Visible-browser metrics before fix:
- At `390x520`, `.v2-inputbar` height was about `236px`.
- Workspace height collapsed to about `4px`.
- At `390x430`, workspace height collapsed to `0px`.

Root cause:
- The mobile media query grouped `.v2-inputbar` with forms that should become one-column.
- This made Date, Record, Check, and Save stack vertically on phone/short windows.

Fix:
- Keep `.v2-inputbar` as a compact four-column grid in the mobile media query.
- Use constrained mobile columns: date, record, Check, Save.
- Compact the mobile topbar, summary strip, Cash/Card rail, and Write/Details/Check tabs so the journal remains the dominant visible surface.
- Hide inputbar labels on mobile and reduce input/button height to keep the bottom entry form compact.
- Add browser-smoke assertions for reduced viewport:
  - shell stays within viewport
  - no document/body horizontal overhang
  - Save button passes Playwright trial click
  - `inputHeight <= 100`
  - `workspaceHeight >= 120`
  - input and submit button remain within `window.innerHeight`

## Agent Reports

Frontend Performance and Interaction Agent:
Pending at initial write.

QA, Audit, and Acceptance Agent:
Initial REJECT until explicit shell bounds, horizontal overhang, submit click reachability, and reduced-viewport screenshot evidence were added. Final ACCEPT after those checks passed in browser smoke.

## Director Final Handoff

Sprint:
SPRINT-13R — Viewport Fit and Mobile Inputbar Hardening

Status:
Accepted

Agents assigned:
- Frontend Performance and Interaction Agent: Ohm
- QA, Audit, and Acceptance Agent: Mencius

Agent reports received:
- Frontend Performance and Interaction Agent: ACCEPT.
- QA, Audit, and Acceptance Agent: initial REJECT for missing reachability/shell evidence; final ACCEPT after smoke hardening.

Accepted work:
- Mobile inputbar no longer switches to one-column layout.
- Reduced viewport browser smoke now guards against inputbar consuming the viewport and workspace collapse.
- Manual visible-browser metrics after fix:
  - first fit pass at `390x520`: inputbar about `75px`, workspace about `166px`.
  - compact readability pass at `390x520`: inputbar about `50px`, workspace about `307px`.
  - compact readability pass at `360x480`: workspace about `249px`.
- Automated browser-smoke metrics at `390x520`:
  - `shellTop=0`
  - `shellBottom=520`
  - `bodyScrollWidth=390`
  - `htmlScrollWidth=390`
  - `inputHeight=50`
  - `workspaceHeight=315`
  - `submitBottom=501`

Rejected work:
- No dashboard/layout redesign.
- No finance/parser/API behavior changes.

Files changed:
- `FinDesk v2.0/sprints/SPRINT-13R-viewport-fit-inputbar-hardening.md`
- `public/assets/v2/app.css`
- `scripts/v2_operational_browser_smoke.cjs`

Tests or checks:
- `node --check scripts/v2_operational_browser_smoke.cjs`
- `git diff --check`
- `npm run smoke:v2:ui`
- `npm run smoke:v2:browser`
- Reduced viewport screenshot:
  `test-results/v2-browser-smoke/mobile-reduced-viewport-fit.png`
- Manual compact screenshot:
  `test-results/v2-browser-smoke/mobile-compact-readable-390x520.png`

Risks:
- Extremely short browser windows can still leave limited workspace height, but the workspace no longer collapses to zero and the page remains fixed within the viewport.

What must not be touched:
- Do not change financial formulas.
- Do not replace operational journal with dashboard-first UI.
- Do not broaden this sprint into design redesign.

Next sprint:
Return to SPRINT-11R live production deployment evidence unless another visible-browser MVP blocker appears.

Paste-to-next-director prompt:
You are the next Director of FinDesk v2.0. Source of truth is only GitHub files. SPRINT-13R fixed the visible-browser viewport-fit bug by keeping the mobile inputbar compact and adding browser-smoke guards. Continue with SPRINT-11R live production deployment evidence unless a new browser-visible MVP blocker appears.
