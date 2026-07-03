<?php
declare(strict_types=1);

namespace FinDesk\V2;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function config(): array
    {
        $base = require dirname(__DIR__) . '/config.php';
        $localPath = dirname(__DIR__) . '/config.local.php';

        if (is_file($localPath)) {
            $local = require $localPath;
            return array_merge($base, $local);
        }

        return $base;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = self::config();
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4';

        self::$pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}
