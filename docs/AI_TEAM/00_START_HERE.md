# AI Team Office: Start Here

Date: 2026-06-02
Project: `finance.brkovic.ltd / FinDesk`
Local path: `/home/alexey/GitHub/finance.brkovic.ltd`

## Mission

This folder is the working office for specialist AI chats. Each chat acts as one professional role, works inside strict boundaries, records its findings, and leaves tasks for other roles.

The office exists because FinDesk must be prepared for release as a finance control product, not as a collection of patched screens.

## Local Dashboard

Open the six-cabinet dashboard:

```text
docs/AI_TEAM/OFFICE_DASHBOARD.html
```

It contains the tech deputy cabinet plus specialist role cabinets (including Web Designer), links to documents, and copy-ready starter prompts for new chats.

Latest Project Director start handoff:

```text
docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-02.md
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
php scripts/local-smoke.php http://127.0.0.1:18889
```

Important baseline:

```text
Last known synced base before the 2026-06-02 handoff was 459c751.
Newer commits may exist after the GitHub sync; check HEAD and origin/main before work.
The working tree can contain important local changes.
Never reset, checkout, clean, or discard local changes unless the CEO explicitly orders it.
```

## Read First

Read in this order:

1. `docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-02.md`
2. `docs/AI_TEAM/01_PRODUCT_COMPASS.md`
3. `docs/AI_TEAM/02_CURRENT_STATE.md`
4. `docs/AI_TEAM/03_WORKFLOW_RULES.md`
5. `docs/AI_TEAM/04_TASK_BOARD.md`
6. `docs/AI_TEAM/05_DECISIONS.md`
7. Your role file: `docs/AI_TEAM/roles/<your_role>/ROLE.md`
8. Your status file: `docs/AI_TEAM/roles/<your_role>/STATUS.md`
9. `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md`
10. `docs/USER_MESSAGES_DIGEST_2026-05-22.md`
11. `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`

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
