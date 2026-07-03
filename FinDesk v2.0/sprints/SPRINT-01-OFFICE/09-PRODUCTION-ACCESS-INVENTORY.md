# Sprint 01 — Production Access Inventory

Director: Codex Director, Sprint 01
Date: 2026-07-03

## Security Rule

No real passwords, tokens, private keys, database credentials, FTP credentials, API keys, recovery codes, cookies, or cPanel credentials are recorded here.

## Known Production Facts From Repository Evidence

| Item | Value / finding | Confidence |
| --- | --- | --- |
| GitHub repo | `vetus-nauta/finance.brkovic.ltd` | Confirmed by `gh repo view` |
| Production domain | `https://finance.brkovic.ltd` | Confirmed in public files and old production reports |
| Live app path | `/app.php` | Confirmed in old production reports |
| Public root | `/` serves public landing | Confirmed in public files and old smoke reports |
| Production deploy path | `/home/brkovic/finance.brkovic.ltd` | Found in old deploy/access docs |
| FTP tree/path | `/finance.brkovic.ltd` | Found in old deploy artifacts |
| Hosting type | Apache/PHP with `.htaccess`; Namecheap/cPanel inferred | Needs current owner confirmation |
| Database engine | MariaDB `11.4.10-MariaDB-cll-lve-log` | Confirmed in old production deploy reports |
| DB external port | `3306` reported not reachable externally from old environment | Old environment finding |
| FTP port | `21` reported reachable from old environment | Old environment finding |
| SSL | HTTPS active; old checkpoint mentions Sectigo DV and corrected document root | Needs current renewal owner confirmation |
| Last confirmed production marker | `20260528-records-scroll1` | From `44_RECORDS_SCROLL_HOTFIX_PRODUCTION_2026-05-28.md` |
| Later open-items release | `20260528-open-sprint1` local only, not deployed | Confirmed blocked by missing deploy env vars |

## Deployment Method

Historical production deployment used a controlled manual package flow:

1. Select narrow runtime package.
2. Back up production files/storage over FTP.
3. Back up production DB.
4. Run DB preflight.
5. Apply approved SQL separately from web file upload.
6. Upload selected runtime files.
7. Run production smoke.
8. Remove temporary DB-gate and verify it returns `404`.
9. Record checksums, smoke ids, and rollback source.

The full dirty worktree must not be uploaded blindly.

## Required Secret Names / Placeholders

General v2 inventory names:

```text
APP_URL
DATABASE_URL
FTP_HOST
FTP_USER
FTP_PASSWORD
SFTP_HOST
SFTP_USER
SSH_HOST
SSH_USER
DEPLOY_PATH
```

Observed old deploy/runtime names:

```text
FINDESK_FTP_HOST
FINDESK_FTP_USER
FINDESK_FTP_PASS
FINDESK_FTP_ROOT
FINDESK_DB_GATE_URL
OPENAI_API_KEY
OPENAI_MODEL
OPENAI_API_BASE
FINDESK_MONGO_URI
FINDESK_MONGO_URI_FILE
FINDESK_MONGO_DB
FINDESK_PORT
FINDESK_HOST
```

PHP config placeholders:

```text
app_url
db_host
db_name
db_user
db_pass
session_cookie_name
mail.mode
mail.host
mail.port
mail.secure
mail.username
mail.password
mail.from_email
mail.from_name
openai.enabled
openai.api_key
openai.api_key_file
openai.model
openai.endpoint
openai.timeout_seconds
openai.max_output_tokens
openai.web_search_enabled
openai.web_search_tool
```

## Where Real Secrets Must Live

- `app/config.local.php` or equivalent private server-only config for PHP runtime.
- Server environment for deploy and API keys.
- `storage/secrets/openai_api_key` only if a key-file pattern is approved.
- `storage/secrets/mongodb_uri` only if a Mongo/Atlas support path is approved.
- Private backup storage outside webroot for DB/file backups.

These paths must stay out of git and out of chat.

## Database Connection Notes

Old runtime:

- PHP PDO MySQL/MariaDB.
- DSN shape: `mysql:host=<db_host>;dbname=<db_name>;charset=utf8mb4`.
- PDO mode: exceptions, associative fetch, emulated prepares disabled.
- Production engine evidence: MariaDB `11.4.10-MariaDB-cll-lve-log`.

Director DB decision for Sprint 02:

- Default target for deployable v2 on this production site is MariaDB-compatible clean v2 schema, because the proven production runtime is MariaDB/PDO on shared hosting.
- Current `FinDesk v2.0/sql/clean-core-schema.sql` is a logical clean-core source of truth but appears PostgreSQL-style (`pgcrypto`, UUID defaults, `jsonb`, `timestamptz`).
- Sprint 02 must either produce a MariaDB-compatible v2 migration set or obtain an explicit owner decision to provision PostgreSQL hosting before implementation.
- Under no condition may old MySQL legacy tables be treated as v2 schema.

## Domain / DNS / SSL Notes

Known:

- Production URL: `https://finance.brkovic.ltd`.
- Public landing canonical and sitemap point to `https://finance.brkovic.ltd/`.
- SSL was active in old checkpoint/deploy reports.

Missing:

- Registrar confirmation.
- DNS provider.
- Current DNS record inventory.
- SSL renewal owner and renewal path.
- Staging subdomain, if any.

## Missing Access Items

These are expected to carry forward without blocking the no-secret Sprint 01 report:

- current hosting provider confirmation;
- control panel URL;
- account owner;
- current server IP;
- current FTP/SFTP/SSH method and port;
- credential owner/storage location;
- staging domain/path;
- DNS provider and records;
- SSL renewal owner;
- backup storage location and restore owner;
- current DB gate or migration channel;
- current deploy owner.

## Security Risks

- Old deploys used a temporary DB-gate. This must remain temporary, protected, logged, and removed after use.
- `app/config.local.php`, `storage/`, `backups/`, logs, DB dumps, and secret files must never be committed.
- Old production smoke data and QA artifacts may contain test identities; do not publish or expand them into product docs.
- `.htaccess` protection must be revalidated on any hosting change.

## Recommended Deployment Path For v2

For Sprint 02+, use controlled staging/local flow first:

1. Decide DB engine.
2. Build clean v2 namespace.
3. Build clean v2 migrations.
4. Prepare `.env.example` or config example with placeholders only.
5. Run local/staging smoke.
6. Before production, require backup, preflight, selected package, rollback owner, and smoke owner.

