# Field Combat Mode

Date: 2026-05-26

Owner: Project Director

Status: CEO rule; role validation required.

## Meaning

Field combat mode is the mobile foundation of FinDesk business MVP.

The user is moving, distracted, possibly offline or in bad signal, and still needs to preserve the money fact immediately.

The product must feel simple:

```text
пишу -> фоткаю/сканирую -> система считает -> все сохранено -> потом сдаю отчет
```

## Non-Negotiable Rules

1. No data loss in an unfinished session.

   Current rows, amounts, comments, proof, photos, scans, selected group, participant, stream, and status must survive:

   - screen navigation;
   - accidental refresh;
   - phone lock and return;
   - browser/PWA pause;
   - weak network;
   - partial upload failure.

2. Capture before perfect accounting.

   User can save the fact before full categorization.

3. Proof is immediate.

   Photo, scan, or file proof is reachable from the capture moment, not buried later.

4. Calculation is automatic.

   The user writes facts. FinDesk recalculates cash, card, accountable money, current totals, and report totals.

5. Open session is always visible.

   If a report/card/session is not closed, user can return to it and continue.

6. Sync state is visible.

   The app must show whether data is saved, pending, failed, or needs retry.

7. Closing is a deliberate action.

   A working session does not become a final report silently.

8. Simplicity beats completeness at capture time.

   Advanced fields can wait. The first screen captures the money truth.

## MVP Field Actions

- write amount/note;
- choose received/cash/card/accountable;
- choose or inherit group;
- choose or inherit participant;
- take photo;
- scan/attach proof;
- save as draft automatically;
- submit when ready;
- recover unfinished session.

## Backend/Data Implications

Backend/Data must later map:

- what is already saved server-side per row/card/session;
- what is only client-side and can be lost;
- whether photo/proof upload has retry state;
- whether open session identity is stable;
- whether recalculation reads saved state after recovery.

## Frontend/UX Implications

Frontend/UX must later design:

- one-hand quick capture;
- persistent open-session indicator;
- saved/pending/failed sync labels;
- photo/scan action near the writing area;
- recovery banner for unfinished sessions;
- no dense table before save.

## QA Implications

QA must later test:

- write row -> refresh -> row remains;
- write row -> switch module -> return -> row remains;
- attach photo/proof -> interrupted upload -> retry visible;
- create draft offline or bad network -> pending state visible;
- submit only after deliberate action;
- recovered session totals match saved facts.

## Relation To Advanced

Field combat mode is MVP.

Everything not needed to preserve and submit the first working money truth can move to `Advanced`.
