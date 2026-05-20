CREATE TABLE IF NOT EXISTS cash_advances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    issued_by_user_id BIGINT UNSIGNED NOT NULL,
    assigned_to_user_id BIGINT UNSIGNED NOT NULL,
    on_the_go_tape_id BIGINT UNSIGNED DEFAULT NULL,
    title VARCHAR(190) NOT NULL DEFAULT 'Pocket advance',
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    status ENUM('issued','submitted','accepted','returned','closed','discrepancy') NOT NULL DEFAULT 'issued',
    expected_remaining DECIMAL(12,2) DEFAULT NULL,
    actual_remaining DECIMAL(12,2) DEFAULT NULL,
    difference_amount DECIMAL(12,2) DEFAULT NULL,
    submitted_note TEXT DEFAULT NULL,
    moderation_note TEXT DEFAULT NULL,
    submitted_at DATETIME DEFAULT NULL,
    accepted_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    accepted_at DATETIME DEFAULT NULL,
    returned_by_user_id BIGINT UNSIGNED DEFAULT NULL,
    returned_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_advances_group_status (group_id, status),
    KEY idx_advances_assigned_status (assigned_to_user_id, status),
    KEY idx_advances_tape (on_the_go_tape_id),
    CONSTRAINT fk_advances_group FOREIGN KEY (group_id) REFERENCES groups(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_advances_issued_by FOREIGN KEY (issued_by_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_advances_assigned_to FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_advances_tape FOREIGN KEY (on_the_go_tape_id) REFERENCES on_the_go_tapes(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_advances_accepted_by FOREIGN KEY (accepted_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_advances_returned_by FOREIGN KEY (returned_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE on_the_go_tapes
    ADD COLUMN IF NOT EXISTS group_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS advance_id BIGINT UNSIGNED DEFAULT NULL AFTER group_id,
    ADD COLUMN IF NOT EXISTS submitted_at DATETIME DEFAULT NULL AFTER closed_at,
    ADD COLUMN IF NOT EXISTS actual_remaining DECIMAL(12,2) DEFAULT NULL AFTER submitted_at,
    ADD COLUMN IF NOT EXISTS difference_amount DECIMAL(12,2) DEFAULT NULL AFTER actual_remaining;

ALTER TABLE on_the_go_tapes
    ADD KEY IF NOT EXISTS idx_otr_tapes_group (group_id),
    ADD KEY IF NOT EXISTS idx_otr_tapes_advance (advance_id);
