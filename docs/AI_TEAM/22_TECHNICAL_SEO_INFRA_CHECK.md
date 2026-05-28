# Technical SEO / PWA Infra Check

Date: 2026-05-27

Owner: Backend / Infra SEO Engineer

Status: SEO/infra check completed; production deploy remains NO-GO under `19_PRODUCTION_GO_NO_GO_2026-05-27.md`.

## Scope

Read:

- `public/index.php`
- `public/app.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `public/api.php`
- `docs/AI_TEAM/19_PRODUCTION_GO_NO_GO_2026-05-27.md`

Additional infra context read:

- `.htaccess`
- `public/assets/app.js` service-worker registration and API URL usage
- `app/auth.php` JSON/cookie header boundary

No production action was executed.
No application code was changed.
No credentials were read or documented.

## Robots / Noindex Boundary

Current state:

- `public/robots.txt` allows the public site root and disallows:
  - `/app.php`
  - `/api.php`
  - `/storage/`
- `public/app.php` includes `<meta name="robots" content="noindex,nofollow">`.
- `public/sitemap.xml` lists only `https://finance.brkovic.ltd/`.
- `public/api.php` is not in the sitemap and returns JSON through `ql_json()` with `Content-Type: application/json; charset=utf-8`.
- `.htaccess` has `Options -Indexes` and `RedirectMatch 403 ^/(app|storage|deploy|cron)(/|$)`, which blocks `/storage/` if production serves through this root config.

Risk / required production control:

- Robots `Disallow` prevents crawling, but it is not a confidentiality control and does not by itself authenticate `/app.php` or `/api.php`.
- For `/app.php`, `/api.php`, and any storage response, add or verify production `X-Robots-Tag: noindex, nofollow, noarchive` as defense in depth.
- Keep `/storage/` non-public by routing/auth/403, not by robots only.
- Verify the real production document root honors the `.htaccess` storage block or has an equivalent nginx/hosting rule.

## Canonical / Sitemap Consistency

Pass:

- `public/index.php` canonical is `https://finance.brkovic.ltd/`.
- `public/index.php` OG URL is `https://finance.brkovic.ltd/`.
- `public/robots.txt` references `https://finance.brkovic.ltd/sitemap.xml`.
- `public/sitemap.xml` contains only `https://finance.brkovic.ltd/`.
- `/app.php`, `/api.php`, and `/storage/` are not in the sitemap.

Follow-up:

- Production smoke should verify `/`, `/robots.txt`, and `/sitemap.xml` return 200 on the public host.
- If `/index.php` is externally reachable, keep the canonical to `/`; a redirect from `/index.php` to `/` is optional hardening, not a current release blocker.

## Service Worker / Cache / Rollback

Current state:

- `public/service-worker.js` only handles `install` and `activate`.
- It calls `self.skipWaiting()` and `self.clients.claim()`.
- It deletes old `findesk-*` caches except `findesk-20260522-v134`.
- There is no `fetch` handler in the inspected service worker.
- `public/assets/app.js` registers `/service-worker.js` outside localhost and unregisters/clears `findesk-*` caches on localhost.

SEO risk:

- Current service worker does not serve cached HTML, robots, sitemap, manifest, or assets. Stale SEO HTML risk from SW fetch interception is therefore low in the current file.

Rollback risk:

- `skipWaiting()` and `clients.claim()` make a newly uploaded worker take control quickly.
- Cache deletion is one-way for old `findesk-*` cache entries.
- Rollback plan must include active service-worker verification, browser hard reload/private-window smoke, and a cache-version decision if the worker changes again.

Future rule:

- If a future service worker adds a `fetch` handler, exclude `/robots.txt`, `/sitemap.xml`, `/manifest.webmanifest`, `/api.php`, `/app.php`, and dynamic HTML/API responses from stale cache or use network-first/no-store behavior.

## Production Headers Needed

Current repository evidence:

- `public/index.php` sends `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` and `Pragma: no-cache`.
- `public/app.php` sends the same no-store/no-cache headers.
- `.htaccess` sets `AddType application/manifest+json .webmanifest`.
- No explicit repository rule was found for `robots.txt` as `text/plain`.
- No explicit repository rule was found for `sitemap.xml` as `application/xml` or `text/xml`.
- No explicit repository rule was found for service-worker cache policy.
- `ql_json()` sets JSON content type, but `/api.php` does not globally add no-store or X-Robots headers in the inspected code.

Required production verification / config:

- `/manifest.webmanifest`: `Content-Type: application/manifest+json; charset=utf-8`; cache short/no-cache during rollout.
- `/robots.txt`: `Content-Type: text/plain; charset=utf-8`; cache short/no-cache during rollout.
- `/sitemap.xml`: `Content-Type: application/xml; charset=utf-8` or `text/xml; charset=utf-8`; cache short/no-cache during rollout.
- `/service-worker.js`: JavaScript content type and `Cache-Control: no-cache` or `max-age=0, must-revalidate`.
- `/`: no-store/no-cache is already set by PHP; verify production does not override it.
- `/app.php`: preserve no-store/no-cache and add/verify `X-Robots-Tag: noindex, nofollow, noarchive`.
- `/api.php`: add/verify `Cache-Control: no-store` and `X-Robots-Tag: noindex, nofollow, noarchive`.
- Versioned assets such as `/assets/app.css?v=...` and `/assets/app.js?v=...`: may use longer browser cache only if rollout/rollback instructions account for query-version changes.
- Add/verify `X-Content-Type-Options: nosniff` globally.

## Search Console / Bing Webmaster

Required later, not now:

- Verification must be done by an authorized owner without credentials in repository/docs.
- Acceptable later options:
  - domain-level DNS verification by the domain owner;
  - static HTML verification file added through an explicit deploy task;
  - temporary verification meta tag in `public/index.php` through an explicit deploy task.
- Do not add Search Console/Bing tokens to the current package without owner approval.

## Analytics / Measurement Boundary

Current state:

- No Google Analytics, gtag, Pixel, PostHog, Mixpanel, Plausible, Sentry, or similar measurement snippet was found in inspected public files.
- Main API calls use `/api.php?action=...`; finance payloads are sent in POST JSON/FormData, not as finance amounts in URL query strings.
- Session token is set as an HttpOnly same-site cookie in `app/auth.php`.
- Google Sheets export opens `https://docs.google.com/spreadsheets/u/0/create` without embedding finance data in the URL; the user pastes copied report data manually.

Risk / rule for later analytics:

- `/app.php?invite=<token>` is a real invite-token URL. The app removes it after join, but it can exist before login/join.
- Any future analytics must strip query strings and must not collect full URLs for `/app.php`, `/api.php`, or invite/share pages.
- Do not send finance amounts, report names, proof filenames, group names, emails, invite tokens, auth codes, API response bodies, or storage URLs to analytics.
- Donation or third-party scripts must load only after user action and must not receive app state, finance context, invite tokens, or authenticated API payloads.

## Deploy Package / NO-GO Impact

Production NO-GO remains unchanged:

- DB backup is not confirmed.
- Production files/storage backup is not confirmed.
- DB engine/schema preflight is not executed.
- Runtime SQL proof/application is not complete.
- Rollback owner/evidence and production smoke owner/evidence are not confirmed.

SEO/PWA package finding:

- `19_PRODUCTION_GO_NO_GO_2026-05-27.md` selected package includes `public/index.php`, `public/app.php`, `public/service-worker.js`, public assets and icons, but does not list `public/robots.txt`, `public/sitemap.xml`, or `public/manifest.webmanifest`.
- If the SEO/PWA public surface is part of the release, deploy owner must either add those files to the selected public package or verify production already has byte-equivalent current versions.
- This is a package/smoke addendum only; it does not convert production to GO.

## Verification Performed Locally

- `node` JSON parse passed for `public/manifest.webmanifest`.
- `xmllint --noout public/sitemap.xml` passed.
- `node --check public/service-worker.js` passed.
- `node --check public/assets/app.js` passed.

## Final Classification

- Technical SEO boundary: PASS with production header/storage verification required.
- PWA service-worker SEO cache risk: LOW in current file because there is no fetch handler.
- Production deploy: still NO-GO.
- Next owner: Project Director / Deploy Owner for package addendum and header rules; QA Release Engineer for post-upload SEO/PWA smoke after production action is approved.
