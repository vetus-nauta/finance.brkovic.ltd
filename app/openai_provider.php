<?php

function ql_openai_env_value($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\$\{?([A-Z0-9_]+)\}?$/', $value, $match)) {
        return trim((string)(getenv($match[1]) ?: ''));
    }

    return $value;
}

function ql_openai_config(): array
{
    $config = function_exists('ql_config') ? ql_config() : [];
    $openai = is_array($config['openai'] ?? null) ? $config['openai'] : [];

    $apiKey = ql_openai_env_value($openai['api_key'] ?? '');
    $apiKeyFile = ql_openai_env_value($openai['api_key_file'] ?? '');
    if ($apiKey === '' && $apiKeyFile !== '' && is_file($apiKeyFile)) {
        $apiKey = trim((string)file_get_contents($apiKeyFile));
    }

    if ($apiKey === '') {
        $apiKey = trim((string)(getenv('OPENAI_API_KEY') ?: ''));
    }

    if (in_array($apiKey, ['change-me', 'changeme', 'todo'], true)) {
        $apiKey = '';
    }

    $model = trim((string)($openai['model'] ?? 'gpt-5.4-mini'));
    if ($model === '') {
        $model = 'gpt-5.4-mini';
    }

    $endpoint = rtrim((string)($openai['endpoint'] ?? 'https://api.openai.com/v1'), '/');
    if ($endpoint === '') {
        $endpoint = 'https://api.openai.com/v1';
    }

    $timeout = (int)($openai['timeout_seconds'] ?? 90);
    if ($timeout < 10) {
        $timeout = 10;
    }

    $maxOutputTokens = (int)($openai['max_output_tokens'] ?? 1800);
    if ($maxOutputTokens < 200) {
        $maxOutputTokens = 200;
    }

    return [
        'enabled' => !empty($openai['enabled']),
        'has_key' => $apiKey !== '',
        'api_key' => $apiKey,
        'api_key_file' => $apiKeyFile,
        'model' => $model,
        'endpoint' => $endpoint,
        'timeout_seconds' => $timeout,
        'max_output_tokens' => $maxOutputTokens,
        'web_search_enabled' => !array_key_exists('web_search_enabled', $openai) || !empty($openai['web_search_enabled']),
        'web_search_tool' => (string)($openai['web_search_tool'] ?? 'web_search'),
    ];
}

function ql_openai_key_status(): array
{
    $config = ql_openai_config();

    return [
        'enabled' => $config['enabled'],
        'has_key' => $config['has_key'],
        'model' => $config['model'],
        'endpoint' => $config['endpoint'],
        'web_search_enabled' => $config['web_search_enabled'],
    ];
}

function ql_openai_responses_create(array $payload): array
{
    $config = ql_openai_config();

    if (!$config['enabled']) {
        return ['ok' => false, 'error' => 'openai_disabled'];
    }

    if (!$config['has_key']) {
        return ['ok' => false, 'error' => 'openai_key_missing'];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'curl_unavailable'];
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return ['ok' => false, 'error' => 'payload_json_failed'];
    }

    $ch = curl_init($config['endpoint'] . '/responses');
    if (!$ch) {
        return ['ok' => false, 'error' => 'curl_init_failed'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => $config['timeout_seconds'],
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($responseBody === false || $responseBody === '') {
        return [
            'ok' => false,
            'error' => 'openai_empty_response',
            'status' => $status,
            'curl_error' => $curlError,
        ];
    }

    $json = json_decode((string)$responseBody, true);
    if (!is_array($json)) {
        return [
            'ok' => false,
            'error' => 'openai_bad_json',
            'status' => $status,
            'body' => substr((string)$responseBody, 0, 1000),
        ];
    }

    if ($status < 200 || $status >= 300) {
        return [
            'ok' => false,
            'error' => 'openai_http_error',
            'status' => $status,
            'message' => (string)($json['error']['message'] ?? ''),
            'response' => $json,
        ];
    }

    return [
        'ok' => true,
        'status' => $status,
        'response' => $json,
    ];
}

function ql_openai_response_text(array $response): string
{
    $chunks = [];

    foreach (($response['output'] ?? []) as $output) {
        foreach (($output['content'] ?? []) as $content) {
            if (isset($content['text']) && is_string($content['text'])) {
                $chunks[] = $content['text'];
            }
        }
    }

    if (!$chunks && isset($response['output_text']) && is_string($response['output_text'])) {
        $chunks[] = $response['output_text'];
    }

    return trim(implode("\n", $chunks));
}
