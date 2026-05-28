# Production Smoke Runbook For 100% MVP

Date: 2026-05-27

Owner: QA Release Engineer

Status: executed for HTTP/API MVP path; browser visual matrix remains optional follow-up.

Execution record:

- `docs/AI_TEAM/27_PRODUCTION_DEPLOY_REPORT_2026-05-27.md`
- smoke id: `20260527192655`
- group id: `4`
- final report id: `20`

## Scope

This runbook verifies the deployed FinDesk 100% MVP after production upload. It does not authorize production upload, database changes, smoke execution on the live site, or application code changes.

Business-MVP product gate is approved. Production gate remains separate and must prove that the selected deployment package works on the live site and can be rolled back.

Source basis:

- `docs/AI_TEAM/15_100_PERCENT_MVP_CONTROL.md`
- `docs/AI_TEAM/09_MVP_DEPLOY_HANDOFF.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`

## Pre-Smoke Prerequisites

Do not start production smoke until all items below are available.

1. Deployment authorization:
   - Project Director or Deploy Owner confirms production smoke window.
   - Production URL/domain is provided.
   - Production smoke tester account(s) are named.
   - Test-data policy is explicit: whether smoke may create harmless rows/messages/proformas or must use existing safe fixtures only.
2. Package evidence:
   - Deployment mode is recorded: full current working-tree bundle or narrow MVP runtime bundle.
   - Exact uploaded file list, deployment artifact id, or release package id is recorded.
   - Source baseline is recorded, including `HEAD=72b38e6` if it remains the selected baseline.
   - Local/test/reset/support files are excluded unless explicitly accepted by the Deploy Owner.
3. Backup evidence:
   - Pre-deploy production database backup exists and has a timestamp/reference.
   - Pre-deploy production files backup exists and has a timestamp/reference.
   - Backup references contain no passwords, tokens, raw cookies, private keys, or customer financial dumps.
4. Rollback readiness:
   - Rollback owner is named and reachable during the smoke window.
   - Rollback procedure is documented without secrets.
   - Runtime SQL/migration changes, if any, are listed with restore/reverse approach.
   - Post-rollback verification checklist is available: app loads, login/session works, current report opens, known closed report opens, no fatal production error remains.
5. Smoke environment:
   - Fresh browser profile or incognito session is available.
   - Mobile viewport `390 x 844` is mandatory.
   - Tablet `820 x 1180` and desktop `1440 x 900` are strongly recommended before CEO use.
   - Network/devtools capture is available for checked API calls, but secrets must not be copied into reports.
6. Safe data anchors:
   - Approved smoke group/account is identified.
   - Known production-safe closed `report_id` is identified, or Deploy Owner approves creating/finalizing a dedicated smoke report.
   - Safe proof fixture is identified if proof download is part of the smoke.

If any prerequisite is missing, smoke status is `BLOCKED`, not `PASS`.

## Exact Must-Pass Smoke List After Deploy

Run in the order below. Stop immediately on any P0 stop criterion listed later in this document.

### 1. Deploy Evidence Capture

Must record:

- production URL/domain;
- deploy timestamp and timezone;
- deployment mode;
- uploaded file list/artifact id;
- database backup reference;
- file backup reference;
- rollback owner and rollback procedure reference.

Must pass: evidence is complete enough to identify exactly what was deployed and how to restore the previous state.

### 2. App Shell And Assets

Steps:

- Open production app in a fresh browser session.
- Hard reload once.
- Confirm app shell returns HTTP 200.
- Confirm no blank screen.
- Confirm JS/CSS assets load.
- Check browser console for fatal boot errors.

Must pass: app loads, assets are current, and no fatal frontend boot error blocks login/navigation.

### 3. Authentication And `current_user`

Minimum user/API path:

- Sign in or resume authorized smoke session.
- Call or observe the authenticated current-user/bootstrap request, including `api.php?action=current_user` or the production equivalent.
- Confirm logout/session-expiry behavior does not expose another user's data if a safe check is available.

Must pass:

- current user returns the expected authenticated identity shape;
- no `user:null` state after authenticated login;
- no cross-user leakage;
- no unexpected 5xx on session/bootstrap.

### 4. Mobile Primary Navigation

Viewport: mobile `390 x 844`.

Required entrances:

- On the Go / live capture;
- reports/group money/current period;
- closed archive/final reports;
- closed group report package;
- group messages;
- Business Desk/proforma;
- Travel / Trip with Friends staging;
- Advanced staging.

Must pass: all required entrances are reachable without blocking overlap, unusable buttons, or navigation traps.

### 5. Field Capture No-Data-Loss

Minimum user/browser/API path:

- Open On the Go / live capture for the approved smoke group/account.
- Open cash stream.
- Create one small harmless draft row only if production smoke policy permits mutation; otherwise use an approved existing safe draft fixture.
- Wait for visible saved state.
- Refresh the browser and return to the same stream.
- Confirm the same draft row is still present.
- If proof retry is permitted, attach a harmless proof, force/retry only through ordinary UI behavior, and confirm no duplicate money row.
- Watch that no `on_the_go_card_submit`, `on_the_go_card_include`, or `ledger_group_finalize_report` action happens without explicit user action.

Must pass:

- saved draft survives refresh/return;
- proof retry does not duplicate money rows if exercised;
- cash/card separation remains intact;
- no silent submit/include/finalize occurs;
- no checked API call returns unexpected 5xx.

### 6. Current Period Report And Export

Minimum user/browser/API path:

- Open selected smoke group current/open period.
- Confirm current period is visible as current, not closed history.
- Trigger current-period export/print/download reachability check.
- Observe current report/open-period API and export API through browser or safe authenticated call.

Must pass:

- current period opens without server error;
- current export is reachable;
- current period remains visibly separate from historical final reports;
- carryover may appear as carryover but does not reappear as new income.

### 7. Historical Final Report List, Detail, And Export

Minimum user/browser/API path:

- Open closed/final reports list.
- Select a known safe `report_id`, or create/finalize a dedicated smoke report only if approved.
- Open final report detail by explicit `report_id`.
- Trigger final-report export/print/download reachability check.
- Confirm current activity after finalization does not mutate the selected final report if a new smoke finalization is created.

Must pass:

- final report list opens;
- selected `report_id` opens as historical/final data;
- final report export is reachable and labeled as final/historical report;
- final report remains immutable after later current activity;
- current and historical endpoints are not merged.

### 8. Closed Group Report Package

Minimum user/browser/API path:

- Open one closed group package by explicit `report_id`.
- Observe `ledger_group_final_report_package` or equivalent package endpoint.
- Open print/PDF flow.
- Open at least one safe proof link as authorized reviewer if fixture is available.

Must-pass package sections:

- summary;
- participant reports;
- captures/proofs;
- money rows;
- accountable/advance state;
- group messages or message references;
- audit references;
- print/PDF.

Must pass: package is a full archive object, not summary-only; authorized proof access works; unauthorized access is rejected if safe permission check is available.

### 9. Group Messages

Minimum user/browser/API path:

- Open group messages in the approved smoke group.
- List messages.
- Send one harmless smoke message only if production smoke policy permits mutation.
- Check unread/mark-read behavior if safe.
- Check non-member rejection only if a safe non-member account is available.

Must pass:

- messages are group-scoped;
- list/send/unread/mark-read behavior works for permitted checks;
- non-member or unrelated group context cannot read group messages;
- closed package message references remain understandable.

### 10. Business Desk / Proforma

Minimum user/browser/API path:

- Open Business Desk.
- List existing proformas or create a dedicated smoke proforma only if permitted.
- Open proforma preview.
- Trigger print/save-PDF flow.
- Confirm no operational ledger/report totals are mutated.

Must pass:

- Business Desk is reachable;
- proforma list/detail/preview/print path works;
- proforma flow remains separate from operational money formulas.

### 11. Travel / Advanced Staging

Minimum user/browser/API path:

- Open Travel / Trip with Friends surface.
- Confirm it is visible as staged/non-core unless explicitly selected for launch.
- Open Advanced.
- Return to the ordinary money loop.

Must pass:

- Travel staging remains visible;
- Advanced remains reachable;
- neither surface changes current operational report totals during smoke;
- ordinary money loop still works after visiting staging surfaces.

### 12. Final Health Check

Steps:

- Hard reload once after all checks.
- Re-open current period.
- Re-open selected closed report/package.
- Check for fatal frontend errors or repeated 5xx in the MVP path.

Must pass: no unresolved fatal production error remains in the checked 100% MVP path.

## Minimum Browser Matrix

Must-pass before CEO use:

| Viewport | Required checks |
| --- | --- |
| Mobile `390 x 844` | Full smoke list above, including navigation, Field Capture, current/historical reports, closed package, messages, Business Desk, Travel, and Advanced. |
| Tablet `820 x 1180` | App shell, navigation reachability, current/historical reports, closed package, messages, Business Desk. |
| Desktop `1440 x 900` | App shell, current/historical reports, closed package, Business Desk/proforma, export/print reachability. |

If time allows only one viewport before CEO use, it must be mobile `390 x 844`; tablet/desktop then remain recorded as residual production smoke risk.

## Minimum API Paths

Use authenticated production-safe smoke sessions only. Never record secrets.

| API area | Required path | Must-pass condition |
| --- | --- | --- |
| Session/current user | `current_user` or equivalent bootstrap identity request. | Authenticated identity returns expected shape; no cross-user data; no unexpected 5xx. |
| Field capture no-data-loss | Autosave/recover path used by the browser, proof upload/retry path if permitted. | Saved draft recovers after refresh; retry does not duplicate money; no silent submit/include/finalize. |
| Current report/export | Current group/open-period report endpoint and current export endpoint. | Current period data returns successfully and remains separate from final-report endpoints. |
| Historical final report | Final report list, detail by `report_id`, and final export endpoint. | Selected report returns immutable historical data; export is reachable and non-mutating. |
| Closed group package | `ledger_group_final_report_package` or equivalent by `report_id`. | Package returns summary, participant reports, captures/proofs, money rows, accountable state, messages/audit refs. |
| Proof access | Package proof download/view URL for safe proof. | Authorized reviewer gets expected success; unauthorized access is rejected if safely checked. |
| Messages | Message list/send/mark-read paths for smoke group. | Group-scoped behavior holds; non-member access is rejected if safely checked. |
| Business Desk/proforma | Proforma list/detail/preview/print path. | Business document path works without changing operational ledger/report formulas. |
| Travel/Advanced staging | Browser-backed route/bootstrap calls for staged surfaces. | Routes load and do not mutate ordinary money reports. |

## Stop Criteria

Stop the smoke immediately and mark status `FAIL - rollback review required` if any item occurs:

- production app shell cannot load;
- login/session/current-user is broken for authorized smoke tester;
- current-user/session leaks another user's data;
- app has a fatal frontend boot error blocking the ordinary MVP path;
- Field Capture loses a saved row after visible saved state;
- proof retry duplicates a money row;
- an autosave/retry silently submits, includes, or finalizes a report;
- current period and historical final report are merged;
- selected final report mutates after later current-period activity;
- closed group report package cannot open by explicit `report_id`;
- package proof access is broken for authorized reviewer or exposed to unauthorized user;
- group messages leak across groups or to non-members;
- Business Desk/proforma changes operational ledger/report totals;
- Travel/Advanced staging corrupts or blocks the ordinary money loop;
- repeated production 5xx appears in any checked must-pass MVP path;
- backup/rollback evidence is missing, unusable, or contradicted by the deployment.

Mark status `BLOCKED` instead of `FAIL` if smoke cannot start because URL, credentials, safe fixtures, package evidence, backups, or rollback owner are missing.

## Rollback Triggers

Rollback or hold production release is required when a P0 stop criterion is confirmed and cannot be immediately corrected by a clearly reversible non-code operational action.

Rollback decision must include:

- failure timestamp and timezone;
- affected URL/surface/API;
- user/account role used, without secrets;
- expected result;
- actual result;
- whether customer data integrity, privacy, or CEO usability is affected;
- rollback owner decision: rollback now, hold with mitigation, or accept with Project Director approval.

After rollback, verify:

1. App shell loads.
2. Login/session/current-user works.
3. Current report opens.
4. Known closed report/package opens.
5. No fatal production error remains in the ordinary MVP path.

## Evidence Rules

Record evidence with enough detail to reproduce the decision, but never include secrets.

Allowed:

- timestamps;
- URL paths without private tokens;
- report/group ids approved for smoke;
- screenshots with sensitive customer data masked when needed;
- HTTP status codes and endpoint names;
- error names/messages without credentials;
- uploaded package/artifact id;
- backup references without secret locations or credentials.

Not allowed:

- passwords;
- tokens;
- raw cookies;
- private keys;
- full customer financial dumps;
- production database dumps inside docs;
- personally sensitive data not required for smoke decision.

## Short Report Format After Smoke

Short report to Project Director must be in Russian:

```text
Роль: QA Production Smoke Coordinator
Задача: Production smoke 100% MVP после deploy.
Статус: PASS / FAIL / BLOCKED
Доказательство:
- Production URL: <domain/path>
- Deploy: <timestamp/timezone>, <package/artifact/file-list reference>
- Backup/rollback: <db backup ref>, <files backup ref>, <rollback owner/procedure ref>
- Smoke: current_user <PASS/FAIL>, Field Capture no-data-loss <PASS/FAIL>, current/historical reports <PASS/FAIL>, closed group package <PASS/FAIL>, messages <PASS/FAIL>, Business Desk/proforma <PASS/FAIL>, Travel/Advanced <PASS/FAIL>
Блокер: <none or exact blocker>
Следующий владелец: <Project Director / Deploy Owner / Backend/Data / Frontend/UX / QA>
```

## Current QA Position

Runbook preparation: `PASS`.

Production smoke execution: `BLOCKED` until Deploy Owner provides production upload, production URL, selected package evidence, backup evidence, and rollback evidence.

CEO use of production: blocked until this smoke is executed and all must-pass items are green or explicitly accepted by Project Director with documented risk.
