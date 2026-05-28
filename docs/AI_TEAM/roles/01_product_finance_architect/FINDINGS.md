# Product Finance Architect Findings

## Pre-Deploy Residual Classification - 2026-05-28

Role: Product Finance Architect
Task: classify remaining work before `доделать все` into production blocker, limited-release allowed, and post-MVP/Advanced.
Status: DECISION ISSUED / RUNTIME CODE NOT CHANGED.

Evidence basis:

- Business MVP product gate is approved for the checked new-data path.
- Receipt Scanner local browser/HTTP file-input slice is approved, but real-device PWA/camera QA is not done.
- Field Combat Mode remains a business-MVP foundation: write, photo/scan, autosave, no data loss after visible saved/retry state, simple mobile use.
- CEO production leftovers: stale login-code text, legacy `03.05` report, Live Report card overlap, no exit from an intermediate action block, refresh returning to the wrong surface, no visible test-group removal.
- Frontend/UX has a local hotfix pack for the reported UI leftovers; Backend/Data has a production hotfix for the legacy May report. Product acceptance still depends on production browser verification, not on code notes alone.
- Prior production participant-control blocker is not reopened by this classification; current task board records it as closed unless QA finds a regression.

### Production Blockers Before The Next CEO-Facing Deploy

These must be fixed or explicitly verified before the next normal production deploy is accepted:

1. Stale login/code-delivery text on production.
   - Product reason: login is the first trust surface. Old product names or dev-style copy make the deployed PWA look stale.
   - Acceptance: production browser and PWA reload show current FinDesk/brkovic.ltd code-delivery wording, not old copy.
   - Owner: Frontend/UX Engineer, then QA Release Engineer.

2. Legacy `03.05` report visible as stuck submitted/returned work.
   - Product reason: a report that cannot be returned, deleted, or meaningfully corrected destroys archive trust.
   - Acceptance: it is no longer visible in the active submitted/live working surface, or it is visibly marked as legacy archived/non-working. If it still appears after reload, it remains P0.
   - Owner: Backend/Data Engineer, then QA Release Engineer.

3. Live Report card/header overlap on production mobile.
   - Product reason: Field Combat Mode is MVP foundation; a moving user must read and tap the working report without title/action collision.
   - Acceptance: no title/preview/action overlap on mobile, tablet, desktop in the production build.
   - Owner: Frontend/UX Engineer, then QA Release Engineer.

4. No visible exit from the intermediate action block before share/print/invite actions.
   - Product reason: a user must never feel trapped in a confirmation/share/print layer while handling money.
   - Acceptance: clear `Закрыть` / back/cancel action exists and returns to the prior working context.
   - Owner: Frontend/UX Engineer, then QA Release Engineer.

5. Refresh resets the user to the wrong surface instead of preserving the active work context.
   - Product reason: this is a no-data-loss and mobile multitasking issue, not cosmetic polish.
   - Acceptance: after refresh/reopen, the user returns to the same On-the-go stream/card/editor context where practical, without duplicate money rows or silent submit/include/finalize.
   - Owner: Frontend/UX Engineer, then QA Release Engineer.

6. No visible way for a group admin to remove a test group from the working list.
   - Product reason: production use needs a basic cleanup control for test groups and mistaken groups.
   - MVP acceptance: archive/hide-from-working-list is enough; permanent hard delete is not required and should stay audited.
   - Owner: Backend/Data Engineer for safe archive semantics, Frontend/UX Engineer for visible control, QA Release Engineer for production smoke.

7. Receipt Scanner real-device PWA/camera claim.
   - Product reason: local file-input QA does not prove physical camera behavior on installed iPhone/Android PWA.
   - Acceptance: production scanner deployment cannot be called device-ready until `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md` passes, or CEO accepts a limited release with no camera/PWA scanner claim.
   - Owner: QA Release Engineer / CEO physical device check.

8. Production smoke after deploy.
   - Product reason: the business MVP gate proves product coherence, not that the deployed PWA has the current files, current cache version, current schema, and working mobile path.
   - Acceptance: production smoke covers login, app shell, core money loop, closed package, group messages, Business Desk/proforma separation, reported UI leftovers, and cache/service-worker freshness.
   - Owner: Project Director / Deploy Owner, then QA Release Engineer.

### Limited Release Allowed Without Device Scanner Claim

The next limited release can go forward as a core FinDesk MVP if the deploy package excludes or clearly limits any unproven scanner claim.

Allowed scope:

- quick field fixation after visible saved/retry state;
- cash/card separation;
- accountable money and participant responsibility;
- current period vs closed final report separation;
- `Закрытый групповой отчет` archive package for new data;
- browser print/PDF for closed package;
- group messages as scoped communication;
- Business Desk/proforma as preserved separate surface that does not mutate operational ledger/report formulas;
- Travel/Trip and Advanced as visible staged areas, not as completed engines;
- Receipt Scanner only as a local/file-input or beta proof helper if labeled and routed as not device-certified.

Conditions:

- no marketing or in-app claim that iPhone/Android installed PWA camera scanning is proven;
- no OCR/auto extraction claim;
- no statement that every legacy historical report is migrated into the new package format;
- production browser smoke must confirm the six CEO-reported leftovers are gone or accepted as explicitly limited.

### Post-MVP / Advanced

These are not blockers for the next limited MVP release unless CEO upgrades one of them explicitly:

- OCR amount/date/vendor extraction;
- automatic receipt edge detection beyond manual corners;
- automatic category/article/tax suggestions;
- OCR-driven financial row creation or mutation;
- full package-wide ZIP/file export beyond browser print/PDF and proof links;
- first-class `report_id` / `capture_id` / `advance_id` message schema beyond current audit-derived report context;
- migration of every old legacy report into new immutable packages;
- same-second finalization cutoff hardening unless reproduced in normal use;
- permanent destructive group deletion. MVP needs audited archive/hide, not irreversible delete;
- full travel equalization engine;
- full Business Desk/invoicing integration with group money reports;
- advanced dashboards, forecasts, fraud scoring, AI audit depth.

### Product Release Decision

Business MVP remains product-approved for the checked new-data path, but the next production step is not "ship everything blindly".

The next correct release mode is:

```text
limited core MVP release + production UX cleanup + production smoke
```

Scanner becomes part of a full production claim only after the real-device PWA/camera gate passes.

## Receipt Scanner / Proof PDF Product Contract - 2026-05-28

Role: Product Finance Architect
Task: product contract for Receipt Scanner / proof PDF as a separate product flow after CEO request.
Status: CONTRACT ISSUED / IMPLEMENTATION NOT STARTED BY THIS ROLE.
Evidence basis:

- CEO/product request: receipt scanner / proof PDF must become a product flow, not only a generic file picker.
- Existing product foundation: Field Combat Mode already requires photo/scan/proof near money capture, visible save state, recoverable unfinished sessions, and no data loss before deliberate submit/close.
- Existing Frontend note: current `scan` action is not true automatic document scanning/OCR; it opens the existing image/PDF proof path. Apple Notes-style edge detection, PDF cleanup, and OCR remain a separate Product/Backend/Frontend decision.

### Product Decision

Receipt Scanner / proof PDF is accepted as a separate evidence-capture stream for FinDesk proof, linked to live report rows and later archive packages.

It is not a new financial formula, not an automatic expense creation engine, and not an OCR-accounting product in the first MVP step.

The first MVP step must solve evidence trust and no-data-loss:

```text
photo/file source -> saved original source -> cleaned proof PDF -> linked evidence chain -> live report row -> final report/archive package
```

### MVP User Meaning

The user can attach proof to a live report row by:

- taking a receipt photo;
- choosing an existing image or PDF file;
- using the scan/proof action from the field capture or live report row surface.

The user must understand that:

- the original photo/file is preserved;
- FinDesk creates a cleaned PDF for reading/printing/archive use;
- both the original source and cleaned PDF belong to the same proof;
- proof can be pending offline/weak network without losing the row or source;
- OCR, automatic amount extraction, automatic supplier/date extraction, and automatic accounting category suggestion are not required in the first MVP step.

### Evidence Chain Contract

Every scanned receipt/proof must have one evidence chain identity.

Minimum evidence chain contents:

- `evidence_chain_id` or equivalent stable proof identity;
- linked live report row/capture id before the row enters final report;
- original source file preserved unchanged;
- cleaned PDF generated from the original source;
- creation timestamps and user/participant identity;
- upload/sync state for original source and cleaned PDF;
- manual crop/corner-correction metadata when used;
- final report/archive reference after the row is accepted and finalized.

Both assets are evidence:

- Original source is the source-of-truth proof file. It must not be overwritten by cleanup.
- Cleaned PDF is a derived proof artifact for review, print, package, and archive.
- The cleaned PDF must be traceable back to the exact original source.
- If cleaned PDF generation fails, the original source remains saved/pending and the proof is not lost.

### Manual Corner Correction

MVP must allow manual correction of receipt/document corners before or during cleaned PDF generation.

Accepted MVP behavior:

- automatic corner detection may be absent, weak, or best-effort;
- user can adjust corners manually;
- user can accept a rectangular crop/page cleanup;
- the app preserves the original even if the cleaned crop is wrong;
- user can regenerate/replace the cleaned PDF from the same original before final acceptance, with a visible history or latest-derived artifact reference.

Manual corner correction is more important than OCR for the first step, because it directly affects proof readability and archive quality.

### Live Report Link

Receipt proof must attach to a specific live report row/capture, not float as an orphan document.

Required product relationship:

```text
live report row/capture <-> evidence_chain_id <-> original source + cleaned PDF
```

The row must show proof state in ordinary language:

- `proof saved`;
- `proof pending upload`;
- `cleaned PDF pending`;
- `proof needs review`;
- `proof accepted`;
- `proof failed - retry needed`.

Final report and `Закрытый групповой отчет` package must preserve the evidence chain for accepted rows. Reviewers must be able to open the cleaned PDF and, when authorized, the original source.

### No-Data-Loss / Offline Pending Rule

Receipt Scanner inherits Field Combat no-data-loss rules.

Minimum accepted states:

- row saved, original source pending upload;
- row saved, original source uploaded, cleaned PDF pending generation;
- row saved, cleaned PDF generated, proof ready for review;
- failed upload/generation with visible retry;
- offline/weak-network pending state that survives refresh, navigation, phone lock/return, and app resume.

The system must never silently discard:

- the live report row;
- the selected/taken original source;
- the link between row and proof;
- the cleaned PDF once generated.

If the source file cannot be fully uploaded yet, the UI must not pretend proof is final. It must show pending/retry state and keep the row out of final accepted proof status until the evidence chain is complete or explicitly reviewed under a known missing-proof state.

### OCR Boundary

OCR is not MVP for the first Receipt Scanner step.

Out of MVP for first step:

- automatic amount/date/vendor extraction;
- automatic line-item parsing;
- automatic category/article assignment;
- automatic fraud scoring from receipt text;
- OCR-driven financial row creation;
- forcing a row amount to change because OCR disagrees.

Allowed later:

- OCR as assistant suggestion only, after original + cleaned PDF evidence chain and no-data-loss behavior are proven;
- OCR values must never silently mutate accepted financial amounts or formulas.

### Financial Boundary

Receipt Scanner changes evidence quality, not money logic.

Rules:

- attaching a receipt does not create income or expense by itself;
- cleaned PDF generation does not change cash/card/accountable formulas;
- OCR, if later added, can only suggest values until explicitly reviewed/accepted under normal FinDesk review rules;
- proof acceptance supports a row becoming accepted report truth, but the row amount/type/participant/stream still come from the existing live report/review process.

### Acceptance Position

Business MVP can treat true scanning/cleaned PDF as a separate product flow after the currently proven proof picker, but if CEO upgrades it into release scope, it must satisfy this contract before being called complete.

Blocker: not a formula blocker; product implementation and QA evidence are not yet present for true receipt scanner / cleaned proof PDF.
Next owner: Backend/Data Engineer + Frontend/UX Engineer, then QA Release Engineer.

## Production Multi-Employee Money-Flow QA Blocker Decision - 2026-05-27

Role: Product Finance Architect
Task: financial decision for production multi-employee money-flow QA blocker.
Status: DECISION ISSUED / BACKEND DATA CONTRACT REQUIRED.
Production evidence:

- Production run: `group_id=8`, `report_id=66`.
- QA artifact folder: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/`.
- QA finding: final detail/package/export headline totals show `admin_cash_left=532` and `accountable_money_left=184`.
- QA finding: employee 2 overrun is preserved only in audit refs as `status=discrepancy`, `expected_remaining=-36.00`, `difference_amount=36.00`.

### Financial Decision

For MVP final report, closed group package, export, and print, `admin_cash_left` must mean actual physical cash still held by the administrator at finalization before any explicit reimbursement payment is recorded.

In this production scenario the correct `admin_cash_left` is:

```text
admin_cash_left = received - issued_to_employees - admin_own_expenses
admin_cash_left = 1000 - (135 + 94 + 117) - (20 + 45 + 17 + 4)
admin_cash_left = 568
```

The value `532` is not the administrator's cash on hand. It is a projected or net position after recognizing that the administrator/group owes employee 2 `EUR 36`, but no actual reimbursement cash movement is recorded in the scenario. It may be useful as a separate settlement field, but it must not be labeled or exported as `admin_cash_left`.

Accepted MVP meaning:

- `admin_cash_left`: actual physical money held by the administrator before unsettled reimbursement payments.
- `employee_remaining`: participant accountable money remaining; can be positive or negative per participant.
- `employee_overrun` / `reimbursement_due`: negative participant remaining caused by accepted employee spend above issued accountable money.
- `admin_cash_after_reimbursement_due` or equivalent settlement projection: optional separate field, only if explicitly labeled as after-settlement/projected; it must not replace `admin_cash_left`.

### Required Control For group_id=8 / report_id=66

Scenario facts:

- Admin received: `EUR 1000`.
- Admin issued accountable cash: `135 + 94 + 117 = EUR 346`.
- Admin own expenses: `20 + 45 + 17 + 4 = EUR 86`.
- Employee 1 expenses: `6 + 9 + 43 + 10 = EUR 68`; remaining `135 - 68 = EUR 67`.
- Employee 2 expenses: `12 + 23 + 41 + 54 = EUR 130`; remaining `94 - 130 = EUR -36`; reimbursement due `EUR 36`.
- Employee 3 expenses: `EUR 0`; remaining `EUR 117`.

Accepted totals:

```text
total_expenses = 86 + 68 + 130 = 284
group_balance = 1000 - 284 = 716
holder control = 568 + 67 - 36 + 117 = 716
```

This holder-control equation must be visible from final detail/package/export/print without opening raw audit refs.

### Display Rule For Employee Overrun

Employee overrun is not a hidden audit-only fact. It is first-class participant-control data.

For each participant line in final detail, closed package, export, and print, MVP must show:

- participant identity;
- issued/accountable received amount;
- accepted spend amount;
- returned cash amount if a return action exists;
- remaining accountable amount with sign preserved;
- reimbursement due when remaining is negative;
- proof/status reference for accepted spend and discrepancy/overrun.

For employee 2 in this scenario, the visible participant line must show:

```text
issued/accountable received = 94
accepted spend = 130
remaining accountable = -36
reimbursement due to employee = 36
status/discrepancy = overrun / reimbursement due
```

User-facing wording can be localized, but the financial meaning must be clear:

- `Осталось у сотрудника`: use for positive remaining money, for example employee 1 `EUR 67` and employee 3 `EUR 117`.
- `Перерасход сотрудника`: use for negative remaining, for example employee 2 `EUR -36`.
- `К возмещению сотруднику`: use for the positive reimbursement amount, for example `EUR 36`.

Do not collapse employee 2 into `accountable_money_left=184` without also showing the negative overrun. `184` is only the sum of positive employee remainders (`67 + 117`). It is not the complete participant-control total unless the `-36` reimbursement due is also shown at the same headline/control level.

### Backend/Data Acceptance Contract

Backend/Data must make final detail, closed package, export, and print expose enough first-class fields to satisfy the same equation:

```text
admin_cash_left + positive_employee_remaining - employee_reimbursement_due = group_balance
568 + 184 - 36 = 716
```

or equivalently:

```text
admin_cash_left + net_employee_remaining = group_balance
568 + (67 - 36 + 117) = 716
568 + 148 = 716
```

Minimum accepted fields or equivalent explicit values:

- `received_money = 1000`.
- `total_expenses = 284`.
- `admin_cash_left = 568`.
- `employee_positive_remaining_total = 184` or clearly labeled equivalent.
- `employee_reimbursement_due_total = 36` or clearly labeled equivalent.
- `employee_net_remaining_total = 148` or calculable from first-class participant lines.
- `cash_balance` / `group_balance = 716`.
- participant row for employee 1: `issued=135`, `accepted_spend=68`, `remaining=67`, `reimbursement_due=0`.
- participant row for employee 2: `issued=94`, `accepted_spend=130`, `remaining=-36`, `reimbursement_due=36`.
- participant row for employee 3: `issued=117`, `accepted_spend=0`, `remaining=117`, `reimbursement_due=0`.

Acceptance checks:

1. `admin_cash_left` in final detail/package/export/print is `568`, unless a real reimbursement payment transaction was explicitly recorded before finalization.
2. If a real reimbursement payment transaction is recorded, it must appear as its own cash movement/proof and then may reduce `admin_cash_left`; the report must still show that the reduction came from reimbursement payment, not from formula netting.
3. Employee 2 negative remaining `-36` is visible outside raw audit refs in final detail, closed package, export, and print.
4. Headline/control totals do not rely on `532 + 184 = 716` as the only explanation, because that hides the employee overrun from participant control.
5. If `accountable_money_left` remains a positive-only field, it must be labeled as positive employee cash remaining and accompanied by `employee_reimbursement_due_total=36`.
6. If `accountable_money_left` is used as a net participant-control field, it must equal `148`, not `184`.
7. Archive package by `report_id=66` remains immutable after correction and later current-period activity must not rewrite the closed facts.
8. Audit refs may support the overrun, but audit refs alone do not satisfy MVP headline/package/export acceptance.

### Blocker Position

Current production output is a P0 financial-control blocker for the multi-employee money-flow scenario.

Reason: the final report and archive package currently reconcile the group balance arithmetically with `532 + 184 = 716`, but the headline participant-control surface does not show the required holder equation `568 + 67 - 36 + 117 = 716`. The product therefore hides who physically holds money and who must be reimbursed.

Next owner: Backend/Data Engineer.

## Final Business MVP Product Readiness Check - 2026-05-27

Role: Product Finance Architect
Task: Final Business MVP product readiness check after QA residual surface PASS.
Status: PASS.
Evidence read:

- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- this role's prior product contracts in `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`

Latest QA evidence accepted for this product position:

- QA residual surface run `20260527968710`.
- Accepted closed package anchor: group `222`, report `454`.
- QA status: PASS for baseline, group messages send/list/unread/mark-read/scope, report-context message references in `Закрытый групповой отчет`, Business Desk/proforma create/list/open/print and formula separation, Travel/Trip staged visibility, Advanced reachability, and mobile/tablet/desktop navigation reachability.
- QA recorded no residual surface financial contradiction with cash/card/accountable/report-package behavior.

### Product Readiness Position

The business MVP product loop is now coherent for a normal non-accountant for the first ordinary working cycle:

```text
зафиксировать -> проверить/понять -> сдать отчет -> сохранить -> распечатать/экспортировать -> свести группу отчетов -> сохранить/распечатать -> архив
```

Reason:

- Foundation MVP already proved the core money tree: fast field capture, cash/card split, current period vs historical finalized report separation, carryover, proof, and review boundary.
- Field Combat no-data-loss gate already proved the moving-user foundation: saved facts survive refresh/return, proof retry does not duplicate money rows, cash/card separation holds, and autosave/retry does not submit, include, or finalize.
- Closed group report package gate already proved `Закрытый групповой отчет` as one archive object by `report_id`, with participant reports, captures/proofs, money rows, accountable/advance state, message/audit references, authorized proof access, print/PDF, and immutability after later current-period activity.
- Residual surface QA proved the remaining product memory is still reachable and not formula-polluting: group messages, report-context message clarity, Business Desk/proforma, Travel/Trip staging, Advanced staging, and phone/tablet/desktop navigation.

For a non-accountant, the product story is now understandable without knowing backend endpoint names:

- `On the Go` records the money fact and proof while the user is moving.
- FinDesk review decides what becomes accepted report truth.
- `Текущий период` and `Закрытые финальные отчеты` separate live money from closed report history.
- `Закрытый групповой отчет` opens the group report as one closed case, not as a manual search across ledger, files, advances, messages, and audit.
- Group messages are reachable in group context and unread state works.
- Business Desk/proforma remains a separate business-solutions path and does not mutate operational group cash/report formulas.
- Travel/Trip remains visible as staged product memory and does not mix into ordinary business cash reporting.

### Advanced Boundary

`Advanced` remains non-MVP staging, not deletion and not a mandatory third MVP layer.

Keep in `Advanced` / post-MVP unless the CEO or Project Director later marks them launch-critical:

- full travel settlement engine;
- full invoicing/accounting suite beyond the preserved Business Desk/proforma create/list/open/print path;
- deep AI analytics, forecasting, fraud scoring, notarization/hash chain, third-party accounting integrations, and specialist/admin-heavy workflows;
- broad social chat archive beyond finance/report-context messages.

### True P0 Product Contradictions

None found from the evidence read for this task.

No current evidence shows a P0 contradiction between product meaning, accepted money formulas, UI reachability, group package evidence, group messages, Business Desk/proforma separation, Travel staging, or `Advanced` staging.

This PASS is a product-readiness position for the Business MVP loop. It is not a production deployment approval; deployment package selection, production smoke, backup, rollback, and dirty-tree deployment control remain separate Project Director / release concerns.

### P1 / Post-MVP Classification Confirmed

The following stay P1 / post-MVP and do not block Business MVP product coherence unless new evidence changes the risk:

- Package-wide downloadable file export beyond browser print/PDF and package proof links.
- First-class report-linked message schema beyond audit-derived report-context messages and clearly marked unlinked group refs.
- Legacy packages / old reports without `report_package`; keep warning/fallback instead of pretending they are new closed packages.
- Full travel settlement and equalization engine.
- Full invoicing/accounting suite beyond the preserved Business Desk/proforma path.

### Blocker And Next Owner

Blocker: none.
Next owner: Project Director.

## Open Findings

- The product risk is not arithmetic alone; it is context confusion between historical report and open period.
- Any screen showing `€1000` after report fixation must clearly indicate it is historical report income, not current open cash base.
- `Раздел учета` must mean category/article of accounting, not physical money source.

## Product Contract 2026-05-26: Two Report Truths After Finalization

Release requires a dedicated historical finalized report/export action. Archive rows, ledger rows, Live Report captures, and audit log are necessary proof sources, but they are not enough as the user's closed final report. A non-accountant must be able to open and export the old final report as a first-class product object after the main export has switched to the current open period.

FinDesk must expose two separate truths:

1. Current open-period truth.
   - User label for the screen/action: `Текущий период`.
   - User label for export: `Экспорт текущего периода`.
   - Meaning: live working money after the latest final report fixation.
   - It starts from `Переходящий остаток из финального отчета`.
   - It must not present old closed-period income as new current income.
   - It can change when new period money is received, spent, reviewed, accepted, or finalized.
   - Every current number must link to its proof: carryover links to the finalized report/date, current income links to the current income record, current cash/card expenses link to current accepted records or Live Report proofs, and accountable money links to the employee advance/report proof.

2. Historical finalized-report truth.
   - User label for the list/action: `Закрытые финальные отчеты`.
   - User label for one report: `Финальный отчет за закрытый период`.
   - User label for export: `Экспорт финального отчета`.
   - Meaning: closed report snapshot at the moment of finalization.
   - It must remain readable and exportable after a new open period starts.
   - It must not change because later current-period entries, archive visibility, or employee advance state changed.
   - Every historical number must link to its proof as it existed at finalization: income records, included cash/card expense records, accepted employee reports if included, finalization date/time, archive records, and audit evidence.

Carryover appears in both truths, but with different meaning:

- In the old closed final report it is `Остаток перенесен в следующий период`.
- In the new open period it is `Переходящий остаток из финального отчета`.
- The same `€400` may be visible in both places, but old report carryover is the result of the closed period, while current carryover is the starting money for the open period.

Scenario contract:

```text
EUR 1000 income -> EUR 600 expense -> EUR 400 carryover
```

Old closed report / historical export must show:

- `Получено`: `EUR 1000`.
- `Потрачено`: `EUR 600`.
- `Остаток перенесен в следующий период`: `EUR 400`.
- Proof: the income record for `EUR 1000`, the expense proof records for `EUR 600`, and the finalization proof that locked this report and created the `EUR 400` carryover.

New open period / current export must show:

- `Переходящий остаток из финального отчета`: `EUR 400`.
- `Получено за текущий период`: `EUR 0` until new money is received after finalization.
- Current cash base starts from `EUR 400`, not from the old `EUR 1000`.
- The old `EUR 1000` may only be opened through the historical finalized report/export, not as current income.
- Proof for the current `EUR 400` is the old finalized report, not a new income receipt.

Approved user-facing action names:

- `Текущий период` -> opens live current money.
- `Экспорт текущего периода` -> exports live current money.
- `Закрытые финальные отчеты` -> opens the history of closed reports.
- `Экспорт финального отчета` -> exports the selected closed report.

Dangerous wording:

- `Отчет` alone is ambiguous after finalization. Use `Текущий период` or `Финальный отчет за закрытый период`.
- `Доход EUR 400` is wrong for carryover. Use `Переходящий остаток из финального отчета`.
- `Архив` is not a substitute for `Закрытые финальные отчеты`; archive is evidence/history storage, not the closed report action.

## Product Contract 2026-05-26: Advanced And Field Combat Mode

Accepted rule: `Advanced` means non-MVP staging. It is not deletion and not a mandatory third layer for the first business MVP. A real feature goes to `Advanced` when it is useful, specialist, admin-heavy, future-facing, or deeper than the first ordinary working money loop.

Accepted rule: Field Combat Mode is MVP foundation. It cannot be moved to `Advanced`, because the first useful FinDesk loop starts when a moving user can preserve the money fact before perfect accounting.

Product meaning of Field Combat Mode:

- user writes the money fact quickly;
- user can photo, scan, or attach proof immediately;
- FinDesk calculates totals automatically;
- unfinished work remains recoverable;
- save/sync state is visible;
- weak network, navigation, phone lock, refresh, or partial upload must not silently lose the current money truth;
- closing/submitting a report remains a deliberate action.

Product boundary:

- MVP must keep fixation, proof capture, automatic totals, open-session recovery, visible sync state, review/submission, current period, finalized historical report/export, carryover, group context, participant responsibility, and archive proof.
- `Advanced` may hold deeper analytics, broad accounting completeness, specialist dashboards, full travel settlement if not launch-critical, full invoicing suite beyond the preserved Business Desk/proforma path, integrations, and other workflows not required for the first ordinary money cycle.
- Old modules such as group messages, travel equalization, and Business Desk must remain in product memory; they may be MVP-minimum or `Advanced`, but they must not disappear as a side effect of mobile simplification.

Open risk: the foundation gate proved a checked slice, but full Field Combat Mode no-data-loss behavior across refresh, module switch, weak network, pending proof upload, and unfinished-session recovery still needs Backend/Data, Frontend/UX, QA, and Chief Auditor evidence before business MVP can be called complete.

## Product Contract 2026-05-26: Immutable Group Report Archive Package

User-facing object name: `Закрытый групповой отчет`.

Internal/product contract name: immutable group report archive package. The word `Архивный пакет` may be used in technical tasks, but ordinary users should see a closed report, not a technical package.

Ordinary-user meaning:

- `Закрытый групповой отчет` is the saved folder of the whole group report.
- It answers: how much the group received, who held or spent the money, what stayed as physical cash, what was paid by card, what was accountable money, what was returned or carried forward, what proof supports each number, who accepted it, and when it became final.
- Opening it from archive must feel like opening one closed case, not like searching through ledger, Live Report, files, advances, messages, and audit screens.
- Later current-period activity must not change the closed package.

MVP rule: business MVP requires one immutable closed group report package by `report_id`. A group final report snapshot/export alone is not enough if the manager cannot open the accepted participant reports, captures, proofs, accountable state, audit references, and report-context messages from the same closed report.

### What Must Be Inside At MVP Close

The MVP package must contain:

1. Group report identity.
   - `report_id`, group identity/name, closed period or finalization scope, finalization date/time, status `closed/final`, finalizer/reviewer identity, and export/print identity.

2. Group financial summary.
   - Received money.
   - Physical cash spent.
   - Card/noncash spending.
   - Cash left in administrator/group cash.
   - Accountable money with employees.
   - Returned cash.
   - Discrepancy or unresolved responsibility.
   - Carryover into the next current period.
   - The summary must keep cash, card, accountable money, and carryover separate.

3. Participant report snapshots.
   - Each participant report included in the group package must have its own immutable identity inside the package.
   - User-facing term: `Отчет участника`.
   - It may be embedded inside the group package, but the user must be able to open it as a distinct accepted participant report.
   - The participant report must show participant, submitted/accepted time, reviewer, cash/card/accountable totals, returned cash, remaining accountable cash, discrepancy, and proof status.

4. Captures and money rows.
   - Every included money fact/capture must be frozen with amount, type, participant, cash/card effect, accountable effect when relevant, note/comment/category if present, created/submitted/accepted timestamps when available, and source reference.
   - A card expense must stay card/noncash and must not reduce physical cash.
   - Issuing money to an employee remains accountable movement, not expense.

5. Proof index and proof access.
   - Proof files must be directly visible and downloadable to authorized reviewers from the closed package.
   - Proof access must not depend on the original participant being the file owner.
   - MVP export/print may include a proof index with links/references, but the archive package itself must let the authorized reviewer open the proof.
   - If a proof is missing, the package must show `Нужно доказательство` or equivalent historical missing-proof state instead of pretending the number is fully proven.

6. Accountable/advance state.
   - Accepted employee spend appears as accepted expense with proof.
   - Returned cash appears as money returned to group/admin cash.
   - Open remaining cash appears as `Осталось у сотрудника` / carryover responsibility, not as expense.
   - Discrepancy appears as unresolved responsibility by participant.
   - Carryover responsibility must state who is responsible for the remaining accountable cash after the group report closes.
   - The state must be frozen as of finalization; later advance changes must not rewrite the closed package.

7. Report-context messages.
   - MVP package must preserve report-context messages that affect trust in the report.
   - Required message events: missing proof question, return for clarification, participant clarification/reply, proof-added/updated note when used for review, and acceptance note.
   - General group chat remains a linked group discussion and does not need to be fully copied into the closed package for MVP.
   - A closed package must make clear whether a message was part of the report review context or only ordinary group discussion.

8. Audit references.
   - The package must include references for finalization, included participant reports/cards, acceptance/return decisions, proof events when available, and export/print generation identity when available.
   - MVP requires a readable audit reference/index, not a full low-level journal dump inside the user-facing report.

### Print And Export At MVP

MVP print/export of `Закрытый групповой отчет` must include:

- group summary;
- participant breakdown;
- cash/card/accountable/carryover split;
- accepted spend and returned/open accountable money by participant;
- proof index;
- report-context messages index;
- audit/finalization references;
- enough identifiers for a reviewer to reopen the same archive package later.

MVP print/export must not be group summary only. A summary-only export is acceptable as an additional quick view, but it is not the business-MVP archive package.

### One-Click Archive Requirement

Archive must open `Закрытый групповой отчет` in one click from the group archive or closed reports list.

The opened package must show, without manual endpoint stitching:

- group summary;
- participant reports;
- captures/rows;
- proofs;
- accountable/advance state;
- report-context messages;
- audit/finalization references;
- export/print actions for the selected closed package.

### Post-MVP / Advanced

The following can stay post-MVP / `Advanced` without deleting product memory:

- migration of every old legacy finalization into the new package format;
- bulk ZIP/download bundle of every proof file if direct proof access from package exists;
- full append-only journal dump inside every user-facing package;
- cryptographic notarization/hash chain beyond ordinary audit references;
- advanced fraud scoring and AI anomaly analysis;
- full social chat archive beyond report-context messages;
- full travel settlement engine unless CEO marks travel launch-critical;
- full Business Desk/invoicing package integration with group money reports;
- third-party accounting integrations;
- deep management dashboards and forecasting.

Release implication: full business MVP remains blocked until Backend/Data implements the package source, Frontend/UX exposes it as one ordinary archive object, QA proves the multi-participant package, and Chief Auditor accepts the gate.

## Closed Findings

- None yet.
