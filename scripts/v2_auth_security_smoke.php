<?php

declare(strict_types=1);

final class AuthSmokeResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $json,
        public readonly string $raw,
        public readonly array $headers
    ) {
    }
}

function authSmokeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function authSmokeDb(): PDO
{
    $socket = (string)getenv('FINDESK_V2_AUTH_SOCKET');
    $dbName = (string)getenv('FINDESK_V2_AUTH_DB');
    authSmokeAssert($socket !== '', 'Missing FINDESK_V2_AUTH_SOCKET');
    authSmokeAssert($dbName !== '', 'Missing FINDESK_V2_AUTH_DB');

    return new PDO('mysql:unix_socket=' . $socket . ';dbname=' . $dbName . ';charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function authSmokeRequest(string $action, array $body): AuthSmokeResponse
{
    $base = rtrim((string)getenv('FINDESK_V2_AUTH_BASE'), '/');
    authSmokeAssert($base !== '', 'Missing FINDESK_V2_AUTH_BASE');

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nHost: finance.brkovic.ltd\r\n",
            'content' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'ignore_errors' => true,
            'timeout' => 10,
        ],
    ]);

    $raw = file_get_contents($base . '/api.php?action=' . rawurlencode($action), false, $context);
    authSmokeAssert($raw !== false, "HTTP request failed: {$action}");

    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
            $status = (int)$match[1];
            break;
        }
    }

    $json = json_decode($raw, true);
    authSmokeAssert(is_array($json), "Invalid JSON for {$action}: {$raw}");

    return new AuthSmokeResponse($status, $json, $raw, $headers);
}

function authSmokeSetCookie(array $headers): string
{
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) {
            return $header;
        }
    }

    return '';
}

$pdo = authSmokeDb();
$email = 'v2-auth-security@example.test';
$code = '123456';
$hash = password_hash($code, PASSWORD_DEFAULT);
$pdo->prepare("
    INSERT INTO users (id, email, display_name, preferred_language, timezone, status, last_login_at)
    VALUES (21001, ?, 'V2 Auth Security', 'en', 'UTC', 'active', NOW())
")->execute([$email]);
$pdo->prepare("
    INSERT INTO auth_codes (email, code_hash, purpose, expires_at)
    VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL 10 MINUTE))
")->execute([$email, $hash]);

$request = authSmokeRequest('request_code', ['email' => 'new-auth-security@example.test']);
authSmokeAssert($request->status === 200, 'request_code status mismatch');
authSmokeAssert(($request->json['ok'] ?? null) === false, 'production log mail mode should not silently succeed');
authSmokeAssert(($request->json['error'] ?? null) === 'email_send_failed', 'request_code should fail without production mail delivery');
authSmokeAssert(!array_key_exists('dev_code', $request->json), 'production response must not include dev_code');
authSmokeAssert(!str_contains($request->raw, 'saved locally'), 'production response must not mention local code storage');

$logPath = rtrim((string)getenv('FINDESK_V2_AUTH_HARNESS'), '/') . '/storage/logs/auth_codes.log';
authSmokeAssert(!is_file($logPath), 'production-like request_code wrote plaintext auth_codes.log');

$verify = authSmokeRequest('verify_code', ['email' => $email, 'code' => $code]);
authSmokeAssert($verify->status === 200, 'verify_code status mismatch');
authSmokeAssert(($verify->json['ok'] ?? null) === true, "verify_code failed: {$verify->raw}");
$cookie = authSmokeSetCookie($verify->headers);
authSmokeAssert($cookie !== '', 'verify_code did not set session cookie');
authSmokeAssert(stripos($cookie, 'secure') !== false, "session cookie missing Secure: {$cookie}");
authSmokeAssert(stripos($cookie, 'HttpOnly') !== false, "session cookie missing HttpOnly: {$cookie}");
authSmokeAssert(stripos($cookie, 'SameSite=Lax') !== false, "session cookie missing SameSite=Lax: {$cookie}");

echo "FinDesk v2 auth security smoke: OK\n";
