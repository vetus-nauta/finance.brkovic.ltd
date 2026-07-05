# 33 — Director Agent Orchestration Protocol

## Purpose

The Director must not work alone.

Every sprint must be run through assigned agents.

## Director duty

At the start of each sprint, the Director must:

1. read the sprint file;
2. select the required agents;
3. write a task for each agent;
4. wait for each agent report;
5. compare reports against sprint exit criteria;
6. accept or reject the sprint;
7. write final handoff for the next Director.

## Mandatory sprint opening format

```text
Director Sprint Opening

Sprint:
Goal:
Required files read:
Agents assigned:
Agent tasks:
Expected reports:
Exit criteria:
Risks:
```

## Agent task format

```text
Agent:
Scope:
Files to read:
What to check:
What to change if allowed:
What not to touch:
Report required:
```

## Minimal agent groups

Reset / audit sprint:

```text
Director
Data and Backend Core Agent
QA, Audit, and Acceptance Agent
```

Financial logic sprint:

```text
Director
Financial Logic Engine Agent
Localization and Linguistic Rules Agent
Data and Backend Core Agent
QA, Audit, and Acceptance Agent
```

UX sprint:

```text
Director
iOS-Native UX Layout Agent
Frontend Performance and Interaction Agent
QA, Audit, and Acceptance Agent
Financial Logic Engine Agent as reviewer
```

Import sprint:

```text
Director
Legacy Import and Archive Agent
Financial Logic Engine Agent
Data and Backend Core Agent
QA, Audit, and Acceptance Agent
```

Security / deploy sprint:

```text
Director
Data and Backend Core Agent
QA, Audit, and Acceptance Agent
Security and Privacy Agent if available
```

## Sprint cannot close without reports

A sprint cannot be marked complete unless every assigned agent reports.

If an agent report is missing, the sprint status is blocked.

## Director final report format

```text
Director Final Handoff

Sprint:
Status:
Agents assigned:
Agent reports received:
Accepted work:
Rejected work:
Files changed:
Tests or checks:
Risks:
Next sprint:
Paste-to-next-director prompt:
```

## Rule

If a Director does not assign agents, the Director has failed the sprint opening.
