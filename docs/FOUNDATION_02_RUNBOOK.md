# Foundation-02 Runbook

Date: 2026-08-20

## Sprint Name

Foundation-02: Supabase Bootstrap, RLS Skeleton, Migration Dry Run Plan

## Objective

Prepare the first real technical foundation for `brkovic.app` without touching production traffic or moving real data.

## What Codex Controls Now

- Supabase migration files
- RLS helper/policy skeleton
- env variable examples without values
- migration dry-run plan
- account setup runbook
- verification checklist

## What Requires Owner Action

Supabase:

- create a development/staging project
- do not create production yet
- keep generated secrets outside Git
- provide only the needed env values through local ignored files when Codex must connect

Vercel:

- create/connect a project only after `apps/web` exists
- keep project tokens in Vercel env/local ignored env

DNS:

- confirm control of `brkovic.app`
- prepare staging subdomain later
- do not point production root to unfinished app

## Current Outputs

- `supabase/migrations/20260820143018_drop_public_rls_auto_enable_trigger.sql`
- `supabase/migrations/20260820143100_foundation_core.sql`
- `supabase/migrations/20260820143200_foundation_hardening.sql`
- `supabase/migrations/20260820143300_rls_auth_initplan_hardening.sql`
- `supabase/README.md`
- `env.foundation.example`
- `docs/MIGRATION_DRY_RUN_PLAN.md`
- `docs/RLS_TEST_PLAN.md`

## Applied Dev/Staging State

Supabase project ref: `suebhgyqvzcrigfdplot`

Applied on 2026-08-20:

- removed legacy public `rls_auto_enable` event trigger/function from the empty project
- created the Foundation Core schema
- enabled RLS on all 42 public tables
- seeded roles, permissions, and role-permission mappings
- fixed `public.set_updated_at()` search path
- added covering indexes for all detected foreign keys
- split broad write policies into action-specific insert/update/delete policies
- wrapped direct RLS `auth.uid()` calls for init-plan performance

## Acceptance Gate

Foundation-02 is acceptable only when:

- migrations are committed
- migrations can run in a clean Supabase/Postgres environment
- remote `supabase db push --dry-run` reports no pending migrations
- RLS direct-client tests are defined
- migration dry-run checks are documented
- no production traffic is touched
- no real secrets are committed
- Supabase security advisor returns no lints
- FK indexes and duplicate permissive policies have no remaining warnings

Current residual advisory note:

- performance advisor reports `unused_index` INFO items because the new database is empty and indexes have not served traffic yet. This is expected at foundation stage and must be rechecked after seed/scenario testing.

## Next Sprint Candidate

Foundation-03: Seed Org/Workspace/User Scenario And RLS Smoke

Expected outputs:

- first RLS tests executed
- first seed org/workspace/user scenario
- direct-client denied/allowed matrix captured
- storage bucket policies drafted
- application scaffold connected to Supabase through env
