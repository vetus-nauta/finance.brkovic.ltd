<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/v2/Api.php';

$seedPath = dirname(__DIR__, 2) . '/schemas/categories.seed.json';
$seed = json_decode((string)file_get_contents($seedPath), true);

if (!is_array($seed) || count($seed) === 0) {
    fwrite(STDERR, "Category seed is empty or invalid\n");
    exit(1);
}

$routes = \FinDesk\V2\Api::describeRoutes();

foreach (['POST /workspaces', 'POST /workspaces/{workspaceId}/entries', 'PATCH /entries/{entryId}', 'GET /workspaces/{workspaceId}/categories'] as $required) {
    if (!in_array($required, $routes, true)) {
        fwrite(STDERR, "Missing route: {$required}\n");
        exit(1);
    }
}

echo "FinDesk v2 API smoke OK: " . count($routes) . " routes, " . count($seed) . " seed categories\n";
