# Chief Auditor Risk Register

## P0 Risks / MVP Guardrails

- No unresolved P0 blocker remains for the MVP gate approved on 2026-05-26.
- If old finalized income appears as current-period income again, it is a P0 regression.
- If card spending changes physical cash again, it is a P0 regression.
- If a draft/submitted quick record silently enters final report truth without review/acceptance, it is a P0 regression.
- If a closed final report cannot be opened/exported as a first-class historical object for new finalizations, it is a P0 regression.
- If a visible MVP amount cannot answer who holds or spent the money, where it is, what changed it, and where the proof is, it is a P0 regression.
- If a visibly saved Field Combat draft loses typed money facts after refresh/return, it is a P0 regression.
- If Field Combat proof retry duplicates a money row or moves proof to the wrong tape/card, it is a P0 regression.
- If Field Combat autosave/retry silently submits, includes, or finalizes a report, it is a P0 regression.
- If `Закрытый групповой отчет` opens as a summary-only report instead of one archive package with participant reports, proofs, accountable state, message/audit refs, and package proof access, it is a P0 regression.
- If later current-period activity mutates a closed group report package, it is a P0 regression.
- If card/noncash spending in a closed package reduces physical cash or open accountable employee cash is shown as expense, it is a P0 regression.
- No unresolved P0 blocker remains for the full business-MVP product gate approved on 2026-05-27 for the checked new-data path.
- If group messages, Business Desk/proforma, Travel staging, or Advanced staging start mutating or contradicting the proven money loop, it is a P0 regression for business-MVP product coherence.
- Production deploy readiness is a separate gate, not a product P0 inside this approval.
- Receipt Scanner local browser/HTTP file-input evidence is approved for the checked local slice only; production scanner deployment remains blocked until real-device PWA/camera QA proves iPhone Safari PWA and Android Chrome behavior.
- Candidate 34 is accepted for deploy preflight only; production remains conditional until selected-bundle, DB preflight, backup/rollback, smoke, and CEO limited-release decision are recorded.
- If the release claims device-ready scanner/PWA camera behavior without real-device iPhone Safari PWA and Android Chrome evidence, it is a P0 release-claim blocker.
- If the deploy uploads the full dirty tree instead of the selected candidate 34 bundle, it is a P0 deploy-control blocker.
- If scanner DB columns are missing or applied without non-destructive preflight/backup, it is a P0 deploy-control blocker.
- If production upload happens without rollback artifacts and production smoke, it is a P0 deploy-control blocker.
- If a cleaned PDF replaces, overwrites, hides, or becomes the only retained receipt source, it is a P0 evidence-integrity blocker.
- If archive, final report package, print/PDF, or authorized proof download opens only the cleaned PDF and cannot open the original source, it is a P0 evidence-access blocker.
- If scanner/OCR output silently becomes final amount/category/cash-card/accountable/tax/report truth without the existing review and acceptance controls, it is a P0 accounting-truth blocker.
- If processing metadata cannot prove the source-to-derivative chain, including source id, derivative id, timestamps, user context, file identity/integrity, processing version/status, and relevant errors/retries, it is a P0 auditability blocker.

## Evidence Updates 2026-05-26

- QA run `20260526141856` found no bypass in the assigned instant field capture slice: draft and submitted quick records stayed out of final report until include/acceptance.
- Card quick capture kept physical cash unchanged in the recorded QA scenario.
- `Подотчет` opened accountable-money flow and did not create a Live Report expense row.
- Chief Auditor approved the assigned instant field capture slice on 2026-05-26.
- The quick-capture bypass risk is closed for QA run `20260526141856` scope: cash/card quick capture, proof picker, `Подотчет`, saved-row reopen, edit/delete, physical-cash separation, review gate, final-report acceptance boundary, and cash sequence guard.
- Product Finance Architect approved the two-truth contract: current truth is `Текущий период` / `Экспорт текущего периода`, closed truth is `Закрытые финальные отчеты` / `Экспорт финального отчета`.
- Backend/Data implemented historical finalized report/export for new finalizations using `audit_log.details.report_snapshot` and kept current export on current open-period endpoints.
- QA proved historical export for new snapshots and later accepted the current export combo fix: backend recheck `group_id=195`, `report_id=371`, current income `90`, current Live Report tape `184`.
- Frontend/UX implemented current vs historical report actions; QA verified the user-facing flow on mobile/tablet/desktop with `group_id=200`, `report_id=406`, current income `100`, current Live Report tape `199`.
- Chief Auditor approved the MVP gate on 2026-05-26 after reviewing Product/Backend/Frontend/QA evidence.
- MVP evidence ids: instant run `20260526141856`; backend combo recheck `group_id=195`, `report_id=371`, current income `90`, current Live Report tape `184`; UI QA `group_id=200`, `report_id=406`, current income `100`, current Live Report tape `199`.
- Current-vs-historical report confusion is closed for the MVP path: current export stayed `400 / 50 / 25`, historical export stayed `1000 / 600 / 400`, and later current entries did not mutate the selected historical report.
- The quick-capture, physical cash/card separation, and historical finalized-report risks remain as regression guardrails after MVP approval, not active MVP blockers.
- Backend/Data implemented durable Field Combat draft/proof APIs: `on_the_go_field_draft_save`, `on_the_go_field_recover`, `on_the_go_proof_state_begin`, `on_the_go_proof_state_fail`, and `on_the_go_proof_state_list`.
- Frontend/UX wired active `Живой отчет` autosave/recovery/proof-state behavior and fixed the empty-draft recovery identity blocker from QA run `20260526264416`.
- QA rechecked recovery identity in run `20260526109674`: saved cash rows survived refresh/module return/same-stream reselection, and autosave did not submit/include/finalize.
- QA found proof retry duplicate-money P0 in run `20260526109674`; Frontend/UX fixed proof retry to use proof-only retry on the original saved capture without re-running money sync.
- QA final recheck `20260526929348` closed the proof retry duplicate-money P0: groups `218/219/220`, original rows `176/178/180` stayed single and received proof files, previous `next_tape_id` cards `252/258/264` stayed clean, repeated `client_operation_id` was idempotent, and no submit/include/finalize request was observed.
- Chief Auditor approved the Field Combat no-data-loss gate on 2026-05-26 for the verified foundation scope only; full business MVP remains outside this gate.

## Evidence Updates 2026-05-27

- Product Finance Architect defined the user-facing object as `Закрытый групповой отчет`: one closed group report package by `report_id`, not a summary-only export.
- Backend/Data implemented `ledger_group_final_report_package` and `ledger_group_final_report_proof_download`; new finalizations store immutable `report_package` in `audit_log.details`.
- Backend/Data preserved current/historical behavior: current export remains current-period truth, historical short table export remains separate, and package reads use stored package data rather than reconstructing mutable current data.
- Frontend/UX exposed the closed report as one archive object labeled `Закрытый групповой отчет #report_id`, with package sections and package proof URLs.
- QA verified fresh package `group_id=222`, `report_id=454`, admin `520`, member `521`, run `20260527816949`.
- QA package evidence covered group summary, participant reports, captures/proofs, money rows, accountable/advance state, report-context/general message refs, audit refs, authorized proof download HTTP 200, print/PDF content, and mobile/tablet/desktop layout.
- QA proved cash/card/accountable separation in the package: `received_money=1000`, `physical_cash_spent=640`, `card_noncash_spent=70`, `admin_cash_left=300`, `accountable_money_left=60`, `cash_balance=360`, `balance=290`; card capture had zero cash effect and open accountable `60` remained responsibility/carryover, not expense.
- QA added later current-period activity after finalization and confirmed the closed package digest did not mutate.
- Chief Auditor approved the closed group report package gate on 2026-05-27 for the verified package scope only; full business MVP remains outside this gate.
- QA residual surface run `20260527968710` passed using group `222` and report `454`: group messages send/list/unread/mark-read and group scope passed, Business Desk/proforma create/list/open/print passed without mutating `ledger_report`, Travel/Trip remained staged, Advanced remained reachable, and mobile/tablet/desktop navigation reached the proven money loop without blocking overlap.
- Product Finance Architect final readiness check passed and found no true P0 product contradiction after residual QA.
- Frontend/UX final readiness check passed and found no remaining P0 mobile/frontend contradiction after residual QA.
- Backend/Data final readiness check found no backend/API P0 for business-MVP product readiness and classified production deployment controls as a separate deploy gate.
- Chief Auditor approved the full business-MVP product gate on 2026-05-27 for the checked new-data path. Production deploy approval remains separate.

## Evidence Updates 2026-05-28

- Frontend/UX recorded that the current `scan` action is only the existing image/PDF proof picker with camera capture enabled, not true automatic document scanning, PDF cleanup, or OCR.
- Chief Auditor opens a Receipt Scanner evidence/audit gate as blocked until implementation evidence proves the full source/derivative/metadata/archive/OCR contract.
- Required evidence contract: original receipt/source remains the primary evidence; cleaned PDF is a derivative stored beside the source; processing metadata is verifiable; archive/final report opens both versions; OCR is helper text/suggestions only and is never accounting truth by itself.
- No runtime code, financial formula, or existing proof upload approval is changed by this risk update.
- Backend/Data implemented local scanner proof storage fields and idempotent upload behavior for `scanner_original` and `scanner_cleaned_pdf`.
- Frontend/UX implemented local scanner modal, manual crop/perspective cleanup, one-page PDF generation, and original plus cleaned PDF upload wiring.
- QA Release passed local browser/HTTP file-input scanner QA in run `20260528RSQA01` across mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.
- QA evidence showed one money row per viewport after retries, exactly two scanner artifacts per viewport, shared `proof_bundle_id`, cleaned PDF `source_file_id` pointing to the original, and idempotent original/PDF/signed-sync replay.
- Project Director authenticated final-package scanner proof recheck showed `group_id=226`, `report_id=516`, bundle `scanner-package-bundle-20260528080910`; final report package contained `scanner_original` and `scanner_cleaned_pdf`, and cleaned PDF pointed back to the original through `derived_from_file_id`.
- `scripts/local-smoke.php` now includes final report package scanner proof assertions for proof roles, bundle, derived original, and hash metadata; execution remains blocked here by unavailable PHP CLI.
- Chief Auditor approved the local Receipt Scanner evidence gate on 2026-05-28 for the checked file-input path only.
- Physical camera capture and installed iPhone/Android PWA mode remain required production-deploy evidence and were not proven by headless Chromium.
- Candidate 34 deploy-preflight was accepted by Chief Auditor on 2026-05-28 for limited-release routing only.
- Evidence reviewed for candidate 34: `34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`, QA local recheck `20260528LOCALLEFTOVERS01`, and Receipt Scanner local evidence gate `20260528RSQA01`.
- Upload may proceed only if CEO explicitly accepts no device-ready scanner claim and deploy controls close: DB preflight, selected bundle, backup/rollback, PHP/HTTP smoke, and production smoke.
- Full release ready was not declared.

## P1 Risks

- Menu/page responsibilities can drift back into duplication.
- Mobile can become overloaded with desktop information.
- Export can be technically correct but unreadable for business use.
- Archive can be mistaken for an accounting action.
- User-facing language can drift back to accounting/developer terms instead of ordinary action terms.
- Fast mobile entry can become too slow if it requires full categorization, dense tables, or desktop-style fields during movement.
- Quick-action UI can be misunderstood as final posting if status, save, review, and FinDesk acceptance remain visually weak.
- Legacy finalizations without `report_snapshot` lack an accessible QA fixture for `historical_snapshot_missing`; this is post-MVP unless Product/Director requires legacy migration.
- Same-second finalization cutoff (`le.created_at > finalized_at`) is a backend hardening risk unless QA reproduces it in normal flow.
- Downloaded current export wording may need Backend/Data adjustment if Product requires the exact phrase `Переходящий остаток из финального отчета` inside server-rendered files.
- Purely local unsent typing before the first successful autosave request remains outside backend recovery; the product depends on early frontend autosave and visible sync state.
- Package-wide downloadable file export beyond browser print/PDF remains a follow-up unless Product/Director upgrades it to P0.
- Direct report-linked message schema (`report_id`, `tape_id`, `capture_id`, `advance_id`) remains a follow-up because the accepted package uses audit-derived report-context messages plus clearly marked unlinked general group refs.
- Legacy finalizations without `report_package` remain outside this gate; they should warn/fallback rather than pretend to be the new closed group report package.
- Full business-MVP product gate is approved for the checked new-data path; remaining phone-first organization, role/permission refinements, and non-package module depth are P1/post-MVP unless new evidence shows a money-loop contradiction.
- Full travel settlement remains post-MVP unless CEO or Project Director marks travel launch-critical.
- Full Business Desk/invoicing integration with group money reports remains post-MVP; the preserved proforma path must stay formula-isolated until explicitly connected.
- Broad social chat archive remains post-MVP beyond finance/report-context messages.
- Production deploy package selection, dirty-tree exclusion, database migration verification, backup/rollback, and production smoke are separate deploy-gate risks, not residual business-MVP product P1 items.
- Receipt Scanner UX can confuse users if cleaned PDF is labeled as the only proof; labels must distinguish `Original source` from `Cleaned PDF`.
- OCR confidence/search text can create false certainty; Product and QA must keep visible language that reviewed/accepted human financial rows remain the report truth.

## P2 Risks

- Branding polish may distract from release-critical financial checks.
- AI/analytics features can add noise before core flows are stable.
