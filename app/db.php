<?php

function ql_config(): array
{
    $base = require __DIR__ . '/config.php';
    $localPath = __DIR__ . '/config.local.php';

    if (is_file($localPath)) {
        $local = require $localPath;
        return array_merge($base, $local);
    }

    return $base;
}

function ql_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = ql_config();

    $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';

    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
