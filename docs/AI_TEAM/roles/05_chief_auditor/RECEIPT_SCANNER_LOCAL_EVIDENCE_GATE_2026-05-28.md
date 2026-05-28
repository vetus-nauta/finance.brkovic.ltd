# Receipt Scanner Local Evidence Gate

Date: 2026-05-28
Role: Chief Auditor FinDesk
Decision: approved for the local browser/HTTP file-input scanner slice only.

## Evidence Reviewed

- `docs/AI_TEAM/32_RECEIPT_SCANNER_SPRINT_2026-05-28.md`
- `docs/AI_TEAM/31_RECEIPT_SCANNER_TASK_CARD_2026-05-28.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `/tmp/findesk-receipt-scanner-20260528RSQA01/receipt-scanner-qa-result.json`

## Evidence IDs

- QA run: `20260528RSQA01`
- Viewports: mobile `390x844`, tablet `820x1180`, desktop `1440x900`
- Captures: `202`, `203`, `204`
- Original files: `18`, `20`, `22`
- Cleaned PDF files: `19`, `21`, `23`
- Final-package API recheck: `user_id=542`, `group_id=226`, `tape_id=303`, `capture_id=206`, `report_id=516`
- Final-package bundle: `scanner-package-bundle-20260528080910`

## Audit Findings

- Each checked viewport kept one money row after retry/replay.
- Each checked viewport stored exactly two scanner artifacts: `scanner_original` and `scanner_cleaned_pdf`.
- Original and cleaned PDF shared one `proof_bundle_id`.
- Cleaned PDF pointed to the original through `source_file_id`.
- Original replay, cleaned PDF replay, and signed sync replay were idempotent.
- Closed final report package evidence contained both `scanner_original` and `scanner_cleaned_pdf`.
- In the closed final report package, cleaned PDF pointed back to the original through `derived_from_file_id`.
- `scripts/local-smoke.php` now checks scanner proof roles, bundle, derived original, and hash metadata inside the closed package; PHP CLI execution remains unavailable in this shell.
- The checked local flow preserved original source evidence and treated the cleaned PDF as a derivative, not a replacement.

## Boundary

This gate does not approve:

- physical camera capture;
- installed iPhone/Android PWA mode;
- production device behavior;
- automatic edge detection;
- OCR;
- production deployment;
- full release readiness.

Real-device PWA/camera QA on iPhone Safari PWA and Android Chrome is required before production scanner deployment is treated as ready.

## Next Owner

Project Director for real-device/PWA camera QA routing and deploy decision.
