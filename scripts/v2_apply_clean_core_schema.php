<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

$root = dirname(__DIR__);
$sqlPath = $root . '/FinDesk v2.0/sql/001-clean-core-mariadb.sql';
if (!is_file($sqlPath)) {
    throw new RuntimeException("Schema file not found: {$sqlPath}");
}

$sql = (string)file_get_contents($sqlPath);
$statements = preg_split('/;\s*(?:\R|$)/', $sql) ?: [];
$db = ql_db();
$applied = 0;
$skipped = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }
    if (preg_match('/\ACREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+/i', $statement) !== 1) {
        $skipped++;
        continue;
    }

    $db->exec($statement);
    $applied++;
}

echo "FinDesk v2 clean-core schema apply: OK\n";
echo "Create statements applied/idempotent: {$applied}\n";
echo "Other statements skipped: {$skipped}\n";
