# SPRINT-95R - Atlas Write Latency Hardening

Date: 2026-08-13

## Director Opening

Goal: remove the browser-blocking latency discovered during Atlas browser rehearsal, without changing financial formulas.

Director discipline:
- No production deploy was performed.
- No FTP sync was performed.
- No parser/report formula behavior was changed.
- Test records used `atlas browser smoke` marker and were fully removed from Atlas after verification.

## Diagnosis

The Atlas write path was functionally correct but too slow for browser UX because cash balance recalculation updated entries one-by-one.

Old behavior:
- Load all active entries in a cash flow.
- Recalculate `balance_after` in `date, created_seq` order.
- Send one Mongo `updateOne` per entry.

With Atlas network latency, this could turn ordinary create/edit/delete into long synchronous browser waits.

## Implemented

File:
- `server/findesk-v2-atlas-read-server.js`

Change:
- `recalculateCashFlowBalance()` still computes the same balances in the same order.
- Unchanged balances are skipped.
- Changed balances are persisted via one ordered `bulkWrite()` instead of many sequential `updateOne()` calls.

Financial contract preserved:
- Same `opening_balance`.
- Same `cashBalanceDelta()`.
- Same entry ordering: `date`, then `created_seq`.
- Same `balance_after` values.
- No formula changes.

Related harness updates:
- `scripts/v2_atlas_browser_smoke.cjs`
  - Covers real UI read screens.
  - Covers UI create.
  - Covers UI edit.
  - Uses guaranteed Atlas handler cleanup for the disposable smoke entry.
  - Explicitly reports `ui_delete_covered: false`.
- `public/v2-api.php`
  - Local Atlas proxy max timeout ceiling raised to support controlled long-running rehearsal, while default remains opt-in.

## Acceptance Evidence

Passed:

```bash
node --check server/findesk-v2-atlas-read-server.js
node --check scripts/v2_atlas_browser_smoke.cjs
npm run smoke:v2:atlas-proxy
npm run smoke:v2:atlas-browser
npm run smoke:v2:atlas-write
npm run smoke:v2:atlas-runtime
npm run gate:v2:atlas-cutover
```

Browser smoke result:
- `ok: true`
- Desktop overflow: `1365 / 1365`
- Mobile overflow: `390 / 390`
- Disposable entry cleaned: `true`
- UI delete covered: `false`

Database cleanup:
- Removed `9` historical `atlas browser smoke` test entries.
- Removed `23` related test audit rows.
- Final active smoke entries: `0`
- Final any smoke entries: `0`

Clean snapshot:
- `v2_entries`: `1638`
- `v2_audit_log`: `2792`
- `v2_attachments`: `0`
- Claudia Z entries: `279`
- Claudia Z August entries: `39`
- Claudia Z cash now: `3893.00`
- Claudia Z August ending cash: `3893.00`

## Decision

Accepted:
- Atlas write latency hardening for cash-chain recalculation.
- Browser smoke can now complete UI read/create/edit through Atlas proxy.
- Test database pollution was removed.

Rejected:
- Production cutover.
- Claiming UI delete is covered by the browser rehearsal.
- Silent FTP deployment.

## Remaining Blockers

- UI delete needs a dedicated browser scenario stabilization.
- Production FTP cutover still requires explicit authorization.

## Next Step

SPRINT-96R should cover UI delete/recovery specifically:
- create disposable entry through UI;
- re-select the exact operational journal row;
- enter edit mode;
- confirm delete;
- verify row disappears in operational and structural views;
- verify no active smoke rows remain;
- keep screenshots for the delete flow.
