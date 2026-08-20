# RLS Test Plan

Date: 2026-08-20

## Purpose

Prove that tenant isolation is real at the database boundary, not only in UI or API code.

## Required Personas

- owner A
- finance A
- viewer A
- employee A
- revoked member A
- owner B

## Required Fixtures

- organization A
- workspace A
- organization B
- workspace B
- ledger rows in A and B
- report snapshots in A and B
- document metadata in A and B
- employee expense report in A
- revoked membership in A

## Direct Client Tests

1. User A cannot read organization/workspace B rows by guessed ID.
2. User A cannot read ledger rows from workspace B.
3. Viewer A cannot insert/update/delete ledger rows.
4. Employee A cannot read full workspace ledger unless explicitly granted.
5. Employee A can read/write own draft expense report rows.
6. Employee A cannot approve own report through direct table mutation.
7. Finance A can read/manage reports only inside workspace A.
8. Revoked member A loses read/write immediately.
9. Guessed report/document IDs do not reveal metadata.
10. Invite token with revoked/expired status cannot create active membership.
11. Role downgrade removes write permissions without browser refresh assumptions.
12. Storage metadata access follows workspace membership and document permissions.

## Server Command Tests

Critical commands must be tested through the server-side API/business layer:

- create organization/workspace
- invite/revoke member
- issue accountable money
- submit accountable report
- approve/return accountable report
- materialize accepted report rows
- create report snapshot
- close/reopen period
- create correction
- upload/link document
- AI/OCR extraction confirmation

## Audit Tests

Audit rows must be created for:

- role change
- invite/revoke
- issue accountable money
- approve/return report
- close/reopen period
- correction
- destructive/void action
- document upload/delete
