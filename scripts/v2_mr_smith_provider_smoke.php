<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/v2/Support.php';
require_once $root . '/app/v2/InternetReferenceProvider.php';

$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expectProviderError = static function (FinDeskV2InternetReferenceProvider $provider, array $request, string $message) use (&$failures): void {
    try {
        $provider->lookup($request);
        $failures[] = $message . ' did not fail';
    } catch (FinDeskV2HttpError) {
        return;
    } catch (Throwable $error) {
        $failures[] = $message . ' failed with unexpected error: ' . $error->getMessage();
    }
};

putenv('FINDESK_V2_MR_SMITH_ALLOWLIST_ENABLED=0');
putenv('FINDESK_V2_MR_SMITH_ALLOWED_DOMAINS=example.com');
$assert(FinDeskV2InternetReferenceProviderConfig::allowlistedHttpEnabled() === false, 'provider env gate must default off');

putenv('FINDESK_V2_MR_SMITH_ALLOWLIST_ENABLED=1');
putenv('FINDESK_V2_MR_SMITH_ALLOWED_DOMAINS=example.com,*.example.com,127.0.0.1,/bad,shop.example.com');
$domains = FinDeskV2InternetReferenceProviderConfig::allowedDomains();
$assert($domains === ['example.com', 'shop.example.com'], 'provider allowlist must keep exact safe domains only');

$provider = new FinDeskV2AllowlistedHttpInternetReferenceProvider(['example.com']);
$expectProviderError($provider, [
    'sanitized_query' => 'Example',
], 'missing candidate URL');
$expectProviderError($provider, [
    'sanitized_query' => 'Example',
    'candidate_url' => 'http://example.com',
], 'non-HTTPS URL');
$expectProviderError($provider, [
    'sanitized_query' => 'Example',
    'candidate_url' => 'https://shop.example.com',
], 'implicit subdomain allowlist');

$expectProviderError(new FinDeskV2AllowlistedHttpInternetReferenceProvider(['localhost']), [
    'sanitized_query' => 'Local',
    'candidate_url' => 'https://localhost',
], 'localhost URL');
$expectProviderError(new FinDeskV2AllowlistedHttpInternetReferenceProvider(['192.168.1.10']), [
    'sanitized_query' => 'Private',
    'candidate_url' => 'https://192.168.1.10',
], 'private IP URL');

if ($failures) {
    echo "FinDesk v2 Mr. Smith provider smoke: FAIL\n";
    foreach ($failures as $failure) {
        echo "- {$failure}\n";
    }
    exit(1);
}

echo "FinDesk v2 Mr. Smith provider smoke: OK\n";
