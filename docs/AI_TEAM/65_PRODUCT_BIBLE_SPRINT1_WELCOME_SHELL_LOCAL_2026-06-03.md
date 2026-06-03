# FinDesk Product Bible Sprint 1 — Welcome / Shell Local Report — 2026-06-03

## Source

Highest source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Sprint 0 route map:

```text
docs/AI_TEAM/64_PRODUCT_BIBLE_SPRINT0_ROUTE_MAP_2026-06-03.md
```

## Goal

Make the new FinDesk product route visible before rebuilding deeper screens.

Sprint 1 is not a final UX pass. It is the first local product-shell correction after Product Bible V1.

## Files Changed

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
docs/AI_TEAM/04_TASK_BOARD.md
docs/AI_TEAM/05_DECISIONS.md
docs/AI_TEAM/64_PRODUCT_BIBLE_SPRINT0_ROUTE_MAP_2026-06-03.md
```

## Done

### 1. Pre-auth Welcome Hall

The login panel now starts with a short FinDesk Welcome block:

```text
Деньги исчезают тихо.
Потратил — запиши. Получил — запиши.
```

Start paths:

```text
Работаю один
Работаю с людьми
Готовые шаблоны
```

Clicking a path stores intended product route locally and focuses email login.

### 2. Authenticated Menu Reduced

Visible shell menu now follows Product Bible direction:

```text
Workspace
Reports
Account
```

Removed from normal visible menu:

```text
Admin Card
Employee Card
Report Assembly
Protected Actions
old module links
```

Those screens may still exist as internal routes, but they are not top-level menu clutter.

### 3. Product Route State

Added first-class product state:

```text
module: product
findesk_product: true
phase_screen: <screen>
```

`qlOpenPhaseScreen()` now writes product state instead of legacy:

```text
ontherun + phase1_*
```

### 4. Legacy State Gate

Old saved module state no longer restores legacy modules as the normal user route.

Legacy module state now redirects to Product shell.

### 5. Legacy Click Gate

Old `data-module-tab` and `data-mode-open` clicks are mapped back into Product Bible routes:

| Legacy target | Product route |
|---|---|
| `captain` | `team` |
| `groups` | `team` |
| `reports` | `reports` |
| `ontherun` | `journal-choice` |
| `money` / `advances` | `admin` |
| other | `welcome` |

### 6. Welcome Hall Rebuilt After Login

Authenticated Welcome no longer starts with money metrics.

It now shows:

```text
Деньги исчезают тихо.
Работаю один
Работаю с людьми
Готовые шаблоны
```

### 7. New Service Screens

Added local product screens:

```text
templates
profile
```

`profile` is service-only and does not contain money controls.

### 8. Legacy Flash Reduced

`moduleOnTheGo` is no longer `active` by default in `public/app.php`.

It starts hidden, like other legacy modules.

### 9. Asset Version Updated

```text
20260603-product-shell1
```

Service worker cache:

```text
findesk-20260603-product-shell1
```

## Local Checks

Passed:

```bash
node --check public/assets/app.js
node --check public/service-worker.js
git diff --check -- public/app.php public/assets/app.js public/assets/app.css public/service-worker.js
curl -I http://127.0.0.1:18889/app.php
curl -I http://127.0.0.1:18889/assets/app.js?v=20260603-product-shell1
curl -I http://127.0.0.1:18889/assets/app.css?v=20260603-product-shell1
```

HTTP result:

```text
app.php 200 OK
app.js 200 OK
app.css 200 OK
```

## Not Done Yet

Sprint 1 does not complete:

- full auth-intercept return for every interrupted action;
- full browser visual QA;
- mobile keyboard QA;
- Live Journal rebuild;
- Team Workspace rebuild;
- Admin / Employee Card rebuild;
- Report Assembly rebuild;
- production deployment.

## Next Sprint

Sprint 2 target:

```text
Solo Workspace
Cash / Card Choice
Live Journal records-first rebuild
```

Definition of done:

```text
Welcome -> Работаю один -> Cash -> Live Journal -> add record -> Зафиксировать журнал
```

The screen must feel like the operational heart of FinDesk, not a modified old On The Go form.
