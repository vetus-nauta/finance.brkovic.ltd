# Free Tier Infrastructure

Date: 2026-08-20

## Strategy

Start on free tiers where safe, but do not design around free-tier limits as product truth.

Target start:

- Supabase Free for PostgreSQL/Auth/Storage during foundation and early staging.
- Vercel Hobby for early web previews if license/use case allows it.
- Expo/EAS Free for mobile development builds while usage is low.
- GitHub for source control and CI.

## Upgrade Path

- Supabase Free -> Supabase Pro -> larger managed PostgreSQL/self-hosted PostgreSQL if needed.
- Vercel Hobby -> Vercel Pro or another Next-compatible host.
- Supabase Storage -> Supabase Pro storage or S3-compatible storage adapter.
- Expo Free -> Expo paid plan/EAS paid usage when build volume grows.
- AI/OCR providers -> budgeted provider adapters.

## Guardrails

- No paid service is enabled without owner approval.
- No architecture depends on a specific free quota number.
- Production financial documents require private storage even on free tier.
- Backups must be documented because free tier may not provide production-grade backup features.
