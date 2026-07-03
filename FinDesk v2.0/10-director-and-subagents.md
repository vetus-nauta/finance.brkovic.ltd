# 10 — Director and Subagents Operating Model

## Purpose

FinDesk v2.0 must be built by separated roles, not by one mixed agent doing everything.

The goal is a very light, simple, iOS-oriented financial web app where all functional information fits inside one screen.

## Director rule

The Director does not write code.

The Director coordinates subagents, reads reports, resolves conflicts, protects product logic, checks the MVP remains light, prevents scope creep, and accepts or rejects output.

The Director must not write implementation code, patch components directly, create database migrations directly, or invent UI outside the layout contract.

## Required subagents

Minimum recommended team: seven subagents.

1. Financial Logic Engine Agent.
2. Data and Backend Core Agent.
3. iOS-Native UX Layout Agent.
4. Frontend Performance and Interaction Agent.
5. Localization and Linguistic Rules Agent.
6. Legacy Import and Archive Agent.
7. QA, Audit, and Acceptance Agent.

## Optional later subagent

Security and Privacy Agent, required before real user data.

## Reporting format

Each subagent report must use:

```text
Subagent:
Scope:
What was checked:
Findings:
Decisions needed:
Risks:
Recommended next action:
Files touched:
```

## Director weekly report format

```text
FinDesk v2.0 Director Report

1. Current objective
2. What changed
3. What is blocked
4. Subagent reports summary
5. Product risks
6. Simplicity risks
7. iOS/native-feel risks
8. Next approved tasks
9. Tasks explicitly rejected or postponed
```

## iOS-native feel requirements

- calm layout;
- small but readable text;
- no decorative dashboard cards;
- no empty form state;
- current month feed always visible;
- input always reachable;
- keyboard-safe on iPhone;
- dense but not cramped;
- native-feeling drawers/sheets;
- no web-page scrolling.

## Final Director acceptance gate

A build is not acceptable unless:

1. The operational journal is source of truth.
2. Summary is generated.
3. Cash/Card funding-flow logic matches approved model.
4. Commercial income is an income category.
5. No-sign rows are visible but not counted.
6. Current month feed is visible during entry.
7. The app fits the one-screen layout contract.
8. iPhone portrait and landscape are usable.
9. iPad portrait and landscape are usable.
10. The implementation did not revive old FinDesk complexity.
