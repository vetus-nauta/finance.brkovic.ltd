# MVP Runtime Artifact

Date: 2026-05-27

Owner: Project Director

Status: superseded by final production artifact `findesk-mvp-runtime-20260527T192800Z`.

Final production deploy record:

- `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`

## Artifact

Release id:

- `findesk-mvp-runtime-20260527T185423Z`

Local artifact:

- `backups/findesk-mvp-runtime-20260527T185423Z/findesk-mvp-runtime-20260527T185423Z.tar.gz`

Checksum:

- `0bf15e78f3e17f4d40f7444fe92213d2cee9f6335712eee7851f294563d96dfc`

File list:

- `backups/findesk-mvp-runtime-20260527T185423Z/file-list.txt`

## Included Files

- `public/api.php`
- `app/auth.php`
- `app/groups.php`
- `app/ledger.php`
- `app/on_the_go.php`
- `app/advances.php`
- `app/ai.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `public/app.php`
- `public/index.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`
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

## Explicit Decisions Captured In Artifact

- `app/ai.php` is included because current `public/api.php` requires it before routing. It remains Advanced/staged surface, not a money-core MVP formula.
- `public/service-worker.js` is included because the app registers it and it clears old `findesk-*` caches. Rollback must account for browser cache/service-worker behavior.
- `public/index.php`, robots, sitemap, manifest, and icon/OG assets are included because SEO/PWA public surface is in the selected release package.
- `deploy/on_the_go_sessions_runtime.sql` is included in the artifact as migration input, but it is not a web upload file.

## Excluded From Artifact

- `docs/`
- `docs/AI_TEAM/`
- `scripts/`
- `public/reset-local.php`
- `test-results/`
- `storage/`
- `backups/`
- `public/assets/brand-logo.png`
- local config files

## Local Verification

Passed:

- all selected files exist;
- `node --check public/assets/app.js`;
- `node --check public/service-worker.js`;
- manifest JSON parse;
- sitemap XML parse;
- artifact content matches `file-list.txt`;
- artifact checksum verified;
- artifact exclusion grep for docs/scripts/reset/test/storage/config returned clean;
- artifact secret-name scan returned clean.

Environment-blocked:

- PHP CLI lint/smoke remains blocked because `php` CLI is not installed in this shell.
- Direct production DB preflight remains blocked because DB port is not reachable and local MySQL/MariaDB CLI is unavailable.

## Production Gate

This artifact does not authorize upload.

This artifact was superseded before final production deploy by:

- `backups/findesk-mvp-runtime-20260527T192800Z/findesk-mvp-runtime-20260527T192800Z.tar.gz`
- checksum `c4e1a79d1bd8091aa21bd7ac21c685c95f1389d62bf572d0ba98b481ccb4f7f4`

Production file/storage backup is now complete:

- backup id: `prod-files-before-mvp-20260527T185902Z`;
- record: `docs/AI_TEAM/25_PRODUCTION_FILE_BACKUP_2026-05-27.md`.

Upload remains NO-GO until:

- production DB backup is complete;
- production DB engine/schema preflight is complete;
- `deploy/on_the_go_sessions_runtime.sql` is applied or proven unnecessary/compatible;
- rollback owner and smoke owner are named;
- production smoke window and test-data policy are approved.
