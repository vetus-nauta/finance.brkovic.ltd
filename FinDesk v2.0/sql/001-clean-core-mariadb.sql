-- FinDesk v2.0 Clean Core MVP schema for MariaDB/MySQL
-- Runtime target decision for SPRINT-01R: reuse existing PDO MySQL infrastructure only.
-- Product data is isolated in v2_* tables and does not reuse old FinDesk finance tables.

CREATE TABLE IF NOT EXISTS v2_workspaces (
    id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    type ENUM('yacht','family','personal','business','trip','custom') NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    locale VARCHAR(10) NOT NULL DEFAULT 'ru',
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_v2_workspaces_type (type),
    KEY idx_v2_workspaces_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_workspace_members (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('owner','admin','assistant','viewer') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_workspace_member (workspace_id, user_id),
    KEY idx_v2_workspace_members_user (user_id),
    CONSTRAINT fk_v2_workspace_members_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_flows (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('cash','card','assistant_journal') NOT NULL,
    has_live_balance TINYINT(1) NOT NULL DEFAULT 0,
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_flow_type (workspace_id, type, name),
    KEY idx_v2_flows_workspace (workspace_id),
    CONSTRAINT fk_v2_flows_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_categories (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) DEFAULT NULL,
    code VARCHAR(80) NOT NULL,
    name_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(name_json)),
    direction ENUM('income','expense','movement','mixed') NOT NULL DEFAULT 'expense',
    parent_code VARCHAR(80) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_categories_workspace (workspace_id),
    KEY idx_v2_categories_code (code),
    CONSTRAINT fk_v2_categories_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_actors (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    name VARCHAR(190) NOT NULL,
    actor_type ENUM('person','role','supplier','company','unknown') NOT NULL DEFAULT 'unknown',
    aliases_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(aliases_json)),
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_actor_name (workspace_id, name),
    CONSTRAINT fk_v2_actors_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_import_sources (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    source_type ENUM('google_drive','excel','legacy_db','manual_upload') NOT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_url TEXT DEFAULT NULL,
    file_id VARCHAR(190) DEFAULT NULL,
    status VARCHAR(80) NOT NULL DEFAULT 'pending',
    include_decision ENUM('included','excluded_by_title_marker','excluded_duplicate','included_partially','manual_review') NOT NULL DEFAULT 'manual_review',
    reason TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_import_sources_workspace (workspace_id),
    CONSTRAINT fk_v2_import_sources_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_entries (
    id CHAR(36) NOT NULL,
    created_seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    workspace_id CHAR(36) NOT NULL,
    flow_id CHAR(36) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    actor_id CHAR(36) DEFAULT NULL,
    date DATE NOT NULL,
    raw_text TEXT NOT NULL,
    sign CHAR(1) DEFAULT NULL,
    amount DECIMAL(14,2) DEFAULT NULL,
    direction ENUM('in','out','none') NOT NULL DEFAULT 'none',
    entry_type ENUM('cash_income','cash_expense','card_expense','card_income','opening_balance','correction','info','unrecognized','assistant_pending') NOT NULL,
    category_id CHAR(36) DEFAULT NULL,
    status ENUM('recognized','unrecognized','other_review','excluded','imported','assistant_pending','accepted','rejected','corrected','duplicate_suspect') NOT NULL,
    balance_after DECIMAL(14,2) DEFAULT NULL,
    source_type ENUM('manual','import','assistant','correction') NOT NULL DEFAULT 'manual',
    source_id CHAR(36) DEFAULT NULL,
    source_row_id CHAR(36) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    confidence DECIMAL(4,3) DEFAULT NULL,
    matched_rules_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(matched_rules_json)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_entries_created_seq (created_seq),
    KEY idx_v2_entries_workspace_date (workspace_id, date),
    KEY idx_v2_entries_flow_date (flow_id, date),
    KEY idx_v2_entries_status (status),
    KEY idx_v2_entries_category (category_id),
    CONSTRAINT fk_v2_entries_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_entries_flow FOREIGN KEY (flow_id) REFERENCES v2_flows (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_v2_entries_actor FOREIGN KEY (actor_id) REFERENCES v2_actors (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_v2_entries_category FOREIGN KEY (category_id) REFERENCES v2_categories (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_v2_entries_source FOREIGN KEY (source_id) REFERENCES v2_import_sources (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_v2_entries_sign CHECK (sign IN ('+', '-') OR sign IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_category_rules (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) DEFAULT NULL,
    category_id CHAR(36) NOT NULL,
    pattern VARCHAR(255) NOT NULL,
    pattern_type ENUM('keyword','phrase','regex','supplier','role') NOT NULL DEFAULT 'keyword',
    language ENUM('ru','en','it','es','de','bcms','multi') NOT NULL DEFAULT 'multi',
    weight INT NOT NULL DEFAULT 10,
    negative_weight INT NOT NULL DEFAULT 0,
    requires_any_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(requires_any_json)),
    excludes_any_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(excludes_any_json)),
    created_by_user TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_category_rules_workspace (workspace_id),
    KEY idx_v2_category_rules_category (category_id),
    CONSTRAINT fk_v2_category_rules_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_category_rules_category FOREIGN KEY (category_id) REFERENCES v2_categories (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_attachments (
    id CHAR(36) NOT NULL,
    entry_id CHAR(36) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url TEXT NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    size_bytes BIGINT UNSIGNED DEFAULT NULL,
    image_mode ENUM('original','compressed','grayscale_scan') DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_attachments_entry (entry_id),
    CONSTRAINT fk_v2_attachments_entry FOREIGN KEY (entry_id) REFERENCES v2_entries (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_monthly_closures (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    opening_balance DECIMAL(14,2) DEFAULT NULL,
    closing_balance DECIMAL(14,2) DEFAULT NULL,
    is_closed TINYINT(1) NOT NULL DEFAULT 0,
    comment TEXT DEFAULT NULL,
    closed_by BIGINT UNSIGNED DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_monthly_closure (workspace_id, year, month),
    CONSTRAINT fk_v2_monthly_closures_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_v2_monthly_closures_month CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_import_rows (
    id CHAR(36) NOT NULL,
    import_source_id CHAR(36) NOT NULL,
    sheet_name VARCHAR(190) DEFAULT NULL,
    `row_number` INT DEFAULT NULL,
    raw_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(raw_json)),
    entry_id CHAR(36) DEFAULT NULL,
    parse_status VARCHAR(80) NOT NULL DEFAULT 'pending',
    parse_notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_import_rows_source (import_source_id),
    KEY idx_v2_import_rows_entry (entry_id),
    CONSTRAINT fk_v2_import_rows_source FOREIGN KEY (import_source_id) REFERENCES v2_import_sources (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_import_rows_entry FOREIGN KEY (entry_id) REFERENCES v2_entries (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_audit_log (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) DEFAULT NULL,
    entity_type VARCHAR(120) NOT NULL,
    entity_id CHAR(36) DEFAULT NULL,
    action VARCHAR(120) NOT NULL,
    before_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (before_json IS NULL OR json_valid(before_json)),
    after_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (after_json IS NULL OR json_valid(after_json)),
    performed_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_audit_workspace (workspace_id),
    KEY idx_v2_audit_entity (entity_type, entity_id),
    KEY idx_v2_audit_action (action),
    CONSTRAINT fk_v2_audit_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'crew', '{"ru":"Экипаж","en":"Crew"}', 'expense', 10, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'crew');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'commercial_income', '{"ru":"Коммерческий приход","en":"Commercial income"}', 'income', 15, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'commercial_income');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'dry_dock', '{"ru":"Сухой док","en":"Dry dock"}', 'expense', 20, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'dry_dock');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'berth', '{"ru":"Стоянка","en":"Berth"}', 'expense', 30, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'berth');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'marina_ports', '{"ru":"Марины и портовые","en":"Marinas and port fees"}', 'expense', 40, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'marina_ports');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'service_water', '{"ru":"Сервис на воде","en":"Service on water"}', 'expense', 50, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'service_water');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'tech_parts', '{"ru":"Техчасть и запчасти","en":"Technical parts"}', 'expense', 60, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'tech_parts');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'tender', '{"ru":"Тендер / тузик","en":"Tender"}', 'expense', 70, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'tender');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'fuel', '{"ru":"Топливо","en":"Fuel"}', 'expense', 80, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'fuel');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'provisions', '{"ru":"Продукты и гости","en":"Provisions and guests"}', 'expense', 90, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'provisions');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'interior', '{"ru":"Интерьер и быт","en":"Interior and household"}', 'expense', 100, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'interior');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'cleaning', '{"ru":"Клининг и химия","en":"Cleaning and chemicals"}', 'expense', 110, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'cleaning');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'media_comms', '{"ru":"Мультимедиа и связь","en":"Media and communications"}', 'expense', 120, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'media_comms');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'admin_legal', '{"ru":"Админка / документы","en":"Admin and legal"}', 'expense', 130, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'admin_legal');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'cash_topup_from_card', '{"ru":"Пополнение cash с карты","en":"Cash top-up from card"}', 'movement', 140, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'cash_topup_from_card');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'other', '{"ru":"Другие расходы","en":"Other expenses"}', 'expense', 999, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'other');
