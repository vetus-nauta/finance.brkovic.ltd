<?php

return [
    'app_url' => 'https://finance.brkovic.ltd',
    'db_host' => 'localhost',
    'db_name' => 'finance_database',
    'db_user' => 'finance_user',
    'db_pass' => 'change-me',
    'session_cookie_name' => 'ql_session',

    'mail' => [
        // Use "log" for localhost/dev, "smtp" for production.
        'mode' => 'smtp',
        'host' => 'mail.example.com',
        'port' => 465,
        'secure' => 'ssl',
        'username' => 'no-reply@example.com',
        'password' => 'change-me',
        'from_email' => 'no-reply@example.com',
        'from_name' => 'Quick Ledger',
    ],
];
