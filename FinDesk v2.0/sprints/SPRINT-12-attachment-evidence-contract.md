# Sprint 12 - Attachment Evidence Contract

## Goal

Close attachments as evidence linked to entries without changing the financial source of truth.

## Depends on

- Sprint 11 handoff
- `16-api-contract.md`
- `18-security-privacy-notes.md`
- `20-definition-of-done.md`

## Director rule

Attachments support audit and review. They never replace the journal entry or generated money state.

## Agents

- Director
- Backend Core Agent
- Security/Privacy Agent
- QA/Audit Agent

## Scope

- attachment create/list/delete contract;
- entry linkage;
- file metadata requirements;
- privacy and storage boundary;
- audit record for attachment changes;
- non-visual evidence rules.

## Visible-change bypass

Any preview gallery, drawer, thumbnail, or upload UI work is deferred. This sprint records API/data evidence rules only.

## Forbidden

- no financial calculations from attachment OCR;
- no public exposure of private receipts;
- no storage secrets committed;
- no visual upload flow as a completion condition.

## Exit criteria

- attachment evidence boundaries are clear;
- entry linkage is mandatory;
- privacy risks are documented;
- UI preview work is postponed separately.

## Final handoff

Pass evidence contract, privacy risks, and attachment audit requirements to Sprint 13.
