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

function ql_on_the_go_stream_type($value): string
{
    return (string)$value === 'card' ? 'card' : 'cash';
}

function ql_on_the_go_tape_stream(array $tape): string
{
    return ql_on_the_go_stream_type($tape['stream_type'] ?? 'cash');
}

function ql_on_the_go_capture_types_for_stream(string $streamType): array
{
    return ql_on_the_go_stream_type($streamType) === 'card'
        ? ['noncash_out']
        : ['cash_in', 'cash_out'];
}

function ql_on_the_go_capture_where_for_stream(string $streamType, string $alias = ''): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return ql_on_the_go_stream_type($streamType) === 'card'
        ? "{$prefix}capture_type = 'noncash_out'"
        : "{$prefix}capture_type IN ('cash_in', 'cash_out')";
}

function ql_on_the_go_capture_allowed_for_stream(string $captureType, string $streamType): bool
{
    return in_array($captureType, ql_on_the_go_capture_types_for_stream($streamType), true);
}

function ql_on_the_go_pending_transfer_block(int $tapeId, int $userId): ?array
{
    if ($tapeId <= 0 || $userId <= 0) {
        return null;
    }

    if (function_exists('ql_advance_tape_pending_transfer')) {
        $advance = ql_advance_tape_pending_transfer($tapeId, $userId);
        if ($advance) {
            return [
                'code' => 'advance_transfer_pending_confirmation_required',
                'message' => 'Сначала подтвердите получение денег в карточке сотрудника.',
                'advance_id' => (int)($advance['id'] ?? 0),
            ];
        }
    }

    try {
        $stmt = ql_db()->prepare("
            SELECT ft.id
            FROM on_the_go_tapes t
            JOIN findesk_transfers ft
              ON ft.group_id = t.group_id
             AND ft.assigned_to_user_id = t.user_id
             AND ft.stream_type = t.stream_type
             AND ft.state = 'pending'
            WHERE t.id = ?
              AND t.user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tapeId, $userId]);
        $transferId = (int)($stmt->fetchColumn() ?: 0);
        if ($transferId > 0) {
            return [
                'code' => 'findesk_transfer_pending_confirmation_required',
                'message' => 'Сначала подтвердите получение денег. До подтверждения журнал заблокирован.',
                'transfer_id' => $transferId,
            ];
        }
    } catch (Throwable $e) {
        return null;
    }

    return null;
}

function ql_on_the_go_client_token($value, bool $generate = false, string $prefix = 'field'): string
{
    $token = trim((string)$value);
    if ($token === '' && $generate) {
        $token = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6));
    }
    $token = preg_replace('/[^a-zA-Z0-9._:-]+/', '-', $token) ?? '';
    $token = trim($token, '-');
    if ($token === '' && $generate) {
        $token = $prefix . '-' . bin2hex(random_bytes(8));
    }
    return mb_substr($token, 0, 120);
}

function ql_on_the_go_json_array($json): array
{
    $value = json_decode((string)$json, true);
    return is_array($value) ? $value : [];
}

function ql_on_the_go_json_string(array $value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return '[]';
    }
    return $json;
}

function ql_on_the_go_proof_role($value): string
{
    $role = strtolower(trim((string)$value));
    $role = preg_replace('/[^a-z0-9_-]+/', '_', $role) ?? '';
    $allowed = ['attachment', 'scanner_original', 'scanner_cleaned_pdf'];
    return in_array($role, $allowed, true) ? $role : 'attachment';
}

function ql_on_the_go_proof_metadata($value): string
{
    if (is_array($value)) {
        return ql_on_the_go_json_string($value);
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return '{}';
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return '{}';
    }

    $json = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? '{}' : mb_substr($json, 0, 60000);
}

function ql_on_the_go_public_metadata($json): array
{
    $decoded = json_decode((string)$json, true);
    return is_array($decoded) ? $decoded : [];
}

function ql_on_the_go_field_ensure_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $db = ql_db();
    $db->exec("
        CREATE TABLE IF NOT EXISTS on_the_go_field_drafts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            group_id BIGINT UNSIGNED DEFAULT NULL,
            participant_user_id BIGINT UNSIGNED DEFAULT NULL,
            tape_id BIGINT UNSIGNED NOT NULL,
            session_id BIGINT UNSIGNED NOT NULL,
            stream_type ENUM('cash','card') NOT NULL DEFAULT 'cash',
            client_draft_id VARCHAR(120) NOT NULL,
            draft_status ENUM('active','submitted','closed','archived') NOT NULL DEFAULT 'active',
            sync_state ENUM('saved','pending','failed','retry_needed') NOT NULL DEFAULT 'saved',
            raw_notes MEDIUMTEXT NULL,
            parsed_rows_json MEDIUMTEXT NULL,
            skipped_rows_json MEDIUMTEXT NULL,
            cash_received DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            last_error TEXT NULL,
            last_operation_id VARCHAR(120) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            closed_at DATETIME DEFAULT NULL,
            submitted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_otr_field_draft_user_client (user_id, client_draft_id),
            KEY idx_otr_field_draft_user_open (user_id, draft_status, updated_at),
            KEY idx_otr_field_draft_tape (tape_id),
            KEY idx_otr_field_draft_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS on_the_go_field_sync_ops (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            client_operation_id VARCHAR(120) NOT NULL,
            tape_id BIGINT UNSIGNED DEFAULT NULL,
            session_id BIGINT UNSIGNED DEFAULT NULL,
            status ENUM('pending','succeeded','failed','retry_needed') NOT NULL DEFAULT 'pending',
            response_json MEDIUMTEXT NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_otr_field_sync_user_client (user_id, client_operation_id),
            KEY idx_otr_field_sync_user_status (user_id, status, updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
	    $db->exec("
	        CREATE TABLE IF NOT EXISTS on_the_go_upload_states (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            draft_id BIGINT UNSIGNED DEFAULT NULL,
            capture_id BIGINT UNSIGNED DEFAULT NULL,
            client_upload_id VARCHAR(120) NOT NULL,
            status ENUM('pending','uploaded','failed','retry_needed') NOT NULL DEFAULT 'pending',
            original_name VARCHAR(255) DEFAULT NULL,
            storage_path VARCHAR(500) DEFAULT NULL,
            mime_type VARCHAR(120) DEFAULT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            retry_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            uploaded_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_otr_upload_state_user_client (user_id, client_upload_id),
            KEY idx_otr_upload_state_draft (draft_id),
            KEY idx_otr_upload_state_capture (capture_id),
            KEY idx_otr_upload_state_user_status (user_id, status, updated_at)
	        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	    ");
	    $db->exec("
	        ALTER TABLE on_the_go_files
	            ADD COLUMN IF NOT EXISTS proof_role VARCHAR(40) NOT NULL DEFAULT 'attachment' AFTER size_bytes,
	            ADD COLUMN IF NOT EXISTS proof_bundle_id VARCHAR(120) DEFAULT NULL AFTER proof_role,
	            ADD COLUMN IF NOT EXISTS source_file_id BIGINT UNSIGNED DEFAULT NULL AFTER proof_bundle_id,
	            ADD COLUMN IF NOT EXISTS file_hash_sha256 CHAR(64) DEFAULT NULL AFTER source_file_id,
	            ADD COLUMN IF NOT EXISTS metadata_json MEDIUMTEXT NULL AFTER file_hash_sha256,
	            ADD KEY IF NOT EXISTS idx_otr_files_bundle (proof_bundle_id),
	            ADD KEY IF NOT EXISTS idx_otr_files_role (proof_role)
	    ");
	    $db->exec("
	        ALTER TABLE on_the_go_upload_states
	            ADD COLUMN IF NOT EXISTS proof_role VARCHAR(40) NOT NULL DEFAULT 'attachment' AFTER size_bytes,
	            ADD COLUMN IF NOT EXISTS proof_bundle_id VARCHAR(120) DEFAULT NULL AFTER proof_role,
	            ADD COLUMN IF NOT EXISTS file_hash_sha256 CHAR(64) DEFAULT NULL AFTER proof_bundle_id,
	            ADD COLUMN IF NOT EXISTS metadata_json MEDIUMTEXT NULL AFTER file_hash_sha256
	    ");

	    $done = true;
	}

function ql_on_the_go_field_parsed_totals(array $rows, string $streamType, float $cashReceived = 0.0): array
{
    $streamType = ql_on_the_go_stream_type($streamType);
    $extraCashIn = 0.0;
    $cashOut = 0.0;
    $cardOut = 0.0;

    foreach ($rows as $row) {
        $amount = round((float)($row['amount'] ?? 0), 2);
        if (($row['capture_type'] ?? '') === 'cash_in') {
            $extraCashIn += $amount;
        } elseif (($row['capture_type'] ?? '') === 'cash_out') {
            $cashOut += $amount;
        } elseif (($row['capture_type'] ?? '') === 'noncash_out') {
            $cardOut += $amount;
        }
    }

    if ($streamType === 'card') {
        $cashReceived = 0.0;
        $extraCashIn = 0.0;
        $cashOut = 0.0;
    }

    $cashIn = round($cashReceived + $extraCashIn, 2);
    $cashLeft = $streamType === 'card' ? 0.0 : round($cashIn - $cashOut, 2);

    return [
        'stream_type' => $streamType,
        'cash_received' => round($cashReceived, 2),
        'extra_cash_in' => round($extraCashIn, 2),
        'cash_in' => $cashIn,
        'cash_out' => round($cashOut, 2),
        'card_out' => round($cardOut, 2),
        'cash_left' => $cashLeft,
        'records_count' => count($rows),
    ];
}

function ql_on_the_go_journal_root(): string
{
    $config = function_exists('ql_config') ? ql_config() : [];
    $storage = (string)($config['storage_path'] ?? dirname(__DIR__) . '/storage');
    return rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'live-report-logs';
}

function ql_on_the_go_journal_ensure_dir(string $dir): bool
{
    if (!is_dir($dir) && !mkdir($dir, 0770, true)) {
        return false;
    }

    $deny = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($deny)) {
        @file_put_contents($deny, "Require all denied\nDeny from all\n");
        @chmod($deny, 0640);
    }

    return is_dir($dir) && is_writable($dir);
}

function ql_on_the_go_journal_actor(?int $userId): array
{
    $userId = (int)($userId ?? 0);
    if ($userId <= 0) {
        return ['id' => null, 'email' => '', 'display_name' => 'system'];
    }

    $user = function_exists('ql_current_user_by_id') ? ql_current_user_by_id($userId) : null;
    if (!$user) {
        $stmt = ql_db()->prepare("SELECT id, email, display_name FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch() ?: [];
    }

    return [
        'id' => $userId,
        'email' => (string)($user['email'] ?? ''),
        'display_name' => (string)($user['display_name'] ?? $user['email'] ?? 'user#' . $userId),
    ];
}

function ql_on_the_go_journal_card_snapshot(int $tapeId): ?array
{
    if ($tapeId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT
            t.*,
            g.name AS group_name,
            u.email AS owner_email,
            COALESCE(u.display_name, u.email) AS owner_display_name
        FROM on_the_go_tapes t
        LEFT JOIN groups g ON g.id = t.group_id
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.id = ?
        LIMIT 1
    ");
    $stmt->execute([$tapeId]);
    $card = $stmt->fetch();
    if (!$card) {
        return null;
    }

    $itemsStmt = ql_db()->prepare("
        SELECT
            id, user_id, tape_id, capture_type, amount, currency, description,
            review_status, reportable, created_at, updated_at
        FROM on_the_go_captures
        WHERE tape_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $itemsStmt->execute([$tapeId]);

    $summary = ql_on_the_go_card_summary($tapeId);
    $state = ql_on_the_go_card_state($card, $summary);

    return [
        'id' => (int)$card['id'],
        'title' => (string)($card['title'] ?? ''),
        'state' => $state,
        'status' => (string)($card['status'] ?? ''),
        'group_id' => isset($card['group_id']) ? (int)$card['group_id'] : null,
        'group_name' => (string)($card['group_name'] ?? ''),
        'owner_user_id' => (int)($card['user_id'] ?? 0),
        'owner_email' => (string)($card['owner_email'] ?? ''),
        'owner_display_name' => (string)($card['owner_display_name'] ?? ''),
        'cash_received' => round((float)($card['cash_received'] ?? 0), 2),
        'currency' => (string)($card['currency'] ?? 'EUR'),
        'submitted_at' => (string)($card['submitted_at'] ?? ''),
        'archived_at' => (string)($card['archived_at'] ?? ''),
        'created_at' => (string)($card['created_at'] ?? ''),
        'updated_at' => (string)($card['updated_at'] ?? ''),
        'summary' => $summary,
        'records' => $itemsStmt->fetchAll(),
    ];
}

function ql_on_the_go_journal_append(string $action, ?int $actorUserId, int $tapeId, array $details = []): bool
{
    try {
        $snapshot = ql_on_the_go_journal_card_snapshot($tapeId);
        if (!$snapshot) {
            return false;
        }

        $groupKey = (int)($snapshot['group_id'] ?? 0);
        $dir = ql_on_the_go_journal_root() . DIRECTORY_SEPARATOR . 'append-only';
        if (!ql_on_the_go_journal_ensure_dir($dir)) {
            return false;
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'group-' . ($groupKey > 0 ? $groupKey : 'personal') . '-' . date('Y-m') . '.jsonl';
        $entry = [
            'event_id' => date('YmdHis') . '-' . bin2hex(random_bytes(6)),
            'logged_at' => date('Y-m-d H:i:s'),
            'action' => $action,
            'actor' => ql_on_the_go_journal_actor($actorUserId),
            'ip_address' => function_exists('ql_client_ip') ? ql_client_ip() : '',
            'user_agent' => function_exists('ql_user_agent') ? ql_user_agent() : '',
            'group_id' => $snapshot['group_id'],
            'group_name' => $snapshot['group_name'],
            'tape_id' => $snapshot['id'],
            'owner_user_id' => $snapshot['owner_user_id'],
            'owner_display_name' => $snapshot['owner_display_name'],
            'owner_email' => $snapshot['owner_email'],
            'card_state' => $snapshot['state'],
            'card_status' => $snapshot['status'],
            'summary' => $snapshot['summary'],
            'records' => $snapshot['records'],
            'details' => $details,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        $fh = fopen($file, 'ab');
        if (!$fh) {
            return false;
        }
        if (flock($fh, LOCK_EX)) {
            fwrite($fh, $line);
            fflush($fh);
            flock($fh, LOCK_UN);
        }
        fclose($fh);
        @chmod($file, 0640);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ql_on_the_go_journal_card_label(array $card, array $summary): string
{
    if (($card['status'] ?? '') === 'archived') {
        return 'отменено/удалено';
    }
    if (!empty($card['archived_at'])) {
        return 'в архиве интерфейса';
    }
    if ((int)($summary['reportable_count'] ?? 0) > 0) {
        return 'сдано и включено';
    }
    if (!empty($card['submitted_at'])) {
        return 'сдано';
    }
    if ((int)($summary['records_count'] ?? 0) > 0) {
        return 'не сдано';
    }
    return 'пусто';
}

function ql_on_the_go_journal_row_label(array $item): string
{
    if (($item['review_status'] ?? '') === 'archived') {
        return 'отменено';
    }
    if (!empty($item['reportable'])) {
        return 'в отчете';
    }
    return 'рабочая строка';
}

function ql_on_the_go_journal_admin_scope(int $groupId, int $userId): ?array
{
    if ($groupId <= 0 || !function_exists('ql_ledger_group_scope')) {
        return null;
    }
    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || empty($scope['is_admin'])) {
        return null;
    }
    return $scope;
}

function ql_on_the_go_journal_export(array $input = []): array
{
	    $user = ql_otr_user();
	    if (ql_otr_is_user_error($user)) return $user;
	    ql_on_the_go_field_ensure_schema();

	    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    if (!ql_on_the_go_journal_admin_scope($groupId, $userId)) {
        return ['ok' => false, 'error' => 'archive_admin_only'];
    }

    $dir = ql_on_the_go_journal_root() . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'group-' . $groupId;
    if (!ql_on_the_go_journal_ensure_dir($dir)) {
        return ['ok' => false, 'error' => 'journal_storage_not_writable'];
    }

    $fileName = 'live-report-journal-group-' . $groupId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.csv';
    $path = $dir . DIRECTORY_SEPARATOR . $fileName;
    $fh = fopen($path, 'wb');
    if (!$fh) {
        return ['ok' => false, 'error' => 'journal_export_failed'];
    }

    fwrite($fh, "\xEF\xBB\xBF");
    fputcsv($fh, [
        'Дата записи',
        'Группа',
        'Исполнитель',
        'Email',
        'Карточка ID',
        'Статус карточки',
        'Статус строки',
        'Сдано',
        'Архив интерфейса',
        'Тип',
        'Сумма',
        'Валюта',
        'Описание',
        'Было',
        'Приход',
        'Расход',
        'Остаток',
        'Запись ID',
        'Карточка создана',
        'Обновлено',
    ], ';');

    $stmt = ql_db()->prepare("
        SELECT
            t.*,
            g.name AS group_name,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM on_the_go_tapes t
        LEFT JOIN groups g ON g.id = t.group_id
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.group_id = ?
        ORDER BY t.created_at ASC, t.id ASC
    ");
    $stmt->execute([$groupId]);

    $itemsStmt = ql_db()->prepare("
        SELECT id, capture_type, amount, currency, description, review_status, reportable, created_at, updated_at
        FROM on_the_go_captures
        WHERE tape_id = ?
        ORDER BY created_at ASC, id ASC
    ");

    $rows = 0;
    foreach ($stmt->fetchAll() as $card) {
        $summary = ql_on_the_go_card_summary((int)$card['id']);
        $cardLabel = ql_on_the_go_journal_card_label($card, $summary);
        $itemsStmt->execute([(int)$card['id']]);
        $items = $itemsStmt->fetchAll();

        if (!$items) {
            $items = [[
                'id' => '',
                'capture_type' => '',
                'amount' => '',
                'currency' => $card['currency'] ?? 'EUR',
                'description' => '',
                'review_status' => '',
                'reportable' => 0,
                'created_at' => $card['created_at'] ?? '',
                'updated_at' => $card['updated_at'] ?? '',
            ]];
        }

        foreach ($items as $item) {
            fputcsv($fh, [
                $item['created_at'] ?: ($card['created_at'] ?? ''),
                $card['group_name'] ?? '',
                $card['user_display_name'] ?? $card['email'] ?? '',
                $card['email'] ?? '',
                (int)$card['id'],
                $cardLabel,
                ql_on_the_go_journal_row_label($item),
                $card['submitted_at'] ?? '',
                $card['archived_at'] ?? '',
                $item['capture_type'] ?? '',
                $item['amount'] ?? '',
                $item['currency'] ?? $card['currency'] ?? 'EUR',
                $item['description'] ?? '',
                number_format((float)($summary['before_amount'] ?? 0), 2, '.', ''),
                number_format((float)($summary['extra_cash_in'] ?? 0), 2, '.', ''),
                number_format((float)($summary['spent_total'] ?? 0), 2, '.', ''),
                number_format((float)($summary['after_amount'] ?? 0), 2, '.', ''),
                $item['id'] ?? '',
                $card['created_at'] ?? '',
                $item['updated_at'] ?? $card['updated_at'] ?? '',
            ], ';');
            $rows++;
        }
    }

    fclose($fh);
    @chmod($path, 0640);

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_journal_exported', 'group', $groupId, [
            'file' => $fileName,
            'rows' => $rows,
        ]);
    }

    return [
        'ok' => true,
        'file' => $fileName,
        'rows' => $rows,
        'download_url' => '/api.php?action=on_the_go_journal_download&group_id=' . $groupId . '&file=' . rawurlencode($fileName),
    ];
}

function ql_on_the_go_journal_download(): void
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $groupId = (int)($_GET['group_id'] ?? 0);
    if (!ql_on_the_go_journal_admin_scope($groupId, (int)$user['id'])) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }

    $file = basename((string)($_GET['file'] ?? ''));
    if (!preg_match('/^live-report-journal-group-\d+-\d{8}-\d{6}-[a-f0-9]{8}\.csv$/', $file)) {
        http_response_code(400);
        echo 'Invalid file';
        exit;
    }

    $dir = ql_on_the_go_journal_root() . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR . 'group-' . $groupId;
    $base = realpath($dir);
    $target = realpath($dir . DIRECTORY_SEPARATOR . $file);
    if (!$base || !$target || strpos($target, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($target)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Length: ' . filesize($target));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($target);
    exit;
}

function ql_on_the_go_open_accountable_totals(int $groupId, ?int $ownerUserId = null): array
{
    if ($groupId <= 0) {
        return [
            'issued_open' => 0.0,
            'cash_left_open' => 0.0,
            'cash_spent_open' => 0.0,
            'card_spent_open' => 0.0,
            'spent_open' => 0.0,
            'open_count' => 0,
        ];
    }

    $params = [$groupId];
    $ownerWhere = '';
    if ($ownerUserId !== null && $ownerUserId > 0) {
        $ownerWhere = ' AND ca.assigned_to_user_id = ?';
        $params[] = $ownerUserId;
    }

    $stmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(ca.amount), 0) AS issued_open,
            COALESCE(SUM(
                CASE
                    WHEN ca.actual_remaining IS NOT NULL THEN ca.actual_remaining
                    ELSE COALESCE(t.cash_received, ca.amount) + COALESCE(s.extra_cash_in, 0) - COALESCE(s.cash_out, 0)
                END
            ), 0) AS cash_left_open,
            COALESCE(SUM(COALESCE(s.cash_out, 0)), 0) AS cash_spent_open,
            COALESCE(SUM(COALESCE(s.card_out, 0)), 0) AS card_spent_open,
            COALESCE(SUM(COALESCE(s.cash_out, 0) + COALESCE(s.card_out, 0)), 0) AS spent_open,
            COUNT(ca.id) AS open_count
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
          AND ca.status IN ('issued', 'submitted', 'returned', 'discrepancy')
          {$ownerWhere}
    ");
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];

    $issued = round((float)($row['issued_open'] ?? 0), 2);
    $left = round((float)($row['cash_left_open'] ?? 0), 2);
    $cashSpent = round((float)($row['cash_spent_open'] ?? 0), 2);
    $cardSpent = round((float)($row['card_spent_open'] ?? 0), 2);
    $spent = round((float)($row['spent_open'] ?? 0), 2);

    return [
        'issued_open' => $issued,
        'cash_left_open' => $left,
        'cash_spent_open' => $cashSpent,
        'card_spent_open' => $cardSpent,
        'spent_open' => $spent,
        'open_count' => (int)($row['open_count'] ?? 0),
    ];
}

function ql_on_the_go_group_work_balance(int $groupId): float
{
    if ($groupId <= 0) {
        return 0.0;
    }

    $stmt = ql_db()->prepare("
        SELECT COALESCE(SUM(CASE WHEN entry_type = 'income' THEN amount ELSE -amount END), 0)
        FROM ledger_entries
        WHERE group_id = ?
          AND deleted_at IS NULL
          AND money_type = 'cash'
    ");
    $stmt->execute([$groupId]);

    $balance = (float)($stmt->fetchColumn() ?: 0);
    if (function_exists('ql_on_the_go_submitted_card_totals')) {
        $virtual = ql_on_the_go_submitted_card_totals($groupId, null, false);
        $balance += (float)($virtual['cash_delta'] ?? $virtual['balance_delta'] ?? 0);
    }
    $accountable = ql_on_the_go_open_accountable_totals($groupId);
    $balance -= (float)($accountable['cash_left_open'] ?? 0) + (float)($accountable['cash_spent_open'] ?? 0);

    return round($balance, 2);
}

function ql_on_the_go_can_use_group_work_balance(int $userId, int $groupId): bool
{
    if ($userId <= 0 || $groupId <= 0 || !function_exists('ql_ledger_group_scope')) {
        return false;
    }

    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope) {
        return false;
    }

    return !empty($scope['can_write_group_ledger'])
        || !empty($scope['can_view_group_reports'])
        || !empty($scope['is_admin']);
}

function ql_on_the_go_latest_unsent_card_after_amount(int $userId, int $groupId): ?float
{
    if ($userId <= 0 || $groupId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT t.id
        FROM on_the_go_tapes t
        WHERE t.user_id = ?
          AND t.group_id = ?
          AND t.status <> 'archived'
          AND t.stream_type = 'cash'
          AND t.submitted_at IS NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.review_status <> 'archived'
              LIMIT 1
          )
        ORDER BY COALESCE(t.updated_at, t.created_at) DESC, t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $groupId]);

    $tapeId = (int)($stmt->fetchColumn() ?: 0);
    if ($tapeId <= 0) {
        return null;
    }

    $summary = ql_on_the_go_card_summary($tapeId);
    return round((float)($summary['after_amount'] ?? 0), 2);
}

function ql_on_the_go_sync_empty_group_tape_base(int $userId, int $groupId): void
{
    if ($userId <= 0 || $groupId <= 0 || !function_exists('ql_ledger_group_scope')) {
        return;
    }

    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope || (empty($scope['can_write_group_ledger']) && empty($scope['can_view_group_reports']) && empty($scope['is_admin']))) {
        return;
    }

    try {
        $findeskStmt = ql_db()->prepare("
            SELECT
                (SELECT COUNT(*) FROM findesk_transfers WHERE group_id = ? AND state IN ('pending','active')) +
                (SELECT COUNT(*) FROM findesk_reports WHERE group_id = ? AND status IN ('draft','finalized','archived'))
        ");
        $findeskStmt->execute([$groupId, $groupId]);
        if ((int)($findeskStmt->fetchColumn() ?: 0) > 0) {
            return;
        }
    } catch (Throwable $e) {
        // Older installations may not have the FinDesk phase-2 tables yet.
    }

    $balance = ql_on_the_go_latest_unsent_card_after_amount($userId, $groupId);
    if ($balance === null) {
        $balance = ql_on_the_go_group_work_balance($groupId);
    }
    $stmt = ql_db()->prepare("
        UPDATE on_the_go_tapes t
        SET t.cash_received = ?,
            t.updated_at = t.updated_at
        WHERE t.user_id = ?
          AND t.group_id = ?
          AND t.status = 'active'
          AND t.stream_type = 'cash'
          AND t.submitted_at IS NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND NOT EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.review_status <> 'archived'
              LIMIT 1
          )
    ");
    $stmt->execute([number_format($balance, 2, '.', ''), $userId, $groupId]);
}

function ql_on_the_go_sync_empty_group_tapes_for_user(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $stmt = ql_db()->prepare("
        SELECT DISTINCT t.group_id
        FROM on_the_go_tapes t
        WHERE t.user_id = ?
          AND t.group_id IS NOT NULL
          AND t.status = 'active'
          AND t.stream_type = 'cash'
          AND t.submitted_at IS NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND NOT EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.review_status <> 'archived'
              LIMIT 1
          )
    ");
    $stmt->execute([$userId]);

    foreach ($stmt->fetchAll() as $row) {
        ql_on_the_go_sync_empty_group_tape_base($userId, (int)($row['group_id'] ?? 0));
    }
}

function ql_on_the_go_normalize_saved_drafts(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $stmt = ql_db()->prepare("
        UPDATE on_the_go_tapes t
        SET t.status = 'closed',
            t.updated_at = t.updated_at
        WHERE t.user_id = ?
          AND t.status = 'active'
          AND t.submitted_at IS NULL
          AND EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.review_status <> 'archived'
              LIMIT 1
          )
    ");
    $stmt->execute([$userId]);
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
            group_id,
            advance_id,
            stream_type,
            title,
            cash_received,
            currency,
            status,
            created_at,
            updated_at,
            closed_at,
            submitted_at,
            actual_remaining,
            difference_amount,
            archived_at
        FROM on_the_go_tapes
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tapeId, $userId]);

    $row = $stmt->fetch();
    return $row ?: null;
}

function ql_on_the_go_active_tape_id(int $userId, ?int $groupId = null, string $streamType = 'cash'): int
{
    $groupId = (int)($groupId ?? 0);
    $streamType = ql_on_the_go_stream_type($streamType);
    $groupWhere = '';
    $params = [$userId, $streamType];
    if ($groupId > 0) {
        $groupWhere = ' AND group_id = ?';
        $params[] = $groupId;
    }

    $stmt = ql_db()->prepare("
        SELECT id
        FROM on_the_go_tapes
        WHERE user_id = ?
          AND stream_type = ?
          AND status = 'active'
          AND submitted_at IS NULL
          {$groupWhere}
          AND NOT EXISTS (
            SELECT 1
            FROM on_the_go_captures c
            WHERE c.tape_id = on_the_go_tapes.id
              AND c.review_status <> 'archived'
            LIMIT 1
          )
        ORDER BY
          CASE WHEN advance_id IS NOT NULL AND advance_id > 0 THEN 0 ELSE 1 END,
          created_at DESC,
          id DESC
        LIMIT 1
    ");
    $stmt->execute($params);

    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $cashReceived = 0.0;
    if ($streamType === 'cash' && $groupId > 0) {
        $ownLatest = ql_on_the_go_latest_unsent_card_after_amount($userId, $groupId);
        $cashReceived = $ownLatest !== null ? $ownLatest : 0.0;
        if (ql_on_the_go_can_use_group_work_balance($userId, $groupId)) {
            $cashReceived = ql_on_the_go_group_work_balance($groupId);
        }
    }
    $title = $streamType === 'card' ? 'Живой отчет: карта' : 'On the Go';
    $stmt = ql_db()->prepare("
        INSERT INTO on_the_go_tapes
            (user_id, group_id, stream_type, title, cash_received, currency, status)
        VALUES
            (?, ?, ?, ?, ?, 'EUR', 'active')
    ");
    $stmt->execute([
        $userId,
        $groupId > 0 ? $groupId : null,
        $streamType,
        $title,
        number_format($cashReceived, 2, '.', ''),
    ]);

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
        SELECT stream_type, cash_received
        FROM on_the_go_tapes
        WHERE id = ?
        LIMIT 1
    ");
    $tapeStmt->execute([$tapeId]);
    $tape = $tapeStmt->fetch() ?: [];
    $streamType = ql_on_the_go_stream_type($tape['stream_type'] ?? 'cash');
    $adminCashIn = $streamType === 'card' ? 0.0 : round((float)($tape['cash_received'] ?? 0), 2);

    $stmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN c.capture_type = 'cash_in' THEN c.amount ELSE 0 END), 0) AS extra_cash_in,
            COALESCE(SUM(CASE WHEN c.capture_type = 'cash_out' THEN c.amount ELSE 0 END), 0) AS cash_out,
            COALESCE(SUM(CASE WHEN c.capture_type = 'noncash_out' THEN c.amount ELSE 0 END), 0) AS card_out,
            COUNT(c.id) AS records_count
        FROM on_the_go_captures c
        JOIN on_the_go_sessions s ON s.id = c.session_id
        WHERE c.tape_id = ?
          AND c.review_status = 'needs_review'
          AND c.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
          AND s.status = 'active'
    ");
    $stmt->execute([$tapeId]);

    $s = $stmt->fetch() ?: [];

    $extraCashIn = round((float)($s['extra_cash_in'] ?? 0), 2);
    $cashOut = round((float)($s['cash_out'] ?? 0), 2);
    $cardOut = round((float)($s['card_out'] ?? 0), 2);
    if ($streamType === 'card') {
        $extraCashIn = 0.0;
        $cashOut = 0.0;
    }
    $cashIn = round($adminCashIn + $extraCashIn, 2);
    $cashLeft = $streamType === 'card' ? 0.0 : round($cashIn - $cashOut, 2);

    return [
        'stream_type' => $streamType,
        'admin_cash_in' => $adminCashIn,
        'extra_cash_in' => $extraCashIn,
        'cash_in' => $cashIn,
        'cash_out' => $cashOut,
        'card_out' => $cardOut,
        'cash_left' => $cashLeft,
        'records_count' => (int)($s['records_count'] ?? 0),
    ];
}

function ql_on_the_go_card_summary(int $tapeId): array
{
    $tapeStmt = ql_db()->prepare("
        SELECT stream_type, cash_received, submitted_at, actual_remaining, difference_amount
        FROM on_the_go_tapes
        WHERE id = ?
        LIMIT 1
    ");
    $tapeStmt->execute([$tapeId]);
    $tape = $tapeStmt->fetch() ?: [];

    $streamType = ql_on_the_go_stream_type($tape['stream_type'] ?? 'cash');
    $before = $streamType === 'card' ? 0.0 : round((float)($tape['cash_received'] ?? 0), 2);

    $stmt = ql_db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN capture_type = 'cash_in' THEN amount ELSE 0 END), 0) AS extra_cash_in,
            COALESCE(SUM(CASE WHEN capture_type = 'cash_out' THEN amount ELSE 0 END), 0) AS cash_out,
            COALESCE(SUM(CASE WHEN capture_type = 'noncash_out' THEN amount ELSE 0 END), 0) AS card_out,
            COUNT(id) AS records_count,
            SUM(CASE WHEN reportable = 1 THEN 1 ELSE 0 END) AS reportable_count,
            COALESCE(MIN(created_at), '') AS first_record_at,
            COALESCE(MAX(created_at), '') AS last_record_at,
            COALESCE((
                SELECT COUNT(*)
                FROM on_the_go_files f
                JOIN on_the_go_captures c2 ON c2.id = f.capture_id
                WHERE c2.tape_id = ?
                  AND c2.review_status <> 'archived'
            ), 0) AS files_count
        FROM on_the_go_captures
        WHERE tape_id = ?
          AND review_status <> 'archived'
          AND capture_type IN ('cash_in', 'cash_out', 'noncash_out')
    ");
    $stmt->execute([$tapeId, $tapeId]);
    $row = $stmt->fetch() ?: [];

    $extraCashIn = round((float)($row['extra_cash_in'] ?? 0), 2);
    $cashOut = round((float)($row['cash_out'] ?? 0), 2);
    $cardOut = round((float)($row['card_out'] ?? 0), 2);
    if ($streamType === 'card') {
        $extraCashIn = 0.0;
        $cashOut = 0.0;
    }
    $spent = round($cashOut + $cardOut, 2);
    $cashDelta = $streamType === 'card' ? 0.0 : round($extraCashIn - $cashOut, 2);
    $cardDelta = round(0 - $cardOut, 2);
    $after = $streamType === 'card' ? $cardDelta : round($before + $cashDelta, 2);
    $delta = $streamType === 'card' ? $cardDelta : round($cashDelta + $cardDelta, 2);

    return [
        'stream_type' => $streamType,
        'admin_cash_in' => $before,
        'extra_cash_in' => $extraCashIn,
        'cash_in' => round($before + $extraCashIn, 2),
        'cash_out' => $cashOut,
        'card_out' => $cardOut,
        'spent_total' => $spent,
        'cash_left' => $streamType === 'card' ? 0.0 : $after,
        'before_amount' => $before,
        'after_amount' => $after,
        'cash_delta' => $cashDelta,
        'card_delta' => $cardDelta,
        'delta' => $delta,
        'records_count' => (int)($row['records_count'] ?? 0),
        'reportable_count' => (int)($row['reportable_count'] ?? 0),
        'files_count' => (int)($row['files_count'] ?? 0),
        'first_record_at' => $row['first_record_at'] ?? '',
        'last_record_at' => $row['last_record_at'] ?? '',
        'submitted_actual_remaining' => $tape['actual_remaining'] !== null ? round((float)$tape['actual_remaining'], 2) : null,
        'submitted_delta' => $tape['difference_amount'] !== null ? round((float)$tape['difference_amount'], 2) : null,
    ];
}

function ql_on_the_go_card_state(array $tape, array $summary): string
{
    if ((int)($summary['reportable_count'] ?? 0) > 0) {
        return 'included';
    }

    if (!empty($tape['submitted_at'])) {
        return 'submitted';
    }

    if ((int)($summary['records_count'] ?? 0) > 0) {
        return 'draft';
    }

    return 'empty';
}


function ql_on_the_go_tape_list(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) {
        return $user;
    }

    $groupId = (int)($input['group_id'] ?? 0);
    $streamType = ql_on_the_go_stream_type($input['stream_type'] ?? 'cash');
    if ($groupId > 0 && function_exists('ql_ledger_group_scope') && !ql_ledger_group_scope($groupId, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }

    ql_on_the_go_normalize_saved_drafts((int)$user['id']);
    ql_on_the_go_sync_empty_group_tapes_for_user((int)$user['id']);

    $activeId = ql_on_the_go_active_tape_id((int)$user['id'], $groupId > 0 ? $groupId : null, $streamType);
    $groupWhere = '';
    $params = [(int)$user['id'], $streamType];
    if ($groupId > 0) {
        $groupWhere = ' AND group_id = ?';
        $params[] = $groupId;
    }
    $params[] = $activeId;

    $stmt = ql_db()->prepare("
        SELECT
            id,
            user_id,
            group_id,
            advance_id,
            stream_type,
            title,
            cash_received,
            currency,
            status,
            created_at,
            updated_at,
            closed_at,
            submitted_at,
            actual_remaining,
            difference_amount,
            archived_at
        FROM on_the_go_tapes
        WHERE user_id = ?
          AND stream_type = ?
          AND status <> 'archived'
          {$groupWhere}
        ORDER BY
          CASE WHEN id = ? THEN 0 ELSE 1 END,
          CASE WHEN EXISTS (
            SELECT 1
            FROM on_the_go_captures c
            WHERE c.tape_id = on_the_go_tapes.id
              AND c.review_status <> 'archived'
            LIMIT 1
          ) THEN 0 ELSE 1 END,
          created_at DESC,
          id DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    $tapes = $stmt->fetchAll();

    if (!$tapes) {
        $tape = ql_on_the_go_tape_get($activeId, (int)$user['id']);
        $tapes = $tape ? [$tape] : [];
    }

    foreach ($tapes as &$tape) {
        $tape['summary'] = ql_on_the_go_tape_summary((int)$tape['id']);
        $tape['card_summary'] = ql_on_the_go_card_summary((int)$tape['id']);
        $tape['card_state'] = ql_on_the_go_card_state($tape, $tape['card_summary']);
        if ((int)($tape['advance_id'] ?? 0) > 0 && function_exists('ql_advance_row') && function_exists('ql_advance_transfer_public_flags')) {
            $advance = ql_advance_row((int)$tape['advance_id']);
            if ($advance) {
                $tape = array_merge($tape, ql_advance_transfer_public_flags($advance));
            }
        }
    }

    return [
        'ok' => true,
        'active_tape_id' => $activeId,
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
    $streamType = ql_on_the_go_stream_type($input['stream_type'] ?? 'cash');
    if ($streamType === 'card') {
        $amount = 0.0;
    }

    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        $title = $streamType === 'card' ? 'Живой отчет: карта' : 'On the Go';
    }
    if (mb_strlen($title) > 190) {
        $title = mb_substr($title, 0, 190);
    }

    $groupId = (int)($input['group_id'] ?? 0);
    if ($groupId > 0) {
        if (!function_exists('ql_ledger_group_scope') || !ql_ledger_group_scope($groupId, (int)$user['id'])) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }
    } else {
        $groupId = null;
    }

    $currency = strtoupper(trim((string)($input['currency'] ?? 'EUR')));
    if (!preg_match('/^[A-Z]{3}$/', $currency)) {
        $currency = 'EUR';
    }

    $db = ql_db();

    $stmt = $db->prepare("
        INSERT INTO on_the_go_tapes
            (user_id, group_id, stream_type, title, cash_received, currency, status)
        VALUES
            (?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([(int)$user['id'], $groupId, $streamType, $title, $amount, $currency]);

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
        $streamType = ql_on_the_go_stream_type($input['stream_type'] ?? ($type === 'noncash_out' ? 'card' : 'cash'));
        $tapeId = ql_on_the_go_active_tape_id((int)$user['id'], null, $streamType);
        $tape = ql_on_the_go_tape_get($tapeId, (int)$user['id']);
    }
    $streamType = ql_on_the_go_tape_stream($tape ?? []);
    $pendingTransfer = ql_on_the_go_pending_transfer_block($tapeId, (int)$user['id']);
    if ($pendingTransfer) {
        return [
            'ok' => false,
            'error' => $pendingTransfer['code'],
            'message' => $pendingTransfer['message'],
            'advance_id' => (int)($pendingTransfer['advance_id'] ?? 0),
            'transfer_id' => (int)($pendingTransfer['transfer_id'] ?? 0),
        ];
    }
    if (!ql_on_the_go_capture_allowed_for_stream($type, $streamType)) {
        return ['ok' => false, 'error' => 'capture_type_not_allowed_for_stream'];
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

    $sessionType = $streamType === 'card' ? 'card' : 'cash';
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

    $captureId = (int)$db->lastInsertId();
    ql_on_the_go_journal_append('record_created', (int)$user['id'], $tapeId, [
        'capture_id' => $captureId,
        'capture_type' => $type,
        'amount' => $amount,
        'description' => $description,
    ]);

    return [
        'ok' => true,
        'capture' => ql_on_the_go_get_one($captureId, (int)$user['id'])
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

function ql_on_the_go_get_one_visible(int $id, int $viewerId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT
            c.*,
            t.group_id,
            t.user_id AS tape_user_id,
            (SELECT COUNT(*) FROM on_the_go_files f WHERE f.capture_id = c.id) AS files_count
        FROM on_the_go_captures c
        LEFT JOIN on_the_go_tapes t ON t.id = c.tape_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if ((int)$row['user_id'] === $viewerId || (int)($row['tape_user_id'] ?? 0) === $viewerId) {
        return $row;
    }

    $groupId = (int)($row['group_id'] ?? 0);
    if ($groupId <= 0 || !function_exists('ql_ledger_group_scope')) {
        return null;
    }

    $scope = ql_ledger_group_scope($groupId, $viewerId);
    if (!$scope) {
        return null;
    }

    if (!empty($scope['can_write_group_ledger']) || !empty($scope['can_view_group_reports']) || !empty($scope['is_admin'])) {
        return $row;
    }

    return null;
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
    $sessionType = ql_on_the_go_stream_type($input['session_type'] ?? $input['stream_type'] ?? 'cash');
    if ($tapeId > 0) {
        $tape = ql_on_the_go_tape_get($tapeId, $userId);
        if (!$tape) {
            return ['ok' => false, 'error' => 'invalid_tape_id'];
        }
        $sessionType = ql_on_the_go_tape_stream($tape);
    } else {
        $tapeId = ql_on_the_go_active_tape_id($userId, null, $sessionType);
    }

    $captureWhere = ql_on_the_go_capture_where_for_stream($sessionType, 'c');
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
          AND {$captureWhere}
          AND s.status = 'active'
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$userId, $tapeId, $activeSessionId]);

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

function ql_on_the_go_parse_signed_notes(string $notes, string $streamType = 'cash'): array
{
    $streamType = ql_on_the_go_stream_type($streamType);
    $normalized = str_replace(["\r", ";"], ["\n", "\n"], $notes);
    $parts = preg_split('/\n|,(?=\s*[+\-]\s*\d)/u', $normalized) ?: [];
    $items = [];
    $skipped = [];
    $pattern = '/^([+\-])\s*((?:\d{1,3}(?:[ .]\d{3})+|\d+)(?:[,.]\d+)?)\s*(.*)$/u';

    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part === '') {
            continue;
        }

        if (!preg_match($pattern, $part, $match)) {
            $skipped[] = $part;
            continue;
        }

        $rawAmount = str_replace([" ", "\xc2\xa0"], '', $match[2]);
        if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $rawAmount)) {
            $rawAmount = str_replace('.', '', $rawAmount);
        }
        $rawAmount = str_replace(',', '.', $rawAmount);
        $amount = round(abs((float)$rawAmount), 2);

        if ($amount <= 0) {
            $skipped[] = $part;
            continue;
        }

        $description = trim((string)($match[3] ?? ''));
        if (mb_strlen($description) > 255) {
            $description = mb_substr($description, 0, 255);
        }

        if ($streamType === 'card' && $match[1] === '+') {
            $skipped[] = $part;
            continue;
        }

        $captureType = $streamType === 'card'
            ? 'noncash_out'
            : ($match[1] === '+' ? 'cash_in' : 'cash_out');

        $items[] = [
            'capture_type' => $captureType,
            'amount' => $amount,
            'description' => $description,
            'source' => $part,
        ];
    }

    return ['items' => $items, 'skipped' => $skipped];
}

function ql_on_the_go_field_draft_row_by_client(int $userId, string $clientDraftId): ?array
{
    if ($userId <= 0 || $clientDraftId === '') {
        return null;
    }
    ql_on_the_go_field_ensure_schema();
    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_field_drafts
        WHERE user_id = ?
          AND client_draft_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $clientDraftId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ql_on_the_go_field_draft_row(int $draftId, int $userId): ?array
{
    if ($draftId <= 0 || $userId <= 0) {
        return null;
    }
    ql_on_the_go_field_ensure_schema();
    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_field_drafts
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$draftId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ql_on_the_go_field_latest_draft(int $userId, int $groupId = 0, string $streamType = ''): ?array
{
    if ($userId <= 0) {
        return null;
    }
    ql_on_the_go_field_ensure_schema();
    $where = "user_id = ? AND draft_status = 'active'";
    $params = [$userId];
    if ($groupId > 0) {
        $where .= " AND group_id = ?";
        $params[] = $groupId;
    }
    if ($streamType !== '') {
        $where .= " AND stream_type = ?";
        $params[] = ql_on_the_go_stream_type($streamType);
    }
    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_field_drafts
        WHERE {$where}
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ql_on_the_go_upload_state_public(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'draft_id' => isset($row['draft_id']) ? (int)$row['draft_id'] : null,
        'capture_id' => isset($row['capture_id']) ? (int)$row['capture_id'] : null,
        'client_upload_id' => (string)($row['client_upload_id'] ?? ''),
        'status' => (string)($row['status'] ?? 'pending'),
        'original_name' => (string)($row['original_name'] ?? ''),
	        'storage_path' => (string)($row['storage_path'] ?? ''),
	        'mime_type' => (string)($row['mime_type'] ?? ''),
	        'size_bytes' => (int)($row['size_bytes'] ?? 0),
	        'proof_role' => (string)($row['proof_role'] ?? 'attachment'),
	        'proof_bundle_id' => (string)($row['proof_bundle_id'] ?? ''),
	        'file_hash_sha256' => (string)($row['file_hash_sha256'] ?? ''),
	        'metadata' => ql_on_the_go_public_metadata($row['metadata_json'] ?? '{}'),
	        'last_error' => (string)($row['last_error'] ?? ''),
        'retry_count' => (int)($row['retry_count'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'uploaded_at' => (string)($row['uploaded_at'] ?? ''),
    ];
}

function ql_on_the_go_upload_states_for(int $userId, ?int $draftId = null, ?int $captureId = null): array
{
    ql_on_the_go_field_ensure_schema();
    $where = "user_id = ?";
    $params = [$userId];
    if ($draftId !== null && $draftId > 0) {
        $where .= " AND draft_id = ?";
        $params[] = $draftId;
    }
    if ($captureId !== null && $captureId > 0) {
        $where .= " AND capture_id = ?";
        $params[] = $captureId;
    }
    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_upload_states
        WHERE {$where}
        ORDER BY updated_at DESC, id DESC
        LIMIT 100
    ");
    $stmt->execute($params);
    return array_map('ql_on_the_go_upload_state_public', $stmt->fetchAll());
}

function ql_on_the_go_upload_state_record(int $userId, string $clientUploadId, array $data): ?array
{
    if ($userId <= 0 || $clientUploadId === '') {
        return null;
    }
    ql_on_the_go_field_ensure_schema();

    $existing = null;
    $stmt = ql_db()->prepare("SELECT * FROM on_the_go_upload_states WHERE user_id = ? AND client_upload_id = ? LIMIT 1");
    $stmt->execute([$userId, $clientUploadId]);
    $existing = $stmt->fetch() ?: null;

    $status = (string)($data['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'uploaded', 'failed', 'retry_needed'], true)) {
        $status = 'pending';
    }
    $retryCount = (int)($existing['retry_count'] ?? 0);
    if (!empty($data['increment_retry'])) {
        $retryCount++;
    }

    $draftId = isset($data['draft_id']) && (int)$data['draft_id'] > 0 ? (int)$data['draft_id'] : null;
    $captureId = isset($data['capture_id']) && (int)$data['capture_id'] > 0 ? (int)$data['capture_id'] : null;
    if (!$draftId && $existing && !empty($existing['draft_id'])) {
        $draftId = (int)$existing['draft_id'];
    }
    if (!$captureId && $existing && !empty($existing['capture_id'])) {
        $captureId = (int)$existing['capture_id'];
    }

	    $proofRole = ql_on_the_go_proof_role($data['proof_role'] ?? ($existing['proof_role'] ?? 'attachment'));
	    $proofBundleId = ql_on_the_go_client_token($data['proof_bundle_id'] ?? ($existing['proof_bundle_id'] ?? ''), false, 'proof');
	    $fileHash = strtolower(trim((string)($data['file_hash_sha256'] ?? ($existing['file_hash_sha256'] ?? ''))));
	    if (!preg_match('/^[a-f0-9]{64}$/', $fileHash)) {
	        $fileHash = null;
	    }
	    $metadataJson = array_key_exists('metadata_json', $data)
	        ? ql_on_the_go_proof_metadata($data['metadata_json'])
	        : ($existing['metadata_json'] ?? null);

	    $stmt = ql_db()->prepare("
	        INSERT INTO on_the_go_upload_states
	            (user_id, draft_id, capture_id, client_upload_id, status, original_name, storage_path, mime_type, size_bytes, proof_role, proof_bundle_id, file_hash_sha256, metadata_json, last_error, retry_count, uploaded_at)
	        VALUES
	            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	        ON DUPLICATE KEY UPDATE
	            draft_id = VALUES(draft_id),
	            capture_id = VALUES(capture_id),
	            status = VALUES(status),
	            original_name = COALESCE(VALUES(original_name), original_name),
	            storage_path = COALESCE(VALUES(storage_path), storage_path),
	            mime_type = COALESCE(VALUES(mime_type), mime_type),
	            size_bytes = VALUES(size_bytes),
	            proof_role = VALUES(proof_role),
	            proof_bundle_id = COALESCE(VALUES(proof_bundle_id), proof_bundle_id),
	            file_hash_sha256 = COALESCE(VALUES(file_hash_sha256), file_hash_sha256),
	            metadata_json = COALESCE(VALUES(metadata_json), metadata_json),
	            last_error = VALUES(last_error),
	            retry_count = VALUES(retry_count),
	            uploaded_at = COALESCE(VALUES(uploaded_at), uploaded_at),
            updated_at = NOW()
    ");
    $stmt->execute([
        $userId,
        $draftId,
        $captureId,
        $clientUploadId,
        $status,
        $data['original_name'] ?? null,
        $data['storage_path'] ?? null,
	        $data['mime_type'] ?? null,
	        (int)($data['size_bytes'] ?? ($existing['size_bytes'] ?? 0)),
	        $proofRole,
	        $proofBundleId ?: null,
	        $fileHash,
	        $metadataJson,
	        $data['last_error'] ?? null,
	        $retryCount,
        $status === 'uploaded' ? date('Y-m-d H:i:s') : null,
    ]);

    $stmt = ql_db()->prepare("SELECT * FROM on_the_go_upload_states WHERE user_id = ? AND client_upload_id = ? LIMIT 1");
    $stmt->execute([$userId, $clientUploadId]);
    $row = $stmt->fetch();
    return $row ? ql_on_the_go_upload_state_public($row) : null;
}

function ql_on_the_go_field_draft_public(array $row): array
{
    $parsedRows = ql_on_the_go_json_array($row['parsed_rows_json'] ?? '[]');
    $skippedRows = ql_on_the_go_json_array($row['skipped_rows_json'] ?? '[]');
    $streamType = ql_on_the_go_stream_type($row['stream_type'] ?? 'cash');
    $cashReceived = round((float)($row['cash_received'] ?? 0), 2);

    return [
        'id' => (int)($row['id'] ?? 0),
        'user_id' => (int)($row['user_id'] ?? 0),
        'group_id' => isset($row['group_id']) ? (int)$row['group_id'] : null,
        'participant_user_id' => isset($row['participant_user_id']) ? (int)$row['participant_user_id'] : null,
        'tape_id' => (int)($row['tape_id'] ?? 0),
        'session_id' => (int)($row['session_id'] ?? 0),
        'stream_type' => $streamType,
        'client_draft_id' => (string)($row['client_draft_id'] ?? ''),
        'draft_status' => (string)($row['draft_status'] ?? 'active'),
        'sync_state' => (string)($row['sync_state'] ?? 'saved'),
        'raw_notes' => (string)($row['raw_notes'] ?? ''),
        'parsed_rows' => $parsedRows,
        'skipped_rows' => $skippedRows,
        'cash_received' => $cashReceived,
        'draft_totals' => ql_on_the_go_field_parsed_totals($parsedRows, $streamType, $cashReceived),
        'last_error' => (string)($row['last_error'] ?? ''),
        'last_operation_id' => (string)($row['last_operation_id'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'closed_at' => (string)($row['closed_at'] ?? ''),
        'submitted_at' => (string)($row['submitted_at'] ?? ''),
    ];
}

function ql_on_the_go_field_operation_response(int $userId, string $clientOperationId): ?array
{
    if ($clientOperationId === '') {
        return null;
    }
    ql_on_the_go_field_ensure_schema();
    $stmt = ql_db()->prepare("
        SELECT status, response_json
        FROM on_the_go_field_sync_ops
        WHERE user_id = ?
          AND client_operation_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $clientOperationId]);
    $row = $stmt->fetch();
    if (!$row || ($row['status'] ?? '') !== 'succeeded') {
        return null;
    }
    $response = json_decode((string)($row['response_json'] ?? ''), true);
    if (!is_array($response)) {
        return null;
    }
    $response['idempotent'] = true;
    return $response;
}

function ql_on_the_go_field_operation_record(int $userId, string $clientOperationId, string $status, ?int $tapeId = null, ?int $sessionId = null, ?array $response = null, string $error = ''): void
{
    if ($clientOperationId === '') {
        return;
    }
    ql_on_the_go_field_ensure_schema();
    if (!in_array($status, ['pending', 'succeeded', 'failed', 'retry_needed'], true)) {
        $status = 'pending';
    }
    $stmt = ql_db()->prepare("
        INSERT INTO on_the_go_field_sync_ops
            (user_id, client_operation_id, tape_id, session_id, status, response_json, last_error)
        VALUES
            (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            tape_id = COALESCE(VALUES(tape_id), tape_id),
            session_id = COALESCE(VALUES(session_id), session_id),
            status = VALUES(status),
            response_json = VALUES(response_json),
            last_error = VALUES(last_error),
            updated_at = NOW()
    ");
    $stmt->execute([
        $userId,
        $clientOperationId,
        $tapeId,
        $sessionId,
        $status,
        $response ? ql_on_the_go_json_string($response) : null,
        $error !== '' ? $error : null,
    ]);
}

function ql_on_the_go_field_draft_save(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    ql_on_the_go_field_ensure_schema();
    $userId = (int)$user['id'];
    $streamType = ql_on_the_go_stream_type($input['stream_type'] ?? $input['session_type'] ?? 'cash');
    $clientDraftId = ql_on_the_go_client_token($input['client_draft_id'] ?? '', true, 'draft');
    $clientOperationId = ql_on_the_go_client_token($input['client_operation_id'] ?? '', false);
    $groupId = (int)($input['group_id'] ?? 0);
    $participantUserId = (int)($input['participant_user_id'] ?? $userId);
    if ($participantUserId <= 0) {
        $participantUserId = $userId;
    }
    $groupScope = null;

    if ($groupId > 0) {
        $groupScope = function_exists('ql_ledger_group_scope') ? ql_ledger_group_scope($groupId, $userId) : null;
        if (!$groupScope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }
        if (
            empty($groupScope['can_write_group_ledger'])
            && empty($groupScope['can_view_group_reports'])
            && empty($groupScope['is_admin'])
        ) {
            $participantUserId = $userId;
        }
    }

    $tapeId = (int)($input['tape_id'] ?? 0);
    if ($tapeId > 0) {
        $tape = ql_on_the_go_tape_get($tapeId, $userId);
        if (!$tape) {
            return ['ok' => false, 'error' => 'invalid_tape_id'];
        }
        $streamType = ql_on_the_go_tape_stream($tape);
        if ($groupId <= 0 && !empty($tape['group_id'])) {
            $groupId = (int)$tape['group_id'];
        }
    } else {
        $tapeId = ql_on_the_go_active_tape_id($userId, $groupId > 0 ? $groupId : null, $streamType);
        $tape = ql_on_the_go_tape_get($tapeId, $userId);
        if (!$tape) {
            return ['ok' => false, 'error' => 'invalid_tape_id'];
        }
    }

    $sessionType = $streamType === 'card' ? 'card' : 'cash';
    $sessionId = ql_on_the_go_active_session_id($userId, $tapeId, $sessionType);
    $rawNotes = (string)($input['raw_notes'] ?? $input['notes'] ?? '');
    $cashReceived = ql_otr_amount_or_null($input['cash_received'] ?? $input['before_amount'] ?? '');
    if ($cashReceived === null) {
        $cashReceived = round((float)($tape['cash_received'] ?? 0), 2);
    }
    if ($streamType === 'card') {
        $cashReceived = 0.0;
    }
    $syncState = (string)($input['sync_state'] ?? 'saved');
    if (!in_array($syncState, ['saved', 'pending', 'failed', 'retry_needed'], true)) {
        $syncState = 'saved';
    }
    $lastError = trim((string)($input['last_error'] ?? ''));

    $parsed = ql_on_the_go_parse_signed_notes($rawNotes, $streamType);

    $db = ql_db();
    $db->beginTransaction();
    try {
        if ($groupId > 0 || $cashReceived !== null) {
            $stmt = $db->prepare("
                UPDATE on_the_go_tapes
                SET group_id = COALESCE(?, group_id),
                    stream_type = ?,
                    cash_received = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $groupId > 0 ? $groupId : null,
                $streamType,
                number_format($cashReceived, 2, '.', ''),
                $tapeId,
                $userId,
            ]);
        }

        $stmt = $db->prepare("
            INSERT INTO on_the_go_field_drafts
                (user_id, group_id, participant_user_id, tape_id, session_id, stream_type, client_draft_id, draft_status, sync_state, raw_notes, parsed_rows_json, skipped_rows_json, cash_received, last_error, last_operation_id)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                group_id = VALUES(group_id),
                participant_user_id = VALUES(participant_user_id),
                tape_id = VALUES(tape_id),
                session_id = VALUES(session_id),
                stream_type = VALUES(stream_type),
                draft_status = 'active',
                sync_state = VALUES(sync_state),
                raw_notes = VALUES(raw_notes),
                parsed_rows_json = VALUES(parsed_rows_json),
                skipped_rows_json = VALUES(skipped_rows_json),
                cash_received = VALUES(cash_received),
                last_error = VALUES(last_error),
                last_operation_id = VALUES(last_operation_id),
                updated_at = NOW(),
                closed_at = NULL,
                submitted_at = NULL
        ");
        $stmt->execute([
            $userId,
            $groupId > 0 ? $groupId : null,
            $participantUserId,
            $tapeId,
            $sessionId,
            $streamType,
            $clientDraftId,
            $syncState,
            $rawNotes,
            ql_on_the_go_json_string($parsed['items']),
            ql_on_the_go_json_string($parsed['skipped']),
            number_format($cashReceived, 2, '.', ''),
            $lastError !== '' ? $lastError : null,
            $clientOperationId !== '' ? $clientOperationId : null,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'field_draft_save_failed', 'message' => $e->getMessage()];
    }

    $row = ql_on_the_go_field_draft_row_by_client($userId, $clientDraftId);
    $draft = $row ? ql_on_the_go_field_draft_public($row) : null;
    $tape = ql_on_the_go_tape_get($tapeId, $userId);
    if ($tape) {
        $tape['summary'] = ql_on_the_go_tape_summary($tapeId);
        $tape['card_summary'] = ql_on_the_go_card_summary($tapeId);
    }

    return [
        'ok' => true,
        'draft' => $draft,
        'tape' => $tape,
        'tape_id' => $tapeId,
        'session_id' => $sessionId,
        'proof_states' => $draft ? ql_on_the_go_upload_states_for($userId, (int)$draft['id']) : [],
    ];
}

function ql_on_the_go_field_recover(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    ql_on_the_go_field_ensure_schema();
    $userId = (int)$user['id'];
    $clientDraftId = ql_on_the_go_client_token($input['client_draft_id'] ?? '', false);
    $draftId = (int)($input['draft_id'] ?? $input['id'] ?? 0);
    $groupId = (int)($input['group_id'] ?? 0);
    $streamType = isset($input['stream_type']) ? ql_on_the_go_stream_type($input['stream_type']) : '';

    $row = null;
    if ($draftId > 0) {
        $row = ql_on_the_go_field_draft_row($draftId, $userId);
    } elseif ($clientDraftId !== '') {
        $row = ql_on_the_go_field_draft_row_by_client($userId, $clientDraftId);
    } else {
        $row = ql_on_the_go_field_latest_draft($userId, $groupId, $streamType);
    }

    if (!$row && !empty($input['ensure_open'])) {
        return ql_on_the_go_field_draft_save([
            'client_draft_id' => $clientDraftId,
            'group_id' => $groupId,
            'stream_type' => $streamType !== '' ? $streamType : 'cash',
            'notes' => '',
            'sync_state' => 'saved',
        ]);
    }

    if (!$row) {
        return ['ok' => true, 'draft' => null, 'proof_states' => []];
    }

    $draft = ql_on_the_go_field_draft_public($row);
    $tape = ql_on_the_go_tape_get((int)$draft['tape_id'], $userId);
    if ($tape) {
        $tape['summary'] = ql_on_the_go_tape_summary((int)$draft['tape_id']);
        $tape['card_summary'] = ql_on_the_go_card_summary((int)$draft['tape_id']);
        $tape['card_state'] = ql_on_the_go_card_state($tape, $tape['card_summary']);
    }

    return [
        'ok' => true,
        'draft' => $draft,
        'tape' => $tape,
        'tape_id' => (int)$draft['tape_id'],
        'session_id' => (int)$draft['session_id'],
        'proof_states' => ql_on_the_go_upload_states_for($userId, (int)$draft['id']),
    ];
}

function ql_on_the_go_proof_state_begin(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $clientUploadId = ql_on_the_go_client_token($input['client_upload_id'] ?? '', true, 'upload');
    $draftId = (int)($input['draft_id'] ?? 0);
    $clientDraftId = ql_on_the_go_client_token($input['client_draft_id'] ?? '', false);
    if ($draftId <= 0 && $clientDraftId !== '') {
        $draft = ql_on_the_go_field_draft_row_by_client($userId, $clientDraftId);
        $draftId = $draft ? (int)$draft['id'] : 0;
    }
    $captureId = (int)($input['capture_id'] ?? 0);

    $state = ql_on_the_go_upload_state_record($userId, $clientUploadId, [
        'draft_id' => $draftId,
        'capture_id' => $captureId,
        'status' => 'pending',
	        'original_name' => isset($input['original_name']) ? basename((string)$input['original_name']) : null,
	        'mime_type' => $input['mime_type'] ?? null,
	        'size_bytes' => (int)($input['size_bytes'] ?? 0),
	        'proof_role' => $input['proof_role'] ?? 'attachment',
	        'proof_bundle_id' => $input['proof_bundle_id'] ?? null,
	        'metadata_json' => $input['metadata_json'] ?? ($input['metadata'] ?? null),
	    ]);

    return ['ok' => true, 'proof_state' => $state];
}

function ql_on_the_go_proof_state_fail(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $clientUploadId = ql_on_the_go_client_token($input['client_upload_id'] ?? '', false);
    if ($clientUploadId === '') {
        return ['ok' => false, 'error' => 'invalid_client_upload_id'];
    }
    $status = (string)($input['status'] ?? 'retry_needed');
    if (!in_array($status, ['failed', 'retry_needed'], true)) {
        $status = 'retry_needed';
    }
    $state = ql_on_the_go_upload_state_record($userId, $clientUploadId, [
        'draft_id' => (int)($input['draft_id'] ?? 0),
        'capture_id' => (int)($input['capture_id'] ?? 0),
	        'status' => $status,
	        'proof_role' => $input['proof_role'] ?? null,
	        'proof_bundle_id' => $input['proof_bundle_id'] ?? null,
	        'last_error' => (string)($input['last_error'] ?? $input['error'] ?? 'upload_failed'),
        'increment_retry' => true,
    ]);

    return ['ok' => true, 'proof_state' => $state];
}

function ql_on_the_go_proof_state_list(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $draftId = (int)($input['draft_id'] ?? 0);
    $captureId = (int)($input['capture_id'] ?? 0);
    return [
        'ok' => true,
        'proof_states' => ql_on_the_go_upload_states_for($userId, $draftId > 0 ? $draftId : null, $captureId > 0 ? $captureId : null),
    ];
}

function ql_on_the_go_signed_sync(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $clientOperationId = ql_on_the_go_client_token($input['client_operation_id'] ?? '', false);
    if ($clientOperationId !== '') {
        $existingOperation = ql_on_the_go_field_operation_response($userId, $clientOperationId);
        if ($existingOperation) {
            return $existingOperation;
        }
        ql_on_the_go_field_operation_record($userId, $clientOperationId, 'pending');
    }
    $tapeId = (int)($input['tape_id'] ?? 0);
    $streamType = ql_on_the_go_stream_type($input['stream_type'] ?? $input['session_type'] ?? 'cash');
    $groupId = (int)($input['group_id'] ?? 0);
    if ($tapeId > 0) {
        $tape = ql_on_the_go_tape_get($tapeId, $userId);
        if (!$tape) {
            return ['ok' => false, 'error' => 'invalid_tape_id'];
        }
        $streamType = ql_on_the_go_tape_stream($tape);
    } else {
        $tapeId = ql_on_the_go_active_tape_id($userId, $groupId > 0 ? $groupId : null, $streamType);
        $tape = ql_on_the_go_tape_get($tapeId, $userId);
        if (!$tape) {
            return ['ok' => false, 'error' => 'invalid_tape_id'];
        }
        $streamType = ql_on_the_go_tape_stream($tape);
    }

    $cashReceived = ql_otr_amount_or_null($input['cash_received'] ?? $input['before_amount'] ?? '');
    if ($streamType === 'card') {
        $cashReceived = 0.0;
    }
    $replaceTape = !empty($input['replace_tape']);
    $startNext = !empty($input['start_next']);
    $isAdvanceTape = !empty($tape['advance_id']);
    if ($isAdvanceTape) {
        $cashReceived = round((float)($tape['cash_received'] ?? 0), 2);
        if ($groupId <= 0) {
            $groupId = (int)($tape['group_id'] ?? 0);
        }
    } elseif ($groupId <= 0 && !empty($tape['group_id'])) {
        $groupId = (int)$tape['group_id'];
    }
    if ($groupId > 0) {
        if (!function_exists('ql_ledger_group_scope') || !ql_ledger_group_scope($groupId, $userId)) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }
    }
    $pendingTransfer = ql_on_the_go_pending_transfer_block($tapeId, $userId);
    if ($pendingTransfer) {
        return [
            'ok' => false,
            'error' => $pendingTransfer['code'],
            'message' => $pendingTransfer['message'],
            'advance_id' => (int)($pendingTransfer['advance_id'] ?? 0),
            'transfer_id' => (int)($pendingTransfer['transfer_id'] ?? 0),
        ];
    }

    $existingSummary = ql_on_the_go_card_summary($tapeId);
    $existingState = ql_on_the_go_card_state($tape, $existingSummary);
    if (in_array($existingState, ['submitted', 'included'], true)) {
        return ['ok' => false, 'error' => 'card_locked_after_findesk'];
    }

    $parsed = ql_on_the_go_parse_signed_notes((string)($input['notes'] ?? ''), $streamType);
    $items = $parsed['items'];
    $db = ql_db();
    $created = [];
    $syncedCount = 0;
    $nextTapeId = 0;
    $sessionId = null;

    try {
        $db->beginTransaction();

        $sessionType = $streamType === 'card' ? 'card' : 'cash';
        $captureWhere = ql_on_the_go_capture_where_for_stream($streamType);
        $sessionId = ql_on_the_go_active_session_id($userId, $tapeId, $sessionType);

        if ($groupId > 0) {
            $groupStmt = $db->prepare("
                UPDATE on_the_go_tapes
                SET group_id = ?,
                    updated_at = updated_at
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");
            $groupStmt->execute([$groupId, $tapeId, $userId]);
        }

        if ($cashReceived !== null) {
            $cashStmt = $db->prepare("
                UPDATE on_the_go_tapes
                SET stream_type = ?,
                    cash_received = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");
            $cashStmt->execute([
                $streamType,
                number_format($cashReceived, 2, '.', ''),
                $tapeId,
                $userId,
            ]);
        }

        $insert = $db->prepare("
            INSERT INTO on_the_go_captures
                (user_id, tape_id, session_id, capture_type, amount, currency, description, review_status, reportable, recognition_status)
            VALUES
                (?, ?, ?, ?, ?, 'EUR', ?, 'needs_review', 0, 'none')
        ");

        if ($replaceTape) {
            $keyFor = static function (string $type, $amount, $description): string {
                return $type . "\t" . number_format(round((float)$amount, 2), 2, '.', '') . "\t" . trim((string)$description);
            };

            $existingStmt = $db->prepare("
                SELECT id, capture_type, amount, description
                FROM on_the_go_captures
                WHERE user_id = ?
                  AND tape_id = ?
                  AND (session_id = ? OR session_id IS NULL)
                  AND review_status <> 'archived'
                  AND {$captureWhere}
                ORDER BY created_at ASC, id ASC
            ");
            $existingStmt->execute([$userId, $tapeId, $sessionId]);

            $existingByKey = [];
            foreach ($existingStmt->fetchAll() as $row) {
                $key = $keyFor((string)$row['capture_type'], $row['amount'], $row['description'] ?? '');
                if (!isset($existingByKey[$key])) {
                    $existingByKey[$key] = [];
                }
                $existingByKey[$key][] = (int)$row['id'];
            }

            $reuse = $db->prepare("
                UPDATE on_the_go_captures
                SET session_id = ?,
                    review_status = 'needs_review',
                    reportable = 0,
                    amount = ?,
                    description = ?,
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
                  AND (session_id = ? OR session_id IS NULL)
                LIMIT 1
            ");

            foreach ($items as $item) {
                $description = $item['description'] !== '' ? $item['description'] : null;
                $key = $keyFor($item['capture_type'], $item['amount'], $description ?? '');
                $existingId = isset($existingByKey[$key]) ? (int)array_shift($existingByKey[$key]) : 0;

                if ($existingId > 0) {
                    $reuse->execute([
                        $sessionId,
                        $item['amount'],
                        $description,
                        $existingId,
                        $userId,
                        $sessionId,
                    ]);
                    $syncedCount++;
                    continue;
                }

                $insert->execute([
                    $userId,
                    $tapeId,
                    $sessionId,
                    $item['capture_type'],
                    $item['amount'],
                    $description,
                ]);
                $created[] = ql_on_the_go_get_one((int)$db->lastInsertId(), $userId);
                $syncedCount++;
            }

            $unusedIds = [];
            foreach ($existingByKey as $ids) {
                foreach ($ids as $id) {
                    if ((int)$id > 0) {
                        $unusedIds[] = (int)$id;
                    }
                }
            }

            if ($unusedIds) {
                $placeholders = implode(',', array_fill(0, count($unusedIds), '?'));
                $archive = $db->prepare("
                    UPDATE on_the_go_captures
                    SET review_status = 'archived',
                        reportable = 0,
                        updated_at = NOW()
                    WHERE user_id = ?
                      AND tape_id = ?
                      AND (session_id = ? OR session_id IS NULL)
                      AND id IN ({$placeholders})
                ");
                $archive->execute(array_merge([$userId, $tapeId, $sessionId], $unusedIds));
            }
        } else {
            $stmt = $db->prepare("
                UPDATE on_the_go_captures
                SET review_status = 'archived',
                    reportable = 0,
                    updated_at = NOW()
                WHERE user_id = ?
                  AND tape_id = ?
                  AND session_id = ?
                  AND review_status = 'needs_review'
                  AND {$captureWhere}
            ");
            $stmt->execute([$userId, $tapeId, $sessionId]);

            foreach ($items as $item) {
                $insert->execute([
                    $userId,
                    $tapeId,
                    $sessionId,
                    $item['capture_type'],
                    $item['amount'],
                    $item['description'] !== '' ? $item['description'] : null,
                ]);
                $created[] = ql_on_the_go_get_one((int)$db->lastInsertId(), $userId);
                $syncedCount++;
            }
        }

        $runningSummary = ql_on_the_go_tape_summary($tapeId);
        if ($startNext && (int)($runningSummary['records_count'] ?? 0) > 0) {
            $effectiveGroupId = $groupId;
            if ($effectiveGroupId <= 0) {
                $lookup = $db->prepare("SELECT group_id FROM on_the_go_tapes WHERE id = ? AND user_id = ? LIMIT 1");
                $lookup->execute([$tapeId, $userId]);
                $effectiveGroupId = (int)($lookup->fetchColumn() ?: 0);
            }

            $close = $db->prepare("
                UPDATE on_the_go_tapes
                SET status = 'closed',
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
                  AND submitted_at IS NULL
                LIMIT 1
            ");
            $close->execute([$tapeId, $userId]);

            if (!$isAdvanceTape) {
                $nextTapeId = ql_on_the_go_seed_next_tape(
                    $userId,
                    $effectiveGroupId,
                    $streamType === 'card' ? 0.0 : round((float)($runningSummary['cash_left'] ?? 0), 2),
                    $tapeId,
                    $streamType
                );
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($clientOperationId !== '') {
            ql_on_the_go_field_operation_record($userId, $clientOperationId, 'failed', $tapeId ?: null, null, null, $e->getMessage());
        }
        return ['ok' => false, 'error' => 'signed_sync_failed', 'message' => $e->getMessage()];
    }

    $tape = ql_on_the_go_tape_get($tapeId, $userId);
    if ($tape) {
        $tape['summary'] = ql_on_the_go_tape_summary($tapeId);
        $tape['card_summary'] = ql_on_the_go_card_summary($tapeId);
    }

    $nextTape = $nextTapeId > 0 ? ql_on_the_go_tape_get($nextTapeId, $userId) : null;
    if ($nextTape) {
        $nextTape['summary'] = ql_on_the_go_tape_summary($nextTapeId);
        $nextTape['card_summary'] = ql_on_the_go_card_summary($nextTapeId);
    }

    ql_on_the_go_journal_append('card_saved', $userId, $tapeId, [
        'synced_count' => $syncedCount,
        'created_count' => count($created),
        'stream_type' => $streamType,
        'start_next' => $startNext,
        'next_tape_id' => $nextTapeId ?: null,
        'skipped' => $parsed['skipped'],
    ]);

    $response = [
        'ok' => true,
        'tape_id' => $tapeId,
        'stream_type' => $streamType,
        'session_id' => $sessionId,
        'tape' => $tape,
        'next_tape_id' => $nextTapeId ?: null,
        'next_tape' => $nextTape,
        'items' => $created,
        'skipped' => $parsed['skipped'],
        'created_count' => count($created),
        'synced_count' => $syncedCount,
    ];
    if ($clientOperationId !== '') {
        $response['client_operation_id'] = $clientOperationId;
        ql_on_the_go_field_operation_record($userId, $clientOperationId, 'succeeded', $tapeId, $sessionId, $response);
    }

    return $response;
}

function ql_on_the_go_card_row(int $tapeId, int $viewerId): ?array
{
    if ($tapeId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT
            t.*,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM on_the_go_tapes t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.id = ?
          AND t.status <> 'archived'
        LIMIT 1
    ");
    $stmt->execute([$tapeId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if ((int)$row['user_id'] === $viewerId && empty($row['archived_at'])) {
        return $row;
    }

    $groupId = (int)($row['group_id'] ?? 0);
    if ($groupId <= 0 || !function_exists('ql_ledger_group_scope')) {
        return null;
    }

    $scope = ql_ledger_group_scope($groupId, $viewerId);
    if (!$scope) {
        return null;
    }

    if (!empty($scope['can_view_group_reports']) || !empty($scope['can_write_group_ledger']) || !empty($scope['is_admin'])) {
        return $row;
    }

    return null;
}

function ql_on_the_go_card_moderation_scope(array $card, int $viewerId): ?array
{
    $groupId = (int)($card['group_id'] ?? 0);
    if ($groupId <= 0 || !function_exists('ql_ledger_group_scope')) {
        return null;
    }

    $scope = ql_ledger_group_scope($groupId, $viewerId);
    if (!$scope) {
        return null;
    }

    if (!empty($scope['can_write_group_ledger']) || !empty($scope['can_view_group_reports']) || !empty($scope['is_admin'])) {
        return $scope;
    }

    return null;
}

function ql_on_the_go_card_can_moderate(array $card, int $viewerId): bool
{
    return ql_on_the_go_card_moderation_scope($card, $viewerId) !== null;
}

function ql_on_the_go_card_can_view_archive(array $card, int $viewerId): bool
{
    $scope = ql_on_the_go_card_moderation_scope($card, $viewerId);
    return $scope && !empty($scope['is_admin']);
}

function ql_on_the_go_card_can_manage(array $card, int $viewerId): bool
{
    if ((int)($card['user_id'] ?? 0) === $viewerId) {
        return true;
    }

    return ql_on_the_go_card_can_moderate($card, $viewerId);
}

function ql_on_the_go_seed_next_tape(int $userId, int $groupId, float $cashReceived, int $sourceTapeId = 0, string $streamType = 'cash'): int
{
    if ($userId <= 0) {
        return 0;
    }

    $streamType = ql_on_the_go_stream_type($streamType);
    if ($sourceTapeId > 0) {
        $sourceStmt = ql_db()->prepare("SELECT stream_type FROM on_the_go_tapes WHERE id = ? AND user_id = ? LIMIT 1");
        $sourceStmt->execute([$sourceTapeId, $userId]);
        $streamType = ql_on_the_go_stream_type($sourceStmt->fetchColumn() ?: $streamType);
    }
    if ($streamType === 'card') {
        $cashReceived = 0.0;
    }

    $db = ql_db();
    $stmt = $db->prepare("
        SELECT t.id
        FROM on_the_go_tapes t
        WHERE t.user_id = ?
          AND t.status = 'active'
          AND t.stream_type = ?
          AND t.submitted_at IS NULL
          AND t.id <> ?
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND NOT EXISTS (
            SELECT 1
            FROM on_the_go_captures c
            WHERE c.tape_id = t.id
              AND c.review_status <> 'archived'
            LIMIT 1
          )
        ORDER BY COALESCE(t.updated_at, t.created_at) DESC, t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $streamType, $sourceTapeId]);
    $nextId = (int)($stmt->fetchColumn() ?: 0);

    if ($nextId > 0) {
        $upd = $db->prepare("
            UPDATE on_the_go_tapes
            SET group_id = ?,
                stream_type = ?,
                cash_received = ?,
                currency = 'EUR',
                updated_at = NOW()
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $upd->execute([
            $groupId > 0 ? $groupId : null,
            $streamType,
            number_format($cashReceived, 2, '.', ''),
            $nextId,
            $userId,
        ]);

        return $nextId;
    }

    $ins = $db->prepare("
        INSERT INTO on_the_go_tapes
            (user_id, group_id, stream_type, title, cash_received, currency, status, updated_at)
        VALUES
            (?, ?, ?, ?, ?, 'EUR', 'active', NOW())
    ");
    $ins->execute([
        $userId,
        $groupId > 0 ? $groupId : null,
        $streamType,
        $streamType === 'card' ? 'Живой отчет: карта' : 'On the Go',
        number_format($cashReceived, 2, '.', ''),
    ]);

    return (int)$db->lastInsertId();
}

function ql_on_the_go_previous_card_after_amount(int $userId, int $groupId, int $sourceTapeId): ?float
{
    if ($userId <= 0 || $groupId <= 0 || $sourceTapeId <= 0) {
        return null;
    }

    $stmt = ql_db()->prepare("
        SELECT t.*
        FROM on_the_go_tapes t
        WHERE t.user_id = ?
          AND t.group_id = ?
          AND t.status <> 'archived'
          AND t.stream_type = 'cash'
          AND t.submitted_at IS NOT NULL
          AND t.id < ?
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $groupId, $sourceTapeId]);
    $previous = $stmt->fetch();
    if (!$previous) {
        return null;
    }

    $summary = ql_on_the_go_card_summary((int)$previous['id']);
    return round((float)($summary['after_amount'] ?? 0), 2);
}

function ql_on_the_go_previous_fixed_card_info(int $userId, int $groupId, int $sourceTapeId, string $streamType): ?array
{
    if ($userId <= 0 || $sourceTapeId <= 0) {
        return null;
    }

    $scopeGroupId = $groupId > 0 ? $groupId : 0;
    $stmt = ql_db()->prepare("
        SELECT t.id, t.submitted_at
        FROM on_the_go_tapes t
        WHERE t.user_id = ?
          AND COALESCE(t.group_id, 0) = ?
          AND t.status <> 'archived'
          AND t.stream_type = ?
          AND t.submitted_at IS NOT NULL
          AND t.id < ?
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $stmt->execute([
        $userId,
        $scopeGroupId,
        ql_on_the_go_stream_type($streamType),
        $sourceTapeId,
    ]);
    $previous = $stmt->fetch();
    if (!$previous) {
        return null;
    }

    $summary = ql_on_the_go_card_summary((int)$previous['id']);

    return [
        'tape_id' => (int)$previous['id'],
        'amount' => round((float)($summary['after_amount'] ?? 0), 2),
        'submitted_at' => (string)($previous['submitted_at'] ?? ''),
    ];
}

function ql_on_the_go_apply_sequential_base(array $card, int $groupId): array
{
    if ((int)($card['advance_id'] ?? 0) > 0) {
        return $card;
    }
    if (ql_on_the_go_tape_stream($card) === 'card') {
        if (round((float)($card['cash_received'] ?? 0), 2) !== 0.0) {
            $stmt = ql_db()->prepare("
                UPDATE on_the_go_tapes
                SET cash_received = 0,
                    updated_at = NOW()
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([(int)$card['id'], (int)$card['user_id']]);
            $card['cash_received'] = '0.00';
            $card['updated_at'] = date('Y-m-d H:i:s');
        }
        return $card;
    }

    $expectedBase = ql_on_the_go_previous_card_after_amount(
        (int)$card['user_id'],
        $groupId,
        (int)$card['id']
    );
    if ($expectedBase === null) {
        return $card;
    }

    $currentBase = round((float)($card['cash_received'] ?? 0), 2);
    if (abs($currentBase - $expectedBase) < 0.005) {
        return $card;
    }

    $stmt = ql_db()->prepare("
        UPDATE on_the_go_tapes
        SET cash_received = ?,
            updated_at = NOW()
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $stmt->execute([
        number_format($expectedBase, 2, '.', ''),
        (int)$card['id'],
        (int)$card['user_id'],
    ]);

    $card['cash_received'] = number_format($expectedBase, 2, '.', '');
    $card['updated_at'] = date('Y-m-d H:i:s');
    return $card;
}

function ql_on_the_go_card_submission_block(array $card, array $summary, ?int $groupIdOverride = null): ?array
{
    $cardId = (int)($card['id'] ?? 0);
    $ownerId = (int)($card['user_id'] ?? 0);
    $groupId = (int)($groupIdOverride ?? ($card['group_id'] ?? 0));

    if ($cardId <= 0 || $ownerId <= 0 || $groupId <= 0 || (int)($card['advance_id'] ?? 0) > 0) {
        return null;
    }

    $streamType = ql_on_the_go_tape_stream($card);
    if ($streamType === 'card') {
        return null;
    }

    if (ql_on_the_go_card_state($card, $summary) !== 'draft') {
        return null;
    }

    $pendingStmt = ql_db()->prepare("
        SELECT t.*
        FROM on_the_go_tapes t
        WHERE t.user_id = ?
          AND t.group_id = ?
          AND t.id <> ?
          AND t.status <> 'archived'
          AND t.stream_type = ?
          AND t.submitted_at IS NOT NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND NOT EXISTS (
              SELECT 1
              FROM on_the_go_captures rc
              WHERE rc.tape_id = t.id
                AND rc.reportable = 1
                AND rc.review_status <> 'archived'
              LIMIT 1
          )
          AND EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.review_status <> 'archived'
              LIMIT 1
          )
        ORDER BY t.id ASC
        LIMIT 1
    ");
    $pendingStmt->execute([$ownerId, $groupId, $cardId, $streamType]);
    $pending = $pendingStmt->fetch();
    if ($pending) {
        return [
            'code' => (int)$pending['id'] < $cardId ? 'previous_live_report_waits_findesk' : 'another_live_report_waits_findesk',
            'message' => 'Обработайте предыдущую запись в FinDesk.',
            'blocking_card_id' => (int)$pending['id'],
        ];
    }

    $draftStmt = ql_db()->prepare("
        SELECT t.*
        FROM on_the_go_tapes t
        WHERE t.user_id = ?
          AND t.group_id = ?
          AND t.id < ?
          AND t.status <> 'archived'
          AND t.stream_type = ?
          AND t.submitted_at IS NULL
          AND (t.advance_id IS NULL OR t.advance_id = 0)
          AND EXISTS (
              SELECT 1
              FROM on_the_go_captures c
              WHERE c.tape_id = t.id
                AND c.review_status <> 'archived'
              LIMIT 1
          )
        ORDER BY t.id ASC
        LIMIT 1
    ");
    $draftStmt->execute([$ownerId, $groupId, $cardId, $streamType]);
    $draft = $draftStmt->fetch();
    if ($draft) {
        return [
            'code' => 'previous_live_report_must_be_sent_first',
            'message' => 'Проверьте предыдущую запись: сдайте, очистите или удалите ее.',
            'blocking_card_id' => (int)$draft['id'],
        ];
    }

    return null;
}

function ql_on_the_go_card_return_requested_at(int $tapeId): string
{
    if ($tapeId <= 0) {
        return '';
    }

    $stmt = ql_db()->prepare("
        SELECT created_at
        FROM audit_log
        WHERE action = 'on_the_go_card_return_requested'
          AND entity_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$tapeId]);
    return (string)($stmt->fetchColumn() ?: '');
}

function ql_on_the_go_card_public(array $card, int $viewerId): array
{
    $summary = ql_on_the_go_card_summary((int)$card['id']);
    $sessionSnapshot = ql_on_the_go_tape_primary_session_snapshot((int)$card['id'], ql_on_the_go_tape_stream($card));
    $lastFixed = ql_on_the_go_previous_fixed_card_info(
        (int)($card['user_id'] ?? 0),
        isset($card['group_id']) ? (int)$card['group_id'] : 0,
        (int)$card['id'],
        ql_on_the_go_tape_stream($card)
    );
    $state = ql_on_the_go_card_state($card, $summary);
    $canManage = ql_on_the_go_card_can_manage($card, $viewerId);
    $canModerate = ql_on_the_go_card_can_moderate($card, $viewerId);
    $canViewArchive = ql_on_the_go_card_can_view_archive($card, $viewerId);
    $isOwner = (int)($card['user_id'] ?? 0) === $viewerId;
    $isLocked = in_array($state, ['submitted', 'included'], true);
    $isUiArchived = !empty($card['archived_at']);
    $isPersonalCard = (int)($card['group_id'] ?? 0) <= 0;
    $canSelfReturn = $isOwner && $isLocked && !$isUiArchived && ($isPersonalCard || $state === 'submitted');
    $submissionBlock = ql_on_the_go_card_submission_block($card, $summary);
    $canSubmit = $canManage
        && $state === 'draft'
        && (int)($summary['records_count'] ?? 0) > 0
        && $submissionBlock === null;
    $returnRequestedAt = ql_on_the_go_card_return_requested_at((int)$card['id']);
    $advanceFlags = [
        'transfer_pending' => false,
        'transfer_active' => false,
        'transfer_state' => '',
        'transfer_pending_meta' => null,
    ];
    if ((int)($card['advance_id'] ?? 0) > 0 && function_exists('ql_advance_row') && function_exists('ql_advance_transfer_public_flags')) {
        $advance = ql_advance_row((int)$card['advance_id']);
        if ($advance) {
            $advanceFlags = ql_advance_transfer_public_flags($advance);
        }
    }

    $isTransferPending = !empty($advanceFlags['transfer_pending']);

    return array_merge([
        'id' => (int)$card['id'],
        'tape_id' => (int)$card['id'],
        'user_id' => (int)$card['user_id'],
        'group_id' => isset($card['group_id']) ? (int)$card['group_id'] : null,
        'advance_id' => isset($card['advance_id']) ? (int)$card['advance_id'] : null,
        'stream_type' => ql_on_the_go_tape_stream($card),
        'title' => $card['title'] ?? 'On the Go',
        'cash_received' => isset($card['cash_received']) ? round((float)$card['cash_received'], 2) : 0.0,
        'currency' => $card['currency'] ?? 'EUR',
        'status' => $card['status'] ?? 'active',
        'card_state' => $state,
        'submitted' => in_array($state, ['submitted', 'included'], true),
        'included' => $state === 'included',
        'archived_at' => $card['archived_at'] ?? '',
        'ui_archived' => $isUiArchived,
        'created_at' => $card['created_at'] ?? '',
        'updated_at' => $card['updated_at'] ?? '',
        'closed_at' => $card['closed_at'] ?? '',
        'submitted_at' => $card['submitted_at'] ?? '',
        'actual_remaining' => $card['actual_remaining'] !== null ? round((float)$card['actual_remaining'], 2) : null,
        'difference_amount' => $card['difference_amount'] !== null ? round((float)$card['difference_amount'], 2) : null,
        'last_fixed_amount' => $lastFixed['amount'] ?? null,
        'last_fixed_at' => $lastFixed['submitted_at'] ?? '',
        'user_display_name' => $card['user_display_name'] ?? $card['email'] ?? 'Участник',
        'email' => $card['email'] ?? '',
        'summary' => $summary,
        'session_snapshot' => $sessionSnapshot,
        'viewer_is_owner' => $isOwner,
        'can_moderate' => $canModerate,
        'can_edit' => $canManage && !$isLocked && !$isTransferPending,
        'can_toggle' => $canManage,
        'can_delete' => $canManage && !$isTransferPending,
        'can_submit' => $canSubmit,
        'can_return' => ($canModerate || $canSelfReturn) && $isLocked,
        'can_request_return' => $isOwner && !$canModerate && !$canSelfReturn && $isLocked && !$isUiArchived && $returnRequestedAt === '',
        'return_requested_at' => $returnRequestedAt,
        'can_archive_completed' => $state === 'included' && !$isUiArchived && !$returnRequestedAt && ($isOwner || $canViewArchive),
        'can_view_archive' => $canViewArchive,
        'submit_block_reason' => $submissionBlock['code'] ?? '',
        'submit_block_message' => $submissionBlock['message'] ?? '',
        'submit_blocking_card_id' => $submissionBlock['blocking_card_id'] ?? null,
    ], $advanceFlags);
}

function ql_on_the_go_tape_primary_session_snapshot(int $tapeId, string $preferredSessionType = ''): ?array
{
    if ($tapeId <= 0) {
        return null;
    }

    $preferredSessionType = in_array($preferredSessionType, ['cash', 'card'], true) ? $preferredSessionType : '';
    $cacheKey = $tapeId . ':' . ($preferredSessionType !== '' ? $preferredSessionType : '*');
    static $cache = [];
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = ql_db()->prepare("
        SELECT
            s.id,
            s.tape_id,
            s.session_type,
            s.status,
            s.started_at,
            s.closed_at,
            s.archived_at,
            s.created_at,
            s.updated_at,
            COALESCE(SUM(CASE
                WHEN c.review_status <> 'archived'
                 AND c.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
                THEN c.amount ELSE 0 END), 0) AS amount_total,
            SUM(CASE
                WHEN c.review_status <> 'archived'
                 AND c.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
                THEN 1 ELSE 0 END) AS records_total,
            SUM(CASE
                WHEN c.review_status = 'needs_review'
                 AND c.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
                THEN 1 ELSE 0 END) AS pending_total
        FROM on_the_go_sessions s
        LEFT JOIN on_the_go_captures c ON c.session_id = s.id
        WHERE s.tape_id = ?
        GROUP BY s.id
        ORDER BY
            CASE
                WHEN ? <> '' AND s.session_type = ? THEN 0
                WHEN ? <> '' THEN 1
                ELSE 0
            END,
            CASE WHEN EXISTS (
                SELECT 1
                FROM on_the_go_captures c2
                WHERE c2.session_id = s.id
                  AND c2.review_status <> 'archived'
                  AND c2.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
                LIMIT 1
            ) THEN 0 ELSE 1 END,
            CASE s.status WHEN 'active' THEN 0 WHEN 'closed' THEN 1 ELSE 2 END,
            COALESCE(s.closed_at, s.started_at, s.created_at) DESC,
            s.id DESC
        LIMIT 1
    ");
    $stmt->execute([$tapeId, $preferredSessionType, $preferredSessionType, $preferredSessionType]);
    $row = $stmt->fetch();
    if (!$row) {
        $cache[$cacheKey] = null;
        return null;
    }

    $cache[$cacheKey] = [
        'id' => (int)($row['id'] ?? 0),
        'tape_id' => (int)($row['tape_id'] ?? 0),
        'session_type' => (string)($row['session_type'] ?? 'cash'),
        'status' => (string)($row['status'] ?? 'active'),
        'selection_mode' => 'primary_meaningful',
        'started_at' => (string)($row['started_at'] ?? ''),
        'closed_at' => (string)($row['closed_at'] ?? ''),
        'archived_at' => (string)($row['archived_at'] ?? ''),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'amount_total' => round((float)($row['amount_total'] ?? 0), 2),
        'records_total' => (int)($row['records_total'] ?? 0),
        'pending_total' => (int)($row['pending_total'] ?? 0),
    ];
    return $cache[$cacheKey];
}

function ql_on_the_go_archive_legacy_ledger_rows(int $tapeId, int $ownerUserId): int
{
    if ($tapeId <= 0 || $ownerUserId <= 0) {
        return 0;
    }

    $stmt = ql_db()->prepare("
        SELECT details, created_at
        FROM audit_log
        WHERE action = 'on_the_go_report_submitted'
          AND entity_id = ?
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute([$tapeId]);

    $update = ql_db()->prepare("
        UPDATE ledger_entries
        SET deleted_at = COALESCE(deleted_at, NOW()),
            updated_at = NOW()
        WHERE user_id = ?
          AND group_id = ?
          AND deleted_at IS NULL
          AND note LIKE 'From On the Go:%'
          AND created_at BETWEEN DATE_SUB(?, INTERVAL 10 SECOND) AND DATE_ADD(?, INTERVAL 10 SECOND)
    ");

    $archived = 0;
    foreach ($stmt->fetchAll() as $row) {
        $details = json_decode((string)($row['details'] ?? ''), true);
        if (!is_array($details)) {
            $details = [];
        }

        $groupId = (int)($details['group_id'] ?? 0);
        if ($groupId <= 0) {
            continue;
        }

        $createdAt = (string)($row['created_at'] ?? '');
        if ($createdAt === '') {
            continue;
        }

        $update->execute([$ownerUserId, $groupId, $createdAt, $createdAt]);
        $archived += $update->rowCount();
    }

    return $archived;
}

function ql_on_the_go_active_legacy_ledger_rows(int $tapeId, int $ownerUserId): int
{
    if ($tapeId <= 0 || $ownerUserId <= 0) {
        return 0;
    }

    $stmt = ql_db()->prepare("
        SELECT details, created_at
        FROM audit_log
        WHERE action = 'on_the_go_report_submitted'
          AND entity_id = ?
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute([$tapeId]);

    $count = ql_db()->prepare("
        SELECT COUNT(*)
        FROM ledger_entries
        WHERE user_id = ?
          AND group_id = ?
          AND deleted_at IS NULL
          AND note LIKE 'From On the Go:%'
          AND created_at BETWEEN DATE_SUB(?, INTERVAL 10 SECOND) AND DATE_ADD(?, INTERVAL 10 SECOND)
    ");

    $total = 0;
    foreach ($stmt->fetchAll() as $row) {
        $details = json_decode((string)($row['details'] ?? ''), true);
        if (!is_array($details)) {
            $details = [];
        }

        $groupId = (int)($details['group_id'] ?? 0);
        $createdAt = (string)($row['created_at'] ?? '');
        if ($groupId <= 0 || $createdAt === '') {
            continue;
        }

        $count->execute([$ownerUserId, $groupId, $createdAt, $createdAt]);
        $total += (int)($count->fetchColumn() ?: 0);
    }

    return $total;
}

function ql_on_the_go_submitted_card_totals(int $groupId, ?int $ownerUserId = null, bool $includedOnly = true): array
{
    if ($groupId <= 0) {
        return [
            'cash_income' => 0.0,
            'cash_expense' => 0.0,
            'noncash_expense' => 0.0,
            'submitted_cash_expense' => 0.0,
            'submitted_noncash_expense' => 0.0,
            'included_cash_expense' => 0.0,
            'included_noncash_expense' => 0.0,
            'balance_delta' => 0.0,
            'cash_delta' => 0.0,
            'card_delta' => 0.0,
            'records' => 0,
            'cards' => 0,
            'submitted_cards' => 0,
            'submitted_records' => 0,
            'included_cards' => 0,
            'included_records' => 0,
            'latest' => null,
        ];
    }

    $where = "t.group_id = ? AND t.status <> 'archived' AND (t.advance_id IS NULL OR t.advance_id = 0)";
    $params = [$groupId];

    if ($includedOnly) {
        $where .= " AND EXISTS (
            SELECT 1
            FROM on_the_go_captures rc
            WHERE rc.tape_id = t.id
              AND rc.reportable = 1
              AND rc.review_status <> 'archived'
            LIMIT 1
        )";
    } else {
        $where .= " AND t.submitted_at IS NOT NULL";
    }
    if ($ownerUserId !== null && $ownerUserId > 0) {
        $where .= " AND t.user_id = ?";
        $params[] = $ownerUserId;
    }

    $stmt = ql_db()->prepare("
        SELECT
            t.id,
            t.user_id,
            t.group_id,
            t.stream_type,
            t.title,
            t.submitted_at,
            t.created_at,
            t.updated_at,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM on_the_go_tapes t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE {$where}
        ORDER BY t.submitted_at DESC, t.id DESC
        LIMIT 200
    ");
    $stmt->execute($params);

    $totals = [
        'cash_income' => 0.0,
        'cash_expense' => 0.0,
        'noncash_expense' => 0.0,
        'submitted_cash_expense' => 0.0,
        'submitted_noncash_expense' => 0.0,
        'included_cash_expense' => 0.0,
        'included_noncash_expense' => 0.0,
        'balance_delta' => 0.0,
        'cash_delta' => 0.0,
        'card_delta' => 0.0,
        'records' => 0,
        'cards' => 0,
        'submitted_cards' => 0,
        'submitted_records' => 0,
        'included_cards' => 0,
        'included_records' => 0,
        'latest' => null,
    ];

    foreach ($stmt->fetchAll() as $card) {
        if (ql_on_the_go_active_legacy_ledger_rows((int)$card['id'], (int)$card['user_id']) > 0) {
            continue;
        }

        $summary = ql_on_the_go_card_summary((int)$card['id']);
        $records = (int)($summary['records_count'] ?? 0);
        if ($records <= 0) {
            continue;
        }

        $delta = round((float)($summary['delta'] ?? 0), 2);
        $cashDelta = round((float)($summary['cash_delta'] ?? (($summary['extra_cash_in'] ?? 0) - ($summary['cash_out'] ?? 0))), 2);
        $cardDelta = round((float)($summary['card_delta'] ?? (0 - ($summary['card_out'] ?? 0))), 2);
        $totals['cash_income'] += (float)($summary['extra_cash_in'] ?? 0);
        $totals['cash_expense'] += (float)($summary['cash_out'] ?? 0);
        $totals['noncash_expense'] += (float)($summary['card_out'] ?? 0);
        $totals['balance_delta'] += $delta;
        $totals['cash_delta'] += $cashDelta;
        $totals['card_delta'] += $cardDelta;
        $totals['records'] += $records;
        $totals['cards'] += 1;

        if ((int)($summary['reportable_count'] ?? 0) > 0) {
            $totals['included_cards'] += 1;
            $totals['included_records'] += $records;
            $totals['included_cash_expense'] += (float)($summary['cash_out'] ?? 0);
            $totals['included_noncash_expense'] += (float)($summary['card_out'] ?? 0);
        } else {
            $totals['submitted_cards'] += 1;
            $totals['submitted_records'] += $records;
            $totals['submitted_cash_expense'] += (float)($summary['cash_out'] ?? 0);
            $totals['submitted_noncash_expense'] += (float)($summary['card_out'] ?? 0);
        }

        if ($totals['latest'] === null) {
            $totals['latest'] = [
                'id' => (int)$card['id'],
                'user_id' => (int)$card['user_id'],
                'group_id' => (int)$card['group_id'],
                'stream_type' => ql_on_the_go_stream_type($card['stream_type'] ?? 'cash'),
                'title' => $card['title'] ?? 'On the Go',
                'submitted_at' => $card['submitted_at'],
                'created_at' => $card['created_at'],
                'updated_at' => $card['updated_at'],
                'user_display_name' => $card['user_display_name'] ?? $card['email'] ?? 'Участник',
                'email' => $card['email'] ?? '',
                'summary' => $summary,
            ];
        }
    }

    foreach ([
        'cash_income',
        'cash_expense',
        'noncash_expense',
        'submitted_cash_expense',
        'submitted_noncash_expense',
        'included_cash_expense',
        'included_noncash_expense',
        'balance_delta',
        'cash_delta',
        'card_delta',
    ] as $key) {
        $totals[$key] = round((float)$totals[$key], 2);
    }

    return $totals;
}

function ql_on_the_go_card_list(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $limit = (int)($input['limit'] ?? 60);
    if ($limit < 1 || $limit > 200) {
        $limit = 60;
    }

    $submittedOnly = !empty($input['submitted_only']);
    $includeEmpty = !empty($input['include_empty']);
    $archivedOnly = !empty($input['archived_only']);
    $includeArchived = !empty($input['include_archived']);
    $excludeAdvances = !empty($input['exclude_advances']);
    $streamFilter = isset($input['stream_type']) && in_array((string)$input['stream_type'], ['cash', 'card'], true)
        ? ql_on_the_go_stream_type($input['stream_type'])
        : '';
    $params = [];
    $scope = null;

    if ($groupId > 0) {
        if (!function_exists('ql_ledger_group_scope')) {
            return ['ok' => false, 'error' => 'group_scope_unavailable'];
        }

        $scope = ql_ledger_group_scope($groupId, $userId);
        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        $ownArchiveOnly = $archivedOnly && empty($scope['is_admin']);
        if (!$ownArchiveOnly && (!empty($scope['can_view_group_reports']) || !empty($scope['can_write_group_ledger']) || !empty($scope['is_admin']))) {
            $where = "t.group_id = ?";
            $params[] = $groupId;
        } else {
            $where = "t.user_id = ? AND (t.group_id = ? OR t.group_id IS NULL)";
            $params[] = $userId;
            $params[] = $groupId;
        }
    } else {
        $where = "t.user_id = ?";
        $params[] = $userId;
    }

    if ($archivedOnly) {
        $where .= " AND t.archived_at IS NOT NULL";
    } elseif (!$includeArchived) {
        $where .= " AND t.archived_at IS NULL";
    }

    if ($submittedOnly) {
        $where .= " AND (t.submitted_at IS NOT NULL OR EXISTS (
            SELECT 1 FROM on_the_go_captures c
            WHERE c.tape_id = t.id
              AND c.reportable = 1
              AND c.review_status <> 'archived'
            LIMIT 1
        ))";
    }

    if ($streamFilter !== '') {
        $where .= " AND t.stream_type = ?";
        $params[] = $streamFilter;
    }

    if ($excludeAdvances) {
        $where .= " AND (t.advance_id IS NULL OR t.advance_id = 0)";
    }

    $stmt = ql_db()->prepare("
        SELECT
            t.*,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM on_the_go_tapes t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE {$where}
          AND t.status <> 'archived'
        ORDER BY
            COALESCE(t.submitted_at, t.created_at) DESC,
            t.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);

    $cards = [];
    foreach ($stmt->fetchAll() as $row) {
        $public = ql_on_the_go_card_public($row, $userId);
        if (!$includeEmpty && ($public['card_state'] ?? '') === 'empty') {
            continue;
        }
        $cards[] = $public;
    }

    return [
        'ok' => true,
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'personal',
            'group_id' => $groupId ?: null,
            'can_view_group_reports' => $scope ? !empty($scope['can_view_group_reports']) : false,
            'can_view_archive' => $scope ? !empty($scope['is_admin']) : false,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'cards' => $cards,
    ];
}

function ql_on_the_go_card_detail(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }

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
            (
                SELECT COUNT(*)
                FROM on_the_go_files f
                WHERE f.capture_id = c.id
            ) AS files_count
        FROM on_the_go_captures c
        WHERE c.tape_id = ?
          AND c.review_status <> 'archived'
          AND c.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
        ORDER BY c.created_at ASC, c.id ASC
    ");
    $stmt->execute([(int)$card['id']]);

    return [
        'ok' => true,
        'card' => ql_on_the_go_card_public($card, $userId),
        'items' => $stmt->fetchAll(),
    ];
}

function ql_on_the_go_card_submit(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $groupId = (int)($input['group_id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }

    if ($groupId <= 0) {
        $groupId = (int)($card['group_id'] ?? 0);
    }
    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }
    if (!function_exists('ql_ledger_group_scope') || !ql_ledger_group_scope($groupId, $userId)) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (!ql_on_the_go_card_can_manage($card, $userId) && (int)$card['user_id'] !== $userId) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $summary = ql_on_the_go_card_summary((int)$card['id']);
    if ((int)($summary['records_count'] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'empty_report'];
    }
    $submitCard = $card;
    $submitCard['group_id'] = $groupId;
    $submissionBlock = ql_on_the_go_card_submission_block($submitCard, $summary, $groupId);
    if ($submissionBlock !== null) {
        return [
            'ok' => false,
            'error' => $submissionBlock['code'],
            'message' => $submissionBlock['message'],
            'blocking_card_id' => $submissionBlock['blocking_card_id'],
        ];
    }

    $db = ql_db();
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE on_the_go_captures
            SET reportable = 0,
                updated_at = NOW()
            WHERE tape_id = ?
              AND review_status <> 'archived'
        ");
        $stmt->execute([(int)$card['id']]);

        $stmt = $db->prepare("
            UPDATE on_the_go_tapes
            SET group_id = ?,
                submitted_at = NOW(),
                actual_remaining = ?,
                difference_amount = ?,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $groupId,
            number_format((float)$summary['after_amount'], 2, '.', ''),
            number_format((float)$summary['delta'], 2, '.', ''),
            (int)$card['id'],
        ]);

        $nextTapeId = ql_on_the_go_seed_next_tape(
            (int)$card['user_id'],
            $groupId,
            round((float)$summary['after_amount'], 2),
            (int)$card['id']
        );

        $legacyArchived = ql_on_the_go_archive_legacy_ledger_rows((int)$card['id'], (int)$card['user_id']);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'card_submit_failed', 'message' => $e->getMessage()];
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_card_submitted', 'on_the_go_tape', (int)$card['id'], [
            'group_id' => $groupId,
            'summary' => $summary,
            'legacy_ledger_archived' => $legacyArchived ?? 0,
            'next_tape_id' => $nextTapeId ?? 0,
        ]);
    }
    ql_on_the_go_journal_append('card_submitted', $userId, (int)$card['id'], [
        'group_id' => $groupId,
        'next_tape_id' => $nextTapeId ?? 0,
    ]);

    $detail = ql_on_the_go_card_detail(['id' => (int)$card['id']]);
    if (!empty($detail['ok'])) {
        $detail['next_tape_id'] = $nextTapeId ?? 0;
    }
    return $detail;
}

function ql_on_the_go_card_include(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $groupId = (int)($input['group_id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }

    if ($groupId <= 0) {
        $groupId = (int)($card['group_id'] ?? 0);
    }
    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    if (!function_exists('ql_ledger_group_scope')) {
        return ['ok' => false, 'error' => 'group_scope_unavailable'];
    }
    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (empty($scope['can_write_group_ledger']) && empty($scope['can_view_group_reports']) && empty($scope['is_admin'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $summary = ql_on_the_go_card_summary((int)$card['id']);
    if ((int)($summary['records_count'] ?? 0) <= 0) {
        return ['ok' => false, 'error' => 'empty_report'];
    }

    $db = ql_db();
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE on_the_go_captures
            SET reportable = 1,
                updated_at = NOW()
            WHERE tape_id = ?
              AND review_status <> 'archived'
        ");
        $stmt->execute([(int)$card['id']]);

        $stmt = $db->prepare("
            UPDATE on_the_go_tapes
            SET group_id = ?,
                submitted_at = COALESCE(submitted_at, NOW()),
                actual_remaining = ?,
                difference_amount = ?,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([
            $groupId,
            number_format((float)$summary['after_amount'], 2, '.', ''),
            number_format((float)$summary['delta'], 2, '.', ''),
            (int)$card['id'],
        ]);

        $nextTapeId = ql_on_the_go_seed_next_tape(
            (int)$card['user_id'],
            $groupId,
            round((float)$summary['after_amount'], 2),
            (int)$card['id']
        );

        $legacyArchived = ql_on_the_go_archive_legacy_ledger_rows((int)$card['id'], (int)$card['user_id']);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'card_include_failed', 'message' => $e->getMessage()];
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_card_included', 'on_the_go_tape', (int)$card['id'], [
            'group_id' => $groupId,
            'summary' => $summary,
            'legacy_ledger_archived' => $legacyArchived ?? 0,
            'next_tape_id' => $nextTapeId ?? 0,
        ]);
    }
    ql_on_the_go_journal_append('card_included', $userId, (int)$card['id'], [
        'group_id' => $groupId,
        'next_tape_id' => $nextTapeId ?? 0,
    ]);

    $detail = ql_on_the_go_card_detail(['id' => (int)$card['id']]);
    if (!empty($detail['ok'])) {
        $detail['next_tape_id'] = $nextTapeId ?? 0;
    }
    return $detail;
}

function ql_on_the_go_card_uninclude(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }
    $isOwner = (int)($card['user_id'] ?? 0) === $userId;
    $isPersonalCard = (int)($card['group_id'] ?? 0) <= 0;
    if (!ql_on_the_go_card_can_moderate($card, $userId) && !($isOwner && $isPersonalCard)) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    $db = ql_db();
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE on_the_go_captures
            SET reportable = 0,
                updated_at = NOW()
            WHERE tape_id = ?
              AND review_status <> 'archived'
        ");
        $stmt->execute([(int)$card['id']]);

        $stmt = $db->prepare("
            UPDATE on_the_go_tapes
            SET submitted_at = COALESCE(submitted_at, NOW()),
                archived_at = NULL,
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$card['id']]);

        $legacyArchived = ql_on_the_go_archive_legacy_ledger_rows((int)$card['id'], (int)$card['user_id']);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'card_uninclude_failed', 'message' => $e->getMessage()];
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_card_unincluded', 'on_the_go_tape', (int)$card['id'], [
            'group_id' => (int)($card['group_id'] ?? 0),
            'legacy_ledger_archived' => $legacyArchived ?? 0,
        ]);
    }
    ql_on_the_go_journal_append('card_unincluded', $userId, (int)$card['id'], [
        'group_id' => (int)($card['group_id'] ?? 0),
    ]);

    return ql_on_the_go_card_detail(['id' => (int)$card['id']]);
}

function ql_on_the_go_card_unsubmit(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }
    $summary = ql_on_the_go_card_summary((int)$card['id']);
    $state = ql_on_the_go_card_state($card, $summary);
    $isOwner = (int)($card['user_id'] ?? 0) === $userId;
    $canOwnerSelfReturn = $isOwner && $state === 'submitted';
    if (!ql_on_the_go_card_can_moderate($card, $userId) && !$canOwnerSelfReturn) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    $db = ql_db();
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE on_the_go_captures
            SET reportable = 0,
                review_status = 'needs_review',
                updated_at = updated_at
            WHERE tape_id = ?
              AND review_status <> 'archived'
        ");
        $stmt->execute([(int)$card['id']]);

        ql_on_the_go_active_session_id((int)$card['user_id'], (int)$card['id'], 'cash');

        $stmt = $db->prepare("
            UPDATE on_the_go_tapes
            SET submitted_at = NULL,
                actual_remaining = NULL,
                difference_amount = NULL,
                archived_at = NULL,
                updated_at = updated_at
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$card['id']]);

        $legacyArchived = ql_on_the_go_archive_legacy_ledger_rows((int)$card['id'], (int)$card['user_id']);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'card_unsubmit_failed', 'message' => $e->getMessage()];
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_card_unsubmitted', 'on_the_go_tape', (int)$card['id'], [
            'group_id' => (int)($card['group_id'] ?? 0),
            'legacy_ledger_archived' => $legacyArchived ?? 0,
        ]);
    }
    ql_on_the_go_journal_append('card_returned_to_edit', $userId, (int)$card['id'], [
        'group_id' => (int)($card['group_id'] ?? 0),
    ]);

    return ql_on_the_go_card_detail(['id' => (int)$card['id']]);
}

function ql_on_the_go_card_archive_completed(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }

    $summary = ql_on_the_go_card_summary((int)$card['id']);
    $state = ql_on_the_go_card_state($card, $summary);
    if ($state !== 'included') {
        return ['ok' => false, 'error' => 'only_included_card_can_be_archived'];
    }

    $isOwner = (int)($card['user_id'] ?? 0) === $userId;
    if (!$isOwner && !ql_on_the_go_card_can_view_archive($card, $userId)) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $stmt = ql_db()->prepare("
        UPDATE on_the_go_tapes
        SET archived_at = COALESCE(archived_at, NOW()),
            updated_at = updated_at
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$card['id']]);

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_card_completed_archived', 'on_the_go_tape', (int)$card['id'], [
            'group_id' => (int)($card['group_id'] ?? 0),
            'owner_user_id' => (int)($card['user_id'] ?? 0),
            'summary' => $summary,
        ]);
    }
    ql_on_the_go_journal_append('card_archived_from_workspace', $userId, (int)$card['id'], [
        'group_id' => (int)($card['group_id'] ?? 0),
        'owner_user_id' => (int)($card['user_id'] ?? 0),
    ]);

    return ['ok' => true, 'archived' => true, 'tape_id' => (int)$card['id']];
}

function ql_on_the_go_card_request_return(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }
    if ((int)($card['user_id'] ?? 0) !== $userId) {
        return ['ok' => false, 'error' => 'only_owner_can_request_return'];
    }

    $summary = ql_on_the_go_card_summary((int)$card['id']);
    $state = ql_on_the_go_card_state($card, $summary);
    if (!in_array($state, ['submitted', 'included'], true)) {
        return ['ok' => false, 'error' => 'card_is_not_locked'];
    }
    if (ql_on_the_go_card_can_moderate($card, $userId)) {
        return ['ok' => false, 'error' => 'moderator_can_return_directly'];
    }

    $reason = trim((string)($input['reason'] ?? ''));
    if (mb_strlen($reason) > 500) {
        $reason = mb_substr($reason, 0, 500);
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_card_return_requested', 'on_the_go_tape', (int)$card['id'], [
            'group_id' => (int)($card['group_id'] ?? 0),
            'state' => $state,
            'reason' => $reason,
        ]);
    }
    ql_on_the_go_journal_append('card_return_requested', $userId, (int)$card['id'], [
        'group_id' => (int)($card['group_id'] ?? 0),
        'state' => $state,
        'reason' => $reason,
    ]);

    return ql_on_the_go_card_detail(['id' => (int)$card['id']]);
}

function ql_on_the_go_card_delete(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $tapeId = (int)($input['tape_id'] ?? $input['id'] ?? 0);
    $card = ql_on_the_go_card_row($tapeId, $userId);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }
    if (!ql_on_the_go_card_can_manage($card, $userId)) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    $summary = ql_on_the_go_card_summary((int)$card['id']);
    $state = ql_on_the_go_card_state($card, $summary);
    if (!in_array($state, ['empty', 'draft'], true)) {
        return ['ok' => false, 'error' => 'card_not_deletable_after_findesk'];
    }

    $db = ql_db();
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE on_the_go_tapes
            SET status = 'archived',
                archived_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$card['id']]);

        $stmt = $db->prepare("
            UPDATE on_the_go_captures
            SET review_status = 'archived',
                reportable = 0,
                updated_at = NOW()
            WHERE tape_id = ?
        ");
        $stmt->execute([(int)$card['id']]);

        $stmt = $db->prepare("
            UPDATE on_the_go_sessions
            SET status = 'archived',
                archived_at = NOW(),
                updated_at = NOW()
            WHERE tape_id = ?
        ");
        $stmt->execute([(int)$card['id']]);

        $legacyArchived = ql_on_the_go_archive_legacy_ledger_rows((int)$card['id'], (int)$card['user_id']);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'error' => 'card_delete_failed', 'message' => $e->getMessage()];
    }

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_card_deleted', 'on_the_go_tape', (int)$card['id'], [
            'group_id' => (int)($card['group_id'] ?? 0),
            'legacy_ledger_archived' => $legacyArchived ?? 0,
        ]);
    }
    ql_on_the_go_journal_append('card_cancelled_deleted', $userId, (int)$card['id'], [
        'group_id' => (int)($card['group_id'] ?? 0),
        'legacy_ledger_archived' => $legacyArchived ?? 0,
    ]);

    return ['ok' => true, 'deleted' => true, 'tape_id' => (int)$card['id'], 'legacy_ledger_archived' => $legacyArchived ?? 0];
}

function ql_on_the_go_update(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

    $existing = ql_on_the_go_get_one_visible($id, (int)$user['id']);
    if (!$existing) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    if (($existing['review_status'] ?? '') === 'archived') {
        return ['ok' => false, 'error' => 'capture_not_editable'];
    }

    $card = ql_on_the_go_card_row((int)($existing['tape_id'] ?? 0), (int)$user['id']);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }
    $state = ql_on_the_go_card_state($card, ql_on_the_go_card_summary((int)$card['id']));
    if (!in_array($state, ['empty', 'draft'], true)) {
        return ['ok' => false, 'error' => 'card_locked_after_findesk'];
    }
    if (!ql_on_the_go_card_can_manage($card, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }
    $pendingTransfer = ql_on_the_go_pending_transfer_block((int)($card['id'] ?? 0), (int)$user['id']);
    if ($pendingTransfer) {
        return [
            'ok' => false,
            'error' => $pendingTransfer['code'],
            'message' => $pendingTransfer['message'],
            'advance_id' => (int)($pendingTransfer['advance_id'] ?? 0),
            'transfer_id' => (int)($pendingTransfer['transfer_id'] ?? 0),
        ];
    }

    $type = (string)($input['capture_type'] ?? $existing['capture_type']);
    if (!in_array($type, ['cash_in', 'cash_out', 'noncash_out'], true)) {
        return ['ok' => false, 'error' => 'invalid_capture_type'];
    }
    if (!ql_on_the_go_capture_allowed_for_stream($type, ql_on_the_go_tape_stream($card))) {
        return ['ok' => false, 'error' => 'capture_type_not_allowed_for_stream'];
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
          AND review_status <> 'archived'
        LIMIT 1
    ");

    $stmt->execute([
        $type,
        $amount,
        $description !== '' ? $description : null,
        $id
    ]);

    ql_on_the_go_journal_append('record_updated', (int)$user['id'], (int)$card['id'], [
        'capture_id' => $id,
        'capture_type' => $type,
        'amount' => $amount,
        'description' => $description,
    ]);

    return [
        'ok' => true,
        'capture' => ql_on_the_go_get_one_visible($id, (int)$user['id'])
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

    $existing = ql_on_the_go_get_one_visible($id, (int)$user['id']);
    if (!$existing) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    $card = ql_on_the_go_card_row((int)($existing['tape_id'] ?? 0), (int)$user['id']);
    if (!$card) {
        return ['ok' => false, 'error' => 'card_not_found'];
    }
    $state = ql_on_the_go_card_state($card, ql_on_the_go_card_summary((int)$card['id']));
    if (!in_array($state, ['empty', 'draft'], true)) {
        return ['ok' => false, 'error' => 'card_locked_after_findesk'];
    }
    if (!ql_on_the_go_card_can_manage($card, (int)$user['id'])) {
        return ['ok' => false, 'error' => 'access_denied'];
    }

    $stmt = ql_db()->prepare("
        UPDATE on_the_go_captures
        SET review_status = 'archived',
            reportable = 0,
            updated_at = NOW()
        WHERE id = ?
          AND review_status <> 'archived'
        LIMIT 1
    ");
    $stmt->execute([$id]);

    ql_on_the_go_journal_append('record_cancelled', (int)$user['id'], (int)$card['id'], [
        'capture_id' => $id,
    ]);

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

function ql_on_the_go_submit_to_ledger(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);

    if ($groupId <= 0) {
        return ['ok' => false, 'error' => 'invalid_group_id'];
    }

    $scope = ql_ledger_group_scope($groupId, $userId);
    if (!$scope) {
        return ['ok' => false, 'error' => 'not_group_member'];
    }
    if (empty($scope['can_write_group_ledger'])) {
        return ['ok' => false, 'error' => 'access_denied', 'required' => 'manager'];
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

    $summaryBefore = ql_on_the_go_tape_summary($tapeId);

    $stmt = ql_db()->prepare("
        SELECT c.id
        FROM on_the_go_captures c
        LEFT JOIN on_the_go_sessions s ON s.id = c.session_id
        WHERE c.user_id = ?
          AND c.tape_id = ?
          AND c.review_status = 'needs_review'
          AND c.capture_type IN ('cash_in', 'cash_out', 'noncash_out')
          AND c.amount IS NOT NULL
          AND c.amount > 0
          AND (c.session_id IS NULL OR s.status = 'active')
        ORDER BY c.created_at ASC, c.id ASC
    ");
    $stmt->execute([$userId, $tapeId]);
    $captureIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if (!$captureIds) {
        return ['ok' => false, 'error' => 'empty_report'];
    }

    $entries = [];
    $failed = [];
    $copiedFiles = 0;

    foreach ($captureIds as $captureId) {
        $result = ql_on_the_go_convert_to_ledger([
            'id' => $captureId,
            'group_id' => $groupId,
        ]);

        if (!empty($result['ok'])) {
            $entries[] = $result['entry'] ?? ['id' => $result['entry_id'] ?? null];
            $copiedFiles += (int)($result['copied_files'] ?? 0);
        } else {
            $failed[] = [
                'capture_id' => $captureId,
                'error' => $result['error'] ?? 'convert_failed',
                'message' => $result['message'] ?? null,
            ];
        }
    }

    $actualRemaining = round((float)($summaryBefore['cash_left'] ?? 0), 2);

    $update = ql_db()->prepare("
        UPDATE on_the_go_tapes
        SET group_id = COALESCE(group_id, ?),
            submitted_at = NOW(),
            actual_remaining = ?,
            difference_amount = 0.00,
            updated_at = NOW()
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $update->execute([
        $groupId,
        number_format($actualRemaining, 2, '.', ''),
        $tapeId,
        $userId,
    ]);

    if (function_exists('ql_audit_write')) {
        ql_audit_write($userId, 'on_the_go_report_submitted', 'on_the_go_tape', $tapeId, [
            'group_id' => $groupId,
            'converted' => count($entries),
            'failed' => count($failed),
            'files_copied' => $copiedFiles,
            'summary' => $summaryBefore,
        ]);
    }
    ql_on_the_go_journal_append('legacy_report_submitted_to_ledger', $userId, $tapeId, [
        'group_id' => $groupId,
        'converted' => count($entries),
        'failed' => count($failed),
        'files_copied' => $copiedFiles,
    ]);

    return [
        'ok' => count($entries) > 0,
        'partial' => count($failed) > 0,
        'tape_id' => $tapeId,
        'group_id' => $groupId,
        'converted' => count($entries),
        'failed' => $failed,
        'files_copied' => $copiedFiles,
        'summary' => ql_on_the_go_tape_summary($tapeId),
        'summary_before' => $summaryBefore,
        'entries' => $entries,
    ];
}

function ql_on_the_go_report_list(array $input = []): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $userId = (int)$user['id'];
    $groupId = (int)($input['group_id'] ?? 0);
    $limit = (int)($input['limit'] ?? 20);
    if ($limit < 1 || $limit > 100) {
        $limit = 20;
    }

    $where = "al.action = 'on_the_go_report_submitted'";
    $params = [];
    $scope = null;
    $canViewGroup = false;

    if ($groupId > 0) {
        $scope = ql_ledger_group_scope($groupId, $userId);
        if (!$scope) {
            return ['ok' => false, 'error' => 'not_group_member'];
        }

        $canViewGroup = !empty($scope['can_view_group_reports']);
        $where .= " AND CAST(JSON_UNQUOTE(JSON_EXTRACT(al.details, '$.group_id')) AS UNSIGNED) = ?";
        $params[] = $groupId;

        if (!$canViewGroup) {
            $where .= " AND al.user_id = ?";
            $params[] = $userId;
        }
    } else {
        $where .= " AND al.user_id = ?";
        $params[] = $userId;
    }

    $stmt = ql_db()->prepare("
        SELECT
            al.id,
            al.user_id,
            al.entity_id AS tape_id,
            al.details,
            al.created_at,
            u.email,
            COALESCE(u.display_name, u.email) AS user_display_name
        FROM audit_log al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE {$where}
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);

    $entryStmt = ql_db()->prepare("
        SELECT id, entry_type, money_type, amount, currency, purpose, note, entry_datetime, created_at
        FROM ledger_entries
        WHERE group_id = ?
          AND user_id = ?
          AND deleted_at IS NULL
          AND note LIKE 'From On the Go:%'
          AND created_at BETWEEN DATE_SUB(?, INTERVAL 10 SECOND) AND DATE_ADD(?, INTERVAL 10 SECOND)
        ORDER BY entry_datetime ASC, id ASC
    ");

    $reports = [];
    foreach ($stmt->fetchAll() as $row) {
        $details = json_decode((string)($row['details'] ?? ''), true);
        if (!is_array($details)) {
            $details = [];
        }

        $rowGroupId = (int)($details['group_id'] ?? $groupId);
        $entryStmt->execute([
            $rowGroupId,
            (int)$row['user_id'],
            (string)$row['created_at'],
            (string)$row['created_at'],
        ]);
        $entries = $entryStmt->fetchAll();

        $income = 0.0;
        $expense = 0.0;
        foreach ($entries as $entry) {
            $amount = (float)($entry['amount'] ?? 0);
            if (($entry['entry_type'] ?? '') === 'income') {
                $income += $amount;
            } else {
                $expense += $amount;
            }
        }

        $summary = $details['summary'] ?? [];
        $reports[] = [
            'id' => (int)$row['id'],
            'tape_id' => (int)($row['tape_id'] ?? 0),
            'group_id' => $rowGroupId,
            'user_id' => (int)$row['user_id'],
            'user_display_name' => $row['user_display_name'] ?? $row['email'] ?? 'Участник',
            'email' => $row['email'] ?? '',
            'created_at' => $row['created_at'],
            'converted' => (int)($details['converted'] ?? count($entries)),
            'files_copied' => (int)($details['files_copied'] ?? 0),
            'summary' => [
                'cash_in' => round((float)($summary['cash_in'] ?? $income), 2),
                'cash_out' => round((float)($summary['cash_out'] ?? $expense), 2),
                'card_out' => round((float)($summary['card_out'] ?? 0), 2),
                'cash_left' => round((float)($summary['cash_left'] ?? ($income - $expense)), 2),
                'records_count' => (int)($summary['records_count'] ?? count($entries)),
                'movement' => round($income - $expense, 2),
            ],
            'entries' => array_slice($entries, 0, 8),
        ];
    }

    return [
        'ok' => true,
        'scope' => [
            'mode' => $groupId > 0 ? 'group' : 'personal',
            'group_id' => $groupId ?: null,
            'can_view_group_reports' => $canViewGroup,
            'access_level' => $scope['access_level'] ?? null,
        ],
        'reports' => $reports,
    ];
}

function ql_on_the_go_file_list(array $input): array
{
    $user = ql_otr_user();
    if (ql_otr_is_user_error($user)) return $user;

    $captureId = (int)($input['capture_id'] ?? 0);
    if ($captureId <= 0) {
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

    $capture = ql_on_the_go_get_one_visible($captureId, (int)$user['id']);
    if (!$capture) {
        return ['ok' => false, 'error' => 'capture_not_found'];
    }

    ql_on_the_go_field_ensure_schema();
    $stmt = ql_db()->prepare("
        SELECT *
        FROM on_the_go_files
        WHERE capture_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$captureId]);
    $files = $stmt->fetchAll();

    $files = array_map('ql_on_the_go_file_public', $files);

    return ['ok' => true, 'files' => $files];
}

function ql_on_the_go_file_public(array $file): array
{
    return [
        'id' => (int)($file['id'] ?? 0),
        'capture_id' => (int)($file['capture_id'] ?? 0),
        'original_name' => (string)($file['original_name'] ?? ''),
        'storage_path' => (string)($file['storage_path'] ?? ''),
        'mime_type' => (string)($file['mime_type'] ?? ''),
        'size_bytes' => (int)($file['size_bytes'] ?? 0),
        'proof_role' => (string)($file['proof_role'] ?? 'attachment'),
        'proof_bundle_id' => (string)($file['proof_bundle_id'] ?? ''),
        'source_file_id' => isset($file['source_file_id']) ? (int)$file['source_file_id'] : null,
        'file_hash_sha256' => (string)($file['file_hash_sha256'] ?? ''),
        'metadata' => ql_on_the_go_public_metadata($file['metadata_json'] ?? '{}'),
        'created_at' => (string)($file['created_at'] ?? ''),
        'download_url' => '/api.php?action=on_the_go_file_download&id=' . (int)($file['id'] ?? 0),
    ];
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
        LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file || !ql_on_the_go_get_one_visible((int)($file['capture_id'] ?? 0), (int)$user['id'])) {
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

	    $userId = (int)$user['id'];
	    $clientUploadId = ql_on_the_go_client_token($_POST['client_upload_id'] ?? '', false);
	    $draftId = (int)($_POST['draft_id'] ?? 0);
	    $clientDraftId = ql_on_the_go_client_token($_POST['client_draft_id'] ?? '', false);
	    $proofRole = ql_on_the_go_proof_role($_POST['proof_role'] ?? 'attachment');
	    $proofBundleId = ql_on_the_go_client_token($_POST['proof_bundle_id'] ?? '', false, 'proof');
	    $sourceFileId = (int)($_POST['source_file_id'] ?? 0);
	    $metadataJson = ql_on_the_go_proof_metadata($_POST['metadata_json'] ?? ($_POST['metadata'] ?? '{}'));
	    if ($draftId <= 0 && $clientDraftId !== '') {
	        $draft = ql_on_the_go_field_draft_row_by_client($userId, $clientDraftId);
	        $draftId = $draft ? (int)$draft['id'] : 0;
	    }
	    $recordUploadFailure = static function (string $error, string $status = 'retry_needed') use ($userId, $clientUploadId, $draftId, $proofRole, $proofBundleId, $metadataJson): void {
	        if ($clientUploadId === '') {
	            return;
	        }
	        ql_on_the_go_upload_state_record($userId, $clientUploadId, [
	            'draft_id' => $draftId,
	            'status' => $status,
	            'proof_role' => $proofRole,
	            'proof_bundle_id' => $proofBundleId,
	            'metadata_json' => $metadataJson,
	            'last_error' => $error,
	            'increment_retry' => true,
	        ]);
    };

    $captureId = (int)($_POST['capture_id'] ?? 0);
    if ($captureId <= 0) {
        $recordUploadFailure('invalid_capture_id', 'failed');
        return ['ok' => false, 'error' => 'invalid_capture_id'];
    }

	    $capture = ql_on_the_go_get_one($captureId, $userId);
	    if (!$capture) {
	        $recordUploadFailure('capture_not_found', 'failed');
	        return ['ok' => false, 'error' => 'capture_not_found'];
	    }

	    if ($clientUploadId !== '') {
	        $existingStateStmt = ql_db()->prepare("
	            SELECT *
	            FROM on_the_go_upload_states
	            WHERE user_id = ?
	              AND client_upload_id = ?
	              AND status = 'uploaded'
	              AND storage_path IS NOT NULL
	              AND storage_path <> ''
	            LIMIT 1
	        ");
	        $existingStateStmt->execute([$userId, $clientUploadId]);
	        $existingState = $existingStateStmt->fetch();
	        if ($existingState) {
	            $existingFileStmt = ql_db()->prepare("
	                SELECT *
	                FROM on_the_go_files
	                WHERE user_id = ?
	                  AND capture_id = ?
	                  AND storage_path = ?
	                LIMIT 1
	            ");
	            $existingFileStmt->execute([$userId, $captureId, (string)$existingState['storage_path']]);
	            $existingFile = $existingFileStmt->fetch();
	            if ($existingFile) {
	                return [
	                    'ok' => true,
	                    'idempotent' => true,
	                    'file' => ql_on_the_go_file_public($existingFile),
	                    'proof_state' => ql_on_the_go_upload_state_public($existingState),
	                    'capture' => $capture,
	                ];
	            }
	        }
	    }

    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        $recordUploadFailure('missing_file', 'retry_needed');
        return ['ok' => false, 'error' => 'missing_file'];
    }

    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'upload_error_' . (int)$file['error'];
        $recordUploadFailure($error, 'retry_needed');
        return ['ok' => false, 'error' => $error];
    }

    $maxSize = 8 * 1024 * 1024;
    if ((int)$file['size'] > $maxSize) {
        $recordUploadFailure('file_too_large', 'failed');
        return ['ok' => false, 'error' => 'file_too_large'];
    }

    $original = basename((string)$file['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
	    $allowed = ['jpg','jpeg','png','webp','pdf','txt','doc','docx','heic','heif'];
	    if (!in_array($ext, $allowed, true)) {
	        $recordUploadFailure('file_type_not_allowed', 'failed');
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
        $recordUploadFailure('move_failed', 'retry_needed');
        return ['ok' => false, 'error' => 'move_failed'];
    }

	    $relative = "storage/documents/on-the-go/{$year}/{$safeName}";
	    $mime = (string)($file['type'] ?? '');
	    if (function_exists('finfo_open')) {
	        $finfo = finfo_open(FILEINFO_MIME_TYPE);
	        if ($finfo) {
	            $detected = finfo_file($finfo, $target);
	            if (is_string($detected) && $detected !== '') {
	                $mime = $detected;
	            }
	            finfo_close($finfo);
	        }
	    }
	    if ($mime === '') {
	        $mime = 'application/octet-stream';
	    }
	    $fileHash = hash_file('sha256', $target) ?: null;

	    $stmt = ql_db()->prepare("
	        INSERT INTO on_the_go_files
	            (capture_id, user_id, original_name, storage_path, mime_type, size_bytes, proof_role, proof_bundle_id, source_file_id, file_hash_sha256, metadata_json)
	        VALUES
	            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
	    ");
	    $stmt->execute([
	        $captureId,
	        (int)$user['id'],
	        $original,
	        $relative,
	        $mime,
	        (int)$file['size'],
	        $proofRole,
	        $proofBundleId ?: null,
	        $sourceFileId > 0 ? $sourceFileId : null,
	        $fileHash,
	        $metadataJson,
	    ]);
	    $fileId = (int)ql_db()->lastInsertId();
	    $storedFile = [
	        'id' => $fileId,
	        'capture_id' => $captureId,
	        'user_id' => (int)$user['id'],
	        'original_name' => $original,
	        'storage_path' => $relative,
	        'mime_type' => $mime,
	        'size_bytes' => (int)$file['size'],
	        'proof_role' => $proofRole,
	        'proof_bundle_id' => $proofBundleId,
	        'source_file_id' => $sourceFileId > 0 ? $sourceFileId : null,
	        'file_hash_sha256' => $fileHash,
	        'metadata_json' => $metadataJson,
	        'created_at' => date('Y-m-d H:i:s'),
	    ];

	    $proofState = null;
	    if ($clientUploadId !== '') {
        $proofState = ql_on_the_go_upload_state_record($userId, $clientUploadId, [
            'draft_id' => $draftId,
            'capture_id' => $captureId,
            'status' => 'uploaded',
	            'original_name' => $original,
	            'storage_path' => $relative,
	            'mime_type' => $mime,
	            'size_bytes' => (int)$file['size'],
	            'proof_role' => $proofRole,
	            'proof_bundle_id' => $proofBundleId,
	            'file_hash_sha256' => $fileHash,
	            'metadata_json' => $metadataJson,
	        ]);
	    }

    return [
        'ok' => true,
	        'file' => ql_on_the_go_file_public($storedFile),
	        'proof_state' => $proofState,
        'capture' => ql_on_the_go_get_one($captureId, $userId)
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
