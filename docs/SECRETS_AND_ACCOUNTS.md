# Secrets And Accounts

Date: 2026-08-20

## Rule

No real credentials, tokens, connection strings, passwords, private keys, recovery codes, or FTP/database secrets are committed to Git.

The repository may contain only:

- documentation
- placeholder examples
- variable names
- setup checklists
- non-secret public identifiers when needed

## Owner-Controlled Secret Stores

Local development:

- ignored `.env` files
- ignored local PHP config files while legacy PHP exists
- owner-controlled password manager or manual secure handoff

Hosted web:

- Vercel environment variables

Data/backend:

- Supabase dashboard secrets
- Supabase CLI linked project config without secret values

Mobile:

- Expo/EAS secrets

Legacy production:

- existing hosting panel/FTP/database secrets remain outside Git
- legacy secrets are used only for audited backup/migration/deployment tasks

## Naming Convention

Use explicit variable names:

- `SUPABASE_URL`
- `SUPABASE_ANON_KEY`
- `SUPABASE_SERVICE_ROLE_KEY`
- `DATABASE_URL`
- `VERCEL_PROJECT_ID`
- `VERCEL_ORG_ID`
- `EXPO_TOKEN`
- `BRKOVIC_APP_PRIMARY_DOMAIN`
- `LEGACY_FINANCE_BASE_URL`
- `LEGACY_MYSQL_HOST`
- `LEGACY_MYSQL_DATABASE`
- `LEGACY_MYSQL_USER`
- `LEGACY_MYSQL_PASSWORD`
- `LEGACY_FTP_HOST`
- `LEGACY_FTP_USER`
- `LEGACY_FTP_PASSWORD`

Do not add values for these variables to tracked files.

## Account Creation Ledger

When a new third-party account/project is created, record the non-secret facts here or in a follow-up account ledger document:

- provider
- project name
- purpose
- owner email
- production/staging/local role
- billing status
- login location
- where secrets are stored
- recovery/rotation notes

Never record passwords, token values, recovery codes, private keys, or full connection strings.

## Initial Provider Plan

Provider: Supabase

- Purpose: PostgreSQL, Auth, Storage, Edge/server functions where appropriate
- First project role: development/staging foundation
- Production rule: create production project only after schema/RLS/migration dry run

Provider: Vercel

- Purpose: Next.js web deployments and preview environments
- First project role: staging preview for `brkovic.app`

Provider: Expo

- Purpose: iOS/Android builds and updates
- First project role: mobile foundation once web/API contracts exist

Provider: GitHub

- Purpose: source control, pull requests, CI, release history
- Existing repository: `vetus-nauta/finance.brkovic.ltd`

## Rotation Rule

Rotate any secret before production use if:

- it was pasted into chat
- it was committed to Git history
- it was stored in a screenshot
- it was reused from an old project
- its access scope is broader than needed

## Access Rule For Codex Work

Codex may:

- create placeholder env examples
- document variable names
- read public docs
- inspect ignored config file existence
- use provided secrets only for the specific task the owner requested

Codex must not:

- print secret values back to the chat
- commit secret values
- create permanent hidden credentials without documenting the non-secret location
- silently change production credentials
- migrate production data without backup and explicit owner approval
