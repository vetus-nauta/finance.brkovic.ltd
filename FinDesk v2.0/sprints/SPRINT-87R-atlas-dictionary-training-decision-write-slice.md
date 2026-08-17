# SPRINT-87R - Atlas Dictionary Training Decision Write Slice

Date: 2026-08-13
Director: Codex
Status: Accepted as local Atlas dictionary-training write slice

## Director Opening

SPRINT-87R extends Atlas write parity for manual dictionary-training decisions:

- `POST /api/workspaces/:workspaceId/dictionary-training-decisions`

This sprint does not enable production cutover and does not implement raw-history conversion, internet-reference writes, imports, attachments, or FTP deployment.

## Agents

- Director: Codex
- QA / Contract Inspector: Godel

Godel performed read-only inspection of the PHP/MySQL contract, response shape, validation errors, side effects, audit requirements, duplicate/idempotency behavior, blocker rules, and smoke coverage.

## Contract

Accepted decision types:

- `defer`
- `reject_training`
- `approve_existing_guess_local`
- `correct_category_local`
- `mark_semantic_blocked`
- `propose_universal_candidate`

Aliases:

- `accept` -> `approve_existing_guess_local`
- `reject` -> `reject_training`
- `skip` -> `defer`

Explicitly unsupported:

- `promote_universal` -> `universal_promotion_not_supported`

Rule-creating decisions:

- `approve_existing_guess_local`
- `correct_category_local`

These may create or reuse one workspace-local `v2_category_rules` row.

Decision-only cases:

- `defer`
- `reject_training`
- `mark_semantic_blocked`
- `propose_universal_candidate`

These must not create a category rule.

## Safety Rules

Rule creation is blocked with `dictionary_training_blocked` when:

- `blockers` is non-empty.
- `review_reason` is one of:
  - `blocked_by_personal`
  - `blocked_by_debt`
  - `private_money_movement`
  - `commercial_income_unclear`
  - `card_income_not_allowed`

Duplicate behavior is keyed by `(workspace_id, source_row_id)`:

- same source row updates the same decision;
- exact same rule payload reuses the same `category_rule_id`;
- no second rule/decision is created for exact duplicate approval.

## Implementation

Changed:

- `server/findesk-v2-atlas-read-server.js`
  - Added dictionary-training decision type normalization.
  - Added source-row lookup and source snapshot reconstruction.
  - Added blocker enforcement.
  - Added source-row keyed duplicate/update behavior.
  - Added category-rule reuse check.
  - Added transactional decision write and optional local rule creation.
  - Added `dictionary_training_decision/create|update` audit.
  - Added route allowlist and dispatch for `POST /dictionary-training-decisions`.
- `scripts/v2_atlas_write_smoke.js`
  - Added disposable import source/rows for dictionary-training source-row smoke.
  - Added viewer rejection smoke.
  - Added approve-local smoke.
  - Added duplicate/idempotency smoke.
  - Added reject-without-rule smoke.
  - Added blocker rejection smoke.
  - Added universal-candidate smoke.
  - Added unsupported universal-promotion smoke.
  - Added dictionary-training audit assertions.
  - Moved unsupported write guard to `raw-history/convert`.
- `scripts/v2_atlas_runtime_smoke.js`
  - Moved unsupported write guard to `raw-history/convert`.
- `scripts/v2_atlas_cutover_gate.js`
  - Updated Atlas write support from `37/45` to `38/45`.

## Verification

Passed:

- `node --check server/findesk-v2-atlas-read-server.js`
- `node --check scripts/v2_atlas_write_smoke.js`
- `node --check scripts/v2_atlas_runtime_smoke.js`
- `node --check scripts/v2_atlas_cutover_gate.js`
- `git diff --check -- server/findesk-v2-atlas-read-server.js scripts/v2_atlas_write_smoke.js scripts/v2_atlas_runtime_smoke.js scripts/v2_atlas_cutover_gate.js`
- `npm run smoke:v2:atlas-write`
- `npm run smoke:v2:atlas-runtime`
- `npm run gate:v2:atlas-cutover`
- `npm run check:atlas`

Expected blocked:

- `npm run gate:v2:atlas-cutover:strict` exited with code `2`

Write-smoke evidence:

- supported write routes: `38`
- temporary workspace cleaned: `true`
- audit created: `true`
- unsupported write guard: `ok`
- dictionary-training local approval created one local category rule
- exact duplicate reused the same decision id and category rule id
- reject decision did not create a category rule
- blocked debt approval rejected with `dictionary_training_blocked`
- universal candidate did not create a local category rule
- universal promotion rejected with `universal_promotion_not_supported`
- viewer write rejected with `workspace_read_only`
- Claudia Z finance snapshot unchanged:
  - `v2_entries`: `1638`
  - `v2_flows`: `52`
  - `v2_monthly_closures`: `4`
  - `v2_report_batches`: `8`
  - `v2_report_batch_entries`: `629`
  - `v2_report_batch_html_snapshots`: `17`
  - `v2_report_packages`: `0`
  - `v2_report_package_items`: `0`
  - `v2_report_versions`: `0`
  - `v2_report_snapshots`: `0`
  - `v2_import_sources`: `60`
  - `v2_import_rows`: `3507`
  - `v2_category_rules`: `85`
  - `v2_dictionary_training_decisions`: `111`
  - `v2_internet_reference_lookups`: `0`
  - Claudia Z cash now: `3893.00`
  - August ending cash: `3893.00`

Gate result:

- total routes: `81`
- reads: `36`
- writes: `45`
- Atlas read supported: `36`
- Atlas write supported: `38`
- unsupported reads: `0`
- unsupported writes: `7`
- cutover allowed: `false`

Remaining unsupported writes:

- `POST /api/workspaces/:workspaceId/raw-history/convert`
- `POST /api/workspaces/:workspaceId/dictionary-training-internet-reference`
- `PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId`
- `POST /api/workspaces/:workspaceId/imports/excel`
- `POST /api/workspaces/:workspaceId/imports/:importId/accept`
- `POST /api/entries/:entryId/attachments`
- `DELETE /api/attachments/:attachmentId`

Remaining blockers:

- `shadow_gateway_available_but_not_cutover`
- `atlas_write_repository_incomplete`
- `ftp_production_cutover_not_authorized`

## Director Decision

SPRINT-87R is accepted locally.

Atlas now supports manual dictionary-training decisions with PHP-compatible decision typing, blocker protections, category-rule creation/reuse, idempotent source-row updates, audit, disposable smoke data, and no Claudia Z finance mutation.

Next safe sprint:

- SPRINT-88R - Internet-reference lookup/feedback write slice or attachments write slice.
