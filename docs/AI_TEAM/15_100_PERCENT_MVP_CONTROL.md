# 100 Percent MVP Control

Date: 2026-05-27

Owner: Project Director

Status: 100 percent MVP deployed and production-smoked.

## Definition

`100 percent MVP` means:

1. Business-MVP product gate is approved.
2. Production deploy package is selected from the dirty tree.
3. Production database/runtime migration plan is explicit.
4. Backup and rollback are ready.
5. Production smoke checklist is ready and then executed after upload.
6. CEO can use the product on the live site without relying on local-only evidence.

## Product Gate

Current state:

- Product Finance Architect: final product readiness `PASS`.
- Frontend/UX: final frontend/mobile readiness `PASS`.
- QA Release Engineer: final evidence pack `PASS`.
- Backend/Data: no known backend/API P0 for business-MVP product readiness.
- Chief Auditor: full business-MVP product gate `approved`.

Product gate is not the same as production deploy gate.

## Production Gate

Production gate is closed for the checked MVP path.

Closed production controls:

- deploy file list selected and packaged as `findesk-mvp-runtime-20260527T192800Z`;
- unrelated dirty-tree files excluded from the runtime artifact;
- production files/storage backup completed as `prod-files-before-mvp-20260527T185902Z`;
- production DB backups completed;
- production schema preflight and runtime SQL application completed;
- production HTTP/API smoke passed.

## Local Release Artifact

Final release artifact built on 2026-05-27:

- release id: `findesk-mvp-runtime-20260527T192800Z`;
- artifact: `backups/findesk-mvp-runtime-20260527T192800Z/findesk-mvp-runtime-20260527T192800Z.tar.gz`;
- checksum: `c4e1a79d1bd8091aa21bd7ac21c685c95f1389d62bf572d0ba98b481ccb4f7f4`;
- full record: `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`.

This closes the local package-selection gap and records the deployed production package.

## Project Director Deploy Package Decision

Default mode: narrow MVP runtime bundle.

Do not deploy the full dirty worktree.

Selected runtime package candidate:

- Backend/API:
  - `public/api.php`
  - `app/auth.php`
  - `app/groups.php`
  - `app/ledger.php`
  - `app/on_the_go.php`
  - `app/advances.php`
  - `app/ai.php`
- Runtime SQL:
  - `deploy/on_the_go_sessions_runtime.sql` as a controlled DB migration, not a web upload.
- Frontend/runtime:
  - `public/app.php`
  - `public/index.php`
  - `public/robots.txt`
  - `public/sitemap.xml`
  - `public/manifest.webmanifest`
  - `public/service-worker.js`
  - `public/assets/app.js`
  - `public/assets/app.css`
  - `public/assets/i18n.js`
- Referenced public assets:
  - `public/assets/brand-mark.png`
  - `public/assets/brand-og.png`
  - `public/favicon.ico`
  - `public/assets/favicon.ico`
  - `public/assets/favicon-16x16.png`
  - `public/assets/favicon-32x32.png`
  - `public/assets/favicon-48x48.png`
  - `public/assets/favicon-64x64.png`
  - `public/assets/apple-touch-icon.png`
  - `public/assets/icon-180.png`
  - `public/assets/icon-192.png`
  - `public/assets/icon-512.png`
  - `public/assets/icon-maskable-512.png`
- Root routing:
  - `.htaccess`

Director decision on `app/ai.php`: include it as an explicit dependency of the current `public/api.php`. It is treated as `Advanced` / staged product surface, not as a financial formula or MVP money-core requirement. Do not silently omit it because the current API file requires it before routing.

Director decision on `public/service-worker.js`: include it with the selected frontend package because the production app registers `/service-worker.js` and the changed file clears old `findesk-*` caches. Rollback must include browser hard reload/service-worker cleanup if smoke fails.

Excluded by default:

- `public/reset-local.php`
- `scripts/start-local.sh`
- `scripts/local-smoke.php`
- `test-results/`
- `docs/` and `docs/AI_TEAM/` control-plane files
- `public/assets/brand-logo.png` unless a later brand/root decision explicitly needs it.

## SEO/PWA Addendum

SEO Growth role has been added.

Public SEO surface is `/`.

Private app boundary remains:

- `/app.php` has `noindex,nofollow`;
- `/api.php` is disallowed by robots;
- `/storage/` is disallowed by robots.

SEO/PWA deployment now includes:

- public landing metadata and JSON-LD;
- robots/sitemap updates;
- manifest metadata/shortcuts;
- social preview asset references.

Local SEO/PWA non-visual QA:

- HTTP 200 checks passed for `/`, `/app.php`, `robots.txt`, `sitemap.xml`, and `manifest.webmanifest`;
- title, description, canonical, robots meta, JSON-LD, manifest JSON, sitemap XML, and app `noindex` checks passed;
- `node --check public/assets/app.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed.

SEO/PWA production checks:

- HTTP/PWA/private-boundary smoke passed on production;
- mobile `390x844` visual overlap check remains an optional follow-up from this shell because no local Playwright/browser is installed;
- PHP lint remains environment-blocked because local CLI `php` is unavailable.

SEO changes are deployed with private app/API/storage boundaries preserved in the checked HTTP smoke.

## Environment Findings 2026-05-27

- Local `mysql` / `mariadb` CLI is unavailable.
- Local `php` CLI is unavailable.
- Local `ftp` / `lftp` CLI is unavailable.
- `curl` is available and supports FTP/FTPS.
- Network check to production IP:
  - FTP port `21`: reachable;
  - DB port `3306`: not reachable from this environment;
  - HTTP/HTTPS ports `80/443`: reachable.

This means the current environment can potentially transfer files by FTP with `curl`, but cannot perform direct DB preflight, DB backup, or DB migration safely from here.

## Active Background Roles

- No role is authorized to continue feature changes for MVP product scope.
- Project Director owns CEO live review support and rollback watch.
- QA Release Engineer may run optional browser visual matrix on production.

## Director Position

Do not deploy the entire dirty working tree blindly.

Default deploy mode should be a named MVP runtime bundle unless the CEO explicitly accepts a full dirty-tree deployment.

Do not copy credentials into docs or reports.

## Current Local Checks

- `node --check public/assets/app.js`: passed on 2026-05-27.
- `curl http://127.0.0.1:18889/api.php?action=current_user`: returned `{"ok":true,"user":null}` on 2026-05-27.
- CLI PHP remains unavailable in this shell, so CLI smoke remains environment-blocked unless the environment changes.
- Production files/storage backup: completed by read-only FTP, archive `backups/prod-files-before-mvp-20260527T185902Z.tgz`, checksum `b095d4c6c8cf35ac0fbc76657fd7653d4757596b58944c81a13325122b1c8823`.
- Production reachability recheck: HTTPS root returns `200`; local `php`, `mysql`, and `mariadb` CLIs are unavailable; production DB port `3306` times out from this environment.
- Production business smoke passed: smoke id `20260527192655`, group id `4`, final report id `20`.

## Next Stop/Go Decision

Current stop/go decision:

- Product gate: approved.
- Deploy package: selected, locally packaged, and deployed as `findesk-mvp-runtime-20260527T192800Z`.
- Production upload: completed.
- Production smoke: passed for HTTP/API MVP path.
- Next required operation: CEO live review on real mobile device; optional QA browser visual matrix when available.
