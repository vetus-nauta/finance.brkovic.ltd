# 02 — Clean Data Model

## Rule

Do not migrate old business logic into the new MVP core.

Old database and backend can be used as infrastructure donors only. New finance tables must be clean.

## Main entities

```text
users
workspaces
workspace_members
flows
entries
categories
category_rules
actors
attachments
monthly_closures
import_sources
import_rows
dictionary_training_decisions
audit_log
```

## workspaces

A workspace is a separate financial space.

Fields:

```text
id
name
type: yacht | family | personal | business | trip | custom
currency
locale
created_by
created_at
updated_at
archived_at
```

## workspace_members

Roles:

```text
owner
admin
assistant
viewer
```

Assistants can keep their own journals. Their records are pending until accepted by admin.

## flows

A flow is a funding source.

MVP flow types:

```text
cash
card
assistant_journal
```

Cash usually has live balance. Card does not require live bank balance in MVP.

## entries

Main source-of-truth table.

Fields:

```text
id
workspace_id
flow_id
created_by
actor_id nullable
date
raw_text
sign: + | - | null
amount
direction: in | out | none
entry_type
category_id nullable
status
balance_after nullable
source_type: manual | import | assistant | correction
source_id nullable
source_row_id nullable
notes nullable
created_at
updated_at
archived_at
```

Entry types:

```text
cash_income
cash_expense
card_expense
card_income
opening_balance
correction
info
unrecognized
assistant_pending
```

Statuses:

```text
recognized
unrecognized
other_review
excluded
imported
assistant_pending
accepted
rejected
corrected
duplicate_suspect
```

## dictionary_training_decisions

Dictionary training decisions are explicit review outcomes for raw-history rows or groups.

They are not operational entries and must not affect finance totals.

Fields:

```text
id
workspace_id
archive_workspace_id nullable
source_id nullable
source_row_id nullable
decision_scope: row | group
group_key nullable
source_row_ids
decision_type
current_rule_guess nullable
target_category_id nullable
category_rule_id nullable
pattern nullable
pattern_type nullable
language
weight nullable
negative_weight nullable
requires_any
excludes_any
confidence nullable
review_reason nullable
blockers
matched_signals
semantic_markers
source_snapshot
note nullable
decided_by
decided_at
updated_at
```

Allowed decision types:

```text
defer
reject_training
approve_existing_guess_local
correct_category_local
mark_semantic_blocked
propose_universal_candidate
```

Only `approve_existing_guess_local` and `correct_category_local` may create workspace-local `category_rules`.

Blocked rows and universal candidates do not create category rules.

## workspace_assistant_settings

Workspace-local assistant consent/settings record.

Fields:

```text
workspace_id
mr_smith_enabled
internet_reference_mode: disabled | per_request | workspace_enabled
provider_key
retention_days
updated_by nullable
created_at
updated_at
```

Rules:

```text
provider_key = stub by default
provider_key = allowlisted_http only when server env gate is enabled and allowlist is non-empty
settings changes require owner/admin
settings do not create entries, rules, reports, imports, balances, or closures
```

## internet_reference_lookups

Local provenance rows for Mr. Smith reference preview.

They are not operational entries, not training decisions, and not financial audit truth.

Fields:

```text
id
workspace_id
source_row_id nullable
provider_key
provider_request_id nullable
consent_source: request | workspace_setting
sanitized_query
query_hash
masked_fields
result_status: stub | ok | error | timeout
latency_ms
matches
selected_match nullable
no_financial_mutation
created_by nullable
created_at
retention_delete_after nullable
```

Rules:

```text
query_hash is workspace-scoped
source_row_id must belong to the workspace dictionary/archive context
stub provider performs no network request
allowlisted_http is disabled by default
allowlisted_http requires https candidate_url with exact allowlisted host
allowlisted_http rejects localhost, IP literals, private/reserved/local addresses, and redirects
matches store bounded metadata only, not raw HTML, cookies, headers, scraped page text, or provider payloads
successful accepted preview writes exactly one provenance row
failed validation, authorization, unsafe-payload, or unsafe-URL preview writes no provenance row
provenance rows must not create or modify dictionary_training_decisions
provenance rows must not create or modify category_rules
provenance rows are not accounting truth
selected_match may store human feedback verdict useful | unclear | not_useful
selected_match feedback is local evidence feedback only and not a training decision
```

## categories

Categories are fixed for MVP, editable only by admin/settings later.

Fields:

```text
id
workspace_id nullable
code
name
direction: income | expense | movement | mixed
parent_code nullable
sort_order
is_system
is_active
```

Direction defines how the category should be treated in reports.

Examples:

```text
commercial_income => income
non_commercial_income => income
cash_topup_from_card => movement
transport_expenses => expense
representation_expenses => expense
current_boat_expenses => expense
guest_trip_support => expense
guest_cash_issued => expense
crew/fuel/provisions/etc. => expense
other => expense by default
```

## derived accounting sections

`entries` remains the source of truth. MVP lower accounting is derived from category and semantic markers, not stored as a separate table.

Derived fields exposed by API:

```text
accounting_section: operational | lower_accounting | admin_debt
accounting_type: operational | debt_or_return | money_movement | guest_cash_issued | admin_debt
accounting_label
```

Lower-accounting rows include debt, loan, credit, return, accountable-cash, private settlement, and guest-cash-issued control rows.

Admin-debt rows are personal administrator liability rows, such as confirmed personal credit/debt residue or later personal expenses/refunds. They are a separate reporting block and must not be mixed with lower accounting, guest/accountable money, or normal operational categories.

They remain counted in physical cash/card balances when their flow/sign/entry_type is counted, but are excluded from operational category totals in Layer 1 summary.

## category_rules

Rules for auto-categorization.

Fields:

```text
id
workspace_id nullable
category_code
pattern
pattern_type: keyword | phrase | regex | supplier | role
language: ru | en | it | es | de | bcms | multi
weight
negative_weight
requires_any nullable
excludes_any nullable
created_by_user
is_active
```

Rules must be explainable.

## actors

Actor is a person, role, supplier, or company involved in an entry. Actor is not category.

## attachments

Each entry can have multiple attachments.

Fields:

```text
id
entry_id
file_name
file_url
mime_type
size_bytes
image_mode: original | compressed | grayscale_scan
created_at
```

## monthly_closures

Locks and comments for months. Reports must remain reproducible from entries.

## import_sources and import_rows

Keep file-level and row-level traceability for legacy imports.

## audit_log

Every meaningful edit should be logged with before/after JSON and performer.
