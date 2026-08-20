# ADR-0001: PostgreSQL/Supabase Foundation

Date: 2026-08-20

## Status

Accepted for foundation planning.

## Context

The current FinDesk v2 runtime is PHP + MySQL/MariaDB, with MongoDB Atlas parity/cutover scripts created during earlier work. The new architecture brief requires a platform suitable for web, iOS, Android, PWA, API integrations, multi-tenant SaaS, RLS, private document storage, and shared business contracts.

The brief explicitly says MongoDB Atlas is not part of the basic foundation unless a future ADR proves technical necessity.

## Decision

The new foundation will target:

- PostgreSQL as the transactional source of truth
- Supabase as the initial managed platform for PostgreSQL/Auth/Storage/functions
- Next.js + TypeScript for web
- React Native + Expo + TypeScript for mobile
- shared TypeScript contracts
- server-side business command layer
- RLS for tenant isolation

MongoDB Atlas is demoted to legacy/parity context. It is not the target persistence layer.

## Consequences

- Existing Atlas scripts stay available for historical audit until they are intentionally retired.
- New migrations must be PostgreSQL migrations under `supabase/migrations`.
- Existing MySQL schema requires a migration map.
- Business logic must be moved behind shared commands/contracts, not duplicated in web/mobile.
