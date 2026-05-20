<?php

require_once __DIR__ . '/groups.php';

function ql_message_require_group_member(int $groupId, int $userId): ?array
{
    if ($groupId <= 0) {
        return null;
    }

    return ql_group_membership($groupId, $userId);
}

function ql_message_send(array $input): array
{
    $user = ql_require_user();

    $groupId = (int)($input['group_id'] ?? 0);
    $text = trim((string)($input['message_text'] ?? ''));

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    if (!ql_message_require_group_member($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }

    if ($text === '') {
        return ['ok' => false, 'error' => 'empty_message'];
    }

    if (mb_strlen($text) > 3000) {
        return ['ok' => false, 'error' => 'message_too_long'];
    }

    $stmt = ql_db()->prepare("
        INSERT INTO group_messages (group_id, sender_user_id, message_text, message_type)
        VALUES (?, ?, ?, 'text')
    ");
    $stmt->execute([$groupId, (int)$user['id'], $text]);

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
    $stmt = ql_db()->prepare("
        SELECT
            gm.id,
            gm.group_id,
            gm.sender_user_id,
            gm.message_text,
            gm.message_type,
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
    return $row ?: null;
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

    if (!ql_message_require_group_member($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }

    $stmt = ql_db()->prepare("
        SELECT
            gm.id,
            gm.group_id,
            gm.sender_user_id,
            gm.message_text,
            gm.message_type,
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

    $messages = array_reverse($stmt->fetchAll());

    return ['ok' => true, 'messages' => $messages];
}

function ql_message_unread(array $input = []): array
{
    $user = ql_require_user();

    $stmt = ql_db()->prepare("
        SELECT
            gm.id,
            gm.group_id,
            g.name AS group_name,
            gm.sender_user_id,
            COALESCE(mem.display_name, u.display_name, u.email) AS sender_name,
            u.email AS sender_email,
            gm.message_text,
            gm.created_at
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

    $messages = $stmt->fetchAll();

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

    if (!ql_message_require_group_member($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'not_group_member'];
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
