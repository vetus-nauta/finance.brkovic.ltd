# Supabase Foundation

Date: 2026-08-20

This folder is the `brkovic.app` PostgreSQL/Supabase foundation.

As of 2026-08-20, this Supabase project is connected to Vercel for the new
foundation web app. Treat Supabase migrations and committed application code as
the source of truth for this platform layer.

Rules:

- migrations are ordered and committed
- no dashboard-only production schema changes
- no secrets in this folder
- RLS must be enabled before real tenant data is loaded
- legacy PHP/MySQL/Atlas data moves only through audited import/provision steps
  after the platform gate is accepted
- do not make dashboard-only schema changes that are not reflected in committed
  migrations

First migration:

- `migrations/20260820143018_drop_public_rls_auto_enable_trigger.sql`
- `migrations/20260820143100_foundation_core.sql`

Current production gate notes:

- Supabase URL is public and may be referenced as an environment variable.
- Publishable anon key belongs in Vercel/public app env.
- Service role key must not be exposed to the browser and is not required for
  production runtime while local dev-login is disabled.
- Auth OTP email template is tracked in `auth-email-templates/`.
