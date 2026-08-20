# Infrastructure Limits

Date checked: 2026-08-20

Sources checked:

- Supabase pricing: https://supabase.com/pricing
- Supabase billing docs: https://supabase.com/docs/guides/platform/billing-on-supabase
- Vercel pricing: https://vercel.com/pricing
- Expo pricing: https://expo.dev/pricing
- Expo billing docs: https://docs.expo.dev/billing/plans/

## Supabase Free

Observed official limits on 2026-08-20:

- $0/month
- 50,000 monthly active users
- 500 MB database size
- shared CPU / 500 MB RAM
- 5 GB egress
- 5 GB cached egress
- 1 GB file storage
- 2 active free projects
- free projects pause after 1 week of inactivity
- no automatic backups on Free
- community support

Architecture implication:

- OK for foundation/dev/staging experiments.
- Not enough as a serious production backup posture.
- Use migrations and manual exports from day one.

## Supabase Pro

Observed official starting point on 2026-08-20:

- starts from $25/month
- 100,000 monthly active users included
- 8 GB disk size per project
- 250 GB egress
- 100 GB file storage
- daily backups stored for 7 days
- email support

Architecture implication:

- Natural first paid production step.

## Vercel Hobby

Observed official public pricing on 2026-08-20:

- $0/month
- automatic CI/CD
- global CDN
- WAF/DDoS mitigation
- 1M edge requests/month included
- 100 GB/month fast data transfer included
- 1M function invocations/month included
- 4 hours/month active CPU included
- 1 developer seat

Architecture implication:

- Good for preview/early web client.
- Verify commercial-use fit and project limits before production launch.
- Keep web deploy portable to another Next-compatible host.

## Expo/EAS Free

Observed official public pricing on 2026-08-20:

- $0/month
- 15 Android and 15 iOS builds
- low-priority queue
- 60 minutes CI/CD workflows
- submit to app stores
- updates to 1K MAUs

Architecture implication:

- Good for mobile foundation and early testing.
- Paid plan likely needed once regular release cadence begins.

## Required Recheck

Recheck limits before:

- creating paid accounts
- production launch
- enabling public signups
- app store submission
- any commitment to a client SLA
