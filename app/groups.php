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

function ql_group_workspace_types(): array
{
    return ['team', 'yacht', 'home'];
}

function ql_group_normalize_workspace_type($value, string $name = ''): string
{
    $type = strtolower(trim((string)$value));
    if (in_array($type, ql_group_workspace_types(), true)) {
        return $type;
    }

    $lowerName = strtolower(trim($name));
    if (strpos($lowerName, 'yacht:') === 0 || strpos($lowerName, 'yacht') !== false) {
        return 'yacht';
    }
    if ($lowerName === 'дом' || strpos($lowerName, 'home:') === 0 || strpos($lowerName, 'home') !== false || strpos($lowerName, 'house') !== false) {
        return 'home';
    }

    return 'team';
}

function ql_group_ensure_workspace_type_schema(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    if (ql_group_table_has_column('groups', 'workspace_type')) {
        $ready = true;
        return true;
    }

    try {
        ql_db()->exec("
            ALTER TABLE groups
            ADD COLUMN workspace_type ENUM('team','yacht','home') NOT NULL DEFAULT 'team' AFTER description
        ");
        $ready = true;
        return true;
    } catch (Throwable $e) {
        $ready = false;
        return false;
    }
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
    $member['workspace_type'] = ql_group_normalize_workspace_type($member['workspace_type'] ?? '', (string)($member['name'] ?? ''));
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
    if (!$m) return false;
    $role = (string)($m['role'] ?? '');
    $access = (string)($m['access_level'] ?? '');
    return $role === 'admin' || $role === 'owner' || $access === 'advanced';
}

function ql_group_create(array $input): array
{
    $hasWorkspaceType = ql_group_ensure_workspace_type_schema();
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
        $description = trim((string)($input['description'] ?? '')) ?: null;
        if ($hasWorkspaceType) {
            $stmt = $db->prepare("
                INSERT INTO groups (name, description, workspace_type, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $description,
                ql_group_normalize_workspace_type($input['workspace_type'] ?? '', $name),
                (int)$user['id']
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO groups (name, description, created_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $description,
                (int)$user['id']
            ]);
        }

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
    $workspaceTypeSelect = ql_group_ensure_workspace_type_schema() ? 'g.workspace_type,' : "NULL AS workspace_type,";
    $stmt = ql_db()->prepare("
        SELECT
            g.id,
            g.name,
            g.description,
            {$workspaceTypeSelect}
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
    $workspaceTypeSelect = ql_group_ensure_workspace_type_schema() ? 'g.workspace_type,' : "NULL AS workspace_type,";
    $user = ql_require_user();
    ql_group_trash_purge_expired(['silent' => true]);

    $stmt = ql_db()->prepare("
        SELECT
            g.id,
            g.name,
            g.description,
            {$workspaceTypeSelect}
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

function ql_group_trash_list(array $input = []): array
{
    $workspaceTypeSelect = ql_group_ensure_workspace_type_schema() ? 'g.workspace_type,' : "NULL AS workspace_type,";
    $user = ql_require_user();
    ql_group_trash_purge_expired(['silent' => true]);

    $stmt = ql_db()->prepare("
        SELECT
            g.id,
            g.name,
            g.description,
            {$workspaceTypeSelect}
            g.created_by,
            g.status,
            g.created_at,
            g.archived_at,
            gm.role,
            gm.access_level,
            gm.permissions_json,
            gm.display_name AS member_display_name,
            GREATEST(0, 60 - DATEDIFF(NOW(), COALESCE(g.archived_at, g.updated_at, g.created_at))) AS trash_days_left,
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
          AND g.status = 'archived'
          AND COALESCE(g.archived_at, g.updated_at, g.created_at) >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        ORDER BY g.archived_at DESC, g.id DESC
    ");
    $stmt->execute([(int)$user['id']]);

    $groups = array_map('ql_group_member_public', $stmt->fetchAll());

    return ['ok' => true, 'groups' => $groups, 'retention_days' => 60];
}

function ql_group_trash(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    if (!ql_group_is_admin($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'admin_required'];
    }

    $now = date('Y-m-d H:i:s');
    $set = ["status = 'archived'"];
    $params = [];
    if (ql_group_table_has_column('groups', 'archived_at')) {
        $set[] = 'archived_at = ?';
        $params[] = $now;
    }
    if (ql_group_table_has_column('groups', 'updated_at')) {
        $set[] = 'updated_at = ?';
        $params[] = $now;
    }
    $params[] = $groupId;
    $stmt = ql_db()->prepare("UPDATE groups SET " . implode(', ', $set) . " WHERE id = ? AND status = 'active'");
    $stmt->execute($params);

    if (function_exists('ql_audit_write')) {
        ql_audit_write((int)$user['id'], 'group_moved_to_trash', 'group', $groupId, [
            'archived_at' => $now,
            'retention_days' => 60,
            'financial_evidence_preserved' => true,
        ]);
    }

    return ['ok' => true, 'group_id' => $groupId, 'status' => 'archived', 'retention_days' => 60];
}

function ql_group_restore(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $membership = ql_group_membership($groupId, (int)$user['id']);
    if (!$membership) {
        return ['ok' => false, 'error' => 'group_not_found'];
    }

    $role = (string)($membership['role'] ?? '');
    $access = (string)($membership['access_level'] ?? '');
    if ($role !== 'admin' && $role !== 'owner' && $access !== 'advanced') {
        return ['ok' => false, 'error' => 'admin_required'];
    }

    $now = date('Y-m-d H:i:s');
    $set = ["status = 'active'"];
    $params = [];
    if (ql_group_table_has_column('groups', 'updated_at')) {
        $set[] = 'updated_at = ?';
        $params[] = $now;
    }
    $params[] = $groupId;
    $stmt = ql_db()->prepare("
        UPDATE groups
        SET " . implode(', ', $set) . "
        WHERE id = ?
          AND status = 'archived'
          AND COALESCE(archived_at, updated_at, created_at) >= DATE_SUB(NOW(), INTERVAL 60 DAY)
    ");
    $stmt->execute($params);

    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'error' => 'restore_window_expired'];
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write((int)$user['id'], 'group_restored_from_trash', 'group', $groupId, [
            'restored_at' => $now,
            'financial_evidence_preserved' => true,
        ]);
    }

    return ['ok' => true, 'group' => ql_group_get($groupId, (int)$user['id'])];
}

function ql_group_trash_purge_expired(array $input = []): array
{
    $silent = !empty($input['silent']);
    try {
        $db = ql_db();
        $now = date('Y-m-d H:i:s');
        $expiredStmt = $db->query("
            SELECT id
            FROM groups
            WHERE status = 'archived'
              AND COALESCE(archived_at, updated_at, created_at) < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");
        $ids = array_map('intval', array_column($expiredStmt->fetchAll(), 'id'));
        if (!$ids) {
            return ['ok' => true, 'purged' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $memberSet = ["status = 'left'"];
        $memberParams = [];
        if (ql_group_table_has_column('group_members', 'left_at')) {
            $memberSet[] = 'left_at = ?';
            $memberParams[] = $now;
        }
        if (ql_group_table_has_column('group_members', 'updated_at')) {
            $memberSet[] = 'updated_at = ?';
            $memberParams[] = $now;
        }
        $memberParams = array_merge($memberParams, $ids);
        $members = $db->prepare("UPDATE group_members SET " . implode(', ', $memberSet) . " WHERE group_id IN ($placeholders) AND status = 'active'");
        $members->execute($memberParams);

        $inviteSet = ["status = 'revoked'"];
        $inviteParams = [];
        if (ql_group_table_has_column('group_invites', 'revoked_at')) {
            $inviteSet[] = 'revoked_at = ?';
            $inviteParams[] = $now;
        }
        $inviteParams = array_merge($inviteParams, $ids);
        $invites = $db->prepare("UPDATE group_invites SET " . implode(', ', $inviteSet) . " WHERE group_id IN ($placeholders) AND status = 'active'");
        $invites->execute($inviteParams);

        return [
            'ok' => true,
            'purged' => count($ids),
            'retention_days' => 60,
        ];
    } catch (Throwable $e) {
        if ($silent) {
            return ['ok' => false, 'error' => 'purge_failed'];
        }
        return ['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()];
    }
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
            'whatsapp' => 'https://wa.me/?text=' . rawurlencode('Join my FinDesk group: ' . $url),
            'telegram' => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode('Join my FinDesk group'),
            'viber' => 'viber://forward?text=' . rawurlencode('Join my FinDesk group: ' . $url),
            'email' => 'mailto:?subject=' . rawurlencode('FinDesk group invite') . '&body=' . rawurlencode('Join my FinDesk group: ' . $url),
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

    if (function_exists('ql_audit_write')) {
        ql_audit_write((int)$user['id'], 'member_access_updated', 'group_member', $targetUserId, [
            'group_id' => $groupId,
            'target_user_id' => $targetUserId,
            'access_level' => $accessLevel,
            'role' => $role
        ]);
    }

    return [
        'ok' => true,
        'member' => ql_group_membership($groupId, $targetUserId)
    ];
}

function ql_group_count_value(string $sql, array $params = []): ?int
{
    try {
        $stmt = ql_db()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

function ql_group_financial_evidence_summary(int $groupId): array
{
    return [
        'ledger_entries' => ql_group_count_value(
            "SELECT COUNT(*) FROM ledger_entries WHERE group_id = ? AND deleted_at IS NULL",
            [$groupId]
        ),
        'live_report_tapes' => ql_group_count_value(
            "SELECT COUNT(*) FROM on_the_go_tapes WHERE group_id = ?",
            [$groupId]
        ),
        'live_report_captures' => ql_group_count_value(
            "
                SELECT COUNT(*)
                FROM on_the_go_captures c
                JOIN on_the_go_tapes t ON t.id = c.tape_id
                WHERE t.group_id = ?
            ",
            [$groupId]
        ),
        'proof_files' => ql_group_count_value(
            "
                SELECT COUNT(*)
                FROM on_the_go_files f
                JOIN on_the_go_captures c ON c.id = f.capture_id
                JOIN on_the_go_tapes t ON t.id = c.tape_id
                WHERE t.group_id = ?
            ",
            [$groupId]
        ),
        'advances' => ql_group_count_value(
            "SELECT COUNT(*) FROM cash_advances WHERE group_id = ? AND deleted_at IS NULL",
            [$groupId]
        ),
        'messages' => ql_group_count_value(
            "SELECT COUNT(*) FROM group_messages WHERE group_id = ? AND deleted_at IS NULL",
            [$groupId]
        ),
        'closed_final_reports' => ql_group_count_value(
            "
                SELECT COUNT(*)
                FROM audit_log
                WHERE action = 'ledger_group_report_finalized'
                  AND entity_type = 'group'
                  AND entity_id = ?
            ",
            [$groupId]
        ),
    ];
}

function ql_group_evidence_preserved(array $before, array $after): bool
{
    foreach ($before as $key => $value) {
        if ($value === null || !array_key_exists($key, $after) || $after[$key] === null) {
            continue;
        }
        if ((int)$after[$key] !== (int)$value) {
            return false;
        }
    }

    return true;
}

function ql_group_table_has_column(string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    try {
        $stmt = ql_db()->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function ql_group_delete(array $input): array
{
    $user = ql_require_user();
    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $db = ql_db();
    $db->beginTransaction();

    try {
        $groupStmt = $db->prepare("SELECT id, name, created_by, status FROM groups WHERE id = ? LIMIT 1 FOR UPDATE");
        $groupStmt->execute([$groupId]);
        $group = $groupStmt->fetch();

        if (!$group) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'group_not_found'];
        }

        $isCreator = (int)($group['created_by'] ?? 0) === (int)$user['id'];
        $isAdmin = ql_group_is_admin($groupId, (int)$user['id']);
        if (!$isCreator && !$isAdmin) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'admin_required'];
        }

        $evidenceBefore = ql_group_financial_evidence_summary($groupId);

        if (($group['status'] ?? '') === 'archived') {
            $db->rollBack();
            return [
                'ok' => true,
                'group' => ['id' => $groupId, 'name' => (string)$group['name'], 'status' => 'archived'],
                'archive_mode' => 'soft',
                'financial_evidence' => [
                    'before' => $evidenceBefore,
                    'after' => $evidenceBefore,
                    'preserved' => true,
                ],
                'already_deleted' => true
            ];
        }

        $now = date('Y-m-d H:i:s');

        $memberSet = ["status = 'left'"];
        $memberParams = [];
        if (ql_group_table_has_column('group_members', 'left_at')) {
            $memberSet[] = 'left_at = ?';
            $memberParams[] = $now;
        }
        if (ql_group_table_has_column('group_members', 'updated_at')) {
            $memberSet[] = 'updated_at = ?';
            $memberParams[] = $now;
        }
        $memberParams[] = $groupId;
        $members = $db->prepare("UPDATE group_members SET " . implode(', ', $memberSet) . " WHERE group_id = ? AND status = 'active'");
        $members->execute($memberParams);
        $membersArchived = $members->rowCount();

        $inviteSet = ["status = 'revoked'"];
        $inviteParams = [];
        if (ql_group_table_has_column('group_invites', 'revoked_at')) {
            $inviteSet[] = 'revoked_at = ?';
            $inviteParams[] = $now;
        }
        if (ql_group_table_has_column('group_invites', 'updated_at')) {
            $inviteSet[] = 'updated_at = ?';
            $inviteParams[] = $now;
        }
        $inviteParams[] = $groupId;
        $invites = $db->prepare("UPDATE group_invites SET " . implode(', ', $inviteSet) . " WHERE group_id = ? AND status = 'active'");
        $invites->execute($inviteParams);
        $invitesRevoked = $invites->rowCount();

        $groupSet = ["status = 'archived'"];
        $groupParams = [];
        if (ql_group_table_has_column('groups', 'archived_at')) {
            $groupSet[] = 'archived_at = ?';
            $groupParams[] = $now;
        }
        if (ql_group_table_has_column('groups', 'updated_at')) {
            $groupSet[] = 'updated_at = ?';
            $groupParams[] = $now;
        }
        $groupParams[] = $groupId;
        $groupArchive = $db->prepare("UPDATE groups SET " . implode(', ', $groupSet) . " WHERE id = ? AND status = 'active'");
        $groupArchive->execute($groupParams);
        $evidenceAfter = ql_group_financial_evidence_summary($groupId);
        $evidencePreserved = ql_group_evidence_preserved($evidenceBefore, $evidenceAfter);

        if (function_exists('ql_audit_write')) {
            ql_audit_write((int)$user['id'], 'group_deleted', 'group', $groupId, [
                'group_name' => (string)$group['name'],
                'archive_mode' => 'soft',
                'created_by' => (int)($group['created_by'] ?? 0),
                'deleted_at' => $now,
                'archived_at' => $now,
                'members_archived' => $membersArchived,
                'invites_revoked' => $invitesRevoked,
                'financial_evidence_before' => $evidenceBefore,
                'financial_evidence_after' => $evidenceAfter,
                'financial_evidence_preserved' => $evidencePreserved
            ]);
        }

        $db->commit();

        return [
            'ok' => true,
            'group' => ['id' => $groupId, 'name' => (string)$group['name'], 'status' => 'archived'],
            'archive_mode' => 'soft',
            'members_archived' => $membersArchived,
            'invites_revoked' => $invitesRevoked,
            'financial_evidence' => [
                'before' => $evidenceBefore,
                'after' => $evidenceAfter,
                'preserved' => $evidencePreserved,
            ],
        ];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()];
    }
}
