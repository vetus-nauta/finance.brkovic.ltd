# SPRINT-46R — Report Fragment Delivery Controls

Date: 2026-08-10
Status: local acceptance passed

## Director opening

Problem: the report fragment modal showed correct arithmetic, but it did not complete the user's workflow. After creating a report, the user needs practical controls for delivery and closure.

Product rule:
- preview remains calculation-only;
- after report creation, the modal exposes delivery controls;
- report arithmetic is not recalculated by delivery controls;
- status/date updates mutate only the report batch metadata;
- generated HTML remains the canonical sendable artifact for MVP;
- PDF is handled through the browser print/PDF path until a server-side PDF engine is explicitly introduced.

## Scope

Implemented:
- `PATCH /api/workspaces/{workspace}/reports/operational-fragments/{id}` updates report fragment metadata.
- Report fragment can be marked `sent`.
- Report fragment can be closed on a chosen date.
- Modal shows controls after creation:
  - `HTML`
  - `Скачать HTML`
  - `PDF / печать`
  - `Закрыть на дату`
  - `На отправку`
- `/v2-report.php?id=...&download=1` returns the generated HTML as a download.
- `/v2-report.php?id=...&print=1` opens the generated HTML and triggers browser print/PDF.
- Generated HTML shows status and close date.
- Browser smoke now verifies close date, sent status, download link, and generated HTML status labels.

Not implemented:
- server-side binary PDF generation;
- email sending;
- external messenger sending.

## Files

- `app/v2/Repository.php`
- `app/v2/Api.php`
- `public/v2-report.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_report_fragment_browser_smoke.cjs`

## Agent reports

### Product / Director

Decision: do not pretend that FinDesk sends email before mail transport exists. `На отправку` means the report is finalized as ready/sent in FinDesk state. Actual channel integration remains a later explicit connector task.

MVP artifact decision: HTML is the canonical generated artifact. PDF is created via browser print/PDF because it preserves the same report view without adding a server PDF dependency.

### QA, Audit, and Acceptance Agent

Result: PASS, no blocking defects.

Findings and resolutions:
- Risk: report creation was assigning `closed_at` automatically. Resolved: creation no longer closes the report unless an explicit close date is provided.
- Risk: `content_hash` was not recalculated after metadata PATCH. Resolved: status/close-date updates now recalculate `content_hash` and rewrite HTML.
- Risk: smoke did not validate download headers. Resolved: browser smoke now fetches the download URL and checks `Content-Disposition: attachment`.
- Remaining manual check: print dialog behavior must be verified in a real headed browser because automated smoke checks only the `print=1` URL.

## Verification

Commands:

```bash
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l public/v2-report.php
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
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870/01-operational-ready.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870/02-report-range-feed.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870/03-fragment-preview.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870/04-fragment-created.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870/05-fragment-controls-sent.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870/06-report-html.png`
- `test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870/07-locked-rows.png`

Browser smoke result:

```json
{
  "ok": true,
  "workspaceId": "0598267c-9d90-4237-99db-db97134261a0",
  "marker": "REPORT_FRAGMENT_1786368254870",
  "screenshots": "/home/alexey/GitHub/finance.brkovic.ltd/test-results/v2-report-fragment-browser/REPORT_FRAGMENT_1786368254870",
  "link": "/v2-report.php?id=40cfb1aa-7e01-405a-a0cf-43365402f213",
  "reportsCount": {
    "status": 200,
    "hasSummary": true
  }
}
```

## Acceptance

Accepted locally for MVP delivery controls.

Deployment acceptance remains separate.
