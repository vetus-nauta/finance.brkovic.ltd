# Quick Ledger — Checkpoint After SMTP Email Auth

## Project

Domain: http://finance.brkovic.ltd  
Path: /home/brkovic/finance.brkovic.ltd  
Main app: /app.php

## Confirmed working

- DB connection restored after config.local.php format fix
- config.local.php now uses flat DB keys:
  - db_host
  - db_name
  - db_user
  - db_pass
- SMTP config stored in config.local.php under mail
- config.local.php permissions set to 600
- SMTP email delivery works
- Auth code arrives by email
- mail_method: smtp
- PHP mail() is no longer the primary delivery path
- Dev fallback remains: storage/logs/auth_codes.log
- Mobile auth tested and works
- Code input has:
  - inputmode="numeric"
  - autocomplete="one-time-code"
  - pattern="[0-9]*"
  - maxlength="6"

## SMTP settings

Do not expose passwords in chat or docs.

Current non-secret settings:
- SMTP host: brkovic.ltd
- SMTP port: 465
- SMTP secure: ssl
- SMTP username: no-reply@brkovic.ltd
- SMTP from email: no-reply@brkovic.ltd
- SMTP from name: Quick Ledger

## Important note

Earlier mistake: config.local.php was temporarily written with nested db keys:
- db.host
- db.name
- db.user
- db.pass

But app/db.php expects flat keys:
- db_host
- db_name
- db_user
- db_pass

This has been fixed and DB connection is OK.

## Current working product state

Quick Ledger MVP includes:
- email-code auth
- mobile login
- personal ledger
- group ledger
- sections
- documents
- reports
- group reports
- messages
- unread modal
- Business Desk
- company profile
- clients
- proforma create/list/get
- proforma print/save PDF layout
- company logo slot in proforma

## Next recommended steps

1. Enable/fix HTTPS for finance.brkovic.ltd.
2. Finalize mobile auth/PWA behavior under HTTPS.
3. Add module navigation so Business Desk is not buried in the long main flow.
4. Create another full project snapshot after HTTPS is verified.
