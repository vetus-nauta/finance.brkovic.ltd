CREATE TABLE IF NOT EXISTS on_the_go_tapes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
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

