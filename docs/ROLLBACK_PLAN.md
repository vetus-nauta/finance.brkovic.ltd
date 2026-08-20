# Rollback Plan

Date: 2026-08-20

Status: draft.

## Rule

Rollback must restore the last known-good production state without data ambiguity.

## Required Before Cutover

- production database backup
- production file backup
- deployed runtime backup
- DNS state recorded
- migration logs
- rollback decision owner named

## Rollback Triggers

- auth failure for existing owner/admin
- tenant isolation failure
- missing workspace/ledger data
- ledger/report totals mismatch
- document access failure
- severe performance or availability failure

## Rollback Steps

1. Stop new writes to target system.
2. Preserve failed target logs and migrated database for forensic analysis.
3. Restore DNS/routing to previous production runtime.
4. Restore legacy database/files if they were changed.
5. Verify owner login and key reports.
6. Publish internal incident note.
