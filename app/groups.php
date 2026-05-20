<?php

require_once __DIR__ . '/ledger.php';

function ql_group_access_levels(): array
{
    return ['base', 'manager', 'advanced'];
}

function ql_group_normalize_access_level($level): string
{
    $level = strtolower(trim((string)$level));
    return in_array($level, ql_group_access_levels(), true) ? $level : 'base';
}

function ql_group_role_for_access(string $accessLevel): string
{
    return $accessLevel === 'advanced' ? 'admin' : 'member';
}

function ql_group_default_permissions(string $accessLevel): array
{
    if ($accessLevel === 'advanced') {
        return [
            'mode' => 'advanced',
            'can_use_on_the_go' => true,
            'can_use_captain_fin' => true,
            'can_moderate' => true,
            'can_view_group_reports' => true,
            'can_write_group_ledger' => true,
            'can_manage_money' => true,
            'can_manage_members' => true,
        ];
    }

    if ($accessLevel === 'manager') {
        return [
            'mode' => 'manager',
            'can_use_on_the_go' => true,
            'can_use_captain_fin' => true,
            'can_moderate' => true,
            'can_view_group_reports' => true,
            'can_write_group_ledger' => true,
            'can_manage_money' => false,
            'can_manage_members' => false,
        ];
    }

    return [
        'mode' => 'base',
        'can_use_on_the_go' => true,
        'can_use_captain_fin' => false,
        'can_moderate' => false,
        'can_view_group_reports' => false,
        'can_write_group_ledger' => false,
        'can_manage_money' => false,
        'can_manage_members' => false,
    ];
}

function ql_group_permissions_json(string $accessLevel): string
{
    return json_encode(ql_group_default_permissions($accessLevel), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ql_group_member_public(array $member): array
{
    $accessLevel = ql_group_normalize_access_level($member['access_level'] ?? $member['role'] ?? 'base');
    $permissions = json_decode((string)($member['permissions_json'] ?? ''), true);
    if (!is_array($permissions)) {
        $permissions = ql_group_default_permissions($accessLevel);
    }

    $member['access_level'] = $accessLevel;
    $member['permissions'] = $permissions;
    unset($member['permissions_json']);

    return $member;
}

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

    return $row ? ql_group_member_public($row) : null;
}

function ql_group_is_admin(int $groupId, int $userId): bool
{
    $m = ql_group_membership($groupId, $userId);
    return $m && (($m['role'] ?? '') === 'admin' || ($m['access_level'] ?? '') === 'advanced');
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
            INSERT INTO group_members (group_id, user_id, display_name, role, access_level, permissions_json, status)
            VALUES (?, ?, ?, 'admin', 'advanced', ?, 'active')
        ");
        $member->execute([
            $groupId,
            (int)$user['id'],
            $user['display_name'] ?: $user['email'],
            ql_group_permissions_json('advanced')
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
            gm.access_level,
            gm.permissions_json,
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

    return $row ? ql_group_member_public($row) : null;
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
            gm.access_level,
            gm.permissions_json,
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

    $groups = array_map('ql_group_member_public', $stmt->fetchAll());

    return ['ok' => true, 'groups' => $groups];
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
    $accessLevel = ql_group_normalize_access_level($input['access_level'] ?? 'base');
    $invitedEmail = ql_normalize_email((string)($input['invited_email'] ?? ''));

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

    if ($invitedEmail !== '' && !filter_var($invitedEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_invited_email'];
    }

    $token = bin2hex(random_bytes(24));
    $hash = hash('sha256', $token);
    $hint = substr($token, 0, 8);
    $permissionsJson = ql_group_permissions_json($accessLevel);

    $stmt = ql_db()->prepare("
        INSERT INTO group_invites
            (group_id, invited_by, token_hash, token_hint, invited_email, access_level, permissions_json, channel, max_uses, expires_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    $stmt->execute([
        $groupId,
        (int)$user['id'],
        $hash,
        $hint,
        $invitedEmail !== '' ? $invitedEmail : null,
        $accessLevel,
        $permissionsJson,
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
            'invited_email' => $invitedEmail !== '' ? $invitedEmail : null,
            'access_level' => $accessLevel,
            'permissions' => ql_group_default_permissions($accessLevel),
            'url' => $url,
            'expires_in_days' => 7
        ],
        'share_links' => [
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode('Join my Captain Fin group: ' . $url),
            'telegram' => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode('Join my Captain Fin group'),
            'viber' => 'viber://forward?text=' . rawurlencode('Join my Captain Fin group: ' . $url),
            'email' => 'mailto:?subject=' . rawurlencode('Captain Fin group invite') . '&body=' . rawurlencode('Join my Captain Fin group: ' . $url),
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

    $invitedEmail = ql_normalize_email((string)($invite['invited_email'] ?? ''));
    if ($invitedEmail !== '' && $invitedEmail !== ql_normalize_email((string)$user['email'])) {
        return ['ok' => false, 'error' => 'invite_email_mismatch'];
    }

    $db = ql_db();
    $db->beginTransaction();

    try {
        $existing = ql_group_membership((int)$invite['group_id'], (int)$user['id']);

        if (!$existing) {
            $accessLevel = ql_group_normalize_access_level($invite['access_level'] ?? 'base');
            $role = ql_group_role_for_access($accessLevel);
            $permissionsJson = (string)($invite['permissions_json'] ?? '');
            if ($permissionsJson === '') {
                $permissionsJson = ql_group_permissions_json($accessLevel);
            }

            $ins = $db->prepare("
                INSERT INTO group_members
                    (group_id, user_id, display_name, role, access_level, permissions_json, invited_by, invite_id, status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $ins->execute([
                (int)$invite['group_id'],
                (int)$user['id'],
                $user['display_name'] ?: $user['email'],
                $role,
                $accessLevel,
                $permissionsJson,
                (int)$invite['invited_by'],
                (int)$invite['id']
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

    $membership = ql_group_membership($groupId, (int)$user['id']);
    if (!$membership) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }

    $canSeeAll = in_array((string)($membership['access_level'] ?? 'base'), ['manager', 'advanced'], true);
    $whereExtra = $canSeeAll ? '' : ' AND gm.user_id = ?';

    $stmt = ql_db()->prepare("
        SELECT
            gm.user_id,
            gm.display_name,
            gm.role,
            gm.access_level,
            gm.permissions_json,
            gm.status,
            gm.joined_at,
            u.email
        FROM group_members gm
        JOIN users u ON u.id = gm.user_id
        WHERE gm.group_id = ?
          AND gm.status = 'active'
          {$whereExtra}
        ORDER BY gm.role ASC, gm.joined_at ASC
    ");
    $params = [$groupId];
    if (!$canSeeAll) {
        $params[] = (int)$user['id'];
    }
    $stmt->execute($params);

    $members = array_map('ql_group_member_public', $stmt->fetchAll());

    return [
        'ok' => true,
        'scope' => $membership,
        'members' => $members
    ];
}

function ql_group_member_access_update(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);
    $targetUserId = (int)($input['user_id'] ?? 0);
    $accessLevel = ql_group_normalize_access_level($input['access_level'] ?? '');

    if ($groupId <= 0 || $targetUserId <= 0) {
        return ['ok' => false, 'error' => 'invalid_member'];
    }

    if (!ql_group_is_admin($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'admin_required'];
    }

    $target = ql_group_membership($groupId, $targetUserId);
    if (!$target) {
        return ['ok' => false, 'error' => 'member_not_found'];
    }

    if ($targetUserId === (int)$user['id'] && $accessLevel !== 'advanced') {
        return ['ok' => false, 'error' => 'cannot_demote_self'];
    }

    $role = ql_group_role_for_access($accessLevel);
    $permissionsJson = ql_group_permissions_json($accessLevel);

    $stmt = ql_db()->prepare("
        UPDATE group_members
        SET role = ?,
            access_level = ?,
            permissions_json = ?,
            updated_at = NOW()
        WHERE group_id = ?
          AND user_id = ?
          AND status = 'active'
    ");
    $stmt->execute([$role, $accessLevel, $permissionsJson, $groupId, $targetUserId]);

    return [
        'ok' => true,
        'member' => ql_group_membership($groupId, $targetUserId)
    ];
}
