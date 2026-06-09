# Decisions Log

## 2026-06-03: Yacht Bunkering Is Internal To Yacht Template

Decision: `Бункеровка` is not a primary FinDesk start path. It belongs inside the Yacht template only.

Reason:

- FinDesk is a financial program first, not a yacht-only application;
- the general start page must remain understandable for all users;
- Yacht is one ready template under `Готовые шаблоны`;
- bunkering/starter package is a yacht work-order function, not a global FinDesk function.

Control:

- scope correction report: `docs/AI_TEAM/87_YACHT_BUNKERING_SCOPE_CORRECTION_LOCAL_2026-06-03.md`;
- Yacht section handoff: `docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md`;
- do not add `Бункеровка` back to the pre-auth start, Welcome Hall, or top menu;
- keep the internal Yacht button that scrolls to `Бункеровка / стартовый пакет`.

## 2026-06-02: Product Bible V1 Becomes Highest-Level FinDesk Source

Decision: accept the Drive package `findesk_product_bible_full_v1.zip` as the highest-level FinDesk product source.

Reason:

- it explicitly defines FinDesk from Welcome Hall to final report;
- it resolves conflicts between Phase 1, Phase 2, Phase 3, QA, audit and handoff documents;
- it forbids reviving old interface patterns, dashboard-first thinking, accounting/ERP language and ecosystem portal links;
- it defines Product Completion as real UX, visual system, mobile-first pass, physical QA and old interface removal.

Control:

- intake record: `docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md`;
- Product Bible stands above all phase documents unless the CEO explicitly changes product direction;
- next implementation must expose the approved structure, not patch over old FinDesk;
- Phase 3 cannot pass while old FinDesk remains visually recognizable;
- production release remains blocked until functional QA, engine audit, UX QA, mobile QA, visual QA, report/export QA, backup/rollback check and production release audit are complete.

## 2026-06-03: Sprint 0 Route Map Before Product Bible Implementation

Decision: close Sprint 0 as a route/product audit and start Sprint 1 from Welcome Hall and the application shell.

Reason:

- current Product screens exist, but they are mixed with the legacy `ontherun` router;
- old modules remain physically present in DOM and can be reopened through `qlSetModule`, `data-module-tab`, saved localStorage state, or browser Back;
- Product Bible V1 requires removing old interface remnants before Phase 3 can pass;
- implementing deeper screens before isolating the route layer would repeat the previous patch-over-old-product failure.

Control:

- route map: `docs/AI_TEAM/64_PRODUCT_BIBLE_SPRINT0_ROUTE_MAP_2026-06-03.md`;
- Sprint 1 starts with `Welcome Hall`, shell, menu, and Back behavior;
- old `ledger/ontherun/captain/money/groups/settings` screens are hidden engine support only, not normal product navigation;
- do not continue styling or patching the old `captain` or `ontherun` surface as the user-facing FinDesk.

## 2026-06-02: Phase 3 Product Identity Queued After Phase 2 QA

Decision: accept the Drive package `FinDesk Phase 3 - Product Identity, UX Validation & Cohesion` as the next product validation source after Phase 2 authenticated QA.

Reason:

- Phase 3 is about making Phase 1 logic and Phase 2 architecture visible as one coherent product;
- it explicitly forbids inventing a new FinDesk or redesigning approved business logic;
- it defines the style direction as operational luxury minimalism and requires physical QA across desktop, iPhone, Android and iPad.

Control:

- source recorded at `docs/AI_TEAM/61_PHASE3_PRODUCT_IDENTITY_UX_VALIDATION_2026-06-02.md`;
- Phase 2 QA remains the current gate;
- Phase 3 is not a styling escape hatch and not a new dashboard/ERP direction;
- old interface remnants must disappear before physical QA.

## 2026-06-02: Phase 2 Gate Before Implementation

Decision: start Phase 2 with Sprint 0/1/2 audit and block implementation until the working blueprint and QA checklist reflect the Phase 2 packages.

Reason:

- Phase 2 package explicitly defines this as logic/workflow construction, not styling;
- the current app still contains visible old navigation and legacy UX paths;
- continuing implementation over hidden old state would repeat the previous product failure.

Control:

- current Phase 2 audit: `docs/AI_TEAM/59_PHASE2_LOGIC_NAV_ENGINE_AUDIT_2026-06-02.md`;
- old modules must not remain normal product navigation before physical QA;
- Cash/Card choice must appear before Live Journal;
- transfer offer must be first-class: pending -> employee confirmation -> active;
- active workspace, report assembly and protected actions must become first-class contracts;
- no destructive database action is approved;
- no visual/style sprint starts before the new product hierarchy is visible.

## 2026-06-02: Phase 2 Local Implementation Sprint

Decision: implement the first local Phase 2 slice after the audit gate, using additive backend state and a new visible product shell.

Reason:

- the audit confirmed auth, groups, journal and attachment foundations can be preserved;
- transfer, active workspace, report assembly and protected actions needed first-class state;
- physical QA cannot start while old routes are the normal visible product path.

Control:

- local sprint report: `docs/AI_TEAM/60_PHASE2_IMPLEMENTATION_SPRINT_LOCAL_2026-06-02.md`;
- new backend module: `app/findesk_phase2.php`;
- new DB rollout script: `deploy/findesk_phase2_foundation.sql`;
- no old tables were dropped;
- no production deploy was done;
- authenticated local QA is required before production upload.

## 2026-06-02: Phase 2 Authenticated API QA Pass

Decision: accept the local authenticated API workflow as passed for Phase 2 logic, while keeping physical UX QA and production deploy blocked.

Reason:

- two authenticated users completed the first-class transfer lifecycle;
- pending transfer blocked employee Live Journal input;
- employee confirmation activated cash/card workflows;
- Cash and Card stayed separated through report assembly and final reports;
- Protected Action required reason and exact `CONFIRM`;
- active workspace preference persisted per user.

Control:

- QA report: `docs/AI_TEAM/62_PHASE2_AUTHENTICATED_API_QA_2026-06-02.md`;
- this is not a physical UX pass;
- desktop/iPhone/Android/iPad validation is still required;
- production DB rollout and deploy remain blocked until local product path QA is approved.

## 2026-06-02: Mandatory Phase 1 Alignment Patch

Decision: block further Phase 1 implementation until the mandatory alignment patch is reflected in the working blueprint, Phase 1 audit, and QA checklist.

Reason:

- product architecture still needed explicit confirmation of transfer activation rules;
- card/non-cash rules needed a final MVP definition;
- Team Workspace and Employee Card needed stronger people-first constraints;
- final report structure needed an explicit `Cash / Card / Total` contract;
- QA needed exact checks for these rules before more implementation.

Control:

- pending transfer is a first-class lifecycle: issue -> pending -> employee confirmation -> active;
- employee journal must stay blocked while transfer is pending;
- cash and card remain separate streams through journal and report composition;
- Team Workspace stays a people screen;
- Employee Card top layout is `name / position / issued / remaining`;
- Live Journal stays records-feed-first;
- final report structure is `Cash Section -> Card / Non-Cash Section -> Total`;
- implementation continues only after these rules are present in blueprint, audit, and QA checklist.

## 2026-06-02: Phase 1 Functional Blueprint Becomes The Main Product Beacon

Decision: adopt `docs/AI_TEAM/51_PHASE1_FUNCTIONAL_BLUEPRINT_MANDATE_2026-06-02.md` as the main source for the next FinDesk product step.

Reason:

- CEO provided a new Drive package with a screen-by-screen functional blueprint;
- the current FinDesk result was rejected as a mixed technical layer rather than a product;
- continuing to patch the current screen would deepen the structural error.

Control:

- Phase 1 is functional cleanup first, not visual redesign first;
- auth, backend, database, PWA, manifest, and service worker foundations stay in place unless a direct requirement forces change;
- `Live Journal` is the first cleanup target;
- no production-first implementation of half-finished Phase 1 screens;
- all next role tasks must read the new blueprint mandate before editing code.

## 2026-06-01: FinDesk Must Move From Board Rebuild To Active Session Model

Decision: treat the 2026-06-01 FinDesk active-session task as the current main FinDesk product task, and treat `48_FINDESK_BOARD_REBUILD_LOCAL_2026-05-28.md` as an intermediate rebuild rather than the final target.

Reason:

- the older board rebuild improved structure and mobile fit, but still lived too close to the legacy mixed surface;
- CEO clarified a stricter operating model: active session only, one participant report per session, one administrator report per session, one summary report, and one immutable archived summary object;
- the main problem is now product/session behavior, not only card layout.

Control:

- current task card: `docs/AI_TEAM/49_FINDESK_ACTIVE_SESSION_REBUILD_TASK_2026-06-01.md`;
- no formula rewrite is authorized by this decision;
- no destructive log deletion is authorized by this decision;
- active-session UX, report uniqueness, confirmation state, and archive transition must be formalized before broad implementation;
- `48` remains valid as an intermediate implementation record, not as the final requirement source.

## 2026-06-02: Director Handoff And Production Boundary

Decision: make `docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-02.md` the current start handoff for the next Project Director.

Reason:

- the previous director handoff and `00_START_HERE.md` still pointed to May baselines;
- production frontend has moved to `20260601-findesk-mobilefit2`;
- the repository also contains local backend/open-items candidate work that must not be confused with a completed production DB rollout.

Control:

- the office entry point is `docs/AI_TEAM/OFFICE_DASHBOARD.html`;
- the correct production host is `https://finance.brkovic.ltd/app.php`;
- the correct production FTP path is `finance.brkovic.ltd/public`;
- do not deploy this project to another host or subdirectory;
- frontend `mobilefit2` is confirmed live;
- backend DB migration/package-export/message-context rollout remains a separate deploy candidate until backup, DB preflight, migration, smoke, and deploy report are completed.

## 2026-05-28: FinDesk Board Rebuild Boundary

Decision: rebuild the FinDesk board in the existing `#moduleCaptain` surface and reuse current financial APIs instead of adding a new calculation layer.

Reason:

- CEO requested interface consolidation, not a formula rewrite;
- existing endpoints already provide the required states: `ledger_balance`, `on_the_go_card_list`, `advance_list`, `on_the_go_card_include/unsubmit`, `advance_accept/return`, and `ledger_group_finalize_report`;
- immutable final report/package behavior must remain untouched.

Boundary:

- `20 cards` is a UI working-list limit in this sprint, not physical deletion of financial logs;
- destructive retention behavior requires a separate product/audit decision.

## 2026-05-23: AI Team Office

Decision: create `docs/AI_TEAM/` as the visible office for specialist AI chats.

Reason:

- the project needs role separation;
- financial logic, frontend, backend, QA, and audit must stop blending into one chat;
- every chat must leave a readable trace for the next one.

## 2026-05-23: Five Roles

Decision: operate with five virtual roles:

1. Product Finance Architect
2. Backend Data Engineer
3. Frontend UX Engineer
4. QA Release Engineer
5. Chief Auditor

Reason:

- enough specialization for release discipline;
- no inflated virtual staff;
- Chief Auditor keeps contradictions visible.

## 2026-05-26: Project Director Handoff

Decision: add a project director layer above the specialist chats.

Reason:

- the CEO needs one management-facing handoff for accepting and routing the project;
- the Tech Deputy coordinates the office, while the Project Director owns work order, role separation, and first review cycle;
- specialist chats must be launched and attached through `CHAT_LINKS.md`.

## 2026-05-26: Director Intake Accepted

Decision: accept the project under the Project Director role and keep release preparation routed through the AI Team Office.

Reason:

- `HEAD` and `origin/main` both point to `72b38e6`;
- the working tree is intentionally dirty and must not be reset or cleaned;
- all five specialist role cabinets exist;
- real specialist chat links are still missing and must be attached in `CHAT_LINKS.md`.

## 2026-05-27: Web Designer Cabinet Added

Decision: add a dedicated Web Designer role for logo alignment and favicon/icon consistency.

Reason:

- user requirement for brand-layer quality was explicit and blocking perception readiness on public/app entry screens;
- frontend UX role scope is already loaded with product flows and should remain focused on flow/functionality while designer handles presentation polish;
- visual QA for brand/favicons must remain in its own reporting cabinet and be delivered as short reports only.

Decision details:

- cabinet created under `docs/AI_TEAM/roles/07_web_designer`;
- task assigned as `HANDOFF_2026-05-27_WEB_DESIGNER_BRANDING_TASK.md`;
- current status recorded in `docs/AI_TEAM/roles/07_web_designer/STATUS.md`.

## 2026-05-26: Smoke Baseline Handling

Decision: do not treat the failed director smoke command as product failure until the CLI environment is fixed.

Reason:

- `php scripts/local-smoke.php http://127.0.0.1:18889` cannot run in the current shell because `php` is not found;
- the local server at `http://127.0.0.1:18889` responds `200 OK` and reports PHP/8.3.6;
- QA Release Engineer must record the smoke result as passed, failed, or blocked with exact environment evidence.

## 2026-05-26: First Role Cycle Launched

Decision: run the first release-preparation cycle in order: Product Finance Architect -> Backend Data Engineer -> Frontend UX Engineer -> QA Release Engineer -> Chief Auditor.

Reason:

- financial meaning must be fixed before data, UX, QA, and release-gate conclusions;
- backend/API changes require Backend Data Engineer ownership;
- UX flow changes require Frontend UX Engineer ownership;
- readiness cannot be declared without QA evidence and Chief Auditor approval.

## 2026-05-26: Production FTP And Database Access

Decision: CEO granted FTP and database access for all employee chats, including view, edit, and delete permissions.

Reason:

- release preparation may require checking deployed files, deployed behavior, database state, and production data consistency;
- specialist chats need access to verify their own role-owned areas without routing every read through one overloaded chat;
- credentials are treated as out-of-band secrets and must not be committed or copied into office documents.

Control:

- role boundaries still apply;
- destructive production actions require a concrete documented task and recovery path;
- Backend Data Engineer owns database/API changes;
- Frontend UX Engineer owns frontend deployment changes;
- QA Release Engineer and Chief Auditor use access primarily for verification and release evidence.

## 2026-05-26: Non-Accountant Money Map

Decision: make the main FinDesk product language a human money map, not an accounting interface.

Reason:

- the CEO's core need is to understand where money is, not to operate accounting terminology;
- release quality depends on preserving the full money tree while making it readable to ordinary administrators and employees;
- every visible number must answer who holds or spent it, where it is, what changed it, and where the proof is.

Control:

- the first product layer must use simple action language: received, handed to employee, spent cash, spent card, returned, sent to review, accepted into report, closed;
- accounting/journal/API terms remain internal or advanced-layer language;
- formula and data changes still require Product Finance Architect, Backend Data Engineer, and Chief Auditor visibility.

## 2026-05-26: Instant Field Capture

Decision: treat instant income/expense capture as a core FinDesk workflow for people in active movement.

Reason:

- field users can lose the truth of a transaction if the app demands a full accounting form at the moment of action;
- the product must capture the event quickly and then move it through review, proof, final report, and archive;
- speed must not bypass financial control, so fast records can be drafts or review items before they become final numbers.

Control:

- quick capture requires only event type, amount, person, and proof/comment when available;
- incomplete records are allowed but must be visibly marked as draft, needs proof, or on review;
- a quick record cannot silently become final report data without review/acceptance;
- mobile entry must stay one-hand, compact, and free of dense accounting tables.

## 2026-05-26: CEO Ideas Registry

Decision: maintain a dedicated registry of old CEO ideas so practical implementation does not lose previous product intent.

Reason:

- the project has multiple handoff and knowledge documents with important product decisions;
- implementation work must not regress saved-card opening, cash/card split, archive meaning, mobile-first capture, or accountable-money rules;
- each idea needs an owning role and an acceptance check.

Control:

- the registry lives in `docs/AI_TEAM/06_CEO_IDEAS_REGISTRY.md`;
- new practical work must either reference an existing registry item or add a new one;
- old ideas are converted into role-owned tasks before code changes.

## 2026-05-26: First Practical Slice

Decision: start practical implementation with a safe frontend slice for instant field capture.

Reason:

- the CEO explicitly highlighted momentary income/expense fixation for people in movement;
- current Live Report already has signed-line capture and cash/card stream separation;
- adding quick line starters and proof access improves field speed without changing formulas or API contracts.

Control:

- `+ Получили` is cash-only;
- `- Наличные` is cash-only;
- `- Карта` is card-only;
- `Подотчет` routes to the accountable-money screen and must not create an expense;
- quick records remain draft/review workflow items until the existing save/review/FinDesk flow accepts them.

## 2026-05-26: Instant Field Capture QA Slice Passed

Decision: accept QA Release Engineer evidence for the assigned instant field capture slice and move the slice to Chief Auditor review.

Reason:

- QA run `20260526141856` verified mobile `390 x 844`, tablet `820 x 1180`, and desktop `1440 x 900`;
- cash/card quick actions, proof picker, `Подотчет`, saved-card reopen, edit, delete, review gate, physical-cash separation, and cash sequence guard passed;
- draft and submitted quick records stayed out of final report until include/acceptance;
- CLI smoke remains environment-blocked because `php` is unavailable, while server reachability and JS syntax passed.

Control:

- this is a slice-level QA pass, not full release approval;
- full release stays blocked until carryover, export, archive, and Chief Auditor gate are complete;
- Chief Auditor must confirm that instant capture preserves proof, ownership, review, and final-report boundaries.

## 2026-05-26: Instant Field Capture Slice Gate Approved

Decision: Chief Auditor approved the assigned instant field capture slice only.

Reason:

- Chief Auditor reviewed QA run `20260526141856`;
- no slice-blocking contradiction was found between financial meaning, backend behavior, frontend quick actions, QA evidence, and release-gate control;
- quick capture preserved proof access, money ownership path, review status, physical cash/card separation, and final-report acceptance boundary in the verified scope.

Control:

- approval is limited to the tested instant field capture slice;
- full release remains blocked;
- next release-critical work moves to Backend Data Engineer for carryover/export/archive data-trace before wider QA.

## 2026-05-26: Finalized Report Must Be A First-Class Historical Truth

Decision: release requires two separate user-facing report/export actions after finalization: current open-period report/export and historical finalized report/export.

Reason:

- Backend/Data traced that open-period carryover/export can correctly start from carryover after finalization;
- the old `€1000` income is not treated as new current income in the traced open-period path;
- raw historical evidence remains in ledger entries, archived Live Report cards/captures, and audit log;
- there is no first-class immutable finalized-report/export source that returns the closed report as a product object after the main export switches to open-period mode;
- a financial product cannot make "final report" mean only recoverable raw evidence.

Control:

- archive/audit evidence is necessary but not sufficient as the user's closed report;
- Product Finance Architect must define labels and user actions for both current open-period export and historical finalized-report export;
- Backend/Data must not change formulas silently; any implementation must preserve the existing open-period carryover behavior;
- QA must verify both truths separately before release candidate.

## 2026-05-26: Product Contract For Two Report Truths Accepted

Decision: accept Product Finance Architect's labels and release contract for current-period truth and historical finalized-report truth.

Reason:

- ordinary users must not need backend/archive knowledge to tell current money from a closed report;
- `€400` means `Остаток перенесен в следующий период` inside the old closed report and `Переходящий остаток из финального отчета` in the new current period;
- `€1000` from the old report must never look like new current income after finalization.

Control:

- approved actions: `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, `Экспорт финального отчета`;
- Backend/Data owns the historical finalized report/export source;
- Frontend/UX may expose labels only after backend source is clear;
- QA must verify current export and historical finalized export as separate flows.

## 2026-05-26: Historical Finalized Report Backend Source Implemented

Decision: accept the Backend/Data patch as implemented and move it to QA verification before any release or UX wiring decision.

Reason:

- new finalizations now store an immutable `report_snapshot` in `audit_log.details`;
- `ledger_group_finalize_report` returns a `report_id`;
- historical list/detail/export endpoints were added for selected finalized reports;
- existing `ledger_group_excel` and `ledger_group_google_sheet` remain current open-period export paths;
- old finalizations without snapshot return `historical_snapshot_missing` instead of mutable reconstruction.

Control:

- this is not release approval;
- QA must prove the `EUR 1000 -> EUR 600 -> EUR 400` scenario through both current and historical exports;
- Frontend/UX must not wire final labels until QA confirms the backend contract;
- Chief Auditor final gate remains blocked.

## 2026-05-26: Historical Snapshot Passed, Current Export Combo Blocked

Decision: accept QA evidence that historical finalized report snapshot/export works for new finalizations, but keep the backend handoff blocked by a current open-period export regression.

Reason:

- QA verified `report_id=342` in group `191`: historical detail, Google Sheet, and Excel export preserved `1000 / 600 / 400`;
- QA verified later current-period income did not mutate the selected historical report/export;
- QA found group `192`, report `348`: after adding current income `50` and current included Live Report expense `25`, current export omitted the current income;
- `ledger_group_open_received_funds.entries` returned Live Report tape id `175` instead of current income ledger entry `84`.

Control:

- historical finalized report source remains promising but not release-approved;
- Backend/Data must fix current open-period export before Frontend/UX wiring;
- QA must rerun the combined current income plus current included Live Report scenario;
- Chief Auditor final gate remains blocked.

## 2026-05-26: Current Export Combo Fix Implemented

Decision: accept Backend/Data's current export regression fix as implemented and move it to QA regression recheck.

Reason:

- the current income row was being overwritten by a later Live Report loop because `ql_ledger_group_open_received_funds()` used `foreach ($rows as &$row)` without releasing the reference;
- Backend/Data added `unset($row)` immediately after the by-reference loop;
- Backend/Data HTTP/API fixture `group_id=194`, `report_id=364` showed historical export still `1000 / 600 / 400` and current export containing carryover `400`, current income `50`, and current Live Report expense `25`;
- `git diff --check` passed and HTTP load check returned `200`.

Control:

- this is not release approval;
- QA must rerun the combined regression scenario independently;
- the same-second cutoff edge is tracked as P1 hardening and must not be confused with the fixed reference overwrite blocker;
- Chief Auditor final gate remains blocked.

## 2026-05-26: Current Export Combo Regression Fixed By QA

Decision: accept QA Release Engineer's recheck that the P0 current export combo regression is fixed for the backend contract.

Reason:

- QA reran `1000 income -> 600 Live Report expense -> finalize -> carryover 400 -> current income 50 -> current Live Report expense 25`;
- evidence: `group_id=195`, `report_id=371`, current income ledger entry `90`, current Live Report tape `184`;
- historical detail, Google Sheet export, and Excel export stayed `1000 / 600 / 400`;
- `ledger_group_open_received_funds.entries` returned the current income ledger row, not a Live Report tape row;
- current export contained carryover `400`, current income `50`, and current Live Report expense `25`;
- current export did not show old finalized income `1000` as current income.

Control:

- backend contract can proceed toward Frontend/UX wiring;
- full release is still not ready;
- P1 same-second cutoff hardening remains open.

## 2026-05-26: Role Reporting Discipline

Decision: every role chat must store full reports in its own role folder and send only a short report to the CEO / Project Director chat.

Reason:

- the CEO / Project Director chat must stay operational and not become a dump of logs, evidence, and long reasoning;
- role folders are the source of truth for detailed work;
- the Director can read full findings directly from the role cabinets.

Control:

- global rule lives in `docs/AI_TEAM/ROLE_REPORTING_RULES.md`;
- each role folder has its own `REPORTING_RULES.md`;
- all future role tasks must update the role folder first, then send a short report only.

## 2026-05-26: MVP Finish Line

Decision: define the MVP finish line and stop adding non-P0 work to the current MVP cycle.

Reason:

- the project must not spend unlimited time moving data and rechecking technical branches without a stop point;
- the MVP is the minimum trusted money tree for a non-accountant, not a complete accounting platform;
- the user must be able to capture, review, close, export, and later prove money without losing current vs historical truth.

Control:

- MVP gate lives in `docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md`;
- remaining MVP work is Frontend/UX current-vs-historical action wiring or confirmation, QA user-facing flow pass, and Chief Auditor MVP gate;
- AI analytics, broad polish, old legacy snapshot migration, and P1 hardening move to post-MVP unless they become P0 blockers.

## 2026-05-26: Director Task Cards

Decision: Project Director assigns existing role chats with short technical cards, not long prompts.

Reason:

- role chats already have cabinets and context files;
- CEO / Project Director chat should not carry full task bodies, logs, and checklists;
- full task text belongs in the role folder, where the Director can read it later.

Control:

- rule lives in `docs/AI_TEAM/PROJECT_DIRECTOR_TASK_CARD_RULES.md`;
- card must name exact role/chat, existing/new chat status, assignment file paths, result file paths, and expected short report;
- full prompts are reserved for new chats, lost-context chats, or explicit CEO request.

## 2026-05-26: Current And Historical Report UI Implemented

Decision: accept Frontend/UX implementation as ready for QA verification, not release approval.

Reason:

- UI now separates `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, and `Экспорт финального отчета`;
- current export remains on existing group endpoints;
- historical export uses explicit `report_id` endpoints;
- Frontend/UX reported no backend/API or formula changes;
- Director verification passed `node --check public/assets/app.js` and `git diff --check`.

Control:

- QA must verify the user-facing flow on desktop/tablet/mobile;
- any downloaded server-rendered export label issue belongs to Backend/Data if it requires changing backend output;
- Chief Auditor MVP gate remains blocked until QA evidence is recorded.

## 2026-05-26: Current And Historical Report UI QA Passed

Decision: accept QA evidence that the user-facing current/historical report UI passes the MVP scenario.

Reason:

- QA verified mobile `390x844`, tablet `820x1180`, and desktop `1440x900`;
- evidence: `group_id=200`, `report_id=406`, current income entry `100`, current Live Report tape `199`;
- `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, selected `report_id`, and `Экспорт финального отчета` were visible and reachable;
- current export stayed current-period truth with carryover `400`, current income `50`, and current Live Report expense `25`;
- historical export stayed `1000 / 600 / 400` and excluded later current entries.

Control:

- full release is not declared ready;
- next owner is Chief Auditor for MVP gate;
- P1 legacy `historical_snapshot_missing` fixture remains separate from MVP gate unless Chief Auditor upgrades it to P0.

## 2026-05-26: Short Report Template Enforced

Decision: strengthen role reporting with a mandatory short-report template.

Reason:

- role chats still sent long reports to the CEO / Project Director chat;
- full evidence belongs in role folders;
- the chief chat needs only status, files, evidence pointer, blocker, and next owner.

Control:

- template lives in `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`;
- screenshot lists and full checklists must stay in `FINDINGS.md`;
- role `REPORTING_RULES.md` files now point to the exact template.

## 2026-05-26: MVP Gate Approved

Decision: accept Chief Auditor approval and close the current MVP gate.

Reason:

- Product Finance Architect, Backend Data Engineer, Frontend UX Engineer, and QA Release Engineer completed the required MVP path;
- Chief Auditor approved the MVP gate after reviewing the role evidence;
- instant field capture evidence is `20260526141856`;
- backend current/historical evidence is `group_id=195`, `report_id=371`;
- UI current/historical evidence is `group_id=200`, `report_id=406`;
- no unresolved P0 contradiction remains for the MVP money-tree path.

Control:

- this is MVP approval, not a declaration of complete accounting-platform release;
- legacy snapshot fixture and same-second cutoff hardening stay P1/post-MVP;
- no new feature enters the MVP cycle unless it fixes a P0 blocker;
- Project Director owns the MVP release package and handoff.

## 2026-05-26: Deployment Requires Package Control

Decision: do not treat the dirty working tree as an automatic production deploy package.

Reason:

- MVP gate is approved, but the repository contains broader modified and untracked files from earlier work;
- some files are local/test/support files and should not be blindly sent to production;
- production FTP/database access exists, but destructive or broad production actions need a documented package and backup point.

Control:

- deploy handoff lives in `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`;
- Project Director must name the deployment mode before production action;
- after production deploy, QA Release Engineer must run production smoke and Chief Auditor may review only if smoke finds a P0 contradiction.

## 2026-05-26: CEO Business MVP Scope Correction

Decision: treat the approved 2026-05-26 gate as MVP foundation, not the complete CEO business MVP.

Reason:

- CEO clarified that MVP means the full operational business loop, not only the current/historical money-tree foundation;
- the business loop includes fixation, analysis/checking, report submission, immutable save, print/export, group report consolidation, group save/print, archive, participant groups, and money streams into one common group pot;
- deploying the foundation as "MVP" would create a false finish line.

Control:

- business MVP scope lives in `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`;
- deployment handoff is paused unless CEO explicitly chooses internal alpha;
- next owner is Product Finance Architect for business-MVP acceptance terms;
- role chats continue with short technical cards and full reports in their own folders.

## 2026-05-26: Legacy Product Modules Stay In Scope

Decision: keep group messages, travel equalization, and business solutions in the FinDesk product scope.

Reason:

- CEO clarified that messages from older decisions are not accidental leftovers;
- the live site presents FinDesk as three layers: On the Go, FinDesk, and Advanced;
- older handoffs list group messages/unread state, Trip with Friends/travel equalization, and Business Desk/proforma tools as real product areas;
- removing or ignoring these sections would narrow the product incorrectly.

Control:

- group messages are MVP-relevant when they support report submission, review, return, and missing-proof clarification;
- Business Desk remains a separate business-solutions section and must not pollute cash/report formulas;
- travel equalization remains a product section; full algorithm can be phased unless CEO chooses travel as launch-critical;
- Product Finance Architect must place each module either in business MVP minimum or post-MVP with an explicit reason.

## 2026-05-26: Mobile Multitasking Is A Product Constraint

Decision: treat mobile convenience as a hard product constraint for business MVP, not as late visual polish.

Reason:

- CEO repeatedly stated that field users are in active movement;
- FinDesk combines capture, review, reports, group messages, archive, business documents, and travel equalization;
- a small screen cannot show the desktop product all at once without destroying usability;
- modern mobile patterns favor a few stable primary entrances, contextual actions, progressive disclosure, list-detail adaptation, and offline/loss prevention.

Control:

- research pack lives in `docs/AI_TEAM/11_MOBILE_MULTITASKING_RESEARCH.md`;
- Product Finance Architect must classify what belongs in the mobile business MVP;
- Frontend UX Engineer must preserve modules while designing a phone-first information architecture;
- no role may solve mobile overload by deleting CEO product ideas from scope.

## 2026-05-26: Advanced Means Non-MVP

Decision: define `Advanced` as the place for everything outside the current business MVP.

Reason:

- this keeps the first working product simple;
- old ideas remain preserved without overloading the first mobile workflow;
- Product/UX can classify modules without deleting them.

Control:

- if a feature is required for the ordinary first money loop, it is MVP;
- if a feature is real but not required for the ordinary first money loop, it goes to `Advanced` / post-MVP;
- role chats must not use `Advanced` as a reason to lose product memory.

## 2026-05-26: Field Combat Mode Is Foundation

Decision: treat field combat mode as a foundation requirement for business MVP.

Reason:

- field users work in movement and cannot fill slow desktop-like forms;
- the product must support writing, photo, scan/proof, automatic calculation, and unfinished session recovery;
- losing current data before a report is closed would break trust immediately.

Control:

- detailed rule lives in `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`;
- Product Finance Architect must define acceptance terms;
- Backend/Data must verify autosave/session persistence coverage;
- Frontend/UX must make the flow simple enough for one-hand mobile use;
- QA must later test refresh, navigation, lock/return, network interruption, and unfinished-session recovery.

## 2026-05-26: Product Accepted Advanced And Field Combat Mode Contract

Decision: accept Product Finance Architect's contract for `Advanced = non-MVP staging` and `Field Combat Mode = MVP foundation`.

Reason:

- Product confirmed `Advanced` is not deletion and not a mandatory third layer for the first business MVP;
- Product confirmed Field Combat Mode cannot move to `Advanced`;
- Product defined the minimum as quick money fact, immediate proof/photo/scan, automatic totals, visible save/sync state, recoverable unfinished session, and deliberate submit/close.

Control:

- business MVP cannot be called complete until unfinished field-session no-data-loss behavior is proven;
- Backend/Data owns persistence/autosave/session-recovery mapping;
- Frontend/UX owns the mobile open-session experience;
- QA owns no-data-loss verification;
- Chief Auditor owns the final contradiction/gate check.

## 2026-05-26: Field Combat Backend Trace Blocked Business MVP

Decision: keep business MVP blocked and route next work to Backend Implementation Queue.

Reason:

- Backend/Data confirmed saved rows, cards, sessions, stream, group, participant, totals, and successful proof uploads are recoverable after successful save/upload;
- Backend/Data found raw typed facts are client-only until successful API save;
- Backend/Data found proof pending/failed/retry state is not durable;
- backend cannot distinguish not-yet-sent, pending retry, failed proof, and lost-before-server states.

Control:

- durable Field Combat draft/sync model is P0;
- durable proof upload state is P0;
- Frontend/UX implementation must follow the backend sync-state contract;
- QA and Chief Auditor remain downstream until backend and UX evidence exists.

## 2026-05-26: Field Combat Backend Patch Implemented

Decision: accept Backend/Data durable Field Combat backend/API patch as ready for Frontend/UX wiring, not release approval.

Reason:

- Backend added durable draft storage, client operation idempotency, durable proof upload state, recovery endpoints, migration SQL, and smoke coverage;
- Backend evidence fixture: `group_id=202`, `draft_id=1`, `tape_id=202`, `session_id=142`, `capture_id=160`;
- backend can preserve typed facts after first successful `on_the_go_field_draft_save`;
- backend can preserve proof pending/failed/retry state after proof-state begin/fail or upload attempt with `client_upload_id`;
- release readiness still requires the real UI to call these endpoints early and QA to prove recovery.

Control:

- Frontend/UX owns wiring to `on_the_go_field_draft_save`, `on_the_go_field_recover`, proof-state begin/fail/list, and `client_operation_id`;
- UI must not show proof as saved unless backend state is `uploaded`;
- autosave must not submit/include/finalize;
- QA remains blocked until Frontend wiring exists.

## 2026-05-26: Field Combat Frontend Wiring Implemented

Decision: accept Frontend/UX Field Combat UI autosave/proof-state wiring as ready for QA verification, not release approval.

Reason:

- Frontend/UX wired stable `client_draft_id`, `on_the_go_field_draft_save`, `on_the_go_field_recover`, proof-state begin/fail/list, upload with `client_upload_id`, and `on_the_go_signed_sync` with `client_operation_id`;
- UI now reports ordinary-language sync states: saved, pending, failed, and retry;
- autosave is reported as separate from submit/include/finalize;
- Director verification passed `node --check public/assets/app.js`, `git diff --check`, and local HTTP reachability.

Control:

- QA must prove the real browser/HTTP flow after refresh, module switch, proof failure/retry, upload success, idempotent save retry, and no silent submit/include/finalize;
- Chief Auditor remains downstream until QA evidence exists;
- business MVP remains blocked until QA closes Field Combat no-data-loss.

## 2026-05-26: Field Combat UI QA Blocked By Draft Identity Regression

Decision: keep business MVP blocked and route the next task to Frontend/UX.

Reason:

- QA run `20260526264416` reproduced the no-data-loss failure on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`;
- after typed content reached `Сохранено`, refresh/return opened an empty editor;
- Backend still returns the old durable draft by the original `client_draft_id`;
- UI replaces localStorage with a new empty draft when re-entering the same stream.

Control:

- Frontend/UX owns the client draft identity and recovery fix;
- Backend/Data is not the next owner unless Frontend/UX proves the API cannot support the required recovery;
- QA remains blocked until Frontend/UX fixes the identity regression and reruns browser/HTTP verification;
- Chief Auditor remains downstream and must not approve business MVP while this P0 is open.

## 2026-05-26: Field Combat Draft Identity Fix Ready For QA Recheck

Decision: accept the Frontend/UX fix as ready for QA browser/HTTP recheck, not as a pass.

Reason:

- Frontend/UX found the client-side root cause: the stream gate path reset Field Combat identity before backend recovery;
- the fix keeps/reuses the durable `client_draft_id` and tries backend recovery before creating a new identity;
- Director syntax/check verification passed for `public/assets/app.js` and changed Frontend/UX role files.

Control:

- QA Release Engineer owns the next P0 verification;
- the business MVP remains blocked until QA proves refresh/return, module switch/return, proof failure/retry, idempotent save retry, and cash/card separation;
- Backend/Data is only re-entered if QA or Frontend/UX proves an API contract blocker.

## 2026-05-26: Field Combat Proof Retry Duplication Blocks Business MVP

Decision: keep business MVP blocked and route the next P0 task to Frontend/UX.

Reason:

- QA run `20260526109674` confirmed the old empty-draft recovery blocker is fixed on mobile, tablet, and desktop;
- proof failure state and original `client_operation_id` idempotency passed;
- after proof failure, refresh, and retry, UI kept the same `client_draft_id` but resolved `tape_id` to the previous `next_tape_id`;
- the same cash expense was saved twice: once on the original tape without file and once on `next_tape_id` with file.

Control:

- Frontend/UX owns the proof retry context fix;
- proof retry must stay attached to the original saved row/card or resolve the original pending proof without creating a second money row;
- Backend/Data is not the next owner unless Frontend/UX proves the upload/API contract cannot attach proof to the original saved context;
- QA remains downstream for a browser/HTTP recheck on all three viewports.

## 2026-05-26: Field Combat Proof Retry Fix Ready For QA Recheck

Decision: accept the Frontend/UX proof retry duplicate-money fix as ready for QA browser/HTTP recheck, not as a pass.

Reason:

- Frontend/UX found the client-side cause: proof retry reused the full signed save path after active context could move to `next_tape_id`;
- the fix pins proof retry context to the original saved capture/card/tape and routes retry through proof-only upload/state handling;
- no backend/API behavior and no financial formulas were changed;
- Director verification passed `node --check public/assets/app.js` and `git diff --check` for the changed Frontend/UX files.

Control:

- QA Release Engineer owns the next P0 verification;
- business MVP remains blocked until QA proves proof failure, refresh/return, retry, idempotent operation, and cash/card separation without duplicate money rows;
- Chief Auditor remains downstream.

## 2026-05-26: Field Combat QA Evidence Ready For Chief Auditor Gate

Decision: route Field Combat no-data-loss evidence to Chief Auditor.

Reason:

- QA passed the final proof retry duplicate-money recheck in run `20260526929348`;
- original saved rows `176/178/180` stayed single and received proof files;
- previous `next_tape_id` cards `252/258/264` did not receive duplicate money rows;
- no submit/include/finalize action was observed during autosave/recovery/proof retry;
- earlier QA evidence already covered refresh/return recovery and cash/card separation.

Control:

- this closes the QA P0 recheck, not the full business MVP;
- Chief Auditor must decide the Field Combat no-data-loss gate;
- group report consolidation, archive, participants/common pot, and messages remain separate business-MVP work after this gate.

## 2026-05-26: Field Combat No-Data-Loss Gate Approved

Decision: accept Chief Auditor approval for the Field Combat no-data-loss foundation gate.

Reason:

- Chief Auditor approved the verified active `Живой отчет` Field Combat foundation;
- QA run `20260526929348` proved proof retry no longer duplicates money rows;
- typed facts after visible save, proof retry state, idempotent retry, cash/card separation, and deliberate submit/include/finalize boundaries were covered by role evidence.

Control:

- this is not full business MVP approval;
- Field Combat no-data-loss is no longer the active P0 blocker;
- next business-MVP work moves to Backend/Data for group report consolidation, archive/export, participant/common pot, and group-scope trace;
- messages, production deployment, and broader business MVP proof remain separate gates.

## 2026-05-26: Group Report Archive Package Is A Business-MVP P0

Decision: block full business MVP until FinDesk has a defined immutable group report archive package.

Reason:

- Backend/Data traced that group final report snapshot/export exists for prepared group rows and totals;
- the current backend does not expose one package that opens a closed group report together with linked participant reports, captures, proofs, accountable/advance state, audit references, and report-context messages;
- proof file access is fragmented and owner-scoped, so a reviewer/archive reader cannot reliably inspect participant proof evidence through one closed-report artifact;
- group messages are group-scoped only and are not tied to report, tape, capture, or advance context.

Control:

- Product Finance Architect owns the next decision: define exactly what the closed group report package must contain and what can remain outside MVP;
- Backend Implementation Queue starts only after Product defines the package contract;
- QA will need a multi-participant fixture after implementation;
- Field Combat no-data-loss remains approved and is not reopened by this package blocker.

## 2026-05-26: Product Contract Accepted For Closed Group Report Package

Decision: accept Product Finance Architect's contract for `Закрытый групповой отчет` and route implementation to Backend Implementation Queue.

Reason:

- Product defined the user-facing object as `Закрытый групповой отчет`, not a technical archive package;
- business MVP requires one immutable package by `report_id`;
- the package must include group summary, participant report snapshots, captures/rows, proof index and authorized proof access, accountable/advance state, report-context messages, and audit/finalization references;
- summary-only export is not enough for MVP package acceptance.

Control:

- Backend/Data owns package source implementation;
- financial formulas must not change without Product Finance Architect and Chief Auditor visibility;
- Frontend/UX and QA remain downstream until the backend package source exists;
- full business MVP remains blocked until implementation, QA multi-participant proof, and Chief Auditor gate.

## 2026-05-27: Closed Group Report Package Backend Source Implemented

Decision: accept Backend/Data implementation as ready for Frontend/UX wiring, not as business-MVP approval.

Reason:

- Backend added `ledger_group_final_report_package` by `report_id`;
- new finalizations store immutable `audit_log.details.report_package`;
- backend added `ledger_group_final_report_proof_download` so authorized reviewers can access package proof copies without original file ownership;
- HTTP fixture passed with `group_id=221`, `report_id=441`;
- no financial formulas were changed.

Control:

- Frontend/UX owns the next step: open `Закрытый групповой отчет` as one ordinary archive object;
- QA remains downstream until user-facing UI exists;
- full business MVP remains blocked until UI, QA multi-participant proof, and Chief Auditor gate;
- package-wide print/export file and direct report-linked message schema remain visible follow-ups if Frontend/Product cannot satisfy MVP through the package source.

## 2026-05-27: Closed Group Report Package UI Ready For QA

Decision: accept Frontend/UX package UI as ready for QA verification, not as business-MVP approval.

Reason:

- Frontend opens `Закрытый групповой отчет` through `ledger_group_final_report_package` by `report_id`;
- package view is not summary-only and includes participant reports, captures/proofs, money rows, accountable/advance state, messages, and audit refs;
- proof links use package proof metadata/download URLs;
- package print/PDF is available, while existing Excel/Google exports are labeled as short tables;
- no financial formulas and no backend/API behavior were changed by Frontend/UX.

Control:

- QA Release Engineer owns the next P0 verification on mobile, tablet, and desktop;
- QA must prove ordinary-user traceability and authorized proof access, not only that the endpoint returns data;
- full business MVP remains blocked until QA and Chief Auditor gate;
- package-wide file export remains a Product/Backend follow-up only if print/PDF plus package source is not enough for MVP acceptance.

## 2026-05-27: Closed Group Report Package QA Passed

Decision: route the `Закрытый групповой отчет` package evidence to Chief Auditor.

Reason:

- QA created a fresh multi-participant fixture `group_id=222`, `report_id=454`;
- package API returned one closed report object with summary, participant reports, captures, proofs, money rows, accountable state, messages, and audit refs;
- authorized reviewer proof downloads passed through package proof URLs;
- UI passed on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`;
- cash/card separation, accountable carryover, short-table export labels, print/PDF, and immutability after later current activity passed.

Control:

- this is QA pass, not Chief Auditor approval;
- Chief Auditor owns the next business-MVP gate;
- no new Backend/Data or Frontend/UX P0 was opened by QA;
- full business MVP remains blocked until Chief Auditor decision.

## 2026-05-27: Closed Group Report Package Gate Approved

Decision: accept Chief Auditor approval for the `Закрытый групповой отчет` package gate.

Reason:

- Chief Auditor approved the verified package as one immutable archive object by `report_id`;
- the package preserves group received money, participant responsibility, physical cash vs card/noncash split, accountable/open employee cash responsibility, authorized proof access, message/audit references, and immutability after later current activity;
- QA evidence covered API, UI, proof downloads, print/PDF, short-table export labels, and mobile/tablet/desktop layout.

Control:

- this closes the group-report/archive package business-MVP block for the verified new-package flow;
- this is not automatic full business MVP approval;
- package-wide downloadable file export, first-class report-linked message schema, and legacy reports without `report_package` remain follow-ups unless upgraded by Project Director/Product;
- Project Director owns the final business-MVP readiness review and remaining-scope decision.

## 2026-05-27: Final Readiness Classification

Decision: classify remaining business-MVP work and route the next task to QA Release Engineer.

Reason:

- Field Combat no-data-loss is approved;
- `Закрытый групповой отчет` package is approved;
- the money-core loop is materially proven for new data;
- remaining uncertainty is product-surface preservation and navigation, not another financial formula/API blocker.

Control:

- residual surface QA is P0 before requesting final full business-MVP gate;
- package-wide downloadable file export, first-class report-linked message schema, and legacy package migration stay P1 unless upgraded by CEO/Product;
- Travel full settlement engine, full Business Desk integration, full social chat archive, AI analytics, fraud scoring, and third-party accounting integrations stay post-MVP/Advanced;
- production deployment package and production smoke are production P0, separate from product readiness.

## 2026-05-27: Residual Surface QA Passed

Decision: accept QA Release Engineer residual surface QA and route the full business-MVP evidence package to Chief Auditor.

Reason:

- QA run `20260527968710` passed on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`;
- group messages send/list/unread/mark-read worked and stayed group-scoped;
- `Закрытый групповой отчет #454` kept report-context message refs understandable and marked unlinked group discussion;
- Business Desk/proforma create/list/open/print worked and did not mutate group `222` operational `ledger_report`;
- Travel / Trip with Friends stayed visible as staged product memory;
- Advanced stayed reachable as non-MVP staging;
- On the Go, report review/finalization area, closed package, group messages, Business Desk/proforma, Travel, and Advanced were reachable without blocking overlap in the checked path.

Control:

- residual surface QA is closed for the checked business-MVP path;
- no new Backend/Data or Frontend/UX P0 is opened by this QA pass;
- full business-MVP approval still requires Chief Auditor gate;
- production deploy remains a separate package/smoke/rollback decision.

## 2026-05-27: 100 Percent MVP Control Opened

Decision: split `100 percent MVP` into final product gate plus production deploy gate.

Reason:

- product readiness and production upload are different risks;
- Backend/Data found no known backend/API P0 for business-MVP product readiness, but production deploy readiness is still blocked by file selection, DB/runtime migration planning, backup/rollback, and smoke;
- the working tree is dirty and contains broad unrelated or unclassified changes;
- CEO wants practical completion, not only repeated local checks.

Control:

- Chief Auditor owns the full business-MVP product gate;
- Backend/Data, Frontend/UX, and QA Release Engineer own deploy readiness planning;
- Project Director owns the stop/go decision after role reports;
- no FTP/database production action is authorized until deploy package, backup, migration, smoke, and rollback are explicit.

## 2026-05-27: Full Business-MVP Product Gate Approved

Decision: accept Chief Auditor approval for the full business-MVP product gate.

Reason:

- Foundation MVP, Field Combat no-data-loss, closed group report package, and residual surface QA all passed;
- Product Finance Architect, Frontend/UX, QA Release Engineer, and Backend/Data found no product-gate P0 for the checked new-data path;
- Backend/Data separated production deploy readiness from product readiness;
- Chief Auditor approved the full product gate with no product blocker.

Control:

- business-MVP product gate is closed for the checked new-data path;
- production deploy is not approved by this decision;
- Project Director must complete deploy package, backup, migration, rollback, and production smoke planning before any live-site action.

## 2026-05-27: Production No-Go Until DB/Backup Controls

Decision: select a narrow MVP runtime bundle but block production upload until server-side DB preflight, backups, runtime SQL decision, rollback, and smoke are ready.

Reason:

- full dirty-tree deploy is too broad for a controlled MVP release;
- selected runtime files have been classified and explicit decisions were made for `app/ai.php` and `public/service-worker.js`;
- FTP is reachable from the current environment, but DB port `3306` is not reachable;
- local MySQL/MariaDB/PHP CLI tools are unavailable;
- production DB backup/migration cannot be verified safely from this environment.

Control:

- no FTP upload before DB/file/storage backups and schema preflight;
- no production database change before engine/schema compatibility is known;
- no CEO production use before QA production smoke passes;
- docs/control-plane, local reset tools, local scripts, and test outputs remain excluded from production runtime.

## 2026-05-27: Interface Language Fallback

Decision: English is the default interface language when the user's system language is not in the supported language list.

Reason:

- FinDesk is intended to work beyond Russian-speaking users;
- the supported list is explicit and finite;
- unsupported system languages must not fall back to Russian by accident;
- production/PWA behavior should be predictable after install.

Control:

- supported interface languages are `ru`, `en`, `de`, `it`, `es`, `sr`, and `zh`;
- `sr` covers Serbian / Montenegro / Croatian-family staging for MVP;
- Chinese uses `zh-Hans`;
- if browser/system language cannot be normalized to the supported list, use `en`;
- language choice persists in `localStorage` under `finDeskLanguage`;
- language coverage audit is P1 before international public launch, not a blocker for Russian-first operational MVP.

## 2026-05-27: SEO Growth Role Added

Decision: add a sixth AI Team role: SEO Growth Engineer.

Reason:

- FinDesk needs an internet growth strategy, not only a working PWA;
- SEO work must cover visible public content, hidden metadata, PWA install surface, sitemap/robots, structured data, multilingual policy, and production smoke;
- SEO must not accidentally expose private finance data or personal app screens to crawlers.

Control:

- SEO Growth Engineer owns strategy, search positioning, content map, measurement plan, and SEO task routing;
- Frontend/UX owns public UI/meta/PWA implementation;
- Backend/Data owns crawler boundaries, headers, routes, and production infrastructure risks;
- QA Release Engineer owns SEO/PWA verification;
- Chief Auditor remains the release gate when SEO changes could affect privacy, proof access, or product trust;
- `/app.php`, `/api.php`, and `/storage/` remain non-public search surfaces unless a later explicit decision changes that boundary.

## 2026-05-27: SEO/PWA Implementation Added To Release Package

Decision: include SEO/PWA public-surface files in the selected production package candidate, while keeping production upload blocked until deploy controls are complete.

Reason:

- SEO Growth strategy is complete;
- Frontend/PWA SEO implemented public landing metadata/content, JSON-LD, robots, sitemap, and manifest updates;
- Backend/Infra SEO confirmed noindex/crawler boundaries and production header risks;
- QA SEO local non-visual checks passed;
- the remaining SEO blockers are environment/production checks, not discovered product contradictions.

Control:

- `/` is the public indexable SEO surface;
- `/app.php` remains `noindex,nofollow`;
- `/api.php` and `/storage/` remain non-public crawler surfaces;
- SEO/PWA deployment must include `public/index.php`, `public/robots.txt`, `public/sitemap.xml`, and `public/manifest.webmanifest`;
- mobile visual QA and production SEO/PWA smoke remain required after deploy controls are ready;
- production NO-GO remains in force until DB/files/storage backup, schema preflight, runtime SQL decision, rollback owner, and smoke owner are confirmed.

## 2026-05-27: Brkovic SEO Office Created

Decision: move shared SEO strategy and knowledge to a brkovic-level office outside the FinDesk-only project.

Reason:

- SEO must serve multiple brkovic properties, not only `finance.brkovic.ltd`;
- `game.brkovic.ltd` will need its own game/discovery SEO for Captain Ether and Watch Officer;
- `brkovic.ltd` must act as umbrella/trust/operator hub;
- shared SEO lessons, standards, failures, and measurement rules should be accessible to every project SEO role.

Control:

- shared SEO office path: `/home/alexey/GitHub/BRKOVIC_SEO_OFFICE`;
- FinDesk keeps project-specific SEO implementation and QA in its own repo;
- shared SEO office owns common strategy, standards, content clusters, project map, and anti-patterns;
- no project code is changed from the shared office without a project-owner task;
- private app/API/storage boundaries remain project-level P0 controls.

## 2026-05-27: Brkovic SEO Knowledge Base First Version Ready

Decision: accept the first working shared SEO knowledge base.

Reason:

- required top-level SEO Office documents are present;
- project briefs exist for `finance.brkovic.ltd`, `game.brkovic.ltd`, and `brkovic.ltd`;
- `captain-ether` and `watch-officer` are now attached GitHub repositories and local paths;
- shared SEO rules now separate public search surfaces from private app/admin/API/storage surfaces across projects.

Control:

- shared SEO Office remains at `/home/alexey/GitHub/BRKOVIC_SEO_OFFICE`;
- future SEO roles start with `00_START_HERE.md`;
- project-specific implementation must still be assigned to each project owner;
- FinDesk production deploy remains governed by its separate production NO-GO until DB/backup controls are complete.

## 2026-05-27: Game Repositories Attached To Shared SEO Office

Decision: record `captain-ether` and `watch-officer` as real game repositories in the shared Brkovic SEO Office.

Reason:

- CEO created the GitHub repositories;
- both repositories were cloned locally, connected to the shared SEO layer, committed, and pushed;
- SEO Office can now plan game SEO against actual paths instead of conceptual slots.

Control:

- `captain-ether`: `/home/alexey/GitHub/captain-ether`, remote `git@github.com:vetus-nauta/captain-ether.git`, current GitHub HEAD `4502b10`;
- `watch-officer`: `/home/alexey/GitHub/watch-officer`, remote `git@github.com:vetus-nauta/watch-officer.git`, current GitHub HEAD `c022390`;
- game production SEO verification still needs confirmed hosting/deploy state;
- FinDesk deploy NO-GO remains unchanged.

## 2026-05-27: Game SEO Growth Engineer Activated

Decision: create a dedicated Game SEO Growth Engineer cabinet inside the shared SEO Office.

Reason:

- game SEO now has real repositories, not just conceptual project slots;
- Captain Ether and Watch Officer need separate route, repository, and claim boundaries;
- deeper SEO implementation should wait for Game Owner routing decisions.

Control:

- role folder: `/home/alexey/GitHub/BRKOVIC_SEO_OFFICE/roles/02_game_seo_growth_engineer`;
- first full report: `FINDINGS.md`;
- tasks to others: `TASKS_TO_OTHERS.md`;
- no game code or production route changes were made in this step.

## 2026-05-27: FinDesk MVP Runtime Artifact Built

Decision: materialize the selected narrow FinDesk MVP runtime bundle as a local release artifact.

Reason:

- product MVP is approved;
- production gate was blocked partly because the deploy package existed only as a candidate list;
- a concrete artifact reduces the risk of uploading the dirty worktree blindly.

Control:

- release id: `findesk-mvp-runtime-20260527T185423Z`;
- artifact: `backups/findesk-mvp-runtime-20260527T185423Z/findesk-mvp-runtime-20260527T185423Z.tar.gz`;
- checksum: `0bf15e78f3e17f4d40f7444fe92213d2cee9f6335712eee7851f294563d96dfc`;
- record: `docs/AI_TEAM/24_MVP_RUNTIME_ARTIFACT_2026-05-27.md`;
- upload remains NO-GO until production DB backup, schema preflight, runtime SQL decision, rollback owner, and smoke owner are confirmed.

## 2026-05-27: Production File / Storage Backup Completed

Decision: accept the read-only FTP backup of the production file tree as the MVP deploy file/storage backup control.

Reason:

- production upload must not start without a rollback source for deployed files and uploaded storage;
- the backup downloaded the production `/finance.brkovic.ltd` tree without upload, edit, delete, or schema operations;
- download result was `110` files and `0` errors.

Control:

- backup id: `prod-files-before-mvp-20260527T185902Z`;
- archive: `backups/prod-files-before-mvp-20260527T185902Z.tgz`;
- checksum: `b095d4c6c8cf35ac0fbc76657fd7653d4757596b58944c81a13325122b1c8823`;
- record: `docs/AI_TEAM/25_PRODUCTION_FILE_BACKUP_2026-05-27.md`;
- this does not cover the production database;
- upload remains NO-GO until DB backup, DB schema preflight, runtime SQL decision, rollback owner, and smoke owner are confirmed.

## 2026-05-27: FinDesk MVP Production Deploy Completed

Decision: deploy the final narrow MVP runtime package to production and close the checked MVP production gate.

Reason:

- business-MVP product gate was already approved;
- production file/storage backup and DB backups were completed;
- MariaDB schema preflight passed;
- runtime SQL was applied and verified;
- production HTTP/API smoke passed on the live domain.

Control:

- final release id: `findesk-mvp-runtime-20260527T192800Z`;
- artifact checksum: `c4e1a79d1bd8091aa21bd7ac21c685c95f1389d62bf572d0ba98b481ccb4f7f4`;
- production deploy report: `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`;
- smoke id: `20260527192655`;
- smoke group id: `4`;
- final report id: `20`;
- temporary DB-gate script was removed after migration and returned `404`;
- production browser visual matrix remains an optional follow-up because this shell has no browser automation runtime.

## 2026-05-27: Production Physical Multi-Employee QA Scenario

Decision: run a real production QA scenario with one admin and three employees before treating CEO live review as complete.

Reason:

- CEO needs proof that ordinary group money flow works beyond API smoke;
- the scenario exercises received money, accountable cash, participant spends, overrun, no-spend remainder, exports, final report, and archive;
- this is the closest current MVP proof to real operational finance work.

Control:

- Product Finance Architect expected totals: `EUR 284` expenses and `EUR 716` net group balance;
- employee 2 overrun must remain visible as `EUR 36` reimbursement/overrun;
- employee 3 must not get fake expense and must preserve `EUR 117` remainder unless explicitly returned;
- QA task card: `docs/AI_TEAM/roles/04_qa_release_engineer/TASK_CARD_PRODUCTION_MULTI_EMPLOYEE_2026-05-27.md`;
- full evidence stays in the QA role folder; Project Director chat receives one short report only.

## 2026-05-27: Participant-Control Meaning For Employee Overrun

Decision: treat accepted employee overrun as first-class participant-control data in final report, closed package, export, and print.

Reason:

- Product Finance confirmed `admin_cash_left` means physical cash held by the administrator before an explicit reimbursement payment is recorded;
- the production QA blocker hid employee 2 overrun in audit refs and exposed only `532 + 184 = 716`;
- CEO's money tree requires the visible holder equation `568 + 67 - 36 + 117 = 716`.

Control:

- local patch applied to `app/ledger.php` and `public/assets/app.js`;
- local HTTP fixture passed: `group_id=223`, `report_id=499`;
- accepted visible totals: `admin_cash_left=568`, positive employee remainders `184`, reimbursement due `36`, employee net `148`, balance `716`;
- production hotfix deployed only `app/ledger.php` and `public/assets/app.js`;
- production hotfix backup: `prod-hotfix-before-participant-control-20260527T204210Z`, checksum `39550b6b4b4938d009085af33e2ece1bde1dc64477c1f84aded0299a23770471`;
- director production smoke passed: `group_id=9`, `report_id=84`;
- formal QA gate remains open until QA Release Engineer records an independent production recheck.

## 2026-05-27: Default Invited Employee Rights

Decision: default invited group participant is `base` and receives only operational group capture/self-control, not group data access.

Reason:

- CEO clarified that invited employees must be able to work normally in the app as personal users;
- group invitation must not expose group ledger, reports, archive, messages, roles, money management, or other participants' money by default;
- operational capture must remain simple and available for the employee's own rows and accountable/self-control.

Control:

- `base` permissions deny group reports, group ledger write, money management, moderation, and member management;
- base field tape no longer seeds from full group working cash balance;
- base draft participant identity is forced to self;
- group messages require manager/admin/group-data rights;
- group UI hides invite/messages/members/rename panels for base access;
- production hotfix deployed `app/on_the_go.php`, `app/messages.php`, `public/app.php`, and `public/assets/app.js`;
- production hotfix backup: `prod-hotfix-before-base-rights-20260527T210230Z`, checksum `d6344267925c9742f4f6f21e3e4609942d53544fe2ea998a5eaf9904afe8d732`;
- director production rights smoke passed: `group_id=10`, employee user `27`;
- formal QA gate remains open until QA Release Engineer records an independent production recheck.

## 2026-05-27: Base Employee `message_unread` Must Fail Closed Without SQL Error

Decision: default base employees must receive a safe empty unread-message response instead of SQL/server errors or group message exposure.

Reason:

- QA production recheck accepted participant-control but found `message_unread` returning HTTP `500` for base employee;
- the endpoint runs automatically in the PWA header/feed path, so a base employee cannot carry a hidden server error;
- default employee rights require no group-message exposure.

Control:

- backend alias collision fixed in `app/messages.php`;
- deployed only `app/messages.php`;
- production backup: `prod-hotfix-before-message-unread-20260527T212247Z`, checksum `910dfe9a79731f30d3fab4511f078ea39c47a9366020db1237d6e0d0ebf48891`;
- production file checksum after upload: `75e8f9c61fbcb5f595bc8ffb56dd36b42fa8beee8b08cb88d6e99c33e815cb15`;
- director production smoke passed: `group_id=19`, employee user `57`, `message_unread ok=true/unread_count=0`;
- QA Release Engineer must rerun the default base employee rights slice before the P0 gate closes.

## 2026-05-27: Production Base Employee Rights P0 Closed After Rerun

Decision: base employee production rights gate is closed.

Reason:

- QA Release rerun confirmed no regression in default base employee restriction after the `message_unread` alias hotfix.

Control:

- run id: `20260527212947`
- group id: `20`
- final report id: `194`
- base user id: `59`
- production proof: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_base_rights_rerun_2026-05-27/SUMMARY.md`
- key pass points:
  - base employee has only operational/fixed-role scope;
  - `message_unread`: HTTP `200`, `ok=true`, `unread_count=0`;
  - `message_list`/`message_send`: `access_denied`;
  - no group report/final report/archive export/data ownership exposure.

Current gate state:

- foundation gate: approved
- MVP/business-MVP readiness gates: passed
- remaining open items are P1/P2 and optional production QA/SEO/visual follow-ups outside this rerun.

## 2026-05-28: Receipt Scanner Is A First-Class Proof Module

Decision: create `Receipt Scanner` as a separate FinDesk proof module, not as a renamed camera upload.

Reason:

- CEO requires field work to be as simple as write/photo/scan while still producing clean evidence;
- Apple Notes-style scanning cannot be assumed inside Safari/PWA as a native feature;
- FinDesk needs audit-safe proof: original source plus cleaned PDF plus metadata, linked to the money row and final archive;
- OCR and automatic extraction may help later but must not become financial truth without review.

Control:

- task card: `docs/AI_TEAM/31_RECEIPT_SCANNER_TASK_CARD_2026-05-28.md`;
- Product Finance Architect defines the proof contract;
- Frontend/UX defines scanner screen and one-hand mobile flow;
- Backend/Data defines original/PDF/metadata/proof-state storage;
- QA Release defines device and no-data-loss matrix;
- Chief Auditor defines evidence acceptance gate;
- runtime code implementation waits until the first slice is selected.

## 2026-05-28: Receipt Scanner Full Sprint Started Locally

Decision: start the full local Receipt Scanner sprint after role contracts were accepted.

Reason:

- the scanner is part of the field-combat foundation;
- a PDF-only upload is not enough for audit;
- FinDesk needs original source, cleaned PDF, metadata, hashes, and final package access.

Control:

- sprint doc: `docs/AI_TEAM/32_RECEIPT_SCANNER_SPRINT_2026-05-28.md`;
- runtime code changed locally only;
- production deploy remains blocked until QA Release and Chief Auditor approve scanner evidence;
- existing attachments stay backward-compatible as `attachment`;
- scanner artifacts are separated as `scanner_original` and `scanner_cleaned_pdf`.

## 2026-05-28: Receipt Scanner Local Browser QA Passed

Decision: accept QA Release run `20260528RSQA01` as local browser/HTTP evidence for the file-input scanner path, but not as physical camera/PWA evidence.

Evidence:

- mobile `390 x 844`, tablet `820 x 1180`, desktop `1440 x 900`;
- scanner modal opened from Live Report;
- attaching generated PDF kept one money row per viewport;
- backend stored `scanner_original` and `scanner_cleaned_pdf` in one proof bundle;
- repeated original upload, repeated PDF upload, and repeated signed sync were idempotent;
- result JSON and screenshots are in `/tmp/findesk-receipt-scanner-20260528RSQA01/`.

Control:

- Chief Auditor must gate the local scanner slice before release routing;
- physical camera and installed iPhone/Android PWA behavior remain a separate device QA requirement before claiming device-level scanner readiness;
- production deploy remains blocked.

## 2026-05-28: Receipt Scanner Local Gate Approved, Device Gate Required

Decision: Chief Auditor approved only the local browser/HTTP file-input scanner slice.

Evidence:

- Chief Auditor gate file: `docs/AI_TEAM/roles/05_chief_auditor/RECEIPT_SCANNER_LOCAL_EVIDENCE_GATE_2026-05-28.md`;
- QA run `20260528RSQA01`;
- final-package recheck `group_id=226`, `report_id=516`.

Control:

- production scanner deploy requires real-device QA on iPhone Safari PWA and Android Chrome;
- device QA card: `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`;
- without this gate, scanner can only be described as locally approved for the file-input path, not device-ready.

## 2026-05-28: Local Production Leftovers Recheck Passed

Decision: accept the local scanner/UX/backend leftovers package as locally QA-rechecked, but not production-ready.

Evidence:

- Frontend/UX fixed login fallback/cache versions, `Живой отчет` state persistence, mobile card/action overlap hardening, scanner `Закрыть`, and Escape close.
- Backend/Data fixed `group_delete` as soft archive that works without optional `updated_at` columns and preserves financial evidence.
- QA run `20260528LOCALLEFTOVERS01` passed.
- QA fixture: `group_id=233`, base member denied `admin_required`, admin soft archive `ok=true`, evidence counters preserved, repeated delete `already_deleted=true`.
- QA artifact: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/local_leftovers_recheck_20260528/SUMMARY.md`.

Control:

- production release is still NO-GO until deploy checklist blockers close;
- real-device scanner/PWA camera gate remains separate;
- screenshot-level mobile overlap was not accepted in this shell because browser automation libraries were unavailable;
- next deploy must use an explicit file bundle, DB preflight, backup/rollback, and production smoke.

## 2026-05-28: Limited Scanner/UX/Backend Deploy Candidate Created

Decision: create a new limited deploy candidate for the locally accepted scanner/UX/backend fixes, while keeping production status as NO-GO.

Candidate:

- `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`

Runtime scope:

- `app/groups.php`;
- `app/ledger.php`;
- `app/on_the_go.php`;
- `public/api.php`;
- `public/app.php`;
- `public/assets/app.js`;
- `public/assets/app.css`;
- `public/assets/i18n.js`.

Control:

- no full dirty-tree upload;
- scanner SQL requires DB preflight;
- real-device scanner gate remains required for device-ready claim;
- production upload waits for backup/rollback and smoke.

## 2026-05-28: Deploy Preflight Sprint Started And Local Artifact Built

Decision: execute deploy-preflight for candidate `34` up to go/no-go, without uploading to production.

Evidence:

- sprint doc: `docs/AI_TEAM/35_DEPLOY_PREFLIGHT_SPRINT_2026-05-28.md`;
- DB read-only SQL: `docs/AI_TEAM/36_DB_PREFLIGHT_READONLY_SQL_2026-05-28.sql`;
- local artifact: `docs/AI_TEAM/37_LIMITED_CANDIDATE34_ARTIFACT_2026-05-28.md`;
- artifact path: `backups/findesk-limited-candidate34-20260528T134812Z/findesk-limited-candidate34-20260528T134812Z.tar.gz`;
- SHA256: `a159c4000a580db314981529bdb3812dbed953b18b93dd9148b2e9d60f7cffd9`;
- `node --check public/assets/app.js` passed;
- `node --check public/assets/i18n.js` passed;
- `git diff --check` passed for preflight docs and API hardening;
- `/api.php?action=current_user` returned `ok=true`.

Control:

- `public/api.php` now treats `app/ai.php` as optional to avoid broadening the limited release or fatalling the API if Advanced AI is excluded;
- production DB preflight has not yet been run from this shell because `mysql`, `mysqldump`, and `php` are unavailable here;
- production remains NO-GO until DB preflight, backup/rollback, PHP/HTTP smoke, and CEO limited-release/device-scanner decision are recorded.

## 2026-05-28: Candidate 34 Limited Production Deploy Passed

Decision: deploy candidate `34` as a limited production release for scanner file-input/runtime support, UX leftovers, and backend soft-delete support.

Evidence:

- deploy report: `docs/AI_TEAM/38_CANDIDATE34_PRODUCTION_DEPLOY_REPORT_2026-05-28.md`;
- DB backup: `backups/prod-db-before-candidate34-20260528T135737Z/findesk-db-before-candidate34-20260528T135737Z.sql.gz`;
- DB backup checksum: `c439ae9b49e3394fcd18272134aae22abcd1bc37d53954e0af8048e58e92d738`;
- file/storage backup: `backups/prod-files-before-candidate34-20260528T135752Z.tgz`;
- file/storage backup checksum: `5097fbc9beb0affbf109d82a82c299de26985fc558801ece66ab4ad4d51d1c26`;
- DB engine: `11.4.10-MariaDB-cll-lve-log`;
- runtime SQL applied: `deploy/on_the_go_sessions_runtime.sql`;
- post-SQL missing scanner/runtime columns and indexes: none;
- production smoke run: `prod-candidate34-20260528140302`;
- production smoke fixture: group `24`, report `218`, tape `82`, capture `143`.

Control:

- no full dirty-tree upload;
- temporary DB-gate was removed and returned `404`;
- production login fallback copy and asset version are updated;
- current and historical exports passed production smoke;
- scanner proof bundle storage passed production smoke;
- real-device scanner/PWA camera behavior remains outside this release claim until device QA passes.

## 2026-05-28: Owner Can Return Own Submitted Legacy Card

Decision: allow the owner of a `submitted` Live Report card to return it directly to draft even when the old group/moderator scope is no longer active.

Reason:

- legacy test cards can be stuck after a group is soft-archived;
- routing the owner to `Запросить исправление` is wrong when there is no active administrator to process the request;
- direct owner return is limited to `submitted` cards and does not unlock included/final-report evidence.

Evidence:

- hotfix report: `docs/AI_TEAM/39_OWNER_SELF_RETURN_HOTFIX_PRODUCTION_2026-05-28.md`;
- production smoke run: `prod-owner-self-return-20260528140915`;
- fixture: group `25`, tape `84`;
- file backup before hotfix: `backups/prod-files-before-owner-self-return-hotfix-20260528T140737Z.tgz`;
- backup checksum: `951f5d1e584fff40849d2bd1be4583fe10c074f450f81b0c843e4700cd64bf3d`.

Control:

- financial formulas were not changed;
- final-report included cards remain protected;
- temporary DB-gate was removed and returned `404`.

## 2026-05-28: Proof Files Must Be Visible On Their Money Row

Decision: every saved PDF/scan attached to a Live Report capture must be visible as a row-level link, not only counted as `вложений N`.

Reason:

- the proof is part of the money row;
- if the UI hides the link, the user reasonably thinks the PDF disappeared;
- admins checking employee reports need direct access to employee proof files for permitted group rows.

Evidence:

- hotfix report: `docs/AI_TEAM/40_PROOF_LINKS_HOTFIX_PRODUCTION_2026-05-28.md`;
- production smoke run: `prod-proof-links-20260528153719`;
- fixture: group `26`, tape `87`, capture `145`, file `9`;
- file backup before hotfix: `backups/prod-files-before-proof-links-hotfix-20260528T153541Z.tgz`;
- backup checksum: `f5658008282bd3c80d0f5a587a0ecebafec94f6334ada9df6d2b3884aefd8971`.

Control:

- backend stores files in `storage/documents/on-the-go/<year>/...`;
- DB relation is `on_the_go_files.capture_id -> on_the_go_captures.id`;
- download route is `/api.php?action=on_the_go_file_download&id=<file_id>`;
- financial formulas were not changed.

## 2026-05-28: Proof Files Need In-App Viewing, Not Only Links

Decision: add an in-app proof viewer for photo/PDF proofs attached to Live Report rows.

Reason:

- a direct link is not enough on mobile/PWA;
- users must be able to inspect a proof without losing the current card context;
- proof review is part of the records page, not a separate hidden technical path.

Evidence:

- hotfix report: `docs/AI_TEAM/41_PROOF_VIEWER_HOTFIX_PRODUCTION_2026-05-28.md`;
- production smoke run: `prod-proof-viewer-20260528154804`;
- fixture: group `28`, tape `91`, capture `148`, image file `14`, PDF file `15`;
- file backup before hotfix: `backups/prod-files-before-proof-viewer-hotfix-20260528T154611Z.tgz`;
- backup checksum: `6844ac266619212830e2d769b6b8db67b7db01efe3aeec909362151f49c533d2`.

Control:

- no DB migration;
- no financial formula changes;
- temporary DB-gate was removed and returned `404`;
- QA Release Engineer remains responsible for broader records-page UI QA.

## 2026-05-28: Records Page Must Use Group Scope For Group Admins

Decision: the normal `Живые отчеты` records page must request cards with `group_id` when the current user has group report access in the selected/active group.

Reason:

- direct card/API access is not enough for a working admin flow;
- a group admin must discover employee Live Report cards from the visible records page;
- base employees must keep personal-only visibility inside the same group.

Evidence:

- Frontend/UX report: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`;
- local smoke fixture: group `235`, admin tape `307`, employee tape `308`;
- local Playwright mobile fixture: group `244`, employee tape `332`, capture `217`, proof controls `2`;
- admin list with `group_id` sees both cards;
- base employee list with `group_id` sees only own card.

Control:

- no backend/API contract change;
- no DB migration;
- no financial formula change;
- records panel explicitly closes the intermediate stream gate before showing the list;
- QA browser matrix passed in run `20260528RECORDSRECHECK04`;
- production hotfix report: `docs/AI_TEAM/42_RECORDS_ADMIN_DISCOVERY_HOTFIX_PRODUCTION_2026-05-28.md`;
- production smoke passed in run `prod-records-hotfix-20260528161828`;
- temporary DB-gate was removed and returned `404`.

## 2026-05-28: Scanner Modal Must Fit Phone Viewport Before Device Gate

Decision: scanner modal geometry is a release-quality UI requirement even before the real-device camera/PWA gate is closed.

Reason:

- the scanner is part of Field Combat proof capture;
- if scanner controls overflow the phone viewport, the user cannot reliably attach proof in motion;
- browser file-input scanner QA and real-device camera QA must start from a non-overflowing modal.

Evidence:

- local Playwright run: `local-notes-ui-20260528162458`;
- production hotfix report: `docs/AI_TEAM/43_SCANNER_FIT_HOTFIX_PRODUCTION_2026-05-28.md`;
- production smoke run: `prod-scanner-fit-20260528162815`;
- production mobile `390x844`: notes field `578px`, scanner modal `390x844`, controls reachable.

Control:

- no backend/API contract change;
- no DB migration;
- no proof storage change;
- no financial formula change;
- real-device iPhone Safari/PWA camera behavior remains a separate gate.

## 2026-05-28: Records Window Must Scroll Its Long Column Internally

Decision: the `Живые отчеты` records window must keep the header visible and scroll the records list internally when the card column is long.

Reason:

- mobile users must be able to review many saved cards without losing context;
- a long list must increase `scrollHeight`, not compress cards into unreadable rows;
- Safari/PWA needs explicit momentum scrolling on the inner list container.

Evidence:

- production hotfix report: `docs/AI_TEAM/44_RECORDS_SCROLL_HOTFIX_PRODUCTION_2026-05-28.md`;
- local Playwright run: `local-scroll-20260528164027`;
- production smoke run: `prod-records-scroll-20260528164351`;
- mobile `390x844`: list `clientHeight=621`, `scrollHeight=3183`, `scrollTop=2562`.

Control:

- no backend/API contract change;
- no DB migration;
- no proof storage change;
- no financial formula change;
- temporary DB-gate was not used.

## 2026-05-28: Open Items Sprint Candidate

Decision: package the remaining known open items as a limited sprint candidate `20260528-open-sprint1`.

Reason:

- after `records-scroll1`, no current production P0 remained for the core money loop;
- the remaining items were known and bounded: real-device scanner/PWA camera gate, language/PWA fallback audit, legacy package fallback, first-class message links, and package-wide archive export;
- several items can be improved locally without changing financial formulas or reopening approved MVP gates.

Implemented locally:

- `group_messages` gets first-class optional context columns: `report_id`, `tape_id`, `capture_id`, `advance_id`;
- group messages API returns `context_links` and validates context ids against the selected group;
- new final report packages include direct linked messages when links exist before finalization;
- new action `ledger_group_final_report_package_export` downloads a JSON package export, with legacy snapshot JSON fallback when `report_package` is missing;
- language/PWA state now exposes `QL_LANGUAGE_STATE` and unsupported system language fallback is explicit English;
- scanner modal copy and input setup now state and preserve the browser/PWA camera boundary;
- app asset and service-worker versions are bumped to `20260528-open-sprint1`.

Control:

- this sprint does not claim real-device scanner readiness;
- JSON package export is the first package-wide export, not a ZIP proof-binary bundle;
- production upload must use a selected bundle and apply `deploy/messages_foundation.sql` after backup/preflight;
- production smoke must prove asset version, language markers, direct message context, package message inclusion, and JSON export;
- full dirty-tree upload remains disallowed.

## 2026-05-28: Open Items Sprint Deploy Attempt Blocked

Decision: keep `20260528-open-sprint1` as a local production candidate, but do not claim production deployment from the current shell.

Reason:

- local implementation and local smoke passed after the local `messages_foundation.sql` migration, including package end-to-end export for `report_id=587`;
- the current shell has no `FINDESK_FTP_HOST`, `FINDESK_FTP_USER`, `FINDESK_FTP_PASS`, `FINDESK_FTP_ROOT`, or `FINDESK_DB_GATE_URL`;
- without FTP variables, production backup/upload/DB-gate apply/smoke cannot be executed safely.

Control:

- deploy report: `docs/AI_TEAM/46_OPEN_ITEMS_SPRINT_DEPLOY_BLOCKED_2026-05-28.md`;
- no production file upload was executed in this attempt;
- no production DB change was executed in this attempt;
- next deploy must start with FTP backup and DB-gate preflight, then apply `deploy/messages_foundation.sql`.

## 2026-05-28: Fast Entry Must Be Calm, Not ERP

Decision: accept the Google Drive UI handoff as the next large UX direction and start with a narrow local fast-entry cleanup.

Reason:

- the product problem is now interface exposure, not missing backend capability;
- daily users need a lightweight operational entry surface;
- report consolidation, balances, merge/export, archive, and admin detail belong on a separate administrator report screen.

Local first slice:

- report: `docs/AI_TEAM/47_FAST_ENTRY_UX_BACK_LOCAL_2026-05-28.md`;
- fast-entry proof controls are modernized to `Фото`, `Скан`, `Файл`;
- saved proof access is exposed from the fast-entry card;
- `Нал` is replaced with `Наличные`;
- edit/finish no longer overlaps amount metrics;
- decorative lower-right pseudo-card is disabled;
- fixed expense preview scroll is hidden in editor mode;
- browser Back receives app-step history for key navigation transitions.

Control:

- no backend/API/storage/report formula change in this slice;
- browser visual QA remains required before production routing;
- this slice is a first cleanup, not the full two-level UI redesign.

## 2026-06-04: Atlas MongoDB Becomes Primary Persistence Target

Decision: use MongoDB Atlas as the new primary persistence target for the current FinDesk Product Shell and stop treating the temporary local fallback API as a working data layer.

Reason:

- the old production database is legacy and may contain stale data;
- the current product state lives in the local codebase and needs a clean backend connection before further UX work is reliable;
- workspace creation must persist across refresh/restart and must not depend on local browser or `/tmp` fallback state.

Implemented locally:

- local Atlas-backed server added at `server/findesk-atlas-server.js`;
- runtime dependency added on the official MongoDB Node driver;
- local secret source is `storage/secrets/mongodb_uri` or `FINDESK_MONGO_URI`;
- database name defaults to `finance_brkovic_ltd` and can be overridden with `FINDESK_MONGO_DB`;
- `current_user`, `group_list`, `group_create`, `group_trash`, `group_trash_list`, `group_restore`, and `findesk_workspace_set` are available through the local Atlas server;
- recovered workspace `Yacht: Ckaudia Z` was written into Atlas and survived local server restart.

Control:

- this is not a production deploy;
- only the workspace/groups persistence slice is connected to Atlas so far;
- legacy PHP/MySQL API remains in the repository until each module is migrated or retired deliberately;
- Atlas URI must stay in local secrets or environment variables, never in public JS or committed files.

## 2026-06-04: Yacht State Persists In Atlas

Decision: persist Yacht workspace state in Atlas per workspace instead of relying only on browser `localStorage`.

Implemented locally:

- `yacht_state_get` and `yacht_state_save` added to the Atlas-backed local server;
- yacht state stores `profile`, `crew_roles`, `order`, fuel rows, product shopping rows, provisioning settings, and the last provisioning calculation result;
- the frontend loads yacht state from Atlas when a Yacht workspace is opened;
- the frontend keeps instant local save for responsiveness, then debounces Atlas save;
- `Yacht: Ckaudia Z` has a clean starter state in Atlas with the fuel package rows and agent fee row.

Control:

- no production deploy;
- no public JS secret exposure;
- product/fuel price source automation remains a separate migration slice;
- broader finance modules still require deliberate migration from legacy PHP/MySQL/API paths.

## 2026-06-07: Yacht Price Engine Moves To Atlas Snapshots

Decision: move the Yacht fuel/food price refresh layer from local/static assumptions toward Atlas-backed snapshots generated from the reviewed source registry.

Implemented locally:

- duplicate workspace `Yacht: Claudia Z` was moved to trash; active Atlas workspace is `Yacht: Ckaudia Z`;
- Atlas-backed local server now maintains `yacht_price_snapshots`;
- `yacht_price_approved_catalog` returns the active Atlas snapshot and auto-creates one when missing;
- `yacht_price_snapshot_refresh` generates a new active snapshot by region and family;
- fuel snapshot includes `marine_diesel_liter` alias for approved fuel application;
- food snapshot includes the reference product buckets used by provisioning prices;
- source registry policy is carried into snapshots: minimum 5 sources, fuel monthly refresh, food 90-day refresh, average available sources, last-good fallback;
- duty-free is explicitly treated as a regional fallback estimate unless verified supplier data exists, not as a universal 35% rule.

Control:

- no production deploy;
- this is not live scraping yet; the snapshot generator uses the reviewed source registry plus controlled baseline observations;
- next backend slice should add actual source fetchers/AI refresh jobs and source-level failure recording;
- financial accounting formulas remain untouched.

## 2026-06-07: Price Source Transparency In Yacht UI

Decision: expose Atlas price source details in the Yacht UI so the user can see which sources participated in a price snapshot instead of seeing only a final number.

Implemented locally:

- approved price panel now reads the active `order.price_snapshot` when no manually loaded catalog is in memory;
- panel shows available/total/failed source counts;
- panel lists compact source rows with source label, bucket, source type, and normalized net EUR value;
- products screen now has Atlas price refresh/load controls and uses the same approved source panel as fuel;
- source panel is responsive via CSS grid for phone/tablet/desktop.

Control:

- source rows are transparency metadata, not financial evidence;
- live scraping and AI refresh are still next-slice tasks;
- production remains untouched.

## 2026-06-07: Universal Cash Session Engine Above Yacht-Specific Cashbox

Decision: treat Ship Cashbox behavior as the behavioral source for a universal cash/session engine, not as a yacht-only module.

Reason:

- the same operational model applies to yacht, home, family, road and the base FinDesk tool;
- separate engines per direction would create duplicated reports and incompatible logic;
- FinDesk already has a workspace shell, so the correct model is workspace -> active session -> journal -> records -> report -> archive;
- Yacht-specific fuel/products tools must remain specialized tools under Yacht, while ЖЗ/Записи/Отчет become common product behavior.

Control:

- implementation record: `docs/AI_TEAM/91_UNIVERSAL_CASH_SESSION_ENGINE_2026-06-07.md`;
- first local slice is additive Atlas collection `cash_sessions` and Product Shell routes `cash-session`, `cash-journal`, `cash-records`, `cash-report`;
- no old Ship Cashbox PHP/file-storage code is transplanted;
- existing official FinDesk financial formulas and report finalization are not replaced without architect/auditor approval.

## 2026-06-07: Cash Sessions Close Into Preview Archive Snapshots

Decision: closed universal cash sessions are stored as immutable preview archive snapshots before any final/audited report workflow exists.

Implemented locally:

- active `cash_sessions` can be closed with `cash_session_close`;
- closing changes `status` to `closed` and stores `archive_snapshot`;
- archive snapshot contains participants, batches, totals and settlement preview at close time;
- Product Shell exposes archive cards for the current workspace.

Control:

- archive status remains `preview_not_final`;
- archive snapshot is operational history, not a final financial/audit document;
- no production deploy;
- no official financial formulas were changed.

## 2026-06-07: Cash Report Print Is Professional Preview, Not Final Audit

Decision: the first professional cash session report is a printable/PDF preview generated from active session data or archive snapshots, not a final audited financial report.

Implemented locally:

- Product Shell can print active cash report preview;
- archive cards can print closed snapshot reports;
- print layout is isolated from the UI and uses FinDesk/Vetus Nauta document structure;
- report contains parties, meta, totals, participant balances, preliminary transfers, fixed record batches, signatures and footer.

Control:

- report status remains `preview_not_final`;
- official settlement math must be audited before final status is allowed;
- no production deploy;
- no official financial formulas were changed.

## 2026-06-07: Settlement Math Requires Harness Before Final Status

Decision: Universal Cash Session settlement math must pass deterministic local harness checks and explicit review notes before any report can move beyond `preview_not_final`.

Implemented locally:

- `npm run audit:cash` verifies current preview settlement helper behavior;
- the harness uses exported server helpers rather than duplicate formula code;
- known review cases are documented instead of hidden.

Control:

- no settlement formula was changed;
- excluded participant and contribution surplus behavior require architect/auditor decision;
- professional print report remains a preview document until review is complete.

## 2026-06-09: Cash Layout Discipline Uses Shell Title Only

Decision: cash flow pages must not render duplicate large page headers under the Product Shell title.

Implemented locally:

- `cash-session`, `cash-journal`, `cash-records`, `cash-report`, and participant self-view no longer render duplicate large `phase1Header` blocks;
- ЖЗ inner context is `Активная запись`, not another `Личный журнал · ЖЗ` heading;
- `cash-report` is labeled `Отчеты`, not `Отчет-превью`;
- cash ЖЗ has its own compact layout and no longer inherits the old oversized `.phase1-journal-workspace` min-height/grid behavior;
- build advanced to `routes44`.

Control:

- no production deploy;
- no financial formula change;
- this is layout/wording discipline only.
