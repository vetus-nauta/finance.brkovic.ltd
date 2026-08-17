# SPRINT-32R — Dictionary Training Review UI

## Director Sprint Opening

Sprint:
SPRINT-32R — Dictionary Training Review UI

Date:
2026-07-08

Goal:
Add a human decision console for dictionary training review rows, so linguistic decisions can be made from raw-history evidence without changing operational accounting or report behavior.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/sprints/SPRINT-31R-dictionary-training-review-workflow.md`
- `FinDesk v2.0/16-api-contract.md`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_operational_browser_smoke.cjs`
- `scripts/v2_operational_ui_smoke.sh`

Agents assigned:

- UX/Product Agent
- Frontend Integration Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- UX/Product Agent: decide whether training belongs inside Summary or as a separate mode, and define minimal safe console behavior.
- Frontend Integration Agent: identify exact UI/API insertion points and regression risks.
- QA, Audit, and Acceptance Agent: define browser, responsive, security, and finance-isolation checks.

## Agent Reports

### UX/Product Agent

Recommendation:
Create a separate top-level `Training` mode beside `Operational` and `Summary`.

Reasoning:

- Summary is report-like and should remain read-only in user perception.
- Training writes audited decisions, so its mental model must be separate.
- The UI should reuse FinDesk operational density and panels, not become a dashboard.
- Blockers must remain visible near the submit action.
- No batch approve in MVP.

### Frontend Integration Agent

Recommendation:
Smallest implementation path is inside the existing Summary Information dictionary block.

Risks found:

- Adding another Summary tab would break four-tab assumptions and mobile CSS.
- Wider dictionary tables can reintroduce page-level overhang.
- Double-clicks can duplicate POSTs unless row actions are disabled while pending.
- `GET /dictionary-review-queue` must remain read-only.

Director decision:
Accepted the product recommendation and created a separate top-level `Training` mode, while preserving Summary's four tabs.

### QA, Audit, and Acceptance Agent

Required acceptance:

- Stable automation hooks for queue rows, decision panel, action buttons, category, pattern, and note.
- Browser test must POST only to `/dictionary-training-decisions`.
- `approve_existing_guess_local` creates a local rule only when no blockers are present.
- Rejected/deferred/blocked/universal-candidate decisions must not create rules.
- Blocked rows must disable local approval in the UI.
- Training screen must not introduce body/page scroll or viewport overhang.
- Operational entry selection, draft input, active month, reports, and balances remain isolated.

## Implemented

UI:

- Added top-level `Training` mode.
- Added `Training queue` panel with raw-history dictionary review rows.
- Added `Decision` panel with evidence, current guess, confidence, review reason, blockers, markers, and matched signals.
- Added decision actions:
  - `Approve local`
  - `Correct local`
  - `Reject`
  - `Defer`
  - `Block`
  - `Universal candidate`
- Disabled `Approve local` when no current guess exists.
- Disabled local approval controls when blockers or blocked review reasons are present.
- Kept universal candidate audit-only; no UI path sends `promote_universal`.

API integration:

- `GET /api/workspaces/:workspaceId/dictionary-review-queue?limit=120&examples=4`
- `GET /api/workspaces/:workspaceId/dictionary-training-decisions?limit=120`
- `POST /api/workspaces/:workspaceId/dictionary-training-decisions`

Responsive:

- Desktop uses two panels.
- Tablet keeps two dense panels with constrained inner scrolls.
- Phone stacks queue and decision panels vertically.
- Phone landscape uses two panels with internal scrolls.
- Body/page scroll remains forbidden.

Browser smoke:

- Seeds raw-history review rows through `/imports/excel`.
- Opens `Training`.
- Refreshes queue after import.
- Selects `агент`.
- Saves `approve_existing_guess_local`.
- Verifies saved decision readback.
- Selects `мой кредит`.
- Verifies `blocked_by_debt` and disabled local approval.
- Saves screenshot `desktop-dictionary-training-decision.png`.

## Files Changed In This Sprint

- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_operational_browser_smoke.cjs`
- `FinDesk v2.0/sprints/SPRINT-32R-dictionary-training-review-ui.md`

Note:
The working tree contains prior sprint changes in other files; this sprint did not revert them.

## Verification

Commands:

```text
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
php -l public/v2.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Results:

```text
No syntax errors detected in public/v2.php
FinDesk v2 clean core static smoke: OK
FinDesk v2 fixture runner: PASS 21
FinDesk v2 HTTP API smoke: OK
FinDesk v2 operational UI smoke: OK
FinDesk v2 browser UI smoke: OK
```

Browser evidence:

```text
test-results/v2-browser-smoke/desktop-dictionary-training-decision.png
test-results/v2-browser-smoke/layout-metrics.json
```

Key browser metric:

```text
Training screen 1280x820:
bodyOverflow hidden
htmlOverflow hidden
bodyScrollWidth 1280
htmlScrollWidth 1280
queue overflowY auto
detail overflowY auto
```

## Director Final Handoff

Status:
ACCEPT.

Accepted work:

- Training decisions now have a visible decision console.
- Summary remains a reporting surface, not a mutation surface.
- Local approvals are guarded by blockers and current-guess availability.
- Browser smoke covers a real imported raw-history row and a blocked debt row.
- Finance/parser/report behavior remains isolated.

Rejected/deferred work:

- No batch approve.
- No universal promotion.
- No product-wide dictionary mutation from this UI.
- No separate mobile-only training architecture beyond responsive stacking in this sprint.

Next recommended sprint:
SPRINT-33R — Dictionary Training Review Triage Filters and Decision Readback.

Scope:

- Add filters: `All`, `Weak`, `Mixed`, `Blocked`, `No category`, `Deferred`, `Decided`.
- Add search by raw text / category / source file.
- Add decision-state badges in queue rows.
- Add readback sorting so unresolved/high-risk rows stay first.
