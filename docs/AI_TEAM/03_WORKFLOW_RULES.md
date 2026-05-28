# Workflow Rules

## General Rules

- Do not run destructive git commands.
- Do not reset, checkout, clean, or discard local changes.
- Do not change another role's area without recording the reason.
- Do not change financial formulas without Product Finance Architect approval and Chief Auditor visibility.
- Do not change database/API contracts without Backend Data Engineer ownership.
- Do not redesign user flow without Frontend UX Engineer ownership.
- Do not mark release-ready without QA Release Engineer verification and Chief Auditor approval.

## Production Access Rules

- CEO granted FTP and database access for all employee chats on 2026-05-26.
- Credentials are out-of-band secrets and must not be written into repository files, docs, screenshots, logs, task texts, or commits.
- Access can be used only for a documented role task and must stay inside role boundaries.
- View/read operations require the owning role to record what was checked.
- Edit/write operations require a task, acceptance criteria, and before/after evidence.
- Delete/drop/truncate operations are allowed only when explicitly assigned for a concrete target and after a backup or recovery path is documented.
- Backend Data Engineer owns database/API changes.
- Frontend UX Engineer owns deployed frontend asset changes.
- QA Release Engineer may verify production behavior and evidence.
- Chief Auditor may inspect production data for release risk, contradictions, and audit gate decisions.

## Required Status Updates

Every role must update:

```text
STATUS.md
FINDINGS.md
TASKS_TO_OTHERS.md
```

Chief Auditor additionally updates:

```text
MASTER_STATUS.md
RISKS.md
RELEASE_GATE.md
```

## Reporting Discipline

- Full role reports must stay inside the role's own folder under `docs/AI_TEAM/roles/<role>/`.
- The CEO / Project Director chat receives only a short report.
- Short reports must include only role name, task name, status, files updated, key ids/evidence pointers, and blocker or next owner.
- Short reports must follow `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`.
- Do not paste full logs, full checklists, screenshot lists, long API responses, long diffs, or unrelated reasoning into the CEO / Project Director chat.
- If the Director needs details, the Director reads the role folder.
- Each role must read its local `REPORTING_RULES.md` before the next task and treat it as mandatory for all future tasks.

## Director Task Cards

- For an existing role chat, Project Director gives the CEO only a short technical card.
- Full task text must be stored in the role folder before the card is sent.
- The card must state the exact role/chat, whether it is an existing chat or a new chat, where to read the assignment, where to write results, and what short report to return.
- The Director must not append personal commentary under task text that could be pasted into a role chat.
- Detailed rule: `docs/AI_TEAM/PROJECT_DIRECTOR_TASK_CARD_RULES.md`.

## Task Format

Use this structure for cross-role tasks:

```text
Date:
From role:
To role:
Priority: P0/P1/P2
Context:
Request:
Acceptance criteria:
Files/screens involved:
```

## Priority

```text
P0 = blocks financial correctness or data safety
P1 = blocks release quality or user workflow
P2 = polish, clarity, non-blocking improvement
```

## Release Rule

A release candidate is not ready if any of these are unclear:

- who holds cash;
- what was spent by cash;
- what was spent by card;
- what remains with administrator;
- what remains with employees;
- what is included in report;
- what is open or pending;
- what is archived;
- what can be reproduced from journal/export.
