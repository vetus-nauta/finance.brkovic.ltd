# FinDesk SEO/Growth Strategy 2026

Date: 2026-05-27

Owner: SEO Growth Engineer

Status: strategy ready; implementation requires Product/Frontend/Backend/QA tasks. Production deploy remains separate and NO-GO until DB/backup/rollback controls and production smoke are approved.

## Strategic Position

FinDesk SEO in 2026 must not try to rank a private finance app shell. The public site should rank and convert; the authenticated app should remain private.

The strategy is:

```text
public trust + clear use cases + install confidence -> app entry -> private finance work
```

Search engines, AI search features, and cautious users need visible public evidence:

- what FinDesk does;
- who operates it;
- who it is for;
- what problems it solves;
- what remains private;
- how the PWA is installed;
- why the product is trustworthy enough to try.

## Current Surface

Observed in local files:

- `/` is public and indexable through `public/index.php`.
- `/` has server-rendered HTML, title, description, canonical, OG/Twitter image, manifest link, app/install CTAs, and thin public copy.
- `/app.php` has `noindex,nofollow`.
- `robots.txt` disallows `/app.php`, `/api.php`, and `/storage/`.
- `sitemap.xml` lists only `https://finance.brkovic.ltd/`.
- `manifest.webmanifest` starts installed PWA at `/app.php`.
- Default/fallback language policy is English.
- No public `hreflang`, no public structured data, no public trust/privacy/use-case pages yet.

## 2026 Reality Check

- AI search features do not require special hidden optimization. They require the same fundamentals: indexable helpful pages, technical eligibility, snippets, and people-first content.
- Thin app shells are weak. Public pages need server-rendered, visible content that explains the product without requiring login.
- Finance is trust-sensitive. A brand without a trust/privacy/operator page will under-convert even if it ranks.
- PWA install is a growth surface, but not a substitute for public SEO. The install flow needs clear device-specific guidance and measurement.
- Multilingual SEO must use real localized public URLs and visible translated main content. Translated app UI alone is not international SEO.
- Privacy is part of SEO quality for this product. Any leak of finance routes, proof files, reports, invites, or private identifiers is a P0 failure.

## 1. SEO For The Public Landing

Goal: make `/` a strong, crawlable, conversion-oriented public entry point.

Required:

- Keep English as the default root language.
- Expand visible server-rendered copy for:
  - FinDesk one-line value proposition;
  - three layers: On the Go, FinDesk, Advanced;
  - mobile field capture with proof/photo;
  - group report review and archive;
  - cash/card/accountable money separation;
  - Business Desk/proforma as a separate business tool;
  - privacy boundary: public site vs private app;
  - operator/trust signal: brkovic.ltd.
- Keep canonical root as `https://finance.brkovic.ltd/`.
- Use clear headings, descriptive paragraphs, and crawlable internal links.
- Avoid keyword stuffing and broad claims that the product cannot prove.

Public landing should answer:

- What is FinDesk?
- Who is it for?
- What problem does it solve better than a spreadsheet/chat/photo mess?
- What happens after clicking Open App?
- What is private and not indexed?
- How can I install it as a web app?

## 2. App Store / PWA Install Surface

Goal: turn PWA installation into a measured growth funnel without pretending it is a native store listing.

Current base is good:

- manifest exists;
- install buttons exist;
- `beforeinstallprompt` handling exists;
- iOS/Android/desktop instructions exist;
- installed app starts at `/app.php`.

Strategy:

- Public install page or section: `/install/` or an expanded root section.
- Device-specific install copy:
  - iOS/iPadOS Safari: Add to Home Screen path;
  - Android Chrome: install prompt or browser menu;
  - desktop Chrome/Edge: install icon/menu.
- Keep manual fallback instructions because `beforeinstallprompt` is not universal.
- Measure only public/install events:
  - install help opened;
  - platform tab selected;
  - native prompt available;
  - prompt accepted/dismissed;
  - installed app launch source as coarse channel.
- QA every deploy:
  - manifest 200;
  - icons 200 and correct dimensions;
  - app starts at `/app.php`;
  - service worker is current;
  - returning installed session is not stale.

Do not:

- claim App Store / Play Store availability unless real listings exist;
- collect finance activity in install analytics;
- cache public or app shell in a way that prevents deploy updates.

## 3. Multilingual / International SEO

Goal: use English fallback now; add international SEO only when real public localized pages exist.

Current policy:

- fallback language: English;
- supported app languages: `ru`, `en`, `de`, `it`, `es`, `sr`, `zh` rendered as `zh-Hans`.

Phased plan:

P0:

- Keep `/` English and canonical.
- Do not add `hreflang` for nonexistent localized URLs.
- Do not auto-redirect crawlers/users away from public root based on browser language.

P1:

- Add public localized pages only when Product approves real translated main content:
  - `/en/`
  - `/de/`
  - `/it/`
  - `/es/`
  - `/sr/`
  - `/zh-Hans/`
  - optional `/ru/`
- Each localized page must have:
  - visible localized main content;
  - self-canonical;
  - reciprocal `hreflang`;
  - English or root as `x-default`;
  - localized title/description/OG where practical.

P2:

- Localize content clusters by demand, not by copying every page mechanically.
- Add country-specific variants only if Product has real country intent, not because a language exists.

## 4. Content Clusters

Goal: build topical authority around actual FinDesk workflows, not generic finance-blog noise.

Core clusters:

1. Field finance capture
   - mobile expense capture;
   - proof/photo/scan;
   - unstable network and draft recovery;
   - field worker workflow.

2. Group finance and report review
   - submitted reports;
   - reviewer acceptance/return;
   - group report consolidation;
   - closed archive.

3. Cash/card/accountable money control
   - physical cash vs noncash;
   - money held by admin vs employees;
   - accountable advances;
   - current period vs closed report.

4. Crew/yacht/project operations
   - yacht/crew expense tracking only where FinDesk genuinely fits;
   - project teams and managers;
   - practical examples with synthetic data.

5. Business Desk/proforma
   - company profile;
   - clients;
   - proforma documents;
   - separation from operational cash reports.

6. PWA install and use
   - install on iPhone/iPad;
   - install on Android;
   - install on desktop;
   - why PWA works for field finance.

7. Trust, privacy, and security
   - what is public;
   - what is private;
   - noindex boundary;
   - operator details;
   - backup/deploy status when approved.

Post-MVP / controlled clusters:

- Travel equalization.
- Advanced analytics.
- AI audit/fraud scoring.
- Native app store wrappers.
- Third-party accounting integrations.

Content rules:

- Write from product truth.
- Use synthetic examples only.
- Do not scrape.
- Do not mass-generate thin pages.
- Do not publish pages for features that are only parked unless the page clearly says they are prepared/planned.

## 5. Technical SEO

P0:

- `/` indexable and crawlable.
- `/app.php`, `/api.php`, `/storage/`, proof/files/invites/private reports remain noindex/private.
- Add or verify `X-Robots-Tag: noindex, nofollow` for private non-public responses where feasible.
- Sitemap contains only canonical public URLs.
- Robots references sitemap.
- Public pages have absolute canonical.
- No soft-404 public app states.

P1:

- Generate sitemap from public route inventory when there are multiple public pages.
- Add accurate `lastmod` only when main content/schema/internal links materially change.
- Add public 404 with correct HTTP 404.
- Keep landing payload small; avoid loading full private app JS for public pages if not needed.
- Monitor Core Web Vitals:
  - LCP <= 2.5s;
  - INP <= 200ms;
  - CLS <= 0.1;
  - measure p75 mobile and desktop.

P2:

- Add public changelog/release notes only if maintained.
- Add CDN/static caching for public assets after deploy process is stable.
- Add public performance budgets by route.

## 6. Schema / Structured Data

Initial schema:

- `Organization` for brkovic.ltd / FinDesk operator facts visible on public page.
- `WebSite` for the public site.
- `SoftwareApplication` or `WebApplication` for FinDesk PWA, with only truthful fields.
- `FAQPage` only if the FAQ is visible on the page.
- `BreadcrumbList` after multi-page public structure exists.

Rules:

- JSON-LD must describe visible public content.
- Do not mark up private app data.
- Do not mark up hidden claims.
- Do not create fake reviews, fake aggregate ratings, fake prices, or fake native app store availability.
- Validate with Rich Results Test and URL Inspection where applicable.

## 7. Privacy / Noindex Boundary

The privacy boundary is a product trust feature and a growth prerequisite.

Indexable:

- public root;
- public trust/privacy/install/use-case/content pages;
- public static brand images intended for previews;
- public docs only if Product explicitly approves publishing them outside repo.

Noindex/private:

- `/app.php`;
- `/api.php`;
- `/storage/`;
- proofs/files/uploads;
- reports and report IDs;
- group/member/invite routes;
- messages;
- user emails;
- finance amounts;
- AI/audit outputs;
- any authenticated workspace.

Controls:

- HTML robots meta where HTML is rendered.
- `X-Robots-Tag` for non-HTML/private responses.
- Auth checks before private object access.
- Sitemap excludes private URLs.
- Robots blocks crawler access to private paths.
- QA crawl smoke checks both public and private examples.

Important nuance:

Robots disallow controls crawling, not guaranteed deindexing. For sensitive private surfaces, use auth plus noindex headers/meta where crawlers can see the response, and never expose public unauthenticated private content.

## 8. Analytics / Measurement Without Breaking Privacy

Growth metrics:

- Search Console impressions/clicks by public URL and query.
- Public landing visits.
- CTA clicks: Open App, Install Web App, Donate.
- Install help opened by platform.
- Native install prompt available/accepted/dismissed where browser exposes it.
- Public page scroll depth or section visibility in aggregate.
- Public page Core Web Vitals.
- Public 404/asset errors.
- Signup/login start count as aggregate event only.

Activation metrics:

- app opened after public page;
- app opened from installed PWA;
- account created or login code requested as aggregate count only;
- no finance details.

Forbidden analytics payloads:

- emails;
- names;
- group names;
- report IDs;
- proof URLs/file names;
- transaction amounts;
- message text;
- invite tokens;
- private route paths with identifiers;
- AI/audit content.

Implementation preference:

- Start with Search Console plus privacy-safe server logs.
- Prefer first-party/cookieless aggregate analytics.
- Add consent only if selected tools use cookies, ads IDs, or cross-site tracking.
- Maintain an analytics event allowlist owned by Product + Backend + QA.

## Typical Failures To Avoid

- Empty SPA/PWA without server-rendered text.
- Indexing the personal account/app area.
- Keyword stuffing.
- Duplicate pages without canonical.
- Incorrect `hreflang`.
- Heavy assets.
- Service-worker stale cache.
- Brand without a trust page.
- Missing sitemap, robots, or structured data.

## P0/P1/P2 Task Matrix

### Frontend UX Engineer

P0:

- Preserve `/app.php` `noindex,nofollow`.
- Expand public root with meaningful server-rendered English content.
- Keep private app JS/data out of public SEO content.
- Make public install path clear and non-misleading.

P1:

- Add public pages for trust/privacy, install, use cases, and content clusters.
- Add structured data for visible public facts.
- Add canonical to all public pages.
- Prepare localized public page templates only after content exists.

P2:

- Add demo/comparison pages with synthetic data.
- Add breadcrumbs and richer public navigation.
- Add install screenshots after QA approves actual flows.

### Backend Data Engineer

P0:

- Verify private routes/files are protected by auth and noindex headers where feasible.
- Serve robots/sitemap/manifest with correct content type.
- Keep sitemap public-only.
- Ensure unauthorized private object requests do not return indexable 200 pages.

P1:

- Generate sitemap from public routes.
- Add accurate `lastmod` support.
- Provide privacy-safe first-party analytics endpoint if selected.
- Add public 404/410 status behavior.

P2:

- Add static/public content caching after deploy controls are stable.
- Support public changelog/release notes if Product commits to maintenance.

### QA Release Engineer

P0:

- Add SEO/privacy crawl smoke to production readiness.
- Verify `/`, `/app.php`, `/api.php`, `/storage/`, sitemap, robots, manifest, service worker, icons, OG image.
- Confirm no private URLs in sitemap or indexable crawl output.
- Test returning installed PWA session after deploy.

P1:

- Validate structured data.
- Validate canonical/hreflang only after localized pages exist.
- Run Lighthouse/PageSpeed public checks for mobile and desktop.
- Record Core Web Vitals lab proxies and production field data source.

P2:

- Run quarterly SEO regression.
- Check stale cache, sitemap freshness, schema validity, public broken links, and privacy boundary.

### Product / Project Director

P0:

- Approve public positioning and trust/privacy boundary.
- Decide which public pages may ship before production deploy is GO.
- Confirm public SEO launch is not a substitute for DB/backup production readiness.

P1:

- Approve content cluster order and page intent.
- Provide public operator/trust/legal facts.
- Define growth KPIs that do not require private finance data.

P2:

- Decide native store strategy after PWA install funnel is measured.
- Approve case studies only with permission and anonymization.
- Decide whether yacht/crew, travel, or Business Desk becomes a priority growth segment.

## Measurement Dashboard

Minimum dashboard:

- indexed public URL count;
- sitemap submitted/processed;
- Search Console clicks/impressions by public URL;
- top branded and non-branded queries;
- public landing conversion to Open App;
- install help open rate;
- native install accepted/dismissed where available;
- installed PWA launch count;
- public Core Web Vitals;
- private indexability incidents: target zero.

Growth quality guardrails:

- no private data in analytics;
- no growth experiment ships without privacy QA;
- no SEO content page publishes without Product truth check;
- no localized page publishes without language QA;
- no schema publishes without visible matching content.

## Implementation Order

1. Lock privacy/noindex boundary.
2. Expand public root and trust copy.
3. Add structured data for public visible facts.
4. Add public trust/privacy/install/use-case pages.
5. Generate sitemap from public route inventory.
6. Add Search Console and privacy-safe analytics.
7. Add localized public pages and `hreflang`.
8. Build content clusters and measure activation quality.
9. Consider native store wrappers only after PWA funnel proves demand.

## Source Notes

Primary references checked on 2026-05-27:

- Google Search technical requirements: https://developers.google.com/search/docs/essentials/technical
- Google AI features and websites: https://developers.google.com/search/docs/appearance/ai-features
- Google JavaScript SEO basics: https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics
- Google canonicalization: https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls
- Google sitemaps: https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
- Google robots meta / X-Robots-Tag: https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag
- Google spam policies: https://developers.google.com/search/docs/essentials/spam-policies
- Google localized versions: https://developers.google.com/search/docs/specialty/international/localized-versions
- Google multi-regional and multilingual sites: https://developers.google.com/search/docs/specialty/international/managing-multi-regional-sites
- Google structured data guidelines: https://developers.google.com/search/docs/appearance/structured-data/sd-policies
- web.dev Web Vitals: https://web.dev/articles/vitals
- web.dev PWA manifest: https://web.dev/learn/pwa/web-app-manifest
- web.dev install prompt: https://web.dev/learn/pwa/installation-prompt
- MDN PWA installability: https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable
- MDN manifest `start_url`: https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/start_url
- MDN manifest `scope`: https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/scope
