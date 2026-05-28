<?php

require_once __DIR__ . '/db.php';

function ql_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ql_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}


function ql_mail_config(): array
{
    $local = __DIR__ . '/config.local.php';

    if (is_file($local)) {
        $cfg = require $local;
        return is_array($cfg['mail'] ?? null) ? $cfg['mail'] : [];
    }

    return [];
}

function ql_smtp_read($fp): string
{
    $data = '';

    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function ql_smtp_expect($fp, array $codes, string $step): void
{
    $reply = ql_smtp_read($fp);
    $code = substr($reply, 0, 3);

    if (!in_array($code, $codes, true)) {
        throw new RuntimeException("SMTP {$step} failed: " . trim($reply));
    }
}

function ql_smtp_command($fp, string $command, array $codes, string $step): void
{
    fwrite($fp, $command . "\r\n");
    ql_smtp_expect($fp, $codes, $step);
}

function ql_smtp_send(string $to, string $subject, string $body): bool
{
    $cfg = ql_mail_config();

    if (($cfg['mode'] ?? '') !== 'smtp') {
        return false;
    }

    $host = (string)($cfg['host'] ?? '');
    $port = (int)($cfg['port'] ?? 465);
    $secure = (string)($cfg['secure'] ?? 'ssl');
    $user = (string)($cfg['username'] ?? '');
    $pass = (string)($cfg['password'] ?? '');
    $fromEmail = (string)($cfg['from_email'] ?? $user);
    $fromName = (string)($cfg['from_name'] ?? 'FinDesk');

    if ($host === '' || $user === '' || $pass === '' || $fromEmail === '') {
        return false;
    }

    $target = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($target, $port, $errno, $errstr, 15);

    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: {$errno} {$errstr}");
    }

    stream_set_timeout($fp, 15);

    ql_smtp_expect($fp, ['220'], 'connect');
    ql_smtp_command($fp, 'EHLO finance.brkovic.ltd', ['250'], 'ehlo');

    ql_smtp_command($fp, 'AUTH LOGIN', ['334'], 'auth login');
    ql_smtp_command($fp, base64_encode($user), ['334'], 'auth user');
    ql_smtp_command($fp, base64_encode($pass), ['235'], 'auth pass');

    ql_smtp_command($fp, 'MAIL FROM:<' . $fromEmail . '>', ['250'], 'mail from');
    ql_smtp_command($fp, 'RCPT TO:<' . $to . '>', ['250', '251'], 'rcpt to');
    ql_smtp_command($fp, 'DATA', ['354'], 'data');

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $safeFromName = str_replace(['"', "\r", "\n"], ['', '', ''], $fromName);

    $headers = [];
    $headers[] = 'From: "' . $safeFromName . '" <' . $fromEmail . '>';
    $headers[] = 'To: <' . $to . '>';
    $headers[] = 'Subject: ' . $encodedSubject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@finance.brkovic.ltd>';

    $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body);
    $message = str_replace("\n.", "\n..", $message);
    $message = str_replace("\n", "\r\n", $message);

    fwrite($fp, $message . "\r\n.\r\n");
    ql_smtp_expect($fp, ['250'], 'message body');

    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    return true;
}

function ql_send_auth_email(string $email, string $code): array
{
    $mailConfig = ql_mail_config();

    if (($mailConfig['mode'] ?? '') === 'log') {
        return ['ok' => true, 'method' => 'log'];
    }

    $subject = 'Your FinDesk sign-in code: ' . $code;
    $message = "Your FinDesk sign-in code is: {$code}\n\n" .
        "Enter this 6-digit code in the FinDesk sign-in form.\n" .
        "The code expires in 10 minutes.\n\n" .
        "If you did not request this code, you can ignore this email.\n";

    try {
        if (ql_smtp_send($email, $subject, $message)) {
            return ['ok' => true, 'method' => 'smtp'];
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'method' => 'smtp', 'error' => $e->getMessage()];
    }

    $headers = [
        'From: FinDesk <no-reply@brkovic.ltd>',
        'Reply-To: no-reply@brkovic.ltd',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $sent = @mail($email, $subject, $message, implode("\r\n", $headers));

    return ['ok' => (bool)$sent, 'method' => 'mail'];
}

function ql_is_local_dev_context(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $addr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $config = function_exists('ql_config') ? ql_config() : [];
    $appUrl = strtolower((string)($config['app_url'] ?? ''));

    return in_array($addr, ['127.0.0.1', '::1'], true)
        || str_contains($host, '127.0.0.1')
        || str_contains($host, 'localhost')
        || str_contains($appUrl, '127.0.0.1')
        || str_contains($appUrl, 'localhost');
}

function ql_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function ql_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function ql_user_agent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function ql_audit_write(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): bool
{
    try {
        $stmt = ql_db()->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ql_client_ip(),
            ql_user_agent()
        ]);

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ql_audit_list(array $input = []): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $limit = (int)($input['limit'] ?? 40);

    if ($limit < 1 || $limit > 100) {
        $limit = 40;
    }

    $where = 'al.user_id = ?';
    $params = [$userId];
    $scope = null;

    if ($groupId > 0) {
        if (!function_exists('ql_group_membership')) {
            return ['ok' => false, 'error' => 'group_module_missing'];
        }

        $scope = ql_group_membership($groupId, $userId);
        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        $permissions = $scope['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }

        $accessLevel = (string)($scope['access_level'] ?? 'base');
        $canViewGroup = ($scope['role'] ?? '') === 'admin'
            || in_array($accessLevel, ['manager', 'advanced'], true)
            || !empty($permissions['can_moderate'])
            || !empty($permissions['can_view_group_reports'])
            || !empty($permissions['can_manage_money']);

        $where = "CAST(JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.group_id')) AS UNSIGNED) = ?";
        $params = [$groupId];

        if (!$canViewGroup) {
            $where .= ' AND al.user_id = ?';
            $params[] = $userId;
        }
    }

    $sql = "
        SELECT
            al.id,
            al.user_id,
            al.action,
            al.entity_type,
            al.entity_id,
            al.details,
            al.created_at,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM audit_log al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE {$where}
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT {$limit}
    ";

    $stmt = ql_db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $details = json_decode((string)($item['details'] ?? ''), true);
        $item['details'] = is_array($details) ? $details : [];
    }

    return [
        'ok' => true,
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'personal',
            'group_id' => $groupId ?: null,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'items' => $items
    ];
}

function ql_cookie_name(): string
{
    $config = ql_config();
    return $config['session_cookie_name'] ?? 'ql_session';
}

function ql_issue_code(string $email): array
{
    $email = ql_normalize_email($email);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email'];
    }

    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);

    $db = ql_db();

    $stmt = $db->prepare("
        INSERT INTO auth_codes (email, code_hash, purpose, expires_at)
        VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ");
    $stmt->execute([$email, $hash]);

    $logLine = date('c') . " | {$email} | {$code}\n";
    file_put_contents(dirname(__DIR__) . '/storage/logs/auth_codes.log', $logLine, FILE_APPEND);

    $send = ql_send_auth_email($email, $code);

    if (!$send['ok']) {
        return [
            'ok' => false,
            'error' => 'email_send_failed',
            'mail_method' => $send['method'] ?? 'unknown',
            'mail_error' => $send['error'] ?? null,
            'dev_message' => 'The sign-in code was saved locally.'
        ];
    }

    $response = [
        'ok' => true,
        'email_sent' => true,
        'mail_method' => $send['method'] ?? 'unknown',
        'dev_message' => 'The sign-in code was sent by email and saved locally.'
    ];

    if (($send['method'] ?? '') === 'log' && ql_is_local_dev_context()) {
        $response['email_sent'] = false;
        $response['dev_code'] = $code;
        $response['dev_message'] = 'Local sign-in code. Mail delivery is logged locally.';
    }

    return $response;
}

function ql_verify_code(string $email, string $code): array
{
    $email = ql_normalize_email($email);
    $code = trim($code);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email'];
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        return ['ok' => false, 'error' => 'invalid_code'];
    }

    $db = ql_db();

    $stmt = $db->prepare("
        SELECT *
        FROM auth_codes
        WHERE email = ?
          AND purpose = 'login'
          AND used_at IS NULL
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'code_not_found_or_expired'];
    }

    if ((int)$row['attempts'] >= 5) {
        return ['ok' => false, 'error' => 'too_many_attempts'];
    }

    if (!password_verify($code, $row['code_hash'])) {
        $upd = $db->prepare("UPDATE auth_codes SET attempts = attempts + 1 WHERE id = ?");
        $upd->execute([$row['id']]);
        return ['ok' => false, 'error' => 'wrong_code'];
    }

    $db->beginTransaction();

    try {
        $updCode = $db->prepare("UPDATE auth_codes SET used_at = NOW() WHERE id = ?");
        $updCode->execute([$row['id']]);

        $userStmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch();

        if (!$user) {
            $ins = $db->prepare("
                INSERT INTO users (email, display_name, preferred_language, timezone, last_login_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $display = explode('@', $email)[0];
            $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
            $ins->execute([$email, $display, $lang, date_default_timezone_get()]);
            $userId = (int)$db->lastInsertId();
        } else {
            $userId = (int)$user['id'];
            $updUser = $db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $updUser->execute([$userId]);
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $sess = $db->prepare("
            INSERT INTO sessions (user_id, session_token_hash, device_label, ip_address, user_agent, expires_at, last_seen_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
        ");
        $sess->execute([
            $userId,
            $tokenHash,
            'web',
            ql_client_ip(),
            ql_user_agent()
        ]);

        $audit = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, ip_address, user_agent)
            VALUES (?, 'login', 'user', ?, ?, ?)
        ");
        $audit->execute([$userId, $userId, ql_client_ip(), ql_user_agent()]);

        $db->commit();

        setcookie(ql_cookie_name(), $token, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return ['ok' => true, 'user' => ql_current_user_by_id($userId)];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()];
    }
}

function ql_current_user_by_id(int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT id, email, display_name, preferred_language, timezone, created_at, last_login_at
        FROM users
        WHERE id = ? AND status = 'active' AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function ql_current_user(): ?array
{
    $token = $_COOKIE[ql_cookie_name()] ?? '';

    if (!$token) {
        return null;
    }

    $tokenHash = hash('sha256', $token);

    $stmt = ql_db()->prepare("
        SELECT s.*, u.status
        FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.session_token_hash = ?
          AND s.revoked_at IS NULL
          AND s.expires_at > NOW()
          AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    $upd = ql_db()->prepare("UPDATE sessions SET last_seen_at = NOW() WHERE id = ?");
    $upd->execute([$session['id']]);

    return ql_current_user_by_id((int)$session['user_id']);
}

function ql_logout(): void
{
    $token = $_COOKIE[ql_cookie_name()] ?? '';

    if ($token) {
        $tokenHash = hash('sha256', $token);
        $stmt = ql_db()->prepare("UPDATE sessions SET revoked_at = NOW() WHERE session_token_hash = ?");
        $stmt->execute([$tokenHash]);
    }

    setcookie(ql_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
