# Production Hotfix: Participant Control

Date: 2026-05-27

Owner: Project Director / Backend Data Engineer

Status: deployed; director production API smoke passed; QA release recheck still required for role gate.

## Scope

Uploaded only:

- `app/ledger.php`
- `public/assets/app.js`

Not changed:

- database schema;
- production data;
- credentials/config;
- docs/scripts on production;
- other runtime files.

## Backup

Before upload, the two replaced production files were downloaded and archived.

- backup id: `prod-hotfix-before-participant-control-20260527T204210Z`
- archive: `backups/prod-hotfix-before-participant-control-20260527T204210Z.tgz`
- checksum: `39550b6b4b4938d009085af33e2ece1bde1dc64477c1f84aded0299a23770471`

## Uploaded Checksums

Local files were re-downloaded from production after upload and matched these checksums:

- `app/ledger.php`: `1f0e6dbbd71d9c19c8ec219acf0e379cc1d30709c3933afe481db67abb22a12d`
- `public/assets/app.js`: `ba9952ad34ee5fddf03a7dcc554abcb97c01142976e06bea2f93b7d8610bf358`

## Production Smoke

HTTP load:

- `https://finance.brkovic.ltd/api.php?action=current_user`: `200`
- `https://finance.brkovic.ltd/public/assets/app.js`: `200`

Director production API scenario:

- stamp: `20260527204359`
- group id: `9`
- report id: `84`

Scenario:

- admin received `1000`;
- admin issued `135`, `94`, `117`;
- admin spent `20`, `45`, `17`, `4`;
- employee 1 spent `6`, `9`, `43`, `10`;
- employee 2 spent `12`, `23`, `41`, `54`;
- employee 3 spent `0`;
- report finalized.

Passed production checks:

- base totals: income `1000`, expenses `284`, cash/balance `716`;
- `admin_cash_left=568`;
- `employee_positive_remaining_total=184`;
- `employee_reimbursement_due_total=36`;
- `employee_net_remaining_total=148`;
- signed employee overrun row is visible: `-36` with reimbursement due `36`;
- package summary exposes the corrected participant-control totals;
- final export contains `568.00`, `-36.00`, and `36.00`.

## Gate

This hotfix closes the director/backend production smoke for the P0 blocker. It does not replace the QA Release Engineer gate. QA must rerun or inspect the production multi-employee scenario and record a short role report plus full evidence in the QA folder.
