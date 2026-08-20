# Security Model

Date: 2026-08-20

## Security Boundaries

1. Supabase Auth for identity/session.
2. PostgreSQL RLS for tenant isolation.
3. Server-side command authorization for critical mutations.
4. Private storage buckets with signed URLs.
5. UI role visibility as convenience only.

## Mandatory Rules

- No credentials in Git.
- No public financial document buckets.
- No client-trusted roles.
- No float money.
- No direct critical mutation from browser/mobile to tables.
- No production schema changes outside migrations.
- No destructive migration without backup and rollback window.
- No AI/OCR final financial mutation without workflow confirmation.

## Secrets

Tracked files may contain examples only.

Real secrets live in:

- local `.env` files, ignored by Git
- Supabase project secrets
- Vercel environment variables
- Expo/EAS secrets where needed
- a private password manager/manual handoff controlled by the owner

If a secret was ever committed to Git history, rotate it before production use.

## Baseline Test Requirements

- anti-IDOR API tests
- RLS direct-client tests
- auth rate-limit tests
- upload MIME/size tests
- XSS rendering tests for user-entered text
- audit-log creation tests for critical commands
- dependency scanning in CI
