# FinDesk -> finance.brkovic.ltd Strategy

## Decision

FinDesk is the serious product name for the manager/report layer inside `finance.brkovic.ltd`. The old local report workflow should be integrated through this layer, preserving the current working UX and storage behavior first.

Do not rewrite it into generic ledger records in the first pass.

## Why

`finance.brkovic.ltd` is a broad financial platform foundation:

- users
- groups
- ledgers
- messages
- business/proforma
- On the Go capture

The previous local report product is a focused operational report workflow:

- one current report
- fast signed note input
- submitted reports
- attachments
- Excel/print output
- desktop sync

The overlap is useful, but the workflows are not identical.

## Recommended Data Model Direction

- User: real login identity
- Group / Workspace: shared finance area
- Role: owner/admin/editor/viewer/captain/accountant/client
- Module: ledger, business, on-the-go, findesk, advanced
- Report: FinDesk document
- ReportEntry: parsed `+ / -` line or manually entered row
- LedgerEntry: normalized accounting row
- Attachment: file linked to report or ledger entry
- AuditLog: important state changes

## First Integration Pass

1. Use the existing FinDesk module navigation entry as the middle layer.
2. Mount the current report workflow inside the FinDesk shell.
3. Reuse finance auth/session.
4. Keep report JSON/storage initially.
5. Add server-side adapter for finance user/group ownership.
6. Add migration/mapping from FinDesk report entries to ledger only after the UI is stable.

## Desktop Rule

The local PC app remains desktop/two-column:

- left: report list
- right: report editor and details

Do not convert it to the mobile split-screen PWA layout.

## Main Risk

The biggest risk is product mixing. KeepCash, CalmHelp, AdvCash, earlier Quick Ledger materials and the old Captain Fin experiment must not be merged blindly. Finance is the platform; FinDesk is the clear manager/report layer inside it.
