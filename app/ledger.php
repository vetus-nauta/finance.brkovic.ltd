<?php

require_once __DIR__ . '/auth.php';

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

    $entryId = (int)($input['id'] ?? 0);

    if ($entryId <= 0) {
        return ['ok' => false, 'error' => 'invalid_entry_id'];
    }

    $existing = ql_ledger_get_one($entryId, (int)$user['id']);

    if (!$existing) {
        return ['ok' => false, 'error' => 'entry_not_found'];
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

    $existingGroupId = (int)($existing['group_id'] ?? 0);
    if (!ql_category_allowed($categoryId, $entryType, (int)$user['id'], $existingGroupId)) {
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
          AND user_id = ?
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        $entryType,
        $moneyType,
        $categoryId,
        $amount,
        $purpose,
        $note ?: null,
        $entryId,
        (int)$user['id']
    ]);

    return [
        'ok' => true,
        'entry' => ql_ledger_get_one($entryId, (int)$user['id'])
    ];
}


function ql_ledger_delete(array $input): array
{
    $user = ql_require_user();

    $entryId = (int)($input['id'] ?? 0);

    if ($entryId <= 0) {
        return ['ok' => false, 'error' => 'invalid_entry_id'];
    }

    $existing = ql_ledger_get_one($entryId, (int)$user['id']);

    if (!$existing) {
        return ['ok' => false, 'error' => 'entry_not_found'];
    }

    $stmt = ql_db()->prepare("
        UPDATE ledger_entries
        SET deleted_at = NOW(), edited_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND deleted_at IS NULL
    ");

    $stmt->execute([$entryId, (int)$user['id']]);

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
        } else {
            $where = "group_id = ? AND user_id = ? AND deleted_at IS NULL AND entry_datetime BETWEEN ? AND ?";
            $params = [$groupId, (int)$user['id'], $fromSql, $toSql];
        }
    } else {
        $where = "user_id = ? AND group_id IS NULL AND deleted_at IS NULL AND entry_datetime BETWEEN ? AND ?";
        $params = [(int)$user['id'], $fromSql, $toSql];
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

    $result['balance'] = $result['income'] - $result['expense'];
    $result['cash_balance'] = $result['cash_income'] - $result['cash_expense'];
    $result['noncash_balance'] = $result['noncash_income'] - $result['noncash_expense'];

    foreach ($result as $key => $value) {
        if ($key !== 'records') {
            $result[$key] = round((float)$value, 2);
        }
    }

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
        $name = $row['section_name'] ?: 'No section';

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
