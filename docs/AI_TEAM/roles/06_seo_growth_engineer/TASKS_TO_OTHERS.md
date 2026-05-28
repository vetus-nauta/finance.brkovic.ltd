# Tasks To Others: SEO Growth Engineer

Date: 2026-05-27

## P0 Tasks

### Frontend UX Engineer

Request:

- Keep `/app.php` and all authenticated app surfaces noindex.
- Expand the public root with server-rendered, visible English copy that explains FinDesk, the three layers, privacy boundary, who it is for, and safe install path.
- Do not ship an empty public SPA or hide the primary product explanation behind JavaScript.
- Remove reliance on `meta keywords` as an SEO lever; do not expand it into stuffing.

Acceptance:

- View source of `/` contains meaningful public text without login.
- `/app.php` still contains `noindex,nofollow`.
- Public CTA text makes clear that private finance work happens after entering the app.

### Backend Data Engineer

Request:

- Add or verify server-level `X-Robots-Tag: noindex, nofollow` for private app/API/storage/file/proof responses where feasible.
- Ensure private/auth routes return meaningful 401/403/404 status codes and never public 200 content for unauthorized finance objects.
- Ensure `robots.txt` and sitemap are served with correct content type and production URL.

Acceptance:

- `curl -I` evidence shows private surfaces are not indexable.
- Public sitemap lists only canonical public URLs.
- No private identifier route can return indexable public HTML.

### QA Release Engineer

Request:

- Add SEO/privacy crawl smoke to production readiness.
- Check `/`, `/app.php`, `/api.php`, `/storage/`, manifest, sitemap, robots, service worker, public assets, and public OG image.
- Confirm no private route appears in sitemap or indexable crawl output.

Acceptance:

- QA records HTTP status, robots meta/header, canonical, sitemap contents, manifest/icon fetches, and noindex boundary.
- QA verifies returning installed PWA session after deploy does not show stale public/app shell.

### Product / Project Director

Request:

- Approve public positioning and trust copy before SEO launch.
- Decide what public pages may exist before production deploy while DB/backup controls remain NO-GO.
- Approve the strict boundary: public marketing/trust/install content is indexable; app finances are private/noindex.

Acceptance:

- Public page list and message map are approved.
- No production SEO push is treated as production app GO.

## P1 Tasks

### Frontend UX Engineer

Request:

- Add public page templates for Trust/Privacy, Use Cases, Install PWA, and core feature clusters.
- Add JSON-LD for visible public facts: Organization, WebSite, WebApplication/SoftwareApplication, FAQ only where visible.
- Add canonical tags on every public URL.
- Prepare real localized public pages before any `hreflang`.

Acceptance:

- Structured data passes Rich Results Test where applicable.
- Canonical is absolute and self-consistent.
- Localized pages have visible translated main content, not only translated navigation.

### Backend Data Engineer

Request:

- Generate sitemap from public route inventory when public pages exceed a handful of URLs.
- Add accurate `lastmod` only for meaningful content/schema/link updates.
- Provide a safe public analytics endpoint if first-party aggregate events are selected.

Acceptance:

- Sitemap contains only indexable public canonicals.
- Analytics endpoint rejects private event names and payload keys.

### QA Release Engineer

Request:

- Add Lighthouse/PageSpeed/Core Web Vitals lab checks for public pages.
- Validate mobile/desktop LCP/INP/CLS targets and asset weight budget.
- Validate `hreflang` reciprocity only after localized URLs exist.

Acceptance:

- QA stores public SEO evidence by URL.
- Any localized URL has self-canonical, reciprocal alternates, and English/x-default fallback evidence.

### Product / Project Director

Request:

- Approve content cluster order and conversion KPI definitions.
- Provide trust/legal facts that can be publicly shown.
- Decide whether yacht/crew/project positioning is a primary cluster or a secondary niche.

Acceptance:

- Content calendar has owner, intent, page URL, language, and conversion goal.
- Trust claims have evidence and do not overpromise accounting/legal/security guarantees.

## P2 Tasks

### Frontend UX Engineer

Request:

- Build comparison/demo pages with synthetic data only.
- Add public breadcrumbs after page depth exists.
- Improve public install UX with screenshots only after QA confirms actual install flows.

Acceptance:

- Demo pages contain no real user data.
- Screenshots match current product and do not expose private workspace data.

### Backend Data Engineer

Request:

- Support public changelog/release notes if Product wants freshness signals.
- Add static export or cache strategy for public content if traffic grows.

Acceptance:

- Public content can be served quickly without touching private finance tables.

### QA Release Engineer

Request:

- Run quarterly SEO regression: indexability, schema, sitemap, robots, hreflang, CWV, PWA install, privacy boundary.

Acceptance:

- Regression report records pass/fail by public route and private-boundary route.

### Product / Project Director

Request:

- Decide if native App Store / Play Store wrapper is worth a separate growth project after PWA validation.
- Approve case studies only after customer permission and anonymization rules exist.

Acceptance:

- No store listing, review, or case-study claim is published without evidence and permission.
