# FinDesk Product Bible Sprint 7 — Mobile UX / Route Cleanup Local Report — 2026-06-03

## Source

Highest source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Previous sprint:

```text
docs/AI_TEAM/70_PRODUCT_BIBLE_SPRINT6_REPORT_DETAIL_EXPORT_LOCAL_2026-06-03.md
```

## Goal

Reduce physical mobile friction before real-device QA:

```text
keyboard/input conflict
touch/scroll behavior
PWA viewport height
legacy route side effects
```

## Files Changed

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
docs/AI_TEAM/04_TASK_BOARD.md
docs/AI_TEAM/71_PRODUCT_BIBLE_SPRINT7_MOBILE_UX_ROUTE_CLEANUP_LOCAL_2026-06-03.md
```

## Done

### 1. Dynamic Mobile Viewport

Added Product shell viewport sync through:

```text
window.visualViewport
--phase1-viewport-height
```

The shell updates height on:

```text
resize
orientationchange
visualViewport resize
visualViewport scroll
```

### 2. Keyboard State

Product shell inputs now set:

```text
body.phase1-keyboard-open
```

on focus, and remove it after focus leaves Product inputs.

Focused Product inputs scroll into view with a short delay.

### 3. Live Journal Mobile Layout

Live Journal no longer depends on raw `100vh`.

Updated:

```text
phase1-journal-workspace
phase1-records-feed
phase1-journal-bottom
phase1-input-line
```

Effects:

- visible height follows the real mobile viewport;
- records feed remains touch-scrollable;
- journal bottom/input is sticky near the safe bottom;
- keyboard-open state compacts the journal surface;
- iOS zoom risk is reduced by keeping input font size at 16px.

### 4. Touch Behavior

Added touch handling for Product buttons and inputs:

```text
touch-action: manipulation
-webkit-tap-highlight-color: transparent
-webkit-overflow-scrolling: touch
overscroll-behavior
```

### 5. Legacy Route Side Effects Guard

Added:

```text
phase1ShellIsActive()
```

Legacy click listeners and `qlSetModule` wrappers now stop side effects while Product shell is active.

This reduces old-route dirt from:

```text
captain
ontherun
money/advances
legacy report wrappers
```

The old modules remain available as hidden engine support, but should not wake up behind the Product shell.

### 6. Asset Version Updated

```text
20260603-mobile-ux1
```

Service worker cache:

```text
findesk-20260603-mobile-ux1
```

## Local Checks

Passed:

```bash
node --check public/assets/app.js
node --check public/service-worker.js
git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/service-worker.js
curl -I http://127.0.0.1:18889/app.php
curl -I http://127.0.0.1:18889/assets/app.js?v=20260603-mobile-ux1
curl -I http://127.0.0.1:18889/assets/app.css?v=20260603-mobile-ux1
```

## Not Done

- This is not a real-device physical QA pass.
- Browser visual QA was not run because Playwright is not installed in this local environment.
- Camera/scanner PWA gate remains open.
- Package-wide archive ZIP export remains open.
- No production deploy was performed in this sprint.

## Next Sprint

Sprint 8 should be the physical QA gate:

```text
real mobile device
PWA install mode
keyboard open/close
touch scroll
Live Journal add/submit
Team flow
Report detail/export
old-route dirt check
```
