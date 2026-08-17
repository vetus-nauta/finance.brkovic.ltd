# SPRINT-61R — v2 Parity Export and Compare Gate

Date: 2026-08-13
Status: implemented locally / expected mismatch proven

## Director Sprint Opening

Sprint:
SPRINT-61R — v2 Parity Export and Compare Gate

Goal:
Create read-only parity tooling that can compare current MySQL/MariaDB v2 truth against MongoDB Atlas v2 collections before any persistence cutover.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-60R-atlas-foundation-restoration.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `app/db.php`
- `scripts/atlas_connection_smoke.js`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`

Agents assigned:

- Data and Backend Core Agent
- Security and Privacy Agent
- QA, Audit, and Financial Logic Agent

Agent tasks:

- Data and Backend Core Agent: identify parity scope and required entity counts/hashes.
- Security and Privacy Agent: ensure parity exports do not print secrets, passwords, tokens, or raw Mongo URI.
- QA, Audit, and Financial Logic Agent: define failure mode and prove current Atlas gap without mutating data.

Expected reports:

- Read-only exporter behavior.
- Comparator behavior.
- Current mismatch evidence.

Exit criteria:

- MySQL parity export exists and runs read-only.
- Atlas parity export exists and runs read-only.
- Comparator exists and reports mismatch with non-zero exit when databases differ.
- Current mismatch is expected and documented.
- Claudia Z arithmetic remains unchanged.

Risks:

- Parity export must not expose raw operational feed in terminal output.
- Auth sessions and one-time auth codes must not be exported.
- Comparator must not be treated as migration tooling.

## Implementation

Files added:

- `scripts/v2_mysql_parity_export.php`
- `scripts/v2_atlas_parity_export.js`
- `scripts/v2_compare_parity_exports.js`

Files updated:

- `package.json`

New scripts:

```bash
npm run parity:v2:mysql
npm run parity:v2:atlas
npm run parity:v2:compare -- <left-export.json> <right-export.json>
```

## Export Rules

MySQL export:

- Exports all `v2_*` tables plus `users`.
- Omits `sessions` and `auth_codes`.
- Omits `users.password_hash`.
- Converts `users.email` into lowercase SHA-256 hash.
- Stores row keys and row hashes, not readable raw row bodies.
- Adds Claudia Z cash business summary.

Atlas export:

- Expects the same v2 collection names as MySQL table names.
- Exports row keys and row hashes.
- Omits Mongo `_id`.
- Omits `users.password_hash`.
- Converts `users.email` into lowercase SHA-256 hash.
- Records old non-v2 Atlas collections separately as legacy collections.

Comparator:

- Compares count, table hash, missing keys, extra keys, and changed row hashes.
- Returns exit code `0` only when exports match.
- Returns exit code `1` when mismatch exists.

## Evidence

Commands:

```bash
npm run parity:v2:mysql
npm run parity:v2:atlas
node scripts/v2_compare_parity_exports.js "$MYSQL_EXPORT" "$ATLAS_EXPORT"
```

Artifacts:

- `storage/production-audits/v2-parity-export-20260813-082332/mysql-parity-export.json`
- `storage/production-audits/v2-parity-export-atlas-20260813082335/atlas-parity-export.json`
- `storage/production-audits/v2-parity-latest-compare.json`

Current expected mismatch:

- `users`: MySQL `99`, Atlas `1`
- `v2_entries`: MySQL `1638`, Atlas `0`
- `v2_report_batches`: MySQL `8`, Atlas `0`
- `v2_import_rows`: MySQL `3507`, Atlas `0`
- `v2_dictionary_training_decisions`: MySQL `111`, Atlas `0`
- `v2_audit_log`: MySQL `2763`, Atlas `0`

Comparator result:

- `mismatch_count`: `22`
- exit code: `1`

## Director Decision

SPRINT-61R is accepted locally as a parity gate.

The red comparator is the correct current result. It proves that Atlas connection is healthy but Atlas is not yet a v2 data peer.

No runtime cutover is allowed until SPRINT-62R migration dry-run and later commit/parity pass.

## Next Sprint

SPRINT-62R — v2 Atlas Migration Dry Run

Scope:

- Build a no-write migration planner from MySQL v2 tables to Atlas v2 collections.
- Preserve IDs, created sequence, reports, source links, and status fields.
- Define money precision policy.
- Produce insert/update/noop counts and expected parity delta.
- Require explicit `--commit` for future writes.
