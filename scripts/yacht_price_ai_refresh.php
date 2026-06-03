<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(1);
}

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/openai_provider.php';

function yp_regions(): array
{
    return [
        'europe_basic' => 'Europe baseline',
        'adriatic_balkans' => 'Adriatic / Balkans',
        'mediterranean_west' => 'Western Mediterranean',
        'usa_coastal' => 'USA coastal states',
        'asia_marina' => 'Asia marina hubs',
        'caribbean_islands' => 'Caribbean islands',
    ];
}

function yp_families(): array
{
    $config = function_exists('ql_config') ? ql_config() : [];
    $refresh = is_array($config['yacht_price_refresh'] ?? null) ? $config['yacht_price_refresh'] : [];

    return [
        'food' => [
            'label' => 'Food provisioning',
            'interval_days' => max(1, (int)($refresh['food_interval_days'] ?? 90)),
            'items' => [
                'Bottled water pack',
                'Soft drinks pack',
                'Coffee, tea, sugar',
                'Basic dry food package',
                'Fresh fruit and vegetables basket',
                'Cleaning supplies',
                'Paper towels and napkins',
            ],
        ],
        'fuel' => [
            'label' => 'Marine fuel',
            'interval_days' => max(1, (int)($refresh['fuel_interval_days'] ?? 30)),
            'items' => [
                'Marine diesel per liter',
                'Duty-free marine diesel per liter',
                'Gasoline per liter',
            ],
        ],
    ];
}

function yp_args(array $argv): array
{
    $args = [
        'family' => 'due',
        'region' => 'all',
        'run' => false,
        'force' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--run') {
            $args['run'] = true;
            continue;
        }
        if ($arg === '--force') {
            $args['force'] = true;
            continue;
        }
        if (str_starts_with($arg, '--family=')) {
            $args['family'] = substr($arg, 9);
            continue;
        }
        if (str_starts_with($arg, '--region=')) {
            $args['region'] = substr($arg, 9);
            continue;
        }
    }

    return $args;
}

function yp_storage_dir(): string
{
    $config = ql_config();
    $base = (string)($config['storage_path'] ?? dirname(__DIR__) . '/storage');
    return rtrim($base, '/') . '/yacht-price-catalog';
}

function yp_state_path(): string
{
    return yp_storage_dir() . '/ai-refresh-state.json';
}

function yp_read_json_file(string $path): array
{
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

function yp_write_json_file(string $path, array $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents(
        $path,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

function yp_days_since(?string $iso): ?int
{
    if (!$iso) {
        return null;
    }

    try {
        $then = new DateTimeImmutable($iso);
    } catch (Throwable $error) {
        return null;
    }

    return (int)floor((time() - $then->getTimestamp()) / 86400);
}

function yp_selected_regions(string $arg): array
{
    $regions = yp_regions();
    if ($arg === 'all') {
        return $regions;
    }

    return isset($regions[$arg]) ? [$arg => $regions[$arg]] : [];
}

function yp_selected_families(string $arg): array
{
    $families = yp_families();
    if ($arg === 'all' || $arg === 'due') {
        return $families;
    }

    return isset($families[$arg]) ? [$arg => $families[$arg]] : [];
}

function yp_plan(array $args, array $state): array
{
    $jobs = [];

    foreach (yp_selected_regions((string)$args['region']) as $region => $regionLabel) {
        foreach (yp_selected_families((string)$args['family']) as $family => $familyConfig) {
            $key = $region . ':' . $family;
            $lastSuccess = (string)($state[$key]['last_success_at'] ?? '');
            $days = yp_days_since($lastSuccess);
            $interval = (int)$familyConfig['interval_days'];
            $due = !empty($args['force']) || $days === null || $days >= $interval;

            if ($args['family'] === 'due' && !$due) {
                continue;
            }

            $jobs[] = [
                'region' => $region,
                'region_label' => $regionLabel,
                'family' => $family,
                'family_label' => $familyConfig['label'],
                'interval_days' => $interval,
                'last_success_at' => $lastSuccess ?: null,
                'days_since_success' => $days,
                'due' => $due,
                'items' => $familyConfig['items'],
            ];
        }
    }

    return $jobs;
}

function yp_schema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['region', 'family', 'currency', 'items', 'warnings'],
        'properties' => [
            'region' => ['type' => 'string'],
            'family' => ['type' => 'string'],
            'currency' => ['type' => 'string'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => [
                        'name',
                        'unit',
                        'net_estimate',
                        'full_price_estimate',
                        'duty_free_price_estimate',
                        'confidence',
                        'sources',
                        'notes',
                    ],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'unit' => ['type' => 'string'],
                        'net_estimate' => ['type' => 'number'],
                        'full_price_estimate' => ['type' => 'number'],
                        'duty_free_price_estimate' => ['type' => 'number'],
                        'confidence' => ['type' => 'string'],
                        'sources' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['title', 'url', 'observed_price', 'unit', 'currency', 'date_seen'],
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                    'observed_price' => ['type' => 'number'],
                                    'unit' => ['type' => 'string'],
                                    'currency' => ['type' => 'string'],
                                    'date_seen' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'notes' => ['type' => 'string'],
                    ],
                ],
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
    ];
}

function yp_prompt(array $job): string
{
    $items = implode(', ', $job['items']);

    return "Refresh yacht provisioning reference prices.\n"
        . "Region: {$job['region_label']} ({$job['region']}).\n"
        . "Family: {$job['family_label']} ({$job['family']}).\n"
        . "Items: {$items}.\n"
        . "Use current public sources where available. Prefer wholesale, distributor, marina, bunker supplier or official pump data.\n"
        . "Normalize values to EUR and the requested unit. Estimate net price and final visible price, but do not expose tax/markup math in notes.\n"
        . "For duty-free prices, estimate only when the source supports duty-free, bonded, tax-free or yacht bunker pricing. Otherwise use 0 and add a warning.\n"
        . "Do not invent. If sources are weak, set confidence to low and explain the limitation in notes.";
}

function yp_openai_payload(array $job): array
{
    $config = ql_openai_config();
    $payload = [
        'model' => $config['model'],
        'instructions' => 'You are a yacht provisioning price normalization worker. Return only structured JSON that matches the schema.',
        'input' => yp_prompt($job),
        'max_output_tokens' => $config['max_output_tokens'],
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'yacht_price_refresh',
                'strict' => true,
                'schema' => yp_schema(),
            ],
        ],
    ];

    if ($config['web_search_enabled']) {
        $payload['tools'] = [
            ['type' => $config['web_search_tool']],
        ];
        $payload['tool_choice'] = 'auto';
    }

    return $payload;
}

function yp_run_job(array $job): array
{
    $result = ql_openai_responses_create(yp_openai_payload($job));
    if (empty($result['ok'])) {
        return $result;
    }

    $response = $result['response'];
    $text = ql_openai_response_text($response);
    $parsed = json_decode($text, true);

    if (!is_array($parsed)) {
        return [
            'ok' => false,
            'error' => 'model_output_json_failed',
            'response_id' => (string)($response['id'] ?? ''),
            'text' => substr($text, 0, 1200),
        ];
    }

    return [
        'ok' => true,
        'response_id' => (string)($response['id'] ?? ''),
        'data' => $parsed,
    ];
}

$args = yp_args($argv);
$state = yp_read_json_file(yp_state_path());
$jobs = yp_plan($args, $state);
$openaiStatus = ql_openai_key_status();

if (!$jobs) {
    echo json_encode([
        'ok' => true,
        'mode' => $args['run'] ? 'run' : 'dry_run',
        'message' => 'no_due_jobs',
        'openai' => $openaiStatus,
        'jobs' => [],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if (!$args['run']) {
    echo json_encode([
        'ok' => true,
        'mode' => 'dry_run',
        'message' => 'no_api_calls_made',
        'openai' => $openaiStatus,
        'jobs' => $jobs,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

$results = [];
$runAt = gmdate('c');

foreach ($jobs as $job) {
    if (!$job['due'] && empty($args['force'])) {
        $results[] = [
            'ok' => true,
            'region' => $job['region'],
            'family' => $job['family'],
            'skipped' => 'not_due',
        ];
        continue;
    }

    $result = yp_run_job($job);
    $key = $job['region'] . ':' . $job['family'];

    if (!empty($result['ok'])) {
        $snapshot = [
            'ok' => true,
            'generated_at' => $runAt,
            'region' => $job['region'],
            'region_label' => $job['region_label'],
            'family' => $job['family'],
            'family_label' => $job['family_label'],
            'model' => ql_openai_config()['model'],
            'response_id' => $result['response_id'],
            'data' => $result['data'],
        ];
        $file = yp_storage_dir() . '/' . gmdate('Ymd-His') . '-' . $job['region'] . '-' . $job['family'] . '.json';
        yp_write_json_file($file, $snapshot);

        $state[$key] = [
            'last_success_at' => $runAt,
            'last_snapshot' => $file,
            'last_response_id' => $result['response_id'],
        ];

        $results[] = [
            'ok' => true,
            'region' => $job['region'],
            'family' => $job['family'],
            'snapshot' => $file,
        ];
        continue;
    }

    $state[$key]['last_error_at'] = $runAt;
    $state[$key]['last_error'] = $result['error'] ?? 'unknown_error';
    $results[] = array_merge([
        'ok' => false,
        'region' => $job['region'],
        'family' => $job['family'],
    ], $result);
}

yp_write_json_file(yp_state_path(), $state);

echo json_encode([
    'ok' => !array_filter($results, fn($result) => empty($result['ok'])),
    'mode' => 'run',
    'openai' => $openaiStatus,
    'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
