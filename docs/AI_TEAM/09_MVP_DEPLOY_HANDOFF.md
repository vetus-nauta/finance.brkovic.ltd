# MVP Deploy Handoff

Date: 2026-05-26

Owner: Project Director

Status: business-MVP product evidence is in final gate; production deploy planning active; production deploy not executed from this handoff.

## Decision Point

MVP foundation gate is approved, and business-MVP product evidence has passed Product, QA, Frontend, and Backend/Data product-readiness checks.

Final full business-MVP product approval still belongs to Chief Auditor.

The current working tree is dirty and includes broad pre-existing changes. Do not deploy the entire tree blindly.

## Baseline

- `HEAD=72b38e6`
- `origin/main=72b38e6`
- local server `http://127.0.0.1:18889` responds `200 OK`
- `node --check public/assets/app.js` passed
- `git diff --check` passed
- CLI PHP is unavailable in the current shell, so `php scripts/local-smoke.php http://127.0.0.1:18889` is environment-blocked

## MVP Evidence

- Instant field capture QA: `20260526141856`
- Backend current/historical contract: `group_id=195`, `report_id=371`
- UI current/historical report QA: `group_id=200`, `report_id=406`
- Chief Auditor MVP gate: approved
- Field Combat no-data-loss gate: approved
- Closed group report package gate: approved, `group_id=222`, `report_id=454`
- Business MVP residual surface QA: passed, run `20260527968710`, `group_id=222`, `report_id=454`

## Runtime Areas Touched By MVP Path

- `app/ledger.php`
- `public/api.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`
- `scripts/local-smoke.php`

These files are part of the verified MVP path, but some also contain earlier work. Review the final diff before production upload.

## Broader Dirty Tree Warning

The working tree also contains modified/untracked files outside the final MVP gate path, including:

- `app/advances.php`
- `app/auth.php`
- `app/groups.php`
- `app/on_the_go.php`
- `deploy/on_the_go_sessions_runtime.sql`
- icon/brand assets
- `public/index.php`
- `public/service-worker.js`
- `app/ai.php`
- `public/reset-local.php`
- `scripts/start-local.sh`
- `test-results/`

Do not include local reset/test/support files in production unless a deployment owner explicitly approves them.

## Deployment Modes

### Mode A: Full Current Working-Tree Bundle

Use only if CEO accepts that all current dirty-tree work is part of the deploy.

Required controls:

- backup production files;
- backup production database;
- record exact file list uploaded;
- run production smoke after upload;
- keep rollback copy.

### Mode B: Narrow MVP Runtime Bundle

Use if the release should include only the verified MVP money-tree path.

Required controls:

- review diffs in the runtime files listed above;
- exclude local/test files;
- backup production files and database;
- upload only named files;
- run production smoke after upload.

## Post-Deploy QA Card

Role/chat: `QA/Release · FinDesk`

Assignment path:

- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

Expected result files:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

Short report only in chief chat.

## Production Smoke Minimum

- App loads.
- Current user/session check works.
- Fast capture controls are visible on mobile.
- Current period export is reachable.
- Closed final report list/detail/export is reachable for a newly finalized report.
- Scenario remains separated:
  - historical report: `1000 / 600 / 400`;
  - current period after finalization: carryover `400` plus current entries only.

## Current Next Step

Project Director is routing final full business-MVP gate to Chief Auditor and production readiness to Backend/Data, Frontend/UX, and QA Release Engineer.

No FTP/database production change is authorized by this handoff alone.
