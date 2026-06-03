# FinDesk Product Bible V1 Intake — 2026-06-02

Source package: Google Drive file `findesk_product_bible_full_v1.zip`

Included files reviewed:
- `00_FIN_DESK_PRODUCT_BIBLE_V1_FULL.md`
- `README_CODEX.md`
- `PRODUCT_BIBLE_ALIGNMENT_CHECKLIST.md`

## Status

Product Bible V1 is now treated as the highest-level FinDesk product document.

It stands above:
- Phase 1 reports;
- Phase 2 reports;
- Phase 3 reports;
- QA notes;
- audit notes;
- handoff notes;
- temporary implementation decisions.

If code, UI, QA, or a local report contradicts Product Bible V1, the implementation must be corrected unless the product owner explicitly changes the product direction.

## Core Direction

FinDesk is a modern shared money journal.

The product must keep one simple habit at the center:

```text
Потратил — запиши.
Получил — запиши.
```

The key user question:

```text
Сколько денег у меня реально есть сейчас и где они находятся?
```

## Required Product Structure

Approved high-level flow:

```text
Welcome Hall
  -> Work alone / Work with people / Templates
  -> Solo Workspace or Team Workspace
  -> Cash / Card choice
  -> Live Journal
  -> Fixed Journal
  -> Report Assembly
  -> Reports Archive
```

Team Workspace must be a people screen, not a finance dashboard.

Live Journal must be the primary operational surface, not a report screen, menu screen, or archive screen.

Cash and Card / Non-Cash must remain separate until final report composition.

## Mandatory Lifecycle Rules

Transfer flow:

```text
Admin issues money
  -> Pending Transfer
  -> Employee confirms
  -> Money becomes active
```

Rules:
- employee journal is blocked while transfer is pending;
- pending transfer can be edited or cancelled by admin;
- issue, edit, cancellation, and confirmation must be logged.

Journal flow:

```text
Live Journal
  -> Зафиксировать журнал
  -> Fixed Journal
  -> Ready Report
  -> Admin review
  -> Attached to Report
  -> Saved Report
```

Protected actions require:
- consequences preview;
- reason;
- exact `CONFIRM`;
- log entry.

## UI / UX Guardrails

Do not revive old FinDesk interface patterns.

Do not add:
- old dashboard blocks;
- old all-in-one menu;
- Nav Desk / Ops ecosystem links;
- reports inside Live Journal;
- Cash/Card mixing;
- accounting or ERP language;
- card-in-card-in-card layouts;
- decorative fintech styling.

Use:
- compact application shell;
- real navigation stack;
- records-first Live Journal;
- bottom working input area;
- clear people-first Team Workspace;
- human Russian labels;
- operational luxury minimalism.

## Phase Meaning

Phase 1:
- functional rebuild;
- Live Journal cleanup;
- Team Workspace as People Screen;
- Admin Card;
- Employee Card;
- Transfer Offer;
- Cash/Card separation;
- Report Assembly.

Phase 2:
- navigation shell;
- localization policy;
- profile/account menu;
- protected actions;
- engine audit;
- QA gate;
- clean application boundaries.

Phase 3:
- product completion;
- real UX;
- visual system;
- mobile-first pass;
- physical QA;
- old interface removal.

Phase 3 is not done while old FinDesk is still visually recognizable.

## Alignment Checklist Imported

Before accepting any phase:

- [ ] Welcome Hall follows Product Bible.
- [ ] Auth returns user to interrupted action.
- [ ] Team Workspace is People Screen.
- [ ] Admin Card follows approved structure.
- [ ] Employee Card shows Name, Position, Issued, Remaining.
- [ ] Live Journal is records-first.
- [ ] Input uses `± amount and note`.
- [ ] Cash/Card choice exists before Live Journal.
- [ ] Cash and Card remain separate.
- [ ] Card warning appears only on manual non-zero card balance.
- [ ] Transfer Offer blocks journal until confirmation.
- [ ] Employee loses control of fixed report after fixation.
- [ ] Admin controls review/attach/return after fixation.
- [ ] Final reports contain Cash, Card, Total.
- [ ] Protected actions require consequences, reason and `CONFIRM`.
- [ ] Back navigation uses stack.
- [ ] Menu does not include Nav Desk / Ops / unrelated ecosystem links.
- [ ] Localization uses shared brkovic.ltd infrastructure if available.
- [ ] Old UI remnants are removed.
- [ ] Phase 3 produces product, not just engineering scaffold.

## Director Conclusion

Current FinDesk work must be judged against Product Bible V1, not against the partially rebuilt Phase 1/2 interface.

The next implementation pass must not patch the old UI. It must expose the approved product structure:

```text
Welcome Hall
Solo Workspace
Cash/Card Choice
Live Journal
Team Workspace
Admin Card
Employee Card
Report Assembly
Reports
Protected Actions
Profile / Account
```

No production release should be accepted before functional QA, engine audit, UX QA, mobile QA, visual QA, report/export QA, backup/rollback check, and production release audit.
