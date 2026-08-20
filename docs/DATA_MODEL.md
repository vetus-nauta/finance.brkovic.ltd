# Data Model

Date: 2026-08-20

## Principles

- PostgreSQL is the source of truth.
- Tenant-owned tables carry `organization_id` and/or `workspace_id`.
- Financial facts are stored as primary operations, not only reports.
- Reports are computed from source rows or stored as snapshots with source links.
- Use JSONB for flexible metadata where it does not hide relational truth.
- Use `numeric`, never float, for money.
- Use soft delete, reversal, or status transitions for critical financial records.

## Core Tables

Identity and tenancy:

- `profiles`
- `organizations`
- `workspaces`
- `memberships`
- `roles`
- `permissions`
- `invitations`

Memberships must include explicit state and revocation fields so RLS can deny removed users without relying on UI state.

Finance:

- `accounts`
- `transactions`
- `ledger_entries`
- `categories`
- `category_rules`
- `counterparties`
- `period_closures`
- `corrections`
- `report_definitions`
- `report_snapshots`

Accountable money:

- `cash_advances`
- `expense_reports`
- `expense_items`
- `approval_events`
- `expense_report_ledger_links`
- `settlements`

Accountable money records must keep source links between issued money, reported expense items, approved ledger entries, returns, reimbursements, and settlements.

Documents:

- `documents`
- `document_versions`
- `document_links`
- `document_extractions`

Collaboration:

- `comments`
- `messages`
- `notifications`

AI/OCR:

- `ai_jobs`
- `ocr_jobs`
- `ai_provider_events`
- `user_corrections`

Commercial readiness:

- `subscriptions`
- `entitlements`
- `usage_events`

Audit:

- `audit_log`

## Legacy Mapping Anchor

Current v2 MySQL tables are mostly prefixed with `v2_`. The new model should map them explicitly in `docs/MIGRATION_MAP.md` before any data movement.
