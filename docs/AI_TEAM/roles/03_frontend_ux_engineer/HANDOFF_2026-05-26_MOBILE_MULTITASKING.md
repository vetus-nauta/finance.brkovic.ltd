# Handoff: Mobile Multitasking UX

Date: 2026-05-26

From role: Project Director

To role: Frontend UX Engineer

Priority: P0 after Product Finance Architect validates business-MVP scope

## Context

CEO emphasized that mobile convenience is critical. FinDesk must handle a multitasking finance workflow on small screens:

- fast capture;
- report review;
- group money;
- group messages;
- save / print / export;
- archive;
- Business Desk if Product keeps it in MVP minimum;
- Travel / Trip with Friends if Product keeps it in MVP minimum;
- Advanced.

Do not remove older product modules to make the UI easier. Preserve them and stage them properly.

CEO clarified that `Advanced` means everything outside business MVP.

CEO also clarified that field combat mode is foundational: write, photo, scan/proof, automatic calculation, continuous save, and no data loss in an unfinished session.

## Read First

- `docs/AI_TEAM/11_MOBILE_MULTITASKING_RESEARCH.md`
- `docs/AI_TEAM/12_FIELD_COMBAT_MODE.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/06_CEO_IDEAS_REGISTRY.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/REPORTING_RULES.md`

## Task

After Product Finance Architect finishes the business-MVP classification, create a phone/tablet/desktop UX map.

Define:

1. Five or fewer permanent phone entrances.
2. Where `Archive`, `Advanced`, `Settings`, and any MVP-approved Business/Travel surfaces live.
3. Which actions become sticky action strips.
4. Which details become bottom sheets.
5. Which desktop two-pane screens become phone list-detail flows.
6. How messages attach to report review and group work.
7. How print/export is reached from saved reports and group reports.
8. How unfinished field sessions survive navigation, refresh, phone lock/return, and weak network.
9. How `Advanced` contains non-MVP work without hiding or deleting it.

## Output

Update:

- `docs/AI_TEAM/roles/03_frontend_ux_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/STATUS.md`
- `docs/AI_TEAM/roles/03_frontend_ux_engineer/TASKS_TO_OTHERS.md`

## Acceptance Criteria

- Phone flow does not require desktop-like dense tables.
- User can capture and submit without losing the current card.
- User can find messages, archive, Advanced, and any MVP-approved Business/Travel surfaces.
- Product modules are staged, not deleted.
- Report save/print/export remains reachable from the report itself.
- Open-session data loss is treated as a UX blocker.
- Short report only is sent to the Project Director chat.
