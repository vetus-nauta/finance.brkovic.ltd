# SPRINT-64R — Atlas Backup and Commit Tool Gate

Date: 2026-08-13
Status: accepted / first v2 Atlas payload committed with parity evidence

## Director Sprint Opening

Sprint:
SPRINT-64R — Atlas Backup and Commit Tool Gate

Goal:
Prepare the safe gate for the first v2 write into MongoDB Atlas: create Atlas backup/export tooling and a guarded commit tool, while keeping actual Atlas writes blocked until backup, dry-run, parity, and explicit user confirmation.

Required files read:

- `FinDesk v2.0/sprints/SPRINT-60R-atlas-foundation-restoration.md`
- `FinDesk v2.0/sprints/SPRINT-61R-v2-parity-export-compare-gate.md`
- `FinDesk v2.0/sprints/SPRINT-62R-v2-atlas-migration-dry-run.md`
- `FinDesk v2.0/sprints/SPRINT-63R-v2-atlas-document-payload-builder.md`
- `scripts/v2_atlas_parity_export.js`
- `scripts/v2_build_atlas_payload.php`
- `scripts/atlas_connection_smoke.js`

Agents assigned:

- Data/Safety Migration Agent
- QA Gate Agent
- Director as implementation owner

Agent tasks:

- Data/Safety Migration Agent: define backup requirements, allowed collections, forbidden collections, commit flags, and shell/v2 separation.
- QA Gate Agent: define before/after commit checks, hard-stop criteria, rollback path, and post-commit parity.
- Director: implement backup/export and guarded commit tools, then run no-write checks.

Expected reports:

- Backup/export requirements.
- Commit-tool safety contract.
- QA hard stops.
- Evidence of no-write defaults.

Exit criteria:

- Backup/export tool exists.
- Commit tool defaults to dry-run only.
- Commit requires explicit `--commit`.
- Commit requires a fresh backup path.
- Commit requires exact confirmation phrase.
- `users` update requires separate `--allow-users-update`.
- Legacy shell collections are never v2 targets.
- Actual Atlas write is not performed without explicit user approval.

Risks:

- Atlas Network Access can change when the workstation public IP changes.
- Old Atlas shell collections must not be overwritten.
- `users` exists both in old shell and v2 runtime context; it requires explicit merge permission.
- Payload contains private operational data and must stay outside Git/web runtime.

## Agent Reports Received

### Data/Safety Migration Agent

Required backup:

- Full export of current Atlas collections.
- Indexes per collection.
- Manifest with timestamp, database, collection counts, hashes, and storage path.
- Current Atlas shell collections must be preserved before any write.

Allowed write targets:

- `v2_*` collections.
- `users` only with a separate explicit flag.

Forbidden write targets:

- `workspaces`
- `cash_sessions`
- `workspace_audit`
- `yacht_states`
- `yacht_price_snapshots`
- `counters`
- `sessions`
- `auth_codes`

### QA Gate Agent

Hard stop if:

- no fresh Atlas backup;
- no fresh MySQL parity export;
- no fresh Atlas parity export before commit;
- no clear dry-run plan;
- commit tool writes without `--commit`;
- commit tool writes old shell collections;
- Claudia Z arithmetic does not match;
- rollback path is not available.

Before commit:

```bash
npm run check:atlas
npm run audit:v2:claudia-z
npm run parity:v2:mysql
npm run parity:v2:atlas
npm run migration:v2:atlas:dry-run
npm run payload:v2:atlas
npm run backup:v2:atlas
npm run commit:v2:atlas -- --allow-users-update
```

After future commit:

```bash
npm run parity:v2:atlas
node scripts/v2_compare_parity_exports.js <mysql-export.json> <new-atlas-export.json>
npm run audit:v2:claudia-z
```

## Implementation

Files added:

- `scripts/v2_atlas_backup_export.js`
- `scripts/v2_atlas_commit_payload.js`

Files updated:

- `package.json`

New scripts:

```bash
npm run backup:v2:atlas
npm run commit:v2:atlas
```

Commit tool safety:

- Without `--commit`, it writes only local commit-plan artifact.
- With `--commit`, it requires `--backup <atlas-backup.json>`.
- With `--commit`, it requires exact confirm phrase:
  - `WRITE_V2_TO_ATLAS_20260813`
- `users` is rejected unless `--allow-users-update` is present.
- Only `v2_*` collections are allowed by default.
- Legacy shell collections are rejected as unsafe targets.

## Evidence

Local no-write safety checks:

```bash
node --check scripts/v2_atlas_commit_payload.js
npm run commit:v2:atlas
npm run commit:v2:atlas -- --commit --allow-users-update
git diff --check
```

Results:

- `npm run commit:v2:atlas` exits `2` with `unsafe_collection_target:users`.
- `npm run commit:v2:atlas -- --commit --allow-users-update` exits `2` with `Commit requires --backup <atlas-backup.json>.`
- No Atlas writes performed.

## Current Blocker

Atlas access failed after workstation public IP changed.

Previous working IP:

- `150.228.67.27/32`

Current public IP:

- `150.228.67.3`

`npm run check:atlas` now fails with Atlas TLS alert on all shard hosts.

Repeat check:

- 2026-08-13 10:19 UTC: current public IP still `150.228.67.3`.
- `npm run check:atlas` still fails with Atlas TLS alert on all three shard hosts.
- No Atlas backup was created.
- No Atlas write was performed.

Local gates still clean:

- `npm run audit:v2:claudia-z`: cash diff `0`, mismatches `0`.
- `npm run parity:v2:mysql`: fresh MySQL parity export created.
- `npm run migration:v2:atlas:dry-run`: decision `migration_required`, insert `9195`, update `1`, noop `0`, target-only `0`.

Required user-side action:

- Add `150.228.67.3/32` to MongoDB Atlas Network Access for project `finance-brkovic-ltd`.

After that, rerun:

```bash
npm run check:atlas
npm run backup:v2:atlas
npm run commit:v2:atlas -- --allow-users-update
```

## Network Access Restored

2026-08-13 10:20 UTC:

- Current IP `150.228.67.3/32` was added to Atlas Network Access.
- `npm run check:atlas` passed:
  - DNS SRV ok.
  - TLSv1.3 ok on all three shard hosts.
  - Mongo ping ok.

## Fresh Pre-Commit Evidence

Atlas backup:

- `storage/production-audits/v2-atlas-backup-20260813102018/atlas-backup.json`
- database: `finance_brkovic_ltd`
- collections: `7`
- backup hash: `c8bca31baac4a96ed18ebb9125a301c277cf6f7f3e31593f8bcfd60ecc47f61d`
- collections:
  - `cash_sessions`: `2`
  - `counters`: `3`
  - `users`: `1`
  - `workspace_audit`: `31`
  - `workspaces`: `2`
  - `yacht_price_snapshots`: `2`
  - `yacht_states`: `2`

Atlas parity before commit:

- `storage/production-audits/v2-parity-export-atlas-20260813102021/atlas-parity-export.json`
- `v2_entries`: `0`
- legacy collections present: `cash_sessions`, `counters`, `workspace_audit`, `workspaces`, `yacht_price_snapshots`, `yacht_states`

Payload:

- `storage/production-audits/v2-atlas-payload-20260813-102022/atlas-payload.json`
- collections: `29`
- documents: `9196`
- payload hash: `97d3880cf43dbdbc5be563b8936d3fb2cdae24958a8568e96e20b90a91c77585`

Commit plan, no writes:

- `storage/production-audits/v2-atlas-commit-plan-20260813102026/atlas-commit-plan.json`
- mode: `dry_run_no_writes`
- commit enabled: `false`
- allow users update: `true`
- documents: `9196`
- insert: `9195`
- update: `1`
- noop: `0`
- terminal confirmed: `No Atlas writes performed.`

Claudia Z pre-commit arithmetic:

- `npm run audit:v2:claudia-z`
- cash opening: `8015`
- computed cash: `3893`
- latest balance after: `3893`
- diff: `0`
- mismatches: `0`

MySQL parity before commit:

- `storage/production-audits/v2-parity-export-20260813-102052/mysql-parity-export.json`
- `v2_entries` hash: `c8da11bdbde152568ea83a137be0bccda3e2a06714244c559682da2d4011a099`

Expected pre-commit comparator:

- `storage/production-audits/v2-parity-precommit-compare.json`
- mismatch count: `22`
- examples:
  - `users`: MySQL `99`, Atlas `1`
  - `v2_entries`: MySQL `1638`, Atlas `0`
  - `v2_report_batches`: MySQL `8`, Atlas `0`
  - `v2_import_rows`: MySQL `3507`, Atlas `0`
  - `v2_dictionary_training_decisions`: MySQL `111`, Atlas `0`

## Ready For Explicit Commit Decision

The first Atlas v2 write is now technically ready, but still blocked by Director policy until explicit user approval.

Required exact command for future commit:

```bash
npm run commit:v2:atlas -- --commit --allow-users-update --backup storage/production-audits/v2-atlas-backup-20260813102018/atlas-backup.json --confirm WRITE_V2_TO_ATLAS_20260813
```

Post-commit mandatory checks:

```bash
npm run parity:v2:atlas
node scripts/v2_compare_parity_exports.js storage/production-audits/v2-parity-export-20260813-102052/mysql-parity-export.json <new-atlas-export.json>
npm run audit:v2:claudia-z
```

## Commit Executed

User approved the first Atlas write with: `давай`.

Command executed:

```bash
npm run commit:v2:atlas -- --commit --allow-users-update --backup storage/production-audits/v2-atlas-backup-20260813102018/atlas-backup.json --confirm WRITE_V2_TO_ATLAS_20260813
```

First pass:

- plan artifact: `storage/production-audits/v2-atlas-commit-plan-20260813102706/atlas-commit-plan.json`
- documents: `9196`
- insert: `9195`
- update: `1`
- noop: `0`
- result: `Atlas commit applied. Documents planned: 9196`

Post-first-pass Atlas parity:

- `storage/production-audits/v2-parity-export-atlas-20260813102903/atlas-parity-export.json`
- `v2_entries`: `1638`
- legacy collections preserved: `cash_sessions`, `counters`, `workspace_audit`, `workspaces`, `yacht_price_snapshots`, `yacht_states`

Comparator after first pass:

- `storage/production-audits/v2-parity-postcommit-compare-canonical.json`
- mismatch count: `1`
- issue: old Atlas `users` document id `1` retained legacy shell field `name` because the first commit used `$set`.

Accepted correction:

- Commit tool changed from `$set` upsert to exact `replaceOne` upsert.
- Second pass executed with the same backup and confirm gate.

Second pass:

- plan artifact: `storage/production-audits/v2-atlas-commit-plan-20260813103314/atlas-commit-plan.json`
- insert: `0`
- update: `1`
- noop: `9195`
- result: `Atlas commit applied. Documents planned: 9196`

Final Atlas parity:

- `storage/production-audits/v2-parity-export-atlas-20260813103558/atlas-parity-export.json`
- `v2_entries`: `1638`

Final comparator:

- `storage/production-audits/v2-parity-postcommit-compare-zero-final.json`
- compared against MySQL export: `storage/production-audits/v2-parity-export-20260813-102955/mysql-parity-export.json`
- mismatch count: `0`
- table count: `29`

Final Claudia Z audit:

- cash opening: `8015`
- computed cash: `3893`
- latest balance after: `3893`
- diff: `0`
- mismatches: `0`

Final Atlas health:

- `npm run check:atlas` passed.
- TLSv1.3 ok on all shard hosts.
- Mongo ping ok.

## Director Decision

SPRINT-64R is accepted.

Atlas now contains the canonical FinDesk v2 payload for 29 collections with parity evidence against MySQL.

Current runtime has not been switched yet. PHP/MySQL remains the active application runtime until the next sprint builds and verifies the Atlas-backed runtime/API path.

Rollback remains available:

- MySQL is still intact and remains the active runtime source.
- Atlas backup before v2 commit is stored at `storage/production-audits/v2-atlas-backup-20260813102018/atlas-backup.json`.

## Next Sprint

SPRINT-65R — Atlas-backed v2 Runtime Adapter

Scope:

- Decide runtime architecture: Node Atlas API vs PHP Mongo adapter.
- Do not change financial formulas.
- Route v2 reads through Atlas in a controlled local mode first.
- Run browser/manual QA against Atlas-backed data before production cutover.
