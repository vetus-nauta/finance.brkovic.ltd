<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

const CLAUDIA_Z_WORKSPACE_ID = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';

function norm_text(string $text): string
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
}

function category_rule_for(string $text, ?string $categoryCode, string $status): ?array
{
    $t = norm_text($text);

    if (($categoryCode === null || $categoryCode === '' || $categoryCode === 'other') && preg_match('/адвокат|нотариус|переводчик|тамож/u', $t)) {
        return ['admin_legal', 'legal_admin_obvious'];
    }
    if (($categoryCode === null || $categoryCode === '' || $categoryCode === 'other') && preg_match('/цогимар|цоги\\s*мар|cogimar/u', $t)) {
        return ['provisions', 'fish_store_user_confirmed'];
    }
    if (($categoryCode === null || $categoryCode === '' || $categoryCode === 'other') && preg_match('/рыб|морепродукт/u', $t)) {
        return ['provisions', 'food_store_context_obvious'];
    }
    if (($categoryCode === null || $categoryCode === '' || $categoryCode === 'other') && preg_match('/айфон/u', $t)) {
        return ['guest_trip_support', 'guest_purchase_user_confirmed'];
    }
    if (($categoryCode === null || $categoryCode === '' || $categoryCode === 'other') && preg_match('/чай\\s+александр/u', $t)) {
        return ['current_boat_expenses', 'current_expense_user_confirmed'];
    }

    if ($categoryCode !== null && $categoryCode !== '') {
        return null;
    }

    if (preg_match('/тузик|отпорный крюк|крыло сап/u', $t)) {
        return ['tender', 'tender_obvious'];
    }
    if (preg_match('/пошив подушек|перешив подушек/u', $t)) {
        return ['interior', 'interior_household_obvious'];
    }

    $rules = [
        ['crew', '/\bзп\b|зарплат|моя зп|экипаж|женя зп|вова зп|сергей повар|сотруднику|данил, помощь/u', 'crew_payroll_obvious'],
        ['dry_dock', '/подьем лодки|подъ[её]м лодки|спуск подьем|спуск подъ[её]м/u', 'dock_lift_obvious'],
        ['fuel', '/\bбензин\b|топливо|заправ/u', 'fuel_obvious'],
        ['tech_parts', '/инструмент|сверл|расходник|креплен|магнит|клипс|радиостанц|icom|огни|шнур|тик брайтнер/u', 'technical_parts_obvious'],
        ['media_comms', '/картина тв|подписка навигац|сим|instagram|инстаграм|facebook|фейсбук|реклама|телефон|тв приставк|динамик/u', 'media_comms_obvious'],
        ['interior', '/ножи|посуда|душ принадлеж|свечи|брызгалка|холодильник/u', 'interior_household_obvious'],
        ['transport_expenses', '/экспресс почт|доставк/u', 'transport_delivery_obvious'],
        ['current_boat_expenses', '/перевод денег|швейцарск|докеры|расходники кухня/u', 'current_boat_expenses_obvious'],
        ['cash_topup_from_card', '/снял кеш|снял к[эе]ш/u', 'card_cash_topup_obvious'],
    ];

    foreach ($rules as [$code, $pattern, $reason]) {
        if (preg_match($pattern, $t)) {
            return [$code, $reason];
        }
    }

    if (preg_match('/кредит|долг|займ/u', $t)) {
        return null;
    }
    if (preg_match('/айфон|свои расходы|вернул кеш|итого|temu|тему|чай александр/u', $t)) {
        return null;
    }

    return null;
}

function write_backup(array $payload): string
{
    $dir = __DIR__ . '/../storage/production-audits/claudia-z-soft-classification-' . date('Ymd-His');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create backup dir: {$dir}");
    }
    $path = $dir . '/before.json';
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    return $path;
}

$apply = in_array('--apply', $argv, true);
$db = ql_db();
$categories = [];
$stmt = $db->query('SELECT id, code FROM v2_categories WHERE workspace_id IS NULL AND is_active = 1');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $categories[$row['code']] = $row['id'];
}

$sql = <<<SQL
SELECT
    e.id,
    e.date,
    e.raw_text,
    e.amount,
    e.direction,
    e.status,
    e.entry_type,
    e.category_id,
    c.code AS category_code,
    f.type AS flow_type,
    e.balance_after,
    e.matched_rules_json
FROM v2_entries e
INNER JOIN v2_flows f ON f.id = e.flow_id
LEFT JOIN v2_categories c ON c.id = e.category_id
WHERE e.workspace_id = ?
  AND e.archived_at IS NULL
  AND (
      e.category_id IS NULL
      OR e.status = 'other_review'
      OR c.code = 'other'
  )
ORDER BY e.date, e.created_seq
SQL;

$stmt = $db->prepare($sql);
$stmt->execute([CLAUDIA_Z_WORKSPACE_ID]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$changes = [];
$skipped = [];
foreach ($rows as $row) {
    $decision = category_rule_for((string)$row['raw_text'], $row['category_code'] ?: null, (string)$row['status']);
    if ($decision === null) {
        $skipped[] = $row;
        continue;
    }
    [$targetCode, $reason] = $decision;
    if (!isset($categories[$targetCode])) {
        throw new RuntimeException("Missing category: {$targetCode}");
    }
    if ($row['category_code'] === $targetCode && $row['status'] !== 'other_review') {
        continue;
    }
    $matched = json_decode((string)($row['matched_rules_json'] ?? '[]'), true);
    if (!is_array($matched)) {
        $matched = [];
    }
    $matched[] = [
        'source' => 'claudia_z_soft_classification_cleanup',
        'category_code' => $targetCode,
        'reason' => $reason,
        'applied_at' => date(DATE_ATOM),
    ];
    $changes[] = [
        'id' => $row['id'],
        'date' => $row['date'],
        'raw_text' => $row['raw_text'],
        'amount' => $row['amount'],
        'direction' => $row['direction'],
        'flow_type' => $row['flow_type'],
        'balance_after' => $row['balance_after'],
        'before_category_code' => $row['category_code'],
        'after_category_code' => $targetCode,
        'before_status' => $row['status'],
        'after_status' => $row['status'] === 'other_review' ? 'recognized' : $row['status'],
        'reason' => $reason,
        'matched_rules_json' => json_encode($matched, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

$backupPath = null;
if ($apply && $changes) {
    $backupPath = write_backup([
        'created_at' => date(DATE_ATOM),
        'workspace_id' => CLAUDIA_Z_WORKSPACE_ID,
        'changes' => $changes,
        'skipped' => array_map(static fn (array $row): array => [
            'id' => $row['id'],
            'date' => $row['date'],
            'raw_text' => $row['raw_text'],
            'amount' => $row['amount'],
            'direction' => $row['direction'],
            'flow_type' => $row['flow_type'],
            'balance_after' => $row['balance_after'],
            'category_code' => $row['category_code'],
            'status' => $row['status'],
        ], $skipped),
    ]);
    $update = $db->prepare('UPDATE v2_entries SET category_id = ?, status = ?, matched_rules_json = ?, updated_at = NOW() WHERE id = ?');
    $db->beginTransaction();
    try {
        foreach ($changes as $change) {
            $update->execute([
                $categories[$change['after_category_code']],
                $change['after_status'],
                $change['matched_rules_json'],
                $change['id'],
            ]);
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

$summary = [
    'mode' => $apply ? 'applied' : 'dry_run',
    'workspace_id' => CLAUDIA_Z_WORKSPACE_ID,
    'candidate_rows' => count($rows),
    'changes' => count($changes),
    'skipped' => count($skipped),
    'backup' => $backupPath,
    'by_category' => [],
    'changed_rows' => $changes,
    'skipped_rows' => array_map(static fn (array $row): array => [
        'date' => $row['date'],
        'raw_text' => $row['raw_text'],
        'amount' => $row['amount'],
        'direction' => $row['direction'],
        'flow_type' => $row['flow_type'],
        'category_code' => $row['category_code'],
        'status' => $row['status'],
    ], $skipped),
];
foreach ($changes as $change) {
    $code = $change['after_category_code'];
    $summary['by_category'][$code] = ($summary['by_category'][$code] ?? 0) + 1;
}
ksort($summary['by_category']);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
