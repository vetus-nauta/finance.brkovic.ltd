<?php
require __DIR__ . '/../app/db.php';

try {
    ql_db();
    echo "DB connection: OK\n";
} catch (Throwable $e) {
    echo "DB connection: FAIL\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
