# FinDesk Product Bible Sprint 8 — Archive Export / Physical QA Gate Local Report — 2026-06-03

## Source

Highest source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Previous sprint:

```text
docs/AI_TEAM/71_PRODUCT_BIBLE_SPRINT7_MOBILE_UX_ROUTE_CLEANUP_LOCAL_2026-06-03.md
```

## Goal

Prepare the product for physical QA and close the local archive export gap:

```text
Reports
  -> export one report
  -> export full group archive package
  -> hand QA a real-device checklist
```

## Files Changed

```text
app/findesk_phase2.php
public/api.php
public/app.php
public/assets/app.js
public/service-worker.js
docs/AI_TEAM/04_TASK_BOARD.md
docs/AI_TEAM/72_PRODUCT_BIBLE_SPRINT8_ARCHIVE_EXPORT_QA_GATE_LOCAL_2026-06-03.md
docs/AI_TEAM/roles/04_qa_release_engineer/PHYSICAL_QA_CHECKLIST_PRODUCT_BIBLE_2026-06-03.md
```

## Done

### 1. Package-Wide Archive Export API

Added:

```text
findesk_report_archive_export
```

The endpoint returns a group-level archive JSON package:

```text
package_type: findesk_archive_package
package_version: 1
exported_at
exported_by_user_id
group
reports_count
reports[]
  report
  items
  snapshot
```

Access is limited to users who can view group reports.

### 2. Reports UI Archive Export

Reports screen now has:

```text
Экспорт архива
```

It downloads:

```text
findesk-archive-{groupId}.json
```

### 3. Physical QA Checklist

Created:

```text
docs/AI_TEAM/roles/04_qa_release_engineer/PHYSICAL_QA_CHECKLIST_PRODUCT_BIBLE_2026-06-03.md
```

Checklist covers:

- Product route recognition;
- mobile keyboard behavior;
- touch and scroll;
- Live Journal;
- Team Workspace;
- Report Assembly;
- Reports / Export;
- PWA / Camera gate;
- fail conditions.

### 4. Asset Version Updated

```text
20260603-archive-export1
```

Service worker cache:

```text
findesk-20260603-archive-export1
```

## Local Checks

Passed:

```bash
node --check public/assets/app.js
node --check public/service-worker.js
git diff --check -- app/findesk_phase2.php public/api.php public/app.php public/assets/app.js public/assets/app.css public/service-worker.js
curl -I http://127.0.0.1:18889/app.php
curl -I http://127.0.0.1:18889/assets/app.js?v=20260603-archive-export1
curl -I http://127.0.0.1:18889/assets/app.css?v=20260603-archive-export1
```

Authenticated API smoke passed:

```text
admin login
admin creates group
admin creates cash journal
admin submits journal
admin attaches journal to draft report
admin finalizes report
admin exports archive package
archive package contains finalized report
archive package contains report items
```

Smoke result:

```json
{
  "ok": true,
  "groupId": 272,
  "tapeId": 455,
  "reportId": 5,
  "packageType": "findesk_archive_package",
  "reportsCount": 1,
  "itemCount": 1
}
```

## Not Done

- Archive export is JSON package, not ZIP with binary attachments.
- Real-device physical QA was not run.
- Browser visual QA was not run because Playwright is not installed in this local environment.
- Camera/scanner PWA gate still requires physical device check.
- No production deploy was performed in this sprint.

## Next Step

Run the physical QA checklist on real devices.

Production release remains blocked until physical QA passes.
