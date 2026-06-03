<?php

function ql_yacht_price_storage_dir(): string
{
    $config = function_exists('ql_config') ? ql_config() : [];
    $base = (string)($config['storage_path'] ?? dirname(__DIR__) . '/storage');
    return rtrim($base, '/') . '/yacht-price-approved';
}

function ql_yacht_price_key(string $value, array $allowed, string $fallback): string
{
    $value = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($value)));
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function ql_yacht_price_approved_catalog(array $input): array
{
    ql_require_user();

    $region = ql_yacht_price_key((string)($input['region'] ?? 'adriatic_balkans'), [
        'europe_basic',
        'adriatic_balkans',
        'mediterranean_west',
        'usa_coastal',
        'asia_marina',
        'caribbean_islands',
    ], 'adriatic_balkans');
    $family = ql_yacht_price_key((string)($input['family'] ?? 'fuel'), ['fuel', 'food'], 'fuel');
    $path = ql_yacht_price_storage_dir() . '/active-' . $region . '-' . $family . '.json';

    if (!is_file($path)) {
        return [
            'ok' => true,
            'catalog' => null,
            'message' => 'approved_catalog_missing',
            'region' => $region,
            'family' => $family,
        ];
    }

    $raw = file_get_contents($path);
    $catalog = $raw ? json_decode($raw, true) : null;
    if (!is_array($catalog)) {
        return [
            'ok' => false,
            'error' => 'approved_catalog_bad_json',
            'region' => $region,
            'family' => $family,
        ];
    }

    return [
        'ok' => true,
        'catalog' => [
            'status' => (string)($catalog['status'] ?? ''),
            'active_catalog' => !empty($catalog['active_catalog']),
            'ui_published' => !empty($catalog['ui_published']),
            'approved_at' => (string)($catalog['approved_at'] ?? ''),
            'approver' => (string)($catalog['approver'] ?? ''),
            'region' => (string)($catalog['region'] ?? $region),
            'region_label' => (string)($catalog['region_label'] ?? ''),
            'family' => (string)($catalog['family'] ?? $family),
            'family_label' => (string)($catalog['family_label'] ?? ''),
            'source_candidate' => (string)($catalog['source_candidate'] ?? ''),
            'source_snapshot' => (string)($catalog['source_snapshot'] ?? ''),
            'warnings' => array_values(is_array($catalog['warnings'] ?? null) ? $catalog['warnings'] : []),
            'policy' => is_array($catalog['policy'] ?? null) ? $catalog['policy'] : [],
            'prices' => is_array($catalog['prices'] ?? null) ? $catalog['prices'] : [],
            'blocked_items' => array_values(is_array($catalog['blocked_items'] ?? null) ? $catalog['blocked_items'] : []),
        ],
    ];
}
