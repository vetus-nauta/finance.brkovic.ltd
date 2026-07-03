Subagent:
iOS-Native UX Layout Agent

Scope:
Sprint 01 read-only legacy UI/layout inspection for FinDesk v2.0. The old app was reviewed only as donor/inspiration risk, not as product truth. No implementation code, application UI, CSS, or JS was changed.

The FinDesk v2.0 one-screen iOS notes contract has priority over all old UI, old docs, old dashboards, old Product Bible/Phase documents, route trees, and previous UX decisions.

Files read:
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/04-responsive-layout-contract.md`
- `FinDesk v2.0/17-screen-registry.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/10-director-and-subagents.md`
- `FinDesk v2.0/12-agent-work-protocol.md`
- `FinDesk v2.0/agents/03-IOS-NATIVE-UX-LAYOUT-READ-FIRST.md`
- `FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md`
- `FinDesk v2.0/11-build-phases.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/00-DIRECTOR-LOG.md`
- `index.php`
- `app.php`
- `public/index.php`
- `public/app.php`
- `public/assets/app.css` (targeted layout, shell, scroll, device, modal, and legacy selector inspection)
- `public/assets/app.js` (targeted shell, viewport, route, modal, auth, PWA, scroll, and legacy interaction inspection)
- `docs/AI_TEAM/00_START_HERE.md`
- `docs/AI_TEAM/01_PRODUCT_COMPASS.md`
- `docs/AI_TEAM/11_MOBILE_MULTITASKING_RESEARCH.md`
- `docs/AI_TEAM/58_PHASE1_CLEAN_PRODUCT_SHELL_2026-06-02.md`
- `docs/AI_TEAM/66_PRODUCT_BIBLE_SPRINT2_SOLO_LIVE_JOURNAL_LOCAL_2026-06-03.md`
- `docs/AI_TEAM/71_PRODUCT_BIBLE_SPRINT7_MOBILE_UX_ROUTE_CLEANUP_LOCAL_2026-06-03.md`
- `docs/AI_TEAM/89_CURRENT_SITE_ROUTE_TREE_2026-06-03.md`
- `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md`
- `docs/AI_TEAM/OFFICE_DASHBOARD.html` (selector/structure search)
- `docs/AI_TEAM/FINDESK_PHASE1_FUNCTIONAL_PROTOTYPE_2026-06-02.html` (selector/structure search)
- `docs/AI_TEAM/CHAT_START_PORTAL.html` (selector/structure search)
- `docs/AI_TEAM/` file inventory and UI/layout keyword search

Findings:
1. v2 layout authority is clear and incompatible with the old dashboard shell.
   - v2 Journal is the current-month notes-style feed with reachable input.
   - Mobile/phone/iPad mini: vertical movement scrolls notes; horizontal movement reveals structured/report-ready rows.
   - Desktop/iPad 11+: full workspace layout, not a centered mobile column.
   - Body/page scroll is forbidden; only named internal containers may scroll.

2. Root wrappers are safe infrastructure candidates.
   - `index.php` only requires `public/index.php`.
   - `app.php` only requires `public/app.php`.
   - These wrappers do not encode old UX or finance logic.

3. `public/index.php` is mostly public/PWA/SEO shell, not v2 product UI.
   - Donor candidates: viewport-fit cover, manifest/icon metadata, install modal wiring, public-to-private entry boundary.
   - Unsafe as UX truth: landing sections, hero cards, glass styling, public marketing copy, three-layer old product language.

4. `public/app.php` mixes too many old product surfaces in one document.
   - It contains auth, language strip, phase shell, ledger, On the Go, Captain, Advanced/Money, Premium, Groups, Business, Settings, scanner, proof viewer, final report, archive, and message modals.
   - This violates the v2 screen registry principle if reused as a main UX base.
   - Useful only as an inventory of old surfaces and possible infrastructure donors.

5. Old private shell is not the v2 one-screen Journal.
   - The old private app has centered shell/card composition, menus, panels, report blocks, modals, and legacy modules.
   - The current old layout can show feed, forms, reports, employee cards, business/proforma tools, scanner, archive, and advanced dashboards in the same legacy surface.
   - That is dashboard sprawl, not the v2 iOS Notes-like financial feed.

6. `public/assets/app.css` contains some reusable primitives but is unsafe wholesale.
   - Donor/helper candidates: system font stack, safe-area padding, touch target sizing, modal primitives, file picker style idea, input focus treatment, internal scroll treatment.
   - Unsafe: centered `ql-shell` widths, glass/hero/card visual language, large dashboard/card/KPI grids, phase/captain/advanced/business/yacht styling, body/page scroll assumptions, old print/report CSS.
   - CSS does not globally enforce v2 no body/page scroll.

7. `public/assets/app.js` contains generic shell helpers mixed with old finance/route logic.
   - Donor/helper candidates: PWA install prompt pattern, service worker registration pattern after review, auth request/verify/logout shell, `qlApi` transport wrapper idea, modal open/close pattern, file upload pattern, `visualViewport` keyboard-height idea.
   - Unsafe: old ledger reports, final report package UI, On the Go card submit/include/archive flows, Captain/Advanced/group workflows, localStorage/history module routing, old cash/card/noncash rules, old scroll-to-panel behavior.
   - Search found pointer drag handling only around receipt scanner crop controls; no reliable v2 horizontal swipe model for notes vs structured rows was found.

8. Old device classes do not match v2 acceptance as-is.
   - Legacy JS derives `phone/tablet/desktop` from viewport and pointer state.
   - v2 needs iPhone/iPad mini mobile notes system and iPad 11+/desktop full workspace system.
   - The old algorithm and CSS device classes must not be reused without Director-approved redesign.

9. `docs/AI_TEAM` confirms the legacy risk but is not authority for v2.
   - Several docs already warn not to build new UI on old screens and identify legacy DOM/route resurrection risks.
   - `FINDESK_PHASE1_FUNCTIONAL_PROTOTYPE` has useful warnings like keeping reports/archive/employee cards out of the live journal, but it remains old prototype material.
   - `OFFICE_DASHBOARD.html` and `CHAT_START_PORTAL.html` are AI office tooling, not application UX.
   - Product Bible, Phase 1/2/3, route tree, and AI_TEAM docs must be rejected as v2 product truth when they conflict with `FinDesk v2.0/`.

UI parts classification:

| UI part | Classification | Notes |
| --- | --- | --- |
| Root `index.php` and `app.php` require wrappers | INFRASTRUCTURE_DONOR | Safe wrapper pattern. No UX logic. |
| `public/index.php` HTTP/cache boundary, viewport, manifest, icons | INFRASTRUCTURE_DONOR | Public/private boundary and PWA metadata can inform v2 infrastructure. Marketing layout cannot. |
| `public/app.php` private noindex, cache headers, manifest/icons | INFRASTRUCTURE_DONOR | Shell metadata only. |
| Email/code auth panel and current-user/logout flow | INFRASTRUCTURE_DONOR | Auth shell candidate. Must be separated from old screens. |
| `qlApi` fetch wrapper idea | GENERIC_HELPER | Transport helper idea only; action names and payloads are old. |
| Modal open/close mechanics | GENERIC_HELPER | Useful primitive after accessibility and focus review. Modal contents are mostly old. |
| File picker/upload/proof viewer shell | GENERIC_HELPER | Attachment infrastructure candidate. Old proof/report semantics are unsafe. |
| `visualViewport` height/keyboard state idea | GENERIC_HELPER | Good inspiration for keyboard-safe input. Old phase-specific implementation is not reusable as-is. |
| Safe-area padding and touch target CSS ideas | GENERIC_HELPER | Keep as design constraints, not as copied CSS. |
| Language selector/i18n surface | GENERIC_HELPER | Useful app plumbing; old wording and placement are not v2 authority. |
| `moduleLedger` personal ledger UI | UNSAFE_LEGACY_LOGIC | Old ledger/form/report model conflicts with notes-first v2 Journal. |
| Old report/final report panels | UNSAFE_LEGACY_LOGIC | v2 reports are generated screens with strict registry, not embedded dashboard/report sprawl. |
| On the Go / cards / tapes / session flows | UNSAFE_LEGACY_LOGIC | Old capture/report semantics and state model are not v2 truth. |
| Captain/group/participant dashboards | UNSAFE_LEGACY_LOGIC | Old group finance and dashboard UX must not drive v2 layout. |
| Advanced/Money/KPI dashboards | UNSAFE_LEGACY_LOGIC | Decorative/operational dashboard cards are explicitly rejected for v2 Journal. |
| Business/proforma/Yacht/Home template surfaces | UNSAFE_LEGACY_LOGIC | Separate legacy product surfaces; not v2 main shell truth. |
| Glass/hero/card-heavy CSS theme | UNSAFE_LEGACY_LOGIC | Conflicts with calm one-screen iOS notes contract and dashboard rejection. |
| Centered `ql-shell` desktop layout | UNSAFE_LEGACY_LOGIC | v2 desktop/iPad 11+ must use full workspace, not a centered mobile column. |
| Body/page scroll and `scrollIntoView` panel behavior | UNSAFE_LEGACY_LOGIC | v2 forbids body/page scroll and allows only controlled internal scroll. |
| Old module/localStorage/history route system | UNSAFE_LEGACY_LOGIC | Route resurrection risk; screen registry must be clean. |
| Legacy `phase1/phase2/Product Bible` route docs | UNSAFE_LEGACY_LOGIC | Useful as warning evidence, not v2 authority. |
| Existing service worker/cache behavior | UNKNOWN_REQUIRES_DIRECTOR | PWA infrastructure may be reusable, but old cache keys/assets can preserve legacy UI. Needs separate infrastructure review. |
| Existing device breakpoint algorithm | UNKNOWN_REQUIRES_DIRECTOR | Needs Director-approved mapping to v2 iPhone/iPad mini/iPad 11+/desktop classes. |
| `legacy:true` escape hatch mentioned in route docs | UNKNOWN_REQUIRES_DIRECTOR | Could be internal rescue path, but dangerous for v2 if reachable from normal UI. |
| AI_TEAM screenshots/assets and iPhone Notes references | UNKNOWN_REQUIRES_DIRECTOR | Can inspire tone only if Director approves; v2 written contract remains priority. |

Changes made:
- Created this Sprint 01 iOS-native UX layout report only.
- No application PHP, CSS, JS, SQL, runtime config, assets, or docs outside this report were changed.

Risks:
1. Legacy DOM is physically large and mixed. Future agents may accidentally revive old modules if they edit `public/app.php`/`app.js` without isolation.
2. Old CSS/JS includes many phase-specific and module-specific rules that can override clean v2 layout if copied wholesale.
3. Old shell does not prove v2 no-body-scroll acceptance; several old behaviors rely on page-level scrolling or `scrollIntoView`.
4. No reliable v2 horizontal swipe model was found in legacy code.
5. Old docs contain attractive but conflicting product language. The Director must keep rejecting them as authority when they conflict with `FinDesk v2.0/`.
6. Old cash/card/noncash UI semantics may conflict with v2 Cash/Card flow rules, especially card-to-cash handling.
7. Old desktop layout patterns include centered shell constraints; v2 large screens must use full workspace width.

Recommended next action:
Director should approve only the infrastructure/helper donor list above for later extraction. For v2 UX, start from a clean AppShell/Journal contract after Foundation and Logic gates are accepted:
- AppShell, TopBar, WorkspaceSummary, MainArea, EventFeed, DetailPanel, InputBar.
- Phone/iPad mini: one-screen notes feed, internal EventFeed scroll, keyboard-safe InputBar, horizontal structured rows.
- Desktop/iPad 11+: full workspace layout with panels, no centered mobile column.
- Reject old dashboard/card/report/advanced/captain/business/yacht surfaces as v2 UX direction.

Files touched:
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/03-IOS-NATIVE-UX-LAYOUT-REPORT.md`
