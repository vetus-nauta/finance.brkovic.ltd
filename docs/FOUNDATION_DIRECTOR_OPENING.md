# Foundation Director Opening

Date: 2026-08-20

## Sprint Name

Foundation-01: Clean SaaS Architecture For `brkovic.app`

## Source Of Truth

Primary brief:

- Google Drive document: `ТЗ для Codex — Фундамент архитектуры finance.brkovic.ltd`

Repository truth:

- current files in `vetus-nauta/finance.brkovic.ltd`
- this branch: `foundation-brkovic-app-architecture`

Legacy product truth:

- current PHP FinDesk v2 behavior and data

Target architecture truth:

- documents in `docs/`
- future ordered PostgreSQL migrations
- future typed API contracts

## Director Position

The current PHP application is valuable as product evidence and migration source, but it is not the future architecture.

The future product must be built as a clean multi-user SaaS foundation for:

- web
- PWA
- iOS
- Android
- API/business layer
- private document storage
- AI/OCR adapters

## Assigned Review Roles

Architecture and infrastructure reviewer:

- checks stack fit, hosting direction, domain plan, and temporary-solution risks

Security and data reviewer:

- checks RLS, permissions, data-loss risks, audit, backup, and migration boundaries

Product migration reviewer:

- checks that operational journal, reports, accountable money, quick notes, Mr. Smith, roles, and workspace hall are preserved

QA and acceptance reviewer:

- checks that every foundation step has verifiable files, migrations, tests, and rollback notes before acceptance

## Sprint Objectives

1. Freeze the architectural direction before writing the new app.
2. Document current state and risks.
3. Define PostgreSQL/Supabase data and RLS model.
4. Define storage, API, deployment, backup, cutover, and rollback models.
5. Define account/secret discipline before creating third-party projects.
6. Prevent accidental mixing of old v1/v2 implementation details into the new foundation.

## Explicit Non-Actions

Do not:

- delete legacy PHP code
- deploy `brkovic.app`
- move production traffic
- migrate production data
- introduce MongoDB Atlas as target foundation
- create paid resources without owner confirmation
- commit credentials
- rely on UI checks as security

## Acceptance Gate

Foundation-01 is acceptable only when:

- all required docs exist
- contradictions with the new brief are listed
- legacy/live safety is preserved
- account and secret discipline is documented
- next implementation sprint is clear
- docs are committed and pushed to GitHub

## Next Sprint Candidate

Foundation-02: Supabase Bootstrap, RLS Skeleton, Migration Dry Run Plan

Expected outputs:

- Supabase project checklist
- local Supabase CLI setup plan
- initial ordered migrations
- RLS policy draft migrations
- seed data for one test organization/workspace/user
- migration dry-run script design from legacy PHP/MySQL data
