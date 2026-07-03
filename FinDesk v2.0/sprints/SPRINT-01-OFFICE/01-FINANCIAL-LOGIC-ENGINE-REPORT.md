# FinDesk v2.0 Sprint 01 - Financial Logic Engine Report

Subagent:
Financial Logic Engine Agent

Scope:
Sprint 01 legacy isolation of old FinDesk financial logic. Checked old financial models, tables, entities, calculations, categories, reports, business documents, and adjacent domain calculators against `FinDesk v2.0/` as the only product truth. No implementation code, SQL, or runtime configuration was changed.

Files read:
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/10-director-and-subagents.md`
- `FinDesk v2.0/12-agent-work-protocol.md`
- `FinDesk v2.0/agents/01-FINANCIAL-LOGIC-ENGINE-READ-FIRST.md`
- `FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md`
- `FinDesk v2.0/01-product-logic.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/09-operational-and-summary-table-contract.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/15-test-fixtures.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/21-sprint-plan.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/00-DIRECTOR-LOG.md`
- `README.md`
- `app/ledger.php`
- `app/on_the_go.php`
- `app/advances.php`
- `app/findesk_phase2.php`
- `app/business.php`
- `app/groups.php`
- `app/yacht_provisioning.php`
- `app/yacht_prices.php`
- `app/data/yacht_provisioning/categories.json`
- `public/api.php`
- `public/assets/app.js`
- `public/assets/app.css`
- `server/findesk-atlas-server.js`
- `scripts/cash_session_math_audit.js`
- `tests/findesk-runtime-audit.spec.js`
- `deploy/ledger_foundation.sql`
- `deploy/categories_foundation.sql`
- `deploy/on_the_go_foundation.sql`
- `deploy/on_the_go_sessions_runtime.sql`
- `deploy/advances_foundation.sql`
- `deploy/findesk_phase2_foundation.sql`
- `deploy/business_desk_foundation.sql`
- `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md`
- `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md`
- `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md`
- `docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md`
- `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md`
- `docs/AI_TEAM/50_FINDESK_PRODUCT_REBUILD_TZ_2026-06-02.md`
- `docs/AI_TEAM/91_UNIVERSAL_CASH_SESSION_ENGINE_2026-06-07.md`
- `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md`

Findings:
- FinDesk v2.0 truth is the clean operational journal plus generated summary model: `workspaces`, `flows`, `entries`, `categories`, `category_rules`, `actors`, `attachments`, `monthly_closures`, `import_sources`, `import_rows`, and `audit_log`. Old FinDesk instead centers on `ledger_entries`, `ledger_categories`, `on_the_go_*`, `cash_advances`, `findesk_reports`, `findesk_report_items`, and Atlas `cash_sessions`.
- Old `ledger_entries` uses `entry_type = income|expense` and `money_type = cash|noncash`. This conflicts with v2.0 entry types such as `cash_income`, `cash_expense`, `card_expense`, `opening_balance`, `correction`, `info`, `unrecognized`, and `assistant_pending`, and with v2.0 `flow` semantics.
- Old categories are human labels and old yacht/business assumptions: `Charter`, `Owner cash`, `Management fee`, `Training`, `Delivery`, `Fuel`, `Marina`, `Service`, `Parts`, `Provisioning`, `Transport`, `Crew`, `Documents`, `Other expense`. These do not match v2.0 category codes/directions, especially `commercial_income`, `cash_topup_from_card`, `media_comms`, `admin_legal`, `other`, and `other_review`.
- Old report logic treats current/historical/final/group report snapshots and packages as product truth. v2.0 requires summary reports to be generated from operational entries, with monthly closure snapshots only for audit and no silent mutation of closed months.
- Old accountable-money and podotchet logic (`cash_advances`, issue/submit/accept/return/rollover) is a separate business process. It must not become the v2.0 finance engine base unless the Director explicitly scopes it later as a separate feature.
- Old Cash/Card logic is a false friend. It sometimes resembles v2.0, but it is implemented through `stream_type`, `capture_type`, `noncash_out`, reportable cards, and final packages. v2.0 Cash/Card must be rebuilt from `flows` and entries, preserving the approved card-to-cash two-row model.
- Old parsers in `on_the_go` and Atlas `cash_sessions` have strict signed-line behavior. Even where this agrees with v2.0, the authoritative rule must come only from `FinDesk v2.0/03-parsing-and-rules-engine.md` and `15-test-fixtures.md`, not from old code or docs.
- Old `docs/` and especially `docs/AI_TEAM/` contain extensive product assertions: money tree, final report truth, common pot, accountable money, field combat mode, universal cash session, active session/report/archive, and old sprint discipline. These are useful only as rejected legacy inventory, not v2.0 product truth.
- Business Desk/proforma and yacht provisioning/price logic are not v2.0 financial core. They should be treated as unknown or out-of-scope until the Director decides whether any document or domain tool belongs in the v2.0 roadmap.

Classification table:

| path | item | classification | reason |
|---|---|---|---|
| `app/ledger.php` | `ql_ledger_create`, `ql_ledger_balance`, `ql_ledger_report`, group export/final report package, ledger categories | UNSAFE_LEGACY_LOGIC | Uses old `entry_type/money_type`, old balances, virtual Live Report rows, final package snapshots, and old sections instead of v2.0 entries/flows/generated summaries. |
| `deploy/ledger_foundation.sql` | `ledger_entries`, `entry_files` | UNSAFE_LEGACY_LOGIC | Old table/entity model conflicts with v2.0 `entries` and attachment contract. |
| `deploy/categories_foundation.sql` | `ledger_categories` and default category seeds | UNSAFE_LEGACY_LOGIC | Old category names/directions are not v2.0 category codes and can misclassify commercial income, opening balance, movements, and other review. |
| `app/on_the_go.php` | Live Report tapes/captures, signed-note parsing, card summary, submit/include/archive flows | UNSAFE_LEGACY_LOGIC | Old report-card workflow stores financial facts as `cash_in/cash_out/noncash_out` captures and `reportable` state, not v2.0 journal entries. |
| `deploy/on_the_go_foundation.sql` | `on_the_go_captures`, `on_the_go_files` | UNSAFE_LEGACY_LOGIC | Old capture/proof schema is part of legacy finance workflow and not v2.0 financial core. |
| `deploy/on_the_go_sessions_runtime.sql` | `on_the_go_tapes`, `on_the_go_sessions`, field drafts, sync ops, upload states | UNSAFE_LEGACY_LOGIC | Old tape/session/draft model can only be considered infrastructure after Director review; its financial semantics are unsafe. |
| `app/advances.php` | `cash_advances` issue/submit/accept/return/rollover calculations | UNSAFE_LEGACY_LOGIC | Podotchet/accountable-money flow is not in v2.0 core entities or MVP formulas. |
| `deploy/advances_foundation.sql` | `cash_advances` schema and On the Go advance fields | UNSAFE_LEGACY_LOGIC | Old entity and statuses (`issued`, `submitted`, `accepted`, `returned`, `discrepancy`) are outside v2.0 financial truth. |
| `app/findesk_phase2.php` | `findesk_transfers`, `findesk_reports`, report assembly/finalize/archive | UNSAFE_LEGACY_LOGIC | Old middle-layer report package and transfer model conflicts with generated v2.0 summaries and monthly closure contract. |
| `deploy/findesk_phase2_foundation.sql` | `findesk_workspace_preferences`, `findesk_transfers`, `findesk_reports`, `findesk_report_items` | UNSAFE_LEGACY_LOGIC | Old FinDesk Phase 2 tables are forbidden legacy entity names and report logic. |
| `public/api.php` | Legacy financial API actions (`ledger_*`, `on_the_go_*`, `advance_*`, `findesk_report_*`, `category_*`) | UNSAFE_LEGACY_LOGIC | Exposes old finance behavior directly; must not become v2.0 API contract. |
| `public/assets/app.js` | Ledger UI, report formulas, final package rendering, On the Go card UI | UNSAFE_LEGACY_LOGIC | Frontend contains old finance calculations/labels and report-truth UI. |
| `public/assets/app.css` | Legacy ledger/report/advance/On the Go/Advanced screen styles | UNSAFE_LEGACY_LOGIC | Carries old dashboard/report UX surfaces; v2.0 must follow the clean one-screen notes contract. |
| `server/findesk-atlas-server.js` | `cash_sessions`, `parseCashNotebook`, participant settlement preview, cash reports/archive | UNSAFE_LEGACY_LOGIC | Alternative Atlas cash-session engine uses contribution/expense/note/adjustment and settlement preview, not v2.0 entries/flows/categories. |
| `server/findesk-atlas-server.js` | yacht state/pricing/provisioning routes and calculations | UNKNOWN_REQUIRES_DIRECTOR | Yacht-specific tools may be future optional tooling, but v2.0 is universal and not yacht-only. |
| `scripts/cash_session_math_audit.js` | settlement preview audit harness | UNSAFE_LEGACY_LOGIC | Tests old Atlas settlement behavior, not v2.0 calculation fixtures. |
| `tests/findesk-runtime-audit.spec.js` | runtime audit for advances, On the Go, FinDesk board | UNSAFE_LEGACY_LOGIC | Acceptance criteria are anchored in old workflows and cannot validate v2.0. |
| `README.md` | old product scope, product layers, accountable money flow | UNSAFE_LEGACY_LOGIC | Old README is not product truth for v2.0; useful only as legacy inventory. |
| `docs/FINANCIAL_FLOW_ARCHITECTURE_REVIEW_2026-05-22.md` | money tree, accountable money, final report, financial_events proposal | UNSAFE_LEGACY_LOGIC | Old architecture doc directly competes with v2.0 clean core and must be rejected as authority. |
| `docs/KNOWLEDGE_CASH_CARD_PARALLEL_LIVE_REPORTS_2026-05-22.md` | parallel Live Report Cash/Card rule | UNSAFE_LEGACY_LOGIC | Similar vocabulary but old implementation model; v2.0 Cash/Card rules must come from v2 docs only. |
| `docs/HANDOFF_NEW_CHAT_2026-05-22_CASH_CARD_LIVE_REPORT.md` | old handoff and development instructions | UNSAFE_LEGACY_LOGIC | Old handoff explicitly instructs future chats around legacy architecture; must not guide v2.0. |
| `docs/AI_TEAM/07_MVP_EXIT_CRITERIA.md` | old MVP exit criteria | UNSAFE_LEGACY_LOGIC | Defines old money tree, final report truth, field combat, and legacy acceptance gates. |
| `docs/AI_TEAM/10_BUSINESS_MVP_SCOPE.md` | business MVP scope and common-pot model | UNSAFE_LEGACY_LOGIC | Old product scope includes common pot, group reports, travel, Business Desk, and report consolidation not in v2.0 core. |
| `docs/AI_TEAM/50_FINDESK_PRODUCT_REBUILD_TZ_2026-06-02.md` | active session rebuild brief | UNSAFE_LEGACY_LOGIC | Old active-session model conflicts with v2.0 current-month journal as first screen/source of truth. |
| `docs/AI_TEAM/91_UNIVERSAL_CASH_SESSION_ENGINE_2026-06-07.md` | Atlas cash session engine, participants, reports, archive, settlement | UNSAFE_LEGACY_LOGIC | Later old draft still uses cash sessions and settlement preview, not v2.0 operational journal. |
| `docs/AI_TEAM/92_PERSONAL_JOURNAL_RECORDS_REPORTS_DISCIPLINE_2026-06-07.md` | Zhurnal/records/reports discipline | UNSAFE_LEGACY_LOGIC | Shares strict sign idea but wraps it in old records/report/account container model. |
| `docs/AI_TEAM/**` | old AI team role reports, QA artifacts, product bible, handoffs | UNSAFE_LEGACY_LOGIC | Not product truth per Sprint 01 rule; may contain many persuasive but rejected finance assumptions. |
| `app/business.php` | proforma calculations and business document entities | UNKNOWN_REQUIRES_DIRECTOR | Contains financial document calculations, but v2.0 core spec does not include invoices/proformas. Director must decide future isolation. |
| `deploy/business_desk_foundation.sql` | `company_profiles`, `clients`, `proformas`, `proforma_items` | UNKNOWN_REQUIRES_DIRECTOR | Business documents should not enter v2.0 financial core without explicit scope decision. |
| `app/yacht_provisioning.php` | yacht provisioning quantity calculator | UNKNOWN_REQUIRES_DIRECTOR | Yacht-specific domain calculator is not v2.0 finance core and may bias the universal product. |
| `app/data/yacht_provisioning/categories.json` | provisioning categories | UNKNOWN_REQUIRES_DIRECTOR | Domain categories are not v2.0 financial categories; keep isolated unless Director approves optional yacht tooling. |
| `app/yacht_prices.php` | yacht price catalog endpoints | UNKNOWN_REQUIRES_DIRECTOR | Price tooling is outside v2.0 operational journal and generated summary logic. |
| `app/data/yacht_price_sources.json` | yacht price source catalog | UNKNOWN_REQUIRES_DIRECTOR | Source data may be useful only as optional domain tooling, not financial logic truth. |

Changes made:
- Created this report only.
- No application code was written.
- No SQL was changed.
- No runtime configuration was changed.
- No secrets were read into or written into this report.

Risks:
- Legacy code and API actions remain active in the repository and use vocabulary close to v2.0 (`cash`, `card`, `report`, `journal`, `category`), creating high accidental reuse risk.
- Old category seeds are deceptively close to v2.0 concepts but are not compatible with v2.0 category codes, directions, or review statuses.
- Old docs are numerous and sometimes more detailed than v2.0 docs; agents may accidentally treat them as product authority.
- Some legacy behavior resembles v2.0 fixtures, especially strict `+/-` parsing and Card not affecting physical cash. Similarity must not be treated as permission to copy old code.
- Business Desk/proforma and yacht tools are unresolved product-scope questions; leaving them unclassified beyond `UNKNOWN_REQUIRES_DIRECTOR` could lead to later scope creep.

Recommended next action:
- Director should mark `app/ledger.php`, `app/on_the_go.php`, `app/advances.php`, `app/findesk_phase2.php`, their SQL files, and old report/category docs as rejected financial logic for v2.0.
- Data and Backend Core Agent should verify that Sprint 02 starts from the clean v2.0 schema/contracts and does not depend on legacy tables or API actions.
- Legacy Import and Archive Agent should treat old tables/reports as import sources only, with source traceability and no reuse of old calculations.
- Director should decide whether Business Desk/proforma and yacht provisioning/price tools are out-of-scope, separate optional modules, or future `UNKNOWN_REQUIRES_DIRECTOR` backlog.
- QA should later add a guard check that no v2.0 code calls legacy `ledger_*`, `on_the_go_*`, `advance_*`, `findesk_report_*`, or old category tables for finance-core behavior.

Files touched:
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/01-FINANCIAL-LOGIC-ENGINE-REPORT.md`
