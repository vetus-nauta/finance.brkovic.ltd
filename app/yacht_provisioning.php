<?php

function ql_yacht_provision_data_dir(): string
{
    return __DIR__ . '/data/yacht_provisioning';
}

function ql_yacht_provision_json(string $name): array
{
    $path = ql_yacht_provision_data_dir() . '/' . $name;
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }

    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function ql_yacht_provision_error(string $code, string $message): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ];
}

function ql_yacht_provision_bool(array $filters, string $key, bool $default): bool
{
    if (!array_key_exists($key, $filters)) {
        return $default;
    }

    return filter_var($filters[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
}

function ql_yacht_provision_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $result = [];
    foreach ($value as $item) {
        $item = trim((string)$item);
        if ($item !== '') {
            $result[] = $item;
        }
    }

    return array_values(array_unique($result));
}

function ql_yacht_provision_filters(array $input): array
{
    $filters = is_array($input['filters'] ?? null) ? $input['filters'] : [];

    $storage = (string)($filters['storage'] ?? 'normal_yacht');
    if (!in_array($storage, ['small_fridge', 'normal_yacht', 'large_yacht'], true)) {
        $storage = 'normal_yacht';
    }

    return [
        'include_categories' => ql_yacht_provision_list($filters['include_categories'] ?? []),
        'exclude_categories' => ql_yacht_provision_list($filters['exclude_categories'] ?? []),
        'include_household' => ql_yacht_provision_bool($filters, 'include_household', true),
        'include_hygiene' => ql_yacht_provision_bool($filters, 'include_hygiene', true),
        'include_alcohol' => ql_yacht_provision_bool($filters, 'include_alcohol', false),
        'include_bbq' => ql_yacht_provision_bool($filters, 'include_bbq', true),
        'include_children' => ql_yacht_provision_bool($filters, 'include_children', false),
        'dietary' => ql_yacht_provision_list($filters['dietary'] ?? []),
        'storage' => $storage,
        'route_restock_possible' => ql_yacht_provision_bool($filters, 'route_restock_possible', true),
        'perishable_only' => ql_yacht_provision_bool($filters, 'perishable_only', false),
        'long_storage_only' => ql_yacht_provision_bool($filters, 'long_storage_only', false),
    ];
}

function ql_yacht_provision_profile_multiplier(array $filtersData, string $profile): float
{
    $profiles = is_array($filtersData['profiles'] ?? null) ? $filtersData['profiles'] : [];
    return (float)($profiles[$profile]['multiplier'] ?? 1.0);
}

function ql_yacht_provision_meal_multiplier(string $mealPlan, string $categoryKey): float
{
    $plans = [
        'breakfast_only' => [
            'breakfast' => 1.0,
            'water_drinks' => 1.0,
            'dairy' => 0.8,
            'fruit' => 0.7,
            'snacks_antipasti' => 0.6,
            'sweets' => 0.6,
            'household' => 0.8,
            'hygiene_first_aid' => 1.0,
        ],
        'breakfast_lunch' => [
            'breakfast' => 1.0,
            'water_drinks' => 1.0,
            'dairy' => 0.9,
            'fruit' => 0.9,
            'vegetables_herbs' => 0.75,
            'dry_goods_sides' => 0.75,
            'meat_fish_protein' => 0.65,
            'snacks_antipasti' => 0.8,
            'household' => 0.9,
            'hygiene_first_aid' => 1.0,
        ],
        'full_onboard' => [
            'water_drinks' => 1.1,
            'breakfast' => 1.15,
            'meat_fish_protein' => 1.25,
            'vegetables_herbs' => 1.25,
            'fruit' => 1.15,
            'dry_goods_sides' => 1.25,
            'dairy' => 1.15,
            'snacks_antipasti' => 1.15,
            'canned_emergency' => 1.1,
            'oils_sauces_spices' => 1.15,
            'sweets' => 1.1,
            'household' => 1.15,
            'hygiene_first_aid' => 1.0,
        ],
    ];

    if ($mealPlan === 'breakfast_onboard_lunch_light_dinner_mixed') {
        return 1.0;
    }

    return (float)($plans[$mealPlan][$categoryKey] ?? 0.5);
}

function ql_yacht_provision_item_allowed(array $item, array $filters): bool
{
    $category = (string)($item['category_key'] ?? '');
    if ($category === '') {
        return false;
    }

    if ($filters['include_categories'] && !in_array($category, $filters['include_categories'], true)) {
        return false;
    }

    if (in_array($category, $filters['exclude_categories'], true)) {
        return false;
    }

    if ($category === 'household' && !$filters['include_household']) {
        return false;
    }

    if ($category === 'hygiene_first_aid' && !$filters['include_hygiene']) {
        return false;
    }

    if ($category === 'alcohol' && !$filters['include_alcohol']) {
        return false;
    }

    if ($category === 'children' && !$filters['include_children']) {
        return false;
    }

    $itemFilters = is_array($item['filters'] ?? null) ? $item['filters'] : [];
    if (in_array('bbq', $itemFilters, true) && !$filters['include_bbq']) {
        return false;
    }

    if (in_array('children', $itemFilters, true) && !$filters['include_children']) {
        return false;
    }

    if (in_array('alcohol', $itemFilters, true) && !$filters['include_alcohol']) {
        return false;
    }

    $dietary = $filters['dietary'];
    if (in_array('no_pork', $dietary, true) && in_array('exclude_if_no_pork', $itemFilters, true)) {
        return false;
    }

    if (in_array('no_seafood', $dietary, true) && in_array('exclude_if_no_seafood', $itemFilters, true)) {
        return false;
    }

    $perishable = !empty($item['perishable']);
    if ($filters['perishable_only'] && !$perishable) {
        return false;
    }

    if ($filters['long_storage_only'] && $perishable) {
        return false;
    }

    return true;
}

function ql_yacht_provision_round(float $quantity, string $rounding)
{
    if ($rounding === 'manual') {
        return null;
    }

    if ($rounding === 'ceil_kg_0_5') {
        return ceil($quantity * 2) / 2;
    }

    if (preg_match('/^ceil_pack_(\d+)$/', $rounding, $match)) {
        $pack = max(1, (int)$match[1]);
        return (float)(ceil($quantity / $pack) * $pack);
    }

    return (float)ceil($quantity);
}

function ql_yacht_provision_unit_label(string $unit, string $language): string
{
    $labels = [
        'ru' => [
            'bag' => 'пакет',
            'bar' => 'шт.',
            'bottle' => 'бут.',
            'bottle_0_5l' => 'бут. 0.5 л',
            'bottle_1_5l' => 'бут. 1.5 л',
            'bottle_5l' => 'бут. 5 л',
            'bunch' => 'пучок',
            'can' => 'банка',
            'can_or_bottle' => 'банка/бут.',
            'jar' => 'банка',
            'kg' => 'кг',
            'liter' => 'л',
            'loaf' => 'буханка',
            'pack' => 'уп.',
            'piece' => 'шт.',
            'roll' => 'рулон',
        ],
        'en' => [
            'bag' => 'bag',
            'bar' => 'piece',
            'bottle' => 'bottle',
            'bottle_0_5l' => '0.5 L bottle',
            'bottle_1_5l' => '1.5 L bottle',
            'bottle_5l' => '5 L bottle',
            'bunch' => 'bunch',
            'can' => 'can',
            'can_or_bottle' => 'can/bottle',
            'jar' => 'jar',
            'kg' => 'kg',
            'liter' => 'L',
            'loaf' => 'loaf',
            'pack' => 'pack',
            'piece' => 'piece',
            'roll' => 'roll',
        ],
    ];

    return $labels[$language][$unit] ?? $unit;
}

function ql_yacht_provision_display_quantity($quantity, string $unit, string $rounding, string $language): string
{
    if ($quantity === null) {
        return $language === 'ru' ? 'Уточнить вручную' : 'Manual estimate';
    }

    $quantityText = rtrim(rtrim(number_format((float)$quantity, 2, '.', ''), '0'), '.');
    $unitLabel = ql_yacht_provision_unit_label($unit, $language);

    if (preg_match('/^ceil_pack_(\d+)$/', $rounding, $match)) {
        $pack = max(1, (int)$match[1]);
        $packs = (int)ceil((float)$quantity / $pack);
        return $language === 'ru'
            ? $quantityText . ' ' . $unitLabel . ' (' . $packs . ' уп. x ' . $pack . ')'
            : $quantityText . ' ' . $unitLabel . ' (' . $packs . ' packs x ' . $pack . ')';
    }

    return $quantityText . ' ' . $unitLabel;
}

function ql_yacht_provision_water_liters(int $peopleCount, int $days, string $profile): int
{
    $perPerson = in_array($profile, ['charter_comfort', 'onboard_full'], true) ? 4.0 : 3.5;
    $safety = max(10, $peopleCount * 2);
    return (int)ceil($peopleCount * $days * $perPerson + $safety);
}

function ql_yacht_provision_calculate(array $input): array
{
    $peopleCount = (int)($input['people_count'] ?? 0);
    if ($peopleCount < 1) {
        return ql_yacht_provision_error('INVALID_PEOPLE_COUNT', 'people_count must be greater than 0');
    }

    $days = (int)($input['days'] ?? 0);
    if ($days < 1) {
        return ql_yacht_provision_error('INVALID_DAYS', 'days must be greater than 0');
    }

    $filtersData = ql_yacht_provision_json('filters.json');
    $profiles = is_array($filtersData['profiles'] ?? null) ? array_keys($filtersData['profiles']) : [];
    $profile = (string)($input['profile'] ?? 'balanced');
    if (!$profiles || !in_array($profile, $profiles, true)) {
        $profile = 'balanced';
    }

    $mealPlans = is_array($filtersData['meal_plans'] ?? null) ? array_keys($filtersData['meal_plans']) : [];
    $mealPlan = (string)($input['meal_plan'] ?? 'breakfast_onboard_lunch_light_dinner_mixed');
    if ($mealPlans && !in_array($mealPlan, $mealPlans, true)) {
        $mealPlan = 'breakfast_onboard_lunch_light_dinner_mixed';
    }

    $language = (string)($input['language'] ?? 'ru');
    if (!in_array($language, ['ru', 'en'], true)) {
        $language = 'ru';
    }

    $filters = ql_yacht_provision_filters($input);
    $categoriesData = ql_yacht_provision_json('categories.json');
    $catalog = ql_yacht_provision_json('provision_catalog.json');
    $items = is_array($catalog['items'] ?? null) ? $catalog['items'] : [];

    if (!$categoriesData || !$items) {
        return ql_yacht_provision_error('CATALOG_UNAVAILABLE', 'Yacht provision catalog is unavailable');
    }

    $categories = [];
    foreach ($categoriesData as $category) {
        $key = (string)($category['key'] ?? '');
        if ($key === '') {
            continue;
        }
        $categories[$key] = [
            'category_key' => $key,
            'title' => (string)($category[$language === 'ru' ? 'title_ru' : 'title_en'] ?? $key),
            'priority' => (int)($category['priority'] ?? 999),
            'items' => [],
        ];
    }

    $profileMultiplier = ql_yacht_provision_profile_multiplier($filtersData, $profile);
    $warnings = [];
    $hasRestockPerishables = false;

    foreach ($items as $item) {
        if (!is_array($item) || !ql_yacht_provision_item_allowed($item, $filters)) {
            continue;
        }

        $categoryKey = (string)($item['category_key'] ?? '');
        if (!isset($categories[$categoryKey])) {
            continue;
        }

        $base = (float)($item['base_quantity'] ?? 0);
        $perPersonPerDay = (float)($item['per_person_per_day'] ?? 0);
        $mealMultiplier = ql_yacht_provision_meal_multiplier($mealPlan, $categoryKey);
        $raw = $base + $perPersonPerDay * $peopleCount * $days;
        $profiled = max(0, $raw * $profileMultiplier * $mealMultiplier);
        $rounding = (string)($item['rounding'] ?? 'ceil_integer');
        $quantity = ql_yacht_provision_round($profiled, $rounding);
        $unit = (string)($item['unit'] ?? 'piece');
        $perishable = !empty($item['perishable']);
        $routeRestock = !empty($item['route_restock_recommended']);

        if ($perishable && $routeRestock && $filters['route_restock_possible']) {
            $hasRestockPerishables = true;
        }

        $categories[$categoryKey]['items'][] = [
            'item_key' => (string)($item['item_key'] ?? ''),
            'title' => (string)($item[$language === 'ru' ? 'title_ru' : 'title_en'] ?? ($item['item_key'] ?? '')),
            'title_en' => (string)($item['title_en'] ?? ''),
            'title_ru' => (string)($item['title_ru'] ?? ''),
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_label' => ql_yacht_provision_unit_label($unit, $language),
            'display_quantity' => ql_yacht_provision_display_quantity($quantity, $unit, $rounding, $language),
            'note' => (string)($item[$language === 'ru' ? 'note_ru' : 'note_en'] ?? ''),
            'priority' => (string)($item['priority'] ?? 'standard'),
            'perishable' => $perishable,
            'optional' => !empty($item['optional']),
            'route_restock_recommended' => $routeRestock,
            'filters' => is_array($item['filters'] ?? null) ? array_values($item['filters']) : [],
        ];
    }

    $outCategories = array_values(array_filter($categories, fn($category) => !empty($category['items'])));
    usort($outCategories, fn($a, $b) => ($a['priority'] <=> $b['priority']));

    $totalItems = 0;
    foreach ($outCategories as &$category) {
        usort($category['items'], function ($a, $b) {
            $priorityOrder = ['essential' => 1, 'important' => 2, 'standard' => 3, 'optional' => 4];
            return ($priorityOrder[$a['priority']] ?? 99) <=> ($priorityOrder[$b['priority']] ?? 99);
        });
        unset($category['priority']);
        $totalItems += count($category['items']);
    }
    unset($category);

    if ($hasRestockPerishables) {
        $warnings[] = $language === 'ru'
            ? 'Свежие фрукты, овощи, хлеб и рыбу лучше дозакупать по маршруту, если это возможно.'
            : 'Fresh fruit, vegetables, bread and fish are better restocked during the route if possible.';
    }

    if ($peopleCount >= 20) {
        $warnings[] = $language === 'ru'
            ? 'Для большой группы проверьте холодильники и разделите скоропортящиеся продукты на первую загрузку и дозакупку.'
            : 'For a large crew, check fridge capacity and split perishables into first load and route restock.';
    }

    return [
        'ok' => true,
        'meta' => [
            'people_count' => $peopleCount,
            'days' => $days,
            'currency' => 'EUR',
            'profile' => $profile,
            'meal_plan' => $mealPlan,
            'language' => $language,
            'catalog_version' => (string)($catalog['version'] ?? ''),
            'filters' => $filters,
        ],
        'warnings' => array_values(array_unique($warnings)),
        'summary' => [
            'total_categories' => count($outCategories),
            'total_items' => $totalItems,
            'water_liters_estimated' => ql_yacht_provision_water_liters($peopleCount, $days, $profile),
        ],
        'categories' => $outCategories,
    ];
}
