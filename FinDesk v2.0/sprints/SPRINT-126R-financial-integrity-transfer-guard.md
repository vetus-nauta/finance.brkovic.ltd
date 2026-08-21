# SPRINT-126R — Financial Integrity Transfer Guard

Status: Accepted
Date: 2026-08-21
Director: Codex

## Director Sprint Opening

The production MVP contains a real operational ledger, closed reports, and a
possibly open remainder. Moving that data into the foundation architecture must
not break the final cash/card position or hide report boundaries.

This sprint adds a read-only integrity guard before any data transfer work.

## Implemented

Added:

- `scripts/foundation_workspace_financial_integrity_audit.cjs`;
- `npm run audit:foundation:financial-integrity`.

The audit checks:

- live operational transaction count;
- ledger row count;
- total income, expense, and net balance;
- closed report income, expense, and net balance;
- open remainder income, expense, and net balance;
- account-level split;
- report snapshot totals against actual ledger rows;
- duplicate transaction usage across reports;
- report source rows that no longer exist;
- transactions locked as reported but not present in a live report;
- reported transactions that are not locked;
- live transactions without ledger rows.

The script runs inside `begin read only` and ends with `rollback`.

## Current Evidence

Ran:

```bash
npm run audit:foundation:financial-integrity -- --workspace-name "Claudia Z"
npm run audit:foundation:financial-integrity
```

Current Supabase foundation state:

- `Claudia Z`: 0 transactions, 0 ledger rows, 0 reports;
- `Тестовые прогоны`: 0 transactions, 0 ledger rows, 0 reports.

Conclusion: the new foundation database is structurally ready but does not yet
contain the real Claudia Z operational history. The live MVP data must be
imported through a staged transfer, then checked by this audit before acceptance.

## Transfer Rule

No data transfer is accepted unless:

- closed report rows remain tied to their report snapshots;
- open remainder remains visible as open operational ledger;
- no transaction is included in two reports;
- report snapshot totals equal actual ledger rows;
- closed net + open net equals total net;
- final cash/card figures match the accepted MVP source.

## Verification

Passed:

```bash
node --check scripts/foundation_workspace_financial_integrity_audit.cjs
npm run audit:foundation:financial-integrity -- --workspace-name "Claudia Z"
npm run audit:foundation:financial-integrity
```

## Acceptance

SPRINT-126R is accepted.

We now have a read-only financial integrity guard for the upcoming MVP data
transfer into the clean foundation platform.
