# Local QA Checklist Run — Product Bible FinDesk — 2026-06-03

## Scope

This is a local QA run against:

```text
docs/AI_TEAM/roles/04_qa_release_engineer/PHYSICAL_QA_CHECKLIST_PRODUCT_BIBLE_2026-06-03.md
```

It is not a real-device physical QA pass.

## Environment

```text
Local URL: http://127.0.0.1:18889/app.php
Asset version: 20260603-archive-export1
Service worker cache: findesk-20260603-archive-export1
Date: 2026-06-03
```

## Result Summary

```text
Local API/engine QA: PASS
Static PWA/route/mobile guard QA: PASS with physical verification required
Real-device physical QA: NOT RUN
Production deploy: BLOCKED
```

## Checks Passed Locally

### Server / Assets

Passed:

```bash
curl -I http://127.0.0.1:18889/app.php
curl -I http://127.0.0.1:18889/assets/app.js?v=20260603-archive-export1
curl -I http://127.0.0.1:18889/assets/app.css?v=20260603-archive-export1
curl -I http://127.0.0.1:18889/manifest.webmanifest
node --check public/assets/app.js
node --check public/service-worker.js
git diff --check
```

### Product Route / Legacy Dirt

Static pass:

- Product shell route guards are present;
- `phase1ShellIsActive()` is present;
- legacy click listeners and `qlSetModule` wrappers are guarded while Product shell is active;
- current asset version is wired into `app.php` and service worker.

Not physically verified:

- old screen flash during mobile navigation;
- browser Back on a real mobile browser;
- installed PWA route restore.

### Mobile Keyboard / Touch

Static pass:

- `visualViewport` sync is present;
- `--phase1-viewport-height` is present;
- `phase1-keyboard-open` state is present;
- Live Journal no longer depends on raw `100vh`;
- records feed has touch scrolling styles;
- sticky journal bottom is present.

Not physically verified:

- keyboard open/close on iPhone/Android;
- touch freeze after refresh;
- real mobile horizontal overflow.

### Live Journal

Authenticated API pass:

- Cash journal accepted `+500 Owner`;
- Cash journal accepted `-120 Fuel`;
- Card journal accepted `-85 Food`;
- submitted journals moved into report-ready state.

### Team Workspace

Authenticated API pass:

- group created;
- employee invited and joined;
- pending transfer created;
- employee journal blocked while transfer was pending;
- employee confirmed transfer;
- confirmed transfer became active money;
- employee journal accepted spending after confirmation.

### Report Assembly

Authenticated API pass:

- ready journals were visible through `findesk_report_assembly_get`;
- journals attached through `findesk_report_item_attach`;
- Cash/Card/Total summaries returned;
- finalize without reason blocked;
- finalize without `УТВЕРДИТЬ` blocked;
- report finalized with reason and confirmation.

### Reports / Export

Authenticated API pass:

- report detail returned report + items;
- archive export returned `findesk_archive_package`;
- archive package included finalized report and items.

### PWA / Camera Gate

Static pass:

- manifest is linked;
- manifest is served as `application/manifest+json`;
- standalone display is configured;
- icons are configured;
- service worker version is current.

Not physically verified:

- installed PWA open;
- scanner/camera permission behavior;
- scanner modal viewport fit on device.

## API Smoke Result

```json
{
  "ok": true,
  "groupId": 273,
  "adminCashTape": 457,
  "adminCardTape": 459,
  "memberTapeId": 461,
  "transferBlock": "findesk_transfer_pending_confirmation_required",
  "readyAttached": 3,
  "reportId": 6,
  "finalizeNoReason": "empty_finalize_reason",
  "finalizeNoConfirm": "invalid_finalize_confirmation",
  "archivePackage": "findesk_archive_package",
  "archiveReports": 1
}
```

## QA Verdict

Local QA is green for backend logic, route guards, asset delivery and PWA static configuration.

Physical QA is still open.

Production deploy remains blocked until real-device checks pass:

- iPhone Safari;
- iPhone installed PWA;
- Android Chrome;
- Android installed PWA;
- scanner/camera modal;
- keyboard/touch behavior;
- visible old-route dirt check.
