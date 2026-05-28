# Production Go / No-Go

Date: 2026-05-27

Owner: Project Director

Status: GO / deployed for MVP production use.

## Product Gate

Status: GO.

Chief Auditor approved the full business-MVP product gate.

Accepted product evidence includes:

- Foundation MVP gate;
- Field Combat no-data-loss gate;
- Closed group report package gate;
- QA residual surface pass `20260527968710`;
- Product/Frontend/QA final readiness pass;
- Backend/Data found no known backend/API P0 for business-MVP product readiness.

## Production Gate

Status: GO after controlled deploy and smoke.

Completed controls:

- production files/storage backup completed;
- production DB backup completed;
- production DB engine/schema preflight completed;
- runtime SQL applied and verified;
- narrow runtime package uploaded;
- production HTTP/API smoke passed;
- rollback source exists in local file and DB backups.

Production deploy report:

- `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`

Remaining non-blocking follow-up:

- production browser visual matrix was not executed from this shell because no browser automation runtime is available here.

Backup controls:

- production files/storage backup completed by read-only FTP download:
  - backup id: `prod-files-before-mvp-20260527T185902Z`;
  - archive: `backups/prod-files-before-mvp-20260527T185902Z.tgz`;
  - checksum: `b095d4c6c8cf35ac0fbc76657fd7653d4757596b58944c81a13325122b1c8823`;
  - record: `docs/AI_TEAM/25_PRODUCTION_FILE_BACKUP_2026-05-27.md`.
- production DB backup before runtime migration:
  - archive: `backups/prod-db-before-mvp-20260527T191800Z/findesk-db-before-mvp-20260527T191800Z.sql.gz`;
  - checksum: `97141a46924adcebd4fdcaeeecf87429d089a5147558c4ad2e72aa535b97cb3f`.
- production DB backup before schema hardening:
  - archive: `backups/prod-db-before-mvp-hardening-20260527T192400Z/findesk-db-before-hardening-20260527T192400Z.sql.gz`;
  - checksum: `352face59681afaae1d278bc8a02140991473efbff1f78256f39d7bbbe87f50f`.

## Director Deploy Package Decision

Use narrow MVP runtime bundle, not full dirty-tree deploy.

Final release artifact is now built and deployed:

- release id: `findesk-mvp-runtime-20260527T192800Z`;
- artifact: `backups/findesk-mvp-runtime-20260527T192800Z/findesk-mvp-runtime-20260527T192800Z.tar.gz`;
- checksum: `c4e1a79d1bd8091aa21bd7ac21c685c95f1389d62bf572d0ba98b481ccb4f7f4`;
- record: `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`.

This closes deploy package assembly and production upload for the checked MVP path.

Selected package candidate:

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
- `.htaccess`

`deploy/on_the_go_sessions_runtime.sql` is a database migration input, not a public web file upload.

## Explicit Scope Decisions

- `app/ai.php` is included because current `public/api.php` requires it. It is accepted as `Advanced` / staged surface, not as money-core formula logic.
- `public/service-worker.js` is included because the frontend registers it and the new version clears old `findesk-*` caches.
- `public/assets/brand-logo.png` is excluded because it is not referenced by the selected runtime package.
- `public/reset-local.php`, `scripts/start-local.sh`, `scripts/local-smoke.php`, `test-results/`, and project docs are excluded from production runtime.
- SEO/PWA public-surface files are included in the selected package candidate after local non-visual QA.

## Environment Finding

From the current local environment:

- FTP port `21` on production IP is reachable.
- DB port `3306` on production IP is not reachable.
- Local MySQL/MariaDB/PHP CLI tools are unavailable.

Therefore direct production DB preflight, DB backup, and migration are blocked from this environment.

## Smoke Result

Production smoke passed:

- technical HTTP/PWA/private-boundary smoke: passed;
- business MVP API smoke id: `20260527192655`;
- smoke group id: `4`;
- final report id: `20`.

## Next Required Work

- CEO live review on real mobile device.
- Optional QA browser visual matrix on production when a browser automation runtime is available.
