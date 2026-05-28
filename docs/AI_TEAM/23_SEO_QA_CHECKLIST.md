# SEO/PWA QA Checklist

Date: 2026-05-27

Owner: QA Release Engineer

Status: checklist prepared; execution pending Frontend/PWA SEO implementation. Production smoke was not executed.

## Scope

This checklist covers the public SEO/PWA surface for FinDesk:

- public index: `/` and `public/index.php`;
- public SEO files: `robots.txt`, `sitemap.xml`, manifest, service worker;
- visible public landing content, hidden meta layers, JSON-LD, PWA install, social preview assets, and mobile rendering.

Boundary:

- `/app.php` must remain the PWA app entry and must remain `noindex,nofollow`;
- `/api.php` and `/storage/` must remain blocked from crawl;
- no authenticated app content, API data, storage directory listing, customer data, or app-only route belongs in sitemap or public JSON-LD.

This document does not authorize application-code changes or production smoke execution.

## Source Baseline Read

Read on 2026-05-27:

- `public/index.php`
- `public/app.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `docs/AI_TEAM/20_LANGUAGE_POLICY_AUDIT.md`
- `docs/AI_TEAM/18_PRODUCTION_SMOKE_RUNBOOK.md`

Current local evidence before the next SEO implementation:

- `public/index.php` has viewport, title, description, canonical `https://finance.brkovic.ltd/`, manifest link, OG/Twitter image links, and visible FinDesk public content.
- `public/index.php` currently has no `application/ld+json` script found by source search. After the new SEO work, parseable JSON-LD is a must-pass item unless Project Director explicitly removes it from scope.
- `public/app.php` has `<meta name="robots" content="noindex,nofollow">`.
- `public/robots.txt` allows `/`, disallows `/app.php`, `/api.php`, `/storage/`, and points to `https://finance.brkovic.ltd/sitemap.xml`.
- `public/sitemap.xml` currently contains only `https://finance.brkovic.ltd/`.
- `public/manifest.webmanifest` starts the installed PWA at `/app.php`, uses scope `/`, display `standalone`, and references 192/512/maskable icons.
- `public/service-worker.js` uses cache name `findesk-20260522-v134` and deletes old `findesk-*` caches during activation.
- Local social/PWA image files exist: `brand-og.png` is `1200 x 630`, `brand-mark.png` is `128 x 128`, app icons are `180/192/512`, and favicon files exist.

## Local QA After SEO Implementation

Run this locally after Frontend/PWA SEO Engineer finishes the SEO change. Record actual results in QA findings before handing to production deploy.

### 1. Public HTTP And Visible Page

Must pass:

- `/` returns HTTP 200 from the local public document root.
- `/index.php` returns HTTP 200 or the same public root behavior.
- Public page does not redirect to `/app.php`.
- Page is not blank and has visible brand/product signal: FinDesk, public copy, Open App action, Install action.
- Public page does not show authenticated app modules, private data, API JSON, stack traces, or debug output.
- Default visible public copy remains English unless Product Director approved localized public SEO copy. This aligns with `20_LANGUAGE_POLICY_AUDIT.md`: English is fallback, Russian is supported but not global fallback.

Suggested evidence:

- HTTP status for `/`;
- screenshot at desktop and mobile;
- source excerpt for title/meta/canonical;
- browser console check for fatal errors.

### 2. Hidden Meta Layer

Must pass in raw HTML source, without relying on client-side rendering:

- exactly one useful `<title>` for the public root;
- non-empty `<meta name="description">`;
- canonical is exactly `https://finance.brkovic.ltd/` for production intent, with trailing slash, no localhost, no `/app.php`, no `/index.php`;
- no root-level `<meta name="robots" content="noindex">` unless Project Director explicitly freezes public indexing;
- `<html lang="en">` or approved public language value is present;
- viewport meta is present and supports mobile layout;
- manifest link is present;
- theme color and Apple PWA meta are present if PWA install remains in scope;
- Open Graph and Twitter title/description/image are present and point to public brand assets with absolute production URLs.

Fail if:

- canonical points to local, staging, `/app.php`, `/api.php`, `/storage/`, or wrong host;
- duplicate conflicting canonical/title/description tags exist;
- public root is accidentally noindexed.

### 3. JSON-LD

Must pass after SEO implementation:

- at least one `<script type="application/ld+json">` exists on `/`;
- every JSON-LD block parses with `JSON.parse`;
- schema identifies FinDesk and `https://finance.brkovic.ltd/`;
- schema type is appropriate for the public product, for example `WebApplication`, `SoftwareApplication`, `Organization`, or an approved graph containing those entities;
- JSON-LD does not expose user emails, group ids, report ids, API URLs, storage paths, auth-only routes, or private product internals;
- JSON-LD canonical URL agrees with the HTML canonical.

Suggested browser console check:

```js
[...document.querySelectorAll('script[type="application/ld+json"]')]
  .map((node) => JSON.parse(node.textContent));
```

Fail if any JSON-LD block is absent, invalid JSON, points to the wrong canonical, or contains private/authenticated data.

### 4. PWA Install And Manifest

Must pass:

- `/manifest.webmanifest` returns HTTP 200 and valid JSON.
- Manifest fields remain coherent:
  - `id`: `/app.php`
  - `start_url`: `/app.php`
  - `scope`: `/`
  - `display`: `standalone`
  - `name` and `short_name` are non-empty and brand-correct.
- Manifest icon URLs return HTTP 200:
  - `/assets/icon-192.png`
  - `/assets/icon-512.png`
  - `/assets/icon-maskable-512.png`
- Root and app pages link to the manifest.
- Install buttons on the public page open the expected platform install guidance/modal.
- In a browser that supports install prompts, PWA eligibility has no manifest/icon errors.
- Installed PWA launches `/app.php`, and `/app.php` still has `noindex,nofollow`.

Fail if:

- manifest is invalid JSON;
- required icon fetch returns 404/500;
- installed app opens the wrong URL;
- PWA install path removes the `noindex` boundary from the app.

### 5. Robots, Sitemap, And Noindex Boundary

Must pass:

- `robots.txt` returns HTTP 200.
- `robots.txt` allows public `/`.
- `robots.txt` disallows:
  - `/app.php`
  - `/api.php`
  - `/storage/`
- `robots.txt` contains sitemap URL `https://finance.brkovic.ltd/sitemap.xml`.
- `sitemap.xml` returns HTTP 200 and valid XML.
- Sitemap contains only public canonical URLs intended for indexing.
- Sitemap does not contain `/app.php`, `/api.php`, `/storage/`, test URLs, localhost, staging, or query-string app state.
- `/app.php` source includes `noindex,nofollow`.
- `/api.php` and `/storage/` are not crawlable public HTML surfaces and are not linked from sitemap/JSON-LD as indexable content.
- Storage directory listing is disabled or otherwise not exposed as a crawlable index.

Fail if:

- app URL is indexable through sitemap, canonical, public links marked as SEO URLs, or missing noindex/robots boundary;
- API or storage becomes crawlable/indexable;
- sitemap contains non-public or authenticated app paths.

### 6. Mobile Rendering

Mandatory local viewport:

- mobile `390 x 844`.

Recommended extra viewports:

- small mobile `360 x 800`;
- tablet `820 x 1180`;
- desktop `1440 x 900`.

Must pass:

- public page is not blank;
- FinDesk brand signal, primary CTA, install action, and core public copy are visible/reachable;
- no horizontal scroll caused by SEO/PWA additions;
- no modal/install guidance overflow blocks close/action buttons;
- meta/social/PWA additions do not delay or break visible rendering;
- `/app.php` remains usable when opened from the public CTA.

Fail if:

- public page is empty or visually broken on mobile;
- CTAs are unreachable;
- install modal cannot be closed or read on mobile.

### 7. Service Worker And Cache Update

Must pass after any frontend asset, manifest, public index, icon, or social asset change:

- `/service-worker.js` returns HTTP 200.
- `CACHE_NAME` is bumped when cache-sensitive frontend/PWA assets changed.
- On production-like HTTPS, active service worker updates after reload/reopen.
- Old `findesk-*` caches are removed during activation.
- Returning PWA/browser session sees the new public title/meta/manifest/icons/assets after hard reload or reopen.
- Versioned asset query strings are updated if the underlying referenced files changed.

Current baseline note: service worker cache name is `findesk-20260522-v134`; it has install/activate cleanup and no fetch handler in the inspected file. QA still needs to verify stale client behavior after deploy because an existing installed PWA can hold old assets until update/reload.

Fail if:

- service worker syntax/runtime error blocks activation;
- old cached public assets keep showing after hard reload/reopen;
- cache version was not changed while deployed public assets changed.

### 8. Social Preview And Brand Assets

Must pass locally and on production smoke:

- OG image URL from HTML returns HTTP 200.
- Twitter image URL from HTML returns HTTP 200.
- `brand-og.png` is present and suitable for large preview. Current local asset is `1200 x 630`.
- `brand-mark.png` used in public/app markup returns HTTP 200.
- favicon and Apple touch icon URLs return HTTP 200.
- manifest icons return HTTP 200 and have expected dimensions.
- If a social/brand image changed, URL versioning or cache invalidation is recorded.

Fail if:

- any referenced brand/social/PWA icon is missing;
- OG/Twitter image points to localhost, staging, wrong host, or stale non-existent path;
- image dimensions are incompatible with the intended preview.

## Production Smoke Additions For SEO/PWA

Do not run these until Deploy Owner provides production upload, production URL, package/file-list evidence, backup evidence, and rollback evidence required by `18_PRODUCTION_SMOKE_RUNBOOK.md`.

Add these checks to the production smoke, without replacing the existing 100% MVP smoke.

### A. HTTP 200

Must record production HTTP status for:

- `/`
- `/index.php` if directly reachable
- `/robots.txt`
- `/sitemap.xml`
- `/manifest.webmanifest`
- `/service-worker.js`
- `/app.php`
- `/assets/brand-og.png`
- `/assets/brand-mark.png`
- `/assets/apple-touch-icon.png`
- `/assets/icon-192.png`
- `/assets/icon-512.png`
- `/assets/icon-maskable-512.png`
- `/favicon.ico` and/or `/assets/favicon.ico`

Must pass:

- public SEO/PWA files and assets return HTTP 200;
- `/app.php` may return HTTP 200 but must remain `noindex,nofollow`;
- API/storage status must not create a crawlable public index.

### B. Title, Description, Canonical

Must pass in production raw HTML source for `/`:

- public title is present and brand-correct;
- public description is present and non-empty;
- canonical is exactly `https://finance.brkovic.ltd/`;
- canonical is not local/staging, not `/app.php`, not `/index.php`, and not duplicated with conflicting values.

### C. JSON-LD Parse Presence

Must pass:

- at least one JSON-LD script exists on `/`;
- all JSON-LD scripts parse successfully;
- parsed schema contains FinDesk public identity and canonical production URL;
- no private app/user/report/API/storage data is exposed.

### D. Robots

Must pass:

- `robots.txt` allows `/`;
- `robots.txt` disallows `/app.php`, `/api.php`, `/storage/`;
- `robots.txt` points to `https://finance.brkovic.ltd/sitemap.xml`;
- no deployed robots rule blocks the public root unless Project Director intentionally freezes indexing.

### E. Sitemap

Must pass:

- sitemap is valid XML;
- sitemap URLs return HTTP 200;
- sitemap contains public canonical URLs only;
- sitemap excludes `/app.php`, `/api.php`, `/storage/`, test URLs, localhost, staging, and private query states.

### F. Manifest

Must pass:

- manifest returns valid JSON;
- `name`, `short_name`, `id`, `start_url`, `scope`, `display`, `theme_color`, and icons are present;
- start URL remains `/app.php`;
- all manifest icons return HTTP 200;
- browser Application/Manifest panel shows no install-blocking manifest errors.

### G. Noindex App Boundary

Must pass:

- `/app.php` production source contains `noindex,nofollow`;
- `/app.php` is not in sitemap;
- `/app.php` is disallowed in robots;
- `/api.php` is disallowed in robots and is not represented as indexable HTML;
- `/storage/` is disallowed in robots and directory listing is not exposed;
- public JSON-LD/canonical/social tags do not point crawlers into app/API/storage.

### H. Mobile Viewport

Must pass at mobile `390 x 844`:

- `/` is visible, not blank, and primary public CTA is usable;
- install guidance/modal is usable and closeable;
- `/app.php` opened from CTA remains usable enough to reach the normal app shell/login path;
- no horizontal scroll or blocking overlap appears from SEO/PWA additions.

### I. Service Worker Cache Update

Must pass:

- production `/service-worker.js` is the uploaded version expected by Deploy Owner;
- service worker activates without console errors;
- old `findesk-*` caches are removed after activation;
- returning browser/PWA session sees the new public title/meta/manifest/icons/assets after hard reload/reopen;
- if rollback happens, service-worker/cache cleanup instructions are followed before retesting.

### J. Social Preview Assets

Must pass:

- `og:image` and `twitter:image` production URLs return HTTP 200;
- image host is `finance.brkovic.ltd`;
- OG image remains suitable for preview, with current expected local baseline `1200 x 630`;
- brand mark, favicon, Apple touch icon, and manifest icons return HTTP 200;
- no missing brand asset appears in browser network panel.

## SEO/PWA Stop Criteria

Stop local release acceptance or production smoke immediately and mark status `FAIL - SEO/PWA rollback review required` if any item occurs:

- app indexed accidentally: `/app.php` loses `noindex,nofollow`, appears in sitemap, is allowed by robots, or is used as canonical public SEO URL;
- API/storage open to crawl: `/api.php` or `/storage/` appears in sitemap/JSON-LD as indexable content, robots no longer blocks them, or storage exposes directory listing;
- canonical wrong: canonical points to localhost, staging, `http://`, wrong host, `/app.php`, `/api.php`, `/storage/`, `/index.php`, or conflicting duplicate canonical exists;
- empty public page: `/` returns HTTP 200 but renders blank/empty, lacks visible FinDesk brand signal, or primary CTA is unusable;
- broken manifest: manifest is invalid JSON, missing required PWA fields, opens the wrong start URL, or referenced icons fail;
- missing brand assets: OG/Twitter image, brand mark, favicon, Apple touch icon, or manifest icons are missing, return non-200, or are corrupted.

Mark status `BLOCKED` instead of `FAIL` if production smoke cannot start because production URL, package evidence, backup evidence, rollback owner, or safe smoke window is missing.

## Evidence To Record

For local QA after implementation:

- changed SEO/PWA files reviewed;
- local URL/port used;
- HTTP statuses for public files/assets;
- source checks for title/description/canonical/robots/noindex/JSON-LD;
- JSON-LD parse result;
- manifest parse result and icon checks;
- mobile screenshots or viewport notes;
- service-worker/cache version observation;
- final PASS/FAIL/BLOCKED status.

For production smoke additions:

- production URL and timestamp/timezone;
- deployed package/file-list reference;
- HTTP status table;
- raw source evidence for title/description/canonical/noindex;
- JSON-LD parse evidence;
- robots/sitemap validation;
- manifest/icon evidence;
- mobile viewport screenshot/note;
- service-worker/cache update evidence;
- social asset evidence;
- any stop criterion and rollback/hold decision.
