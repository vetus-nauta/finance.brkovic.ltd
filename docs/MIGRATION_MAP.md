# Migration Map

Date: 2026-08-20

Status: initial map. No production migration executed.

## Migration Rule

Do not migrate production until:

1. current schema is frozen/snapshotted
2. sanitized development dump exists
3. target PostgreSQL schema is created through migrations
4. import scripts pass on staging
5. counts/totals reconcile
6. rollback plan is tested

## Initial Table Mapping

| Current MySQL/PHP entity | Target PostgreSQL entity | Transformation | Validation | Status |
| --- | --- | --- | --- | --- |
| `users` | Supabase `auth.users` + `profiles` | map email/display/language/timezone; preserve created/login metadata where safe | user count, unique emails | planned |
| `v2_workspaces` | `organizations`, `workspaces` | create organization wrapper where needed; keep workspace type/currency/locale | workspace count, owner membership | planned |
| `v2_workspace_members` | `memberships`, `roles`, `permissions` | map role/access_scope to permission grants | role matrix tests | planned |
| `v2_workspace_invites` | `invitations` | migrate pending/accepted/revoked state; do not reuse old tokens as live secrets | invite status counts | planned |
| `v2_flows` | `accounts` | cash/card/accountable flows become accounts/cash sources | opening balances and account totals | planned |
| `v2_entries` | `transactions`, `ledger_entries` | preserve row number/date/raw text/amount/category/status/balance links | count, totals by period/account/category | planned |
| `v2_categories` | `categories` | convert `name_json` to localized category records/jsonb | category count and code uniqueness | planned |
| `v2_actors` | `counterparties` or `actors` | preserve aliases and role/person/supplier meaning | actor count and linked entries | planned |
| `v2_monthly_closures` | `period_closures` | preserve closed/reopened/correction state | closure count by workspace/month | planned |
| `v2_attachments` | `documents`, `document_versions`, `document_links` | move local files to private storage, keep checksum/storage key | file count, checksum, linked entity | planned |
| `v2_quick_notes` | `quick_notes`, `transactions.source` | preserve converted notes as immutable source records | converted note links and created entries | planned |
| `v2_accountable_offers` | `cash_advances` | map offer lifecycle | issue/accept counts and amounts | planned |
| `v2_accountable_reports` | `expense_reports` | map report lifecycle and settlement fields | report totals/statuses | planned |
| `v2_accountable_report_rows` | `expense_items` | map rows to report items | row counts and totals | planned |
| `v2_accountable_settlements` | `settlements` | map return/reimburse/discrepancy resolution | settlement totals | planned |
| `v2_report_batches` | `report_snapshots` or `report_fragments` | preserve source entry links and status | report source row counts | planned |
| `v2_report_package*` | `report_packages` | preserve package composition | package count/items | planned |
| `v2_dictionary_training_decisions` | `category_rules`, `user_corrections` | normalize safe local/global learning rules | rule count and blocked decisions | planned |
| Atlas collections/scripts | none as target | use only for parity reference if needed | no target dependency | legacy-only |

## Known Risk Areas

- Runtime-created schema may differ from SQL files.
- Some reports are snapshots and must not become the only truth.
- Local file paths must be reconciled before storage migration.
- Closed-month edits/corrections must preserve audit chain.
- Accountable money materialization must avoid double-counting.
- Quick notes converted to ledger entries must remain immutable source evidence.
- PHP/parity code may format money through runtime numbers; migration must reconcile exact decimal row values, not only final balances.
- Existing role/access-scope checks are application logic, not PostgreSQL tenant boundaries.
- Report HTML/export files must remain linked versions, not independent financial facts.
- Removed/revoked member access must be tested against direct database/storage access, not only UI buttons.

## Required Reconciliation

- source row count
- per-row amount comparison
- totals by period
- totals by cash/card/account
- totals by category
- final balances
- report source row IDs
- report hashes/snapshot totals
- accountable issued/spent/returned/reimbursed/open balances by actor
- attachment counts and checksums
- member/workspace counts
- removed/revoked member access checks
- audit log continuity

## Accountable Money Migration Rule

Accountable money must be migrated as a linked workflow:

- issued advance
- accepted/declined event
- employee report rows
- approved expense items
- materialized ledger links
- returns
- reimbursements
- settlements

Do not flatten these rows into ordinary expenses without preserving links. That would hide open obligations and risks double counting.

## Storage Migration Rule

Every migrated file needs a manifest row:

- legacy source path
- target private bucket
- target object key
- checksum
- byte size
- MIME type
- linked entity
- migration timestamp

Signed URLs are generated only at read time for authorized users.
