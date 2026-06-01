<?php

require_once __DIR__ . '/groups.php';

function ql_message_require_group_member(int $groupId, int $userId): ?array
{
    if ($groupId <= 0) {
        return null;
    }

    return ql_group_membership($groupId, $userId);
}

function ql_message_can_use_group_messages(?array $membership): bool
{
    if (!$membership) {
        return false;
    }

    $permissions = $membership['permissions'] ?? [];
    if (!is_array($permissions)) {
        $permissions = [];
    }
    $accessLevel = (string)($membership['access_level'] ?? 'base');
    $role = (string)($membership['role'] ?? '');

    return $role === 'admin'
        || in_array($accessLevel, ['manager', 'advanced'], true)
        || !empty($permissions['can_moderate'])
        || !empty($permissions['can_view_group_reports'])
        || !empty($permissions['can_manage_money'])
        || !empty($permissions['can_manage_members']);
}

function ql_message_table_columns(): array
{
    static $columns = null;
    if ($columns !== null) {
        return $columns;
    }

    $columns = [
        'report_id' => function_exists('ql_group_table_has_column') && ql_group_table_has_column('group_messages', 'report_id'),
        'tape_id' => function_exists('ql_group_table_has_column') && ql_group_table_has_column('group_messages', 'tape_id'),
        'capture_id' => function_exists('ql_group_table_has_column') && ql_group_table_has_column('group_messages', 'capture_id'),
        'advance_id' => function_exists('ql_group_table_has_column') && ql_group_table_has_column('group_messages', 'advance_id'),
    ];

    return $columns;
}

function ql_message_context_select(string $alias = 'gm'): string
{
    $columns = ql_message_table_columns();
    $parts = [];
    foreach (['report_id', 'tape_id', 'capture_id', 'advance_id'] as $column) {
        $parts[] = !empty($columns[$column])
            ? "{$alias}.{$column}"
            : "NULL AS {$column}";
    }

    return implode(",\n            ", $parts);
}

function ql_message_validate_context_ref(string $type, int $id, int $groupId): bool
{
    if ($id <= 0) {
        return true;
    }

    try {
        if ($type === 'report_id') {
            $stmt = ql_db()->prepare("
                SELECT COUNT(*)
                FROM audit_log
                WHERE id = ?
                  AND action = 'ledger_group_report_finalized'
                  AND entity_type = 'group'
                  AND entity_id = ?
            ");
            $stmt->execute([$id, $groupId]);
            return (int)$stmt->fetchColumn() > 0;
        }

        if ($type === 'tape_id') {
            $stmt = ql_db()->prepare("
                SELECT COUNT(*)
                FROM on_the_go_tapes
                WHERE id = ?
                  AND group_id = ?
            ");
            $stmt->execute([$id, $groupId]);
            return (int)$stmt->fetchColumn() > 0;
        }

        if ($type === 'capture_id') {
            $stmt = ql_db()->prepare("
                SELECT COUNT(*)
                FROM on_the_go_captures c
                JOIN on_the_go_tapes t ON t.id = c.tape_id
                WHERE c.id = ?
                  AND t.group_id = ?
            ");
            $stmt->execute([$id, $groupId]);
            return (int)$stmt->fetchColumn() > 0;
        }

        if ($type === 'advance_id') {
            $stmt = ql_db()->prepare("
                SELECT COUNT(*)
                FROM cash_advances
                WHERE id = ?
                  AND group_id = ?
            ");
            $stmt->execute([$id, $groupId]);
            return (int)$stmt->fetchColumn() > 0;
        }
    } catch (Throwable $e) {
        return false;
    }

    return false;
}

function ql_message_context_for_group(int $groupId, array $input): array
{
    $context = [];
    foreach (['report_id', 'tape_id', 'capture_id', 'advance_id'] as $key) {
        $value = (int)($input[$key] ?? 0);
        if ($value <= 0) {
            continue;
        }
        if (!ql_message_validate_context_ref($key, $value, $groupId)) {
            return ['ok' => false, 'error' => 'invalid_message_context', 'field' => $key];
        }
        $context[$key] = $value;
    }

    return ['ok' => true, 'context' => $context];
}

function ql_message_public_row(?array $row): ?array
{
    if (!$row) {
        return null;
    }

    foreach (['id', 'group_id', 'sender_user_id', 'report_id', 'tape_id', 'capture_id', 'advance_id', 'is_read'] as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null) {
            $row[$key] = (int)$row[$key];
        }
    }
    $row['context_links'] = [
        'report_id' => (int)($row['report_id'] ?? 0),
        'tape_id' => (int)($row['tape_id'] ?? 0),
        'capture_id' => (int)($row['capture_id'] ?? 0),
        'advance_id' => (int)($row['advance_id'] ?? 0),
    ];

    return $row;
}

function ql_message_send(array $input): array
{
    $user = ql_require_user();

    $groupId = (int)($input['group_id'] ?? 0);
    $text = trim((string)($input['message_text'] ?? ''));

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $membership = ql_message_require_group_member($groupId, (int)$user['id']);
    if (!$membership) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (!ql_message_can_use_group_messages($membership)) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    if ($text === '') {
        return ['ok' => false, 'error' => 'empty_message'];
    }

    if (mb_strlen($text) > 3000) {
        return ['ok' => false, 'error' => 'message_too_long'];
    }

    $contextResult = ql_message_context_for_group($groupId, $input);
    if (empty($contextResult['ok'])) {
        return $contextResult;
    }
    $context = $contextResult['context'] ?? [];
    $availableColumns = ql_message_table_columns();

    $insertColumns = ['group_id', 'sender_user_id', 'message_text', 'message_type'];
    $placeholders = ['?', '?', '?', '?'];
    $params = [$groupId, (int)$user['id'], $text, 'text'];
    foreach (['report_id', 'tape_id', 'capture_id', 'advance_id'] as $key) {
        if (empty($availableColumns[$key])) {
            continue;
        }
        $insertColumns[] = $key;
        $placeholders[] = '?';
        $params[] = $context[$key] ?? null;
    }

    $stmt = ql_db()->prepare("
        INSERT INTO group_messages (" . implode(', ', $insertColumns) . ")
        VALUES (" . implode(', ', $placeholders) . ")
    ");
    $stmt->execute($params);

    $messageId = (int)ql_db()->lastInsertId();

    $read = ql_db()->prepare("
        INSERT IGNORE INTO group_message_reads (message_id, user_id)
        VALUES (?, ?)
    ");
    $read->execute([$messageId, (int)$user['id']]);

    return [
        'ok' => true,
        'message' => ql_message_get($messageId, (int)$user['id'])
    ];
}

function ql_message_get(int $messageId, int $userId): ?array
{
    $contextSelect = ql_message_context_select('gm');
    $stmt = ql_db()->prepare("
        SELECT
            gm.id,
            gm.group_id,
            gm.sender_user_id,
            gm.message_text,
            gm.message_type,
            {$contextSelect},
            gm.created_at,
            COALESCE(mem.display_name, u.display_name, u.email) AS sender_name,
            u.email AS sender_email,
            CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read
        FROM group_messages gm
        JOIN users u ON u.id = gm.sender_user_id
        LEFT JOIN group_members mem ON mem.group_id = gm.group_id AND mem.user_id = gm.sender_user_id
        LEFT JOIN group_message_reads r ON r.message_id = gm.id AND r.user_id = ?
        WHERE gm.id = ?
          AND gm.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$userId, $messageId]);

    $row = $stmt->fetch();
    return ql_message_public_row($row ?: null);
}

function ql_message_list(array $input): array
{
    $user = ql_require_user();

    $groupId = (int)($input['group_id'] ?? 0);
    $limit = (int)($input['limit'] ?? 50);

    if ($limit < 1 || $limit > 200) {
        $limit = 50;
    }

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $membership = ql_message_require_group_member($groupId, (int)$user['id']);
    if (!$membership) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (!ql_message_can_use_group_messages($membership)) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $contextSelect = ql_message_context_select('gm');
    $stmt = ql_db()->prepare("
        SELECT
            gm.id,
            gm.group_id,
            gm.sender_user_id,
            gm.message_text,
            gm.message_type,
            {$contextSelect},
            gm.created_at,
            COALESCE(mem.display_name, u.display_name, u.email) AS sender_name,
            u.email AS sender_email,
            CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_read
        FROM group_messages gm
        JOIN users u ON u.id = gm.sender_user_id
        LEFT JOIN group_members mem ON mem.group_id = gm.group_id AND mem.user_id = gm.sender_user_id
        LEFT JOIN group_message_reads r ON r.message_id = gm.id AND r.user_id = ?
        WHERE gm.group_id = ?
          AND gm.deleted_at IS NULL
        ORDER BY gm.created_at DESC, gm.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([(int)$user['id'], $groupId]);

    $messages = array_map('ql_message_public_row', array_reverse($stmt->fetchAll()));

    return ['ok' => true, 'messages' => $messages];
}

function ql_message_unread(array $input = []): array
{
    $user = ql_require_user();

    $contextSelect = ql_message_context_select('gm');
    $stmt = ql_db()->prepare("
        SELECT
            gm.id,
            gm.group_id,
            g.name AS group_name,
            gm.sender_user_id,
            COALESCE(mem.display_name, u.display_name, u.email) AS sender_name,
            u.email AS sender_email,
            gm.message_text,
            {$contextSelect},
            gm.created_at
            ,
            current_member.role AS member_role_for_scope,
            current_member.access_level AS member_access_level_for_scope,
            current_member.permissions_json AS member_permissions_json_for_scope
        FROM group_messages gm
        JOIN groups g ON g.id = gm.group_id
        JOIN group_members current_member
          ON current_member.group_id = gm.group_id
         AND current_member.user_id = ?
         AND current_member.status = 'active'
        JOIN users u ON u.id = gm.sender_user_id
        LEFT JOIN group_members mem ON mem.group_id = gm.group_id AND mem.user_id = gm.sender_user_id
        LEFT JOIN group_message_reads r ON r.message_id = gm.id AND r.user_id = ?
        WHERE gm.deleted_at IS NULL
          AND gm.sender_user_id <> ?
          AND r.id IS NULL
        ORDER BY gm.created_at DESC, gm.id DESC
        LIMIT 20
    ");
    $stmt->execute([(int)$user['id'], (int)$user['id'], (int)$user['id']]);

    $messages = [];
    foreach ($stmt->fetchAll() as $row) {
        $membership = [
            'role' => (string)($row['member_role_for_scope'] ?? ''),
            'access_level' => (string)($row['member_access_level_for_scope'] ?? 'base'),
            'permissions_json' => (string)($row['member_permissions_json_for_scope'] ?? ''),
        ];
        $membership = ql_group_member_public($membership);
        if (!ql_message_can_use_group_messages($membership)) {
            continue;
        }
        unset($row['member_role_for_scope'], $row['member_access_level_for_scope'], $row['member_permissions_json_for_scope']);
        $messages[] = ql_message_public_row($row);
    }

    return [
        'ok' => true,
        'unread_count' => count($messages),
        'messages' => $messages
    ];
}

function ql_message_mark_read(array $input): array
{
    $user = ql_require_user();

    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $membership = ql_message_require_group_member($groupId, (int)$user['id']);
    if (!$membership) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (!ql_message_can_use_group_messages($membership)) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $stmt = ql_db()->prepare("
        INSERT IGNORE INTO group_message_reads (message_id, user_id)
        SELECT gm.id, ?
        FROM group_messages gm
        WHERE gm.group_id = ?
          AND gm.deleted_at IS NULL
    ");
    $stmt->execute([(int)$user['id'], $groupId]);

    return ['ok' => true];
}
