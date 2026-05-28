# CEO Ideas Registry

Date: 2026-05-26
Owner: Project Director
Purpose: keep the CEO's older product ideas visible while FinDesk moves into practical release work.

## Director Rule

No old idea is considered "remembered" until it has:

- a plain-language product meaning;
- an owning role;
- a status;
- an acceptance check.

## Active Ideas

| Idea | Meaning | Owner | Status | Acceptance check |
| --- | --- | --- | --- | --- |
| Human money map | The product starts from `Где деньги?`, not accounting jargon. | Product Finance Architect | Active | Every visible number says who, where, what changed, and where proof is. |
| Instant field capture | A moving person records the money fact first and completes review later. | Frontend UX Engineer + Backend Data Engineer | Active | User can save amount/proof quickly; record stays draft/review until accepted. |
| Live Report as iPhone Notes | List screen and opened card screen are separate; saved card reopens with original rows. | Frontend UX Engineer | Active | Tap saved card -> exact rows open in view mode, pencil enables editing. |
| Cash/Card split | Cash and bank card are separate streams with shared UX but separate rules. | Product Finance Architect + Backend Data Engineer | Active | Card expenses never reduce physical cash. |
| Cash card sequence guard | Cash cards cannot be submitted out of order because next base depends on previous remainder. | Backend Data Engineer + QA Release Engineer | Active | Blocked submit highlights the previous card and says `Обработайте предыдущую запись в FinDesk.` |
| Employee accountable money | Money handed to an employee is not an expense; it changes custodian. | Product Finance Architect + Backend Data Engineer | Active | Issue, spend, submit, accept, return/carryover are traceable. |
| Archive as cleanup | Archive hides completed working cards but does not mutate money. | Chief Auditor + QA Release Engineer | Active | Archived card remains reproducible in report/journal/export. |
| Journal as black box | Journal is recovery/audit history, not a second operational ledger. | Backend Data Engineer + Chief Auditor | Active | User cannot use journal as a normal money action screen. |
| Mobile-first field work | People do not run through stores with laptops. | Frontend UX Engineer | Active | Mobile is the canonical Live Report path; desktop is a professional work canvas. |
| Mobile multitasking | Small screen must handle capture, reports, messages, archive, business documents, and travel without becoming a desktop menu. | Product Finance Architect + Frontend UX Engineer | Active | Phone IA has stable primary entrances, contextual actions, progressive disclosure, and no lost modules. |
| Field combat mode | Write, photo, scan, automatic calculation, continuous save, and no data loss in unfinished sessions. | Product Finance Architect + Backend Data Engineer + Frontend UX Engineer + QA Release Engineer | Active | QA proves refresh/navigation/network interruption does not lose current open-session data. |
| Receipt scanner proof PDF | FinDesk should create clean PDF proof from photos while preserving the original source. | Product Finance Architect + Backend Data Engineer + Frontend UX Engineer + QA Release Engineer + Chief Auditor | Opened | Original photo/file, cleaned PDF, metadata, money row, archive, and final report remain linked and auditable. |
| Advanced as non-MVP | Everything not required for the first working MVP goes to Advanced, without deleting the idea. | Project Director + Product Finance Architect | Active | Product classification separates MVP, Advanced/post-MVP, and CEO-decision items. |
| Glass product style | Live Report visual language is clean, glass-like, and product-grade. | Frontend UX Engineer | Active | No table-heavy "prostynya"; controls are balanced and compact. |
| Evidence for each number | Receipts, photos, comments, timestamps, and attachments support the shown amount. | QA Release Engineer + Chief Auditor | Active | QA scenario proves where evidence is visible before final report. |
| Final report as snapshot | Closing a report preserves history and starts the next open period from carryover. | Backend Data Engineer + Chief Auditor | Active | Old income stays historical; new period starts from carryover. |
| Group messages | Group finance needs a place to clarify reports, missing proof, returned rows, and decisions. | Product Finance Architect + Frontend UX Engineer + Backend Data Engineer | Active | Send/list/unread works inside group context and does not leak across groups. |
| Travel equalization | Trip with Friends is a separate product path: people, shared pot, trip expenses, final who-owes-whom alignment. | Product Finance Architect + Backend Data Engineer | Active | Product contract says what is MVP minimum and what algorithm remains phased. |
| Business solutions | Business Desk is a separate section for company profile, clients, proformas, and printable documents. | Product Finance Architect + Frontend UX Engineer | Active | Business documents remain reachable/printable and do not pollute operational cash reports. |

## First Practical Slice

Slice: `Instant field capture in Live Report`

Status: in progress.

Scope:

- keep formulas/API unchanged;
- add one-hand quick action controls near the Live Report note area;
- keep cash/card stream separation;
- route employee money handoff to `Подотчеты`, not to a fake expense row;
- keep saved cards editable only through the existing card flow.

Acceptance:

- cash stream offers quick `+ Получили` and `- Наличные` line starts;
- card stream offers quick `- Карта` line start;
- proof capture is reachable next to the note area;
- employee handoff action opens `Деньги -> Подотчеты`;
- no quick action silently marks a record final.

## Source Notes Read

- `docs/USER_MESSAGES_DIGEST_2026-05-22.md`
- `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`
- `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md`
- `docs/KNOWLEDGE_LIVE_REPORTS_ADVANCES_GROUPS_2026-05-21.md`
- `docs/IPHONE_NOTES_UX_ALGORITHMS_2026-05-21.md`
- `docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md`
- `docs/HANDOFF_2026-05-20.md`
- `docs/HANDOFF_FULL_PRODUCT_2026-05-21.md`
- `docs/STEP5_PREMIUM_SHELL_2026-05-20.md`
- `docs/CHECKPOINT_AFTER_BUSINESS_DESK_PROFORMA_20260503.md`
- `docs/AI_TEAM/11_MOBILE_MULTITASKING_RESEARCH.md`
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
