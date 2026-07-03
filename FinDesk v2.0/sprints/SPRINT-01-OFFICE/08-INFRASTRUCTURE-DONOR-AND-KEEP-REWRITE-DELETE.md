# Sprint 01 — Infrastructure Donor And Keep / Rewrite / Delete List

Director: Codex Director, Sprint 01
Date: 2026-07-03

## Rule

This list is Director-approved for Sprint 01 handoff only. It does not authorize application code changes in Sprint 01.

Old FinDesk is an infrastructure donor only. Similar vocabulary is not permission to reuse old finance logic.

## Approved Infrastructure Donors

| Area | Source | Decision | Condition |
| --- | --- | --- | --- |
| Root PHP wrappers | `index.php`, `app.php`, `api.php` | KEEP_AS_PATTERN | Thin require-wrapper pattern only. No old product behavior. |
| Base config loading | `app/config.php`, `app/config.local.example.php`, `app/db.php` | REWRITE_FOR_V2 | Reuse config/private override idea. Do not copy old table assumptions. |
| PDO connection pattern | `app/db.php` | REWRITE_FOR_V2 | Useful if v2 targets MariaDB. If v2 targets PostgreSQL, use equivalent clean DB layer. |
| Email-code auth shell | `app/auth.php` | REWRITE_FOR_V2 | One-time code, hashed session token, httpOnly cookie, audit event are donors. Rewrite on v2 tables and add security review. |
| JSON API input/output helpers | `app/auth.php`, `public/api.php` | REWRITE_FOR_V2 | Router/try-catch/JSON pattern only. Do not extend old action soup. |
| Private path protection | `.htaccess` | KEEP_AS_PATTERN | Revalidate on target hosting. Do not rely on `.htaccess` alone if server changes. |
| PWA metadata and shell | `public/manifest.webmanifest`, `public/service-worker.js`, public icons | REWRITE_FOR_V2 | New v2 cache prefix, start URL, orientation policy, and app identity required. |
| iOS viewport/keyboard handling | `public/assets/app.js`, `public/assets/app.css` | REWRITE_FOR_V2 | Use `visualViewport`, safe-area, internal scroll, touch target ideas only. |
| Internal scroll containment | `public/assets/app.css` | REWRITE_FOR_V2 | Use containment tactics only; v2 must enforce no body/page scroll. |
| Async UI guards | `public/assets/app.js` | REWRITE_FOR_V2 | Busy guards, autosave debounce, queued retry, and client operation ids are useful patterns. |
| Attachment/proof plumbing | `app/on_the_go.php`, `public/assets/app.js`, old proof viewer | UNKNOWN_REQUIRES_DIRECTOR | Can inspire later attachment sprint. Not finance truth. |
| Language switch shell | `public/assets/i18n.js` | REWRITE_FOR_V2 | Language normalization/fallback/persisted choice only. Old translation copy is rejected. |
| Migration/deploy discipline | `deploy/README_MIGRATION.md`, old deploy reports | KEEP_AS_PATTERN | Package selection, backups, preflight, smoke, rollback, and no-secret evidence are donors. |
| DB/mail/OpenAI smoke helpers | `deploy/test_db_connection.php`, `deploy/test_php_mail.php`, `app/openai_provider.php` | REWRITE_FOR_V2 | Operator-only patterns. No secrets in repo. AI is outside v2 core. |
| QA/audit harness style | `scripts/findesk_runtime_audit.cjs`, `tests/findesk-runtime-audit.spec.js` | REWRITE_FOR_V2 | Use Playwright/viewport/artifact pattern only. Replace old scenarios. |

## Explicit Rewrite List

These may donate ideas but must be rewritten cleanly under v2 contracts:

- clean v2 API boundary; do not add v2 core actions to legacy `public/api.php`;
- v2 DB layer and migrations;
- v2 auth/session/audit tables;
- v2 parser and category engine;
- v2 PWA/service-worker/manifest;
- v2 iOS/desktop layout shell;
- v2 test harness and fixtures;
- v2 language dictionaries and parser rules;
- v2 import traceability pipeline.

## Explicit Do-Not-Reuse List

These are rejected as FinDesk v2.0 truth:

- `app/ledger.php`
- `app/on_the_go.php`
- `app/advances.php`
- `app/findesk_phase2.php`
- `app/ai.php` for finance analysis
- `server/findesk-atlas-server.js`
- `scripts/cash_session_math_audit.js`
- legacy actions in `public/api.php`
- `deploy/ledger_foundation.sql`
- `deploy/categories_foundation.sql`
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `deploy/advances_foundation.sql`
- `deploy/findesk_phase2_foundation.sql`
- old `ledger_entries`, `ledger_categories`, `on_the_go_*`, `cash_advances`, `findesk_*` tables
- old `income/expense` plus `cash/noncash` model
- old report/final package/snapshot formulas
- old dashboard/module navigation
- old Captain, On the Go, Advanced, Business Desk, Yacht surfaces as v2 product direction
- old category names as v2 category codes
- `docs/` and `docs/AI_TEAM/` as product authority.

## Keep / Rewrite / Delete Policy

| Action | Director decision |
| --- | --- |
| Keep in repository | Yes. Old files remain as donor/archive evidence. |
| Delete during Sprint 01 | No. No destructive cleanup approved. |
| Rewrite for v2 | Yes, in later sprints, inside a clean v2 namespace. |
| Import old formulas | No. |
| Import old rows/artifacts | Later only as traceable legacy source rows, not as formulas or product truth. |
| Use old docs as v2 authority | No. |

## Scope Decisions

- Business Desk/proforma: `UNKNOWN_REQUIRES_DIRECTOR`; out of v2 core until explicitly scoped.
- Yacht provisioning/pricing: `UNKNOWN_REQUIRES_DIRECTOR`; out of universal v2 finance core until explicitly scoped.
- Mongo Atlas server: not v2 core; only URI masking/smoke ideas may be reused if a future Mongo path is explicitly approved.

## Sprint 02 Guardrails

- Create a clean v2 runtime/API namespace before implementation.
- Do not extend old `public/api.php` for v2 finance core.
- Do not run old deploy SQL as v2 schema.
- Do not use old parser/category/report logic.
- Decide DB engine before writing migrations.
