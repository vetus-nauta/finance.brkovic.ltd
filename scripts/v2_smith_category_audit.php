<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';

const SMITH_AUDIT_CLAUDIA_Z_WORKSPACE_ID = '0d4faca6-3138-4ffe-9805-a6a29895b7ed';

function smith_norm(string $text): string
{
    $text = mb_strtolower($text);
    $text = str_replace('ё', 'е', $text);
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function smith_suggest(string $rawText, string $direction, string $entryType, ?string $categoryCode): array
{
    $text = smith_norm($rawText);
    $signals = [];

    $add = static function (string $category, string $reason, string $strength = 'safe') use (&$signals): void {
        $signals[] = [
            'category_code' => $category,
            'reason' => $reason,
            'strength' => $strength,
        ];
    };

    if ($direction !== 'out' || !in_array($entryType, ['cash_expense', 'card_expense'], true)) {
        if ($direction === 'in' && preg_match('/остаток .*предыдущ.*отчет|возврат.*под ?отчет|недорасход/u', $text) === 1) {
            return [
                'suggested_category_code' => null,
                'confidence' => 'safe',
                'reason' => 'accountable cash return is money movement, not owner income',
                'signals' => [['category_code' => null, 'reason' => 'accountable_cash_return', 'strength' => 'safe']],
            ];
        }
        if ($direction === 'in' && preg_match('/\b(?:мой\s+)?долг\b|кредит|за[ий]м|взял себе|в долг/u', $text) === 1) {
            return [
                'suggested_category_code' => $categoryCode,
                'confidence' => 'discuss',
                'reason' => 'incoming debt/loan wording needs owner decision before becoming income or lower accounting',
                'signals' => [['category_code' => $categoryCode, 'reason' => 'incoming_debt_or_loan_wording', 'strength' => 'discuss']],
            ];
        }
        if ($direction === 'in' && preg_match('/кеш с карты|с карты .*кеш|снял кеш|положил кеш/u', $text) === 1 && $categoryCode !== 'cash_topup_from_card') {
            return [
                'suggested_category_code' => 'cash_topup_from_card',
                'confidence' => 'discuss',
                'reason' => 'possible card-to-cash movement with actor context needs owner confirmation',
                'signals' => [['category_code' => 'cash_topup_from_card', 'reason' => 'possible_card_to_cash_transfer', 'strength' => 'discuss']],
            ];
        }
        return ['suggested_category_code' => $categoryCode, 'confidence' => 'skip', 'reason' => 'non expense row', 'signals' => []];
    }

    if ($categoryCode === null && preg_match('/\b(?:последн[а-я]*\s+)?кредит\b|за[ий]м|взял себе|в долг/u', $text) === 1) {
        return [
            'suggested_category_code' => null,
            'confidence' => 'discuss',
            'reason' => 'uncategorized loan/credit expense must be reviewed as lower accounting or operational repayment',
            'signals' => [['category_code' => null, 'reason' => 'uncategorized_loan_or_credit_expense', 'strength' => 'discuss']],
        ];
    }

    if (preg_match('/оплата отел|отел[ьяеи]?|гостиниц/u', $text) === 1) {
        $add('guest_trip_support', 'hotel / guest accommodation belongs to guest trip support');
    }
    if (preg_match('/маски ласты|подводн[а-я]* маск|набор для ныряния|водные игрушки|самокат|скутер|параплан|музыкант/u', $text) === 1) {
        $add('guest_trip_support', 'guest activity / equipment support');
    }
    if (preg_match('/одежд[аы]? экипаж|форма экипаж|спец.?одеж/u', $text) === 1) {
        $add('current_boat_expenses', 'crew uniform / work clothing is current boat expense by owner decision');
    }
    $hasTechObject = preg_match('/запчаст|фильтр|кабел|антенн|адаптер|насос|мотор|детал|инструмент/u', $text) === 1;
    if (preg_match('/доставк/u', $text) === 1 && !$hasTechObject && preg_match('/передал|отдал|дал|выдал/u', $text) !== 1) {
        $add('transport_expenses', 'delivery without cash handoff wording');
    }
    if (preg_match('/(?:передал|отдал|дал|выдал)\s+(?:лв|леонид владимирович|арику?|саше?|гост)/u', $text) === 1) {
        $add('guest_cash_issued', 'explicit cash handoff to guest / guest side');
    }
    if (preg_match('/контрольк[а-я]* конд|кондиц/u', $text) === 1 && preg_match('/ремонт|сервис|диагност/u', $text) !== 1) {
        $add('tech_parts', 'air-conditioner controller / part');
    }
    if (preg_match('/банк|банковск|комисс/u', $text) === 1 && preg_match('/тамож|такс[аы]|документ/u', $text) !== 1) {
        $add('current_boat_expenses', 'bank fees / regular current boat expense');
    }

    if ($signals === []) {
        return ['suggested_category_code' => $categoryCode, 'confidence' => 'same_or_unknown', 'reason' => 'no stronger signal', 'signals' => []];
    }

    $byCategory = [];
    foreach ($signals as $signal) {
        $byCategory[$signal['category_code'] ?? '__none__'][] = $signal;
    }
    if (count($byCategory) > 1) {
        return [
            'suggested_category_code' => $categoryCode,
            'confidence' => 'discuss',
            'reason' => 'mixed strong signals',
            'signals' => $signals,
        ];
    }

    $suggested = (string)array_key_first($byCategory);
    if ($suggested === '__none__') {
        $suggested = null;
    }

    return [
        'suggested_category_code' => $suggested,
        'confidence' => $suggested === $categoryCode ? 'same' : 'safe',
        'reason' => $byCategory[array_key_first($byCategory)][0]['reason'],
        'signals' => $signals,
    ];
}

$db = ql_db();
$stmt = $db->prepare("
    SELECT
        e.id,
        e.created_seq,
        e.date,
        e.raw_text,
        e.amount,
        e.direction,
        e.entry_type,
        e.status,
        COALESCE(c.code, '') AS category_code,
        e.matched_rules_json,
        e.notes
    FROM v2_entries e
    LEFT JOIN v2_categories c ON c.id = e.category_id
    WHERE e.workspace_id = ?
      AND e.archived_at IS NULL
    ORDER BY e.date, e.created_seq
");
$stmt->execute([SMITH_AUDIT_CLAUDIA_Z_WORKSPACE_ID]);

$rows = [];
$safeMoves = [];
$discuss = [];
$sameSignals = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $current = $row['category_code'] === '' ? null : (string)$row['category_code'];
    $suggestion = smith_suggest((string)$row['raw_text'], (string)$row['direction'], (string)$row['entry_type'], $current);
    $item = [
        'id' => (string)$row['id'],
        'created_seq' => (int)$row['created_seq'],
        'date' => (string)$row['date'],
        'raw_text' => (string)$row['raw_text'],
        'amount' => $row['amount'] === null ? null : (float)$row['amount'],
        'direction' => (string)$row['direction'],
        'entry_type' => (string)$row['entry_type'],
        'status' => (string)$row['status'],
        'current_category_code' => $current,
        'suggested_category_code' => $suggestion['suggested_category_code'],
        'confidence' => $suggestion['confidence'],
        'reason' => $suggestion['reason'],
        'signals' => $suggestion['signals'],
    ];
    $rows[] = $item;
    if ($item['confidence'] === 'safe' && $item['current_category_code'] !== $item['suggested_category_code']) {
        $safeMoves[] = $item;
    } elseif ($item['confidence'] === 'discuss') {
        $discuss[] = $item;
    } elseif ($item['confidence'] === 'same') {
        $sameSignals[] = $item;
    }
}

$artifactDir = __DIR__ . '/../storage/production-audits/smith-category-audit-' . date('Ymd-His');
if (!is_dir($artifactDir) && !mkdir($artifactDir, 0775, true) && !is_dir($artifactDir)) {
    throw new RuntimeException("Unable to create artifact dir: {$artifactDir}");
}
$artifact = $artifactDir . '/report.json';
$payload = [
    'created_at' => date(DATE_ATOM),
    'workspace_id' => SMITH_AUDIT_CLAUDIA_Z_WORKSPACE_ID,
    'active_rows' => count($rows),
    'safe_move_count' => count($safeMoves),
    'discuss_count' => count($discuss),
    'same_signal_count' => count($sameSignals),
    'safe_moves' => $safeMoves,
    'discuss' => $discuss,
    'same_signals' => $sameSignals,
];
file_put_contents($artifact, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo json_encode([
    'ok' => true,
    'artifact' => $artifact,
    'active_rows' => count($rows),
    'safe_move_count' => count($safeMoves),
    'discuss_count' => count($discuss),
    'safe_moves' => array_map(static fn (array $row): array => [
        'created_seq' => $row['created_seq'],
        'date' => $row['date'],
        'raw_text' => $row['raw_text'],
        'amount' => $row['amount'],
        'from' => $row['current_category_code'],
        'to' => $row['suggested_category_code'],
        'reason' => $row['reason'],
    ], $safeMoves),
    'discuss' => array_map(static fn (array $row): array => [
        'created_seq' => $row['created_seq'],
        'date' => $row['date'],
        'raw_text' => $row['raw_text'],
        'amount' => $row['amount'],
        'current' => $row['current_category_code'],
        'reason' => $row['reason'],
        'signals' => $row['signals'],
    ], $discuss),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
