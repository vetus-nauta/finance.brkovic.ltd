# SPRINT-101R — Foundation Financial Command Core

## Director Sprint Opening

Sprint:
SPRINT-101R — Foundation Financial Command Core

Goal:
Move operational entry creation from multi-step application code into one atomic database command, while preserving the future architecture for scanner input, Telegram bot input, voice input, quick notes, imports, and multilingual Smith training.

Required files read:
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-100B-mobile-tablet-operational-ux.md`
- `supabase/migrations/20260820143100_foundation_core.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/lib/workspace-data.ts`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/app/workspaces/[workspaceId]/SyncedLedgerTable.tsx`

Agents assigned:
- Financial Logic Engine Agent as reviewer
- Backend/Data Integrity Agent
- QA, Audit, and Acceptance Agent
- Localization/Linguistic Agent

Agent execution note:
New subagent spawn was blocked by the active thread limit. Director proceeded locally using the accepted SPRINT-100B Financial Logic report and recorded the agent responsibilities in this handoff.

Agent tasks:
- Financial Logic Engine Agent: preserve cash/card separation, no-sign review behavior, and transaction/ledger invariants.
- Backend/Data Integrity Agent: design atomic creation command and row-number concurrency guard.
- QA, Audit, and Acceptance Agent: run SQL, TypeScript, build, and diff checks.
- Localization/Linguistic Agent: prepare language-pack foundation without forcing English service terms into user-visible Russian UX.

Exit criteria:
- Creating an operational entry is one database command.
- Row numbers are allocated inside the database under a transaction-scoped lock.
- Counted entries create both `transactions` and `ledger_entries`.
- Unclear/no-sign entries are saved as visible review transactions and do not affect money.
- Manual card income remains guarded.
- Source channel and language are stored for future scanner, Telegram, voice, quick notes, imports, and Smith learning.
- No old PHP/v2 behavior is copied as source truth.

## Director Final Handoff

Sprint:
SPRINT-101R — Foundation Financial Command Core

Status:
Accepted as backend foundation slice.

Accepted work:
- Added reference table `public.input_channels` for future input ports:
  - `manual`
  - `quick_note`
  - `scanner`
  - `telegram`
  - `voice`
  - `import`
  - `api`
- Added reference table `public.language_packs` for first supported languages:
  - `ru`
  - `en`
  - `it`
  - `hr`
  - `sr`
  - `de`
  - `es`
  - `fr`
  - `zh`
- Added `public.create_operational_entry(...)`.
- The command validates workspace write permission, account, source type, source channel, and language.
- The command creates transaction, optional ledger entry, row number, and audit event atomically.
- The command blocks manual income on card accounts.
- The Next.js action now calls the RPC instead of manually inserting `transactions` and `ledger_entries`.
- The UI now treats `needs_review` transactions as visible “проверить” rows.
- The workspace review counter includes both ledger review rows and transaction-level review rows.

Deferred work:
- Actual scanner/OCR capture.
- Telegram bot integration.
- Voice transcription.
- Full Smith multilingual reasoning and training UI.
- Applying the migration to remote Supabase; this handoff only records source and local verification.

Files changed:
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/app/workspaces/[workspaceId]/SyncedLedgerTable.tsx`
- `apps/web/src/lib/workspace-data.ts`
- `FinDesk v2.0/sprints/SPRINT-101R-foundation-financial-command-core.md`

Tests or checks:
- `npm run check:foundation:sql`
- `npm run typecheck:web`
- `npm run build:web`
- `git diff --check`

Risks:
- The RPC must be applied to Supabase before the deployed app can use it.
- Browser write smoke should be repeated after migration apply, because the source now depends on a new database function.
- Quick notes still stop at “submitted to Smith”; conversion into structured ledger rows remains a future Smith sprint.

Next sprint:
SPRINT-102R — Smith Intake and Entry Conversion Command.

Paste-to-next-director prompt:
Continue from SPRINT-101R. The foundation app now has an atomic operational-entry RPC and reference tables for input channels and languages. Do not build scanner, Telegram, or voice as one-off UI hacks. Build Smith intake next as a command layer that can convert quick notes and future external inputs into proposed operational entries, preserving source channel, source language, source references, review decisions, and user corrections.
