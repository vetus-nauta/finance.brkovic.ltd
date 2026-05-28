# SEO Growth Engineer Status

## Latest Status 2026-05-27

Role: SEO Growth Engineer
Task: Strategic SEO/Growth strategy for FinDesk PWA in 2026.
Status: DONE for strategy and role cabinet; no application code changed.

Changed files:

- `docs/AI_TEAM/roles/06_seo_growth_engineer/ROLE.md`
- `docs/AI_TEAM/roles/06_seo_growth_engineer/STATUS.md`
- `docs/AI_TEAM/roles/06_seo_growth_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/06_seo_growth_engineer/TASKS_TO_OTHERS.md`
- `docs/AI_TEAM/roles/06_seo_growth_engineer/REPORTING_RULES.md`
- `docs/AI_TEAM/21_SEO_GROWTH_STRATEGY.md`

## Inputs Read

- `public/index.php`
- `public/app.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/20_LANGUAGE_POLICY_AUDIT.md`

## Current Product Context

- Business MVP product gate is approved.
- Production deploy remains NO-GO until database, backup, rollback, package selection, and production smoke controls are completed.
- Public growth must promote the public website/PWA surface.
- The authenticated finance app remains private and noindex.
- Default/fallback interface language is English.
- CEO chat reports stay short and Russian-only.

## Current SEO Position

- `/` has server-rendered HTML, title, description, canonical, Open Graph/Twitter image, manifest link, install buttons, and a small public value proposition.
- `/app.php` has `<meta name="robots" content="noindex,nofollow">`.
- `robots.txt` disallows `/app.php`, `/api.php`, and `/storage/`; sitemap points to `/sitemap.xml`.
- `sitemap.xml` contains only the root URL.
- `manifest.webmanifest` starts the installed PWA at `/app.php`, has `id`, `name`, `short_name`, `description`, `start_url`, `scope`, `display`, categories, and 192/512/maskable icons.
- Public page has no `hreflang`, no JSON-LD structured data, no trust/legal/privacy pages in sitemap, and only thin public body copy.
- Private app shell contains many authenticated workflows and mixed hardcoded languages; this is acceptable for app UX work but must not become public SEO content.

## Blocker

No blocker for documentation strategy.

Implementation is blocked only by role boundary: SEO changes in application files must be executed by Frontend/Backend/QA/Product through assigned tasks, and production deployment remains blocked by DB/backup controls.

## Next Owner

Product Director for prioritization, then Frontend UX Engineer, Backend Data Engineer, and QA Release Engineer for implementation and verification.
