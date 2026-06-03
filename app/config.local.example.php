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
        'from_name' => 'FinDesk',
    ],

    'openai' => [
        // Keep disabled until the production server has a real key.
        // Preferred: set OPENAI_API_KEY in the server environment and leave api_key empty.
        'enabled' => false,
        'api_key' => '',
        'api_key_file' => '',
        'model' => 'gpt-5.4-mini',
        'endpoint' => 'https://api.openai.com/v1',
        'timeout_seconds' => 90,
        'max_output_tokens' => 1800,
        'web_search_enabled' => true,
        'web_search_tool' => 'web_search',
    ],

    'yacht_price_refresh' => [
        'food_interval_days' => 90,
        'fuel_interval_days' => 30,
    ],
];
