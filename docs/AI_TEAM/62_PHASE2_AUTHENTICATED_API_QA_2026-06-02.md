# FinDesk Phase 2 Authenticated API QA

Date: 2026-06-02
Status: PASS for authenticated local API workflow. Physical UX QA is still pending.

## Scope

Checked locally on:

```text
http://127.0.0.1:18889
```

This QA verifies Phase 2 backend workflow behavior through authenticated API calls. It does not replace browser/mobile physical QA.

## Test Users

Admin:

- email: `phase2-admin@example.test`
- user_id: `631`

Employee:

- email: `phase2-worker@example.test`
- user_id: `632`

Group:

- name: `Phase 2 QA Team 20260602`
- group_id: `264`

## Auth

Used the existing local email-code flow:

- `request_code`
- `verify_code`

Local mail mode returned development sign-in codes on localhost. No auth bypass was used.

## Cash Transfer Flow

Admin created first-class cash transfer:

- API: `findesk_transfer_create`
- transfer_id: `1`
- stream: `cash`
- amount: `EUR 500`
- state: `pending`

Employee attempted Live Journal before confirmation:

- API: `on_the_go_signed_sync`
- result: blocked
- error: `findesk_transfer_pending_confirmation_required`
- transfer_id: `1`

Employee confirmed:

- API: `findesk_transfer_confirm`
- result: transfer state became `active`
- active tape_id: `427`

Employee wrote cash journal:

```text
-25 Fuel
-15 Parking
```

Result:

- start cash: `500`
- cash spent: `40`
- cash remaining: `460`
- records: `2`

Employee submitted journal:

- API: `on_the_go_card_submit`
- state: `submitted`

Admin Report Assembly:

- API: `findesk_report_assembly_get`
- ready item found: tape_id `427`

Admin attached item:

- API: `findesk_report_item_attach`
- report_id: `1`

Draft summary:

```text
Cash received: 500
Cash spent: 40
Cash remaining: 460
Card spent: 0
Total remaining: 460
```

Admin finalized:

- API: `findesk_report_finalize`
- report_id: `1`
- status: `finalized`

## Protected Action Flow

Admin prepared protected action:

- API: `findesk_protected_action_prepare`
- protected_action_id: `1`
- action: `report_archive`
- entity: `findesk_report`
- entity_id: `1`
- required phrase: `CONFIRM`

Wrong phrase check:

- API: `findesk_protected_action_confirm`
- phrase: `WRONG`
- result: blocked
- error: `confirmation_phrase_required`

Correct phrase check:

- API: `findesk_protected_action_confirm`
- phrase: `CONFIRM`
- result: confirmed

Report status after commit:

- report_id: `1`
- status: `archived`
- Cash/Card/Total summaries preserved.

## Card / Non-Cash Flow

Admin created first-class card transfer:

- API: `findesk_transfer_create`
- transfer_id: `2`
- stream: `card`
- amount: `EUR 200`
- state: `pending`

Employee attempted Card Live Journal before confirmation:

- API: `on_the_go_signed_sync`
- result: blocked
- error: `findesk_transfer_pending_confirmation_required`
- transfer_id: `2`

Employee confirmed:

- API: `findesk_transfer_confirm`
- result: transfer state became `active`
- active tape_id: `429`

Employee wrote card journal:

```text
-60 Supplies
-20 Taxi
```

Result:

- card tape cash_received: `0.00`
- card spent: `80`
- cash spent: `0`
- cash remaining: `0`
- card remaining/delta: `-80`
- records: `2`

Employee submitted card journal:

- API: `on_the_go_card_submit`
- state: `submitted`

Admin attached item:

- API: `findesk_report_item_attach`
- report_id: `2`

Draft summary:

```text
Cash received: 0
Cash spent: 0
Cash remaining: 0
Card spent: 80
Card remaining: -80
Total spent: 80
```

Admin finalized:

- API: `findesk_report_finalize`
- report_id: `2`
- status: `finalized`

## Active Workspace Preference

Checked:

- admin `findesk_workspace_set`: mode `group`, group_id `264`
- employee `findesk_workspace_set`: mode `group`, group_id `264`

Result:

- both users returned persisted group workspace preference.

## Report List Check

API:

- `findesk_report_list`

Result:

- report_id `2`: `finalized`, Card section contains spent `80`;
- report_id `1`: `archived`, Cash section contains `500 / 40 / 460`;
- summaries remain separated by Cash, Card and Total.

## QA Verdict

PASS for authenticated local API workflow:

- first-class transfer pending state works;
- employee journal is blocked while transfer is pending;
- employee confirmation activates money;
- cash journal uses issued cash as start amount;
- card journal keeps cash start at `0`;
- Cash and Card remain separated in report assembly and final reports;
- protected action requires reason and exact `CONFIRM`;
- active workspace preference is persisted.

## Still Pending

Not checked in this pass:

- physical desktop browser QA;
- iPhone physical QA;
- Android physical QA;
- iPad physical QA;
- visual clarity of the Phase 2 shell;
- real user ability to understand the flow without explanation;
- production DB rollout;
- production deploy.

## Next Recommended Step

Run local browser/physical QA against the same workflow before production.

Production rollout still requires a controlled DB apply:

```text
deploy/findesk_phase2_foundation.sql
```

Then upload only after QA approves the local product path.
