# Environment Matrix

Date: 2026-08-20

## Purpose

Keep local, staging, production, legacy, and future environments separate. No hidden mixing.

## Legacy Production

Domain:

- `finance.brkovic.ltd`

Runtime:

- PHP FinDesk v2

Data:

- current production persistence
- Atlas may be used only as legacy/parity context

Rule:

- do not break, delete, or cut over without owner approval

## New Local Foundation

Domain:

- localhost only

Runtime:

- future Next.js web
- future Expo mobile
- future Supabase local/dev project

Data:

- seed/test data
- migration dry-run copies only

Rule:

- safe to build and reset
- no production secrets committed

## New Staging

Domain:

- staging subdomain under `brkovic.app` or Vercel preview URL

Runtime:

- Vercel web preview/staging
- Supabase staging project

Data:

- imported sanitized or copied legacy data for reconciliation

Rule:

- staging must pass migration, RLS, API, storage, and UI smoke before production

## New Production

Domain:

- `brkovic.app`

Runtime:

- Vercel web
- Supabase production project
- Expo/EAS mobile builds when ready

Data:

- PostgreSQL/Supabase source of truth after cutover

Rule:

- create only after foundation is verified
- cut over only after owner approval

## Account Creation Order

1. Confirm domain/DNS control for `brkovic.app`.
2. Create Supabase development/staging project.
3. Create Vercel project for web foundation.
4. Create Supabase production project only after schema and RLS are accepted.
5. Create Expo/EAS project once shared API contracts are stable.
