CREATE TABLE IF NOT EXISTS group_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    sender_user_id BIGINT UNSIGNED NOT NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('text','system') NOT NULL DEFAULT 'text',
    report_id BIGINT UNSIGNED DEFAULT NULL,
    tape_id BIGINT UNSIGNED DEFAULT NULL,
    capture_id BIGINT UNSIGNED DEFAULT NULL,
    advance_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_group_messages_group (group_id, created_at),
    KEY idx_group_messages_sender (sender_user_id),
    KEY idx_group_messages_report (report_id),
    KEY idx_group_messages_tape (tape_id),
    KEY idx_group_messages_capture (capture_id),
    KEY idx_group_messages_advance (advance_id),
    CONSTRAINT fk_group_messages_group FOREIGN KEY (group_id) REFERENCES groups(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_group_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE group_messages
    ADD COLUMN IF NOT EXISTS report_id BIGINT UNSIGNED DEFAULT NULL AFTER message_type,
    ADD COLUMN IF NOT EXISTS tape_id BIGINT UNSIGNED DEFAULT NULL AFTER report_id,
    ADD COLUMN IF NOT EXISTS capture_id BIGINT UNSIGNED DEFAULT NULL AFTER tape_id,
    ADD COLUMN IF NOT EXISTS advance_id BIGINT UNSIGNED DEFAULT NULL AFTER capture_id,
    ADD KEY IF NOT EXISTS idx_group_messages_report (report_id),
    ADD KEY IF NOT EXISTS idx_group_messages_tape (tape_id),
    ADD KEY IF NOT EXISTS idx_group_messages_capture (capture_id),
    ADD KEY IF NOT EXISTS idx_group_messages_advance (advance_id);

CREATE TABLE IF NOT EXISTS group_message_reads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_message_user_read (message_id, user_id),
    KEY idx_message_reads_user (user_id),
    CONSTRAINT fk_message_reads_message FOREIGN KEY (message_id) REFERENCES group_messages(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_message_reads_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
