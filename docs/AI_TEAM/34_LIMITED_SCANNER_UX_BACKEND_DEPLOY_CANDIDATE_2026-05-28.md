# Limited Scanner/UX/Backend Deploy Candidate

Date: 2026-05-28
Owner: Project Director / Deploy Owner
Status: deployed as limited production release; production smoke passed

## Local Gates Passed

- Receipt Scanner local browser/HTTP file-input QA: run `20260528RSQA01`.
- Chief Auditor local scanner gate: approved for local file-input path only.
- Frontend/UX leftovers: done locally.
- Backend/Data `group_delete` hardening: done locally.
- QA formal local recheck: run `20260528LOCALLEFTOVERS01`, fixture `group_id=233`.

## What This Candidate Fixes

- Login fallback text no longer shows old `FinDesk sign-in code` H1.
- Asset versions for `app.css`, `app.js`, and `i18n.js` are bumped to reduce stale browser cache.
- `Живой отчет` keeps the last safe working zone instead of falling back blindly after refresh.
- Mobile Live Report card/action layout is hardened against header/action overlap.
- Scanner modal has a visible `Закрыть` action and Escape-close support.
- `group_delete` soft-archives test groups without deleting financial evidence.
- `group_delete` works when optional `groups.updated_at` / `group_invites.updated_at` columns are absent.
- Base/non-admin users remain denied from group delete.

## Candidate Runtime File List

Upload only after the blockers below are closed:

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

Candidate controlled SQL/preflight:

- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `docs/AI_TEAM/36_DB_PREFLIGHT_READONLY_SQL_2026-05-28.sql` as read-only production/pre-production schema check

Verification-only, not web runtime upload by default:

- `scripts/local-smoke.php`

Dependency control:

- `public/api.php` now loads `app/ai.php` only if it exists, so the limited bundle does not fatal when Advanced AI is excluded.
- `app/ai.php` remains optional and should not be uploaded unless CEO/Deploy Owner accepts the Advanced AI surface.
- `app/business.php` must be confirmed present and compatible on production before uploading the selected API file.

Local artifact:

- `docs/AI_TEAM/37_LIMITED_CANDIDATE34_ARTIFACT_2026-05-28.md`
- `backups/findesk-limited-candidate34-20260528T134812Z/findesk-limited-candidate34-20260528T134812Z.tar.gz`
- SHA256 `a159c4000a580db314981529bdb3812dbed953b18b93dd9148b2e9d60f7cffd9`

Do not upload the full dirty tree.

## Production Deploy Result

Deploy report:

- `docs/AI_TEAM/38_CANDIDATE34_PRODUCTION_DEPLOY_REPORT_2026-05-28.md`

Production smoke:

- run id: `prod-candidate34-20260528140302`;
- group id: `24`;
- report id: `218`;
- scanner bundle id: `c34-scanner-20260528140302`.

Closed blockers:

- production DB preflight recorded;
- production DB backup recorded;
- production file/storage backup recorded;
- runtime SQL applied and post-check passed;
- limited runtime bundle uploaded;
- production HTTP/API smoke passed;
- temporary DB-gate removed and returned `404`.

Still not claimed:

- physical camera/PWA scanner is not device-ready until real-device QA passes.

## Original Production NO-GO Blockers

1. Real-device scanner/PWA camera gate is still open:
   - `docs/AI_TEAM/33_RECEIPT_SCANNER_REAL_DEVICE_QA_GATE_2026-05-28.md`
   - required for any claim that scanner is device-ready.

2. PHP CLI/smoke availability:
   - current shell has no `php`;
   - selected PHP files need `php -l` or an approved HTTP/API replacement smoke on the deploy host.

3. DB migration preflight:
   - scanner proof columns must be present or applied safely;
   - no destructive SQL;
   - record before/after column state.

4. Backup/rollback:
   - files backup for selected paths;
   - database backup before SQL;
   - rollback owner and exact rollback artifact.

5. Production smoke after upload:
   - auth/login code flow;
   - group create/delete soft archive;
   - Live Report open/save/reopen;
   - scanner file-input path if included;
   - final package proof visibility if scanner storage is included;
   - base employee still denied from group data and group delete.

## Allowed Limited Release Decision

If CEO accepts a limited release before real-device scanner QA:

- do not describe scanner as device-ready;
- describe it as local/browser file-input scanner path only;
- keep physical camera/PWA scanner as blocked until device QA.

## Current Director Decision

No production upload yet. The package is locally QA-rechecked, but deploy remains blocked by the production checklist.
