# Formal Local Recheck After Frontend Leftovers And Group Delete Hardening

Role: QA Release Engineer FinDesk.
Task: formal local recheck after Frontend/UX leftovers fix and Backend/Data `group_delete` hardening.
Run id: `20260528LOCALLEFTOVERS01`.
Status: PASS for requested local checks.

## Scope

- Runtime code was not changed by QA.
- Backend/API/UX implementation, scripts, deploy SQL, and financial formulas were not changed by QA.
- QA wrote only role documentation and this artifact.

## Baseline

- `HEAD=72b38e6`
- `origin/main=72b38e6`
- local server `http://127.0.0.1:18889/api.php?action=current_user`: HTTP `200`, `ok=true`
- `node --check public/assets/app.js`: PASS
- `node --check public/assets/i18n.js`: PASS
- HTTP asset load:
  - `app.php`: `200`, `83303` bytes
  - `app.css?v=20260528-frontend-residual1`: `200`, `145876` bytes
  - `app.js?v=20260528-frontend-residual1`: `200`, `470944` bytes
  - `i18n.js?v=20260528-frontend-residual1`: `200`, `44254` bytes

## Login/Auth

- Loaded `app.php` fallback H1: `FinDesk access code`.
- Old `FinDesk sign-in code` was not present as the login H1 in loaded `app.php`.
- Auth flow still loads: `request_code` and `verify_code` worked for the fresh API users used in this run.

## Live Report UI Leftovers

- Browser automation modules were not available in this QA shell: `playwright`, `@playwright/test`, and `puppeteer` returned `MODULE_NOT_FOUND`.
- QA performed static/HTTP checks instead of browser screenshot overlap acceptance.
- Current HTML/CSS/JS assets load and parse.
- Static selectors for Live Report card/action layout hardening are present.

## Scanner Modal

- `receiptScannerModal` exists.
- Top `×` close button exists with `data-close-receipt-scanner`.
- Visible `Закрыть` button exists with `data-close-receipt-scanner`.
- JavaScript handles `data-close-receipt-scanner`.
- JavaScript handles outside click on the scanner modal.
- Global Escape handler includes `data-close-receipt-scanner`.

## Module/Work-Zone Persistence

- `QL_MODULE_STATE_KEY` exists.
- `ontherun` is in the allowed module-state list.
- `qlSaveModuleState('ontherun', ...)` persists screen, stream type, tape id, and archive mode.
- Restored `ontherun` state routes through `qlApplyModuleState()` and `qlSetModule()`.
- Syntax checks passed.

## group_delete API

Fresh fixture:

- admin user: `555`
- base member user: `556`
- group_id: `233`

Verified:

- admin created the group;
- base member joined through invite as `access_level=base`;
- admin created one group ledger income row;
- base member `group_delete` returned `admin_required`;
- admin `group_delete` returned `ok=true`, `status=archived`, `archive_mode=soft`;
- evidence counters were preserved: `ledger_entries` stayed `1` before and after archive;
- repeated admin `group_delete` returned `already_deleted=true`;
- archived group was absent from active `group_list` for both admin and base member.

Result JSON:

```json
{
  "ok": true,
  "run_id": "20260528LOCALLEFTOVERS01",
  "group_id": 233,
  "denied_error": "admin_required",
  "archive_status": "archived",
  "archive_mode": "soft",
  "members_archived": 2,
  "evidence_before": {
    "ledger_entries": 1,
    "live_report_tapes": 0,
    "live_report_captures": 0,
    "proof_files": 0,
    "advances": 0,
    "messages": 0,
    "closed_final_reports": 0
  },
  "evidence_after": {
    "ledger_entries": 1,
    "live_report_tapes": 0,
    "live_report_captures": 0,
    "proof_files": 0,
    "advances": 0,
    "messages": 0,
    "closed_final_reports": 0
  },
  "evidence_preserved": true,
  "already_deleted": true,
  "archived_group_visible_to_admin": 0,
  "archived_group_visible_to_member": 0
}
```

## Scanner Regression Guard

- Previous local scanner file-input QA gate `20260528RSQA01` remains accepted.
- Full scanner matrix was not rerun in this task.
- No syntax/API regression was found in this scoped recheck.

## Limitations

- Physical iPhone Safari PWA camera and Android Chrome/PWA camera behavior were not tested.
- Screenshot-level mobile overlap acceptance was not possible because browser automation libraries were unavailable in this shell.
- Production deploy readiness is still controlled by the separate deploy checklist and real-device scanner gate.

## QA Decision

Requested local recheck: PASS.
Production release: not declared ready by this artifact.
Next owner: Project Director / Deploy Owner.
