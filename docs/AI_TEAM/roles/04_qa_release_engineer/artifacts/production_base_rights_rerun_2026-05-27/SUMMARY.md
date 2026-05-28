# Production Base Rights Rerun Summary

Date: 2026-05-27

Role: QA Release Engineer FinDesk

Task: production rerun default base employee rights after backend `message_unread` alias hotfix.

Status: PASS

Production target: `https://finance.brkovic.ltd`

Run stamp: `20260527212947`

Fresh production fixture:

- group_id: `20`
- report_id: `194`
- admin user_id: `58`
- base employee user_id: `59`
- other employee user_id: `60`

Verified:

- default invite creates `access_level=base`;
- base permissions deny group reports, group ledger write, money management, moderation, and member management;
- base employee sees only self in `group_members`;
- base employee cannot access current group export;
- base employee cannot access final report list/detail/package/export;
- base employee cannot read or send group messages;
- base employee cannot create accountable money;
- base employee cannot change roles;
- base `message_unread` returns HTTP `200`, `ok=true`, `unread_count=0`;
- base employee can use personal FinDesk ledger/report;
- base employee can save own operational Field Combat / On the Go row in group context;
- base employee operational tape starts from own cash base `0`, not administrator group cash `1000`;
- base employee sees only own operational cards;
- base employee sees only own accountable data;
- base group self-control excludes administrator group cash;
- admin setup path remains working for group income, messages, accountable money, card include, finalization, member list, and final report list.

Artifacts:

- `production_base_rights_rerun.mjs`
- `production_base_rights_rerun_evidence.json`
- `production_base_rights_rerun_result.json`

Release position:

- default base employee rights slice is accepted by QA.
- previous participant-control PASS remains accepted and was not reopened.
- no backend/API/UX/financial formulas were changed by QA.
