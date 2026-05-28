# Handoff: Closed Group Report Package Gate

Date: 2026-05-27

From: Project Director

To: Chief Auditor

Priority: P0

## Read First

- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

Product defined the business object as `Закрытый групповой отчет`.

Backend/Data implemented the package source:

- `ledger_group_final_report_package`
- `ledger_group_final_report_proof_download`

Frontend/UX exposed the package as one archive object, not a summary-only report.

QA passed the package UI/API verification.

## QA Evidence

Fresh QA fixture:

- `group_id=222`
- `report_id=454`
- admin user `520`
- member user `521`

QA verified:

- package opens by `report_id`;
- UI label is `Закрытый групповой отчет`;
- package view is not summary-only;
- visible package sections include group summary, participant reports, captures/proofs, money rows, accountable/advance state, messages, and audit refs;
- authorized proof downloads use package proof URLs and return HTTP 200;
- cash/card split is preserved;
- accountable/open remaining employee cash is responsibility/carryover, not expense;
- Excel/Google are short final-report tables;
- package print/PDF includes package sections;
- later current-period activity does not mutate the closed package;
- mobile `390x844`, tablet `820x1180`, and desktop `1440x900` layout passed.

## Task

Decide the `Закрытый групповой отчет` package gate: approved, blocked, or waiting for more evidence.

Review whether the verified package satisfies the business-MVP requirement for one closed archive object that preserves:

- group received money;
- participant responsibility;
- physical cash vs card/noncash split;
- accountable/advance state;
- proof access for authorized reviewers;
- report-context messages or accepted backend-provided references;
- audit/finalization references;
- immutability after later current activity.

## Boundaries

- Do not declare full business MVP ready unless you explicitly decide all remaining gates are closed.
- This gate covers the closed group report package only.
- Field Combat no-data-loss is already approved and should not be reopened unless this evidence creates a contradiction.
- Package-wide file export beyond print/PDF is a follow-up only if you decide it is required for this gate.
- Direct report-linked message schema remains a follow-up unless audit-derived/package references are insufficient for MVP trust.

## Output

Update:

- `docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md`
- `docs/AI_TEAM/roles/05_chief_auditor/RISKS.md`
- `docs/AI_TEAM/roles/05_chief_auditor/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: Chief Auditor
Task: Closed group report package gate
Status: approved / blocked / waiting
Evidence pointer: `docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md`
Blocker: ...
Next owner: Project Director
