<?php

require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/on_the_go.php';

function ql_advance_decimal($value, bool $allowZero = true): ?string
{
    $raw = str_replace(',', '.', trim((string)$value));

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return null;
    }

    $amount = round((float)$raw, 2);
    if ($amount < 0 || (!$allowZero && $amount <= 0)) {
        return null;
    }

    return number_format($amount, 2, '.', '');
}

function ql_advance_currency($value): string
{
    $currency = strtoupper(trim((string)$value));
    return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';
}

function ql_advance_pending_marker_prefix(): string
{
    return '__QL_TRANSFER_PENDING__';
}

function ql_advance_pending_note_payload($note): ?array
{
    $raw = trim((string)$note);
    $prefix = ql_advance_pending_marker_prefix();
    if ($raw === '' || strpos($raw, $prefix) !== 0) {
        return null;
    }

    $json = trim(substr($raw, strlen($prefix)));
    if ($json === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function ql_advance_pending_note(array $payload = []): string
{
    $base = [
        'state' => 'pending',
        'issued_at' => date('Y-m-d H:i:s'),
    ];

    return ql_advance_pending_marker_prefix() . json_encode(
        array_merge($base, $payload),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function ql_advance_clean_moderation_note($note): ?string
{
    $raw = trim((string)$note);
    if ($raw === '') {
        return null;
    }

    return ql_advance_pending_note_payload($raw) !== null ? null : $raw;
}

function ql_advance_is_pending_transfer(array $advance): bool
{
    return (string)($advance['status'] ?? '') === 'issued'
        && ql_advance_pending_note_payload($advance['moderation_note'] ?? null) !== null;
}

function ql_advance_transfer_public_flags(array $advance): array
{
    $status = (string)($advance['status'] ?? '');
    $pending = ql_advance_is_pending_transfer($advance);

    if ($pending) {
        $state = 'pending';
    } elseif ($status === 'issued') {
        $state = 'active';
    } elseif ($status === 'submitted') {
        $state = 'ready_report';
    } elseif ($status === 'discrepancy') {
        $state = 'ready_report_discrepancy';
    } elseif ($status === 'returned') {
        $state = 'rework';
    } elseif ($status === 'accepted') {
        $state = 'accepted';
    } elseif ($status === 'closed') {
        $state = 'closed';
    } else {
        $state = $status !== '' ? $status : 'unknown';
    }

    return [
        'transfer_pending' => $pending,
        'transfer_active' => $status === 'issued' && !$pending,
        'transfer_state' => $state,
        'transfer_pending_meta' => $pending ? ql_advance_pending_note_payload($advance['moderation_note'] ?? null) : null,
    ];
}

function ql_advance_open_for_totals_sql(string $alias = 'ca'): string
{
    $like = str_replace("'", "''", ql_advance_pending_marker_prefix() . '%');
    return "({$alias}.status IN ('issued', 'submitted', 'returned', 'discrepancy')"
        . " AND NOT ({$alias}.status = 'issued' AND COALESCE({$alias}.moderation_note, '') LIKE '{$like}'))";
}

function ql_advance_tape_pending_transfer(int $tapeId, int $userId = 0): ?array
{
    if ($tapeId <= 0) {
        return null;
    }

    $params = [$tapeId];
    $userWhere = '';
    if ($userId > 0) {
        $userWhere = ' AND assigned_to_user_id = ?';
        $params[] = $userId;
    }

    $stmt = ql_db()->prepare("
        SELECT *
        FROM cash_advances
        WHERE on_the_go_tape_id = ?
          AND deleted_at IS NULL
          {$userWhere}
        LIMIT 1
    ");
    $stmt->execute($params);
    $row = $stmt->fetch();

    return ($row && ql_advance_is_pending_transfer($row)) ? $row : null;
}

function ql_advance_scope(int $groupId, int $userId): ?array
{
    $membership = ql_group_membership($groupId, $userId);
    if (!$membership) {
        return null;
    }

    $permissions = $membership['permissions'] ?? [];
    if (!is_array($permissions)) {
        $permissions = [];
    }

    $accessLevel = (string)($membership['access_level'] ?? 'base');
    $isAdmin = ($membership['role'] ?? '') === 'admin' || $accessLevel === 'advanced';

    return [
        'membership' => $membership,
        'access_level' => $accessLevel,
        'is_admin' => $isAdmin,
        'can_moderate' => $isAdmin || !empty($permissions['can_moderate']) || in_array($accessLevel, ['manager', 'advanced'], true),
        'can_view_group_reports' => $isAdmin || !empty($permissions['can_view_group_reports']) || in_array($accessLevel, ['manager', 'advanced'], true),
        'can_manage_money' => $isAdmin || !empty($permissions['can_manage_money']),
    ];
}

function ql_advance_can_see_group(array $scope): bool
{
    return !empty($scope['can_moderate']) || !empty($scope['can_view_group_reports']) || !empty($scope['can_manage_money']) || !empty($scope['is_admin']);
}

function ql_advance_tape_summary(int $tapeId): array
{
    $tapeStmt = ql_db()->prepare("
        SELECT cash_received
        FROM on_the_go_tapes
        WHERE id = ?
        LIMIT 1
    ");
    $tapeStmt->execute([$tapeId]);
    $adminCashIn = round((float)($tapeStmt->fetchColumn() ?: 0), 2);

    $stmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN capture_type = 'cash_in' THEN amount ELSE 0 END), 0) AS extra_cash_in,
            COALESCE(SUM(CASE WHEN capture_type = 'cash_out' THEN amount ELSE 0 END), 0) AS cash_out,
            COALESCE(SUM(CASE WHEN capture_type = 'noncash_out' THEN amount ELSE 0 END), 0) AS card_out,
            COUNT(id) AS records_count,
            SUM(CASE WHEN review_status = 'needs_review' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN reportable = 1 THEN 1 ELSE 0 END) AS reportable_count
        FROM on_the_go_captures
        WHERE tape_id = ?
          AND review_status <> 'archived'
          AND capture_type IN ('cash_in', 'cash_out', 'noncash_out')
    ");
    $stmt->execute([$tapeId]);
    $row = $stmt->fetch() ?: [];

    $extraCashIn = round((float)($row['extra_cash_in'] ?? 0), 2);
    $cashIn = round($adminCashIn + $extraCashIn, 2);
    $cashOut = round((float)($row['cash_out'] ?? 0), 2);
    $cardOut = round((float)($row['card_out'] ?? 0), 2);

    return [
        'admin_cash_in' => $adminCashIn,
        'extra_cash_in' => $extraCashIn,
        'cash_in' => $cashIn,
        'cash_out' => $cashOut,
        'card_out' => $cardOut,
        'cash_left' => round($cashIn - $cashOut, 2),
        'records_count' => (int)($row['records_count'] ?? 0),
        'pending_count' => (int)($row['pending_count'] ?? 0),
        'reportable_count' => (int)($row['reportable_count'] ?? 0),
    ];
}

function ql_advance_row(int $advanceId): ?array
{
    if ($advanceId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT
            ca.*,
            g.name AS group_name,
            issued.email AS issued_by_email,
            COALESCE(issued_gm.display_name, issued.display_name, issued.email) AS issued_by_display_name,
            assigned.email AS assigned_to_email,
            COALESCE(assigned_gm.display_name, assigned.display_name, assigned.email) AS assigned_to_display_name
        FROM cash_advances ca
        JOIN groups g ON g.id = ca.group_id
        JOIN users issued ON issued.id = ca.issued_by_user_id
        JOIN users assigned ON assigned.id = ca.assigned_to_user_id
        LEFT JOIN group_members issued_gm ON issued_gm.group_id = ca.group_id AND issued_gm.user_id = ca.issued_by_user_id
        LEFT JOIN group_members assigned_gm ON assigned_gm.group_id = ca.group_id AND assigned_gm.user_id = ca.assigned_to_user_id
        WHERE ca.id = ?
          AND ca.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$advanceId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function ql_advance_visible_row(int $advanceId, int $userId): ?array
{
    $advance = ql_advance_row($advanceId);
    if (!$advance) {
        return null;
    }

    if ((int)$advance['assigned_to_user_id'] === $userId || (int)$advance['issued_by_user_id'] === $userId) {
        return $advance;
    }

    $scope = ql_advance_scope((int)$advance['group_id'], $userId);
    if ($scope && ql_advance_can_see_group($scope)) {
        return $advance;
    }

    return null;
}

function ql_advance_public(array $advance): array
{
    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    $flags = ql_advance_transfer_public_flags($advance);
    $advance['session_snapshot'] = $tapeId > 0 ? ql_on_the_go_tape_primary_session_snapshot($tapeId, 'cash') : null;
    $advance['summary'] = $tapeId > 0 ? ql_advance_tape_summary($tapeId) : [
        'cash_in' => round((float)($advance['amount'] ?? 0), 2),
        'cash_out' => 0.0,
        'card_out' => 0.0,
        'cash_left' => round((float)($advance['amount'] ?? 0), 2),
        'records_count' => 0,
        'pending_count' => 0,
        'reportable_count' => 0,
    ];
    $effectiveLeft = $advance['actual_remaining'] !== null
        ? (float)$advance['actual_remaining']
        : (float)($advance['summary']['cash_left'] ?? 0);
    $advance['summary']['effective_cash_left'] = round($flags['transfer_pending'] ? 0 : $effectiveLeft, 2);
    $advance['summary']['active_cash_left'] = round($flags['transfer_pending'] ? 0 : $effectiveLeft, 2);
    $advance['summary']['pending_amount'] = $flags['transfer_pending'] ? round((float)($advance['amount'] ?? 0), 2) : 0.0;
    $advance['moderation_note'] = ql_advance_clean_moderation_note($advance['moderation_note'] ?? null);
    $advance = array_merge($advance, $flags);

    return $advance;
}

function ql_advance_totals(int $groupId, ?int $assignedUserId = null): array
{
    if ($groupId <= 0) {
        return [
            'open_issued' => 0.0,
            'open_spent' => 0.0,
            'open_cash_spent' => 0.0,
            'open_card_spent' => 0.0,
            'open_cash_left' => 0.0,
            'open_count' => 0,
            'accepted_spent' => 0.0,
            'accepted_cash_spent' => 0.0,
            'accepted_card_spent' => 0.0,
            'accepted_count' => 0,
            'accepted_records' => 0,
        ];
    }

    $params = [$groupId];
    $ownerWhere = '';
    if ($assignedUserId !== null && $assignedUserId > 0) {
        $ownerWhere = ' AND ca.assigned_to_user_id = ?';
        $params[] = $assignedUserId;
    }

    $openWhere = ql_advance_open_for_totals_sql('ca');
    $stmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN {$openWhere} THEN ca.amount ELSE 0 END), 0) AS open_issued,
            COALESCE(SUM(CASE WHEN {$openWhere} THEN COALESCE(s.cash_out, 0) + COALESCE(s.card_out, 0) ELSE 0 END), 0) AS open_spent,
            COALESCE(SUM(CASE WHEN {$openWhere} THEN COALESCE(s.cash_out, 0) ELSE 0 END), 0) AS open_cash_spent,
            COALESCE(SUM(CASE WHEN {$openWhere} THEN COALESCE(s.card_out, 0) ELSE 0 END), 0) AS open_card_spent,
            COALESCE(SUM(
                CASE
                    WHEN {$openWhere} THEN
                        CASE
                            WHEN ca.actual_remaining IS NOT NULL THEN ca.actual_remaining
                            ELSE COALESCE(t.cash_received, ca.amount) + COALESCE(s.extra_cash_in, 0) - COALESCE(s.cash_out, 0)
                        END
                    ELSE 0
                END
            ), 0) AS open_cash_left,
            SUM(CASE WHEN {$openWhere} THEN 1 ELSE 0 END) AS open_count,
            COALESCE(SUM(CASE WHEN ca.status = 'accepted' THEN COALESCE(s.cash_out, 0) + COALESCE(s.card_out, 0) ELSE 0 END), 0) AS accepted_spent,
            COALESCE(SUM(CASE WHEN ca.status = 'accepted' THEN COALESCE(s.cash_out, 0) ELSE 0 END), 0) AS accepted_cash_spent,
            COALESCE(SUM(CASE WHEN ca.status = 'accepted' THEN COALESCE(s.card_out, 0) ELSE 0 END), 0) AS accepted_card_spent,
            SUM(CASE WHEN ca.status = 'accepted' AND COALESCE(s.records_count, 0) > 0 THEN 1 ELSE 0 END) AS accepted_count,
            COALESCE(SUM(CASE WHEN ca.status = 'accepted' THEN COALESCE(s.records_count, 0) ELSE 0 END), 0) AS accepted_records
        FROM cash_advances ca
        LEFT JOIN on_the_go_tapes t ON t.id = ca.on_the_go_tape_id
        LEFT JOIN (
            SELECT
                tape_id,
                COALESCE(SUM(CASE WHEN capture_type = 'cash_in' THEN amount ELSE 0 END), 0) AS extra_cash_in,
                COALESCE(SUM(CASE WHEN capture_type = 'cash_out' THEN amount ELSE 0 END), 0) AS cash_out,
                COALESCE(SUM(CASE WHEN capture_type = 'noncash_out' THEN amount ELSE 0 END), 0) AS card_out,
                COUNT(id) AS records_count
            FROM on_the_go_captures
            WHERE review_status <> 'archived'
              AND capture_type IN ('cash_in', 'cash_out', 'noncash_out')
            GROUP BY tape_id
        ) s ON s.tape_id = t.id
        WHERE ca.group_id = ?
          AND ca.deleted_at IS NULL
          {$ownerWhere}
    ");
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];

    return [
        'open_issued' => round((float)($row['open_issued'] ?? 0), 2),
        'open_spent' => round((float)($row['open_spent'] ?? 0), 2),
        'open_cash_spent' => round((float)($row['open_cash_spent'] ?? 0), 2),
        'open_card_spent' => round((float)($row['open_card_spent'] ?? 0), 2),
        'open_cash_left' => round((float)($row['open_cash_left'] ?? 0), 2),
        'open_count' => (int)($row['open_count'] ?? 0),
        'accepted_spent' => round((float)($row['accepted_spent'] ?? 0), 2),
        'accepted_cash_spent' => round((float)($row['accepted_cash_spent'] ?? 0), 2),
        'accepted_card_spent' => round((float)($row['accepted_card_spent'] ?? 0), 2),
        'accepted_count' => (int)($row['accepted_count'] ?? 0),
        'accepted_records' => (int)($row['accepted_records'] ?? 0),
    ];
}

function ql_advance_create(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];

    $groupId = (int)($input['group_id'] ?? 0);
    $assignedUserId = (int)($input['assigned_to_user_id'] ?? 0);
    $amount = ql_advance_decimal($input['amount'] ?? '', false);
    $currency = ql_advance_currency($input['currency'] ?? 'EUR');

    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        $title = 'Pocket advance';
    }
    if (mb_strlen($title) > 190) {
        $title = mb_substr($title, 0, 190);
    }

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }
    if ($assignedUserId <= 0) {
        return ['ok' => false, 'error' => 'invalid_assigned_user'];
    }
    if ($amount === null) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }

    $scope = ql_advance_scope($groupId, $userId);
    if (!$scope) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (empty($scope['can_manage_money'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'advanced'];
    }

    $assigned = ql_group_membership($groupId, $assignedUserId);
    if (!$assigned) {
        return ['ok' => false, 'error' => 'assigned_user_not_group_member'];
    }

    $db = ql_db();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO cash_advances
                (group_id, issued_by_user_id, assigned_to_user_id, title, amount, currency, status, moderation_note)
            VALUES
                (?, ?, ?, ?, ?, ?, 'issued', ?)
        ");
        $stmt->execute([
            $groupId,
            $userId,
            $assignedUserId,
            $title,
            $amount,
            $currency,
            ql_advance_pending_note([
                'issued_by_user_id' => $userId,
                'assigned_to_user_id' => $assignedUserId,
                'amount' => $amount,
                'currency' => $currency,
                'title' => $title,
            ])
        ]);
        $advanceId = (int)$db->lastInsertId();

        $tapeStmt = $db->prepare("
            INSERT INTO on_the_go_tapes
                (user_id, group_id, advance_id, stream_type, title, cash_received, currency, status)
            VALUES
                (?, ?, ?, 'cash', ?, ?, ?, 'active')
        ");
        $tapeStmt->execute([$assignedUserId, $groupId, $advanceId, $title, $amount, $currency]);
        $tapeId = (int)$db->lastInsertId();

        $update = $db->prepare("
            UPDATE cash_advances
            SET on_the_go_tape_id = ?
            WHERE id = ?
            LIMIT 1
        ");
        $update->execute([$tapeId, $advanceId]);

        if (function_exists('ql_audit_write')) {
            ql_audit_write($userId, 'advance_issued', 'cash_advance', $advanceId, [
                'group_id' => $groupId,
                'assigned_to_user_id' => $assignedUserId,
                'amount' => $amount,
                'currency' => $currency,
                'title' => $title,
                'tape_id' => $tapeId,
                'transfer_state' => 'pending',
            ]);
        }
        $db->commit();
        ql_on_the_go_journal_append('advance_issued', $userId, $tapeId, [
            'group_id' => $groupId,
            'advance_id' => $advanceId,
            'assigned_to_user_id' => $assignedUserId,
            'amount' => $amount,
            'currency' => $currency,
            'title' => $title,
            'transfer_state' => 'pending',
        ]);

        return ['ok' => true, 'advance' => ql_advance_public(ql_advance_row($advanceId))];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'advance_create_failed', 'message' => $e->getMessage()];
    }
}

function ql_advance_list(array $input = []): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $status = trim((string)($input['status'] ?? ''));
    $limit = (int)($input['limit'] ?? 100);

    if ($limit < 1 || $limit > 300) {
        $limit = 100;
    }

    $params = [];
    $where = "ca.deleted_at IS NULL";
    $scope = null;

    $totalsOwnerUserId = null;
    if ($groupId > 0) {
        $scope = ql_advance_scope($groupId, $userId);
        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        if (ql_advance_can_see_group($scope)) {
            $where .= " AND ca.group_id = ?";
            $params[] = $groupId;
        } else {
            $where .= " AND ca.group_id = ? AND ca.assigned_to_user_id = ?";
            $params[] = $groupId;
            $params[] = $userId;
            $totalsOwnerUserId = $userId;
        }
    } else {
        $where .= " AND ca.assigned_to_user_id = ?";
        $params[] = $userId;
    }

    if ($status !== '' && in_array($status, ['issued','submitted','accepted','returned','closed','discrepancy'], true)) {
        $where .= " AND ca.status = ?";
        $params[] = $status;
    }

    $pendingLike = str_replace("'", "''", ql_advance_pending_marker_prefix() . '%');
    $sql = "
        SELECT
            ca.*,
            g.name AS group_name,
            issued.email AS issued_by_email,
            COALESCE(issued_gm.display_name, issued.display_name, issued.email) AS issued_by_display_name,
            assigned.email AS assigned_to_email,
            COALESCE(assigned_gm.display_name, assigned.display_name, assigned.email) AS assigned_to_display_name
        FROM cash_advances ca
        JOIN groups g ON g.id = ca.group_id
        JOIN users issued ON issued.id = ca.issued_by_user_id
        JOIN users assigned ON assigned.id = ca.assigned_to_user_id
        LEFT JOIN group_members issued_gm ON issued_gm.group_id = ca.group_id AND issued_gm.user_id = ca.issued_by_user_id
        LEFT JOIN group_members assigned_gm ON assigned_gm.group_id = ca.group_id AND assigned_gm.user_id = ca.assigned_to_user_id
        WHERE {$where}
        ORDER BY
            CASE ca.status
                WHEN 'issued' THEN CASE WHEN COALESCE(ca.moderation_note, '') LIKE '{$pendingLike}' THEN 0 ELSE 3 END
                WHEN 'submitted' THEN 1
                WHEN 'discrepancy' THEN 2
                WHEN 'returned' THEN 4
                WHEN 'accepted' THEN 5
                ELSE 6
            END,
            ca.created_at DESC,
            ca.id DESC
        LIMIT {$limit}
    ";

    $stmt = ql_db()->prepare($sql);
    $stmt->execute($params);
    $advances = array_map('ql_advance_public', $stmt->fetchAll());

    return [
        'ok' => true,
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'assigned',
            'group_id' => $groupId ?: null,
            'access_level' => $scope['access_level'] ?? null,
            'can_moderate' => $scope['can_moderate'] ?? false,
            'can_manage_money' => $scope['can_manage_money'] ?? false,
        ],
        'advances' => $advances,
        'totals' => $groupId > 0 ? ql_advance_totals($groupId, $totalsOwnerUserId) : null
    ];
}

function ql_advance_detail(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? $input['advance_id'] ?? 0);

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }

    $groupId = (int)$advance['group_id'];
    $scope = ql_advance_scope($groupId, $userId);
    $isAssigned = (int)$advance['assigned_to_user_id'] === $userId;

    if (!$isAssigned && (!$scope || !ql_advance_can_see_group($scope))) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    $assignedUserId = (int)$advance['assigned_to_user_id'];
    $items = [];

    if ($tapeId > 0) {
        $stmt = ql_db()->prepare("
            SELECT
                c.id,
                c.user_id,
                c.tape_id,
                c.session_id,
                c.capture_type,
                c.amount,
                c.currency,
                c.description,
                c.review_status,
                c.reportable,
                c.created_at,
                c.updated_at,
                s.session_type,
                s.status AS session_status,
                (
                    SELECT COUNT(*)
                    FROM on_the_go_files f
                    WHERE f.capture_id = c.id
                ) AS files_count
            FROM on_the_go_captures c
            LEFT JOIN on_the_go_sessions s ON s.id = c.session_id
            WHERE c.user_id = ?
              AND c.tape_id = ?
              AND c.review_status <> 'archived'
            ORDER BY c.created_at ASC, c.id ASC
        ");
        $stmt->execute([$assignedUserId, $tapeId]);
        $items = $stmt->fetchAll();
    }

    return [
        'ok' => true,
        'scope' => [
            'is_assigned' => $isAssigned,
            'can_moderate' => $scope['can_moderate'] ?? false,
            'can_manage_money' => $scope['can_manage_money'] ?? false,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'advance' => ql_advance_public($advance),
        'items' => $items,
    ];
}

function ql_advance_submit(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);
    $actualRemaining = ql_advance_decimal($input['actual_remaining'] ?? '', true);
    $note = trim((string)($input['note'] ?? ''));

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }
    if ($actualRemaining === null) {
        return ['ok' => false, 'error' => 'invalid_actual_remaining'];
    }
    if (mb_strlen($note) > 5000) {
        $note = mb_substr($note, 0, 5000);
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }
    if ((int)$advance['assigned_to_user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    if (ql_advance_is_pending_transfer($advance)) {
        return ['ok' => false, 'error' => 'advance_transfer_pending_confirmation_required'];
    }
    if (!in_array((string)$advance['status'], ['issued', 'returned', 'discrepancy'], true)) {
        return ['ok' => false, 'error' => 'invalid_advance_status'];
    }

    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    if ($tapeId <= 0) {
        return ['ok' => false, 'error' => 'advance_tape_missing'];
    }

    $summary = ql_advance_tape_summary($tapeId);
    $expected = round((float)$summary['cash_left'], 2);
    $actual = round((float)$actualRemaining, 2);
    $difference = round($actual - $expected, 2);
    $nextStatus = abs($difference) < 0.01 ? 'submitted' : 'discrepancy';

    $stmt = ql_db()->prepare("
        UPDATE cash_advances
        SET status = ?,
            expected_remaining = ?,
            actual_remaining = ?,
            difference_amount = ?,
            submitted_note = ?,
            submitted_at = NOW()
        WHERE id = ?
          AND assigned_to_user_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([
        $nextStatus,
        number_format($expected, 2, '.', ''),
        number_format($actual, 2, '.', ''),
        number_format($difference, 2, '.', ''),
        $note !== '' ? $note : null,
        $advanceId,
        $userId
    ]);

    $tapeStmt = ql_db()->prepare("
        UPDATE on_the_go_tapes
        SET submitted_at = NOW(),
            actual_remaining = ?,
            difference_amount = ?
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $tapeStmt->execute([
        number_format($actual, 2, '.', ''),
        number_format($difference, 2, '.', ''),
        $tapeId,
        $userId
    ]);

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'advance_submitted', 'cash_advance', $advanceId, [
            'group_id' => (int)$advance['group_id'],
            'status' => $nextStatus,
            'expected_remaining' => number_format($expected, 2, '.', ''),
            'actual_remaining' => number_format($actual, 2, '.', ''),
            'difference_amount' => number_format($difference, 2, '.', '')
        ]);
    }
    ql_on_the_go_journal_append('advance_submitted', $userId, $tapeId, [
        'group_id' => (int)$advance['group_id'],
        'advance_id' => $advanceId,
        'status' => $nextStatus,
        'expected_remaining' => number_format($expected, 2, '.', ''),
        'actual_remaining' => number_format($actual, 2, '.', ''),
        'difference_amount' => number_format($difference, 2, '.', ''),
    ]);

    return ['ok' => true, 'advance' => ql_advance_public(ql_advance_row($advanceId))];
}

function ql_advance_confirm(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }
    if ((int)$advance['assigned_to_user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    if ((string)$advance['status'] !== 'issued' || !ql_advance_is_pending_transfer($advance)) {
        return ['ok' => false, 'error' => 'advance_not_pending_transfer'];
    }

    $stmt = ql_db()->prepare("
        UPDATE cash_advances
        SET moderation_note = NULL,
            updated_at = NOW()
        WHERE id = ?
          AND assigned_to_user_id = ?
          AND status = 'issued'
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$advanceId, $userId]);

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'advance_transfer_confirmed', 'cash_advance', $advanceId, [
            'group_id' => (int)$advance['group_id'],
            'assigned_to_user_id' => $userId,
            'amount' => (string)($advance['amount'] ?? ''),
            'currency' => (string)($advance['currency'] ?? 'EUR'),
        ]);
    }
    ql_on_the_go_journal_append('advance_transfer_confirmed', $userId, (int)($advance['on_the_go_tape_id'] ?? 0), [
        'group_id' => (int)$advance['group_id'],
        'advance_id' => $advanceId,
        'assigned_to_user_id' => $userId,
        'amount' => (string)($advance['amount'] ?? ''),
        'currency' => (string)($advance['currency'] ?? 'EUR'),
    ]);

    return ['ok' => true, 'advance' => ql_advance_public(ql_advance_row($advanceId))];
}

function ql_advance_update_pending(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);
    $amount = ql_advance_decimal($input['amount'] ?? '', false);
    $currency = ql_advance_currency($input['currency'] ?? 'EUR');
    $title = trim((string)($input['title'] ?? ''));

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }
    if ($amount === null) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }
    if ($title === '') {
        $title = 'Деньги сотруднику';
    }
    if (mb_strlen($title) > 190) {
        $title = mb_substr($title, 0, 190);
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }

    $scope = ql_advance_scope((int)$advance['group_id'], $userId);
    if (!$scope || empty($scope['can_manage_money'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'advanced'];
    }
    if ((string)$advance['status'] !== 'issued' || !ql_advance_is_pending_transfer($advance)) {
        return ['ok' => false, 'error' => 'advance_not_pending_transfer'];
    }

    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    $pendingMeta = ql_advance_pending_note_payload($advance['moderation_note'] ?? null) ?: [];
    $pendingMeta['edited_at'] = date('Y-m-d H:i:s');
    $pendingMeta['edited_by_user_id'] = $userId;
    $pendingMeta['amount'] = $amount;
    $pendingMeta['currency'] = $currency;
    $pendingMeta['title'] = $title;

    $db = ql_db();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE cash_advances
            SET title = ?,
                amount = ?,
                currency = ?,
                moderation_note = ?,
                updated_at = NOW()
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            $title,
            $amount,
            $currency,
            ql_advance_pending_note($pendingMeta),
            $advanceId,
        ]);

        if ($tapeId > 0) {
            $tapeStmt = $db->prepare("
                UPDATE on_the_go_tapes
                SET title = ?,
                    cash_received = ?,
                    currency = ?,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $tapeStmt->execute([
                $title,
                $amount,
                $currency,
                $tapeId,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'advance_update_pending_failed', 'message' => $e->getMessage()];
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'advance_transfer_edited', 'cash_advance', $advanceId, [
            'group_id' => (int)$advance['group_id'],
            'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
            'amount' => $amount,
            'currency' => $currency,
            'title' => $title,
        ]);
    }
    ql_on_the_go_journal_append('advance_transfer_edited', $userId, $tapeId, [
        'group_id' => (int)$advance['group_id'],
        'advance_id' => $advanceId,
        'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
        'amount' => $amount,
        'currency' => $currency,
        'title' => $title,
    ]);

    return ['ok' => true, 'advance' => ql_advance_public(ql_advance_row($advanceId))];
}

function ql_advance_copy_files_to_entry(int $captureId, int $captureUserId, int $entryId): int
{
    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_files
        WHERE capture_id = ?
          AND user_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$captureId, $captureUserId]);
    $files = $stmt->fetchAll();

    if (!$files) {
        return 0;
    }

    $baseRoot = realpath(dirname(__DIR__));
    $ledgerDir = dirname(__DIR__) . '/storage/documents/' . date('Y') . '/' . date('m');

    if (!is_dir($ledgerDir) && !mkdir($ledgerDir, 0755, true)) {
        throw new RuntimeException('ledger_storage_not_writable');
    }

    $fileInsert = ql_db()->prepare("
        INSERT INTO entry_files
            (entry_id, user_id, file_original_name, file_stored_name, file_path, file_mime, file_size, file_kind)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $copied = 0;

    foreach ($files as $file) {
        $source = realpath(dirname(__DIR__) . '/' . $file['storage_path']);

        if (!$baseRoot || !$source || strpos($source, $baseRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'on-the-go' . DIRECTORY_SEPARATOR) !== 0 || !is_file($source)) {
            continue;
        }

        $original = (string)($file['original_name'] ?? 'attachment');
        $mime = (string)($file['mime_type'] ?? 'application/octet-stream');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'bin';
        }

        $stored = 'entry_' . $entryId . '_advance_' . $captureId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $ledgerDir . '/' . $stored;

        if (!copy($source, $target)) {
            throw new RuntimeException('file_copy_failed');
        }

        chmod($target, 0640);

        $relativePath = 'documents/' . date('Y') . '/' . date('m') . '/' . $stored;
        $size = filesize($target);

        $fileInsert->execute([
            $entryId,
            $captureUserId,
            $original,
            $stored,
            $relativePath,
            $mime,
            $size !== false ? (int)$size : (int)($file['size_bytes'] ?? 0),
            ql_otr_file_kind($mime)
        ]);

        $copied++;
    }

    return $copied;
}

function ql_advance_accept(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }
    if (mb_strlen($note) > 5000) {
        $note = mb_substr($note, 0, 5000);
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }

    $groupId = (int)$advance['group_id'];
    $scope = ql_advance_scope($groupId, $userId);
    if (!$scope || empty($scope['can_moderate'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'manager'];
    }

    if ((string)$advance['status'] === 'accepted') {
        return [
            'ok' => true,
            'already_accepted' => true,
            'advance' => ql_advance_public($advance)
        ];
    }

    if (!in_array((string)$advance['status'], ['submitted', 'discrepancy'], true)) {
        return ['ok' => false, 'error' => 'invalid_advance_status'];
    }

    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    if ($tapeId <= 0) {
        return ['ok' => false, 'error' => 'advance_tape_missing'];
    }

    $assignedUserId = (int)$advance['assigned_to_user_id'];
    $db = ql_db();
    $entriesCreated = 0;
    $filesCopied = 0;
    $rolloverAdvanceId = 0;
    $rolloverTapeId = 0;

    try {
        $db->beginTransaction();

        $captureStmt = $db->prepare("
            SELECT *
            FROM on_the_go_captures
            WHERE user_id = ?
              AND tape_id = ?
              AND review_status = 'needs_review'
              AND capture_type IN ('cash_out', 'noncash_out')
              AND amount IS NOT NULL
              AND amount > 0
            ORDER BY created_at ASC, id ASC
            FOR UPDATE
        ");
        $captureStmt->execute([$assignedUserId, $tapeId]);
        $captures = $captureStmt->fetchAll();

        $entryInsert = $db->prepare("
            INSERT INTO ledger_entries
                (user_id, group_id, entry_type, money_type, category_id, amount, currency, purpose, note, entry_datetime, original_position_at)
            VALUES
                (?, ?, 'expense', ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $captureUpdate = $db->prepare("
            UPDATE on_the_go_captures
            SET review_status = 'reviewed',
                reportable = 1,
                updated_at = NOW()
            WHERE id = ?
              AND user_id = ?
              AND review_status = 'needs_review'
            LIMIT 1
        ");

        foreach ($captures as $capture) {
            $moneyType = (string)$capture['capture_type'] === 'noncash_out' ? 'noncash' : 'cash';
            $categoryId = ql_otr_ensure_on_the_go_category($assignedUserId, 'expense', $groupId);
            $purpose = trim((string)($capture['description'] ?? ''));
            if ($purpose === '') {
                $purpose = 'Advance expense';
            }
            if (mb_strlen($purpose) > 255) {
                $purpose = mb_substr($purpose, 0, 255);
            }

            $entryNote = 'From advance #' . $advanceId . ', On the Go capture #' . (int)$capture['id'];
            $createdAt = (string)($capture['created_at'] ?? date('Y-m-d H:i:s'));

            $entryInsert->execute([
                $assignedUserId,
                $groupId,
                $moneyType,
                $categoryId ?: null,
                $capture['amount'],
                ql_advance_currency($capture['currency'] ?? $advance['currency'] ?? 'EUR'),
                $purpose,
                $entryNote,
                $createdAt,
                $createdAt
            ]);

            $entryId = (int)$db->lastInsertId();
            $filesCopied += ql_advance_copy_files_to_entry((int)$capture['id'], $assignedUserId, $entryId);

            $captureUpdate->execute([(int)$capture['id'], $assignedUserId]);
            $entriesCreated++;
        }

        $advanceUpdate = $db->prepare("
            UPDATE cash_advances
            SET status = 'accepted',
                moderation_note = ?,
                accepted_by_user_id = ?,
                accepted_at = NOW()
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $advanceUpdate->execute([
            $note !== '' ? $note : null,
            $userId,
            $advanceId
        ]);

        $remainingSource = $advance['actual_remaining'] ?? $advance['expected_remaining'] ?? null;
        if ($remainingSource !== null) {
            $remaining = round((float)$remainingSource, 2);
        } else {
            $summary = ql_advance_tape_summary($tapeId);
            $remaining = round((float)($summary['cash_left'] ?? 0), 2);
        }

        if ($remaining > 0.009) {
            $rolloverTitle = 'Остаток подотчета #' . $advanceId;
            $rolloverTapeId = ql_on_the_go_seed_next_tape($assignedUserId, $groupId, $remaining, $tapeId);

            $rolloverInsert = $db->prepare("
                INSERT INTO cash_advances
                    (group_id, issued_by_user_id, assigned_to_user_id, on_the_go_tape_id, title, amount, currency, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, 'issued')
            ");
            $rolloverInsert->execute([
                $groupId,
                (int)($advance['issued_by_user_id'] ?? $userId),
                $assignedUserId,
                $rolloverTapeId ?: null,
                $rolloverTitle,
                number_format($remaining, 2, '.', ''),
                ql_advance_currency($advance['currency'] ?? 'EUR')
            ]);
            $rolloverAdvanceId = (int)$db->lastInsertId();

            if ($rolloverTapeId > 0) {
                $rolloverTapeUpdate = $db->prepare("
                    UPDATE on_the_go_tapes
                    SET advance_id = ?,
                        title = ?,
                        cash_received = ?,
                        group_id = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND user_id = ?
                    LIMIT 1
                ");
                $rolloverTapeUpdate->execute([
                    $rolloverAdvanceId,
                    $rolloverTitle,
                    number_format($remaining, 2, '.', ''),
                    $groupId,
                    $rolloverTapeId,
                    $assignedUserId
                ]);
            }
        }

        if (function_exists('ql_audit_write')) {
            ql_audit_write($userId, 'advance_accepted', 'cash_advance', $advanceId, [
                'group_id' => $groupId,
                'assigned_to_user_id' => $assignedUserId,
                'entries_created' => $entriesCreated,
                'files_copied' => $filesCopied,
                'rollover_advance_id' => $rolloverAdvanceId,
                'rollover_tape_id' => $rolloverTapeId,
                'rollover_amount' => $rolloverAdvanceId > 0 ? number_format($remaining, 2, '.', '') : null
            ]);
        }
        $db->commit();
        ql_on_the_go_journal_append('advance_accepted', $userId, $tapeId, [
            'group_id' => $groupId,
            'advance_id' => $advanceId,
            'assigned_to_user_id' => $assignedUserId,
            'entries_created' => $entriesCreated,
            'files_copied' => $filesCopied,
            'rollover_advance_id' => $rolloverAdvanceId,
            'rollover_tape_id' => $rolloverTapeId,
        ]);

        if ($rolloverAdvanceId > 0) {
            ql_on_the_go_journal_append('advance_remainder_reserved', $userId, $rolloverTapeId, [
                'group_id' => $groupId,
                'source_advance_id' => $advanceId,
                'advance_id' => $rolloverAdvanceId,
                'assigned_to_user_id' => $assignedUserId,
                'amount' => number_format($remaining, 2, '.', ''),
            ]);
        }

        return [
            'ok' => true,
            'advance' => ql_advance_public(ql_advance_row($advanceId)),
            'entries_created' => $entriesCreated,
            'files_copied' => $filesCopied,
            'rollover_advance_id' => $rolloverAdvanceId,
            'rollover_tape_id' => $rolloverTapeId
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'advance_accept_failed', 'message' => $e->getMessage()];
    }
}

function ql_advance_return(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }
    if (mb_strlen($note) > 5000) {
        $note = mb_substr($note, 0, 5000);
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }

    $scope = ql_advance_scope((int)$advance['group_id'], $userId);
    if (!$scope || empty($scope['can_moderate'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'manager'];
    }
    if (!in_array((string)$advance['status'], ['issued', 'submitted', 'discrepancy'], true)) {
        return ['ok' => false, 'error' => 'invalid_advance_status'];
    }

    $stmt = ql_db()->prepare("
        UPDATE cash_advances
        SET status = 'returned',
            moderation_note = ?,
            returned_by_user_id = ?,
            returned_at = NOW()
        WHERE id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([
        $note !== '' ? $note : null,
        $userId,
        $advanceId
    ]);

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'advance_returned', 'cash_advance', $advanceId, [
            'group_id' => (int)$advance['group_id'],
            'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
            'note' => $note
        ]);
    }
    ql_on_the_go_journal_append('advance_returned', $userId, (int)($advance['on_the_go_tape_id'] ?? 0), [
        'group_id' => (int)$advance['group_id'],
        'advance_id' => $advanceId,
        'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
        'note' => $note,
    ]);

    return ['ok' => true, 'advance' => ql_advance_public(ql_advance_row($advanceId))];
}

function ql_advance_unaccept(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }
    if (mb_strlen($note) > 5000) {
        $note = mb_substr($note, 0, 5000);
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }

    $groupId = (int)$advance['group_id'];
    $assignedUserId = (int)$advance['assigned_to_user_id'];
    $scope = ql_advance_scope($groupId, $userId);
    if (!$scope || empty($scope['can_moderate'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'manager'];
    }
    if ((string)$advance['status'] !== 'accepted') {
        return ['ok' => false, 'error' => 'invalid_advance_status'];
    }

    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    if ($tapeId <= 0) {
        return ['ok' => false, 'error' => 'advance_tape_missing'];
    }

    $db = ql_db();
    $moderationNote = $note !== '' ? $note : 'Возвращено из рабочего пакета на доработку';
    $ledgerRowsArchived = 0;
    $childClosed = null;

    try {
        $db->beginTransaction();

        $lockStmt = $db->prepare("
            SELECT *
            FROM cash_advances
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->execute([$advanceId]);
        $locked = $lockStmt->fetch();
        if (!$locked) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'advance_not_found'];
        }
        if ((string)$locked['status'] !== 'accepted') {
            $db->rollBack();
            return ['ok' => false, 'error' => 'invalid_advance_status'];
        }

        $childStmt = $db->prepare("
            SELECT ca.*
            FROM cash_advances ca
            WHERE ca.group_id = ?
              AND ca.assigned_to_user_id = ?
              AND ca.title = ?
            ORDER BY ca.id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $childStmt->execute([$groupId, $assignedUserId, 'Остаток подотчета #' . $advanceId]);
        $child = $childStmt->fetch();

        if ($child) {
            if (!empty($child['deleted_at']) || (string)$child['status'] === 'closed') {
                $db->rollBack();
                return [
                    'ok' => false,
                    'error' => 'advance_remainder_closed',
                    'message' => 'Остаток этого подотчета уже закрыт или возвращен в кассу. Вернуть его на доработку можно только вручную после сверки кассы.',
                    'followup_advance_id' => (int)$child['id'],
                ];
            }

            $childTapeId = (int)($child['on_the_go_tape_id'] ?? 0);
            $childRecords = 0;
            if ($childTapeId > 0) {
                $recordStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM on_the_go_captures
                    WHERE tape_id = ?
                      AND review_status <> 'archived'
                ");
                $recordStmt->execute([$childTapeId]);
                $childRecords = (int)$recordStmt->fetchColumn();
            }

            if ($childRecords > 0 || !in_array((string)$child['status'], ['issued', 'returned'], true)) {
                $db->rollBack();
                return [
                    'ok' => false,
                    'error' => 'advance_has_followup',
                    'message' => 'Сначала обработайте следующий подотчет #' . (int)$child['id'],
                    'followup_advance_id' => (int)$child['id'],
                ];
            }

            $closeChild = $db->prepare("
                UPDATE cash_advances
                SET status = 'closed',
                    expected_remaining = 0,
                    actual_remaining = 0,
                    difference_amount = 0,
                    moderation_note = ?,
                    returned_by_user_id = ?,
                    returned_at = NOW(),
                    deleted_at = NOW()
                WHERE id = ?
                  AND deleted_at IS NULL
                LIMIT 1
            ");
            $closeChild->execute(['Закрыто при возврате исходного подотчета #' . $advanceId, $userId, (int)$child['id']]);
            $childClosed = (int)$child['id'];

            if ($childTapeId > 0) {
                $db->prepare("
                    UPDATE on_the_go_sessions
                    SET status = 'archived',
                        closed_at = COALESCE(closed_at, NOW()),
                        archived_at = COALESCE(archived_at, NOW())
                    WHERE tape_id = ?
                      AND status <> 'archived'
                ")->execute([$childTapeId]);
                $db->prepare("
                    UPDATE on_the_go_tapes
                    SET status = 'archived',
                        actual_remaining = 0,
                        difference_amount = 0,
                        closed_at = COALESCE(closed_at, NOW()),
                        archived_at = COALESCE(archived_at, NOW())
                    WHERE id = ?
                      AND status <> 'archived'
                    LIMIT 1
                ")->execute([$childTapeId]);
            }
        }

        $ledgerUpdate = $db->prepare("
            UPDATE ledger_entries
            SET deleted_at = COALESCE(deleted_at, NOW()),
                updated_at = NOW()
            WHERE group_id = ?
              AND deleted_at IS NULL
              AND note LIKE ?
        ");
        $ledgerUpdate->execute([$groupId, 'From advance #' . $advanceId . ',%']);
        $ledgerRowsArchived = $ledgerUpdate->rowCount();

        $captureUpdate = $db->prepare("
            UPDATE on_the_go_captures
            SET review_status = 'needs_review',
                reportable = 0,
                updated_at = NOW()
            WHERE tape_id = ?
              AND review_status <> 'archived'
              AND capture_type IN ('cash_in', 'cash_out', 'noncash_out')
        ");
        $captureUpdate->execute([$tapeId]);

        ql_on_the_go_active_session_id($assignedUserId, $tapeId, 'cash');

        $tapeUpdate = $db->prepare("
            UPDATE on_the_go_tapes
            SET status = 'active',
                submitted_at = NULL,
                actual_remaining = NULL,
                difference_amount = NULL,
                archived_at = NULL,
                updated_at = NOW()
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $tapeUpdate->execute([$tapeId, $assignedUserId]);

        $advanceUpdate = $db->prepare("
            UPDATE cash_advances
            SET status = 'returned',
                expected_remaining = NULL,
                actual_remaining = NULL,
                difference_amount = NULL,
                submitted_at = NULL,
                accepted_by_user_id = NULL,
                accepted_at = NULL,
                moderation_note = ?,
                returned_by_user_id = ?,
                returned_at = NOW()
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $advanceUpdate->execute([$moderationNote, $userId, $advanceId]);

        if (function_exists('ql_audit_write')) {
            ql_audit_write($userId, 'advance_unaccepted', 'cash_advance', $advanceId, [
                'group_id' => $groupId,
                'assigned_to_user_id' => $assignedUserId,
                'ledger_rows_archived' => $ledgerRowsArchived,
                'child_closed' => $childClosed,
                'note' => $note,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'advance_unaccept_failed', 'message' => $e->getMessage()];
    }

    ql_on_the_go_journal_append('advance_unaccepted', $userId, $tapeId, [
        'group_id' => $groupId,
        'advance_id' => $advanceId,
        'assigned_to_user_id' => $assignedUserId,
        'ledger_rows_archived' => $ledgerRowsArchived,
        'child_closed' => $childClosed,
        'note' => $note,
    ]);

    return [
        'ok' => true,
        'advance' => ql_advance_public(ql_advance_row($advanceId)),
        'ledger_rows_archived' => $ledgerRowsArchived,
        'child_closed' => $childClosed,
    ];
}

function ql_advance_return_cash(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);
    $note = trim((string)($input['note'] ?? ''));

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }
    if (mb_strlen($note) > 5000) {
        $note = mb_substr($note, 0, 5000);
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }

    $groupId = (int)$advance['group_id'];
    $scope = ql_advance_scope($groupId, $userId);
    if (!$scope || empty($scope['can_manage_money'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'advanced'];
    }

    $status = (string)$advance['status'];
    if (!in_array($status, ['issued', 'returned'], true)) {
        return ['ok' => false, 'error' => 'invalid_advance_status'];
    }

    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    $summary = $tapeId > 0 ? ql_advance_tape_summary($tapeId) : [
        'cash_left' => round((float)($advance['amount'] ?? 0), 2),
        'records_count' => 0,
    ];
    if ((int)($summary['records_count'] ?? 0) > 0) {
        return ['ok' => false, 'error' => 'advance_has_records'];
    }

    $returnedAmount = round((float)($summary['cash_left'] ?? $advance['amount'] ?? 0), 2);
    if ($returnedAmount <= 0.009) {
        return ['ok' => false, 'error' => 'empty_cash_remainder'];
    }

    $db = ql_db();
    $moderationNote = $note !== '' ? $note : ('Остаток возвращен в кассу: ' . number_format($returnedAmount, 2, '.', '') . ' EUR');

    try {
        $db->beginTransaction();

        $lockStmt = $db->prepare("
            SELECT id, status
            FROM cash_advances
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->execute([$advanceId]);
        $locked = $lockStmt->fetch();
        if (!$locked) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'advance_not_found'];
        }
        if (!in_array((string)$locked['status'], ['issued', 'returned'], true)) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'invalid_advance_status'];
        }

        if ($tapeId > 0) {
            $recordStmt = $db->prepare("
                SELECT COUNT(*)
                FROM on_the_go_captures
                WHERE tape_id = ?
                  AND review_status <> 'archived'
            ");
            $recordStmt->execute([$tapeId]);
            if ((int)$recordStmt->fetchColumn() > 0) {
                $db->rollBack();
                return ['ok' => false, 'error' => 'advance_has_records'];
            }
        }

        $advanceUpdate = $db->prepare("
            UPDATE cash_advances
            SET status = 'closed',
                expected_remaining = 0,
                actual_remaining = 0,
                difference_amount = 0,
                moderation_note = ?,
                returned_by_user_id = ?,
                returned_at = NOW(),
                deleted_at = NOW()
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $advanceUpdate->execute([$moderationNote, $userId, $advanceId]);

        if ($tapeId > 0) {
            $sessionUpdate = $db->prepare("
                UPDATE on_the_go_sessions
                SET status = 'archived',
                    closed_at = COALESCE(closed_at, NOW()),
                    archived_at = COALESCE(archived_at, NOW())
                WHERE tape_id = ?
                  AND status <> 'archived'
            ");
            $sessionUpdate->execute([$tapeId]);

            $tapeUpdate = $db->prepare("
                UPDATE on_the_go_tapes
                SET status = 'archived',
                    actual_remaining = 0,
                    difference_amount = 0,
                    closed_at = COALESCE(closed_at, NOW()),
                    archived_at = COALESCE(archived_at, NOW())
                WHERE id = ?
                  AND status <> 'archived'
                LIMIT 1
            ");
            $tapeUpdate->execute([$tapeId]);
        }

        if (function_exists('ql_audit_write')) {
            ql_audit_write($userId, 'advance_cash_returned', 'cash_advance', $advanceId, [
                'group_id' => $groupId,
                'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
                'amount_returned' => number_format($returnedAmount, 2, '.', ''),
                'currency' => (string)($advance['currency'] ?? 'EUR'),
                'previous_status' => $status,
                'note' => $note,
            ]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'advance_return_cash_failed', 'message' => $e->getMessage()];
    }

    ql_on_the_go_journal_append('advance_cash_returned', $userId, $tapeId, [
        'group_id' => $groupId,
        'advance_id' => $advanceId,
        'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
        'amount_returned' => number_format($returnedAmount, 2, '.', ''),
        'currency' => (string)($advance['currency'] ?? 'EUR'),
        'previous_status' => $status,
        'note' => $note,
    ]);

    return [
        'ok' => true,
        'cash_returned' => true,
        'advance_id' => $advanceId,
        'tape_id' => $tapeId,
        'amount_returned' => $returnedAmount,
        'currency' => (string)($advance['currency'] ?? 'EUR'),
    ];
}

function ql_advance_cancel(array $input): array
{
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $advanceId = (int)($input['id'] ?? 0);
    $reason = trim((string)($input['reason'] ?? $input['note'] ?? ''));

    if ($advanceId <= 0) {
        return ['ok' => false, 'error' => 'invalid_advance_id'];
    }
    if ($reason === '') {
        return ['ok' => false, 'error' => 'empty_cancel_reason'];
    }
    if (mb_strlen($reason) > 5000) {
        $reason = mb_substr($reason, 0, 5000);
    }

    $advance = ql_advance_visible_row($advanceId, $userId);
    if (!$advance) {
        return ['ok' => false, 'error' => 'advance_not_found'];
    }

    $scope = ql_advance_scope((int)$advance['group_id'], $userId);
    if (!$scope || empty($scope['can_manage_money'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'advanced'];
    }

    $status = (string)$advance['status'];
    if (in_array($status, ['accepted', 'closed'], true)) {
        return ['ok' => false, 'error' => 'cannot_cancel_final_advance'];
    }
    if (!in_array($status, ['issued', 'submitted', 'returned', 'discrepancy'], true)) {
        return ['ok' => false, 'error' => 'invalid_advance_status'];
    }

    $tapeId = (int)($advance['on_the_go_tape_id'] ?? 0);
    $note = 'Отмена выдачи: ' . $reason;
    if (mb_strlen($note) > 5000) {
        $note = mb_substr($note, 0, 5000);
    }

    $db = ql_db();

    try {
        $db->beginTransaction();

        $lockStmt = $db->prepare("
            SELECT id, status
            FROM cash_advances
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->execute([$advanceId]);
        $locked = $lockStmt->fetch();
        if (!$locked) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'advance_not_found'];
        }

        $lockedStatus = (string)$locked['status'];
        if (in_array($lockedStatus, ['accepted', 'closed'], true)) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'cannot_cancel_final_advance'];
        }
        if (!in_array($lockedStatus, ['issued', 'submitted', 'returned', 'discrepancy'], true)) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'invalid_advance_status'];
        }

        $advanceUpdate = $db->prepare("
            UPDATE cash_advances
            SET status = 'closed',
                moderation_note = ?,
                returned_by_user_id = ?,
                returned_at = NOW(),
                deleted_at = NOW()
            WHERE id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $advanceUpdate->execute([$note, $userId, $advanceId]);

        if ($tapeId > 0) {
            $captureUpdate = $db->prepare("
                UPDATE on_the_go_captures
                SET review_status = 'archived',
                    updated_at = NOW()
                WHERE tape_id = ?
                  AND review_status = 'needs_review'
            ");
            $captureUpdate->execute([$tapeId]);

            $sessionUpdate = $db->prepare("
                UPDATE on_the_go_sessions
                SET status = 'archived',
                    closed_at = COALESCE(closed_at, NOW()),
                    archived_at = COALESCE(archived_at, NOW())
                WHERE tape_id = ?
                  AND status <> 'archived'
            ");
            $sessionUpdate->execute([$tapeId]);

            $tapeUpdate = $db->prepare("
                UPDATE on_the_go_tapes
                SET status = 'archived',
                    closed_at = COALESCE(closed_at, NOW()),
                    archived_at = COALESCE(archived_at, NOW())
                WHERE id = ?
                  AND status <> 'archived'
                LIMIT 1
            ");
            $tapeUpdate->execute([$tapeId]);
        }

        if (function_exists('ql_audit_write')) {
            ql_audit_write($userId, 'advance_cancelled', 'cash_advance', $advanceId, [
                'group_id' => (int)$advance['group_id'],
                'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
                'amount' => (string)($advance['amount'] ?? ''),
                'currency' => (string)($advance['currency'] ?? 'EUR'),
                'reason' => $reason,
                'previous_status' => $status
            ]);
        }
        $db->commit();
        ql_on_the_go_journal_append('advance_cancelled', $userId, $tapeId, [
            'group_id' => (int)$advance['group_id'],
            'advance_id' => $advanceId,
            'assigned_to_user_id' => (int)$advance['assigned_to_user_id'],
            'amount' => (string)($advance['amount'] ?? ''),
            'currency' => (string)($advance['currency'] ?? 'EUR'),
            'reason' => $reason,
            'previous_status' => $status,
        ]);

        return [
            'ok' => true,
            'cancelled' => true,
            'advance_id' => $advanceId,
            'reason' => $reason
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'advance_cancel_failed', 'message' => $e->getMessage()];
    }
}
