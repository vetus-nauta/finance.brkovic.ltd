# Deploy Preflight Sprint

Date: 2026-05-28
Owner: Project Director / Deploy Owner
Candidate: `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`
Status: closed; candidate deployed as limited production release

## Sprint Goal

Prepare the limited scanner/UX/backend package for a real go/no-go decision without uploading to production yet.

This sprint closes only when:

- exact runtime file bundle is accepted;
- production DB column state is recorded;
- SQL apply/skip plan is recorded;
- file and DB backup plan is ready;
- PHP lint or approved HTTP replacement smoke is ready;
- production smoke checklist is ready;
- CEO decision is recorded for device-ready scanner claim or limited release without that claim.

## Current Local Position

Closed locally:

- Receipt Scanner local browser/HTTP file-input gate `20260528RSQA01`;
- Chief Auditor local scanner gate;
- Frontend/UX leftovers;
- Backend/Data `group_delete` soft archive hardening;
- QA local recheck `20260528LOCALLEFTOVERS01`.

## Production Read-Only Check

Checked on 2026-05-28 without login and without writes:

- `GET https://finance.brkovic.ltd/app.php`: HTTP `200`;
- `GET https://finance.brkovic.ltd/api.php?action=current_user`: `{"ok":true,"user":null}`;
- production `app.php` still shows fallback H1 `FinDesk sign-in code`;
- production assets are still versioned as `20260528-mvpfix1`;
- candidate 34 local app uses `FinDesk access code` and asset version `20260528-frontend-residual1`.

Conclusion:

- CEO's production observation about old login copy is confirmed by read-only production check;
- candidate 34 contains the local fix, but it has not been uploaded.

Still blocking production upload:

- real-device scanner/PWA camera evidence, unless CEO accepts limited release without device-ready scanner claim;
- DB preflight for scanner proof columns;
- PHP lint/smoke on a PHP-capable host or approved HTTP replacement smoke;
- file/DB backup and rollback;
- production smoke after upload.

## Director Finding: API Dependency Risk

`public/api.php` previously required `app/ai.php` unconditionally. This was a deploy risk because `app/ai.php` is not part of the narrow production bundle by default.

Local hardening applied:

- `app/ai.php` is now loaded only if the file exists;
- `ai_analysis_run` returns `ai_unavailable` if the function is unavailable;
- `audit_list` returns `audit_unavailable` if the function is unavailable;
- `/api.php?action=current_user` still returns `ok=true`;
- authenticated AI/audit endpoints still return `not_authenticated` locally when the optional module is present.

## Candidate Runtime Bundle

Core upload candidates:

- `app/auth.php`
- `app/advances.php`
- `app/groups.php`
- `app/ledger.php`
- `app/messages.php`
- `app/on_the_go.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`

Production dependency check before upload:

- `app/auth.php`
- `app/messages.php`
- `app/business.php`
- `app/advances.php`
- `app/ai.php`

Decision:

- `app/ai.php` is optional after API hardening and should not be included unless CEO/Deploy Owner accepts the Advanced AI surface.
- `app/business.php` must already exist and be compatible on production, or be added explicitly by Deploy Owner before upload.
- local artifact is recorded in `docs/AI_TEAM/37_LIMITED_CANDIDATE34_ARTIFACT_2026-05-28.md`.

## Preflight Artifacts

- DB read-only SQL: `docs/AI_TEAM/36_DB_PREFLIGHT_READONLY_SQL_2026-05-28.sql`
- Deploy candidate: `docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md`
- QA deploy checklist: `docs/AI_TEAM/roles/04_qa_release_engineer/DEPLOY_READINESS_CHECKLIST_2026-05-28.md`
- Chief Auditor frame: `docs/AI_TEAM/roles/05_chief_auditor/DEPLOY_PREFLIGHT_GATE_CANDIDATE_34_2026-05-28.md`

## Closing Result

Candidate `34` was deployed to production as a limited release.

Evidence:

- deploy report: `docs/AI_TEAM/38_CANDIDATE34_PRODUCTION_DEPLOY_REPORT_2026-05-28.md`;
- DB backup: `backups/prod-db-before-candidate34-20260528T135737Z/findesk-db-before-candidate34-20260528T135737Z.sql.gz`;
- file/storage backup: `backups/prod-files-before-candidate34-20260528T135752Z.tgz`;
- runtime SQL applied: `deploy/on_the_go_sessions_runtime.sql`;
- production smoke run: `prod-candidate34-20260528140302`;
- smoke fixture: group `24`, final report `218`.

Control:

- temporary DB-gate removed after use and returned `404`;
- no full dirty-tree upload;
- scanner remains limited to the file-input/runtime path until real-device scanner/PWA camera QA passes.
