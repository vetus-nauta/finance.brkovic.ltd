# Foundation-04 Runbook: Clean Web App Foundation

Date: 2026-08-20

## Objective

Create the first clean `brkovic.app` web foundation without deepening the legacy PHP/V2 runtime.

This sprint does not cut over production and does not migrate financial data. It creates the
application rail that future SaaS web, mobile, auth, role, report, and Mr. Smith workflows can use.

## Director Scope

Agents:

- Product/UX reviewer: keep the shell understandable for a normal user, not a technical admin.
- Frontend foundation agent: add Next.js/React/TypeScript under `apps/web`.
- Supabase security reviewer: keep browser env limited to public URL and publishable key.
- QA/audit reviewer: run type/build/RLS checks and verify no service role leaks to the web app.

## Implemented Shape

```text
apps/web
  src/app
  src/components
  src/lib
```

The root package now uses npm workspaces:

- `npm run dev:web`
- `npm run build:web`
- `npm run typecheck:web`

## Supabase Auth Boundary

The web app uses:

- `@supabase/ssr`
- `@supabase/supabase-js`
- browser client only for browser-safe operations
- server client for Server Components and route handlers
- proxy session refresh using `auth.getClaims()`

Public variables:

- `NEXT_PUBLIC_SUPABASE_URL`
- `NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY`

Forbidden in `apps/web`:

- service role key
- direct database URL
- FTP credentials
- legacy MySQL credentials
- Atlas credentials

## Email Code UX Contract

FinDesk must keep the existing user-facing auth flow:

1. user enters email
2. user receives a 6-digit numeric code
3. user enters the 6-digit code in the same FinDesk window
4. FinDesk opens the hall/workspace

Do not use magic-link-first UX as the primary product behavior.

Supabase `signInWithOtp` sends either a magic link or a numeric OTP depending on the active email
template. The Magic Link template must use `{{ .Token }}`, not only `{{ .ConfirmationURL }}`.

Dashboard setup:

1. Open Supabase Dashboard.
2. Go to `Authentication` -> `Email Templates`.
3. Select `Magic Link`.
4. Set subject to:

```text
Код входа в FinDesk: {{ .Token }}
```

5. Paste the HTML from `supabase/auth-email-templates/magic-link-otp.html`.
6. Save and test the sign-in form.

Script setup, when `SUPABASE_ACCESS_TOKEN` is available:

```bash
SUPABASE_PROJECT_REF=suebhgyqvzcrigfdplot \
SUPABASE_ACCESS_TOKEN=... \
node scripts/supabase_apply_auth_otp_template.cjs
```

If the project is on the Supabase Free tier with the default email provider, template updates are
blocked. Configure custom SMTP first. When the production SMTP config exists locally in
`storage/secrets/prod-config.local.php`, use:

```bash
SUPABASE_PROJECT_REF=suebhgyqvzcrigfdplot \
SUPABASE_ACCESS_TOKEN=... \
npm run setup:supabase:auth-email
```

This script reads SMTP settings from the local secrets file at runtime, enables Supabase custom SMTP,
sets `mailer_otp_length` to `6`, and then applies the numeric-code Magic Link email template. It
must not print or commit SMTP passwords or Supabase access tokens.

The sender name must be `FinDesk` by default. The SMTP mailbox may be inherited from older
infrastructure, but old product names such as Quick Ledger must not appear in user-facing email.

Supabase default OTP throttling allows a new OTP request for the same user roughly once per
60 seconds, and project/email hourly limits may also apply. The UI must keep a resend cooldown
instead of letting users repeatedly request codes.

Optional fallback link template, if a link is needed later:

```html
<a href="{{ .SiteURL }}/auth/confirm?token_hash={{ .TokenHash }}&type=email">Войти</a>
```

The fallback route exists at `/auth/confirm`, but it is not the primary UX.

## Domain Decision

`brkovic.app` remains registered at Namecheap. DNS should point to the selected web deployment
target only after staging smoke tests pass.

Target production URLs:

- `https://brkovic.app`
- `https://www.brkovic.app`

## Current UI Surface

The current Next UI is deliberately a foundation shell:

- email auth entry
- hall
- hall workspace list from real Supabase memberships
- selected workspace shell at `/workspaces/{workspaceId}`
- clean Russian labels
- responsive safe-area layout

It does not pretend that the migrated financial product is already complete.

## First Workspace Bootstrap

The hall only shows real accepted memberships. After the first owner signs in once, provision the
first workspace with:

```bash
npm run bootstrap:foundation:workspace -- \
  --owner-email vetus.nauta@gmail.com \
  --organization-name "Vetus Nauta" \
  --workspace-name "Claudia Z"
```

The command is idempotent. It creates or repairs:

- owner profile
- organization
- yacht workspace
- owner membership
- `Кеш` and `Карта` accounts

Do not restore static workspace cards in the hall as a visual shortcut.

## Acceptance Checks

Run:

```bash
npm run typecheck:web
npm run build:web
npm run check:foundation:sql
npm run smoke:foundation:rls
git diff --check
```

For Supabase remote state:

```bash
supabase db push --db-url "$SUPABASE_DB_POOLER_URL" --skip-vault --dry-run
```

## Next Sprint

Foundation-05 should add server-side business command skeletons:

- create workspace
- invite member
- accept invitation
- list workspaces by membership
- create quick note draft

No financial mutation should be implemented as a direct client-only write.
