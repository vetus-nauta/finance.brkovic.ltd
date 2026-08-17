# SPRINT-16R — Keyboard Row Sync + Bidirectional Focus Rails

Status: Accepted

## Director Sprint Opening

Sprint:
SPRINT-16R — Keyboard Row Sync + Bidirectional Focus Rails

Goal:
Turn the linked ledger into a desktop-grade row workspace: equal-height journal/check rows, journal header columns, hover-only row inspection, keyboard row navigation, Enter-to-open details, Escape-to-return, and bidirectional focus rails.

User requirements:
- Operational journal and Structured check rows must share equal row height.
- Operational journal must have columns: `#`, `Описание`, `Сумма`.
- Hovering a row should visually highlight it only; it must not switch the active window, move DOM focus, change active row state, or open Entry details.
- ArrowUp and ArrowDown should move the active row.
- ArrowLeft and ArrowRight should move the active row focus between Operational journal and Structured check.
- Active row must sync between Operational journal and Structured check.
- Enter opens contextual Entry details for the active row.
- Escape closes Entry details and restores focus to the same active row.
- Desktop focus rails must be bidirectional:
  - journal focus expands journal and compacts Structured check;
  - check focus expands Structured check and compacts journal.

Assigned agents:
- UX/Interaction Agent: Godel
- QA/Audit Agent: Bacon
- Finance Logic Reviewer: Fermat

## Agent Reports

UX/Interaction Agent:
Initial verdict was REJECT. The agent found missing keyboard proof, incomplete journal focus rails, and row-button keyboard dead-end risk.

Director response:
- Row buttons are now accepted keyboard navigation targets.
- `activeEntryId` is separate from `selectedEntryId`.
- Hover and keyboard active state no longer opens details.
- Journal focus CSS and check focus CSS are both implemented.
- Browser smoke now proves keyboard and focus behavior.

QA/Audit Agent:
ACCEPT criteria defined. Required browser evidence:
- equal row heights by shared `data-v2-entry-id`;
- journal header `# / Описание / Сумма`;
- hover does not open details;
- ArrowUp/ArrowDown moves active row;
- active row syncs across both surfaces;
- Enter opens details;
- Escape restores row focus;
- bidirectional focus rails are proven with screenshots and metrics.

Finance Logic Reviewer:
ACCEPT. No finance-safety blockers found. Changes are UI-only for row layout, focus rails, keyboard navigation, detail overlay, and browser smoke. `app/v2/Api.php` and finance/parser/report behavior were not changed.

## Implementation Summary

Changed behavior:
- Operational journal is now a row grid with header columns: `#`, `Описание`, `Сумма`.
- Journal rows and Structured check rows use the same 40px row height.
- Active row state is synchronized across journal and check.
- Hover is visual-only; it does not open Entry details, switch the active window, move DOM focus, or change active row state.
- Click still opens Entry details for quick mouse use.
- ArrowUp/ArrowDown moves the active row repeatedly in the focused surface.
- ArrowLeft/ArrowRight transfers focus between journal and check for the same active row.
- Enter opens Entry details for the active row.
- Escape closes Entry details and restores focus to the originating row/surface.
- Journal/check panel headers now share the same panel-head height, sticky header behavior, and scrollbar gutter handling to keep first rows aligned.
- Desktop has two focus rail modes:
  - `.is-journal-focused`;
  - `.is-check-focused`.
- Structured check remains the full finance field view when not compacted.

Changed files:
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `public/v2.php`
- `scripts/v2_operational_browser_smoke.cjs`
- `FinDesk v2.0/sprints/SPRINT-16R-keyboard-row-sync-focus-rails.md`

Not changed:
- `app/v2/Api.php`
- `app/v2/Repository.php`
- SQL/schema files
- parser/import files
- report generation files
- auth/storage/deploy behavior
- calculation/API contracts

## Evidence

Browser evidence:
- `test-results/v2-browser-smoke/layout-metrics.json`
- `test-results/v2-browser-smoke/desktop-journal-header-row-sync.png`
- `test-results/v2-browser-smoke/desktop-hover-no-detail.png`
- `test-results/v2-browser-smoke/desktop-arrow-left-right-surface-switch.png`
- `test-results/v2-browser-smoke/desktop-escape-focus-restored-journal.png`
- `test-results/v2-browser-smoke/desktop-escape-focus-restored-check.png`
- `test-results/v2-browser-smoke/desktop-journal-focus.png`
- `test-results/v2-browser-smoke/desktop-check-focus.png`
- `test-results/v2-sprint-16r/full-gate.log`

Evidence labels in `layout-metrics.json`:
- `initial desktop row height sync`
- `desktop journal/check header and first row alignment`
- `SPRINT-16R linked ledger keyboard evidence`
- `scroll filler row height sync`
- `SPRINT-16R scroll filler row sync`

Full local gate:
- `npm run smoke:v2`: PASS
- `npm run smoke:v2:auth`: PASS
- `npm run test:v2:fixtures`: PASS, 17 tests
- `npm run smoke:v2:http`: PASS
- `npm run smoke:v2:db`: PASS
- `npm run smoke:v2:ui`: PASS
- `npm run smoke:v2:browser`: PASS
- `npm run smoke:v2:deploy`: PASS with 2 known deployment warnings

Latest full gate:
- `test-results/v2-sprint-16r/full-gate.log`
- Finished at `2026-07-06T22:01:26+02:00` with status `0`.
- Browser assets are cache-busted as `20260706-sprint16r-b`.

Deployment warnings observed:
- `storage/logs/auth_codes.log exists locally; production deploy must purge/avoid this file`
- `FINDESK_V2_PRODUCTION_BASE_URL is not set; live HTTP deny checks were skipped`

## Director Decision

ACCEPT SPRINT-16R as local implementation and browser-evidence accepted.

This does not accept production deployment. SPRINT-11R production deployment remains blocked until live production evidence exists.
