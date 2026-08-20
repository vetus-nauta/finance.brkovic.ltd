# Current State Audit

Date: 2026-08-20

## Scope

This audit covers the current `finance.brkovic.ltd` repository state before creating a new long-term architecture for `brkovic.app`.

Source materials:

- Google Drive brief: `ТЗ для Codex — Фундамент архитектуры finance.brkovic.ltd`
- Current repository files
- `README.md`
- `FinDesk v2.0/`
- `app/`, `public/`, `deploy/`, `scripts/`, `server/`

## Current Runtime Shape

The live product is a PHP FinDesk v2 application.

Main runtime files:

- UI: `public/v2.php`
- API: `public/v2-api.php`
- Auth bridge: `public/api.php` and `app/auth.php`
- Database bridge: `app/db.php`
- Domain/application logic: `app/v2/Repository.php`, `app/v2/Api.php`
- Report view: `public/v2-report.php`

Current primary database access uses PHP PDO against MySQL/MariaDB:

- `app/db.php` builds a MySQL DSN from `app/config.local.php`.
- `app/v2/Database.php` returns the shared PDO connection.
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql` is the main schema source for v2 clean core.

## Current Product Capabilities

FinDesk v2 already contains important product logic:

- email-code authentication
- workspace hall
- workspace members and invites
- role/access scope logic
- operational ledger entries
- cash/card flows
- responsive operational journal
- structured check view
- archive/month switching
- month close/reopen/correction
- accountable money workflow
- quick notes and Mr. Smith migration
- dictionary training
- imports/raw history
- attachments
- report fragments/packages/html snapshots
- Layer 1 summary/reporting
- audit calls inside repository methods

This logic is valuable as legacy product truth, but the current implementation is not the target architecture for web + iOS + Android.

## Current Architecture Risks

1. PHP/MySQL monolith

   Business logic, persistence, authorization, report generation, and UI-specific workflows are concentrated in PHP repository/API code. This is workable for the current web app, but not a clean shared backend foundation for web, iOS, and Android.

2. Runtime schema evolution inside application code

   `Repository.php` contains several `ensure*Schema` methods that create or alter tables at runtime. This makes production schema drift harder to audit. Target architecture must use ordered migrations only.

3. MySQL/MariaDB target

   The new architecture brief requires PostgreSQL/Supabase as the foundation. Current SQL is MariaDB/MySQL-specific and must be mapped, not reused directly.

4. Atlas parity path conflicts with new target

   The current README and scripts include MongoDB Atlas parity/cutover tooling. The new architecture brief explicitly excludes MongoDB Atlas from the foundation unless a future ADR proves necessity. Atlas is now legacy/parity context only, not target architecture.

5. File storage is local runtime storage

   Attachments are written under `storage/v2/attachments/...` and referenced from database rows. Target architecture requires private object storage with metadata in PostgreSQL and signed URLs.

6. Auth is custom PHP session/code flow

   Current email code UX is valuable, but the new foundation should use Supabase Auth and preserve the UX through standard auth/session flows.

7. No PostgreSQL RLS boundary

   Current access control is mostly application-level checks. Target architecture requires PostgreSQL RLS plus server-side authorization plus UI visibility.

8. Secrets hygiene needs strict enforcement

   `.gitignore` excludes local config and env files. Existing local secret files must never be committed. Any credentials historically committed must be rotated before production cutover.

9. Legacy directories and historical artifacts

   The repository contains backups, old scripts, Atlas scripts, and previous sprint artifacts. They are useful for migration research but must not become product truth for the new foundation.

## Current Tests And QA

Current v2 test surface includes:

- PHP static smoke
- auth security smoke
- HTTP API smoke
- browser UI smoke
- manual responsive walkthrough
- fixture runner
- Atlas parity/runtime scripts

Recent accepted checks for the current PHP v2 state:

- `npm run test:v2:fixtures`
- `npm run smoke:v2:http`
- `npm run smoke:v2:browser`
- `npm run smoke:v2:manual-responsive`

These tests are valuable as legacy behavior evidence. The new foundation needs its own test ladder: unit, API, RLS, migration, E2E, deployment smoke.

## Immediate Director Decisions

1. Do not delete current PHP FinDesk.
2. Do not cut over `finance.brkovic.ltd` during foundation work.
3. Use `brkovic.app` as the clean future product domain.
4. Create the new foundation in parallel.
5. Treat Atlas as non-target legacy/parity infrastructure.
6. Move toward PostgreSQL/Supabase, Next.js, Expo, shared TypeScript packages, RLS, private storage, and command API boundaries.
