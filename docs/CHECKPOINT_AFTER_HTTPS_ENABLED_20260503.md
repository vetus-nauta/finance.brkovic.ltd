# Quick Ledger — Checkpoint After HTTPS Enabled

## Project

Domain: https://finance.brkovic.ltd  
Path: /home/brkovic/finance.brkovic.ltd  
Main app: /app.php  
Current asset version: 20260503-22

## HTTPS status

Confirmed working:

- SSL certificate installed for finance.brkovic.ltd
- HTTPS root works
- HTTPS /app.php works
- HTTPS API works
- HTTP root redirects to HTTPS
- HTTP /app.php redirects to HTTPS

Certificate:
- CN: finance.brkovic.ltd
- SAN: finance.brkovic.ltd
- Issuer: Sectigo Public Server Authentication CA DV R36
- Valid until: 17 Nov 2026

## What happened

Namecheap SSL initially got stuck in PENDING.

Root cause:
- Namecheap auto-installer placed the HTTP DCV validation file in a duplicated wrong path:
  /home/brkovic/home/brkovic/finance.brkovic.ltd/.well-known/pki-validation/

Correct document root:
  /home/brkovic/finance.brkovic.ltd

Fix:
- Copied the DCV file into:
  /home/brkovic/finance.brkovic.ltd/.well-known/pki-validation/
- Confirmed HTTP 200 OK for the validation URL.
- Namecheap support pushed issuance.
- Synced certificate through cPanel → Namecheap SSL.
- Enabled HTTPS Redirect in Namecheap SSL/cPanel.

## Current working product state

Quick Ledger MVP includes:

- HTTPS secure web app
- HTTP → HTTPS redirect
- email-code auth via SMTP
- mobile login
- one-time-code friendly code input
- personal ledger
- group ledger
- sections
- documents/files
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

## SMTP status

Confirmed working:

- SMTP auth mail works
- code arrives by email
- mail_method: smtp
- dev fallback remains in storage/logs/auth_codes.log

Non-secret SMTP settings:

- host: brkovic.ltd
- port: 465
- secure: ssl
- username: no-reply@brkovic.ltd
- from_email: no-reply@brkovic.ltd
- from_name: Quick Ledger

Do not expose passwords in chat or docs.

## Important config note

app/db.php expects flat DB keys in config.local.php:

- db_host
- db_name
- db_user
- db_pass

Do not rewrite config.local.php with nested db.host/db.name/db.user/db.pass format.

## Next recommended steps

1. Test full login on mobile using https://finance.brkovic.ltd/app.php
2. Check PWA/service-worker behavior under HTTPS.
3. Add module navigation so Business Desk is not buried in the long main flow.
4. Keep Business Desk as separate module/workspace later:
   - mobile: separate section
   - desktop: dedicated workspace
5. Later: proper PDF download, company logo upload, multiple proforma items.
