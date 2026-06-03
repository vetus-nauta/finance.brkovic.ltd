# FinDesk Product Bible Sprint 6 — Report Detail / Export Local Report — 2026-06-03

## Source

Highest source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Previous sprint:

```text
docs/AI_TEAM/69_PRODUCT_BIBLE_SPRINT5_REPORT_ASSEMBLY_LOCAL_2026-06-03.md
```

## Goal

Make finalized reports usable after creation:

```text
Reports
  -> open report
  -> inspect Cash / Card / Total
  -> inspect included journals
  -> export report package
```

## Files Changed

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
docs/AI_TEAM/04_TASK_BOARD.md
docs/AI_TEAM/70_PRODUCT_BIBLE_SPRINT6_REPORT_DETAIL_EXPORT_LOCAL_2026-06-03.md
```

## Done

### 1. Report Detail

Finalized report rows now have:

```text
Открыть
Экспорт
```

`Открыть` loads:

```text
findesk_report_detail
```

The detail panel shows:

```text
Cash
Card / Non-cash
Total
Cash Section
Card / Non-cash Section
```

### 2. Report Package Export

`Экспорт` builds a browser JSON download from the report detail snapshot.

Package shape:

```text
package_type: findesk_report_package
package_version: 1
exported_at
group
report
cash_summary
card_summary
total_summary
items
snapshot
```

This is a local product step toward package-wide archive export. It does not yet export files/attachments as a ZIP package.

### 3. Group Safety

Open report detail is cleared when the active workspace changes.

This prevents showing a report from a previous group after switching environment.

### 4. Asset Version Updated

```text
20260603-report-detail1
```

Service worker cache:

```text
findesk-20260603-report-detail1
```

## Local Checks

Passed:

```bash
node --check public/assets/app.js
node --check public/service-worker.js
git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/service-worker.js
curl -I http://127.0.0.1:18889/app.php
curl -I http://127.0.0.1:18889/assets/app.js?v=20260603-report-detail1
curl -I http://127.0.0.1:18889/assets/app.css?v=20260603-report-detail1
```

Authenticated API smoke passed:

```text
admin login
admin creates group
admin creates cash journal
admin submits journal
admin attaches journal to draft report
admin finalizes report
admin opens report detail
detail returns report + items + snapshot
export payload contains report summaries and items
```

Smoke result:

```json
{
  "ok": true,
  "groupId": 271,
  "tapeId": 453,
  "reportId": 4,
  "detailItems": 1,
  "packageType": "findesk_report_package"
}
```

## Not Done

- Browser visual QA was not run because Playwright is not installed in this local environment.
- Mobile physical QA was not run.
- Export is JSON package, not ZIP with attachments.
- Package-wide archive export across all reports remains open.
- No production deploy was performed in this sprint.

## Next Sprint

Sprint 7 should focus on physical UX and mobile:

```text
mobile layout
touch responsiveness
keyboard/input conflict
old-route remnants
physical QA checklist
```
