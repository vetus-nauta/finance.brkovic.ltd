# Universal Web Product Bible V1 Implementation Checklist — 2026-06-04

Source: Google Drive `universal_web_product_bible_v1_full.zip`

Primary file reviewed:

- `00_UNIVERSAL_WEB_PRODUCT_BIBLE_V1.md`

Status: accepted as a universal product-completion filter for FinDesk implementation work. It does not replace the FinDesk Product Bible V1, but strengthens the execution checklist for route clarity, shell discipline, mobile behavior, visual cohesion, QA and release gates.

## Authority Order

1. FinDesk Product Bible V1 remains the highest FinDesk-specific source.
2. CEO decisions in `docs/AI_TEAM/05_DECISIONS.md` override generic rules when explicitly newer and project-specific.
3. Universal Web Product Bible V1 applies as a mandatory product-quality filter where FinDesk Product Bible is silent.
4. Legacy phase documents remain historical unless confirmed by the current product bible or current CEO decision.

## Non-Negotiable Boundaries

- [ ] `BND-001` Do not deploy to production without direct CEO command.
- [ ] `BND-002` Do not change financial formulas without architect and auditor approval.
- [ ] `BND-003` Do not restore `Бункеровка` as a global FinDesk entry point.
- [ ] `BND-004` Keep `Бункеровка` inside Yacht only.
- [ ] `BND-005` Do not make legacy modules normal navigation.
- [ ] `BND-006` Do not build dashboard-first screens where the user needs an operational workspace.
- [ ] `BND-007` Do not add menu items for every function.
- [ ] `BND-008` Do not create card-in-card-in-card layouts.
- [ ] `BND-009` Do not use accounting, ERP or API language in user-facing text.
- [ ] `BND-010` Do not style over broken structure.

## Phase 0 — Source Intake And Project Control

- [ ] `UWP-0001` Store the Drive package intake summary in project docs.
  - Owner: Project Director.
  - Acceptance: source link, file title, reviewed files and authority order are documented.

- [ ] `UWP-0002` Add this checklist to the active task board as a project-control artifact.
  - Owner: Project Director.
  - Acceptance: `04_TASK_BOARD.md` links to this file and states implementation is backlog, not completed work.

- [ ] `UWP-0003` Cross-check conflicts with FinDesk Product Bible V1.
  - Owner: Product Director.
  - Acceptance: conflicts are listed as `none`, `overridden`, or `requires CEO decision`.

- [ ] `UWP-0004` Freeze scope for the current product-completion pass.
  - Owner: Project Director.
  - Acceptance: no new feature branch is opened for decorative work before structure QA passes.

## Phase 1 — Golden Path And Route Map

- [ ] `UWP-0101` Define the current FinDesk golden path.
  - Target path: Welcome Hall -> scenario -> auth gate if needed -> workspace -> object/action -> fixation -> report/archive.
  - Acceptance: path can be explained in one sentence and mapped to current routes.

- [ ] `UWP-0102` Define the Yacht golden path separately.
  - Target path: Templates -> Yacht -> settings -> products/fuel -> calculated order -> print/archive.
  - Acceptance: Yacht remains a template/workspace, not the global product identity.

- [ ] `UWP-0103` Audit all visible routes.
  - Owner: FE + UX.
  - Acceptance: every visible route has one role: entry, scenario, workspace, object, operational, fixed result, archive, settings.

- [ ] `UWP-0104` Mark legacy routes as engine support or hidden.
  - Acceptance: no legacy route appears as normal product navigation.

- [ ] `UWP-0105` Remove or hide duplicate route entries.
  - Acceptance: one route has one entry point unless explicitly justified.

- [ ] `UWP-0106` Confirm Back-stack behavior.
  - Acceptance: Back returns by actual path, not always to root.

- [ ] `UWP-0107` Create route QA matrix.
  - Devices: desktop, iPad portrait, iPad landscape, iPhone portrait, iPhone landscape.
  - Acceptance: each route has expected entry, exit, back and empty-state behavior.

## Phase 2 — Welcome Hall And Start Page

- [ ] `UWP-0201` Re-audit the local start page.
  - URL: `http://127.0.0.1:18889/app.php`
  - Acceptance: user sees what FinDesk is, who it is for, and what to do next.

- [ ] `UWP-0202` Limit start scenarios to 2-4 human choices.
  - Required candidates: work alone, work with people, templates, continue work.
  - Acceptance: no grid of all functions on the first screen.

- [ ] `UWP-0203` Keep auth as a gate, not destination.
  - Acceptance: user can understand the product before being forced into login unless action truly requires auth.

- [ ] `UWP-0204` Implement return-after-auth contract.
  - Acceptance: if user selected a scenario before login, login returns to that scenario.

- [ ] `UWP-0205` Remove marketing/landing-page noise from Welcome.
  - Acceptance: no long advantage grid, no decorative blocks that do not guide work.

- [ ] `UWP-0206` Keep language access visible but secondary.
  - Acceptance: language does not compete with main action.

- [ ] `UWP-0207` Add start-page empty/fallback state.
  - Acceptance: if workspace is not selected, page explains the next first action.

## Phase 3 — Application Shell

- [ ] `UWP-0301` Define minimal menu structure.
  - Required: Workspace, Reports/History, Language, Profile, Login/Logout.
  - Acceptance: no unrelated ecosystem links, no experimental tools, no technical pages.

- [ ] `UWP-0302` Rebuild menu behavior for desktop and mobile.
  - Acceptance: menu opens predictably, closes predictably, does not hide primary actions.

- [ ] `UWP-0303` Standardize screen header.
  - Acceptance: each internal screen shows title, context object if any, back if applicable, secondary actions if needed.

- [ ] `UWP-0304` Standardize root header.
  - Acceptance: root screen shows product name and menu, not a fake inner title.

- [ ] `UWP-0305` Standardize Back button.
  - Acceptance: Back is present on internal screens and follows navigation stack.

- [ ] `UWP-0306` Move settings into Profile/Account.
  - Acceptance: profile contains language, account, install web app, logout, support and secondary service actions only.

- [ ] `UWP-0307` Remove operational actions from Profile.
  - Acceptance: no journal, report, object-management or finance action lives in profile.

- [ ] `UWP-0308` Add clear current-state indicators.
  - Acceptance: user knows current workspace, current object, and whether data is saved.

## Phase 4 — Workspaces And Object Model

- [ ] `UWP-0401` Confirm Solo Workspace role.
  - Acceptance: solo workspace shows current work and fast entry into action, not a finance dashboard.

- [ ] `UWP-0402` Confirm Team Workspace role.
  - Acceptance: team workspace is people-first, not money/chart-first.

- [ ] `UWP-0403` Confirm Templates role.
  - Acceptance: templates are scenario starters, not primary modules.

- [ ] `UWP-0404` Confirm Yacht workspace role.
  - Acceptance: Yacht has settings, products, fuel, print/export and internal bunkering, without becoming FinDesk main entry.

- [ ] `UWP-0405` Define object cards.
  - Objects: person, workspace, journal period, report, yacht order.
  - Acceptance: each card shows only fields needed for work with that object.

- [ ] `UWP-0406` Remove decorative cards.
  - Acceptance: if block can live without a card, card is removed.

- [ ] `UWP-0407` Document object lifecycle states.
  - Acceptance: each object has draft/current/fixed/approved/archive states where applicable.

## Phase 5 — Operational Screens

- [ ] `UWP-0501` Reconfirm Live Journal as records-first.
  - Acceptance: Live Journal is not report screen, dashboard or menu.

- [ ] `UWP-0502` Keep Cash/Card choice before journal input.
  - Acceptance: user never mixes cash and non-cash streams accidentally.

- [ ] `UWP-0503` Keep input close to work.
  - Acceptance: primary input is reachable and not hidden under decorative panels or mobile keyboard.

- [ ] `UWP-0504` Standardize save/fixation feedback.
  - Acceptance: after save/fix/finalize, system shows clear human confirmation.

- [ ] `UWP-0505` Separate current work from history.
  - Acceptance: operational screen does not become an archive.

- [ ] `UWP-0506` Recheck Yacht Products operational screen.
  - Acceptance: categories expand/collapse, selected counts and totals are visible, prices use current source rules.

- [ ] `UWP-0507` Recheck Yacht Fuel operational screen.
  - Acceptance: normal/duty-free selector is clear, source warning exists, ordinary fuel is based on regional average.

- [ ] `UWP-0508` Recheck Yacht Settings discoverability.
  - Acceptance: user can find yacht settings without guessing.

- [ ] `UWP-0509` Recheck product/fuel separation.
  - Acceptance: food never appears in fuel and fuel never appears in product category lists.

## Phase 6 — Fixation, History, Reports And Export

- [ ] `UWP-0601` Define fixation points.
  - Acceptance: user knows when work is draft, fixed, ready for review, approved or archived.

- [ ] `UWP-0602` Ensure fixed work is no longer controlled by the wrong role.
  - Acceptance: employee/admin ownership follows FinDesk Product Bible lifecycle.

- [ ] `UWP-0603` Keep reports out of operational screens.
  - Acceptance: reports are result screens, not the place where daily work happens.

- [ ] `UWP-0604` Preserve stream separation in reports.
  - Acceptance: Cash, Card/Non-Cash and Total remain distinct until final composition.

- [ ] `UWP-0605` Standardize report structure.
  - Fields: period, total, participants/objects, movements/actions, attachments, export/print/send.
  - Acceptance: every report can be read without developer explanation.

- [ ] `UWP-0606` Make export reflect approved data only.
  - Acceptance: PDF/print/send does not silently export live drafts where approval is required.

- [ ] `UWP-0607` Add report empty states.
  - Acceptance: no blank archive; user sees why it is empty and how first report appears.

## Phase 7 — Trust, Protected Actions And Audit Trail

- [ ] `UWP-0701` Inventory dangerous actions.
  - Include: delete, rollback, approved-object edit, balance/result change, unlock, unsubmit, stage transfer.
  - Acceptance: every dangerous action is listed.

- [ ] `UWP-0702` Apply Protected Action flow to every dangerous action.
  - Required: consequences, affected objects, reason, exact `CONFIRM`.
  - Acceptance: no dangerous action runs from a plain button.

- [ ] `UWP-0703` Confirm audit log fields.
  - Required: actor, timestamp, before, after, reason, affected objects.
  - Acceptance: audit trail exists for important changes.

- [ ] `UWP-0704` Hide audit history from daily work unless needed.
  - Acceptance: history exists but does not clutter operational screens.

- [ ] `UWP-0705` Improve save-state language.
  - Acceptance: statuses use human Russian labels, not API/enums.

- [ ] `UWP-0706` Confirm rollback rule.
  - Acceptance: every rollback explains what will happen before execution.

## Phase 8 — Language, Copy And Localization

- [ ] `UWP-0801` Audit all visible labels.
  - Acceptance: no user-facing `payload`, `attached`, `enum`, `finalized package`, `sync object`.

- [ ] `UWP-0802` Normalize Russian working language.
  - Acceptance: buttons use human verbs: сохранить, зафиксировать, отправить, вернуть, подтвердить.

- [ ] `UWP-0803` Keep localization in shared namespace.
  - Acceptance: keys are namespaced and not duplicated manually across screens where avoidable.

- [ ] `UWP-0804` Move rare language controls to menu/profile where appropriate.
  - Acceptance: language remains reachable but not primary.

- [ ] `UWP-0805` Standardize error copy.
  - Acceptance: errors explain what to do next.

- [ ] `UWP-0806` Standardize status copy.
  - Acceptance: statuses are human: no records, in work, ready for review, awaiting confirmation, saved, sent, returned.

## Phase 9 — Visual Constitution

- [ ] `UWP-0901` Define FinDesk visual rules from Operational Luxury Minimalism.
  - Acceptance: one documented visual direction exists before broad styling.

- [ ] `UWP-0902` Reduce decorative air on working screens.
  - Acceptance: working screens use 60-80% of useful area for work data/actions.

- [ ] `UWP-0903` Audit card usage.
  - Acceptance: cards represent real objects only.

- [ ] `UWP-0904` Audit typography.
  - Acceptance: text hierarchy does the work before containers/decor.

- [ ] `UWP-0905` Audit color usage.
  - Acceptance: one main accent, calm surfaces, red/green only for state and financial meaning.

- [ ] `UWP-0906` Remove template feeling.
  - Acceptance: no WordPress/SaaS/admin/table-first look remains in primary paths.

- [ ] `UWP-0907` Keep Yacht metaphor controlled.
  - Acceptance: yacht visual language supports work but does not hide settings/products/fuel actions.

## Phase 10 — Mobile And Device Behavior

- [ ] `UWP-1001` Validate iPhone portrait.
  - Acceptance: user sees where they are, what is main, what to do, how to go back, what is saved.

- [ ] `UWP-1002` Validate iPhone landscape.
  - Acceptance: no clipped header, menu, product category, fuel table, keyboard or bottom action.

- [ ] `UWP-1003` Validate iPad portrait.
  - Acceptance: layout does not become oversized mobile or broken desktop.

- [ ] `UWP-1004` Validate iPad landscape.
  - Acceptance: desktop-like layout remains readable and touch-safe.

- [ ] `UWP-1005` Validate desktop.
  - Acceptance: same product order and behavior as mobile, only with wider layout.

- [ ] `UWP-1006` Remove mobile-hostile tables.
  - Acceptance: no five-column table is required for primary phone work.

- [ ] `UWP-1007` Validate keyboard behavior.
  - Acceptance: input and action buttons do not disappear under mobile keyboard.

- [ ] `UWP-1008` Validate touch targets.
  - Acceptance: primary buttons are finger-safe and not too close to dangerous actions.

- [ ] `UWP-1009` Validate scrolling.
  - Acceptance: long lists scroll naturally; fixed panels do not trap content.

## Phase 11 — Empty States, Errors And Notifications

- [ ] `UWP-1101` Inventory empty states.
  - Acceptance: every screen with no data has a purposeful empty state.

- [ ] `UWP-1102` Add first-action guidance.
  - Acceptance: empty state explains what to do first and what happens after.

- [ ] `UWP-1103` Replace technical errors.
  - Acceptance: user sees cause and recovery action.

- [ ] `UWP-1104` Standardize short success feedback.
  - Acceptance: every important action confirms success without modal spam.

- [ ] `UWP-1105` Standardize warning placement.
  - Acceptance: stale price/source warnings sit near affected product/fuel area.

## Phase 12 — NFR And Engineering Safety

- [ ] `UWP-1201` Preserve auth/session/database/API/PWA/storage foundations.
  - Acceptance: no foundation change without explicit reason and rollback path.

- [ ] `UWP-1202` Service worker cache versioning.
  - Acceptance: every JS/CSS behavior change bumps asset version.

- [ ] `UWP-1203` Performance budget for start page.
  - Acceptance: start page loads without unnecessary heavy decorative assets.

- [ ] `UWP-1204` Local storage migration safety.
  - Acceptance: old local state does not reopen forbidden legacy routes.

- [ ] `UWP-1205` Accessibility pass.
  - Acceptance: keyboard navigation, labels, focus states and contrast are checked on primary paths.

- [ ] `UWP-1206` Security pass for protected actions.
  - Acceptance: frontend confirmation is not the only protection where backend mutation exists.

- [ ] `UWP-1207` Price engine freshness policy.
  - Acceptance: products and fuel have source registry, partial-failure averaging and stale-warning behavior.

- [ ] `UWP-1208` OpenAI key usage policy.
  - Acceptance: background refresh is scheduled/triggered intentionally and not exposed as uncontrolled UI spending.

## Phase 13 — QA Gates

- [ ] `UWP-1301` Product QA.
  - Acceptance: user can complete golden path without explanation.

- [ ] `UWP-1302` Physical QA.
  - Acceptance: real manual pass on desktop, iPhone, iPad portrait/landscape.

- [ ] `UWP-1303` UX QA.
  - Acceptance: no confusing entry, hidden main action, unexplained state or broken Back behavior.

- [ ] `UWP-1304` Design QA.
  - Acceptance: visual system feels coherent and does not look like admin/template/ERP.

- [ ] `UWP-1305` Functional QA.
  - Acceptance: key workflows work: auth return, workspace, journal, transfer, reports, Yacht products, Yacht fuel, print.

- [ ] `UWP-1306` Engine audit.
  - Acceptance: financial formulas are not changed or are explicitly approved by architect/auditor.

- [ ] `UWP-1307` Report/export QA.
  - Acceptance: PDF/print/send use fixed/approved data rules.

- [ ] `UWP-1308` Regression QA.
  - Acceptance: legacy screens do not reappear through saved state, menu, Back, hidden tabs or direct route.

## Phase 14 — Release Candidate And Production Boundary

- [ ] `UWP-1401` Create local release-candidate report.
  - Acceptance: changed files, risks, tests, screenshots/visual proof and unresolved blockers are recorded.

- [ ] `UWP-1402` Confirm no production deploy.
  - Acceptance: release report explicitly says local-only until CEO command.

- [ ] `UWP-1403` Backup/rollback plan.
  - Acceptance: if backend/database changes exist, backup and rollback steps are documented before production.

- [ ] `UWP-1404` Final CEO review package.
  - Acceptance: CEO can inspect start page, shell, Yacht products/fuel, mobile behavior and reports before deploy.

- [ ] `UWP-1405` Production release only after explicit command.
  - Acceptance: no FTP/deploy action occurs from this checklist alone.

## Immediate Implementation Order

1. `UWP-0101` to `UWP-0107`: route/golden-path audit.
2. `UWP-0201` to `UWP-0207`: Welcome/start-page cleanup.
3. `UWP-0301` to `UWP-0308`: application shell/menu/back/profile.
4. `UWP-0506` to `UWP-0509`: Yacht products/fuel/settings cleanup after current engine work.
5. `UWP-1001` to `UWP-1009`: device behavior standardization.
6. `UWP-1301` to `UWP-1308`: QA gates before release candidate.

## Current Project Notes

- Product source already says FinDesk is a shared money journal, not yacht-only software.
- Yacht is a ready template/workspace and must stay inside the template path.
- Products and fuel source logic now require separate source registries and stale-warning behavior.
- Current dirty working tree includes local product/fuel changes; do not overwrite or revert unrelated edits.
- PHP CLI is not available in the current shell; PHP worker lint/run remains a separate environment task.
