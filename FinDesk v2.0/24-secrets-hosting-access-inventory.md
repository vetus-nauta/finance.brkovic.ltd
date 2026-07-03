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
