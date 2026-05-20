CREATE TABLE IF NOT EXISTS ledger_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED DEFAULT NULL,
    entry_type ENUM('income','expense') NOT NULL,
    money_type ENUM('cash','noncash') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    purpose VARCHAR(255) NOT NULL,
    note TEXT DEFAULT NULL,
    entry_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    original_position_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_ledger_user_date (user_id, entry_datetime),
    KEY idx_ledger_user_active (user_id, deleted_at),
    KEY idx_ledger_type (entry_type),
    CONSTRAINT fk_ledger_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entry_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    file_original_name VARCHAR(255) DEFAULT NULL,
    file_stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_mime VARCHAR(120) DEFAULT NULL,
    file_size BIGINT UNSIGNED DEFAULT NULL,
    file_kind ENUM('image','document','other') NOT NULL DEFAULT 'other',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_entry_files_entry (entry_id),
    KEY idx_entry_files_user (user_id),
    CONSTRAINT fk_entry_files_entry FOREIGN KEY (entry_id) REFERENCES ledger_entries(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_entry_files_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
