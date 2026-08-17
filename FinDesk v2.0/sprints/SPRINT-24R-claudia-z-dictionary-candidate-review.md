# SPRINT-24R — Claudia Z Dictionary Candidate Review

## Director Opening

Review the Claudia Z raw history dictionary candidates without changing operational finance logic.

This sprint is candidate staging only: discussion-ready rules, exclusions, and semantic markers.

## Agent Assignments

- Linguistic Review Agent — recurring human wording and compact rule candidates.
- Financial Logic Reviewer — prevent category mistakes and protect finance invariants.
- QA Acceptance Agent — verify artifacts and non-mutation guarantees.

## Current Export

- Source rows: `3338`
- Unique descriptions: `1192`
- Candidate descriptions: `189`
- Manual merchant aliases excluded from dictionary training: `4`
- JSON: `storage/imports/claudia-z-dictionary/sprint-24-review-candidates.json`

## Action Counts

- `ignore_or_balance_context`: `50`
- `existing_category_candidate`: `48`
- `semantic_only`: `91`

## Top Candidates

| Count | Proposed action | Category | Marker | Description | Reason |
|---:|---|---|---|---|---|
| 34 | `ignore_or_balance_context` |  |  | Остаток переход | balance/summary wording is not an expense/income category |
| 23 | `existing_category_candidate` | guest_cash_issued | actor_context | ЛВ | category follows transaction wording; actor name remains context marker |
| 18 | `semantic_only` |  | cash_location_safe | взял из сейфа | safe/сейф is cash location context only |
| 12 | `semantic_only` |  | cash_location_safe | Из Сейфа | safe/сейф is cash location context only |
| 12 | `semantic_only` |  | owner_funding | Александр. Перевод на карту Усова | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 12 | `semantic_only` |  | owner_funding | Пополнение служебной карты | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 10 | `semantic_only` |  | money_movement | Вернул в кеш кассу | owner decision: card/cash/private settlement wording is a separate money-movement block, not an expense category |
| 10 | `semantic_only` |  | money_movement | Оплатил с карты для себя | owner decision: card/cash/private settlement wording is a separate money-movement block, not an expense category |
| 9 | `semantic_only` |  | owner_funding | Александр | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 9 | `semantic_only` |  | money_movement | Остались на карте. Сдал | owner decision: card/cash/private settlement wording is a separate money-movement block, not an expense category |
| 9 | `semantic_only` |  | owner_funding | Пришло из крипты | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 8 | `semantic_only` |  | debt_or_return | мой долг | owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category |
| 7 | `ignore_or_balance_context` |  |  | остаток | balance/summary wording is not an expense/income category |
| 6 | `semantic_only` |  | owner_funding | от Александра | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 6 | `semantic_only` |  | owner_funding | Леонид Владимирович | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 6 | `semantic_only` |  | owner_funding | Принял из сейфа | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 5 | `semantic_only` |  | cash_location_safe | Получено из сейфа | safe/сейф is cash location context only |
| 5 | `semantic_only` |  | non_yacht_or_personal | Аудио система для РФ - задаток | personal or non-yacht context must not train yacht operational categories |
| 4 | `semantic_only` |  | cash_location_safe | убрал в сейф | safe/сейф is cash location context only |
| 4 | `semantic_only` |  | cash_location_safe | Из сейфа на стоянку | safe/сейф is cash location context only |
| 4 | `semantic_only` |  | owner_funding | От Саши | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 4 | `semantic_only` |  | non_yacht_or_personal | Музыка на катер рф | personal or non-yacht context must not train yacht operational categories |
| 4 | `semantic_only` |  | non_yacht_or_personal | Колодки порше передние | personal or non-yacht context must not train yacht operational categories |
| 4 | `semantic_only` |  | non_yacht_or_personal | Колодки порше задние | personal or non-yacht context must not train yacht operational categories |
| 4 | `ignore_or_balance_context` |  |  | Начальный остаток | non-money heading/context row is not dictionary vocabulary |
| 3 | `existing_category_candidate` | guest_cash_issued | actor_context | Расходы ЛВ | category follows transaction wording; actor name remains context marker |
| 3 | `existing_category_candidate` | guest_cash_issued | actor_context | игра ЛВ | category follows transaction wording; actor name remains context marker |
| 3 | `semantic_only` |  | debt_or_return | возврат ндс | owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category |
| 3 | `ignore_or_balance_context` |  |  | Итоговый остаток | non-money heading/context row is not dictionary vocabulary |
| 3 | `ignore_or_balance_context` |  |  | Общий приход | non-money heading/context row is not dictionary vocabulary |
| 3 | `ignore_or_balance_context` |  |  | Общий расход | non-money heading/context row is not dictionary vocabulary |
| 3 | `ignore_or_balance_context` |  |  | женя под отчет | non-money heading/context row is not dictionary vocabulary |
| 2 | `semantic_only` |  | owner_funding | Принял сейфы | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 2 | `semantic_only` |  | cash_location_safe | в сейф | safe/сейф is cash location context only |
| 2 | `ignore_or_balance_context` |  |  | приход | balance/summary wording is not an expense/income category |
| 2 | `semantic_only` |  | owner_funding | приход Александр | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 2 | `semantic_only` |  | cash_location_safe | положил в сейф | safe/сейф is cash location context only |
| 2 | `semantic_only` |  | owner_funding | получил от Александра | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 2 | `semantic_only` |  | owner_funding | от Наталии | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 2 | `semantic_only` |  | owner_funding | получил от ЛВ | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 2 | `existing_category_candidate` | guest_cash_issued | actor_context | передал ЛВ | owner decision: issued/spent cash for guests belongs to guest cash issued |
| 2 | `ignore_or_balance_context` |  |  | переходящий остаток | balance/summary wording is not an expense/income category |
| 2 | `semantic_only` |  | debt_or_return | мой кредит | owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category |
| 2 | `semantic_only` |  | debt_or_return | долг за гараж | owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category |
| 2 | `semantic_only` |  | debt_or_return | Возврат НДС покупки порто | owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category |
| 2 | `semantic_only` |  | debt_or_return | долг за пошив сидушки | owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category |
| 2 | `existing_category_candidate` | admin_legal |  | транзитлог | documents, licenses, taxes, company/admin context |
| 2 | `existing_category_candidate` | guest_cash_issued | actor_context | заряднаястанция с проводами на всё для лв | category follows transaction wording; actor name remains context marker |
| 2 | `semantic_only` |  | actor_context | перевел для друга Александра | actor/source names alone are semantic context markers; expense/mixed rows must not train a category |
| 1 | `semantic_only` |  | owner_funding | принял | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `semantic_only` |  | owner_funding | Получил от александра (3 500 000 р в мск) | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `semantic_only` |  | owner_funding | от арика 30000 долларов | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `semantic_only` |  | owner_funding | от александра  27200 долларов | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `semantic_only` |  | owner_funding | Александр через Юру | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `semantic_only` |  | owner_funding | 0,82 курс евро к усдт | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `semantic_only` |  | owner_funding | из крипты | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `existing_category_candidate` | guest_cash_issued | actor_context | дал ЛВ | owner decision: issued/spent cash for guests belongs to guest cash issued |
| 1 | `semantic_only` |  | debt_or_return | Вернул Леониду Владимировичу | owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category |
| 1 | `semantic_only` |  | owner_funding | саша | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |
| 1 | `semantic_only` |  | owner_funding | получил от Наталии 15000 долларов | clear incoming owner/source funding stays semantic-only even when actor is ЛВ |

## Non-Goals

- New approved categories in this sprint: `transport_expenses`, `representation_expenses`, `current_boat_expenses`, `guest_trip_support`, `guest_cash_issued`.
- Do not auto-approve category rules.
- Do not convert archive/raw history to operational entries.
- Do not use review sums as financial report totals.

## Acceptance

- Candidate file is reproducible from `description-corpus.json`.
- Every top candidate has proposed action and reason.
- `сейф` remains semantic-only cash location context.
- Owner funding remains semantic-only unless explicit yacht rental/charter wording exists.
- `аренда авто`, taxis, transfers, tickets, deliveries, and rentals map to `transport_expenses`, never to commercial income.
- `брендирование`, crew uniform/special clothing, `агент`, generic `магазин`, and bank commissions map to `current_boat_expenses`.
- `забрал свои` maps to `current_boat_expenses`; loan/debt/credit/return/accountable wording stays in the separate lower accounting block.
- `самокат`, scooters, paragliding, musicians, and chef charging-device rows map to `guest_trip_support`.
- Expense-side `ЛВ`, `передал ЛВ`, `отдал ЛВ`, `игра ЛВ`, and `расходы ЛВ` map to `guest_cash_issued`; clear incoming owner funding remains owner/source funding.
- `контролька кондея` maps to `tech_parts`; unsortable rows such as `айфон`, `планшет`, and `обезналич` stay `other` / `other_review`.
- Crew tips / `чаевые` map to `crew`.
- `докупка необходимого в поход`, trip purchases, cosmetics, shampoos, marine pharmacy, mask spray, and plain water purchases map to `provisions`.
- Plain alcohol rows such as champagne, vodka, and Grey Goose map to `provisions` unless gift/business-hospitality wording is explicit.
- `sonos`/`сонос` and modem rows map to `media_comms`.
- Generic incoming `принял` maps to owner/source funding unless paired with safe/cash-location or actor transfer context.
- Kitchen/interior utensils, cushion rework, covers, cushions, and sunbeds map to `interior`.
- Jet-ski transport maps to `transport_expenses`; `склад`, `гараж`, `электричество`, and `вода электричество` map to `berth`; single `вода` maps to `provisions`.
- Sea-entry, Corinth passage, TEPAI, and entry-tax wording maps to `marina_ports`.
- Replacement, mounting, welding, conservation, system tests, and fire-extinguisher work maps to `service_water`, displayed as service works.
- Bow/stern thruster, winch, compressor, dimmer, gelcoat, fenders, mooring lines, and pressure regulator map to `tech_parts` when they are parts/equipment rather than service work.
- `цоги мар`, `цогимар`, and `cogimar` are manual merchant aliases: keep operational rows in `other_review`, but exclude these names from dictionary training and candidate counts.
- `аптека` maps to `provisions`; hotels map to guest/provision-style yacht guest expenses.
- `brokerage` and `agency fee` remain discussion-required unless explicit yacht/charter wording is present.
- `сим-карта и фрукты`, settlement/card/cash movement rows, and `тендер остаток за зиму и сервис` stay review/discussion cases.
- `цветы` maps to `provisions`; `подарок`, `презент`, `розы`, birthday decoration, and explicit business hospitality map to `representation_expenses`.
- Generic `инвентарь` maps to `current_boat_expenses`; `инвентарь по кухне` remains `interior`.
- Debt/loan/credit/return/accountable wording remains semantic-only lower accounting context even when another category word is present.
- Personal/non-yacht wording such as `Порше`, `катер РФ`, `для РФ`, and `аудио система для РФ` remains semantic-only and must not train yacht operational categories.
- Operational finance tables are not mutated.
