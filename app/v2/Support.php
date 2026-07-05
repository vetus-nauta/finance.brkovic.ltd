<?php

declare(strict_types=1);

final class FinDeskV2HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}

final class FinDeskV2Support
{
    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function jsonEncode($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new FinDeskV2HttpError(500, 'json_encode_failed');
        }

        return $json;
    }

    public static function jsonDecode(?string $json, $fallback)
    {
        if ($json === null || $json === '') {
            return $fallback;
        }

        $decoded = json_decode($json, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }

    public static function requireString(array $input, string $key, int $max = 500): string
    {
        $value = trim((string)($input[$key] ?? ''));
        if ($value === '') {
            throw new FinDeskV2HttpError(422, 'missing_' . $key);
        }

        return mb_substr($value, 0, $max);
    }

    public static function optionalString(array $input, string $key, ?string $default = null, int $max = 500): ?string
    {
        if (!array_key_exists($key, $input)) {
            return $default;
        }

        $value = trim((string)$input[$key]);
        return $value === '' ? $default : mb_substr($value, 0, $max);
    }

    public static function enum(string $value, array $allowed, string $key): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        return $value;
    }

    public static function date(array $input, string $key = 'date'): string
    {
        $value = self::optionalString($input, $key, date('Y-m-d'), 20);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$value);

        if (!$dt || $dt->format('Y-m-d') !== $value) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        return $value;
    }

    public static function nullableAmount($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new FinDeskV2HttpError(422, 'invalid_amount');
        }

        return number_format((float)$value, 2, '.', '');
    }

    public static function normalizeRoute(string $route): string
    {
        $route = '/' . trim($route, '/');
        return $route === '/' ? '/api' : $route;
    }
}
