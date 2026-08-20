# Supabase Foundation

Date: 2026-08-20

This folder is the future `brkovic.app` PostgreSQL/Supabase foundation.

It is not connected to production yet.

Rules:

- migrations are ordered and committed
- no dashboard-only production schema changes
- no secrets in this folder
- RLS must be enabled before real tenant data is loaded
- legacy PHP/MySQL data moves only through audited dry-run scripts

First migration:

- `migrations/20260820143018_drop_public_rls_auto_enable_trigger.sql`
- `migrations/20260820143100_foundation_core.sql`
