# SPRINT-24R — Discussion Pack 01

## Purpose

Turn the largest `discussion_required` Claudia Z dictionary candidates into owner decisions.

This is not a category-rule implementation file. It is a decision sheet for vocabulary training.

Source:

- `storage/imports/claudia-z-dictionary/sprint-24-review-candidates.json`
- `storage/imports/claudia-z-dictionary/description-corpus.json`

## Director Rule

One category is approved from this pack:

- `transport_expenses` — `Транспортные расходы`.
- `representation_expenses` — `Представительские расходы`.

For all other groups, use only:

- existing category candidate;
- semantic-only marker;
- ignore/balance context;
- keep in discussion.

## 1. Transport

Examples:

- `Такси`
- `трансфер`
- `аренда авто`
- `билеты в чг и обратно`

Default decision:

- create/use `transport_expenses`.
- map tickets, car rentals, taxis, and transfers into this one category.
- never map `аренда авто` to `commercial_income`.

Rule:

- `такси`, `трансфер`, `аренда авто`, `билеты`, `перелет/перелёт`, `авиа`, `поезд`, `автобус` -> `transport_expenses`.

Decision status:

- approved by owner.

## 2. Flowers And Gifts

Examples:

- `цветы`
- `Розы-подарок Алине`
- `Navar + презент`

Default decision:

- `цветы` -> `provisions`.
- `розы`, `подарок`, `презент`, or named gift wording -> `representation_expenses`.
- `долг`, `возврат`, `подотчет` context stays `discussion_required`.

Reason:

- Plain flowers are treated as guest/household hospitality.
- Gifts, presents, roses, and business hospitality are representation/diplomacy expenses when they are in the yacht/business interest.
- Debt/return wording is accounting context and must not be hidden by a category keyword.

Decision status:

- approved by owner.

## 3. Pharmacy And Medical

Examples:

- `аптека`
- `лекарства для Наталии`
- `лекарства под заказ Натальи`

Default decision:

- keep `discussion_required`.

Reason:

- No clean existing category unless it is clearly boat medical kit or guest/crew operational expense.

Owner question:

- Do yacht/crew medical purchases belong in an existing category, or do medical/personal rows stay manual?

## 4. Teak And Surface Care

Examples:

- `Пропитка тика`
- `Расходники тик.`
- `Расходники по тику`
- `материалы по тику`
- `замена тиковых досок на флае и платформе`

Default decision:

- phrase-level review.

Candidate split:

- teak cleaner/sealer/polish consumables -> likely `cleaning` or `tech_parts`.
- teak boards/material/labor -> likely `service_water` or `tech_parts`.

Owner question:

- Should teak care be treated as cleaning consumables, technical materials, or service/maintenance depending on wording?

## 5. Debt, Return, Accountable Money

Examples:

- `Вернул в кеш кассу`
- `мой долг`
- `женя под отчет`
- `возврат ндс`
- `Вернул Леониду Владимировичу`

Default decision:

- semantic-only `debt_or_return`;
- do not infer category from debt/return wording alone.

Owner question:

- Should these rows remain visible as cash/accountable context and require manual final category when needed?

## 6. Card/Cash Remainders And Balance Rows

Examples:

- `Остались на карте. Сдал`
- `Остаток переход`
- `остаток`
- `Начальный остаток`
- `Итоговый остаток`

Default decision:

- `Остаток/Начальный/Итоговый` -> ignore/balance context.
- `Остались на карте. Сдал` -> discussion-required money movement.

Owner question:

- Should `сдал`, `остались на карте`, and similar handover rows become a separate semantic money-movement marker?

## 7. Agents, Borders, Transit

Examples:

- `агент`
- `агент на границе`
- `агент корфу, транзит лог`
- `транзитлог`
- `закрытие границы`

Default decision:

- single `агент` stays review.
- agent + border/customs/transit log likely `admin_legal`.

Owner question:

- Is `агент` normally a maritime/admin agent in Claudia Z records, or too ambiguous without context?

## 8. Personal Or Non-Yacht Context

Examples:

- `Оплатил с карты для себя`
- `Аудио система для РФ - задаток`
- `Музыка на катер рф`
- `Колодки порше передние`
- `Колодки порше задние`
- `мой кредит`
- `айфон`

Default decision:

- keep `discussion_required`.
- actor/source names are not categories.

Owner question:

- Should these be excluded from yacht operational training, or kept as Claudia Z workspace history with manual category?

## 9. Household, Interior, Guest Use

Examples:

- `Кухонные принадлежности`
- `Скатерть`
- `нарды`
- `шезлонги`
- `2 подушки с наволочками`
- `Чехлы на нос`
- `форма`

Default decision:

- discussion-required until split rules are approved.

Possible split:

- table/linen/soft goods -> `interior`;
- kitchen consumables -> `provisions` or `interior`;
- guest/game/leisure goods -> needs owner rule.

Owner question:

- Should guest/household objects default to `interior`, or remain review unless clearly food/cleaning/technical?

## 10. Safe Candidates For Existing Categories

These look safe enough for later guarded rules, but should not be auto-applied in this discussion pack.

Examples:

- `Прачка` -> `cleaning`
- `мусор`, `вывоз мусора` -> `cleaning`
- `Покраска seabob` -> `tender`
- `Переходники шлангов` -> `tech_parts`
- `За ТО опреснителя` -> `service_water`
- `ТО генераторы` -> `service_water` or guarded technical service
- `Виньета` -> `admin_legal`
- `Оплата радио лецензии на яхту` -> `admin_legal`
- `Изготовление печати Sanada ltd` -> `admin_legal`
- `Нетфликс`, `картина тв` -> `media_comms`

Default decision:

- approve as candidate families after owner review.

Owner question:

- Which of these can be accepted as safe guarded dictionary rules immediately?

## Red Lines

- `сейф` is cash location context only.
- non-yacht-rental income is owner funding metadata, not commercial income.
- `brokerage` and `agency fee` are not commercial income without explicit yacht/charter wording.
- single `вода` is not enough for `provisions`.
- mixed rows like `сим-карта и фрукты` remain review.
- `тендер остаток за зиму и сервис` remains review because it mixes tender, balance, and service context.
