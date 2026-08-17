# 16 — API Contract

## Purpose

Backend and frontend agents must connect through a small, explicit API.

Do not create broad APIs before the clean core works.

## Workspaces

```text
GET /api/workspaces
POST /api/workspaces
GET /api/workspaces/:id
PATCH /api/workspaces/:id
```

## Flows

```text
GET /api/workspaces/:workspaceId/flows
POST /api/workspaces/:workspaceId/flows
```

MVP default flows:

```text
Cash
Card
```

## Entries

```text
GET /api/workspaces/:workspaceId/entries?year=2026&month=7
POST /api/workspaces/:workspaceId/entries
PATCH /api/entries/:entryId
DELETE /api/entries/:entryId
```

### POST entry request

```json
{
  "flow_id": "uuid",
  "date": "2026-07-03",
  "raw_text": "-250 рыба"
}
```

### Entry semantic markers

Entry and parse-preview responses may include:

```json
{
  "semantic_markers": [
    {
      "marker": "owner_funding",
      "source_actor": "Александр",
      "pattern": "non_commercial_income"
    }
  ]
}
```

Semantic markers are read/explainability metadata only.

They must not change arithmetic or report totals:

```text
amount
sign
flow
direction
entry_type
category_code
status
balance_after
```

Current marker ids:

```text
cash_location_safe
owner_funding
commercial_income_allowed
debt_or_return
tender_related
weak_dictionary_context
mixed_dictionary_context
```

`weak_dictionary_context` and `mixed_dictionary_context` are dictionary-training metadata. They may explain low-confidence or mixed category signals, but they must not change entry primitives or financial totals.

### Entry classification decision

Entry and parse-preview responses may include:

```json
{
  "confidence": 0.48,
  "review_reason": "weak_only",
  "matched_signals": [
    {
      "type": "category",
      "category_code": "current_boat_expenses",
      "pattern": "current_boat_expenses",
      "source": "fixture_keyword"
    },
    {
      "type": "semantic_marker",
      "marker": "weak_dictionary_context",
      "pattern": "weak_dictionary_context",
      "source": "semantic_marker"
    }
  ],
  "blockers": [],
  "classification_decision": {
    "category_code": "current_boat_expenses",
    "confidence": 0.48,
    "review_reason": "weak_only",
    "matched_signals": [],
    "blockers": []
  }
}
```

Allowed `review_reason` values:

```text
blocked_by_personal
blocked_by_debt
private_money_movement
commercial_income_unclear
mixed_context
weak_only
other_review
no_category
card_income_not_allowed
```

Known blocker ids:

```text
non_yacht_or_personal
debt_or_return
money_movement
missing_yacht_charter_phrase
card_income_manual_guard
```

Classification decision fields are explainability metadata only.

They must not change entry primitives, cash/card balances, report totals, month closure behavior, import acceptance behavior, or dictionary-review read-only guarantees.

### Entry accounting section

Entry and parse-preview responses may include derived accounting fields:

```json
{
  "accounting_section": "lower_accounting",
  "accounting_type": "debt_or_return",
  "accounting_label": "Debt / loan / return / accountable"
}
```

`accounting_section=lower_accounting` is a reporting/view layer derived from category and semantic markers.

It must not change entry primitives or physical cash/card balances.

Current lower-accounting types:

```text
debt_or_return
money_movement
guest_cash_issued
```

## Parse preview

```text
POST /api/parse-entry-preview
```

Used to preview how a note will be parsed without saving.

## Dictionary Review

```text
GET /api/workspaces/:workspaceId/dictionary-review-queue?limit=120&examples=4
```

Read-only queue for dictionary training against raw import history.

If the selected workspace has a sibling named `Archive Raw History`, the queue reads that raw archive workspace. Otherwise it reads the selected workspace import rows.

Response:

```text
ok
queue.workspace_id
queue.workspace_name
queue.rows_total
queue.rows_with_money
queue.rows_needs_review
queue.groups[]
queue.groups[].semantic_markers
queue.groups[].current_rule_guess
queue.groups[].examples[].source
queue.groups[].examples[].confidence
queue.groups[].examples[].review_reason
queue.groups[].examples[].matched_signals
queue.groups[].examples[].blockers
queue.groups[].examples[].classification_decision
```

Dictionary review queue must not create operational entries, actors, category rules, audit rows, flows, categories, or monthly closures.

Queue amount fields are review metadata only. They must not be used as finance-report totals.

## Dictionary Training Decisions

```text
GET /api/workspaces/:workspaceId/dictionary-training-decisions?limit=120
POST /api/workspaces/:workspaceId/dictionary-training-decisions
```

The review queue is read-only. Training decisions are written through this separate endpoint only.

Writer role is required for `POST`.

Request:

```json
{
  "source_row_id": "uuid",
  "decision_type": "approve_existing_guess_local",
  "category_code": "current_boat_expenses",
  "pattern": "агент",
  "pattern_type": "keyword",
  "language": "ru",
  "weight": 10,
  "requires_any": [],
  "excludes_any": [],
  "note": "reviewer explanation"
}
```

Allowed `decision_type` values:

```text
defer
reject_training
approve_existing_guess_local
correct_category_local
mark_semantic_blocked
propose_universal_candidate
```

Aliases accepted for API convenience:

```text
accept -> approve_existing_guess_local
reject -> reject_training
skip -> defer
```

`promote_universal` is rejected by this MVP endpoint.

Response:

```text
ok
decision.id
decision.workspace_id
decision.archive_workspace_id
decision.source_row_id
decision.decision_scope
decision.decision_type
decision.current_rule_guess
decision.target_category_code
decision.category_rule_id
decision.pattern
decision.pattern_type
decision.language
decision.weight
decision.requires_any
decision.excludes_any
decision.confidence
decision.review_reason
decision.blockers
decision.matched_signals
decision.semantic_markers
decision.source_snapshot
decision.note
decision.decided_by
decision.decided_at
decision.category_rule
```

Mutation rules:

```text
approve_existing_guess_local => creates/links one workspace-local category rule
correct_category_local => creates/links one workspace-local category rule
defer => decision/audit only
reject_training => decision/audit only
mark_semantic_blocked => decision/audit only
propose_universal_candidate => decision/audit only
```

Rows with blockers cannot create category rules.

Blocked review reasons:

```text
blocked_by_personal
blocked_by_debt
private_money_movement
commercial_income_unclear
card_income_not_allowed
```

Known blockers:

```text
non_yacht_or_personal
debt_or_return
money_movement
missing_yacht_charter_phrase
card_income_manual_guard
```

Training decisions must not create or modify operational entries, actors, flows, monthly closures, import rows, import acceptance decisions, report snapshots, balances, or financial reports.

## Future Beta Internet Reference Contract

This contract is reserved for a beta assistant named `Mr. Smith`.

Production internet lookup is not accepted until consent, provenance, privacy, tenant-isolation, and allowlisted provider gates are implemented.

Current MVP shape is a safe stub by default, with a gated beta allowlisted HTTP adapter:

```text
GET /api/workspaces/:workspaceId/assistant-settings
PATCH /api/workspaces/:workspaceId/assistant-settings
POST /api/workspaces/:workspaceId/dictionary-training-internet-reference
GET /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups?limit=50
PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId
```

Purpose:

Return read-only reference evidence for a possible store, supplier, service company, marina, restaurant, transport provider, or other public resource.

Required request rules:

```text
writer role or explicit reviewer role required
explicit lookup consent required
sanitized query required
raw operational row forbidden by default
amounts/balances forbidden
source row identifiers forbidden in external query
tenant/workspace scope required
no real internet request in MVP stub
```

`allowlisted_http` beta request extension:

```text
candidate_url optional for stub
candidate_url required for allowlisted_http
candidate_url must be https
candidate_url host must exactly match a server allowlist entry
implicit subdomains are not allowed
localhost, IP literals, private, reserved, link-local, and local-network targets are rejected
redirects are disabled
transport timeout and body-size caps are mandatory
provider receives sanitized_query and candidate_url only
provider must not receive workspace ids, user ids, source row ids, raw rows, amounts, balances, entries, reports, imports, or snapshots
```

Canonical preview request:

```json
{
  "source_row_id": "...",
  "sanitized_query": "...",
  "candidate_url": "https://...",
  "lookup_consent": true
}
```

Allowed response fields:

```text
ok
request_id
lookup_id
workspace_id
source_row_id
sanitized_query
query_hash
masked_fields
provider_key
provider_request_id
result_status
consent_source
matches[].label
matches[].business_type
matches[].location
matches[].aliases
matches[].source_url
matches[].source_domain
matches[].source_type
matches[].retrieved_at
matches[].confidence
matches[].uncertainty_reason
suggested_reviewer_question
no_financial_mutation
```

Mutation rules:

```text
no operational entry mutation
no actor mutation
no category mutation
no category rule mutation
no universal candidate mutation
no import mutation
no report mutation
no balance mutation
no monthly closure mutation
```

Internet reference evidence is metadata only.

It must not be displayed as a confirmed accounting classification.

It must not automatically create dictionary decisions or training rules.

MVP stub behavior:

```text
requires explicit consent
requires sanitized query
rejects raw operational payload fields
returns matches[].source_type = stub
returns no_financial_mutation = true
writes exactly one v2_internet_reference_lookups provenance row on successful preview
```

Beta allowlisted adapter behavior:

```text
provider_key = allowlisted_http only when FINDESK_V2_MR_SMITH_ALLOWLIST_ENABLED=1
allowed domains come from FINDESK_V2_MR_SMITH_ALLOWED_DOMAINS
empty allowlist means provider is not selectable
invalid env allowlist entries are ignored
fetch uses metadata-only extraction
matches_json may store title/domain/source URL/source type/confidence/uncertainty only
raw HTML, headers, cookies, scraped body text, and full provider payloads are never stored
successful accepted preview writes exactly one provenance row
validation, authorization, unsafe-payload, or unsafe-URL failures write no provenance row
```

Assistant settings:

```text
GET is readable by workspace members.
PATCH requires owner/admin.
provider_key = stub by default.
provider_key = allowlisted_http only when server env gate is enabled and allowlist is non-empty.
internet_reference_mode = disabled | per_request | workspace_enabled
```

Lookup readback:

```text
returns local provenance rows only
does not fetch external sources
does not create decisions or rules
```

Lookup feedback:

```text
PATCH /api/workspaces/:workspaceId/dictionary-training-internet-reference/lookups/:lookupId
writer role required
CSRF header required
local provenance row only
no external fetch
no lookup consent required because no provider call occurs
verdict required: useful | unclear | not_useful
match_index optional, bounded to first 5 matches
note optional, max 240 chars
unsafe raw-row/amount/balance/report/import payload fields are rejected
cross-workspace lookup id returns not found
updates selected_match_json only
does not create dictionary_training_decisions
does not create category_rules
does not mutate entries, flows, actors, reports, imports, balances, or closures
```

## Categories

```text
GET /api/workspaces/:workspaceId/categories
PATCH /api/entries/:entryId/category
POST /api/workspaces/:workspaceId/category-rules
```

## Reports

```text
GET /api/workspaces/:workspaceId/reports/monthly?year=2026&month=7
GET /api/workspaces/:workspaceId/reports/category-matrix?year=2026
GET /api/workspaces/:workspaceId/reports/other-review
GET /api/workspaces/:workspaceId/reports/layer1-summary?year=2026&month=7
GET /api/workspaces/:workspaceId/reports/layer1-source-entries?ids=uuid,uuid
GET /api/workspaces/:workspaceId/reports/layer1-snapshots?year=2026&month=7
POST /api/workspaces/:workspaceId/reports/layer1-snapshots
```

Reports are generated from entries.

Layer 1 summary response:

```text
ok
report.header
report.totals
report.blocks
report.source_trace
```

Layer 1 lower accounting response:

```text
report.blocks.lower_accounting.count
report.blocks.lower_accounting.total
report.blocks.lower_accounting.entries[]
report.blocks.lower_accounting.settlements
report.blocks.lower_accounting.settlements.by_counterparty[]
report.blocks.lower_accounting.source_entry_ids[]
report.source_trace.totals.lower_accounting_total[]
```

Layer 1 administrator debt response:

```text
report.totals.admin_debt_total
report.blocks.admin_debt.count
report.blocks.admin_debt.total
report.blocks.admin_debt.opening_total
report.blocks.admin_debt.increased_total
report.blocks.admin_debt.returned_total
report.blocks.admin_debt.net_change
report.blocks.admin_debt.entries[]
report.blocks.admin_debt.source_entry_ids[]
report.source_trace.totals.admin_debt_total[]
```

Administrator debt is a liability/reporting block. It is still reflected in physical cash/card balances through the source entries, but it is excluded from operational category totals and lower-accounting settlement rows.

Lower-accounting entry fields:

```text
settlement_counterparty
settlement_effect
settlement_direction
```

Lower-accounting settlement row fields:

```text
counterparty
issued_total
returned_total
net_open
open_amount
over_returned_amount
status
needs_review_reason
entry_count
source_entry_ids[]
```

Allowed settlement statuses:

```text
open
partial
closed
needs_actor
review
```

Lower-accounting rows stay in physical `cash_expense`, `card_expense`, `external_cash_income`, and `ending_cash` where the flow/sign/entry_type requires it.

They are excluded from operational `blocks.categories.rows` to avoid treating debt/loan/return/accountable/guest-cash control rows as normal expense categories.

Layer 1 source rules:

```text
source_trace.totals.* = operational entry ids only
source_trace.basis.opening_cash = non-entry opening cash basis
```

`source_trace.basis.opening_cash` may explain flow opening balance and prior operational cash delta, but it is not an editable operational entry.

Layer 1 source entries request:

```text
ids = comma-separated UUID list
max ids = 150
```

Response:

```text
entries
missing_ids
```

Cross-workspace or inaccessible ids must not leak data and must appear as missing.

Layer 1 snapshot create request:

```json
{
  "year": 2026,
  "month": 7,
  "status": "stored",
  "comment": "optional"
}
```

`status` may be `draft`, `stored`, or `closed`.

`closed` requires the month to be closed.

Layer 1 snapshot response/readback:

```text
snapshot.version
snapshot.status
snapshot.summary
snapshot.source_trace
snapshot.source_entry_ids
snapshot.correction_ids
snapshot.attachment_refs
snapshot.content_hash
```

Snapshot versions are monotonic per:

```text
workspace_id + report_type + year + month
```

Concurrent snapshot saves must not create duplicate versions.

Stored snapshots are read-only evidence, not editable financial truth.

## Month closure

```text
POST /api/workspaces/:workspaceId/months/:year/:month/close
POST /api/workspaces/:workspaceId/months/:year/:month/reopen
POST /api/workspaces/:workspaceId/months/:year/:month/correction
```

Editing a closed month must require explicit mode: correction or recalculation.

## Import

```text
POST /api/workspaces/:workspaceId/imports/excel
GET /api/workspaces/:workspaceId/imports/:importId/review
POST /api/workspaces/:workspaceId/imports/:importId/accept
```

## Attachments

```text
POST /api/entries/:entryId/attachments
GET /api/entries/:entryId/attachments
DELETE /api/attachments/:attachmentId
```

## API rule

All API responses that affect money must return enough data for the UI to update without guessing.
