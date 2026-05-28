# Task Card: Receipt Scanner Storage/API Contract

Role: Backend/Data Engineer
Priority: P1 after current production MVP stabilization
Main task card: `docs/AI_TEAM/31_RECEIPT_SCANNER_TASK_CARD_2026-05-28.md`

## Task

Define the backend/API/storage design for FinDesk-owned `Receipt Scanner`.

## Read First

- `docs/AI_TEAM/31_RECEIPT_SCANNER_TASK_CARD_2026-05-28.md`
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/17_DB_BACKUP_ROLLBACK_PLAN.md`
- `app/on_the_go.php`
- `public/api.php`

## Required Output In This Role Folder

- Update `FINDINGS.md`.
- Update `STATUS.md`.
- Update `TASKS_TO_OTHERS.md`.

## Acceptance

- Define storage for original source and cleaned PDF.
- Define metadata fields: corners, perspective transform, filters, version, hashes.
- Define proof state: pending, saved, failed, retrying.
- Define links to `capture_id`, `tape_id`, archive, report package, final report.
- Compare client-side PDF generation vs server-side generation for first slice.
- Do not implement runtime code until Project Director selects the slice.

## Short Report To Main Chat

Use one short report only:

Role: Backend/Data
Task: Receipt Scanner storage/API contract
Status:
Evidence pointer:
Blocker:
Next owner:
