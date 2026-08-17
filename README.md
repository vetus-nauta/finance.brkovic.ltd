# finance.brkovic.ltd

FinDesk v2 production codebase for `finance.brkovic.ltd`.

## Source Of Truth

FinDesk v1 has been decommissioned from the runtime tree. The product source of truth is now:

- UI: `public/v2.php`
- API: `public/v2-api.php`
- Domain code: `app/v2/`
- Shared auth bridge: `public/api.php` + `app/auth.php`
- Schema and contracts: `FinDesk v2.0/`

Do not use deleted v1 modules, old `docs/AI_TEAM` materials, old yacht price scripts, or old Atlas server code as product truth. Claudia Z data is a beta corpus for `space_type=yacht`, not a source of private product rules.

The canonical live address is:

```text
https://finance.brkovic.ltd/
```

Compatibility paths:

- `/v2.php` opens the same v2 UI.
- `/app.php` redirects to `/`.
- `/api.php` is retained only for email-code authentication until auth is moved fully into `v2-api.php`.

## Local Config

Do not commit `app/config.local.php`.

Create it from:

```bash
cp app/config.local.example.php app/config.local.php
```

Then fill DB and SMTP credentials locally/on server.

For localhost, use mail mode `log` so the 6-digit auth code is written to:

```text
storage/logs/auth_codes.log
```

## Local Run

```bash
php -S 127.0.0.1:18889
```

Open:

```text
http://127.0.0.1:18889/
```

## Verification

Use the v2 checks only:

```bash
npm run smoke:v2
npm run smoke:v2:auth
npm run smoke:v2:http
npm run smoke:v2:ui
npm run audit:v2:claudia-z
```

For browser-level regression:

```bash
npm run smoke:v2:browser
```

## Deployment

Local secret locations on Alexey's PC:

```text
/home/alexey/.config/findesk-v2/ftp.env
/home/alexey/.config/findesk-v2/prod-session.env
storage/secrets/mongodb_uri
```

Do not print or commit these values.
The PHP runtime uses `app/db.php` for the current server backend. MongoDB Atlas is the required v2 parity/persistence target and must be kept synchronized before production sign-off.
Atlas URI is stored locally. The Atlas project is `finance-brkovic-ltd`, cluster `pwa-finance`, database `finance_brkovic_ltd`; verify with `npm run check:atlas` before migration or sync work.
To replace the local Atlas URI safely, use `FINDESK_MONGO_URI='<new atlas uri>' npm run set:atlas-uri`.

Atlas sync discipline:

```bash
npm run payload:v2:atlas
npm run backup:v2:atlas
node scripts/v2_atlas_commit_payload.js --payload <payload> --backup <backup> --allow-users-update --commit --confirm WRITE_V2_TO_ATLAS_20260813
npm run parity:v2:mysql
npm run parity:v2:atlas
npm run parity:v2:compare
```

The v2 runtime upload manifest is maintained in:

```text
scripts/v2_ftp_sync_runtime.py
```

Canonical v2 deploy with v1 runtime removal:

```bash
python3 scripts/v2_ftp_sync_runtime.py --delete-v1-runtime
```

Use `--dry-run` first when checking a new FTP target.

The repository intentionally does not include:

- `storage/`
- `app/config.local.php`
- logs
- FTP backup files
- zip archives
