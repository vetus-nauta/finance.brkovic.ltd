# Role Reporting Rules

Effective date: 2026-05-26

## Mandatory Rule

Every role chat must keep the full work report inside its own role folder.

The CEO / Project Director chat receives only a short report.

## Full Report Location

Each role writes detailed work into:

```text
docs/AI_TEAM/roles/<role>/STATUS.md
docs/AI_TEAM/roles/<role>/FINDINGS.md
docs/AI_TEAM/roles/<role>/TASKS_TO_OTHERS.md
```

Chief Auditor also writes into:

```text
docs/AI_TEAM/roles/05_chief_auditor/MASTER_STATUS.md
docs/AI_TEAM/roles/05_chief_auditor/RISKS.md
docs/AI_TEAM/roles/05_chief_auditor/RELEASE_GATE.md
```

## Short Report To Chief Chat

The role chat reports back to the CEO / Project Director chat only:

- role name;
- task name;
- status: `PASS`, `BLOCKED`, `WAITING`, or `DONE`;
- files updated;
- key ids/evidence pointers;
- blocker or next owner, if any.

Use the exact template:

```text
docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md
```

Maximum short report length: 10 lines plus file paths.

## Do Not Put In Chief Chat

- full logs;
- full checklists;
- screenshot lists;
- long API responses;
- long diffs;
- long reasoning;
- credentials or secrets;
- unrelated commentary.

If the Director needs details, the Director reads the role folder.

## Required Closing Line

Every role task must end by updating its own files first, then sending the short report.

If a role needs to mention screenshots or detailed evidence, it writes only:

```text
Details/screenshots: see FINDINGS.md
```
