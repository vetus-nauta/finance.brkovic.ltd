# SPRINT-63R — v2 Atlas Document Payload Builder

Date: 2026-08-13
Status: implemented locally / no Atlas writes

## Director Sprint Opening

Sprint:
SPRINT-63R — v2 Atlas Document Payload Builder

Goal:
Build a deterministic local payload artifact for future Atlas commit, without writing to Atlas and without changing the current MySQL/MariaDB source of truth.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-60R-atlas-foundation-restoration.md`
- `FinDesk v2.0/sprints/SPRINT-61R-v2-parity-export-compare-gate.md`
- `FinDesk v2.0/sprints/SPRINT-62R-v2-atlas-migration-dry-run.md`
- `app/db.php`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/auth.php`

Agents assigned:

- Data Migration Review Agent
- QA Acceptance Review Agent
- Director as integration owner

Agent tasks:

- Data Migration Review Agent: require deterministic payload, strict v2 collection naming, no mixing with legacy Atlas shell collections.
- QA Acceptance Review Agent: require no-write evidence, money policy, excluded runtime tables, and artifact manifest.
- Director: implement payload builder and run syntax/artifact checks.

Expected reports:

- Payload scope.
- Money precision rule.
- Exclusions and safety boundaries.
- Artifact evidence.

Exit criteria:

- Payload builder creates local artifact only.
- Payload contains deterministic collection manifest.
- Runtime-only `sessions` and `auth_codes` are excluded.
- Money values are represented as canonical decimal strings.
- Future commit tool may convert to Decimal128 only after parity tests.
- No Atlas write occurs.

Risks:

- Payload artifact contains operational data and must stay local/private.
- Payload is not a migration commit.
- Future commit must validate references again immediately before write.

## Implementation

Files added:

- `scripts/v2_build_atlas_payload.php`

Files updated:

- `package.json`

New script:

```bash
npm run payload:v2:atlas
```

## Payload Rules

- Target collections keep current v2 table names.
- Old Atlas shell collections are not used as target collections.
- `users` are included because FinDesk v2 uses passwordless auth without password hashes.
- `sessions` are excluded.
- `auth_codes` are excluded.
- Money fields are canonical decimal strings in payload.
- JSON text columns are decoded into structured JSON where valid.
- Payload builder does not connect to Atlas.

## Evidence

Artifact:

- `storage/production-audits/v2-atlas-payload-20260813-083407/atlas-payload.json`

Command:

```bash
npm run payload:v2:atlas
```

Result:

- collections: `29`
- documents: `9196`
- payload hash: `97d3880cf43dbdbc5be563b8936d3fb2cdae24958a8568e96e20b90a91c77585`

Critical counts:

- `users`: `99`
- `v2_workspaces`: `22`
- `v2_flows`: `52`
- `v2_entries`: `1638`
- `v2_report_batches`: `8`
- `v2_report_batch_html_snapshots`: `17`

Validation:

```bash
php -l scripts/v2_build_atlas_payload.php
node -e 'JSON.parse(require("fs").readFileSync("package.json","utf8")); console.log("package.json ok")'
git diff --check
```

## Director Decision

SPRINT-63R is accepted as a local payload builder only.

No Atlas data was changed.

Next sprint must not commit this payload until backup/export evidence exists and the commit tool has explicit user approval.

## Next Sprint

SPRINT-64R — Atlas Backup and Commit Tool Gate

Scope:

- Export current Atlas legacy collections before any write.
- Create commit tool with explicit `--commit`.
- Upsert only v2 collections.
- Never overwrite old shell collections.
- Re-run parity exports and comparator after commit.
- Keep MySQL as rollback truth until Atlas parity passes.
