# AI Team Task Board

## Director Sprint 2026-06-04 - Yacht Fuel Print And Rows Cleanup

Status: local fuel screen cleanup implemented; professional print document implemented; static contract QA passed; fallback headless browser/device QA passed; real PHP/WebStorm backend smoke remains pending.

Done locally:

- removed fuel print-price toggle from the upper fuel settings area;
- added `Печатать с ценами` checkbox next to the print button;
- default fuel print checkbox is off;
- screen prices remain visible for work; print prices are hidden only in print mode when the checkbox is off;
- unchecked fuel rows are hidden during print;
- default fuel package rebuilt:
  - row 1: `Топливо / Дизель`;
  - row 2: `Агентский сбор`, default `250 EUR`;
  - following rows are disabled, empty and use weak placeholders for motor oil, spare filters, first aid/consumables and port incidentals;
- added `+ Своя категория` button for custom fuel-section rows;
- old local default fuel package is migrated to the new structure when it matches the previous default rows;
- fuel source refresh interval returned to monthly: `30` days;
- asset version updated to `20260604-fuel-print-routes18`;
- local JS/service-worker syntax and diff whitespace checks passed.
- static JS/CSS contract checks passed for:
  - no `show_prices` field on fuel screen;
  - `Печатать с ценами` near print button;
  - `+ Своя категория`;
  - no fuel-screen `yacht-add-fuel` / `yacht-add-tech` buttons;
  - disabled rows hidden during print;
  - price cells hidden during fuel print when print-price checkbox is off;
  - weak placeholders;
  - no old default prices for oil, filters, first aid or port incidentals.
- fallback Node server opened `http://127.0.0.1:18889/app.php?build=routes19` with current assets because PHP CLI is unavailable in the current shell;
- Playwright headless browser/device smoke passed on fallback/stub API:
  - desktop `1440x950`;
  - iPad portrait `834x1194`;
  - iPad landscape `1194x834`;
  - iPhone portrait `393x852`;
  - iPhone landscape `852x393`;
- each headless case passed:
  - fuel screen rendered;
  - print checkbox exists and defaults off;
  - upper `show_prices` is absent;
  - reference panel is present;
  - first row is `Топливо / Дизель`;
  - second row is `Агентский сбор / 250`;
  - disabled placeholder rows exist;
  - `+ Своя категория` adds an enabled custom row;
  - print CSS hides disabled rows;
  - print CSS hides price cells when price printing is off;
  - no page JS errors;
  - no horizontal overflow in tested viewport width.
- professional fuel print document added in asset version `20260604-fuel-print-routes19`:
  - FinDesk logo and branded document header;
  - clear document title and order number;
  - contractor block from company profile with FinDesk/brkovic fallback;
  - customer/yacht block with marina, berth, model and registration when available;
  - print meta block for price region, fuel mode, catalog and update date;
  - separate static print table, not the interactive screen table;
  - only enabled rows with real category/item values are printed;
  - disabled placeholder/technical rows stay out of the document unless the user explicitly fills and enables a row;
  - professional footer with document caveat and three signature blocks;
  - screen controls, reference panel and editable table are hidden in print mode.
- Playwright print-mode smoke passed on fallback/stub API for desktop, iPad portrait/landscape and iPhone portrait/landscape:
  - print document exists and is hidden on screen;
  - print document becomes visible in print media;
  - screen form is hidden in print media;
  - FinDesk logo, contractor/customer blocks, meta block, clean print table and footer signatures are present;
  - print hide-prices also hides price cells in the new print document;
  - no horizontal overflow;
  - no page JS errors.
- fuel placeholder correction added in asset version `20260604-fuel-print-routes20`:
  - старые реальные технические значения из fuel-пакета принудительно мигрируют в настоящие placeholders;
  - row 1 keeps real diesel quantity/price when they already exist;
  - row 2 is fixed to `Агентский сбор / Услуга агента / 1 услуга / 250`;
  - rows 3+ are empty values with grey placeholders only;
  - disabled-row text color is no longer forced grey, so typed text stays standard;
  - old print lock is cleared during package migration;
  - fuel-screen reference/approved price application changes only `Дизель`, not agent fee or placeholder rows.
- Playwright migration smoke passed against the bad saved state from the screenshot:
  - old `Агент + Масло моторное` becomes `Агентский сбор + Услуга агента`;
  - old technical rows become empty placeholder-only rows;
  - `Подставить цены региона` changes diesel only;
  - no page JS errors.
- print pagination/footer correction added in asset version `20260604-fuel-print-routes22`:
  - print CSS resets outer app containers to avoid an empty second page;
  - professional print document is compacted for one A4 page in the standard fuel order case;
  - custom footer stamp added: `finance.brkovic.ltd - Vetus Nauta Brkovic`;
  - print timestamp is shown in the document footer;
  - Chromium PDF smoke confirmed the standard fuel order prints as 1 page.
  - Native browser print headers/footers with URL/time are controlled by the browser print dialog, not by app CSS; they should be disabled in the print dialog when using the branded footer.
- print action path fixed in asset version `20260604-fuel-print-routes23`:
  - removed delayed `setTimeout` before `window.print()`;
  - print is now called synchronously inside the click handler, preserving browser user activation;
  - layout is forced before print so the print-only document class is active;
  - cleanup uses `afterprint` with a timeout fallback;
  - Playwright click smoke confirms the print button calls `window.print()` once with the print class active.
  - Printer queue/status could not be inspected from the current WebStorm/Flatpak shell because `lpstat`, `lpq` and `lpoptions` are unavailable; only `lpr` is present.

## Director Sprint 2026-06-04 - Workspace Trash And Session Direction

Status: workspace soft-trash implemented locally; active-session model recorded as product direction; PHP runtime smoke pending because PHP CLI/WebStorm backend is unavailable in this shell.

Product decision:

- Do not encourage users to multiply duplicate workspace cards for the same work pattern.
- A workspace is a durable container.
- Repeated work inside the same context should become active sessions inside that workspace.
- When a user needs another run of the same work type, the UX should guide them to start/open the next active session instead of creating another duplicate workspace.
- For early experimentation, users must be able to move any created workspace to trash and restore it before the retention window expires.

Done locally:

- added backend soft-trash API:
  - `group_trash`;
  - `group_restore`;
  - `group_trash_list`;
  - `group_trash_purge_expired`;
- active group list now excludes trashed workspaces;
- trash list returns archived workspaces visible to the current active member;
- trash retention is 60 days;
- expired trash purge keeps financial evidence preserved by leaving groups archived and moving active memberships/invites out of the active user surface;
- added deploy index migration `deploy/group_trash_retention.sql`;
- added workspace card `Удалить` action for manageable non-solo workspaces;
- added delete confirmation modal that requires typing `удалить`;
- added workspace trash section with restore action;
- if the current active workspace is moved to trash, local workspace selection is cleared;
- asset version updated to `20260604-workspace-trash-routes24`.

QA:

- `node --check public/assets/app.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- Playwright workspace trash smoke passed:
  - delete button visible;
  - modal mentions the required `удалить` word;
  - wrong confirmation shows warning;
  - confirmed modal calls `group_trash` API.
- workspace trash stale-API correction added in asset version `20260604-workspace-trash-routes25`:
  - after successful trash API response, the workspace is stored in local client tombstones;
  - locally trashed workspaces are hidden from the active workspace list even if a stale/fallback API still returns them;
  - locally trashed workspaces are shown in the Trash panel with restore action;
  - restoring a workspace clears the local tombstone;
  - the active/current workspace is no longer duplicated in the `Все пространства` list.
- Playwright stale/fallback API smoke passed:
  - `QA Yacht` is hidden from the main list after trash even when `group_list` still returns it;
  - `QA Yacht` appears in the Trash panel;
  - local tombstone is stored;
  - restore brings the card back.

## Director Sprint 2026-06-04 - Product Menu Home Entry

Status: implemented locally.

Done:

- added first menu item `На главную`;
- `На главную` routes to `workspace-hub`, the working hall / workspace hub;
- kept `Мои пространства` as a separate second item for clarity;
- asset version updated to `20260604-menu-home-routes26`.

QA:

- `node --check public/assets/app.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- Playwright menu smoke passed:
  - first menu item is `На главную`;
  - clicking it opens `workspace-hub`.

## Director Sprint 2026-06-04 - Trash Moved To Menu

Status: implemented locally.

Done:

- removed trash display from `Мои рабочие пространства`;
- added dedicated `workspace-trash` route;
- added `Корзина` as a menu item;
- trash page shows deleted workspaces and restore action;
- Back from trash returns to `workspace-hub`;
- asset version updated to `20260604-menu-trash-routes27`.

QA:

- `node --check public/assets/app.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- Playwright menu/trash smoke passed:
  - trash is not visible in workspace hub;
  - menu contains `Корзина`;
  - clicking `Корзина` opens the trash page.

## Director Sprint 2026-06-04 - Independent Section Pages

Status: implemented locally.

Done:

- removed the old combined Yacht render that mixed yacht settings, crew and bunkering in one page;
- `yacht` now renders only a standalone entry page with links to separate sections;
- `phase1FocusYachtBunkering()` now routes to `yacht-bunkering` instead of scrolling to a removed embedded block;
- `Бункеровка` copy now states it is a standalone section;
- confirmed route separation:
  - `yacht-home`;
  - `yacht-tools`;
  - `yacht-bunkering`;
  - `yacht-fuel`;
  - `yacht-products`;
  - `yacht-settings`;
  - `home-home`;
  - `home-tools`;
  - `home-household`;
  - `home-shopping`;
  - `home-budget`;
- asset version updated to `20260604-independent-pages-routes29`.

QA:

- `node --check public/assets/app.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- targeted Playwright route smoke passed:
  - yacht sections render as separate screens;
  - `yacht-bunkering` does not contain yacht settings fields;
  - `yacht-settings` does not contain bunkering/fuel/product controls.

Open:

- real PHP/WebStorm backend smoke for `group_trash`, `group_restore`, `group_trash_list`;
- DB migration application on real database after backup;
- design the next layer: active sessions inside workspaces, so duplicate workspace cards are not the default behavior.

Open:

- real PHP/WebStorm local server smoke on fuel screen;
- real print preview QA with prices off/on;
- real iPad/iPhone portrait and landscape visual QA after PHP/WebStorm server is restored;
- PHP CLI lint remains unavailable in current shell;
- local server `127.0.0.1:18889` must be restarted from WebStorm/PHP environment before browser QA.

## Director Sprint 2026-06-04 - Route Tree And Legacy Guard Pass

Status: current route tree updated; one legacy wrapper side-effect guarded; deeper route cleanup pending.

Primary report:

- `docs/AI_TEAM/89_CURRENT_SITE_ROUTE_TREE_2026-06-03.md`

Done locally:

- updated current route tree to reflect actual `workspace-*`, `yacht-*` and `home-*` screens;
- documented Product Shell menu, route guard, workspace state, Yacht tree, Home tree, Back behavior, API snapshot loading and weak spots;
- confirmed old route tree was stale after the latest Product Shell/Yacht work;
- added missing `phase1ShellIsActive()` guard to the early On The Go `qlSetModule` wrapper so legacy data-load does not run after Product Shell redirect;
- asset version updated to `20260604-route-guard-routes16`;
- local JS/service-worker syntax and diff whitespace checks passed.

Open:

- explicit `workspace_type` storage added locally; production DB rollout/backfill still pending;
- decide Welcome behavior for returning users: always show hall vs continue workspace;
- simplify `templates` vs `workspace-create` naming;
- make Yacht settings/products/fuel more obvious from `yacht-home`;
- physical QA for Back/menu/device behavior.

## Director Sprint 2026-06-04 - Explicit Workspace Type

Status: local additive implementation complete; production rollout not performed.

Done locally:

- added backend `workspace_type` support for groups: `team`, `yacht`, `home`;
- `group_create` accepts `workspace_type` and stores it when the column exists;
- `group_get` and `group_list` return `workspace_type`;
- if the column is missing or schema alteration is unavailable, API falls back safely to old name-based detection;
- Yacht workspace creation now sends `workspace_type: yacht`;
- Home workspace creation now sends `workspace_type: home`;
- frontend route logic now reads `group.workspace_type` before using legacy name fallback;
- added deploy SQL candidate: `deploy/group_workspace_type.sql`;
- updated `deploy/groups_foundation.sql`;
- asset version updated to `20260604-workspace-type-routes17`.

Open:

- run PHP syntax check in an environment with PHP CLI;
- run authenticated API smoke for `group_create` / `group_list` after DB column creation;
- production DB migration/backfill only after backup and explicit approval;
- backfill old Yacht/Home groups where needed.

## Director Sprint 2026-06-04 - Universal Web Product Bible Checklist

Status: source reviewed; implementation checklist fixed as backlog/control artifact.

Primary checklist:

- `docs/AI_TEAM/90_UNIVERSAL_WEB_PRODUCT_BIBLE_IMPLEMENTATION_CHECKLIST_2026-06-04.md`

Source package:

- Google Drive `universal_web_product_bible_v1_full.zip`

Done locally:

- reviewed `00_UNIVERSAL_WEB_PRODUCT_BIBLE_V1.md`;
- mapped universal product rules to FinDesk/Yacht constraints;
- fixed task IDs from `UWP-0001` through `UWP-1405`;
- separated boundaries, golden path, Welcome, shell, workspace, operational screens, reports, protected actions, visual system, mobile, NFR, QA and release gates;
- preserved current decisions: FinDesk remains shared money journal, Yacht remains a template, bunkering remains inside Yacht, production deploy remains blocked.

Open:

- execute route/golden-path audit first;
- then start Welcome/start-page cleanup;
- then shell/menu/back/profile standardization;
- then Yacht products/fuel/settings cleanup;
- then device behavior QA and release-candidate report.

## Director Sprint 2026-06-03 - Yacht Bunkering Button Scope Correction

Status: local scope correction implemented; browser QA pending.

Primary report:

- `docs/AI_TEAM/87_YACHT_BUNKERING_SCOPE_CORRECTION_LOCAL_2026-06-03.md`

Done locally:

- removed external "Бункеровка" start buttons from FinDesk entry points;
- removed "Бункеровка" from the top "Шаблоны" menu;
- removed separate "Бункеровка" card from Welcome Hall and Templates screen;
- kept bunkering inside Yacht template as an internal action;
- asset version updated to `20260603-yacht-bunkering-inside1`.

Open:

- browser visual QA;
- production deploy only after explicit approval.

## Director Sprint 2026-06-03 - Yacht Approved Price Bridge

Status: local read-only API and frontend bridge implemented; browser QA pending.

Primary report:

- `docs/AI_TEAM/85_YACHT_APPROVED_PRICE_BRIDGE_LOCAL_2026-06-03.md`

Done locally:

- added `app/yacht_prices.php`;
- added API action `yacht_price_approved_catalog`;
- API reads active approved storage catalog only;
- API requires authenticated user;
- Yacht UI can load approved prices and apply them manually;
- approved diesel/gasoline prices map into Yacht fuel rows;
- blocked duty-free item is not applied;
- printing sets `price_locked_at` and stores `price_snapshot`;
- automated price apply is blocked after price lock;
- asset version updated to `20260603-yacht-approved-bridge1`.

Open:

- browser visual QA;
- real mobile QA;
- server-side immutable Yacht order archive if Yacht orders need durable storage beyond local state;
- explicit supplier quote path for duty-free fuel.

## Director Sprint 2026-06-03 - Yacht Price Approval Gate

Status: local approval gate implemented; active storage catalog approved; UI not published.

Primary report:

- `docs/AI_TEAM/84_YACHT_PRICE_APPROVAL_GATE_LOCAL_2026-06-03.md`

Done locally:

- added `scripts/yacht_price_candidate_gate.cjs`;
- review command works;
- approval without phrase/warning/duty-free allowances is blocked;
- approved local catalog created at `storage/yacht-price-approved/20260603T132215Z-adriatic_balkans-fuel-approved.json`;
- active pointer created at `storage/yacht-price-approved/active-adriatic_balkans-fuel.json`;
- approved local prices: diesel `EUR 2.24 / 1.57`, gasoline `EUR 2.13 / 1.49`;
- blocked: `duty_free_marine_diesel_liter`;
- `ui_published: false`.

Open:

- build read-only API/frontend bridge from approved storage catalog to Yacht price engine;
- keep direct UI publication blocked until source/version indicators are visible;
- collect explicit duty-free supplier quotes.

## Director Sprint 2026-06-03 - Yacht Fuel Price Candidate

Status: local candidate created; not published.

Primary report:

- `docs/AI_TEAM/83_YACHT_FUEL_PRICE_CANDIDATE_LOCAL_2026-06-03.md`

Done locally:

- full `adriatic_balkans/fuel` OpenAI run completed;
- snapshot created at `storage/yacht-price-catalog/20260603T131456Z-adriatic_balkans-fuel-node.json`;
- candidate builder added: `scripts/yacht_price_candidate_from_snapshot.cjs`;
- candidate created at `storage/yacht-price-candidates/20260603T131613Z-adriatic_balkans-fuel-candidate.json`;
- accepted: `marine_diesel_liter`, `gasoline_liter`;
- blocked: `duty_free_marine_diesel_liter`;
- candidate remains `pending_review`, `publish_allowed: false`.

Open:

- build review/approval gate;
- do not publish candidate into UI until review gate exists;
- collect direct supplier/bunker/duty-free sources for explicit duty-free prices.

## Director Sprint 2026-06-03 - Yacht AI Price Cycle

Status: local AI price cycle implemented and tested.

Primary report:

- `docs/AI_TEAM/82_YACHT_AI_PRICE_CYCLE_LOCAL_2026-06-03.md`

Done locally:

- added Node worker `scripts/yacht_price_ai_refresh.cjs`;
- added snapshot review helper `scripts/yacht_price_snapshot_review.cjs`;
- AI now collects source observations with explicit `price_basis`;
- code computes final full/duty-free prices deterministically;
- tax-included pump/retail prices are not treated as hidden net prices;
- first controlled OpenAI run created `storage/yacht-price-catalog/20260603T130305Z-adriatic_balkans-fuel-node.json`;
- latest reviewed result for `marine_diesel_liter`: confidence `medium`, full `EUR 2.00`, estimated duty-free `EUR 1.40`, publish status `review_required`.

Open:

- run full `adriatic_balkans/fuel` family;
- review all fuel items;
- add publication candidate layer;
- do not publish to UI until review gate exists.

## Director Sprint 2026-06-03 - OpenAI Key Smoke

Status: local API key installed; OpenAI API smoke passed.

Primary report:

- `docs/AI_TEAM/81_OPENAI_KEY_SMOKE_PASS_2026-06-03.md`

Done locally:

- key file exists at `storage/secrets/openai_api_key`;
- key file permissions are `600`;
- `/v1/models` auth check passed;
- `gpt-5.4-mini` is visible to the account;
- Responses API structured-output smoke passed;
- no key value was printed or committed.

Blocked:

- Yacht PHP worker dry-run/real-run, because PHP CLI is not available in the current shell.

Next:

- find PHP CLI path or run from an environment where `php` exists;
- run `php scripts/yacht_price_ai_refresh.php`;
- then one controlled `fuel/adriatic_balkans` real snapshot run.

## Director Sprint 2026-06-03 - OpenAI Key Terminal Install

Status: complete; key file installed.

Primary report:

- `docs/AI_TEAM/80_OPENAI_KEY_TERMINAL_INSTALL_READY_2026-06-03.md`

Done locally:

- OpenAI provider now supports `api_key_file`;
- local config points to `storage/secrets/openai_api_key`;
- terminal installer added at `scripts/install_openai_key.sh`;
- installer status and shell syntax check passed.

Result:

- terminal install completed;
- key status moved to `docs/AI_TEAM/81_OPENAI_KEY_SMOKE_PASS_2026-06-03.md`.

## Director Sprint 2026-06-03 - Yacht Provision API Package

Status: local deterministic API implementation complete.

Primary report:

- `docs/AI_TEAM/79_YACHT_PROVISION_API_PACKAGE_APPLIED_2026-06-03.md`

Source package:

- `https://drive.google.com/file/d/15f78Qt6NNA8nuh4u3I_T6Y9aoo4yncz4/view?usp=sharing`

Done locally:

- package catalog, filters and schemas copied into `app/data/yacht_provisioning`;
- added `app/yacht_provisioning.php`;
- added API action `yacht_provision_calculate`;
- implemented people/days formula, profile multiplier, meal plan multiplier, filters, rounding, warnings and grouped category response;
- local HTTP smoke passed for normal, invalid and large-group requests.

Open:

- frontend UI integration into Yacht template;
- optional route alias for `/api/provisioning/calculate` if the app adds a routing layer;
- PHP CLI syntax check where PHP CLI is available;
- production deployment only after QA.

## Director Sprint 2026-06-03 - OpenAI Yacht Price Refresh

Status: local background scaffold complete; real key/provider run not executed.

Primary report:

- `docs/AI_TEAM/78_OPENAI_YACHT_PRICE_REFRESH_LOCAL_2026-06-03.md`

Done locally:

- searched the project for an active OpenAI key and external AI calls;
- confirmed current `app/ai.php` is local FinDesk analysis, not OpenAI-backed;
- added `app/openai_provider.php`;
- added OpenAI config template to `app/config.local.example.php`;
- added CLI-only worker `scripts/yacht_price_ai_refresh.php`;
- worker supports food refresh every 90 days and fuel refresh every 30 days;
- worker defaults to `dry-run` and requires `--run` for any API call;
- snapshots are written to `storage/yacht-price-catalog` and do not overwrite active Yacht prices.

Open:

- set real `OPENAI_API_KEY` only in server environment;
- run PHP syntax check where PHP CLI is available;
- add approved source registry per region;
- add source confidence, outlier control and director approval before publishing catalog values.

## Director Sprint 2026-06-03 - Yacht Price Engine

Status: local implementation complete; provider integration open.

Primary report:

- `docs/AI_TEAM/77_YACHT_PRICE_ENGINE_LOCAL_2026-06-03.md`

Done locally:

- yacht price zones were converted from flat presets to `Price Engine v1`;
- user sees only the final visible price;
- net source values, averaging, tax, logistics, markup and duty-free discount stay inside the engine;
- food and fuel support `Полная цена / Duty free`;
- failed/unavailable source entries are ignored in the averaging model;
- yacht crew roles are preserved during form synchronization;
- asset version updated to `20260603-yacht-price-engine1`.

Open path to 100 percent readiness:

- define approved truth sources per region and product family;
- move catalog snapshots to backend storage;
- add manual and scheduled refresh;
- add outlier control, source confidence and fallback to last good snapshot;
- keep printed/archived orders immutable;
- run browser, source-failure, duty-free and print QA.

## Director Task 2026-06-02 - Product Bible V1 Alignment

Status: accepted as highest-level FinDesk product source.

Primary intake:

- `docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md`

Source package:

- `https://drive.google.com/file/d/1hS3pcVRbxHTD3PnzxxRHCJ41vlN5bcs_/view?usp=sharing`

Meaning:

- Product Bible V1 stands above Phase 1, Phase 2, Phase 3, QA, audit and handoff documents;
- FinDesk is a modern shared money journal, not accounting, ERP, CRM, bank, dashboard, or ecosystem portal;
- the product path is `Welcome Hall -> Solo/Team -> Cash/Card -> Live Journal -> Fixed Journal -> Report Assembly -> Reports`;
- Team Workspace is a people screen;
- Live Journal is records-first;
- old interface remnants must be removed before Phase 3 can pass.

Current gate:

- no implementation should proceed unless it answers the Product Bible guardrails;
- Phase 3 remains open until UX, visual identity, mobile QA and physical QA pass;
- production release remains blocked until functional QA, engine audit, UX QA, mobile QA, visual QA, report/export QA, backup/rollback and release audit are complete.

## Director Sprint 2026-06-03 - Product Bible Sprint 0 Route Map

Status: local route audit complete; Sprint 1 implementation brief ready.

Primary route map:

- `docs/AI_TEAM/64_PRODUCT_BIBLE_SPRINT0_ROUTE_MAP_2026-06-03.md`

Key findings:

- current Product screens exist, but still use `ontherun + phase1_*` as browser state;
- old modules remain in DOM: `ledger`, `ontherun`, `captain`, `money`, `premium`, `groups`, `business`, `settings`;
- old `data-module-tab`, `data-mode-open`, `qlSetModule`, `localStorage` restore and extra `popstate` handlers can revive legacy screens;
- Sprint 1 must make Product Bible shell the only normal visible route.

Sprint 1 target:

- rebuild Welcome Hall and application shell locally;
- restrict visible menu to Product Bible items;
- prevent old module restore from becoming the first screen;
- keep legacy modules as hidden engine support only;
- do not style or patch old `captain/ontherun/money` surfaces.

## Director Sprint 2026-06-03 - Product Bible Sprint 1 Welcome / Shell

Status: local implementation complete; browser/physical QA pending.

Primary report:

- `docs/AI_TEAM/65_PRODUCT_BIBLE_SPRINT1_WELCOME_SHELL_LOCAL_2026-06-03.md`

Done locally:

- pre-auth Welcome Hall added above email code login;
- authenticated Welcome rebuilt around Product Bible start paths;
- visible menu reduced to `Workspace / Reports / Account`;
- product route state added as `module=product`;
- old `ontherun + phase1_*` state is no longer the normal route layer;
- legacy module restore and legacy click paths redirect to Product routes;
- `moduleOnTheGo` no longer starts as active DOM module;
- asset version updated to `20260603-product-shell1`.

Checks passed:

- `node --check public/assets/app.js`;
- `node --check public/service-worker.js`;
- `git diff --check`;
- local HTTP checks for app shell, JS and CSS.

Next sprint:

- Sprint 2: `Solo Workspace -> Cash/Card Choice -> Live Journal records-first rebuild`.

## Director Sprint 2026-06-03 - Product Bible Sprint 2 Solo / Live Journal

Status: local implementation complete; browser/mobile QA pending.

Primary report:

- `docs/AI_TEAM/66_PRODUCT_BIBLE_SPRINT2_SOLO_LIVE_JOURNAL_LOCAL_2026-06-03.md`

Done locally:

- `Работаю один` now activates Solo workspace;
- Solo screen presents Cash / Card choice directly;
- Live Journal rebuilt around records feed;
- input is one line: `± Сумма и заметка...`;
- `Записать` saves the line through existing journal engine;
- `Зафиксировать журнал` uses `start_next=1`;
- frontend now sends current `tape_id` to avoid saving into another active tape;
- frontend preserves carry-forward start amount after fixation;
- Cash and Card smoke tests passed separately;
- asset version updated to `20260603-live-journal1`.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check;
- local HTTP app/asset checks;
- authenticated API smoke for Cash save/fix/carry-forward and Card fix.

Next sprint:

- Sprint 3: `Team Workspace = People Screen`, then Admin Card / Employee Card skeleton and pending transfer visible state.

## Director Sprint 2026-06-03 - Product Bible Sprint 3 Team Workspace / Transfers

Status: local implementation complete; browser/mobile QA pending.

Primary report:

- `docs/AI_TEAM/67_PRODUCT_BIBLE_SPRINT3_TEAM_WORKSPACE_LOCAL_2026-06-03.md`

Done locally:

- Team route is now a people-first workspace;
- group creation is available inside Product shell;
- Admin Card shows `У меня / У сотрудников / Ожидают проверки`;
- Admin Card can create invite links;
- Admin Card can create first-class pending transfers;
- Employee Card shows pending transfer state;
- employee confirmation activates transfer and creates active journal tape;
- Product shell snapshot now loads `findesk_transfer_list`;
- employee issued/remaining calculations include first-class transfers;
- pending transfer blocks employee journal before confirmation;
- asset version updated to `20260603-team-workspace1`.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check;
- local HTTP app/asset checks;
- authenticated API smoke for group invite, join, pending transfer, confirm;
- pending journal block smoke.

Next sprint:

- Sprint 4: Admin / Employee card completion, Add Money, edit/cancel pending transfer and confirmed team Live Journal path.

## Director Sprint 2026-06-03 - Product Bible Sprint 4 Admin Card Completion

Status: local implementation complete; browser/mobile QA pending.

Primary report:

- `docs/AI_TEAM/68_PRODUCT_BIBLE_SPRINT4_ADMIN_CARD_COMPLETION_LOCAL_2026-06-03.md`

Done locally:

- Admin Card can add money into the active group cash journal;
- pending transfer rows expose `Изменить` and `Отменить`;
- edit requires reason and exact phrase `ИЗМЕНИТЬ`;
- cancel requires reason and exact phrase `ОТМЕНИТЬ`;
- exact phrases are checked by both frontend and backend;
- admin money intake appends one cash-in record through `on_the_go_create`;
- backend edit audit now stores reason plus previous/next amount and stream;
- employee journal remains blocked while pending transfer exists;
- confirmed transfer path was rechecked;
- asset version updated to `20260603-admin-card1`.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check for frontend assets;
- local HTTP app/asset checks;
- authenticated API smoke for pending block, edit reason/confirmation gates, cancel confirmation gate, edit, cancel, confirm and admin cash-in.

Known gaps:

- browser visual QA not run because Playwright is not installed locally;
- mobile physical QA pending;
- Report Assembly and final archive/export remain open.

Next sprint:

- Sprint 5: Report Assembly, Cash/Card/Total final report structure and Protected finalization path.

## Director Sprint 2026-06-03 - Product Bible Sprint 5 Report Assembly

Status: local implementation complete; browser/mobile QA pending.

Primary report:

- `docs/AI_TEAM/69_PRODUCT_BIBLE_SPRINT5_REPORT_ASSEMBLY_LOCAL_2026-06-03.md`

Done locally:

- Product snapshot now loads `findesk_report_assembly_get`;
- Product snapshot now loads `findesk_report_list`;
- Assembly screen shows `Cash / Card / Total`;
- Assembly screen separates `Cash Section` and `Card / Non-cash Section`;
- ready submitted journals can be attached through `findesk_report_item_attach`;
- finalization requires reason and exact phrase `УТВЕРДИТЬ`;
- backend `findesk_report_finalize` blocks missing reason and missing phrase;
- Reports screen shows finalized group reports from `findesk_report_list`;
- asset version updated to `20260603-report-assembly1`.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check;
- local HTTP app/asset checks;
- authenticated API smoke for admin journal, employee journal, attach, finalize gates, final report list.

Known gaps:

- browser visual QA not run because Playwright is not installed locally;
- mobile physical QA pending;
- report detail/export remains open;
- package-wide archive export remains open.

Next sprint:

- Sprint 6: physical UX closure, mobile keyboard/touch behavior, report detail/export and remaining old-route dirt.

## Director Sprint 2026-06-03 - Product Bible Sprint 6 Report Detail / Export

Status: local implementation complete; browser/mobile QA pending.

Primary report:

- `docs/AI_TEAM/70_PRODUCT_BIBLE_SPRINT6_REPORT_DETAIL_EXPORT_LOCAL_2026-06-03.md`

Done locally:

- finalized report rows now expose `Открыть` and `Экспорт`;
- report detail loads through `findesk_report_detail`;
- report detail shows `Cash / Card / Total`;
- report detail shows `Cash Section` and `Card / Non-cash Section`;
- export builds a JSON report package from the report snapshot;
- open report detail is cleared when workspace changes;
- asset version updated to `20260603-report-detail1`.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check;
- local HTTP app/asset checks;
- authenticated API smoke for report detail and export payload shape.

Known gaps:

- browser visual QA not run because Playwright is not installed locally;
- mobile physical QA pending;
- export is JSON package, not ZIP with attachments;
- package-wide archive export across all reports remains open.

Next sprint:

- Sprint 7: mobile physical UX closure, keyboard/input conflict, touch responsiveness and remaining old-route remnants.

## Director Sprint 2026-06-03 - Product Bible Sprint 7 Mobile UX / Route Cleanup

Status: local implementation complete; real-device QA pending.

Primary report:

- `docs/AI_TEAM/71_PRODUCT_BIBLE_SPRINT7_MOBILE_UX_ROUTE_CLEANUP_LOCAL_2026-06-03.md`

Done locally:

- Product shell now syncs dynamic mobile viewport through `visualViewport`;
- Product inputs set `phase1-keyboard-open` while focused;
- focused Product inputs scroll into view;
- Live Journal no longer depends on raw `100vh`;
- Live Journal bottom/input is sticky near safe bottom on mobile;
- records feed has touch scrolling;
- Product buttons/inputs use touch-friendly behavior;
- legacy click listeners and `qlSetModule` wrappers stop side effects while Product shell is active;
- asset version updated to `20260603-mobile-ux1`.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check;
- local HTTP app/asset checks.

Known gaps:

- real-device physical QA still required;
- browser visual QA not run because Playwright is not installed locally;
- camera/scanner PWA gate remains open;
- package-wide archive ZIP export remains open.

Next sprint:

- Sprint 8: real-device QA gate for mobile keyboard, touch scroll, PWA mode, Live Journal, Team flow and report export.

## Director Sprint 2026-06-03 - Product Bible Sprint 8 Archive Export / QA Gate

Status: local implementation complete; real-device QA still blocks production.

Primary report:

- `docs/AI_TEAM/72_PRODUCT_BIBLE_SPRINT8_ARCHIVE_EXPORT_QA_GATE_LOCAL_2026-06-03.md`

QA checklist:

- `docs/AI_TEAM/roles/04_qa_release_engineer/PHYSICAL_QA_CHECKLIST_PRODUCT_BIBLE_2026-06-03.md`

Done locally:

- added `findesk_report_archive_export`;
- Reports screen can export full group archive JSON package;
- archive package includes finalized reports, items and snapshots;
- physical QA checklist created for real devices;
- asset version updated to `20260603-archive-export1`.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check;
- local HTTP app/asset checks;
- authenticated API smoke for archive package export.

Known gaps:

- archive export is JSON package, not ZIP with binary attachments;
- real-device physical QA not run;
- browser visual QA not run because Playwright is not installed locally;
- camera/scanner PWA gate still requires physical device check.

Release gate:

- production deploy remains blocked until physical QA passes on real devices.

Local QA run:

- `docs/AI_TEAM/roles/04_qa_release_engineer/LOCAL_QA_CHECKLIST_RUN_PRODUCT_BIBLE_2026-06-03.md`
- local API/engine QA passed;
- static PWA/route/mobile guard QA passed;
- real-device physical QA remains open.

## Director QA Scenario 2026-06-03 - A. Usov Final Report

Status: local API QA passed; production deploy not performed.

Primary report:

- `docs/AI_TEAM/73_A_USOV_FINAL_QA_SCENARIO_2026-06-03.md`

Done locally:

- created clean group `FinDesk A. Usov Final QA 2026-06-03`, group id `275`;
- admin `a.usov@mail.com` received `65,765 EUR`;
- five employee transfers were issued and confirmed: `4,000`, `3,000`, `700`, `7,000`, `300 EUR`;
- admin and all five employees submitted one Live Journal each;
- common report id `8` was assembled and finalized;
- archive export contains the finalized report.

QA result:

- final cash received: `65,765 EUR`;
- internal issued: `15,000 EUR`;
- total spent: `60,600 EUR`;
- total remaining: `5,165 EUR`;
- active Live Journals after finalization are empty;
- carry-forward balances passed: admin `3,765`, employees `380 / 270 / 60 / 650 / 40 EUR`.

Local fixes added:

- FinDesk report summary now adjusts duplicated transfer received/remaining totals;
- old empty group-journal auto-sync no longer overwrites FinDesk Phase 2 carry-forward balances.

## Director Sprint 2026-06-03 - Yacht Template MVP

Status: local implementation complete; production deploy not performed.

Primary report:

- `docs/AI_TEAM/74_YACHT_TEMPLATE_MVP_LOCAL_2026-06-03.md`

Done locally:

- added `Yacht Template` product screen;
- `Templates -> Yacht` opens the Yacht screen;
- menu includes `Templates -> Yacht`;
- yacht profile card supports name, marina, berth, customer contact, registration/model/hull/size/year/logo and technical fields;
- quiet fallback logo text is `Vetus Nauta`;
- crew role presets added for captain and crew;
- yacht workspace creation creates a normal FinDesk group named `Yacht: <name>`;
- separate `Bunkering / starter package` calculator added;
- rows support quantity, unit, approximate price, selection and total;
- prices can be hidden before printing;
- print mode prints the yacht work order area only.

Checks passed:

- JS syntax;
- service worker syntax;
- diff check;
- local HTTP app check.

Deferred:

- dynamic regional price library;
- learning filters over historical entries;
- service work orders from yacht technical fields;
- backend persistence across devices.

## Director Sprint 2026-06-03 - Yacht Bunkering Order

Status: local implementation complete; production deploy not performed.

Primary report:

- `docs/AI_TEAM/75_YACHT_BUNKERING_ORDER_LOCAL_2026-06-03.md`

Done locally:

- `Bunkering / starter package` now has modes `Все / Еда / Топливо / Техника`;
- mode tabs filter rows without deleting hidden rows;
- total package amount always counts all enabled rows;
- selected section total is shown separately;
- local reference price presets added as optional hints;
- reference prices are disabled by default and must be explicitly applied;
- applied prices remain editable manually;
- quick row buttons added for food, fuel and technical positions;
- fuel rows use liters and price per liter.

Guardrail:

- bunkering prices and package totals remain outside Live Journal, reports and archive accounting.

## Director Sprint 2026-06-03 - Yacht Price Zones

Status: local implementation complete; production deploy not performed.

Primary report:

- `docs/AI_TEAM/76_YACHT_PRICE_ZONES_LOCAL_2026-06-03.md`

Done locally:

- yacht reference prices are split into zones:
  - Europe base;
  - Adriatic / Balkans;
  - Western Mediterranean;
  - USA coastal states;
  - Asia marina hubs;
  - Caribbean islands;
- added catalog version `2026-06-03-local-zones1`;
- added manual `Обновить справочник` button;
- manual update records local version/date metadata;
- manual update does not overwrite order rows;
- applying selected-zone prices remains a separate explicit action.

Guardrail:

- external live price integration is deferred until a controlled server-side provider/source exists.

## Director Task 2026-06-02 - Phase 3 Product Identity / UX Validation

Status: queued after Phase 2 authenticated QA.

Primary source:

- `docs/AI_TEAM/61_PHASE3_PRODUCT_IDENTITY_UX_VALIDATION_2026-06-02.md`

Source package:

- `https://drive.google.com/file/d/1S11kHRLFfb5yidEDNpGO5hsBd6-UtlZa/view?usp=drive_link`

Meaning:

- do not invent a new FinDesk;
- do not redesign business logic;
- express the approved product in one coherent UX;
- remove old interface remnants before physical QA;
- validate on Desktop, iPhone, Android and iPad.

Gate:

- Phase 3 starts only after the Phase 2 local implementation passes authenticated QA.

## Director Task 2026-06-02 - Phase 2 Logic / Navigation / Engine Gate

Status: authenticated local API QA passed; physical UX QA pending.

Primary audit and working blueprint:

- `docs/AI_TEAM/59_PHASE2_LOGIC_NAV_ENGINE_AUDIT_2026-06-02.md`

Source packages:

- Logic: `https://drive.google.com/file/d/1HGyjkl0Dv6aU8OjdjN5fw2mWrK9KxjFu/view?usp=sharing`
- Navigation/localization: `https://drive.google.com/file/d/1s1dRKCxUWUwqdqoUsiFU-NX-D_JgHyTS/view?usp=sharing`

Current decision:

- Sprint 0, Sprint 1 and Sprint 2 gate are documented;
- implementation is not started yet;
- physical QA is blocked until old visible navigation is removed and Phase 2 hierarchy is visible.

Role task cards:

- Product Finance Architect: `docs/AI_TEAM/roles/01_product_finance_architect/TASK_CARD_PHASE2_LOGIC_2026-06-02.md`
- Backend Data Engineer: `docs/AI_TEAM/roles/02_backend_data_engineer/TASK_CARD_PHASE2_ENGINE_2026-06-02.md`
- Frontend UX Engineer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASK_CARD_PHASE2_NAVIGATION_2026-06-02.md`
- QA Release Engineer: `docs/AI_TEAM/roles/04_qa_release_engineer/TASK_CARD_PHASE2_QA_2026-06-02.md`
- Chief Auditor: `docs/AI_TEAM/roles/05_chief_auditor/TASK_CARD_PHASE2_GATE_2026-06-02.md`

Next implementation direction after approval:

- local sprint report: `docs/AI_TEAM/60_PHASE2_IMPLEMENTATION_SPRINT_LOCAL_2026-06-02.md`;
- authenticated API QA report: `docs/AI_TEAM/62_PHASE2_AUTHENTICATED_API_QA_2026-06-02.md`;
- additive schema/API patch for transfers, workspace preference, report assembly and protected actions is implemented locally;
- Phase 2 shell with Back stack, compact menu, language and logout is implemented locally;
- Cash/Card intermediate screen before Live Journal is implemented locally;
- old modules are removed from the visible normal product menu locally;
- next required step is browser/physical UX QA before production deploy.

## Director Task 2026-06-02 - Phase 1 Functional Blueprint Reset

Status: opened as the current FinDesk product task; this overrides patch-first work.

Primary task card:

- `docs/AI_TEAM/51_PHASE1_FUNCTIONAL_BLUEPRINT_MANDATE_2026-06-02.md`

Why this is current:

- CEO provided a new functional blueprint package and ordered product rethinking;
- the current live FinDesk result was rejected as a mixed, non-product screen;
- the next correct step is a screen-by-screen rebuild, not continued patching.

Current target:

- functional cleanup first;
- no style-first redesign;
- preserve auth/backend/DB/PWA foundations;
- start with `Live Journal`;
- then rebuild team flow, admin card, employee card, report assembly, and reports.
- do not continue implementation until the mandatory alignment patch of 2026-06-02 is reflected in the working blueprint, audit summary, and QA checklist.

Still pending:

- local functional prototype;
- role findings from Product, Frontend, Backend, QA, Auditor;
- exact reuse map of current APIs and states;
- production rollback or replacement decision after local approval.
- mandatory alignment of:
  - pending transfer confirmation flow;
  - separate card/non-cash stream definition;
  - people-first Team Workspace;
  - final Employee Card layout;
  - final report `Cash / Card / Total` structure;
  - QA checklist for these rules.

## Director Task 2026-06-01 - FinDesk Active Session Rebuild

Status: still relevant as a product idea, but no longer the main working source.

Primary task card:

- `docs/AI_TEAM/49_FINDESK_ACTIVE_SESSION_REBUILD_TASK_2026-06-01.md`

Why this is current:

- the older `48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md` is now treated as an intermediate board rebuild, not the final product target;
- CEO clarified the next FinDesk model around active sessions, one report per participant per session, one admin report, one final summary object, and no stale session noise on the active surface.

Current target:

- active-session-first FinDesk;
- card-button outside, full-page work area inside;
- administrator card plus participant cards;
- pending issue confirmation state, signed issue state, review/return/approve flow;
- one immutable archived summary object per closed session.

Still pending:

- product formalization of session semantics;
- backend support map for active session identity and one-report-per-session rules;
- frontend implementation sprint;
- QA matrix for active session, confirmation, parallel sessions, and archive transition.

## Director Sync 2026-06-02 - Handoff And GitHub

Status: ready for GitHub sync.

Latest start handoff:

- `docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-02.md`

Office:

- `docs/AI_TEAM/OFFICE_DASHBOARD.html`

Production frontend:

- correct host: `https://finance.brkovic.ltd/app.php`;
- latest asset version: `20260601-findesk-mobilefit2`;
- uploaded files: `public/app.php`, `public/assets/app.css`, `public/assets/app.js`, `public/service-worker.js`;
- production HTTP checks passed for app shell, CSS asset, and service worker;
- Playwright mobile start-screen smoke passed after top brand-pill correction.

Important boundary:

- local backend/open-items candidate from reports `45-46` is in source, but production DB migration/package-export rollout must not be treated as deployed until a separate backup, DB preflight, migration, smoke, and deploy report are completed.
- physical scanner/PWA camera readiness remains open until real-device evidence exists.
- authenticated production QA after login is still needed for the final `mobilefit2` FinDesk screen.

## Director Sprint 2026-05-28 - FinDesk Board Rebuild

Status: local implementation complete; QA/browser pass pending.

Evidence:

- `docs/AI_TEAM/48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md`

Done locally:

- FinDesk board rebuilt around administrator card, child reports, employee cards, and top cash strip.
- Other modules moved under one `Детали` menu while keeping `Живой отчет` and `FinDesk` as first-level actions.
- Employee submitted cards render as orange-highlighted working cards.
- Approve/return/finalize buttons reuse existing API/actions; financial formulas and backend architecture were not changed.

Pending:

- authenticated browser QA on mobile/tablet/desktop;
- production deploy package and post-deploy smoke;
- no physical deletion for the `20 cards` rule until retention policy is confirmed.

## Intake 2026-05-26

- Project Director accepted the 2026-05-26 handoff.
- Baseline: `HEAD=72b38e6`, `origin/main=72b38e6`, working tree is dirty with important local work.
- Smoke command is blocked in the current shell because `php` is not available; local server at `http://127.0.0.1:18889` responds `200 OK`.
- All five specialist cabinets exist under `docs/AI_TEAM/roles/`.
- Real specialist chat links/ids are not attached yet; `CHAT_LINKS.md` requests CEO-provided links.

## First Cycle 2026-05-26

1. Product Finance Architect: define glossary and expected open-period vs historical-report output.
2. Backend Data Engineer: verify final report fixation, carryover separation, card zero cash delta, and group scope defaults.
3. Frontend UX Engineer: prepare screen-responsibility and responsive UX pass after terms/data are clear.
4. QA Release Engineer: create release test plan and formalize the `€1000 -> €600 -> €400 carryover` scenario.
5. Chief Auditor: review role outputs, contradictions, risk register, and release gate.

## Director Workstreams 2026-05-26

Root decision: FinDesk must show a non-accountant where money is and why each number is trustworthy.

| Direction | Owner | Output |
| --- | --- | --- |
| Money meaning | Product Finance Architect | human glossary and `€1000 -> €600 -> €400` money map |
| Data truth | Backend Data Engineer | endpoint/data mapping to money places and states |
| Human screen | Frontend UX Engineer | first-screen and menu responsibility proposal |
| Evidence | QA Release Engineer | ordinary-person verification scenario |
| Gate | Chief Auditor | contradiction list and release-blocking risks |
| Instant field capture | Product + Frontend + Backend + QA | capture-now/review-later workflow for people in movement |

Backlog source:

- `docs/AI_TEAM/06_CEO_IDEAS_REGISTRY.md`

MVP finish line:

- `docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md`

## MVP Gate 2026-05-26

Status: approved for MVP foundation by Chief Auditor.

Scope:

- This is MVP foundation approval, not the complete CEO business MVP and not a declaration of full accounting-platform release.
- No unresolved P0 blocker remains for the MVP money-tree path.
- The foundation cycle is stopped by `07_MVP_EXIT_CRITERIA.md`.
- The broader CEO business MVP is tracked in `10_BUSINESS_MVP_SCOPE.md`.
- New work enters post-MVP unless it fixes a P0 blocker.

Evidence:

- instant field capture QA run `20260526141856`;
- backend current/historical contract recheck `group_id=195`, `report_id=371`;
- UI current/historical report QA `group_id=200`, `report_id=406`;
- Chief Auditor gate files under `docs/AI_TEAM/roles/05_chief_auditor/`.

Next owner:

- Project Director: convert CEO business MVP scope into role-owned tasks.
- Deployment is not automatic from this gate; foundation deploy would be internal alpha unless CEO explicitly decides otherwise.

## Practical Work 2026-05-26

- Created `docs/AI_TEAM/06_CEO_IDEAS_REGISTRY.md` from old handoff/knowledge notes.
- Started first slice: instant field capture in Live Report.
- Added quick actions near the Live Report note area:
  - `+ Получили`
  - `- Наличные`
  - `- Карта`
  - `Фото`
  - `Подотчет`
- `Подотчет` routes to the accountable-money screen instead of creating an expense row.
- No backend/API/formula change was made in this slice.
- Director browser check passed for mobile cash/card quick strip visibility and line insertion.
- QA Release Engineer verified the assigned instant field capture slice on mobile/tablet/desktop, run id `20260526141856`.
- QA pass covers saved-card reopen, exact rows, delete from opened card, proof picker, `Подотчет` navigation, card/cash separation, review gate, physical-cash separation, and cash sequence guard.
- Chief Auditor approved the assigned instant field capture slice only.
- Full release remains blocked: broader QA still needs carryover/export/archive coverage and final Chief Auditor gate.
- Backend Data Engineer traced carryover/export/archive data path.
- Open-period carryover/export path exists for `€1000 -> €600 -> €400`: current period can start from `€400` carryover instead of old `€1000` income.
- Release blocker found: historical finalized report is not exposed as a first-class immutable report/export source; raw evidence exists, but the old report cannot be exported through a dedicated finalized-report action after export switches to open-period mode.
- Product Finance Architect confirmed release requires a dedicated historical finalized report/export action.
- Approved labels: `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, `Экспорт финального отчета`.
- Backend Data Engineer implemented a historical finalized report/export backend patch.
- New backend actions: `ledger_group_final_report_list`, `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, `ledger_group_final_report_excel`.
- New finalizations store `report_snapshot` in `audit_log.details` and return `report_id`; old finalizations without snapshot return `historical_snapshot_missing`.
- Director verification: local HTTP server responds `200`, `/api.php?action=current_user` responds `200`, `git diff --check` is clean; CLI PHP remains unavailable in this shell.
- QA Release Engineer verified historical finalized report snapshot/export works for new finalizations.
- QA blocker found: current open-period export loses post-finalization income when a current included Live Report also exists.
- Evidence: group `192`, report `348`, current income ledger entry `84`, current Live Report tape `175`; `ledger_group_open_received_funds.entries` returned `{"id":175}` instead of the current income row.
- Suspected backend cause: PHP reference leak in `ql_ledger_group_open_received_funds` after `foreach ($rows as &$row)` before later reuse of `$row`.
- Backend Data Engineer fixed the reference leak by adding `unset($row)` after the by-reference loop.
- Backend/Data HTTP/API verification passed on fixture `group_id=194`, `report_id=364`, current income entry `88`, current Live Report tape `181`.
- Additional P1 hardening risk: same-second `le.created_at > finalized_at` cutoff can exclude income created in the exact same DB second as finalization.
- QA Release Engineer reran the combined regression scenario and accepted the P0 combo blocker as fixed.
- QA pass evidence: `group_id=195`, `report_id=371`, current income ledger entry `90`, current Live Report tape `184`.
- Current export now contains carryover `400`, current income `50`, current Live Report expense `25`, and excludes old finalized income `1000`.
- Historical detail/export remained `1000 / 600 / 400`.
- New office rule added: full role reports stay in role folders; CEO / Project Director chat receives only short reports.
- MVP exit criteria added. The current MVP stops after Frontend/UX wiring or confirmation, QA pass on the user-facing current/historical report flow, and Chief Auditor MVP gate.
- Frontend UX Engineer task issued for current vs historical report UI actions.
- Frontend UX Engineer implemented UI separation for `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, and `Экспорт финального отчета`.
- Current export remains wired to group endpoints; historical export uses `report_id` endpoints.
- Director verification: `node --check public/assets/app.js` passed; `git diff --check` passed.
- QA Release Engineer verified current/historical report UI on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.
- QA pass evidence: `group_id=200`, `report_id=406`, current income entry `100`, current Live Report tape `199`.
- No blocker from UI QA; current export stayed current-period truth and historical export stayed `1000 / 600 / 400`.
- Reporting rule strengthened with exact `SHORT_REPORT_TEMPLATE.md`.
- Chief Auditor approved MVP gate after Product/Backend/Frontend/QA pass.
- Chief Auditor evidence pointer: instant `20260526141856`, backend `group_id=195/report_id=371`, UI `group_id=200/report_id=406`.
- No P0 blocker remains for MVP; legacy snapshot fixture and same-second cutoff are P1/post-MVP.
- Next owner: Project Director for MVP release package and handoff.
- MVP deploy handoff added in `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`.
- CEO corrected business MVP scope: fixation, analysis, report submission, save/print, group report consolidation, save/print, archive, participant groups, and money flows into one common group pot.
- Business MVP scope added in `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`.
- CEO added legacy product modules that must remain in scope: group messages, travel equalization, and business solutions.
- Live site confirms three product layers: On the Go, FinDesk, Advanced.
- CEO emphasized that mobile convenience is critical for multitasking finance work on small screens.
- Mobile multitasking research pack added in `docs/AI_TEAM/11_MOBILE_MULTITASKING_RESEARCH.md`.
- CEO clarified `Advanced`: everything outside business MVP goes there.
- CEO clarified field combat mode as foundation: write, photo, scan, automatic calculation, continuous saving, no loss in unfinished sessions.
- Field combat mode rule added in `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`.
- Product Finance Architect accepted `Advanced = non-MVP staging` and `Field Combat Mode = MVP foundation`.
- Product evidence pointer: `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md:73`, `STATUS.md:138`, `TASKS_TO_OTHERS.md:110`.
- Field Combat Mode no-data-loss evidence remains a business-MVP blocker until Backend/Data, Frontend/UX, QA, and Chief Auditor close it.
- Backend/Data, Frontend/UX, QA, and Chief Auditor follow-up handoffs were added in their role folders.
- Backend/Data completed Field Combat Mode backend/API/storage trace and marked business MVP `P0 blocked`.
- Backend evidence: `group_id=201`, `cash_tape_id=200`, `cash_capture_id=158`, `card_tape_id=201`.
- Backend blocker: typed facts before successful save are not durable; proof failed/pending/retry state is not durable.
- Next active owner: Backend Implementation Queue for durable Field Combat draft/sync model and durable proof upload state.
- Backend/Data implemented durable Field Combat draft/sync/proof-state backend patch.
- Backend durable evidence: `group_id=202`, `draft_id=1`, `tape_id=202`, `session_id=142`, `capture_id=160`.
- New backend APIs include `on_the_go_field_draft_save`, `on_the_go_field_recover`, `on_the_go_proof_state_begin`, `on_the_go_proof_state_fail`, and `on_the_go_proof_state_list`.
- Release gate still waits for Frontend/UX wiring and QA refresh/upload-failure recovery evidence.
- Frontend/UX implemented Field Combat UI autosave/proof-state wiring for the active `Живой отчет` simple editor.
- Frontend/UX evidence pointer: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`, section `2026-05-26 Field Combat UI autosave/proof-state wiring`.
- Director verification: `node --check public/assets/app.js` passed; `git diff --check` passed for frontend/doc touched files; local HTTP server responds `200 OK`.
- QA Release Engineer ran Field Combat UI browser/HTTP no-data-loss QA and blocked the slice as P0.
- QA evidence: run `20260526264416`, viewports `390x844`, `820x1180`, `1440x900`, groups `204/205/206`.
- Blocker: after typing `-25 Durable autosave row ...` and seeing `Сохранено`, refresh/return opens an empty editor.
- Backend returns the old `client_draft_id` data, but UI replaces localStorage with a new empty draft: mobile draft `8 -> 14`, tablet `16 -> 20`, desktop `22 -> 27`.
- Next active owner: Frontend UX Engineer for Field Combat draft recovery identity fix.
- Business MVP release gate remains blocked until QA reruns refresh/return, proof failure/retry, idempotent save retry, and cash/card no-data-loss checks.
- Frontend/UX implemented the Field Combat draft recovery identity fix in `public/assets/app.js`.
- Frontend/UX root cause: stream gate path reset draft identity before backend recovery, replacing the durable `client_draft_id` with a new empty draft.
- Director verification: `node --check public/assets/app.js` passed; `git diff --check` passed for Frontend/UX changed files.
- Next active owner: QA Release Engineer for browser/HTTP rerun of the blocked no-data-loss scenario.
- QA Release Engineer reran Field Combat draft recovery identity QA: run `20260526109674`, groups `210/211/212`.
- Old empty-draft recovery blocker is fixed on mobile/tablet/desktop.
- New P0 blocker: after proof upload failure and refresh, proof retry duplicates the same cash row into the previous `next_tape_id`.
- Evidence: mobile original tape `227` row `167` plus retry tape `226` row `168`; tablet original tape `232` row `170` plus retry tape `231` row `171`; desktop original tape `237` row `173` plus retry tape `236` row `174`.
- Next active owner: Frontend UX Engineer for proof retry duplicate-money fix.
- Frontend/UX implemented the proof retry duplicate-money fix in `public/assets/app.js`.
- Frontend/UX root cause: proof retry reused the full signed save path after active context could move to `next_tape_id`; retry could send the same money row again instead of proof-only retry.
- Director verification: `node --check public/assets/app.js` passed; `git diff --check` passed for Frontend/UX changed files.
- Next active owner: QA Release Engineer for proof failure + refresh + retry browser/HTTP rerun.
- QA Release Engineer reran proof retry duplicate-money QA and passed the P0 recheck.
- QA evidence: run `20260526929348`, groups `218/219/220`, original rows `176/178/180`, previous `next_tape_id` cards `252/258/264` clean.
- QA confirmed proof retry attached proof to the original saved rows, did not create duplicate money rows, and did not submit/include/finalize.
- Next active owner: Chief Auditor for Field Combat no-data-loss gate review.
- Chief Auditor approved the Field Combat no-data-loss gate for the verified foundation scope only.
- Auditor evidence pointer: run `20260526929348`, groups `218/219/220`, rows `176/178/180`; details in `docs/AI_TEAM/roles/05_chief_auditor/FIELD_COMBAT_NO_DATA_LOSS_GATE_2026-05-26.md`.
- Boundary: full business MVP is not approved; group report consolidation, archive, participants/common pot, messages, production deploy, and broader scope remain separate gates.
- Next active owner: Backend Data Engineer for business-MVP group report/archive/common-pot data trace.
- Backend/Data completed the business-MVP group report/archive/common-pot trace and blocked full business MVP.
- Backend blocker: no single immutable archive/package by group `report_id` that contains the closed group report plus linked participant reports, captures, proofs, accountable/advance state, audit references, and report-context messages.
- Backend trace found partial support: group final report snapshot/export exists for prepared rows/totals, but archive/package evidence is fragmented across group final report, Live Report cards, file endpoints, advances, journal, and group messages.
- Next active owner: Product Finance Architect for the product contract of the immutable group report archive package before Backend Implementation Queue.
- Product Finance Architect defined the business-MVP contract for `Закрытый групповой отчет`.
- Product decision: business MVP requires one immutable closed group report package by `report_id`; summary/export alone is not enough.
- Required package contents: group identity/summary, participant report snapshots, captures/money rows, proof index and authorized proof access, accountable/advance state, report-context messages, and audit/finalization references.
- Product boundary: legacy migration, ZIP proof bundle, full journal dump, notarization/hash chain, fraud scoring, full social chat archive, travel engine, Business Desk integration, and deep dashboards stay post-MVP/Advanced.
- Next active owner: Backend Implementation Queue for immutable group report archive package source.
- Backend/Data implemented the closed group report archive package source for new finalizations.
- New backend actions: `ledger_group_final_report_package` and `ledger_group_final_report_proof_download`.
- Backend evidence: HTTP fixture `group_id=221`, `report_id=441`, `proof_id=proof-441-on_the_go_capture-12`, `advance_id=65`.
- Package source includes group summary, participant report snapshots, captures, proof index/download access, accountable/advance state, messages/audit references, and export action metadata.
- Backend known follow-up: report-context messages are audit-derived until message schema gets direct report/capture/advance links; package-wide print/export file is not a new backend export yet.
- Director verification: backend `git diff --check` passed; local server returned `200 OK`; CLI PHP remains environment-blocked.
- Next active owner: Frontend UX Engineer for opening `Закрытый групповой отчет` as one ordinary archive object.
- Frontend/UX implemented the `Закрытый групповой отчет` package UI.
- Frontend package view opens by `report_id` through `ledger_group_final_report_package` and is not summary-only.
- Frontend package sections include summary, participant reports, captures/proofs, money rows/proofs, accountable/advance state, report-context messages, general unlinked group chat refs, and audit refs.
- Proof links use package metadata/download URLs; package print/PDF is available; Excel/Google are explicitly labeled as short final-report tables.
- Director verification: `node --check public/assets/app.js` passed; `git diff --check` passed for Frontend/UX changed files.
- Next active owner: QA Release Engineer for multi-participant `Закрытый групповой отчет` package UI/API verification.
- QA Release Engineer passed the `Закрытый групповой отчет` package UI/API verification.
- QA evidence: fresh fixture `group_id=222`, `report_id=454`, admin `520`, member `521`; screenshots `/tmp/findesk-closed-package-20260527816949-*`.
- QA verified package API, authorized proof downloads, cash/card split, accountable carryover, short-table Excel/Google labels, print/PDF, immutability after later current-period activity, and mobile/tablet/desktop package UI.
- QA opened no new Backend/Data or Frontend/UX task from this pass.
- Next active owner: Chief Auditor for `Закрытый групповой отчет` business-MVP gate.
- Chief Auditor approved the `Закрытый групповой отчет` package gate for the verified package scope only.
- Auditor evidence pointer: QA run `20260527816949`, `group_id=222`, `report_id=454`; details in `docs/AI_TEAM/roles/05_chief_auditor/CLOSED_GROUP_REPORT_PACKAGE_GATE_2026-05-27.md`.
- Group report/archive package business-MVP block is closed for the verified new-package flow.
- Accepted follow-ups outside this gate: package-wide downloadable file export beyond browser print/PDF, first-class report-linked message schema, and legacy reports without `report_package`.
- Next active owner: Project Director for final business-MVP readiness review and remaining-scope decision.
- Project Director classified remaining readiness items in `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`.
- Money-core loop and closed package are materially proven for new data.
- Remaining business-MVP P0 before full approval: residual surface QA for group messages, Business Desk/proforma, Travel/Advanced staging, and complete mobile/tablet/desktop navigation reachability.
- Deployment package/production smoke remains a separate production P0 after product readiness.
- Next active owner: QA Release Engineer for residual surface QA.
- QA Release Engineer passed business-MVP residual surface QA.
- QA evidence: run `20260527968710`, group `222`, report `454`.
- Residual QA verified group messages send/list/unread/mark-read and group scope, `Закрытый групповой отчет` message references, Business Desk/proforma create/list/open/print without ledger mutation, Travel/Advanced staging, and mobile/tablet/desktop reachability.
- Next active owner: Chief Auditor for final full business-MVP gate.
- 100 percent MVP control opened in `docs/AI_TEAM/15_100_PERCENT_MVP_CONTROL.md`.
- Background roles launched for final gate and deploy readiness: Chief Auditor, Backend/Data, Frontend/UX, QA Release Engineer.
- Production deploy remains blocked until file selection, DB/runtime migration plan, backup/rollback, and production smoke are ready.
- Chief Auditor approved the full business-MVP product gate.
- Business MVP product gate is closed for the checked new-data path.
- 100 percent MVP now depends on production deploy gate only.
- Project Director selected narrow MVP runtime bundle and recorded production no-go in `docs/AI_TEAM/19_PRODUCTION_GO_NO_GO_2026-05-27.md`.
- Production upload remains blocked because DB preflight/backup/migration and rollback evidence are not complete from this environment.
- SEO Growth Engineer role created and SEO/Growth strategy completed in `docs/AI_TEAM/21_SEO_GROWTH_STRATEGY.md`.
- Frontend/PWA SEO implemented public landing technical SEO/PWA metadata without changing private app/backend/formulas.
- Backend/Infra SEO check completed; production NO-GO remains because DB/backup/deploy controls are still missing.
- QA SEO checklist created; local SEO/PWA QA is active.
- QA SEO local non-visual checks passed: local HTTP 200 for `/`, SEO files, manifest, service worker, `/app.php`, and assets; meta/JSON-LD/robots/sitemap/manifest/service-worker checks passed; `node --check public/assets/app.js`, `node --check public/service-worker.js`, and `git diff --check` passed.
- QA SEO release acceptance remains blocked by environment/production controls: no local Playwright/browser for mobile `390x844` visual overlap check, no PHP CLI for `php -l`, and production deploy remains NO-GO until DB/files backup and rollback controls.
- Shared Brkovic SEO Office created at `/home/alexey/GitHub/BRKOVIC_SEO_OFFICE`.
- Brkovic SEO Knowledge Architect launched to create common SEO knowledge base for `finance.brkovic.ltd`, `game.brkovic.ltd` with Captain Ether / Watch Officer, and main `brkovic.ltd`.
- Brkovic SEO Knowledge Architect completed first working shared SEO knowledge base.
- Shared SEO start file: `/home/alexey/GitHub/BRKOVIC_SEO_OFFICE/00_START_HERE.md`.
- Project briefs now exist for FinDesk, game.brkovic.ltd, and main brkovic.ltd.
- Game repos are now attached, pushed, and synchronized to GitHub HEAD: `/home/alexey/GitHub/captain-ether` at `4502b10`, `/home/alexey/GitHub/watch-officer` at `c022390`.
- Game SEO Growth Engineer cabinet created in shared SEO Office; first game repo SEO audit completed and tasks assigned to Game Owner / Game Director.
- FinDesk MVP local runtime artifact built: `findesk-mvp-runtime-20260527T185423Z`; upload remains blocked by production backup/preflight controls.
- Production files/storage backup completed by read-only FTP: `prod-files-before-mvp-20260527T185902Z`, 110 files, checksum `b095d4c6c8cf35ac0fbc76657fd7653d4757596b58944c81a13325122b1c8823`; DB backup/preflight remains the upload blocker.
- Next strict owner card created: Project Director / Deploy Owner / Database Migration Owner must complete `docs/AI_TEAM/26_NEXT_DEPLOY_TASK_CARD_2026-05-27.md` before any production upload.
- Production deploy completed with final artifact `findesk-mvp-runtime-20260527T192800Z`.
- Production DB backup, schema preflight, runtime SQL application, and schema hardening completed.
- Production HTTP/API smoke passed: smoke id `20260527192655`, group id `4`, final report id `20`.
- CEO opened production physical QA scenario: one admin, three employees, accountable cash, individual reports, exports, final group package, and archive check.
- Product Finance Architect expected control recorded in `docs/AI_TEAM/roles/01_product_finance_architect/PRODUCTION_MULTI_EMPLOYEE_FINANCIAL_CONTROL_2026-05-27.md`.
- QA Release Engineer task card recorded in `docs/AI_TEAM/roles/04_qa_release_engineer/TASK_CARD_PRODUCTION_MULTI_EMPLOYEE_2026-05-27.md`.

## P0

Status: no unresolved P0 for foundation gate as of Chief Auditor approval on 2026-05-26.

Business MVP P0 is reopened for scope completion.

Closed for MVP:

- Product Finance Architect approved current/historical money meanings and labels.
- Backend Data Engineer implemented and verified historical finalized report/export by `report_id`.
- Backend Data Engineer fixed the current export combo regression.
- Frontend UX Engineer wired current vs historical report/export actions.
- QA Release Engineer verified instant capture, backend contract, combo regression, and UI flow.
- Chief Auditor approved MVP gate.

Active P0:

- no unresolved production P0 gates after rerun.

Recently closed by Project Director / Backend Data Engineer:

- Participant-control patch for production physical multi-employee money-flow QA scenario.
- Local HTTP fixture `group_id=223`, `report_id=499` passed.
- Production hotfix deployed for `app/ledger.php` and `public/assets/app.js`.
- Director production smoke fixture `group_id=9`, `report_id=84` passed.
- Default base employee rights hotfix deployed for `app/on_the_go.php`, `app/messages.php`, `public/app.php`, and `public/assets/app.js`.
- Director production rights smoke fixture `group_id=10`, employee user `27` passed.
- QA accepted participant-control in production recheck: `group_id=17`, `report_id=176`.
- Backend fixed and deployed `message_unread` alias hotfix for base employee rights.
- Director production message-unread smoke fixture `group_id=19`, employee user `57` passed.
- QA Release Engineer passed production base-rights rerun (run `20260527212947`, group `20`, report `194`, base user `59`): `message_unread` safe, group data and write blockers preserved.
- Frontend/UX closed local production leftovers for next package: login fallback/cache versions, `Живой отчет` state persistence, mobile card/action overlap hardening, scanner `Закрыть`, and Escape close.
- Backend/Data fixed safe test-group soft archive: `group_delete` now works without optional `updated_at` columns, preserves financial evidence, denies base/non-admin users, and is idempotent.
- QA Release Engineer passed formal local recheck `20260528LOCALLEFTOVERS01`: login H1, scanner close controls, `ontherun` state persistence, and `group_delete` fixture `group_id=233` passed.

## Release / Deploy

Current owner: Project Director.

Status:

- MVP gate is approved.
- Foundation gate is approved.
- Business MVP product gate is complete.
- Production deploy is executed from the Director chat.
- Working tree contains broad pre-existing changes outside the final MVP gate path.
- The full dirty tree was not deployed blindly; final deployed artifact is `findesk-mvp-runtime-20260527T192800Z`.
- Next limited scanner/UX/backend package is locally improved and QA-rechecked, but production deploy remains NO-GO until the deploy checklist blockers are closed.

Next steps:

- CEO live review on real mobile device.
- Project Director background watch is active for the scanner device gate: PID `100620`, interval `10 minutes`, log `/tmp/findesk-director-watch-20260528.log`. It monitors `33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md` and QA role files until real-device scanner evidence arrives. Previous shell-sleep watcher PID `100117` stopped after heartbeat stalled.
- Project Director / Deploy Owner: close `docs/AI_TEAM/roles/04_qa_release_engineer/DEPLOY_READINESS_CHECKLIST_2026-05-28.md` before any next production upload.
- Project Director created next limited deploy candidate: `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`. Status: local PASS, production NO-GO until scanner device gate or limited-release decision, PHP/smoke, DB preflight, backup/rollback, and production smoke are closed.
- Project Director opened deploy-preflight sprint: `docs/AI_TEAM/35_DEPLOY_PREFLIGHT_SPRINT_2026-05-28.md`.
- Read-only DB preflight SQL prepared: `docs/AI_TEAM/36_DB_PREFLIGHT_READONLY_SQL_2026-05-28.sql`.
- Limited candidate 34 local artifact built: `backups/findesk-limited-candidate34-20260528T134812Z/findesk-limited-candidate34-20260528T134812Z.tar.gz`, SHA256 `a159c4000a580db314981529bdb3812dbed953b18b93dd9148b2e9d60f7cffd9`.
- Director hardened `public/api.php` optional AI dependency: missing `app/ai.php` no longer fatals the whole API; `ai_analysis_run` returns `ai_unavailable` if the optional module is excluded.
- Candidate 34 limited scanner/UX/backend package deployed to production after DB backup, full FTP backup, runtime SQL, and production smoke.
- Deploy report: `docs/AI_TEAM/38_CANDIDATE34_PRODUCTION_DEPLOY_REPORT_2026-05-28.md`.
- Production smoke passed: run `prod-candidate34-20260528140302`, group `24`, final report `218`, scanner bundle `c34-scanner-20260528140302`.
- Temporary DB-gate was removed after use and returned `404`.
- Real-device scanner/PWA camera gate remains open; do not claim scanner is device-ready yet.
- Owner self-return hotfix deployed for stuck legacy submitted Live Report cards where the owner has no active moderator to ask.
- Hotfix report: `docs/AI_TEAM/39_OWNER_SELF_RETURN_HOTFIX_PRODUCTION_2026-05-28.md`.
- Hotfix smoke passed: run `prod-owner-self-return-20260528140915`, group `25`, tape `84`.
- Proof links hotfix deployed: PDFs/scans linked to Live Report rows are now visible as row-level download links, and permitted group admins can open employee proof files.
- Hotfix report: `docs/AI_TEAM/40_PROOF_LINKS_HOTFIX_PRODUCTION_2026-05-28.md`.
- Hotfix smoke passed: run `prod-proof-links-20260528153719`, group `26`, tape `87`, capture `145`, file `9`.
- Proof viewer hotfix deployed: row proof controls now open an in-app photo/PDF viewer instead of relying only on a new-tab link.
- Hotfix report: `docs/AI_TEAM/41_PROOF_VIEWER_HOTFIX_PRODUCTION_2026-05-28.md`.
- Hotfix smoke passed: run `prod-proof-viewer-20260528154804`, group `28`, tape `91`, capture `148`, image file `14`, PDF file `15`.
- QA Release Engineer found P0 records-page blocker: permitted group admin could open employee proof card by direct detail/API, but the normal records page did not discover the employee card.
- Frontend/UX fixed the records page locally: group admins now load records with active `group_id`; proof viewer direct-open link no longer forces download; mobile overflow hardening added.
- Local smoke passed: group `235`, admin tape `307`, employee tape `308`; admin sees both group cards, base employee sees only own card.
- Project Director local Playwright mobile smoke passed after stream-gate fix: group `244`, employee tape `332`, capture `217`, proof controls `2`; records list/card/proof viewer path works and the stream gate no longer intercepts clicks.
- QA Release Engineer passed P0 browser recheck for records-page discovery/proof viewer: run `20260528RECORDSRECHECK04`, blocker none.
- Records admin discovery hotfix deployed to production for `public/app.php`, `public/assets/app.js`, and `public/assets/app.css`.
- Hotfix report: `docs/AI_TEAM/42_RECORDS_ADMIN_DISCOVERY_HOTFIX_PRODUCTION_2026-05-28.md`.
- Production smoke passed: run `prod-records-hotfix-20260528161828`, group `36`, employee tape `112`, capture `157`, image file `30`, PDF file `31`.
- Temporary DB-gate was removed after use and returned `404`.
- Project Director audited the notes-style `Живой отчет` editor and found scanner modal overflow on phone `390x844`.
- Scanner fit CSS hotfix deployed to production for `public/app.php` and `public/assets/app.css`.
- Hotfix report: `docs/AI_TEAM/43_SCANNER_FIT_HOTFIX_PRODUCTION_2026-05-28.md`.
- Production smoke passed: run `prod-scanner-fit-20260528162815`; notes field `578px`, scanner modal `390x844`, controls reachable.
- Temporary DB-gate was removed after use and returned `404`.
- CEO reported impossible scrolling in the `Живые отчеты` records window when the card column is long.
- Records scroll CSS hotfix deployed to production for `public/app.php` and `public/assets/app.css`.
- Hotfix report: `docs/AI_TEAM/44_RECORDS_SCROLL_HOTFIX_PRODUCTION_2026-05-28.md`.
- Production smoke passed: run `prod-records-scroll-20260528164351`; mobile list `clientHeight=621`, `scrollHeight=3183`, `scrollTop=2562`; desktop `scrollTop=3180`.
- Temporary DB-gate was not used and stayed `404`.
- Project Director opened and completed a local sprint for the remaining open items.
- Local sprint report: `docs/AI_TEAM/45_OPEN_ITEMS_SPRINT_LOCAL_2026-05-28.md`.
- Local asset candidate: `20260528-open-sprint1`.
- Implemented locally: first-class group message context fields (`report_id`, `tape_id`, `capture_id`, `advance_id`), package JSON export, legacy snapshot JSON fallback, explicit language fallback state, scanner capture fallback copy/hardening.
- Local verification passed: `node --check public/assets/app.js`, `node --check public/assets/i18n.js`, `node --check public/service-worker.js`, Python deploy helper compile, local `current_user`, local `app.php`, `git diff --check`, local message context smoke, package end-to-end smoke (`report_id=587`), and language fallback VM smoke.
- Production deploy attempt is blocked in the current shell because FTP/DB-gate environment variables are absent; report: `docs/AI_TEAM/46_OPEN_ITEMS_SPRINT_DEPLOY_BLOCKED_2026-05-28.md`.
- Production deploy remains next for selected bundle only once deploy variables are available; real-device scanner/PWA camera gate remains open until physical device evidence exists.
- CEO opened the UI simplification handoff from Google Drive: fast entry must feel like a calm operational notebook, with admin/report complexity separated.
- First local frontend patch for fast-entry complaints and browser Back behavior is complete.
- Local report: `docs/AI_TEAM/47_FAST_ENTRY_UX_BACK_LOCAL_2026-05-28.md`.
- Changed locally: modern `Фото/Скан/Файл` controls, saved proof access button, `Наличные` label, no edit-over-amount overlap, no lower-right pseudo-card, hidden fixed expense preview, app-step browser history.
- QA visual/manual check remains required before production routing.
- Optional QA browser visual matrix on production when browser automation is available.
- Post-MVP/Advanced tasks remain P1/P2 below.

## P1

- Product Finance Architect + Backend/Data + Frontend/UX + QA Release + Chief Auditor: open `Receipt Scanner` as FinDesk-owned proof scanner. Task card: `docs/AI_TEAM/31_RECEIPT_SCANNER_TASK_CARD_2026-05-28.md`. Scope: original photo/file, cleaned PDF, crop/perspective/cleanup, durable proof state, audit-safe archive/final-report proof chain. OCR and automatic extraction are Advanced unless reclassified.
- Frontend/UX + Backend/Data: local Receipt Scanner implementation is wired end to end. `Скан` opens a scanner modal, image crop corners are draggable, canvas cleanup generates a one-page PDF, original+PDF+metadata/hash are stored as linked proof artifacts, and retry is idempotent by `client_upload_id`. Browser/device QA remains open before release gate.
- Project Director opened full Receipt Scanner sprint in `docs/AI_TEAM/32_RECEIPT_SCANNER_SPRINT_2026-05-28.md`. Local backend/frontend evidence-chain implementation passed QA run `20260528RSQA01` for browser/HTTP file-input scanner path. Chief Auditor approved the local slice only. Production scanner deploy remains blocked until `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md` passes or CEO explicitly accepts a limited release without physical camera/PWA readiness.
- SEO Growth Engineer: create SEO/Growth strategy and route implementation tasks across public PWA surface, metadata, structured data, robots/sitemap, language policy, and production smoke.
- Frontend UX Engineer + QA Release Engineer: run language coverage/PWA audit for seven supported languages; fallback must be English when system language is outside the supported list.
- Backend Data Engineer: provide or create a legacy finalization fixture to verify `historical_snapshot_missing`.
- Backend Data Engineer: decide deterministic cutoff identity for same-second rows after finalization.
- Backend/Data or Product Finance Architect: decide whether downloaded current export wording must show exact carryover phrase server-side.
- Frontend UX Engineer: rebalance menu/pages into small screens for phone/tablet.
- Frontend UX Engineer: finish FinDesk as report checking layer only after financial terms are stable.
- Product Finance Architect + Frontend UX Engineer: separate information/reference data from operational money actions.
- Backend Data Engineer + QA Release Engineer: ensure archive opens all live reports and employee reports for group.
- Product Finance Architect + QA Release Engineer: review Excel/Google export for readable columns, colors, articles/categories, and old/new money movement.
- Frontend UX Engineer: make instant field capture one-hand, compact, photo/receipt-friendly, and free of dense tables.
- QA Release Engineer: verify quick actions on mobile and ensure saved rows still reopen exactly from the card list.

## P2

- Continue branding pass after functional screens settle.
- Add clear AI/analytics entry points without crowding operational screens.
- Improve help text wording where numbers can be misunderstood.
- Web Designer branding pass passed in local browser QA (run `20260527`): `index.php`/`app.php` logo/favicons checked on `390x844`, `820x1180`, `1440x900`; see `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/web_designer_branding_20260527/SUMMARY.md`.

## 2026-06-04 Atlas Persistence Cutover

- Backend Data Engineer: continue MongoDB Atlas migration after the completed workspace/groups slice. Next scope: workspace sessions, yacht profiles/settings, yacht fuel drafts, yacht provisioning drafts, price sources, and price snapshots.
- QA Release Engineer: verify Atlas-backed local restart persistence for create/list/trash/restore and confirm `Yacht: Ckaudia Z` remains visible after hard refresh.
- Project Director: keep fallback API only for emergency static UI viewing; do not use fallback as accepted persistence.
- Deploy Owner: before production, provision a separate Atlas production database/URI and wire it through production environment secrets, not through committed files.

### Completed Local Slice: Yacht State To Atlas

- Backend/Data: `yacht_state_get`, `yacht_state_save`, and Node provisioning calculation are available through the Atlas-backed local server.
- Frontend/UX: Yacht workspace opening loads state from Atlas; edits still save instantly locally and then sync to Atlas with debounce.
- QA: local smoke passed for Atlas restart persistence: `Ckaudia Z` remained visible, yacht state survived restart, fuel package kept 6 rows, agent fee row stayed `250 EUR`, provisioning calculation returned categories/items.

### Completed Local Slice: Yacht Atlas Price Engine

- Project Director: moved duplicate `Yacht: Claudia Z` to trash; `Yacht: Ckaudia Z` remains active.
- Backend/Data: added Atlas `yacht_price_snapshots` model and local APIs `yacht_price_approved_catalog` and `yacht_price_snapshot_refresh`.
- Frontend/UX: reference price refresh now calls Atlas snapshot refresh; food/fuel screens keep the correct render target; product reference prices can read Atlas food snapshots.
- QA: local smoke on `127.0.0.1:18890` passed for group list, trash list, fuel snapshot, food snapshot, and app route `routes31`.
- Open next: actual source fetchers/AI price refresh, source failure telemetry, production deployment plan with separate production Atlas URI.

### Completed Local Slice: Yacht Price Source UI

- Frontend/UX: fuel and product screens now show Atlas source transparency: available/total sources, failures, labels, source type, and normalized net price.
- Backend/Data: `source_details` from Atlas snapshots are exposed through `yacht_price_approved_catalog` and rendered by the UI.
- QA: local smoke passed on `127.0.0.1:18891` for `routes32`, fuel source details `5/5`, food source details `25/25`, and app availability.
- Open next: browser visual QA for source panel on desktop/iPad/iPhone, then actual source fetchers/AI refresh jobs.

## Director Sprint 2026-06-07 - Universal Cash Session Engine V1

Status: local first slice implemented; smoke pending.

Done locally:

- reviewed Ship Cashbox as a behavior source, not a codebase foundation;
- recorded Universal Cash Session direction;
- added Atlas-backed `cash_sessions` collection indexes in the local Node Atlas server;
- added API actions:
  - `cash_session_get_or_create`;
  - `cash_session_save_draft`;
  - `cash_session_submit_draft`;
- added universal Product Shell routes:
  - `cash-session`;
  - `cash-journal`;
  - `cash-records`;
  - `cash-report`;
- added entry points from generic workspace, Yacht home and Home home;
- added first ЖЗ page, fixed records page and report-preview page;
- marked settlement output as preview/not final, not as audited official report.

Next:

- smoke test Atlas API and browser routes;
- add participants/roles to the universal session;
- add close/archive snapshot;
- design professional report document after report structure approval.

## Director Sprint 2026-06-07 - Universal Cash Session Participants/Roles

Status: local first participant slice implemented; API smoke passed; smoke data cleaned.

Done locally:

- added participant upsert/remove backend actions for `cash_sessions`;
- added role normalization: owner, treasurer, manager, participant, viewer;
- added include/exclude-from-split flag for report preview;
- added participant selector to ЖЗ;
- ЖЗ save/submit now sends `participant_id`;
- fixed record batches store participant identity;
- records page displays participant name per batch;
- engine page displays participant cards and add/remove UI;
- smoke verified adding a participant, submitting participant ЖЗ, and preview transfer generation;
- smoke participant and test records were removed from Atlas after verification.

QA:

- `node --check public/assets/app.js` passed;
- `node --check server/findesk-atlas-server.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- local Atlas API smoke passed on `http://127.0.0.1:18893/app.php?build=routes34`.

Next:

- add participant self-view/auth discipline;
- add session close/archive snapshot;
- review settlement math with architect/auditor before official final report use.

## Director Sprint 2026-06-07 - Universal Cash Session Participant Self-View

Status: local self-view slice implemented; API smoke passed; smoke data cleaned.

Done locally:

- added participant `invite_token` to cash session participants;
- added restricted participant APIs:
  - `cash_participant_view`;
  - `cash_participant_save_draft`;
  - `cash_participant_submit_draft`;
- participant payload excludes full participant list and all session batches;
- participant can save/submit only their own ЖЗ by token;
- added `cash-participant` Product Shell route;
- added manager-side `Вид участника` button on participant cards;
- added `cashToken` URL handling for participant self-view route.

QA:

- `node --check public/assets/app.js` passed;
- `node --check server/findesk-atlas-server.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- local Atlas API smoke passed on `http://127.0.0.1:18894/app.php?build=routes35`;
- smoke confirmed participant payload does not expose full `participants` or global `batches`;
- smoke participant and test records were removed from Atlas after verification.

Next:

- invitation/token delivery UX;
- session close/archive snapshot;
- audited final settlement/report flow.

## Director Sprint 2026-06-07 - Universal Cash Session Invite Delivery UX

Status: local copy-link delivery implemented; API smoke passed; smoke data cleaned.

Done locally:

- participant form now stores optional email;
- participant cards display email and self-view invite URL;
- added `Копировать приглашение` action;
- copied invitation includes participant name, self-view URL and limited-view explanation;
- invite URL format uses `cashToken` and current build `routes36`;
- no production email sending was added.

QA:

- `node --check public/assets/app.js` passed;
- `node --check server/findesk-atlas-server.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- local Atlas API smoke passed on `http://127.0.0.1:18895/app.php?build=routes36`;
- smoke confirmed optional email persistence, token shape and restricted participant payload;
- smoke participant was removed from Atlas after verification.

Next:

- close/archive snapshot;
- production-grade token policy and optional email sending pipeline later;
- audited final settlement/report flow.

## Director Sprint 2026-06-07 - Universal Cash Session Close/Archive Snapshot

Status: local close/archive slice implemented; API smoke passed; smoke data cleaned.

Done locally:

- added backend `cash_session_close` action;
- added backend archive list/get actions;
- closing active session writes an immutable `archive_snapshot` into the closed session;
- snapshot stores participants, batches, participant totals, settlement preview, close timestamp and closer user id;
- active session route shows archive cards for the current workspace;
- `cash-session` and `cash-report` expose `Закрыть в архив` action;
- build advanced to `routes37` / `20260607-cash-archive-snapshot-routes37`.

QA:

- `node --check public/assets/app.js` passed;
- `node --check server/findesk-atlas-server.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- local Atlas API smoke passed on `http://127.0.0.1:18896/app.php?build=routes37`;
- smoke closed a temporary session, verified snapshot batches/totals, then removed smoke workspace/session/audit records;
- active `Yacht: Ckaudia Z` cash session remains clean: one participant, zero batches, empty owner draft.

Next:

- commit and push repository state to GitHub;
- audit settlement math before any final report status;
- add professional PDF/export later after report structure approval.

## Director Sprint 2026-06-07 - Universal Cash Session Professional Report V1

Status: local print/PDF report slice implemented; static/API smoke passed on existing Atlas-connected server.

Done locally:

- added professional report builder for active cash session preview;
- added archive snapshot print action from `cash-session` archive cards;
- added isolated print host `phase1-print-cash-report` so Product Shell UI is not printed;
- added report sections: FinDesk header, contractor/customer, session meta, totals, participants, preliminary transfers, fixed record batches, signatures and footer;
- footer uses `finance.brkovic.ltd - Vetus Nauta Brkovic`;
- report explicitly says preliminary/not final audit;
- build advanced to `routes38` / `20260607-cash-professional-report-routes38`.

QA:

- `node --check public/assets/app.js` passed;
- `node --check server/findesk-atlas-server.js` passed;
- `node --check public/service-worker.js` passed;
- `git diff --check` passed;
- static build smoke passed on `http://127.0.0.1:18896/app.php?build=routes38`;
- Atlas API smoke passed on the existing connected server `18896`: active `Yacht: Ckaudia Z` session returned clean with one participant, zero batches and report preview available;
- new server processes on `18897` and `18898` were stopped after Atlas TLS handshake failures; existing connected server remained healthy.

Next:

- browser visual print QA from the actual print dialog/PDF on desktop, iPad and iPhone;
- audit settlement math before changing report status from `preview_not_final`;
- later add server-side PDF/export only after report structure is accepted.

## Director Sprint 2026-06-07 - Atlas TLS Stability Check

Status: infrastructure issue reproduced; diagnostic script added.

Observed locally:

- existing Atlas-connected local server `18896` remains healthy and can read `current_user`, `group_list`, active cash session and archive list;
- new server processes on fresh ports fail when opening new Atlas connections;
- current public IP during the check: `77.222.27.84`;
- `mongodb+srv` host resolves correctly to three Atlas SRV records;
- direct TLS handshake to all three Atlas shard endpoints fails with `ERR_SSL_TLSV1_ALERT_INTERNAL_ERROR` / `tlsv1 alert internal error`;
- MongoDB driver ping fails with the same TLS alert before useful application-level auth or collection checks.

Repository support added:

- `npm run check:atlas` runs `scripts/atlas_connection_smoke.js`;
- the script prints masked URI metadata, SRV records, TLS probe results and Mongo ping status without printing credentials.

Likely cause:

- Atlas/network access policy or cluster-side TLS rejection for the current public IP/new handshakes;
- not a Product Shell report bug and not a collection/schema bug.

Next:

- check MongoDB Atlas Network Access and allow current public IP or a stable office/VPN IP;
- rerun `npm run check:atlas` after the Atlas access change;
- avoid restarting the working `18896` process until new Atlas handshakes pass.

### Atlas TLS Stability Follow-up - 2026-06-07

Status: resolved after Atlas Network Access update.

Verification:

- public IP remained `77.222.27.84`;
- `npm run check:atlas` passed;
- all three Atlas TLS probes returned `ok: true` with `TLSv1.3`;
- MongoDB driver ping returned `ok: true`;
- new local Atlas server started on `http://127.0.0.1:18899/app.php?build=routes38`;
- new server process connected to Atlas database `finance_brkovic_ltd`;
- `current_user` and `cash_session_get_or_create` passed on port `18899`;
- active `Yacht: Ckaudia Z` session remained clean: one participant, zero batches.

Control:

- no production deploy;
- `npm run check:atlas` should be used after IP/network changes or before blaming application code for Atlas failures.

## Director Sprint 2026-06-07 - Settlement Preview Audit Harness V1

Status: local audit harness implemented; checks passed; no formula changes made.

Done locally:

- exported cash math helpers from the Atlas local server without auto-starting HTTP when imported;
- added `npm run audit:cash`;
- added deterministic in-memory scenarios for expenses, mixed participants, excluded participant and contribution/cash-in behavior;
- current preview math is now reproducible outside the browser and outside Atlas data.

QA:

- `npm run audit:cash` passed;
- scenarios: 5 total, 0 failed, 2 `requires_review`;
- `node --check server/findesk-atlas-server.js` passed;
- `node --check scripts/cash_session_math_audit.js` passed;
- `git diff --check` passed.

Findings requiring architect/auditor decision:

- excluded participant expenses are reimbursed while that participant is excluded from share;
- contributions/cash-in create surplus credit that is not fully allocated by settlement transfer lines.

Control:

- no official financial formulas were changed;
- report remains `preview_not_final`.

## Director Sprint 2026-06-07 - ЖЗ Strict Sign Discipline

Status: local strict ЖЗ parser/UI discipline implemented; audit harness passed.

Done locally:

- corrected ЖЗ rule: `+` means income, `-` means expense, unsigned number is not accepted into calculation;
- backend `parseCashNotebook` follows the strict sign rule;
- frontend ЖЗ current-line warning follows the same rule;
- ЖЗ working page now shows one local balance, accepted line count, ignored line count;
- ЖЗ primary button changes between `К записям` and `Зафиксировать и к записям`;
- placeholders now show signed expenses;
- build advanced to `routes40` / `20260607-cash-journal-sign-discipline-routes40`.

QA:

- `node --check public/assets/app.js` passed;
- `node --check public/service-worker.js` passed;
- `node --check server/findesk-atlas-server.js` passed;
- `node --check scripts/cash_session_math_audit.js` passed;
- `git diff --check` passed;
- `npm run audit:cash` passed with parser checks: `+100` contribution, `-40` expense, `40`, `=40`, `_40` notes outside calculation.

Control:

- no production deploy;
- no official settlement formula change;
- this is ЖЗ input discipline, not final report audit.

## Director Sprint 2026-06-07 - Personal Journal Records Reports Discipline Lock

Status: product logic captured; implementation next.

Locked behavior:

- ЖЗ edits one active record only and must not contain the records list below it;
- strict ЖЗ signs: `+` income, `-` expense, unsigned number is outside calculation;
- Records page is card management with selected report/account context;
- Records page always shows `Входящая сумма`, `Поступило`, and `Остаток` for the selected context;
- unattached cards must appear under a visible `Без учета` context and calculate as movement without report opening income;
- typing in ЖЗ creates/updates an active draft record card with date/time and must survive page exit;
- Reports page owns create/start/fix/print/save/archive report lifecycle;
- attachment/paperclip behavior belongs to ЖЗ active record context;
- mobile-first: one-screen work surface, internal scroll where needed.

Reference:

- `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md`.

Next implementation slice:

- strict parser/warning correction for `+500`, `-300`, unsigned number rejection;
- duplicate title cleanup;
- active draft autosave card foundation.
