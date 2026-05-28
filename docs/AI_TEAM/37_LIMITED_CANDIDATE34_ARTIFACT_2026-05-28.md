# Limited Candidate 34 Local Artifact

Date: 2026-05-28
Owner: Project Director / Deploy Owner
Status: built locally; not uploaded

## Artifact

- Path: `backups/findesk-limited-candidate34-20260528T134812Z/findesk-limited-candidate34-20260528T134812Z.tar.gz`
- SHA256: `a159c4000a580db314981529bdb3812dbed953b18b93dd9148b2e9d60f7cffd9`

## Contents

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
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `docs/AI_TEAM/36_DB_PREFLIGHT_READONLY_SQL_2026-05-28.sql`

## Excluded

- `app/ai.php`: optional Advanced AI surface; `public/api.php` now guards this module and returns `ai_unavailable` if absent.
- `public/reset-local.php`: local-only helper.
- `scripts/start-local.sh`: local-only helper.
- `test-results/`: local test output.
- branding/favicon/root SEO files: outside this limited scanner/UX/backend package.
- full `docs/AI_TEAM/**`: control-plane docs, except the DB preflight SQL included for deploy operator reference.

## Production Status

This artifact is not upload approval.

Read-only production check after artifact build:

- `https://finance.brkovic.ltd/app.php` returned HTTP `200`;
- production fallback H1 still contains `FinDesk sign-in code`;
- production asset version is still `20260528-mvpfix1`;
- artifact/local candidate uses `FinDesk access code` and `20260528-frontend-residual1`.

Production upload remains blocked until:

- DB preflight output is recorded;
- backup/rollback artifacts are recorded;
- PHP lint or approved HTTP replacement smoke is recorded;
- CEO accepts limited release without device-ready scanner claim, or real-device scanner/PWA camera QA passes;
- production smoke owner is ready.
