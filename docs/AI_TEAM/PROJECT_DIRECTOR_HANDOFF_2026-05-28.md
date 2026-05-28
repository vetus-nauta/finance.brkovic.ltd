# Project Director Handoff - FinDesk - 2026-05-28

Role taking over: Project Director FinDesk
Repository: `/home/alexey/GitHub/finance.brkovic.ltd`
GitHub remote: `git@github.com:vetus-nauta/finance.brkovic.ltd.git`
Branch: `main`

## Read First

Start here, in this order:

1. `docs/AI_TEAM/00_START_HERE.md`
2. `docs/AI_TEAM/01_PRODUCT_COMPASS.md`
3. `docs/AI_TEAM/02_CURRENT_STATE.md`
4. `docs/AI_TEAM/03_WORKFLOW_RULES.md`
5. `docs/AI_TEAM/04_TASK_BOARD.md`
6. `docs/AI_TEAM/05_DECISIONS.md`
7. `docs/AI_TEAM/15_100_PERCENT_MVP_CONTROL.md`
8. This handoff: `docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-05-28.md`

Role folders:

- Product Finance Architect: `docs/AI_TEAM/roles/01_product_finance_architect/`
- Backend/Data Engineer: `docs/AI_TEAM/roles/02_backend_data_engineer/`
- Frontend/UX Engineer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/`
- QA Release Engineer: `docs/AI_TEAM/roles/04_qa_release_engineer/`
- Chief Auditor: `docs/AI_TEAM/roles/05_chief_auditor/`

Short reports belong in the director chat. Full findings belong in the role folders.

## Operating Rules

- Do not reset, checkout, clean, or overwrite user work.
- Do not expose FTP, DB, gate tokens, or production credentials in docs or chat.
- Do not change financial formulas without Product Finance Architect and Chief Auditor.
- Do not change backend/API without Backend/Data ownership.
- Do not change UX flow without Frontend/UX ownership.
- Do not declare readiness without QA Release Engineer and Chief Auditor evidence.
- One request to a role should produce one short role report back to the director chat.
- Role reports must stay in their own folders; the director reads details from files.
- When assigning a role, give a short technical card: role, task, exact files to read/write, acceptance criteria, blocker rule, short-report format.

## Product Compass

FinDesk is not an accounting toy. It must preserve the full money tree:

- who holds the money;
- where physical cash is;
- where card/noncash spending is;
- where accountable money sits;
- where proof files are attached;
- where review happens;
- where final reports are fixed;
- where archived report packages live;
- where every number can be traced to evidence.

MVP user promise:

- quick field capture in motion;
- clear analysis/review;
- submit and save report;
- print/PDF;
- group report consolidation;
- archive;
- group members and money streams into one common pot.

Advanced/non-MVP:

- deep travel settlement engine;
- broad Business Desk automation beyond proforma basics;
- full AI dashboards;
- fraud scoring;
- ZIP/notarized package export;
- deep SEO work outside this repo;
- OCR extraction beyond current scanner proof capture.

## Where The Product Is

Business MVP product gate is approved for the checked new-data path. Production deploys and hotfixes have been executed from the director chat. The current production-facing loop is:

- `Живой отчет` / Field Combat capture;
- cash/card separation;
- proof attachments;
- group admin review;
- employee reports;
- closed group report package by `report_id`;
- package proof download;
- print/PDF page path;
- short Excel/Google final-report tables;
- archive view.

Do not claim real-device scanner/PWA camera readiness yet. Browser/modal proof is good; physical iPhone Safari/PWA camera gate remains separate.

## Production Deploy Trail

Important production reports:

- `docs/AI_TEAM/38_CANDIDATE34_PRODUCTION_DEPLOY_REPORT_2026-05-28.md`
- `docs/AI_TEAM/39_OWNER_SELF_RETURN_HOTFIX_PRODUCTION_2026-05-28.md`
- `docs/AI_TEAM/40_PROOF_LINKS_HOTFIX_PRODUCTION_2026-05-28.md`
- `docs/AI_TEAM/41_PROOF_VIEWER_HOTFIX_PRODUCTION_2026-05-28.md`
- `docs/AI_TEAM/42_RECORDS_ADMIN_DISCOVERY_HOTFIX_PRODUCTION_2026-05-28.md`
- `docs/AI_TEAM/43_SCANNER_FIT_HOTFIX_PRODUCTION_2026-05-28.md`
- `docs/AI_TEAM/44_RECORDS_SCROLL_HOTFIX_PRODUCTION_2026-05-28.md`

Most recent production hotfixes:

- `20260528-records-admin1`: group admins see employee cards in normal records page, proof viewer opens row files.
- `20260528-scanner-fit1`: scanner modal fits `390x844`.
- `20260528-records-scroll1`: long `Живые отчеты` records columns scroll correctly.

Latest production smoke evidence:

- Records admin discovery: `prod-records-hotfix-20260528161828`
- Scanner fit: `prod-scanner-fit-20260528162815`
- Records scroll: `prod-records-scroll-20260528164351`

Temporary DB-gate was removed after production smokes and returned `404`.

## What Is Closed

Closed gates and accepted slices:

- instant field capture slice;
- current vs historical finalized report/export separation;
- combo regression after finalization;
- Field Combat no-data-loss gate for browser/API scope;
- closed group report package gate;
- business MVP residual surfaces QA;
- base employee rights and group data isolation;
- owner self-return for stuck legacy submitted card;
- proof row links and in-app proof viewer;
- admin records discovery for employee cards;
- mobile scanner modal fit;
- records window scrolling for long card columns.

## What Is Not Solved Yet

Open or post-MVP items:

- Real-device scanner gate: iPhone Safari browser, iPhone installed PWA, Android Chrome, and installed PWA if available. See `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`.
- First-class message schema links to `report_id`, `tape_id`, `capture_id`, `advance_id`; current package message context is partly audit-derived.
- Legacy finalizations/packages without new immutable snapshot/package need fixtures and fallback verification.
- Same-second finalization cutoff hardening remains P1 unless reproduced.
- Package-wide downloadable archive/export beyond browser print/PDF is not implemented.
- Language/PWA audit for supported languages remains P1; fallback must be English when system language is outside the supported list.
- Current export wording around carryover may need Product Finance decision if exact downloaded text matters.
- Broader mobile UX polish remains active: keep screens simple, large input, no hidden proof path, no blocked scroll.

## How To Continue

Use this loop:

1. Read `04_TASK_BOARD.md` and the latest role `STATUS.md` files.
2. Pick the next concrete blocker or CEO-reported issue.
3. Assign exactly one role if needed, with a short technical card.
4. Implement only the narrow fix needed.
5. Run local checks:
   - `node --check public/assets/app.js`
   - `git diff --check`
   - browser/HTTP smoke where the issue is visual or flow-related.
6. If deploying:
   - take production FTP backup first;
   - upload only selected files;
   - run production smoke;
   - remove any temporary gate;
   - verify gate returns `404`;
   - write a numbered production report in `docs/AI_TEAM/`.
7. Update `04_TASK_BOARD.md`, `05_DECISIONS.md`, and relevant role files.
8. Commit and push to GitHub.

## Local Commands

Baseline:

```bash
git status --short
git rev-parse --short HEAD
git rev-parse --short origin/main
curl -sS -I http://127.0.0.1:18889/app.php
curl -sS http://127.0.0.1:18889/api.php?action=current_user -H 'Content-Type: application/json' --data '{}'
```

Checks:

```bash
node --check public/assets/app.js
node --check public/assets/i18n.js
node --check public/service-worker.js
git diff --check
```

PHP CLI is not reliably available in this environment. Use HTTP/API smokes when CLI PHP is missing.

## Git Sync Notes

The worktree contains broad MVP runtime changes plus docs. Ignored local-only data includes:

- `app/config.local.php`
- `storage/`
- `backups/`

Do not force push. Normal sync path:

```bash
git add -A
git status --short
git commit -m "Prepare FinDesk MVP handoff"
git push origin main
git rev-parse --short HEAD
git rev-parse --short origin/main
```

## Short Prompt For The New Director

Use the short prompt from the director chat together with this file. The new director should not restart discovery from zero; the project state is documented and production hotfixes have reports.
