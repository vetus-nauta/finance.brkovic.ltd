# SEO Growth Engineer Findings

Date: 2026-05-27

## Source Basis

Local files inspected:

- `public/index.php`
- `public/app.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/20_LANGUAGE_POLICY_AUDIT.md`

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

## Executive Finding

FinDesk has the correct initial privacy posture for a finance PWA: public root is indexable, authenticated app is noindex, and robots blocks app/API/storage crawling. The growth weakness is not an SEO trick gap; it is a public content and trust gap. The current public landing is thin, single-page, English-only, and has no structured data or content clusters. It can be crawled, but it does not yet give search engines, AI search features, or cautious finance users enough visible evidence to trust and classify the product.

## Public Landing SEO

Observed:

- `public/index.php` is server-rendered HTML with `lang="en"`.
- It has title, meta description, canonical URL, manifest, favicon/app icons, Open Graph and Twitter images.
- It includes visible public text for FinDesk, the three-layer model, install buttons, app entry, donation modal, and a short SEO section.
- It includes a `meta keywords` tag with a broad list of terms.

Findings:

- The root page is not an empty SPA, which is good.
- Public copy is too thin for serious 2026 SEO in a trust-sensitive finance category.
- The `meta keywords` tag should not be treated as a ranking lever and can become a quality risk if expanded into keyword stuffing.
- The landing needs a clearer public promise: who FinDesk is for, what workflows it solves, what is private, what is not financial advice/accounting/legal service, and who operates the product.
- The public page should lead with FinDesk and brkovic.ltd trust, not only with app installation.

## App Store / PWA Install Surface

Observed:

- `public/index.php` and `public/app.php` link `/manifest.webmanifest`.
- Manifest starts at `/app.php`, uses standalone display, has categories, 192/512 icons, and maskable icon.
- `public/assets/app.js` handles `beforeinstallprompt`, appinstalled state, iOS Safari fallback instructions, Android/desktop install guidance.
- `public/service-worker.js` has install/activate cleanup and deletes older `findesk-*` caches, but no fetch handler in the inspected file.

Findings:

- PWA install surface is present and practical.
- Growth should measure install intent, install prompt accepted/dismissed, and installed launches without storing finance contents.
- Manifest `start_url` to `/app.php` is correct for app usage, but SEO must keep `/app.php` private. Public install copy must make this boundary explicit.
- Service worker stale-cache risk is lower because there is no fetch handler, but returning installed sessions still need deploy smoke around SW update, app assets, manifest, and icons.

## Multilingual / International SEO

Observed:

- Product language policy says English is fallback.
- Supported languages: `ru`, `en`, `de`, `it`, `es`, `sr`, `zh` rendered as `zh-Hans`.
- `public/index.php` is English only.
- `public/app.php` starts as English HTML but private app UI uses i18n and many hardcoded strings.
- No public language routes or `hreflang` tags exist.

Findings:

- Do not implement `hreflang` until real public localized URLs exist.
- For SEO, translated interface strings are not enough; each public localized page needs visible localized main content.
- Preferred future model: separate public URLs such as `/en/`, `/de/`, `/it/`, `/es/`, `/sr/`, `/zh-Hans/`, and optionally `/ru/`, with self-canonical and reciprocal `hreflang`.
- Root `/` can remain English canonical and `x-default` if it is the global fallback.
- Automatic redirects by browser language are risky for crawlers and should not hide other language pages.

## Content Clusters

Required public clusters:

- Field finance capture: mobile expense capture, proof/photo, unstable network, no-data-loss workflow.
- Group expense and report checking: reviewer flow, accepted reports, closed archive.
- Cash/card/accountable money control: where money physically sits and who holds it.
- Crew/yacht/project operations: narrow commercial use case without stuffing.
- Business Desk/proforma: separate business documents, not mixed into daily cash reports.
- Travel equalization: post-MVP/staged content only if Product confirms scope.
- PWA finance app install/use: how to install on iOS, Android, Windows/desktop.
- Trust/security/privacy: what is indexed, what is private, how data is protected, who operates the service.

Findings:

- Content must be original, product-specific, and tied to visible FinDesk workflows.
- Avoid broad generic pages like "best budget tracker" unless FinDesk actually serves that intent and page content proves it.
- Public examples must use dummy/demo data only.

## Technical SEO

Observed:

- `robots.txt` allows root and disallows `/app.php`, `/api.php`, `/storage/`.
- `/app.php` also has `noindex,nofollow`.
- `sitemap.xml` lists only root.
- Root has canonical URL.
- No structured data found on root.
- No public 404/410 SEO policy inspected.

Findings:

- Dual protection for `/app.php` is good for privacy, but note the technical nuance: robots disallow can prevent crawlers from seeing a page-level `noindex`. For high-stakes private routes, Backend should add server-level `X-Robots-Tag: noindex, nofollow` to app/API/storage/private file responses where applicable.
- Sitemap should include only canonical, indexable public URLs.
- When public pages expand, add accurate `<lastmod>` only for meaningful content/schema/link updates.
- Canonical must be absolute and stable.
- Public pages need meaningful HTTP status codes, not soft-404 app states.
- Core Web Vitals should be measured with LCP, INP, and CLS at p75 mobile/desktop. Landing assets and JS should not turn the public page into an app-sized payload.

## Schema / Structured Data

Observed:

- No JSON-LD structured data found in the public root.

Findings:

- Add structured data only for visible public facts.
- Initial candidates: `Organization`, `WebSite`, `SoftwareApplication` or `WebApplication`, `FAQPage` only if FAQ content is visible, and `BreadcrumbList` after multiple public pages exist.
- Do not mark up hidden, private, user-generated, or future features as live.
- Do not add fake ratings, fake reviews, fake prices, or unsupported app-store claims.

## Privacy / Noindex Boundary

Observed:

- `/app.php` has `noindex,nofollow`.
- `robots.txt` blocks `/app.php`, `/api.php`, `/storage/`.
- Private app contains finance data workflows, proofs, reports, messages, groups, invites, Business Desk, and Travel staging.

Findings:

- Public SEO must stop at the marketing/trust/install boundary.
- Never expose report IDs, proof URLs, invite URLs, group names, user emails, or finance amounts to public snippets, analytics labels, schema, sitemap, logs, or screenshots.
- Public demo pages must use synthetic examples only.
- Private app search protection should be enforced at HTML meta, HTTP header, robots, auth status, and QA crawl smoke levels.

## Analytics / Measurement Without Breaking Privacy

Measurement allowed:

- public page views by public URL;
- CTA clicks: open app, install help, donate open, language switch on public pages;
- PWA install prompt shown/accepted/dismissed where browser exposes it;
- app launch source as coarse channel only;
- Core Web Vitals and asset errors;
- Search Console impressions/clicks by public URL/query.

Measurement not allowed:

- transaction amounts;
- report IDs;
- proof names/URLs;
- user emails;
- group names;
- message text;
- invite tokens;
- private route paths with identifiers;
- AI/audit outputs.

Findings:

- Start with privacy-safe server logs, Search Console, and coarse event analytics.
- Add consent/cookie policy only if the chosen analytics stack requires cookies or advertising identifiers.
- Prefer cookieless/first-party aggregate analytics for public growth.

## Typical SEO Failures To Avoid

- Empty SPA/PWA without server-rendered public text.
- Indexing the personal account/app area.
- Keyword stuffing.
- Duplicate pages without canonical.
- Incorrect `hreflang`.
- Heavy assets that damage LCP/INP/CLS.
- Service-worker stale cache after deploy.
- Brand without a trust page.
- Missing sitemap, robots, or structured data.

## Risk Register

P0 risks:

- Private app, API, files, proofs, or invite routes become indexable.
- Public pages expose real finance/customer data.
- SEO analytics collects private financial content or identifiers.

P1 risks:

- Thin public page cannot rank beyond brand terms.
- Incorrect multilingual implementation causes duplicate/canonical/hreflang confusion.
- Stale PWA shell shows old install/app copy after deployment.
- Trust gap reduces conversion for a finance product.

P2 risks:

- Content production drifts into generic finance blog spam.
- App Store/native positioning is promised before a real store package exists.
- Growth dashboard over-optimizes clicks and ignores activation quality.
