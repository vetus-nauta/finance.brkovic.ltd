CREATE TABLE IF NOT EXISTS on_the_go_tapes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED DEFAULT NULL,
    advance_id BIGINT UNSIGNED DEFAULT NULL,
    stream_type ENUM('cash','card') NOT NULL DEFAULT 'cash',
    title VARCHAR(190) NOT NULL DEFAULT 'On the Go',
    cash_received DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    status ENUM('active','closed','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    archived_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_otr_tapes_user_status (user_id, status),
    KEY idx_otr_tapes_stream (user_id, group_id, stream_type, status),
    KEY idx_otr_tapes_created (created_at),
    CONSTRAINT fk_otr_tapes_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS on_the_go_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    tape_id BIGINT UNSIGNED NOT NULL,
    session_type ENUM('cash','card') NOT NULL DEFAULT 'cash',
    title VARCHAR(190) NOT NULL DEFAULT 'Cash session',
    status ENUM('active','closed','archived') NOT NULL DEFAULT 'active',
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    archived_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_otr_sessions_user_tape (user_id, tape_id),
    KEY idx_otr_sessions_type_status (session_type, status),
    CONSTRAINT fk_otr_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_otr_sessions_tape FOREIGN KEY (tape_id) REFERENCES on_the_go_tapes(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE on_the_go_captures
    ADD COLUMN IF NOT EXISTS tape_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS session_id BIGINT UNSIGNED DEFAULT NULL AFTER tape_id;

ALTER TABLE on_the_go_captures
    ADD KEY IF NOT EXISTS idx_otr_captures_tape (tape_id),
    ADD KEY IF NOT EXISTS idx_otr_captures_session (session_id);

ALTER TABLE on_the_go_tapes
    ADD COLUMN IF NOT EXISTS stream_type ENUM('cash','card') NOT NULL DEFAULT 'cash' AFTER advance_id,
    ADD COLUMN IF NOT EXISTS title VARCHAR(190) NOT NULL DEFAULT 'On the Go' AFTER stream_type,
    ADD COLUMN IF NOT EXISTS cash_received DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER title,
    ADD COLUMN IF NOT EXISTS currency CHAR(3) NOT NULL DEFAULT 'EUR' AFTER cash_received,
    ADD COLUMN IF NOT EXISTS status ENUM('active','closed','archived') NOT NULL DEFAULT 'active' AFTER currency,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD COLUMN IF NOT EXISTS closed_at DATETIME DEFAULT NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS archived_at DATETIME DEFAULT NULL AFTER closed_at,
    ADD KEY IF NOT EXISTS idx_otr_tapes_stream (user_id, group_id, stream_type, status);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
    proof_role VARCHAR(40) NOT NULL DEFAULT 'attachment',
    proof_bundle_id VARCHAR(120) DEFAULT NULL,
    file_hash_sha256 CHAR(64) DEFAULT NULL,
    metadata_json MEDIUMTEXT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE on_the_go_files
    ADD COLUMN IF NOT EXISTS proof_role VARCHAR(40) NOT NULL DEFAULT 'attachment' AFTER size_bytes,
    ADD COLUMN IF NOT EXISTS proof_bundle_id VARCHAR(120) DEFAULT NULL AFTER proof_role,
    ADD COLUMN IF NOT EXISTS source_file_id BIGINT UNSIGNED DEFAULT NULL AFTER proof_bundle_id,
    ADD COLUMN IF NOT EXISTS file_hash_sha256 CHAR(64) DEFAULT NULL AFTER source_file_id,
    ADD COLUMN IF NOT EXISTS metadata_json MEDIUMTEXT NULL AFTER file_hash_sha256,
    ADD KEY IF NOT EXISTS idx_otr_files_bundle (proof_bundle_id),
    ADD KEY IF NOT EXISTS idx_otr_files_role (proof_role);

ALTER TABLE on_the_go_upload_states
    ADD COLUMN IF NOT EXISTS proof_role VARCHAR(40) NOT NULL DEFAULT 'attachment' AFTER size_bytes,
    ADD COLUMN IF NOT EXISTS proof_bundle_id VARCHAR(120) DEFAULT NULL AFTER proof_role,
    ADD COLUMN IF NOT EXISTS file_hash_sha256 CHAR(64) DEFAULT NULL AFTER proof_bundle_id,
    ADD COLUMN IF NOT EXISTS metadata_json MEDIUMTEXT NULL AFTER file_hash_sha256;
