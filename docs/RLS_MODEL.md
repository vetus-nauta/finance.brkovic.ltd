# RLS Model

Date: 2026-08-20

## Goal

PostgreSQL Row Level Security must prove tenant isolation even if a client guesses IDs or bypasses UI navigation.

## Required Policies

For tenant tables:

- users can read only organizations/workspaces where they have active membership
- users can mutate only data allowed by membership permissions
- base/member users cannot read manager/admin-only data
- employee accountable mode can read/write own expense reports only
- owner/admin can invite, revoke, issue advances, approve, and manage workspace settings
- removed membership loses access immediately

## Membership Lifecycle

Target memberships must include explicit lifecycle fields:

- `status`
- `invited_at`
- `accepted_at`
- `revoked_at`
- `left_at`

RLS must treat only active accepted memberships as valid access.

A removed or revoked member must immediately lose:

- table read access
- mutation access
- signed document URL creation
- report/package access
- invite and member-management access

## Role Matrix To Freeze Before Migrations

The legacy role names are useful, but target permissions must be explicit.

Minimum roles to map:

- owner
- admin
- finance
- assistant
- viewer
- employee

Required decisions before production migrations:

- who can close/reopen periods
- who can create corrections
- who can create/send/return reports
- who can approve accountable money reports
- who can issue accountable money
- who can import data
- who can change category rules/dictionaries
- who can invite/revoke members
- what an employee can see outside their own submitted report rows

## Required Tests

- user A cannot read organization B
- user A cannot read workspace B by guessed ID
- base/member cannot perform manager/admin commands
- manager cannot perform owner-only operations
- employee cannot read full workspace ledger unless granted
- invite token is bound to expected workspace/email/token flow
- removed member gets denied by direct database client
- storage metadata cannot expose files across tenant boundary
- direct Supabase client cannot bypass server-side command rules for critical finance mutations
- guessed report/document IDs do not leak rows or metadata
- revoked invitation token cannot create membership
- role downgrade takes effect without browser refresh assumptions

## Implementation Notes

Use helper SQL functions for membership checks, for example:

- `auth.uid()`
- `is_workspace_member(workspace_id)`
- `has_workspace_permission(workspace_id, permission_code)`
- `workspace_role(workspace_id)`

The exact helper shape belongs in migrations and must be tested.
