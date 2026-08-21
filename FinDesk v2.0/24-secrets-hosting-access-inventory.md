# 24 — Secrets, Hosting, and Production Access Inventory

## Purpose

Sprint 01 must extract everything useful for connecting FinDesk v2.0 to the real production site and hosting environment.

This includes FTP/SFTP/SSH, hosting, domain, deployment, database, and server/account access information.

## Security rule

Do not commit real secrets, passwords, tokens, private keys, FTP credentials, cPanel credentials, database passwords, API keys, or recovery codes into the repository.

The repository may contain only:

- names of required secrets;
- where they are used;
- where they should be stored;
- owner/account notes;
- connection method notes without secret values;
- `.env.example` placeholders.

## Sprint 01 must inventory

Hosting/server:

```text
hosting provider
account owner
control panel URL
server hostname
server IP if available
production domain
staging domain if any
public_html or deployment path
runtime version
SSL/certificate notes
```

FTP/SFTP/SSH:

```text
FTP host
SFTP host
SSH host
port
username placeholder
remote path
connection type
key-based or password-based access
where credentials are stored
```

Database:

```text
database type
database host
database name placeholder
database user placeholder
connection string variable name
migration method
backup/export notes
```

Environment variables:

```text
APP_URL=
DATABASE_URL=
FTP_HOST=
FTP_USER=
FTP_PASSWORD=
SFTP_HOST=
SFTP_USER=
SSH_HOST=
SSH_USER=
DEPLOY_PATH=
```

Deployment:

```text
manual FTP upload
SFTP deploy
GitHub Actions
hosting panel deploy
Namecheap/cPanel deploy
custom script
```

Domain/DNS:

```text
domain registrar
DNS provider
active DNS records needed
SSL status
subdomains
production URL
staging URL
```

## Required output

```text
Production Access Inventory Report

Hosting provider:
Control panel:
Production domain:
Deployment method:
Deploy path:
Runtime:
Database connection method:
Required secrets list:
Where real secrets are stored:
Missing access items:
Security risks:
Recommended deployment path for v2.0:
```

## 2026-08-13 Local Secret Location Addendum

Do not store secret values in Git.

Known local secret files on Alexey's Linux PC:

- FTP deploy env: `/home/alexey/.config/findesk-v2/ftp.env`
- Production session smoke env: `/home/alexey/.config/findesk-v2/prod-session.env`
- MongoDB Atlas URI: `/home/alexey/GitHub/finance.brkovic.ltd/storage/secrets/mongodb_uri`

Active production data source:

- FinDesk v2 runtime uses PHP `PDO` through `app/db.php` and the server-side MySQL/MariaDB config.
- MongoDB Atlas is intended as the FinDesk product persistence foundation, but is not currently wired into the live v2 runtime.
- Treat the current MySQL/MariaDB v2 runtime as an architecture gap to be repaired by an Atlas restoration/migration sprint, not as a final persistence decision.

Current Atlas status:

- Secret file exists locally with restricted permissions.
- 2026-08-13: local Atlas access restored after current workstation IP `150.228.67.27/32` was added to Atlas Network Access.
- `npm run check:atlas` passes with DNS SRV, TLSv1.3 to all shard hosts, and Mongo ping ok.
- Non-secret labels: project `finance-brkovic-ltd`, cluster `pwa-finance`, database `finance_brkovic_ltd`.
- 2026-08-13: local Atlas sidecar API parity reached `81/81` routes: `36/36` reads and `45/45` writes.
- Treat Atlas as a verified live connection and local sidecar runtime foundation, but not yet cut over into the live PHP v2 runtime.
- Current cutover blocker is procedural, not a route-parity gap: `ftp_production_cutover_not_authorized`.
- 2026-08-13: Atlas write latency was hardened by bulk balance updates.
- 2026-08-13: Atlas browser rehearsal now covers UI read/create/edit/delete through the controlled PHP Atlas proxy. Evidence is saved at `test-results/v2-atlas-browser-smoke/result.json` and consumed by `npm run gate:v2:atlas-cutover`.
- Latest Atlas backup evidence: `storage/production-audits/v2-atlas-backup-20260813163017/atlas-backup.json`, hash `0d6f92e5f73a762b1443234d5129e2c241ab9675a3a149ab9b743647eeb40b9e`.
- 2026-08-13 FTP dry-run evidence: stamp `20260813-183214`, files checked `23`, real server writes `0`, real v1 deletes `0`. Remote differs from local only in `public/v2-api.php` and `public/assets/v2/app.js`; v1 runtime targets are already missing on the server.
- 2026-08-13 production deploy evidence: real FTP sync stamp `20260813-184346`, files uploaded and hash-verified `26/26`, v1 runtime targets absent on server, clean root `/` returns FinDesk v2, old `/assets/app.js`, `/assets/app.css`, `/assets/i18n.js` return `404`, old `/app/findesk_phase2.php` and `/app/ledger.php` return `403`.
- 2026-08-13 production responsive readonly evidence: `test-results/v2-production-responsive-readonly/PROD_RESPONSIVE_READONLY_1786639622811/`, mobile/iPad/desktop operational, summary tabs, training, and archive modal passed without page horizontal overflow.
- 2026-08-13 production auth/cache hotfix evidence: real FTP sync stamp `20260813-190213`, files uploaded and hash-verified `27/27`, including `app/auth.php`. Root `/` serves CSS/JS cache-buster `20260813-prod-auth-hotfix-c`; `/service-worker.js` uses cache `findesk-v2-20260813-prod-auth-hotfix-c`; `/assets/v2/app.css`, `/assets/v2/app.js`, and `/assets/v2/findesk-mark.svg` return `200`. Live `request_code` for the beta account returned SMTP `ok`. Auth-screen browser smoke screenshot: `test-results/prod-auth-hotfix-c-auth-screen.png`.

Deployment rule:

```bash
set -a
. /home/alexey/.config/findesk-v2/ftp.env
set +a
python3 scripts/v2_ftp_sync_runtime.py --delete-v1-runtime
```

Atlas check rule:

```bash
npm run check:atlas
```

Atlas URI update rule:

```bash
FINDESK_MONGO_URI='<new atlas uri>' npm run set:atlas-uri
```

Never paste the real URI into committed files, logs, issue text, or chat transcripts.

## 2026-08-20 Supabase + Vercel Foundation Addendum

The new `brkovic.app` foundation app uses Supabase as the platform database/auth
layer and Vercel as the web deployment layer.

Current non-secret facts:

- Supabase project ref: `suebhgyqvzcrigfdplot`
- Supabase project URL: `https://suebhgyqvzcrigfdplot.supabase.co`
- Vercel integration: Supabase is connected to Vercel.
- GitHub branch under active development: `foundation-brkovic-app-architecture`
- App workspace package: `apps/web`
- Product domain target: `brkovic.app`

Required Vercel environment variables:

```text
NEXT_PUBLIC_APP_ENV=production
NEXT_PUBLIC_APP_DOMAIN=https://brkovic.app
NEXT_PUBLIC_SUPABASE_URL=https://suebhgyqvzcrigfdplot.supabase.co
NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY=<from Supabase API settings / Vercel integration>
FINDESK_DEV_LOGIN_ENABLED=0
```

Do not set production `FINDESK_DEV_LOGIN_ENABLED=1`.

Do not expose `SUPABASE_SERVICE_ROLE_KEY` to browser runtime. It is allowed only
for server-only administrative scripts or local development tools that explicitly
need it.

Current production gate rule:

- GitHub, local code, Supabase migrations, and Vercel build settings must be in
  sync before real Claudia Z data, employee invitations, and accountable-money
  workflows are re-attached.
- Supabase schema changes must be committed migrations and visible in Supabase
  migration history.
- Legacy PHP/MySQL/Atlas runtime is not the product truth for the new
  foundation app. It remains historical/import reference only until audited
  migration steps are accepted.

## 2026-08-21 brkovic.app Vercel DNS Cutover

Non-secret production routing facts:

- Vercel team: `vetus-nauta`
- Vercel project: `finance-brkovic-ltd`
- Git branch: `main`
- App root: `apps/web`
- Checked commit: `032a003`
- Domains added to Vercel: `brkovic.app`, `www.brkovic.app`
- DNS provider in use: Namecheap Hosting cPanel zone, with nameservers
  `dns1.namecheaphosting.com` and `dns2.namecheaphosting.com`

Applied DNS records:

```text
brkovic.app A 216.198.79.1
brkovic.app A 64.29.17.1
www.brkovic.app CNAME 62224e740c9563d5.vercel-dns-017.com.
```

Verified:

- Vercel domain verification for `brkovic.app`: ok
- Vercel domain verification for `www.brkovic.app`: ok
- Forced-resolution HTTPS check for `brkovic.app`: HTTP 200 from Vercel

Local backup path outside Git:

```text
storage/production-audits/dns-brkovic-app-20260821-121815/
```
