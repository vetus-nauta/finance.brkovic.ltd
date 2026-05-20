<?php

require __DIR__ . '/../app/db.php';

$file = $argv[1] ?? '';

if (!$file || !is_file($file)) {
    echo "SQL file missing\n";
    exit(1);
}

try {
    $sql = file_get_contents($file);
    $db = ql_db();
    $db->exec($sql);

    $tables = $db->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    echo "SQL import: OK\n";
    echo "Tables:\n";

    foreach ($tables as $table) {
        echo "- {$table}\n";
    }
} catch (Throwable $e) {
    echo "SQL import: FAIL\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
