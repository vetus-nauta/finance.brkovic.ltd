<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

final class FinDeskV2Database
{
    public static function pdo(): PDO
    {
        return ql_db();
    }

    public static function transact(callable $callback)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
