# SPRINT-21R — Claudia Z Dictionary Linguistic Training

## Director Sprint Opening

Sprint:
SPRINT-21R — Claudia Z Dictionary Linguistic Training

Goal:
Build the first controlled linguistic dictionary pass for Claudia Z archive/raw history without inflating categories and without changing the current operational balance.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/02-data-model.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/06-dictionaries-and-localization.md`
- `FinDesk v2.0/schemas/categories.seed.json`
- `FinDesk v2.0/37-claudia-z-current-operational-balance-chain.json`
- `scripts/v2_import_claudia_z_local.php`
- local archive import artifacts under `storage/imports/claudia-z-archive/`

Agents assigned:

- Linguistic Taxonomy Agent
- Vocabulary Normalization Agent
- QA / Category Inflation Audit Agent

Agent tasks:

- Linguistic Taxonomy Agent: propose a compact category tree and rules for when not to create categories.
- Vocabulary Normalization Agent: extract aliases, spelling variants, risky phrases, and canonical labels.
- QA / Category Inflation Audit Agent: identify category inflation risks and safety checks for fact/forecast/cash/card logic.

Expected reports:

- Category grouping recommendation.
- Synonym and phrase normalization report.
- Category-inflation and finance-safety report.

Exit criteria:

- Current `Claudia Z` workspace remains current-only and keeps cash balance `15262.00`.
- `Claudia Z Archive Raw History` remains raw-only with zero operational entries.
- No new category is accepted without a real financial reporting need.
- Actor/person names are not treated as categories.
- Private/external income, debt, cash movements, and carry/opening rows are not mapped to `commercial_income`.
- User decision: `сейф` is cash context, not a separate flow/category.
- User decision: income rows are owner funding unless the description explicitly contains commercial yacht revenue wording such as `аренда`, `сдача яхты`, `чартер`, or similar.
- Draft rules are stored as candidate rules only, not applied blindly.

Risks:

- Historical rows contain cash movements, private owner flows, debts, and carry rows that look like income/expense.
- Broad regex rules can hide review debt and produce false confidence.
- Google Drive source files include blocked/read-problem files that must remain visible as import exceptions.

## Agent Reports Received

### Linguistic Taxonomy Agent

Recommendation:
Keep categories flat in data and use a report/UI grouping layer.

Recommended grouping:

```text
Money / Balance
  commercial_income
  cash_topup_from_card
  external/private income -> no category yet; flow + direction + notes/review

Crew
  crew

Voyage Operations
  fuel
  marina_ports
  berth

Maintenance
  service_water
  tech_parts
  dry_dock
  tender

Household & Guests
  provisions
  cleaning
  interior

Overhead
  media_comms
  admin_legal

Review
  other
```

Do not create categories for people, one-off items, brands, suppliers, payment method, weak words, or unclear rows.

### Vocabulary Normalization Agent

Confirmed compact dictionary groups:

- `marina_ports`: marina, стоянка, порт, причал, паром, Porto, Рипосто, Бари, Котор, Ластово, Цавтат, Полиньяно, Портороса.
- `service_water`: сервис, ремонт, ТО, обслуживание, диагностика, мастер, консервация, механик, генераторы, опреснитель, Miele, холодильник, насос.
- `fuel`: заправка, дозаправка, топливо, бензин, дизель, fuel, diesel, petrol, liters.
- `crew`: зп, зарплата, аванс, под отчет, экипаж, капитан, хостесс, помощник.
- `admin_legal`: документы, печать, виза, тур регистрация, таможня, дьюти фри, адвокат, агент, сбор, такса.
- `provisions`: продукты, еда, рыба, морепродукты, фрукты, овощи, хлеб, мясо, напитки, вино, пиво, рынок.
- `tech_parts`: запчасти, расходники, материалы, инструмент, кабель, анод, болт, фильтр, аккумулятор, клей, реле, датчик.

Important warning:
`вода электричество` must not be classified by the single word `вода`. It is marina/utilities context.

### QA / Category Inflation Audit Agent

Safety conclusions:

- Do not classify private/external income as `commercial_income`: crypto, bank top-ups, owner money, debt returns, ЛВ, Александр, Данил, Germany.
- Treat non-commercial income as owner funding / owner contribution with source person from the description where possible.
- Do not import archive rows as operational entries.
- Do not collapse repeated identical rows automatically.
- Do not silently move uncertain rows out of `other_review`.
- Keep card-to-cash movement as two-sided movement where source evidence exists.
- Keep forecast separate from archive raw history.

## User Decisions Added

### Safe / Cash

`сейф` equals cash context.

Rules:

- `сейф` is not a category.
- `сейф` is not a separate flow in the MVP dictionary model.
- Phrases such as `из сейфа`, `взял из сейфа`, `получено из сейфа`, `принял из сейфа`, `в сейф`, `убрал в сейф` mark internal cash source/location.
- These rows must not become `commercial_income`.
- Whether a safe movement affects a specific operational month balance depends on the source report chain, but the dictionary must treat it as cash movement context, not revenue.

### Owner Funding / Commercial Income Boundary

Income rows are owner funding unless the description clearly says commercial yacht revenue.

Commercial revenue words:

```text
аренда
сдача яхты
чартер
оплата чартера
booking
charter
rental
```

Owner/source funding examples:

```text
от Александра
Александр
ЛВ
Леонид Владимирович
от Данила
из Германии
через крипту
из крипты
приход из рф
пополнение служебной карты
```

Rule:

- Keep these as income/funding movement with source actor where possible.
- Do not map them to `commercial_income`.
- Store the person/source from description as actor/source metadata when parser support exists.

## Local Corpus Evidence

Generated by:

```bash
php scripts/v2_export_claudia_z_dictionary_corpus.php storage/imports/claudia-z-dictionary
```

Corpus:

- rows total: `3338`
- unique descriptions: `1192`
- generated files:
  - `storage/imports/claudia-z-dictionary/description-corpus.json`
  - `storage/imports/claudia-z-dictionary/token-frequency.json`
  - `storage/imports/claudia-z-dictionary/rule-coverage.json`
  - `storage/imports/claudia-z-dictionary/unrecognized-rows.json`

Initial coverage from draft rules:

```text
needs_review=1124
provisions=515
crew=218
cleaning=116
tech_parts=114
fuel=90
service_water=88
media_comms=78
admin_legal=56
marina_ports=48
tender=42
berth=35
interior=32
commercial_income=10
dry_dock=6
```

High-frequency review themes:

- money movement / balance / owner flows: `сейф`, `остаток`, `ЛВ`, `Александр`, `крипта`, `пополнение карты`, `вернул`.
- admin/legal candidates: `виньета`, `печать`, `радио лицензия`, `таможня`, `тур регистрация`.
- service/technical candidates: `опреснитель`, `генератор`, `ТО`, `Seabob`, `спас плот`, `навигация`.
- household/personal candidates: `цветы`, `такси`, `прачка`, `аптека`, `алкоголь`.

## Director Decision

Accepted:

- Do not expand category list now.
- Treat archive/raw history as dictionary training material only.
- Keep `other_review` as a visible review buffer.
- Build candidate phrase rules and semantic flags before applying anything to operational entries.
- Treat `сейф` as cash context.
- Treat non-commercial income as owner funding with source actor/description, not `commercial_income`.

Rejected:

- Creating `transport`, `medical`, `gifts`, `owner_personal`, or `utilities_marina` as new leaf categories now.
- Auto-classifying owner/private funding as `commercial_income`.
- Using names as categories.

## Next Work

1. Review candidate rules with the user by disputed group.
2. Add parser support for semantic markers separate from categories:
   - `actor`
   - `supplier`
   - `money_movement`
   - `tender_related`
   - `debt_or_return`
   - `carry_or_opening`
3. Build a small review screen for archive dictionary training:
   - raw description
   - suggested category
   - matched phrase
   - risk flag
   - accept / change / keep review
