<?php

declare(strict_types=1);

final class ClaudiaZDictionaryReviewPreparer
{
    /** @var array<string, array{pattern: string, action: string, suggested_category: ?string, semantic_marker: ?string, reason: string}> */
    private array $rules = [
        'balance_or_summary_row' => [
            'pattern' => '/(^|\\s)(остаток|начальный остаток|итоговый остаток|остаток переход|общий приход|общий расход|итого|приход$|расход$|добавлено в этот пересчет|новый приход\\s*\\/\\s*заправка|продолжение отчета|июнь доп|карта \\([0-9-]+\\)|отчет [^,.;]+)(\\s|$)/u',
            'action' => 'ignore_or_balance_context',
            'suggested_category' => null,
            'semantic_marker' => null,
            'reason' => 'balance/summary wording is not an expense/income category',
        ],
        'safe_cash_location' => [
            'pattern' => '/сейф|сеф/u',
            'action' => 'semantic_only',
            'suggested_category' => null,
            'semantic_marker' => 'cash_location_safe',
            'reason' => 'safe/сейф is cash location context only',
        ],
        'guest_cash_issued' => [
            'pattern' => '/^(?:[+-]?\s*\d+(?:[.,]\d+)?\s+)?(?:лв|леонид владимирович)$|расходы лв|общая потраченная сумма лв|игра лв|(?:передал|отдал|дал|выдал)\s+(?:лв|леонид владимирович|арику?|саше?|гост)/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'guest_cash_issued',
            'semantic_marker' => 'actor_context',
            'reason' => 'owner decision: issued/spent cash for guests belongs to guest cash issued',
        ],
        'owner_funding_source' => [
            'pattern' => '/(^|\\s)(принял|лв|леонид владимирович|александр|саша|от саши|от александра|натали|наталь|арик|арика|приход александр|крипт|usdt|усдт|пополнение служебной карты)(\\s|$)/u',
            'action' => 'semantic_only',
            'suggested_category' => null,
            'semantic_marker' => 'owner_funding',
            'reason' => 'non-commercial income source must stay owner funding unless yacht rental is explicit',
        ],
        'non_yacht_or_personal' => [
            'pattern' => '/порше|porsche|для рф|отправк[а-я]* в рф|катер рф|аудио система для рф|музыка на катер рф|мото навигатор/u',
            'action' => 'semantic_only',
            'suggested_category' => null,
            'semantic_marker' => 'non_yacht_or_personal',
            'reason' => 'personal or non-yacht context must not train yacht operational categories',
        ],
        'actor_context' => [
            'pattern' => '/(^|\\s)(лв|леонид владимирович|александр|александра|саша|саше|олег|вова|володя|натали|наталь|арик|арика|данил)(\\s|$)/u',
            'action' => 'semantic_only',
            'suggested_category' => null,
            'semantic_marker' => 'actor_context',
            'reason' => 'actor/source names alone are semantic context markers; expense/mixed rows must not train a category',
        ],
        'money_movement' => [
            'pattern' => '/остались на карте.*сдал|оплатил с карты для себя|вернул в кеш кассу|свои нужды.*карты.*кеш|карты.*свои нужды.*кеш|пр[еe]вод со счета на карту|перевод со счета на карту/u',
            'action' => 'semantic_only',
            'suggested_category' => null,
            'semantic_marker' => 'money_movement',
            'reason' => 'owner decision: card/cash/private settlement wording is a separate money-movement block, not an expense category',
        ],
        'debt_or_return' => [
            'pattern' => '/долг|возврат|вернул|под ?отчет|подотчет|пот отчет|кредит|займ|заем|рассрочк/u',
            'action' => 'semantic_only',
            'suggested_category' => null,
            'semantic_marker' => 'debt_or_return',
            'reason' => 'owner decision: debt/loan/credit/return/accountable wording belongs to a separate lower accounting block, not an expense category',
        ],
        'local_unclear_vendor_or_place' => [
            'pattern' => '/цоги\s*мар|цогимар|cogimar/u',
            'action' => 'discussion_required',
            'suggested_category' => 'other',
            'semantic_marker' => null,
            'reason' => 'owner decision: local unclear Cogimar/Tsogi Mar wording stays Other expenses review even when food words are present',
        ],
        'commercial_yacht_income' => [
            'pattern' => '/чартер|аренд[^,.;]*яхт|ареда яхты|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'commercial_income',
            'semantic_marker' => 'commercial_income_allowed',
            'reason' => 'explicit yacht rental/charter wording allows commercial income',
        ],
        'cash_topup_from_card' => [
            'pattern' => '/снял кеш|снял с карты|снятие с карты|банкомат|atm/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'cash_topup_from_card',
            'semantic_marker' => null,
            'reason' => 'cash withdrawal/top-up wording',
        ],
        'current_boat_expenses' => [
            'pattern' => '/брендир|(?:^|[\s-])форм[а-я]*|одежд[аы]? экипаж|спец.?одеж|спецодеж|агент|магазин|хоз.?товар|принтер|(?:^|\s)инвентарь(?!\s+по\s+кухне)(?:\s|$)|банковск[а-я]* перевод|комисси[яи] банк|банковск[а-я]* комисс|банковск[а-я]* процент[а-я]*.*перевод|забрал свои|bank fee|bank commission/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'current_boat_expenses',
            'semantic_marker' => null,
            'reason' => 'owner decision: branding, crew uniform/special clothing, agent, bank commissions, and reimbursed own cash belong to current boat expenses',
        ],
        'guest_trip_support' => [
            'pattern' => '/самокат|скутер|параплан|музыкант|вход в музей|снаст|зарядк[а-я]* шефу/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'guest_trip_support',
            'semantic_marker' => null,
            'reason' => 'owner decision: scooters, paragliding, musicians, and similar items are guest trip support',
        ],
        'transport_expenses' => [
            'pattern' => '/такси|трансфер|аренда авто|арендованн[а-я]* авто|билеты?|перел[её]т|авиа|поезд|автобус|самол[её]т|парковк|курьер|доставк|почт[а-я]* в сербию|перевозк[а-я]* гидроцикл|taxi|transfer|car rental|tickets|delivery/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'transport_expenses',
            'semantic_marker' => null,
            'reason' => 'owner decision: tickets, car rentals, taxis, transfers, and delivery belong to one transport expense category',
        ],
        'representation_expenses' => [
            'pattern' => '/представительск|подарок|подарк[а-я]* служб|презент|розы|сувенир|украшен[а-я]* др|делов\p{L}*[^,.;]*(обед|ужин|встреч)|(?:обед|ужин|ланч|встреча)[^,.;]*(?:\sс\s|\sдля\s)[\p{L}]|hospitality|business lunch|business dinner|lunch with|dinner with/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'representation_expenses',
            'semantic_marker' => null,
            'reason' => 'owner decision: gifts, presents, roses, and business hospitality belong to representation expenses',
        ],
        'admin_legal' => [
            'pattern' => '/виньет|лиценз|леценз|печат[ьи]|регистрац|такса|такс[аы] банк перевод|налог|ндс|страхов|документ|sanada|транзит.?лог|траст компани|внж|крулист|crew.?list|виза|судебн[а-я]* перевод|открытие счета|обеспечение счета|берегов[а-я]* служб|морск[а-я]* сертиф[а-я]*|сертифиткат|разрешен[а-я]* на вход|флаг[а-я]* итали|флаг[а-я]* кайман|границ|просрочк[а-я]* нахождения/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'admin_legal',
            'semantic_marker' => null,
            'reason' => 'documents, licenses, taxes, company/admin context',
        ],
        'cleaning' => [
            'pattern' => '/прачк|прачеч|полирол|пенообразователь|керхер|мойк|моющ[а-я]* средств[а-я]*|химчист|уборк|мусор|отбеливател|плесен|грибк|распылител|щетк[а-я]*(?: для лодк)?/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'cleaning',
            'semantic_marker' => null,
            'reason' => 'cleaning/laundry/cleaning supplies wording',
        ],
        'tender_or_water_toy' => [
            'pattern' => '/seabob|сибоб|тузик|тендер|dinghy|tender|williams/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'tender',
            'semantic_marker' => null,
            'reason' => 'tender or water-toy context',
        ],
        'tech_parts' => [
            'pattern' => '/навигац|шлиф|машинк|пылесос|шланг|сантехник|расходник|расходники|крюк|аккумулятор|аккум|кабель|переходник|генератор|батаре[яи]|батарейк|безопа[сст]+ност[а-я]* плаван|материал[а-я]* по тику|пропитк[аеи]? тик[а]?|расходники? по тику|расходники? тик|расодники? тик|для тика|тик.?клинер|тик.?силер|тик.?вандер|силер для платформы|средств[ао] для тика|очистител[ья]* тика|пятновыводител[ья]* тик|дезинфектор тик|обработк[а-я]* тика|щетк[а-я]*.*тик|тик.*щетк|трюмн|помп|подрульк|пордрульк|лебедк|смазк[а-я]* для лебед|компрессор|диммер|гелькоут|кранц|кранец|швартов|веревк|регулятор давления|контрольк|конде[яй]?|подгонк[а-я]*.*контрол[её]к.*кондиц|блок управления туалет|петл[яи].*(?:холодильн|хододильн)|амортизатор[а-я]*.*люк|люк[иа].*танк|датчик.*танк|ролик[а-я]* цепи|маркер[а-я]* цепи|подстаканник|экран на флай|кругов[а-я]* огонь|фонар[а-я]* на корм|плоттер|навионикс|навион|удлинитель|хомут|адаптер|болт|крепеж|крепеж[а-я]* гайк|втулк[а-я]* под стапел|строительн[а-я]* фен|мультиметр|предохранитель|сикафлекс|sikaflex|шарнир[а-я]*|шуруп[а-я]*|чертеж[а-я]* для 3д/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'tech_parts',
            'semantic_marker' => null,
            'reason' => 'technical parts, tools, consumables or equipment wording',
        ],
        'service_water' => [
            'pattern' => '/сервис|обслуж|мастер|ремонт|репарац|механик|токарь|опреснител|спас.?плот|пересертифик|дайвер|водолаз|электрик|откачка серых вод|откачка черн[а-я]* танк|черн[а-я]* танк|откачк[а-я]* вод|откачк[а-я]* грязн[а-я]* вод|выкачк[а-я]* танк|замен|монтаж|варк|консервац|тест систем|огнетуш|(?:^|\\s)то(?:\\s|$)/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'service_water',
            'semantic_marker' => null,
            'reason' => 'boat service, inspection, diver, or technical maintenance wording',
        ],
        'media_comms' => [
            'pattern' => '/нетфликс|netflix|радио|интернет|инет|интенрнет|сим|starlink|старлинк|картина.?тв|\\bтв\\b|телевиз|sonos|сонос|модем|роуминг|сайт[а-я]* клауди|домен|хостинг|платн[а-я]* погод|прогноз погод|прогнох погод|обновлен[а-я]* карт|hdmi|шнур[а-я]* телефон|чехол телефон/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'media_comms',
            'semantic_marker' => null,
            'reason' => 'media, communications, subscriptions',
        ],
        'provisions' => [
            'pattern' => '/алкоголь|виски|водк|шампан|грей.?гус|моет|moet|вдова клико|аберлоу|ликер|кофе(?![\\s-]?машин)|холодн[а-я]* чай|рынок|(^|\\s)еда(\\s|$)|продукт|продуукт|цветы|клубник|монтефиш|обед|кафе|скамп|шкамп|краб|кальмар|лангустин|осминог|лосось|сок|сироп|сладост|коктел|коктейл|докупк[а-я]* необходим[а-я]* в поход|закупк[а-я]* в поход|косметик|гигиен|шампун|аптечк|аптек|лекарств|(?:^|\s)вода(?!\s+электричеств)(?:\s|$)|вода (?:на|в) лодк/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'provisions',
            'semantic_marker' => null,
            'reason' => 'guest/crew household consumables, hospitality, pharmacy, hotel, and food-adjacent wording',
        ],
        'interior' => [
            'pattern' => '/кухонн[^,.;]*принадлежн|кухонн[а-я]* расход|инвентарь по кухне|кухн[а-я]*.*интерьер|кухн[а-я]*.*обновлен|утварь.*кухн|перешив.*подуш|подушк|чехл|скатерт|нарды|шезлонг|кофе[\\s-]?машин|кофемашин|блендер|соковыжималк|микроволновк|печка|капучинатор|графин|пепельниц|жалюзи|одеял|наволочк|плед|комплект постельн|мешк[иа]|контейнер|замк[иа] на дверц|на кухню/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'interior',
            'semantic_marker' => null,
            'reason' => 'interior, tableware, leisure or household object wording',
        ],
        'marina_ports' => [
            'pattern' => '/марин|порт|выход в море|переход коринф|проход через коринф|tepai|такс[аы] по вход/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'marina_ports',
            'semantic_marker' => null,
            'reason' => 'marina, port, sea-entry or Corinth/TEPAI port fee context',
        ],
        'berth' => [
            'pattern' => '/стоянк|зимовк|склад|гараж|вода электричество|электричеств|муринг/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'berth',
            'semantic_marker' => null,
            'reason' => 'berth/storage/garage/standing utility context',
        ],
        'personal_or_non_yacht' => [
            'pattern' => '/порше|porsche|для рф|отправк[а-я]* в рф|катер рф|аудио система для рф|игра лв|для себя/u',
            'action' => 'semantic_only',
            'suggested_category' => null,
            'semantic_marker' => 'non_yacht_or_personal',
            'reason' => 'personal or non-yacht context must not train yacht operational categories',
        ],
        'unsortable_other_review' => [
            'pattern' => '/планшет|обезналич|консьерж|книжк[а-я]* моряка|подставк[а-я]* под динги/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'other',
            'semantic_marker' => null,
            'reason' => 'owner decision: unsortable rows belong to Other expenses review',
        ],
        'crew_role' => [
            'pattern' => '/повар|чаев|работник в помощь|сотруднику|докеры/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'crew',
            'semantic_marker' => null,
            'reason' => 'crew role or crew tips wording',
        ],
        'transport_followup' => [
            'pattern' => '/рентакар|air serbia|логистик|забрал гостей|дорожн[а-я]* расход|запра[вк][а-я]* авто|велосипед[а-я]* млет/u',
            'action' => 'existing_category_candidate',
            'suggested_category' => 'transport_expenses',
            'semantic_marker' => null,
            'reason' => 'owner decision: rentals, airlines, logistics, and guest pickup belong to transport expenses',
        ],
    ];

    public function __construct(
        private readonly string $corpusPath,
        private readonly string $outputRoot,
        private readonly string $sprintDocPath
    ) {
    }

    public function run(): void
    {
        $corpus = $this->readJson($this->corpusPath);
        $rows = $corpus['descriptions'] ?? [];
        if (!is_array($rows)) {
            throw new RuntimeException('Invalid corpus descriptions');
        }

        $candidates = [];
        $actions = [];
        $manualMerchantAliasExcluded = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($this->isManualMerchantAlias((string)($row['description'] ?? ''))) {
                $manualMerchantAliasExcluded++;
                continue;
            }
            $guess = $row['current_rule_guess'] ?? null;
            $markers = is_array($row['semantic_markers'] ?? null) ? $row['semantic_markers'] : [];
            if ($guess !== null && $markers === []) {
                continue;
            }
            $decision = $this->decision((string)($row['description'] ?? ''), $guess, $markers, $row);
            $candidate = [
                'description' => (string)($row['description'] ?? ''),
                'count' => (int)($row['count'] ?? 0),
                'amount_abs_total' => round((float)($row['amount_abs_total'] ?? 0), 2),
                'current_rule_guess' => $guess,
                'semantic_markers' => $markers,
                'proposed_action' => $decision['action'],
                'proposed_category' => $decision['suggested_category'],
                'proposed_semantic_marker' => $decision['semantic_marker'],
                'decision_reason' => $decision['reason'],
                'examples' => array_slice(is_array($row['examples'] ?? null) ? $row['examples'] : [], 0, 3),
            ];
            $candidates[] = $candidate;
            $actions[$decision['action']] = ($actions[$decision['action']] ?? 0) + 1;
        }

        usort($candidates, static function (array $a, array $b): int {
            return [$b['count'], $b['amount_abs_total'], $a['description']] <=> [$a['count'], $a['amount_abs_total'], $b['description']];
        });

        $payload = [
            'generated_at' => date(DATE_ATOM),
            'source' => $this->corpusPath,
            'purpose' => 'SPRINT-24R dictionary review candidates. Discussion/training only; no operational finance mutation.',
            'rows_total' => (int)($corpus['rows_total'] ?? 0),
            'unique_descriptions' => (int)($corpus['unique_descriptions'] ?? count($rows)),
            'candidate_count' => count($candidates),
            'action_counts' => $actions,
            'excluded_manual_merchant_alias_count' => $manualMerchantAliasExcluded,
            'candidates' => $candidates,
        ];

        if (!is_dir($this->outputRoot) && !mkdir($this->outputRoot, 0775, true) && !is_dir($this->outputRoot)) {
            throw new RuntimeException("Cannot create output folder: {$this->outputRoot}");
        }
        $this->writeJson($this->outputRoot . '/sprint-24-review-candidates.json', $payload);
        file_put_contents($this->sprintDocPath, $this->markdown($payload));

        echo "SPRINT-24R dictionary review prepared\n";
        echo "candidates=" . count($candidates) . "\n";
        echo "excluded_manual_merchant_aliases={$manualMerchantAliasExcluded}\n";
        echo "json={$this->outputRoot}/sprint-24-review-candidates.json\n";
        echo "doc={$this->sprintDocPath}\n";
    }

    private function isManualMerchantAlias(string $description): bool
    {
        $text = mb_strtolower(trim(str_replace('ё', 'е', $description)));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return preg_match('/цоги\s*мар|цогимар|cogimar/u', $text) === 1;
    }

    /** @return array<string, mixed> */
    private function decision(string $description, $guess, array $markers, array $row): array
    {
        $text = mb_strtolower(trim(str_replace('ё', 'е', $description)));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (preg_match('/^\d+(?:[.,]\d+)?$/u', $text) === 1 && $this->allExamplesIgnored($row)) {
            return [
                'action' => 'ignore_or_balance_context',
                'suggested_category' => null,
                'semantic_marker' => null,
                'reason' => 'numeric-only ignored chronology/summary artifact is not dictionary vocabulary',
            ];
        }
        if ((int)($row['income_count'] ?? 0) === 0 && (int)($row['expense_count'] ?? 0) === 0) {
            return [
                'action' => 'ignore_or_balance_context',
                'suggested_category' => null,
                'semantic_marker' => null,
                'reason' => 'non-money heading/context row is not dictionary vocabulary',
            ];
        }

        if (
            in_array('owner_funding', $markers, true)
            && (int)($row['income_count'] ?? 0) > 0
            && (int)($row['expense_count'] ?? 0) === 0
        ) {
            return [
                'action' => 'semantic_only',
                'suggested_category' => null,
                'semantic_marker' => 'owner_funding',
                'reason' => 'clear incoming owner/source funding stays semantic-only even when actor is ЛВ',
            ];
        }

        foreach ($this->rules as $rule) {
            if (preg_match($rule['pattern'], $text) === 1) {
                if (($rule['semantic_marker'] ?? null) === 'actor_context' && $guess !== null) {
                    return [
                        'action' => 'existing_category_candidate',
                        'suggested_category' => (string)$guess,
                        'semantic_marker' => 'actor_context',
                        'reason' => 'category follows transaction wording; actor name remains context marker',
                    ];
                }
                return $rule;
            }
        }

        $hasActorName = preg_match('/(^|\s)(лв|леонид владимирович|александр|александра|саша|саше|олег|вова|володя|натали|наталь|арик|арика|данил)(\s|$)/u', $text) === 1;
        $hasActorExpenseContext = preg_match('/расход|игра|передал|отдал|дал|перев[её]л|для друга|для лв/u', $text) === 1
            || (int)($row['expense_count'] ?? 0) > 0;
        if ($hasActorName && $hasActorExpenseContext) {
            if ($guess !== null) {
                return [
                    'action' => 'existing_category_candidate',
                    'suggested_category' => (string)$guess,
                    'semantic_marker' => 'actor_context',
                    'reason' => 'category follows transaction wording; actor name remains context marker',
                ];
            }
            return [
                'action' => 'semantic_only',
                'suggested_category' => null,
                'semantic_marker' => 'actor_context',
                'reason' => 'actor/source names with expense, transfer, or mixed-money context stay semantic-only until row is manually clarified',
            ];
        }

        if (in_array('actor_context', $markers, true)) {
            if ($guess !== null) {
                return [
                    'action' => 'existing_category_candidate',
                    'suggested_category' => (string)$guess,
                    'semantic_marker' => 'actor_context',
                    'reason' => 'category follows transaction wording; actor name remains context marker',
                ];
            }
            return [
                'action' => 'semantic_only',
                'suggested_category' => null,
                'semantic_marker' => 'actor_context',
                'reason' => 'existing semantic marker says actor/source context; no category is trained',
            ];
        }
        if (in_array('owner_funding', $markers, true)) {
            return [
                'action' => 'semantic_only',
                'suggested_category' => null,
                'semantic_marker' => 'owner_funding',
                'reason' => 'existing semantic marker says source/funding context',
            ];
        }
        if (in_array('cash_location_safe', $markers, true)) {
            return [
                'action' => 'semantic_only',
                'suggested_category' => null,
                'semantic_marker' => 'cash_location_safe',
                'reason' => 'existing semantic marker says cash location context',
            ];
        }
        if (in_array('money_movement', $markers, true)) {
            return [
                'action' => 'semantic_only',
                'suggested_category' => null,
                'semantic_marker' => 'money_movement',
                'reason' => 'existing semantic marker says separate money-movement/private settlement context',
            ];
        }
        if (in_array('non_yacht_or_personal', $markers, true)) {
            return [
                'action' => 'semantic_only',
                'suggested_category' => null,
                'semantic_marker' => 'non_yacht_or_personal',
                'reason' => 'existing semantic marker says personal/non-yacht context',
            ];
        }
        if ($guess !== null) {
            return [
                'action' => 'existing_category_candidate',
                'suggested_category' => (string)$guess,
                'semantic_marker' => null,
                'reason' => 'already matched by current seed category rule',
            ];
        }

        return [
            'action' => 'discussion_required',
            'suggested_category' => null,
            'semantic_marker' => null,
            'reason' => 'no safe compact rule yet',
        ];
    }

    private function allExamplesIgnored(array $row): bool
    {
        $examples = is_array($row['examples'] ?? null) ? $row['examples'] : [];
        if ($examples === []) {
            return false;
        }
        foreach ($examples as $example) {
            if (!is_array($example) || (string)($example['parse_status'] ?? '') !== 'ignored') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $json = json_decode((string)file_get_contents($path), true);
        if (!is_array($json)) {
            throw new RuntimeException("Cannot read JSON: {$path}");
        }

        return $json;
    }

    private function writeJson(string $path, array $payload): void
    {
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function markdown(array $payload): string
    {
        $lines = [
            '# SPRINT-24R — Claudia Z Dictionary Candidate Review',
            '',
            '## Director Opening',
            '',
            'Review the Claudia Z raw history dictionary candidates without changing operational finance logic.',
            '',
            'This sprint is candidate staging only: discussion-ready rules, exclusions, and semantic markers.',
            '',
            '## Agent Assignments',
            '',
            '- Linguistic Review Agent — recurring human wording and compact rule candidates.',
            '- Financial Logic Reviewer — prevent category mistakes and protect finance invariants.',
            '- QA Acceptance Agent — verify artifacts and non-mutation guarantees.',
            '',
            '## Current Export',
            '',
            '- Source rows: `' . (string)$payload['rows_total'] . '`',
            '- Unique descriptions: `' . (string)$payload['unique_descriptions'] . '`',
            '- Candidate descriptions: `' . (string)$payload['candidate_count'] . '`',
            '- Manual merchant aliases excluded from dictionary training: `' . (string)($payload['excluded_manual_merchant_alias_count'] ?? 0) . '`',
            '- JSON: `storage/imports/claudia-z-dictionary/sprint-24-review-candidates.json`',
            '',
            '## Action Counts',
            '',
        ];

        foreach ($payload['action_counts'] as $action => $count) {
            $lines[] = '- `' . $action . '`: `' . (string)$count . '`';
        }

        $lines[] = '';
        $lines[] = '## Top Candidates';
        $lines[] = '';
        $lines[] = '| Count | Proposed action | Category | Marker | Description | Reason |';
        $lines[] = '|---:|---|---|---|---|---|';

        foreach (array_slice($payload['candidates'], 0, 60) as $candidate) {
            $lines[] = '| '
                . (string)$candidate['count'] . ' | `'
                . $this->cell((string)$candidate['proposed_action']) . '` | '
                . $this->cell((string)($candidate['proposed_category'] ?? '')) . ' | '
                . $this->cell((string)($candidate['proposed_semantic_marker'] ?? '')) . ' | '
                . $this->cell((string)$candidate['description']) . ' | '
                . $this->cell((string)$candidate['decision_reason']) . ' |';
        }

        $lines[] = '';
        $lines[] = '## Non-Goals';
        $lines[] = '';
        $lines[] = '- New approved categories in this sprint: `transport_expenses`, `representation_expenses`, `current_boat_expenses`, `guest_trip_support`, `guest_cash_issued`.';
        $lines[] = '- Do not auto-approve category rules.';
        $lines[] = '- Do not convert archive/raw history to operational entries.';
        $lines[] = '- Do not use review sums as financial report totals.';
        $lines[] = '';
        $lines[] = '## Acceptance';
        $lines[] = '';
        $lines[] = '- Candidate file is reproducible from `description-corpus.json`.';
        $lines[] = '- Every top candidate has proposed action and reason.';
        $lines[] = '- `сейф` remains semantic-only cash location context.';
        $lines[] = '- Owner funding remains semantic-only unless explicit yacht rental/charter wording exists.';
        $lines[] = '- `аренда авто`, taxis, transfers, tickets, deliveries, and rentals map to `transport_expenses`, never to commercial income.';
        $lines[] = '- `брендирование`, crew uniform/special clothing, `агент`, generic `магазин`, and bank commissions map to `current_boat_expenses`.';
        $lines[] = '- `забрал свои` maps to `current_boat_expenses`; loan/debt/credit/return/accountable wording stays in the separate lower accounting block.';
        $lines[] = '- `самокат`, scooters, paragliding, musicians, and chef charging-device rows map to `guest_trip_support`.';
        $lines[] = '- Expense-side `ЛВ`, `передал ЛВ`, `отдал ЛВ`, `игра ЛВ`, and `расходы ЛВ` map to `guest_cash_issued`; clear incoming owner funding remains owner/source funding.';
        $lines[] = '- `контролька кондея` maps to `tech_parts`; unsortable rows such as `айфон`, `планшет`, and `обезналич` stay `other` / `other_review`.';
        $lines[] = '- Crew tips / `чаевые` map to `crew`.';
        $lines[] = '- `докупка необходимого в поход`, trip purchases, cosmetics, shampoos, marine pharmacy, mask spray, and plain water purchases map to `provisions`.';
        $lines[] = '- Plain alcohol rows such as champagne, vodka, and Grey Goose map to `provisions` unless gift/business-hospitality wording is explicit.';
        $lines[] = '- `sonos`/`сонос` and modem rows map to `media_comms`.';
        $lines[] = '- Generic incoming `принял` maps to owner/source funding unless paired with safe/cash-location or actor transfer context.';
        $lines[] = '- Kitchen/interior utensils, cushion rework, covers, cushions, and sunbeds map to `interior`.';
        $lines[] = '- Jet-ski transport maps to `transport_expenses`; `склад`, `гараж`, `электричество`, and `вода электричество` map to `berth`; single `вода` maps to `provisions`.';
        $lines[] = '- Sea-entry, Corinth passage, TEPAI, and entry-tax wording maps to `marina_ports`.';
        $lines[] = '- Replacement, mounting, welding, conservation, system tests, and fire-extinguisher work maps to `service_water`, displayed as service works.';
        $lines[] = '- Bow/stern thruster, winch, compressor, dimmer, gelcoat, fenders, mooring lines, and pressure regulator map to `tech_parts` when they are parts/equipment rather than service work.';
        $lines[] = '- `цоги мар`, `цогимар`, and `cogimar` are manual merchant aliases: keep operational rows in `other_review`, but exclude these names from dictionary training and candidate counts.';
        $lines[] = '- `аптека` maps to `provisions`; hotels map to guest/provision-style yacht guest expenses.';
        $lines[] = '- `brokerage` and `agency fee` remain discussion-required unless explicit yacht/charter wording is present.';
        $lines[] = '- `сим-карта и фрукты`, settlement/card/cash movement rows, and `тендер остаток за зиму и сервис` stay review/discussion cases.';
        $lines[] = '- `цветы` maps to `provisions`; `подарок`, `презент`, `розы`, birthday decoration, and explicit business hospitality map to `representation_expenses`.';
        $lines[] = '- Generic `инвентарь` maps to `current_boat_expenses`; `инвентарь по кухне` remains `interior`.';
        $lines[] = '- Debt/loan/credit/return/accountable wording remains semantic-only lower accounting context even when another category word is present.';
        $lines[] = '- Personal/non-yacht wording such as `Порше`, `катер РФ`, `для РФ`, and `аудио система для РФ` remains semantic-only and must not train yacht operational categories.';
        $lines[] = '- Operational finance tables are not mutated.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function cell(string $value): string
    {
        $value = str_replace(["\n", "\r"], ' ', $value);
        return str_replace('|', '\\|', $value);
    }
}

$root = dirname(__DIR__);
$corpusPath = $argv[1] ?? $root . '/storage/imports/claudia-z-dictionary/description-corpus.json';
$outputRoot = $argv[2] ?? $root . '/storage/imports/claudia-z-dictionary';
$sprintDocPath = $argv[3] ?? $root . '/FinDesk v2.0/sprints/SPRINT-24R-claudia-z-dictionary-candidate-review.md';

(new ClaudiaZDictionaryReviewPreparer($corpusPath, $outputRoot, $sprintDocPath))->run();
