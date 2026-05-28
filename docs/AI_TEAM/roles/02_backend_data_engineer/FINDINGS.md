# Backend Data Engineer Findings

## Candidate 34 DB Deploy Preflight 2026-05-28

Role: Backend/Data Engineer + Database Migration Owner FinDesk
Task: deploy-preflight sprint for candidate 34, DB scanner/group-delete migration safety.
Status: documented; runtime code unchanged; production DB-side NO-GO until production preflight is run and recorded.

Evidence pointer:

- `docs/AI_TEAM/roles/02_backend_data_engineer/DEPLOY_PREFLIGHT_DB_CANDIDATE_34_2026-05-28.md`

Result:

- Exact read-only production SQL was prepared for scanner columns/indexes and optional group-delete columns.
- Candidate scanner SQL files were classified:
  - `deploy/on_the_go_foundation.sql` creates foundation capture/file tables and file scanner columns;
  - `deploy/on_the_go_sessions_runtime.sql` is the main candidate apply file for existing runtime DBs because it covers runtime tables, upload states, and scanner columns.
- The candidate SQL uses `ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS`; this is MariaDB-friendly, but MySQL/fork compatibility remains a deploy control until `VERSION()` / `@@version_comment` is checked on production.
- `app/groups.php::ql_group_delete()` no longer requires `groups.updated_at`, `groups.archived_at`, `group_members.left_at`, `group_members.updated_at`, `group_invites.revoked_at`, or `group_invites.updated_at`; it checks optional columns before writing them.
- `group_delete` requires no SQL migration when the base group/member/invite status columns already exist.

Blocker:

- Production DB-side remains NO-GO until the read-only preflight is run on the production DB, any missing scanner schema is applied after backup, and before/after evidence is recorded.

## Group Soft Archive API Hardening 2026-05-28

Role: Backend/Data Engineer FinDesk
Task: local backend/API residue before next deploy package; safe test-group removal without frontend UX or formula changes.
Status: implemented locally; waits for QA.

### Scope

- Changed only backend/API/smoke/docs within the assigned scope.
- No hard SQL delete of group financial evidence.
- No frontend UX, `app/ledger.php`, `app/on_the_go.php`, CSS, templates, or financial formulas changed.

### Current API State

- `public/api.php` already exposes `group_delete`.
- `app/groups.php::ql_group_delete()` now treats the action as a soft archive:
  - `groups.status='archived'`;
  - active group members are marked `left` so direct group API access stops after archive;
  - active invites are revoked;
  - ledger entries, Live Report tapes/captures, proof files, advances, messages, and final-report audit rows are not deleted.

### Safety Additions

- Base/regular employee is denied with `admin_required`.
- Group creator can receive idempotent `already_deleted` after the archive even though active membership was closed.
- Response includes `archive_mode='soft'`.
- Response includes `financial_evidence.before`, `financial_evidence.after`, and `financial_evidence.preserved`.
- Audit log `group_deleted` records the soft archive mode, member/invite counts, and before/after evidence counters.
- Runtime schema drift is handled: `ql_group_delete()` checks whether optional timestamp columns exist before writing them, so DBs without `groups.updated_at` or `group_invites.updated_at` do not fail.

### Local-Smoke Coverage Added

`scripts/local-smoke.php` now creates a dedicated delete-test group and checks:

- admin creates group;
- base member joins;
- admin creates a ledger evidence row;
- base member cannot call `group_delete`;
- admin archives the group;
- returned financial evidence counter preserves the ledger row before/after;
- repeated owner call is idempotent;
- archived group disappears from active `group_list`;
- archived group rejects later group ledger writes.

### Director HTTP Smoke 2026-05-28

- Fixture: `group_id=228`.
- Base member delete attempt returned `admin_required`.
- Admin `group_delete` returned `ok=true`, `archive_mode=soft`, `status=archived`.
- Evidence counters preserved the ledger row: `ledger_entries before=1`, `after=1`.
- Repeated creator call returned `already_deleted=true`.
- This reproduces the missing-`updated_at` runtime path that previously failed with `Unknown column 'updated_at' in 'SET'`.

### Known Boundary

- This patch only adds/strengthens the backend API. It does not add the visible UI delete button.
- Archived group access is intentionally closed by moving memberships to `left`; evidence remains in database/audit storage.
- Full confirmation on production requires QA/API smoke and later frontend wiring if Product wants a visible control.

## Receipt Scanner Storage/API Task Card 2026-05-28

Role: Backend Data Engineer FinDesk
Task: define storage/API contract for Receipt Scanner evidence.
Status: task card recorded; implementation pending; no runtime code changed.

### Boundary

- Documentation-only backend task card.
- No schema, API, PHP, JavaScript, production data, or formula change was made in this pass.
- This card defines the required evidence/storage contract before implementation.

### Required Backend Evidence Contract

The Receipt Scanner must persist scanner evidence as durable backend proof, not as a UI-only visual state.

Required stored artifacts:

- original uploaded image/file:
  - immutable private storage object;
  - MIME type, extension, byte size, width/height when applicable;
  - original SHA-256 hash;
  - stable storage key and upload timestamp;
  - owner/user/group authorization scope.
- cleaned image and/or generated PDF:
  - stored as derivative artifacts, not regenerated from the current browser view;
  - derivative type: `cleaned_image`, `cleaned_pdf`, or both;
  - byte size, MIME type, SHA-256 hash, storage key, and generated timestamp;
  - explicit link to the original artifact.
- scanner metadata:
  - detected/selected corners in normalized coordinates;
  - perspective/crop/rotation parameters;
  - filter mode and filter parameters;
  - scanner algorithm/version, client build, processing mode, and source device/browser hints if available;
  - canonical metadata JSON hash so the same artifact set produces the same evidence digest.
- proof upload state:
  - durable states for `pending`, `failed`, and `retry_needed` / retry;
  - `uploaded` or ready state after hash/size/MIME validation succeeds;
  - `client_upload_id` or equivalent idempotency key;
  - retry count, last error code/message, and timestamps.
- finance/archive links:
  - `capture_id` link for the money row being proven;
  - `tape_id` / field-session link for Field Combat recovery;
  - report/final report link when the capture is submitted/included/finalized;
  - closed archive/package link so the selected historical report opens the exact saved original, cleaned image/PDF, metadata, and hashes.

### Deterministic Evidence Rule

Backend evidence must be deterministic and replayable:

- artifact hashes are calculated from stored bytes, not from transient browser state;
- scanner metadata is stored as canonical JSON before hashing;
- a proof/evidence digest should include original hash, derivative hash, metadata hash, capture/tape/report/archive identifiers, and scanner version;
- closed report/archive views must open saved artifacts and stored metadata, not re-run scanning against current code;
- retries must not create duplicate proof rows when the same `client_upload_id` is replayed.

### File Size And Privacy Rules

Implementation must define and enforce:

- maximum accepted size per original file and per cleaned/PDF derivative;
- allowed MIME types for images/PDF and rejection path for unsupported files;
- private storage outside public listing, or a hard 403/auth boundary for any storage URL;
- authorization-scoped download endpoint for original and cleaned/PDF artifacts;
- `X-Robots-Tag: noindex, nofollow, noarchive` or equivalent for any reachable storage response;
- privacy policy for EXIF/device metadata:
  - original may be preserved privately as legal/audit evidence;
  - cleaned image/PDF should strip unnecessary EXIF/location metadata unless Product/Legal explicitly requires it;
  - logs must not leak raw storage paths, public receipt URLs, or financial proof contents.

### API Shape To Implement

Exact names are open, but the backend should expose these semantics:

1. begin scanner proof for a capture/tape context and return a durable `pending` proof id.
2. upload original file with `client_upload_id`, size, MIME, and expected hash.
3. upload or attach cleaned image/PDF derivative plus scanner metadata and expected hashes.
4. mark upload failure/retry with durable state and error detail.
5. list/open proof artifacts from capture, tape, report, final report, and archive/package contexts according to role permissions.
6. expose deterministic evidence metadata in final report/package/export proof index.

### Processing Options

Frontend-only PDF generation:

- Pros: fastest MVP path, works naturally with mobile camera/offline Field Combat flow, avoids server image/PDF CPU and queue work, and lets the user immediately confirm the cleaned receipt.
- Cons: output can vary by browser/device/library version, backend cannot independently trust visual transformations unless it stores original bytes, generated bytes, scanner metadata, and hashes.

Server-side PDF generation:

- Pros: more canonical output, central library/version control, stronger reproducibility, easier future OCR/validation/reprocessing, and less reliance on client device behavior.
- Cons: requires image/PDF processing runtime, CPU/memory limits, sandboxing, queue/error handling, and extra production operations before the first scanner MVP.

MVP recommendation:

- Start with client-side scanning/cleaning/PDF generation plus backend storage of original, cleaned image/PDF, metadata, states, and hashes.
- This matches mobile/Field Combat no-data-loss needs and keeps first delivery smaller.
- Backend storage must still be authoritative: archive/report evidence is the stored artifact set, not the frontend's ability to redraw it later.
- Server-side generation can be added later as a validator/reprocessor without changing the core artifact/link/hash contract.

### Backend Acceptance Criteria

- Original and cleaned/PDF artifacts survive refresh, logout/login, browser cache clear, and report finalization.
- `pending`, `failed`, and retry states survive refresh and are visible through recovery/list endpoints.
- A receipt attached to a capture remains linked through tape, report, final report, and archive/package views.
- File size/MIME/privacy rules reject unsafe or oversized uploads deterministically.
- Replaying the same upload id does not duplicate proof artifacts.
- Closed report/archive evidence can show the saved original, cleaned/PDF derivative, scanner metadata, and evidence digest.

### Evidence Pointer

This section is the current backend evidence pointer for the Receipt Scanner storage/API task card until implementation artifacts exist.

### Blocker

Runtime implementation is not started. Release-ready Receipt Scanner claims remain blocked until Backend implements storage/API, Frontend wires client scanning uploads, and QA proves deterministic recovery/archive evidence.

## Base Employee Rights Hotfix 2026-05-27

Role: Backend Data Engineer FinDesk
Task: enforce default invited employee rights.
Status: implemented and deployed; director production smoke passed; QA recheck required.

### Product Rule

Default invited `base` employee:

- can use the app as a normal personal user;
- inside the invited group, can use operational capture, accountable/self-control, and own primary outputs;
- cannot access group ledger, group reports, final report list/detail/export, group archive, group messages, member list beyond self, money management, role management, or other participants' money data.

### Code Changes

- `app/on_the_go.php`
  - base employee active field tape no longer seeds from full group working cash balance;
  - base employee draft `participant_user_id` is forced to self.
- `app/messages.php`
  - group messages now require manager/admin/group-data permissions;
  - default base employee cannot read/send group messages or receive group message unread feed.
- `public/app.php`
  - default invite label clarified as `Сотрудник · фиксация и самоконтроль`.
- `public/assets/app.js`
  - group panels for invite/messages/members/rename are hidden for default base access;
  - base access label clarified as `Фиксация`.

### Local Evidence

- local fixture: `group_id=224`, employee user `532`, stamp `20260527210214`;
- base employee could not access group export, final report list, or group messages;
- base employee saw only self in members;
- self-control balance did not include admin group cash `1000`;
- field tape started from `0`, not group cash;
- draft participant was forced to self;
- own operational field row saved.

### Production Evidence

Hotfix record:

- `docs/AI_TEAM/29_PRODUCTION_HOTFIX_BASE_RIGHTS_2026-05-27.md`

Production backup:

- backup id `prod-hotfix-before-base-rights-20260527T210230Z`;
- checksum `d6344267925c9742f4f6f21e3e4609942d53544fe2ea998a5eaf9904afe8d732`.

Production fixture:

- `group_id=10`
- employee user id `27`
- stamp `20260527210337`

Passed production checks:

- base invite permissions restricted;
- base employee cannot export group report;
- base employee cannot list final reports;
- base employee cannot read/send group messages;
- base self-control balance excludes admin cash `1000`;
- base field tape starts from own cash base `0`;
- base draft participant forced to self;
- base employee can save own operational field row;
- base employee sees only own operational cards.

### Verification

- `git diff --check app/on_the_go.php app/messages.php public/assets/app.js public/app.php`: PASS.
- `node --check public/assets/app.js`: PASS.
- local HTTP load check `/api.php?action=current_user`: `200 OK`.
- production HTTP load check `/api.php?action=current_user`: `200 OK`.
- PHP CLI lint: environment-blocked, `php: command not found`.

### Remaining Gate

Formal QA Release Engineer production recheck remains required.

## Production Multi-Employee Participant-Control Patch 2026-05-27

Role: Backend Data Engineer FinDesk
Task: fix production multi-employee money-flow blocker after Product Finance decision.
Status: implemented and deployed; director production smoke passed; QA recheck required.

### Product Finance Contract Applied

- `admin_cash_left` means physical cash held by the administrator at finalization before an explicit reimbursement payment is recorded.
- Employee overrun is first-class participant-control data, not audit-only data.
- Accepted control equation for the QA scenario: `568 + 67 - 36 + 117 = 716`.

### Code Changes

- `app/ledger.php`
  - Added participant-control rows that preserve positive employee remainders and accepted negative employee overrun.
  - Final detail/list/package/export now expose:
    - `admin_cash_left`;
    - `employee_positive_remaining_total`;
    - `employee_reimbursement_due_total`;
    - `employee_net_remaining_total`;
    - signed participant rows, including negative `cash_left` and `reimbursement_due`.
  - Historical final report reads apply compatible participant-control correction from saved package/audit refs without rewriting the stored archive row.
  - Current open-period carryover reads use the effective participant-control snapshot, so existing finalized reports do not keep the hidden `532/184` model at runtime.
  - Package accountable summary and participant rows now expose `positive_remaining_cash`, `reimbursement_due`, and `net_remaining_cash`.
- `public/assets/app.js`
  - Closed package and final report UI now show `Сотрудники net`, `Остатки сотрудников`, and `К возмещению`.
  - Accountable rows now show signed remaining balance and reimbursement due.

### Local HTTP Evidence

Local server: `http://127.0.0.1:18889`.

Fixture:

- `group_id=223`
- `report_id=499`
- stamp `20260527203918`

Scenario:

- admin received `1000`;
- employee advances `135 / 94 / 117`;
- admin expenses `20 / 45 / 17 / 4`;
- employee 1 expenses `6 / 9 / 43 / 10`;
- employee 2 expenses `12 / 23 / 41 / 54`;
- employee 3 expenses `0`;
- finalization completed.

Passed checks:

- base totals: income `1000`, expense `284`, cash/balance `716`;
- `admin_cash_left=568`;
- `employee_positive_remaining_total=184`;
- `employee_reimbursement_due_total=36`;
- `employee_net_remaining_total=148`;
- participant rows include `67`, `-36` with `reimbursement_due=36`, and `117`;
- package summary exposes the same participant-control values;
- final export contains `568.00`, `-36.00`, and `36.00`.

### Production Hotfix Evidence

Hotfix record:

- `docs/AI_TEAM/28_PRODUCTION_HOTFIX_PARTICIPANT_CONTROL_2026-05-27.md`

Uploaded runtime files:

- `app/ledger.php`
- `public/assets/app.js`

Production backup:

- backup id `prod-hotfix-before-participant-control-20260527T204210Z`;
- checksum `39550b6b4b4938d009085af33e2ece1bde1dc64477c1f84aded0299a23770471`.

Production director smoke fixture:

- `group_id=9`
- `report_id=84`
- stamp `20260527204359`

Passed production checks:

- base totals: income `1000`, expense `284`, cash/balance `716`;
- `admin_cash_left=568`;
- `employee_positive_remaining_total=184`;
- `employee_reimbursement_due_total=36`;
- `employee_net_remaining_total=148`;
- signed employee overrun row visible as `-36` with reimbursement due `36`;
- package summary fixed;
- final export contains `568.00`, `-36.00`, and `36.00`.

### Verification

- `git diff --check app/ledger.php public/assets/app.js`: PASS.
- `node --check public/assets/app.js`: PASS.
- local HTTP load check `/api.php?action=current_user`: `200 OK`.
- PHP CLI lint: environment-blocked, `php: command not found`.

### Remaining Gate

Production director smoke is fixed for this scenario. Formal release acceptance remains blocked until QA Release Engineer records an independent production recheck in the QA role folder.

## Production Multi-Employee Money-Flow QA Blocker Trace 2026-05-27

Role: Backend Data Engineer FinDesk
Task: technical trace of production multi-employee money-flow QA blocker, `group_id=8`, `report_id=66`, without code/schema/API/UX/formula changes before Product Finance decision.
Status: trace completed; patch is blocked pending Product Finance Architect decision on participant-control semantics.

### Boundary

- No application code was changed.
- No financial formula was changed.
- No backend/API contract, schema, or UX was changed.
- This report records evidence, root-cause path, and patch plan candidates only.

### Production Evidence Read

- QA flow: `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`, lines 20-40.
- Runner/artifacts: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/production_multi_employee_runner.mjs`, lines 210-274.
- Final detail: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/final_report_detail.json`.
- Final package: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/closed_group_package.json`.
- Retrieved summary: `docs/AI_TEAM/roles/04_qa_release_engineer/artifacts/production_multi_employee_2026-05-27/retrieved_final_artifacts.json`.

### Reproduced Accounting State From Artifacts

- `final_report_detail.json` snapshot totals: `income=1000`, `expense=284`, `cash_expense=284`, `admin_cash_left=532`, `employee_cash_left=184`, `cash_balance=716`, `balance=716`.
- `final_report_detail.json` accountable rows contain only positive open employee balances: employee 1 `67`, employee 3 `117`; employee 2 is absent.
- `closed_group_package.json` summary: `admin_cash_left=532`, `accountable_money_left=184`, `discrepancy=0`, `cash_balance=716`, `balance=716`.
- `closed_group_package.json` audit refs preserve employee 2 submit state: `status=discrepancy`, `expected_remaining=-36.00`, `actual_remaining=0.00`, `difference_amount=36.00`.
- `closed_group_package.json` accountable snapshot has employee 2 as accepted advance: `issued=94`, `accepted_spent=130`, `open_remaining_cash=0`, `discrepancy=0`.
- `retrieved_final_artifacts.json` counts show `participants=1`, `captures=4`, `money_rows=13`, `accountable=3`, `audit_refs=12`; the only package participant section is the admin non-advance Live Report card.

### Calculation Point For `admin_cash_left=532` And `accountable_money_left=184`

Primary source is `app/ledger.php`.

1. `ql_ledger_group_accountable_left_rows()` builds employee accountable rows from `cash_advances` only in statuses `issued`, `submitted`, `returned`, `discrepancy`, and keeps only positive balances with `HAVING cash_left > 0.009` (`app/ledger.php:360-390`). Accepted advances and negative overrun balances are not returned.
2. `ql_ledger_group_export_snapshot()` totals prepared ledger/live rows and then subtracts the sum of `accountableRows.cash_left` from cash balance (`app/ledger.php:601-732`):
   - `cash_balance = income cash - cash expenses = 1000 - 284 = 716`;
   - `employee_cash_left = 67 + 117 = 184`;
   - `admin_cash_left = 716 - 184 = 532`.
3. `ql_ledger_group_rows_snapshot()` has the same formula for open-period/carryover snapshot composition (`app/ledger.php:420-515`).
4. Finalization freezes those values into `audit_log.details.report_snapshot` and carryover fields (`app/ledger.php:2122-2255`, especially `2137`, `2207-2219`).
5. Final detail/list/export read the frozen snapshot, not current recomputation: `ql_ledger_group_final_report_public()` maps `snapshot.totals` (`app/ledger.php:1120-1152`), `ledger_group_final_report_detail` returns that snapshot (`app/ledger.php:1312-1325`), and final Google/Excel exports call `ql_ledger_group_snapshot_sheet_payload()` over the same snapshot (`app/ledger.php:914-934`, `1328-1377`).
6. Package headline summary copies the same frozen snapshot totals: `admin_cash_left` from `totals.admin_cash_left`, `accountable_money_left` from `totals.employee_cash_left`, `cash_balance` and `balance` from the same totals (`app/ledger.php:2039-2054`).

Conclusion: production `532/184/716/716` is not a print/export-only defect. It is the frozen group snapshot formula plus the positive-open-accountable filter.

### Why Accepted Overrun Disappears From Headline Totals

1. Advance submit calculates expected, actual, and difference (`app/advances.php:552-556`) and persists them to `cash_advances` and `on_the_go_tapes` (`app/advances.php:558-595`). For employee 2 this produced `expected_remaining=-36.00`, `actual_remaining=0.00`, `difference_amount=36.00`.
2. The audit write stores that discrepancy as `advance_submitted` (`app/advances.php:597-604`), which is why package audit refs still show the overrun.
3. `ql_advance_accept()` accepts both `submitted` and `discrepancy` advances (`app/advances.php:725-727`), converts captures into normal `ledger_entries` expenses (`app/advances.php:745-810`), then sets the advance status to `accepted` (`app/advances.php:812-826`).
4. On accept, rollover is created only when `$remaining > 0.009` (`app/advances.php:828-878`). Employee 2 actual remaining is `0`, so no rollover/negative carryover row is created.
5. The final package accountable snapshot computes `open_remaining_cash` only for statuses `issued/submitted/returned/discrepancy` and computes `discrepancy` only while `status === 'discrepancy'` (`app/ledger.php:1582-1590`). Once employee 2 is accepted, package discrepancy becomes `0` even though the accepted row still implicitly proves `accepted_spent 130 - issued 94 = 36`.
6. The headline `accountable_money_left` comes from `snapshot.totals.employee_cash_left`, which is sourced by `ql_ledger_group_accountable_left_rows()` and therefore excludes accepted/negative overrun responsibility (`app/ledger.php:360-390`, `682-688`, `2039-2046`).

Conclusion: accepted overrun survives only as audit evidence and as an implicit accepted-spent-vs-issued delta. There is no first-class accepted-overrun/reimbursement field feeding final detail/package/export headline participant-control totals.

### Why Employee Participant Reports Are Not First-Class Package Participant Sections

Confirmed by code.

1. Finalization selects package cards only from `on_the_go_tapes` where `(t.advance_id IS NULL OR t.advance_id = 0)` (`app/ledger.php:2140-2164`). Advance-linked employee tapes are intentionally excluded from `$cards`.
2. `ql_ledger_virtual_on_the_go_entries()` also excludes advance-linked tapes from virtual Live Report entries with `(t.advance_id IS NULL OR t.advance_id = 0)` (`app/ledger.php:91-99`).
3. `ql_advance_accept()` converts advance captures into ordinary `ledger_entries` with note `From advance #...` (`app/advances.php:760-810`), so accepted employee reports enter the final snapshot as money rows, not as package participant sections.
4. `ql_ledger_group_final_report_build_package()` creates `participants[]` only by iterating `$cardRows` from the non-advance `$cardIds` (`app/ledger.php:1825-1974`). It does not create participant sections from `cash_advances`, advance tapes, or accepted advance ledger rows.
5. The accountable state is present separately via `ql_ledger_group_final_report_accountable_snapshot()` (`app/ledger.php:2012`, `1520-1675`), but that object is not the package `participants[]` first-class section list.

Conclusion: production package behavior is consistent with code. Employee advance reports are represented as ledger money rows plus accountable/audit refs, not as first-class package participant report sections.

### Product Finance Decision Required

The blocker is semantic, not a missing arithmetic operation in one export.

Product Finance must decide whether final report headline participant-control totals should:

1. Keep current cash-holder formula: `admin_cash_left = cash_balance - positive open accountable cash`, with accepted overruns shown only in accountable detail/audit; or
2. Introduce a participant-control formula where accepted overruns are negative employee balances/reimbursement due, e.g. `admin 568 + emp1 67 - emp2 36 + emp3 117 = cash_balance 716`; or
3. Preserve existing financial totals and add a separate `participant_control` headline object without redefining `admin_cash_left/accountable_money_left`.

Backend should not implement option 2 or 3 until Product Finance names the canonical terms and confirms whether `admin_cash_left` is physical admin-held cash, settlement-control cash, or a participant-control balancing field.

### Patch Plan After Product Finance Approval

Proposed implementation direction if Product Finance approves participant-control visibility:

1. Add a backend helper in `app/ledger.php` that computes participant-control balances separately from income/expense/cash balance:
   - positive employee balances from open/rollover accountable cash;
   - negative employee balances from accepted overrun/reimbursement due, sourced from accepted advance evidence (`expected_remaining < 0`, `difference_amount`, and/or `accepted_cash_spent - issued_amount - returned_cash` as Product Finance chooses);
   - admin/control holder balance as `cash_balance - sum(employee participant-control balances)`;
   - no change to `expense`, `cash_expense`, `cash_balance`, or `balance`.
2. Extend `ql_ledger_group_final_report_accountable_snapshot()` to freeze explicit accepted-overrun fields, not only status-based `discrepancy`:
   - `submitted_expected_remaining`;
   - `actual_remaining`;
   - `difference_amount`;
   - `overrun_reimbursement_due`;
   - `participant_control_balance`.
3. Extend final snapshot/package with a new stable object, preferably `participant_control`, unless Product Finance explicitly redefines existing `admin_cash_left` and `accountable_money_left`.
4. If Product Finance requires headline replacement, update `summary.admin_cash_left`, `summary.accountable_money_left`, carryover fields, final detail/report public totals, and export payload together so detail/package/TSV/Excel cannot diverge.
5. Add first-class package participant sections for advance-linked employee reports:
   - source type `cash_advance_report`;
   - `source_advance_id`, `source_tape_id`;
   - submitted/accepted/reviewer timestamps;
   - accepted cash/card spend, returned cash, remaining cash, overrun/reimbursement due;
   - proof ids copied from accepted advance ledger entry files where available;
   - capture or ledger-entry links sufficient for archive inspection.
6. Update QA/local smoke to cover:
   - accepted overrun remains visible after accept/finalization;
   - participant equation equals `cash_balance`;
   - final detail/package/final export agree;
   - employee advance reports appear as first-class participant sections;
   - no regression to card/noncash and positive rollover behavior.

### Candidate Files For Patch

- `app/ledger.php`: snapshot totals/control helper, package summary, accountable snapshot, package participants, final detail/list/export snapshot payload.
- `app/advances.php`: only if Product Finance wants accept-time explicit overrun audit/details or additional accepted-overrun persistence; current submit evidence already exists.
- `public/assets/app.js`: only after backend contract decision, to render participant-control headline and first-class advance participant sections.
- `scripts/local-smoke.php`: add regression fixture for multi-employee accepted overrun and package participant sections.
- QA artifact/runner owned by QA Release Engineer: update production gate expectations after Product Finance decision.

### Current Blocker

P0 Product Finance decision required before patch. Backend can implement either a separate participant-control surface or a redefinition of existing headline totals, but those are different financial products and must not be chosen implicitly in code.

## Technical SEO / PWA Infra Check 2026-05-27

Role: Backend / Infra SEO Engineer
Task: technical SEO / production infra check for the PWA before production upload.
Status: check completed; production deploy remains NO-GO under `docs/AI_TEAM/19_PRODUCTION_GO_NO_GO_2026-05-27.md`.

### Inputs Read

- `public/index.php`
- `public/app.php`
- `public/robots.txt`
- `public/sitemap.xml`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `public/api.php`
- `docs/AI_TEAM/19_PRODUCTION_GO_NO_GO_2026-05-27.md`
- additional context: `.htaccess`, `public/assets/app.js`, `app/auth.php`

### Findings

- Robots boundary exists: `robots.txt` disallows `/app.php`, `/api.php`, and `/storage/`; sitemap lists only `https://finance.brkovic.ltd/`.
- App noindex exists: `public/app.php` has `<meta name="robots" content="noindex,nofollow">`.
- App/API/storage need production hardening beyond robots: add/verify `X-Robots-Tag: noindex, nofollow, noarchive` for `/app.php`, `/api.php`, and any storage response, and keep `/storage/` behind 403/auth or outside public docroot.
- Canonical/sitemap is consistent: index canonical, OG URL, robots sitemap URL, and sitemap `loc` all point to `https://finance.brkovic.ltd/`; no private app/API/storage URLs are in sitemap.
- Service worker SEO cache risk is currently low: `public/service-worker.js` has no `fetch` handler, so it does not serve stale HTML/robots/sitemap/manifest/API responses.
- Service worker rollback still needs explicit smoke: `skipWaiting()` and `clients.claim()` activate new workers quickly and delete old `findesk-*` caches.
- Production headers must be verified/configured for correct MIME and cache policy:
  - `/manifest.webmanifest`: `application/manifest+json; charset=utf-8`;
  - `/robots.txt`: `text/plain; charset=utf-8`;
  - `/sitemap.xml`: `application/xml; charset=utf-8` or `text/xml; charset=utf-8`;
  - `/service-worker.js`: JavaScript content type plus no-cache/revalidate;
  - `/app.php`: current PHP no-store/no-cache headers should be preserved and `X-Robots-Tag` added/verified;
  - `/api.php`: JSON content type plus `Cache-Control: no-store` and `X-Robots-Tag`;
  - `/`: current PHP no-store/no-cache headers should be preserved;
  - versioned assets can have longer cache only with rollout/rollback discipline.
- Search Console / Bing Webmaster verification is a later owner task without credentials in docs. Use DNS, static verification file, or explicit meta-tag deploy only after authorization.
- Analytics boundary currently passes: no analytics/measurement snippets were found in inspected public files; finance data is sent in POST bodies/FormData, not finance query strings.
- Analytics caveat: invite URLs use `/app.php?invite=<token>`, so any future analytics must strip query strings/full URLs and must not collect invite tokens, emails, auth codes, finance amounts, report names, proof filenames, storage URLs, or API bodies.
- SEO/PWA package finding: the selected package in `19_PRODUCTION_GO_NO_GO_2026-05-27.md` includes `public/index.php`, `public/app.php`, and `public/service-worker.js`, but does not list `public/robots.txt`, `public/sitemap.xml`, or `public/manifest.webmanifest`; deploy owner must add them or verify production already matches.
- SEO changes do not clear or weaken production NO-GO. DB backup, storage backup, schema preflight, runtime SQL decision, rollback owner, and smoke owner remain blockers before any production upload.

### Local Verification

- `node` JSON parse passed for `public/manifest.webmanifest`.
- `xmllint --noout public/sitemap.xml` passed.
- `node --check public/service-worker.js` passed.
- `node --check public/assets/app.js` passed.

### Output

- Detailed check written to `docs/AI_TEAM/22_TECHNICAL_SEO_INFRA_CHECK.md`.
- No production action was executed.
- No application code was changed.
- No credentials were documented.

## Production Deploy Readiness Plan 2026-05-27

Role: Backend Data Engineer
Task: Production deploy readiness plan for 100% MVP.
Status: Production deploy readiness BLOCKED. Backend/API product readiness remains PASS, but production upload is blocked until Project Director/deploy owner selects the exact dirty-tree deploy package, confirms/applies the runtime DB migration, completes backup/rollback planning, and assigns production smoke.

### Inputs Read

- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `git status --short`

### Output

- Full deploy-readiness handoff written to `docs/AI_TEAM/14_PRODUCTION_DEPLOY_READINESS.md`.
- No production action was executed.
- No database change was made.
- No application code was changed.
- No credentials were written into docs.

### Backend/API/Runtime SQL Deploy Candidates

Candidate list for deploy-owner review, not approval to upload:

- `public/api.php`: MVP API routing; current file also requires untracked `app/ai.php`, so partial deploy without dependency closure can break all API traffic.
- `app/auth.php`: loaded by API; dirty diff adds audit helpers and local-dev code-return behavior guarded by local host/app URL detection; must be reviewed as auth surface before production.
- `app/groups.php`: loaded by API; dirty diff adds audit write on member access update.
- `app/on_the_go.php`: Field Combat durable draft/recovery/proof state/idempotency/session/card runtime; depends on the On the Go runtime schema.
- `app/ledger.php`: current/historical separation, finalization, final report detail/export/package, package proof download, and open carryover runtime.
- `app/advances.php`: accountable/advance state used by package and residual MVP flow; depends on On the Go tape/session state and audit helpers.
- `app/ai.php`: untracked and non-MVP/Advanced surface, but required by current `public/api.php`; either deploy it deliberately as a dependency or use a reviewed API file that does not require it.
- `deploy/on_the_go_sessions_runtime.sql`: required runtime migration to apply or prove already applied before PHP upload.
- `scripts/local-smoke.php`: verification/support only, not production web runtime unless explicitly approved.

Frontend/public files remain outside Backend/Data ownership, but `public/app.php`, `public/assets/app.js`, `public/assets/app.css`, and `public/assets/i18n.js` are part of the accepted MVP runtime path and need Frontend/Project Director deploy selection. Modified icons, brand assets, `public/index.php`, and `public/service-worker.js` must not be included blindly.

### Migration Checklist Summary

P0 before PHP upload:

- Confirm production DB engine/version.
- Verify compatibility with `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS` in `deploy/on_the_go_sessions_runtime.sql`.
- If syntax is unsupported, stop and produce engine-compatible idempotent SQL before upload.
- Apply or prove existing:
  - `on_the_go_sessions`;
  - `on_the_go_captures.tape_id`;
  - `on_the_go_captures.session_id`;
  - `on_the_go_tapes.group_id`;
  - `on_the_go_tapes.advance_id`;
  - `on_the_go_tapes.stream_type`;
  - `on_the_go_tapes.submitted_at`;
  - `on_the_go_tapes.actual_remaining`;
  - `on_the_go_tapes.difference_amount`;
  - `on_the_go_field_drafts`;
  - `on_the_go_field_sync_ops`;
  - `on_the_go_upload_states`.
- Verify previous foundation schema is present for auth/audit, groups/access levels, ledger, On the Go foundation, advances, messages, and Business Desk if those surfaces are deployed/smoked.

### Backup/Rollback Summary

P0 before upload:

- Back up selected production files.
- Back up production database.
- Back up uploaded-document/proof storage, including On the Go proofs, ledger files, and group final report proof copies.
- Record exact selected deploy files and approver.
- Name rollback owner.
- Prefer file rollback first if smoke fails.
- Treat database restore as separate high-risk rollback because it can erase production writes after the backup; use only with explicit Project Director/CEO approval.

### Production Smoke Summary

Minimum backend/API smoke after approved upload:

- app load and `current_user`/session check;
- login flow without production dev-code exposure;
- Field Combat draft save/recover;
- proof state begin/fail/list and upload retry;
- idempotent `on_the_go_signed_sync` with no duplicate money rows;
- cash stream vs card stream separation;
- current export through `ledger_group_google_sheet` and `ledger_group_excel`;
- closed report list/detail/package/export;
- package proof download through `ledger_group_final_report_proof_download`;
- historical/current separation with `1000 / 600 / 400` or approved equivalent;
- advance/accountable state represented in package;
- group message send/list/unread/mark-read group scope;
- proforma create/list/open/print does not mutate ledger formulas.

### Dirty-Tree Risks

Do not blindly deploy:

- local/test/reset/support artifacts: `public/reset-local.php`, `scripts/start-local.sh`, `test-results/`;
- unreviewed advanced/AI surface: `app/ai.php`, unless deliberately selected as current API dependency;
- local docs/work notes under `docs/AI_TEAM/` and other untracked docs;
- modified service worker/icons/brand/public index without Frontend/Project Director approval;
- local storage backups or database dumps.

### Final Classification

- Backend/API business-MVP product readiness: PASS based on accepted prior evidence.
- Production deploy readiness: BLOCKED by deployment controls.
- Next owner: Project Director for deploy mode/file-list decision, then Database Migration Owner/Deploy Owner and QA Release Engineer.

## Final Business MVP Backend/API Readiness Risk Check 2026-05-27

Role: Backend Data Engineer
Task: Final Business MVP backend/API production-readiness risk check after QA residual surface PASS.
Status: Business-MVP product readiness PASS for backend/API; production deploy readiness remains BLOCKED by deployment controls, not by a confirmed product/API behavior bug.

### Inputs Read

- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- Runtime surfaces reviewed: `app/on_the_go.php`, `app/ledger.php`, `public/api.php`, `deploy/on_the_go_sessions_runtime.sql`, `scripts/local-smoke.php`; message/proforma backend surfaces were also checked where residual QA depended on them.

### Product Readiness Verdict

No known backend/API P0 remains for business-MVP product readiness based on the accepted evidence available in this tree.

Evidence:

- Director final-readiness doc marks Field Combat no-data-loss and Closed group report package as approved gates, with residual surface QA as the remaining business-MVP product check.
- QA residual surface PASS is recorded in `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`, section `Verification 2026-05-27 - Business MVP Residual Surface QA`, run `20260527968710`, using accepted closed package anchor `group_id=222`, `report_id=454`.
- Residual QA passed:
  - group message send/list/unread/mark-read and non-member group-scope denial;
  - report-context message references inside `Закрытый групповой отчет`, with general group discussion explicitly marked unlinked to `report_id`;
  - Business Desk company/client/proforma create/list/open/print, with `ledger_report` unchanged before/after proforma operations;
  - Travel / Trip with Friends visible as staged and Advanced reachable;
  - mobile/tablet/desktop navigation reachability across On the Go, report review, closed report package, group messages, proforma, Travel staging, and Advanced.
- Backend/API route check confirms required actions are exposed through `public/api.php`: `ledger_group_final_report_package`, `ledger_group_final_report_proof_download`, `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, `ledger_group_final_report_excel`, `ledger_group_open_received_funds`, `message_send/list/unread/mark_read`, and `proforma_create/list/get`.
- `app/ledger.php` stores new finalizations as immutable `audit_log.details.report_snapshot` plus `report_package`; package proof download is authorized through final report package access, not original Live Report file ownership.
- `app/ledger.php` current-period export path remains separate from historical package/export: after finalization, `ql_ledger_group_open_received_funds()` uses finalization carryover plus post-finalization income and open included Live Report aggregates.
- `app/on_the_go.php` field-capture paths keep draft recovery by `client_draft_id`, idempotency by `client_operation_id`, proof retry state by `client_upload_id`, and stream-specific cash/card parsing. QA accepted the no-data-loss and duplicate-money proof retry checks before this pass.
- Business Desk/proforma backend (`app/business.php`) writes to company/client/proforma tables and does not call ledger mutation APIs; QA confirmed group `222` report formulas stayed unchanged.

Known product limitations remain classified as non-blocking unless Product/Director upgrades them:

- Legacy finalizations without `report_package` return `historical_package_missing`; accepted product path uses new package finalizations.
- Package-wide downloadable file export beyond browser print/PDF is still a product decision/P1 unless raised.
- First-class report-linked message schema is not present; MVP package uses audit-derived report-context refs and marks general group messages as unlinked.

### Production Deploy Readiness Verdict

Production deploy readiness is BLOCKED before upload/smoke. These are deploy controls and environment risks, not a confirmed backend/API product P0.

P0 before production upload/smoke:

- Dirty tree deploy selection is unresolved. `HEAD=72b38e6`, `origin/main=72b38e6`, but the worktree contains broad modified/untracked runtime files. Do not upload the whole tree blindly.
- Deployment owner must choose a file list/mode from `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md` and explicitly exclude local/reset/test artifacts such as `public/reset-local.php`, `scripts/start-local.sh`, and `test-results/` unless approved.
- Production database migration must be verified before deploying the PHP that assumes it. `app/on_the_go.php` runtime code depends on `on_the_go_sessions`, `on_the_go_captures.session_id`, and `on_the_go_tapes.stream_type`. The application creates the newer field draft/sync/upload state tables at runtime, but it does not create every core session table/column dependency.
- `deploy/on_the_go_sessions_runtime.sql` must be applied or proven already applied on production. It uses `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS`; the deploy owner must verify the production MySQL/MariaDB engine supports that syntax or assign an engine-compatible idempotent migration before upload.
- Production backup/rollback plan is still required before upload.
- Production smoke is still required after upload. Minimum smoke remains: app load, current user/session, On the Go field capture visibility, current-period export, closed final report list/detail/export/package/proof access, and the `1000 -> 600 -> 400` historical/current separation.

### Verification Run In This Pass

- `git diff --check` passed for the current tree.
- `git diff --check -- app/on_the_go.php app/ledger.php public/api.php deploy/on_the_go_sessions_runtime.sql scripts/local-smoke.php` passed.
- `node --check public/assets/app.js` passed.
- Local HTTP server responded: `GET http://127.0.0.1:18889/app.php` returned `HTTP/1.1 200 OK` with `X-Powered-By: PHP/8.3.6`.
- Local unauthenticated API health responded: `GET /api.php?action=current_user` returned `{"ok":true,"user":null}`.
- CLI PHP is unavailable in this shell: `php -v` returned `php: command not found`; therefore `php scripts/local-smoke.php http://127.0.0.1:18889` is environment-blocked in this pass.

### Runtime File Risk Summary

- `app/on_the_go.php`: no new product-readiness P0 found in the reviewed Field Combat / draft / idempotency / proof-state / cash-card stream paths. Production risk is schema dependency on the session migration.
- `app/ledger.php`: no new product-readiness P0 found in final package, final proof download, current/historical separation, or open carryover path. Production risk is upload selection plus smoke verification.
- `public/api.php`: required backend/API actions are routable. Production risk is deploying a partial runtime set without matching dependencies.
- `deploy/on_the_go_sessions_runtime.sql`: production deploy P0 until engine compatibility and successful migration/application are confirmed.
- `scripts/local-smoke.php`: includes focused checks for durable draft/proof/idempotent sync and final package/open carryover separation, but could not be run here because CLI PHP is missing.

### Final Classification

- Business-MVP product readiness, backend/API: PASS.
- Production deploy readiness: BLOCKED until Project Director/deploy owner resolves deploy package selection, database migration compatibility/application, backup/rollback, and production smoke.
- No backend code patch was made in this pass.

## Open Findings

- Direct CLI checks may differ from web session checks where helper functions require logged-in user context.
- Export must support two truths: historical finalized snapshot and current open-period snapshot.
- Archive/list filters must not accidentally hide employee advance-linked live cards.

## Closed Group Report Archive Package Implementation 2026-05-27

Status: implemented for new finalizations. No financial formulas were changed.

Baseline:

- `HEAD=72b38e6`, `origin/main=72b38e6`.
- Working tree was already dirty with broad local work; no reset/checkout/clean was performed.
- CLI `php` is unavailable in this shell, so `php -l` and `php scripts/local-smoke.php http://127.0.0.1:18889` are environment-blocked.
- HTTP server check passed: `GET /api.php?action=current_user` returned HTTP 200 after the patch.
- `git diff --check -- app/ledger.php public/api.php scripts/local-smoke.php docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md` passed.

Implemented backend/API contract:

- New API action: `ledger_group_final_report_package`.
- Input: `report_id` or `id`.
- Output shape:
  - `ok`
  - `package_type=group_final_report`
  - `report_id`
  - `report`
  - `package`
- New proof download action: `ledger_group_final_report_proof_download`.
- Download input: `report_id` and `proof_id`.
- Proof download is authorized by final report package access (`can_view_group_reports`), not by original Live Report file ownership.

Immutable storage source:

- New finalizations now store `report_package` in the same `audit_log.details` JSON row as `action='ledger_group_report_finalized'`.
- `report_package.package_version=1`.
- `ledger_group_finalize_report` inserts the finalization audit row, receives `report_id`, builds the package by that identity, and updates the same audit row with the immutable package.
- Later reads of `ledger_group_final_report_package` read `audit_log.details.report_package`; they do not reconstruct mutable current data.
- Old finalizations that have `report_snapshot` but no `report_package` return `historical_package_missing` from the package endpoint.

Package contents implemented:

- `group`: group id/name.
- `finalization`: `report_id`, status `closed`, finalization time, finalizer identity, snapshot creation time.
- `summary`: received money, physical cash spent, card/noncash spent, admin cash left, accountable money left, returned cash, discrepancy, carryover, cash balance, total balance.
- `participants`: embedded immutable participant report identities such as `participant-report-<report_id>-<tape_id>`, participant identity, reviewer/finalizer, stream type, submitted/accepted/finalized times, card/cash/accountable proof summary, capture ids, proof ids, proof status.
- `captures`: included Live Report capture rows with amount, capture type, cash/card effect, participant id, proof ids, proof status, timestamps, and comments/description.
- `money_rows`: prepared group report rows with source refs for ledger entries, On the Go captures, carryover rows, cash/card/accountable effects, proof ids, and proof status.
- `proofs`: copied immutable proof metadata under `storage/documents/group-final-reports/<year>/report-<report_id>/...` with reviewer download URLs.
- `accountable`: frozen advance/accountable state at finalization time, including accepted spend, accepted cash/card split, returned cash, open remaining cash, discrepancy, carryover responsibility, items by advance, and by-participant totals.
- `messages`: report-context review events derived from audit refs plus frozen general group message references before finalization. Because `group_messages` has no `report_id/tape_id/capture_id/advance_id`, the package marks general messages as `general_group_discussion_unlinked`.
- `audit_refs`: finalization, included card, return/include/review, advance, and proof-relevant audit references without requiring a separate journal screen.
- `exports`: action metadata binding package identity to existing final report export actions.

Proof access implementation:

- Original Live Report proofs remain in `on_the_go_files`.
- During finalization, package proofs are copied into report-owned storage and referenced by package proof ids.
- Authorized group report viewers can download package proof copies through `ledger_group_final_report_proof_download`.
- This removes the previous P0 blocker where `on_the_go_file_list` / `on_the_go_file_download` depended on original file owner.

Current/historical behavior preserved:

- Existing current export actions remain `ledger_group_google_sheet` and `ledger_group_excel`.
- Existing historical summary export actions remain `ledger_group_final_report_google_sheet` and `ledger_group_final_report_excel`.
- Current open-period carryover/export formulas were not changed.
- Card/noncash rows keep `card_effect` and do not reduce physical cash.
- Employee issued/open remaining money is represented as accountable responsibility/carryover, not as expense.

Smoke / fixture:

- `scripts/local-smoke.php` was extended with:
  - multipart proof upload helper;
  - authenticated proof download helper;
  - final report package read by `report_id`;
  - package participants/captures/proofs/audit/accountable/messages checks;
  - package proof download check;
  - package immutability check after new current-period activity.
- CLI smoke could not be executed because CLI `php` is unavailable.
- HTTP/API fixture was executed through the local server and passed.
- Evidence:
  - `group_id=221`
  - `report_id=441`
  - `tape_id=269`
  - `capture_id=182`
  - `proof_id=proof-441-on_the_go_capture-12`
  - `advance_id=65`
- Fixture verified:
  - package read by `report_id`;
  - participant report snapshot exists;
  - captures exist;
  - proof index exists;
  - authorized reviewer proof download returned HTTP 200 and file body;
  - accountable accepted spend exists: `40`;
  - accountable open remaining/carryover responsibility exists: `60`;
  - summary preserved `received_money=1000`, `physical_cash_spent=640`, `accountable_money_left=60`;
  - adding later current income did not mutate package summary.

Known limitation / follow-up:

- First-class report-context messages are still audit-derived because `group_messages` has only group scope. A schema/API follow-up is still needed if Product wants users to create messages directly linked to `report_id`, `tape_id`, `capture_id`, or `advance_id`.
- Full package print/export is not implemented as a new file export. The package source exposes `exports` metadata and existing summary export actions; Frontend/UX can open the complete package, while Product/Frontend should decide whether a package-wide printable/export file is required as a separate P0/P1 after this source lands.
- Old finalizations remain explicitly limited with `historical_package_missing`; they are not reconstructed.

## Business MVP Group Report / Archive / Common Pot Backend Trace 2026-05-26

Status: blocked for the full business-MVP group report/archive package. No backend/API/formula/UX patch was made in this trace.

Baseline:

- `HEAD=72b38e6`, `origin/main=72b38e6`.
- Working tree is dirty with broad pre-existing local work; no reset/checkout/clean was performed.
- CLI `php` is unavailable in this shell, so CLI smoke is environment-blocked for this pass.
- Local HTTP server is reachable: `curl -I --max-time 3 http://127.0.0.1:18889` returned `HTTP/1.1 200 OK`; `GET /api.php?action=current_user` returned HTTP 200 with `{"ok":true,"user":null}`.

Direct acceptance answers:

- Can several participant reports currently be consolidated into one group report? Partially yes. `ledger_group_finalize_report` can collect multiple included non-advance Live Report cards from a group and store one group final report snapshot. This is not yet a complete business package because advance-linked reports are excluded as cards, pure-ledger periods without included cards do not create a final report, and participant reports do not have their own immutable finalized report identities.
- Can that group report be saved as immutable historical truth? Yes for new group finalizations at prepared-row/totals level. The immutable source is `audit_log.details.report_snapshot` for `action='ledger_group_report_finalized'`. It does not freeze an expanded package of participant card details, proof file ids/download links, messages, and advance/accountable report context.
- Can that group report be printed/exported? Yes for the group snapshot by `report_id` through `ledger_group_final_report_google_sheet` and `ledger_group_final_report_excel`. It exports the prepared group report rows and totals, not a full archive bundle with all linked participant reports and proofs.
- Can archive reopen the closed group report and all linked participant reports/proofs? No. The backend has separate pieces: group final report list/detail/export, archived Live Report card list/detail, file storage, and journal export. There is no single archive/package endpoint that opens `final_report_id -> card_ids -> captures -> proof files -> advances/accountable context -> messages`. In addition, `on_the_go_file_list` and `on_the_go_file_download` are owner-only, so a manager/admin archive reader can see card rows/file counts but cannot reliably list/download another participant's original Live Report proofs through those endpoints.
- Does the common pot preserve who holds/spent money? Partially. Snapshot rows keep owner, email, member totals, and `accountable_rows`; open balances keep accountable cash left/spent by employee. But the final group report does not preserve a full participant responsibility package with each participant report, proof links, and advance/open accountable state frozen as of finalization.
- Does card spending stay out of physical cash? Yes in the checked backend paths. Card stream forces `cash_received=0`, card summaries set `cash_left=0` and `cash_delta=0`, `noncash_out` maps to `money_type='noncash'`, and group snapshots keep `cash_expense` separate from `noncash_expense`.
- Are group messages available in group/report context or only as a separate module? Messages are group-scoped but separate. `group_messages` has `group_id`, sender, text, type, read state, and timestamps; it has no `report_id`, `tape_id`, `capture_id`, or `advance_id` context. Existing send/list/unread behavior can support a group thread, but it cannot bind a missing-proof or return/acceptance discussion to a closed report archive.
- What is missing for business MVP? See P0/P1/P2 list below.

Backend/API source map:

- Participant Live Reports: `on_the_go_tapes` + `on_the_go_captures`; APIs `on_the_go_card_list`, `on_the_go_card_detail`, `on_the_go_card_submit`, `on_the_go_card_include`, `on_the_go_card_uninclude`, `on_the_go_card_archive_completed`, `on_the_go_file_list`, `on_the_go_file_download`.
- Included reports entering group finance: `ql_ledger_virtual_on_the_go_entries()` reads non-advance tapes with `reportable=1`; `ql_on_the_go_card_include()` sets captures to `reportable=1` and makes the card included.
- Consolidated group report source: `ql_ledger_group_export_rows()` merges real `ledger_entries` with included virtual Live Report captures, then `ql_ledger_group_rows_snapshot()` calculates rows, totals, member rows, article rows, cash/card split, and accountable rows.
- Group finalization identity: `ledger_group_finalize_report` writes `audit_log.action='ledger_group_report_finalized'` and returns `report_id`; new rows include `snapshot_type='group_final_report'`, `snapshot_version=1`, `card_ids`, carryover fields, and `report_snapshot`.
- Historical group final report access: `ledger_group_final_report_list`, `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, `ledger_group_final_report_excel` read by `report_id`. Old finalizations without `report_snapshot` return `historical_snapshot_missing`.
- Current open-period export: `ledger_group_google_sheet` / `ledger_group_excel` still call `ql_ledger_group_export_snapshot()`. If a finalization exists, it switches to open-period snapshot from carryover, post-finalization income, and current included Live Report aggregates.
- Archive pieces: closed group reports are listed by `ledger_group_final_report_list`; archived cards are listed by `on_the_go_card_list` with `archived_only=1`; card details are opened by `on_the_go_card_detail`; proof file metadata/download is owner-only through `on_the_go_file_list` / `on_the_go_file_download`; append-only journal export is admin-only through `ql_on_the_go_journal_export()`.
- Employee/accountable money: `cash_advances`, linked `on_the_go_tapes.advance_id`, APIs `advance_create`, `advance_list`, `advance_detail`, `advance_submit`, `advance_accept`, `advance_return`, `advance_unaccept`, `advance_return_cash`, `advance_cancel`.
- Group messages: `message_send`, `message_list`, `message_unread`, `message_mark_read`; schema is group-level only.
- Group scope: most finance/list endpoints default to personal or assigned scope when `group_id` is missing; group final report/export/finalization endpoints require explicit valid `group_id` or `report_id`.

Detailed trace notes:

- `ledger_group_finalize_report` finalizes only non-advance included cards: `t.group_id=?`, `t.status <> 'archived'`, `t.archived_at IS NULL`, `(t.advance_id IS NULL OR t.advance_id = 0)`, and at least one capture with `reportable=1`. Accepted advances are represented as real `ledger_entries` after `advance_accept`, but advance-linked cards are not part of `card_ids`.
- `ledger_group_finalize_report` returns `finalized=0` and does not create `ledger_group_report_finalized` when there are no included cards. That blocks a pure-ledger group period from being closed into an immutable report/carryover by the current finalization action.
- `ql_ledger_group_rows_snapshot` preserves owner/member totals and cash/card totals, but historical detail/export returns the snapshot object only; it does not expand `card_ids` into participant card details or proof files.
- `ql_ledger_group_open_received_funds` and current export use latest finalization carryover plus current post-finalization income and current included Live Report aggregate. This preserves current-period truth but is not an archive package.
- `on_the_go_card_list archived_only=1` can list finalized/completed cards because finalization sets `archived_at` and leaves `status <> 'archived'`. Deleted draft cards have `status='archived'` and are intentionally hidden. Non-admin archived group visibility is owner-limited, while full group archive visibility is effectively advanced/admin.
- `on_the_go_card_detail` can show card rows to report viewers/managers, but `on_the_go_file_list` and `on_the_go_file_download` call owner-only lookup by `capture.user_id = current_user_id`. This is a P0 evidence gap for a reviewer opening another participant's archived proof.
- `ql_on_the_go_journal_export` can export group card/capture audit rows for admins, but it is a separate journal export and not tied to one final group report identity.
- `advance_accept` converts accepted employee advance captures into `ledger_entries` and copies proof files into `entry_files`. Open advances remain in accountable totals. This supports money accountability, but the final group report package does not explicitly freeze the accepted/open advance evidence set by final report identity.
- `ql_ledger_balance` adds submitted/included non-advance Live Reports and open accountable totals into group summary while preserving `accountable_cash_left_open`, `accountable_cash_spent_open`, `accountable_card_spent_open`, `cash_expense`, and `noncash_expense`.
- `ql_ledger_report`, `ql_ledger_balance`, `ql_ledger_list`, `advance_list`, and Live Report list APIs use personal/assigned scope without `group_id`. Business MVP group screens must pass `group_id` explicitly and must not treat a personal default as group truth.

Business MVP missing work:

P0:

- Add a unified closed group report package endpoint by `report_id` that returns immutable group snapshot plus expanded linked participant cards, captures, proof file metadata/download URLs visible to authorized reviewers, accepted advances, open accountable carryover state, and audit references.
- Add immutable finalized participant report identity/snapshot for each included Live Report card, or embed participant report snapshots inside the group final report snapshot. Current `submitted/included/card_id` state is not enough for a closed participant report artifact.
- Fix proof access for archive/reviewer scope: authorized group reviewers must be able to list/download proofs attached to participant reports included in a closed group report without becoming the file owner.
- Decide and implement how employee/accountable advance reports are represented inside the closed group report package: accepted advance ledger rows, proof copies, open accountable cash left, returned/discrepancy state, and carryover responsibility must be visible by participant.
- Add an archive open/list contract that links closed group reports to all linked participant reports/proofs and does not require the UI to manually stitch `ledger_group_final_report_detail`, `on_the_go_card_detail`, file endpoints, advance endpoints, and journal export.
- Add QA fixture/smoke for multi-participant business MVP: group income/common pot, at least two participant reports, cash expense, card expense, proof files, one employee/accountable advance path, final group report, export, archive reopen, and new current-period activity after finalization.

P1:

- Decide whether managers, not only advanced/admin users, must see all archived employee cards in group archive.
- Update or deprecate `on_the_go_report_list`; it reads legacy `on_the_go_report_submitted` audit rows and misses the newer `on_the_go_card_submitted`, `on_the_go_card_included`, and `ledger_group_report_finalized` flow.
- Add deterministic finalization cutoff identity for same-second post-finalization ledger rows instead of relying only on `le.created_at > finalized_at`.
- Add a pure-ledger finalization path if a group period with ledger income/expenses but no included Live Report cards must be closable.
- Decide whether report-context messages need first-class links to `report_id`, `tape_id`, `capture_id`, or `advance_id`; current messages are group-only.

P2:

- Connect append-only journal export to final report archive package if the business wants a downloadable audit bundle.
- Plan travel equalization/business-desk reuse of proof, participant, group pot, and archive concepts separately from operational group report formulas.
- Improve historical support for old finalizations without `report_snapshot`; current behavior intentionally returns `historical_snapshot_missing`.

## Carryover / Export / Archive Trace 2026-05-26

Baseline:

- `HEAD=72b38e6`, `origin/main=72b38e6`.
- Working tree is dirty; no reset/checkout/clean was performed.
- `php scripts/local-smoke.php http://127.0.0.1:18889` is environment-blocked because CLI `php` is unavailable in this shell.
- Server reachability check passed: `curl -I --max-time 3 http://127.0.0.1:18889` returned `HTTP/1.1 200 OK` and `X-Powered-By: PHP/8.3.6`.

### Source Map

- API routing: `public/api.php` exposes `ledger_group_finalize_report`, `ledger_group_open_received_funds`, `ledger_group_excel`, `ledger_group_google_sheet`, `ledger_balance`, `ledger_report`, `on_the_go_card_list`, `on_the_go_card_detail`, and advance endpoints.
- Finalization source: `app/ledger.php::ql_ledger_group_finalize_report`.
- Open-period carryover source: `app/ledger.php::ql_ledger_group_open_received_funds`.
- Export source selection: `app/ledger.php::ql_ledger_group_export_snapshot`, used by both Excel and Google Sheet export paths.
- Live report/card truth: `app/on_the_go.php` tables `on_the_go_tapes`, `on_the_go_captures`, `on_the_go_sessions`.
- Employee/accountable truth: `app/advances.php` table `cash_advances` plus linked `on_the_go_tapes.advance_id`.
- Finalization metadata: `audit_log.action='ledger_group_report_finalized'`.

### Scenario: EUR 1000 income -> EUR 600 cash expense -> EUR 400 carryover

Expected simple admin-cash path is supported for the open-period/export path:

1. Before first finalization, `ql_ledger_group_export_snapshot()` has no previous `ledger_group_report_finalized` audit row, so it uses all active group ledger rows plus virtual included non-advance Live Report captures.
2. A EUR 1000 cash ledger income plus EUR 600 included cash Live Report expense produces snapshot totals of `income=1000`, `expense=600`, `cash_expense=600`, `cash_balance=400`, and, with no accountable employee cash, `admin_cash_left=400`.
3. `ql_ledger_group_finalize_report()` writes `audit_log.action='ledger_group_report_finalized'` with `carryover_admin_cash_left`, `carryover_employee_cash_left`, `carryover_cash_balance`, `report_balance`, and finalized `card_ids`; it also writes one audit row per finalized card.
4. After that audit row exists, `ql_ledger_group_open_received_funds()` builds a synthetic readonly cash income row `Переходящий остаток` from `audit_log.details.carryover_admin_cash_left`.
5. The same open endpoint filters current ledger income with `le.created_at > finalized_at`, so the old EUR 1000 income is not treated as new current income in this path.
6. With no new period income/expense, the open-period payload should expose carryover EUR 400 and no old EUR 1000 current income.

Blocker/risk:

- Historical finalized report rows are not materialized as an immutable report snapshot. Old ledger income stays in `ledger_entries`; finalized Live Report evidence stays in `on_the_go_tapes/on_the_go_captures` with `archived_at`; finalization totals and `card_ids` stay in `audit_log.details`. That preserves raw evidence, but the backend does not expose a dedicated historical finalized report/export endpoint that can return the exact old prepared rows after the main export switches to the open-period source.

### Final Report Fixation

- `ledger_group_finalize_report` requires a valid `group_id` and `can_write_group_ledger`.
- It takes the export snapshot before archiving cards.
- It finalizes only non-advance Live Report cards: `t.group_id=?`, `t.status <> 'archived'`, `t.archived_at IS NULL`, `(t.advance_id IS NULL OR t.advance_id = 0)`, and at least one capture with `reportable=1` and `review_status <> 'archived'`.
- It archives those cards by setting `on_the_go_tapes.archived_at`; it does not set `status='archived'`, so the cards remain available to archive/history APIs.
- If there are no included cards, it returns `finalized=0` and does not write a `ledger_group_report_finalized` audit row. A pure-ledger period cannot currently be closed into a new carryover without at least one included Live Report card.

### Historical Report Preservation

- Preserved data sources:
  - ledger income/expense rows remain in `ledger_entries` unless explicitly deleted;
  - finalized card captures remain in `on_the_go_captures` unless explicitly archived/deleted;
  - finalized cards get `archived_at` but keep `status <> 'archived'`;
  - finalization audit stores card ids and carryover totals.
- Not preserved as a first-class object:
  - no `finalized_reports` / `report_snapshots` table was found;
  - no API endpoint was found that selects a previous finalization and returns the historical prepared rows/export for that specific final report.
- Generic `ledger_list`, `ledger_balance`, `ledger_report`, and `ledger_work_position` are cumulative/current ledger views and are not finalization-aware in the same way as `ledger_group_open_received_funds`. If the UI uses them as the open-period truth after finalization, old income/expense can still appear without carryover context.

### Open-Period Carryover Source

- Primary source: latest `audit_log` row where `action='ledger_group_report_finalized'`, `entity_type='group'`, and `entity_id=group_id`.
- Admin carryover source: `details.carryover_admin_cash_left`.
- Fallback if old audit details are missing: `ql_ledger_balance(group_id).summary.available_cash_balance - postFinalCashIncome`.
- Employee carryover rows are currently recomputed from `ql_ledger_group_accountable_left_rows(group_id)` instead of being frozen from `details.carryover_employee_cash_left`. This keeps current accountable cash visible, but historical employee carryover can drift if advance state changes after finalization.
- Current period income source: post-final `ledger_entries` with `entry_type='income'`, excluding notes starting with `From On the Go` and `From advance #`.
- Current open included live expense source: non-archived, non-advance Live Report cards with reportable captures; cash and card totals are aggregated into `open_period.live_included`.

### Export Source Selection

- `ledger_group_excel` and `ledger_group_google_sheet` both call `ql_ledger_group_export_snapshot()`.
- If any `ledger_group_report_finalized` audit row exists for the group, export switches to `ql_ledger_group_open_export_snapshot()`.
- The open export converts carryover rows into synthetic cash income rows, adds post-final ledger income rows, and adds aggregate current Live Report cash/card expense rows.
- Therefore, after the EUR 1000 -> EUR 600 finalization, the current export should show EUR 400 as `Переходящий остаток`, not the old EUR 1000 as new current income.
- Same blocker as above: this source selection is correct for current open-period export, but it means the same export actions no longer export the old finalized report. Historical export needs a separate source or explicit mode.

### Archive Listing And Filters

- `on_the_go_card_list` defaults to active workspace cards by adding `t.archived_at IS NULL`.
- `archived_only=1` adds `t.archived_at IS NOT NULL`.
- The query always keeps `t.status <> 'archived'`; cards deleted through `on_the_go_card_delete` are set to `status='archived'` and disappear from this archive list. Finalized/completed cards archived with `archived_at` remain listable.
- For group archive listing, non-admin archived-only requests are forced to owner scope (`ownArchiveOnly`). Full group archive visibility is effectively admin/advanced only. Managers with report permissions can moderate current cards but may not see all archived employee cards through the list endpoint.
- `exclude_advances=1` removes employee advance-linked cards. Default is false, so API can list advance-linked cards unless caller filters them out.
- `on_the_go_report_list` is legacy: it reads only `audit_log.action='on_the_go_report_submitted'`. It does not list newer `on_the_go_card_submitted`, `on_the_go_card_included`, or `ledger_group_report_finalized` records, so it can miss current card-workflow reports.

### Employee-Linked Live Reports

- `advance_create` creates `cash_advances` plus a linked cash `on_the_go_tapes` row with `advance_id`.
- General group live-report paths intentionally exclude advance-linked tapes:
  - virtual ledger rows exclude `t.advance_id`;
  - group finalization excludes `t.advance_id`;
  - open-period live included totals exclude `t.advance_id`;
  - submitted card totals exclude `t.advance_id`.
- Accepted employee advance reports are promoted to real `ledger_entries` by `advance_accept`; capture type `cash_out` becomes `money_type='cash'`, and `noncash_out` becomes `money_type='noncash'`.
- Open accountable cash is calculated from `cash_advances` in statuses `issued`, `submitted`, `returned`, `discrepancy`; accepted advances leave the open accountable pool and their accepted expenses live in `ledger_entries`.

### Card Stream Zero Physical-Cash Effect

- Card stream type allows only `noncash_out`.
- Card tape creation forces `cash_received=0`.
- Signed sync forces `cash_received=0` for card streams and skips `+` rows.
- Card summary forces `extra_cash_in=0`, `cash_out=0`, `cash_left=0`, and `cash_delta=0`; only `card_out/card_delta` changes.
- Export row snapshot keeps `cash_change=0` for `money_type='noncash'` expenses while increasing `noncash_expense`.
- Advance summaries also keep `card_out` separate from `cash_left`; card spend does not reduce employee physical cash left.

### Group Scope Defaults

- Backend financial endpoints do not auto-select a group. Missing or invalid `group_id` means personal/assigned scope for `ledger_list`, `ledger_balance`, `ledger_report`, `ledger_work_position`, `advance_list`, and Live Report list APIs.
- Group report/export/finalize/open-carryover APIs require an explicit valid `group_id`; otherwise they return `invalid_group_id`.
- Default invite access level is `base`; base users can use Live Report but cannot view group reports or write group ledger. Manager and advanced/admin can view group reports; manager and advanced can write group ledger by current `ql_ledger_group_scope` fallback/permissions logic.
- Release risk: if a group screen calls a backend endpoint without `group_id`, the API can return personal zeros or personal totals while the user expects group truth.

## Closed Findings

- None yet.

## Historical Final Report Snapshot Implementation 2026-05-26

Product decision accepted: current open-period export remains current truth; closed final report export must be immutable historical truth.

Implemented backend/API patch:

- New finalizations now store `report_snapshot` inside `audit_log.details` for `action='ledger_group_report_finalized'`.
- Snapshot is taken before `ledger_group_finalize_report` archives included Live Report cards.
- Existing carryover details are preserved: `carryover_admin_cash_left`, `carryover_employee_cash_left`, `carryover_cash_balance`, `report_balance`, `card_ids`.
- New finalization response includes `report_id`, the `audit_log.id` identity for the closed final report.
- No new DB table/schema migration was added; existing `audit_log.details JSON` is used as the immutable storage.

New API actions:

- `ledger_group_final_report_list`: list closed final reports for a group.
- `ledger_group_final_report_detail`: read one closed final report by `report_id`.
- `ledger_group_final_report_google_sheet`: export one closed final report as TSV/HTML payload.
- `ledger_group_final_report_excel`: download one closed final report as Excel-compatible HTML.

Current-period behavior kept:

- `ledger_group_google_sheet` and `ledger_group_excel` still use `ql_ledger_group_export_snapshot()`.
- After a finalization exists, those current export paths still switch to open-period snapshot and start from carryover.
- `ledger_group_open_received_funds` still uses latest finalization carryover and filters current ledger income to rows created after finalization.

Fallback limitation:

- Old `ledger_group_report_finalized` rows that do not contain `details.report_snapshot` are not reconstructed.
- `ledger_group_final_report_detail`, `ledger_group_final_report_google_sheet`, and `ledger_group_final_report_excel` return `historical_snapshot_missing` for those old rows.
- This is intentional: reconstruction from mutable ledger/live tables would not be immutable historical truth.

Verification:

- CLI `php` remains unavailable in this shell, so `php -l` and `php scripts/local-smoke.php` are environment-blocked.
- Server load/parse check passed: `GET /api.php?action=current_user` returned HTTP 200 after the patch.
- HTTP API smoke passed on 2026-05-26 using local server `http://127.0.0.1:18889`.
- Smoke-created evidence after final patch: `group_id=188`, `report_id=325`.
- Verified totals from historical detail snapshot: `income=1000`, `expense=600`, `cash_expense=600`, `cash_balance=400`, `admin_cash_left=400`, `balance=400`.
- Verified current open-period endpoint returned carryover `400` and did not return old `Backend final income` as current income.
- Verified historical final report export still contained old `Backend final income` and `Backend final expense`.
- Verified current export after finalization contained `Переходящий остаток` and did not contain old `Backend final income`.
- Follow-up HTTP check verified final report list and Excel endpoint: `group_id=187`, `report_id=320`, `ledger_group_final_report_list` returned `snapshot_available=true`, and `ledger_group_final_report_excel` returned HTTP 200 with an HTML table body.

## Current Open-Period Export Regression Fix 2026-05-26

QA blocker accepted: historical finalized report/export works for new finalizations, but current open-period path could drop/overwrite the current income row after a Live Report existed.

Root cause:

- `ql_ledger_group_open_received_funds()` normalized current income rows with `foreach ($rows as &$row)`.
- The reference variable was not released before later loops reused `$row`.
- In PHP, that leaves `$row` bound to the last `$rows` element; the later Live Report loop could overwrite the last current income row, producing evidence like `entries: [{"id": <live_tape_id>}]` instead of the ledger income row.

Patch:

- Added `unset($row)` immediately after the by-reference loop in `app/ledger.php`.
- No financial formula, UX, historical snapshot, or export source behavior was changed.
- Extended `scripts/local-smoke.php` to cover the combined release scenario: finalized `1000 / 600 / 400`, then current income `50`, then current Live Report expense `25`.

Verification:

- `git diff --check -- app/ledger.php scripts/local-smoke.php public/api.php` passed.
- CLI `php` remains unavailable in this shell; CLI smoke is `environment-blocked`.
- Server load check passed: `GET /api.php?action=current_user` returned HTTP 200.
- HTTP/API scenario passed on local server:
  - QA fixture email: `qa-current-export-regression-20260526174848@example.test`
  - `group_id=194`, `report_id=364`
  - old finalized income entry `87`, old finalized tape `179`
  - current income entry `88`, current Live Report tape `181`
  - historical detail/export remained `income=1000`, `expense=600`, `cash_balance=400`, `admin_cash_left=400`
  - `ledger_group_open_received_funds.entries` returned the current income row: `id=88`, `amount=50`
  - current export contained `Переходящий остаток` `400`, current income `50`, current Live Report expense `25`
  - current export did not contain old finalized income `1000`

Additional boundary observed during synthetic testing:

- If a current ledger income is created in the exact same DB second as `ledger_group_report_finalized`, the existing cutoff `le.created_at > finalized_at` can exclude it.
- This was not the QA blocker evidence, which showed the income row was selected and then overwritten.
- A future schema/data-path decision should consider storing a deterministic ledger cutoff identity at finalization time if same-second correctness must be guaranteed.

## Field Combat Mode Backend Persistence Trace 2026-05-26

Status: P0 blocked for full Field Combat Mode no-data-loss claim.

What is proven server-side after successful save:

- Amount/note rows persist in `on_the_go_captures` after `on_the_go_signed_sync` or `on_the_go_create` returns `ok=true`.
- Card/session identity persists through `on_the_go_tapes.id` and `on_the_go_sessions.id`.
- Group context persists in `on_the_go_tapes.group_id`; participant/owner persists in `on_the_go_tapes.user_id` and `on_the_go_captures.user_id`.
- Cash/card stream persists in `on_the_go_tapes.stream_type`; card stream forces `cash_received=0` and only accepts `noncash_out`.
- Recovered totals are recalculated from saved DB rows by `ql_on_the_go_card_summary()` / `ql_on_the_go_tape_summary()`.
- Successful proof upload persists by moving the file into `storage/documents/on-the-go/<year>/...` and inserting `on_the_go_files`.
- Card/list/detail/session APIs can recover saved unfinished cards and rows:
  - `on_the_go_card_list`
  - `on_the_go_card_detail`
  - `on_the_go_session_list`
  - `on_the_go_session_detail`

P0 blockers:

1. Raw typed money facts are client-only until an explicit API save succeeds.
   - The simple editor updates only DOM state (`simpleDirty`, textarea value) while typing.
   - No backend draft endpoint stores raw note text, parsed rows, selected file, or selected stream/group before `on_the_go_signed_sync`.
   - `public/assets/app.js` has no `localStorage` / `sessionStorage` persistence for Field Combat rows.
   - Refresh, module reload, browser/PWA pause, or phone kill before successful save can lose the typed money fact.

2. Proof upload pending/failed/retry state is not durable.
   - `on_the_go_upload_file` inserts `on_the_go_files` only after `move_uploaded_file()` succeeds.
   - On upload failure it returns immediate errors such as `upload_error_*`, `file_too_large`, `file_type_not_allowed`, or `move_failed`.
   - There is no `on_the_go_uploads` / pending-proof table, no failed upload row, no retry identity, and no persisted link from the selected proof to the capture before successful upload.
   - The frontend can show a transient message like `Saved, but attachment failed`, but after refresh there is no backend state proving a proof is pending/failed/retry-needed.

3. Partial network/offline save has no backend queue identity.
   - `qlApi()` posts directly with `fetch`; if the request never reaches `/api.php`, backend storage has no row, draft, idempotency key, pending state, or retry state.
   - `ql_on_the_go_create` has only a 4-second duplicate guard, and `ql_on_the_go_signed_sync` has no client-generated operation id.
   - Backend cannot distinguish “not yet sent”, “pending retry”, and “lost before server”.

4. Selected group/participant/stream before successful save can be lost.
   - Once saved, group/user/stream are durable.
   - Before successful save, current UI selection is not represented by a backend draft/session record.

Deliberate close/submit boundary:

- `on_the_go_signed_sync` saves draft rows and does not submit by itself unless caller asks `start_next` to close/seed the next tape.
- `on_the_go_card_submit` / `on_the_go_card_include` are explicit boundary endpoints.
- HTTP fixture confirmed saved cash/card cards stayed `card_state=draft` before submit/include.

HTTP/API evidence:

- Server: `http://127.0.0.1:18889`
- CLI `php`: unavailable in this shell, so CLI smoke is environment-blocked.
- Load check: `GET /api.php?action=current_user` returned HTTP 200.
- Fixture email: `qa-field-combat-backend-20260526195439@example.test`
- `user_id=497`
- `group_id=201`
- Cash unfinished card: `cash_tape_id=200`, `cash_capture_id=158`, `cash_active_session_id=140`
- Proof upload: `proof_files=1` via `on_the_go_upload_file` + `on_the_go_file_list`
- Card unfinished card: `card_tape_id=201`
- Verified recovery through `on_the_go_card_list`, `on_the_go_card_detail`, `on_the_go_session_list`, and `on_the_go_session_detail`.
- Verified card stream summary: `cash_left=0`, `card_out=12`, `cash_delta=0`.

Conclusion:

- Backend/API/storage can preserve Field Combat data after a successful save/upload.
- The full Field Combat Mode contract is not proven and should remain blocked because unfinished typed facts and proof upload retry state are not durable before successful save/upload.

## Durable Field Combat Draft/Proof Implementation 2026-05-26

Status: backend/API patch implemented; release claim still needs Frontend wiring and QA recheck.

Changed files:

- `app/on_the_go.php`
- `public/api.php`
- `deploy/on_the_go_sessions_runtime.sql`
- `scripts/local-smoke.php`

Storage added:

- `on_the_go_field_drafts`
  - durable raw draft notes;
  - parsed rows JSON;
  - skipped rows JSON;
  - group, participant/user, stream, tape, session;
  - cash received/base amount;
  - `draft_status`: `active`, `submitted`, `closed`, `archived`;
  - `sync_state`: `saved`, `pending`, `failed`, `retry_needed`.
- `on_the_go_field_sync_ops`
  - durable `client_operation_id`;
  - status: `pending`, `succeeded`, `failed`, `retry_needed`;
  - saved response JSON for idempotent retry.
- `on_the_go_upload_states`
  - durable `client_upload_id`;
  - status: `pending`, `uploaded`, `failed`, `retry_needed`;
  - `last_error`, `retry_count`, original name, storage path, capture/draft link.

API added:

- `on_the_go_field_draft_save`
  - creates/reuses open tape/session identity before submit/close;
  - persists raw note text, parsed rows, selected group, participant/user, stream, cash base, tape/session identity.
- `on_the_go_field_recover`
  - returns saved draft text, parsed rows, proof states, recalculated draft totals, tape/session identity, current tape summary.
- `on_the_go_proof_state_begin`
  - creates durable pending proof/upload state before transfer.
- `on_the_go_proof_state_fail`
  - records durable failed/retry-needed proof state with last error and retry count.
- `on_the_go_proof_state_list`
  - lists persisted proof states by draft/capture.

Existing API extended:

- `on_the_go_signed_sync` accepts `client_operation_id`.
- A repeated successful `client_operation_id` returns the saved response with `idempotent=true` instead of creating duplicate rows.
- `on_the_go_upload_file` accepts `client_upload_id`, `client_draft_id`, and `draft_id`.
- Successful upload now marks the upload state `uploaded`.
- Upload errors mark durable `failed` or `retry_needed` when `client_upload_id` is supplied.

Behavior boundaries:

- No financial formulas were changed.
- Autosave does not submit, include, finalize, or accept a report.
- Submit/include/finalization remain deliberate separate endpoints.
- Backend can preserve typed facts after the first successful `on_the_go_field_draft_save`.
- Backend can preserve proof pending/failed/retry state after `on_the_go_proof_state_begin` or an upload attempt with `client_upload_id`.
- If the client never sends the first autosave/proof-state request to the server, backend still cannot recover that purely local state. Frontend must call the new endpoints early.

Verification:

- CLI `php` remains unavailable in this shell; CLI smoke is environment-blocked.
- `git diff --check -- app/on_the_go.php public/api.php deploy/on_the_go_sessions_runtime.sql scripts/local-smoke.php` passed.
- Server load check passed: `GET /api.php?action=current_user` returned HTTP 200.
- HTTP/API fixture passed on local server `http://127.0.0.1:18889`.
- Fixture:
  - email: `qa-field-combat-durable-20260526203628@example.test`
  - `user_id=498`
  - `group_id=202`
  - `draft_id=1`
  - `client_draft_id=draft-20260526203628`
  - `tape_id=202`
  - `session_id=142`
  - `capture_id=160`
  - `client_operation_id=op-20260526203628`
  - uploaded proof state: `upload-20260526203628`
  - retry-needed proof state: `upload-fail-20260526203628`
- Verified:
  - `on_the_go_field_draft_save` persisted raw notes `-25 Durable autosave row`;
  - `on_the_go_field_recover` restored the raw notes and same tape/session identity;
  - draft totals recovered as `cash_out=25`, `cash_left=75`;
  - proof state moved `pending -> retry_needed` and remained visible after recovery;
  - `on_the_go_signed_sync` with repeated `client_operation_id` returned `idempotent=true`;
  - proof upload with `client_upload_id` moved state to `uploaded`;
  - `on_the_go_card_detail` returned the saved row/proof and card stayed `card_state=draft`.

## Production Message Unread Alias Hotfix 2026-05-27

Trigger: QA Release Engineer accepted participant-control in production but blocked default base employee rights because `message_unread` returned HTTP `500` for a base employee. Production SQL error pointed to alias `current_role`.

Root cause: production MySQL treats `current_role` as a problematic alias. Local MariaDB accepted the query, so the earlier director smoke missed this parser difference.

Patch: `app/messages.php` now uses safe internal aliases:

- `member_role_for_scope`
- `member_access_level_for_scope`
- `member_permissions_json_for_scope`

Behavior unchanged:

- base employee still cannot list/send group messages;
- base employee `message_unread` filters group messages and returns a safe empty response;
- manager/admin/group-data roles keep group-message access.

Local verification:

- group id: `225`
- admin user id: `533`
- base employee user id: `534`
- `message_unread`: `ok=true`, `unread_count=0`
- `message_list`: `access_denied`
- `message_send`: `access_denied`

Production deployment:

- uploaded only `app/messages.php`;
- backup id: `prod-hotfix-before-message-unread-20260527T212247Z`;
- backup checksum: `910dfe9a79731f30d3fab4511f078ea39c47a9366020db1237d6e0d0ebf48891`;
- uploaded checksum: `75e8f9c61fbcb5f595bc8ffb56dd36b42fa8beee8b08cb88d6e99c33e815cb15`.

Production verification:

- group id: `19`
- admin user id: `56`
- base employee user id: `57`
- `message_unread`: `ok=true`, `unread_count=0`
- `message_list`: `access_denied`
- `message_send`: `access_denied`

Status: backend defect fixed by director smoke. QA Release Engineer rerun is required to close the P0 gate.

## Production Legacy May Report Hotfix 2026-05-28

Trigger: CEO reported that after clearing Safari cache a `03.05` submitted/returned report still remained visible and could not be deleted or returned.

Finding: this was not browser cache. Production DB contained legacy tape `on_the_go_tapes.id=1`, title `Legacy On the Go`, owner `vetus.nauta`, created `2026-05-03 16:23:59`, computed state `included`, with a later `return_requested_at=2026-05-27 17:58:57`. The card had no group, so “request correction” had no real recipient.

Data hotfix:

- archived tape `id=1`;
- archived its remaining visible/reportable capture `id=5`, amount `1235.00`, description `Заправка`;
- set that capture `reportable=0`;
- wrote audit action `codex_legacy_may_report_archived`;
- deleted temporary diagnostic/maintenance scripts from production.

Code hardening:

- `app/on_the_go.php` now treats a locked personal card without group as self-returnable by the owner;
- personal locked cards no longer route owners into a meaningless return-request state;
- group participant behavior remains unchanged: employees still request return from group managers/admins.

Verification:

- production `/api.php?action=current_user`: HTTP 200, `ok=true`;
- production `/app.php`: HTTP 200;
- local `git diff --check -- app/on_the_go.php`: PASS.

Backup:

- `backups/production-on-the-go-before-self-return-20260528-084330/on_the_go.php`.

---

# Backend/Data Findings - 2026-05-28 Receipt Scanner Storage Implementation

Role: Backend/Data Engineer FinDesk
Task: local storage/API implementation for Receipt Scanner evidence chain.

## Implemented Locally

- Extended `on_the_go_files` for scanner artifacts:
  - `proof_role`;
  - `proof_bundle_id`;
  - `source_file_id`;
  - `file_hash_sha256`;
  - `metadata_json`.
- Extended `on_the_go_upload_states` for scanner proof state metadata:
  - `proof_role`;
  - `proof_bundle_id`;
  - `file_hash_sha256`;
  - `metadata_json`.
- Extended `on_the_go_upload_file` to accept scanner metadata while remaining compatible with current single-file attachments.
- Added idempotent return for already uploaded `client_upload_id` to avoid duplicate proof files on retry.
- Returned scanner metadata through `on_the_go_file_list`.
- Added scanner proof-chain coverage to `scripts/local-smoke.php`.

## Files Changed

- `app/on_the_go.php`
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `scripts/local-smoke.php`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/STATUS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md`

## Verification

- Local HTTP `/api.php?action=current_user`: PASS.
- `git diff --check` for backend/storage files: PASS.
- Authenticated local API scanner storage smoke: PASS.
  - user id `536`;
  - tape id `294`;
  - capture id `201`;
  - original file id `16`, role `scanner_original`;
  - PDF file id `17`, role `scanner_cleaned_pdf`;
  - bundle `scanner-bundle-api-20260528`;
  - PDF `source_file_id=16`;
  - repeated PDF upload with same `client_upload_id` returned `idempotent=true`;
- `on_the_go_file_list` returned exactly two scanner artifacts.
- Frontend now sends a stable `client_upload_id` for `scanner_original`; `scripts/local-smoke.php` includes idempotent retry assertions for both original and cleaned PDF.
- `scripts/local-smoke.php` updated with scanner original/PDF/idempotent/file-list assertions.
- API idempotency recheck after original-upload fix: user id `541`, tape id `302`, capture id `205`, bundle `scanner-api-bundle-20260528080559`, original file id `24` replay idempotent, PDF file id `25` replay idempotent, file list returned exactly `scanner_original` and `scanner_cleaned_pdf`.
- Final package scanner proof API recheck: user id `542`, group id `226`, tape id `303`, capture id `206`, report id `516`, bundle `scanner-package-bundle-20260528080910`; package proofs contained `scanner_original` and `scanner_cleaned_pdf`, with cleaned PDF derived from the original file id. `scripts/local-smoke.php` now includes this final-package scanner proof assertion.
- PHP CLI lint not run: `php` command is unavailable in this shell.

## Blocker

Independent QA/browser scanner scenario still needs evidence.
