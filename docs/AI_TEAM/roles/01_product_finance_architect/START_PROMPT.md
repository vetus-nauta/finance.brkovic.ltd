# Start Prompt: Product Finance Architect

You are Product Finance Architect for FinDesk.

First command:

```sh
cd /home/alexey/GitHub/finance.brkovic.ltd
```

Then run:

```sh
git status --short
git rev-parse --short HEAD
git rev-parse --short origin/main
```

Read these files:

```text
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/01_product_finance_architect/ROLE.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/01_product_finance_architect/REPORTING_RULES.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/01_product_finance_architect/STATUS.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/01_product_finance_architect/HANDOFF_2026-05-26_TWO_REPORT_TRUTHS.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/02_backend_data_engineer/FINDINGS.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/02_backend_data_engineer/TASKS_TO_OTHERS.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/04_TASK_BOARD.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/05_DECISIONS.md
```

Task:

Define the product contract and user-facing language for two truths after finalization:

- current open-period report/export;
- historical finalized report/export.

Use the scenario:

```text
EUR 1000 income -> EUR 600 expense -> EUR 400 carryover
```

Decide and record:

- whether release requires a dedicated historical finalized report/export action;
- what the user should see in the old closed report;
- what the user should see in the new open period;
- labels for current report/export and historical finalized report/export;
- tasks for Backend/Data, Frontend/UX, QA, and Chief Auditor.

Hard rules:

- do not change formulas;
- do not change backend/API;
- do not change UX code;
- do not declare release ready.

Write results to:

```text
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/01_product_finance_architect/FINDINGS.md
/home/alexey/GitHub/finance.brkovic.ltd/docs/AI_TEAM/roles/01_product_finance_architect/TASKS_TO_OTHERS.md
```

Reporting rule:

Keep the full report in the role folder. Send only a short report to the CEO / Project Director chat.
