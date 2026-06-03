<?php

require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/on_the_go.php';

function ql_findesk_json($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? '{}' : $json;
}

function ql_findesk_stream_type($value): string
{
    return (string)$value === 'card' ? 'card' : 'cash';
}

function ql_findesk_currency($value): string
{
    $currency = strtoupper(trim((string)$value));
    return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'EUR';
}

function ql_findesk_amount($value): ?float
{
    if (is_string($value)) {
        $value = str_replace(',', '.', trim($value));
    }
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    $amount = round((float)$value, 2);
    return $amount > 0 ? $amount : null;
}

function ql_findesk_ensure_schema(): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $db = ql_db();

    $db->exec("
        CREATE TABLE IF NOT EXISTS findesk_workspace_preferences (
            user_id BIGINT UNSIGNED NOT NULL,
            mode ENUM('solo','group') NOT NULL DEFAULT 'solo',
            group_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY idx_findesk_workspace_group (group_id),
            CONSTRAINT fk_findesk_workspace_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_workspace_group FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS findesk_transfers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            issued_by_user_id BIGINT UNSIGNED NOT NULL,
            assigned_to_user_id BIGINT UNSIGNED NOT NULL,
            stream_type ENUM('cash','card') NOT NULL DEFAULT 'cash',
            amount DECIMAL(12,2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT 'EUR',
            description VARCHAR(500) DEFAULT NULL,
            state ENUM('pending','active','cancelled') NOT NULL DEFAULT 'pending',
            on_the_go_tape_id BIGINT UNSIGNED DEFAULT NULL,
            confirmed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            confirmed_at DATETIME DEFAULT NULL,
            cancelled_by_user_id BIGINT UNSIGNED DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_findesk_transfers_group_state (group_id, state),
            KEY idx_findesk_transfers_assigned_state (assigned_to_user_id, state),
            KEY idx_findesk_transfers_stream (stream_type),
            KEY idx_findesk_transfers_tape (on_the_go_tape_id),
            CONSTRAINT fk_findesk_transfers_group FOREIGN KEY (group_id) REFERENCES groups (id) ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_transfers_issued FOREIGN KEY (issued_by_user_id) REFERENCES users (id) ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_transfers_assigned FOREIGN KEY (assigned_to_user_id) REFERENCES users (id) ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_transfers_confirmed FOREIGN KEY (confirmed_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_transfers_cancelled FOREIGN KEY (cancelled_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_transfers_tape FOREIGN KEY (on_the_go_tape_id) REFERENCES on_the_go_tapes (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS findesk_transfer_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            transfer_id BIGINT UNSIGNED NOT NULL,
            group_id BIGINT UNSIGNED NOT NULL,
            actor_user_id BIGINT UNSIGNED DEFAULT NULL,
            event_type VARCHAR(80) NOT NULL,
            previous_state_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(previous_state_json)),
            new_state_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(new_state_json)),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_findesk_transfer_events_transfer (transfer_id),
            KEY idx_findesk_transfer_events_group (group_id),
            CONSTRAINT fk_findesk_transfer_events_transfer FOREIGN KEY (transfer_id) REFERENCES findesk_transfers (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_transfer_events_group FOREIGN KEY (group_id) REFERENCES groups (id) ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_transfer_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS findesk_reports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED NOT NULL,
            created_by_user_id BIGINT UNSIGNED NOT NULL,
            status ENUM('draft','finalized','archived') NOT NULL DEFAULT 'draft',
            period_from DATE DEFAULT NULL,
            period_to DATE DEFAULT NULL,
            cash_summary_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(cash_summary_json)),
            card_summary_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(card_summary_json)),
            total_summary_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(total_summary_json)),
            snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(snapshot_json)),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            finalized_at DATETIME DEFAULT NULL,
            archived_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_findesk_reports_group_status (group_id, status),
            CONSTRAINT fk_findesk_reports_group FOREIGN KEY (group_id) REFERENCES groups (id) ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_reports_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS findesk_report_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT UNSIGNED NOT NULL,
            tape_id BIGINT UNSIGNED NOT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL,
            stream_type ENUM('cash','card') NOT NULL DEFAULT 'cash',
            state ENUM('ready','attached','detached') NOT NULL DEFAULT 'attached',
            snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(snapshot_json)),
            attached_at DATETIME DEFAULT NULL,
            detached_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_findesk_report_tape (report_id, tape_id),
            KEY idx_findesk_report_items_tape (tape_id),
            KEY idx_findesk_report_items_owner (owner_user_id),
            CONSTRAINT fk_findesk_report_items_report FOREIGN KEY (report_id) REFERENCES findesk_reports (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_report_items_tape FOREIGN KEY (tape_id) REFERENCES on_the_go_tapes (id) ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_report_items_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS findesk_protected_actions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT UNSIGNED DEFAULT NULL,
            action_type VARCHAR(120) NOT NULL,
            entity_type VARCHAR(120) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL,
            preview_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(preview_json)),
            previous_state_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(previous_state_json)),
            new_state_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(new_state_json)),
            reason TEXT DEFAULT NULL,
            confirmation_phrase VARCHAR(80) DEFAULT NULL,
            status ENUM('prepared','confirmed','cancelled') NOT NULL DEFAULT 'prepared',
            performed_by_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_findesk_protected_group (group_id),
            KEY idx_findesk_protected_entity (entity_type, entity_id),
            KEY idx_findesk_protected_status (status),
            CONSTRAINT fk_findesk_protected_group FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT fk_findesk_protected_user FOREIGN KEY (performed_by_user_id) REFERENCES users (id) ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $ready = true;
}

function ql_findesk_scope(int $groupId, int $userId): ?array
{
    if ($groupId <= 0 || $userId <= 0 || !function_exists('ql_group_membership')) {
        return null;
    }

    $membership = ql_group_membership($groupId, $userId);
    if (!$membership) {
        return null;
    }

    $permissions = $membership['permissions'] ?? [];
    if (!is_array($permissions)) {
        $permissions = [];
    }
    $accessLevel = (string)($membership['access_level'] ?? 'base');
    $role = (string)($membership['role'] ?? 'member');
    $isAdmin = $role === 'admin' || $accessLevel === 'advanced';

    return [
        'membership' => $membership,
        'role' => $role,
        'access_level' => $accessLevel,
        'permissions' => $permissions,
        'is_admin' => $isAdmin,
        'can_manage_money' => $isAdmin || !empty($permissions['can_manage_money']),
        'can_view_reports' => $isAdmin || !empty($permissions['can_view_group_reports']) || in_array($accessLevel, ['manager', 'advanced'], true),
        'can_write_reports' => $isAdmin || !empty($permissions['can_write_group_ledger']) || in_array($accessLevel, ['manager', 'advanced'], true),
    ];
}

function ql_findesk_transfer_event(int $transferId, int $groupId, ?int $actorUserId, string $eventType, ?array $previous, array $next): void
{
    ql_db()->prepare("
        INSERT INTO findesk_transfer_events
            (transfer_id, group_id, actor_user_id, event_type, previous_state_json, new_state_json)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([
        $transferId,
        $groupId,
        $actorUserId,
        $eventType,
        $previous ? ql_findesk_json($previous) : null,
        ql_findesk_json($next),
    ]);
}

function ql_findesk_workspace_public(?array $row, int $userId): array
{
    $mode = (string)($row['mode'] ?? 'solo');
    $groupId = (int)($row['group_id'] ?? 0);
    if ($mode !== 'group') {
        $mode = 'solo';
        $groupId = 0;
    }

    return [
        'user_id' => $userId,
        'mode' => $mode,
        'group_id' => $groupId,
        'updated_at' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
    ];
}

function ql_findesk_workspace_get(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];

    $stmt = ql_db()->prepare("SELECT * FROM findesk_workspace_preferences WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $workspace = ql_findesk_workspace_public($stmt->fetch() ?: null, $userId);

    return ['ok' => true, 'workspace' => $workspace];
}

function ql_findesk_workspace_set(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $mode = (string)($input['mode'] ?? 'solo') === 'group' ? 'group' : 'solo';
    $groupId = $mode === 'group' ? (int)($input['group_id'] ?? 0) : 0;

    if ($mode === 'group' && !ql_findesk_scope($groupId, $userId)) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }

    ql_db()->prepare("
        INSERT INTO findesk_workspace_preferences (user_id, mode, group_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE mode = VALUES(mode), group_id = VALUES(group_id), updated_at = NOW()
    ")->execute([$userId, $mode, $groupId ?: null]);

    return ql_findesk_workspace_get([]);
}

function ql_findesk_transfer_row(int $transferId): ?array
{
    if ($transferId <= 0) {
        return null;
    }
    $stmt = ql_db()->prepare("
        SELECT
            ft.*,
            g.name AS group_name,
            COALESCE(issued_gm.display_name, issued.display_name, issued.email) AS issued_by_name,
            issued.email AS issued_by_email,
            COALESCE(assigned_gm.display_name, assigned.display_name, assigned.email) AS assigned_to_name,
            assigned.email AS assigned_to_email
        FROM findesk_transfers ft
        JOIN groups g ON g.id = ft.group_id
        JOIN users issued ON issued.id = ft.issued_by_user_id
        JOIN users assigned ON assigned.id = ft.assigned_to_user_id
        LEFT JOIN group_members issued_gm ON issued_gm.group_id = ft.group_id AND issued_gm.user_id = ft.issued_by_user_id
        LEFT JOIN group_members assigned_gm ON assigned_gm.group_id = ft.group_id AND assigned_gm.user_id = ft.assigned_to_user_id
        WHERE ft.id = ?
        LIMIT 1
    ");
    $stmt->execute([$transferId]);
    return $stmt->fetch() ?: null;
}

function ql_findesk_transfer_public(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'group_id' => (int)$row['group_id'],
        'group_name' => (string)($row['group_name'] ?? ''),
        'issued_by_user_id' => (int)$row['issued_by_user_id'],
        'issued_by_name' => (string)($row['issued_by_name'] ?? ''),
        'assigned_to_user_id' => (int)$row['assigned_to_user_id'],
        'assigned_to_name' => (string)($row['assigned_to_name'] ?? ''),
        'stream_type' => ql_findesk_stream_type($row['stream_type'] ?? 'cash'),
        'amount' => round((float)$row['amount'], 2),
        'currency' => (string)($row['currency'] ?? 'EUR'),
        'description' => (string)($row['description'] ?? ''),
        'state' => (string)$row['state'],
        'on_the_go_tape_id' => (int)($row['on_the_go_tape_id'] ?? 0),
        'confirmed_at' => (string)($row['confirmed_at'] ?? ''),
        'cancelled_at' => (string)($row['cancelled_at'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function ql_findesk_transfer_list(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $state = trim((string)($input['state'] ?? ''));
    $limit = (int)($input['limit'] ?? 100);
    if ($limit < 1 || $limit > 200) {
        $limit = 100;
    }

    $where = [];
    $params = [];
    $scope = null;
    if ($groupId > 0) {
        $scope = ql_findesk_scope($groupId, $userId);
        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }
        $where[] = 'ft.group_id = ?';
        $params[] = $groupId;
        if (empty($scope['can_manage_money']) && empty($scope['can_view_reports'])) {
            $where[] = '(ft.assigned_to_user_id = ? OR ft.issued_by_user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }
    } else {
        $where[] = '(ft.assigned_to_user_id = ? OR ft.issued_by_user_id = ?)';
        $params[] = $userId;
        $params[] = $userId;
    }
    if (in_array($state, ['pending', 'active', 'cancelled'], true)) {
        $where[] = 'ft.state = ?';
        $params[] = $state;
    }

    $stmt = ql_db()->prepare("
        SELECT
            ft.*,
            g.name AS group_name,
            COALESCE(issued_gm.display_name, issued.display_name, issued.email) AS issued_by_name,
            COALESCE(assigned_gm.display_name, assigned.display_name, assigned.email) AS assigned_to_name
        FROM findesk_transfers ft
        JOIN groups g ON g.id = ft.group_id
        JOIN users issued ON issued.id = ft.issued_by_user_id
        JOIN users assigned ON assigned.id = ft.assigned_to_user_id
        LEFT JOIN group_members issued_gm ON issued_gm.group_id = ft.group_id AND issued_gm.user_id = ft.issued_by_user_id
        LEFT JOIN group_members assigned_gm ON assigned_gm.group_id = ft.group_id AND assigned_gm.user_id = ft.assigned_to_user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ft.created_at DESC, ft.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);

    return [
        'ok' => true,
        'transfers' => array_map('ql_findesk_transfer_public', $stmt->fetchAll()),
    ];
}

function ql_findesk_transfer_create(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $assignedUserId = (int)($input['assigned_to_user_id'] ?? 0);
    $amount = ql_findesk_amount($input['amount'] ?? null);
    $currency = ql_findesk_currency($input['currency'] ?? 'EUR');
    $streamType = ql_findesk_stream_type($input['stream_type'] ?? 'cash');
    $description = trim((string)($input['description'] ?? $input['title'] ?? ''));
    if (mb_strlen($description) > 500) {
        $description = mb_substr($description, 0, 500);
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
    $scope = ql_findesk_scope($groupId, $userId);
    if (!$scope) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (empty($scope['can_manage_money'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    if (!ql_group_membership($groupId, $assignedUserId)) {
        return ['ok' => false, 'error' => 'assigned_user_not_group_member'];
    }

    $db = ql_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            INSERT INTO findesk_transfers
                (group_id, issued_by_user_id, assigned_to_user_id, stream_type, amount, currency, description, state)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$groupId, $userId, $assignedUserId, $streamType, $amount, $currency, $description ?: null]);
        $transferId = (int)$db->lastInsertId();
        $next = ql_findesk_transfer_row($transferId) ?: [];
        ql_findesk_transfer_event($transferId, $groupId, $userId, 'issued', null, $next);
        ql_audit_write($userId, 'findesk_transfer_issued', 'findesk_transfer', $transferId, [
            'group_id' => $groupId,
            'assigned_to_user_id' => $assignedUserId,
            'stream_type' => $streamType,
            'amount' => $amount,
            'currency' => $currency,
            'state' => 'pending',
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'findesk_transfer_create_failed', 'message' => $e->getMessage()];
    }

    return ['ok' => true, 'transfer' => ql_findesk_transfer_public(ql_findesk_transfer_row($transferId))];
}

function ql_findesk_transfer_update(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $transferId = (int)($input['id'] ?? $input['transfer_id'] ?? 0);
    $amount = ql_findesk_amount($input['amount'] ?? null);
    $currency = ql_findesk_currency($input['currency'] ?? 'EUR');
    $streamType = ql_findesk_stream_type($input['stream_type'] ?? 'cash');
    $description = trim((string)($input['description'] ?? $input['title'] ?? ''));
    $reason = trim((string)($input['reason'] ?? ''));
    $confirmPhrase = trim((string)($input['confirm_phrase'] ?? $input['confirmation'] ?? ''));
    if (mb_strlen($description) > 500) {
        $description = mb_substr($description, 0, 500);
    }

    $transfer = ql_findesk_transfer_row($transferId);
    if (!$transfer) {
        return ['ok' => false, 'error' => 'transfer_not_found'];
    }
    $scope = ql_findesk_scope((int)$transfer['group_id'], $userId);
    if (!$scope || empty($scope['can_manage_money'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    if ((string)$transfer['state'] !== 'pending') {
        return ['ok' => false, 'error' => 'transfer_not_pending'];
    }
    if ($amount === null) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }
    if ($reason === '') {
        return ['ok' => false, 'error' => 'empty_edit_reason'];
    }
    if ($confirmPhrase !== 'ИЗМЕНИТЬ') {
        return ['ok' => false, 'error' => 'invalid_edit_confirmation'];
    }

    ql_db()->prepare("
        UPDATE findesk_transfers
        SET stream_type = ?, amount = ?, currency = ?, description = ?, updated_at = NOW()
        WHERE id = ? AND state = 'pending'
        LIMIT 1
    ")->execute([$streamType, $amount, $currency, $description ?: null, $transferId]);

    $next = ql_findesk_transfer_row($transferId) ?: [];
    ql_findesk_transfer_event($transferId, (int)$transfer['group_id'], $userId, 'edited', $transfer, $next);
    ql_audit_write($userId, 'findesk_transfer_edited', 'findesk_transfer', $transferId, [
        'group_id' => (int)$transfer['group_id'],
        'state' => 'pending',
        'reason' => $reason,
        'previous_amount' => round((float)($transfer['amount'] ?? 0), 2),
        'next_amount' => round((float)($next['amount'] ?? 0), 2),
        'previous_stream_type' => (string)($transfer['stream_type'] ?? ''),
        'next_stream_type' => (string)($next['stream_type'] ?? ''),
    ]);

    return ['ok' => true, 'transfer' => ql_findesk_transfer_public($next)];
}

function ql_findesk_transfer_confirm(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $transferId = (int)($input['id'] ?? $input['transfer_id'] ?? 0);
    $transfer = ql_findesk_transfer_row($transferId);
    if (!$transfer) {
        return ['ok' => false, 'error' => 'transfer_not_found'];
    }
    if ((int)$transfer['assigned_to_user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    if ((string)$transfer['state'] !== 'pending') {
        return ['ok' => false, 'error' => 'transfer_not_pending'];
    }

    $streamType = ql_findesk_stream_type($transfer['stream_type'] ?? 'cash');
    $cashReceived = $streamType === 'cash' ? (float)$transfer['amount'] : 0.0;
    $title = $streamType === 'card' ? 'Card / non-cash assignment' : 'Cash transfer';
    if ((string)($transfer['description'] ?? '') !== '') {
        $title = mb_substr((string)$transfer['description'], 0, 190);
    }

    $db = ql_db();
    $db->beginTransaction();
    try {
        $tapeStmt = $db->prepare("
            INSERT INTO on_the_go_tapes
                (user_id, group_id, stream_type, title, cash_received, currency, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ");
        $tapeStmt->execute([
            $userId,
            (int)$transfer['group_id'],
            $streamType,
            $title,
            $cashReceived,
            (string)$transfer['currency'],
        ]);
        $tapeId = (int)$db->lastInsertId();

        $db->prepare("
            UPDATE findesk_transfers
            SET state = 'active',
                on_the_go_tape_id = ?,
                confirmed_by_user_id = ?,
                confirmed_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND state = 'pending'
            LIMIT 1
        ")->execute([$tapeId, $userId, $transferId]);

        $next = ql_findesk_transfer_row($transferId) ?: [];
        ql_findesk_transfer_event($transferId, (int)$transfer['group_id'], $userId, 'confirmed', $transfer, $next);
        ql_audit_write($userId, 'findesk_transfer_confirmed', 'findesk_transfer', $transferId, [
            'group_id' => (int)$transfer['group_id'],
            'stream_type' => $streamType,
            'amount' => (float)$transfer['amount'],
            'currency' => (string)$transfer['currency'],
            'tape_id' => $tapeId,
        ]);
        if (function_exists('ql_on_the_go_journal_append')) {
            ql_on_the_go_journal_append('findesk_transfer_confirmed', $userId, $tapeId, [
                'group_id' => (int)$transfer['group_id'],
                'transfer_id' => $transferId,
                'stream_type' => $streamType,
            ]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'findesk_transfer_confirm_failed', 'message' => $e->getMessage()];
    }

    return ['ok' => true, 'transfer' => ql_findesk_transfer_public(ql_findesk_transfer_row($transferId))];
}

function ql_findesk_transfer_cancel(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $transferId = (int)($input['id'] ?? $input['transfer_id'] ?? 0);
    $reason = trim((string)($input['reason'] ?? ''));
    $confirmPhrase = trim((string)($input['confirm_phrase'] ?? $input['confirmation'] ?? ''));
    $transfer = ql_findesk_transfer_row($transferId);
    if (!$transfer) {
        return ['ok' => false, 'error' => 'transfer_not_found'];
    }
    $scope = ql_findesk_scope((int)$transfer['group_id'], $userId);
    if (!$scope || empty($scope['can_manage_money'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    if ((string)$transfer['state'] !== 'pending') {
        return ['ok' => false, 'error' => 'transfer_not_pending'];
    }
    if ($reason === '') {
        return ['ok' => false, 'error' => 'empty_cancel_reason'];
    }
    if ($confirmPhrase !== 'ОТМЕНИТЬ') {
        return ['ok' => false, 'error' => 'invalid_cancel_confirmation'];
    }

    ql_db()->prepare("
        UPDATE findesk_transfers
        SET state = 'cancelled',
            cancelled_by_user_id = ?,
            cancelled_at = NOW(),
            updated_at = NOW()
        WHERE id = ? AND state = 'pending'
        LIMIT 1
    ")->execute([$userId, $transferId]);

    $next = ql_findesk_transfer_row($transferId) ?: [];
    ql_findesk_transfer_event($transferId, (int)$transfer['group_id'], $userId, 'cancelled', $transfer, $next);
    ql_audit_write($userId, 'findesk_transfer_cancelled', 'findesk_transfer', $transferId, [
        'group_id' => (int)$transfer['group_id'],
        'reason' => $reason,
        'previous_state' => 'pending',
    ]);

    return ['ok' => true, 'transfer' => ql_findesk_transfer_public($next)];
}

function ql_findesk_report_transfer_adjustments(array $items, int $groupId): array
{
    $adjustments = [
        'cash' => ['issued' => 0.0],
        'card' => ['issued' => 0.0],
    ];
    if ($groupId <= 0 || !$items) {
        return $adjustments;
    }

    $tapeIds = array_values(array_unique(array_filter(array_map(
        static fn($item) => (int)($item['tape_id'] ?? 0),
        $items
    ))));
    if (!$tapeIds) {
        return $adjustments;
    }

    $placeholders = implode(',', array_fill(0, count($tapeIds), '?'));
    $params = array_merge([$groupId], $tapeIds);
    $stmt = ql_db()->prepare("
        SELECT stream_type, COALESCE(SUM(amount), 0) AS issued
        FROM findesk_transfers
        WHERE group_id = ?
          AND state = 'active'
          AND on_the_go_tape_id IN ({$placeholders})
        GROUP BY stream_type
    ");
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $streamType = ql_findesk_stream_type($row['stream_type'] ?? 'cash');
        $adjustments[$streamType]['issued'] = round((float)($row['issued'] ?? 0), 2);
    }

    return $adjustments;
}

function ql_findesk_report_summary(array $items, int $groupId = 0): array
{
    $cash = ['received' => 0.0, 'spent' => 0.0, 'remaining' => 0.0, 'issued' => 0.0, 'items' => 0];
    $card = ['received' => 0.0, 'spent' => 0.0, 'remaining' => 0.0, 'issued' => 0.0, 'items' => 0];

    foreach ($items as $item) {
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
        if (($item['stream_type'] ?? 'cash') === 'card') {
            $card['spent'] += (float)($summary['card_out'] ?? $summary['spent_total'] ?? 0);
            $card['remaining'] += (float)($summary['after_amount'] ?? 0);
            $card['items']++;
        } else {
            $cash['received'] += (float)($summary['cash_in'] ?? 0);
            $cash['spent'] += (float)($summary['cash_out'] ?? 0);
            $cash['remaining'] += (float)($summary['cash_left'] ?? $summary['after_amount'] ?? 0);
            $cash['items']++;
        }
    }

    $adjustments = ql_findesk_report_transfer_adjustments($items, $groupId);
    $cash['issued'] = (float)($adjustments['cash']['issued'] ?? 0);
    $card['issued'] = (float)($adjustments['card']['issued'] ?? 0);
    $cash['received'] -= $cash['issued'];
    $cash['remaining'] -= $cash['issued'];
    $card['remaining'] -= $card['issued'];

    foreach (['received', 'spent', 'remaining', 'issued'] as $key) {
        $cash[$key] = round($cash[$key], 2);
        $card[$key] = round($card[$key], 2);
    }

    return [
        'cash' => $cash,
        'card' => $card,
        'total' => [
            'received' => round($cash['received'] + $card['received'], 2),
            'spent' => round($cash['spent'] + $card['spent'], 2),
            'remaining' => round($cash['remaining'] + $card['remaining'], 2),
            'issued' => round($cash['issued'] + $card['issued'], 2),
            'items' => (int)$cash['items'] + (int)$card['items'],
        ],
    ];
}

function ql_findesk_report_item_from_tape(array $tape): array
{
    $tapeId = (int)($tape['tape_id'] ?? $tape['id'] ?? 0);
    $summary = function_exists('ql_on_the_go_card_summary') ? ql_on_the_go_card_summary($tapeId) : [];
    return [
        'tape_id' => $tapeId,
        'owner_user_id' => (int)($tape['owner_user_id'] ?? $tape['user_id'] ?? 0),
        'owner_name' => (string)($tape['owner_name'] ?? $tape['owner_email'] ?? ''),
        'title' => (string)($tape['title'] ?? 'Journal'),
        'stream_type' => ql_findesk_stream_type($tape['stream_type'] ?? 'cash'),
        'submitted_at' => (string)($tape['submitted_at'] ?? ''),
        'summary' => $summary,
    ];
}

function ql_findesk_ready_tapes(int $groupId): array
{
    $stmt = ql_db()->prepare("
        SELECT
            t.id,
            t.user_id,
            t.group_id,
            t.title,
            t.stream_type,
            t.submitted_at,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_name,
            u.email AS owner_email
        FROM on_the_go_tapes t
        JOIN users u ON u.id = t.user_id
        LEFT JOIN group_members gm ON gm.group_id = t.group_id AND gm.user_id = t.user_id
        WHERE t.group_id = ?
          AND t.status <> 'archived'
          AND t.archived_at IS NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND t.submitted_at IS NOT NULL
          AND EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.review_status <> 'archived'
              LIMIT 1
          )
        ORDER BY t.submitted_at ASC, t.id ASC
        LIMIT 200
    ");
    $stmt->execute([$groupId]);
    return array_map('ql_findesk_report_item_from_tape', $stmt->fetchAll());
}

function ql_findesk_report_draft(int $groupId, int $userId, bool $create = false): ?array
{
    $stmt = ql_db()->prepare("
        SELECT *
        FROM findesk_reports
        WHERE group_id = ?
          AND created_by_user_id = ?
          AND status = 'draft'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$groupId, $userId]);
    $row = $stmt->fetch();
    if ($row || !$create) {
        return $row ?: null;
    }

    ql_db()->prepare("
        INSERT INTO findesk_reports (group_id, created_by_user_id, status)
        VALUES (?, ?, 'draft')
    ")->execute([$groupId, $userId]);

    $id = (int)ql_db()->lastInsertId();
    $stmt = ql_db()->prepare("SELECT * FROM findesk_reports WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function ql_findesk_report_items(int $reportId): array
{
    if ($reportId <= 0) {
        return [];
    }
    $stmt = ql_db()->prepare("
        SELECT
            ri.*,
            t.title,
            t.submitted_at,
            COALESCE(gm.display_name, u.display_name, u.email) AS owner_name,
            u.email AS owner_email
        FROM findesk_report_items ri
        JOIN on_the_go_tapes t ON t.id = ri.tape_id
        JOIN users u ON u.id = ri.owner_user_id
        LEFT JOIN group_members gm ON gm.group_id = (SELECT group_id FROM findesk_reports WHERE id = ri.report_id) AND gm.user_id = ri.owner_user_id
        WHERE ri.report_id = ?
          AND ri.state <> 'detached'
        ORDER BY ri.attached_at ASC, ri.id ASC
    ");
    $stmt->execute([$reportId]);

    return array_map(static function (array $row): array {
        $item = ql_findesk_report_item_from_tape($row);
        $item['report_item_id'] = (int)$row['id'];
        $item['state'] = (string)$row['state'];
        $item['attached_at'] = (string)($row['attached_at'] ?? '');
        return $item;
    }, $stmt->fetchAll());
}

function ql_findesk_report_public(?array $report): ?array
{
    if (!$report) {
        return null;
    }
    return [
        'id' => (int)$report['id'],
        'group_id' => (int)$report['group_id'],
        'created_by_user_id' => (int)$report['created_by_user_id'],
        'status' => (string)$report['status'],
        'period_from' => (string)($report['period_from'] ?? ''),
        'period_to' => (string)($report['period_to'] ?? ''),
        'cash_summary' => json_decode((string)($report['cash_summary_json'] ?? '{}'), true) ?: [],
        'card_summary' => json_decode((string)($report['card_summary_json'] ?? '{}'), true) ?: [],
        'total_summary' => json_decode((string)($report['total_summary_json'] ?? '{}'), true) ?: [],
        'created_at' => (string)($report['created_at'] ?? ''),
        'finalized_at' => (string)($report['finalized_at'] ?? ''),
    ];
}

function ql_findesk_report_assembly_get(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $scope = ql_findesk_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_reports'])) {
        return ['ok' => false, 'error' => $scope ? 'access_denied' : 'not_group_member'];
    }

    $draft = ql_findesk_report_draft($groupId, $userId, false);
    $attached = $draft ? ql_findesk_report_items((int)$draft['id']) : [];
    $ready = ql_findesk_ready_tapes($groupId);
    $attachedIds = array_flip(array_map(static fn($item) => (int)$item['tape_id'], $attached));
    $ready = array_values(array_filter($ready, static fn($item) => !isset($attachedIds[(int)$item['tape_id']])));
    $summary = ql_findesk_report_summary($attached, $groupId);

    return [
        'ok' => true,
        'draft_report' => ql_findesk_report_public($draft),
        'attached_items' => $attached,
        'ready_items' => $ready,
        'summary' => $summary,
    ];
}

function ql_findesk_report_item_attach(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $tapeId = (int)($input['tape_id'] ?? 0);
    $scope = ql_findesk_scope($groupId, $userId);
    if (!$scope || empty($scope['can_write_reports'])) {
        return ['ok' => false, 'error' => $scope ? 'access_denied' : 'not_group_member'];
    }

    $tapeStmt = ql_db()->prepare("SELECT * FROM on_the_go_tapes WHERE id = ? AND group_id = ? LIMIT 1");
    $tapeStmt->execute([$tapeId, $groupId]);
    $tape = $tapeStmt->fetch();
    if (!$tape) {
        return ['ok' => false, 'error' => 'tape_not_found'];
    }
    if (empty($tape['submitted_at'])) {
        return ['ok' => false, 'error' => 'journal_not_submitted'];
    }

    $include = function_exists('ql_on_the_go_card_include')
        ? ql_on_the_go_card_include(['id' => $tapeId, 'group_id' => $groupId])
        : ['ok' => true];
    if (empty($include['ok'])) {
        return $include;
    }

    $draft = ql_findesk_report_draft($groupId, $userId, true);
    $item = ql_findesk_report_item_from_tape($tape);
    ql_db()->prepare("
        INSERT INTO findesk_report_items
            (report_id, tape_id, owner_user_id, stream_type, state, snapshot_json, attached_at)
        VALUES (?, ?, ?, ?, 'attached', ?, NOW())
        ON DUPLICATE KEY UPDATE
            state = 'attached',
            snapshot_json = VALUES(snapshot_json),
            attached_at = COALESCE(attached_at, NOW()),
            detached_at = NULL,
            updated_at = NOW()
    ")->execute([
        (int)$draft['id'],
        $tapeId,
        (int)$tape['user_id'],
        ql_findesk_stream_type($tape['stream_type'] ?? 'cash'),
        ql_findesk_json($item),
    ]);

    ql_audit_write($userId, 'findesk_report_item_attached', 'on_the_go_tape', $tapeId, [
        'group_id' => $groupId,
        'report_id' => (int)$draft['id'],
    ]);

    return ql_findesk_report_assembly_get(['group_id' => $groupId]);
}

function ql_findesk_report_finalize(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $reportId = (int)($input['report_id'] ?? 0);
    $reason = trim((string)($input['reason'] ?? ''));
    $confirmPhrase = trim((string)($input['confirm_phrase'] ?? $input['confirmation'] ?? ''));
    $scope = ql_findesk_scope($groupId, $userId);
    if (!$scope || empty($scope['can_write_reports'])) {
        return ['ok' => false, 'error' => $scope ? 'access_denied' : 'not_group_member'];
    }
    if ($reason === '') {
        return ['ok' => false, 'error' => 'empty_finalize_reason'];
    }
    if ($confirmPhrase !== 'УТВЕРДИТЬ') {
        return ['ok' => false, 'error' => 'invalid_finalize_confirmation'];
    }
    $report = $reportId > 0 ? null : ql_findesk_report_draft($groupId, $userId, false);
    if ($reportId > 0) {
        $stmt = ql_db()->prepare("SELECT * FROM findesk_reports WHERE id = ? AND group_id = ? LIMIT 1");
        $stmt->execute([$reportId, $groupId]);
        $report = $stmt->fetch() ?: null;
    }
    if (!$report || (string)$report['status'] !== 'draft') {
        return ['ok' => false, 'error' => 'draft_report_not_found'];
    }

    $items = ql_findesk_report_items((int)$report['id']);
    if (!$items) {
        return ['ok' => false, 'error' => 'empty_report'];
    }
    $summary = ql_findesk_report_summary($items, $groupId);
    $snapshot = [
        'snapshot_type' => 'findesk_phase2_report',
        'snapshot_version' => 1,
        'group_id' => $groupId,
        'items' => $items,
        'summary' => $summary,
        'finalized_by_user_id' => $userId,
        'finalized_at' => date('Y-m-d H:i:s'),
    ];

    $db = ql_db();
    $db->beginTransaction();
    try {
        $db->prepare("
            UPDATE findesk_reports
            SET status = 'finalized',
                cash_summary_json = ?,
                card_summary_json = ?,
                total_summary_json = ?,
                snapshot_json = ?,
                finalized_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND status = 'draft'
            LIMIT 1
        ")->execute([
            ql_findesk_json($summary['cash']),
            ql_findesk_json($summary['card']),
            ql_findesk_json($summary['total']),
            ql_findesk_json($snapshot),
            (int)$report['id'],
        ]);
        $ids = array_values(array_filter(array_map(static fn($item) => (int)$item['tape_id'], $items)));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db->prepare("
                UPDATE on_the_go_tapes
                SET status = 'archived',
                    closed_at = COALESCE(closed_at, NOW()),
                    archived_at = COALESCE(archived_at, NOW()),
                    updated_at = NOW()
                WHERE id IN ({$placeholders})
            ")->execute($ids);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'findesk_report_finalize_failed', 'message' => $e->getMessage()];
    }

    ql_audit_write($userId, 'findesk_report_finalized', 'findesk_report', (int)$report['id'], [
        'group_id' => $groupId,
        'summary' => $summary,
        'items' => count($items),
        'reason' => $reason,
    ]);

    return ql_findesk_report_detail(['report_id' => (int)$report['id']]);
}

function ql_findesk_report_list(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $scope = ql_findesk_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_reports'])) {
        return ['ok' => false, 'error' => $scope ? 'access_denied' : 'not_group_member'];
    }

    $stmt = ql_db()->prepare("
        SELECT *
        FROM findesk_reports
        WHERE group_id = ?
          AND status IN ('finalized','archived')
        ORDER BY finalized_at DESC, id DESC
        LIMIT 100
    ");
    $stmt->execute([$groupId]);

    return ['ok' => true, 'reports' => array_map('ql_findesk_report_public', $stmt->fetchAll())];
}

function ql_findesk_report_archive_export(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $scope = ql_findesk_scope($groupId, $userId);
    if (!$scope || empty($scope['can_view_reports'])) {
        return ['ok' => false, 'error' => $scope ? 'access_denied' : 'not_group_member'];
    }

    $groupStmt = ql_db()->prepare("SELECT id, name FROM groups WHERE id = ? LIMIT 1");
    $groupStmt->execute([$groupId]);
    $group = $groupStmt->fetch() ?: ['id' => $groupId, 'name' => ''];

    $limit = (int)($input['limit'] ?? 500);
    if ($limit < 1 || $limit > 1000) {
        $limit = 500;
    }

    $stmt = ql_db()->prepare("
        SELECT *
        FROM findesk_reports
        WHERE group_id = ?
          AND status IN ('finalized','archived')
        ORDER BY finalized_at ASC, id ASC
        LIMIT {$limit}
    ");
    $stmt->execute([$groupId]);
    $reports = [];
    foreach ($stmt->fetchAll() as $row) {
        $report = ql_findesk_report_public($row);
        $reports[] = [
            'report' => $report,
            'items' => ql_findesk_report_items((int)$row['id']),
            'snapshot' => json_decode((string)($row['snapshot_json'] ?? '{}'), true) ?: [],
        ];
    }

    return [
        'ok' => true,
        'package' => [
            'package_type' => 'findesk_archive_package',
            'package_version' => 1,
            'exported_at' => date('c'),
            'exported_by_user_id' => $userId,
            'group' => [
                'id' => (int)$group['id'],
                'name' => (string)($group['name'] ?? ''),
            ],
            'reports_count' => count($reports),
            'reports' => $reports,
        ],
    ];
}

function ql_findesk_report_detail(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $reportId = (int)($input['report_id'] ?? $input['id'] ?? 0);
    $stmt = ql_db()->prepare("SELECT * FROM findesk_reports WHERE id = ? LIMIT 1");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    if (!$report) {
        return ['ok' => false, 'error' => 'report_not_found'];
    }
    $scope = ql_findesk_scope((int)$report['group_id'], $userId);
    if (!$scope || empty($scope['can_view_reports'])) {
        return ['ok' => false, 'error' => $scope ? 'access_denied' : 'not_group_member'];
    }

    $snapshot = json_decode((string)($report['snapshot_json'] ?? '{}'), true) ?: [];
    return [
        'ok' => true,
        'report' => ql_findesk_report_public($report),
        'items' => ql_findesk_report_items((int)$report['id']),
        'snapshot' => $snapshot,
    ];
}

function ql_findesk_protected_action_prepare(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $actionType = trim((string)($input['action_type'] ?? ''));
    $entityType = trim((string)($input['entity_type'] ?? ''));
    $entityId = (int)($input['entity_id'] ?? 0);

    if ($actionType === '' || $entityType === '' || $entityId <= 0) {
        return ['ok' => false, 'error' => 'invalid_protected_action'];
    }
    if ($groupId > 0) {
        $scope = ql_findesk_scope($groupId, $userId);
        if (!$scope || (empty($scope['can_manage_money']) && empty($scope['can_write_reports']))) {
            return ['ok' => false, 'error' => $scope ? 'access_denied' : 'not_group_member'];
        }
    }

    $preview = [
        'action_type' => $actionType,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'consequence' => 'This action changes a fixed FinDesk lifecycle stage and requires a reason plus CONFIRM.',
    ];

    ql_db()->prepare("
        INSERT INTO findesk_protected_actions
            (group_id, action_type, entity_type, entity_id, preview_json, previous_state_json, status, performed_by_user_id)
        VALUES (?, ?, ?, ?, ?, ?, 'prepared', ?)
    ")->execute([
        $groupId ?: null,
        $actionType,
        $entityType,
        $entityId,
        ql_findesk_json($preview),
        ql_findesk_json($input['previous_state'] ?? []),
        $userId,
    ]);

    return [
        'ok' => true,
        'protected_action' => [
            'id' => (int)ql_db()->lastInsertId(),
            'status' => 'prepared',
            'required_phrase' => 'CONFIRM',
            'preview' => $preview,
        ],
    ];
}

function ql_findesk_protected_action_confirm(array $input = []): array
{
    ql_findesk_ensure_schema();
    $user = ql_require_user();
    $userId = (int)$user['id'];
    $id = (int)($input['id'] ?? $input['protected_action_id'] ?? 0);
    $reason = trim((string)($input['reason'] ?? ''));
    $phrase = trim((string)($input['confirmation_phrase'] ?? $input['confirm'] ?? ''));

    if ($reason === '') {
        return ['ok' => false, 'error' => 'empty_reason'];
    }
    if ($phrase !== 'CONFIRM') {
        return ['ok' => false, 'error' => 'confirmation_phrase_required'];
    }

    $stmt = ql_db()->prepare("SELECT * FROM findesk_protected_actions WHERE id = ? AND status = 'prepared' LIMIT 1");
    $stmt->execute([$id]);
    $action = $stmt->fetch();
    if (!$action) {
        return ['ok' => false, 'error' => 'protected_action_not_found'];
    }
    if ((int)$action['performed_by_user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $db = ql_db();
    $db->beginTransaction();
    try {
        $newState = ['confirmed' => true, 'confirmed_at' => date('Y-m-d H:i:s')];
        if ((string)$action['action_type'] === 'report_item_detach' && (string)$action['entity_type'] === 'findesk_report_item') {
            $itemId = (int)$action['entity_id'];
            $itemStmt = $db->prepare("SELECT * FROM findesk_report_items WHERE id = ? LIMIT 1");
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch();
            if ($item) {
                $db->prepare("UPDATE findesk_report_items SET state = 'detached', detached_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1")->execute([$itemId]);
                $db->prepare("UPDATE on_the_go_captures SET reportable = 0, updated_at = NOW() WHERE tape_id = ?")->execute([(int)$item['tape_id']]);
                $newState['detached_item_id'] = $itemId;
                $newState['tape_id'] = (int)$item['tape_id'];
            }
        }
        if ((string)$action['action_type'] === 'report_archive' && (string)$action['entity_type'] === 'findesk_report') {
            $db->prepare("UPDATE findesk_reports SET status = 'archived', archived_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1")->execute([(int)$action['entity_id']]);
            $newState['archived_report_id'] = (int)$action['entity_id'];
        }

        $db->prepare("
            UPDATE findesk_protected_actions
            SET reason = ?,
                confirmation_phrase = 'CONFIRM',
                status = 'confirmed',
                new_state_json = ?,
                confirmed_at = NOW()
            WHERE id = ? AND status = 'prepared'
            LIMIT 1
        ")->execute([$reason, ql_findesk_json($newState), $id]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'protected_action_confirm_failed', 'message' => $e->getMessage()];
    }

    ql_audit_write($userId, 'findesk_protected_action_confirmed', 'findesk_protected_action', $id, [
        'group_id' => (int)($action['group_id'] ?? 0),
        'action_type' => (string)$action['action_type'],
        'entity_type' => (string)$action['entity_type'],
        'entity_id' => (int)$action['entity_id'],
        'reason' => $reason,
    ]);

    return ['ok' => true, 'protected_action_id' => $id, 'status' => 'confirmed'];
}
