<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function ql_otr_user(): array
{
    if (function_exists('ql_require_user')) {
        return ql_require_user();
    }

    if (function_exists('ql_current_user')) {
        $user = ql_current_user();
        if ($user) return $user;
    }

    return ['ok' => false, 'error' => 'not_authenticated'];
}

function ql_otr_is_user_error(array $user): bool
{
    return isset($user['ok']) && $user['ok'] === false;
}

function ql_otr_amount_or_null($value): ?float
{
    $raw = trim(str_replace(',', '.', (string)$value));
    if ($raw === '') return null;
    if (!is_numeric($raw)) return null;
    $amount = round((float)$raw, 2);
    return $amount >= 0 ? $amount : null;
}


function ql_on_the_go_tape_get(int $tapeId, int $userId): ?array
{
    if ($tapeId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT
            id,
            user_id,
            title,
            cash_received,
            currency,
            status,
            created_at,
            updated_at,
            closed_at
        FROM on_the_go_tapes
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tapeId, $userId]);

    $row = $stmt->fetch();
    return $row ?: null;
}

function ql_on_the_go_active_tape_id(int $userId): int
{
    $stmt = ql_db()->prepare("
        SELECT id
        FROM on_the_go_tapes
        WHERE user_id = ?
          AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);

    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $stmt = ql_db()->prepare("
        INSERT INTO on_the_go_tapes
            (user_id, title, cash_received, currency, status)
        VALUES
            (?, 'On the Go', 0.00, 'EUR', 'active')
    ");
    $stmt->execute([$userId]);

    return (int)ql_db()->lastInsertId();
}

function ql_on_the_go_tape_summary(int $tapeId): array
{
    /*
     * OTR-4D-3:
     * Summary is operational/current, not archive.
     * Given comes from tape cash_received.
     * Cash/Card spent are counted only from ACTIVE sessions.
     * Closed sessions stay in Session Cards and do not stretch the working journal.
     */

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
            COALESCE(SUM(CASE WHEN c.capture_type = 'cash_out' THEN c.amount ELSE 0 END), 0) AS cash_out,
            COALESCE(SUM(CASE WHEN c.capture_type = 'noncash_out' THEN c.amount ELSE 0 END), 0) AS card_out,
            COUNT(c.id) AS records_count
        FROM on_the_go_captures c
        JOIN on_the_go_sessions s ON s.id = c.session_id
        WHERE c.tape_id = ?
          AND c.review_status = 'needs_review'
          AND c.capture_type IN ('cash_out', 'noncash_out')
          AND s.status = 'active'
    ");
    $stmt->execute([$tapeId]);

    $s = $stmt->fetch() ?: [];

    $cashOut = round((float)($s['cash_out'] ?? 0), 2);
    $cardOut = round((float)($s['card_out'] ?? 0), 2);

    return [
        'cash_in' => $cashIn,
        'cash_out' => $cashOut,
        'card_out' => $cardOut,
        'cash_left' => round($cashIn - $cashOut, 2),
        'records_count' => (int)($s['records_count'] ?? 0),
    ];
}


function ql_on_the_go_tape_list(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) {
        return $user;
    }

    $stmt = ql_db()->prepare("
        SELECT
            id,
            user_id,
            title,
            cash_received,
            currency,
            status,
            created_at,
            updated_at,
            closed_at
        FROM on_the_go_tapes
        WHERE user_id = ?
          AND status <> 'archived'
        ORDER BY id DESC
        LIMIT 100
    ");
    $stmt->execute([(int)$user['id']]);
    $tapes = $stmt->fetchAll();

    if (!$tapes) {
        $id = ql_on_the_go_active_tape_id((int)$user['id']);
        $tape = ql_on_the_go_tape_get($id, (int)$user['id']);
        $tapes = $tape ? [$tape] : [];
    }

    foreach ($tapes as &$tape) {
        $tape['summary'] = ql_on_the_go_tape_summary((int)$tape['id']);
    }

    return [
        'ok' => true,
        'active_tape_id' => (int)($tapes[0]['id'] ?? 0),
        'tapes' => $tapes
    ];
}

function ql_on_the_go_tape_create(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) {
        return $user;
    }

    $amount = ql_otr_amount_or_null($input['cash_received'] ?? $input['amount'] ?? 0);
    if ($amount === null) {
        $amount = 0.0;
    }

    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        $title = 'On the Go';
    }
    if (mb_strlen($title) > 190) {
        $title = mb_substr($title, 0, 190);
    }

    $currency = strtoupper(trim((string)($input['currency'] ?? 'EUR')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = 'EUR';
    }

    $db = ql_db();

    $stmt = $db->prepare("
        INSERT INTO on_the_go_tapes
            (user_id, title, cash_received, currency, status)
        VALUES
            (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([(int)$user['id'], $title, $amount, $currency]);

    $tapeId = (int)$db->lastInsertId();

    $tape = ql_on_the_go_tape_get($tapeId, (int)$user['id']);
    if ($tape) {
        $tape['summary'] = ql_on_the_go_tape_summary($tapeId);
    }

    return [
        'ok' => true,
        'tape' => $tape
    ];
}


function ql_on_the_go_create(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $type = (string)($input['capture_type'] ?? '');
    if (!in_array($type, ['cash_in', 'cash_out', 'noncash_out'], true)) {
        return ['ok' => false, 'error' => 'invalid_capture_type'];
    }

    $tapeId = (int)($input['tape_id'] ?? 0);
    if ($tapeId > 0) {
        $tape = ql_on_the_go_tape_get($tapeId, (int)$user['id']);
        if (!$tape) {
            return ['ok' => false, 'error' => 'invalid_tape_id'];
        }
    } else {
        $tapeId = ql_on_the_go_active_tape_id((int)$user['id']);
    }

    $amount = ql_otr_amount_or_null($input['amount'] ?? '');
    $description = trim((string)($input['description'] ?? ''));
    if (mb_strlen($description) > 255) {
        $description = mb_substr($description, 0, 255);
    }

    if ($amount === null && $description === '') {
        return ['ok' => false, 'error' => 'empty_capture'];
    }

    $currency = strtoupper(trim((string)($input['currency'] ?? 'EUR')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) $currency = 'EUR';

    $sessionType = $type === 'noncash_out' ? 'card' : 'cash';
    $sessionId = ql_on_the_go_active_session_id((int)$user['id'], $tapeId, $sessionType);

    $db = ql_db();

    /*
     * OTR-4C-2 guard:
     * Prevent fast double tap / duplicate handler from creating identical captures.
     * This is not accounting logic, only input safety.
     */
    $dedupeDescription = $description !== '' ? $description : null;
    $stmt = $db->prepare("
        SELECT id
        FROM on_the_go_captures
        WHERE user_id = ?
          AND tape_id = ?
          AND session_id = ?
          AND capture_type = ?
          AND amount <=> ?
          AND description <=> ?
          AND review_status = 'needs_review'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 4 SECOND)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([
        (int)$user['id'],
        $tapeId,
        $sessionId,
        $type,
        $amount,
        $dedupeDescription
    ]);

    $existingId = (int)($stmt->fetchColumn() ?: 0);
    if ($existingId > 0) {
        return [
            'ok' => true,
            'deduped' => true,
            'item' => ql_on_the_go_get_one($existingId, (int)$user['id'])
        ];
    }

    $stmt = $db->prepare("
        INSERT INTO on_the_go_captures
            (user_id, tape_id, session_id, capture_type, amount, currency, description, review_status, reportable, recognition_status)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, 'needs_review', 0, 'none')
    ");
    $stmt->execute([
        (int)$user['id'],
        $tapeId,
        $sessionId,
        $type,
        $amount,
        $currency,
        $description !== '' ? $description : null
    ]);

    return [
        'ok' => true,
        'capture' => ql_on_the_go_get_one((int)$db->lastInsertId(), (int)$user['id'])
    ];
}

function ql_on_the_go_get_one(int $id, int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM on_the_go_files f WHERE f.capture_id = c.id) AS files_count
        FROM on_the_go_captures c
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ql_on_the_go_list(array $input = []): array
{
    /*
     * OTR-4E-1:
     * Main journal is not a global tape dump anymore.
     * It returns ONLY the active session for the selected working zone:
     *   session_type = cash -> cash_out records from active cash session
     *   session_type = card -> noncash_out records from active card session
     * Closed sessions are shown only as packed session cards.
     */

    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $limit = (int)($input['limit'] ?? 100);
    if ($limit < 1 || $limit > 300) {
        $limit = 100;
    }

    $tapeId = (int)($input['tape_id'] ?? 0);
    if ($tapeId > 0) {
        $tape = ql_on_the_go_tape_get($tapeId, $userId);
        if (!$tape) {
            return ['ok' => false, 'error' => 'invalid_tape_id'];
        }
    } else {
        $tapeId = ql_on_the_go_active_tape_id($userId);
    }

    $sessionType = (string)($input['session_type'] ?? 'cash');
    if (!in_array($sessionType, ['cash', 'card'], true)) {
        $sessionType = 'cash';
    }

    $captureType = $sessionType === 'card' ? 'noncash_out' : 'cash_out';
    $activeSessionId = ql_on_the_go_active_session_id($userId, $tapeId, $sessionType);

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
            c.recognition_status,
            c.recognized_amount,
            c.recognized_currency,
            c.recognized_date,
            c.recognized_vendor,
            c.recognition_confidence,
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
        JOIN on_the_go_sessions s ON s.id = c.session_id
        WHERE c.user_id = ?
          AND c.tape_id = ?
          AND c.session_id = ?
          AND c.review_status = 'needs_review'
          AND c.capture_type = ?
          AND s.status = 'active'
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$userId, $tapeId, $activeSessionId, $captureType]);

    $items = $stmt->fetchAll();
    $summary = ql_on_the_go_tape_summary($tapeId);
    $tapeList = ql_on_the_go_tape_list([]);

    return [
        'ok' => true,
        'active_tape_id' => $tapeId,
        'active_session_id' => $activeSessionId,
        'session_type' => $sessionType,
        'items' => $items,
        'summary' => $summary,
        'tapes' => $tapeList['tapes'] ?? []
    ];
}

function ql_on_the_go_update(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

    $existing = ql_on_the_go_get_one($id, (int)$user['id']);
    if (!$existing) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    if (($existing['review_status'] ?? '') !== 'needs_review') {
        return ['ok' => false, 'error' => 'capture_not_editable'];
    }

    $type = (string)($input['capture_type'] ?? $existing['capture_type']);
    if (!in_array($type, ['cash_in', 'cash_out', 'noncash_out'], true)) {
        return ['ok' => false, 'error' => 'invalid_capture_type'];
    }

    $amount = ql_otr_amount_or_null($input['amount'] ?? '');
    $description = trim((string)($input['description'] ?? ''));

    if (mb_strlen($description) > 255) {
        $description = mb_substr($description, 0, 255);
    }

    if ($amount === null && $description === '') {
        return ['ok' => false, 'error' => 'empty_capture'];
    }

    $stmt = ql_db()->prepare("
        UPDATE on_the_go_captures
        SET capture_type = ?,
            amount = ?,
            description = ?,
            updated_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND review_status = 'needs_review'
        LIMIT 1
    ");

    $stmt->execute([
        $type,
        $amount,
        $description !== '' ? $description : null,
        $id,
        (int)$user['id']
    ]);

    return [
        'ok' => true,
        'capture' => ql_on_the_go_get_one($id, (int)$user['id'])
    ];
}

function ql_on_the_go_archive(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

    $existing = ql_on_the_go_get_one($id, (int)$user['id']);
    if (!$existing) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    $stmt = ql_db()->prepare("
        UPDATE on_the_go_captures
        SET review_status = 'archived',
            updated_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND review_status = 'needs_review'
        LIMIT 1
    ");
    $stmt->execute([$id, (int)$user['id']]);

    return ['ok' => true];
}



function ql_otr_entry_type_from_capture(string $captureType): string
{
    return $captureType === 'cash_in' ? 'income' : 'expense';
}

function ql_otr_money_type_from_capture(string $captureType): string
{
    return $captureType === 'noncash_out' ? 'noncash' : 'cash';
}

function ql_otr_file_kind(string $mime): string
{
    if (strpos($mime, 'image/') === 0) return 'image';
    if (in_array($mime, [
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ], true)) return 'document';
    return 'other';
}

function ql_otr_ensure_on_the_go_category(int $userId, string $entryType, int $groupId = 0): ?int
{
    $db = ql_db();

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, $userId);
        if (!$scope) {
            return null;
        }

        $stmt = $db->prepare("
            SELECT id
            FROM ledger_categories
            WHERE group_id = ?
              AND user_id IS NULL
              AND category_type = ?
              AND name = 'On the Go'
              AND deleted_at IS NULL
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$groupId, $entryType]);
        $found = $stmt->fetchColumn();

        if ($found) return (int)$found;

        $ins = $db->prepare("
            INSERT INTO ledger_categories
                (user_id, group_id, category_type, name, color, sort_order, is_default)
            VALUES
                (NULL, ?, ?, 'On the Go', ?, 500, 0)
        ");
        $ins->execute([
            $groupId,
            $entryType,
            $entryType === 'income' ? '#DFF5E7' : '#FFF1C7'
        ]);

        return (int)$db->lastInsertId();
    }

    $stmt = $db->prepare("
        SELECT id
        FROM ledger_categories
        WHERE user_id = ?
          AND group_id IS NULL
          AND category_type = ?
          AND name = 'On the Go'
          AND deleted_at IS NULL
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId, $entryType]);
    $found = $stmt->fetchColumn();

    if ($found) return (int)$found;

    $ins = $db->prepare("
        INSERT INTO ledger_categories
            (user_id, group_id, category_type, name, color, sort_order, is_default)
        VALUES
            (?, NULL, ?, 'On the Go', ?, 500, 0)
    ");
    $ins->execute([
        $userId,
        $entryType,
        $entryType === 'income' ? '#DFF5E7' : '#FFF1C7'
    ]);

    return (int)$db->lastInsertId();
}

function ql_on_the_go_convert_to_ledger(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $captureId = (int)($input['id'] ?? 0);

    if ($captureId <= 0) {
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

    $capture = ql_on_the_go_get_one($captureId, $userId);

    if (!$capture) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    if (($capture['review_status'] ?? '') !== 'needs_review') {
        return ['ok' => false, 'error' => 'capture_not_convertible'];
    }

    $entryType = (string)($input['entry_type'] ?? ql_otr_entry_type_from_capture((string)$capture['capture_type']));
    $moneyType = (string)($input['money_type'] ?? ql_otr_money_type_from_capture((string)$capture['capture_type']));

    if (!in_array($entryType, ['income', 'expense'], true)) {
        return ['ok' => false, 'error' => 'invalid_entry_type'];
    }

    if (!in_array($moneyType, ['cash', 'noncash'], true)) {
        return ['ok' => false, 'error' => 'invalid_money_type'];
    }

    $amount = ql_otr_amount_or_null($input['amount'] ?? $capture['amount']);

    if ($amount === null) {
        return ['ok' => false, 'error' => 'invalid_amount'];
    }

    $purpose = trim((string)($input['purpose'] ?? ''));
    if ($purpose === '') {
        $purpose = trim((string)($capture['description'] ?? ''));
    }
    if ($purpose === '') {
        $purpose = 'On the Go record';
    }
    if (mb_strlen($purpose) > 255) {
        $purpose = mb_substr($purpose, 0, 255);
    }

    $note = trim((string)($input['note'] ?? ''));
    if ($note === '') {
        $note = trim((string)($capture['description'] ?? ''));
    }
    if ($note !== '') {
        $note = "From On the Go: " . $note;
    } else {
        $note = "From On the Go capture #" . $captureId;
    }

    $groupId = (int)($input['group_id'] ?? 0);
    $groupIdForDb = null;

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, $userId);
        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }
        $groupIdForDb = $groupId;
    }

    $categoryId = isset($input['category_id']) && $input['category_id'] !== '' ? (int)$input['category_id'] : 0;

    if ($categoryId <= 0) {
        $categoryId = ql_otr_ensure_on_the_go_category($userId, $entryType, $groupId);
    }

    if ($categoryId && !ql_category_allowed($categoryId, $entryType, $userId, $groupId)) {
        return ['ok' => false, 'error' => 'invalid_category'];
    }

    $db = ql_db();

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO ledger_entries
                (user_id, group_id, entry_type, money_type, category_id, amount, currency, purpose, note, entry_datetime, original_position_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 'EUR', ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $userId,
            $groupIdForDb,
            $entryType,
            $moneyType,
            $categoryId ?: null,
            $amount,
            $purpose,
            $note
        ]);

        $entryId = (int)$db->lastInsertId();

        $filesStmt = $db->prepare("
            SELECT *
            FROM on_the_go_files
            WHERE capture_id = ?
              AND user_id = ?
            ORDER BY id ASC
        ");
        $filesStmt->execute([$captureId, $userId]);
        $files = $filesStmt->fetchAll();

        $baseRoot = realpath(dirname(__DIR__));
        $ledgerDir = dirname(__DIR__) . '/storage/documents/' . date('Y') . '/' . date('m');

        if (!is_dir($ledgerDir) && !mkdir($ledgerDir, 0755, true)) {
            throw new RuntimeException('ledger_storage_not_writable');
        }

        $fileInsert = $db->prepare("
            INSERT INTO entry_files
                (entry_id, user_id, file_original_name, file_stored_name, file_path, file_mime, file_size, file_kind)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $copiedFiles = 0;

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

            $stored = 'entry_' . $entryId . '_otr_' . $captureId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target = $ledgerDir . '/' . $stored;

            if (!copy($source, $target)) {
                throw new RuntimeException('file_copy_failed');
            }

            chmod($target, 0640);

            $relativePath = 'documents/' . date('Y') . '/' . date('m') . '/' . $stored;
            $size = filesize($target);

            $fileInsert->execute([
                $entryId,
                $userId,
                $original,
                $stored,
                $relativePath,
                $mime,
                $size !== false ? (int)$size : (int)($file['size_bytes'] ?? 0),
                ql_otr_file_kind($mime)
            ]);

            $copiedFiles++;
        }

        $upd = $db->prepare("
            UPDATE on_the_go_captures
            SET review_status = 'reviewed',
                reportable = 1,
                updated_at = NOW()
            WHERE id = ?
              AND user_id = ?
              AND review_status = 'needs_review'
            LIMIT 1
        ");
        $upd->execute([$captureId, $userId]);

        $db->commit();

        return [
            'ok' => true,
            'entry' => ql_ledger_get_one($entryId, $userId),
            'entry_id' => $entryId,
            'copied_files' => $copiedFiles
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['ok' => false, 'error' => 'convert_failed', 'message' => $e->getMessage()];
    }
}

function ql_on_the_go_file_list(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $captureId = (int)($input['capture_id'] ?? 0);
    if ($captureId <= 0) {
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

    $capture = ql_on_the_go_get_one($captureId, (int)$user['id']);
    if (!$capture) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    $stmt = ql_db()->prepare("
        SELECT id, capture_id, original_name, storage_path, mime_type, size_bytes, created_at
        FROM on_the_go_files
        WHERE capture_id = ?
          AND user_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$captureId, (int)$user['id']]);
    $files = $stmt->fetchAll();

    foreach ($files as &$file) {
        $file['download_url'] = '/api.php?action=on_the_go_file_download&id=' . (int)$file['id'];
    }

    return ['ok' => true, 'files' => $files];
}

function ql_on_the_go_file_delete(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $fileId = (int)($input['id'] ?? 0);
    if ($fileId <= 0) {
        return ['ok' => false, 'error' => 'invalid_file_id'];
    }

    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_files
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$fileId, (int)$user['id']]);
    $file = $stmt->fetch();

    if (!$file) {
        return ['ok' => false, 'error' => 'file_not_found'];
    }

    $base = realpath(dirname(__DIR__));
    $target = realpath(dirname(__DIR__) . '/' . $file['storage_path']);

    if ($base && $target && strpos($target, $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'on-the-go' . DIRECTORY_SEPARATOR) === 0) {
        if (is_file($target)) {
            @unlink($target);
        }
    }

    $del = ql_db()->prepare("
        DELETE FROM on_the_go_files
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $del->execute([$fileId, (int)$user['id']]);

    return ['ok' => true];
}

function ql_on_the_go_file_download(): void
{
    $user = ql_otr_user();

    if (ql_otr_is_user_error($user)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $fileId = (int)($_GET['id'] ?? 0);
    if ($fileId <= 0) {
        http_response_code(400);
        echo 'Invalid file id';
        exit;
    }

    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_files
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$fileId, (int)$user['id']]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }

    $base = realpath(dirname(__DIR__));
    $target = realpath(dirname(__DIR__) . '/' . $file['storage_path']);

    if (!$base || !$target || strpos($target, $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . 'on-the-go' . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
        http_response_code(404);
        echo 'File missing';
        exit;
    }

    $mime = trim((string)($file['mime_type'] ?? ''));
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    $name = basename((string)$file['original_name']);

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($target));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($target);
    exit;
}

function ql_on_the_go_upload_file(): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $captureId = (int)($_POST['capture_id'] ?? 0);
    if ($captureId <= 0) {
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

    $capture = ql_on_the_go_get_one($captureId, (int)$user['id']);
    if (!$capture) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        return ['ok' => false, 'error' => 'missing_file'];
    }

    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'upload_error_' . (int)$file['error']];
    }

    $maxSize = 8 * 1024 * 1024;
    if ((int)$file['size'] > $maxSize) {
        return ['ok' => false, 'error' => 'file_too_large'];
    }

    $original = basename((string)$file['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','pdf','txt','doc','docx'];
    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'error' => 'file_type_not_allowed'];
    }

    $year = date('Y');
    $dir = dirname(__DIR__) . "/storage/documents/on-the-go/{$year}";
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $safeName = 'otr_' . (int)$user['id'] . '_' . $captureId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'move_failed'];
    }

    $relative = "storage/documents/on-the-go/{$year}/{$safeName}";
    $mime = (string)($file['type'] ?? '');

    $stmt = ql_db()->prepare("
        INSERT INTO on_the_go_files
            (capture_id, user_id, original_name, storage_path, mime_type, size_bytes)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $captureId,
        (int)$user['id'],
        $original,
        $relative,
        $mime,
        (int)$file['size']
    ]);

    return [
        'ok' => true,
        'file' => [
            'original_name' => $original,
            'storage_path' => $relative,
            'mime_type' => $mime,
            'size_bytes' => (int)$file['size']
        ],
        'capture' => ql_on_the_go_get_one($captureId, (int)$user['id'])
    ];
}

/* === FinDesk On the Go Sessions Foundation OTR-4A 20260503-46 === */

function ql_on_the_go_active_session_id(int $userId, int $tapeId, string $sessionType): int
{
    if (!in_array($sessionType, ['cash', 'card'], true)) {
        $sessionType = 'cash';
    }

    $stmt = ql_db()->prepare("
        SELECT id
        FROM on_the_go_sessions
        WHERE user_id = ?
          AND tape_id = ?
          AND session_type = ?
          AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $tapeId, $sessionType]);

    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $title = $sessionType === 'card' ? 'Card session' : 'Cash session';

    $stmt = ql_db()->prepare("
        INSERT INTO on_the_go_sessions
            (user_id, tape_id, session_type, title, status)
        VALUES
            (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$userId, $tapeId, $sessionType, $title]);

    return (int)ql_db()->lastInsertId();
}

function ql_on_the_go_session_list(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? 0);

    if ($tapeId <= 0) {
        $tapeId = ql_on_the_go_active_tape_id($userId);
    }

    $tape = ql_on_the_go_tape_get($tapeId, $userId);
    if (!$tape) {
        return ['ok' => false, 'error' => 'invalid_tape_id'];
    }

    $stmt = ql_db()->prepare("
        SELECT
            s.id,
            s.user_id,
            s.tape_id,
            s.session_type,
            s.title,
            s.status,
            s.started_at,
            s.closed_at,
            s.archived_at,
            s.created_at,
            s.updated_at,
            COALESCE(SUM(CASE WHEN c.review_status = 'needs_review' THEN c.amount ELSE 0 END), 0) AS amount_total,
            COUNT(c.id) AS records_total,
            SUM(CASE WHEN c.review_status = 'needs_review' THEN 1 ELSE 0 END) AS pending_total
        FROM on_the_go_sessions s
        LEFT JOIN on_the_go_captures c ON c.session_id = s.id
        WHERE s.user_id = ?
          AND s.tape_id = ?
          AND s.status <> 'archived'
        GROUP BY s.id
        ORDER BY
            CASE s.status WHEN 'active' THEN 0 WHEN 'closed' THEN 1 ELSE 2 END,
            s.started_at DESC,
            s.id DESC
    ");
    $stmt->execute([$userId, $tapeId]);

    return [
        'ok' => true,
        'tape_id' => $tapeId,
        'sessions' => $stmt->fetchAll()
    ];
}

/* === FinDesk On the Go Close Session OTR-4C 20260503-48 === */

function ql_on_the_go_close_session(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? 0);
    $sessionType = (string)($input['session_type'] ?? '');

    if (!in_array($sessionType, ['cash', 'card'], true)) {
        return ['ok' => false, 'error' => 'invalid_session_type'];
    }

    if ($tapeId <= 0) {
        $tapeId = ql_on_the_go_active_tape_id($userId);
    }

    $tape = ql_on_the_go_tape_get($tapeId, $userId);
    if (!$tape) {
        return ['ok' => false, 'error' => 'invalid_tape_id'];
    }

    $db = ql_db();

    $stmt = $db->prepare("
        SELECT id
        FROM on_the_go_sessions
        WHERE user_id = ?
          AND tape_id = ?
          AND session_type = ?
          AND status = 'active'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $tapeId, $sessionType]);
    $oldSessionId = (int)($stmt->fetchColumn() ?: 0);

    if ($oldSessionId > 0) {
        $stmt = $db->prepare("
            UPDATE on_the_go_sessions
            SET status = 'closed',
                closed_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
              AND user_id = ?
              AND status = 'active'
        ");
        $stmt->execute([$oldSessionId, $userId]);
    }

    $title = $sessionType === 'card' ? 'Card session' : 'Cash session';

    $stmt = $db->prepare("
        INSERT INTO on_the_go_sessions
            (user_id, tape_id, session_type, title, status)
        VALUES
            (?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$userId, $tapeId, $sessionType, $title]);

    $newSessionId = (int)$db->lastInsertId();

    return [
        'ok' => true,
        'closed_session_id' => $oldSessionId ?: null,
        'active_session_id' => $newSessionId,
        'session_type' => $sessionType,
        'tape_id' => $tapeId
    ];
}


/* === FinDesk On the Go Session Detail / Activate / Archive OTR-4F 20260503-61 === */

function ql_on_the_go_session_get(int $sessionId, int $userId): ?array
{
    if ($sessionId <= 0) return null;

    $stmt = ql_db()->prepare("\n        SELECT\n            id, user_id, tape_id, session_type, title, status,\n            started_at, closed_at, archived_at, created_at, updated_at\n        FROM on_the_go_sessions\n        WHERE id = ?\n          AND user_id = ?\n        LIMIT 1\n    ");
    $stmt->execute([$sessionId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ql_on_the_go_session_detail(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $sessionId = (int)($input['session_id'] ?? $input['id'] ?? 0);
    if ($sessionId <= 0) return ['ok' => false, 'error' => 'invalid_session_id'];

    $session = ql_on_the_go_session_get($sessionId, $userId);
    if (!$session) return ['ok' => false, 'error' => 'session_not_found'];

    $stmt = ql_db()->prepare("\n        SELECT\n            COALESCE(SUM(CASE WHEN review_status = 'needs_review' THEN amount ELSE 0 END), 0) AS amount_total,\n            COUNT(*) AS records_total,\n            SUM(CASE WHEN review_status = 'needs_review' THEN 1 ELSE 0 END) AS pending_total\n        FROM on_the_go_captures\n        WHERE session_id = ?\n          AND user_id = ?\n    ");
    $stmt->execute([$sessionId, $userId]);
    $summary = $stmt->fetch() ?: [];

    $stmt = ql_db()->prepare("\n        SELECT\n            c.id, c.user_id, c.tape_id, c.session_id, c.capture_type, c.amount,\n            c.currency, c.description, c.review_status, c.reportable, c.created_at, c.updated_at,\n            (SELECT COUNT(*) FROM on_the_go_files f WHERE f.capture_id = c.id) AS files_count\n        FROM on_the_go_captures c\n        WHERE c.session_id = ?\n          AND c.user_id = ?\n          AND c.review_status <> 'archived'\n        ORDER BY c.created_at DESC, c.id DESC\n    ");
    $stmt->execute([$sessionId, $userId]);

    return [
        'ok' => true,
        'session' => $session,
        'summary' => [
            'amount_total' => round((float)($summary['amount_total'] ?? 0), 2),
            'records_total' => (int)($summary['records_total'] ?? 0),
            'pending_total' => (int)($summary['pending_total'] ?? 0),
        ],
        'items' => $stmt->fetchAll()
    ];
}

function ql_on_the_go_activate_session(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $sessionId = (int)($input['session_id'] ?? $input['id'] ?? 0);
    if ($sessionId <= 0) return ['ok' => false, 'error' => 'invalid_session_id'];

    $session = ql_on_the_go_session_get($sessionId, $userId);
    if (!$session) return ['ok' => false, 'error' => 'session_not_found'];
    if (($session['status'] ?? '') === 'archived') return ['ok' => false, 'error' => 'session_archived'];

    $db = ql_db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("\n            UPDATE on_the_go_sessions\n            SET status = 'closed',\n                closed_at = COALESCE(closed_at, NOW()),\n                updated_at = NOW()\n            WHERE user_id = ?\n              AND tape_id = ?\n              AND session_type = ?\n              AND status = 'active'\n              AND id <> ?\n        ");
        $stmt->execute([$userId, (int)$session['tape_id'], (string)$session['session_type'], $sessionId]);

        $stmt = $db->prepare("\n            UPDATE on_the_go_sessions\n            SET status = 'active',\n                closed_at = NULL,\n                archived_at = NULL,\n                updated_at = NOW()\n            WHERE id = ?\n              AND user_id = ?\n        ");
        $stmt->execute([$sessionId, $userId]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['ok' => false, 'error' => 'activate_failed', 'message' => $e->getMessage()];
    }

    $detail = ql_on_the_go_session_detail(['session_id' => $sessionId]);
    $detail['activated'] = true;
    return $detail;
}

function ql_on_the_go_archive_session(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $sessionId = (int)($input['session_id'] ?? $input['id'] ?? 0);
    if ($sessionId <= 0) return ['ok' => false, 'error' => 'invalid_session_id'];

    $session = ql_on_the_go_session_get($sessionId, $userId);
    if (!$session) return ['ok' => false, 'error' => 'session_not_found'];

    $db = ql_db();
    $db->beginTransaction();
    try {
        $wasActive = ($session['status'] ?? '') === 'active';

        $stmt = $db->prepare("\n            UPDATE on_the_go_sessions\n            SET status = 'archived',\n                archived_at = NOW(),\n                updated_at = NOW()\n            WHERE id = ?\n              AND user_id = ?\n        ");
        $stmt->execute([$sessionId, $userId]);

        $newSessionId = null;
        if ($wasActive) {
            $title = ($session['session_type'] ?? 'cash') === 'card' ? 'Card session' : 'Cash session';
            $stmt = $db->prepare("\n                INSERT INTO on_the_go_sessions\n                    (user_id, tape_id, session_type, title, status)\n                VALUES\n                    (?, ?, ?, ?, 'active')\n            ");
            $stmt->execute([$userId, (int)$session['tape_id'], (string)$session['session_type'], $title]);
            $newSessionId = (int)$db->lastInsertId();
        }

        $db->commit();
        return ['ok' => true, 'archived_session_id' => $sessionId, 'active_session_id' => $newSessionId];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['ok' => false, 'error' => 'archive_session_failed', 'message' => $e->getMessage()];
    }
}
