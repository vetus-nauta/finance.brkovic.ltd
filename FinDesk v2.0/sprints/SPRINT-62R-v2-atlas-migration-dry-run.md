# SPRINT-62R — v2 Atlas Migration Dry Run

Date: 2026-08-13
Status: implemented locally / no-write planner accepted

## Director Sprint Opening

Sprint:
SPRINT-62R — v2 Atlas Migration Dry Run

Goal:
Build a no-write migration planner that reads MySQL and Atlas parity exports and produces a safe v2 Atlas migration estimate before any commit is allowed.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-60R-atlas-foundation-restoration.md`
- `FinDesk v2.0/sprints/SPRINT-61R-v2-parity-export-compare-gate.md`
- `scripts/v2_mysql_parity_export.php`
- `scripts/v2_atlas_parity_export.js`
- `scripts/v2_compare_parity_exports.js`
- `FinDesk v2.0/20-definition-of-done.md`

Agents assigned:

- Data Migration Review Agent
- QA Acceptance Review Agent
- Director as integration owner

Agent tasks:

- Data Migration Review Agent: define dry-run planner counts, blockers, and legacy Atlas shell boundaries.
- QA Acceptance Review Agent: define no-write acceptance tests, commit lock behavior, expected mismatch, and artifact requirements.
- Director: implement planner, run checks, record evidence.

Expected reports:

- Dry-run schema and blocker rules.
- QA acceptance commands and expected result.
- Artifact evidence.

Exit criteria:

- Planner reads parity exports only.
- Planner writes only local audit artifact.
- Planner does not write to MySQL or Atlas.
- `--commit` is explicitly blocked.
- Planner reports current Atlas as `migration_required`.
- MySQL source integrity is checked and clean.
- Money precision policy forbids JavaScript `Number` for persisted money.

Risks:

- A dry-run can look like progress while not migrating anything.
- Old Atlas shell collections must remain separate from v2 collections.
- A future commit tool must preserve every relationship and money value.

## Agent Reports Received

### Data Migration Review Agent

Required planner output:

- `source_count`
- `target_count`
- `insert_count`
- `update_count`
- `noop_count`
- `extra_target_count`
- `missing_dependency_count`
- `blocked_count`
- `ready_for_commit`
- `expected_after_commit_parity`
- `requires_backup`
- `requires_explicit_commit_flag`

Non-negotiable boundaries:

- Do not write v2 data into old Atlas `workspaces`.
- Do not treat old `cash_sessions` as `v2_entries`.
- Do not mix embedded old members with `v2_workspace_members`.
- Do not mix `workspace_audit` with `v2_audit_log`.
- Do not mix numeric old shell IDs with UUID v2 IDs.

### QA Acceptance Review Agent

Required checks:

```bash
php -l scripts/v2_mysql_parity_export.php
node --check scripts/v2_atlas_parity_export.js
node --check scripts/v2_compare_parity_exports.js
node --check scripts/v2_atlas_migration_dry_run.js
node scripts/v2_atlas_migration_dry_run.js --commit
npm run parity:v2:mysql
npm run parity:v2:atlas
npm run migration:v2:atlas:dry-run
```

Expected current result:

- `decision: migration_required`
- `totals.insert > 0`
- `commit_enabled: false`
- `ready_for_commit: false`
- `v2_entries` planned for insert
- no raw feed text or secrets in terminal output

## Implementation

Files added:

- `scripts/v2_atlas_migration_dry_run.js`

Files updated:

- `package.json`
- `scripts/v2_mysql_parity_export.php`

New script:

```bash
npm run migration:v2:atlas:dry-run
```

## Evidence

Artifacts:

- `storage/production-audits/v2-parity-export-20260813-082625/mysql-parity-export.json`
- `storage/production-audits/v2-parity-export-atlas-20260813082628/atlas-parity-export.json`
- `storage/production-audits/v2-atlas-migration-dry-run-20260813082629/migration-dry-run.json`

Dry-run result:

- decision: `migration_required`
- insert: `9195`
- update: `1`
- noop: `0`
- target-only: `0`
- source integrity total issues: `0`
- blockers: `0`
- warnings: `1`
- warning: legacy Atlas shell collection `workspaces` is present and must not be used as v2 schema.

Critical collection plan:

- `users`: insert `98`, update `1`
- `v2_workspaces`: insert `22`
- `v2_flows`: insert `52`
- `v2_entries`: insert `1638`
- `v2_report_batches`: insert `8`
- `v2_report_batch_entries`: insert `629`

Commit lock:

```bash
node scripts/v2_atlas_migration_dry_run.js --commit
```

Result:

- `Commit is intentionally disabled in SPRINT-62R dry-run planner.`
- exit code: `2`

## Director Decision

SPRINT-62R is accepted as a dry-run planner only.

No data was migrated. No Atlas runtime switch is allowed yet.

Current MySQL/MariaDB remains the source of truth.

The next sprint must create actual exportable document payloads and a reviewed commit strategy, with backup evidence and explicit user approval before any Atlas write.

## Next Sprint

SPRINT-63R — v2 Atlas Document Payload Builder

Scope:

- Build deterministic MySQL row-to-Atlas-document transformer.
- Keep a dry-run default.
- Write payload samples to local artifact only.
- Validate references before commit.
- Decide Decimal128 vs canonical decimal string.
- Do not write to Atlas yet.
