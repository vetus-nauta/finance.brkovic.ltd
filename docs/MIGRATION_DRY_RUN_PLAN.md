# Migration Dry Run Plan

Date: 2026-08-20

## Purpose

Move a copy of legacy FinDesk data into the new PostgreSQL model for verification only. This is not production migration.

## Dry Run Inputs

Legacy identity/access:

- `users`
- `v2_workspaces`
- `v2_workspace_members`
- `v2_workspace_invites`

Legacy operational finance:

- `v2_flows`
- `v2_entries`
- `v2_categories`
- `v2_category_rules`
- `v2_actors`
- `v2_monthly_closures`

Legacy sources:

- `v2_import_sources`
- `v2_import_rows`
- `v2_quick_notes`

Legacy reports:

- `v2_report_snapshots`
- `v2_report_batches`
- `v2_report_batch_entries`
- `v2_report_batch_html_snapshots`
- `v2_report_versions`
- `v2_report_packages`
- `v2_report_package_items`

Legacy accountable money:

- `v2_accountable_offers`
- `v2_accountable_reports`
- `v2_accountable_report_rows`
- `v2_accountable_settlements`
- `v2_accountable_report_entry_links`
- accountable projection flow rows in `v2_flows` / `v2_entries`

Legacy files:

- `v2_attachments`
- local `storage/`
- report HTML/export files

Legacy learning/audit:

- `v2_dictionary_training_decisions`
- `v2_audit_log`
- `v2_internet_reference_lookups`

## Mapping Rule

Financial facts move from operational entries into `transactions` and `ledger_entries`.

Reports move as snapshots/packages linked to source transaction IDs. They do not become new money rows.

Accountable money keeps the chain:

```text
offer -> employee report -> report rows -> accepted items -> linked ledger projection -> settlement
```

Materialized accountable projection entries must not be counted twice as cash movement.

## Required Reconciliation

- row counts by table/status
- per-row decimal amount comparison
- totals by workspace
- totals by period
- totals by account/flow
- totals by category
- totals by actor/counterparty
- opening and final balances
- report source IDs
- report snapshot totals and hashes
- accountable issued/spent/returned/reimbursed/open balance by actor
- file count, byte size, checksum, linked entity
- monthly closure/reopen/correction state
- audit event counts

## Output Artifacts

Each dry run must produce:

- migration timestamp
- source DB identifier
- target DB identifier
- source row counts
- target row counts
- reconciliation report
- rejected/ambiguous rows file
- file manifest
- RLS smoke result

## Stop Conditions

Stop and do not cut over if:

- any final balance differs
- any report source row set differs
- accountable money has unexplained open balances
- any file checksum is missing
- any revoked user can read rows
- any direct client can mutate critical finance rows
