# Production Deploy Package Candidate

Date: 2026-05-27

Owner: Deploy Package Coordinator

Status: CANDIDATE LIST MATERIALIZED AS LOCAL ARTIFACT; production upload remains BLOCKED.

No FTP upload was executed. No production database action was executed. No application code was changed. No credentials are included in this report.

## Inputs Read

- `docs/AI_TEAM/15_100_PERCENT_MVP_CONTROL.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `git status --short`
- Dependency checks from local diffs for `public/api.php`, `public/app.php`, `public/service-worker.js`, `deploy/on_the_go_sessions_runtime.sql`, and selected backend/frontend references.

## Executive Recommendation

Default deploy mode: **narrow MVP runtime bundle**.

2026-05-27 Project Director update:

- Local artifact built: `backups/findesk-mvp-runtime-20260527T185423Z/findesk-mvp-runtime-20260527T185423Z.tar.gz`.
- Checksum: `0bf15e78f3e17f4d40f7444fe92213d2cee9f6335712eee7851f294563d96dfc`.
- Artifact record: `docs/AI_TEAM/24_MVP_RUNTIME_ARTIFACT_2026-05-27.md`.
- No production upload was executed.

Do not deploy the full dirty tree unless CEO/Project Director explicitly accepts every dirty runtime file, public asset, documentation file, local support file, and advanced/AI surface as production scope.

Production remains blocked until:

- Project Director selects the final file package.
- Backend/Data confirms or applies the runtime SQL migration.
- Production files and database backups are completed.
- Rollback owner and rollback path are named.
- QA Release Engineer runs production smoke after upload.

## Default Narrow MVP Runtime Bundle

This is a deploy candidate, not upload approval.

### Backend/API Candidates

| Path | Dirty state | Area | Classification | Notes |
| --- | --- | --- | --- | --- |
| `public/api.php` | modified | API | deploy-candidate with blocker | Routes accepted MVP API actions, but also adds `audit_list` and `ai_analysis_run`; current file unconditionally requires `app/ai.php`. |
| `app/auth.php` | modified | backend/auth | deploy-candidate | Adds audit helpers and local-dev auth-code behavior guarded by local host/app URL detection. Needs auth-surface review before production. |
| `app/groups.php` | modified | backend/groups | deploy-candidate | Adds audit write on member access update; loaded through API dependency chain. |
| `app/ledger.php` | modified | backend/money reports | deploy-candidate | Contains current/historical report separation, final report list/detail/package/export/proof download. Current file requires `app/on_the_go.php`. |
| `app/on_the_go.php` | modified | backend/live report | deploy-candidate | Contains Field Combat draft/recovery/idempotent sync/proof state/cash-card stream runtime. Required for accepted MVP flow. |
| `app/advances.php` | modified | backend/accountable money | deploy-candidate | Supports accountable/advance detail, totals, accept/unaccept/return/cancel behavior used by closed package and residual MVP path. |

### Runtime SQL Candidate

| Path | Dirty state | Area | Classification | Notes |
| --- | --- | --- | --- | --- |
| `deploy/on_the_go_sessions_runtime.sql` | modified | runtime SQL | deploy-candidate migration, not web upload | Must be applied or proven already applied before uploading PHP that assumes new On the Go/session/draft/proof schema. Engine compatibility for `ADD COLUMN IF NOT EXISTS` and `ADD KEY IF NOT EXISTS` must be checked first. |

### Frontend Runtime Candidates

| Path | Dirty state | Area | Classification | Notes |
| --- | --- | --- | --- | --- |
| `public/app.php` | modified | frontend shell | deploy-candidate | Main app shell for MVP runtime. References `/assets/brand-mark.png`; include that asset or produce a clean shell without the reference. |
| `public/assets/app.js` | modified | frontend runtime | deploy-candidate with blocker | Contains MVP UI wiring, but also exposes AI/audit UI calls. Requires Project Director decision on whether this broader surface is acceptable in the narrow package. |
| `public/assets/app.css` | modified | frontend styles | deploy-candidate | Styles for accepted MVP UI and new brand-mark classes. |
| `public/assets/i18n.js` | modified | frontend translations | deploy-candidate | Translation/runtime text dependency for changed app UI. |
| `public/assets/brand-mark.png` | untracked | frontend asset | deploy-candidate dependency | Required by current `public/app.php`; without it the app shell shows a missing image. |

## Needs Human Decision Before Final Package

These files are not safe for blind inclusion. Project Director/CEO or the relevant owner must decide whether to include them, exclude them, or ask engineering for a cleaned narrow package.

| Path | Dirty state | Area | Classification | Decision needed |
| --- | --- | --- | --- | --- |
| `app/ai.php` | untracked | backend/AI | needs-human-decision | Not business-MVP core, but current `public/api.php` requires it before routing. If excluded while deploying current API, API can fatal. If included, AI API surface is production-exposed. |
| `public/service-worker.js` | modified | service-worker/cache | needs-human-decision | Cache cleanup can help asset rollout, but it changes client cache/rollback behavior. Include only with Frontend/Deploy owner rollback instruction. |
| `public/index.php` | modified | public landing/runtime entry | needs-human-decision | Branding/cache-busting/OG changes outside core app path. Include only if production root landing is in the accepted deploy scope. |
| `public/favicon.ico` | modified | frontend asset | needs-human-decision | Branding/PWA asset, not business-MVP runtime-critical. |
| `public/assets/favicon.ico` | modified | frontend asset | needs-human-decision | Branding/PWA asset, not business-MVP runtime-critical. |
| `public/assets/favicon-16x16.png` | modified | frontend asset | needs-human-decision | Branding/PWA asset, not business-MVP runtime-critical. |
| `public/assets/favicon-32x32.png` | modified | frontend asset | needs-human-decision | Branding/PWA asset, not business-MVP runtime-critical. |
| `public/assets/favicon-48x48.png` | modified | frontend asset | needs-human-decision | Branding/PWA asset, not business-MVP runtime-critical. |
| `public/assets/favicon-64x64.png` | modified | frontend asset | needs-human-decision | Branding/PWA asset, not business-MVP runtime-critical. |
| `public/assets/apple-touch-icon.png` | modified | frontend asset | needs-human-decision | Branding/PWA asset, not business-MVP runtime-critical. |
| `public/assets/icon-180.png` | modified | frontend asset | needs-human-decision | PWA/icon asset. Include only if brand/PWA refresh is accepted. |
| `public/assets/icon-192.png` | modified | frontend asset | needs-human-decision | PWA/icon asset. Include only if brand/PWA refresh is accepted. |
| `public/assets/icon-512.png` | modified | frontend asset | needs-human-decision | PWA/icon asset. Include only if brand/PWA refresh is accepted. |
| `public/assets/icon-maskable-512.png` | modified | frontend asset | needs-human-decision | PWA/icon asset. Include only if brand/PWA refresh is accepted. |
| `public/assets/brand-logo.png` | untracked | frontend asset | needs-human-decision | Brand asset. Not referenced by the checked MVP app shell; include only with brand/root landing scope. |
| `public/assets/brand-og.png` | untracked | frontend asset | needs-human-decision | Referenced by modified `public/index.php`; include only if root landing/OG changes are deployed. |

## Exclude From Production Runtime Deploy By Default

These are excluded from the narrow production runtime bundle. They may remain useful as project documentation or local QA support, but they should not be uploaded as app runtime.

### Local Reset, Local Support, Test Evidence

| Path | Dirty state | Area | Classification | Notes |
| --- | --- | --- | --- | --- |
| `public/reset-local.php` | untracked | local reset | exclude | Local reset utility. Do not expose on production. |
| `scripts/start-local.sh` | untracked | local support | exclude | Local server helper. Not production runtime. |
| `scripts/local-smoke.php` | modified | test/smoke | exclude from production runtime | Useful for local/staging/QA smoke if CLI PHP is available. Do not upload to production web surface by default. |
| `test-results/.last-run.json` | untracked | test output | exclude | Local test runner state. Not deploy material. |

### Documentation Files

| Path or group | Dirty state | Area | Classification | Notes |
| --- | --- | --- | --- | --- |
| `docs/HANDOFF_FULL_PRODUCT_2026-05-21.md` | modified | docs | exclude | Documentation only. |
| `docs/HANDOFF_ON_THE_GO_INTERMEDIATE_PAGE_2026-05-21.md` | modified | docs | exclude | Documentation only. |
| `docs/STEP3_ADVANCES_2026-05-20.md` | modified | docs | exclude | Documentation only. |
| `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md` | untracked | docs | exclude | Documentation only. |
| `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md` | untracked | docs | exclude | Documentation only. |
| `docs/IPHONE_NOTES_UX_ALGORITHMS_2026-05-21.md` | untracked | docs | exclude | Documentation only. |
| `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md` | untracked | docs | exclude | Documentation only. |
| `docs/KNOWLEDGE_LIVE_REPORTS_ADVANCES_GROUPS_2026-05-21.md` | untracked | docs | exclude | Documentation only. |
| `docs/KNOWLEDGE_UX_CSS_GLASS_RULE_2026-05-21.md` | untracked | docs | exclude | Documentation only. |
| `docs/USER_MESSAGES_DIGEST_2026-05-22.md` | untracked | docs | exclude | Documentation only. |
| `docs/AI_TEAM/*.md`, `docs/AI_TEAM/*.html`, `docs/AI_TEAM/*.docx` | untracked | docs/control plane | exclude | Team coordination evidence, not app runtime. Includes this candidate report. |
| `docs/AI_TEAM/roles/**` | untracked | docs/control plane | exclude | Role reports and handoffs, not app runtime. |
| `docs/AI_TEAM/templates/**` | untracked | docs/control plane | exclude | Role templates, not app runtime. |

## Dirty-Tree Classification Summary

Deploy-candidate by default:

- Backend/API: `public/api.php`, `app/auth.php`, `app/groups.php`, `app/ledger.php`, `app/on_the_go.php`, `app/advances.php`.
- Runtime SQL: `deploy/on_the_go_sessions_runtime.sql` as controlled migration only.
- Frontend runtime: `public/app.php`, `public/assets/app.js`, `public/assets/app.css`, `public/assets/i18n.js`.
- Frontend asset dependency: `public/assets/brand-mark.png`.

Needs human decision:

- Backend/API dependency and scope: `app/ai.php`.
- Service worker: `public/service-worker.js`.
- Public root/branding/PWA assets: `public/index.php`, favicon/icon files, `public/assets/brand-logo.png`, `public/assets/brand-og.png`.

Exclude by default:

- Local reset/support/test files: `public/reset-local.php`, `scripts/start-local.sh`, `scripts/local-smoke.php`, `test-results/.last-run.json`.
- All docs/control-plane files under `docs/` and `docs/AI_TEAM/`.

## P0 Package Blockers

1. `public/api.php` requires `app/ai.php`.
   - Resolved for the local artifact: `app/ai.php` is included as an explicit dependency.
   - Scope control: AI remains Advanced/staged surface, not a financial formula or money-core MVP requirement.

2. `public/assets/app.js` includes AI/audit UI calls.
   - Resolved for the local artifact: accepted as part of the current product surface already proven by business-MVP residual QA.
   - Scope control: this does not change financial formulas or release gates.

3. Runtime SQL must be applied or proven compatible before PHP upload.
   - `deploy/on_the_go_sessions_runtime.sql` has schema required by Field Combat/session/draft/proof state.
   - Confirm database engine/version and syntax compatibility first.

4. Service worker decision affects rollback.
   - Resolved for the local artifact: `public/service-worker.js` is included.
   - Rollback control remains active: browser cache/service-worker cleanup is required if smoke fails.

5. Backup, rollback, and production smoke are still missing.
   - This report does not authorize upload.

## Proposed Deploy Sequence After Approval

1. Project Director selects final package mode: narrow MVP runtime bundle or CEO-approved full dirty-tree deploy.
2. Backup production files for every selected path.
3. Backup production database and uploaded-document storage.
4. Confirm production DB engine/version and schema prerequisites.
5. Apply or prove `deploy/on_the_go_sessions_runtime.sql` on the target runtime.
6. Upload only the approved selected files.
7. Clear PHP/opcache if applicable.
8. Handle service worker/cache only if `public/service-worker.js` is included.
9. Run production smoke with approved test account/group/report ids.
10. Record deployed file list, SQL state, smoke result, and rollback decision without secrets.

## Final Candidate Position

The narrow MVP runtime bundle is the default candidate, but it is not final-upload-ready until Project Director resolves the AI/API dependency and service-worker/branding decisions.

Full dirty-tree deploy is not recommended by default. It is acceptable only as an explicit CEO/Project Director decision with the same backup, migration, rollback, and smoke controls.
