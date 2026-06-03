# Project Director Handoff - FinDesk - 2026-06-03

## Start Point

Role taking over: Project Director FinDesk.

Project folder:

```text
/home/alexey/GitHub/finance.brkovic.ltd
```

GitHub:

```text
git@github.com:vetus-nauta/finance.brkovic.ltd.git
branch: main
```

Local start page:

```text
http://127.0.0.1:18889/app.php
```

Desktop shortcut:

```text
/home/alexey/Рабочий стол/Fin Desk.desktop
```

The old desktop shortcut `FinDesk AI Office.desktop` was removed. The new shortcut opens the local FinDesk start page, not the AI office dashboard.

WebStorm note:

- open the project as `/home/alexey/GitHub/finance.brkovic.ltd`;
- the database for `finance.brkovic.ltd` is already attached in WebStorm templates;
- for schema, tables, relations or SQL checks, start from that WebStorm database connection instead of rediscovering access;
- do not print or commit local DB credentials.

## Office

AI Team office:

```text
docs/AI_TEAM/
```

Office dashboard:

```text
docs/AI_TEAM/OFFICE_DASHBOARD.html
```

Universal start portal:

```text
docs/AI_TEAM/CHAT_START_PORTAL.html
```

Read first:

```text
docs/AI_TEAM/00_START_HERE.md
docs/AI_TEAM/PROJECT_DIRECTOR_HANDOFF_2026-06-03.md
docs/AI_TEAM/04_TASK_BOARD.md
docs/AI_TEAM/05_DECISIONS.md
docs/AI_TEAM/roles/*/STATUS.md
```

Current highest-level FinDesk product source:

```text
docs/AI_TEAM/63_PRODUCT_BIBLE_V1_INTAKE_2026-06-02.md
```

Yacht-only handoff:

```text
docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md
```

## Discipline

Do not start from scratch.

Before work, run:

```bash
git status --short
git status -sb
git rev-parse --short HEAD
git rev-parse --short origin/main
```

Then check the local app:

```bash
curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:18889/app.php
```

If a local smoke command mentions PHP CLI, remember: PHP CLI was not available in this shell during the 2026-06-03 handoff. Use local HTTP/API checks unless PHP CLI is installed.

Hard rules:

- never run `git reset --hard`;
- never run destructive `checkout`, `clean`, or reset commands unless the CEO explicitly orders it;
- do not commit secrets, `storage/`, backups, FTP credentials, or local DB credentials;
- do not change financial formulas without Product Finance Architect and Chief Auditor;
- do not change backend/API contracts without Backend Data Engineer;
- do not change UX flow without Frontend UX Engineer;
- do not mark production-ready without QA Release Engineer and Chief Auditor;
- role folders keep full reports; main chat receives short reports only.

## Role Routing

Use short technical cards for role chats.

Each task card must include:

- role;
- exact read paths;
- exact write paths;
- acceptance criteria;
- blocker rule;
- required short report format.

Role folders:

```text
docs/AI_TEAM/roles/01_product_finance_architect/
docs/AI_TEAM/roles/02_backend_data_engineer/
docs/AI_TEAM/roles/03_frontend_ux_engineer/
docs/AI_TEAM/roles/04_qa_release_engineer/
docs/AI_TEAM/roles/05_chief_auditor/
docs/AI_TEAM/roles/07_web_designer/
```

## Current Local State

The local repository contains a broad FinDesk Product Bible / Phase implementation and Yacht-template package.

Main local asset version after the latest Yacht scope correction:

```text
20260603-yacht-bunkering-inside1
```

Latest important local Yacht reports:

```text
docs/AI_TEAM/74_YACHT_TEMPLATE_MVP_LOCAL_2026-06-03.md
docs/AI_TEAM/75_YACHT_BUNKERING_ORDER_LOCAL_2026-06-03.md
docs/AI_TEAM/76_YACHT_PRICE_ZONES_LOCAL_2026-06-03.md
docs/AI_TEAM/77_YACHT_PRICE_ENGINE_LOCAL_2026-06-03.md
docs/AI_TEAM/78_OPENAI_YACHT_PRICE_REFRESH_LOCAL_2026-06-03.md
docs/AI_TEAM/79_YACHT_PROVISION_API_PACKAGE_APPLIED_2026-06-03.md
docs/AI_TEAM/80_OPENAI_KEY_TERMINAL_INSTALL_READY_2026-06-03.md
docs/AI_TEAM/81_OPENAI_KEY_SMOKE_PASS_2026-06-03.md
docs/AI_TEAM/82_YACHT_AI_PRICE_CYCLE_LOCAL_2026-06-03.md
docs/AI_TEAM/83_YACHT_FUEL_PRICE_CANDIDATE_LOCAL_2026-06-03.md
docs/AI_TEAM/84_YACHT_PRICE_APPROVAL_GATE_LOCAL_2026-06-03.md
docs/AI_TEAM/85_YACHT_APPROVED_PRICE_BRIDGE_LOCAL_2026-06-03.md
docs/AI_TEAM/87_YACHT_BUNKERING_SCOPE_CORRECTION_LOCAL_2026-06-03.md
docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md
```

OpenAI key state:

- key installed locally into `storage/secrets/openai_api_key`;
- key file is ignored by Git;
- do not print or expose the key;
- AI price workers default to dry-run and require `--run` for API calls.

Git hygiene:

- `storage/`, backups, zip archives and `test-results/` are local-only;
- `test-results/` was added to `.gitignore` during this handoff because it contains temporary audit screenshots and JSON.

## Production

Do not deploy unless explicitly instructed.

Correct production host:

```text
https://finance.brkovic.ltd/app.php
```

Correct production project:

```text
finance.brkovic.ltd
```

The current handoff is for GitHub/WebStorm synchronization and local continuity, not production deployment.

## Immediate Next Work

Within Yacht only, continue from:

```text
docs/AI_TEAM/88_YACHT_TEMPLATE_SECTION_HANDOFF_2026-06-03.md
```

Recommended next Yacht sprint:

1. browser visual QA of Yacht screen and print work order;
2. server-side durable Yacht order/archive model;
3. supplier/source registry for fuel and provisions;
4. explicit duty-free supplier quote path;
5. Yacht provisioning UI using `app/yacht_provisioning.php`;
6. real mobile QA.
