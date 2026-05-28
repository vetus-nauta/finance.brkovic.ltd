# Handoff: Business MVP Residual Surface QA

Date: 2026-05-27

From: Project Director

To: QA Release Engineer

Priority: P0

## Read First

- `docs/AI_TEAM/13_BUSINESS_MVP_FINAL_READINESS_REVIEW.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/roles/05_chief_auditor/CLOSED_GROUP_REPORT_PACKAGE_GATE_2026-05-27.md`
- `docs/AI_TEAM/roles/05_chief_auditor/FIELD_COMBAT_NO_DATA_LOSS_GATE_2026-05-26.md`
- `docs/AI_TEAM/SHORT_REPORT_TEMPLATE.md`

## Context

The core money loop is materially proven for new data:

```text
field capture -> review/acceptance -> final report -> closed group package -> archive/open/print/proof
```

Remaining P0 before final full business-MVP gate is residual product surface QA. This is not a request for new code unless QA finds a real blocker.

## Task

Verify that the non-core but required business-MVP surface is still reachable and not conflicting with the proven money loop.

## Required Checks

Run browser/HTTP QA on mobile `390x844`, tablet `820x1180`, and desktop `1440x900`.

Check:

- Group messages:
  - group message send/list/unread/mark-read flow is reachable;
  - messages are group-scoped;
  - report-context message references in `Закрытый групповой отчет` remain understandable as report refs or clearly marked unlinked group discussion.
- Business Desk / proforma:
  - Business Desk is reachable;
  - company/client/proforma surface is preserved;
  - proforma create/list/open/print path works or exact blocker is recorded;
  - Business Desk stays separate from operational cash/report formulas.
- Travel / Trip with Friends:
  - Travel/Trip marker is visible or clearly staged;
  - it does not mix into the ordinary business cash report;
  - if not launch-ready, it is clearly `Advanced` / post-MVP staging, not deleted.
- Advanced:
  - Advanced remains reachable as non-MVP staging;
  - it does not hide required MVP actions.
- Final mobile navigation reachability:
  - user can reach On the Go / field capture;
  - user can reach report review/finalization area;
  - user can reach `Закрытый групповой отчет`;
  - user can reach group messages;
  - user can reach Business Desk/proforma and Travel/Advanced staging;
  - no blocking overlap or unreachable primary action on mobile/tablet/desktop.

## Acceptance Decision

PASS if:

- required residual surfaces are reachable;
- they do not mutate or confuse the money-core loop;
- non-MVP items are visibly staged rather than deleted;
- mobile/tablet/desktop do not block access.

BLOCKED if:

- group messages are unusable or not group-scoped;
- Business Desk/proforma path is broken or mixed into money formulas;
- Travel/Advanced has disappeared without staging;
- mobile navigation cannot reach the proven money loop or required residual surfaces;
- any residual surface creates a financial contradiction with cash/card/accountable/report package behavior.

## Output

Update:

- `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/STATUS.md`
- `docs/AI_TEAM/roles/04_qa_release_engineer/TASKS_TO_OTHERS.md`

## Report Back

Use one short report only:

Role: QA/Release
Task: Business MVP residual surface QA
Status: PASS / BLOCKED
Evidence pointer: `docs/AI_TEAM/roles/04_qa_release_engineer/FINDINGS.md`
Blocker: ...
Next owner: Project Director / Frontend UX Engineer / Backend Data Engineer
