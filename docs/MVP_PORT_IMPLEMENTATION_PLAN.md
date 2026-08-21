# MVP Port Implementation Plan

Date: 2026-08-21

## Director Rule

This is not a greenfield rebuild.

The existing FinDesk MVP is the product behavior source. The new `brkovic.app` foundation is the target architecture for carrying that behavior forward with stronger auth, database integrity, mobile readiness, and future AI/OCR/bot input ports.

Do not invent replacement UX or accounting logic unless a current MVP behavior is proven broken and the replacement is explicitly documented.

## Test Data Rule

`Claudia Z` is not a sandbox. It is reserved for accepted beta/history data, migration evidence, and real product reconciliation.

All future manual QA, destructive checks, note conversion experiments, Smith trials, report experiments, and employee-money rehearsals must use the dedicated workspace:

```text
Тестовые прогоны
```

This workspace is marked in database metadata as non-financial truth and must not be used as acceptance evidence for Claudia Z balances.

## Model Mode Discipline

- `GPT-5.5 high` / strongest high-reasoning mode: architecture, financial invariants, security, production gates, migration reconciliation, hard UX decisions.
- Balanced mode: normal implementation, UI wiring, documentation, small bug fixes.
- Fast/light mode: dictionary expansion, bulk row classification, simple QA checklists, repetitive summaries.

Each sprint names the recommended mode. If the runtime cannot actually switch models, treat the mode as the expected reasoning level and risk posture.

## Sprint Roadmap

### SPRINT-101R - Foundation Financial Command Evidence

Recommended mode: `GPT-5.5 high`.

Goal:
Prove that the new foundation has a safe operational-entry command, not a fragile UI-only insert.

Agents:
- Financial Logic Engine Agent
- Data and Backend Core Agent
- QA, Audit, and Acceptance Agent

Scope:
- Verify `create_operational_entry` RPC behavior.
- Confirm transaction + ledger creation is atomic.
- Confirm safe row numbering.
- Confirm no-sign rows remain visible and non-counted.
- Confirm manual card income is blocked.
- Confirm audit event creation.
- Add or update smoke tests where evidence is missing.

Exit:
- SQL/API smoke proves the command.
- UI action uses the command only.
- No direct counted transaction insert from the web action.

### SPRINT-102R - Operational Journal MVP Parity

Recommended mode: strongest/high for design decisions, balanced for code.

Goal:
Port the proven working journal UX into the new foundation.

Agents:
- iOS-Native UX Layout Agent
- Frontend Performance and Interaction Agent
- Financial Logic Engine Agent as reviewer
- QA, Audit, and Acceptance Agent

Scope:
- Synced operational journal + structured check.
- Active zone behavior.
- Cash/Card switching.
- Create/edit/delete entry.
- New-row focus behavior.
- Entry detail modal/drawer.
- Closed-period edit guard.
- Desktop/tablet/mobile responsive behavior.

Exit:
- The user can run the daily accounting loop without dead buttons.
- Mobile is not a squeezed desktop.
- Desktop keeps two aligned work zones.
- Screenshots for desktop, iPhone, iPad mini, iPad 11+ portrait/landscape.

### SPRINT-103R - Quick Notes and Mr. Smith Parity

Recommended mode: balanced for UI, high for Smith logic, fast/light for dictionary bulk work.

Goal:
Make Notes behave like the accepted MVP/Apple Notes style and make Smith a confirmation assistant, not an automatic writer.

Agents:
- Frontend Performance and Interaction Agent
- Localization/Linguistic Rules Agent
- Financial Logic Engine Agent as reviewer
- QA, Audit, and Acceptance Agent

Scope:
- Current note always first and clearly labeled.
- Notes history as a separate full screen/list, not modal clutter.
- Saved note cards are compact: date, total, status, delete action.
- Sent/converted notes are visually subdued.
- Send-to-journal runs Smith preview.
- User confirms proposals before ledger rows are created.
- Duplicate/same-amount warnings are visible before confirmation.
- Future placeholders: scanner, Telegram, voice, file upload.

Exit:
- Notes are usable on phone without exposing financial noise.
- Converted notes clear the current input and remain in history.
- Smith proposals are understandable to a non-technical user.

### SPRINT-104R - Reports, Archive, and Closed Periods

Recommended mode: `GPT-5.5 high`.

Goal:
Port the working report logic: report is a generated snapshot over operational rows, not a separate financial truth.

Agents:
- Financial Logic Engine Agent
- Data and Backend Core Agent
- Frontend Performance and Interaction Agent
- QA, Audit, and Acceptance Agent

Scope:
- Select report period from journal.
- Close selected rows into a report line.
- Keep closed period collapsed in operational journal.
- Report archive is a report viewer, not another operational journal.
- Open report, view categories, expand category rows.
- Return report for revision without hiding it.
- Save revised report version.
- Export HTML/PDF/table through controlled version records.

Exit:
- Existing report patterns from MVP are visible and testable.
- Closed report rows remain traceable to source operational entries.
- Report versions do not mutate financial truth silently.

### SPRINT-105R - Hall, Roles, Invitations, and Employee Money

Recommended mode: high for permissions/money; balanced for UI.

Goal:
Port the hall and employee accountable-money workflow.

Agents:
- Data and Backend Core Agent
- Financial Logic Engine Agent
- Security and Privacy Agent if available
- QA, Audit, and Acceptance Agent

Scope:
- Workspace hall.
- Create/delete workspace with 60-day trash policy.
- Invite/referral link.
- Role-based workspace entry.
- Employee simple accounting view.
- Issue money under report.
- Employee accepts/reports/returns/overspends.
- Admin approves and materializes into the main workspace.
- Final pool view: admin + employees money position.

Exit:
- Three-employee scenario passes with exact arithmetic.
- Employee cannot see admin-only financial picture.
- Admin can reconcile cash advances without double counting.

### SPRINT-106R - Claudia Z Migration and Reconciliation

Recommended mode: `GPT-5.5 high` for reconciliation; fast/light for bulk classification.

Goal:
Load Claudia Z as beta history while preserving the current true final balance and report chain.

Agents:
- Legacy Import and Archive Agent
- Financial Logic Engine Agent
- Data and Backend Core Agent
- Localization/Linguistic Rules Agent
- QA, Audit, and Acceptance Agent

Scope:
- Import full operational feed.
- Preserve raw history source.
- Map current operational balance separately.
- Migrate report snapshots and links.
- Reconcile cash/card/accountable/admin debt.
- Produce mismatch report.
- Keep dictionary-learning data separate from financial truth.

Exit:
- Counts and balances match accepted MVP evidence.
- No hidden correction rows are introduced to make totals look good.
- Every report proves its source rows.

### SPRINT-107R - Production Readiness Gate

Recommended mode: `GPT-5.5 high`.

Goal:
Prepare a deployable product slice, not a demo.

Agents:
- Security and Privacy Agent if available
- Data and Backend Core Agent
- QA, Audit, and Acceptance Agent
- Frontend Performance and Interaction Agent

Scope:
- Supabase RLS tests.
- Auth/email flow.
- Vercel env and domain checks.
- Storage privacy.
- No V1 runtime leakage.
- Backup/restore rehearsal.
- Desktop/mobile/tablet manual QA.
- GitHub/Vercel/Supabase state sync.

Exit:
- Production gate report exists.
- Rollback path exists.
- `brkovic.app` is either explicitly accepted as production slice or marked staging.

## Immediate Next Action

Start with SPRINT-101R. The RPC exists, but acceptance evidence must be made explicit before building more UX on top of it.
