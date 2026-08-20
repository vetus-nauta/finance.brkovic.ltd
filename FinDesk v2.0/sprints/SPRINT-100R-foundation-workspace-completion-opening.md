# SPRINT-100R — Foundation Workspace Completion Opening

## Director Sprint Opening

Sprint:
SPRINT-100R — Foundation Workspace Completion

Goal:
Turn the current Supabase/Next.js foundation workspace from a visual skeleton into a trustworthy MVP work surface:
operational ledger, quick notes, reports, and Hall navigation must have real user-facing behavior or clear disabled states.

Required files read:
- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/39-hall-roles-and-accountable-workflow-contract.md`
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/lib/workspace-data.ts`
- `supabase/migrations/20260820143100_foundation_core.sql`

Agents assigned:
- UX/Layout Revision Agent
- Functional QA Agent
- Technical Debt and Security Agent
- Quick Notes Implementation Agent

Agent tasks:
- UX/Layout Revision Agent: audit responsive workspace geometry, active split table, side actions, entry bar, and scroll ownership.
- Functional QA Agent: audit visible controls, navigation, entry creation path, auth/dev login, and dead buttons.
- Technical Debt and Security Agent: audit env handling, old v2/PHP boundaries, Supabase RLS assumptions, and deploy risks.
- Quick Notes Implementation Agent: propose or implement the first bounded Quick Notes slice if it can be done without touching legacy v2.

Expected reports:
- Prioritized findings.
- Sprint split proposal.
- Files changed or recommended write scopes.
- Verification commands.

Exit criteria:
- No visible inert controls in the core workspace unless they are explicitly disabled with plain language.
- Cash/Card operational entry loop remains working.
- Quick Notes has a real route/view or is explicitly staged with a visible product reason.
- Desktop and mobile table scroll remains inside the workspace, not the page body.
- Typecheck, production build, and visual smoke screenshots pass.

Risks:
- Old PHP/v2 and foundation app coexist in the repository; do not use old v2 as product truth.
- Supabase schema contains future-ready entities that may not yet have complete server actions.
- Employee/accountable workflow requires scoped visibility and must not be enabled prematurely.

## Sprint Map

SPRINT-100A — Workspace Navigation and Quick Notes Entry
- Make side navigation deterministic: Cash, Card, Notes, Reports.
- Add real Notes view backed by `quick_notes` draft/history.
- Keep operational entry input focused on ledger mode only.

SPRINT-100B — Reports Viewer and Report Storage UX
- Replace placeholder Reports button with report snapshot/package list.
- Open report as a viewer, not as an operational ledger clone.
- Keep generated reports separate from source ledger truth.

SPRINT-100C — Operational Entry Edit/Delete and Review Loop
- Restore create/edit/delete clarity.
- Keep selected row stable.
- Add plain-language review states.

SPRINT-100D — Hall Role Router MVP
- Remove remaining placeholder wording.
- Show workspaces, role, employee mode availability, and disabled future actions honestly.

SPRINT-100E — Responsive Acceptance Pass
- Desktop, iPad 11, iPad mini, phone portrait/landscape screenshots.
- No body horizontal overflow.
- Input remains reachable.

## Current Director Decision

Start with SPRINT-100A because the user-facing workspace currently shows inert side controls.
This is the shortest path from skeleton to usable product behavior without changing financial formulas.

## Director Final Handoff

Sprint:
SPRINT-100A — Workspace Navigation and Quick Notes Entry

Status:
Accepted as first foundation workspace completion slice.

Agents assigned:
- UX/Layout Revision Agent
- Functional QA Agent
- Technical Debt and Security Agent
- Quick Notes Implementation Agent

Agent reports received:
- UX/Layout report received: mobile/tablet ledger needs a separate UX sprint; split table remains fragile on small screens.
- Functional QA report received: Notes/Reports were inert, entry creation is not atomic, Hall has disabled future actions.
- Technical/Security report received: foundation builds, but dev-login must stay local-only and PHP v2 must not be removed without production boundary decision.
- Quick Notes Implementation report received: minimal quick notes draft/history slice implemented and integrated.

Accepted work:
- Workspace side navigation now has real modes: ledger, notes, reports.
- Notes mode reads and writes `quick_notes`.
- Draft notes can be saved, reopened, submitted to Smith, and removed from visible history.
- Reports mode reads `report_snapshots` and shows a clear empty state when no reports exist.
- Operational entry bar is shown only in ledger mode.

Rejected or deferred work:
- Atomic transaction + ledger entry creation deferred to backend/RPC sprint.
- Full report generation/viewer deferred to reports sprint.
- Mobile/tablet dedicated operational layout deferred to UX responsive sprint.
- Hall employee/accountable flow deferred until scoped visibility is implemented.

Files changed:
- `apps/web/src/app/workspaces/[workspaceId]/page.tsx`
- `apps/web/src/app/workspaces/[workspaceId]/actions.ts`
- `apps/web/src/lib/workspace-data.ts`
- `apps/web/src/app/globals.css`
- `FinDesk v2.0/sprints/SPRINT-100R-foundation-workspace-completion-opening.md`

Tests or checks:
- `npm run typecheck:web`
- `npm run build:web`
- `git diff --check`
- Browser smoke: login, hall, workspace, notes save/delete, reports open, ledger return.
- Mobile smoke: notes mode width remains inside 390px viewport.

Risks:
- `createOperationalEntry` is still non-atomic and must move to a transaction/RPC command.
- Reports mode is only a viewer/empty state; report creation is not implemented in foundation.
- Notes submit marks status `submitted_to_smith`; Smith conversion workflow is not implemented yet.
- Foundation is not yet a full production replacement for the PHP v2 runtime.

Next sprint:
SPRINT-100B — Mobile/Tablet Operational Workspace UX.

Paste-to-next-director prompt:
Continue from SPRINT-100A. Source of truth is GitHub files only. Read this handoff, then implement a dedicated mobile/tablet operational layout: phone should show operational feed first, structured check as a separate controlled view, input reachable, no body horizontal overflow, and desktop split table must not regress.
