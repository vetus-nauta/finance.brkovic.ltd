# Universal Cash Session Engine - 2026-06-07

Status: local Atlas slice started.
Scope: FinDesk Product Shell, Universal Cash Session, Yacht/Home/Base future behavior.
Source references: Product Bible V1, Ship Cashbox behavior review, current FinDesk workspace shell.

## Decision

FinDesk must not build a separate cash/report engine for Yacht, Home, Family, Road and Base.

The common behavior is:

```text
workspace -> active session -> participants -> live journal -> fixed record batches -> report preview -> final report/archive
```

Yacht is the first serious preset. Home, Family, Road and the base tool must reuse the same engine with different terminology, categories and optional tools.

## What We Take From Ship Cashbox

- Personal/group mode as a first-class session property.
- Treasurer/participant role split as a generic owner/manager/participant role model.
- Entry kinds: contribution, expense, note, adjustment.
- Live journal as the main working page.
- Fixed record batches instead of only a mutable textarea.
- Separate participant-visible and manager-visible payloads in future iterations.
- Settlement preview lines as a derived report result.
- Archive/export direction, but not the old implementation.
- Invite-code discipline for later group participant entry.
- Attachment/OCR as later evidence layer, not core accounting input.

## What We Do Not Take

- PHP flat-file session storage.
- Ship Cashbox standalone PWA shell.
- Direct code transplant.
- Old public route structure and multilingual static wrappers.
- Final financial formula changes without architect/auditor approval.
- OCR/receipt implementation inside the first engine slice.

## Current Local Slice

Backend Node Atlas server now exposes:

- `cash_session_get_or_create`
- `cash_session_save_draft`
- `cash_session_submit_draft`

New Atlas collection:

- `cash_sessions`

The first implementation stores the active session as an additive document with:

- `workspace_id`
- `preset`
- `mode`
- `participants`
- `notebooks.owner.draft_text`
- `batches[]`
- derived `totals`
- derived `settlement_preview`

This is a preview layer. It does not replace existing official FinDesk report assembly or existing yacht fuel/products calculations.

## Product Routes

Universal routes:

```text
cash-session  -> engine home / current active session
cash-journal  -> ЖЗ, live journal page
cash-records  -> fixed record batches
cash-report   -> report preview / settlement preview
```

Workspace-specific entry points:

```text
workspace-home -> cash-session / journal / records / reports
Yacht home     -> cash-session / cash-records / cash-report / yacht tools
Home home      -> cash-session / cash-journal / cash-records / home tools
```

Future presets should not add independent report engines. They add vocabulary and optional tools around the same routes.

## Strong Current Assets

- Workspace shell is already a durable container.
- Trash/restore behavior is already aligned with session direction.
- Yacht/Home are already separated as independent pages.
- Atlas server is active and suitable for the new engine layer.
- Yacht fuel/products can remain specialized tools under Yacht while records/report behavior becomes common.

## Weak Spots To Remove Or Downgrade

- Old Cash/Card split should stop being the main mental model for all contexts.
- Legacy `on_the_go` should become support plumbing, not the primary product route.
- `Касса`, `Финальный расчёт`, and old reports should not compete with the new common ЖЗ/Записи/Отчет structure.
- Any route that makes Yacht/Home look like separate products with separate financial logic should be reduced to preset/tool behavior.
- Technical labels and preview/audit details must not leak into final user documents.

## Next Implementation Steps

1. Add participant management to `cash_sessions`.
2. Add per-participant visible payload and role discipline.
3. Add session close/archive with retention and immutable snapshot.
4. Add professional report/PDF after report structure is approved.
5. Add preset dictionaries:
   - Yacht: captain, crew, guest, agent; fuel/products/service categories.
   - Home: household, helper, supplier; shopping/service/budget categories.
   - Family: family members, children, shared budget categories.
   - Road: driver/passenger; fuel/toll/parking/hotel/food categories.
6. Audit settlement math before making it official.

## Local Slice 2 - Participants And Roles

Implemented locally on 2026-06-07:

- active `cash_sessions` now supports session participants;
- participant roles are normalized as `owner`, `treasurer`, `manager`, `participant`, `viewer`;
- participant can be included or excluded from split preview;
- ЖЗ can be written for the selected participant;
- fixed record batches store `participant_id` and `participant_display_name`;
- records page shows which participant owns each fixed batch;
- report preview totals participants separately;
- owner cannot be removed from the active session.

New API actions:

- `cash_participant_upsert`
- `cash_participant_remove`

Current limitation:

- participant self-view and participant auth are not implemented yet;
- settlement remains `preview_not_final` and is not an audited final report;
- this slice does not replace existing FinDesk report assembly.

## Local Slice 3 - Participant Self-View Discipline

Implemented locally on 2026-06-07:

- each cash participant receives an `invite_token`;
- participant token opens a restricted participant payload;
- participant payload includes only:
  - basic session title/status/currency;
  - current participant profile;
  - current participant draft;
  - current participant fixed batches;
  - current participant totals;
  - current participant relevant settlement preview lines;
- participant payload does not expose full `participants` or all session `batches`;
- participant can save and submit only their own ЖЗ by token;
- Product Shell route `cash-participant` renders the restricted self-view;
- URL parameter `cashToken` routes to participant self-view after local auth shell initializes.

New API actions:

- `cash_participant_view`
- `cash_participant_save_draft`
- `cash_participant_submit_draft`

Current limitation:

- token delivery/invitation UI is not finished;
- token is the first access boundary, not a full identity/auth provider;
- final report and settlement remain preview-only until audit.

## Local Slice 4 - Participant Invite Delivery UX

Implemented locally on 2026-06-07:

- participant add form now accepts optional email;
- participant cards show saved email when present;
- participant cards show the self-view URL in a muted single-line form;
- manager can open participant self-view from the participant card;
- manager can copy a prepared invitation text containing:
  - participant name;
  - self-view link;
  - clear note that the page shows only that participant's records and preview;
- generated local participant URL uses `app.php?cashToken=<token>&build=routes36`.

Current limitation:

- no SMTP/email sending in this slice;
- copied invitation is a delivery UX, not a verified email delivery pipeline;
- token delivery security must be reviewed before public production use.

## Local Slice 5 - Close/Archive Snapshot

Implemented locally on 2026-06-07:

- cash session can be closed into immutable `archive_snapshot`;
- close changes session `status` from `active` to `closed`;
- archive snapshot stores participants, batches, totals, settlement preview, `closed_at`, and `closed_by_user_id`;
- archive list returns compact snapshot summaries for the current workspace;
- Product Shell shows archive cards on `cash-session`;
- `cash-session` and `cash-report` expose the close action.

New API actions:

- `cash_session_close`
- `cash_session_archive_list`
- `cash_session_archive_get`

Current limitation:

- archive is marked `preview_not_final` and is not an audited final report;
- PDF/export is not implemented in this slice;
- reopen/restore is not implemented in this slice.
