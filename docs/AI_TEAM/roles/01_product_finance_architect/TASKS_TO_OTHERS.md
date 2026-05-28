# Tasks To Others: Product Finance Architect

## Pre-Deploy Residual Routing - 2026-05-28

Status: product classification issued. No runtime code changed by Product Finance Architect.

## To Project Director / Deploy Owner

Date: 2026-05-28
Priority: P0 before next CEO-facing production deploy
Context: business MVP product gate is approved, but CEO reported production leftovers and scanner device QA is not complete.

Request:

1. Choose release mode explicitly:
   - limited core MVP release without device scanner claim; or
   - hold release until scanner real-device PWA/camera gate passes.
2. Ensure the deploy package includes the production UX cleanup needed for the six CEO-reported defects.
3. Keep Receipt Scanner wording limited unless `33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md` passes.
4. Route production browser/PWA smoke after upload.

Acceptance criteria:

- no deployed copy claims device-ready scanner until real-device QA passes;
- production smoke verifies login copy, old May report visibility, Live Report overlap, intermediate close action, refresh context preservation, and group archive/delete-from-working-list control;
- deploy decision records whether scanner is limited/beta or held.

## To Frontend UX Engineer

Date: 2026-05-28
Priority: P0 before next CEO-facing production deploy
Context: CEO-reported production UX leftovers are release blockers when visible on production.

Request:

1. Confirm the deploy candidate contains current FinDesk login/code-delivery wording and cache/service-worker version bump.
2. Confirm Live Report cards do not overlap title/preview/actions on mobile/tablet/desktop.
3. Confirm the intermediate share/print/invite block has a visible close/back action.
4. Confirm refresh preserves active On-the-go stream/card/editor context where practical.
5. Confirm a group admin has a visible audited `archive/delete from working list` action for test groups.
6. Do not add OCR or new scanner claims in this pass.

Acceptance criteria:

- production browser smoke can verify all six reported UI defects without relying on source inspection;
- any failure on mobile that blocks money capture is P0.

## To Backend Data Engineer

Date: 2026-05-28
Priority: P0 for production cleanup controls
Context: stale legacy May report and test group cleanup are backend/data trust issues.

Request:

1. Ensure the legacy `03.05` report is not returned as active working/submitted report after the production data hotfix.
2. Ensure personal no-group locked cards do not route the owner into meaningless correction requests.
3. Provide safe group removal semantics for MVP: audited archive/hide from working list, not irreversible hard delete.
4. Preserve report/archive/audit history for any archived group or legacy card.

Acceptance criteria:

- QA can prove the old May report is gone from the active working surface or explicitly marked legacy archived/non-working;
- admin group cleanup removes test groups from ordinary work lists without destroying financial/audit evidence;
- no production financial formula changes are made in this cleanup pass.

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 after next production upload
Context: Product Finance allows a limited core MVP release only after production smoke verifies the reported leftovers.

Request:

Run production browser/PWA smoke for:

1. login/code-delivery copy is current after normal reload and hard reload;
2. old `03.05` legacy report is absent from active work or clearly archived;
3. Live Report card/header/action overlap is gone on mobile/tablet/desktop;
4. intermediate share/print/invite block has a working close/back action;
5. refresh returns to the prior active On-the-go context where practical and does not duplicate money rows;
6. group admin can archive/remove a test group from the working list;
7. core MVP path still works: capture, review, final report, closed group package, print/PDF, archive;
8. if scanner is included, its wording does not claim device-ready PWA/camera unless the real-device gate passes.

Acceptance criteria:

- any blocked mobile money-capture path is P0;
- any stale production login copy after cache refresh is P0 for next CEO-facing deploy;
- any scanner device-ready claim without real-device evidence is P0 wording/release blocker.

## To QA Release Engineer / CEO Physical Device Check

Date: 2026-05-28
Priority: P0 only if scanner is included as production device-ready
Context: local scanner file-input QA passed, but physical camera/PWA behavior is not proven.

Request:

Run `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`.

Acceptance criteria:

- iPhone Safari browser and installed PWA pass or fail with evidence;
- Android Chrome browser and installed PWA if available pass or fail with evidence;
- camera/file picker returns an image, crop handles are usable by finger, cleaned PDF attaches, refresh/retry do not duplicate money rows, and final package exposes original plus cleaned PDF;
- if this gate does not pass, release may proceed only without device scanner claim.

## Receipt Scanner / Proof PDF Follow-Up - 2026-05-28

Status: product contract issued. This is evidence-flow work only; do not change financial formulas.

## To Backend Data Engineer

Date: 2026-05-28
Priority: P0 if CEO keeps Receipt Scanner in release scope; otherwise P1 product-flow implementation.
Context: Receipt Scanner / proof PDF is now a separate evidence-capture stream linked to live report rows. The current scan action is only a proof picker and is not enough to claim cleaned proof PDF scanner behavior.

Request:

1. Create or expose an evidence chain identity for every scanned/attached receipt proof.
2. Persist the original source photo/file unchanged.
3. Generate and persist a cleaned PDF derived from that original source.
4. Link both original source and cleaned PDF to the same live report row/capture before review/finalization.
5. Store manual corner/crop metadata or equivalent transform metadata used to make the cleaned PDF.
6. Preserve upload/generation states: original pending, original uploaded, cleaned PDF pending, ready, failed/retry.
7. Preserve the evidence chain into final report and `Закрытый групповой отчет` package for accepted rows.
8. Do not introduce OCR-driven row creation or formula mutation in the first MVP step.

Acceptance criteria:

- QA can verify `live_report_row_id/capture_id -> evidence_chain_id -> original_source + cleaned_pdf`.
- Original source remains available after cleaned PDF generation.
- Cleaned PDF remains traceable to the original source.
- Upload/generation failure leaves visible pending/retry state and does not lose the row or source.
- Existing cash/card/accountable formulas are unchanged.

## To Frontend UX Engineer

Date: 2026-05-28
Priority: P0 if CEO keeps Receipt Scanner in release scope; otherwise P1 product-flow implementation.
Context: users need a real receipt proof flow: take photo or choose file, correct corners, create cleaned PDF, and see whether the proof is saved or pending.

Request:

1. Expose Receipt Scanner / proof PDF from the live report row or field capture proof area.
2. Allow camera photo and existing image/PDF file input.
3. Show the original source and generated cleaned PDF as one proof/evidence chain.
4. Provide manual corner correction before accepting/generating the cleaned PDF.
5. Show ordinary-language states: saved, pending upload, cleaned PDF pending, failed/retry, ready for review, accepted.
6. Keep proof state attached to the exact row; avoid orphan proof documents.
7. Make clear that OCR/auto extraction is not part of the first MVP scanner step.

Acceptance criteria:

- A user can attach receipt proof to a specific row without leaving the money-capture context.
- The user can correct receipt corners and produce a readable cleaned PDF.
- The UI never implies proof is final while original upload or cleaned PDF generation is pending.
- Refresh/navigation/return shows the same row-proof relationship and pending/retry state.

## To QA Release Engineer

Date: 2026-05-28
Priority: P0 if CEO keeps Receipt Scanner in release scope; otherwise P1 verification.
Context: QA must prove evidence-chain behavior separately from money formulas.

Minimum scenario:

1. Create a live report row.
2. Attach receipt via camera/photo or image file.
3. Confirm original source is saved or visibly pending.
4. Generate cleaned PDF after manual corner correction.
5. Refresh and switch modules; verify row, original source, cleaned PDF, and row link remain.
6. Simulate weak/offline upload or generation failure; verify pending/retry state and no data loss.
7. Submit/review/accept the row and verify final report/package can open cleaned PDF and authorized original source.
8. Verify OCR is absent or suggestion-only and does not mutate row amount/type/formula.

Acceptance criteria:

- Any lost original source, lost row-proof link, or silent proof failure is P0.
- Any cleaned PDF with no trace to original source is P0 for scanner scope.
- Any OCR-driven financial mutation in first MVP scanner step is P0.
- Any cash/card/accountable formula change caused by scanner work is P0.

## To Chief Auditor

Date: 2026-05-28
Priority: P0 if Receipt Scanner is treated as release scope.
Context: scanner proof must improve evidence trust without weakening formula trust.

Request:

1. Audit that original source and cleaned PDF are both preserved in one evidence chain.
2. Confirm final report/archive evidence can open cleaned PDF and authorized original source.
3. Confirm no formula changes or OCR-driven accepted values were introduced.
4. Confirm pending/offline proof states are visible and do not masquerade as accepted proof.

Acceptance criteria:

- Release claim cannot say "Receipt Scanner / proof PDF complete" until Backend/Data, Frontend/UX, and QA evidence satisfy the product contract in `FINDINGS.md`.

## To Backend Data Engineer

Date: 2026-05-27
Priority: P0
Context: Production multi-employee QA blocker on `https://finance.brkovic.ltd`, `group_id=8`, `report_id=66`. QA proved total expenses `EUR 284` and group balance `EUR 716`, but final detail/package/export headline totals show `admin_cash_left=532` and `accountable_money_left=184`. Employee 2 overrun `EUR 36` is visible only in audit refs, not in headline participant-control totals.

Financial decision:

- `admin_cash_left` means actual physical cash held by the administrator at finalization before an explicit reimbursement payment is recorded.
- For this scenario `admin_cash_left` must be `EUR 568`.
- `EUR 532` is only a projected/net after-reimbursement position (`568 - 36`) and must not be labeled as `admin_cash_left` unless a real reimbursement payment transaction exists and is shown.
- Employee overrun must be first-class participant-control data, not audit-only data.

Required acceptance contract:

1. Final detail, closed group package, export, and print expose `admin_cash_left=568` for `group_id=8`, `report_id=66`, unless a real reimbursement payment was recorded before finalization.
2. Employee 1 participant row exposes issued `135`, accepted spend `68`, remaining `67`, reimbursement due `0`.
3. Employee 2 participant row exposes issued `94`, accepted spend `130`, remaining `-36`, reimbursement due `36`.
4. Employee 3 participant row exposes issued `117`, accepted spend `0`, remaining `117`, reimbursement due `0`.
5. Headline/control totals expose either:
   - `admin_cash_left=568`, positive employee remaining `184`, employee reimbursement due `36`, group balance `716`; or
   - `admin_cash_left=568`, net employee remaining `148`, group balance `716`.
6. If `accountable_money_left` remains positive-only, it must be clearly paired with `employee_reimbursement_due_total=36`; positive-only `184` alone is not accepted as participant control.
7. If `accountable_money_left` is used as the net participant-control total, it must equal `148`, not `184`.
8. Final detail/package/export/print must allow QA to verify `568 + 67 - 36 + 117 = 716` without reading raw audit refs.
9. Audit refs remain supporting evidence, not the only location where overrun/reimbursement is represented.
10. Later current-period activity must not mutate the closed package for `report_id=66`.

Blocked until:

- Backend/Data exposes the accepted fields or exact equivalents in final detail/package/export/print.
- QA re-runs the production multi-employee scenario and records PASS for headline participant-control totals.

Next owner: Backend/Data Engineer.

## Final Business MVP Readiness Follow-Up - 2026-05-27

Status: no new P0 product tasks to Backend/Data, Frontend/UX, or QA from this review.

Evidence basis:

- Business MVP scope accepted the ordinary loop from field capture to archived group report.
- Final readiness review classified residual surface QA as the last P0 product surface before final product position.
- QA residual surface run `20260527968710` passed against group `222`, report `454`.
- Approved gates already cover Foundation MVP, Field Combat no-data-loss, and `Закрытый групповой отчет` package.

## To Project Director

Date: 2026-05-27
Priority: P0 decision owner
Context: Product Finance Architect final readiness check is PASS after QA residual surface PASS.
Request: decide the next release-governance step: final Chief Auditor full business-MVP gate, production package planning, or both in sequence.

Acceptance criteria:

- Do not reopen Foundation MVP, Field Combat no-data-loss, or `Закрытый групповой отчет` package gates unless new regression evidence appears.
- Keep production deploy controls separate from product coherence: deployment package selection, production smoke, backup, rollback, and dirty-tree upload control are not resolved by this product PASS.
- Keep `Advanced` as non-MVP staging.
- Keep these items P1/post-MVP unless new evidence or CEO direction changes priority:
  - package-wide downloadable file export beyond print/PDF and proof links;
  - first-class report-linked message schema beyond audit-derived report-context messages and marked unlinked group refs;
  - legacy packages / old reports without `report_package`;
  - full travel settlement engine;
  - full invoicing/accounting suite beyond preserved Business Desk/proforma.

Blocker: none from Product Finance Architect.
Next owner: Project Director.

## To Backend Data Engineer

Date: 2026-05-23
Priority: P0
Context: open-period carryover after report fixation.
Request: verify backend endpoints separate historical report totals from current open-period carryover.
Acceptance criteria: old report export can show old income; open accounting screen shows carryover as base, not new income.

## To Frontend UX Engineer

Date: 2026-05-23
Priority: P1
Context: screen wording.
Request: replace unclear labels with business terms from glossary once finalized.
Acceptance criteria: user can tell cash, card, accountable money, report summary, and archive apart without reading developer notes.

## To Backend Data Engineer

Date: 2026-05-26
Priority: P0
Context: release now requires two separate report/export truths after finalization: current open-period truth and historical finalized-report truth. Current open-period export may continue to start from carryover, but the old closed final report must also be a first-class immutable report/export source.
Request:

1. Design and implement a dedicated historical finalized report/export source, either as an immutable snapshot object or an explicit finalized-report id mode.
2. Preserve the existing current open-period behavior: after finalization, current export starts from `Переходящий остаток` and does not treat old income as new current income.
3. Make the historical source return the selected closed report's totals, rows, proof references, finalization date/time, and carryover as they were at finalization.
4. Ensure the historical export for `EUR 1000 income -> EUR 600 expense -> EUR 400 carryover` can still show `1000 / 600 / 400` after the current export has switched to the new open period.
5. Do not change financial formulas silently; surface any required formula or snapshot-field decision to Product Finance Architect and Chief Auditor before implementation.

Acceptance criteria:

- There is a callable product source for `Финальный отчет за закрытый период`.
- There is a separate callable/exportable source for `Текущий период`.
- Historical export is selected by a finalized report identity, not by reconstructing whichever current records happen to be visible today.
- Current export still shows carryover `EUR 400` as the open-period starting point and does not show old `EUR 1000` as current income.
- Each historical and current total has a proof path.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P0
Context: after finalization, one generic `export/report` action is product-confusing because the same group has both live current money and a closed historical report.
Request:

1. Separate user-facing actions/modes with these labels:
   - `Текущий период`
   - `Экспорт текущего периода`
   - `Закрытые финальные отчеты`
   - `Экспорт финального отчета`
2. In current-period UI, label carryover as `Переходящий остаток из финального отчета`, not as new income.
3. In closed-report UI, label the same remainder as `Остаток перенесен в следующий период`.
4. Avoid using `Отчет` alone where the user could confuse current live money with a closed final report.
5. Show a visible proof path for each amount: open the source record, receipt/proof, employee report, archive item, or finalization proof.

Acceptance criteria:

- A normal user can choose between current work and closed reports without knowing backend terms.
- The old `EUR 1000` is visually tied to `Финальный отчет за закрытый период`.
- The current `EUR 400` is visually tied to `Переходящий остаток из финального отчета`.
- Export buttons make it clear whether they export the current period or the selected closed final report.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0
Context: QA must prove the two truths separately before release candidate. The instant capture slice approval does not cover carryover/export/archive/finalized-report history.
Request: create and execute a scenario for `EUR 1000 income -> EUR 600 expense -> EUR 400 carryover` after Backend/Data exposes the historical finalized-report source.

Checklist:

1. Before finalization, record the report/export showing `Получено EUR 1000`, `Потрачено EUR 600`, and remaining `EUR 400`.
2. Finalize the report and record the finalization id/date/time.
3. Open `Текущий период`; verify it shows `Переходящий остаток из финального отчета EUR 400` and does not show old `EUR 1000` as current income.
4. Run `Экспорт текущего периода`; verify it exports current open-period truth, starting from carryover `EUR 400`.
5. Open `Закрытые финальные отчеты`; select the old report.
6. Run `Экспорт финального отчета`; verify it still exports the closed `EUR 1000 / EUR 600 / EUR 400` report.
7. Add a new current-period entry after finalization and verify the historical finalized report/export does not change.
8. Verify proof links for each visible amount in both truths.

Acceptance criteria:

- QA evidence includes screenshots or exact responses for both exports.
- Any appearance of old `EUR 1000` as current-period income is P0.
- Any inability to export the selected historical final report is P0.
- Any missing proof path for a visible total is at least P1, P0 if it affects final-report trust.

## To Chief Auditor

Date: 2026-05-26
Priority: P0
Context: product contract now requires historical finalized report/export as a release condition, separate from current open-period export.
Request: keep full release blocked until the two report truths are implemented and QA-proven.

Audit checks:

1. `Текущий период` and `Финальный отчет за закрытый период` are not merged into one ambiguous report.
2. `Экспорт текущего периода` and `Экспорт финального отчета` are separate actions or explicit modes.
3. Carryover is not called new income.
4. Archive/audit evidence supports the closed report but does not replace it.
5. Every amount has a proof path.
6. No formula/API/backend behavior change is accepted without Product Finance Architect and Backend/Data trace.

Acceptance criteria:

- Chief Auditor records whether any contradiction remains between product meaning, backend source, UI wording, export behavior, and QA evidence.
- Full release is not marked ready until both current and historical export truths pass the gate.

## To Backend Data Engineer

Date: 2026-05-26
Priority: P0
Context: Product accepted Field Combat Mode as MVP foundation and `Advanced` as non-MVP staging. The MVP must not lose unfinished money facts before report submission/finalization.
Request:

1. Map which Field Combat Mode data is saved server-side per row/card/session: amount, note, proof/photo/file reference, group, participant, stream, status, and open-session identity.
2. Identify which data can still be client-only and therefore losable.
3. Define save/sync states for saved, pending, failed, and retry-needed proof uploads.
4. Confirm recalculation reads recovered saved state after refresh/module switch/session recovery.
5. Keep deeper/non-MVP workflows out of the MVP critical path and classify them as `Advanced` when they are not required for first money capture -> report -> archive.

Acceptance criteria:

- Backend/Data can state what survives refresh, navigation, weak network, partial upload failure, and return to an unfinished session.
- Any potential money-fact loss before deliberate submit/close is marked P0.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P0
Context: Field Combat Mode is MVP foundation for a moving user, while `Advanced` is the reachable place for non-MVP depth.
Request:

1. Make the open unfinished session visible and recoverable.
2. Keep photo/scan/proof action near the money writing area.
3. Show save/sync state in ordinary language: saved, pending, failed, retry.
4. Keep first capture screen simple; move deeper fields to later review or `Advanced` only when they are not needed to preserve the first money truth.
5. Ensure mobile simplification does not delete group messages, travel, Business Desk, or other parked product areas from product memory.

Acceptance criteria:

- User can leave and return to an unfinished field session without confusion.
- User can tell whether the money fact and proof are saved or need action.
- `Advanced` is visible as non-MVP staging, not a hidden replacement for required MVP capture.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0
Context: the approved foundation slice is not enough for full Field Combat Mode. QA must prove no-data-loss behavior for unfinished money sessions.
Request: test Field Combat Mode against the Product contract.

Minimum checks:

1. Write row -> refresh -> row remains.
2. Write row -> switch module -> return -> row remains.
3. Add proof/photo -> interrupt upload -> pending or retry state is visible.
4. Bad network/offline-like save -> user sees pending/failed state and can retry.
5. Recovered session totals match saved facts.
6. Session does not become submitted/final without deliberate user action.

Acceptance criteria:

- Any lost money fact, lost proof without visible failure, or silent finalization is P0.
- QA evidence distinguishes MVP Field Combat Mode from non-MVP `Advanced` features.

## To Chief Auditor

Date: 2026-05-26
Priority: P0
Context: Product accepted `Advanced = non-MVP staging` and `Field Combat Mode = MVP foundation`.
Request: enforce this boundary in release gate.

Audit checks:

1. Required MVP capture/recovery/proof behavior is not moved to `Advanced`.
2. `Advanced` is used for non-MVP depth without deleting old product decisions.
3. No-data-loss claims are backed by Backend/Data and QA evidence.
4. The business MVP is not marked complete while unfinished field-session recovery remains unproven.

Acceptance criteria:

- Chief Auditor records any contradiction between product scope, UI placement, backend persistence, QA evidence, and release status.

## To Backend Implementation Queue

Date: 2026-05-26
Priority: P0
Context: Product defined the business-MVP immutable group report archive package. User-facing object is `Закрытый групповой отчет`; internal contract is one immutable package by `report_id`. Current backend has group snapshot/export pieces, but not the full package.
Request:

1. Implement or design a unified closed group report package source by `report_id`.
2. Include immutable group report identity, finalization metadata, reviewer/finalizer, group summary, and export/print identity.
3. Include immutable participant report identities/snapshots for every participant report included in the group package.
4. Include captures/money rows with cash/card/accountable effect and source references.
5. Include proof metadata and authorized reviewer proof access/download that does not depend on original file ownership.
6. Include accountable/advance state frozen at finalization: accepted spend, returned cash, open remaining cash, discrepancy, and carryover responsibility by participant.
7. Include report-context messages or immutable message references for missing proof question, return for clarification, participant clarification/reply, proof-added/updated note when used for review, and acceptance note.
8. Include audit/finalization references without forcing the user to open a separate journal.
9. Preserve existing financial formulas: card spending stays noncash, employee money issue stays accountable movement, open remaining employee cash stays responsibility/carryover and not expense.

Acceptance criteria:

- One API/product source can open `Закрытый групповой отчет` by `report_id` without the UI manually stitching group final report, Live Report cards, file endpoints, advances, messages, and audit log.
- Later current-period entries, later advance changes, and general group chat changes do not mutate the closed package.
- Authorized manager/reviewer can inspect/download proofs attached to included participant reports.
- Summary-only export remains optional; it is not considered the archive package.

## To Frontend UX Engineer

Date: 2026-05-26
Priority: P0
Context: ordinary users should open `Закрытый групповой отчет`, not a technical archive package. The UI must make one closed group report readable as a complete case.
Request:

1. Expose the archive object as `Закрытый групповой отчет`.
2. From group archive/closed reports, open the selected package in one action.
3. Show clear sections for group summary, participant reports, captures/rows, proofs, accountable money, report-context messages, and audit/finalization references.
4. Keep `Отчет участника` visibly distinct inside the group package.
5. Show proof access near the number it proves.
6. Label accountable states in ordinary language: accepted spend, returned cash, left with employee, discrepancy, carried forward responsibility.
7. Keep general group chat separate from report-context messages.

Acceptance criteria:

- A non-accountant can answer who received, held, spent, returned, or still owes money from the opened package.
- User does not need to know ledger, tape, capture, advance, file, message, or audit endpoint names.
- Export/print actions clearly belong to the selected `Закрытый групповой отчет`.

## To QA Release Engineer

Date: 2026-05-26
Priority: P0
Context: business MVP cannot pass until the immutable group report archive package is proven with several participants and all money streams.
Request: after Backend/Data and Frontend/UX implementation, test a multi-participant package.

Minimum scenario:

1. Group receives money into common pot.
2. Participant A submits cash expense with proof.
3. Participant B submits card/noncash expense with proof.
4. Participant C receives accountable money, has accepted spend, returned cash or open remaining cash, and, if possible, a discrepancy case.
5. Reviewer asks a missing-proof or clarification question, participant replies or fixes proof, and reviewer accepts.
6. Group final report is finalized into `Закрытый групповой отчет`.
7. Archive opens the selected package in one click.
8. Package shows group summary, participant reports, captures, proofs, accountable/advance state, report-context messages, and audit/finalization references.
9. Print/export includes group summary, participant breakdown, proof index, message index, and audit references.
10. Add new current-period activity and verify the closed package does not change.

Acceptance criteria:

- Any missing proof access for an authorized reviewer is P0.
- Any participant report missing from the package is P0.
- Any card expense reducing physical cash is P0.
- Any accountable money shown as expense at issue time is P0.
- Any later current activity mutating the closed package is P0.

## To Chief Auditor

Date: 2026-05-26
Priority: P0
Context: Product contract makes `Закрытый групповой отчет` a business-MVP gate, not a post-MVP improvement.
Request: keep full business MVP blocked until the immutable group report archive package is implemented and QA-proven.

Audit checks:

1. `Закрытый групповой отчет` is one archive object by `report_id`, not a manual collection of endpoints.
2. Participant reports have immutable identities inside the package.
3. Proofs are visible/downloadable to authorized reviewers.
4. Accountable/advance state is frozen and understandable.
5. Report-context messages that affect trust are preserved or linked immutably.
6. Print/export is not summary-only for MVP package acceptance.
7. Cash/card/accountable formulas are unchanged.
8. Post-MVP/Advanced items are not used to remove required MVP evidence.

Acceptance criteria:

- Chief Auditor records whether the package satisfies ordinary-user money trace and evidence trace.
- Full business MVP remains blocked while any P0 package item is missing.
