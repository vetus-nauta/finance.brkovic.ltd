# Deployment

Date: 2026-08-20

## Domains

- `finance.brkovic.ltd`: current PHP production until explicit cutover.
- `brkovic.app`: target clean SaaS foundation domain.

## Target Environments

- local
- staging
- production

## Target Deployment Shape

Web:

- Next.js app from `apps/web`
- deployed to Vercel first
- domain `brkovic.app` or staging subdomain mapped through DNS

Backend/data:

- Supabase project for PostgreSQL/Auth/Storage
- server-side business commands through Supabase functions or Next.js server/API routes, depending on command and runtime needs

Mobile:

- Expo app from `apps/mobile`
- uses same API contracts and Supabase Auth flow

## DNS Direction

For `brkovic.app`, the intended production shape is DNS -> Vercel web app.

Shared hosting may keep a temporary placeholder or redirect, but it should not become the long-term application runtime.

Namecheap remains the domain registrar. Do not move the domain as part of Foundation work.
Change DNS records only after a verified web deployment target exists.

Expected clean URLs after cutover:

- `https://brkovic.app`
- `https://www.brkovic.app`

The app source is `apps/web`; legacy PHP routes must not be introduced under the clean
`brkovic.app` runtime.

## Secrets

No production secrets in Git.

Use:

- local `.env`
- `apps/web/.env.local`
- Vercel environment variables
- Supabase secrets
- Expo/EAS secrets

Commit only examples and documentation.

## Production Cutover Rule

No production cutover without:

- staging deploy
- migration dry run
- totals/counts reconciliation
- backup
- rollback plan
- owner approval
