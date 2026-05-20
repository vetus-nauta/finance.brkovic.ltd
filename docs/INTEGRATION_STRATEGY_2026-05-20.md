# Captain Fin -> finance.brkovic.ltd Strategy

## Decision

Captain Fin should be integrated into `finance.brkovic.ltd` as a dedicated module, preserving the current working UX and storage behavior first.

Do not rewrite it into generic ledger records in the first pass.

## Why

`finance.brkovic.ltd` is a broad financial platform foundation:

- users
- groups
- ledgers
- messages
- business/proforma
- On the Go capture

Captain Fin is a focused operational report product:

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
- Module: ledger, business, on-the-go, captain-fin
- Report: Captain Fin document
- ReportEntry: parsed `+ / -` line or manually entered row
- LedgerEntry: normalized accounting row
- Attachment: file linked to report or ledger entry
- AuditLog: important state changes

## First Integration Pass

1. Add module navigation entry: Captain Fin.
2. Mount current Captain Fin web UI inside finance shell.
3. Reuse finance auth/session.
4. Keep Captain Fin report JSON/storage initially.
5. Add server-side adapter for finance user/group ownership.
6. Add migration/mapping from Captain Fin report entries to ledger only after the UI is stable.

## Desktop Rule

The local PC app remains desktop/two-column:

- left: report list
- right: report editor and details

Do not convert it to the mobile split-screen PWA layout.

## Main Risk

The biggest risk is product mixing. KeepCash, CalmHelp, AdvCash, Quick Ledger and Captain Fin must not be merged blindly. Finance should become the platform; Captain Fin should be one clear module inside it.

