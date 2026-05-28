# Release Gate

## Required Before Release Candidate

- P0 financial flows pass smoke and manual review.
- Product Finance Architect approves terms and formulas.
- Backend Data Engineer confirms historical/open-period data separation.
- Frontend UX Engineer confirms responsive screens are compact and readable.
- QA Release Engineer records desktop/tablet/mobile test evidence.
- Chief Auditor confirms no unresolved P0 contradictions for MVP; remaining P1 items are assigned with owner and scope.
- The human money map is proven: each visible amount explains who holds or spent it, where it is, what changed it, and where the proof is.
- Instant field capture is proven: quick records can be saved in movement, keep visible status, and do not become final report truth without review/acceptance.
- Quick action shortcuts are proven not to bypass money ownership, proof, review, or final-report acceptance.
- Field Combat no-data-loss foundation is proven: saved draft facts and proof retry state survive return/recovery, retries do not duplicate money, and autosave/retry does not submit/include/finalize.
- Closed group report package is proven: `Закрытый групповой отчет` opens by `report_id` as one immutable archive object with participant reports, proofs, accountable state, message/audit references, print/PDF, and no mutation after later current activity.
- Receipt Scanner evidence contract is proven before release use: original source evidence is preserved next to any cleaned PDF derivative; cleaned PDF never replaces the primary proof; processing metadata is verifiable; archive/final report opens both original and cleaned versions; OCR text is only an assistive extraction and is not accounting truth.
- MVP exit criteria in `docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md` are satisfied or exact blockers are recorded.

## Slice Gate Status 2026-05-26

Instant field capture:

- QA status: passed for assigned slice, run id `20260526141856`.
- Audit status: approved for the assigned instant field capture slice only.
- Auditor decision date: 2026-05-26.
- Evidence source: `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md` and `STATUS.md`.
- Scope covered: cash/card quick actions, proof picker, `Подотчет`, exact saved-row reopen, edit, delete, physical-cash separation, review gate, final-report acceptance boundary, and cash sequence guard.
- Scope not covered as release approval: carryover, export readability, archive behavior, and full release gate.
- Audit conclusion: quick field capture remained usable for movement and did not bypass proof, money ownership, review, physical cash/card separation, or final report acceptance in QA run `20260526141856`.

Full release:

- Status: not declared beyond MVP scope.
- Reason: this gate approves only the MVP exit criteria, not a broader accounting-platform release.
- Historical backend status: new finalized report snapshot/export passed QA for new snapshot finalizations.
- Current export blocker: fixed and QA-rechecked for the combined historical/current scenario.
- Frontend/UX status: current vs historical report actions implemented and passed QA on mobile/tablet/desktop.
- Product contract: current truth is `Текущий период` / `Экспорт текущего периода`; closed truth is `Закрытые финальные отчеты` / `Экспорт финального отчета`.

MVP gate:

- Status: approved.
- Auditor decision date: 2026-05-26.
- Evidence source: `docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md`, `04_TASK_BOARD.md`, `05_DECISIONS.md`, Product/Backend/Frontend findings, and QA findings.
- Evidence ids: instant run `20260526141856`; backend combo recheck `group_id=195`, `report_id=371`, current income `90`, current Live Report tape `184`; UI QA `group_id=200`, `report_id=406`, current income `100`, current Live Report tape `199`.
- Audit conclusion: a non-accountant can distinguish where the money is for the MVP path: current period starts from carryover, old finalized `1000 / 600 / 400` remains selected by `report_id`, current export keeps `400 / 50 / 25` without old income as current income, card expense does not reduce physical cash, and instant capture does not bypass review/final acceptance.
- Non-blocking P1 after MVP: legacy finalizations without `report_snapshot` lack an accessible QA fixture for `historical_snapshot_missing`; same-second cutoff hardening for `le.created_at > finalized_at` remains a backend hardening item unless reproduced in normal QA flow.

Field Combat no-data-loss gate:

- Status: approved.
- Auditor decision date: 2026-05-26.
- Evidence source: `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`, `10_BUSINESS_MVP_SCOPE.md`, Product/Backend/Frontend findings, QA findings, and QA status.
- Evidence ids: Backend fixture `group_id=202`, `draft_id=1`, `tape_id=202`, `session_id=142`, `capture_id=160`; old UI blocker run `20260526264416`; recovery identity recheck `20260526109674`; final proof retry recheck `20260526929348`; QA groups `218/219/220`, original rows `176/178/180`, previous `next_tape_id` cards `252/258/264`.
- Scope approved: active `Живой отчет` Field Combat foundation for durable draft save/recovery, proof failed/retry state, idempotent save retry, no duplicate money row on proof retry, cash/card separation, visible save/retry language, and deliberate submit/include/finalize boundaries.
- Audit conclusion: after visible saved state, typed money facts survived refresh/return; failed proof state survived recovery and retried on the original saved capture; retry did not create duplicate money rows; repeated `client_operation_id` stayed idempotent; card rows stayed `noncash_out` with zero physical-cash effect; no `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` request was observed.
- Boundary: this is not full business MVP approval. Group report consolidation, archive, participant/common pot, messages, production deployment, and broader business MVP scope remain separate gates.

Closed group report package gate:

- Status: approved.
- Auditor decision date: 2026-05-27.
- Evidence source: `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`, Product/Backend/Frontend findings, QA findings, and QA status.
- Evidence ids: QA run `20260527816949`; `group_id=222`; `report_id=454`; admin user `520`; member user `521`; base income ledger entry `104`; cash tape/capture `272/184`; card tape/capture `274/185`; accountable advance `67`; rollover advance `68`; later current activity income `106` and tape `277`.
- Scope approved: one immutable `Закрытый групповой отчет` package by `report_id`, opened through `ledger_group_final_report_package`, with group summary, participant reports, captures/proofs, money rows, accountable/advance state, report-context/general message refs, audit refs, authorized package proof download, print/PDF, and mobile/tablet/desktop layout.
- Audit conclusion: the verified package is not summary-only; it preserves group received money, participant responsibility, cash/card split, accountable/open employee cash responsibility, proof access for authorized reviewers, audit/finalization references, and immutability after later current-period activity.
- Accepted follow-ups outside this gate: package-wide downloadable file export beyond browser print/PDF; first-class `report_id` / `tape_id` / `capture_id` message schema; legacy reports without `report_package`.
- Boundary: this is not automatic full business MVP approval. Field Combat stays approved and is not reopened by this evidence; remaining business-MVP release decisions still require Project Director/final gate.

Full business-MVP product gate:

- Status: approved.
- Auditor decision date: 2026-05-27.
- Evidence source: `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`, `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`, Product/Backend/Frontend/QA findings, Field Combat no-data-loss gate, and Closed group report package gate.
- Evidence ids: residual QA run `20260527968710`; accepted package anchor `group_id=222`, `report_id=454`; prior Field Combat recheck `20260526929348`; package QA run `20260527816949`; backend combo recheck `group_id=195`, `report_id=371`; UI current/historical run `group_id=200`, `report_id=406`.
- Scope approved: business-MVP product readiness for the checked new-data path from field capture through review/acceptance, final report, closed group package, archive/open/print/proof, group messages, Business Desk/proforma separation, Travel staging, and Advanced staging.
- Audit conclusion: no unresolved product P0 contradiction remains between Product Finance meaning, Backend/Data behavior, Frontend/UX reachability, QA evidence, and prior Chief Auditor gates. A non-accountant can trace the money from capture to closed group report, including who holds or spent it, where physical cash/card/accountable value sits, where proof is, and which report is final.
- Production boundary: this approval is not production deploy approval. Deploy package selection, dirty-tree exclusion, database migration verification, production backup/rollback, and production smoke remain a separate Project Director/deploy-owner gate.
- Accepted P1/post-MVP follow-ups: package-wide downloadable archive export beyond browser print/PDF; first-class report-linked message schema; legacy reports without new snapshot/package fallbacks; same-second finalization cutoff hardening unless reproduced; exact server-rendered export wording if Product requires it; full travel settlement engine; full Business Desk/invoicing integration with group money reports; broad social chat archive.

Receipt Scanner local evidence gate:

- Status: approved for the local browser/HTTP file-input scanner slice only.
- Auditor decision date: 2026-05-28.
- Evidence source: `docs/AI_TEAM/32_RECEIPT_SCANNER_SPRINT_2026-05-28.md`, `docs/AI_TEAM/31_RECEIPT_SCANNER_TASK_CARD_2026-05-28.md`, Backend/Data findings, Frontend/UX findings, QA Release findings/status, and `/tmp/findesk-receipt-scanner-20260528RSQA01/receipt-scanner-qa-result.json`.
- Evidence ids: QA run `20260528RSQA01`; mobile `390x844`, tablet `820x1180`, desktop `1440x900`; captures `202/203/204`; original files `18/20/22`; cleaned PDF files `19/21/23`; final-package API recheck `user_id=542`, `group_id=226`, `tape_id=303`, `capture_id=206`, `report_id=516`, bundle `scanner-package-bundle-20260528080910`.
- Scope approved: local file-input scanner flow from Live Report, original source upload as `scanner_original`, cleaned PDF upload as `scanner_cleaned_pdf`, shared `proof_bundle_id`, cleaned PDF `source_file_id` pointing to original in file-list evidence, cleaned PDF `derived_from_file_id` pointing to original in final report package evidence, idempotent original/PDF upload replay, idempotent signed sync replay, final package scanner proof roles/bundle/hash metadata, and one money row per viewport after retries.
- Audit conclusion: the local evidence chain satisfies the source/derivative proof requirement for the checked browser/HTTP file-input path and for the checked closed final package API path. The cleaned PDF did not replace the original source, both artifacts stayed linked to the same proof context, final package proof metadata preserved the source-to-derivative chain, and retry evidence did not duplicate money rows.
- Not approved by this gate: physical camera capture, installed iPhone/Android PWA mode, production device behavior, automatic edge detection, OCR, production deployment, or full release readiness.
- Production requirement: real-device PWA/camera QA on iPhone Safari PWA and Android Chrome is required before production scanner deployment is treated as ready.
- Continuing guardrail: OCR or extracted text remains assistive only and must not silently set final amount, category, cash/card type, accountable state, tax, or report totals without existing review/acceptance controls.

Limited scanner/UX/backend deploy-preflight gate:

- Status: accepted for deploy preflight; production remains conditional.
- Auditor decision date: 2026-05-28.
- Evidence source: `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`, QA local recheck artifact `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/local_leftovers_recheck_20260528/SUMMARY.md`, and `docs/AI_TEAM/roles/05_chief_auditor/RECEIPT_SCANNER_LOCAL_EVIDENCE_GATE_2026-05-28.md`.
- Evidence ids: scanner QA `20260528RSQA01`; local leftovers QA `20260528LOCALLEFTOVERS01`; group-delete fixture `group_id=233`; final-package scanner recheck `group_id=226`, `report_id=516`.
- Scope accepted for preflight: selected candidate 34 file bundle, local scanner file-input evidence, frontend residual fixes, `group_delete` soft archive hardening, and local QA recheck.
- Production blockers: real-device scanner/PWA camera QA if claiming device-ready scanner behavior; DB preflight; selected bundle only; backup/rollback; PHP lint/smoke or approved HTTP/API replacement smoke; production smoke after upload.
- Conditional upload position: upload may proceed only as a limited release if CEO explicitly accepts no device-ready scanner claim and the DB preflight, backup/rollback, selected-bundle, and production-smoke controls are closed.
- Boundary: this is not full release ready and does not approve uploading the full dirty tree.

## Release Candidate Checklist

- Cash live report works.
- Card live report works and does not change cash.
- Employee advance flow works.
- Administrator live report appears in summaries.
- Final report fixation preserves history and starts open carryover.
- Excel export is readable.
- Google Sheets open flow is understandable.
- Archive is cleanup/history, not money mutation.
- Journal is audit/recovery, not a second operational ledger.
- Receipt Scanner preserves original proof, cleaned PDF derivative, verifiable processing metadata, final/archive access to both versions, and OCR-as-assistive-only behavior.
- Ordinary-user money trace passes: a non-accountant can follow received money, administrator cash, employee accountable cash, cash spending, card spending, review, final report, archive, and proof.
- Mobile quick capture passes for received money, cash expense, card expense, employee handoff, returned balance, later proof attachment, review, and acceptance.
