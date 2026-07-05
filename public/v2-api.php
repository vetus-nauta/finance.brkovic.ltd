<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/v2/Api.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$route = (string)($_GET['route'] ?? ($_SERVER['PATH_INFO'] ?? ''));

if ($route === '') {
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $marker = '/v2-api.php';
    $position = strpos($path, $marker);
    $route = $position === false ? '/api' : substr($path, $position + strlen($marker));
}

$input = ql_input();

try {
    $api = new FinDeskV2Api();
    ql_json($api->handle($method, $route, $input, $_GET));
} catch (FinDeskV2HttpError $e) {
    $payload = json_decode($e->getMessage(), true);
    if (!is_array($payload)) {
        $payload = ['error' => $e->getMessage()];
    }
    ql_json(array_merge(['ok' => false], $payload), $e->status);
} catch (Throwable $e) {
    ql_json(['ok' => false, 'error' => 'v2_internal_error'], 500);
}
