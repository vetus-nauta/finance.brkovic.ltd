# AI Team Office: Start Here

Date: 2026-06-03
Project: `finance.brkovic.ltd / FinDesk`
Local path: `/home/alexey/GitHub/finance.brkovic.ltd`

## Mission

This folder is the working office for specialist AI chats. Each chat acts as one professional role, works inside strict boundaries, records its findings, and leaves tasks for other roles.

The office exists because FinDesk must be prepared for release as a finance control product, not as a collection of patched screens.

## Current Beacon

Highest-level product source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Product Bible V1 now stands above Phase 1, Phase 2, Phase 3, QA, audit and handoff documents. Do not implement features, navigation, wording, or UI patterns that contradict it unless the CEO explicitly changes the product direction.

Current Phase 2 source:

```text
docs/AI_TEAM/59_PHASE2_LOGIC_NAV_ENGINE_AUDIT_2026-06-02.md
```

Phase 2 is the current working gate. It turns the approved FinDesk idea into clear logic, navigation, backend state and QA checks before implementation.

Previous Phase 1 product reset source:

```text
docs/AI_TEAM/51_PHASE1_FUNCTIONAL_BLUEPRINT_MANDATE_2026-06-02.md
```

Phase 1 remains the conceptual base. Phase 2 is now the active execution gate.

Meaning:

- do not continue patching the rejected mixed FinDesk screen;
- do not start with visual redesign;
- first expose the approved product hierarchy and first-class state;
- preserve auth, backend, database, PWA, and attachment foundations unless an audited additive patch is approved.

## Local Dashboard

Open the six-cabinet dashboard:

```text
docs/AI_TEAM/OFFICE_DASHBOARD.html
```

Universal new-chat launch page:

```text
docs/AI_TEAM/CHAT_START_PORTAL.html
```

It contains the tech deputy cabinet plus specialist role cabinets (including Web Designer), links to documents, and copy-ready starter prompts for new chats.

Latest Project Director start handoff:

```text
docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-09.md
```

Yacht section handoff:

```text
docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md
```

Local FinDesk start page:

```text
http://127.0.0.1:18902/app.php?build=routes44
```

Desktop shortcut:

```text
/home/alexey/Рабочий стол/Fin Desk.desktop
```

WebStorm database note:

```text
The database for finance.brkovic.ltd is already attached in the WebStorm templates.
Use that attached connection for schema/table/SQL checks before rediscovering DB access.
```

Real VS Code/Codex chat anchors live in:

```text
docs/AI_TEAM/CHAT_LINKS.md
docs/AI_TEAM/TECH_DEPUTY_CURRENT_CHAT.md
docs/AI_TEAM/PROJECT_DIRECTOR_CHAT.md
docs/AI_TEAM/roles/<role>/CHAT.md
```

## First Commands For Every Chat

Run before work:

```bash
git status --short
git rev-parse --short HEAD
git rev-parse --short origin/main
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:18902/app.php?build=routes44
```

Important baseline:

```text
Current synced base at the 2026-06-09 handoff is e233594 before this handoff commit.
Newer commits may exist after the GitHub sync; check HEAD and origin/main before work.
The working tree can contain important local changes.
Never reset, checkout, clean, or discard local changes unless the CEO explicitly orders it.
```

## Read First

Read in this order:

1. `docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md`
2. `docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-09.md`
3. `docs/AI_TEAM/59_PHASE2_LOGIC_NAV_ENGINE_AUDIT_2026-06-02.md`
4. `docs/AI_TEAM/61_PHASE3_PRODUCT_IDENTITY_UX_VALIDATION_2026-06-02.md`
5. `docs/AI_TEAM/51_PHASE1_FUNCTIONAL_BLUEPRINT_MANDATE_2026-06-02.md`
6. `docs/AI_TEAM/01_PRODUCT_COMPASS.md`
7. `docs/AI_TEAM/02_CURRENT_STATE.md`
8. `docs/AI_TEAM/03_WORKFLOW_RULES.md`
9. `docs/AI_TEAM/04_TASK_BOARD.md`
10. `docs/AI_TEAM/05_DECISIONS.md`
11. `docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md` if the task touches Yacht.
12. Your role file: `docs/AI_TEAM/roles/<your_role>/ROLE.md`
13. Your status file: `docs/AI_TEAM/roles/<your_role>/STATUS.md`
14. `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md`
15. `docs/USER_MESSAGES_DIGEST_2026-05-22.md`
16. `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`

## Roles

1. Product Finance Architect
2. Backend Data Engineer
3. Frontend UX Engineer
4. QA Release Engineer
5. Chief Auditor
6. Web Designer

## Rule Of Conduct

Each chat must behave like a hired specialist:

- stay inside its role;
- do not redesign another role's area alone;
- write findings in its own cabinet;
- write requests to other roles in `TASKS_TO_OTHERS.md`;
- update `STATUS.md` before ending work;
- escalate contradictions to the Chief Auditor.
