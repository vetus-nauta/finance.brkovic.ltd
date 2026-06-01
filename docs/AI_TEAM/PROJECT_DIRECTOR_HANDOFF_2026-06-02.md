# Project Director Start Handoff - FinDesk - 2026-06-02

Role taking over: Project Director FinDesk
Repository: `/home/alexey/GitHub/finance.brkovic.ltd`
GitHub remote: `git@github.com:vetus-nauta/finance.brkovic.ltd.git`
Branch: `main`
Last known base before this sync: `459c751`

## Office

The AI Team office is here:

```text
docs/AI_TEAM/
```

Open the office dashboard first:

```text
docs/AI_TEAM/OFFICE_DASHBOARD.html
```

Start documents:

1. `docs/AI_TEAM/00_START_HERE.md`
2. `docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-02.md`
3. `docs/AI_TEAM/04_TASK_BOARD.md`
4. `docs/AI_TEAM/05_DECISIONS.md`
5. Role status files under `docs/AI_TEAM/roles/*/STATUS.md`

The office rule remains strict: role folders keep full reports; the director chat receives short reports only.

## Current Production Reality

Correct production host:

```text
https://finance.brkovic.ltd/app.php
```

Correct FTP production path:

```text
finance.brkovic.ltd/public
```

Do not deploy this project to another domain or subdirectory.

Latest production frontend asset version:

```text
20260601-findesk-mobilefit2
```

Production changes confirmed by HTTP:

- `/app.php` returns `200`;
- app shell loads `app.css`, `i18n.js`, and `app.js` with `20260601-findesk-mobilefit2`;
- `service-worker.js` uses cache `findesk-20260601-findesk-mobilefit2`;
- mobile start-screen Playwright smoke passed.

Production files uploaded in the latest frontend cycle:

- `public/app.php`
- `public/assets/app.css`
- `public/assets/app.js`
- `public/service-worker.js`

Local production backup before the reference rebuild:

```text
backups/deploy-before-reference-20260601-234615
```

Backups are local-only and must not be treated as GitHub artifacts.

## What Was Done After 2026-05-28

1. Fast-entry UX cleanup was implemented locally.
   - Report: `docs/AI_TEAM/47_FAST_ENTRY_UX_BACK_LOCAL_2026-05-28.md`
   - Scope: modern `Фото/Скан/Файл`, saved proof access, `Наличные`, no amount overlap, app-step browser Back.

2. FinDesk board was rebuilt locally, then refined on production frontend.
   - Report: `docs/AI_TEAM/48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md`
   - Final production frontend direction: lightweight FinDesk surface, not a heavy mixed card pile.

3. Google Drive reference was read and used as visual direction.
   - Result: top brand/balance card, administrator card-button, participant card-buttons, separate full-page card view.

4. Mobile skew was corrected.
   - Latest asset version: `20260601-findesk-mobilefit2`
   - Mobile FinDesk now uses one reliable column for balance, participant cards, and archive.
   - Sticky balance was disabled on mobile.
   - Top brand pill no longer squeezes the secondary text on the mobile start screen.

## Important Source/Production Split

The repository also contains a local open-items backend candidate:

- report: `docs/AI_TEAM/45_OPEN_ITEMS_SPRINT_LOCAL_2026-05-28.md`;
- deploy-blocked report: `docs/AI_TEAM/46_OPEN_ITEMS_SPRINT_DEPLOY_BLOCKED_2026-05-28.md`;
- candidate asset version in that local report: `20260528-open-sprint1`;
- changed areas include `app/ledger.php`, `app/messages.php`, `public/api.php`, `public/assets/i18n.js`, and `deploy/messages_foundation.sql`.

Do not assume that this backend/database candidate is live in production.

Production confirmed from the last cycle is the frontend/mobilefit upload only. Any DB migration or backend package-export/message-link rollout still needs:

1. production backup;
2. DB preflight;
3. controlled application of `deploy/messages_foundation.sql`;
4. selected file upload;
5. production smoke;
6. deploy report.

## Current Product Shape

Main operational surfaces:

- `Живой отчет`: field entry, proof/photo/scan, save, submit.
- `FinDesk`: administrator review and participant card-button board.
- `Детали`: everything else: money, reports, archive, groups, business, settings.

FinDesk rule from CEO:

- outside card: only name and remaining money;
- inside card: full page for details and actions;
- no noisy old-session numbers in an active session;
- no formula rewrite without Product Finance Architect and Chief Auditor.

## Closed Or Mostly Closed

- Field Combat no-data-loss foundation for browser/API scope.
- Proof row links and in-app proof viewer.
- Admin records discovery.
- Scanner modal fit.
- Records scrolling.
- Closed group report package for new package flow.
- Basic employee rights.
- Fast-entry first cleanup.
- FinDesk frontend reference/mobile fit pass.

## Still Open

- Authenticated mobile QA for the final `mobilefit2` FinDesk after login.
- Real-device scanner/PWA camera gate: iPhone Safari, installed iPhone PWA, Android Chrome, Android PWA if available.
- Production rollout of first-class message context links and package JSON export with DB migration.
- Legacy final report package fixtures and fallback verification.
- Package-wide archive export beyond JSON links, if CEO wants a ZIP/proof-binary package.
- Language/PWA audit across supported languages on real devices.
- Full active-session model redesign if CEO approves the next architecture sprint.

## Verification Before This Sync

Passed locally:

```bash
node --check public/assets/app.js
git diff --check -- public/app.php public/assets/app.css public/assets/app.js public/service-worker.js
```

Passed on production HTTP:

```text
https://finance.brkovic.ltd/app.php?nocache=mobilefit2
https://finance.brkovic.ltd/assets/app.css?v=20260601-findesk-mobilefit2
https://finance.brkovic.ltd/service-worker.js?nocache=mobilefit2
```

Environment note: PHP CLI is not installed in the current shell, so PHP lint/smoke must be done through server HTTP/API or another environment.

## Director Start Procedure

1. Run:

```bash
git status --short
git status -sb
git rev-parse --short HEAD
git rev-parse --short origin/main
```

2. Read:

```text
docs/AI_TEAM/00_START_HERE.md
docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-02.md
docs/AI_TEAM/04_TASK_BOARD.md
docs/AI_TEAM/05_DECISIONS.md
docs/AI_TEAM/roles/*/STATUS.md
```

3. Open the office:

```text
docs/AI_TEAM/OFFICE_DASHBOARD.html
```

4. Assign roles only with short technical cards:

- role;
- task;
- exact read paths;
- exact write paths;
- acceptance criteria;
- blocker rule;
- short-report format.

5. Do not reset, checkout, clean, or discard local work unless the CEO explicitly orders it.

## Next Recommended Sprint

Run a controlled QA sprint on the production `mobilefit2` frontend:

1. login on real mobile;
2. open `FinDesk`;
3. verify brand/balance/card layout;
4. open administrator card;
5. open participant card;
6. return with the in-app back button and browser Back;
7. verify no horizontal scroll, overlap, or stuck sticky header.

Only after this QA pass should the next backend/DB production candidate be deployed.
