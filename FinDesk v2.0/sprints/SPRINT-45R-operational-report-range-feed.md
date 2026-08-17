# SPRINT-45R — Operational Report Range Feed

Date: 2026-08-10
Status: local acceptance passed

## Director opening

User need: prepare a report from an exact operational fragment that can cross month boundaries, for example from part of July through August.

Product rule:
- ordinary operational mode remains month-based and focused on current work;
- report preparation mode temporarily opens an operational feed by date range;
- the user selects the first and last visible entry in that temporary feed;
- created report protects the selected entries from casual editing;
- after create/cancel, the UI returns to the normal monthly operational journal.

## Scope

Implemented:
- API entry listing accepts `from` / `to` date range filters.
- Operational report bar now has `с` / `по` date inputs and an `Открыть` action.
- Structured check panel reserves the same report-range row height as the operational journal, so linked table headers and rows stay vertically aligned.
- Report range defaults to selected month start and selected month current/end date.
- Report mode no longer self-closes before the first row is selected.
- Creating a report exits range mode before reloading the operational journal.
- Browser smoke now covers a cross-month fragment: `2026-07-31` through `2026-08-02`.

Not changed:
- financial formulas;
- parser behavior;
- report batch schema;
- deployment behavior;
- production runtime.

## Files

- `app/v2/Repository.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_report_fragment_browser_smoke.cjs`

## Agent reports

### Product / Director

Decision: report period selection belongs inside report-preparation mode, not inside the ordinary monthly operational mode. This preserves the clean daily workflow and gives the finance/reporting workflow an explicit temporary state.

Acceptance requirements:
- range feed can include entries from different months;
- selected range summary uses exactly the visible selected entries;
- final HTML report exposes the same summary structure as the in-app summary;
- report creation locks selected entries;
- user returns to the normal current/monthly feed after report creation.

### QA, Audit, and Acceptance Agent

Result: PASS with risks found and patched.

Findings:
- Main acceptance path passed: `2026-07-31` + August entries loaded in one report feed; first/last row selection worked; report fragment was created; selected rows were locked; UI returned to August operational journal.
- Risk found: `Escape` could close report mode without reloading the ordinary monthly feed.
- Risk found: switching Cash/Card while in report mode could close report mode without reloading the ordinary monthly feed.
- Risk found: smoke should open the real HTML report, not only the in-app modal and list API.

Resolution:
- `Escape` now exits through `leaveReportSelectionMode()`.
- Cash/Card switching now reloads the ordinary monthly feed when it exits report mode.
- Browser smoke now opens `/v2-report.php?id=...` and screenshots the real HTML report.

## Verification

Commands:

```bash
php -l app/v2/Repository.php
php -l public/v2.php
node --check public/assets/v2/app.js
node --check scripts/v2_report_fragment_browser_smoke.cjs
php scripts/v2_clean_core_static_smoke.php
node scripts/v2_report_fragment_browser_smoke.cjs
```

Results:
- PHP syntax: passed.
- JS syntax: passed.
- Clean core static smoke: passed.
- Browser report fragment smoke: passed.

Evidence:
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786366789587/01-operational-ready.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786366789587/02-report-range-feed.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786366789587/03-fragment-preview.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786366789587/04-fragment-created.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786366789587/05-locked-rows.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786367058892/01-operational-ready.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786367058892/02-report-range-feed.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786367058892/03-fragment-preview.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786367058892/04-fragment-created.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786367058892/05-report-html.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786367058892/06-locked-rows.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368451391/02-report-range-feed.png`

Browser smoke result:

```json
{
  "ok": true,
  "workspaceId": "bd41eb0d-a0b8-4dbb-b18d-efa1c7e1a751",
  "marker": "REPORT_FRAGMENT_1786367058892",
  "screenshots": "/home/alexey/GitHub/finance.brkovic.ltd/test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786367058892",
  "link": "/v2-report.php?id=da5647c6-65dd-47ef-956a-f8d6dc1bf0ed",
  "reportsCount": {
    "status": 200,
    "hasSummary": true
  }
}
```

## Acceptance

Accepted locally for implementation behavior.

Deployment acceptance remains separate: production must not be accepted until production runtime is updated and live evidence is collected.
