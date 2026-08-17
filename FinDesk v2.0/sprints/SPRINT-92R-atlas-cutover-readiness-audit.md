# SPRINT-92R - Atlas Cutover Readiness Audit

Date: 2026-08-13

## Director Opening

Goal: freeze the post-parity state and prove what is ready before any production switch.

Director discipline:
- Source of truth: repository files and live local command evidence.
- No FTP upload, production runtime switch, or destructive migration was performed.
- Financial formulas, parser behavior, report math, and deployed PHP runtime were not changed.
- This sprint is an audit/checkpoint, not a feature sprint.

## Current State

Atlas local sidecar API parity is complete:
- Total API surface: `81`
- Reads: `36/36`
- Writes: `45/45`
- Unsupported reads: `0`
- Unsupported writes: `0`

The strict production cutover gate remains blocked by design:
- `shadow_gateway_available_but_not_cutover`
- `ftp_production_cutover_not_authorized`

This means the technical route parity work is complete, but the live site is not yet authorized to switch.

## Evidence

Passed:

```bash
npm run check:atlas
npm run smoke:v2:atlas-runtime
npm run gate:v2:atlas-cutover
npm run audit:v2:atlas-model
npm run backup:v2:atlas
```

Strict gate:

```bash
npm run gate:v2:atlas-cutover:strict
```

Result: exit code `2`, expected because production cutover is intentionally blocked until explicit authorization and browser cutover evidence.

## Atlas Snapshot

Backup:
- Path: `storage/production-audits/v2-atlas-backup-20260813150723/atlas-backup.json`
- Hash: `86abb67bee9a3013c62ebe0168401f606c00290c1e4c4c4b383dde99afe8827e`
- Database: `finance_brkovic_ltd`
- Collections: `35`

Model audit:
- Path: `storage/production-audits/v2-persistence-foundation-atlas-20260813150609/atlas-model-audit.json`
- Collections: `35`

Key counts:
- `v2_entries`: `1638`
- `v2_flows`: `52`
- `v2_import_sources`: `60`
- `v2_import_rows`: `3507`
- `v2_category_rules`: `85`
- `v2_dictionary_training_decisions`: `111`
- `v2_report_batches`: `8`
- `v2_report_batch_entries`: `629`
- `v2_report_batch_html_snapshots`: `17`
- `v2_attachments`: `0`
- `v2_workspaces`: `22`
- `v2_workspace_members`: `44`

Claudia Z runtime evidence:
- Workspace entries: `279`
- August entries: `39`
- Cash now: `3893.00`
- August ending cash: `3893.00`
- Active operational fragments: `1`
- Report batches in runtime smoke: `1`
- HTML snapshots: `1`
- Category matrix rows: `22`
- Other review entries: `3`
- Raw history sources: `57`
- Raw history rows: `3338`

Accountable workflow evidence:
- Accountable workspace fixture: `43a20c32-a9e6-4812-a556-6f1cb995147d`
- Invites: `3`
- Offers: `3`
- Reports: `3`
- Materialization links: `1`

## Decision

Accepted:
- Atlas route parity is complete locally.
- Atlas backup exists and is recorded.
- Local Atlas checks are green.
- Production cutover remains blocked for correct reasons.

Rejected:
- Silent FTP deploy.
- Silent runtime switch.
- Treating strict gate exit `2` as a failure.
- Claiming live production is already on Atlas.

## Next Step

SPRINT-93R should be the controlled local browser cutover rehearsal:
- Start the local PHP site and Atlas sidecar together.
- Route local browser traffic through the Atlas-backed runtime mode.
- Run the human-critical scenarios:
  - open hall;
  - open Claudia Z workspace;
  - create, edit, delete a temporary operational entry;
  - open reports/archive;
  - create/open report snapshot;
  - verify employee/accountable screens;
  - verify no body overflow on desktop/mobile widths.
- Restore temporary changes or prove cleanup.
- Only after that, ask for explicit production deployment authorization.
