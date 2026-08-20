# SPRINT-112R — Vercel + Supabase Production Gate

Date: 2026-08-20

## Director opening

Goal: prepare the new `brkovic.app` foundation app for a clean Vercel/Supabase
deployment path before real Claudia Z data, employees, invitations, and report
history are re-attached.

This sprint is a gate, not a full production launch.

## Platform facts

- Supabase project ref: `suebhgyqvzcrigfdplot`
- Supabase URL: `https://suebhgyqvzcrigfdplot.supabase.co`
- Supabase is connected to Vercel.
- Active Git branch: `foundation-brkovic-app-architecture`
- Web app package: `apps/web`
- Target domain: `brkovic.app`

## Required Vercel project settings

Chosen Vercel project root:

```text
apps/web
```

Project settings:

```text
Install command: npm install
Build command: npm run build
Development command: npm run dev -- --hostname 0.0.0.0
Framework: Next.js
```

Committed Vercel config:

```text
apps/web/vercel.json
```

Do not keep parallel Vercel projects pointing at different roots for the same
production domain. Do not deploy the repository root as a second production app.

## Required production env

```text
NEXT_PUBLIC_APP_ENV=production
NEXT_PUBLIC_APP_DOMAIN=https://brkovic.app
NEXT_PUBLIC_SUPABASE_URL=https://suebhgyqvzcrigfdplot.supabase.co
NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY=<Vercel/Supabase value>
FINDESK_DEV_LOGIN_ENABLED=0
```

Forbidden in browser/public production env:

```text
SUPABASE_SERVICE_ROLE_KEY
SUPABASE_DB_PASSWORD
SUPABASE_DB_POOLER_URL
FTP_PASSWORD
MONGODB_URI
```

## Supabase Auth OTP rule

The web login form expects a 6-digit code.

Tracked template:

```text
supabase/auth-email-templates/magic-link-otp.html
```

Both auth-template setup scripts must keep `mailer_otp_length=6`:

```text
scripts/supabase_configure_auth_smtp_and_otp.cjs
scripts/supabase_apply_auth_otp_template.cjs
```

## Supabase checks

MCP evidence:

- `list_tables`: foundation public tables are present and RLS is enabled.
- `list_edge_functions`: no Edge Functions currently deployed.
- `list_migrations`: `smith_gap_tuning` is now visible in Supabase migration
  history as an official migration, version `20260820215428`.

Classifier smoke:

- `+7000 чартер` returns `commercial_income`.
- `-50 радиостанция переносная icom` returns `tech_parts`.

## Local checks

Passed:

- `npm run typecheck:web`
- `npm run build:web`
- `cd apps/web && npm run build`
- `npm run check:foundation:sql`
- `npm run smoke:foundation:rls`
- `git diff --check`
- responsive QA from `SPRINT-111R`

Vercel CLI:

- `npx vercel --version` returns `59.3.0`.
- `npx vercel deploy --dry --yes --cwd apps/web --no-color` is blocked by
  missing local Vercel credentials, not by project code.

## Blockers before live production acceptance

- Vercel CLI/API access was not available in the local environment, so this
  sprint did not trigger a production deployment.
- Next required human/account step: `vercel login` or a Vercel token for the
  correct Vercel account/team.
- A live Vercel deployment URL must be checked after deploy.
- Supabase Auth OTP email must be verified on the deployed domain.
- Domain routing for `brkovic.app` must be checked after Vercel project/domain
  binding.
- No real Claudia Z data or employee invitations should be attached until this
  live gate passes.

## Acceptance rule

Production is accepted only after:

- Vercel build passes on the connected GitHub branch;
- deployed `/`, `/hall`, and `/workspaces` routes load without legacy FinDesk
  assets or V1/V2 path leakage;
- OTP auth works from `brkovic.app`;
- Supabase RLS smoke passes;
- no production env exposes service-role or database secrets to browser runtime.
