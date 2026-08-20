# SPRINT-103R — Smith Review Proposals and Duplicate Guard

## Director Sprint Opening

Sprint:
SPRINT-103R — Smith Review Proposals and Duplicate Guard

Goal:
Insert a human review step between quick notes and operational journal writes. Smith prepares proposed rows, flags possible duplicates, and transfers only the rows selected by the user.

Required files read:
- `FinDesk v2.0/sprints/SPRINT-102R-smith-quick-note-conversion.md`
- `supabase/migrations/20260820162000_smith_quick_note_conversion.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/lib/workspace-data.ts`
- `apps/web/src/app/globals.css`

Agents assigned:
- Product UX Agent
- Financial Logic Engine Agent
- Backend/Data Integrity Agent
- QA, Audit, and Acceptance Agent

Agent execution note:
New subagent spawn remains constrained by active thread limits. Director proceeded locally and preserved the agent review structure in this handoff.

Agent tasks:
- Product UX Agent: keep notes human and simple; hide service internals.
- Financial Logic Engine Agent: prevent silent duplicate writes and keep uncertain rows non-counted.
- Backend/Data Integrity Agent: persist Smith proposals and source lineage.
- QA, Audit, and Acceptance Agent: verify Supabase migration, RLS path, duplicate guard, cleanup, TypeScript, and build.

Exit criteria:
- Smith can prepare proposals without writing money.
- User can transfer selected proposals.
- Unselected proposals are rejected.
- Possible duplicate rows are visible and controllable.
- Converted proposals link back to transactions.

## Director Final Handoff

Sprint:
SPRINT-103R — Smith Review Proposals and Duplicate Guard

Status:
Accepted as review-control foundation slice.

Accepted work:
- Added `public.smith_entry_proposals`.
- Added RLS policies for proposal read/write.
- Added updated-at trigger for proposals.
- Added `public.prepare_quick_note_entry_proposals(...)`.
- Added `public.convert_smith_entry_proposals(...)`.
- Added follow-up migration to qualify `quick_note_id` references and remove PL/pgSQL ambiguity.
- Notes action now prepares Smith proposals instead of writing money immediately.
- Added action to transfer selected proposals into the operational journal.
- Notes UI now shows a `Проверка Смита` proposal list.
- Each proposal has a checkbox, human-readable signal, and status.
- Possible duplicates are flagged but left under user control.
- Quick note history loads proposal data and converted row count.

Deferred work:
- True modal presentation.
- Per-line editing before transfer.
- Category/counterparty preview.
- Scanner/receipt duplicate matching.
- Telegram and voice proposal sources.
- Multilingual parsing beyond stored source language.

Files changed:
- `supabase/migrations/20260820163000_smith_review_proposals.sql`
- `supabase/migrations/20260820163100_smith_review_proposals_ambiguity_fix.sql`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/lib/workspace-data.ts`
- `apps/web/src/app/globals.css`
- `FinDesk v2.0/sprints/SPRINT-103R-smith-review-proposals-duplicate-guard.md`

Tests or checks:
- `npm run check:foundation:sql`
- `npm run typecheck:web`
- `npm run build:web`
- `git diff --check`
- Supabase migration apply:
  - `smith_review_proposals`
  - `smith_review_proposals_ambiguity_fix`
- Supabase authenticated smoke:
  - created an existing operational row;
  - created a draft quick note with three lines;
  - prepared three Smith proposals;
  - duplicate guard flagged one possible duplicate;
  - selected only non-duplicate proposals;
  - transferred two selected proposals;
  - one unselected duplicate proposal became rejected;
  - one no-sign row became review transaction without ledger entry;
  - smoke data was deleted afterward.
- Supabase security advisor:
  - no new schema/function warning from SPRINT-103R;
  - remaining warning is Auth leaked-password protection disabled.

Risks:
- Browser click-through should be run after deploy with real UI screenshots.
- Duplicate detection is intentionally conservative: same date + account + amount, with direction when known.
- This is still a review list, not the final polished modal.

Next sprint:
SPRINT-104R — Notes Review UX Polish and Browser Walkthrough.

Paste-to-next-director prompt:
Continue from SPRINT-103R. Smith now prepares review proposals and only selected rows transfer into the operational journal. Next, polish the review UX into a calmer modal/panel, add per-line editing, preserve mobile layout, and run browser screenshots for desktop/mobile/tablet.
