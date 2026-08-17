# SPRINT-33R — Dictionary Training Triage Filters

## Director Sprint Opening

Sprint:
SPRINT-33R — Dictionary Training Triage Filters

Date:
2026-07-08

Goal:
Improve the Training console from SPRINT-32R with read-only triage controls: filters, search, decision-state badges, and unresolved/high-risk-first ordering.

Source of truth:
GitHub files only.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-32R-dictionary-training-review-ui.md`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_operational_browser_smoke.cjs`

Agents assigned:

- UX/Product Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- UX/Product Agent: define product behavior for filters/search/readback without changing Training's console model.
- QA, Audit, and Acceptance Agent: define acceptance checks, responsive risks, and finance-isolation gates.

## Agent Reports

### UX/Product Agent

Status:
ACCEPT.

Required behavior:

- Filters: `All`, `Weak`, `Mixed`, `Blocked`, `No category`, `Deferred`, `Decided`.
- `Deferred` means the latest saved decision is `defer`.
- `Decided` means a saved non-deferred decision exists.
- Filters and search combine predictably.
- Search matches raw text, category/current guess, and source file/sheet/row label.
- Queue rows show visible decision-state badges.
- Default order keeps unresolved/high-risk rows first.
- Refresh preserves filter/search and selected row when still visible.
- Empty state distinguishes no data from no filter/search match.

### QA, Audit, and Acceptance Agent

Status:
ACCEPT.

Required gates:

- Stable hooks for filters, search, visible count, decision badge, and empty state.
- Browser smoke asserts filter labels and behavior.
- Browser smoke asserts search by raw text, category/current guess, and source file.
- Browser smoke asserts local approval still disables for blocked rows.
- Browser smoke asserts deferred and decided are separate states.
- Training remains top-level; Summary tabs remain `Information|Sending|Printing|Storage`.
- No finance/report/import mutation from filters/search.

## Implemented

Training queue:

- Added filter state:
  - `All`
  - `Weak`
  - `Mixed`
  - `Blocked`
  - `No category`
  - `Deferred`
  - `Decided`
- Added search input.
- Added visible count `visible / total`.
- Added filter/search empty state.
- Added decision badges:
  - `Open`
  - `Deferred`
  - `Blocked`
  - `Decided`
  - `Local rule`
  - `Universal candidate`
- Added unresolved/high-risk-first sorting:
  - blocked
  - no category
  - mixed
  - weak
  - open
  - deferred
  - decided/local rule/universal candidate

Safety:

- Filters/search are read-only UI state only.
- Decision POST behavior remains unchanged.
- Local approval remains disabled for blocker rows.
- `promote_universal` remains unavailable.
- No API, parser, report, import, or financial formula change.

Responsive:

- Controls are inside the Training queue panel.
- Controls collapse to one column on tablet/phone.
- Training queue/detail keep internal scroll.
- Body/page scroll remains forbidden.

## Browser Smoke Additions

Added to `scripts/v2_operational_browser_smoke.cjs`:

- Assert filter labels:
  `All|Weak|Mixed|Blocked|No category|Deferred|Decided`.
- Assert `Blocked` filter shows `мой кредит`.
- Assert `Mixed` filter shows `доставка фильтра`.
- Assert source-file search with `browser-dictionary-training`.
- Assert raw-text search with `ареда`.
- Assert no-match search shows `data-v2-training-empty`.
- Assert category/current-guess search with `current_boat_expenses`.
- Assert local approval creates `Local rule` badge.
- Assert `defer` creates a deferred row.
- Assert `Deferred` filter shows deferred row.
- Assert `Decided` filter excludes deferred row.
- Assert blocked row still disables local approval.

## Files Changed In This Sprint

- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_operational_browser_smoke.cjs`
- `FinDesk v2.0/sprints/SPRINT-33R-dictionary-training-triage-filters.md`

## Verification

Commands:

```text
node --check public/assets/v2/app.js
node --check scripts/v2_operational_browser_smoke.cjs
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Result:

```text
FinDesk v2 clean core static smoke: OK
FinDesk v2 fixture runner: PASS 21
FinDesk v2 HTTP API smoke: OK
FinDesk v2 operational UI smoke: OK
FinDesk v2 browser UI smoke: OK
```

## Director Final Handoff

Status:
ACCEPT.

Accepted work:

- Training triage controls are available and read-only.
- Decision readback is visible in queue rows through badges.
- Deferred and Decided are separate user-facing states.
- Filter/search behavior is covered by browser smoke.

Deferred:

- Backend pagination beyond `limit=120`.
- Advanced token inputs for `requires_any` / `excludes_any`.
- Dedicated mobile tabs for Training queue/decision.
