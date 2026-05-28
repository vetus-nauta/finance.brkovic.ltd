# AI Team Office: Start Here

Date: 2026-05-23
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
HEAD and origin/main are expected around 72b38e6.
The working tree contains important uncommitted changes.
Never reset, checkout, clean, or discard local changes unless the CEO explicitly orders it.
```

## Read First

Read in this order:

1. `docs/AI_TEAM/01_PRODUCT_COMPASS.md`
2. `docs/AI_TEAM/02_CURRENT_STATE.md`
3. `docs/AI_TEAM/03_WORKFLOW_RULES.md`
4. Your role file: `docs/AI_TEAM/roles/<your_role>/ROLE.md`
5. Your status file: `docs/AI_TEAM/roles/<your_role>/STATUS.md`
6. `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md`
7. `docs/USER_MESSAGES_DIGEST_2026-05-22.md`
8. `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`

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
