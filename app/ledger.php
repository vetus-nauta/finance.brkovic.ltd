<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/on_the_go.php';

function ql_require_user(): array
{
    $user = ql_current_user();

    if (!$user) {
        ql_json(['ok' => false, 'error' => 'not_authenticated'], 401);
    }

    return $user;
}

function ql_money_amount($amount): ?string
{
    $raw = str_replace(',', '.', trim((string)$amount));

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return null;
    }

    if ((float)$raw <= 0) {
        return null;
    }

    return number_format((float)$raw, 2, '.', '');
}


function ql_ledger_group_scope(int $groupId, int $userId): ?array
{
    if ($groupId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT role, access_level, permissions_json
        FROM group_members
        WHERE group_id = ?
          AND user_id = ?
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$groupId, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $accessLevel = $row['access_level'] ?? ($row['role'] === 'admin' ? 'advanced' : 'base');
    if (!in_array($accessLevel, ['base', 'manager', 'advanced'], true)) {
        $accessLevel = 'base';
    }

    $permissions = json_decode((string)($row['permissions_json'] ?? ''), true);
    if (!is_array($permissions)) {
        $permissions = [
            'can_write_group_ledger' => in_array($accessLevel, ['manager', 'advanced'], true),
            'can_view_group_reports' => in_array($accessLevel, ['manager', 'advanced'], true),
            'can_manage_members' => $accessLevel === 'advanced',
        ];
    }

    return [
        'group_id' => $groupId,
        'role' => $row['role'],
        'access_level' => $accessLevel,
        'permissions' => $permissions,
        'is_admin' => $row['role'] === 'admin' || $accessLevel === 'advanced',
        'can_write_group_ledger' => !empty($permissions['can_write_group_ledger']) || in_array($accessLevel, ['manager', 'advanced'], true),
        'can_view_group_reports' => !empty($permissions['can_view_group_reports']) || in_array($accessLevel, ['manager', 'advanced'], true),
    ];
}

function ql_ledger_input_group_id(array $input): int
{
    $groupId = (int)($input['group_id'] ?? 0);
    return $groupId > 0 ? $groupId : 0;
}

function ql_ledger_virtual_on_the_go_entries(int $groupId, ?int $ownerUserId = null, ?string $fromSql = null, ?string $toSql = null): array
{
    if ($groupId <= 0) {
        return [];
    }

    $where = "
        t.group_id = ?
        AND t.status <> 'archived'
        AND (t.advance_id IS NULL OR t.advance_id = 0)
        AND c.reportable = 1
        AND c.review_status <> 'archived'
        AND c.amount IS NOT NULL
        AND c.amount > 0
        AND c.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
    ";
    $params = [$groupId];

    if ($ownerUserId !== null && $ownerUserId > 0) {
        $where .= " AND t.user_id = ?";
        $params[] = $ownerUserId;
    }

    if ($fromSql !== null) {
        $where .= " AND c.created_at >= ?";
        $params[] = $fromSql;
    }

    if ($toSql !== null) {
        $where .= " AND c.created_at <= ?";
        $params[] = $toSql;
    }

    $stmt = ql_db()->prepare("
        SELECT
            c.id,
            c.user_id,
            t.group_id,
            t.id AS tape_id,
            t.title AS tape_title,
            t.stream_type,
            t.archived_at,
            c.capture_type,
            c.amount,
            c.currency,
            c.description,
            c.created_at,
            c.updated_at,
            u.email AS owner_email,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_display_name,
            (
                SELECT COUNT(*)
                FROM on_the_go_files f
                WHERE f.capture_id = c.id
            ) AS file_count
        FROM on_the_go_captures c
        JOIN on_the_go_tapes t ON t.id = c.tape_id
        JOIN users u ON u.id = c.user_id
        LEFT JOIN group_members gm ON gm.group_id = t.group_id AND gm.user_id = c.user_id
        WHERE {$where}
        ORDER BY c.created_at ASC, c.id ASC
        LIMIT 500
    ");
    $stmt->execute($params);

    $rows = [];
    $legacyCache = [];
    foreach ($stmt->fetchAll() as $row) {
        $tapeId = (int)$row['tape_id'];
        $rowUserId = (int)$row['user_id'];
        $legacyKey = $tapeId . ':' . $rowUserId;
        if (!array_key_exists($legacyKey, $legacyCache)) {
            $legacyCache[$legacyKey] = function_exists('ql_on_the_go_active_legacy_ledger_rows')
                ? ql_on_the_go_active_legacy_ledger_rows($tapeId, $rowUserId)
                : 0;
        }
        if ($legacyCache[$legacyKey] > 0) {
            continue;
        }

        $captureType = (string)$row['capture_type'];
        $entryType = $captureType === 'cash_in' ? 'income' : 'expense';
        $moneyType = $captureType === 'noncash_out' ? 'noncash' : 'cash';
        $purpose = trim((string)($row['description'] ?? ''));
        if ($purpose === '') {
            $purpose = trim((string)($row['tape_title'] ?? ''));
        }
        if ($purpose === '') {
            $purpose = 'Живой отчет';
        }

        $rows[] = [
            'id' => 'otr-' . (int)$row['id'],
            'virtual_source' => 'on_the_go',
            'archived_at' => (string)($row['archived_at'] ?? ''),
            'capture_id' => (int)$row['id'],
            'tape_id' => $tapeId,
            'user_id' => $rowUserId,
            'group_id' => (int)$row['group_id'],
            'entry_type' => $entryType,
            'money_type' => $moneyType,
            'category_id' => null,
            'category_name' => 'Живой отчет',
            'amount' => number_format((float)$row['amount'], 2, '.', ''),
            'currency' => (string)($row['currency'] ?? 'EUR'),
            'purpose' => $purpose,
            'note' => 'Из включенной карточки живого отчета #' . $tapeId,
            'entry_datetime' => (string)$row['created_at'],
            'original_position_at' => (string)$row['created_at'],
            'edited_at' => null,
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
            'owner_email' => (string)($row['owner_email'] ?? ''),
            'owner_display_name' => (string)($row['owner_display_name'] ?? $row['owner_email'] ?? 'Участник'),
            'file_count' => (int)($row['file_count'] ?? 0),
        ];
    }

    return $rows;
}

function ql_ledger_apply_entry_to_summary(array &$summary, array $entry): void
{
    $amount = (float)($entry['amount'] ?? 0);
    $records = 1;
    $summary['records'] = (int)($summary['records'] ?? 0) + $records;

    if (($entry['entry_type'] ?? '') === 'income') {
        $summary['income'] += $amount;
        if (($entry['money_type'] ?? '') === 'cash') {
            $summary['cash_income'] += $amount;
        } else {
            $summary['noncash_income'] += $amount;
        }
    } else {
        $summary['expense'] += $amount;
        if (($entry['money_type'] ?? '') === 'cash') {
            $summary['cash_expense'] += $amount;
        } else {
            $summary['noncash_expense'] += $amount;
        }
    }
}

function ql_ledger_report_section_label(string $name): string
{
    $normalized = trim($name);
    if ($normalized === '' || $normalized === 'No section') {
        return 'Без раздела';
    }
    if ($normalized === 'On the Go') {
        return 'Подотчеты';
    }
    return $normalized;
}

function ql_excel_text($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ql_excel_money($value): string
{
    return '€' . number_format((float)$value, 2, '.', '');
}

function ql_tsv_cell($value): string
{
    return str_replace(["\t", "\r", "\n"], [' ', ' ', ' '], (string)$value);
}

function ql_tsv_money($value): string
{
    return number_format((float)$value, 2, '.', '');
}

function ql_tsv_line(array $cells): string
{
    return implode("\t", array_map('ql_tsv_cell', $cells));
}

function ql_sheet_td($value, string $style = ''): string
{
    $base = 'border:1px solid #cbd5e1;padding:6px 8px;vertical-align:top;white-space:normal;';
    return '<td style="' . $base . $style . '">' . ql_excel_text($value) . '</td>';
}

function ql_sheet_th($value, string $style = ''): string
{
    $base = 'border:1px solid #94a3b8;padding:7px 8px;vertical-align:top;font-weight:700;color:#0f172a;white-space:normal;';
    return '<th style="' . $base . $style . '">' . ql_excel_text($value) . '</th>';
}

function ql_sheet_row(array $cells, string $tag = 'td', string $style = ''): string
{
    $html = '<tr>';
    foreach ($cells as $cell) {
        if (is_array($cell)) {
            $value = $cell[0] ?? '';
            $cellStyle = $cell[1] ?? $style;
        } else {
            $value = $cell;
            $cellStyle = $style;
        }
        $html .= $tag === 'th' ? ql_sheet_th($value, $cellStyle) : ql_sheet_td($value, $cellStyle);
    }
    return $html . '</tr>';
}

function ql_sheet_section_row(string $title, int $columns, string $background): string
{
    $cells = [[$title, 'background:' . $background . ';font-weight:700;font-size:14px;']];
    for ($i = 1; $i < $columns; $i++) {
        $cells[] = ['', 'background:' . $background . ';'];
    }
    return ql_sheet_row($cells);
}

function ql_ledger_group_export_rows(int $groupId): array
{
    $stmt = ql_db()->prepare("
        SELECT
            le.id,
            le.user_id,
            le.group_id,
            le.entry_type,
            le.money_type,
            le.category_id,
            COALESCE(lc.name, 'No section') AS category_name,
            le.amount,
            le.currency,
            le.purpose,
            le.note,
            le.entry_datetime,
            le.original_position_at,
            le.created_at,
            le.updated_at,
            u.email AS owner_email,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_display_name,
            (
                SELECT COUNT(*)
                FROM entry_files ef
                WHERE ef.entry_id = le.id
                  AND ef.deleted_at IS NULL
            ) AS file_count
        FROM ledger_entries le
        JOIN users u ON u.id = le.user_id
        LEFT JOIN group_members gm ON gm.group_id = le.group_id AND gm.user_id = le.user_id
        LEFT JOIN ledger_categories lc ON lc.id = le.category_id
        WHERE le.group_id = ?
          AND le.deleted_at IS NULL
        ORDER BY le.original_position_at ASC, le.id ASC
    ");
    $stmt->execute([$groupId]);
    $rows = $stmt->fetchAll();

    $rows = array_merge($rows, ql_ledger_virtual_on_the_go_entries($groupId));
    usort($rows, function ($a, $b) {
        $aTime = (string)($a['original_position_at'] ?? $a['entry_datetime'] ?? '');
        $bTime = (string)($b['original_position_at'] ?? $b['entry_datetime'] ?? '');
        $cmp = strcmp($aTime, $bTime);
        if ($cmp !== 0) return $cmp;
        return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
    });

    return $rows;
}

function ql_ledger_group_accountable_left_rows(int $groupId): array
{
    if ($groupId <= 0) {
        return [];
    }

    $stmt = ql_db()->prepare("
        SELECT
            ca.assigned_to_user_id AS user_id,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_display_name,
            u.email,
            COUNT(ca.id) AS open_count,
            COALESCE(SUM(
                CASE
                    WHEN ca.actual_remaining IS NOT NULL THEN ca.actual_remaining
                    ELSE COALESCE(t.cash_received, ca.amount) + COALESCE(s.extra_cash_in, 0) - COALESCE(s.cash_out, 0)
                END
            ), 0) AS cash_left
        FROM cash_advances ca
        JOIN users u ON u.id = ca.assigned_to_user_id
        LEFT JOIN group_members gm ON gm.group_id = ca.group_id AND gm.user_id = ca.assigned_to_user_id
        LEFT JOIN on_the_go_tapes t ON t.id = ca.on_the_go_tape_id
        LEFT JOIN (
            SELECT
                tape_id,
                COALESCE(SUM(CASE WHEN capture_type = 'cash_in' THEN amount ELSE 0 END), 0) AS extra_cash_in,
                COALESCE(SUM(CASE WHEN capture_type = 'cash_out' THEN amount ELSE 0 END), 0) AS cash_out
            FROM on_the_go_captures
            WHERE review_status <> 'archived'
              AND capture_type IN ('cash_in', 'cash_out')
            GROUP BY tape_id
        ) s ON s.tape_id = t.id
        WHERE ca.group_id = ?
          AND ca.deleted_at IS NULL
          AND ca.status IN ('issued', 'submitted', 'returned', 'discrepancy')
        GROUP BY ca.assigned_to_user_id, owner_display_name, u.email
        HAVING cash_left > 0.009
        ORDER BY owner_display_name ASC
    ");
    $stmt->execute([$groupId]);

    return array_map(function ($row) {
        $row['cash_left'] = round((float)($row['cash_left'] ?? 0), 2);
        $row['open_count'] = (int)($row['open_count'] ?? 0);
        return $row;
    }, $stmt->fetchAll());
}

function ql_ledger_group_accountable_control_rows(int $groupId): array
{
    $rowsByUser = [];
    foreach (ql_ledger_group_accountable_left_rows($groupId) as $row) {
        $userId = (int)($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $rowsByUser[$userId] = [
            'user_id' => $userId,
            'owner_display_name' => (string)($row['owner_display_name'] ?? 'Сотрудник'),
            'email' => (string)($row['email'] ?? ''),
            'open_count' => (int)($row['open_count'] ?? 0),
            'overrun_count' => 0,
            'cash_left' => round((float)($row['cash_left'] ?? 0), 2),
            'positive_remaining_cash' => max(0.0, round((float)($row['cash_left'] ?? 0), 2)),
            'reimbursement_due' => 0.0,
            'participant_control_balance' => round((float)($row['cash_left'] ?? 0), 2),
        ];
    }

    if ($groupId <= 0) {
        return array_values($rowsByUser);
    }

    $stmt = ql_db()->prepare("
        SELECT
            ca.*,
            assigned.email,
            COALESCE(assigned_gm.display_name, assigned.display_name, assigned.email) AS owner_display_name
        FROM cash_advances ca
        JOIN users assigned ON assigned.id = ca.assigned_to_user_id
        LEFT JOIN group_members assigned_gm ON assigned_gm.group_id = ca.group_id AND assigned_gm.user_id = ca.assigned_to_user_id
        WHERE ca.group_id = ?
          AND ca.deleted_at IS NULL
          AND ca.status = 'accepted'
        ORDER BY ca.accepted_at ASC, ca.id ASC
        LIMIT 300
    ");
    $stmt->execute([$groupId]);

    foreach ($stmt->fetchAll() as $advance) {
        $userId = (int)($advance['assigned_to_user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $overrun = 0.0;
        if ($advance['expected_remaining'] !== null) {
            $expectedRemaining = round((float)$advance['expected_remaining'], 2);
            if ($expectedRemaining < -0.009) {
                $overrun = abs($expectedRemaining);
            }
        }
        if ($overrun < 0.009) {
            $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
            $summary = $tapeId > 0 && function_exists('ql_advance_tape_summary')
                ? ql_advance_tape_summary($tapeId)
                : [];
            $acceptedCash = round((float)($summary['cash_out'] ?? 0), 2);
            $issued = round((float)($advance['amount'] ?? 0), 2);
            $overrun = max(0.0, round($acceptedCash - $issued, 2));
        }
        if ($overrun < 0.009) {
            continue;
        }

        if (!isset($rowsByUser[$userId])) {
            $rowsByUser[$userId] = [
                'user_id' => $userId,
                'owner_display_name' => (string)($advance['owner_display_name'] ?? $advance['email'] ?? 'Сотрудник'),
                'email' => (string)($advance['email'] ?? ''),
                'open_count' => 0,
                'overrun_count' => 0,
                'cash_left' => 0.0,
                'positive_remaining_cash' => 0.0,
                'reimbursement_due' => 0.0,
                'participant_control_balance' => 0.0,
            ];
        }

        $rowsByUser[$userId]['overrun_count'] += 1;
        $rowsByUser[$userId]['cash_left'] = round((float)$rowsByUser[$userId]['cash_left'] - $overrun, 2);
        $rowsByUser[$userId]['positive_remaining_cash'] = max(0.0, (float)$rowsByUser[$userId]['cash_left']);
        $rowsByUser[$userId]['reimbursement_due'] = max(0.0, 0 - (float)$rowsByUser[$userId]['cash_left']);
        $rowsByUser[$userId]['participant_control_balance'] = $rowsByUser[$userId]['cash_left'];
    }

    $rows = array_values(array_filter($rowsByUser, static function ($row) {
        return abs((float)($row['cash_left'] ?? 0)) > 0.009;
    }));
    usort($rows, static function ($a, $b) {
        return strcmp((string)($a['owner_display_name'] ?? ''), (string)($b['owner_display_name'] ?? ''));
    });

    return $rows;
}

function ql_ledger_group_participant_control_from_package(array $snapshot, ?array $package): array
{
    $existingControl = is_array($snapshot['participant_control'] ?? null) ? $snapshot['participant_control'] : [];
    if (is_array($existingControl['participants'] ?? null)) {
        $rows = array_values($existingControl['participants']);
        usort($rows, static function ($a, $b) {
            return strcmp((string)($a['owner_display_name'] ?? ''), (string)($b['owner_display_name'] ?? ''));
        });
        return $rows;
    }

    $rowsByUser = [];
    foreach (($snapshot['accountable_rows'] ?? []) as $row) {
        $userId = (int)($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $cashLeft = round((float)($row['cash_left'] ?? $row['participant_control_balance'] ?? 0), 2);
        if (abs($cashLeft) < 0.009) {
            continue;
        }
        $rowsByUser[$userId] = [
            'user_id' => $userId,
            'owner_display_name' => (string)($row['owner_display_name'] ?? $row['name'] ?? 'Сотрудник'),
            'email' => (string)($row['email'] ?? ''),
            'open_count' => (int)($row['open_count'] ?? 0),
            'overrun_count' => (int)($row['overrun_count'] ?? 0),
            'cash_left' => $cashLeft,
            'positive_remaining_cash' => max(0.0, $cashLeft),
            'reimbursement_due' => max(0.0, 0 - $cashLeft),
            'participant_control_balance' => $cashLeft,
        ];
    }

    if (!$package) {
        return array_values($rowsByUser);
    }

    $expectedByAdvance = [];
    foreach (($package['audit_refs'] ?? []) as $ref) {
        if (($ref['action'] ?? '') !== 'advance_submitted') {
            continue;
        }
        $advanceId = (int)($ref['entity_id'] ?? 0);
        $details = is_array($ref['details'] ?? null) ? $ref['details'] : [];
        if ($advanceId > 0 && array_key_exists('expected_remaining', $details)) {
            $expectedByAdvance[$advanceId] = round((float)$details['expected_remaining'], 2);
        }
    }

    foreach (($package['accountable']['items'] ?? []) as $item) {
        if (($item['status'] ?? '') !== 'accepted') {
            continue;
        }
        $advanceId = (int)($item['advance_id'] ?? 0);
        $participant = is_array($item['participant'] ?? null) ? $item['participant'] : [];
        $userId = (int)($participant['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $overrun = 0.0;
        if (isset($expectedByAdvance[$advanceId]) && $expectedByAdvance[$advanceId] < -0.009) {
            $overrun = abs($expectedByAdvance[$advanceId]);
        }
        if ($overrun < 0.009) {
            $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
            $acceptedCash = round((float)($summary['accepted_cash_spent'] ?? 0), 2);
            $issued = round((float)($item['issued_amount'] ?? 0), 2);
            $overrun = max(0.0, round($acceptedCash - $issued, 2));
        }
        if ($overrun < 0.009) {
            continue;
        }

        if (!isset($rowsByUser[$userId])) {
            $rowsByUser[$userId] = [
                'user_id' => $userId,
                'owner_display_name' => (string)($participant['name'] ?? $participant['email'] ?? 'Сотрудник'),
                'email' => (string)($participant['email'] ?? ''),
                'open_count' => 0,
                'overrun_count' => 0,
                'cash_left' => 0.0,
                'positive_remaining_cash' => 0.0,
                'reimbursement_due' => 0.0,
                'participant_control_balance' => 0.0,
            ];
        }
        $rowsByUser[$userId]['overrun_count'] += 1;
        $rowsByUser[$userId]['cash_left'] = round((float)$rowsByUser[$userId]['cash_left'] - $overrun, 2);
        $rowsByUser[$userId]['positive_remaining_cash'] = max(0.0, (float)$rowsByUser[$userId]['cash_left']);
        $rowsByUser[$userId]['reimbursement_due'] = max(0.0, 0 - (float)$rowsByUser[$userId]['cash_left']);
        $rowsByUser[$userId]['participant_control_balance'] = $rowsByUser[$userId]['cash_left'];
    }

    $rows = array_values(array_filter($rowsByUser, static function ($row) {
        return abs((float)($row['cash_left'] ?? 0)) > 0.009;
    }));
    usort($rows, static function ($a, $b) {
        return strcmp((string)($a['owner_display_name'] ?? ''), (string)($b['owner_display_name'] ?? ''));
    });

    return $rows;
}

function ql_ledger_group_apply_participant_control(array $snapshot, ?array $package = null): array
{
    $rows = ql_ledger_group_participant_control_from_package($snapshot, $package);
    $employeePositiveRemaining = 0.0;
    $employeeReimbursementDue = 0.0;
    foreach ($rows as &$row) {
        $row['cash_left'] = round((float)($row['cash_left'] ?? 0), 2);
        $row['positive_remaining_cash'] = max(0.0, $row['cash_left']);
        $row['reimbursement_due'] = max(0.0, 0 - $row['cash_left']);
        $row['participant_control_balance'] = $row['cash_left'];
        $employeePositiveRemaining += $row['positive_remaining_cash'];
        $employeeReimbursementDue += $row['reimbursement_due'];
    }
    unset($row);

    $employeePositiveRemaining = round($employeePositiveRemaining, 2);
    $employeeReimbursementDue = round($employeeReimbursementDue, 2);
    $employeeCashLeft = round($employeePositiveRemaining - $employeeReimbursementDue, 2);
    $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
    $cashBalance = round((float)($totals['cash_balance'] ?? 0), 2);
    $adminCashLeft = round($cashBalance - $employeeCashLeft, 2);
    if (abs($adminCashLeft) < 0.01) {
        $adminCashLeft = 0.0;
    }

    $totals['admin_cash_left'] = $adminCashLeft;
    $totals['employee_cash_left'] = $employeeCashLeft;
    $totals['employee_positive_remaining_total'] = $employeePositiveRemaining;
    $totals['employee_reimbursement_due_total'] = $employeeReimbursementDue;
    $totals['employee_net_remaining_total'] = $employeeCashLeft;
    $snapshot['totals'] = $totals;
    $snapshot['accountable_rows'] = $rows;
    $snapshot['participant_control'] = [
        'version' => 1,
        'cash_balance' => $cashBalance,
        'admin_cash_left' => $adminCashLeft,
        'employee_cash_left' => $employeeCashLeft,
        'employee_positive_remaining_total' => $employeePositiveRemaining,
        'employee_reimbursement_due_total' => $employeeReimbursementDue,
        'employee_net_remaining_total' => $employeeCashLeft,
        'participants' => $rows,
    ];

    return $snapshot;
}

function ql_ledger_group_apply_package_participant_control(array $package, array $snapshot): array
{
    $control = is_array($snapshot['participant_control'] ?? null) ? $snapshot['participant_control'] : [];
    $totals = is_array($snapshot['totals'] ?? null) ? $snapshot['totals'] : [];
    $adminCashLeft = round((float)($totals['admin_cash_left'] ?? 0), 2);
    $employeeCashLeft = round((float)($totals['employee_cash_left'] ?? 0), 2);
    $employeePositiveRemaining = round((float)($totals['employee_positive_remaining_total'] ?? max(0.0, $employeeCashLeft)), 2);
    $employeeReimbursementDue = round((float)($totals['employee_reimbursement_due_total'] ?? max(0.0, 0 - $employeeCashLeft)), 2);
    $cashBalance = round((float)($totals['cash_balance'] ?? 0), 2);
    $balance = round((float)($totals['balance'] ?? 0), 2);

    if (!isset($package['summary']) || !is_array($package['summary'])) {
        $package['summary'] = [];
    }
    $package['summary']['admin_cash_left'] = $adminCashLeft;
    $package['summary']['accountable_money_left'] = $employeeCashLeft;
    $package['summary']['employee_positive_remaining_total'] = $employeePositiveRemaining;
    $package['summary']['employee_reimbursement_due_total'] = $employeeReimbursementDue;
    $package['summary']['employee_net_remaining_total'] = $employeeCashLeft;
    $package['summary']['cash_balance'] = $cashBalance;
    $package['summary']['balance'] = $balance;
    if (!isset($package['summary']['carryover']) || !is_array($package['summary']['carryover'])) {
        $package['summary']['carryover'] = [];
    }
    $package['summary']['carryover']['admin_cash_left'] = $adminCashLeft;
    $package['summary']['carryover']['employee_cash_left'] = $employeeCashLeft;
    $package['summary']['carryover']['employee_positive_remaining_total'] = $employeePositiveRemaining;
    $package['summary']['carryover']['employee_reimbursement_due_total'] = $employeeReimbursementDue;
    $package['summary']['carryover']['employee_net_remaining_total'] = $employeeCashLeft;
    $package['summary']['carryover']['cash_balance'] = $cashBalance;
    $package['summary']['carryover']['balance'] = $balance;
    $package['participant_control'] = $control ?: [
        'version' => 1,
        'cash_balance' => $cashBalance,
        'admin_cash_left' => $adminCashLeft,
        'employee_cash_left' => $employeeCashLeft,
        'employee_positive_remaining_total' => $employeePositiveRemaining,
        'employee_reimbursement_due_total' => $employeeReimbursementDue,
        'employee_net_remaining_total' => $employeeCashLeft,
        'participants' => $snapshot['accountable_rows'] ?? [],
    ];

    if (isset($package['accountable']) && is_array($package['accountable'])) {
        if (!isset($package['accountable']['totals']) || !is_array($package['accountable']['totals'])) {
            $package['accountable']['totals'] = [];
        }
        $package['accountable']['totals']['positive_remaining_cash'] = $employeePositiveRemaining;
        $package['accountable']['totals']['reimbursement_due'] = $employeeReimbursementDue;
        $package['accountable']['totals']['net_remaining_cash'] = $employeeCashLeft;

        $controlByUser = [];
        foreach (($package['participant_control']['participants'] ?? []) as $controlRow) {
            $userId = (int)($controlRow['user_id'] ?? 0);
            if ($userId > 0) {
                $controlByUser[$userId] = $controlRow;
            }
        }

        if (isset($package['accountable']['items']) && is_array($package['accountable']['items'])) {
            foreach ($package['accountable']['items'] as &$item) {
                if (!isset($item['summary']) || !is_array($item['summary'])) {
                    $item['summary'] = [];
                }
                $summary = $item['summary'];
                $acceptedCash = round((float)($summary['accepted_cash_spent'] ?? 0), 2);
                $issued = round((float)($item['issued_amount'] ?? 0), 2);
                $reimbursementDue = round((float)($summary['reimbursement_due'] ?? 0), 2);
                if (($item['status'] ?? '') === 'accepted' && $reimbursementDue < 0.009) {
                    $reimbursementDue = max(0.0, round($acceptedCash - $issued, 2));
                }
                $openRemaining = round((float)($summary['open_remaining_cash'] ?? 0), 2);
                $participantBalance = $reimbursementDue > 0.009 ? round(0 - $reimbursementDue, 2) : $openRemaining;

                $item['summary']['positive_remaining_cash'] = max(0.0, $participantBalance);
                $item['summary']['reimbursement_due'] = $reimbursementDue;
                $item['summary']['participant_control_balance'] = $participantBalance;
            }
            unset($item);
        }

        if (isset($package['accountable']['by_participant']) && is_array($package['accountable']['by_participant'])) {
            foreach ($package['accountable']['by_participant'] as &$row) {
                $participant = is_array($row['participant'] ?? null) ? $row['participant'] : [];
                $userId = (int)($participant['user_id'] ?? 0);
                $controlRow = $userId > 0 ? ($controlByUser[$userId] ?? null) : null;
                if ($controlRow) {
                    $row['positive_remaining_cash'] = round((float)($controlRow['positive_remaining_cash'] ?? max(0.0, (float)($controlRow['cash_left'] ?? 0))), 2);
                    $row['reimbursement_due'] = round((float)($controlRow['reimbursement_due'] ?? max(0.0, 0 - (float)($controlRow['cash_left'] ?? 0))), 2);
                    $row['participant_control_balance'] = round((float)($controlRow['cash_left'] ?? $controlRow['participant_control_balance'] ?? 0), 2);
                }
            }
            unset($row);
        }
    }

    return $package;
}

function ql_ledger_group_latest_finalized_at(int $groupId): string
{
    if ($groupId <= 0) {
        return '';
    }

    $stmt = ql_db()->prepare("
        SELECT created_at
        FROM audit_log
        WHERE action = 'ledger_group_report_finalized'
          AND entity_type = 'group'
          AND entity_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$groupId]);
    return (string)($stmt->fetchColumn() ?: '');
}

function ql_ledger_group_rows_snapshot(string $groupName, array $preparedRows, array $accountableRows, array $user): array
{
    $income = 0.0;
    $expense = 0.0;
    $cashExpense = 0.0;
    $noncashExpense = 0.0;
    $cashBalance = 0.0;
    $balance = 0.0;
    $articleRows = [];
    $memberRows = [];

    foreach ($preparedRows as &$row) {
        $amount = round((float)($row['amount'] ?? 0), 2);
        $entryType = (string)($row['entry_type'] ?? ($row['type'] === 'Приход' ? 'income' : 'expense'));
        $moneyType = (string)($row['money_type'] ?? ($row['money'] === 'Наличные' ? 'cash' : 'noncash'));
        $cashBefore = $cashBalance;
        $cashChange = 0.0;
        $balanceChange = 0.0;

        if ($entryType === 'income') {
            $income += $amount;
            $balanceChange = $amount;
            if ($moneyType === 'cash') {
                $cashChange = $amount;
            }
        } else {
            $expense += $amount;
            $balanceChange = -$amount;
            if ($moneyType === 'cash') {
                $cashExpense += $amount;
                $cashChange = -$amount;
            } else {
                $noncashExpense += $amount;
            }
        }

        $cashBalance = round($cashBalance + $cashChange, 2);
        $balance = round($balance + $balanceChange, 2);

        $row['type'] = $entryType === 'income' ? 'Приход' : 'Расход';
        $row['money'] = $moneyType === 'cash' ? 'Наличные' : 'Карта / безнал';
        $row['amount'] = $amount;
        $row['cash_before'] = $cashBefore;
        $row['cash_change'] = $cashChange;
        $row['cash_after'] = $cashBalance;
        $row['balance_after'] = $balance;

        $article = (string)($row['section'] ?: 'Без раздела');
        if (!isset($articleRows[$article])) {
            $articleRows[$article] = ['name' => $article, 'records' => 0, 'income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        }
        $ownerKey = (string)(($row['owner'] ?? '') . '|' . ($row['email'] ?? ''));
        if (!isset($memberRows[$ownerKey])) {
            $memberRows[$ownerKey] = ['name' => (string)($row['owner'] ?? ''), 'records' => 0, 'income' => 0.0, 'expense' => 0.0];
        }
        $articleRows[$article]['records']++;
        $memberRows[$ownerKey]['records']++;
        if ($entryType === 'income') {
            $articleRows[$article]['income'] += $amount;
            $memberRows[$ownerKey]['income'] += $amount;
        } else {
            $articleRows[$article]['expense'] += $amount;
            $memberRows[$ownerKey]['expense'] += $amount;
        }
        $articleRows[$article]['balance'] = $articleRows[$article]['income'] - $articleRows[$article]['expense'];
    }
    unset($row);

    $employeeCashLeft = 0.0;
    foreach ($accountableRows as $row) {
        $employeeCashLeft += (float)($row['cash_left'] ?? 0);
    }
    $employeeCashLeft = round($employeeCashLeft, 2);
    $adminCashLeft = round($cashBalance - $employeeCashLeft, 2);
    if (abs($adminCashLeft) < 0.01) {
        $adminCashLeft = 0.0;
    }

    $snapshot = [
        'group_name' => $groupName,
        'prepared_rows' => $preparedRows,
        'accountable_rows' => $accountableRows,
        'article_rows' => $articleRows,
        'member_rows' => $memberRows,
        'totals' => [
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'cash_expense' => round($cashExpense, 2),
            'noncash_expense' => round($noncashExpense, 2),
            'admin_cash_left' => $adminCashLeft,
            'employee_cash_left' => $employeeCashLeft,
            'cash_balance' => round($cashBalance, 2),
            'balance' => round($balance, 2),
        ],
        'admin_name' => (string)($user['display_name'] ?? $user['email'] ?? 'Администратор'),
    ];
    return ql_ledger_group_apply_participant_control($snapshot);
}

function ql_ledger_group_open_export_snapshot(int $groupId, array $user, string $groupName): array
{
    $open = ql_ledger_group_open_received_funds(['group_id' => $groupId]);
    if (empty($open['ok']) || empty($open['finalized_at'])) {
        return [];
    }

    $preparedRows = [];
    foreach (($open['carryovers'] ?? []) as $row) {
        $preparedRows[] = [
            'source_type' => 'carryover',
            'source_id' => (string)($row['id'] ?? ''),
            'source_ref' => (string)($row['id'] ?? ''),
            'date' => (string)($row['entry_datetime'] ?? $open['finalized_at']),
            'owner' => (string)($row['owner_display_name'] ?? 'Переходящий остаток'),
            'email' => (string)($row['owner_email'] ?? ''),
            'source' => 'Переходящий остаток',
            'section' => 'Переходящий остаток',
            'entry_type' => 'income',
            'money_type' => 'cash',
            'purpose' => 'Переходящий остаток',
            'amount' => round((float)($row['amount'] ?? 0), 2),
            'note' => (string)($row['note'] ?? ''),
        ];
    }

    foreach (($open['entries'] ?? []) as $row) {
        $preparedRows[] = [
            'source_type' => 'ledger_entry',
            'source_id' => (int)($row['id'] ?? 0),
            'ledger_entry_id' => (int)($row['id'] ?? 0),
            'date' => (string)($row['entry_datetime'] ?? $row['created_at'] ?? ''),
            'owner' => (string)($row['owner_display_name'] ?? $row['owner_email'] ?? ''),
            'email' => (string)($row['owner_email'] ?? ''),
            'source' => 'Журнал учета',
            'section' => 'Поступления периода',
            'entry_type' => (string)($row['entry_type'] ?? 'income'),
            'money_type' => (string)($row['money_type'] ?? 'cash'),
            'purpose' => (string)($row['purpose'] ?? ''),
            'amount' => round((float)($row['amount'] ?? 0), 2),
            'note' => (string)($row['note'] ?? ''),
        ];
    }

    $live = $open['open_period']['live_included'] ?? [];
    $cashExpense = round((float)($live['cash_expense'] ?? 0), 2);
    $cardExpense = round((float)($live['noncash_expense'] ?? 0), 2);
    if ($cashExpense > 0.009) {
        $preparedRows[] = [
            'source_type' => 'live_included_aggregate',
            'source_id' => 'live-included-cash',
            'date' => date('Y-m-d H:i:s'),
            'owner' => 'Живой отчет',
            'email' => '',
            'source' => 'Живой отчет',
            'section' => 'Живой отчет',
            'entry_type' => 'expense',
            'money_type' => 'cash',
            'purpose' => 'Включенные живые отчеты текущего периода',
            'amount' => $cashExpense,
            'note' => '',
        ];
    }
    if ($cardExpense > 0.009) {
        $preparedRows[] = [
            'source_type' => 'live_included_aggregate',
            'source_id' => 'live-included-card',
            'date' => date('Y-m-d H:i:s'),
            'owner' => 'Живой отчет',
            'email' => '',
            'source' => 'Живой отчет',
            'section' => 'Живой отчет',
            'entry_type' => 'expense',
            'money_type' => 'noncash',
            'purpose' => 'Карточные расходы текущего периода',
            'amount' => $cardExpense,
            'note' => '',
        ];
    }

    return ql_ledger_group_rows_snapshot($groupName, $preparedRows, ql_ledger_group_accountable_control_rows($groupId), $user);
}

function ql_ledger_group_export_snapshot(int $groupId, array $user): array
{
    $groupStmt = ql_db()->prepare("SELECT name FROM groups WHERE id = ? LIMIT 1");
    $groupStmt->execute([$groupId]);
    $groupName = (string)($groupStmt->fetchColumn() ?: ('group-' . $groupId));
    if (ql_ledger_group_latest_finalized_at($groupId) !== '') {
        $openSnapshot = ql_ledger_group_open_export_snapshot($groupId, $user, $groupName);
        if ($openSnapshot) {
            return $openSnapshot;
        }
    }
    $rows = ql_ledger_group_export_rows($groupId);

    $income = 0.0;
    $expense = 0.0;
    $cashExpense = 0.0;
    $noncashExpense = 0.0;
    $cashBalance = 0.0;
    $balance = 0.0;
    $preparedRows = [];

    foreach ($rows as $row) {
        $amount = round((float)($row['amount'] ?? 0), 2);
        $entryType = (string)($row['entry_type'] ?? 'expense');
        $moneyType = (string)($row['money_type'] ?? 'cash');
        $cashBefore = $cashBalance;
        $balanceChange = 0.0;
        $cashChange = 0.0;

        if ($entryType === 'income') {
            $income += $amount;
            $balanceChange = $amount;
            if ($moneyType === 'cash') {
                $cashChange = $amount;
            }
        } else {
            $expense += $amount;
            $balanceChange = -$amount;
            if ($moneyType === 'cash') {
                $cashExpense += $amount;
                $cashChange = -$amount;
            } else {
                $noncashExpense += $amount;
            }
        }

        $cashBalance = round($cashBalance + $cashChange, 2);
        $balance = round($balance + $balanceChange, 2);
        $note = (string)($row['note'] ?? '');
        $source = !empty($row['virtual_source'])
            ? 'Живой отчет'
            : (str_starts_with($note, 'From advance #') ? 'Подотчет' : 'Журнал учета');

        $preparedRows[] = [
            'source_type' => !empty($row['virtual_source']) ? 'on_the_go_capture' : 'ledger_entry',
            'source_id' => !empty($row['virtual_source']) ? (int)($row['capture_id'] ?? 0) : (int)($row['id'] ?? 0),
            'ledger_entry_id' => empty($row['virtual_source']) ? (int)($row['id'] ?? 0) : null,
            'capture_id' => !empty($row['virtual_source']) ? (int)($row['capture_id'] ?? 0) : null,
            'tape_id' => !empty($row['virtual_source']) ? (int)($row['tape_id'] ?? 0) : null,
            'user_id' => (int)($row['user_id'] ?? 0),
            'group_id' => (int)($row['group_id'] ?? 0),
            'entry_type' => $entryType,
            'money_type' => $moneyType,
            'file_count' => (int)($row['file_count'] ?? 0),
            'date' => (string)($row['entry_datetime'] ?? ''),
            'owner' => (string)($row['owner_display_name'] ?? $row['owner_email'] ?? ''),
            'email' => (string)($row['owner_email'] ?? ''),
            'source' => $source,
            'section' => ql_ledger_report_section_label((string)($row['category_name'] ?? '')),
            'type' => $entryType === 'income' ? 'Приход' : 'Расход',
            'money' => $moneyType === 'cash' ? 'Наличные' : 'Карта / безнал',
            'purpose' => (string)($row['purpose'] ?? ''),
            'amount' => $amount,
            'cash_before' => $cashBefore,
            'cash_change' => $cashChange,
            'cash_after' => $cashBalance,
            'balance_after' => $balance,
            'note' => $note,
        ];
    }

    $accountableRows = ql_ledger_group_accountable_control_rows($groupId);
    $employeeCashLeft = 0.0;
    foreach ($accountableRows as $row) {
        $employeeCashLeft += (float)($row['cash_left'] ?? 0);
    }
    $employeeCashLeft = round($employeeCashLeft, 2);
    $adminCashLeft = round($cashBalance - $employeeCashLeft, 2);
    if (abs($adminCashLeft) < 0.01) {
        $adminCashLeft = 0.0;
    }

    $articleRows = [];
    $memberRows = [];
    foreach ($preparedRows as $row) {
        $article = (string)($row['section'] ?: 'Без раздела');
        if (!isset($articleRows[$article])) {
            $articleRows[$article] = ['name' => $article, 'records' => 0, 'income' => 0.0, 'expense' => 0.0, 'balance' => 0.0];
        }
        $ownerKey = (string)($row['owner'] . '|' . $row['email']);
        if (!isset($memberRows[$ownerKey])) {
            $memberRows[$ownerKey] = ['name' => (string)$row['owner'], 'records' => 0, 'income' => 0.0, 'expense' => 0.0];
        }
        $articleRows[$article]['records']++;
        $memberRows[$ownerKey]['records']++;
        if ($row['type'] === 'Приход') {
            $articleRows[$article]['income'] += (float)$row['amount'];
            $memberRows[$ownerKey]['income'] += (float)$row['amount'];
        } else {
            $articleRows[$article]['expense'] += (float)$row['amount'];
            $memberRows[$ownerKey]['expense'] += (float)$row['amount'];
        }
        $articleRows[$article]['balance'] = $articleRows[$article]['income'] - $articleRows[$article]['expense'];
    }

    $snapshot = [
        'group_name' => $groupName,
        'prepared_rows' => $preparedRows,
        'accountable_rows' => $accountableRows,
        'article_rows' => $articleRows,
        'member_rows' => $memberRows,
        'totals' => [
            'income' => $income,
            'expense' => $expense,
            'cash_expense' => $cashExpense,
            'noncash_expense' => $noncashExpense,
            'admin_cash_left' => $adminCashLeft,
            'employee_cash_left' => $employeeCashLeft,
            'cash_balance' => $cashBalance,
            'balance' => $balance,
        ],
        'admin_name' => (string)($user['display_name'] ?? $user['email'] ?? 'Администратор'),
    ];
    return ql_ledger_group_apply_participant_control($snapshot);
}

function ql_ledger_group_google_sheet(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);
    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $scope = ql_ledger_group_scope($groupId, (int)$user['id']);
    if (!$scope || empty($scope['can_view_group_reports'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $snapshot = ql_ledger_group_export_snapshot($groupId, $user);
    $t = $snapshot['totals'];
    $lines = [];
    $htmlRows = [];
    $columns = 12;
    $headerStyle = 'background:#dbeafe;font-weight:700;';
    $summaryHeaderStyle = 'background:#bbf7d0;font-weight:700;';
    $leftoverHeaderStyle = 'background:#bfdbfe;font-weight:700;';
    $articleHeaderStyle = 'background:#fde68a;font-weight:700;';
    $memberHeaderStyle = 'background:#ddd6fe;font-weight:700;';
    $incomeStyle = 'background:#ecfdf5;color:#047857;font-weight:700;text-align:right;';
    $expenseStyle = 'background:#fef2f2;color:#b91c1c;font-weight:700;text-align:right;';
    $cashStyle = 'background:#eff6ff;text-align:right;';
    $cardStyle = 'background:#fffbeb;text-align:right;';
    $moneyStyle = 'text-align:right;';
    $keeperStyle = 'background:#f8fafc;font-weight:700;';

    $lines[] = ql_tsv_line(['FinDesk отчет группы', $snapshot['group_name']]);
    $lines[] = ql_tsv_line(['Сформировано', date('Y-m-d H:i:s')]);
    $lines[] = '';
    $lines[] = ql_tsv_line(['Итог']);
    $lines[] = ql_tsv_line(['Приход', 'Расход', 'Наличные расходы', 'Карта / безнал', 'У администратора', 'У сотрудников net', 'Остатки сотрудников', 'К возмещению сотрудникам', 'Физическая касса', 'Учетный баланс']);
    $lines[] = ql_tsv_line([
        ql_tsv_money($t['income']),
        ql_tsv_money($t['expense']),
        ql_tsv_money($t['cash_expense']),
        ql_tsv_money($t['noncash_expense']),
        ql_tsv_money($t['admin_cash_left']),
        ql_tsv_money($t['employee_cash_left']),
        ql_tsv_money($t['employee_positive_remaining_total'] ?? max(0.0, (float)$t['employee_cash_left'])),
        ql_tsv_money($t['employee_reimbursement_due_total'] ?? max(0.0, 0 - (float)$t['employee_cash_left'])),
        ql_tsv_money($t['cash_balance']),
        ql_tsv_money($t['balance']),
    ]);

    $htmlRows[] = ql_sheet_row([
        ['FinDesk отчет группы', 'background:#0f172a;color:#ffffff;font-weight:700;font-size:15px;'],
        [$snapshot['group_name'], 'background:#0f172a;color:#ffffff;font-weight:700;font-size:15px;'],
    ]);
    $htmlRows[] = ql_sheet_row([['Сформировано', 'background:#f8fafc;color:#64748b;'], [date('Y-m-d H:i:s'), 'background:#f8fafc;color:#64748b;']]);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Итог', $columns, '#dcfce7');
    $htmlRows[] = ql_sheet_row(['Приход', 'Расход', 'Наличные расходы', 'Карта / безнал', 'У администратора', 'У сотрудников net', 'Остатки сотрудников', 'К возмещению сотрудникам', 'Физическая касса', 'Учетный баланс'], 'th', $summaryHeaderStyle);
    $htmlRows[] = ql_sheet_row([
        [ql_tsv_money($t['income']), $incomeStyle],
        [ql_tsv_money($t['expense']), $expenseStyle],
        [ql_tsv_money($t['cash_expense']), $expenseStyle],
        [ql_tsv_money($t['noncash_expense']), $cardStyle],
        [ql_tsv_money($t['admin_cash_left']), $cashStyle],
        [ql_tsv_money($t['employee_cash_left']), $cashStyle],
        [ql_tsv_money($t['employee_positive_remaining_total'] ?? max(0.0, (float)$t['employee_cash_left'])), $cashStyle],
        [ql_tsv_money($t['employee_reimbursement_due_total'] ?? max(0.0, 0 - (float)$t['employee_cash_left'])), $expenseStyle],
        [ql_tsv_money($t['cash_balance']), $cashStyle],
        [ql_tsv_money($t['balance']), $moneyStyle],
    ]);

    $lines[] = '';
    $lines[] = ql_tsv_line(['Остатки на руках']);
    $lines[] = ql_tsv_line(['Хранитель', 'Тип', 'Отчетов', 'Остаток / net', 'К возмещению']);
    $lines[] = ql_tsv_line([$snapshot['admin_name'], 'Администратор', '', ql_tsv_money($t['admin_cash_left']), ql_tsv_money(0)]);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Остатки на руках', $columns, '#dbeafe');
    $htmlRows[] = ql_sheet_row(['Хранитель', 'Тип', 'Отчетов', 'Остаток / net', 'К возмещению'], 'th', $leftoverHeaderStyle);
    $htmlRows[] = ql_sheet_row([
        [$snapshot['admin_name'], $keeperStyle],
        'Администратор',
        '',
        [ql_tsv_money($t['admin_cash_left']), $cashStyle],
        [ql_tsv_money(0), $moneyStyle],
    ]);
    foreach ($snapshot['accountable_rows'] as $row) {
        $lines[] = ql_tsv_line([$row['owner_display_name'] ?? 'Участник', 'Сотрудник', (int)($row['open_count'] ?? 0), ql_tsv_money($row['cash_left'] ?? 0), ql_tsv_money($row['reimbursement_due'] ?? 0)]);
        $htmlRows[] = ql_sheet_row([
            [$row['owner_display_name'] ?? 'Участник', $keeperStyle],
            'Сотрудник',
            [(int)($row['open_count'] ?? 0), $moneyStyle],
            [ql_tsv_money($row['cash_left'] ?? 0), ((float)($row['cash_left'] ?? 0) < -0.009 ? $expenseStyle : $cashStyle)],
            [ql_tsv_money($row['reimbursement_due'] ?? 0), $expenseStyle],
        ]);
    }
    $lines[] = '';
    $lines[] = ql_tsv_line(['Статьи']);
    $lines[] = ql_tsv_line(['Статья', 'Строк', 'Приход', 'Расход', 'Итог']);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Статьи', $columns, '#fef3c7');
    $htmlRows[] = ql_sheet_row(['Статья', 'Строк', 'Приход', 'Расход', 'Итог'], 'th', $articleHeaderStyle);
    foreach ($snapshot['article_rows'] as $row) {
        $lines[] = ql_tsv_line([$row['name'], (int)$row['records'], ql_tsv_money($row['income']), ql_tsv_money($row['expense']), ql_tsv_money($row['balance'])]);
        $htmlRows[] = ql_sheet_row([
            $row['name'],
            [(int)$row['records'], $moneyStyle],
            [ql_tsv_money($row['income']), $incomeStyle],
            [ql_tsv_money($row['expense']), $expenseStyle],
            [ql_tsv_money($row['balance']), ((float)$row['balance'] < -0.009 ? $expenseStyle : $incomeStyle)],
        ]);
    }
    $lines[] = '';
    $lines[] = ql_tsv_line(['Участники']);
    $lines[] = ql_tsv_line(['Участник', 'Строк', 'Внес', 'Расход', 'Итог']);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Участники', $columns, '#ede9fe');
    $htmlRows[] = ql_sheet_row(['Участник', 'Строк', 'Внес', 'Расход', 'Итог'], 'th', $memberHeaderStyle);
    foreach ($snapshot['member_rows'] as $row) {
        $lines[] = ql_tsv_line([$row['name'], (int)$row['records'], ql_tsv_money($row['income']), ql_tsv_money($row['expense']), ql_tsv_money((float)$row['income'] - (float)$row['expense'])]);
        $total = (float)$row['income'] - (float)$row['expense'];
        $htmlRows[] = ql_sheet_row([
            [$row['name'], $keeperStyle],
            [(int)$row['records'], $moneyStyle],
            [ql_tsv_money($row['income']), $incomeStyle],
            [ql_tsv_money($row['expense']), $expenseStyle],
            [ql_tsv_money($total), ($total < -0.009 ? $expenseStyle : $incomeStyle)],
        ]);
    }
    $lines[] = '';
    $lines[] = ql_tsv_line(['Движение денег']);
    $lines[] = ql_tsv_line(['Дата', 'Кто', 'Источник', 'Статья', 'Тип', 'Деньги', 'Назначение', 'Сумма', 'Касса была', 'Движение', 'Касса стала', 'Баланс стал']);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Движение денег', $columns, '#e0f2fe');
    $htmlRows[] = ql_sheet_row(['Дата', 'Кто', 'Источник', 'Статья', 'Тип', 'Деньги', 'Назначение', 'Сумма', 'Касса была', 'Движение', 'Касса стала', 'Баланс стал'], 'th', $headerStyle);
    foreach ($snapshot['prepared_rows'] as $row) {
        $lines[] = ql_tsv_line([
            $row['date'],
            $row['owner'],
            $row['source'],
            $row['section'],
            $row['type'],
            $row['money'],
            $row['purpose'],
            ql_tsv_money($row['amount']),
            ql_tsv_money($row['cash_before']),
            ql_tsv_money($row['cash_change']),
            ql_tsv_money($row['cash_after']),
            ql_tsv_money($row['balance_after']),
        ]);
        $isIncome = $row['type'] === 'Приход';
        $isCash = $row['money'] === 'Наличные';
        $htmlRows[] = ql_sheet_row([
            $row['date'],
            [$row['owner'], $keeperStyle],
            $row['source'],
            $row['section'],
            [$row['type'], $isIncome ? $incomeStyle : $expenseStyle],
            [$row['money'], $isCash ? $cashStyle : $cardStyle],
            $row['purpose'],
            [ql_tsv_money($row['amount']), $isIncome ? $incomeStyle : $expenseStyle],
            [ql_tsv_money($row['cash_before']), $moneyStyle],
            [ql_tsv_money($row['cash_change']), ((float)$row['cash_change'] < -0.009 ? $expenseStyle : $incomeStyle)],
            [ql_tsv_money($row['cash_after']), $cashStyle],
            [ql_tsv_money($row['balance_after']), $moneyStyle],
        ]);
    }

    $colgroup = '<colgroup>'
        . '<col style="width:150px"><col style="width:170px"><col style="width:120px"><col style="width:150px">'
        . '<col style="width:90px"><col style="width:120px"><col style="width:260px"><col style="width:110px">'
        . '<col style="width:110px"><col style="width:110px"><col style="width:110px"><col style="width:110px">'
        . '</colgroup>';
    $html = '<table style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px;color:#111827;">'
        . $colgroup
        . implode('', $htmlRows)
        . '</table>';

    return [
        'ok' => true,
        'group_name' => $snapshot['group_name'],
        'rows' => count($snapshot['prepared_rows']),
        'tsv' => implode("\n", $lines),
        'html' => $html,
    ];
}

function ql_ledger_group_snapshot_sheet_payload(array $snapshot, ?string $generatedAt = null, string $title = 'FinDesk отчет группы'): array
{
    $generatedAt = $generatedAt ?: date('Y-m-d H:i:s');
    $t = $snapshot['totals'] ?? [];
    $lines = [];
    $htmlRows = [];
    $columns = 12;
    $headerStyle = 'background:#dbeafe;font-weight:700;';
    $summaryHeaderStyle = 'background:#bbf7d0;font-weight:700;';
    $leftoverHeaderStyle = 'background:#bfdbfe;font-weight:700;';
    $articleHeaderStyle = 'background:#fde68a;font-weight:700;';
    $memberHeaderStyle = 'background:#ddd6fe;font-weight:700;';
    $incomeStyle = 'background:#ecfdf5;color:#047857;font-weight:700;text-align:right;';
    $expenseStyle = 'background:#fef2f2;color:#b91c1c;font-weight:700;text-align:right;';
    $cashStyle = 'background:#eff6ff;text-align:right;';
    $cardStyle = 'background:#fffbeb;text-align:right;';
    $moneyStyle = 'text-align:right;';
    $keeperStyle = 'background:#f8fafc;font-weight:700;';
    $groupName = (string)($snapshot['group_name'] ?? 'group');

    $lines[] = ql_tsv_line([$title, $groupName]);
    $lines[] = ql_tsv_line(['Сформировано', $generatedAt]);
    $lines[] = '';
    $lines[] = ql_tsv_line(['Итог']);
    $lines[] = ql_tsv_line(['Приход', 'Расход', 'Наличные расходы', 'Карта / безнал', 'У администратора', 'У сотрудников net', 'Остатки сотрудников', 'К возмещению сотрудникам', 'Физическая касса', 'Учетный баланс']);
    $lines[] = ql_tsv_line([
        ql_tsv_money($t['income'] ?? 0),
        ql_tsv_money($t['expense'] ?? 0),
        ql_tsv_money($t['cash_expense'] ?? 0),
        ql_tsv_money($t['noncash_expense'] ?? 0),
        ql_tsv_money($t['admin_cash_left'] ?? 0),
        ql_tsv_money($t['employee_cash_left'] ?? 0),
        ql_tsv_money($t['employee_positive_remaining_total'] ?? max(0.0, (float)($t['employee_cash_left'] ?? 0))),
        ql_tsv_money($t['employee_reimbursement_due_total'] ?? max(0.0, 0 - (float)($t['employee_cash_left'] ?? 0))),
        ql_tsv_money($t['cash_balance'] ?? 0),
        ql_tsv_money($t['balance'] ?? 0),
    ]);

    $htmlRows[] = ql_sheet_row([
        [$title, 'background:#0f172a;color:#ffffff;font-weight:700;font-size:15px;'],
        [$groupName, 'background:#0f172a;color:#ffffff;font-weight:700;font-size:15px;'],
    ]);
    $htmlRows[] = ql_sheet_row([['Сформировано', 'background:#f8fafc;color:#64748b;'], [$generatedAt, 'background:#f8fafc;color:#64748b;']]);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Итог', $columns, '#dcfce7');
    $htmlRows[] = ql_sheet_row(['Приход', 'Расход', 'Наличные расходы', 'Карта / безнал', 'У администратора', 'У сотрудников net', 'Остатки сотрудников', 'К возмещению сотрудникам', 'Физическая касса', 'Учетный баланс'], 'th', $summaryHeaderStyle);
    $htmlRows[] = ql_sheet_row([
        [ql_tsv_money($t['income'] ?? 0), $incomeStyle],
        [ql_tsv_money($t['expense'] ?? 0), $expenseStyle],
        [ql_tsv_money($t['cash_expense'] ?? 0), $expenseStyle],
        [ql_tsv_money($t['noncash_expense'] ?? 0), $cardStyle],
        [ql_tsv_money($t['admin_cash_left'] ?? 0), $cashStyle],
        [ql_tsv_money($t['employee_cash_left'] ?? 0), $cashStyle],
        [ql_tsv_money($t['employee_positive_remaining_total'] ?? max(0.0, (float)($t['employee_cash_left'] ?? 0))), $cashStyle],
        [ql_tsv_money($t['employee_reimbursement_due_total'] ?? max(0.0, 0 - (float)($t['employee_cash_left'] ?? 0))), $expenseStyle],
        [ql_tsv_money($t['cash_balance'] ?? 0), $cashStyle],
        [ql_tsv_money($t['balance'] ?? 0), $moneyStyle],
    ]);

    $lines[] = '';
    $lines[] = ql_tsv_line(['Остатки на руках']);
    $lines[] = ql_tsv_line(['Хранитель', 'Тип', 'Отчетов', 'Остаток / net', 'К возмещению']);
    $lines[] = ql_tsv_line([$snapshot['admin_name'] ?? 'Администратор', 'Администратор', '', ql_tsv_money($t['admin_cash_left'] ?? 0), ql_tsv_money(0)]);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Остатки на руках', $columns, '#dbeafe');
    $htmlRows[] = ql_sheet_row(['Хранитель', 'Тип', 'Отчетов', 'Остаток / net', 'К возмещению'], 'th', $leftoverHeaderStyle);
    $htmlRows[] = ql_sheet_row([
        [$snapshot['admin_name'] ?? 'Администратор', $keeperStyle],
        'Администратор',
        '',
        [ql_tsv_money($t['admin_cash_left'] ?? 0), $cashStyle],
        [ql_tsv_money(0), $moneyStyle],
    ]);
    foreach (($snapshot['accountable_rows'] ?? []) as $row) {
        $lines[] = ql_tsv_line([$row['owner_display_name'] ?? 'Участник', 'Сотрудник', (int)($row['open_count'] ?? 0), ql_tsv_money($row['cash_left'] ?? 0), ql_tsv_money($row['reimbursement_due'] ?? 0)]);
        $htmlRows[] = ql_sheet_row([
            [$row['owner_display_name'] ?? 'Участник', $keeperStyle],
            'Сотрудник',
            [(int)($row['open_count'] ?? 0), $moneyStyle],
            [ql_tsv_money($row['cash_left'] ?? 0), ((float)($row['cash_left'] ?? 0) < -0.009 ? $expenseStyle : $cashStyle)],
            [ql_tsv_money($row['reimbursement_due'] ?? 0), $expenseStyle],
        ]);
    }

    $lines[] = '';
    $lines[] = ql_tsv_line(['Статьи']);
    $lines[] = ql_tsv_line(['Статья', 'Строк', 'Приход', 'Расход', 'Итог']);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Статьи', $columns, '#fef3c7');
    $htmlRows[] = ql_sheet_row(['Статья', 'Строк', 'Приход', 'Расход', 'Итог'], 'th', $articleHeaderStyle);
    foreach (($snapshot['article_rows'] ?? []) as $row) {
        $lines[] = ql_tsv_line([$row['name'] ?? '', (int)($row['records'] ?? 0), ql_tsv_money($row['income'] ?? 0), ql_tsv_money($row['expense'] ?? 0), ql_tsv_money($row['balance'] ?? 0)]);
        $htmlRows[] = ql_sheet_row([
            $row['name'] ?? '',
            [(int)($row['records'] ?? 0), $moneyStyle],
            [ql_tsv_money($row['income'] ?? 0), $incomeStyle],
            [ql_tsv_money($row['expense'] ?? 0), $expenseStyle],
            [ql_tsv_money($row['balance'] ?? 0), ((float)($row['balance'] ?? 0) < -0.009 ? $expenseStyle : $incomeStyle)],
        ]);
    }

    $lines[] = '';
    $lines[] = ql_tsv_line(['Участники']);
    $lines[] = ql_tsv_line(['Участник', 'Строк', 'Внес', 'Расход', 'Итог']);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Участники', $columns, '#ede9fe');
    $htmlRows[] = ql_sheet_row(['Участник', 'Строк', 'Внес', 'Расход', 'Итог'], 'th', $memberHeaderStyle);
    foreach (($snapshot['member_rows'] ?? []) as $row) {
        $total = (float)($row['income'] ?? 0) - (float)($row['expense'] ?? 0);
        $lines[] = ql_tsv_line([$row['name'] ?? '', (int)($row['records'] ?? 0), ql_tsv_money($row['income'] ?? 0), ql_tsv_money($row['expense'] ?? 0), ql_tsv_money($total)]);
        $htmlRows[] = ql_sheet_row([
            [$row['name'] ?? '', $keeperStyle],
            [(int)($row['records'] ?? 0), $moneyStyle],
            [ql_tsv_money($row['income'] ?? 0), $incomeStyle],
            [ql_tsv_money($row['expense'] ?? 0), $expenseStyle],
            [ql_tsv_money($total), ($total < -0.009 ? $expenseStyle : $incomeStyle)],
        ]);
    }

    $lines[] = '';
    $lines[] = ql_tsv_line(['Движение денег']);
    $lines[] = ql_tsv_line(['Дата', 'Кто', 'Источник', 'Статья', 'Тип', 'Деньги', 'Назначение', 'Сумма', 'Касса была', 'Движение', 'Касса стала', 'Баланс стал']);
    $htmlRows[] = ql_sheet_row(array_fill(0, $columns, ''));
    $htmlRows[] = ql_sheet_section_row('Движение денег', $columns, '#e0f2fe');
    $htmlRows[] = ql_sheet_row(['Дата', 'Кто', 'Источник', 'Статья', 'Тип', 'Деньги', 'Назначение', 'Сумма', 'Касса была', 'Движение', 'Касса стала', 'Баланс стал'], 'th', $headerStyle);
    foreach (($snapshot['prepared_rows'] ?? []) as $row) {
        $type = (string)($row['type'] ?? (($row['entry_type'] ?? '') === 'income' ? 'Приход' : 'Расход'));
        $money = (string)($row['money'] ?? (($row['money_type'] ?? '') === 'noncash' ? 'Карта / безнал' : 'Наличные'));
        $lines[] = ql_tsv_line([
            $row['date'] ?? '',
            $row['owner'] ?? '',
            $row['source'] ?? '',
            $row['section'] ?? '',
            $type,
            $money,
            $row['purpose'] ?? '',
            ql_tsv_money($row['amount'] ?? 0),
            ql_tsv_money($row['cash_before'] ?? 0),
            ql_tsv_money($row['cash_change'] ?? 0),
            ql_tsv_money($row['cash_after'] ?? 0),
            ql_tsv_money($row['balance_after'] ?? 0),
        ]);
        $isIncome = $type === 'Приход';
        $isCash = $money === 'Наличные';
        $htmlRows[] = ql_sheet_row([
            $row['date'] ?? '',
            [$row['owner'] ?? '', $keeperStyle],
            $row['source'] ?? '',
            $row['section'] ?? '',
            [$type, $isIncome ? $incomeStyle : $expenseStyle],
            [$money, $isCash ? $cashStyle : $cardStyle],
            $row['purpose'] ?? '',
            [ql_tsv_money($row['amount'] ?? 0), $isIncome ? $incomeStyle : $expenseStyle],
            [ql_tsv_money($row['cash_before'] ?? 0), $moneyStyle],
            [ql_tsv_money($row['cash_change'] ?? 0), ((float)($row['cash_change'] ?? 0) < -0.009 ? $expenseStyle : $incomeStyle)],
            [ql_tsv_money($row['cash_after'] ?? 0), $cashStyle],
            [ql_tsv_money($row['balance_after'] ?? 0), $moneyStyle],
        ]);
    }

    $colgroup = '<colgroup>'
        . '<col style="width:150px"><col style="width:170px"><col style="width:120px"><col style="width:150px">'
        . '<col style="width:90px"><col style="width:120px"><col style="width:260px"><col style="width:110px">'
        . '<col style="width:110px"><col style="width:110px"><col style="width:110px"><col style="width:110px">'
        . '</colgroup>';
    $html = '<table style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px;color:#111827;">'
        . $colgroup
        . implode('', $htmlRows)
        . '</table>';

    return [
        'ok' => true,
        'group_name' => $groupName,
        'rows' => count($snapshot['prepared_rows'] ?? []),
        'tsv' => implode("\n", $lines),
        'html' => $html,
    ];
}

function ql_ledger_group_final_report_details(array $row): array
{
    $details = json_decode((string)($row['details'] ?? ''), true);
    return is_array($details) ? $details : [];
}

function ql_ledger_group_final_report_snapshot(array $details): ?array
{
    $snapshot = $details['report_snapshot'] ?? null;
    if (!is_array($snapshot)) {
        return null;
    }
    if (!isset($snapshot['prepared_rows'], $snapshot['totals']) || !is_array($snapshot['prepared_rows']) || !is_array($snapshot['totals'])) {
        return null;
    }
    return $snapshot;
}

function ql_ledger_group_final_report_package_snapshot(array $details): ?array
{
    $package = $details['report_package'] ?? null;
    if (!is_array($package)) {
        return null;
    }
    if (($package['package_type'] ?? '') !== 'group_final_report') {
        return null;
    }
    if (!isset($package['report_id'], $package['group'], $package['summary']) || !is_array($package['group']) || !is_array($package['summary'])) {
        return null;
    }
    return $package;
}

function ql_ledger_group_final_report_public(array $row): array
{
    $details = ql_ledger_group_final_report_details($row);
    $snapshot = ql_ledger_group_final_report_snapshot($details);
    $package = ql_ledger_group_final_report_package_snapshot($details);
    if (is_array($snapshot)) {
        $snapshot = ql_ledger_group_apply_participant_control($snapshot, $package);
    }
    $totals = is_array($snapshot) ? ($snapshot['totals'] ?? []) : [];

    return [
        'id' => (int)($row['id'] ?? 0),
        'report_id' => (int)($row['id'] ?? 0),
        'group_id' => (int)($row['group_id'] ?? $row['entity_id'] ?? 0),
        'group_name' => (string)($snapshot['group_name'] ?? $details['group_name'] ?? ''),
        'finalized_at' => (string)($row['created_at'] ?? ''),
        'finalized_by_user_id' => (int)($row['user_id'] ?? 0),
        'finalized_by_display_name' => (string)($row['user_display_name'] ?? $row['email'] ?? ''),
        'finalized_by_email' => (string)($row['email'] ?? ''),
        'finalized_cards' => (int)($details['finalized_cards'] ?? 0),
        'card_ids' => is_array($details['card_ids'] ?? null) ? array_values($details['card_ids']) : [],
        'snapshot_available' => $snapshot !== null,
        'package_available' => $package !== null,
        'snapshot_version' => (int)($details['snapshot_version'] ?? 0),
        'package_version' => (int)($details['package_version'] ?? $package['package_version'] ?? 0),
        'totals' => [
            'income' => round((float)($totals['income'] ?? 0), 2),
            'expense' => round((float)($totals['expense'] ?? 0), 2),
            'cash_expense' => round((float)($totals['cash_expense'] ?? 0), 2),
            'noncash_expense' => round((float)($totals['noncash_expense'] ?? 0), 2),
            'admin_cash_left' => round((float)($totals['admin_cash_left'] ?? $details['carryover_admin_cash_left'] ?? 0), 2),
            'employee_cash_left' => round((float)($totals['employee_cash_left'] ?? $details['carryover_employee_cash_left'] ?? 0), 2),
            'employee_positive_remaining_total' => round((float)($totals['employee_positive_remaining_total'] ?? 0), 2),
            'employee_reimbursement_due_total' => round((float)($totals['employee_reimbursement_due_total'] ?? 0), 2),
            'employee_net_remaining_total' => round((float)($totals['employee_net_remaining_total'] ?? $totals['employee_cash_left'] ?? 0), 2),
            'cash_balance' => round((float)($totals['cash_balance'] ?? $details['carryover_cash_balance'] ?? 0), 2),
            'balance' => round((float)($totals['balance'] ?? $details['report_balance'] ?? 0), 2),
        ],
    ];
}

function ql_ledger_group_final_report_row(int $reportId): ?array
{
    if ($reportId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT
            al.id,
            al.user_id,
            al.entity_id AS group_id,
            al.details,
            al.created_at,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM audit_log al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE al.id = ?
          AND al.action = 'ledger_group_report_finalized'
          AND al.entity_type = 'group'
        LIMIT 1
    ");
    $stmt->execute([$reportId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function ql_ledger_group_final_report_for_user(int $reportId, int $userId): array
{
    $row = ql_ledger_group_final_report_row($reportId);
    if (!$row) {
        return ['ok' => false, 'error' => 'final_report_not_found'];
    }

    $groupId = (int)($row['group_id'] ?? 0);
    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_group_reports'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $details = ql_ledger_group_final_report_details($row);
    $report = ql_ledger_group_final_report_public($row);
    $snapshot = ql_ledger_group_final_report_snapshot($details);
    $package = ql_ledger_group_final_report_package_snapshot($details);
    if (!$snapshot) {
        return [
            'ok' => false,
            'error' => 'historical_snapshot_missing',
            'report' => $report,
        ];
    }
    $snapshot = ql_ledger_group_apply_participant_control($snapshot, $package);
    $report['totals'] = $snapshot['totals'] ?? ($report['totals'] ?? []);

    return [
        'ok' => true,
        'report' => $report,
        'snapshot' => $snapshot,
    ];
}

function ql_ledger_group_final_report_package_for_user(int $reportId, int $userId): array
{
    $row = ql_ledger_group_final_report_row($reportId);
    if (!$row) {
        return ['ok' => false, 'error' => 'final_report_not_found'];
    }

    $groupId = (int)($row['group_id'] ?? 0);
    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_group_reports'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $details = ql_ledger_group_final_report_details($row);
    $report = ql_ledger_group_final_report_public($row);
    $snapshot = ql_ledger_group_final_report_snapshot($details);
    $package = ql_ledger_group_final_report_package_snapshot($details);
    if (!$package) {
        return [
            'ok' => false,
            'error' => 'historical_package_missing',
            'report' => $report,
        ];
    }
    if ($snapshot) {
        $snapshot = ql_ledger_group_apply_participant_control($snapshot, $package);
        $package = ql_ledger_group_apply_package_participant_control($package, $snapshot);
        $report['totals'] = $snapshot['totals'] ?? ($report['totals'] ?? []);
    }

    return [
        'ok' => true,
        'report' => $report,
        'package' => $package,
    ];
}

function ql_ledger_group_final_report_list(array $input = []): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $limit = (int)($input['limit'] ?? 50);
    if ($limit < 1 || $limit > 100) {
        $limit = 50;
    }

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_group_reports'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $stmt = ql_db()->prepare("
        SELECT
            al.id,
            al.user_id,
            al.entity_id AS group_id,
            al.details,
            al.created_at,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM audit_log al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE al.action = 'ledger_group_report_finalized'
          AND al.entity_type = 'group'
          AND al.entity_id = ?
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$groupId]);

    return [
        'ok' => true,
        'scope' => [
            'mode' => 'group',
            'group_id' => $groupId,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'reports' => array_map('ql_ledger_group_final_report_public', $stmt->fetchAll()),
    ];
}

function ql_ledger_group_final_report_package(array $input = []): array
{
    $user = ql_require_user();
    $reportId = (int)($input['report_id'] ?? $input['id'] ?? 0);
    $result = ql_ledger_group_final_report_package_for_user($reportId, (int)$user['id']);
    if (empty($result['ok'])) {
        return $result;
    }

    return [
        'ok' => true,
        'package_type' => 'group_final_report',
        'report_id' => (int)$reportId,
        'report' => $result['report'],
        'package' => $result['package'],
    ];
}

function ql_ledger_group_final_report_detail(array $input = []): array
{
    $user = ql_require_user();
    $reportId = (int)($input['report_id'] ?? $input['id'] ?? 0);
    $result = ql_ledger_group_final_report_for_user($reportId, (int)$user['id']);
    if (empty($result['ok'])) {
        return $result;
    }

    return [
        'ok' => true,
        'report' => $result['report'],
        'snapshot' => $result['snapshot'],
    ];
}

function ql_ledger_group_final_report_google_sheet(array $input = []): array
{
    $user = ql_require_user();
    $reportId = (int)($input['report_id'] ?? $input['id'] ?? 0);
    $result = ql_ledger_group_final_report_for_user($reportId, (int)$user['id']);
    if (empty($result['ok'])) {
        return $result;
    }

    $report = $result['report'];
    $payload = ql_ledger_group_snapshot_sheet_payload(
        $result['snapshot'],
        (string)($report['finalized_at'] ?? ''),
        'FinDesk финальный отчет группы'
    );
    $payload['report'] = $report;
    return $payload;
}

function ql_ledger_group_final_report_excel_download(): void
{
    $user = ql_require_user();
    $reportId = (int)($_GET['report_id'] ?? $_GET['id'] ?? 0);
    $result = ql_ledger_group_final_report_for_user($reportId, (int)$user['id']);
    if (empty($result['ok'])) {
        $error = (string)($result['error'] ?? 'final_report_error');
        http_response_code($error === 'historical_snapshot_missing' ? 409 : ($error === 'access_denied' ? 403 : 404));
        echo $error;
        return;
    }

    $report = $result['report'];
    $snapshot = $result['snapshot'];
    $payload = ql_ledger_group_snapshot_sheet_payload(
        $snapshot,
        (string)($report['finalized_at'] ?? ''),
        'FinDesk финальный отчет группы'
    );
    $groupName = (string)($snapshot['group_name'] ?? 'group');
    $fileBase = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $groupName);
    $fileName = 'findesk-final-report-' . ($fileBase ?: ('group-' . (int)($report['group_id'] ?? 0))) . '-' . (int)$reportId . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo "\xEF\xBB\xBF";
    echo '<!doctype html><html><head><meta charset="utf-8"></head><body>';
    echo $payload['html'];
    echo '</body></html>';
}

function ql_ledger_group_final_report_copy_package_proof(int $reportId, string $sourcePath, string $originalName, string $mime, int $sizeBytes, string $proofId): array
{
    $root = dirname(__DIR__);
    $documentsRoot = realpath($root . '/storage/documents');
    $source = realpath($root . '/' . ltrim($sourcePath, '/'));
    if (!$documentsRoot || !$source || strpos($source, $documentsRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($source)) {
        return [
            'storage_status' => 'source_missing',
            'storage_path' => '',
            'stored_name' => '',
            'size_bytes' => $sizeBytes,
        ];
    }

    $year = date('Y');
    $dir = $root . '/storage/documents/group-final-reports/' . $year . '/report-' . $reportId;
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return [
            'storage_status' => 'copy_failed',
            'storage_path' => '',
            'stored_name' => '',
            'size_bytes' => $sizeBytes,
        ];
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = 'bin';
    }
    $safeProof = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $proofId) ?: ('proof-' . bin2hex(random_bytes(4)));
    $stored = $safeProof . '.' . $ext;
    $target = $dir . '/' . $stored;
    if (is_file($target)) {
        $stored = $safeProof . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . '/' . $stored;
    }

    if (!copy($source, $target)) {
        return [
            'storage_status' => 'copy_failed',
            'storage_path' => '',
            'stored_name' => '',
            'size_bytes' => $sizeBytes,
        ];
    }
    @chmod($target, 0640);
    $copiedSize = filesize($target);

    return [
        'storage_status' => 'copied',
        'storage_path' => 'storage/documents/group-final-reports/' . $year . '/report-' . $reportId . '/' . $stored,
        'stored_name' => $stored,
        'size_bytes' => $copiedSize !== false ? (int)$copiedSize : $sizeBytes,
    ];
}

function ql_ledger_group_final_report_package_add_proof(array &$proofs, array &$proofIdsBySource, int $reportId, string $sourceType, int $sourceId, array $file): string
{
    $proofId = 'proof-' . $reportId . '-' . $sourceType . '-' . (int)($file['id'] ?? 0);
    $original = (string)($file['original_name'] ?? $file['file_original_name'] ?? 'attachment');
    $mime = (string)($file['mime_type'] ?? $file['file_mime'] ?? 'application/octet-stream');
    $size = (int)($file['size_bytes'] ?? $file['file_size'] ?? 0);
    $sourcePath = (string)($file['storage_path'] ?? '');
    if ($sourcePath === '' && isset($file['file_path'])) {
        $sourcePath = 'storage/' . ltrim((string)$file['file_path'], '/');
    }

    $copied = ql_ledger_group_final_report_copy_package_proof($reportId, $sourcePath, $original, $mime, $size, $proofId);
	    $proof = [
	        'id' => $proofId,
	        'proof_id' => $proofId,
	        'source_type' => $sourceType,
	        'source_id' => $sourceId,
	        'source_file_id' => (int)($file['id'] ?? 0),
	        'proof_role' => (string)($file['proof_role'] ?? 'attachment'),
	        'proof_bundle_id' => (string)($file['proof_bundle_id'] ?? ''),
	        'derived_from_file_id' => isset($file['source_file_id']) ? (int)$file['source_file_id'] : null,
	        'file_hash_sha256' => (string)($file['file_hash_sha256'] ?? ''),
	        'metadata' => json_decode((string)($file['metadata_json'] ?? '{}'), true) ?: [],
	        'original_name' => $original,
	        'mime_type' => $mime,
        'size_bytes' => (int)($copied['size_bytes'] ?? $size),
        'storage_status' => (string)($copied['storage_status'] ?? 'source_missing'),
        'storage_path' => (string)($copied['storage_path'] ?? ''),
        'stored_name' => (string)($copied['stored_name'] ?? ''),
        'created_at' => (string)($file['created_at'] ?? ''),
    ];
    if ($proof['storage_status'] === 'copied') {
        $proof['download_url'] = '/api.php?action=ledger_group_final_report_proof_download&report_id=' . $reportId . '&proof_id=' . rawurlencode($proofId);
    }
    $proofs[] = $proof;
    $key = $sourceType . ':' . $sourceId;
    if (!isset($proofIdsBySource[$key])) {
        $proofIdsBySource[$key] = [];
    }
    $proofIdsBySource[$key][] = $proofId;

    return $proofId;
}

function ql_ledger_group_final_report_package_money_row(int $reportId, int $index, array $row, array $proofIdsBySource): array
{
    $sourceType = (string)($row['source_type'] ?? '');
    $sourceId = (string)($row['source_id'] ?? '');
    $proofKey = '';
    if ($sourceType === 'ledger_entry' && (int)($row['ledger_entry_id'] ?? 0) > 0) {
        $proofKey = 'ledger_entry:' . (int)$row['ledger_entry_id'];
    } elseif ($sourceType === 'on_the_go_capture' && (int)($row['capture_id'] ?? 0) > 0) {
        $proofKey = 'on_the_go_capture:' . (int)$row['capture_id'];
    }
    $proofIds = $proofKey !== '' ? ($proofIdsBySource[$proofKey] ?? []) : [];
    $entryType = (string)($row['entry_type'] ?? (($row['type'] ?? '') === 'Приход' ? 'income' : 'expense'));
    $moneyType = (string)($row['money_type'] ?? (($row['money'] ?? '') === 'Наличные' ? 'cash' : 'noncash'));
    $amount = round((float)($row['amount'] ?? 0), 2);

    return [
        'id' => 'money-row-' . $reportId . '-' . ($index + 1),
        'row_index' => $index + 1,
        'source_type' => $sourceType !== '' ? $sourceType : 'snapshot_row',
        'source_id' => $sourceId,
        'ledger_entry_id' => isset($row['ledger_entry_id']) ? (int)$row['ledger_entry_id'] : null,
        'capture_id' => isset($row['capture_id']) ? (int)$row['capture_id'] : null,
        'tape_id' => isset($row['tape_id']) ? (int)$row['tape_id'] : null,
        'participant_user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
        'date' => (string)($row['date'] ?? ''),
        'participant' => [
            'name' => (string)($row['owner'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
        ],
        'section' => (string)($row['section'] ?? ''),
        'entry_type' => $entryType,
        'money_type' => $moneyType,
        'amount' => $amount,
        'cash_effect' => round((float)($row['cash_change'] ?? 0), 2),
        'card_effect' => $moneyType === 'noncash' && $entryType === 'expense' ? round(0 - $amount, 2) : 0.0,
        'accountable_effect' => str_starts_with((string)($row['note'] ?? ''), 'From advance #') ? round(0 - $amount, 2) : 0.0,
        'cash_before' => round((float)($row['cash_before'] ?? 0), 2),
        'cash_after' => round((float)($row['cash_after'] ?? 0), 2),
        'balance_after' => round((float)($row['balance_after'] ?? 0), 2),
        'purpose' => (string)($row['purpose'] ?? ''),
        'note' => (string)($row['note'] ?? ''),
        'proof_ids' => $proofIds,
        'proof_status' => $proofIds ? 'proof_available' : (($sourceType === 'carryover') ? 'finalization_reference' : 'needs_proof'),
    ];
}

function ql_ledger_group_final_report_accountable_snapshot(int $groupId, string $finalizedAt, int $reportId): array
{
    $stmt = ql_db()->prepare("
        SELECT
            ca.*,
            assigned.email AS assigned_to_email,
            COALESCE(assigned_gm.display_name, assigned.display_name, assigned.email) AS assigned_to_display_name,
            issued.email AS issued_by_email,
            COALESCE(issued_gm.display_name, issued.display_name, issued.email) AS issued_by_display_name
        FROM cash_advances ca
        JOIN users assigned ON assigned.id = ca.assigned_to_user_id
        JOIN users issued ON issued.id = ca.issued_by_user_id
        LEFT JOIN group_members assigned_gm ON assigned_gm.group_id = ca.group_id AND assigned_gm.user_id = ca.assigned_to_user_id
        LEFT JOIN group_members issued_gm ON issued_gm.group_id = ca.group_id AND issued_gm.user_id = ca.issued_by_user_id
        WHERE ca.group_id = ?
        ORDER BY ca.created_at ASC, ca.id ASC
        LIMIT 300
    ");
    $stmt->execute([$groupId]);

    $returns = [];
    $auditStmt = ql_db()->prepare("
        SELECT id, user_id, entity_id, details, created_at
        FROM audit_log
        WHERE action = 'advance_cash_returned'
          AND created_at <= ?
        ORDER BY created_at ASC, id ASC
        LIMIT 500
    ");
    $auditStmt->execute([$finalizedAt ?: date('Y-m-d H:i:s')]);
    foreach ($auditStmt->fetchAll() as $row) {
        $details = json_decode((string)($row['details'] ?? ''), true);
        if (!is_array($details) || (int)($details['group_id'] ?? 0) !== $groupId) {
            continue;
        }
        $returns[(int)$row['entity_id']] = [
            'audit_id' => (int)$row['id'],
            'amount' => round((float)($details['amount_returned'] ?? 0), 2),
            'returned_at' => (string)$row['created_at'],
            'returned_by_user_id' => (int)($row['user_id'] ?? 0),
            'note' => (string)($details['note'] ?? ''),
        ];
    }

    $items = [];
    $byParticipant = [];
    $totals = [
        'issued' => 0.0,
        'accepted_spent' => 0.0,
        'accepted_cash_spent' => 0.0,
        'accepted_card_spent' => 0.0,
        'returned_cash' => 0.0,
        'open_remaining_cash' => 0.0,
        'discrepancy' => 0.0,
    ];

    foreach ($stmt->fetchAll() as $advance) {
        $advanceId = (int)($advance['id'] ?? 0);
        $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
        $summary = $tapeId > 0 && function_exists('ql_advance_tape_summary')
            ? ql_advance_tape_summary($tapeId)
            : [];
        $status = (string)($advance['status'] ?? '');
        $assignedUserId = (int)($advance['assigned_to_user_id'] ?? 0);
        $actualRemaining = $advance['actual_remaining'] !== null ? round((float)$advance['actual_remaining'], 2) : null;
        $cashLeft = $actualRemaining !== null ? $actualRemaining : round((float)($summary['cash_left'] ?? $advance['amount'] ?? 0), 2);
        $acceptedCash = $status === 'accepted' ? round((float)($summary['cash_out'] ?? 0), 2) : 0.0;
        $acceptedCard = $status === 'accepted' ? round((float)($summary['card_out'] ?? 0), 2) : 0.0;
        $returned = $returns[$advanceId]['amount'] ?? 0.0;
        $openRemaining = in_array($status, ['issued', 'submitted', 'returned', 'discrepancy'], true) ? $cashLeft : 0.0;
        $discrepancy = $status === 'discrepancy' ? round((float)($advance['difference_amount'] ?? 0), 2) : 0.0;
        $expectedRemaining = $advance['expected_remaining'] !== null ? round((float)$advance['expected_remaining'], 2) : null;
        $differenceAmount = $advance['difference_amount'] !== null ? round((float)$advance['difference_amount'], 2) : null;
        $acceptedOverrun = 0.0;
        if ($status === 'accepted') {
            if ($expectedRemaining !== null && $expectedRemaining < -0.009) {
                $acceptedOverrun = abs($expectedRemaining);
            } else {
                $acceptedOverrun = max(0.0, round($acceptedCash - (float)($advance['amount'] ?? 0), 2));
            }
        }
        $participantBalance = $openRemaining > 0.009
            ? $openRemaining
            : ($acceptedOverrun > 0.009 ? round(0 - $acceptedOverrun, 2) : 0.0);

        $item = [
            'id' => 'accountable-' . $reportId . '-' . $advanceId,
            'advance_id' => $advanceId,
            'tape_id' => $tapeId,
            'status' => $status,
            'title' => (string)($advance['title'] ?? ''),
            'currency' => (string)($advance['currency'] ?? 'EUR'),
            'issued_amount' => round((float)($advance['amount'] ?? 0), 2),
            'issued_at' => (string)($advance['created_at'] ?? ''),
            'issued_by' => [
                'user_id' => (int)($advance['issued_by_user_id'] ?? 0),
                'name' => (string)($advance['issued_by_display_name'] ?? $advance['issued_by_email'] ?? ''),
                'email' => (string)($advance['issued_by_email'] ?? ''),
            ],
            'participant' => [
                'user_id' => $assignedUserId,
                'name' => (string)($advance['assigned_to_display_name'] ?? $advance['assigned_to_email'] ?? ''),
                'email' => (string)($advance['assigned_to_email'] ?? ''),
            ],
            'submitted_at' => (string)($advance['submitted_at'] ?? ''),
            'accepted_at' => (string)($advance['accepted_at'] ?? ''),
            'returned_at' => (string)($advance['returned_at'] ?? ($returns[$advanceId]['returned_at'] ?? '')),
            'summary' => [
                'accepted_cash_spent' => $acceptedCash,
                'accepted_card_spent' => $acceptedCard,
                'accepted_spent' => round($acceptedCash + $acceptedCard, 2),
                'returned_cash' => round((float)$returned, 2),
                'open_remaining_cash' => $openRemaining,
                'discrepancy' => $discrepancy,
                'expected_remaining' => $expectedRemaining,
                'actual_remaining' => $actualRemaining,
                'difference_amount' => $differenceAmount,
                'positive_remaining_cash' => max(0.0, $participantBalance),
                'reimbursement_due' => $acceptedOverrun,
                'participant_control_balance' => $participantBalance,
                'carryover_responsibility' => $openRemaining,
            ],
        ];
        if (isset($returns[$advanceId])) {
            $item['return_audit_ref'] = $returns[$advanceId];
        }
        $items[] = $item;

        if (!isset($byParticipant[$assignedUserId])) {
            $byParticipant[$assignedUserId] = [
                'participant' => $item['participant'],
                'issued' => 0.0,
                'accepted_spent' => 0.0,
                'accepted_cash_spent' => 0.0,
                'accepted_card_spent' => 0.0,
                'returned_cash' => 0.0,
                'open_remaining_cash' => 0.0,
                'discrepancy' => 0.0,
                'positive_remaining_cash' => 0.0,
                'reimbursement_due' => 0.0,
                'participant_control_balance' => 0.0,
                'advance_ids' => [],
            ];
        }
        $issuedInPackage = $item['summary']['accepted_spent'] > 0 || $openRemaining > 0 || (float)$returned > 0 || abs($discrepancy) > 0.009
            ? (float)$item['issued_amount']
            : 0.0;
        $byParticipant[$assignedUserId]['issued'] += $issuedInPackage;
        $byParticipant[$assignedUserId]['accepted_spent'] += $item['summary']['accepted_spent'];
        $byParticipant[$assignedUserId]['accepted_cash_spent'] += $acceptedCash;
        $byParticipant[$assignedUserId]['accepted_card_spent'] += $acceptedCard;
        $byParticipant[$assignedUserId]['returned_cash'] += (float)$returned;
        $byParticipant[$assignedUserId]['open_remaining_cash'] += $openRemaining;
        $byParticipant[$assignedUserId]['discrepancy'] += $discrepancy;
        $byParticipant[$assignedUserId]['positive_remaining_cash'] += max(0.0, $participantBalance);
        $byParticipant[$assignedUserId]['reimbursement_due'] += $acceptedOverrun;
        $byParticipant[$assignedUserId]['participant_control_balance'] += $participantBalance;
        $byParticipant[$assignedUserId]['advance_ids'][] = $advanceId;

        $totals['issued'] += $issuedInPackage;
        $totals['accepted_spent'] += $item['summary']['accepted_spent'];
        $totals['accepted_cash_spent'] += $acceptedCash;
        $totals['accepted_card_spent'] += $acceptedCard;
        $totals['returned_cash'] += (float)$returned;
        $totals['open_remaining_cash'] += $openRemaining;
        $totals['discrepancy'] += $discrepancy;
        $totals['positive_remaining_cash'] = ($totals['positive_remaining_cash'] ?? 0.0) + max(0.0, $participantBalance);
        $totals['reimbursement_due'] = ($totals['reimbursement_due'] ?? 0.0) + $acceptedOverrun;
        $totals['net_remaining_cash'] = ($totals['net_remaining_cash'] ?? 0.0) + $participantBalance;
    }

    foreach ($byParticipant as &$row) {
        foreach (['issued','accepted_spent','accepted_cash_spent','accepted_card_spent','returned_cash','open_remaining_cash','discrepancy','positive_remaining_cash','reimbursement_due','participant_control_balance'] as $key) {
            $row[$key] = round((float)$row[$key], 2);
        }
    }
    unset($row);
    foreach ($totals as $key => $value) {
        $totals[$key] = round((float)$value, 2);
    }

    return [
        'items' => $items,
        'by_participant' => array_values($byParticipant),
        'totals' => $totals,
    ];
}

function ql_ledger_group_final_report_audit_refs(int $groupId, array $cardIds, string $finalizedAt, int $reportId): array
{
    $actions = [
        'ledger_group_report_finalized',
        'ledger_group_report_finalized_card',
        'on_the_go_card_submitted',
        'on_the_go_card_included',
        'on_the_go_card_unincluded',
        'on_the_go_card_return_requested',
        'on_the_go_card_completed_archived',
        'advance_issued',
        'advance_submitted',
        'advance_accepted',
        'advance_returned',
        'advance_unaccepted',
        'advance_cash_returned',
    ];
    $placeholders = implode(',', array_fill(0, count($actions), '?'));
    $params = $actions;
    $params[] = $finalizedAt ?: date('Y-m-d H:i:s');

    $stmt = ql_db()->prepare("
        SELECT id, user_id, action, entity_type, entity_id, details, created_at
        FROM audit_log
        WHERE action IN ({$placeholders})
          AND created_at <= ?
        ORDER BY created_at ASC, id ASC
        LIMIT 700
    ");
    $stmt->execute($params);

    $cardMap = array_fill_keys(array_map('intval', $cardIds), true);
    $refs = [];
    foreach ($stmt->fetchAll() as $row) {
        $details = json_decode((string)($row['details'] ?? ''), true);
        if (!is_array($details)) {
            $details = [];
        }
        $entityType = (string)($row['entity_type'] ?? '');
        $entityId = (int)($row['entity_id'] ?? 0);
        $action = (string)($row['action'] ?? '');
        $include = false;
        if ($action === 'ledger_group_report_finalized' && $entityType === 'group' && $entityId === $groupId && (int)$row['id'] === $reportId) {
            $include = true;
        } elseif ($entityType === 'on_the_go_tape' && isset($cardMap[$entityId])) {
            $include = true;
        } elseif ((int)($details['group_id'] ?? 0) === $groupId && str_starts_with($action, 'advance_')) {
            $include = true;
        }
        if (!$include) {
            continue;
        }
        $refDetails = $details;
        if ($action === 'ledger_group_report_finalized') {
            unset($refDetails['report_snapshot'], $refDetails['report_package']);
        }
        $refs[] = [
            'audit_id' => (int)$row['id'],
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => (int)($row['user_id'] ?? 0),
            'created_at' => (string)$row['created_at'],
            'details' => $refDetails,
        ];
    }

    return $refs;
}

function ql_ledger_group_final_report_messages_snapshot(int $groupId, array $auditRefs, string $finalizedAt): array
{
    $reportContext = [];
    foreach ($auditRefs as $ref) {
        $action = (string)($ref['action'] ?? '');
        $details = is_array($ref['details'] ?? null) ? $ref['details'] : [];
        if ($action === 'on_the_go_card_return_requested') {
            $reportContext[] = [
                'id' => 'audit-message-' . (int)$ref['audit_id'],
                'source_type' => 'audit_log',
                'audit_id' => (int)$ref['audit_id'],
                'message_type' => 'return_for_clarification',
                'entity_type' => (string)($ref['entity_type'] ?? ''),
                'entity_id' => (int)($ref['entity_id'] ?? 0),
                'text' => (string)($details['reason'] ?? ''),
                'created_at' => (string)($ref['created_at'] ?? ''),
                'user_id' => (int)($ref['user_id'] ?? 0),
            ];
        } elseif (in_array($action, ['on_the_go_card_included', 'ledger_group_report_finalized_card', 'advance_accepted', 'advance_returned', 'advance_cash_returned'], true)) {
            $reportContext[] = [
                'id' => 'audit-message-' . (int)$ref['audit_id'],
                'source_type' => 'audit_log',
                'audit_id' => (int)$ref['audit_id'],
                'message_type' => 'review_event',
                'event' => $action,
                'entity_type' => (string)($ref['entity_type'] ?? ''),
                'entity_id' => (int)($ref['entity_id'] ?? 0),
                'text' => (string)($details['note'] ?? $details['reason'] ?? ''),
                'created_at' => (string)($ref['created_at'] ?? ''),
                'user_id' => (int)($ref['user_id'] ?? 0),
            ];
        }
    }

    $groupMessages = [];
    $stmt = ql_db()->prepare("
        SELECT
            gm.id,
            gm.sender_user_id,
            gm.message_text,
            gm.message_type,
            gm.created_at,
            u.email AS sender_email,
            COALESCE(mem.display_name, u.display_name, u.email) AS sender_name
        FROM group_messages gm
        JOIN users u ON u.id = gm.sender_user_id
        LEFT JOIN group_members mem ON mem.group_id = gm.group_id AND mem.user_id = gm.sender_user_id
        WHERE gm.group_id = ?
          AND gm.deleted_at IS NULL
          AND gm.created_at <= ?
        ORDER BY gm.created_at DESC, gm.id DESC
        LIMIT 50
    ");
    $stmt->execute([$groupId, $finalizedAt ?: date('Y-m-d H:i:s')]);
    foreach (array_reverse($stmt->fetchAll()) as $row) {
        $groupMessages[] = [
            'id' => (int)$row['id'],
            'source_type' => 'group_message',
            'context' => 'general_group_discussion_unlinked',
            'sender_user_id' => (int)$row['sender_user_id'],
            'sender_name' => (string)($row['sender_name'] ?? ''),
            'sender_email' => (string)($row['sender_email'] ?? ''),
            'message_type' => (string)($row['message_type'] ?? 'text'),
            'text' => (string)($row['message_text'] ?? ''),
            'created_at' => (string)$row['created_at'],
        ];
    }

    return [
        'report_context' => $reportContext,
        'general_group_refs' => $groupMessages,
        'schema_note' => 'group_messages has group scope only; immutable report-context messages are audit-derived until message rows get report_id/tape_id/capture_id/advance_id links.',
    ];
}

function ql_ledger_group_final_report_build_package(int $reportId, int $groupId, array $snapshot, array $cards, array $user, string $finalizedAt, string $snapshotCreatedAt): array
{
    $cardIds = array_values(array_map(static fn($row) => (int)($row['id'] ?? 0), $cards));
    $cardIds = array_values(array_filter($cardIds, static fn($id) => $id > 0));
    $proofs = [];
    $proofIdsBySource = [];
    $participants = [];
    $captures = [];

    $cardRows = [];
    if ($cardIds) {
        $placeholders = implode(',', array_fill(0, count($cardIds), '?'));
        $stmt = ql_db()->prepare("
            SELECT
                t.*,
                u.email,
                COALESCE(gm.display_name, u.display_name, u.email) AS user_display_name
            FROM on_the_go_tapes t
            JOIN users u ON u.id = t.user_id
            LEFT JOIN group_members gm ON gm.group_id = t.group_id AND gm.user_id = t.user_id
            WHERE t.id IN ({$placeholders})
            ORDER BY t.submitted_at ASC, t.id ASC
        ");
        $stmt->execute($cardIds);
        foreach ($stmt->fetchAll() as $row) {
            $cardRows[(int)$row['id']] = $row;
        }

        $fileStmt = ql_db()->prepare("
            SELECT f.*, c.tape_id
            FROM on_the_go_files f
            JOIN on_the_go_captures c ON c.id = f.capture_id
            WHERE c.tape_id IN ({$placeholders})
            ORDER BY f.capture_id ASC, f.id ASC
        ");
        $fileStmt->execute($cardIds);
        foreach ($fileStmt->fetchAll() as $file) {
            ql_ledger_group_final_report_package_add_proof(
                $proofs,
                $proofIdsBySource,
                $reportId,
                'on_the_go_capture',
                (int)($file['capture_id'] ?? 0),
                $file
            );
        }

        $captureStmt = ql_db()->prepare("
            SELECT *
            FROM on_the_go_captures
            WHERE tape_id IN ({$placeholders})
              AND review_status <> 'archived'
              AND capture_type IN ('cash_in', 'cash_out', 'noncash_out')
            ORDER BY tape_id ASC, created_at ASC, id ASC
        ");
        $captureStmt->execute($cardIds);
        $capturesByTape = [];
        foreach ($captureStmt->fetchAll() as $capture) {
            $tapeId = (int)($capture['tape_id'] ?? 0);
            if (!isset($capturesByTape[$tapeId])) {
                $capturesByTape[$tapeId] = [];
            }
            $capturesByTape[$tapeId][] = $capture;
        }

        foreach ($cardRows as $tapeId => $card) {
            $participantReportId = 'participant-report-' . $reportId . '-' . $tapeId;
            $summary = function_exists('ql_on_the_go_card_summary') ? ql_on_the_go_card_summary($tapeId) : [];
            $captureIds = [];
            $participantProofIds = [];
            $missingProof = 0;
            foreach ($capturesByTape[$tapeId] ?? [] as $capture) {
                $captureId = (int)$capture['id'];
                $proofIds = $proofIdsBySource['on_the_go_capture:' . $captureId] ?? [];
                if (!$proofIds) {
                    $missingProof++;
                }
                $participantProofIds = array_values(array_unique(array_merge($participantProofIds, $proofIds)));
                $captureIds[] = $captureId;
                $captureType = (string)($capture['capture_type'] ?? '');
                $amount = round((float)($capture['amount'] ?? 0), 2);
                $moneyType = $captureType === 'noncash_out' ? 'noncash' : 'cash';
                $entryType = $captureType === 'cash_in' ? 'income' : 'expense';
                $captures[] = [
                    'id' => 'capture-' . $reportId . '-' . $captureId,
                    'capture_id' => $captureId,
                    'participant_report_id' => $participantReportId,
                    'tape_id' => $tapeId,
                    'participant_user_id' => (int)($capture['user_id'] ?? $card['user_id'] ?? 0),
                    'capture_type' => $captureType,
                    'entry_type' => $entryType,
                    'money_type' => $moneyType,
                    'amount' => $amount,
                    'currency' => (string)($capture['currency'] ?? $card['currency'] ?? 'EUR'),
                    'cash_effect' => $captureType === 'cash_in' ? $amount : ($captureType === 'cash_out' ? round(0 - $amount, 2) : 0.0),
                    'card_effect' => $captureType === 'noncash_out' ? round(0 - $amount, 2) : 0.0,
                    'accountable_effect' => 0.0,
                    'description' => (string)($capture['description'] ?? ''),
                    'review_status' => (string)($capture['review_status'] ?? ''),
                    'reportable' => (int)($capture['reportable'] ?? 0),
                    'created_at' => (string)($capture['created_at'] ?? ''),
                    'updated_at' => (string)($capture['updated_at'] ?? ''),
                    'proof_ids' => $proofIds,
                    'proof_status' => $proofIds ? 'proof_available' : 'needs_proof',
                ];
            }

            $participants[] = [
                'id' => $participantReportId,
                'participant_report_id' => $participantReportId,
                'source_type' => 'on_the_go_card',
                'source_card_id' => $tapeId,
                'status' => 'accepted_in_group_final_report',
                'participant' => [
                    'user_id' => (int)($card['user_id'] ?? 0),
                    'name' => (string)($card['user_display_name'] ?? $card['email'] ?? ''),
                    'email' => (string)($card['email'] ?? ''),
                ],
                'reviewer' => [
                    'user_id' => (int)($user['id'] ?? 0),
                    'name' => (string)($user['display_name'] ?? $user['email'] ?? ''),
                    'email' => (string)($user['email'] ?? ''),
                ],
                'stream_type' => ql_on_the_go_stream_type($card['stream_type'] ?? 'cash'),
                'title' => (string)($card['title'] ?? ''),
                'submitted_at' => (string)($card['submitted_at'] ?? ''),
                'accepted_at' => $finalizedAt,
                'finalized_at' => $finalizedAt,
                'archived_at' => (string)($card['archived_at'] ?? ''),
                'summary' => [
                    'cash_received' => round((float)($card['cash_received'] ?? 0), 2),
                    'cash_in' => round((float)($summary['cash_in'] ?? 0), 2),
                    'cash_out' => round((float)($summary['cash_out'] ?? 0), 2),
                    'card_out' => round((float)($summary['card_out'] ?? 0), 2),
                    'spent_total' => round((float)($summary['spent_total'] ?? 0), 2),
                    'cash_left' => round((float)($summary['cash_left'] ?? 0), 2),
                    'cash_delta' => round((float)($summary['cash_delta'] ?? 0), 2),
                    'card_delta' => round((float)($summary['card_delta'] ?? 0), 2),
                    'returned_cash' => 0.0,
                    'remaining_accountable_cash' => 0.0,
                    'discrepancy' => round((float)($card['difference_amount'] ?? 0), 2),
                    'records_count' => (int)($summary['records_count'] ?? 0),
                    'proof_count' => count($participantProofIds),
                    'missing_proof_count' => $missingProof,
                ],
                'capture_ids' => $captureIds,
                'proof_ids' => $participantProofIds,
                'proof_status' => $missingProof > 0 ? 'needs_proof' : 'proof_available',
            ];
        }
    }

    $ledgerEntryIds = [];
    foreach (($snapshot['prepared_rows'] ?? []) as $row) {
        $entryId = (int)($row['ledger_entry_id'] ?? 0);
        if ($entryId > 0) {
            $ledgerEntryIds[$entryId] = true;
        }
    }
    if ($ledgerEntryIds) {
        $ids = array_keys($ledgerEntryIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = ql_db()->prepare("
            SELECT *
            FROM entry_files
            WHERE entry_id IN ({$placeholders})
              AND deleted_at IS NULL
            ORDER BY entry_id ASC, id ASC
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $file) {
            ql_ledger_group_final_report_package_add_proof(
                $proofs,
                $proofIdsBySource,
                $reportId,
                'ledger_entry',
                (int)($file['entry_id'] ?? 0),
                $file
            );
        }
    }

    $moneyRows = [];
    foreach (($snapshot['prepared_rows'] ?? []) as $index => $row) {
        $moneyRows[] = ql_ledger_group_final_report_package_money_row($reportId, (int)$index, $row, $proofIdsBySource);
    }

    $accountable = ql_ledger_group_final_report_accountable_snapshot($groupId, $finalizedAt, $reportId);
    $auditRefs = ql_ledger_group_final_report_audit_refs($groupId, $cardIds, $finalizedAt, $reportId);
    $messages = ql_ledger_group_final_report_messages_snapshot($groupId, $auditRefs, $finalizedAt);
    $totals = $snapshot['totals'] ?? [];

    return [
        'package_type' => 'group_final_report',
        'package_version' => 1,
        'report_id' => $reportId,
        'created_at' => $snapshotCreatedAt,
        'immutability' => [
            'source' => 'audit_log.details.report_package',
            'mutable_reconstruction' => false,
        ],
        'group' => [
            'id' => $groupId,
            'name' => (string)($snapshot['group_name'] ?? ('group-' . $groupId)),
        ],
        'finalization' => [
            'report_id' => $reportId,
            'status' => 'closed',
            'finalized_at' => $finalizedAt,
            'snapshot_created_at' => $snapshotCreatedAt,
            'finalized_by_user_id' => (int)($user['id'] ?? 0),
            'finalized_by_display_name' => (string)($user['display_name'] ?? $user['email'] ?? ''),
            'finalized_by_email' => (string)($user['email'] ?? ''),
        ],
        'summary' => [
            'received_money' => round((float)($totals['income'] ?? 0), 2),
            'physical_cash_spent' => round((float)($totals['cash_expense'] ?? 0), 2),
            'card_noncash_spent' => round((float)($totals['noncash_expense'] ?? 0), 2),
            'admin_cash_left' => round((float)($totals['admin_cash_left'] ?? 0), 2),
            'accountable_money_left' => round((float)($totals['employee_cash_left'] ?? 0), 2),
            'employee_positive_remaining_total' => round((float)($totals['employee_positive_remaining_total'] ?? 0), 2),
            'employee_reimbursement_due_total' => round((float)($totals['employee_reimbursement_due_total'] ?? 0), 2),
            'employee_net_remaining_total' => round((float)($totals['employee_net_remaining_total'] ?? $totals['employee_cash_left'] ?? 0), 2),
            'returned_cash' => round((float)($accountable['totals']['returned_cash'] ?? 0), 2),
            'discrepancy' => round((float)($accountable['totals']['discrepancy'] ?? 0), 2),
            'carryover' => [
                'admin_cash_left' => round((float)($totals['admin_cash_left'] ?? 0), 2),
                'employee_cash_left' => round((float)($totals['employee_cash_left'] ?? 0), 2),
                'employee_positive_remaining_total' => round((float)($totals['employee_positive_remaining_total'] ?? 0), 2),
                'employee_reimbursement_due_total' => round((float)($totals['employee_reimbursement_due_total'] ?? 0), 2),
                'employee_net_remaining_total' => round((float)($totals['employee_net_remaining_total'] ?? $totals['employee_cash_left'] ?? 0), 2),
                'cash_balance' => round((float)($totals['cash_balance'] ?? 0), 2),
                'balance' => round((float)($totals['balance'] ?? 0), 2),
            ],
            'cash_balance' => round((float)($totals['cash_balance'] ?? 0), 2),
            'balance' => round((float)($totals['balance'] ?? 0), 2),
        ],
        'participants' => $participants,
        'captures' => $captures,
        'money_rows' => $moneyRows,
        'proofs' => $proofs,
        'participant_control' => $snapshot['participant_control'] ?? [],
        'accountable' => $accountable,
        'messages' => $messages,
        'audit_refs' => $auditRefs,
        'exports' => [
            'package_action' => 'ledger_group_final_report_package',
            'final_report_google_sheet_action' => 'ledger_group_final_report_google_sheet',
            'final_report_excel_action' => 'ledger_group_final_report_excel',
            'report_id' => $reportId,
        ],
    ];
}

function ql_ledger_group_final_report_proof_download(): void
{
    $user = ql_require_user();
    $reportId = (int)($_GET['report_id'] ?? 0);
    $proofId = (string)($_GET['proof_id'] ?? '');
    if ($reportId <= 0 || $proofId === '') {
        http_response_code(400);
        echo 'Invalid proof request';
        return;
    }

    $result = ql_ledger_group_final_report_package_for_user($reportId, (int)$user['id']);
    if (empty($result['ok'])) {
        $error = (string)($result['error'] ?? 'package_error');
        http_response_code($error === 'access_denied' ? 403 : ($error === 'historical_package_missing' ? 409 : 404));
        echo $error;
        return;
    }

    $proof = null;
    foreach (($result['package']['proofs'] ?? []) as $candidate) {
        if ((string)($candidate['proof_id'] ?? $candidate['id'] ?? '') === $proofId) {
            $proof = $candidate;
            break;
        }
    }
    if (!$proof || ($proof['storage_status'] ?? '') !== 'copied') {
        http_response_code(404);
        echo 'Proof not found';
        return;
    }

    $base = realpath(dirname(__DIR__) . '/storage/documents/group-final-reports');
    $target = realpath(dirname(__DIR__) . '/' . ltrim((string)($proof['storage_path'] ?? ''), '/'));
    if (!$base || !$target || strpos($target, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
        http_response_code(404);
        echo 'Proof file missing';
        return;
    }

    $name = basename((string)($proof['original_name'] ?? $proof['stored_name'] ?? 'proof'));
    $mime = (string)($proof['mime_type'] ?? 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($target));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($target);
    exit;
}

function ql_ledger_group_finalize_report(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || empty($scope['can_write_group_ledger'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $snapshot = ql_ledger_group_export_snapshot($groupId, $user);
    $snapshotCreatedAt = date('Y-m-d H:i:s');

    $stmt = ql_db()->prepare("
        SELECT
            t.id,
            t.user_id,
            t.group_id,
            t.title,
            t.stream_type
        FROM on_the_go_tapes t
        WHERE t.group_id = ?
          AND t.status <> 'archived'
          AND t.archived_at IS NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.reportable = 1
                AND c.review_status <> 'archived'
              LIMIT 1
          )
        ORDER BY t.submitted_at ASC, t.id ASC
        LIMIT 200
    ");
    $stmt->execute([$groupId]);
    $cards = $stmt->fetchAll();

    if (!$cards) {
        return [
            'ok' => true,
            'finalized' => 0,
            'message' => 'no_included_cards',
        ];
    }

    $ids = array_map(static fn($row) => (int)$row['id'], $cards);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $finalReportId = 0;
    ql_db()->beginTransaction();
    try {
        $update = ql_db()->prepare("
            UPDATE on_the_go_tapes
            SET archived_at = COALESCE(archived_at, NOW()),
                updated_at = updated_at
            WHERE id IN ({$placeholders})
        ");
        $update->execute($ids);

        foreach ($cards as $card) {
            $summary = function_exists('ql_on_the_go_card_summary')
                ? ql_on_the_go_card_summary((int)$card['id'])
                : [];
            if (function_exists('ql_audit_write')) {
                ql_audit_write($userId, 'ledger_group_report_finalized_card', 'on_the_go_tape', (int)$card['id'], [
                    'group_id' => $groupId,
                    'owner_user_id' => (int)($card['user_id'] ?? 0),
                    'summary' => $summary,
                ]);
            }
            if (function_exists('ql_on_the_go_journal_append')) {
                ql_on_the_go_journal_append('group_report_finalized_card', $userId, (int)$card['id'], [
                    'group_id' => $groupId,
                    'owner_user_id' => (int)($card['user_id'] ?? 0),
                ]);
            }
        }

        $finalReportDetails = [
            'snapshot_version' => 1,
            'snapshot_type' => 'group_final_report',
            'snapshot_created_at' => $snapshotCreatedAt,
            'group_id' => $groupId,
            'group_name' => (string)($snapshot['group_name'] ?? ''),
            'finalized_cards' => count($cards),
            'card_ids' => $ids,
            'carryover_admin_cash_left' => (float)($snapshot['totals']['admin_cash_left'] ?? 0),
            'carryover_employee_cash_left' => (float)($snapshot['totals']['employee_cash_left'] ?? 0),
            'carryover_cash_balance' => (float)($snapshot['totals']['cash_balance'] ?? 0),
            'report_balance' => (float)($snapshot['totals']['balance'] ?? 0),
            'report_snapshot' => $snapshot,
        ];
        $finalReportJson = json_encode($finalReportDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($finalReportJson === false) {
            throw new RuntimeException('final_report_snapshot_encode_failed');
        }

        $reportAudit = ql_db()->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
            VALUES (?, 'ledger_group_report_finalized', 'group', ?, ?, ?, ?)
        ");
        $reportAudit->execute([
            $userId,
            $groupId,
            $finalReportJson,
            ql_client_ip(),
            ql_user_agent(),
        ]);
        $finalReportId = (int)ql_db()->lastInsertId();
        $createdStmt = ql_db()->prepare("SELECT created_at FROM audit_log WHERE id = ? LIMIT 1");
        $createdStmt->execute([$finalReportId]);
        $finalReportCreatedAt = (string)($createdStmt->fetchColumn() ?: $snapshotCreatedAt);

        $reportPackage = ql_ledger_group_final_report_build_package(
            $finalReportId,
            $groupId,
            $snapshot,
            $cards,
            $user,
            $finalReportCreatedAt,
            $snapshotCreatedAt
        );
        $finalReportDetails['package_version'] = 1;
        $finalReportDetails['report_package'] = $reportPackage;
        $finalReportJson = json_encode($finalReportDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($finalReportJson === false) {
            throw new RuntimeException('final_report_package_encode_failed');
        }
        $reportUpdate = ql_db()->prepare("
            UPDATE audit_log
            SET details = ?
            WHERE id = ?
            LIMIT 1
        ");
        $reportUpdate->execute([$finalReportJson, $finalReportId]);

        ql_db()->commit();
    } catch (Throwable $e) {
        ql_db()->rollBack();
        return ['ok' => false, 'error' => 'finalize_failed', 'message' => $e->getMessage()];
    }

    return [
        'ok' => true,
        'finalized' => count($cards),
        'report_id' => $finalReportId,
        'card_ids' => $ids,
    ];
}

function ql_ledger_group_open_received_funds(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_group_reports'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $finalizeStmt = ql_db()->prepare("
        SELECT created_at, details
        FROM audit_log
        WHERE action = 'ledger_group_report_finalized'
          AND entity_type = 'group'
          AND entity_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $finalizeStmt->execute([$groupId]);
    $finalize = $finalizeStmt->fetch() ?: null;
    $finalizedAt = $finalize ? (string)($finalize['created_at'] ?? '') : '';
    $details = $finalize ? json_decode((string)($finalize['details'] ?? ''), true) : [];
    if (!is_array($details)) {
        $details = [];
    }
    $finalizedSnapshot = ql_ledger_group_final_report_snapshot($details);
    if ($finalizedSnapshot) {
        $finalizedPackage = ql_ledger_group_final_report_package_snapshot($details);
        $finalizedSnapshot = ql_ledger_group_apply_participant_control($finalizedSnapshot, $finalizedPackage);
        $details['carryover_admin_cash_left'] = (float)($finalizedSnapshot['totals']['admin_cash_left'] ?? 0);
        $details['carryover_employee_cash_left'] = (float)($finalizedSnapshot['totals']['employee_cash_left'] ?? 0);
    }

    $where = "
        le.group_id = ?
        AND le.deleted_at IS NULL
        AND le.entry_type = 'income'
        AND (le.note IS NULL OR (le.note NOT LIKE 'From On the Go%' AND le.note NOT LIKE 'From advance #%'))
    ";
    $params = [$groupId];
    if ($finalizedAt !== '') {
        $where .= " AND le.created_at > ?";
        $params[] = $finalizedAt;
    }

    $stmt = ql_db()->prepare("
        SELECT
            le.id,
            le.user_id,
            le.group_id,
            le.entry_type,
            le.money_type,
            le.amount,
            le.currency,
            le.purpose,
            le.note,
            le.entry_datetime,
            le.created_at,
            le.updated_at,
            u.email AS owner_email,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_display_name
        FROM ledger_entries le
        JOIN users u ON u.id = le.user_id
        LEFT JOIN group_members gm ON gm.group_id = le.group_id AND gm.user_id = le.user_id
        WHERE {$where}
        ORDER BY le.entry_datetime DESC, le.id DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $postCashIncome = 0.0;
    foreach ($rows as &$row) {
        $row['amount'] = round((float)($row['amount'] ?? 0), 2);
        if (($row['money_type'] ?? '') === 'cash') {
            $postCashIncome += (float)$row['amount'];
        }
    }
    unset($row);

    $carryover = null;
    $carryovers = [];
    if ($finalizedAt !== '') {
        $carryoverAmount = array_key_exists('carryover_admin_cash_left', $details)
            ? round((float)$details['carryover_admin_cash_left'], 2)
            : null;

        if ($carryoverAmount === null) {
            $balance = ql_ledger_balance(['group_id' => $groupId]);
            $available = !empty($balance['ok']) ? (float)($balance['summary']['available_cash_balance'] ?? 0) : 0.0;
            $carryoverAmount = round($available - $postCashIncome, 2);
        }

        if (abs($carryoverAmount) < 0.01) {
            $carryoverAmount = 0.0;
        }

        $carryover = [
            'id' => 'carryover-' . preg_replace('/[^0-9]/', '', $finalizedAt),
            'entry_type' => 'income',
            'money_type' => 'cash',
            'amount' => $carryoverAmount,
            'currency' => 'EUR',
            'purpose' => 'Переходящий остаток',
            'note' => 'Остаток администратора после фиксации отчета',
            'entry_datetime' => $finalizedAt,
            'created_at' => $finalizedAt,
            'readonly' => true,
            'carryover' => true,
            'owner_display_name' => 'Администратор',
            'owner_email' => '',
        ];
        $carryovers[] = $carryover;

        $accountableRows = ql_ledger_group_accountable_left_rows($groupId);
        foreach ($accountableRows as $row) {
            $amount = round((float)($row['cash_left'] ?? 0), 2);
            if (abs($amount) < 0.01) {
                continue;
            }
            $carryovers[] = [
                'id' => 'carryover-employee-' . (int)($row['user_id'] ?? 0) . '-' . preg_replace('/[^0-9]/', '', $finalizedAt),
                'entry_type' => 'income',
                'money_type' => 'cash',
                'amount' => $amount,
                'currency' => 'EUR',
                'purpose' => 'Переходящий остаток',
                'note' => 'Остаток у сотрудника: ' . (string)($row['owner_display_name'] ?? 'Сотрудник'),
                'entry_datetime' => $finalizedAt,
                'created_at' => $finalizedAt,
                'readonly' => true,
                'carryover' => true,
                'owner_display_name' => (string)($row['owner_display_name'] ?? 'Сотрудник'),
                'owner_email' => (string)($row['email'] ?? ''),
            ];
        }
    }

    $liveStmt = ql_db()->prepare("
        SELECT t.id
        FROM on_the_go_tapes t
        WHERE t.group_id = ?
          AND t.status <> 'archived'
          AND t.archived_at IS NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.reportable = 1
                AND c.review_status <> 'archived'
              LIMIT 1
          )
        LIMIT 200
    ");
    $liveStmt->execute([$groupId]);

    $liveIncluded = [
        'cards' => 0,
        'cash_expense' => 0.0,
        'noncash_expense' => 0.0,
        'records' => 0,
    ];
    foreach ($liveStmt->fetchAll() as $row) {
        $summary = function_exists('ql_on_the_go_card_summary')
            ? ql_on_the_go_card_summary((int)$row['id'])
            : [];
        $liveIncluded['cards'] += 1;
        $liveIncluded['cash_expense'] += (float)($summary['cash_out'] ?? 0);
        $liveIncluded['noncash_expense'] += (float)($summary['card_out'] ?? 0);
        $liveIncluded['records'] += (int)($summary['records_count'] ?? 0);
    }
    $liveIncluded['cash_expense'] = round($liveIncluded['cash_expense'], 2);
    $liveIncluded['noncash_expense'] = round($liveIncluded['noncash_expense'], 2);

    return [
        'ok' => true,
        'finalized_at' => $finalizedAt ?: null,
        'carryover' => $carryover,
        'carryovers' => $carryovers,
        'entries' => $rows,
        'open_period' => [
            'live_included' => $liveIncluded,
        ],
    ];
}

function ql_ledger_group_excel_download(): void
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($_GET['group_id'] ?? 0);

    if ($groupId <= 0) {
        http_response_code(400);
        echo 'Invalid group id';
        return;
    }

    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_group_reports'])) {
        http_response_code(403);
        echo 'Access denied';
        return;
    }

    $snapshot = ql_ledger_group_export_snapshot($groupId, $user);
    $groupName = $snapshot['group_name'];
    $preparedRows = $snapshot['prepared_rows'];
    $accountableRows = $snapshot['accountable_rows'];
    $articleRows = $snapshot['article_rows'];
    $memberRows = $snapshot['member_rows'];
    $income = $snapshot['totals']['income'];
    $expense = $snapshot['totals']['expense'];
    $cashExpense = $snapshot['totals']['cash_expense'];
    $noncashExpense = $snapshot['totals']['noncash_expense'];
    $adminCashLeft = $snapshot['totals']['admin_cash_left'];
    $employeeCashLeft = $snapshot['totals']['employee_cash_left'];
    $cashBalance = $snapshot['totals']['cash_balance'];
    $balance = $snapshot['totals']['balance'];

    $fileBase = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $groupName);
    $fileName = 'findesk-report-' . ($fileBase ?: ('group-' . $groupId)) . '-' . date('Ymd-His') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo "\xEF\xBB\xBF";
    ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; color: #111827; font-size: 11px; }
    h1 { font-size: 18px; margin: 0 0 4px; }
    h2 { font-size: 13px; margin: 16px 0 6px; }
    .muted { color: #64748b; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 12px; table-layout: fixed; }
    th { background: #eaf4ff; color: #0f172a; font-weight: 700; }
    th, td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; overflow-wrap: break-word; }
    .money { text-align: right; white-space: nowrap; }
    .income { color: #047857; font-weight: 700; background: #ecfdf5; }
    .expense { color: #b91c1c; font-weight: 700; background: #fef2f2; }
    .cash { background: #eff6ff; }
    .card { background: #fffbeb; }
    .keeper { background: #f8fafc; font-weight: 700; }
    .summary th { background: #dcfce7; }
    .leftover th { background: #dbeafe; }
    .articles th { background: #fef3c7; }
    .members th { background: #ede9fe; }
    .movement { width: 1680px; }
    .w-date { width: 130px; }
    .w-person { width: 190px; }
    .w-source { width: 120px; }
    .w-article { width: 160px; }
    .w-type { width: 82px; }
    .w-money { width: 120px; }
    .w-purpose { width: 310px; }
    .w-num { width: 118px; }
    .w-title { width: 260px; }
    .w-small { width: 70px; }
    .w-mid { width: 120px; }
  </style>
</head>
<body>
  <h1>FinDesk отчет группы: <?= ql_excel_text($groupName) ?></h1>
  <div class="muted"><?= ql_excel_text(date('Y-m-d H:i:s')) ?> · строк: <?= count($preparedRows) ?></div>

  <h2>Итог</h2>
  <table class="summary">
    <colgroup>
      <col class="w-mid"><col class="w-mid"><col class="w-mid"><col class="w-mid">
      <col class="w-mid"><col class="w-mid"><col class="w-mid"><col class="w-mid">
    </colgroup>
    <tr>
      <th>Приход</th>
      <th>Расход</th>
      <th>Наличные расходы</th>
      <th>Карта / безнал</th>
      <th>У администратора</th>
      <th>У сотрудников</th>
      <th>Физическая касса</th>
      <th>Учетный баланс</th>
    </tr>
    <tr>
      <td class="money income"><?= ql_excel_money($income) ?></td>
      <td class="money expense"><?= ql_excel_money($expense) ?></td>
      <td class="money expense"><?= ql_excel_money($cashExpense) ?></td>
      <td class="money card"><?= ql_excel_money($noncashExpense) ?></td>
      <td class="money cash"><?= ql_excel_money($adminCashLeft) ?></td>
      <td class="money cash"><?= ql_excel_money($employeeCashLeft) ?></td>
      <td class="money cash"><?= ql_excel_money($cashBalance) ?></td>
      <td class="money"><?= ql_excel_money($balance) ?></td>
    </tr>
  </table>

  <h2>Остатки на руках</h2>
  <table class="leftover">
    <colgroup>
      <col class="w-title"><col class="w-source"><col class="w-small"><col class="w-mid">
    </colgroup>
    <tr>
      <th>Хранитель</th>
      <th>Тип</th>
      <th>Отчетов</th>
      <th>Остаток</th>
    </tr>
    <tr>
      <td class="keeper"><?= ql_excel_text($user['display_name'] ?? $user['email'] ?? 'Администратор') ?></td>
      <td>Администратор</td>
      <td>—</td>
      <td class="money cash"><?= ql_excel_money($adminCashLeft) ?></td>
    </tr>
    <?php foreach ($accountableRows as $row): ?>
      <tr>
        <td class="keeper"><?= ql_excel_text($row['owner_display_name'] ?? 'Участник') ?></td>
        <td>Сотрудник</td>
        <td><?= (int)($row['open_count'] ?? 0) ?></td>
        <td class="money cash"><?= ql_excel_money($row['cash_left'] ?? 0) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr>
      <th colspan="3">Физически всего</th>
      <th class="money"><?= ql_excel_money($cashBalance) ?></th>
    </tr>
  </table>

  <h2>Статьи</h2>
  <table class="articles">
    <colgroup>
      <col class="w-title"><col class="w-small"><col class="w-mid"><col class="w-mid"><col class="w-mid">
    </colgroup>
    <tr>
      <th>Статья</th>
      <th>Строк</th>
      <th>Приход</th>
      <th>Расход</th>
      <th>Итог</th>
    </tr>
    <?php foreach ($articleRows as $row): ?>
      <tr>
        <td><?= ql_excel_text($row['name']) ?></td>
        <td class="money"><?= (int)$row['records'] ?></td>
        <td class="money income"><?= (float)$row['income'] > 0.009 ? ql_excel_money($row['income']) : '' ?></td>
        <td class="money expense"><?= (float)$row['expense'] > 0.009 ? ql_excel_money($row['expense']) : '' ?></td>
        <td class="money"><?= ql_excel_money($row['balance']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Участники</h2>
  <table class="members">
    <colgroup>
      <col class="w-title"><col class="w-small"><col class="w-mid"><col class="w-mid"><col class="w-mid">
    </colgroup>
    <tr>
      <th>Участник</th>
      <th>Строк</th>
      <th>Внес</th>
      <th>Расход</th>
      <th>Итог</th>
    </tr>
    <?php foreach ($memberRows as $row): ?>
      <tr>
        <td><?= ql_excel_text($row['name']) ?></td>
        <td class="money"><?= (int)$row['records'] ?></td>
        <td class="money income"><?= (float)$row['income'] > 0.009 ? ql_excel_money($row['income']) : '' ?></td>
        <td class="money expense"><?= (float)$row['expense'] > 0.009 ? ql_excel_money($row['expense']) : '' ?></td>
        <td class="money"><?= ql_excel_money((float)$row['income'] - (float)$row['expense']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <h2>Движение денег</h2>
  <table class="movement">
    <colgroup>
      <col class="w-date"><col class="w-person"><col class="w-source"><col class="w-article">
      <col class="w-type"><col class="w-money"><col class="w-purpose"><col class="w-num">
      <col class="w-num"><col class="w-num"><col class="w-num"><col class="w-num">
    </colgroup>
    <tr>
      <th class="w-date">Дата</th>
      <th class="w-person">Кто</th>
      <th class="w-source">Источник</th>
      <th class="w-article">Статья</th>
      <th class="w-type">Тип</th>
      <th class="w-money">Деньги</th>
      <th class="w-purpose">Назначение</th>
      <th class="w-num">Сумма</th>
      <th class="w-num">Касса была</th>
      <th class="w-num">Движение</th>
      <th class="w-num">Касса стала</th>
      <th class="w-num">Баланс стал</th>
    </tr>
    <?php foreach ($preparedRows as $row): ?>
      <tr>
        <td><?= ql_excel_text($row['date']) ?></td>
        <td><?= ql_excel_text($row['owner']) ?></td>
        <td><?= ql_excel_text($row['source']) ?></td>
        <td><?= ql_excel_text($row['section']) ?></td>
        <td class="<?= $row['type'] === 'Приход' ? 'income' : 'expense' ?>"><?= ql_excel_text($row['type']) ?></td>
        <td class="<?= $row['money'] === 'Наличные' ? 'cash' : 'card' ?>"><?= ql_excel_text($row['money']) ?></td>
        <td><?= ql_excel_text($row['purpose']) ?></td>
        <td class="money <?= $row['type'] === 'Приход' ? 'income' : 'expense' ?>"><?= ql_excel_money($row['amount']) ?></td>
        <td class="money"><?= ql_excel_money($row['cash_before']) ?></td>
        <td class="money"><?= ql_excel_money($row['cash_change']) ?></td>
        <td class="money"><?= ql_excel_money($row['cash_after']) ?></td>
        <td class="money"><?= ql_excel_money($row['balance_after']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
<?php
}

function ql_category_list(array $input = []): array
{
    $user = ql_require_user();
    $groupId = ql_ledger_input_group_id($input);
    $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (int)$input['category_id'] : null;

    if ($groupId > 0 && !ql_ledger_group_scope($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }

    $stmt = ql_db()->prepare("
        SELECT id, category_type, name, color, sort_order, is_default
        FROM ledger_categories
        WHERE deleted_at IS NULL
          AND is_default = 0
          AND (
                user_id = ?
                OR group_id = ?
          )
        ORDER BY sort_order ASC, name ASC
    ");
    $stmt->execute([(int)$user['id'], $groupId ?: 0]);

    return ['ok' => true, 'categories' => $stmt->fetchAll()];
}

function ql_category_create(array $input): array
{
    $user = ql_require_user();

    $type = (string)($input['category_type'] ?? 'income');
    $name = trim((string)($input['name'] ?? ''));
    $groupId = ql_ledger_input_group_id($input);

    if (!in_array($type, ['income', 'expense'], true)) {
        $type = 'income';
    }

    if ($name === '') {
        return ['ok' => false, 'error' => 'empty_category_name'];
    }

    if (mb_strlen($name) > 190) {
        return ['ok' => false, 'error' => 'category_name_too_long'];
    }

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, (int)$user['id']);
        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }
        if (!$scope['is_admin']) {
            return ['ok' => false, 'error' => 'admin_required'];
        }
    }

    $stmt = ql_db()->prepare("
        INSERT INTO ledger_categories
            (user_id, group_id, category_type, name, color, sort_order, is_default)
        VALUES
            (?, ?, ?, ?, ?, 500, 0)
    ");
    $stmt->execute([
        $groupId > 0 ? null : (int)$user['id'],
        $groupId > 0 ? $groupId : null,
        $type,
        $name,
        $type === 'income' ? '#DFF5E7' : '#FFF1C7'
    ]);

    return ql_category_list(['group_id' => $groupId]);
}

function ql_category_allowed(?int $categoryId, string $entryType, int $userId, int $groupId = 0): bool
{
    if (!$categoryId) {
        return true;
    }

    $stmt = ql_db()->prepare("
        SELECT id
        FROM ledger_categories
        WHERE id = ?
          AND deleted_at IS NULL
          AND (
                (is_default = 1 AND user_id IS NULL AND group_id IS NULL)
                OR user_id = ?
                OR group_id = ?
          )
        LIMIT 1
    ");
    $stmt->execute([$categoryId, $userId, $groupId ?: 0]);

    return (bool)$stmt->fetch();
}

function ql_ledger_create(array $input): array
{
    $user = ql_require_user();

    $entryType = $input['entry_type'] ?? '';
    $moneyType = $input['money_type'] ?? '';
    $amount = ql_money_amount($input['amount'] ?? '');
    $purpose = trim((string)($input['purpose'] ?? ''));
    $groupId = ql_ledger_input_group_id($input);
    $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (int)$input['category_id'] : null;

    if (!in_array($entryType, ['income', 'expense'], true)) {
        return ['ok' => false, 'error' => 'invalid_entry_type'];
    }

    if (!in_array($moneyType, ['cash', 'noncash'], true)) {
        return ['ok' => false, 'error' => 'invalid_money_type'];
    }

    if ($amount === null) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }

    if ($purpose === '') {
        return ['ok' => false, 'error' => 'empty_purpose'];
    }

    if (!ql_category_allowed($categoryId, $entryType, (int)$user['id'], $groupId)) {
        return ['ok' => false, 'error' => 'invalid_category'];
    }

    $groupIdForDb = null;

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, (int)$user['id']);

        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }
        if (empty($scope['can_write_group_ledger'])) {
            return ['ok' => false, 'error' => 'access_denied', 'required' => 'manager'];
        }

        $groupIdForDb = $groupId;
    }

    $db = ql_db();

    $stmt = $db->prepare("
        INSERT INTO ledger_entries
            (user_id, group_id, entry_type, money_type, category_id, amount, currency, purpose, note, entry_datetime, original_position_at)
        VALUES
            (?, ?, ?, ?, ?, ?, 'EUR', ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        (int)$user['id'],
        $groupIdForDb,
        $entryType,
        $moneyType,
        $categoryId,
        $amount,
        $purpose,
        trim((string)($input['note'] ?? '')) ?: null
    ]);

    $entryId = (int)$db->lastInsertId();

    if ($groupIdForDb && function_exists('ql_on_the_go_sync_empty_group_tape_base')) {
        ql_on_the_go_sync_empty_group_tape_base((int)$user['id'], (int)$groupIdForDb);
    }

    if (function_exists('ql_audit_write') && $groupIdForDb && $entryType === 'income') {
        ql_audit_write((int)$user['id'], 'group_funds_received', 'ledger_entry', $entryId, [
            'group_id' => $groupIdForDb,
            'amount' => $amount,
            'money_type' => $moneyType,
            'purpose' => $purpose
        ]);
    }

    return [
        'ok' => true,
        'entry' => ql_ledger_get_one($entryId, (int)$user['id'])
    ];
}

function ql_ledger_get_one(int $entryId, int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT
            le.id,
            le.entry_type,
            le.money_type,
            le.category_id,
            lc.name AS category_name,
            le.amount,
            le.currency,
            le.purpose,
            le.note,
            le.entry_datetime,
            le.original_position_at,
            le.edited_at,
            le.created_at,
            le.updated_at,
            (
                SELECT COUNT(*)
                FROM entry_files ef
                WHERE ef.entry_id = le.id
                  AND ef.deleted_at IS NULL
            ) AS file_count
        FROM ledger_entries le
        LEFT JOIN ledger_categories lc ON lc.id = le.category_id
        WHERE le.id = ?
          AND le.user_id = ?
          AND le.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$entryId, $userId]);

    $entry = $stmt->fetch();
    return $entry ?: null;
}

function ql_ledger_list(array $input = []): array
{
    $user = ql_require_user();

    $limit = (int)($input['limit'] ?? 100);
    if ($limit < 1 || $limit > 300) {
        $limit = 100;
    }

    $groupId = ql_ledger_input_group_id($input);
    $scope = null;
    $params = [];

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, (int)$user['id']);

        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        if (!empty($scope['can_view_group_reports'])) {
            $where = "le.group_id = ? AND le.deleted_at IS NULL";
            $params = [$groupId];
        } else {
            $where = "le.group_id = ? AND le.user_id = ? AND le.deleted_at IS NULL";
            $params = [$groupId, (int)$user['id']];
        }
    } else {
        $where = "le.user_id = ? AND le.group_id IS NULL AND le.deleted_at IS NULL";
        $params = [(int)$user['id']];
    }

    $sql = "
        SELECT
            le.id,
            le.user_id,
            le.group_id,
            le.entry_type,
            le.money_type,
            le.category_id,
            lc.name AS category_name,
            le.amount,
            le.currency,
            le.purpose,
            le.note,
            le.entry_datetime,
            le.original_position_at,
            le.edited_at,
            le.created_at,
            le.updated_at,
            u.email AS owner_email,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_display_name,
            (
                SELECT COUNT(*)
                FROM entry_files ef
                WHERE ef.entry_id = le.id
                  AND ef.deleted_at IS NULL
            ) AS file_count
        FROM ledger_entries le
        JOIN users u ON u.id = le.user_id
        LEFT JOIN group_members gm ON gm.group_id = le.group_id AND gm.user_id = le.user_id
        LEFT JOIN ledger_categories lc ON lc.id = le.category_id
        WHERE {$where}
        ORDER BY le.original_position_at ASC, le.id ASC
        LIMIT {$limit}
    ";

    $stmt = ql_db()->prepare($sql);
    $stmt->execute($params);

    $entries = $stmt->fetchAll();
    if ($groupId > 0) {
        $virtualEntries = ql_ledger_virtual_on_the_go_entries(
            $groupId,
            !empty($scope['can_view_group_reports']) ? null : (int)$user['id']
        );
        if ($virtualEntries) {
            $entries = array_merge($entries, $virtualEntries);
            usort($entries, function ($a, $b) {
                $aTime = (string)($a['original_position_at'] ?? $a['entry_datetime'] ?? '');
                $bTime = (string)($b['original_position_at'] ?? $b['entry_datetime'] ?? '');
                $cmp = strcmp($aTime, $bTime);
                if ($cmp !== 0) return $cmp;
                return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
            });
            $entries = array_slice($entries, 0, $limit);
        }
    }

    $dayTotals = [];
    $totalIncome = 0.0;
    $totalExpense = 0.0;

    foreach ($entries as $entry) {
        $day = substr($entry['entry_datetime'], 0, 10);
        if (!isset($dayTotals[$day])) {
            $dayTotals[$day] = [
                'date' => $day,
                'income' => 0.0,
                'expense' => 0.0,
                'balance' => 0.0,
            ];
        }

        $amount = (float)$entry['amount'];

        if ($entry['entry_type'] === 'income') {
            $dayTotals[$day]['income'] += $amount;
            $totalIncome += $amount;
        } else {
            $dayTotals[$day]['expense'] += $amount;
            $totalExpense += $amount;
        }

        $dayTotals[$day]['balance'] = $dayTotals[$day]['income'] - $dayTotals[$day]['expense'];
    }

    return [
        'ok' => true,
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'personal',
            'group_id' => $groupId ?: null,
            'is_admin' => $scope['is_admin'] ?? false,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'entries' => $entries,
        'summary' => [
            'income' => round($totalIncome, 2),
            'expense' => round($totalExpense, 2),
            'balance' => round($totalIncome - $totalExpense, 2),
            'days' => array_values($dayTotals),
        ],
    ];
}

function ql_ledger_balance(array $input = []): array
{
    $user = ql_require_user();
    $groupId = ql_ledger_input_group_id($input);
    $scope = null;

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, (int)$user['id']);

        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        if (!empty($scope['can_view_group_reports'])) {
            $where = "group_id = ? AND deleted_at IS NULL";
            $params = [$groupId];
        } else {
            $where = "group_id = ? AND user_id = ? AND deleted_at IS NULL";
            $params = [$groupId, (int)$user['id']];
        }
    } else {
        $where = "user_id = ? AND group_id IS NULL AND deleted_at IS NULL";
        $params = [(int)$user['id']];
    }

    $stmt = ql_db()->prepare("
        SELECT
            entry_type,
            money_type,
            SUM(amount) AS total,
            COUNT(*) AS records
        FROM ledger_entries
        WHERE {$where}
        GROUP BY entry_type, money_type
    ");
    $stmt->execute($params);

    $summary = [
        'income' => 0.0,
        'expense' => 0.0,
        'balance' => 0.0,
        'cash_income' => 0.0,
        'cash_expense' => 0.0,
        'cash_balance' => 0.0,
        'noncash_income' => 0.0,
        'noncash_expense' => 0.0,
        'noncash_balance' => 0.0,
        'accountable_issued_open' => 0.0,
        'accountable_cash_left_open' => 0.0,
        'accountable_cash_spent_open' => 0.0,
        'accountable_card_spent_open' => 0.0,
        'accountable_spent_open' => 0.0,
        'accountable_open_count' => 0,
        'available_cash_balance' => 0.0,
        'available_balance' => 0.0,
        'records' => 0,
    ];

    foreach ($stmt->fetchAll() as $row) {
        $amount = (float)$row['total'];
        $summary['records'] += (int)$row['records'];

        if ($row['entry_type'] === 'income') {
            $summary['income'] += $amount;
            if ($row['money_type'] === 'cash') {
                $summary['cash_income'] += $amount;
            } else {
                $summary['noncash_income'] += $amount;
            }
        } else {
            $summary['expense'] += $amount;
            if ($row['money_type'] === 'cash') {
                $summary['cash_expense'] += $amount;
            } else {
                $summary['noncash_expense'] += $amount;
            }
        }
    }

    $rawCashBalance = $summary['cash_income'] - $summary['cash_expense'];
    $rawBalance = $summary['income'] - $summary['expense'];
    $virtualCards = null;
    $workingCards = null;
    if ($groupId > 0) {
        $virtualCards = ql_on_the_go_submitted_card_totals(
            $groupId,
            !empty($scope['can_view_group_reports']) ? null : (int)$user['id']
        );
        $workingCards = ql_on_the_go_submitted_card_totals(
            $groupId,
            !empty($scope['can_view_group_reports']) ? null : (int)$user['id'],
            false
        );

        $summary['cash_income'] += (float)($virtualCards['cash_income'] ?? 0);
        $summary['cash_expense'] += (float)($virtualCards['cash_expense'] ?? 0);
        $summary['noncash_expense'] += (float)($virtualCards['noncash_expense'] ?? 0);
        $summary['income'] += (float)($virtualCards['cash_income'] ?? 0);
        $summary['expense'] += (float)($virtualCards['cash_expense'] ?? 0) + (float)($virtualCards['noncash_expense'] ?? 0);
        $summary['records'] += (int)($virtualCards['records'] ?? 0);
    }

    $summary['balance'] = $summary['income'] - $summary['expense'];
    $summary['cash_balance'] = $summary['cash_income'] - $summary['cash_expense'];
    $summary['noncash_balance'] = $summary['noncash_income'] - $summary['noncash_expense'];
    $accountable = null;
    if ($groupId > 0) {
        $accountable = ql_on_the_go_open_accountable_totals(
            $groupId,
            !empty($scope['can_view_group_reports']) ? null : (int)$user['id']
        );
        $summary['accountable_issued_open'] = (float)($accountable['issued_open'] ?? 0);
        $summary['accountable_cash_left_open'] = (float)($accountable['cash_left_open'] ?? 0);
        $summary['accountable_cash_spent_open'] = (float)($accountable['cash_spent_open'] ?? 0);
        $summary['accountable_card_spent_open'] = (float)($accountable['card_spent_open'] ?? 0);
        $summary['accountable_spent_open'] = (float)($accountable['spent_open'] ?? 0);
        $summary['accountable_open_count'] = (int)($accountable['open_count'] ?? 0);
    }
    $workingDelta = (float)($workingCards['balance_delta'] ?? 0);
    $workingCashDelta = (float)($workingCards['cash_delta'] ?? $workingDelta);
    $summary['available_cash_balance'] = $rawCashBalance + $workingCashDelta - $summary['accountable_cash_left_open'] - $summary['accountable_cash_spent_open'];
    $summary['available_balance'] = $rawBalance + $workingDelta - $summary['accountable_cash_left_open'] - $summary['accountable_spent_open'];

    foreach ($summary as $key => $value) {
        if (!in_array($key, ['records', 'accountable_open_count'], true)) {
            $summary[$key] = round((float)$value, 2);
        }
    }

    return [
        'ok' => true,
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'personal',
            'group_id' => $groupId ?: null,
            'is_admin' => $scope['is_admin'] ?? false,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'summary' => $summary,
        'virtual_cards' => $virtualCards,
        'working_cards' => $workingCards,
        'accountable' => $accountable,
    ];
}

function ql_ledger_work_position(array $input = []): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = ql_ledger_input_group_id($input);
    $scope = null;

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, $userId);

        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        if (!empty($scope['can_view_group_reports'])) {
            $where = "le.group_id = ? AND le.deleted_at IS NULL";
            $params = [$groupId];
        } else {
            $where = "le.group_id = ? AND le.user_id = ? AND le.deleted_at IS NULL";
            $params = [$groupId, $userId];
        }
    } else {
        $where = "le.user_id = ? AND le.group_id IS NULL AND le.deleted_at IS NULL";
        $params = [$userId];
    }

    $balanceStmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN le.entry_type = 'income' THEN le.amount ELSE -le.amount END), 0) AS balance,
            COUNT(*) AS records
        FROM ledger_entries le
        WHERE {$where}
    ");
    $balanceStmt->execute($params);
    $current = $balanceStmt->fetch() ?: [];
    $currentBalance = round((float)($current['balance'] ?? 0), 2);
    $virtualCards = null;
    if ($groupId > 0) {
        $virtualCards = ql_on_the_go_submitted_card_totals(
            $groupId,
            !empty($scope['can_view_group_reports']) ? null : $userId,
            false
        );
    }
    $virtualDelta = round((float)($virtualCards['balance_delta'] ?? 0), 2);
    $currentBalanceWithCards = round($currentBalance + $virtualDelta, 2);
    $currentRecordsWithCards = (int)($current['records'] ?? 0) + (int)($virtualCards['records'] ?? 0);
    $latestCard = is_array($virtualCards['latest'] ?? null) ? $virtualCards['latest'] : null;

    $latestStmt = ql_db()->prepare("
        SELECT le.id, le.user_id, le.entry_type, le.amount, le.purpose, le.note, le.entry_datetime, le.created_at
        FROM ledger_entries le
        WHERE {$where}
        ORDER BY le.created_at DESC, le.id DESC
        LIMIT 1
    ");
    $latestStmt->execute($params);
    $latest = $latestStmt->fetch();

    if ($latestCard && (!$latest || strcmp((string)($latestCard['submitted_at'] ?? ''), (string)($latest['created_at'] ?? '')) >= 0)) {
        $cardSummary = $latestCard['summary'] ?? [];
        $cardDate = substr((string)($latestCard['submitted_at'] ?? ''), 0, 10);

        return [
            'ok' => true,
            'period' => ['type' => 'work_position', 'from' => $cardDate ?: null, 'to' => $cardDate ?: null],
            'summary' => ['balance' => $currentBalanceWithCards, 'records' => $currentRecordsWithCards],
            'position' => [
                'before' => round((float)($cardSummary['before_amount'] ?? ($currentBalanceWithCards - (float)($cardSummary['delta'] ?? 0))), 2),
                'movement' => round((float)($cardSummary['delta'] ?? 0), 2),
                'after' => round((float)($cardSummary['after_amount'] ?? $currentBalanceWithCards), 2),
                'records' => (int)($cardSummary['records_count'] ?? 0),
                'mode' => 'latest_on_the_go_card',
                'date' => $cardDate ?: null,
                'before_label' => 'перед сданной карточкой' . ($cardDate ? ' · ' . $cardDate : ''),
                'after_label' => 'после сданной карточки',
                'movement_label' => 'сданная карточка' . ($cardDate ? ' · ' . $cardDate : ''),
                'card_id' => (int)($latestCard['id'] ?? 0),
            ],
            'scope' => [
                'mode' => $groupId > 0 ? 'group' : 'personal',
                'group_id' => $groupId ?: null,
                'is_admin' => $scope['is_admin'] ?? false,
                'access_level' => $scope['access_level'] ?? null,
            ],
            'virtual_cards' => $virtualCards,
        ];
    }

    if (!$latest) {
        return [
            'ok' => true,
            'period' => ['type' => 'work_position', 'from' => null, 'to' => null],
            'summary' => ['balance' => $currentBalanceWithCards, 'records' => $currentRecordsWithCards],
            'position' => [
                'before' => 0.0,
                'movement' => 0.0,
                'after' => $currentBalanceWithCards,
                'records' => 0,
                'mode' => 'empty',
                'before_label' => 'нет предыдущего движения',
                'after_label' => 'текущий баланс',
                'movement_label' => 'операций нет',
            ],
            'scope' => [
                'mode' => $groupId > 0 ? 'group' : 'personal',
                'group_id' => $groupId ?: null,
                'is_admin' => $scope['is_admin'] ?? false,
                'access_level' => $scope['access_level'] ?? null,
            ],
            'virtual_cards' => $virtualCards,
        ];
    }

    $latestNote = (string)($latest['note'] ?? '');
    $movementWhere = $where . " AND le.id = ?";
    $movementParams = array_merge($params, [(int)$latest['id']]);
    $mode = 'latest_entry';
    $beforeLabel = 'перед последней операцией';
    $movementLabel = 'последняя операция';

    if (strpos($latestNote, 'From On the Go:') === 0) {
        $movementWhere = $where . "
            AND le.user_id = ?
            AND le.note LIKE 'From On the Go:%'
            AND le.created_at BETWEEN DATE_SUB(?, INTERVAL 10 SECOND) AND ?
        ";
        $movementParams = array_merge($params, [
            (int)$latest['user_id'],
            (string)$latest['created_at'],
            (string)$latest['created_at'],
        ]);
        $mode = 'latest_on_the_go_report';
        $beforeLabel = 'перед последним отчетом';
        $movementLabel = 'последний отчет';
    } elseif (preg_match('/^From advance #(\d+)/', $latestNote, $match)) {
        $movementWhere = $where . "
            AND le.note LIKE ?
            AND le.created_at BETWEEN DATE_SUB(?, INTERVAL 10 SECOND) AND ?
        ";
        $movementParams = array_merge($params, [
            'From advance #' . $match[1] . '%',
            (string)$latest['created_at'],
            (string)$latest['created_at'],
        ]);
        $mode = 'latest_advance_report';
        $beforeLabel = 'перед принятым отчетом';
        $movementLabel = 'принятый отчет';
    }

    $movementStmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN le.entry_type = 'income' THEN le.amount ELSE -le.amount END), 0) AS movement,
            COUNT(*) AS records,
            MIN(le.entry_datetime) AS from_at,
            MAX(le.entry_datetime) AS to_at,
            MIN(le.created_at) AS created_from,
            MAX(le.created_at) AS created_to
        FROM ledger_entries le
        WHERE {$movementWhere}
    ");
    $movementStmt->execute($movementParams);
    $movement = $movementStmt->fetch() ?: [];
    $movementAmount = round((float)($movement['movement'] ?? 0), 2);
    $before = round($currentBalanceWithCards - $movementAmount, 2);
    $date = substr((string)($movement['to_at'] ?? $latest['entry_datetime'] ?? ''), 0, 10);

    return [
        'ok' => true,
        'period' => [
            'type' => 'work_position',
            'from' => $date ?: null,
            'to' => $date ?: null,
        ],
        'summary' => [
            'balance' => $currentBalanceWithCards,
            'records' => $currentRecordsWithCards,
        ],
        'position' => [
            'before' => $before,
            'movement' => $movementAmount,
            'after' => $currentBalanceWithCards,
            'records' => (int)($movement['records'] ?? 0),
            'mode' => $mode,
            'date' => $date ?: null,
            'before_label' => $beforeLabel . ($date ? ' · ' . $date : ''),
            'after_label' => 'текущий баланс',
            'movement_label' => $movementLabel . ($date ? ' · ' . $date : ''),
            'entry_id' => (int)$latest['id'],
        ],
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'personal',
            'group_id' => $groupId ?: null,
            'is_admin' => $scope['is_admin'] ?? false,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'virtual_cards' => $virtualCards,
    ];
}


function ql_ledger_visible_entry(int $entryId, int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT
            le.id,
            le.user_id,
            le.group_id,
            le.entry_type,
            le.money_type,
            le.category_id,
            lc.name AS category_name,
            le.amount,
            le.currency,
            le.purpose,
            le.note,
            le.entry_datetime,
            le.original_position_at,
            le.edited_at,
            le.created_at,
            le.updated_at,
            u.email AS owner_email,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_display_name,
            (
                SELECT COUNT(*)
                FROM entry_files ef
                WHERE ef.entry_id = le.id
                  AND ef.deleted_at IS NULL
            ) AS file_count
        FROM ledger_entries le
        JOIN users u ON u.id = le.user_id
        LEFT JOIN group_members gm ON gm.group_id = le.group_id AND gm.user_id = le.user_id
        LEFT JOIN ledger_categories lc ON lc.id = le.category_id
        WHERE le.id = ?
          AND le.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        return null;
    }

    if ((int)$entry['user_id'] === $userId) {
        return $entry;
    }

    $groupId = (int)($entry['group_id'] ?? 0);
    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, $userId);
        if ($scope && !empty($scope['can_view_group_reports'])) {
            return $entry;
        }
    }

    return null;
}

function ql_ledger_detail(array $input): array
{
    $user = ql_require_user();
    $entryId = (int)($input['id'] ?? 0);

    if ($entryId <= 0) {
        return ['ok' => false, 'error' => 'invalid_entry_id'];
    }

    $entry = ql_ledger_visible_entry($entryId, (int)$user['id']);

    if (!$entry) {
        return ['ok' => false, 'error' => 'entry_not_found'];
    }

    $files = ql_ledger_file_rows($entryId, (int)$user['id'], $entry);

    return [
        'ok' => true,
        'entry' => $entry,
        'files' => $files
    ];
}

function ql_ledger_file_rows(int $entryId, int $userId, ?array $knownEntry = null): array
{
    $entry = $knownEntry ?: ql_ledger_visible_entry($entryId, $userId);

    if (!$entry) {
        return [];
    }

    $stmt = ql_db()->prepare("
        SELECT
            id,
            entry_id,
            user_id,
            file_original_name,
            file_stored_name,
            file_path,
            file_mime,
            file_size,
            file_kind,
            created_at
        FROM entry_files
        WHERE entry_id = ?
          AND deleted_at IS NULL
        ORDER BY id ASC
    ");
    $stmt->execute([$entryId]);
    $files = $stmt->fetchAll();

    foreach ($files as &$file) {
        $file['download_url'] = '/api.php?action=ledger_file_download&id=' . (int)$file['id'];
    }

    return $files;
}

function ql_ledger_file_list(array $input): array
{
    $user = ql_require_user();
    $entryId = (int)($input['entry_id'] ?? 0);

    if ($entryId <= 0) {
        return ['ok' => false, 'error' => 'invalid_entry_id'];
    }

    $entry = ql_ledger_visible_entry($entryId, (int)$user['id']);

    if (!$entry) {
        return ['ok' => false, 'error' => 'entry_not_found'];
    }

    return [
        'ok' => true,
        'files' => ql_ledger_file_rows($entryId, (int)$user['id'], $entry)
    ];
}

function ql_ledger_file_download(): void
{
    $user = ql_require_user();
    $fileId = (int)($_GET['id'] ?? 0);

    if ($fileId <= 0) {
        http_response_code(400);
        echo 'Invalid file id';
        return;
    }

    $stmt = ql_db()->prepare("
        SELECT ef.*, le.user_id AS entry_user_id, le.group_id
        FROM entry_files ef
        JOIN ledger_entries le ON le.id = ef.entry_id
        WHERE ef.id = ?
          AND ef.deleted_at IS NULL
          AND le.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        echo 'File not found';
        return;
    }

    $canAccess = ((int)$file['entry_user_id'] === (int)$user['id']);

    if (!$canAccess && (int)($file['group_id'] ?? 0) > 0) {
        $scope = ql_ledger_group_scope((int)$file['group_id'], (int)$user['id']);
        $canAccess = $scope && !empty($scope['can_view_group_reports']);
    }

    if (!$canAccess) {
        http_response_code(403);
        echo 'Forbidden';
        return;
    }

    $baseRoot = realpath(dirname(__DIR__) . '/storage/documents');
    $full = realpath(dirname(__DIR__) . '/storage/' . $file['file_path']);

    if (!$baseRoot || !$full || strpos($full, $baseRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) {
        http_response_code(404);
        echo 'Stored file not found';
        return;
    }

    $name = (string)($file['file_original_name'] ?: $file['file_stored_name'] ?: 'attachment');
    $mime = (string)($file['file_mime'] ?: 'application/octet-stream');

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
    header('X-Content-Type-Options: nosniff');

    readfile($full);
    exit;
}

function ql_ledger_update(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];

    $entryId = (int)($input['id'] ?? 0);

    if ($entryId <= 0) {
        return ['ok' => false, 'error' => 'invalid_entry_id'];
    }

    $existing = ql_ledger_visible_entry($entryId, $userId);

    if (!$existing) {
        return ['ok' => false, 'error' => 'entry_not_found'];
    }

    $existingGroupId = (int)($existing['group_id'] ?? 0);
    if ((int)$existing['user_id'] !== $userId) {
        if ($existingGroupId <= 0) {
            return ['ok' => false, 'error' => 'access_denied'];
        }

        $scope = ql_ledger_group_scope($existingGroupId, $userId);
        if (!$scope || empty($scope['can_write_group_ledger'])) {
            return ['ok' => false, 'error' => 'access_denied'];
        }
    }

    $entryType = $input['entry_type'] ?? $existing['entry_type'];
    $moneyType = $input['money_type'] ?? $existing['money_type'];
    $amount = ql_money_amount($input['amount'] ?? $existing['amount']);
    $categoryId = array_key_exists('category_id', $input) && $input['category_id'] !== '' ? (int)$input['category_id'] : ($existing['category_id'] ?? null);
    $purpose = trim((string)($input['purpose'] ?? $existing['purpose']));
    $note = array_key_exists('note', $input) ? trim((string)$input['note']) : $existing['note'];

    if (!in_array($entryType, ['income', 'expense'], true)) {
        return ['ok' => false, 'error' => 'invalid_entry_type'];
    }

    if (!in_array($moneyType, ['cash', 'noncash'], true)) {
        return ['ok' => false, 'error' => 'invalid_money_type'];
    }

    if ($amount === null) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }

    if ($purpose === '') {
        return ['ok' => false, 'error' => 'empty_purpose'];
    }

    if (!ql_category_allowed($categoryId, $entryType, $userId, $existingGroupId)) {
        return ['ok' => false, 'error' => 'invalid_category'];
    }

    $stmt = ql_db()->prepare("
        UPDATE ledger_entries
        SET
            entry_type = ?,
            money_type = ?,
            category_id = ?,
            amount = ?,
            purpose = ?,
            note = ?,
            edited_at = NOW()
        WHERE id = ?
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        $entryType,
        $moneyType,
        $categoryId,
        $amount,
        $purpose,
        $note ?: null,
        $entryId
    ]);

    if ($existingGroupId > 0 && function_exists('ql_on_the_go_sync_empty_group_tape_base')) {
        ql_on_the_go_sync_empty_group_tape_base($userId, $existingGroupId);
    }

    return [
        'ok' => true,
        'entry' => ql_ledger_visible_entry($entryId, $userId)
    ];
}


function ql_ledger_delete(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];

    $entryId = (int)($input['id'] ?? 0);

    if ($entryId <= 0) {
        return ['ok' => false, 'error' => 'invalid_entry_id'];
    }

    $existing = ql_ledger_visible_entry($entryId, $userId);

    if (!$existing) {
        return ['ok' => false, 'error' => 'entry_not_found'];
    }

    $existingGroupId = (int)($existing['group_id'] ?? 0);
    if ((int)$existing['user_id'] !== $userId) {
        if ($existingGroupId <= 0) {
            return ['ok' => false, 'error' => 'access_denied'];
        }

        $scope = ql_ledger_group_scope($existingGroupId, $userId);
        if (!$scope || empty($scope['can_write_group_ledger'])) {
            return ['ok' => false, 'error' => 'access_denied'];
        }
    }

    $stmt = ql_db()->prepare("
        UPDATE ledger_entries
        SET deleted_at = NOW(), edited_at = NOW()
        WHERE id = ?
          AND deleted_at IS NULL
    ");

    $stmt->execute([$entryId]);

    if ($existingGroupId > 0 && function_exists('ql_on_the_go_sync_empty_group_tape_base')) {
        ql_on_the_go_sync_empty_group_tape_base($userId, $existingGroupId);
    }

    return ['ok' => true];
}


function ql_ledger_upload_file(): array
{
    $user = ql_require_user();

    $entryId = (int)($_POST['entry_id'] ?? 0);

    if ($entryId <= 0) {
        return ['ok' => false, 'error' => 'invalid_entry_id'];
    }

    $entry = ql_ledger_get_one($entryId, (int)$user['id']);

    if (!$entry) {
        return ['ok' => false, 'error' => 'entry_not_found'];
    }

    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        return ['ok' => false, 'error' => 'file_missing'];
    }

    $file = $_FILES['file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'upload_error', 'code' => (int)$file['error']];
    }

    $maxBytes = 8 * 1024 * 1024;

    if ((int)$file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'file_too_large'];
    }

    $original = (string)($file['name'] ?? 'file');
    $tmp = (string)$file['tmp_name'];

    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $tmp);
            if ($detected) {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    $allowed = [
        'image/jpeg' => ['jpg', 'image'],
        'image/png' => ['png', 'image'],
        'image/webp' => ['webp', 'image'],
        'application/pdf' => ['pdf', 'document'],
        'text/plain' => ['txt', 'document'],
        'application/msword' => ['doc', 'document'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx', 'document'],
    ];

    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'file_type_not_allowed', 'mime' => $mime];
    }

    [$ext, $kind] = $allowed[$mime];

    $baseDir = dirname(__DIR__) . '/storage/documents/' . date('Y') . '/' . date('m');

    if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
        return ['ok' => false, 'error' => 'storage_not_writable'];
    }

    $stored = 'entry_' . $entryId . '_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $target = $baseDir . '/' . $stored;

    if (!move_uploaded_file($tmp, $target)) {
        return ['ok' => false, 'error' => 'move_failed'];
    }

    chmod($target, 0640);

    $relativePath = 'documents/' . date('Y') . '/' . date('m') . '/' . $stored;

    $stmt = ql_db()->prepare("
        INSERT INTO entry_files
            (entry_id, user_id, file_original_name, file_stored_name, file_path, file_mime, file_size, file_kind)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $entryId,
        (int)$user['id'],
        $original,
        $stored,
        $relativePath,
        $mime,
        (int)$file['size'],
        $kind
    ]);

    return [
        'ok' => true,
        'file' => [
            'id' => (int)ql_db()->lastInsertId(),
            'entry_id' => $entryId,
            'original_name' => $original,
            'mime' => $mime,
            'size' => (int)$file['size'],
            'kind' => $kind
        ]
    ];
}


function ql_ledger_report(array $input): array
{
    $user = ql_require_user();

    $period = (string)($input['period'] ?? 'month');
    $today = new DateTimeImmutable('today');

    if ($period === 'today') {
        $from = $today;
        $to = $today;
    } elseif ($period === 'custom') {
        $fromRaw = (string)($input['from'] ?? '');
        $toRaw = (string)($input['to'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)) {
            return ['ok' => false, 'error' => 'invalid_period'];
        }

        $from = new DateTimeImmutable($fromRaw);
        $to = new DateTimeImmutable($toRaw);

        if ($to < $from) {
            return ['ok' => false, 'error' => 'period_reversed'];
        }
    } else {
        $from = $today->modify('first day of this month');
        $to = $today->modify('last day of this month');
    }

    $fromSql = $from->format('Y-m-d') . ' 00:00:00';
    $toSql = $to->format('Y-m-d') . ' 23:59:59';

    $groupId = ql_ledger_input_group_id($input);
    $scope = null;

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, (int)$user['id']);

        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        if (!empty($scope['can_view_group_reports'])) {
            $where = "group_id = ? AND deleted_at IS NULL AND entry_datetime BETWEEN ? AND ?";
            $params = [$groupId, $fromSql, $toSql];
            $beforeWhere = "group_id = ? AND deleted_at IS NULL AND entry_datetime < ?";
            $beforeParams = [$groupId, $fromSql];
        } else {
            $where = "group_id = ? AND user_id = ? AND deleted_at IS NULL AND entry_datetime BETWEEN ? AND ?";
            $params = [$groupId, (int)$user['id'], $fromSql, $toSql];
            $beforeWhere = "group_id = ? AND user_id = ? AND deleted_at IS NULL AND entry_datetime < ?";
            $beforeParams = [$groupId, (int)$user['id'], $fromSql];
        }
    } else {
        $where = "user_id = ? AND group_id IS NULL AND deleted_at IS NULL AND entry_datetime BETWEEN ? AND ?";
        $params = [(int)$user['id'], $fromSql, $toSql];
        $beforeWhere = "user_id = ? AND group_id IS NULL AND deleted_at IS NULL AND entry_datetime < ?";
        $beforeParams = [(int)$user['id'], $fromSql];
    }

    $stmt = ql_db()->prepare("
        SELECT
            entry_type,
            money_type,
            SUM(amount) AS total,
            COUNT(*) AS records
        FROM ledger_entries
        WHERE {$where}
        GROUP BY entry_type, money_type
    ");

    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [
        'income' => 0.0,
        'expense' => 0.0,
        'balance' => 0.0,
        'cash_income' => 0.0,
        'cash_expense' => 0.0,
        'cash_balance' => 0.0,
        'noncash_income' => 0.0,
        'noncash_expense' => 0.0,
        'noncash_balance' => 0.0,
        'records' => 0,
    ];

    foreach ($rows as $row) {
        $amount = (float)$row['total'];
        $records = (int)$row['records'];
        $result['records'] += $records;

        if ($row['entry_type'] === 'income') {
            $result['income'] += $amount;
            if ($row['money_type'] === 'cash') {
                $result['cash_income'] += $amount;
            } else {
                $result['noncash_income'] += $amount;
            }
        } else {
            $result['expense'] += $amount;
            if ($row['money_type'] === 'cash') {
                $result['cash_expense'] += $amount;
            } else {
                $result['noncash_expense'] += $amount;
            }
        }
    }

    $virtualPeriodEntries = [];
    if ($groupId > 0) {
        $virtualPeriodEntries = ql_ledger_virtual_on_the_go_entries(
            $groupId,
            !empty($scope['can_view_group_reports']) ? null : (int)$user['id'],
            $fromSql,
            $toSql
        );
        foreach ($virtualPeriodEntries as $entry) {
            ql_ledger_apply_entry_to_summary($result, $entry);
        }
    }

    $result['balance'] = $result['income'] - $result['expense'];
    $result['cash_balance'] = $result['cash_income'] - $result['cash_expense'];
    $result['noncash_balance'] = $result['noncash_income'] - $result['noncash_expense'];

    foreach ($result as $key => $value) {
        if ($key !== 'records') {
            $result[$key] = round((float)$value, 2);
        }
    }

    $beforeStmt = ql_db()->prepare("
        SELECT
            entry_type,
            money_type,
            SUM(amount) AS total
        FROM ledger_entries
        WHERE {$beforeWhere}
        GROUP BY entry_type, money_type
    ");
    $beforeStmt->execute($beforeParams);

    $opening = [
        'balance' => 0.0,
        'cash_balance' => 0.0,
        'noncash_balance' => 0.0,
    ];

    foreach ($beforeStmt->fetchAll() as $row) {
        $amount = (float)$row['total'];
        $sign = $row['entry_type'] === 'income' ? 1 : -1;
        $opening['balance'] += $sign * $amount;

        if ($row['money_type'] === 'cash') {
            $opening['cash_balance'] += $sign * $amount;
        } else {
            $opening['noncash_balance'] += $sign * $amount;
        }
    }

    if ($groupId > 0) {
        foreach (ql_ledger_virtual_on_the_go_entries(
            $groupId,
            !empty($scope['can_view_group_reports']) ? null : (int)$user['id'],
            null,
            date('Y-m-d H:i:s', strtotime($fromSql) - 1)
        ) as $entry) {
            $amount = (float)($entry['amount'] ?? 0);
            $sign = ($entry['entry_type'] ?? '') === 'income' ? 1 : -1;
            $opening['balance'] += $sign * $amount;
            if (($entry['money_type'] ?? '') === 'cash') {
                $opening['cash_balance'] += $sign * $amount;
            } else {
                $opening['noncash_balance'] += $sign * $amount;
            }
        }
    }

    foreach ($opening as $key => $value) {
        $opening[$key] = round((float)$value, 2);
    }

    $position = [
        'before' => $opening['balance'],
        'movement' => $result['balance'],
        'after' => round($opening['balance'] + $result['balance'], 2),
        'cash_before' => $opening['cash_balance'],
        'cash_movement' => $result['cash_balance'],
        'cash_after' => round($opening['cash_balance'] + $result['cash_balance'], 2),
        'noncash_before' => $opening['noncash_balance'],
        'noncash_movement' => $result['noncash_balance'],
        'noncash_after' => round($opening['noncash_balance'] + $result['noncash_balance'], 2),
    ];

    $sectionWhere = str_replace(['group_id', 'user_id', 'deleted_at', 'entry_datetime'], ['le.group_id', 'le.user_id', 'le.deleted_at', 'le.entry_datetime'], $where);

    $sectionStmt = ql_db()->prepare("
        SELECT
            COALESCE(lc.name, 'No section') AS section_name,
            le.entry_type,
            SUM(le.amount) AS total,
            COUNT(*) AS records
        FROM ledger_entries le
        LEFT JOIN ledger_categories lc ON lc.id = le.category_id
        WHERE {$sectionWhere}
        GROUP BY section_name, le.entry_type
        ORDER BY section_name ASC
    ");
    $sectionStmt->execute($params);

    $sectionsMap = [];

    foreach ($sectionStmt->fetchAll() as $row) {
        $name = ql_ledger_report_section_label((string)($row['section_name'] ?: 'No section'));

        if (!isset($sectionsMap[$name])) {
            $sectionsMap[$name] = [
                'name' => $name,
                'income' => 0.0,
                'expense' => 0.0,
                'balance' => 0.0,
                'records' => 0,
            ];
        }

        $amount = (float)$row['total'];

        if ($row['entry_type'] === 'income') {
            $sectionsMap[$name]['income'] += $amount;
        } else {
            $sectionsMap[$name]['expense'] += $amount;
        }

        $sectionsMap[$name]['records'] += (int)$row['records'];
        $sectionsMap[$name]['balance'] = $sectionsMap[$name]['income'] - $sectionsMap[$name]['expense'];
    }

    foreach ($virtualPeriodEntries as $entry) {
        $name = ql_ledger_report_section_label((string)($entry['category_name'] ?? 'Живой отчет'));
        if (!isset($sectionsMap[$name])) {
            $sectionsMap[$name] = [
                'name' => $name,
                'income' => 0.0,
                'expense' => 0.0,
                'balance' => 0.0,
                'records' => 0,
            ];
        }
        $amount = (float)($entry['amount'] ?? 0);
        if (($entry['entry_type'] ?? '') === 'income') {
            $sectionsMap[$name]['income'] += $amount;
        } else {
            $sectionsMap[$name]['expense'] += $amount;
        }
        $sectionsMap[$name]['records'] += 1;
        $sectionsMap[$name]['balance'] = $sectionsMap[$name]['income'] - $sectionsMap[$name]['expense'];
    }

    $sections = array_values(array_map(function ($section) {
        $section['income'] = round((float)$section['income'], 2);
        $section['expense'] = round((float)$section['expense'], 2);
        $section['balance'] = round((float)$section['balance'], 2);
        return $section;
    }, $sectionsMap));


    $members = [];

    if ($groupId > 0 && $scope && !empty($scope['can_view_group_reports'])) {
        $memberWhere = "le.group_id = ? AND le.deleted_at IS NULL AND le.entry_datetime BETWEEN ? AND ?";
        $memberParams = [$groupId, $fromSql, $toSql];

        $memberStmt = ql_db()->prepare("
            SELECT
                le.user_id,
                COALESCE(gm.display_name, u.display_name, u.email) AS member_name,
                u.email,
                le.entry_type,
                SUM(le.amount) AS total,
                COUNT(*) AS records
            FROM ledger_entries le
            JOIN users u ON u.id = le.user_id
            LEFT JOIN group_members gm ON gm.group_id = le.group_id AND gm.user_id = le.user_id
            WHERE {$memberWhere}
            GROUP BY le.user_id, member_name, u.email, le.entry_type
            ORDER BY member_name ASC
        ");
        $memberStmt->execute($memberParams);

        $memberMap = [];

        foreach ($memberStmt->fetchAll() as $row) {
            $id = (int)$row['user_id'];

            if (!isset($memberMap[$id])) {
                $memberMap[$id] = [
                    'user_id' => $id,
                    'name' => $row['member_name'],
                    'email' => $row['email'],
                    'income' => 0.0,
                    'expense' => 0.0,
                    'balance' => 0.0,
                    'records' => 0,
                ];
            }

            $amount = (float)$row['total'];

            if ($row['entry_type'] === 'income') {
                $memberMap[$id]['income'] += $amount;
            } else {
                $memberMap[$id]['expense'] += $amount;
            }

            $memberMap[$id]['records'] += (int)$row['records'];
            $memberMap[$id]['balance'] = $memberMap[$id]['income'] - $memberMap[$id]['expense'];
        }

        foreach ($virtualPeriodEntries as $entry) {
            $id = (int)($entry['user_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (!isset($memberMap[$id])) {
                $memberMap[$id] = [
                    'user_id' => $id,
                    'name' => $entry['owner_display_name'] ?? 'Участник',
                    'email' => $entry['owner_email'] ?? '',
                    'income' => 0.0,
                    'expense' => 0.0,
                    'balance' => 0.0,
                    'records' => 0,
                ];
            }
            $amount = (float)($entry['amount'] ?? 0);
            if (($entry['entry_type'] ?? '') === 'income') {
                $memberMap[$id]['income'] += $amount;
            } else {
                $memberMap[$id]['expense'] += $amount;
            }
            $memberMap[$id]['records'] += 1;
            $memberMap[$id]['balance'] = $memberMap[$id]['income'] - $memberMap[$id]['expense'];
        }

        $members = array_values(array_map(function ($member) {
            $member['income'] = round((float)$member['income'], 2);
            $member['expense'] = round((float)$member['expense'], 2);
            $member['balance'] = round((float)$member['balance'], 2);
            return $member;
        }, $memberMap));
    }

    $remainingRaw = $input['remaining'] ?? null;
    $remaining = null;
    $adjustment = null;

    if ($remainingRaw !== null && $remainingRaw !== '') {
        $remaining = ql_money_amount($remainingRaw);
        if ($remaining !== null) {
            $remaining = (float)$remaining;
            $adjustment = round($remaining - $result['balance'], 2);
        }
    }

    return [
        'ok' => true,
        'period' => [
            'type' => $period,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ],
        'summary' => $result,
        'position' => $position,
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'personal',
            'group_id' => $groupId ?: null,
            'is_admin' => $scope['is_admin'] ?? false,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'sections' => $sections,
        'members' => $members,
        'remaining' => $remaining,
        'adjustment' => $adjustment,
    ];
}
