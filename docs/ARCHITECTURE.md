# Architecture

Date: 2026-08-20

## Goal

Build FinDesk as a commercial multi-user SaaS platform where web, iOS, Android, and PWA clients use the same backend, auth, data model, business commands, storage model, and reporting contracts.

The current PHP FinDesk remains legacy until a verified migration and cutover are approved separately.

## Target Stack

- Database: PostgreSQL
- Managed start: Supabase
- Auth: Supabase Auth
- Storage: Supabase Storage private buckets
- Web: Next.js + TypeScript + React
- Mobile: React Native + Expo + TypeScript
- API/business layer: own server-side command layer
- Shared contracts: TypeScript packages and schema validation
- Validation: Zod or equivalent
- Source control: GitHub
- Web deployment: Vercel first
- Mobile delivery: Expo/EAS first
- AI/OCR: provider adapters, not hardcoded providers

## Non-Goals

- No MongoDB Atlas as foundation.
- No separate mobile backend.
- No direct client-only mutation of critical financial data.
- No public financial document buckets.
- No production cutover before migration acceptance.
- No deletion of legacy PHP code before explicit approval.

## High-Level System

```text
apps/web       ┐
apps/mobile    ├── api-client ──> API/business commands ──> PostgreSQL
PWA            ┘                         │                    │
                                          │                    ├── RLS
                                          │                    ├── views/reports
                                          │                    └── audit_log
                                          │
                                          ├── Supabase Auth
                                          ├── Storage service wrapper
                                          ├── OCR provider adapter
                                          ├── AI provider adapter
                                          └── notification adapter
```

## Monorepo Target

```text
/apps
  /web
  /mobile
/packages
  /ui
  /types
  /validation
  /domain
  /api-client
  /config
/supabase
  /migrations
  /functions
  /seed
/docs
  /DECISIONS
/tests
/scripts
```

The current PHP tree stays available during migration, but new foundation work should not deepen the PHP/MySQL architecture.

## Tenancy Model

The system is multi-tenant from day one.

Core rule: a user can belong to multiple organizations/workspaces with different roles per workspace.

Tenant-owned entities must have `organization_id`, `workspace_id`, or both. Exceptions require an ADR.

## Authorization Model

Authorization is layered:

1. PostgreSQL RLS
2. Server-side command authorization
3. UI visibility

UI visibility is convenience only. It is not a security boundary.

## Business Command Boundary

Simple bounded reads may use Supabase client with RLS.

Critical commands go through server-side functions/API:

- create organization/workspace
- invite/accept/revoke membership
- change roles
- issue accountable money
- submit expense report
- approve/return report
- materialize accepted expenses
- close/reopen period
- create correction
- upload/process document
- AI/OCR extraction
- destructive actions
- billing/entitlement changes

## Money Rules

- Use `numeric(14,2)` or stricter numeric types.
- Never use float for money.
- Store primary operations as truth.
- Reports are computed views/snapshots over operations.
- Never delete financial history without audit-safe reversal/soft-delete semantics.

## Domain Workflows To Preserve

- Operational journal
- Structured check
- Quick notes -> Mr. Smith -> confirmed ledger rows
- Monthly archive/close/reopen/correction
- Report fragments/packages/html snapshots
- Accountable money / On the Go
- Roles and workspace hall
- Dictionary training and localization

## Deployment Direction

- `finance.brkovic.ltd`: current PHP production until cutover.
- `brkovic.app`: clean future SaaS domain.
- Vercel should host the Next.js web client.
- Supabase should host PostgreSQL/Auth/Storage and server-side functions where appropriate.
- Namecheap/shared hosting may host a temporary placeholder or redirect only.
