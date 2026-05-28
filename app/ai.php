<?php

require_once __DIR__ . '/ledger.php';
require_once __DIR__ . '/advances.php';

function ql_ai_period_input(array $input): array
{
    $period = (string)($input['period'] ?? 'month');
    if (!in_array($period, ['today', 'month', 'custom'], true)) {
        $period = 'month';
    }

    $payload = ['period' => $period];
    if ($period === 'custom') {
        $payload['from'] = (string)($input['from'] ?? '');
        $payload['to'] = (string)($input['to'] ?? '');
    }

    return $payload;
}

function ql_ai_money($value): string
{
    return 'EUR ' . number_format((float)$value, 2, '.', ' ');
}

function ql_ai_analysis_run(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $periodPayload = ql_ai_period_input($input);

    if ($groupId > 0) {
        $periodPayload['group_id'] = $groupId;
    }

    $report = ql_ledger_report($periodPayload);
    if (empty($report['ok'])) {
        return $report;
    }

    $advances = $groupId > 0 ? ql_advance_list([
        'group_id' => $groupId,
        'limit' => 200
    ]) : ['ok' => true, 'advances' => []];

    if (empty($advances['ok'])) {
        return $advances;
    }

    $audit = function_exists('ql_audit_list')
        ? ql_audit_list(['group_id' => $groupId, 'limit' => 20])
        : ['ok' => true, 'items' => []];

    $advanceRows = $advances['advances'] ?? [];
    $summary = $report['summary'] ?? [];
    $position = $report['position'] ?? [];
    $period = $report['period'] ?? [];

    $openIssued = 0.0;
    $openSpent = 0.0;
    $openLeft = 0.0;
    $records = 0;
    $submitted = 0;
    $discrepancy = 0;
    $returned = 0;
    $issued = 0;
    $accepted = 0;

    foreach ($advanceRows as $advance) {
        $status = (string)($advance['status'] ?? '');
        $s = $advance['summary'] ?? [];

        if ($status === 'submitted') {
            $submitted++;
        } elseif ($status === 'discrepancy') {
            $discrepancy++;
        } elseif ($status === 'returned') {
            $returned++;
        } elseif ($status === 'issued') {
            $issued++;
        } elseif ($status === 'accepted') {
            $accepted++;
        }

        if (!in_array($status, ['accepted', 'closed'], true)) {
            $openIssued += (float)($advance['amount'] ?? 0);
            $openSpent += (float)($s['cash_out'] ?? 0) + (float)($s['card_out'] ?? 0);
            $openLeft += (float)($s['cash_left'] ?? 0);
            $records += (int)($s['records_count'] ?? 0);
        }
    }

    $risks = [];
    $actions = [];

    if ($discrepancy > 0) {
        $risks[] = [
            'level' => 'high',
            'title' => 'Есть отчеты с расхождением',
            'detail' => $discrepancy . ' отчет(ов) требуют ручной проверки фактического остатка.'
        ];
        $actions[] = 'Открыть отчеты со статусом “Расхождение” и сверить строки с фото/документами.';
    }

    if ($submitted > 0) {
        $risks[] = [
            'level' => 'medium',
            'title' => 'Есть отчеты на модерации',
            'detail' => $submitted . ' отчет(ов) можно включить в общий учет после проверки.'
        ];
        $actions[] = 'Проверить сданные отчеты и принять корректные расходы в групповой учет.';
    }

    if ($issued > 0 || $returned > 0) {
        $risks[] = [
            'level' => 'medium',
            'title' => 'Открытые деньги под отчет',
            'detail' => 'Активно: ' . $issued . ', возвращено на правку: ' . $returned . '.'
        ];
        $actions[] = 'Дожать открытые выдачи: сотрудник должен сдать отчет или администратор отменяет ошибочную выдачу с причиной.';
    }

    if ((float)($summary['expense'] ?? 0) > (float)($summary['income'] ?? 0) && (float)($summary['income'] ?? 0) > 0) {
        $risks[] = [
            'level' => 'low',
            'title' => 'Расход выше прихода за период',
            'detail' => 'Расходы: ' . ql_ai_money($summary['expense'] ?? 0) . ', приходы: ' . ql_ai_money($summary['income'] ?? 0) . '.'
        ];
        $actions[] = 'Проверить, не попал ли расход не в тот период или не забыта ли приходная строка.';
    }

    if (!$risks) {
        $risks[] = [
            'level' => 'ok',
            'title' => 'Критичных сигналов нет',
            'detail' => 'По текущим данным нет расхождений и зависших сданных отчетов.'
        ];
    }

    $structure = [
        [
            'title' => '1. Короткая сводка',
            'items' => [
                'Период: ' . (($period['from'] ?? '') . ' - ' . ($period['to'] ?? '')),
                'Было: ' . ql_ai_money($position['before'] ?? 0) . ', стало: ' . ql_ai_money($position['after'] ?? 0),
                'Баланс: ' . ql_ai_money($summary['balance'] ?? 0),
                'Приход: ' . ql_ai_money($summary['income'] ?? 0) . ', расход: ' . ql_ai_money($summary['expense'] ?? 0),
            ]
        ],
        [
            'title' => '2. Деньги под отчет',
            'items' => [
                'Открыто выдано: ' . ql_ai_money($openIssued),
                'Уже потрачено по открытым выдачам: ' . ql_ai_money($openSpent),
                'Ожидаемый остаток: ' . ql_ai_money($openLeft),
                'Записей в открытых отчетах: ' . $records,
            ]
        ],
        [
            'title' => '3. Проверка и ответственность',
            'items' => [
                'На проверке: ' . $submitted,
                'Расхождения: ' . $discrepancy,
                'Возвращено на правку: ' . $returned,
                'Принято в учет: ' . $accepted,
            ]
        ],
        [
            'title' => '4. Что приложить к финальному отчету',
            'items' => [
                'Excel/PDF по группе за период.',
                'Список принятых отчетов сотрудников.',
                'Фото и документы по спорным позициям.',
                'Причины отмен и возвратов из аудита.'
            ]
        ],
    ];

    $executiveSummary = 'За период '
        . (($period['from'] ?? '') . ' - ' . ($period['to'] ?? ''))
        . ' было ' . ql_ai_money($position['before'] ?? 0)
        . ', стало ' . ql_ai_money($position['after'] ?? 0)
        . ', движение ' . ql_ai_money($summary['balance'] ?? 0)
        . '. Открытые деньги под отчет: ' . ql_ai_money($openIssued)
        . ', ожидаемый остаток по ним: ' . ql_ai_money($openLeft)
        . '. На проверке: ' . $submitted
        . ', расхождений: ' . $discrepancy . '.';

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'ai_analysis_run', 'group', $groupId ?: null, [
            'group_id' => $groupId ?: null,
            'period' => $period,
            'mode' => 'local_account_structuring'
        ]);
    }

    return [
        'ok' => true,
        'mode' => 'local_account_structuring',
        'user' => [
            'id' => $userId,
            'email' => $user['email'] ?? null,
        ],
        'period' => $period,
        'summary' => [
            'ledger' => $summary,
            'position' => $position,
            'open_issued' => round($openIssued, 2),
            'open_spent' => round($openSpent, 2),
            'open_left' => round($openLeft, 2),
            'submitted' => $submitted,
            'discrepancy' => $discrepancy,
            'returned' => $returned,
            'issued' => $issued,
            'accepted' => $accepted,
            'audit_events' => count($audit['items'] ?? []),
        ],
        'executive_summary' => $executiveSummary,
        'risks' => $risks,
        'action_items' => array_values(array_unique($actions)),
        'report_structure' => $structure,
    ];
}
