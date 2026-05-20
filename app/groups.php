<?php

require_once __DIR__ . '/ledger.php';

function ql_group_membership(int $groupId, int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT *
        FROM group_members
        WHERE group_id = ?
          AND user_id = ?
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$groupId, $userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function ql_group_is_admin(int $groupId, int $userId): bool
{
    $m = ql_group_membership($groupId, $userId);
    return $m && $m['role'] === 'admin';
}

function ql_group_create(array $input): array
{
    $user = ql_require_user();
    $name = trim((string)($input['name'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'error' => 'empty_group_name'];
    }

    if (mb_strlen($name) > 190) {
        return ['ok' => false, 'error' => 'group_name_too_long'];
    }

    $db = ql_db();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            INSERT INTO groups (name, description, created_by)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $name,
            trim((string)($input['description'] ?? '')) ?: null,
            (int)$user['id']
        ]);

        $groupId = (int)$db->lastInsertId();

        $member = $db->prepare("
            INSERT INTO group_members (group_id, user_id, display_name, role, status)
            VALUES (?, ?, ?, 'admin', 'active')
        ");
        $member->execute([
            $groupId,
            (int)$user['id'],
            $user['display_name'] ?: $user['email']
        ]);

        $db->commit();

        return ['ok' => true, 'group' => ql_group_get($groupId, (int)$user['id'])];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()];
    }
}

function ql_group_get(int $groupId, int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT
            g.id,
            g.name,
            g.description,
            g.created_by,
            g.status,
            g.created_at,
            gm.role,
            gm.display_name AS member_display_name
        FROM groups g
        JOIN group_members gm ON gm.group_id = g.id
        WHERE g.id = ?
          AND gm.user_id = ?
          AND gm.status = 'active'
          AND g.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$groupId, $userId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function ql_group_list(array $input = []): array
{
    $user = ql_require_user();

    $stmt = ql_db()->prepare("
        SELECT
            g.id,
            g.name,
            g.description,
            g.created_by,
            g.status,
            g.created_at,
            gm.role,
            gm.display_name AS member_display_name,
            (
                SELECT COUNT(*)
                FROM group_members gm2
                WHERE gm2.group_id = g.id
                  AND gm2.status = 'active'
            ) AS member_count
        FROM groups g
        JOIN group_members gm ON gm.group_id = g.id
        WHERE gm.user_id = ?
          AND gm.status = 'active'
          AND g.status = 'active'
        ORDER BY g.created_at DESC, g.id DESC
    ");
    $stmt->execute([(int)$user['id']]);

    return ['ok' => true, 'groups' => $stmt->fetchAll()];
}

function ql_group_rename(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);
    $name = trim((string)($input['name'] ?? ''));

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    if ($name === '') {
        return ['ok' => false, 'error' => 'empty_group_name'];
    }

    if (!ql_group_is_admin($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'admin_required'];
    }

    $stmt = ql_db()->prepare("
        UPDATE groups
        SET name = ?
        WHERE id = ?
          AND status = 'active'
    ");
    $stmt->execute([$name, $groupId]);

    return ['ok' => true, 'group' => ql_group_get($groupId, (int)$user['id'])];
}

function ql_group_invite_create(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);
    $channel = (string)($input['channel'] ?? 'copy');

    $allowedChannels = ['email','whatsapp','viber','telegram','copy','qr'];
    if (!in_array($channel, $allowedChannels, true)) {
        $channel = 'copy';
    }

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    if (!ql_group_is_admin($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'admin_required'];
    }

    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);
    $hint = substr($token, 0, 8);

    $stmt = ql_db()->prepare("
        INSERT INTO group_invites
            (group_id, invited_by, token_hash, token_hint, channel, max_uses, expires_at)
        VALUES
            (?, ?, ?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    $stmt->execute([
        $groupId,
        (int)$user['id'],
        $hash,
        $hint,
        $channel
    ]);

    $inviteId = (int)ql_db()->lastInsertId();

    $share = ql_db()->prepare("
        INSERT INTO invite_share_events (invite_id, user_id, channel)
        VALUES (?, ?, ?)
    ");
    $share->execute([$inviteId, (int)$user['id'], $channel]);

    $url = rtrim(ql_config()['app_url'], '/') . '/app.php?invite=' . $token;

    return [
        'ok' => true,
        'invite' => [
            'id' => $inviteId,
            'group_id' => $groupId,
            'channel' => $channel,
            'url' => $url,
            'expires_in_days' => 7
        ],
        'share_links' => [
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode('Join my Quick Ledger group: ' . $url),
            'telegram' => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode('Join my Quick Ledger group'),
            'viber' => 'viber://forward?text=' . rawurlencode('Join my Quick Ledger group: ' . $url),
            'email' => 'mailto:?subject=' . rawurlencode('Quick Ledger group invite') . '&body=' . rawurlencode('Join my Quick Ledger group: ' . $url),
        ]
    ];
}

function ql_group_join(array $input): array
{
    $user = ql_require_user();
    $token = trim((string)($input['token'] ?? ''));

    if ($token === '') {
        return ['ok' => false, 'error' => 'missing_token'];
    }

    $hash = hash('sha256', $token);

    $stmt = ql_db()->prepare("
        SELECT gi.*, g.name AS group_name
        FROM group_invites gi
        JOIN groups g ON g.id = gi.group_id
        WHERE gi.token_hash = ?
          AND gi.status = 'active'
          AND gi.expires_at > NOW()
          AND gi.used_count < gi.max_uses
          AND g.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $invite = $stmt->fetch();

    if (!$invite) {
        return ['ok' => false, 'error' => 'invite_invalid_or_expired'];
    }

    $db = ql_db();
    $db->beginTransaction();

    try {
        $existing = ql_group_membership((int)$invite['group_id'], (int)$user['id']);

        if (!$existing) {
            $ins = $db->prepare("
                INSERT INTO group_members (group_id, user_id, display_name, role, status)
                VALUES (?, ?, ?, 'member', 'active')
            ");
            $ins->execute([
                (int)$invite['group_id'],
                (int)$user['id'],
                $user['display_name'] ?: $user['email']
            ]);
        }

        $upd = $db->prepare("
            UPDATE group_invites
            SET used_count = used_count + 1,
                status = CASE WHEN used_count + 1 >= max_uses THEN 'used' ELSE status END
            WHERE id = ?
        ");
        $upd->execute([(int)$invite['id']]);

        $db->commit();

        return [
            'ok' => true,
            'group' => ql_group_get((int)$invite['group_id'], (int)$user['id'])
        ];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()];
    }
}

function ql_group_members(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    if (!ql_group_membership($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }

    $stmt = ql_db()->prepare("
        SELECT
            gm.user_id,
            gm.display_name,
            gm.role,
            gm.status,
            gm.joined_at,
            u.email
        FROM group_members gm
        JOIN users u ON u.id = gm.user_id
        WHERE gm.group_id = ?
          AND gm.status = 'active'
        ORDER BY gm.role ASC, gm.joined_at ASC
    ");
    $stmt->execute([$groupId]);

    return [
        'ok' => true,
        'members' => $stmt->fetchAll()
    ];
}
