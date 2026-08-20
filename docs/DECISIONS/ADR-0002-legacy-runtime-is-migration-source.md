# ADR-0002: Legacy Runtime Is Migration Source

Date: 2026-08-20

## Status

Accepted

## Context

The current FinDesk v2 PHP application is live and contains valuable product behavior, data, reports, and user-tested workflows.

The new foundation brief requires a clean multi-user SaaS architecture for `brkovic.app` based on PostgreSQL/Supabase, Next.js, Expo, private storage, and typed API contracts.

The existing runtime also contains PHP/MySQL, FTP deployment, and MongoDB Atlas parity tooling. These pieces are useful for continuity and migration, but they conflict with the target foundation if treated as future architecture.

## Decision

The PHP runtime, MySQL/MariaDB schema, FTP deployment scripts, and MongoDB Atlas parity path are legacy/migration sources only.

They must not be extended as the new `brkovic.app` foundation unless a future ADR explicitly changes this decision.

## Consequences

- Current production remains safe while new work happens in parallel.
- Migration mapping must be explicit.
- New code should not deepen the PHP/MySQL/FTP/Atlas architecture.
- PostgreSQL/Supabase migrations and RLS become the future source of truth.
- Any production cutover requires backup, dry run, reconciliation, and rollback acceptance.
