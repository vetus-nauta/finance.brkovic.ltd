<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

const SMITH_SAFE_WORKSPACE_ID = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';

function smith_safe_backup(array $payload): string
{
    $dir = __DIR__ . '/../storage/production-audits/smith-safe-category-moves-' . date('Ymd-His');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create backup dir: {$dir}");
    }
    $path = $dir . '/before.json';
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    return $path;
}

function smith_append_rule(?string $json, array $rule): string
{
    $rules = json_decode((string)($json ?? '[]'), true);
    if (!is_array($rules)) {
        $rules = [];
    }
    $rules[] = $rule + ['applied_at' => date(DATE_ATOM)];
    return json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$apply = in_array('--apply', $argv, true);
$db = ql_db();

$categories = [];
foreach ($db->query('SELECT id, code FROM v2_categories WHERE workspace_id IS NULL AND is_active = 1') as $row) {
    $categories[(string)$row['code']] = (string)$row['id'];
}

$decisions = [
    [16398, 'provisions', 'crew_food_is_provisions'],
    [16407, 'provisions', 'crew_food_is_provisions'],
    [16416, 'provisions', 'crew_food_is_provisions'],
    [16460, 'provisions', 'crew_food_is_provisions'],
    [16473, 'guest_trip_support', 'inflatable_toys_guest_trip_support'],
    [18485, 'transport_expenses', 'delivery_to_lv_without_cash_handoff'],
    [18511, 'guest_trip_support', 'masks_fins_guest_trip_support'],
    [18542, 'current_boat_expenses', 'crew_clothing_current_boat_expense'],
    [18561, 'guest_trip_support', 'hotel_guest_trip_support'],
    [18287, 'tech_parts', 'delivery_of_parts_object_priority'],
    [18293, 'tech_parts', 'delivery_of_parts_object_priority'],
];

$select = $db->prepare("
    SELECT e.*, c.code AS category_code
    FROM v2_entries e
    LEFT JOIN v2_categories c ON c.id = e.category_id
    WHERE e.workspace_id = ? AND e.created_seq = ?
    LIMIT 1
");

$changes = [];
foreach ($decisions as [$createdSeq, $targetCode, $reason]) {
    if (!isset($categories[$targetCode])) {
        throw new RuntimeException("Missing category: {$targetCode}");
    }
    $select->execute([SMITH_SAFE_WORKSPACE_ID, $createdSeq]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException("Entry not found by created_seq: {$createdSeq}");
    }
    if ((string)($row['category_code'] ?? '') === $targetCode) {
        continue;
    }
    $changes[] = [
        'id' => (string)$row['id'],
        'created_seq' => (int)$row['created_seq'],
        'date' => (string)$row['date'],
        'raw_text' => (string)$row['raw_text'],
        'amount' => $row['amount'] === null ? null : (float)$row['amount'],
        'direction' => (string)$row['direction'],
        'before_category_code' => $row['category_code'] === null ? null : (string)$row['category_code'],
        'after_category_code' => $targetCode,
        'before_category_id' => $row['category_id'],
        'after_category_id' => $categories[$targetCode],
        'before_status' => (string)$row['status'],
        'after_status' => (string)$row['status'] === 'other_review' ? 'recognized' : (string)$row['status'],
        'before_matched_rules_json' => $row['matched_rules_json'],
        'after_matched_rules_json' => smith_append_rule($row['matched_rules_json'] ?? '[]', [
            'source' => 'smith_safe_category_training',
            'category_code' => $targetCode,
            'reason' => $reason,
        ]),
        'reason' => $reason,
    ];
}

$backup = null;
if ($apply && $changes !== []) {
    $backup = smith_safe_backup([
        'created_at' => date(DATE_ATOM),
        'workspace_id' => SMITH_SAFE_WORKSPACE_ID,
        'changes' => $changes,
    ]);
    $update = $db->prepare('UPDATE v2_entries SET category_id = ?, status = ?, matched_rules_json = ?, updated_at = NOW() WHERE id = ?');
    $db->beginTransaction();
    try {
        foreach ($changes as $change) {
            $update->execute([
                $change['after_category_id'],
                $change['after_status'],
                $change['after_matched_rules_json'],
                $change['id'],
            ]);
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

echo json_encode([
    'mode' => $apply ? 'applied' : 'dry_run',
    'workspace_id' => SMITH_SAFE_WORKSPACE_ID,
    'changes' => count($changes),
    'backup' => $backup,
    'rows' => array_map(static fn (array $change): array => [
        'created_seq' => $change['created_seq'],
        'date' => $change['date'],
        'raw_text' => $change['raw_text'],
        'amount' => $change['amount'],
        'from' => $change['before_category_code'],
        'to' => $change['after_category_code'],
        'reason' => $change['reason'],
    ], $changes),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
