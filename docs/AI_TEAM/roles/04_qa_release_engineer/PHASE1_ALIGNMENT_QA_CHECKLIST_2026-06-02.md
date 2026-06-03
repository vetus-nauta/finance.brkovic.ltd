# QA Checklist — Phase 1 Alignment Patch

Date: 2026-06-02
Role: QA Release Engineer
Status: mandatory before next implementation sprint continues

## Transfer Offer / Acceptance

- [ ] Admin issues money -> transfer enters `Pending Transfer`
- [ ] Employee journal is blocked while pending transfer exists
- [ ] Money does not become active before employee confirmation
- [ ] Admin can edit unresolved transfer
- [ ] Admin can delete unresolved transfer
- [ ] Issue, confirmation, edit, and cancellation are logged

## Card / Non-Cash Definition

- [ ] Card and Cash are separate streams
- [ ] Card has its own Live Journal
- [ ] Card has its own Reports
- [ ] Card can be assigned to an employee
- [ ] Card balance warning is shown only during manual card balance entry
- [ ] Card warning does not appear during normal Card Journal entry

## Team Workspace

- [ ] Team Workspace remains people-first screen
- [ ] Primary visible objects are name, position, and state
- [ ] User-facing states are limited to:
  - [ ] `No records`
  - [ ] `Live Journal`
  - [ ] `Ready Report`
- [ ] Technical states such as `Attached`, `Locked`, `Archived` are not primary people-screen labels

## Employee Card

- [ ] Employee Card top layout uses:
  - [ ] `Name`
  - [ ] `Position`
  - [ ] `Issued`
  - [ ] `Remaining`
- [ ] Employee Card exposes:
  - [ ] `Open Live Journal`
  - [ ] `My Journals`
- [ ] Employee Card does not expose primary blocks for:
  - [ ] `Last Fixation`
  - [ ] `Delta`
  - [ ] `Spent`
  - [ ] `Analytics`

## Live Journal

- [ ] Main object of the screen is the records feed
- [ ] Live Journal is not dominated by balances, menus, reports, or dashboards
- [ ] Live Journal does not open the retired `Выберите отчет` stream gate
- [ ] Cash/Card selection is a compact control inside Live Journal
- [ ] Approved MVP input model is supported:
  - [ ] `📎  ± Amount and note...`
  - [ ] `-120 Fuel`
  - [ ] `+500 Cash received`
  - [ ] `-85 Food`

## Final Report Structure

- [ ] Final report contains:
  - [ ] `Cash`
  - [ ] `Card / Non-Cash`
  - [ ] `Total`
- [ ] Cash and Card remain separated until final report composition

## Visual Exposure Before Physical QA

- [ ] Physical QA starts from the new `Welcome Hall`
- [ ] Main route renders `phase1ProductShell`, not legacy `.ql-module`
- [ ] First screen does not auto-select old groups or old balances
- [ ] Workspace selector starts from explicit user choice:
  - [ ] `Выберите среду`
  - [ ] `Лично`
  - [ ] active groups
- [ ] First navigation layer exposes:
  - [ ] `Solo Workspace`
  - [ ] `Live Journal`
  - [ ] `Team Workspace`
  - [ ] `Reports`
  - [ ] `Protected Actions`
- [ ] User-facing navigation labels are clean Russian product labels
- [ ] Top navigation is a thin product row, not the old glass/pill menu
- [ ] Role workspaces are directly reachable:
  - [ ] `Admin Card`
  - [ ] `Employee Card`
  - [ ] `Report Assembly`
- [ ] Old accounting sections are not the dominant first-level product structure
- [ ] Old labels such as `Быстрые записи` and legacy `Детали` are not visible in the main route
- [ ] Legacy paths remain behind `Protected Actions`
- [ ] A tester can answer without explanation:
  - [ ] what FinDesk is
  - [ ] how to start
  - [ ] where to record money
  - [ ] where to see people
  - [ ] where to submit journals
  - [ ] where to assemble reports

## Gate Rule

Do not mark the next Phase 1 runtime pass as aligned until all applicable items above are checked with evidence.
