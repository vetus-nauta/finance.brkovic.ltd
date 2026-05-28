# MVP Exit Criteria

Date: 2026-05-26

## Scope Correction 2026-05-26

The gate approved earlier on 2026-05-26 is the MVP foundation gate: the money-tree core can distinguish current period, historical finalized report, carryover, cash/card separation, proof, and fast capture.

The CEO business MVP is broader. It is tracked in `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`.

Business MVP is not complete until FinDesk supports the full operational loop:

```text
фиксация -> анализ/проверка -> сдача отчета -> сохранить -> распечатать/экспортировать -> свести группу отчетов -> сохранить/распечатать -> архив
```

Business MVP scope also preserves the older FinDesk product modules:

- group messages;
- travel equalization / Trip with Friends;
- Business Desk / business solutions.

Director correction:

- `Advanced` means everything outside the current business MVP;
- field combat mode is part of business MVP, not polish.
- non-MVP modules stay visible in product memory and `Advanced`, not deleted.

## MVP Purpose

The MVP is not a complete accounting system.

The MVP is ready when a non-accountant can answer:

```text
Где деньги, кто за них отвечает, что потрачено, что проверено, что закрыто, где доказательство?
```

## MVP Includes

1. Field combat mode for a moving user:
   - write;
   - photo;
   - scan/attach proof;
   - automatic calculation;
   - continuous save;
   - no data loss in unfinished sessions.

2. Fast Live Report capture for a moving user:
   - received cash;
   - cash expense;
   - card expense;
   - proof/photo path;
   - accountable-money entry point.

3. Cash/card separation:
   - card spending never changes physical cash;
   - cash spending changes only cash;
   - each stream stays readable.

4. Review before final truth:
   - draft/submitted/on-review records do not silently enter final report;
   - accepted/included records can enter report.

5. Current open period:
   - starts from carryover after finalization;
   - does not treat old closed income as new current income;
   - current export shows current truth.

6. Historical finalized report:
   - new finalizations have immutable snapshot/export by `report_id`;
   - closed report can still export old `1000 / 600 / 400`;
   - later current-period entries do not mutate the closed report.

7. Archive/proof:
   - finalized Live Report evidence remains reachable;
   - archive is history/cleanup, not a money mutation action.

8. Mobile usability:
   - main field capture works on phone;
   - current vs historical report actions are understandable;
   - no dense accounting table is required for field entry.

## MVP Does Not Include

- full AI analytics;
- full accounting-system completeness;
- visual polish beyond clarity and non-overlap;
- migration of every old legacy finalization into a snapshot;
- perfect production automation while local CLI PHP is unavailable;
- P1 same-second cutoff hardening unless QA reproduces it in normal flow;
- broad redesign of all old screens.
- anything classified into `Advanced` because it is not needed for the first ordinary working loop.

## Required Gates Before MVP Complete

1. Product Finance Architect:
   - confirms labels and financial meaning for current period, final report, carryover, cash, card, accountable money, archive, proof.

2. Backend Data Engineer:
   - confirms current/historical data separation;
   - confirms card zero physical-cash effect;
   - confirms historical final report snapshot/export;
   - records any remaining P1 limitations.

3. Frontend UX Engineer:
   - wires or confirms user-facing current vs historical report actions;
   - confirms mobile/tablet/desktop clarity for MVP screens.

4. QA Release Engineer:
   - verifies the full MVP scenario on HTTP/API and main UI screens;
   - records evidence in the QA folder.

5. Chief Auditor:
   - confirms no unresolved P0 contradiction remains;
   - either approves MVP gate or records exact blockers.

## Stop Rule

After these gates pass, the MVP cycle stops.

No new feature enters MVP unless it fixes a P0 blocker.

Everything else goes to post-MVP backlog.

## Current MVP Status

Foundation status: approved by Chief Auditor on 2026-05-26.

Business MVP status: not complete yet.

Closed or passed:

- instant field capture slice;
- historical finalized report snapshot/export for new finalizations;
- current export combo regression after backend fix.
- Frontend/UX wiring for `Текущий период`, `Экспорт текущего периода`, `Закрытые финальные отчеты`, `Экспорт финального отчета`;
- QA pass on the user-facing current/historical report flow;
- Chief Auditor MVP gate.

Evidence:

- instant field capture QA run `20260526141856`;
- backend contract evidence `group_id=195`, `report_id=371`;
- UI flow evidence `group_id=200`, `report_id=406`;
- Chief Auditor files: `docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md`, `RISKS.md`, `TASKS_TO_OTHERS.md`.

Remaining before foundation gate:

- none.

Remaining before business MVP:

- prove participant/group money flows into one common group pot without losing physical cash/card separation;
- prove report submission, review, acceptance, immutable save, print/export, and archive as one user-facing flow;
- prove multiple participant reports can be consolidated into one group report, then saved, printed/exported, and archived;
- prove field combat mode: unfinished sessions survive navigation, refresh, phone lock/return, weak network, and proof upload retry;
- prove group messages still work and support report clarification;
- classify travel equalization and Business Desk as business MVP or `Advanced`;
- production deploy and production smoke for the selected MVP package.

Post-MVP P1:

- legacy finalizations without snapshot need a reproducible fixture for `historical_snapshot_missing`;
- same-second cutoff hardening needs deterministic finalization identity;
- downloaded export wording can be tightened if Product requires exact carryover phrasing.
