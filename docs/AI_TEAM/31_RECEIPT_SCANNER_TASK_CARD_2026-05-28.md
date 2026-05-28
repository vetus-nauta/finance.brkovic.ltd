# Receipt Scanner Task Card

Date: 2026-05-28
Owner: Project Director
Status: opened / assigned to roles
Priority: P1 after current production MVP stabilization; may become P0 if CEO declares scanner required for the first business release.

## CEO Request

Build FinDesk's own good scanner that turns receipt/document photos into clean PDF proof.

## Product Meaning

`Receipt Scanner` is not just a camera button.

It is a proof-making module for the field workflow:

- capture or choose original photo/file;
- detect document/receipt edges;
- allow manual corner correction;
- straighten perspective;
- clean the image for readable proof;
- generate a polished PDF;
- attach proof to a Live Report row/card;
- preserve the original source next to the cleaned PDF;
- keep upload/sync state durable so proof is not lost on refresh, poor connection, or retry.

## Non-Negotiable Evidence Contract

- Original source is always preserved.
- Cleaned PDF is a derived proof, not a replacement for the original.
- Processing metadata must be stored or reproducible enough to audit.
- PDF/OCR text must not become the accounting truth by itself.
- Money row, original proof, cleaned PDF, report package, archive, and final report must stay linked.

## First Implementation Slice

MVP scanner slice should focus on:

- photo/file input from Live Report;
- manual crop corners;
- perspective correction;
- basic cleanup filters;
- one-page PDF generation;
- durable proof state;
- proof attachment and archive/final-report visibility.

OCR, automatic line-item extraction, multi-page batch scanning, AI categorization, and server-side recognition are Advanced/post-MVP unless Project Director reclassifies them.

## Role Assignments

### Product Finance Architect

Define product contract and financial/audit meaning:

- when a scanner proof is required vs optional;
- how original and cleaned PDF appear in the money tree;
- why OCR text is only helper data;
- acceptance criteria for `proof for each number`.

Role folder: `docs/AI_TEAM/roles/01_product_finance_architect/`

Status 2026-05-28: accepted by role. Evidence: `docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md`, section `Receipt Scanner / Proof PDF Product Contract - 2026-05-28`.

### Backend/Data Engineer

Define storage/API design:

- original file storage;
- cleaned image/PDF storage;
- metadata: corners, perspective transform, filter profile, version, hashes;
- proof state: pending, saved, failed, retrying;
- links to `capture_id`, `tape_id`, report package, archive, and final report;
- file size/privacy strategy.

Role folder: `docs/AI_TEAM/roles/02_backend_data_engineer/`

Status 2026-05-28: accepted by role. Evidence: `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`, section `Receipt Scanner Storage/API Task Card 2026-05-28`.

### Frontend/UX Engineer

Define scanner UX:

- launch from Live Report note proof actions;
- camera/file picker;
- corner handles;
- perspective preview;
- cleanup toggle/slider;
- original vs cleaned preview;
- `Переснять`, `Готово`, `Прикрепить PDF`;
- one-hand mobile-first flow for iPhone Safari PWA and Android Chrome.

Role folder: `docs/AI_TEAM/roles/03_frontend_ux_engineer/`

Status 2026-05-28: accepted by role. Evidence: `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`, section `Receipt Scanner UI task card`.

### QA Release Engineer

Prepare QA matrix:

- iPhone Safari PWA;
- Android Chrome;
- desktop upload;
- poor light, glare, wrinkled receipt, busy background;
- refresh before/after upload;
- offline/retry;
- no duplicate money rows;
- proof visible in archive/final report/package.

Role folder: `docs/AI_TEAM/roles/04_qa_release_engineer/`

Status 2026-05-28: accepted by role. Evidence: `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`, section `Task Card 2026-05-28 - Receipt Scanner QA Matrix`.

### Chief Auditor

Define audit gate:

- original source retained;
- cleaned PDF clearly derived;
- metadata/hash checks;
- archive opens both versions;
- final report proof survives after session close;
- OCR/helper recognition is not treated as accepted financial data.

Role folder: `docs/AI_TEAM/roles/05_chief_auditor/`

Status 2026-05-28: accepted by role. Evidence: `docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md`, Receipt Scanner gate section.

## Director Rule

No runtime code implementation starts until role contracts are written and Project Director selects the first slice.

Each role keeps full report in its own folder and returns one short technical report to the main chat.

## First Local Slice Selected

Status 2026-05-28: Project Director selected a frontend-only local prototype slice after all five role contracts were accepted.

Scope selected:

- open scanner from the Live Report `Скан` action;
- choose/capture image in browser;
- show scanner sheet with draggable crop corners;
- apply perspective correction and basic cleanup in canvas;
- generate a one-page PDF in browser;
- pass the generated PDF into the existing Live Report proof upload path.

Explicit limitation:

- this slice does not yet satisfy the full audit contract because backend still stores one proof file through the existing upload path;
- original source plus cleaned PDF plus processing metadata storage remains Backend/Data implementation work;
- QA must treat this as local prototype evidence only until Backend/Data and Chief Auditor gates are complete.
