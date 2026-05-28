# Closed Group Report Package Gate

Date: 2026-05-27
Role: Chief Auditor
Decision: approved for `Закрытый групповой отчет` package only.

## Scope

This gate covers the closed group report package as one immutable archive object by `report_id`.

Approved scope:

- package opens by `report_id`;
- user-facing label is `Закрытый групповой отчет`;
- package is not summary-only;
- package includes group summary, participant reports, captures/proofs, money rows, accountable/advance state, message references, and audit references;
- authorized proof downloads use package proof URLs;
- cash/card split is preserved;
- accountable/open remaining employee cash is responsibility/carryover, not expense;
- print/PDF includes package sections;
- later current-period activity does not mutate the closed package;
- mobile/tablet/desktop package layout is reachable and non-overlapping.

This gate does not approve full business MVP automatically.

## Evidence Reviewed

Product:

- Product Finance Architect defined the user-facing object as `Закрытый групповой отчет`.
- Product contract requires one immutable closed group report package by `report_id`.
- Summary-only export is acceptable as an additional quick table, but not as the archive package itself.

Backend/Data:

- Implemented `ledger_group_final_report_package`.
- Implemented `ledger_group_final_report_proof_download`.
- New finalizations store `report_package` in `audit_log.details`.
- Package reads use stored immutable package data, not mutable current state reconstruction.
- Proofs are copied into report-owned storage and downloaded by package authorization, not by original file ownership.
- Current export and historical short table export behavior are preserved.

Frontend/UX:

- Closed report list labels rows as `Закрытый групповой отчет #report_id`.
- Opening a closed report is package-first through `ledger_group_final_report_package`.
- Package view exposes summary, participant reports, captures/proofs, money rows/proofs, accountable state, messages, and audit refs.
- Proof links use package proof metadata/download URLs.
- Browser print/PDF is wired to the package view.
- Excel/Google actions are labeled as short tables.

QA:

- Run: `20260527816949`.
- Fixture: `group_id=222`, `report_id=454`.
- Admin user: `520`.
- Member user: `521`.
- Cash Live Report tape/capture: `272/184`.
- Card Live Report tape/capture: `274/185`.
- Accountable advance: `67`.
- Rollover advance: `68`.
- Later current-period activity: income `106`, Live Report tape `277`.

QA verified:

- package endpoint returned `package_type=group_final_report`;
- counts included participants `2`, captures `2`, money rows `4`, proofs `3`, accountable items `2`, report-context audit messages `5`, general group refs `1`, and audit refs `8`;
- package summary stayed `received_money=1000`, `physical_cash_spent=640`, `card_noncash_spent=70`, `admin_cash_left=300`, `accountable_money_left=60`, `cash_balance=360`, `balance=290`;
- cash capture had `cash_effect=-600`, `card_effect=0`;
- card capture had `cash_effect=0`, `card_effect=-70`;
- accepted accountable spend had `accountable_effect=-40`;
- open accountable item showed `open_remaining_cash=60` and `carryover_responsibility=60`, not expense;
- package proof downloads returned HTTP `200`;
- Excel/Google remained short final-report tables;
- package digest did not mutate after later current-period activity;
- UI passed on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`;
- print/PDF content contained package sections.

## Audit Finding

The verified `Закрытый групповой отчет` satisfies the gate requirement for one closed archive object. It preserves group received money, participant responsibility, physical cash vs card/noncash split, accountable/advance state, authorized proof access, message/audit references, and immutability after later current activity.

The package is not summary-only. The user can open the closed report by `report_id` and see the report package without manually stitching ledger, Live Report, proof, advance, message, and audit screens.

## Accepted Follow-Ups

These are not P0 blockers for this gate:

- package-wide downloadable file export beyond browser print/PDF;
- first-class report-linked message schema for `report_id`, `tape_id`, `capture_id`, or `advance_id`;
- migration of legacy reports without `report_package`.

The accepted package already exposes browser print/PDF, short final-report tables, audit-derived report-context messages, clearly marked unlinked group refs, and warning/fallback behavior for legacy missing packages.

## Remaining Boundary

This gate approves only the closed group report package. It does not automatically approve full business MVP.

Field Combat no-data-loss remains approved and is not reopened by this evidence.

## Assigned Follow-Up

- Project Director: decide whether this package gate closes the group-report/archive business-MVP block or whether a separate final business-MVP gate is required.
- Backend Data Engineer + Frontend UX Engineer: implement package-wide downloadable file export only if Product/Director upgrades it from follow-up to P0.
- Product Finance Architect + Backend Data Engineer: decide whether first-class report-linked message schema is required after MVP.
- QA Release Engineer: keep package immutability, proof download authorization, cash/card/accountable split, and mobile layout under regression watch.
