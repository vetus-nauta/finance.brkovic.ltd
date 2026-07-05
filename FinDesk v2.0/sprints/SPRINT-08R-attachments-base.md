# SPRINT-08R — Attachments Base

Status: Accepted

## Director Sprint Opening

Sprint:
SPRINT-08R — Attachments Base

Goal:
Implement the Clean Core MVP attachment base for existing entries without reusing old FinDesk product logic.

Required files read:
- `FinDesk v2.0/07-mvp-scope-and-acceptance.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-07-integration-hardening-attachments.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`

Agents assigned:
- Data and Backend Core Agent
- Frontend Performance and Interaction Agent
- Security and Privacy Agent
- QA, Audit, and Acceptance Agent

Agent tasks:
- Data and Backend Core Agent: define and implement isolated v2 attachment API/storage path, metadata persistence, ownership checks, and audit logging.
- Frontend Performance and Interaction Agent: decide and implement the smallest operational-window attachment interaction needed for MVP, without dashboard expansion.
- Security and Privacy Agent: review upload validation, file path safety, delete behavior, legacy isolation, and closed-month mutation risk.
- QA, Audit, and Acceptance Agent: verify API contract, fixture/smoke coverage, legacy isolation, and sprint exit criteria.

Expected reports:
- Scope implemented or rejected.
- Files changed.
- Tests/checks run.
- Risks and explicit blockers.
- Accept/reject recommendation.

Exit criteria:
- `POST /api/entries/:entryId/attachments` exists and stores metadata for an entry the current user may access.
- `GET /api/entries/:entryId/attachments` lists that entry's attachments.
- `DELETE /api/attachments/:attachmentId` removes attachment metadata and local stored content when present.
- Attachment storage is isolated under v2 paths.
- Attachment actions are audited.
- UI exposes attachment base only inside the operational entry detail surface if implemented.
- Static, fixture, HTTP, DB, UI, and browser smokes pass.
- No old FinDesk product logic is reused.

Risks:
- File uploads can introduce path traversal, oversized payloads, unsafe MIME assumptions, and accidental legacy-storage coupling.
- Closed-month attachment changes must not alter financial formulas or silently recalculate money.
- Upload UI can expand scope; the sprint should keep attachment base small and operational-entry focused.

## Director Notes

- This sprint does not implement OCR, scanners, image processing, bank integration, full archive import, dashboard widgets, or report attachments.
- The old FinDesk attachment code is infrastructure archive only and is not a source of Clean Core product logic.
- Product decision: attachment create/delete is allowed on closed-month entries only as audited non-financial metadata/content work. It must not mutate entry money/category/status/source fields and must not recalculate balances.

## Implementation Summary

Implemented:
- `GET /api/entries/:entryId/attachments`
- `POST /api/entries/:entryId/attachments`
- `DELETE /api/attachments/:attachmentId`
- Private v2 attachment storage under `storage/v2/attachments/{workspace_id}/{entry_id}/{attachment_id}.{ext}`.
- JSON/base64 upload support for fixture/API smoke.
- Multipart `file` upload support for the operational detail UI.
- Server-side MIME detection with an MVP allowlist: PDF, JPEG, PNG, WebP.
- Attachment list/upload/delete controls inside Entry Details only.
- Audit rows for attachment create/delete.
- Static, fixture, HTTP, DB, UI, and browser smoke coverage.

Not implemented:
- OCR/scanner/image processing.
- Public download route.
- Dashboard/report attachment surfaces.
- Full attachment archive/search.
- Cross-user/read-only-member negative smoke.

## Agent Reports

Data and Backend Core Agent:
ACCEPT. Contract routes and repository methods are present; storage is v2-only and private; ownership checks are entry/member based; create/delete require writer role; closed-month attachments do not recalculate money; no old attachment product logic reused.

Frontend Performance and Interaction Agent:
ACCEPT. Attachment UI is confined to Entry Details, remains minimal, passes UI smoke, and does not expand into dashboard/report/archive/scanner/OCR surfaces. Non-blocking note: very fast entry switching during an in-flight attachment request could briefly render stale attachment state.

Security and Privacy Agent:
ACCEPT. Upload limits, MIME sniffing, filename/path controls, private storage, realpath-prefix delete checks, no public download route, and closed-month non-financial audit behavior are acceptable. Non-blocking note: file delete and DB/audit commit are not fully atomic.

QA, Audit, and Acceptance Agent:
ACCEPT. Sprint exit criteria are met; coverage was extended rather than reduced; arithmetic, import, generated reports, closed-month decisions, UI layout, and browser attachment flow remain covered.

## Tests and Checks

Passed:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l public/v2-api.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_http_api_smoke.php
node --check scripts/v2_operational_browser_smoke.cjs
bash -n scripts/v2_operational_ui_smoke.sh
bash -n scripts/v2_disposable_db_smoke.sh
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
bash scripts/v2_disposable_db_smoke.sh
npm run smoke:v2:ui
npm run smoke:v2:browser
```

Final combined run:

```text
npm run smoke:v2 &&
npm run test:v2:fixtures &&
npm run smoke:v2:http &&
bash scripts/v2_disposable_db_smoke.sh &&
npm run smoke:v2:ui &&
npm run smoke:v2:browser
```

Result: PASS.

## Director Final Handoff

Sprint:
SPRINT-08R — Attachments Base

Status:
Accepted

Agents assigned:
- Data and Backend Core Agent
- Frontend Performance and Interaction Agent
- Security and Privacy Agent
- QA, Audit, and Acceptance Agent

Agent reports received:
- Data and Backend Core Agent: ACCEPT
- Frontend Performance and Interaction Agent: ACCEPT
- Security and Privacy Agent: ACCEPT
- QA, Audit, and Acceptance Agent: ACCEPT

Accepted work:
- v2 attachment API contract routes implemented.
- v2-only private storage implemented.
- Entry ownership and writer checks implemented for attachment mutation.
- Attachment create/delete audit implemented.
- Operational Entry Details attachment UI implemented.
- Attachment smoke/fixture/browser coverage implemented.

Rejected work:
- No OCR, scanner, image processing, dashboard/report attachment UI, public download route, bank integration, or legacy product logic.

Files changed:
- `FinDesk v2.0/sprints/SPRINT-08R-attachments-base.md`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `public/v2-api.php`
- `public/v2.php`
- `public/assets/v2/app.js`
- `public/assets/v2/app.css`
- `scripts/v2_clean_core_static_smoke.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_http_api_smoke.sh`
- `scripts/v2_disposable_db_smoke.sh`
- `scripts/v2_operational_ui_smoke.sh`
- `scripts/v2_operational_browser_smoke.cjs`

Tests or checks:
All listed syntax, static, fixture, HTTP, disposable DB, UI, and browser checks passed.

Risks:
- Attachment file deletion and DB/audit transaction are not fully atomic; later hardening can add orphan cleanup or soft-delete staging.
- Add explicit viewer/read-only and second-user negative smoke later.
- Frontend can harden against stale attachment render if the user switches entries during an in-flight request.

Next sprint:
SPRINT-09R — Month Closure API and Operational Controls

Paste-to-next-director prompt:
You are the new Director for FinDesk v2.0. Source of truth is GitHub files only. Start by reading `START_HERE_DIRECTOR.md`, `33-director-agent-orchestration-protocol.md`, this handoff, `16-api-contract.md`, `14-calculation-contract.md`, and the current v2 API/repository/UI files. Do not use old FinDesk product logic. SPRINT-08R Attachments Base is accepted with tests green. Open SPRINT-09R — Month Closure API and Operational Controls. Assign agents first. Goal: implement documented month close/reopen/correction API and the smallest operational control needed to close/reopen a month, without changing financial formulas or report semantics unless explicitly approved. Preserve the operational journal as source of truth; do not build dashboard-first UI.
