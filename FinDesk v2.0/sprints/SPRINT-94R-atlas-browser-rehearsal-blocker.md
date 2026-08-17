# SPRINT-94R - Atlas Browser Rehearsal Blocker

Date: 2026-08-13

## Director Opening

Goal: run the real FinDesk v2 browser UI against the controlled local Atlas proxy.

Director discipline:
- No production deploy was performed.
- No FTP sync was performed.
- Test entries used `atlas browser smoke` marker and were cleaned.
- Financial formulas and report math were not changed.

## Implemented

Files:
- `scripts/v2_atlas_browser_smoke.cjs`
- `package.json`
- `public/v2-api.php`

New command:

```bash
npm run smoke:v2:atlas-browser
```

The browser smoke:
- starts the Atlas sidecar on a temporary local port;
- starts a temporary PHP UI harness with real `v2.php`, `app.js`, `app.css`, and `/v2-api.php`;
- injects a safe local `current_user`;
- opens Claudia Z through `FINDESK_V2_RUNTIME=atlas_write`;
- captures desktop screenshots for operational, summary, training, and hall;
- checks desktop/mobile horizontal overflow;
- attempts create/edit/delete of a disposable operational record.

## Positive Evidence

The real UI can open through Atlas proxy:
- Hall loads.
- Claudia Z workspace loads.
- Operational journal loads.
- Summary screen loads.
- Training screen loads.
- Claudia Z August entries load through `/v2-api.php` -> Atlas sidecar.

Screenshots written:
- `test-results/v2-atlas-browser-smoke/desktop-operational.png`
- `test-results/v2-atlas-browser-smoke/desktop-summary.png`
- `test-results/v2-atlas-browser-smoke/desktop-training.png`
- `test-results/v2-atlas-browser-smoke/desktop-hall.png`

Observed state:
- Cash displayed: `3893.00`
- August workspace opens with Atlas data.

## Blocker

The browser write path is not acceptable for production cutover yet.

Observed:
- UI create through Atlas proxy can create the entry.
- UI edit through Atlas proxy can mutate the entry.
- The browser did not receive the expected response within `120s` during the edit flow.
- Direct cleanup through Atlas handler succeeded, proving the write logic is functional but too slow or response-unsafe for browser UX.

Cleaned active test entry:
- `7b6ef9b1-a267-4673-bb60-d83caac5be2e`
- Final active smoke entries: `0`

Earlier archived smoke rows remain archived only and do not affect active finance:
- `9d0707c9-6ac7-47b6-8e89-4a0029202c1e`
- `f93ecda2-6782-40e2-92c7-8aa06228a899`
- `8fe700e0-747f-409f-86a1-e16943e12755`

## Diagnosis

Atlas API route parity is complete, but browser writes that trigger balance-chain recalculation are too slow for synchronous PHP proxy UX.

This is a product readiness blocker, not a missing API route.

Likely required fix:
- split user-visible write response from heavy recalculation;
- return the changed entry quickly;
- move chain recomputation to bounded affected rows or a background/queued recalculation marker;
- show a clear recalculation status in UI when needed.

## Decision

Accepted:
- Local browser read rehearsal against Atlas proxy.
- Controlled proxy wiring.
- Screenshots and basic screen navigation evidence.

Rejected:
- Production Atlas cutover.
- Claiming local/prod are `100%` symmetric on Atlas.
- Deploying while edit/delete can exceed browser-safe response time.

## Next Step

SPRINT-95R should optimize the Atlas write response path:
- audit `createEntryInSession`, `updateEntry`, `deleteEntry`, and balance-chain recomputation;
- preserve current financial truth;
- make ordinary open-month create/edit/delete browser-safe;
- add a targeted latency smoke with a strict threshold;
- rerun `npm run smoke:v2:atlas-browser`.
