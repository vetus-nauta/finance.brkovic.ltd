# Step 3 - Accountable Money / Advances - 2026-05-20

## Goal

Add the first moderation bridge between `On the Go` and the group ledger.

The important rule:

```text
money issued to an employee is not a group expense yet
```

It becomes group expense rows only after the employee submits the pocket report and a manager/admin accepts it.

Terminology clarification:

- These employee pocket reports are "подотчеты".
- A podotchet is not the administrator's own live report. It is an employee tape linked to `cash_advances.on_the_go_tape_id`.
- The issued amount is owned by Advanced/cash advance data. Mobile "Живой отчет" may edit rows, attachments and submission state, but must not replace the issued base amount with the administrator's current cash balance.

## Product Flow

```text
advanced/admin issues money to base employee
base employee sees received / spent / remaining
base employee records expenses in On the Go
base employee enters real cash left and submits
manager/admin reviews the submitted row
manager/admin accepts
accepted On the Go expenses are copied into group ledger
```

This matches the intended red-line model:

- before acceptance, admin sees issued accountable money as a pending advance;
- after acceptance, the pending row can be expanded into the employee's actual expenses;
- expenses keep the source user identity in the group ledger.

## Database Changes

New migration:

```text
deploy/advances_foundation.sql
```

New table:

```text
cash_advances
```

New `on_the_go_tapes` fields:

```text
group_id
advance_id
submitted_at
actual_remaining
difference_amount
```

## Backend Changes

New module:

```text
app/advances.php
```

New API actions:

```text
advance_create
advance_list
advance_submit
advance_accept
advance_return
```

Access rules:

- `base`: can see and submit only own assigned advances;
- `manager`: can moderate submitted advances;
- `advanced`: can issue money, manage money and moderate.

## Ledger Conversion

`advance_accept` converts only pending On the Go expense captures:

```text
cash_out     -> ledger expense / cash
noncash_out  -> ledger expense / noncash
```

The generated ledger rows use:

```text
user_id  = assigned employee
group_id = work group
note     = source advance and On the Go capture id
```

On the Go attachments are copied to the ledger entry files during acceptance.

## Verification

The local smoke script now checks steps 1, 2 and 3 together:

```bash
php scripts/local-smoke.php http://127.0.0.1:18888
```

New Step 3 checks:

```text
second base member joins for advance flow
advance worker cannot write direct group ledger
admin issues accountable money without ledger expense
base worker sees received/spent/remaining for own advance
advance summary updates from On the Go capture
base worker submits advance for moderation
admin sees submitted advance red-line candidate
admin accepts advance and expands expenses into group ledger
accepted advance appears in group ledger with source user
admin returns mismatched advance for correction
```

Fresh migration order verified locally with a temporary database:

```text
auth -> ledger -> groups -> group access -> categories -> messages -> business -> on the go -> sessions -> advances
```

## Still Foundation, Not Final UI

This step is backend and smoke-test complete.

The next UI step should add a proper money/moderation screen:

- issue money per employee;
- show red pending rows;
- expand submitted rows into captured expenses;
- accept or return for correction;
- keep the existing desktop/tablet/mobile layout split.
