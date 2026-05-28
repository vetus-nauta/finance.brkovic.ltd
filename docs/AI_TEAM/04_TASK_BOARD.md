# AI Team Task Board

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
