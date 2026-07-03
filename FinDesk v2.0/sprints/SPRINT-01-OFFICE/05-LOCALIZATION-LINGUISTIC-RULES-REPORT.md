# Localization and Linguistic Rules Report

Subagent:
Localization and Linguistic Rules Agent.

Scope:
Sprint 01 read-only audit of v2 approved linguistic/parser/category truth versus old FinDesk category, translation, and rule assumptions. No implementation code, schema, dictionary, i18n, or SQL changes were made.

Files read:
- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/06-dictionaries-and-localization.md`
- `FinDesk v2.0/15-test-fixtures.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/10-director-and-subagents.md`
- `FinDesk v2.0/12-agent-work-protocol.md`
- `FinDesk v2.0/agents/05-LOCALIZATION-LINGUISTIC-RULES-READ-FIRST.md`
- `FinDesk v2.0/sprints/SPRINT-01-legacy-cleanup.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/02-data-model.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/18-error-and-edge-cases.md`
- `FinDesk v2.0/sql/clean-core-schema.sql`
- `FinDesk v2.0/schemas/categories.seed.json`
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/00-DIRECTOR-LOG.md`
- `public/assets/i18n.js`
- `deploy/categories_foundation.sql`
- `docs/STEP1_LOCAL_FOUNDATION_2026-05-20.md`
- `docs/CHECKPOINT_AFTER_OTR2C_LEDGER_DETAILS_20260503.md`
- `docs/PREDEPLOY_CHECKLIST_2026-05-20.md`
- Targeted legacy snippets/search results from `app/ledger.php`, `app/on_the_go.php`, and `public/assets/app.js`.

Findings:

1. Approved v2 category codes are fixed for MVP and must be treated as the only category truth:

```text
crew
commercial_income
dry_dock
berth
marina_ports
service_water
tech_parts
tender
fuel
provisions
interior
cleaning
media_comms
admin_legal
cash_topup_from_card
other
```

Approved directions:

```text
commercial_income => income
cash_topup_from_card => movement
crew, dry_dock, berth, marina_ports, service_water, tech_parts, tender, fuel, provisions, interior, cleaning, media_comms, admin_legal, other => expense
```

2. Approved parser and linguistic truth:
- A counted row must start with `+` or `-`.
- Rows without sign are visible, `unrecognized`, and not counted.
- Cash/Card are funding flows, not categories.
- Card-to-cash is two valid flow records: Card `-` expense and Cash `+` income, both allowed to use `cash_topup_from_card`.
- `commercial_income` is income only for real commercial inflows such as charter, rental, agency fee, brokerage, booking, or commission in the right context.
- Opening balance, private top-up, debt return, correction, and card-to-cash top-up are not `commercial_income`.
- Person names are actors, not categories.
- Low-confidence category output must fall back to `other` with `other_review`.
- Manual learning must be explicit and must not create rules from generic words.

3. Old category logic rejected:
- `deploy/categories_foundation.sql` seeds old `ledger_categories` names: income `Charter`, `Owner cash`, `Management fee`, `Training`, `Delivery`, `Other income`; expense `Fuel`, `Marina`, `Service`, `Parts`, `Provisioning`, `Transport`, `Crew`, `Documents`, `Other expense`.
- This old model has only `income` and `expense`, no v2 `movement`, no code-based fixed MVP category set, no `category_rules`, no actor separation, and no parser confidence/status model.
- Old `Owner cash` conflicts with v2 separation of opening balance/private top-up/commercial income.
- Old `Other income` and `Other expense` are display categories, not v2 `other_review` behavior.
- Old `Marina`, `Service`, `Parts`, `Provisioning`, `Documents` are partial semantic donors at most, but cannot be copied as v2 category codes or report truth.

4. Old translation/i18n classification:
- `public/assets/i18n.js` supports UI dictionaries for `en`, `ru`, `de`, `it`, `es`, `sr`, and `zh`; aliases map `srb`, `mne`, `hr`, `hrv`, and `bs` to `sr`.
- DONOR: the generic runtime language shell pattern can be reviewed later: language normalization, fallback to English, `data-i18n` application, persisted language setting, and migration from legacy `captainFinLanguage`.
- UNSAFE: the actual old UI translation strings are old product wording, not v2 parser or category dictionaries. Terms such as Ledger, Live Report, Advanced, Captain layer, given/spent/left, On the Go, and old report labels must not become v2 linguistic truth automatically.
- UNKNOWN: `zh` is outside the approved v2 language scope. v2 language scope is RU, EN, IT, ES, DE, and BCMS / Local. Keep Chinese out of Sprint 03 parser dictionaries unless the Director expands scope.
- NEEDS DECISION: old runtime key `sr` could be a donor alias for BCMS / Local, but v2 schema language code is `bcms`. Sprint 03 should not persist `sr` as parser rule language without Director approval or an explicit mapping.

5. Old rules/runtime classification:
- UNSAFE: old `entry_type` values `income`/`expense` and `money_type` values `cash`/`noncash` do not match v2 `entry_type` values such as `cash_income`, `cash_expense`, `card_expense`, `opening_balance`, `correction`, `info`, and `unrecognized`.
- UNSAFE: old On the Go conversion defaults `cash_in => income`, `cash_out/noncash_out => expense`, and default `On the Go` category creation are capture workflow rules, not v2 parser/category truth.
- UNSAFE: any old report/carryover behavior that models carried balance as income conflicts with v2 rule: opening balance is not income.
- DONOR: old code may donate only UI/runtime plumbing patterns after review, such as language selection wiring or generic category CRUD shape. It must not donate category names, parser behavior, Cash/Card interpretation, or report semantics.
- UNKNOWN: old hardcoded labels and mixed Russian/English UI strings need a later content audit if the frontend agent wants to reuse copy.

6. Old docs classification:
- DONOR: `docs/STEP1_LOCAL_FOUNDATION_2026-05-20.md` and `docs/PREDEPLOY_CHECKLIST_2026-05-20.md` are useful for migration order, local environment, smoke process, and deployment inventory only.
- UNSAFE: their old `ledger_categories`, On the Go, report, and section/category assumptions are not v2 product truth.
- UNSAFE: `docs/CHECKPOINT_AFTER_OTR2C_LEDGER_DETAILS_20260503.md` preserves old On the Go to Ledger conversion and default sorting/category behavior; this is useful history but not v2 parser/category truth.

7. Sprint 03 language/parser risks:
- Commission ambiguity: `+750 agency commission` should be `commercial_income`; `-12 bank commission` should be admin/legal or `other`, not commercial income.
- `rent`, `rental`, `charter`, and `booking` need sign, flow, and nearby phrase checks so expenses are not misclassified as commercial revenue.
- Card-to-cash phrases such as `снял с карты`, `atm`, and `cash withdrawal` must classify as `cash_topup_from_card` while preserving both Card expense and Cash income sides.
- Person-name false positives: `Вова`, `captain`, `hostess`, and BCMS names must be actors/roles first, not automatic categories.
- BCMS false positives: `luka` can mean port/harbour but can also be a person name; it should not force `marina_ports` without context.
- German `Provision` means commission and must not collide with English `provisions` groceries category.
- Generic words from the stop-word list must not generate learned category rules: bought, paid, today, boat, cash, card, money, for, and their RU equivalents.
- Tender fuel ambiguity must produce primary `fuel` with optional tender-related metadata, not primary `tender`.
- `water` and `electricity` are risky generic terms: in marina context they can support `marina_ports`, but in service/household contexts they may need other handling.
- Multilingual normalization must preserve Cyrillic and Latin text, lowercase for matching, normalize comma decimals, and keep raw text unchanged.
- Encoding QA matters: source files are UTF-8, but terminal output can display mojibake; parser fixtures should assert real Unicode strings, not console-rendered text.
- Manual learning must be workspace-specific by default and must require confirmation before creating rules.

Changes made:
Created this report only. No implementation code, dictionaries, schemas, migrations, or i18n files were changed.

Risks:
- If old `ledger_categories` or On the Go category behavior is reused, v2 will revive old income/expense section logic and lose `movement`, actor separation, and `other_review`.
- If old UI translations are treated as dictionaries, Sprint 03 can accidentally build parser rules from product copy rather than approved category keywords.
- If BCMS is implemented as old `sr` without a deliberate mapping, stored parser rule language codes may diverge from the v2 schema.
- If `commercial_income` keywords are used without sign/flow/context, bank fees, private top-ups, and returns can be misreported as revenue.
- If names are allowed to create categories, actor detection and crew expense logic will become unreliable.

Recommended next action:
For Sprint 03, create a fresh v2 parser dictionary/rules fixture set from `FinDesk v2.0/06-dictionaries-and-localization.md`, `03-parsing-and-rules-engine.md`, `15-test-fixtures.md`, and `schemas/categories.seed.json`. Use old i18n only as a possible UI language-shell donor after Director approval, and explicitly reject old category names/rules as parser truth.

Files touched:
- `FinDesk v2.0/sprints/SPRINT-01-OFFICE/05-LOCALIZATION-LINGUISTIC-RULES-REPORT.md`
