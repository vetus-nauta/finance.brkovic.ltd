# Project Director Handoff: FinDesk

Date: 2026-05-26
Project: `finance.brkovic.ltd / FinDesk`
Local path: `/home/alexey/GitHub/finance.brkovic.ltd`
Role receiving project: `Директор проекта`

## Executive Context

You are receiving FinDesk as a project director, not as a coder. Your job is to keep the product moving without letting the work collapse back into one overloaded chat or one screen-level fix at a time.

FinDesk is a finance control product. Its main value is operational clarity:

```text
Who received money?
Who physically holds cash?
What was spent by cash?
What was spent by card?
What remains with administrator?
What remains with each employee?
What is checked?
What is included in final report?
What is archived?
Where is the proof?
```

The CEO's central requirement:

```text
The system must preserve the whole financial tree, not merely fix a number on the screen.
```

## Repository State

Known baseline from earlier office setup:

```text
HEAD:        72b38e6
origin/main: 72b38e6
Working tree: dirty, with important uncommitted changes
```

Strict rule:

```text
Never run git reset --hard
Never run git checkout -- .
Never run git clean -fd
Never discard local changes
```

Before any work session:

```bash
git status --short
git rev-parse --short HEAD
git rev-parse --short origin/main
php scripts/local-smoke.php http://127.0.0.1:18889
```

If the smoke test cannot connect, start the local server:

```bash
php -S 127.0.0.1:18889 -t public
```

or use:

```bash
scripts/start-local.sh
```

## Product Idea

FinDesk is not one flat ledger. It is a layered finance workflow:

```text
Живые отчеты
  fast mobile capture by administrator or employee

Проверка отчетов / FinDesk
  moderation, return, acceptance, inclusion into working package

Деньги / Подотчеты
  physical cash, accountable money, issued amounts, employee responsibility

Сводка отчета
  final report, Excel/Google export, fixation

Архив
  cleanup/history, not money mutation

Журнал учета
  recovery/audit trail, not a second operational ledger

AI / Analytics
  audit, checks, expense analysis, risk flags
```

## Financial Architecture

The money tree:

```text
Money source
  -> administrator physical cash
    -> administrator live cash report
    -> accountable money issued to employees
      -> employee cash pocket
        -> employee expenses
        -> employee remaining cash
        -> return or carry forward
  -> FinDesk review
  -> final report snapshot/export
  -> archive cleanup
  -> journal/audit trail
```

Main rules:

- issuing money to an employee is not an expense;
- employee remaining cash remains accountable money until returned or carried forward;
- administrator participates in live reports too;
- final report fixation preserves history and starts the next open period from carryover;
- archive does not change money;
- journal records/reconstructs history, it is not the primary action screen.

## Cash/Card Architecture

`Живой отчет` has two parallel streams:

```text
cash -> Наличные
card -> Банковская карта
```

Cash:

- physical money on hand;
- has base/carryover;
- affects `cash_left`;
- can represent administrator or employee cash.

Card:

- starts from zero;
- has only card/noncash expenses;
- does not have `cash_received`;
- does not affect `cash_left`;
- does not affect `available_cash_balance`;
- appears in final report as card spending only.

Project director must protect this rule from being blurred again.

## Current Product Direction

The current direction is not to add more features first. The direction is to cleanly prepare the product for release:

1. stabilize the financial model;
2. split screens by responsibility;
3. verify cash/card/advance/report/archive/export flows;
4. make mobile/tablet screens compact;
5. make Excel/Google export business-readable;
6. keep AI/analytics as separate advanced tools, not clutter inside operations.

## Office Structure

The AI office lives here:

```text
docs/AI_TEAM/
```

Important files:

```text
00_START_HERE.md
01_PRODUCT_COMPASS.md
02_CURRENT_STATE.md
03_WORKFLOW_RULES.md
04_TASK_BOARD.md
05_DECISIONS.md
CHAT_LINKS.md
OFFICE_DASHBOARD.html
CEO_TECHNICAL_BRIEF.md
PROJECT_DIRECTOR_HANDOFF_2026-05-26.md
PROJECT_DIRECTOR_START_PROMPT.md
```

There is also a desktop shortcut:

```text
/home/alexey/Рабочий стол/FinDesk AI Office.desktop
```

## Required Roles To Separate

The project director must keep these roles separate. Do not let one role silently do another role's job.

### 1. Tech Deputy / Coordinator

Current coordinating chat.

Owns:

- office structure;
- handoffs;
- task routing;
- decisions log;
- keeping work from becoming chaos.

Does not own:

- final financial formula approval;
- release gate approval;
- hidden mass coding without role routing.

### 2. Product Finance Architect

Owns:

- financial meaning;
- terms;
- formulas;
- cash/card/accountable-money rules;
- report state meaning;
- expected numbers for scenarios.

Must not:

- rewrite frontend/backend alone;
- approve release alone.

First mission:

- define final glossary and expected output for open period vs historical report.

### 3. Backend Data Engineer

Owns:

- PHP/API/data model;
- migrations;
- endpoint behavior;
- report/export snapshots;
- smoke tests for finance flows.

Must not:

- redesign UI;
- rename financial concepts alone;
- delete history to make screens look simpler.

First mission:

- verify final report fixation and carryover separation.

### 4. Frontend UX Engineer

Owns:

- screen structure;
- mobile/tablet/desktop layout;
- menu and page balance;
- readable tables;
- user-facing clarity.

Must not:

- change formulas;
- change backend state machine;
- invent accounting terms.

First mission:

- rebalance pages into small, clear operational screens.

### 5. QA Release Engineer

Owns:

- smoke tests;
- manual scenario verification;
- desktop/tablet/mobile checks;
- export readability checks;
- release evidence.

Must not:

- rewrite product rules;
- approve finance logic alone;
- dismiss confusing but technically correct output.

First mission:

- formalize and run the `€1000 -> €600 -> €400 carryover` scenario.

### 6. Chief Auditor

Owns:

- contradictions across roles;
- risk register;
- release gate;
- CEO-level truth.

Must not:

- become hidden coder;
- approve release without QA/backend/frontend/product evidence.

First mission:

- review all role outputs and keep P0/P1 risks visible.

## Working Order For The Project Director

### Step 1: Establish Control

Read:

```text
docs/AI_TEAM/00_START_HERE.md
docs/AI_TEAM/01_PRODUCT_COMPASS.md
docs/AI_TEAM/02_CURRENT_STATE.md
docs/AI_TEAM/03_WORKFLOW_RULES.md
docs/AI_TEAM/04_TASK_BOARD.md
docs/AI_TEAM/CHAT_LINKS.md
```

Then check each role cabinet:

```text
docs/AI_TEAM/roles/*/ROLE.md
docs/AI_TEAM/roles/*/STATUS.md
docs/AI_TEAM/roles/*/FINDINGS.md
docs/AI_TEAM/roles/*/TASKS_TO_OTHERS.md
```

### Step 2: Create Or Attach Chats

Ask the CEO to create specialist chats or use existing ones.

For each chat:

1. copy prompt from `OFFICE_DASHBOARD.html`;
2. paste it into the new chat;
3. record chat name/link/id in `CHAT_LINKS.md`;
4. record it in the role's `CHAT.md`.

### Step 3: Run First Review Cycle

Order:

1. Product Finance Architect defines expected numbers and terms.
2. Backend Data Engineer verifies data/endpoints against those terms.
3. Frontend UX Engineer adjusts screens and labels only after meaning is clear.
4. QA Release Engineer tests scenarios and device layouts.
5. Chief Auditor reviews contradictions and release gate.

### Step 4: Prevent Scope Drift

Every task must answer:

```text
Which role owns this?
What document records it?
What acceptance criteria prove it?
Does it change money, UI, data, QA, or release gate?
```

If unclear, route it to Chief Auditor before implementation.

## Current High-Risk Areas

P0:

- old historical income appearing as current open-period income;
- cash/card separation being broken;
- personal/group scope showing zeros or wrong totals;
- report fixation changing history instead of creating carryover.

P1:

- FinDesk and Advanced responsibilities duplicating;
- mobile screens overloaded;
- archive mistaken for accounting action;
- Excel/Google export technically correct but not business-readable;
- AI/analytics mixed into operational screens too early.

P2:

- branding polish incomplete across all surfaces;
- dashboard links not yet attached to real VS Code/Codex chats.

## Acceptance Criteria For Release Preparation

Release preparation is not complete until:

- cash report works and carries remainder;
- card report works and never changes cash;
- employee advance works from issue to spend to submit to accept/return;
- administrator live report is included in summaries;
- final report fixation preserves historical report and starts open carryover;
- report/export shows who had money, what changed, and what remains;
- archive is only cleanup/history;
- journal is understandable as audit/recovery trail;
- mobile/tablet/desktop screens are checked;
- Chief Auditor clears P0/P1 risks.

## Director's First Task List

1. Confirm all role chats exist or create them.
2. Fill `CHAT_LINKS.md`.
3. Assign Product Finance Architect first glossary task.
4. Assign Backend Data Engineer first carryover verification task.
5. Assign QA Release Engineer first scenario test plan.
6. Ask Chief Auditor to open the first risk review.
7. Keep Frontend UX Engineer from changing screens until financial terms are stable.

## Final Instruction

Do not manage this project by screen complaints alone. Manage it by the financial tree, role boundaries, and release evidence.
