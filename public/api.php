<?php

require_once __DIR__ . '/../app/auth.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'request_code') {
        $input = ql_input();
        ql_json(ql_issue_code((string)($input['email'] ?? '')));
    }

    if ($action === 'verify_code') {
        $input = ql_input();
        ql_json(ql_verify_code((string)($input['email'] ?? ''), (string)($input['code'] ?? '')));
    }

    if ($action === 'current_user') {
        ql_json(['ok' => true, 'user' => ql_current_user()]);
    }

    if ($action === 'logout') {
        ql_logout();
        ql_json(['ok' => true]);
    }

    ql_json(['ok' => false, 'error' => 'unknown_auth_action'], 404);
} catch (Throwable $e) {
    ql_json(['ok' => false, 'error' => 'auth_bridge_error'], 500);
}
