# FinDesk Phase 2 Logic / Navigation / Engine Audit

Date: 2026-06-02
Status: Sprint 0, Sprint 1, Sprint 2 gate prepared. Implementation is not started.

## Source

CEO Phase 2 packages:

- Logic package: `https://drive.google.com/file/d/1HGyjkl0Dv6aU8OjdjN5fw2mWrK9KxjFu/view?usp=sharing`
- Navigation/localization package: `https://drive.google.com/file/d/1s1dRKCxUWUwqdqoUsiFU-NX-D_JgHyTS/view?usp=sharing`

Local unpacked audit copy:

- `/tmp/findesk_phase2/logic`
- `/tmp/findesk_phase2/navloc`

## Phase 2 Rule

Phase 2 is business-logic and workflow construction. It is not styling, not animation, not dashboard expansion, and not ERP/accounting growth.

Do not implement until Sprint 0, Sprint 1, and Sprint 2 are reflected in the working blueprint and QA checklist.

Preserve:

- current authentication and email code flow;
- users/sessions;
- group foundation;
- PWA manifest and service worker foundation;
- existing attachment/storage foundation;
- existing database foundation unless an audited additive schema patch is approved.

## Product Target

FinDesk is a simple money movement journal.

Core meaning:

- user chooses solo or team workspace;
- user chooses Cash or Card before Live Journal;
- Live Journal is the primary working surface;
- one fixed journal becomes a reviewable report object;
- team money issued by admin is pending until employee confirmation;
- reports keep Cash and Card separated until final composition;
- dangerous changes require protected action flow.

## Sprint 0 - Pre-Implementation Audit

Inspected:

- `public/api.php`
- `app/auth.php`
- `app/db.php`
- `app/groups.php`
- `app/advances.php`
- `app/on_the_go.php`
- `app/ledger.php`
- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/assets/i18n.js`
- `public/manifest.webmanifest`
- `public/service-worker.js`
- `storage/reset-backups/20260522-182307/finance_brkovic_local.sql`

Result:

- auth, group, journal, attachment and report package foundations exist;
- old interface shell still leaks into the user path;
- Phase 2 must stop using old menu routes as normal product navigation;
- implementation must be additive and controlled.

## Sprint 1 - Engine Audit

### Supported As-Is

Auth/session:

- `users`, `sessions`, `auth_codes`;
- APIs: `request_code`, `verify_code`, `current_user`, `logout`;
- audit writes on login/session actions.

Groups/workspaces:

- `groups`, `group_members`, `group_invites`;
- roles/access: `admin/member`, `base/manager/advanced`;
- APIs: `group_create`, `group_list`, `group_join`, `group_members`, `group_member_access_update`.

Live Journal:

- `on_the_go_tapes`, `on_the_go_sessions`, `on_the_go_captures`;
- `stream_type` supports `cash/card`;
- `on_the_go_signed_sync` supports the approved MVP input model: `+/- amount and note`;
- card/tape submit/include/return/delete foundation exists.

Cash/Card streams:

- `on_the_go_tapes.stream_type enum('cash','card')`;
- `on_the_go_sessions.session_type enum('cash','card')`;
- captures separate `cash_in`, `cash_out`, `noncash_out`.

Attachments:

- `on_the_go_files`;
- upload/list/download/delete foundation;
- field draft/upload state tables can be created by existing runtime schema guard;
- proof metadata columns exist through schema ensure.

Reports:

- `ledger_group_finalize_report`;
- `ledger_group_final_report_list`;
- `ledger_group_final_report_detail`;
- `ledger_group_final_report_package`;
- package export/download logic exists.

Audit/history:

- `audit_log`;
- `ql_audit_write()`;
- append-only on-the-go journal in `storage/live-report-logs/append-only`.

### Not First-Class Enough For Phase 2

Transfer offer / acceptance:

- currently encoded through `cash_advances.status='issued'` plus a marker in `moderation_note`;
- pending state is functional but not first-class;
- no dedicated transfer event table;
- Card transfer/assignment is not first-class.

Active workspace:

- group isolation exists through `group_id`;
- selected active workspace currently lives mainly in frontend/localStorage;
- no server-side workspace preference for reliable continuation across devices.

Report Assembly:

- final report is currently an `audit_log` snapshot package;
- no first-class `findesk_reports` / `findesk_report_items` lifecycle;
- no clean draft -> assembled -> finalized -> archived product object.

Protected Actions:

- audit log exists;
- no dedicated protected-action state with consequence preview, reason, typed phrase and previous/new state payload.

Navigation shell:

- old modules still exist in `app.php`;
- old paths are still reachable from visible menu;
- current Phase 1 shell does not yet provide the Phase 2 top shell contract: title, back stack, compact menu, account state, logout and language.

Localization:

- shared language layer exists in `public/assets/i18n.js`;
- Phase 2 must add FinDesk keys under `findesk.*` and not create a separate language system.

## Minimum Additive Schema Patch

Recommended additive tables:

1. `findesk_transfers`
   - `id`
   - `group_id`
   - `issued_by_user_id`
   - `assigned_to_user_id`
   - `stream_type enum('cash','card')`
   - `amount`
   - `currency`
   - `description`
   - `state enum('pending','active','cancelled')`
   - `on_the_go_tape_id`
   - `confirmed_by_user_id`
   - `confirmed_at`
   - `cancelled_by_user_id`
   - `cancelled_at`
   - `created_at`
   - `updated_at`

2. `findesk_transfer_events`
   - issue, edit, confirm and cancel events;
   - actor;
   - previous/new values;
   - timestamp.

3. `findesk_workspace_preferences`
   - `user_id`
   - `mode enum('solo','group')`
   - `group_id`
   - `updated_at`

4. `findesk_reports`
   - `group_id`
   - `created_by_user_id`
   - `status enum('draft','finalized','archived')`
   - `period_from`
   - `period_to`
   - `cash_summary_json`
   - `card_summary_json`
   - `total_summary_json`
   - `snapshot_json`
   - `created_at`
   - `finalized_at`

5. `findesk_report_items`
   - `report_id`
   - `tape_id`
   - `owner_user_id`
   - `stream_type`
   - `state enum('ready','attached','detached')`
   - `snapshot_json`
   - `attached_at`
   - `detached_at`

6. `findesk_protected_actions`
   - `action_type`
   - `entity_type`
   - `entity_id`
   - `preview_json`
   - `previous_state_json`
   - `new_state_json`
   - `reason`
   - `confirmation_phrase`
   - `status`
   - `performed_by_user_id`
   - `created_at`
   - `confirmed_at`

No destructive migration is approved by this audit.

## Minimum API Patch

Workspace:

- `findesk_workspace_get`
- `findesk_workspace_set`

Transfers:

- `findesk_transfer_list`
- `findesk_transfer_create`
- `findesk_transfer_update`
- `findesk_transfer_confirm`
- `findesk_transfer_cancel`

Report Assembly:

- `findesk_report_assembly_get`
- `findesk_report_item_attach`
- `findesk_report_item_detach_prepare`
- `findesk_report_finalize`
- `findesk_report_list`
- `findesk_report_detail`
- `findesk_report_export`

Protected Actions:

- `findesk_protected_action_prepare`
- `findesk_protected_action_confirm`

Compatibility:

- existing `advance_*`, `on_the_go_*`, `ledger_group_*` APIs remain available while Phase 2 moves to clean product APIs.

## Navigation / UX Gate

Before physical QA:

- old module routes must not be visible as normal user navigation;
- root path must show the new FinDesk hierarchy;
- top shell must include title, back, compact menu, login/logout and language access;
- Back must use product navigation stack, not always root;
- Cash/Card choice must be an intermediate screen before Live Journal;
- Card warning must appear only when admin manually enters a non-zero card balance;
- Live Journal must stay records-feed-first;
- Protected Actions must be its own product screen, not a route into old advances.

Approved screen hierarchy:

```text
Welcome Hall
  -> Solo Workspace
      -> Cash / Card Choice
          -> Live Journal
  -> Team Workspace
      -> Admin Card
          -> Cash / Card Choice
              -> Live Journal
          -> Add Money
          -> Issue Money
          -> Reports Waiting Review
      -> Employee Card
          -> Cash / Card Choice
              -> Live Journal
          -> My Journals
      -> Report Assembly
      -> Reports
      -> Protected Actions
```

## Sprint 2 QA Gate

The gate is open with blockers.

Must pass before implementation is considered ready for physical QA:

- [ ] Existing auth inspected and preserved
- [ ] Existing sessions preserved
- [ ] Existing database inspected
- [ ] No destructive DB action planned
- [ ] Manifest/service worker preserved
- [ ] Attachment/storage foundation preserved
- [ ] FinDesk remains a money journal, not ERP/accounting
- [ ] Old interface shell removed from normal user path
- [ ] Welcome Hall is product entry, not landing page
- [ ] Live Journal is clean records feed
- [ ] Live Journal has no reports/analytics/categories/dashboard
- [ ] Input uses `+/- amount and note`
- [ ] Cash/Card choice exists before Live Journal
- [ ] Cash and Card remain separate streams
- [ ] Card defaults to 0
- [ ] Card warning appears only during manual non-zero card balance entry
- [ ] Card may be assigned to employee
- [ ] Team Workspace remains people-first
- [ ] Employee Card uses name, position, issued, remaining
- [ ] Admin transfer creates pending offer
- [ ] Employee journal is blocked while pending transfer exists
- [ ] Employee confirmation makes money active
- [ ] Admin may edit/delete unresolved transfer
- [ ] Issue, edit, confirmation and cancellation are logged
- [ ] Employee fixed journal becomes ready report
- [ ] Admin can attach journal to report
- [ ] Employee cannot change attached report
- [ ] Detach/rollback uses Protected Action
- [ ] Final report contains Cash, Card, Total
- [ ] Protected Actions require consequence preview, reason and `CONFIRM`
- [ ] Back uses actual navigation stack
- [ ] Language uses shared localization layer
- [ ] Login/logout state is visible in app shell
- [ ] No Nav Desk / Ops / other product links in FinDesk MVP menu
- [ ] Day/Night mode is outside current MVP

## Recommendation

Proceed only with constrained Phase 2 implementation after approving the additive schema/API patch above.

Do first:

1. Phase 2 top shell and product navigation stack.
2. Cash/Card intermediate screen before Live Journal.
3. First-class transfer tables and APIs.
4. Server-side active workspace preference.
5. First-class report assembly tables and APIs.
6. Protected Action prepare/confirm flow.

Do not do:

- do not continue adding UI cards over old modules;
- do not keep pending transfer as a hidden `moderation_note` contract;
- do not use `audit_log.details` as the only final report object for Phase 2;
- do not expose old `Выдачи / Открытый учет / Группы / Архив / Аудит` as the main user path;
- do not start visual styling until the new product structure is visible.
