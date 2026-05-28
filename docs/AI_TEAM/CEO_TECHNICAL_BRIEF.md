# CEO Technical Brief: AI Team Office For FinDesk

Date: 2026-05-23
Project: `finance.brkovic.ltd / FinDesk`
CEO: Alexey
Office folder: `docs/AI_TEAM/`

## Executive Summary

FinDesk now has a virtual specialist office for disciplined product preparation. The goal is to stop mixing financial architecture, backend data logic, frontend UX, QA, and audit into one overloaded chat.

The office has five specialist roles:

1. Product Finance Architect
2. Backend Data Engineer
3. Frontend UX Engineer
4. QA Release Engineer
5. Chief Auditor

This is enough to prepare the product professionally without inflating staff.

## Why The Office Exists

The product is financially sensitive. The main risk is not only a wrong number on one screen. The real risk is losing the money tree:

- who holds physical cash;
- what was paid by card;
- what is accountable money;
- what was spent;
- what remains;
- what is checked;
- what is included in the final report;
- what is only archived or recorded in the journal.

The office forces each specialist chat to work inside a controlled professional role.

## Office Structure

```text
docs/AI_TEAM/
  00_START_HERE.md
  01_PRODUCT_COMPASS.md
  02_CURRENT_STATE.md
  03_WORKFLOW_RULES.md
  04_TASK_BOARD.md
  05_DECISIONS.md
  CEO_TECHNICAL_BRIEF.md
  CEO_TECHNICAL_BRIEF.docx
  roles/
    01_product_finance_architect/
    02_backend_data_engineer/
    03_frontend_ux_engineer/
    04_qa_release_engineer/
    05_chief_auditor/
```

## Role 1: Product Finance Architect

Purpose: owns the financial meaning of the product.

Responsibilities:

- defines cash, card, accountable money, administrator cash, employee cash, report, archive, and journal;
- approves financial formulas and wording;
- protects the rule that issuing money is not an expense;
- protects the rule that card spending does not change cash;
- separates historical report from open period.

Hard limits:

- does not rewrite frontend layout;
- does not change backend/database code;
- does not approve release alone.

Current weak spots:

- open-period wording after report fixation;
- old income appearing where carryover is expected;
- `Раздел учета` needing clear category meaning.

## Role 2: Backend Data Engineer

Purpose: owns PHP/API/database correctness.

Responsibilities:

- maintains cash/card separation in backend;
- preserves historical report data;
- implements open-period carryover correctly;
- protects export data sources;
- updates smoke tests for financial flows.

Hard limits:

- does not redesign screens;
- does not rename business concepts alone;
- does not hide data ambiguity with frontend-only changes.

Current weak spots:

- export snapshot source after final report fixation;
- group vs personal scope defaults;
- archive filters for employee-linked live reports.

## Role 3: Frontend UX Engineer

Purpose: owns screen structure and compact mobile/tablet/desktop UX.

Responsibilities:

- keeps screens focused and compact;
- separates operational actions from informational/reference data;
- keeps Live Report close to iPhone Notes behavior;
- improves menu/page distribution;
- makes report tables readable.

Hard limits:

- does not change financial formulas;
- does not invent accounting terms;
- does not add large settings/admin blocks to phone workflows.

Current weak spots:

- old layout residue;
- duplicate information;
- technical labels;
- dense mobile screens.

## Role 4: QA Release Engineer

Purpose: owns verification and release evidence.

Responsibilities:

- runs smoke tests;
- checks desktop/tablet/mobile;
- verifies financial scenarios;
- verifies roles and permissions;
- verifies Excel/Google export readability;
- records exact failures and acceptance evidence.

Hard limits:

- does not rewrite product rules;
- does not approve finance logic alone;
- does not dismiss user confusion as cosmetic.

Current weak spots:

- manual device review is incomplete;
- scenario for `€1000 -> €600 -> €400 carryover` must be formalized;
- personal/group scope confusion must be tested.

## Role 5: Chief Auditor

Purpose: owns coherence, risks, and release gate.

Responsibilities:

- reads all cabinets;
- detects contradictions between product, backend, frontend, and QA;
- maintains risk register;
- blocks release if P0/P1 risks remain;
- gives CEO-level status.

Hard limits:

- does not become a hidden coder;
- does not approve release without evidence;
- does not override financial rules silently.

Current gate:

```text
Not release-ready yet.
Reason: office has been created, but specialist review cycle has not run.
```

## Operating Model

Every specialist chat must:

1. run baseline checks;
2. read office documents;
3. read its role and status files;
4. stay inside role boundaries;
5. write findings in its own cabinet;
6. write tasks to other roles;
7. update status before ending the session.

Cross-role work is written in `TASKS_TO_OTHERS.md`.

Major decisions are written in `05_DECISIONS.md`.

Release readiness is controlled by the Chief Auditor in `RELEASE_GATE.md`.

## CEO Control Points

The CEO should ask these questions before approving release preparation:

1. Does every shown number answer whose money it is?
2. Is physical cash separated from card spending?
3. Are employee remainders carried forward correctly?
4. Does the administrator also appear in live reports and summaries?
5. Can old reports be opened without polluting the open period?
6. Is the phone workflow compact enough for real field use?
7. Does Excel/Google export look like a business report, not a technical dump?
8. Did QA verify desktop, tablet, and mobile?
9. Did Chief Auditor clear P0/P1 risks?

## Recommendation

Use this office immediately for the next phase.

Recommended first cycle:

1. Product Finance Architect finalizes terminology and expected numbers.
2. Backend Data Engineer verifies data snapshots and carryover.
3. Frontend UX Engineer cleans menu/page distribution.
4. QA Release Engineer tests core scenarios.
5. Chief Auditor reviews contradictions and updates release gate.

This keeps the project moving without returning to one-chat overload.
