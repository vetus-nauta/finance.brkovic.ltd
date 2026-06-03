# FinDesk Phase 2 Implementation Sprint - Local

Date: 2026-06-02
Status: local implementation pass complete; production deploy not done.

## Source

Working gate:

- `docs/AI_TEAM/59_PHASE2_LOGIC_NAV_ENGINE_AUDIT_2026-06-02.md`

This sprint follows the Phase 2 rule: preserve old foundations, add first-class product contracts, and stop exposing old modules as the normal user path.

## Backend / Data

Added:

- `app/findesk_phase2.php`
- `deploy/findesk_phase2_foundation.sql`

Connected in:

- `public/api.php`

New first-class additive tables:

- `findesk_workspace_preferences`
- `findesk_transfers`
- `findesk_transfer_events`
- `findesk_reports`
- `findesk_report_items`
- `findesk_protected_actions`

New APIs:

- `findesk_workspace_get`
- `findesk_workspace_set`
- `findesk_transfer_list`
- `findesk_transfer_create`
- `findesk_transfer_update`
- `findesk_transfer_confirm`
- `findesk_transfer_cancel`
- `findesk_report_assembly_get`
- `findesk_report_item_attach`
- `findesk_report_finalize`
- `findesk_report_list`
- `findesk_report_detail`
- `findesk_protected_action_prepare`
- `findesk_protected_action_confirm`

Important behavior:

- transfer lifecycle is first-class: `pending -> active -> cancelled`;
- issue/edit/confirm/cancel write event rows and audit records;
- employee confirmation creates the active Live Journal tape;
- cash transfer creates a cash-start tape;
- card transfer creates a card stream tape with cash start `0`;
- pending first-class `findesk_transfers` block matching employee Live Journal input;
- report assembly has first-class report and report item tables;
- protected actions require reason plus `CONFIRM` and can handle report item detach / report archive.

Compatibility:

- old `advance_*`, `on_the_go_*`, `ledger_group_*` APIs were not removed;
- old tables were not dropped or destructively migrated;
- additive schema is also mirrored in `deploy/findesk_phase2_foundation.sql` for controlled DB rollout.

## Frontend / Navigation

Changed:

- `public/app.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `public/service-worker.js`

Result:

- asset version moved to `20260602-phase2-shell1`;
- service worker cache moved to `findesk-20260602-phase2-shell1`;
- new top shell has Back, current screen title, compact product menu, account, logout and language;
- visible product menu no longer exposes old `Выдачи / Открытый учет / Группы / Архив / Аудит` routes;
- Cash/Card choice is a separate screen before Live Journal;
- Live Journal stream selector inside the journal is locked to the chosen stream with a `Сменить поток` route;
- Protected Actions is a product screen instead of a jump into old advances;
- old On-the-Go stream gate redirects to Phase 2 stream choice.

## Verification

Passed:

```bash
node --check public/assets/app.js
node --check public/assets/i18n.js
node --check public/service-worker.js
git diff --check
```

HTTP local:

```text
http://127.0.0.1:18889/app.php -> 200 OK
http://127.0.0.1:18889/api.php?action=current_user -> 200 OK
```

New unauthenticated endpoint load checks:

```text
findesk_workspace_get -> not_authenticated
findesk_transfer_list -> not_authenticated
findesk_report_assembly_get -> not_authenticated
findesk_protected_action_prepare -> not_authenticated
```

Meaning: new PHP module and schema guard load on the local server without syntax/schema fatal errors.

## Not Done

- production deploy;
- authenticated browser QA;
- real mobile physical QA;
- full transfer issue/confirm flow with two real users;
- full report assembly/finalize flow with submitted journals;
- protected action behavior beyond the first local backend path.

## Current Gate

Local implementation is ready for authenticated QA.

Physical QA is still not fully closed until a real user can pass:

1. choose workspace;
2. choose Cash/Card before Live Journal;
3. record journal rows;
4. submit journal;
5. admin issues transfer;
6. employee confirms transfer;
7. admin assembles report;
8. protected action requires reason and `CONFIRM`;
9. old menu routes are not visible in normal workflow.

## Decision Needed Before Production

Approve a controlled DB rollout using:

```text
deploy/findesk_phase2_foundation.sql
```

Then run authenticated local QA before any upload to:

```text
https://finance.brkovic.ltd/app.php
```
