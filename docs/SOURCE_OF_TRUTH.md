# Source Of Truth

Date: 2026-08-20

## Project Direction

There are two truths during the transition.

## Legacy Runtime Truth

The current live product truth for `finance.brkovic.ltd` is the PHP FinDesk v2 runtime and its production data.

It is authoritative for:

- current user-facing behavior
- current Claudia Z operational data
- existing reports and accountable-money workflows
- migration evidence
- production continuity until cutover

It is not authoritative for:

- future database choice
- future deployment architecture
- future mobile architecture
- future auth/session architecture
- future storage architecture

## New Foundation Truth

The new architecture truth for `brkovic.app` is:

- this `docs/` folder
- future ordered PostgreSQL migrations
- future RLS policies
- future shared TypeScript contracts
- future API/business command implementation

This foundation is authoritative for new development once implementation begins.

## Database Truth

Transition stage:

- legacy production currently uses PHP runtime persistence
- MongoDB Atlas may exist as legacy/parity context
- PostgreSQL/Supabase is the target foundation

Final target:

- PostgreSQL is the source of truth
- reports are computed/snapshotted from source operations
- files live in private object storage with metadata in PostgreSQL

## Cutover Rule

Do not move traffic, delete legacy code, or declare the new foundation production-ready until:

- migration dry run passes
- totals/counts reconcile
- RLS tests pass
- backup and restore are proven
- rollback is documented
- owner approves cutover
