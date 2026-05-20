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
    $cashIn = round((float)($tapeStmt->fetchColumn() ?: 0), 2);

    $stmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN capture_type = 'cash_out' THEN amount ELSE 0 END), 0) AS cash_out,
            COALESCE(SUM(CASE WHEN capture_type = 'noncash_out' THEN amount ELSE 0 END), 0) AS card_out,
            COUNT(id) AS records_count,
            SUM(CASE WHEN review_status = 'needs_review' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN reportable = 1 THEN 1 ELSE 0 END) AS reportable_count
        FROM on_the_go_captures
        WHERE tape_id = ?
          AND review_status <> 'archived'
          AND capture_type IN ('cash_out', 'noncash_out')
    ");
    $stmt->execute([$tapeId]);
    $row = $stmt->fetch() ?: [];

    $cashOut = round((float)($row['cash_out'] ?? 0), 2);
    $cardOut = round((float)($row['card_out'] ?? 0), 2);

    return [
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
    $advance['summary'] = $tapeId > 0 ? ql_advance_tape_summary($tapeId) : [
        'cash_in' => round((float)($advance['amount'] ?? 0), 2),
        'cash_out' => 0.0,
        'card_out' => 0.0,
        'cash_left' => round((float)($advance['amount'] ?? 0), 2),
        'records_count' => 0,
        'pending_count' => 0,
        'reportable_count' => 0,
    ];

    return $advance;
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
                (group_id, issued_by_user_id, assigned_to_user_id, title, amount, currency, status)
            VALUES
                (?, ?, ?, ?, ?, ?, 'issued')
        ");
        $stmt->execute([$groupId, $userId, $assignedUserId, $title, $amount, $currency]);
        $advanceId = (int)$db->lastInsertId();

        $tapeStmt = $db->prepare("
            INSERT INTO on_the_go_tapes
                (user_id, group_id, advance_id, title, cash_received, currency, status)
            VALUES
                (?, ?, ?, ?, ?, ?, 'active')
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

        $db->commit();

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
        }
    } else {
        $where .= " AND ca.assigned_to_user_id = ?";
        $params[] = $userId;
    }

    if ($status !== '' && in_array($status, ['issued','submitted','accepted','returned','closed','discrepancy'], true)) {
        $where .= " AND ca.status = ?";
        $params[] = $status;
    }

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
                WHEN 'submitted' THEN 0
                WHEN 'discrepancy' THEN 1
                WHEN 'issued' THEN 2
                WHEN 'returned' THEN 3
                WHEN 'accepted' THEN 4
                ELSE 5
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
        'advances' => $advances
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

        $db->commit();

        return [
            'ok' => true,
            'advance' => ql_advance_public(ql_advance_row($advanceId)),
            'entries_created' => $entriesCreated,
            'files_copied' => $filesCopied
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

    return ['ok' => true, 'advance' => ql_advance_public(ql_advance_row($advanceId))];
}
