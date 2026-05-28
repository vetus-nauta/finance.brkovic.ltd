# Project Director Task Card Rules

Effective date: 2026-05-26

## Rule

Project Director must not paste long prompts into the CEO / Project Director chat when assigning work to an existing role chat.

The Director must create or update the full assignment inside the role folder, then give the CEO a short technical task card.

## Full Assignment Location

Full assignments live in:

```text
docs/AI_TEAM/roles/<role>/STATUS.md
docs/AI_TEAM/roles/<role>/HANDOFF_*.md
docs/AI_TEAM/roles/<role>/START_PROMPT.md
docs/AI_TEAM/roles/<role>/REPORTING_RULES.md
```

## Short Technical Card Format

Use this format in the CEO / Project Director chat:

```text
Кому: <Role / chat name>
Статус чата: existing chat / new chat
Задача: <one sentence>
Читать задание:
- <absolute path to role handoff>
- <absolute path to role status or start prompt>
Писать результат:
- <absolute path to FINDINGS.md or role output files>
- <absolute path to TASKS_TO_OTHERS.md>
Короткий рапорт обратно: use docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md.
```

## What Not To Include

Do not include:

- long task body;
- long reasoning;
- full checklist if it already exists in the handoff;
- screenshot lists;
- personal director commentary after the task;
- credentials or secrets.

## When A Full Prompt Is Allowed

A full role prompt is allowed only when:

- the role chat is new;
- the old chat has lost context;
- the CEO explicitly asks for a full intro.

Even then, the full prompt must point to role files and require short reporting back.
