# SPRINT-93R - Controlled Atlas Proxy Rehearsal

Date: 2026-08-13

## Director Opening

Goal: add a controlled local PHP gateway path from the existing FinDesk v2 UI/API surface to the Atlas sidecar.

Director discipline:
- No production deploy was performed.
- No FTP sync was performed.
- No financial formulas, parser rules, or report math were changed.
- The default runtime remains `mysql`.
- Atlas proxy mode is opt-in through environment variables.

## Implemented

Files:
- `public/v2-api.php`
- `server/findesk-v2-atlas-read-server.js`
- `scripts/v2_atlas_php_proxy_smoke.sh`
- `package.json`
- `scripts/v2_atlas_cutover_gate.js`

Runtime modes:
- `mysql`: unchanged default PHP/MySQL behavior.
- `atlas_shadow`: unchanged GET shadow comparison behavior.
- `atlas_read`: proxies GET requests to the Atlas sidecar and blocks writes.
- `atlas_write`: proxies GET/POST/PATCH/DELETE requests to the Atlas sidecar.

Safety:
- Atlas proxy is local-only by default through `FINDESK_V2_ATLAS_PROXY_LOCAL_ONLY=1`.
- Non-local hostnames return `atlas_proxy_local_only` unless explicitly disabled.
- `atlas_read` write attempts return `atlas_runtime_write_not_enabled`.
- Production cutover still requires explicit FTP/deploy authorization.

Sidecar HTTP body limit:
- Raised JSON request limit from `1 MiB` to `12 MiB`.
- Reason: browser multipart attachment uploads are converted by PHP proxy into JSON/base64 payloads.

## Smoke Coverage

New script:

```bash
npm run smoke:v2:atlas-proxy
```

It proves:
- Atlas sidecar starts on a temporary local port.
- PHP `/v2-api.php` starts on a temporary local port.
- `atlas_read` can fetch workspaces through PHP.
- `atlas_read` blocks POST writes.
- `atlas_write` can fetch Claudia Z August entries through PHP.
- `atlas_write` can execute a non-mutating parse preview through PHP.

No financial entry is created by this smoke.

## Acceptance Evidence

Passed:

```bash
php -l public/v2-api.php
node --check server/findesk-v2-atlas-read-server.js
bash -n scripts/v2_atlas_php_proxy_smoke.sh
node --check scripts/v2_atlas_cutover_gate.js
npm run smoke:v2:atlas-proxy
npm run smoke:v2:atlas-runtime
npm run smoke:v2:atlas-write
npm run gate:v2:atlas-cutover
```

Gate state after this sprint:
- Total routes: `81`
- Reads: `36/36`
- Writes: `45/45`
- Atlas proxy available: `true`
- Unsupported reads: `0`
- Unsupported writes: `0`

Remaining blockers:
- `browser_cutover_rehearsal_not_completed`
- `ftp_production_cutover_not_authorized`

## Decision

Accepted:
- Controlled local PHP-to-Atlas proxy path exists.
- The API gateway can reach Atlas sidecar in read and write modes.
- The production gate correctly remains blocked.

Rejected:
- Calling this a production cutover.
- Enabling Atlas proxy on a non-local host by default.
- Silent deployment.

## Next Step

SPRINT-94R should be the full local browser rehearsal:
- Start Atlas sidecar.
- Start local PHP site with `FINDESK_V2_RUNTIME=atlas_write`.
- Open the actual UI.
- Run the user-critical workflow manually or with Playwright:
  - hall;
  - Claudia Z operational journal;
  - summary;
  - reports/archive;
  - training;
  - create/edit/delete a disposable record and verify cleanup;
  - no viewport overflow on desktop and mobile widths.

Production deployment remains blocked until that evidence is accepted and Alexey explicitly authorizes FTP sync.
