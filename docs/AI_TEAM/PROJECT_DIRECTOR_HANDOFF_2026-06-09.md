# Project Director Handoff - 2026-06-09

Project: `finance.brkovic.ltd / FinDesk`
Local path: `/home/alexey/GitHub/finance.brkovic.ltd`
Current branch: `main`
Current local route: `http://127.0.0.1:18902/app.php?build=routes44`
Desktop shortcut: `/home/alexey/Рабочий стол/Fin Desk.desktop`
Latest build marker: `20260609-cash-layout-discipline-routes44`
Latest closed local focus: Universal Cash Session / ЖЗ / Записи / Отчеты / Скрепка / Atlas.

## Start Here For The Next Chat

Read in this order before making changes:

1. `docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-09.md` - this file.
2. `docs/AI_TEAM/00_START_HERE.md` - office entry and current route.
3. `docs/AI_TEAM/04_TASK_BOARD.md` - detailed implementation log.
4. `docs/AI_TEAM/05_DECISIONS.md` - product/technical decisions.
5. `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md` - current discipline for ЖЗ/Записи/Отчеты.
6. `docs/AI_TEAM/91_UNIVERSAL_CASH_SESSION_ENGINE_2026-06-07.md` - universal cash/session engine.
7. `docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md` - product bible; it stays above old Phase docs unless CEO explicitly changes direction.
8. `docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md` if the next task touches Yacht.
9. `docs/AI_TEAM/89_CURRENT_SITE_ROUTE_TREE_2026-06-03.md` for old route map context, but verify against current code because routes44 is newer.

## Current Truth

The active local app is Atlas-backed through:

```text
server/findesk-atlas-server.js
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
```

Run locally:

```bash
FINDESK_PORT=18902 npm run start:atlas
```

Open:

```text
http://127.0.0.1:18902/app.php?build=routes44
```

Current desktop shortcut already points to that URL:

```text
/home/alexey/Рабочий стол/Fin Desk.desktop
```

Atlas DB is the current local backend truth for this slice. Mongo URI is read from:

```text
storage/secrets/mongodb_uri
```

Atlas access has been verified after allowlisting current IP `79.143.107.26`.

## Closed In This Chat Block

### Universal Cash Session Core

Implemented and smoke-tested:

- active Atlas-backed `cash_sessions`;
- participants;
- ЖЗ draft save/submit;
- strict signed parser:
  - `+100` / `+100 text` = income/contribution;
  - `-40` / `-40 text` = expense;
  - `100`, `=100`, `_100`, free text = note outside calculation;
- active draft record card creation;
- fixed record card on submit;
- legacy `batches` still written for preview compatibility;
- `record_cards` and `cash_reports` stored in Atlas session documents.

### Records / Записи

Implemented:

- context selector with `Без учета` first;
- report/account contexts next;
- cards filtered visually by selected context;
- non-selected cards dimmed;
- summary metrics for selected context:
  - `Входящая сумма`;
  - `Поступило`;
  - `Расход`;
  - `Остаток`;
- per-card report assignment/reassignment selector;
- attachment count and attachment links.

### Reports / Отчеты

Implemented:

- create/start report with title and opening amount;
- fix/lock report;
- archive report;
- restore report;
- print/PDF individual report package;
- save individual report JSON package;
- old group settlement preview remains below and stays `preview_not_final`.

### ЖЗ / Editor Discipline

Implemented:

- ЖЗ is one active record editor, not a list page;
- no records list below ЖЗ;
- current invalid line warning shows exact red line and explanation;
- primary button text:
  - `К записям` when no accepted lines;
  - `Зафиксировать и к записям` when accepted lines exist;
- note-only/invalid-only draft is saved before leaving to records;
- paperclip attaches files/photos to the active record card.

### Attachment / Paperclip

Implemented pragmatic Atlas-backed storage:

- choose photo/file;
- save attachment metadata and data URL inside active record card;
- preview/open saved attachments;
- current local limit: about 1.3 MB per file;
- this can later migrate to GridFS/S3 without changing UX.

### Layout Discipline Cleanup

Latest user QA found duplicated titles and oversized cards. Fixed in `routes44`:

- removed duplicated large `phase1Header` from cash flow pages;
- removed `Личный журнал · ЖЗ` style repetition inside ЖЗ;
- `cash-report` shell title is `Отчеты`, not `Отчет-превью`;
- ЖЗ inner context is compact `Активная запись`;
- cash ЖЗ no longer inherits old oversized `.phase1-journal-workspace` min-height/grid behavior;
- ledger metrics are compact;
- mobile layout collapses cleanly.

## Verified QA

Passed before this handoff:

```bash
node --check public/assets/app.js
node --check public/service-worker.js
node --check server/findesk-atlas-server.js
git diff --check
npm run audit:cash
npm run check:atlas
```

Final Atlas HTTP smoke passed on `routes43` before the layout cleanup:

- temporary workspace/session/report created;
- ЖЗ saved with `+100`, `-40`, unsigned number and `=` invalid line;
- attachment saved to active record card;
- record submitted/fixed and attachment survived;
- card assigned to report;
- report fixed/archived/restored;
- smoke workspace/session/audit documents deleted;
- cleanup count returned zero.

After layout cleanup to `routes44`, static checks, cash audit and Atlas ping passed. No app-level data smoke was needed because the layout cleanup changed UI structure/CSS/labels only and did not change backend formulas or persistence.

## Paths Of Truth

Product truth:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Current cash behavior truth:

```text
docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md
docs/AI_TEAM/91_UNIVERSAL_CASH_SESSION_ENGINE_2026-06-07.md
```

Current task state:

```text
docs/AI_TEAM/04_TASK_BOARD.md
```

Current decisions:

```text
docs/AI_TEAM/05_DECISIONS.md
```

Current route/build files:

```text
public/app.php
public/assets/app.js
public/assets/app.css
public/service-worker.js
server/findesk-atlas-server.js
```

Current Atlas smoke/check tools:

```text
scripts/atlas_connection_smoke.js
scripts/cash_session_math_audit.js
package.json
```

Yacht truth when touching Yacht:

```text
docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md
```

## Where Not To Go / Do Not Touch Without Explicit Order

Do not:

- do not run `git reset`, `git checkout --`, `git clean`, or destructive cleanup;
- do not deploy to production without direct CEO command;
- do not change settlement/financial formulas without architect/auditor review;
- do not move Бункеровка to the main FinDesk entry; it stays inside Yacht;
- do not make legacy modules normal navigation again;
- do not replace the new Product Shell with old Phase 1/2/3 mixed screens;
- do not transplant old Ship Cashbox code directly; use it as behavior reference only;
- do not store API keys in public JS;
- do not rediscover DB access before checking WebStorm/Atlas config and `storage/secrets/mongodb_uri`;
- do not treat `preview_not_final` reports as audited financial reports;
- do not silently reinterpret unsigned numeric ЖЗ lines as expense.

## Known Risks / Review Items

The current `npm run audit:cash` intentionally keeps two review cases visible:

- excluded participant expenses are reimbursed while participant is excluded from share;
- contribution/cash-in creates surplus credit not fully allocated by settlement transfer lines.

These are policy/formula questions. Do not patch them casually.

Attachment storage is currently pragmatic in-document data URL storage for local/Atlas MVP. It is good enough for QA and small evidence files, but not final large-file architecture.

## Where We Go Next

Recommended next sequence:

1. User-facing QA on `routes44` for `ЖЗ / Записи / Отчеты / Скрепка` with real user behavior.
2. Fix remaining layout/wording issues found by user, but keep structure discipline: one shell title, one working context, compact cards.
3. Apply universal records/report discipline selectively to Yacht/Home/Family/Road.
4. For Yacht, keep fuel/products specialized under Yacht; do not make bunkering the main FinDesk entrance.
5. After UX stabilizes, ask Architect/Auditor to decide the two settlement review cases before any final/audited report status.
6. Later replace attachment storage with dedicated file storage if large files become necessary.

## Current Server / Shortcut State

At handoff time the local server was started with:

```bash
FINDESK_PORT=18902 npm run start:atlas
```

Expected server log:

```text
FinDesk Atlas server http://127.0.0.1:18902/app.php?build=routes44
MongoDB Atlas connected: finance_brkovic_ltd
```

Desktop shortcut should contain:

```text
Exec=xdg-open http://127.0.0.1:18902/app.php?build=routes44
```

## Last Git State At Handoff

Before creating this handoff, `main` was synced with GitHub at:

```text
e233594 Clean up cash layout header duplication
```

This handoff must be committed after creation so the next chat can start from GitHub truth.
