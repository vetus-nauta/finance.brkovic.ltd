# Quick Ledger — Checkpoint After NAV-1 Module Shell

Date: 2026-05-03  
Project path: `/home/brkovic/finance.brkovic.ltd`  
Live app: `https://finance.brkovic.ltd/app.php`

## Status

This checkpoint records the working state after:

- HTTPS was enabled and verified.
- PWA manifest was improved.
- PWA icons were generated and exposed.
- Apple mobile web app meta was added.
- Install UX logic was improved.
- The first module navigation shell was added.

## Confirmed working before this checkpoint

### HTTPS / app

- `https://finance.brkovic.ltd/` returns HTTP 200.
- `https://finance.brkovic.ltd/app.php` returns HTTP 200.
- HTTP redirects to HTTPS.
- API `current_user` returns valid JSON for unauthenticated session.

### PWA foundation

- `manifest.webmanifest` returns HTTP 200.
- Manifest content type is `application/manifest+json`.
- Manifest includes:
  - `id: /app.php`
  - `start_url: /app.php`
  - `scope: /`
  - `display: standalone`
  - categories
  - icons
- Icons exist and return HTTP 200:
  - `/assets/icon-180.png`
  - `/assets/icon-192.png`
  - `/assets/icon-512.png`
- Apple touch icon is connected.
- Apple mobile web app meta is present.

### Install UX

- Install button uses auto detection.
- iPhone/iPad Safari instruction exists.
- iPhone Chrome / iPhone browser instruction exists.
- Android instruction exists.
- Desktop Chrome/Edge instruction exists.
- Real UX note recorded:
  - On iPhone, Share can appear in the address bar, bottom toolbar, or browser menu depending on browser/iOS layout.
  - In iPhone Chrome, Share was found in the address bar.
  - Safari remains the most reliable path for Add to Home Screen.

### NAV-1 module shell

Module navigation was added after login:

- Ledger
- Reports
- Groups
- Business
- Settings

Current module behavior:

- Ledger module contains the existing ledger stack.
- Reports tab currently opens the report panel inside Ledger.
- Groups module contains existing group tools, invites, messages, members.
- Business module contains current Business Desk.
- Settings module contains:
  - Install web app
  - Donate
  - Logout
  - language foundation note

Important: NAV-1 is a safe shell. It does not yet fully split Reports into its own independent workspace.

## Files changed in this stage

- `.htaccess`
- `public/manifest.webmanifest`
- `public/index.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/icon-180.png`
- `public/assets/icon-192.png`
- `public/assets/icon-512.png`

## Current asset version

`20260503-24`

## Confirmed technical checks

- `php -l public/app.php` passed.
- `php -l public/index.php` passed.
- NAV marker counts:
  - CSS NAV markers: 1
  - JS NAV markers: 1
  - HTML module-nav: 1
  - HTML module containers: 4
- `https://finance.brkovic.ltd/app.php` returns HTTP 200.

## Product direction after this checkpoint

Next recommended steps:

1. Manual browser/mobile verification:
   - Ledger
   - Reports
   - Groups
   - Business
   - Settings
   - Logout
   - Install modal

2. If visual check is good:
   - NAV-2: refine module UX.
   - Reports should later become a true separate module, not only a tab that opens `reportPanel` in Ledger.
   - Business Desk layout should be improved as a wider separate workspace.
   - Settings should later include real language selector and UI preferences.

3. Keep future work disciplined:
   - Check current state first.
   - Back up changed files.
   - Patch one small area at a time.
   - Run PHP lint after PHP changes.
   - Use grep markers.
   - Do not expose secrets.
   - Do not rewrite `app/config.local.php` incorrectly.
