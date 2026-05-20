# Quick Ledger — OTR-4F Two-Zone Session UI Patch

Date: 2026-05-03
Version: 20260503-61

## Purpose

This patch corrects the On the Go session UX after OTR-4E.

The screen now treats Cash and Card as two separate operational zones. Closed sessions are rendered as packed session cards inside their own Cash/Card columns, not as loose records in the active journal.

## Added

- Two-zone session board: Cash column and Card column.
- Active and closed session cards inside each column.
- Session detail modal.
- API actions:
  - on_the_go_session_detail
  - on_the_go_activate_session
  - on_the_go_archive_session
- Activate session flow: selected session becomes active and the current active session of that type is closed.
- Archive session flow.

## Preserved

- Active journal remains filtered by selected zone.
- Pending records remain not reportable until reviewed or moved to Ledger.
- Cash/Card routing remains separated by session_type.
