<?php

declare(strict_types=1);

interface FinDeskV2InternetReferenceProvider
{
    public function lookup(array $request): array;
}

final class FinDeskV2InternetReferenceProviderConfig
{
    public static function allowlistedHttpEnabled(): bool
    {
        return in_array(strtolower(trim((string)getenv('FINDESK_V2_MR_SMITH_ALLOWLIST_ENABLED'))), ['1', 'true', 'yes'], true);
    }

    public static function allowedDomains(): array
    {
        $raw = (string)getenv('FINDESK_V2_MR_SMITH_ALLOWED_DOMAINS');
        $domains = [];
        foreach (explode(',', $raw) as $domain) {
            $domain = strtolower(trim($domain));
            if ($domain !== '' && self::isSafeDomain($domain)) {
                $domains[] = $domain;
            }
        }

        return array_values(array_unique($domains));
    }

    private static function isSafeDomain(string $domain): bool
    {
        if (str_contains($domain, '*') || str_contains($domain, '/')) {
            return false;
        }
        if (filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+\z/', $domain) === 1;
    }
}

final class FinDeskV2StubInternetReferenceProvider implements FinDeskV2InternetReferenceProvider
{
    public function lookup(array $request): array
    {
        $sanitizedQuery = trim((string)($request['sanitized_query'] ?? ''));

        return [
            'provider_key' => 'stub',
            'provider_request_id' => null,
            'result_status' => 'stub',
            'latency_ms' => 0,
            'matches' => [[
                'label' => 'No external lookup performed',
                'business_type' => 'beta_stub',
                'location' => null,
                'aliases' => [],
                'source_url' => null,
                'source_domain' => null,
                'source_type' => 'stub',
                'retrieved_at' => date(DATE_ATOM),
                'confidence' => '0.00',
                'uncertainty_reason' => 'Mr. Smith beta is consent/provenance preview only. Internet lookup is not enabled.',
                'query_preview' => $sanitizedQuery,
            ]],
        ];
    }
}

final class FinDeskV2AllowlistedHttpInternetReferenceProvider implements FinDeskV2InternetReferenceProvider
{
    private const MAX_BYTES = 16384;
    private const TIMEOUT_SECONDS = 2;

    public function __construct(private readonly array $allowedDomains)
    {
    }

    public function lookup(array $request): array
    {
        $started = microtime(true);
        $url = trim((string)($request['candidate_url'] ?? ''));
        if ($url === '') {
            throw new FinDeskV2HttpError(422, 'missing_candidate_url');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($scheme !== 'https' || $host === '') {
            throw new FinDeskV2HttpError(422, 'unsafe_candidate_url');
        }
        if (!$this->isAllowedHost($host)) {
            throw new FinDeskV2HttpError(422, 'candidate_url_not_allowlisted');
        }
        if ($this->hasUnsafeResolvedAddress($host)) {
            throw new FinDeskV2HttpError(422, 'unsafe_candidate_url');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
                'header' => implode("\r\n", [
                    'User-Agent: FinDesk-Mr-Smith-Beta/0.1',
                    'Accept: text/html,text/plain;q=0.8,*/*;q=0.2',
                    'Connection: close',
                ]),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $body = @file_get_contents($url, false, $context, 0, self::MAX_BYTES);
        } catch (Throwable) {
            $body = false;
        }
        if ($body === false) {
            return [
                'provider_key' => 'allowlisted_http',
                'provider_request_id' => hash('sha256', $url),
                'result_status' => 'error',
                'latency_ms' => $this->latencyMs($started),
                'matches' => [[
                    'label' => $host,
                    'business_type' => 'allowlisted_reference',
                    'location' => null,
                    'aliases' => [],
                    'source_url' => $url,
                    'source_domain' => $host,
                    'source_type' => 'allowlisted_http',
                    'retrieved_at' => date(DATE_ATOM),
                    'confidence' => '0.00',
                    'uncertainty_reason' => 'Allowlisted source could not be fetched within the beta limits.',
                ]],
            ];
        }

        $title = $this->extractTitle((string)$body);

        return [
            'provider_key' => 'allowlisted_http',
            'provider_request_id' => hash('sha256', $url),
            'result_status' => 'ok',
            'latency_ms' => $this->latencyMs($started),
            'matches' => [[
                'label' => $title !== '' ? $title : $host,
                'business_type' => 'allowlisted_reference',
                'location' => null,
                'aliases' => [],
                'source_url' => $url,
                'source_domain' => $host,
                'source_type' => 'allowlisted_http',
                'retrieved_at' => date(DATE_ATOM),
                'confidence' => '0.10',
                'uncertainty_reason' => 'Allowlisted web evidence is reference metadata only. It is not an accounting classification.',
            ]],
        ];
    }

    private function isAllowedHost(string $host): bool
    {
        foreach ($this->allowedDomains as $domain) {
            if ($host === $domain) {
                return true;
            }
        }

        return false;
    }

    private function hasUnsafeResolvedAddress(string $host): bool
    {
        if ($this->isUnsafeHostLiteral($host)) {
            return true;
        }

        $addresses = @gethostbynamel($host);
        if ($addresses === false || $addresses === []) {
            return true;
        }

        foreach ($addresses as $address) {
            if ($this->isUnsafeIp($address)) {
                return true;
            }
        }

        return false;
    }

    private function isUnsafeHostLiteral(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return false;
    }

    private function isUnsafeIp(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    private function extractTitle(string $body): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $match) !== 1) {
            return '';
        }

        $title = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/\s+/u', ' ', (string)$title);

        return trim(mb_substr((string)$title, 0, 190));
    }

    private function latencyMs(float $started): int
    {
        return max(0, (int)round((microtime(true) - $started) * 1000));
    }
}
