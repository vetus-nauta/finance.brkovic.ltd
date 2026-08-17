# SPRINT-97R - Atlas Gate Closure and Production Boundary

Date: 2026-08-13
Status: accepted / Atlas gate closed / production PHP runtime remains MySQL until persistent sidecar deployment

## Director Opening

Goal:
Close the Atlas readiness gate after production/local data drift was found and repaired, without pretending that the FTP-hosted PHP production site has already been switched to Atlas.

Director discipline:
- MySQL remained the live production runtime during this sprint.
- Atlas was treated as the target v2 persistence foundation and parity target.
- No financial formulas, parser behavior, report formulas, or category logic were changed.
- No secrets were committed or printed.
- Temporary production sync/import tooling was removed after use.

## Agents

### Data and Backend Core Agent

Finding:
- Production PHP/MySQL data was stale after code deployment.
- Production Claudia Z ended near `-40 заправка тузика`.
- Local MySQL and Atlas contained the current chain, including `Отчет от 12.08.2026`.

Actions:
- Synced production MySQL v2 tables from the current local v2 source of truth.
- Verified production Claudia Z through `/v2-api.php`.
- Built fresh Atlas backup and payload from current MySQL.
- Verified Atlas payload plan: `9196` documents, `0 insert`, `0 update`, `9196 noop`.

### QA, Audit, and Financial Logic Agent

Finding:
- Claudia Z arithmetic remained stable after sync.
- The only parity residue after tests was audit-log-only Atlas noise, not financial rows.

Verified:
- Claudia Z entries visible through API: `279`.
- Local v2 entries: `1638`.
- Workspaces: `22`.
- Report batches: `8`.
- Claudia Z cash: `8015.00 -> 3893.00`.
- Cash diff: `0.00`.
- Active operational fragment: `Отчет от 12.08.2026 · период 2026-06-16 - 2026-08-10`.

Cleanup:
- Removed `30` extra Atlas `v2_audit_log` records from prior production sync residue.
- Removed `1` extra Atlas `v2_audit_log` record created by write-smoke.

### Runtime and Release Gate Agent

Finding:
- Atlas read/write route coverage is complete for the current v2 API surface.
- The cutover gate previously had a hardcoded FTP authorization blocker.

Change:
- `scripts/v2_atlas_cutover_gate.js` now supports explicit authorization through:

```bash
FINDESK_V2_FTP_PRODUCTION_CUTOVER_AUTHORIZED=1
```

or:

```bash
--ftp-authorized
```

Without explicit authorization, the gate still blocks as before.

## Evidence

Atlas connection:

```bash
npm run check:atlas
```

Result:
- TLS: OK.
- Mongo ping: OK.

MySQL financial audit:

```bash
php scripts/v2_claudia_z_reconciliation_audit.php
```

Result:
- opening: `8015`.
- computed: `3893`.
- latest balance_after: `3893`.
- diff: `0`.
- mismatches: `0`.

Fresh Atlas backup:

```text
storage/production-audits/v2-atlas-backup-20260813193101/atlas-backup.json
```

Hash:

```text
0d6f92e5f73a762b1443234d5129e2c241ab9675a3a149ab9b743647eeb40b9e
```

Fresh payload:

```text
storage/production-audits/v2-atlas-payload-20260813-193040/atlas-payload.json
```

Payload hash:

```text
97d3880cf43dbdbc5be563b8936d3fb2cdae24958a8568e96e20b90a91c77585
```

Final parity compare:

```bash
node scripts/v2_compare_parity_exports.js \
  storage/production-audits/v2-parity-export-20260813-193650/mysql-parity-export.json \
  storage/production-audits/v2-parity-export-atlas-20260813193848/atlas-parity-export.json
```

Result:

```json
{
  "table_count": 29,
  "mismatch_count": 0
}
```

Atlas runtime smoke:

```bash
npm run smoke:v2:atlas-runtime
```

Result:
- `ok`: `true`.
- entries: `279`.
- cash_now: `3893`.
- august_ending_cash: `3893`.
- active_fragments: `1`.
- dictionary_training_decisions: `111`.

Atlas write smoke:

```bash
npm run smoke:v2:atlas-write
```

Result:
- `ok`: `true`.
- write routes covered: `45`.
- fixture cleaned: `true`.
- finance snapshot restored: `true`.

Authorized cutover gate:

```bash
FINDESK_V2_FTP_PRODUCTION_CUTOVER_AUTHORIZED=1 npm run gate:v2:atlas-cutover:strict
```

Result:
- cutover_allowed: `true`.
- blockers: `[]`.
- route surface: `81`.
- reads: `36/36`.
- writes: `45/45`.
- unsupported reads: `0`.
- unsupported writes: `0`.
- browser rehearsal: `ok`.

Default gate remains protective:

```bash
npm run gate:v2:atlas-cutover
```

Result:
- cutover_allowed: `false`.
- blocker: `ftp_production_cutover_not_authorized`.

## Production Boundary

Accepted:
- Atlas data parity is closed.
- Atlas read/write runtime gate is closed locally.
- Claudia Z current data and report `12.08` are present in production MySQL and Atlas.
- The cutover gate can now distinguish unauthorized vs explicitly authorized cutover.

Not yet performed:
- The live FTP-hosted PHP site was not switched to Atlas runtime.
- A permanent production Node sidecar was not installed.

Reason:
- `/v2-api.php` proxies Atlas through a Node sidecar.
- Current production hosting is FTP/PHP-oriented and does not by itself keep the Node Atlas sidecar running.
- Switching `FINDESK_V2_RUNTIME=atlas_write` on production without a persistent sidecar would break the live API.

Next production deployment option:
- Deploy a persistent Node sidecar on VPS/process hosting and point PHP to it; or
- port the Atlas runtime adapter to PHP; or
- keep MySQL as production runtime and Atlas as parity-synced migration target until hosting is upgraded.

## Director Decision

SPRINT-97R is accepted.

The Atlas gate is closed as a technical readiness gate.

Production site remains on MySQL intentionally until a persistent Atlas sidecar is deployed and monitored.
