<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Support.php';

final class ClaudiaZDictionaryCorpusExporter
{
    private PDO $db;
    private string $workspaceId;

    /** @var array<string, array{pattern: string, label: string}> */
    private array $categoryRules = [
        'cash_topup_from_card' => ['pattern' => '/снял кеш|снял с карты|снятие с карты|банкомат|atm|cash withdrawal|card to cash/u', 'label' => 'Пополнение cash с карты'],
        'commercial_income' => ['pattern' => '/чартер|оплата чартера|аренд[^,.;]*яхт|ареда яхты|яхт[^,.;]*аренд|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)|brokerage|agency fee/u', 'label' => 'Коммерческий приход'],
        'dry_dock' => ['pattern' => '/сухой док|антифоулинг|подъем|подъём|подьем|спуск|haul.?out|launch/u', 'label' => 'Сухой док'],
        'berth' => ['pattern' => '/стоянк|зимовк|склад|гараж|вода электричество|электричеств|муринг|mooring|berth|vez/u', 'label' => 'Стоянка'],
        'marina_ports' => ['pattern' => '/марин|порт|паром|выход в море|переход коринф|проход через коринф|tepai|такс[аы] по вход|luka|harbou?r/u', 'label' => 'Марины и портовые'],
        'service_water' => ['pattern' => '/сервис|обслуж|мастер|ремонт|репарац|механик|токарь|водолаз|diver|диагност|опреснител|спас.?плот|пересертифик|дайвер|электрик|откачка серых вод|откачка черн[а-я]* танк|черн[а-я]* танк|откачк[а-я]* вод|откачк[а-я]* грязн[а-я]* вод|выкачк[а-я]* танк|замен|монтаж|варк|консервац|тест систем|огнетуш|(?:^|\s)то(?:\s|$)/u', 'label' => 'Сервисные работы'],
        'tech_parts' => ['pattern' => '/аккумулятор|аккум|кабел|насос|мотор|детал|запчаст|инструмент|фильтр|анод|клей|реле|навигац|шлиф|машинк|пылесос|шланг|сантехник|расходник|расходники|крюк|переходник|генератор|батаре[яи]|батарейк|безопа[сст]+ност[а-я]* плаван|материал[а-я]* по тику|пропитк[аеи]? тик[а]?|расходники? по тику|расходники? тик|расодники? тик|для тика|тик.?клинер|тик.?силер|тик.?вандер|силер для платформы|средств[ао] для тика|очистител[ья]* тика|пятновыводител[ья]* тик|дезинфектор тик|обработк[а-я]* тика|щетк[а-я]*.*тик|тик.*щетк|трюмн|помп|подрульк|пордрульк|лебедк|смазк[а-я]* для лебед|компрессор|диммер|гелькоут|кранц|кранец|швартов|веревк|регулятор давления|контрольк|конде[яй]?|подгонк[а-я]*.*контрол[её]к.*кондиц|блок управления туалет|петл[яи].*(?:холодильн|хододильн)|амортизатор[а-я]*.*люк|люк[иа].*танк|датчик.*танк|ролик[а-я]* цепи|маркер[а-я]* цепи|подстаканник|экран на флай|кругов[а-я]* огонь|фонар[а-я]* на корм|плоттер|навионикс|навион|удлинитель|хомут|адаптер|болт|крепеж|крепеж[а-я]* гайк|втулк[а-я]* под стапел|строительн[а-я]* фен|мультиметр|предохранитель|сикафлекс|sikaflex|шарнир[а-я]*|шуруп[а-я]*|чертеж[а-я]* для 3д/u', 'label' => 'Техчасть и запчасти'],
        'tender' => ['pattern' => '/тузик|тендер|dinghy|tender|williams|outboard|seabob|сибоб|сапы?|sup/u', 'label' => 'Тендер / тузик'],
        'fuel' => ['pattern' => '/заправ|топлив|дизел|бензин|fuel|diesel|petrol|gorivo|nafta/u', 'label' => 'Топливо'],
        'guest_trip_support' => ['pattern' => '/айфон|iphone|самокат|скутер|параплан|музыкант|прогулк[а-я]* гост|нац парк|вход в музей|снаст|зарядк[а-я]* шефу|маски$|маски ласты|подводн[а-я]* маск|перья на сап|весло сап|набор для ныряния|отел[ьяеи]?|гостиниц/u', 'label' => 'Обеспечение гостей в походе'],
        'guest_cash_issued' => ['pattern' => '/^(?:[+-]?\s*\d+(?:[.,]\d+)?\s+)?(?:лв|леонид владимирович)$|расходы лв|общая потраченная сумма лв|игра лв|(?:передал|отдал|дал|выдал)\s+(?:лв|леонид владимирович|арику?|саше?|гост)/u', 'label' => 'Выданные наличные гостям'],
        'representation_expenses' => ['pattern' => '/представительск|подарок|подарк[а-я]* служб|презент|розы|сувенир|украшен[а-я]* др|делов\p{L}*[^,.;]*(обед|ужин|встреч)|(?:обед|ужин|ланч|встреча)[^,.;]*(?:\sс\s|\sдля\s)[\p{L}]|hospitality|business lunch|business dinner|lunch with|dinner with/u', 'label' => 'Представительские расходы'],
        'provisions' => ['pattern' => '/продукт|продуукт|рыб|стейк|мяс|баранин|хлеб|фрукт|овощ|напит|вино|пиво|кола|сок|сироп|сладост|коктел|коктейл|устриц|скамп|шкамп|краб|кальмар|лангустин|осминог|лосось|тунец|салмон|сыр|морож|инжир|яйц|орех|мед|соус|острог|перекус|еда|ресторан|цветы|алкоголь|виски|водк|шампан|грей.?гус|моет|moet|вдова клико|аберлоу|ликер|кофе(?![\\s-]?машин)|холодн[а-я]* чай|рынок|клубник|монтефиш|обед|кафе|докупк[а-я]* необходим[а-я]* в поход|закупк[а-я]* в поход|косметик|гигиен|шампун|аптечк|аптек|лекарств|(?:^|\s)вода(?!\s+электричеств)(?:\s|$)|вода (?:на|в) лодк/u', 'label' => 'Продукты и гости'],
        'interior' => ['pattern' => '/ковр|текстил|полотен|обувь|судоч|нож|посуд|матрас|игрушк|linen|towels|кухонн[^,.;]*принадлежн|кухонн[а-я]* расход|инвентарь по кухне|кухн[а-я]*.*интерьер|кухн[а-я]*.*обновлен|утварь.*кухн|перешив.*подуш|подушк|чехл|скатерт|нарды|шезлонг|кофе[\\s-]?машин|кофемашин|блендер|соковыжималк|микроволновк|печка|капучинатор|графин|пепельниц|жалюзи|одеял|наволочк|плед|комплект постельн|мешк[иа]|контейнер|замк[иа] на дверц|на кухню/u', 'label' => 'Интерьер и быт'],
        'cleaning' => ['pattern' => '/хим|мойк|моющ[а-я]* средств[а-я]*|салф|тряпк|пена|полиров|уборк|химчист|clean|laundry|detergent|прачк|прачеч|полирол|пенообразователь|керхер|мусор|вывоз мусора|отбеливател|плесен|грибк|распылител|щетк[а-я]*(?: для лодк)?/u', 'label' => 'Клининг и химия'],
        'media_comms' => ['pattern' => '/netflix|нетфликс|apple|ivi|иви|старлинк|starlink|hipo|сим.?карт|интернет|инет|интенрнет|wifi|связ|telekom|картина.?тв|\bтв\b|телевиз|sonos|сонос|модем|роуминг|сайт[а-я]* клауди|домен|хостинг|платн[а-я]* погод|прогноз погод|прогнох погод|обновлен[а-я]* карт|hdmi|шнур[а-я]* телефон|чехол телефон/u', 'label' => 'Мультимедиа и связь'],
        'current_boat_expenses' => ['pattern' => '/брендир|(?:^|[\s-])форм[а-я]*|одежд[аы]? экипаж|спец.?одеж|спецодеж|агент|магазин|хоз.?товар|принтер|(?:^|\s)инвентарь(?!\s+по\s+кухне)(?:\s|$)|банковск[а-я]* перевод|комисси[яи] банк|банковск[а-я]* комисс|банковск[а-я]* процент[а-я]*.*перевод|забрал свои|bank fee|bank commission/u', 'label' => 'Текущие лодочные расходы'],
        'transport_expenses' => ['pattern' => '/такси|трансфер|аренда авто|арендованн[а-я]* авто|рентакар|билеты?|перел[её]т|авиа|поезд|автобус|самол[её]т|air serbia|логистик|забрал гостей|дорожн[а-я]* расход|запра[вк][а-я]* авто|парковк|курьер|доставк|почт[а-я]* в сербию|велосипед[а-я]* млет|перевозк[а-я]* гидроцикл|taxi|transfer|car rental|tickets|delivery/u', 'label' => 'Транспортные расходы'],
        'admin_legal' => ['pattern' => '/тур.?регистрац|тамож|дьюти|документ|печат[ьи]|налог|ндс|страхов|регистрац|юрист|адвокат|license|insurance|customs|виньет|лиценз|леценз|sanada|такса|такс[аы] банк перевод|траст компани|внж|крулист|crew.?list|виза|судебн[а-я]* перевод|открытие счета|обеспечение счета|берегов[а-я]* служб|морск[а-я]* сертиф[а-я]*|сертифиткат|разрешен[а-я]* на вход|флаг[а-я]* итали|флаг[а-я]* кайман|границ|просрочк[а-я]* нахождения/u', 'label' => 'Админка / документы'],
        'crew' => ['pattern' => '/\bзп\b|зарплат|аванс|капитан|хостесс|помощник|экипаж|работник в помощь|сотруднику|докеры|sailor|crew|salary|повар|чаев/u', 'label' => 'Экипаж'],
    ];

    /** @var array<string, bool> */
    private array $stopWords = [];
    /** @var array<string, array{pattern: string, rule: string}> */
    private array $semanticRules = [
        'cash_location_safe' => [
            'pattern' => '/сейф|сеф|из сейфа|из сефа|в сейф|в сеф|взял из сейфа|забрал из сейфа|получено из сейфа|получил из сейфа|принял из сейфа|убрал в сейф|положил в сейф|принял сейфы/u',
            'rule' => 'safe/сейф is cash source/location context, not category or commercial income',
        ],
        'owner_funding' => [
            'pattern' => '/(^|\s)принял($|\s)|от александр|александр|от саши|\bсаша\b|натали|наталь|арик|арика|\bлв\b|леонид владимирович|от данил|получил от данил|из германии|из крипт|через крипт|usdt|усдт|приход из рф|пополнение служебной карты|пришли на банк ерсте|я внес свои/u',
            'rule' => 'non-commercial income is owner/source funding with source actor where possible',
        ],
        'commercial_income_allowed' => [
            'pattern' => '/чартер|оплата чартера|аренд[^,.;]*яхт|ареда яхты|яхт[^,.;]*аренд|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)/u',
            'rule' => 'income can be commercial only with explicit yacht-rental/commercial wording',
        ],
        'debt_or_return' => [
            'pattern' => '/долг|возврат|вернул|под ?отчет|подотчет|пот отчет|кредит|займ|заем|рассрочк/u',
            'rule' => 'debt/loan/credit/return marker, not category by itself',
        ],
        'money_movement' => [
            'pattern' => '/остались на карте.*сдал|оплатил с карты для себя|вернул в кеш кассу|свои нужды.*карты.*кеш|карты.*свои нужды.*кеш|пр[еe]вод со счета на карту|перевод со счета на карту/u',
            'rule' => 'card/cash/private settlement wording is movement/review context, not expense category by itself',
        ],
        'non_yacht_or_personal' => [
            'pattern' => '/порше|porsche|для рф|отправк[а-я]* в рф|катер рф|аудио система для рф|музыка на катер рф|мото навигатор/u',
            'rule' => 'personal or non-yacht context must not train yacht operational categories',
        ],
    ];

    public function __construct(private readonly string $outputRoot)
    {
        $this->db = ql_db();
        $this->workspaceId = $this->workspaceId('Claudia Z Archive Raw History');
        $this->stopWords = array_fill_keys([
            'и', 'в', 'во', 'на', 'за', 'для', 'по', 'от', 'до', 'из', 'с', 'со', 'к', 'ко',
            'купил', 'купила', 'купили', 'оплатил', 'оплата', 'оплатили', 'лодка', 'boat',
            'cash', 'card', 'money', 'paid', 'bought', 'the', 'a', 'for', 'to', 'of',
        ], true);
    }

    public function run(): void
    {
        if (!is_dir($this->outputRoot) && !mkdir($this->outputRoot, 0775, true) && !is_dir($this->outputRoot)) {
            throw new RuntimeException("Cannot create output folder: {$this->outputRoot}");
        }

        $descriptions = [];
        $tokens = [];
        $coverage = [];
        $semanticCoverage = [];
        $unrecognized = [];
        $rowsTotal = 0;

        foreach ($this->rows() as $row) {
            $rowsTotal++;
            $raw = FinDeskV2Support::jsonDecode((string)$row['raw_json'], []);
            $description = trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? ''));
            if ($description === '') {
                if ((string)$row['parse_status'] === 'unrecognized') {
                    $unrecognized[] = $this->rowSummary($row, $raw, null, null, null);
                }
                continue;
            }

            $money = $this->money($raw);
            $guess = $money === null ? null : $this->guessCategory($description, $money['flow_type'], $money['sign']);
            $semanticMarkers = $this->semanticMarkers($description, $money);
            $normalized = $this->normalizeDescription($description);
            if (!isset($descriptions[$normalized])) {
                $descriptions[$normalized] = [
                    'description' => $description,
                    'normalized' => $normalized,
                    'count' => 0,
                    'cash_count' => 0,
                    'card_count' => 0,
                    'income_count' => 0,
                    'expense_count' => 0,
                    'amount_abs_total' => 0.0,
                    'current_rule_guess' => $guess['category_code'] ?? null,
                    'matched_pattern' => $guess['pattern'] ?? null,
                    'semantic_markers' => [],
                    'examples' => [],
                ];
            }
            if (($descriptions[$normalized]['current_rule_guess'] ?? null) === null && ($guess['category_code'] ?? null) !== null) {
                $descriptions[$normalized]['current_rule_guess'] = $guess['category_code'];
                $descriptions[$normalized]['matched_pattern'] = $guess['pattern'] ?? null;
            }

            $descriptions[$normalized]['count']++;
            if ($money !== null) {
                $descriptions[$normalized]['amount_abs_total'] += $money['amount'];
                $descriptions[$normalized][$money['flow_type'] . '_count']++;
                $descriptions[$normalized][$money['sign'] === '+' ? 'income_count' : 'expense_count']++;
            }
            if (count($descriptions[$normalized]['examples']) < 5) {
                $descriptions[$normalized]['examples'][] = $this->rowSummary($row, $raw, $money, $guess, $description, $semanticMarkers);
            }
            foreach ($semanticMarkers as $marker) {
                $descriptions[$normalized]['semantic_markers'][$marker] = true;
                $semanticCoverage[$marker] = ($semanticCoverage[$marker] ?? 0) + 1;
            }

            $code = $guess['category_code'] ?? 'needs_review';
            $coverage[$code] = ($coverage[$code] ?? 0) + 1;
            foreach ($this->tokens($description) as $token) {
                $tokens[$token] = ($tokens[$token] ?? 0) + 1;
            }
        }

        uasort($descriptions, static function (array $a, array $b): int {
            return [$b['count'], $b['amount_abs_total'], $a['normalized']] <=> [$a['count'], $a['amount_abs_total'], $b['normalized']];
        });
        arsort($tokens);
        arsort($coverage);
        arsort($semanticCoverage);

        foreach ($descriptions as &$descriptionRow) {
            $descriptionRow['semantic_markers'] = array_keys($descriptionRow['semantic_markers']);
        }
        unset($descriptionRow);

        $corpus = [
            'generated_at' => date(DATE_ATOM),
            'workspace_id' => $this->workspaceId,
            'workspace_name' => 'Claudia Z Archive Raw History',
            'purpose' => 'Dictionary training corpus. Does not create operational entries.',
            'rows_total' => $rowsTotal,
            'unique_descriptions' => count($descriptions),
            'descriptions' => array_values($descriptions),
        ];

        $this->writeJson('description-corpus.json', $corpus);
        $this->writeJson('token-frequency.json', [
            'generated_at' => date(DATE_ATOM),
            'tokens' => array_slice($tokens, 0, 500, true),
        ]);
        $this->writeJson('rule-coverage.json', [
            'generated_at' => date(DATE_ATOM),
            'coverage' => $coverage,
            'semantic_coverage' => $semanticCoverage,
            'note' => 'Current rule guesses are linguistic seed labels only, not accepted finance categories.',
        ]);
        $this->writeJson('unrecognized-rows.json', [
            'generated_at' => date(DATE_ATOM),
            'rows' => $unrecognized,
        ]);

        echo "Claudia Z dictionary corpus exported\n";
        echo "workspace_id={$this->workspaceId}\n";
        echo "rows_total={$rowsTotal}\n";
        echo 'unique_descriptions=' . count($descriptions) . "\n";
        echo "output={$this->outputRoot}\n";
    }

    /** @return array<int, string> */
    private function semanticMarkers(string $description, ?array $money): array
    {
        $text = $this->normalizeDescription($description);
        $markers = [];
        $hasActorContext = preg_match('/\bлв\b|леонид владимирович|александр|александра|\bсаша\b|саше\b|олег\b|вова\b|володя\b|натали|наталь|арик|арика|данил/u', $text) === 1
            && (($money['sign'] ?? null) === '-' || preg_match('/расход|игра|передал|отдал|дал|перев[её]л|для друга|для лв/u', $text) === 1);
        if ($hasActorContext) {
            $markers[] = 'actor_context';
        }
        foreach ($this->semanticRules as $marker => $rule) {
            if ($marker === 'owner_funding' && ($money['sign'] ?? null) !== '+') {
                continue;
            }
            if ($marker === 'owner_funding' && $hasActorContext) {
                continue;
            }
            if ($marker === 'commercial_income_allowed' && ($money['sign'] ?? null) !== '+') {
                continue;
            }
            if (preg_match($rule['pattern'], $text) === 1) {
                $markers[] = $marker;
            }
        }

        return $markers;
    }

    private function workspaceId(string $name): string
    {
        $stmt = $this->db->prepare("SELECT id FROM v2_workspaces WHERE name = ? AND archived_at IS NULL LIMIT 1");
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            throw new RuntimeException("Workspace not found: {$name}");
        }

        return (string)$id;
    }

    /** @return Generator<array<string, mixed>> */
    private function rows(): Generator
    {
        $stmt = $this->db->prepare("
            SELECT s.file_name, r.sheet_name, r.row_number, r.raw_json, r.parse_status, r.parse_notes
            FROM v2_import_rows r
            INNER JOIN v2_import_sources s ON s.id = r.import_source_id
            WHERE s.workspace_id = ?
            ORDER BY s.file_name, r.sheet_name, r.row_number
        ");
        $stmt->execute([$this->workspaceId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /** @return array{flow_type: string, sign: string, amount: float}|null */
    private function money(array $raw): ?array
    {
        $amounts = [
            ['flow_type' => 'cash', 'sign' => '+', 'amount' => $this->amount($raw['приход кеш'] ?? $raw['приход кэш'] ?? $raw['cash income'] ?? $raw['приход'] ?? null)],
            ['flow_type' => 'cash', 'sign' => '-', 'amount' => $this->amount($raw['расход кеш'] ?? $raw['расход кэш'] ?? $raw['cash expense'] ?? $raw['расход'] ?? null)],
            ['flow_type' => 'card', 'sign' => '+', 'amount' => $this->amount($raw['приход карта'] ?? $raw['приход карты'] ?? $raw['card income'] ?? null)],
            ['flow_type' => 'card', 'sign' => '-', 'amount' => $this->amount($raw['расход карта'] ?? $raw['расход карты'] ?? $raw['card expense'] ?? null)],
        ];
        $nonZero = array_values(array_filter($amounts, static fn (array $item): bool => $item['amount'] !== null && abs((float)$item['amount']) > 0.0001));
        if (count($nonZero) !== 1) {
            return null;
        }

        return $nonZero[0];
    }

    private function amount($value): ?float
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $normalized = str_replace([' ', "\xc2\xa0"], '', $text);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? abs((float)$normalized) : null;
    }

    /** @return array{category_code: ?string, pattern: ?string} */
    private function guessCategory(string $description, string $flowType, string $sign): array
    {
        $text = mb_strtolower($description);
        if (preg_match('/цоги\s*мар|цогимар|cogimar/u', $text) === 1) {
            return ['category_code' => null, 'pattern' => null];
        }
        foreach ($this->categoryRules as $code => $rule) {
            if (preg_match($rule['pattern'], $text) !== 1) {
                continue;
            }
            if ($code === 'fuel' && preg_match('/авто|машин|car/u', $text) === 1) {
                continue;
            }
            if ($sign === '+' && !in_array($code, ['commercial_income', 'cash_topup_from_card'], true)) {
                continue;
            }
            if ($flowType === 'card' && $sign === '+' && $code !== 'cash_topup_from_card') {
                continue;
            }

            return ['category_code' => $code, 'pattern' => $rule['pattern']];
        }

        return ['category_code' => null, 'pattern' => null];
    }

    /** @return array<string> */
    private function tokens(string $description): array
    {
        $text = $this->normalizeDescription($description);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 3 || isset($this->stopWords[$part])) {
                continue;
            }
            $tokens[] = $part;
        }

        return $tokens;
    }

    private function normalizeDescription(string $description): string
    {
        $text = mb_strtolower(trim($description));
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }

    private function rowSummary(array $row, array $raw, ?array $money, ?array $guess, ?string $description, array $semanticMarkers = []): array
    {
        return [
            'file_name' => (string)$row['file_name'],
            'sheet_name' => (string)$row['sheet_name'],
            'row_number' => (int)$row['row_number'],
            'parse_status' => (string)$row['parse_status'],
            'date_context' => $raw['_date_context'] ?? null,
            'description' => $description ?? trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? '')),
            'flow_type' => $money['flow_type'] ?? null,
            'sign' => $money['sign'] ?? null,
            'amount' => $money['amount'] ?? null,
            'current_rule_guess' => $guess['category_code'] ?? null,
            'semantic_markers' => $semanticMarkers,
            'raw' => $raw,
        ];
    }

    private function writeJson(string $fileName, array $payload): void
    {
        file_put_contents(
            $this->outputRoot . '/' . $fileName,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}

$outputRoot = $argv[1] ?? dirname(__DIR__) . '/storage/imports/claudia-z-dictionary';
(new ClaudiaZDictionaryCorpusExporter(rtrim($outputRoot, '/')))->run();
