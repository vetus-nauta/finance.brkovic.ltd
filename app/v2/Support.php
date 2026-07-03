<?php
declare(strict_types=1);

namespace FinDesk\V2;

use InvalidArgumentException;

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function assert_uuid(string $value, string $field): string
{
    $value = trim($value);

    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
        throw new InvalidArgumentException($field . '_invalid');
    }

    return strtolower($value);
}

function optional_uuid($value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return assert_uuid((string)$value, $field);
}

function clean_string($value, string $field, int $maxLength): string
{
    $value = trim((string)$value);

    if ($value === '') {
        throw new InvalidArgumentException($field . '_required');
    }

    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException($field . '_too_long');
    }

    return $value;
}

function optional_clean_string($value, int $maxLength): ?string
{
    $value = trim((string)($value ?? ''));

    if ($value === '') {
        return null;
    }

    if (mb_strlen($value, 'UTF-8') > $maxLength) {
        throw new InvalidArgumentException('value_too_long');
    }

    return $value;
}

function iso_date($value): string
{
    $value = trim((string)$value);

    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        throw new InvalidArgumentException('date_invalid');
    }

    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        throw new InvalidArgumentException('date_invalid');
    }

    return $value;
}

function json_encode_db($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function decode_json_field($value, $fallback)
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $decoded = json_decode((string)$value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}
