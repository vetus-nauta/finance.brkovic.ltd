# SPRINT-60R — Atlas Foundation Restoration

Date: 2026-08-13
Status: opened / audit evidence collected / implementation not yet accepted

## Director Sprint Opening

Sprint:
SPRINT-60R — Atlas Foundation Restoration

Goal:
Restore MongoDB Atlas as the FinDesk v2 persistence foundation without losing the current MySQL/MariaDB operational truth, Claudia Z arithmetic, report batches, dictionary training, hall roles, or accountable workflow.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/24-secrets-hosting-access-inventory.md`
- `public/v2-api.php`
- `app/v2/Database.php`
- `app/v2/Repository.php`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`

Retired context:

- Old `docs/AI_TEAM/*` files and `server/findesk-atlas-server.js` were used only as historical migration clues during this sprint.
- They are not FinDesk v2 product truth and must not be used as active implementation sources after v2 canonicalization.

Agents assigned:

- Data and Backend Core Agent
- Security and Privacy Agent
- QA, Audit, and Financial Logic Agent

Agent tasks:

- Data and Backend Core Agent: map current PHP/MySQL v2 runtime, old Atlas connector, required v2 collections, migration architecture, and data risks.
- Security and Privacy Agent: check secret handling, Atlas access, FTP/deploy risks, direct-root deployment risks, and production checklist.
- QA, Audit, and Financial Logic Agent: define parity gates for Claudia Z, reports, entries, closed periods, accountable flows, and Atlas cutover acceptance.

Expected reports:

- Current persistence truth map.
- Atlas gap map.
- Security checklist.
- Financial and operational parity criteria.
- Safe migration path.

Exit criteria:

- Atlas connection verified without exposing secrets.
- Current MySQL/MariaDB v2 model audited read-only.
- Current Atlas model audited read-only.
- Claudia Z cash chain verified before any migration.
- Decision recorded that Atlas is the target foundation but MySQL remains source of truth until parity migration is proven.
- No live runtime switch before backup, migration, and parity evidence.

Risks:

- Current Atlas collections are not compatible with the current v2 schema.
- Current PHP runtime uses PDO/MySQL and has no Mongo repository layer.
- Direct cutover would lose or orphan entries, report batch links, dictionary decisions, accountable reports, and month closures.
- Money values must not be migrated through floating-point arithmetic.
- Old v1 and old Atlas shell code must not become product truth again.

## Agent Reports Received

### Data and Backend Core Agent

Verdict:
Atlas is a verified target foundation and connection channel, but the old Atlas connector is not the current v2 backend.

Findings:

- Current v2 runtime enters through `public/v2-api.php`, `app/v2/Database.php`, and `app/v2/Repository.php`.
- Current runtime uses MySQL/MariaDB via PDO.
- Current v2 has 28 `v2_*` tables locally.
- Current local MySQL counts include:
  - `v2_entries`: 1638
  - `v2_import_rows`: 3507
  - `v2_report_batch_entries`: 629
  - `v2_audit_log`: 2763
  - `v2_workspaces`: 22
- Old Atlas connector uses only shell-level collections: `users`, `workspaces`, `yacht_states`, `cash_sessions`, `yacht_price_snapshots`, `workspace_audit`, `counters`.
- Old Atlas `workspaces` are not schema-compatible with current `v2_workspaces` and `v2_workspace_members`.

Recommendation:

- Do not switch runtime by config.
- Create dedicated v2 Atlas collections or a clearly namespaced v2 document model.
- Write idempotent exporter/importer and parity comparator.
- Preserve UUIDs, `created_seq`, dates, statuses, report links, `content_hash`, source row links, and audit trail.
- Store money as Decimal128 or canonical decimal strings, never binary floats.
- Build a v2 Atlas API/repository with route parity before production cutover.

### Security and Privacy Agent

Verdict:
No committed Atlas/FTP secrets were found in tracked files, but production hardening is required before cutover.

Findings:

- Atlas URI is local-only: `storage/secrets/mongodb_uri`.
- FTP credentials are env-based and must stay outside Git.
- `storage/`, `backups/`, SQL dumps, `.env`, and local config files must never be deployed or committed.
- Atlas Network Access must allow only the workstation during local migration and only production server IP for production.
- Invite tokens and full URLs must not be logged.

Accepted fixes:

- Local `app/config.local.php` permissions changed to `600`.
- Auth completion no longer returns raw exception messages to the browser.

Production checklist:

- Use separate production Atlas user with least privileges.
- Verify HTTP deny rules for private paths.
- Keep Mongo URI, FTP env, cookies, invite tokens, auth codes out of logs.
- Confirm service worker/cache does not resurrect old v1 paths.

### QA, Audit, and Financial Logic Agent

Verdict:
Atlas is reachable, but v2 data parity is not yet proven. Cutover is blocked until parity tools exist and pass.

Read-only checks:

- `npm run check:atlas` passed.
- `npm run audit:v2:claudia-z` passed:
  - opening cash: `8015.00`
  - computed cash now: `3893.00`
  - latest `balance_after`: `3893.00`
  - difference: `0.00`
  - balance mismatches: `0`

Parity criteria:

- Same workspaces, users, roles, flows, opening balances.
- Same active and archived entries with same ordering by `date ASC, created_seq ASC`.
- Same category, actor, status, source, and report link behavior.
- Same cash/card arithmetic to cent.
- Same report batches, report entries, HTML snapshots, package versions, statuses, and content hashes.
- Same monthly closure behavior.
- Same accountable offer/report/settlement behavior.
- Employee visibility remains scoped.

Required missing tools:

- MySQL parity export.
- Atlas parity export.
- Local/deploy/Atlas parity comparator.

## Evidence Collected

Artifacts:

- `storage/production-audits/v2-persistence-foundation-20260813-081919/mysql-model-audit.json`
- `storage/production-audits/v2-persistence-foundation-atlas-20260813081921./atlas-model-audit.json`
- `storage/imports/claudia-z-reconciliation/sprint-41-reconciliation-audit.json`

Commands:

```bash
npm run check:atlas
npm run audit:v2:mysql-model
npm run audit:v2:atlas-model
npm run audit:v2:claudia-z
php -l app/auth.php
php -l scripts/v2_mysql_model_audit.php
node --check scripts/v2_atlas_model_audit.js
git diff --check
```

## Director Decision

Atlas is accepted as the intended persistence foundation.

Current MySQL/MariaDB v2 runtime remains the operational source of truth until:

1. backup/export is complete;
2. v2 Atlas collections are created in a compatible model;
3. migration is idempotent;
4. parity comparison passes;
5. browser/manual QA passes against Atlas-backed runtime;
6. production cutover has rollback.

This is not a config repair. It is a controlled persistence restoration sprint.

## Next Implementation Sprints

### SPRINT-61R — v2 Parity Export and Compare Gate

Build read-only export tools:

- MySQL local/deploy parity export.
- Atlas parity export.
- Comparator with zero-tolerance financial checks.

Acceptance:

- Local MySQL export produces stable hashes.
- Atlas export works on current old collections.
- Comparator reports current expected gap without mutation.

### SPRINT-62R — v2 Atlas Migration Dry Run

Build idempotent migration dry-run:

- Map all required v2 tables.
- Preserve IDs and links.
- Generate Atlas insert/update plan without writes by default.
- Include money precision policy.

Acceptance:

- Dry-run explains every entity count.
- No write occurs without explicit `--commit`.
- Claudia Z expected final cash remains `3893.00`.
