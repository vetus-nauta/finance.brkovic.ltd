<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

const CLAUDIA_Z_WORKSPACE_ID = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';

function user_classification_backup(array $payload): string
{
    $dir = __DIR__ . '/../storage/production-audits/claudia-z-targeted-user-classification-' . date('Ymd-His');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create backup dir: {$dir}");
    }
    $path = $dir . '/before.json';
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    return $path;
}

function append_rule(?string $json, array $rule): string
{
    $rules = json_decode((string)($json ?? '[]'), true);
    if (!is_array($rules)) {
        $rules = [];
    }
    $rules[] = $rule + ['applied_at' => date(DATE_ATOM)];
    return json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function targeted_rules(?string $json, array $decision): string
{
    if (isset($decision['matched_rules']) && is_array($decision['matched_rules'])) {
        $rules = [];
        foreach ($decision['matched_rules'] as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $rules[] = $rule + ['applied_at' => date(DATE_ATOM)];
        }
        return json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return append_rule($json, $decision['rule']);
}

$apply = in_array('--apply', $argv, true);
$db = ql_db();

$categoryIds = [];
$stmt = $db->query('SELECT id, code FROM v2_categories WHERE workspace_id IS NULL AND is_active = 1');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categoryIds[(string)$row['code']] = (string)$row['id'];
}

$decisions = [
    [
        'id' => '0ed36b74-1a22-4d5c-bc81-c9028372b21b',
        'category_code' => null,
        'status' => null,
        'rule' => [
            'source' => 'user_confirmed_targeted_classification',
            'semantic_marker' => 'debt_or_return',
            'marker' => 'debt_or_return',
            'reason' => 'credit_share_user_confirmed',
            'note' => 'Part of the already identified credit repayment chain.',
        ],
    ],
    [
        'id' => '8b3bebda-c0e9-4666-b3e5-672618d77281',
        'category_code' => 'guest_trip_support',
        'status' => null,
        'rule' => [
            'source' => 'user_confirmed_targeted_classification',
            'category_code' => 'guest_trip_support',
            'reason' => 'guest_airplane_seats_user_confirmed',
        ],
    ],
    [
        'id' => 'b758e7fd-3f7b-40c5-8c30-96868a1f89b5',
        'category_code' => 'cash_topup_from_card',
        'status' => null,
        'rule' => [
            'source' => 'user_confirmed_targeted_classification',
            'category_code' => 'cash_topup_from_card',
            'semantic_marker' => 'money_movement',
            'marker' => 'money_movement',
            'reason' => 'personal_card_spend_reimbursed_cash_user_confirmed',
        ],
    ],
    [
        'id' => '698c83a5-30b8-45e0-9d48-2201e4500df9',
        'category_code' => 'cash_topup_from_card',
        'status' => null,
        'notes' => 'Возмещение наличными расходов, ранее оплаченных со служебной карты. Это money movement, не поступление от судовладельца.',
        'rule' => [
            'source' => 'user_confirmed_targeted_classification',
            'category_code' => 'cash_topup_from_card',
            'semantic_marker' => 'money_movement',
            'marker' => 'money_movement',
            'reason' => 'personal_card_spend_reimbursed_cash_side_user_confirmed',
        ],
    ],
    [
        'id' => '0c0a603e-26b7-44f8-8a0d-7b562e97398a',
        'category_code' => 'cash_topup_from_card',
        'status' => null,
        'notes' => 'Пополнение наличной кассы со служебной карты. Это перенос денег карта -> кеш, не поступление от судовладельца.',
        'rule' => [
            'source' => 'user_confirmed_targeted_classification',
            'category_code' => 'cash_topup_from_card',
            'semantic_marker' => 'money_movement',
            'marker' => 'money_movement',
            'reason' => 'cash_from_card_cash_side_user_confirmed',
        ],
    ],
    [
        'id' => '6a98d728-fcd8-4622-9167-1d78c294a782',
        'category_code' => 'provisions',
        'status' => null,
        'rule' => [
            'source' => 'user_confirmed_targeted_classification',
            'category_code' => 'provisions',
            'reason' => 'provisions_user_confirmed',
        ],
    ],
    [
        'id' => 'defcb9b0-3ab1-4819-949b-6259e46315c9',
        'category_code' => null,
        'clear_category' => true,
        'status' => null,
        'notes' => 'Возврат остатка подотчетных денег Евгении из предыдущего отчета. Это физический возврат в кассу, не доход от судовладельца и не операционная категория.',
        'rule' => [
            'source' => 'user_confirmed_targeted_classification',
            'semantic_marker' => 'money_movement',
            'marker' => 'money_movement',
            'source_actor' => 'Евгения',
            'reason' => 'evgenia_accountable_cash_remainder_return_user_confirmed',
            'note' => 'Return of accountable cash remainder from previous report, not owner funding.',
        ],
        'matched_rules' => [
            [
                'source' => 'user_confirmed_targeted_classification',
                'semantic_marker' => 'money_movement',
                'marker' => 'money_movement',
                'source_actor' => 'Евгения',
                'reason' => 'evgenia_accountable_cash_remainder_return_user_confirmed',
                'note' => 'Return of accountable cash remainder from previous report, not owner funding.',
            ],
        ],
    ],
];

$select = $db->prepare("
    SELECT e.*, c.code AS category_code
    FROM v2_entries e
    LEFT JOIN v2_categories c ON c.id = e.category_id
    WHERE e.workspace_id = ? AND e.id = ?
    LIMIT 1
");

$changes = [];
foreach ($decisions as $decision) {
    $select->execute([CLAUDIA_Z_WORKSPACE_ID, $decision['id']]);
    $row = $select->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException("Entry not found: {$decision['id']}");
    }
    $targetCategoryId = null;
    if ($decision['category_code'] !== null) {
        if (!isset($categoryIds[$decision['category_code']])) {
            throw new RuntimeException("Missing category: {$decision['category_code']}");
        }
        $targetCategoryId = $categoryIds[$decision['category_code']];
    } elseif (($decision['clear_category'] ?? false) !== true) {
        $targetCategoryId = $row['category_id'];
    }
    $changes[] = [
        'id' => $decision['id'],
        'date' => $row['date'],
        'raw_text' => $row['raw_text'],
        'amount' => $row['amount'],
        'direction' => $row['direction'],
        'before_category_id' => $row['category_id'],
        'before_category_code' => $row['category_code'],
        'after_category_id' => $targetCategoryId,
        'after_category_code' => (($decision['clear_category'] ?? false) === true) ? null : ($decision['category_code'] ?? $row['category_code']),
        'before_status' => $row['status'],
        'after_status' => $decision['status'] ?? $row['status'],
        'before_notes' => $row['notes'],
        'after_notes' => $decision['notes'] ?? $row['notes'],
        'before_matched_rules_json' => $row['matched_rules_json'],
        'after_matched_rules_json' => targeted_rules($row['matched_rules_json'] ?? '[]', $decision),
        'decision' => $decision,
    ];
}

$backupPath = null;
if ($apply) {
    $backupPath = user_classification_backup([
        'created_at' => date(DATE_ATOM),
        'workspace_id' => CLAUDIA_Z_WORKSPACE_ID,
        'changes' => $changes,
    ]);
    $update = $db->prepare('UPDATE v2_entries SET category_id = ?, status = ?, notes = ?, matched_rules_json = ?, updated_at = NOW() WHERE id = ?');
    $db->beginTransaction();
    try {
        foreach ($changes as $change) {
            $update->execute([
                $change['after_category_id'],
                $change['after_status'],
                $change['after_notes'],
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
    'workspace_id' => CLAUDIA_Z_WORKSPACE_ID,
    'changes' => array_map(static fn (array $change): array => [
        'date' => $change['date'],
        'raw_text' => $change['raw_text'],
        'amount' => $change['amount'],
        'before_category_code' => $change['before_category_code'],
        'after_category_code' => $change['after_category_code'],
        'before_status' => $change['before_status'],
        'after_status' => $change['after_status'],
        'reason' => $change['decision']['rule']['reason'],
    ], $changes),
    'backup' => $backupPath,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
