# SPRINT-102R — Smith Quick Note Conversion

## Director Sprint Opening

Sprint:
SPRINT-102R — Smith Quick Note Conversion

Goal:
Turn quick notes from a passive draft area into the first working Smith intake path: a human writes short note lines, Smith converts them into operational journal rows through the atomic financial command, and uncertain rows remain visible for review without affecting money.

Required files read:
- `FinDesk v2.0/sprints/SPRINT-101R-foundation-financial-command-core.md`
- `supabase/migrations/20260820160000_foundation_operational_entry_command.sql`
- `supabase/migrations/20260820161000_operational_entry_security_invoker.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/lib/workspace-data.ts`

Agents assigned:
- Financial Logic Engine Agent as reviewer
- Backend/Data Integrity Agent
- QA, Audit, and Acceptance Agent
- Localization/Linguistic Agent

Agent execution note:
New subagent spawn remains constrained by active thread limits. Director proceeded locally using recorded agent roles and acceptance criteria.

Agent tasks:
- Financial Logic Engine Agent: keep operational journal as source of truth; no-sign note lines must be review rows, not counted money.
- Backend/Data Integrity Agent: convert quick notes atomically and preserve source lineage from note to transactions.
- QA, Audit, and Acceptance Agent: verify Supabase migration, RLS path, TypeScript, build, and smoke cleanup.
- Localization/Linguistic Agent: keep user text simple Russian; do not expose service labels as product language.

Exit criteria:
- A quick note can be converted into one operational row per non-empty line.
- Conversion uses `public.create_operational_entry`.
- Conversion is atomic.
- Converted quick note stores linked transaction IDs.
- Counted and review rows behave exactly like manual entries.
- UI offers one clear action: save draft or check and transfer.

## Director Final Handoff

Sprint:
SPRINT-102R — Smith Quick Note Conversion

Status:
Accepted as Smith intake foundation slice.

Accepted work:
- Added `public.convert_quick_note_to_operational_entries(...)`.
- The function runs as `SECURITY INVOKER`; RLS remains active.
- The function validates authenticated user, note ownership or ledger permission, ledger write permission, date, and account through the underlying entry command.
- The function splits the note by non-empty lines.
- Each line is converted via `public.create_operational_entry(...)` with:
  - `source_type = quick_note`
  - `source_channel = quick_note`
  - `source_id = quick_notes.id`
  - `source_ref.quick_note_id`
  - `source_ref.line_no`
- Converted note becomes `status = converted`.
- Converted note stores `converted_transaction_ids`.
- Added narrow audit policy and conversion audit event.
- Notes UI now has date of transfer.
- Notes UI action is user-facing: `Проверить и перенести`.
- Note history shows converted row count when available.

Deferred work:
- Smith proposal modal with per-line accept/reject controls.
- Category/counterparty assignment during conversion.
- Duplicate detection.
- Receipt/photo attachment matching.
- Telegram and voice intake.
- Multilingual Smith reasoning beyond storing source language.

Files changed:
- `supabase/migrations/20260820162000_smith_quick_note_conversion.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/lib/workspace-data.ts`
- `FinDesk v2.0/sprints/SPRINT-102R-smith-quick-note-conversion.md`

Tests or checks:
- `npm run check:foundation:sql`
- `npm run typecheck:web`
- `npm run build:web`
- `git diff --check`
- Supabase migration apply:
  - `smith_quick_note_conversion`
- Supabase authenticated smoke:
  - created a draft quick note as real authenticated user;
  - converted two note lines;
  - counted line created transaction + ledger entry;
  - no-sign line created `needs_review` transaction without ledger entry;
  - note became `converted`;
  - source link from transactions to quick note was verified;
  - smoke data was deleted afterward.
- Supabase security advisor:
  - no new function warning from SPRINT-102R;
  - remaining warning is Auth leaked-password protection disabled.

Risks:
- Browser/UI click-through should be repeated after deploy.
- Current conversion is direct; the richer Smith review modal is still needed before broad beta use.
- Note date applies to all lines in the note for now.

Next sprint:
SPRINT-103R — Smith Review Modal and Duplicate Guard.

Paste-to-next-director prompt:
Continue from SPRINT-102R. Quick notes now convert to operational rows through an atomic Supabase command. Build the next layer as a Smith review modal: show proposed lines before commit, detect duplicate-looking amounts/texts, allow user to exclude or keep duplicates, and keep source lineage for future scanner, Telegram, voice, and multilingual inputs.
