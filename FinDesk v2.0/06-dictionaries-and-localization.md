# 06 — Dictionaries and Localization

## Principle

FinDesk v2.0 must be flexible without becoming random.

The engine uses dictionaries, weighted rules, language variants, suppliers, actor detection, and manual corrections.

No black-box AI is required for MVP.

## Languages

```text
RU — Russian
EN — English
IT — Italian
ES — Spanish
DE — German
BCMS / Local — Bosnian, Croatian, Montenegrin, Serbian
```

Use `BCMS / Local` as neutral product label.

## Dictionary levels

1. Category keywords.
2. Category phrases.
3. Supplier names.
4. Actor/people markers.
5. Role words.
6. Stop words.
7. Internal flow movement phrases.
8. Workspace-specific learned rules.

## Stop words

Do not create category rules from generic words:

```text
купил
оплатил
сегодня
вчера
лодка
boat
cash
card
money
paid
bought
for
на
для
```

## Universal Linguistic Rule Tiers

The product dictionary is not a flat map from one word to one category.

Use rule tiers:

```text
strong category phrase
specific object word
weak role/context word
semantic blocker
review trigger
workspace-specific alias
```

Strong phrase examples:

```text
обед с агентом -> representation_expenses
аренда яхты -> commercial_income on income rows
доставка фильтра -> tech_parts because filter is the object
```

Weak context examples:

```text
агент
магазин
доставка
курьер
обед
кафе
ресторан
инвентарь
```

Weak context can suggest a category, but it must not train a universal rule by itself.

Semantic markers:

```text
weak_dictionary_context = generic word suggested category but confidence is weak
mixed_dictionary_context = more than one category family is present
```

Examples:

```text
-50 агент -> current_boat_expenses + weak_dictionary_context
-15 доставка -> transport_expenses + weak_dictionary_context
-15 доставка фильтра -> tech_parts + mixed_dictionary_context
-50 обед с агентом -> representation_expenses
```

These markers are metadata for review/training. They must not change physical balances.

## Actor recognition

A person name is not a category.

Examples:

```text
-500 Вова аванс
-87 Вова купил кабель
```

Actor is Вова. Category depends on context.

## Category set

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
guest_trip_support
guest_cash_issued
representation_expenses
interior
cleaning
media_comms
transport_expenses
current_boat_expenses
admin_legal
cash_topup_from_card
other
```

`transport_expenses` covers tickets, car rentals, taxis, and transfers.

`current_boat_expenses` covers routine boat overhead that is not repair, parts, documents, or guest hospitality: branding, crew uniform/special clothing, agents, generic store/shop rows (`магазин`), household goods (`хоз товары`), generic inventory / `инвентарь`, boat printer rows, and bank commissions. Kitchen inventory such as `инвентарь по кухне` remains `interior`.

`guest_trip_support` covers non-food guest support during a trip: scooters, paragliding, musicians, museum/national-park entries, fishing gear / `снасти`, masks when they are activity gear, chef charging-device rows, and similar guest activity/support expenses.

`guest_cash_issued` covers cash issued or spent for guests, including `ЛВ`, `Расходы ЛВ`, `игра ЛВ`, `передал ЛВ`, `отдал ЛВ`, and similar guest cash handoff wording. Clear incoming owner funding remains owner/source funding; this category is for expense-side guest cash.

`representation_expenses` covers gifts, presents, roses, birthday decoration, and explicit business hospitality such as business lunch/dinner or meeting expenses. Plain food/provision rows remain `provisions`; actor names remain actors, not categories.

Crew tips / `чаевые` belong to `crew`.

`service_water` is displayed as "Сервисные работы" and covers work verbs such as replacement, mounting, welding, conservation, system tests, fire-extinguisher service, black-tank / `черные танки` service context, and similar service work. Purchase verbs such as `купил`, `покупка`, `заказал`, and `приобрел` are not service work by themselves; when paired with a technical object, they belong to `tech_parts` or review. Parts/equipment words such as winch, thruster, compressor, dimmer, gelcoat, fenders, mooring lines, pressure regulator, `контролька кондея`, teak materials/sealers/cleaners, toilet control blocks, fridge hinges including misspellings such as `петля хододильник`, tank access hatches, chain rollers, and cup holders belong to `tech_parts` when the row is about the part/material rather than the work.

`provisions` also covers trip shopping, seafood, plain water purchases, cosmetics, shampoos, marine pharmacy, mask/flipper rows, mask spray, and plain alcohol rows such as champagne, vodka, Grey Goose, Moet, Veuve Clicquot, and Aberlour unless gift/business-hospitality wording is explicit. Kitchen/interior utensils, kitchen appliances, cushion rework, covers, cushions, and sunbeds belong to `interior`.

`media_comms` covers media, connectivity, subscriptions, TV/radio/internet, Starlink, SIM-card wording, Sonos/audio blocks, and modem rows.

`transport_expenses` covers jet-ski transport, car rentals/rentacar, airlines such as Air Serbia, logistics, guest pickup/transfer wording, and car refuel rows such as `запрака авто`. `berth` covers storage/garage/standing wording plus marina utility rows such as `электричество`, `вода электричество`, and extra mooring / `муринг`; single `вода` is a plain water purchase and belongs to `provisions`. Sea-entry, Corinth passage, TEPAI, and entry-tax wording belongs to `marina_ports`.

`admin_legal` covers yacht/company documents, stamps/`печати`, marine certificates including typos, visas/crew lists, taxes/trust-company taxes, bank-transfer tax wording, and overstay / stay-regularization wording.

`crew` covers crew roles, crew tips, and temporary helper wording such as `работник в помощь`.

`Остались на карте. Сдал`, `Оплатил с карты для себя`, `Вернул в кеш кассу`, and similar settlement rows are semantic-only money movement / private settlement context, not expense categories.

Debt, loan, credit, return, and accountable-cash wording such as `долг`, `кредит`, `займ`, `вернул`, `возврат`, `под отчет`, and `подотчет` is a semantic marker, not an expense category by itself. Explicit accountable wording (`под отчет`, `подотчет`) and clear loan/debt wording without a concrete operational category belong to the lower accounting block. If the same row has a clear yacht operational context such as `долг за гараж` or `долг таможне`, the operational category stays editable and the row must not be blocked by lower accounting.

Administrator-personal debt wording is stricter and overrides lower accounting: `мой кредит`, `моя часть кредита`, `кредит себе`, `последний кредит`, `мой долг`, `для себя`, `себе`, `домой`, `с тему`, `temu`, and `мото навигатор` belong to `admin_debt`. This block is a liability/reporting block, not an operational expense category and not employee/guest accountable money.

Personal/non-yacht wording such as `Порше`, `катер РФ`, `для РФ`, `для отправки в РФ`, `аудио система для РФ`, and `мото навигатор` is semantic-only non-yacht context. It must not train Claudia Z yacht operational categories.

Actor/source names such as `Александр`, `Саша` / `Саше`, `Олег`, `Вова`, `Володя`, `Наталия`, `Арик`, and `Данил` are context markers. They become owner funding only for clear non-commercial incoming funding rows. Expense-side guest cash wording such as `ЛВ`, `передал ЛВ`, `отдал ЛВ`, `игра ЛВ`, and `расходы ЛВ` maps to `guest_cash_issued`.

Generic incoming `принял` maps to owner/source funding unless paired with safe/cash-location or actor transfer context.

`цоги мар`, `цогимар`, and `cogimar` are manual merchant aliases for a fish shop name as written by a crew member. Do not train dictionary categories from these names. If such rows occur operationally, keep them in manual `other_review` and correct the row text/category by hand.

Unsortable rows such as `айфон`, `планшет`, `обезналич`, `консьерж`, `книжка моряка`, and `подставка под динги` stay in `other` with `other_review`.

`commercial_income` is an income category, not an expense category. It covers explicit yacht rental/charter/business inflows only. Generic brokerage or agency wording is not enough without yacht/charter context.

## Category Map For Owner Decisions

```text
crew — Экипаж
commercial_income — Коммерческий приход
non_commercial_income — Некоммерческие поступления
dry_dock — Сухой док
berth — Стоянка
marina_ports — Марины и портовые
service_water — Сервисные работы
tech_parts — Техчасть и запчасти
tender — Тендер / тузик
fuel — Топливо
provisions — Продукты и гости
guest_trip_support — Обеспечение гостей в походе
guest_cash_issued — Выданные наличные гостям
representation_expenses — Представительские расходы
interior — Интерьер и быт
cleaning — Клининг и химия
media_comms — Мультимедиа и связь
transport_expenses — Транспортные расходы
admin_legal — Админка / документы
current_boat_expenses — Текущие лодочные расходы
cash_topup_from_card — Пополнение cash с карты
other — Другие расходы
```

## Category examples

### commercial_income

```text
аренда яхты, сдача яхты, чартер, оплата чартера, yacht rental, yacht booking, charter, charter fee, noleggio yacht, alquiler de yate, chárter, najam jahte, iznajmljivanje jahte, yacht Vermietung
```

Use this category for real income from explicit yacht rental/charter activity.

Do not use it for opening balance, card-to-cash top-up, private replenishment, debt return, correction, car rental, or generic commission/brokerage/agency wording unless the description clearly points to yacht/charter revenue.

### non_commercial_income

```text
пополнение, внес, внёс, получил от, передал, от владельца, из сейфа, owner funding, owner top-up, private funding
```

Use this category for owner/source cash funding and other non-commercial incoming cash. It remains physical `external_cash_income` in monthly formulas. Do not use it for yacht rental/charter revenue or card-to-cash movements.

Review-only weak commercial words:

```text
комиссия
комиссионные
агентские
брокерские
agency fee
brokerage
booking
commission
```

### cash_topup_from_card

```text
снял с карты, снятие с карты, банкомат, atm, cash withdrawal, card to cash, prelievo, retiro, Abhebung
```

### other

Fallback category. Must be highlighted and reviewed.

## Rule weighting

Each matched rule adds weight to a category.

Example:

```text
-42 заправка тузика
```

- `заправка` supports fuel.
- `тузик` supports tender.

Primary category can be fuel, with tender marker as metadata.

## Manual learning

When admin changes category, system can offer:

```text
Remember this rule?
```

But it must not create rules automatically from weak words.

Store learned rules as workspace-specific by default.

## Explainability

Every suggestion must be explainable:

```text
Suggested: fuel
Reason: matched `заправка`, `diesel`, `fuel`
Confidence: 0.87
```

## Confidence and Review Reasons

The dictionary engine must expose why a row is trusted or sent to review.

Public explainability fields:

```text
classification_decision
confidence
review_reason
matched_signals
blockers
```

These fields are dictionary-training metadata only.

They must not change:

```text
entry amount
sign
flow
entry type
category code
status
cash/card totals
monthly reports
Layer 1 reports
```

Use `review_reason` to route human decisions:

```text
weak_only = one weak context word suggested a category
mixed_context = more than one category family appears in the row
blocked_by_personal = non-yacht/private context is present
blocked_by_debt = debt/loan/return/accountable wording is present
private_money_movement = private settlement or cash/card movement wording is present
commercial_income_unclear = income has commission/agency/brokerage wording without yacht/charter wording
other_review = fallback other category
no_category = no accepted category or semantic income marker
```

Universal dictionary learning rules:

```text
strong accepted rows may train universal rules after audit
weak_only rows require human approval before training
mixed_context rows require human approval before training
blocked rows must not train operational category rules
workspace merchant aliases stay workspace/local until explicitly promoted
owner funding is `non_commercial_income`, not commercial income
```

Commercial-income guard:

```text
agency fee, brokerage, commission, агентские
```

These are review-only words unless the row also contains explicit yacht/charter/rental revenue wording.

## Dictionary Training Decisions

Dictionary review does not train by itself.

Training requires an explicit reviewer decision:

```text
defer
reject_training
approve_existing_guess_local
correct_category_local
mark_semantic_blocked
propose_universal_candidate
```

MVP rule:

```text
approve_existing_guess_local -> workspace-local category rule
correct_category_local -> workspace-local category rule
all other decisions -> decision/audit only
```

Universal promotion is not part of normal review.

`propose_universal_candidate` records an audited candidate only. It must not write a universal rule.

Rows with these blockers must not create category rules:

```text
non_yacht_or_personal
debt_or_return
money_movement
missing_yacht_charter_phrase
card_income_manual_guard
```

Rows with these review reasons must not create category rules:

```text
blocked_by_personal
blocked_by_debt
private_money_movement
commercial_income_unclear
card_income_not_allowed
```

Every decision must preserve:

```text
source row id
source file/sheet/row
raw row
description
current rule guess
confidence
review reason
matched signals
blockers
semantic markers
reviewer note
reviewer id
decision timestamp
```

This preserves learning context while keeping operational accounting separate.

## Dictionary Assistant And Mr. Smith

FinDesk may use an assistant layer to help reviewers understand dictionary-training rows.

The assistant is advisory only.

It may:

```text
explain current category guess
show matched signals
show blockers
suggest safer local patterns
suggest requires_any constraints
suggest excludes_any constraints
prepare human-readable review questions
```

It must not:

```text
create operational entries
modify operational entries
change balances
change reports
change imports
change parser primitives
write universal rules
promote universal candidates
replace reviewer decisions
```

`Mr. Smith` is the planned beta internet/resource/supplier/store matching agent.

Mr. Smith may help identify whether unclear text is a possible:

```text
store
supplier
service company
marina
restaurant
transport provider
other public resource
```

Mr. Smith evidence must stay separate from category truth.

Allowed Mr. Smith output:

```text
possible external match
possible business type
possible location
aliases/transliterations
source URL/domain
retrieved_at
confidence
reason for uncertainty
suggested reviewer question
```

Forbidden Mr. Smith output:

```text
final accounting category
final financial classification
universal rule approval
entry mutation instruction
balance/report mutation instruction
```

Internet evidence cannot train by itself.

A reviewer must explicitly decide whether the evidence becomes:

```text
local rule support
manual correction support
rejected evidence
deferred evidence
```

Merchant names and supplier names are not universal dictionary truth.

Example:

```text
цоги мар / цогимар / cogimar
```

These can be local review evidence for one workspace, but must not become a global category rule.

Mr. Smith must use privacy minimization:

```text
send the smallest safe query
strip amounts and balances
strip private notes
strip actor/person names unless explicitly approved
strip source row identifiers
keep tenant/workspace isolation
store provenance for audit
```

Production internet lookup requires explicit user consent and visible provenance.
