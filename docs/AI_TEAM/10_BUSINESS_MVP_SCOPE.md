# CEO Business MVP Scope

Date: 2026-05-26

Owner: Project Director

Status: full business-MVP product gate approved; production deploy remains separate.

## Director Correction

The 2026-05-26 approved gate is the MVP foundation: the project proved the core money tree for fast capture, cash/card split, current period, finalized report snapshot/export, carryover, proof, and review boundary.

The CEO business MVP is broader and is not complete yet.

The live site and older handoffs confirm FinDesk is not only a report checker. The product historically has three product areas:

- `On the Go`: capture money on the move;
- `FinDesk`: reports, checking, moderation, group finance;
- `Advanced`: everything that is not in the current business MVP.

Director rule:

- `Advanced` is not a third mandatory MVP layer.
- `Advanced` is the controlled parking area for non-MVP, future, specialist, admin-heavy, or deeper workflows.
- If a feature is required for the first ordinary working loop, it belongs in business MVP.
- If a feature is real but not required for the first ordinary working loop, it stays visible in product memory and moves to `Advanced` / post-MVP.

Old modules that must not be lost:

- group messages and unread state;
- Trip with Friends / travel equalization;
- Business Desk / business solutions and printable documents.

## Business MVP In One Line

FinDesk MVP is ready when a normal non-accountant can run the money cycle from field event to archived group report:

```text
зафиксировать -> проверить/понять -> сдать отчет -> сохранить -> распечатать/экспортировать -> свести группу отчетов -> сохранить/распечатать -> архив
```

## Must Be In Business MVP

### -1. Field Combat Mode

This is the foundation of practical success.

The field user must be able to:

- write a money fact quickly;
- take a photo;
- scan or attach proof;
- let the system calculate totals automatically;
- leave an unfinished session open;
- return without losing current data;
- keep working even when network or attention is unstable.

The app must continuously preserve current work before the report is closed.

Detailed rule: `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`.

### 0. Mobile-First Multitasking

- phone is not a reduced desktop;
- user can switch between capture, review, group money, messages, archive, and MVP workspaces without losing current work;
- only a few primary entrances stay permanent;
- secondary/non-MVP workspaces stay reachable through `Advanced` / a stable hub;
- actions are contextual and near the current task;
- no product module is deleted to make the phone screen easier.

### 1. Fixation

- fast income/expense capture;
- cash expense;
- card expense;
- received money;
- accountable money;
- person/participant;
- proof/photo/comment;
- group context.

### 2. Analysis And Review

- user can see where money is now;
- user can see who holds or spent money;
- user can see what is cash, card, accountable, returned, carryover;
- missing proof or questionable row is visible;
- draft/review/accepted/final states are understandable.

### 3. Report Submission

- participant can submit a report;
- reviewer can accept or reject;
- accepted report becomes final truth only through visible action;
- finalized report receives stable identity.

### 4. Save And Print

- finalized participant/report can be saved as immutable historical truth;
- finalized report can be exported or printed;
- later current-period entries do not mutate the saved report.

### 5. Group Report Consolidation

- several participant reports can be gathered into one group report;
- group report shows common group pot;
- group report preserves physical cash/card separation;
- group report shows per-participant responsibility and totals;
- group report can be finalized, saved, printed/exported, and archived.

### 6. Groups, Participants, And Money Flows

- group has participants;
- participants can receive, spend, return, or report money;
- all streams are visible in the common group pot;
- common pot does not erase who holds money or where physical cash/card value is.

### 7. Archive

- closed participant reports remain reachable;
- closed group reports remain reachable;
- proof remains reachable from report numbers;
- archive is history and evidence, not a money mutation action.

### 8. Group Messages

- group has an internal message thread;
- participants and managers can ask/report/clarify inside the group context;
- unread state is visible;
- report return/acceptance and missing-proof questions can be communicated without leaving the group workspace;
- messages are permission-aware and group-scoped.

Minimum MVP level: preserve and verify existing send/list/unread behavior and make clear where messages live in the group workflow.

### 9. Travel Equalization

- travel group can have people, shared pot, expenses, and final equalization;
- product keeps a separate place for travel alignment, not mixed into the ordinary business cash report;
- travel equalization must be designed so it can reuse the same proof, participant, group pot, and archive concepts.

Minimum MVP level: keep the product contract visible and classify whether the first travel workflow is part of business MVP. If not, put it into `Advanced` without deleting the idea.

### 10. Business Solutions

- Business Desk remains a separate section, not buried inside daily money capture;
- company profile, clients, proformas, and printable business documents remain part of the product surface;
- business documents must not pollute cash/report formulas unless explicitly connected later.

Minimum MVP level: preserve the working Business Desk/proforma/print path and keep it separate from operational money reports.

If a business-solutions feature is not needed in the first ordinary money loop, it belongs in `Advanced`.

## Already Proven In Foundation

- fast field capture slice;
- cash/card separation in the checked paths;
- current period vs historical finalized report separation;
- historical finalized report/export by `report_id` for new finalizations;
- carryover does not reappear as new income;
- current export can contain carryover plus current income plus current Live Report expense;
- mobile/tablet/desktop UI for the checked current/historical flow;
- Chief Auditor found no P0 blocker in the foundation path.
- Field Combat no-data-loss foundation: after visible save/retry state, typed facts survive refresh/return, proof retry does not duplicate money rows, card/cash separation holds, and autosave/retry does not submit/include/finalize.
- `Закрытый групповой отчет` package gate: one closed group report opens by `report_id` as an immutable archive object with participant reports, captures/proofs, money rows, accountable/advance state, message/audit references, authorized proof access, print/PDF, and immutability after later current activity.
- live/public product surface advertises the three-layer model: On the Go, FinDesk, Advanced.
- repository contains existing group messages API/UI and Business Desk/proforma code.
- repository contains Travel/Trip with Friends product marker.
- residual surface QA passed on run `20260527968710`: group messages, Business Desk/proforma, Travel/Advanced staging, and mobile/tablet/desktop navigation reachability.
- Chief Auditor approved the full business-MVP product gate on 2026-05-27.

## Not Yet Proven For Business MVP

- production deployment and production smoke.

## Post-MVP, Not Required For Business MVP

- full accounting-system completeness;
- deep AI analytics;
- advanced forecasts and management dashboards;
- migration of every legacy finalized report into snapshots;
- broad visual redesign;
- third-party accounting integrations;
- perfect automation around local CLI PHP;
- advanced fraud/audit scoring beyond visible proof and review gates.
- full social chat platform beyond group finance messages;
- full travel settlement engine unless travel is selected as launch-critical;
- full invoicing/accounting suite beyond existing Business Desk/proforma path.
- anything not required for the first ordinary working loop belongs in `Advanced`.

## Acceptance Scenario

A business-MVP test must prove this ordinary flow:

```text
Group receives money into the common pot.
Several participants capture cash/card/accountable movements.
Field users write, photo/scan proof, and keep unfinished sessions without data loss.
Participants submit reports with proofs.
Reviewer accepts the reports.
System saves and prints/exports each finalized report.
System consolidates accepted participant reports into one group report.
Group report preserves common pot, per-person responsibility, cash/card split, carryover, and evidence.
Group report is saved, printed/exported, and archived.
Group messages preserve the discussion/questions around report submission and return.
Business Desk remains available as a separate business-solutions section with printable proforma path.
Travel equalization section remains visible or explicitly staged, with no conflict against common-pot logic.
Closed reports stay immutable after new current-period activity begins.
```

## Next Role

Project Director must complete production package planning before any live deployment.
