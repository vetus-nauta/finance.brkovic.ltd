Subagent:
Frontend Performance and Interaction Agent.

Scope:
Sprint 01 legacy frontend runtime inventory as donor only. Checked old frontend runtime files for reusable shell/performance/test patterns versus unsafe old interaction and product logic. No implementation code was written. Old navigation, dashboards, Captain/On-the-Go flows, reports, categories, and cash/card behavior were not accepted as FinDesk v2.0 truth.

Files read:
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/10-director-and-subagents.md`
- `FinDesk v2.0/12-agent-work-protocol.md`
- `FinDesk v2.0/agents/04-FRONTEND-PERFORMANCE-INTERACTION-READ-FIRST.md`
- `FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md`
- `FinDesk v2.0/08-codex-implementation-brief.md`
- `FinDesk v2.0/11-build-phases.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/service-worker.js`
- `public/manifest.webmanifest`
- `scripts/findesk_runtime_audit.cjs`
- `tests/findesk-runtime-audit.spec.js`
- `package.json`

Findings:
- `public/assets/app.js` is a 19k-line monolith with several generations of old product logic in one runtime: install/auth shell, old ledger/reports/groups, Phase1, yacht-specific logic, Captain, advances, On-the-Go, report cards, receipt scanner, and later overrides. Classification: mixed, mostly `UNSAFE_LEGACY_LOGIC`.
- Reusable `app.js` donors: PWA install detection, standalone-mode detection, basic device detection, service-worker registration discipline, generic API wrapper shape, `escapeHtml`, formatting helpers, delegated event handling, history/localStorage state envelope, `visualViewport` height sync, passive resize/orientation listeners, keyboard focus recovery pattern, autosave debounce with busy/queued guard, action busy guards, `crypto.randomUUID` client IDs, clipboard fallback pattern, and attachment upload/retry state concepts. Classification: `GENERIC_HELPER` only after rewrite into clean v2 names.
- Do not reuse `app.js` interaction/product logic: old `qlSetModule` routes, `QL_MODULE_STATE_ALLOWED`, old dashboard/module navigation, old ledger/report calculations, final report package UI, group/member/advance workflows, Captain board/card flow, On-the-Go card lifecycle, yacht provisioning/order screens, business/premium modules, old cash/card/noncash naming, old category/report mapping, and old prompt/confirm edit flows. Classification: `UNSAFE_LEGACY_LOGIC`.
- `parseSimpleSignedNotes()` looks superficially close to v2 because it parses signed note lines and exposes skipped rows, but it rejects `+` rows in card stream and emits old `cash_in`, `cash_out`, `noncash_out` types. It must not be copied as v2 parser logic. Classification: `UNSAFE_LEGACY_LOGIC` with possible UI parsing-shape donor only.
- `public/assets/app.css` is a 12k-line legacy style layer. Reusable donors are CSS containment techniques: `minmax(0, 1fr)`, safe-area padding, device CSS variables, `100dvh`, internal overflow containers, `overscroll-behavior: contain`, `-webkit-overflow-scrolling: touch`, horizontal strips, text overflow/ellipsis, and touch target sizing. Classification: `GENERIC_HELPER`.
- Do not reuse old CSS screens/classes as v2 UX: `.hero-card`, `.mode-card`, `.captain-*`, `.advanced-*`, `.phase1-*`, `.otr-*`, `.yacht-*`, report print layouts, old cards/dashboard hierarchy, gradients/marketing shell, centered `980px` shell, and body/full-screen overlay behavior. These conflict with v2 one-screen AppShell and full workspace rules unless rewritten. Classification: `UNSAFE_LEGACY_LOGIC`.
- `public/service-worker.js` is a minimal cache-version cleanup worker using `skipWaiting()`, old `findesk-*` cache deletion, and `clients.claim()`. It has no fetch/offline strategy. Classification: `INFRASTRUCTURE_DONOR`; cache name and prefix must be replaced for v2.
- `public/manifest.webmanifest` is a PWA manifest donor for schema, icons, maskable icon, standalone display, theme/background color, and shortcuts. Classification: `INFRASTRUCTURE_DONOR`. Do not reuse exact `start_url: /app.php`, description, shortcut targets, or `orientation: portrait-primary`; the orientation conflicts with required iPhone/iPad landscape support.
- `scripts/findesk_runtime_audit.cjs` is a useful Playwright audit shell: seeded API setup, viewport contexts, screenshots, overflow checks, browser-back assertions, JSON summary/failure artifacts. Classification: `GENERIC_HELPER` as test scaffold. It writes to `test-results/*` and depends on old auth logs and old APIs, so it was not executed in this read-only report scope.
- `tests/findesk-runtime-audit.spec.js` is a reusable Playwright test shape: API seeding, storage state, desktop/mobile contexts, screenshots, and horizontal overflow assertions. Classification: `GENERIC_HELPER` as test scaffold. The scenario itself is old Captain/advance logic and must be replaced.
- `package.json` shows minimal dependency discipline with only `mongodb` and backend scripts. Classification: `INFRASTRUCTURE_DONOR` for low dependency posture. It does not currently declare Playwright dependencies or runtime-audit scripts even though the audit files require Playwright.

Reusable shell/perf/test donors:
- PWA install prompt and standalone detection pattern.
- Service-worker registration/cleanup pattern, with new v2 cache prefix and explicit fetch strategy later.
- PWA manifest structure, icons, maskable icon pattern, and standalone display.
- `visualViewport` CSS variable sync for iOS keyboard and viewport changes.
- Passive resize/orientation/visualViewport listeners.
- Device-class data attributes and CSS custom properties for gutter/touch sizing, rewritten to v2 device classes.
- Internal scroll containment patterns using `minmax(0, 1fr)`, `overflow`, `overscroll-behavior`, and `-webkit-overflow-scrolling`.
- Delegated event handling on stable data attributes.
- Local state envelope pattern for current route/view, rewritten without old modules.
- Autosave debounce, queued-save guard, retry state, and `visibilitychange`/`beforeunload` flush pattern.
- UI busy/disabled guard pattern for async actions.
- Screenshot/JSON Playwright audit harness, viewport matrix, overflow checks, and browser-back assertions.
- Attachment upload/retry state concepts and pointer-capture/canvas ideas for later attachment sprint, subject to Director approval.

Do-not-reuse interaction logic:
- Old module navigation and route names: product, ontherun, ledger, reports, captain, money, groups, business, premium, settings.
- Old dashboard/card-first UX and old centered desktop shell.
- Captain board, participant/admin cards, advances, accountable-money flows, and final report card lifecycle.
- Old ledger/report/final-package calculations and UI rendering.
- Old `cash`, `card`, `noncash`, `cash_received`, `cash_left`, `advance`, `on_the_go` semantics as finance truth.
- Old parser behavior that rejects card `+` rows or maps rows into `cash_in/cash_out/noncash_out`.
- Old category, section, group report, and closed report assumptions.
- Yacht-specific provisioning/order screens and print layouts.
- Body/page scroll or full-screen overlay behavior copied without matching the v2 allowed scroll container contract.

Changes made:
- Created this Sprint 01 frontend performance and interaction inventory report only.
- Did not modify `public/assets/app.js`, `public/assets/app.css`, `public/service-worker.js`, `public/manifest.webmanifest`, `scripts/findesk_runtime_audit.cjs`, `tests/findesk-runtime-audit.spec.js`, `package.json`, or package/service files.
- Did not run the existing runtime audit because it writes artifacts and validates old Captain/advance behavior, not v2 acceptance.

Risks:
- High accidental reuse risk because old frontend logic is large, global, and layered by overrides.
- Old cash/card parser and report semantics can directly conflict with v2 Cash/Card funding-flow rules, especially card `+` handling and `noncash` terminology.
- Old CSS has useful containment tactics but also body/full-screen overlay scroll patterns that can violate v2 no body/page scroll.
- Old desktop shell is centered and card-heavy; v2 requires full workspace usage on desktop and iPad 11+.
- Manifest `portrait-primary` could mask landscape/iPad issues if copied.
- Service-worker cache prefix reuse could delete or collide with future v2 caches.
- Runtime audit/test files depend on Playwright, but `package.json` does not declare Playwright scripts/dependencies.

Recommended next action:
Director should approve only the listed donor patterns, then require a fresh v2 frontend shell and fresh v2 runtime audit. The v2 audit should assert: no body/page scroll, only named internal scroll containers, desktop/iPad 11+ full workspace layout, iPad mini/phone mobile financial-notes system, current-month EventFeed visible during input, InputBar reachable with keyboard open, horizontal mobile structured view, and no old Captain/dashboard/report logic in the acceptance path.

Files touched:
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/04-FRONTEND-PERFORMANCE-INTERACTION-REPORT.md`
