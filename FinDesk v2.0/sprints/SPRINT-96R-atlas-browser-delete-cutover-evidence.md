# SPRINT-96R - Atlas Browser Delete Cutover Evidence

Date: 2026-08-13

## Director Opening

Goal: finish the Atlas browser rehearsal blocker by proving that the real UI can create, edit, and delete a disposable operational entry through the controlled Atlas proxy.

Director discipline:
- No production deploy was performed.
- No FTP sync was performed.
- No financial formulas were changed.
- Browser evidence is written to a machine-readable local artifact and consumed by the cutover gate.

## Agents

### Frontend Interaction Agent

Finding:
- The UI returned to create mode after edit, but browser automation clicked the row before the final post-edit reload/status completed.
- The product intentionally suppresses immediate row recapture briefly after resetting the input, to prevent noisy hover/click behavior.

Change:
- After edit/delete, the UI now clears active/selected entry state and returns the input bar to a new blank record.
- Delete no longer auto-focuses a neighboring row after deletion.

Files:
- `public/assets/v2/app.js`

### QA, Audit, and Acceptance Agent

Finding:
- Browser smoke previously covered create/edit, but delete was not represented as evidence.
- The test needed explicit step diagnostics instead of generic Playwright timeouts.

Change:
- Browser smoke now writes `test-results/v2-atlas-browser-smoke/result.json`.
- Browser smoke result includes:
  - workspace id;
  - desktop overflow;
  - mobile overflow;
  - disposable entry cleanup;
  - UI delete coverage.
- Cutover gate now reads that artifact before removing the browser rehearsal blocker.

Files:
- `scripts/v2_atlas_browser_smoke.cjs`
- `scripts/v2_atlas_cutover_gate.js`

### Financial Logic Engine Reviewer

Finding:
- UI edit/delete coverage did not require formula changes.
- Cash-chain values remained stable after all tests and cleanup.

Verified values:
- Claudia Z entries: `279`
- Claudia Z August entries: `39`
- Cash now: `3893.00`
- August ending cash: `3893.00`

## Acceptance Evidence

Passed:

```bash
node --check public/assets/v2/app.js
node --check scripts/v2_atlas_browser_smoke.cjs
node --check scripts/v2_atlas_cutover_gate.js
npm run smoke:v2:atlas-browser
npm run smoke:v2:atlas-proxy
npm run smoke:v2:atlas-write
npm run smoke:v2:atlas-runtime
npm run gate:v2:atlas-cutover
npm run backup:v2:atlas
```

Browser smoke result:

```json
{
  "ok": true,
  "ui_delete_covered": true,
  "disposable_entry_cleaned": true,
  "desktop_overflow": {
    "width": 1365,
    "viewport": 1365
  },
  "mobile_overflow": {
    "width": 390,
    "viewport": 390
  }
}
```

Cutover gate result:
- Route surface: `81/81`
- Reads: `36/36`
- Writes: `45/45`
- Unsupported reads: `0`
- Unsupported writes: `0`
- Browser rehearsal: `ok`
- Remaining blocker: `ftp_production_cutover_not_authorized`

Atlas cleanup:
- Removed physical smoke entry docs: `7`
- Removed smoke audit docs: `21`
- Active `atlas browser smoke` entries: `0`
- Any `atlas browser smoke` entries: `0`

Fresh backup:
- Path: `storage/production-audits/v2-atlas-backup-20260813163017/atlas-backup.json`
- Hash: `0d6f92e5f73a762b1443234d5129e2c241ab9675a3a149ab9b743647eeb40b9e`

## Decision

Accepted:
- Browser create/edit/delete rehearsal through Atlas proxy.
- Cutover gate evidence integration.
- UI return-to-new-entry behavior after edit/delete.

Rejected:
- Production deployment without explicit FTP cutover authorization.
- Treating archived smoke records as acceptable long-term Atlas residue.

## Current Status

Atlas runtime parity is ready locally.

Production cutover remains blocked only by explicit deployment authorization:

```text
ftp_production_cutover_not_authorized
```
