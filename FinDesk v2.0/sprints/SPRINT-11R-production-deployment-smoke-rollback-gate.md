# SPRINT-11R — Production Deployment Smoke and Rollback Gate

Status: Rejected / Blocked pending live production evidence

## Director Sprint Opening

Sprint:
SPRINT-11R — Production Deployment Smoke and Rollback Gate

Goal:
Verify that the accepted FinDesk v2.0 release candidate can be deployed safely to production without exposing private code, logs, secrets, or attachment storage, and capture rollback requirements. This sprint is a deployment gate only, not a product feature sprint.

Required files read:
- `FinDesk v2.0/START_HERE_DIRECTOR.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/24-secrets-hosting-access-inventory.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-10R-mvp-release-candidate-gate.md`
- `.htaccess`
- `.gitignore`
- `app/config.php`
- `app/auth.php`
- `app/db.php`
- `public/v2.php`
- `public/v2-api.php`
- `deploy/auth_foundation.sql`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `scripts/v2_auth_security_smoke.sh`
- `scripts/v2_disposable_db_smoke.sh`
- `scripts/v2_http_api_smoke.sh`

Agents assigned:
- Data and Backend Core Agent: Laplace
- QA, Audit, and Acceptance Agent: Galileo
- Security and Privacy Agent: Copernicus

Agent tasks:
- Data and Backend Core Agent: verify production schema/config/API/auth/storage readiness and identify deployment blockers.
- QA, Audit, and Acceptance Agent: map deploy-gate acceptance to reproducible checks and reject unverifiable claims.
- Security and Privacy Agent: verify secret/log/storage exposure risks, especially `storage/logs/auth_codes.log` and direct HTTP access to private paths.

Expected reports:
- Each agent must return ACCEPT or REJECT with concrete blockers, evidence, and checks.
- Director must not close the sprint without all three reports.

Exit criteria:
- Static deploy preflight passes.
- Full SPRINT-10R release-candidate gate remains green after deploy-gate additions.
- Production URL deny checks are defined and ready to run without exposing secrets.
- Production deploy cleanup explicitly covers purging any old plaintext `storage/logs/auth_codes.log`.
- Rollback steps are captured.
- No product features, dashboard-first UX, formula changes, or old FinDesk product logic are introduced.

Risks:
- Actual production Apache/docroot behavior can differ from local repository assumptions.
- Local ignored files under `storage/` must never be copied to production as deploy payload.
- Live production checks require an explicit production base URL and authenticated smoke procedure.

## Director Notes

Local inspection confirmed:
- The repository root `.htaccess` denies `/app`, `/storage`, `/deploy`, and `/cron`.
- `app/config.php` uses HTTPS `app_url` and repository-level `storage_path`.
- `app/config.local.php`, `storage/`, local secrets, and `storage/logs/auth_codes.log` are ignored by Git and not tracked.
- A local `storage/logs/auth_codes.log` exists on this workstation; it is not tracked, but production deploy must purge or avoid it.

Added deploy preflight:
- `scripts/v2_production_deploy_preflight.sh`
- `npm run smoke:v2:deploy`

The preflight checks static deployment guardrails locally and can run live deny checks when `FINDESK_V2_PRODUCTION_BASE_URL` is set. In production mode, set `FINDESK_V2_PREFLIGHT_ENV=production`; then any existing `storage/logs/auth_codes.log` is a hard failure.

## Agent Reports

Data and Backend Core Agent:
REJECT. Local backend foundation is green, but there is no production-safe smoke evidence against the real host, and no live rollback proof yet. The agent confirmed schema idempotence, HTTPS app config, auth-cookie posture, and local disposable DB/API/auth smokes.

Final documentation/preflight re-review:
ACCEPT. Rollback blocker was resolved by preferring release-directory symlink rollback and adding an in-place fallback that extracts into an empty temp directory and uses `rsync --delete` while preserving runtime config and storage.

QA, Audit, and Acceptance Agent:
REJECT. SPRINT-10R accepted a release candidate from disposable setup only, not a production deployment. Acceptance requires a SPRINT-11R opening, deploy-gate evidence, production smoke results, rollback proof, and all agent reports.

Final documentation/preflight re-review:
ACCEPT. The sprint doc keeps production deployment rejected/blocked pending live evidence and records required checks, safer backup location, rollback criteria, and handoff.

Security and Privacy Agent:
REJECT. Production cannot be accepted until live server checks and cleanup are proven. Blockers include local old `storage/logs/auth_codes.log`, unproven production denial for `/storage/v2/attachments/...`, and raw deploy risk from ignored local secrets/storage/backups.

Final documentation/preflight re-review:
ACCEPT. Security blockers in the documentation/preflight gate were resolved by blocking backup/archive/SQL/env/Git paths in `.htaccess`, requiring production base URL in production-mode preflight, and moving backup artifacts outside the web-served deploy path.

## Checks Run

Local deploy preflight:
```bash
bash -n scripts/v2_production_deploy_preflight.sh
npm run smoke:v2:deploy
```

Result:
- PASS: required files exist.
- PASS: root `.htaccess` disables indexes and blocks private/runtime directories, deploy archives, SQL dumps, env files, and Git metadata.
- PASS: `public/v2.php` is `noindex,nofollow`.
- PASS: production config shape uses HTTPS `app_url` and repository-level `storage_path`.
- PASS: `app/config.local.php`, local auth logs, local secrets, and v2 attachment examples are ignored and untracked.
- WARN: local `storage/logs/auth_codes.log` exists and must be purged/avoided on production.
- WARN: `FINDESK_V2_PRODUCTION_BASE_URL` is not set, so live HTTP deny checks were skipped.

Release-candidate regression gate after deploy-preflight addition:
```bash
npm run smoke:v2 &&
npm run smoke:v2:auth &&
npm run test:v2:fixtures &&
npm run smoke:v2:http &&
npm run smoke:v2:db &&
npm run smoke:v2:ui &&
npm run smoke:v2:browser
```

Result:
PASS. Browser screenshots were written to `test-results/v2-browser-smoke`.

## Production Deployment Runbook

This runbook is required evidence for the next Director. Do not accept production deployment from local checks alone.

Pre-deploy local gate:
```bash
npm run smoke:v2 &&
npm run smoke:v2:auth &&
npm run test:v2:fixtures &&
npm run smoke:v2:http &&
npm run smoke:v2:db &&
npm run smoke:v2:ui &&
npm run smoke:v2:browser &&
npm run smoke:v2:deploy
```

Deploy payload exclusions:
- Do not deploy `backups/`.
- Do not deploy `storage/logs/`.
- Do not deploy `storage/secrets/`.
- Do not deploy `storage/reset-backups/`.
- Do not deploy `app/config.local.php` or `app/config.local.php.*`.
- Do not deploy `.env` or `.env.*`.
- Do not deploy `node_modules/`, `test-results/`, local harness output, or local smoke artifacts.
- Do not deploy old Google Drive archive material as implementation proof.

Production backup before deploy:
```bash
# Run on production host or hosting panel equivalent.
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="${HOME}/findesk-v2-deploy-backups/${STAMP}"
mkdir -p "${BACKUP_DIR}"
chmod 700 "${HOME}/findesk-v2-deploy-backups" "${BACKUP_DIR}"

tar -czf "${BACKUP_DIR}/backup-files-before-findesk-v2-${STAMP}.tar.gz" \
  --exclude='storage/logs/auth_codes.log' \
  --exclude='storage/logs' \
  --exclude='storage/secrets' \
  --exclude='storage/reset-backups' \
  --exclude='backups' \
  --exclude='zip-archives' \
  --exclude='test-results' \
  --exclude='node_modules' \
  --exclude='app/config.local.php' \
  --exclude='app/config.local.php.*' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='*.sql' \
  --exclude='*.tar.gz' \
  --exclude='*.zip' \
  -C "$DEPLOY_PATH" .
mysqldump --single-transaction --routines --triggers "$PRODUCTION_DB_NAME" \
  > "${BACKUP_DIR}/backup-db-before-findesk-v2-${STAMP}.sql"
sha256sum "${BACKUP_DIR}/backup-files-before-findesk-v2-${STAMP}.tar.gz" \
  "${BACKUP_DIR}/backup-db-before-findesk-v2-${STAMP}.sql" \
  > "${BACKUP_DIR}/SHA256SUMS"
```

Backup artifacts must stay outside the web-served deploy path. Do not place `.tar.gz`, `.sql`, `.zip`, `.bak`, or `.env*` files under the production document root.

Schema apply order:
```bash
php deploy/run_sql_file.php deploy/auth_foundation.sql
php deploy/run_sql_file.php "FinDesk v2.0/sql/001-clean-core-mariadb.sql"
```

Production cleanup before acceptance:
```bash
rm -f storage/logs/auth_codes.log
mkdir -p storage/v2/attachments
```

Production live deny checks:
```bash
FINDESK_V2_PREFLIGHT_ENV=production \
FINDESK_V2_PRODUCTION_BASE_URL=https://finance.brkovic.ltd \
npm run smoke:v2:deploy
```

Required live results:
- `/storage/v2/attachments/preflight-deny-check.txt` returns `401`, `403`, or `404`.
- `/storage/logs/auth_codes.log` returns `401`, `403`, or `404`.
- `/app/config.php` returns `401`, `403`, or `404`.
- `/deploy/auth_foundation.sql` returns `401`, `403`, or `404`.
- `/backups/preflight-deny-check.sql` returns `401`, `403`, or `404`.
- `/backup-db-before-findesk-v2-preflight.sql` returns `401`, `403`, or `404`.
- `/backup-files-before-findesk-v2-preflight.tar.gz` returns `401`, `403`, or `404`.
- `storage/logs/auth_codes.log` is absent on the production filesystem after login-code request.

Production auth smoke:
- Use real SMTP delivery or a preseeded non-secret smoke user/session.
- Do not print login codes, session cookies, DB credentials, SMTP passwords, or full SMTP errors into logs.
- Verify `request_code` does not return `dev_code`.
- Verify login/session cookie has `Secure`, `HttpOnly`, and `SameSite=Lax`.
- Verify `/v2-api.php` returns `401 not_authenticated` without a session.

Production v2 smoke:
- Use a dedicated smoke workspace/user.
- Create workspace if needed.
- Verify default Cash/Card flows.
- Create, read, update, and archive one cash entry.
- Verify balance recalculation after update/archive.
- Upload, list, and delete one small attachment.
- Confirm unauthenticated direct HTTP access to that attachment path is denied.
- Delete/archive smoke data if the procedure creates persistent records.

Rollback steps:
```bash
# 1. Put the site into maintenance mode through hosting controls if available.

# 2. Preferred rollback: switch the web root/current symlink back to the previous release directory.
# If release-directory deploy is available, do not do an in-place restore.

# 3. In-place fallback only: restore into an empty temp directory first, then sync with deletion.
ROLLBACK_EXTRACT="$(mktemp -d "${HOME}/findesk-v2-rollback-XXXXXX")"
tar -xzf "${BACKUP_DIR}/backup-files-before-findesk-v2-YYYYMMDDTHHMMSSZ.tar.gz" -C "${ROLLBACK_EXTRACT}"
rsync -a --delete \
  --exclude='app/config.local.php' \
  --exclude='app/config.local.php.*' \
  --exclude='storage/' \
  "${ROLLBACK_EXTRACT}/" "$DEPLOY_PATH/"

# 4. Restore the DB backup only if the deploy altered production data/schema in a way that must be reverted.
mysql "$PRODUCTION_DB_NAME" < "${BACKUP_DIR}/backup-db-before-findesk-v2-YYYYMMDDTHHMMSSZ.sql"

# 5. Re-run deny checks for /app, /deploy, and /storage.
# 6. Verify previous production surface still loads.
# 7. Record rollback timestamp, backup names, checksum verification, and operator.
```

The in-place fallback must use deletion semantics (`rsync --delete` or equivalent) so newly deployed files such as `app/v2`, `public/v2.php`, and `public/v2-api.php` do not survive rollback accidentally. Preserve production runtime config and storage with explicit excludes.

Rollback acceptance criteria:
- Previous production surface loads.
- `/app`, `/deploy`, and `/storage` remain private.
- No plaintext `storage/logs/auth_codes.log` is public.
- DB restore checksum/source backup is recorded.
- Operator records exact commit or file package that was restored.

## Director Final Handoff

Sprint:
SPRINT-11R — Production Deployment Smoke and Rollback Gate

Status:
Rejected / Blocked pending live production evidence

Agents assigned:
- Data and Backend Core Agent: Laplace
- QA, Audit, and Acceptance Agent: Galileo
- Security and Privacy Agent: Copernicus

Agent reports received:
- Data and Backend Core Agent: initial REJECT for live production acceptance; final ACCEPT for documentation/preflight readiness.
- QA, Audit, and Acceptance Agent: initial REJECT for live production acceptance; final ACCEPT for documentation/preflight readiness.
- Security and Privacy Agent: initial REJECT for live production acceptance; final ACCEPT for documentation/preflight readiness.

Accepted work:
- SPRINT-11R opening created under the required agent-orchestrated protocol.
- Local static deploy preflight added as `npm run smoke:v2:deploy`.
- Local release-candidate gate remains green after deploy-preflight addition.
- Production deployment runbook and rollback requirements are recorded.

Rejected work:
- Production deployment is not accepted.
- Live production storage/auth/log denial is not proven.
- Production rollback has not been executed or evidenced.
- No production-safe authenticated v2 smoke has been run on the real host.

Files changed:
- `FinDesk v2.0/sprints/SPRINT-11R-production-deployment-smoke-rollback-gate.md`
- `.htaccess`
- `package.json`
- `scripts/v2_production_deploy_preflight.sh`

Tests or checks:
- `bash -n scripts/v2_production_deploy_preflight.sh`
- `npm run smoke:v2:deploy`
- `FINDESK_V2_PREFLIGHT_ENV=production bash scripts/v2_production_deploy_preflight.sh` fails as expected without purged auth log and production base URL.
- `npm run smoke:v2`
- `npm run smoke:v2:auth`
- `npm run test:v2:fixtures`
- `npm run smoke:v2:http`
- `npm run smoke:v2:db`
- `npm run smoke:v2:ui`
- `npm run smoke:v2:browser`

Risks:
- Actual production docroot/Apache behavior is still unverified.
- A raw FTP/rsync deploy from the local workspace could accidentally include ignored local files if exclusions are not enforced.
- The local workstation contains old ignored auth-code logs; production must purge/avoid them.
- Production SMTP/auth smoke still needs real delivery or a safe preseeded session method.

What must not be touched:
- Do not add dashboard-first UX.
- Do not change financial formulas.
- Do not reuse old FinDesk business logic as product truth.
- Do not deploy local `storage/`, `backups/`, secrets, `.env`, or `app/config.local.php`.
- Do not accept production without live URL evidence and rollback artifacts.

Next sprint:
Continue SPRINT-11R with production access/evidence, or open SPRINT-11R-B if a separate production operator will execute the live checks.

Paste-to-next-director prompt:
You are the next Director of FinDesk v2.0. Source of truth is only GitHub files. Start with `FinDesk v2.0/START_HERE_DIRECTOR.md`, then read `FinDesk v2.0/sprints/SPRINT-11R-production-deployment-smoke-rollback-gate.md`. SPRINT-10R accepted the Clean Core MVP as a local/disposable release candidate. SPRINT-11R is currently rejected/blocked because live production evidence is missing. Do not add product features. Obtain production deploy context, run the local RC gate, deploy with exclusions, back up files and DB, apply `deploy/auth_foundation.sql` and `FinDesk v2.0/sql/001-clean-core-mariadb.sql`, purge `storage/logs/auth_codes.log`, run `FINDESK_V2_PREFLIGHT_ENV=production FINDESK_V2_PRODUCTION_BASE_URL=https://finance.brkovic.ltd npm run smoke:v2:deploy`, run production-safe auth and v2 smokes without leaking secrets, capture rollback artifacts/checksums, then reassign agents and accept or reject based on evidence.
