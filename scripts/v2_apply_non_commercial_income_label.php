<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

$db = ql_db();
$workspaceId = (string)(getenv('FINDESK_V2_CLAUDIA_Z_WORKSPACE_ID') ?: '0d4faca6-3138-4ffe-9805-a6a29895b7ed');

$categoryId = ensureNonCommercialIncomeCategory($db);

$stmt = $db->prepare("
    UPDATE v2_entries e
    INNER JOIN v2_flows f ON f.id = e.flow_id
    SET e.category_id = ?
    WHERE e.workspace_id = ?
      AND e.archived_at IS NULL
      AND e.category_id IS NULL
      AND e.direction = 'in'
      AND e.entry_type = 'cash_income'
      AND f.type = 'cash'
      AND e.status IN ('recognized', 'other_review', 'imported', 'accepted', 'corrected')
");
$stmt->execute([$categoryId, $workspaceId]);

echo "FinDesk v2 non-commercial income label apply: OK\n";
echo "Workspace: {$workspaceId}\n";
echo "Category: non_commercial_income ({$categoryId})\n";
echo "Entries labeled: {$stmt->rowCount()}\n";

function ensureNonCommercialIncomeCategory(PDO $db): string
{
    $stmt = $db->prepare("
        SELECT id
        FROM v2_categories
        WHERE workspace_id IS NULL AND code = 'non_commercial_income'
        LIMIT 1
    ");
    $stmt->execute();
    $id = $stmt->fetchColumn();
    if ($id) {
        return (string)$id;
    }

    $id = uuidForMysql();
    $db->prepare("
        INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
        VALUES (?, NULL, 'non_commercial_income', ?, 'income', 16, 1, 1)
    ")->execute([
        $id,
        '{"ru":"Некоммерческие поступления","en":"Non-commercial income"}',
    ]);

    return $id;
}

function uuidForMysql(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
