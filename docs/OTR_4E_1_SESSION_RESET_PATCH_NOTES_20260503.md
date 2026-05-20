# Quick Ledger — OTR-4E-1 Session Reset Patch

Date: 2026-05-03

## Purpose

This patch stops treating On the Go as one long global journal. It makes the visible working journal depend on the selected working zone:

- Cash button → active cash session only
- Card button → active card session only
- Closed sessions are packed into session cards and do not remain in the active journal

## Files changed

- app/on_the_go.php
- public/assets/app.js
- public/assets/app.css
- public/app.php
- public/index.php

## Version

Asset version bumped to `20260503-60`.

## Notes

This is a correction patch, not the final session-detail editor. Tapping a closed session card currently confirms selection and marks the next required step: opening session details for review/edit/activate/archive.
