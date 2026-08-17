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
    role ENUM('owner','admin','assistant','finance','employee','viewer') NOT NULL,
    access_scope ENUM('workspace','own_entries','assigned_actor','none') NOT NULL DEFAULT 'workspace',
    assigned_actor_id CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_workspace_member (workspace_id, user_id),
    KEY idx_v2_workspace_members_user (user_id),
    KEY idx_v2_workspace_members_assigned_actor (assigned_actor_id),
    CONSTRAINT fk_v2_workspace_members_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_workspace_liability_openings (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    liability_type ENUM('admin_debt') NOT NULL DEFAULT 'admin_debt',
    counterparty VARCHAR(190) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    basis_date DATE NOT NULL,
    title VARCHAR(255) NOT NULL,
    note TEXT DEFAULT NULL,
    source_json JSON DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_v2_liability_openings_workspace (workspace_id, liability_type, archived_at, basis_date),
    CONSTRAINT fk_v2_liability_openings_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_workspace_invites (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_hint VARCHAR(12) NOT NULL,
    invited_email VARCHAR(190) DEFAULT NULL,
    invited_name VARCHAR(190) DEFAULT NULL,
    role ENUM('employee') NOT NULL DEFAULT 'employee',
    access_scope ENUM('own_entries') NOT NULL DEFAULT 'own_entries',
    status ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME DEFAULT NULL,
    accepted_by BIGINT UNSIGNED DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    revoked_by BIGINT UNSIGNED DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_workspace_invites_token_hash (token_hash),
    KEY idx_v2_workspace_invites_workspace (workspace_id, status, created_at),
    KEY idx_v2_workspace_invites_email (invited_email),
    CONSTRAINT fk_v2_workspace_invites_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_accountable_offers (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    employee_user_id BIGINT UNSIGNED DEFAULT NULL,
    employee_email VARCHAR(190) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    purpose TEXT DEFAULT NULL,
    status ENUM('pending_offer','accepted_by_employee','cancelled') NOT NULL DEFAULT 'pending_offer',
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME DEFAULT NULL,
    accepted_by BIGINT UNSIGNED DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    cancelled_by BIGINT UNSIGNED DEFAULT NULL,
    no_financial_mutation TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_accountable_offers_workspace (workspace_id, status, created_at),
    KEY idx_v2_accountable_offers_employee_user (employee_user_id),
    KEY idx_v2_accountable_offers_employee_email (employee_email),
    CONSTRAINT fk_v2_accountable_offers_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_accountable_reports (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    offer_id CHAR(36) NOT NULL,
    employee_user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    status ENUM('draft','submitted','accepted_by_admin','rework_requested','rejected','cancelled') NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    submitted_at DATETIME DEFAULT NULL,
    submitted_by BIGINT UNSIGNED DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by BIGINT UNSIGNED DEFAULT NULL,
    review_note TEXT DEFAULT NULL,
    accepted_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    rejected_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    accepted_cash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    accepted_noncash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    settlement_status ENUM('pending','closed','return_due','reimburse_due','discrepancy') DEFAULT NULL,
    materialized_at DATETIME DEFAULT NULL,
    ledger_materialization_status ENUM('not_materialized','materialized','partial','revoked') NOT NULL DEFAULT 'not_materialized',
    ledger_materialized_at DATETIME DEFAULT NULL,
    ledger_materialized_by BIGINT UNSIGNED DEFAULT NULL,
    ledger_materialization_hash CHAR(64) DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    no_financial_mutation TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_v2_accountable_reports_workspace (workspace_id, status, created_at),
    KEY idx_v2_accountable_reports_offer (offer_id),
    KEY idx_v2_accountable_reports_employee (workspace_id, employee_user_id, status),
    CONSTRAINT fk_v2_accountable_reports_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_accountable_reports_offer FOREIGN KEY (offer_id) REFERENCES v2_accountable_offers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_accountable_report_rows (
    id CHAR(36) NOT NULL,
    report_id CHAR(36) NOT NULL,
    `row_number` INT UNSIGNED NOT NULL,
    expense_date DATE NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    category_code VARCHAR(80) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    review_status ENUM('pending_review','accepted','adjusted','rejected') NOT NULL DEFAULT 'pending_review',
    accepted_amount DECIMAL(14,2) DEFAULT NULL,
    accepted_category_code VARCHAR(80) DEFAULT NULL,
    payment_method ENUM('cash','card','noncash','own_funds') DEFAULT NULL,
    review_note TEXT DEFAULT NULL,
    operational_entry_id CHAR(36) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_accountable_report_row_number (report_id, `row_number`),
    KEY idx_v2_accountable_report_rows_report (report_id),
    CONSTRAINT fk_v2_accountable_report_rows_report FOREIGN KEY (report_id) REFERENCES v2_accountable_reports (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_accountable_settlements (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    offer_id CHAR(36) NOT NULL,
    report_id CHAR(36) NOT NULL,
    employee_user_id BIGINT UNSIGNED NOT NULL,
    issued_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    accepted_cash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    accepted_noncash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_remaining DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_remaining DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    return_due_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reimburse_due_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    difference_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    status ENUM('closed','return_due','reimburse_due','discrepancy') NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_accountable_settlements_report (report_id),
    KEY idx_v2_accountable_settlements_workspace (workspace_id, status, created_at),
    KEY idx_v2_accountable_settlements_offer (offer_id),
    CONSTRAINT fk_v2_accountable_settlements_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_accountable_settlements_offer FOREIGN KEY (offer_id) REFERENCES v2_accountable_offers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_v2_accountable_settlements_report FOREIGN KEY (report_id) REFERENCES v2_accountable_reports (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_flows (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    type ENUM('cash','card','assistant_journal','accountable') NOT NULL,
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
    source_type ENUM('google_drive','excel','legacy_db','manual_upload','quick_note') NOT NULL,
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
    entry_type ENUM('cash_income','cash_expense','card_expense','card_income','opening_balance','correction','info','unrecognized','assistant_pending','accountable_expense') NOT NULL,
    category_id CHAR(36) DEFAULT NULL,
    status ENUM('recognized','unrecognized','other_review','excluded','imported','assistant_pending','accepted','rejected','corrected','duplicate_suspect') NOT NULL,
    balance_after DECIMAL(14,2) DEFAULT NULL,
    source_type ENUM('manual','import','assistant','correction','accountable_report') NOT NULL DEFAULT 'manual',
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

CREATE TABLE IF NOT EXISTS v2_accountable_report_entry_links (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    report_id CHAR(36) NOT NULL,
    report_row_id CHAR(36) NOT NULL,
    entry_id CHAR(36) NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    cash_effect ENUM('none') NOT NULL DEFAULT 'none',
    payment_method ENUM('cash','card','noncash','own_funds') NOT NULL,
    accepted_amount DECIMAL(14,2) NOT NULL,
    category_code VARCHAR(80) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_accountable_report_entry_links_row (report_row_id),
    UNIQUE KEY uq_v2_accountable_report_entry_links_entry (entry_id),
    UNIQUE KEY uq_v2_accountable_report_entry_links_key (idempotency_key),
    KEY idx_v2_accountable_report_entry_links_workspace (workspace_id, created_at),
    KEY idx_v2_accountable_report_entry_links_report (report_id),
    CONSTRAINT fk_v2_accountable_report_entry_links_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_accountable_report_entry_links_report FOREIGN KEY (report_id) REFERENCES v2_accountable_reports (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_accountable_report_entry_links_row FOREIGN KEY (report_row_id) REFERENCES v2_accountable_report_rows (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_accountable_report_entry_links_entry FOREIGN KEY (entry_id) REFERENCES v2_entries (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
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

CREATE TABLE IF NOT EXISTS v2_report_snapshots (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    report_type ENUM('layer1_summary') NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    status ENUM('draft','stored','closed','superseded') NOT NULL DEFAULT 'stored',
    generated_at DATETIME NOT NULL,
    stored_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    summary_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(summary_json)),
    source_trace_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(source_trace_json)),
    source_entry_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(source_entry_ids_json)),
    correction_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(correction_ids_json)),
    attachment_refs_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(attachment_refs_json)),
    forecast_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (forecast_snapshot_json IS NULL OR json_valid(forecast_snapshot_json)),
    content_hash CHAR(64) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_report_snapshot_version (workspace_id, report_type, year, month, version),
    KEY idx_v2_report_snapshots_period (workspace_id, report_type, year, month),
    KEY idx_v2_report_snapshots_status (status),
    CONSTRAINT fk_v2_report_snapshots_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_v2_report_snapshots_month CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_report_batches (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    batch_type ENUM('operational_fragment') NOT NULL DEFAULT 'operational_fragment',
    title VARCHAR(190) NOT NULL,
    status ENUM('draft','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    from_entry_id CHAR(36) DEFAULT NULL,
    to_entry_id CHAR(36) DEFAULT NULL,
    entry_count INT UNSIGNED NOT NULL DEFAULT 0,
    generated_at DATETIME NOT NULL,
    closed_at DATETIME DEFAULT NULL,
    html_filename VARCHAR(255) DEFAULT NULL,
    summary_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(summary_json)),
    source_trace_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(source_trace_json)),
    source_entry_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(source_entry_ids_json)),
    entry_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(entry_snapshot_json)),
    content_hash CHAR(64) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_report_batches_workspace (workspace_id, created_at),
    KEY idx_v2_report_batches_period (workspace_id, start_date, end_date),
    KEY idx_v2_report_batches_status (status),
    CONSTRAINT fk_v2_report_batches_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_report_batches_from_entry FOREIGN KEY (from_entry_id) REFERENCES v2_entries (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_v2_report_batches_to_entry FOREIGN KEY (to_entry_id) REFERENCES v2_entries (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_report_batch_entries (
    id CHAR(36) NOT NULL,
    batch_id CHAR(36) NOT NULL,
    entry_id CHAR(36) NOT NULL,
    `row_number` INT UNSIGNED NOT NULL,
    entry_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(entry_snapshot_json)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_report_batch_entry (batch_id, entry_id),
    KEY idx_v2_report_batch_entries_entry (entry_id),
    CONSTRAINT fk_v2_report_batch_entries_batch FOREIGN KEY (batch_id) REFERENCES v2_report_batches (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_report_batch_entries_entry FOREIGN KEY (entry_id) REFERENCES v2_entries (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_report_versions (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    report_id CHAR(36) NOT NULL,
    report_type ENUM('operational_fragment','operational_package') NOT NULL,
    version INT UNSIGNED NOT NULL,
    format ENUM('html') NOT NULL DEFAULT 'html',
    status ENUM('stored','closed','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created',
    html_filename VARCHAR(255) DEFAULT NULL,
    content_hash CHAR(64) NOT NULL,
    snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(snapshot_json)),
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_report_versions_report (workspace_id, report_type, report_id, version, format),
    KEY idx_v2_report_versions_workspace (workspace_id, created_at),
    KEY idx_v2_report_versions_report_latest (report_type, report_id, created_at),
    CONSTRAINT fk_v2_report_versions_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_report_batch_html_snapshots (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    batch_id CHAR(36) NOT NULL,
    version INT UNSIGNED NOT NULL,
    status ENUM('stored','closed','superseded') NOT NULL DEFAULT 'stored',
    generated_at DATETIME NOT NULL,
    stored_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    html_filename VARCHAR(255) DEFAULT NULL,
    html_content LONGTEXT NOT NULL,
    html_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    html_hash CHAR(64) NOT NULL,
    source_batch_hash CHAR(64) NOT NULL,
    comment TEXT DEFAULT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_report_batch_html_snapshot_version (workspace_id, batch_id, version),
    KEY idx_v2_report_batch_html_snapshots_batch (batch_id, version),
    KEY idx_v2_report_batch_html_snapshots_workspace (workspace_id, created_at),
    CONSTRAINT fk_v2_report_batch_html_snapshots_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_report_batch_html_snapshots_batch FOREIGN KEY (batch_id) REFERENCES v2_report_batches (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_report_packages (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    package_type ENUM('operational_fragment_package') NOT NULL DEFAULT 'operational_fragment_package',
    title VARCHAR(190) NOT NULL,
    status ENUM('draft','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    fragment_count INT UNSIGNED NOT NULL DEFAULT 0,
    entry_count INT UNSIGNED NOT NULL DEFAULT 0,
    generated_at DATETIME NOT NULL,
    closed_at DATETIME DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    html_filename VARCHAR(255) DEFAULT NULL,
    summary_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(summary_json)),
    fragment_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(fragment_ids_json)),
    source_entry_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(source_entry_ids_json)),
    content_hash CHAR(64) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_report_packages_workspace (workspace_id, created_at),
    KEY idx_v2_report_packages_period (workspace_id, start_date, end_date),
    KEY idx_v2_report_packages_status (status),
    CONSTRAINT fk_v2_report_packages_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_report_package_items (
    id CHAR(36) NOT NULL,
    package_id CHAR(36) NOT NULL,
    batch_id CHAR(36) NOT NULL,
    html_snapshot_id CHAR(36) DEFAULT NULL,
    item_order INT UNSIGNED NOT NULL,
    fragment_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(fragment_snapshot_json)),
    html_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (html_snapshot_json IS NULL OR json_valid(html_snapshot_json)),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_report_package_item (package_id, batch_id),
    KEY idx_v2_report_package_items_batch (batch_id),
    KEY idx_v2_report_package_items_html_snapshot (html_snapshot_id),
    CONSTRAINT fk_v2_report_package_items_package FOREIGN KEY (package_id) REFERENCES v2_report_packages (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_report_package_items_batch FOREIGN KEY (batch_id) REFERENCES v2_report_batches (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_report_package_items_html_snapshot FOREIGN KEY (html_snapshot_id) REFERENCES v2_report_batch_html_snapshots (id)
        ON DELETE SET NULL ON UPDATE CASCADE
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

CREATE TABLE IF NOT EXISTS v2_dictionary_training_decisions (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    archive_workspace_id CHAR(36) DEFAULT NULL,
    source_id CHAR(36) DEFAULT NULL,
    source_row_id CHAR(36) DEFAULT NULL,
    decision_scope ENUM('row','group') NOT NULL DEFAULT 'row',
    group_key VARCHAR(190) DEFAULT NULL,
    source_row_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(source_row_ids_json)),
    decision_type ENUM('defer','reject_training','approve_existing_guess_local','correct_category_local','mark_semantic_blocked','propose_universal_candidate') NOT NULL,
    current_rule_guess VARCHAR(80) DEFAULT NULL,
    category_id CHAR(36) DEFAULT NULL,
    category_rule_id CHAR(36) DEFAULT NULL,
    pattern VARCHAR(255) DEFAULT NULL,
    pattern_type ENUM('keyword','phrase','regex','supplier','role') DEFAULT NULL,
    language ENUM('ru','en','it','es','de','bcms','multi') NOT NULL DEFAULT 'multi',
    weight INT DEFAULT NULL,
    negative_weight INT DEFAULT NULL,
    requires_any_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(requires_any_json)),
    excludes_any_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(excludes_any_json)),
    confidence DECIMAL(8,2) DEFAULT NULL,
    review_reason VARCHAR(80) DEFAULT NULL,
    blockers_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(blockers_json)),
    matched_signals_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(matched_signals_json)),
    semantic_markers_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(semantic_markers_json)),
    example_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(example_snapshot_json)),
    note TEXT DEFAULT NULL,
    decided_by BIGINT UNSIGNED DEFAULT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_v2_dictionary_training_source_row (workspace_id, source_row_id),
    KEY idx_v2_dictionary_training_workspace (workspace_id),
    KEY idx_v2_dictionary_training_archive (archive_workspace_id),
    KEY idx_v2_dictionary_training_source (source_id),
    KEY idx_v2_dictionary_training_row (source_row_id),
    KEY idx_v2_dictionary_training_decision (decision_type),
    CONSTRAINT fk_v2_dictionary_training_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_dictionary_training_archive FOREIGN KEY (archive_workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_v2_dictionary_training_source FOREIGN KEY (source_id) REFERENCES v2_import_sources (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_v2_dictionary_training_row FOREIGN KEY (source_row_id) REFERENCES v2_import_rows (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_v2_dictionary_training_category FOREIGN KEY (category_id) REFERENCES v2_categories (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_v2_dictionary_training_rule FOREIGN KEY (category_rule_id) REFERENCES v2_category_rules (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_workspace_assistant_settings (
    workspace_id CHAR(36) NOT NULL,
    mr_smith_enabled TINYINT(1) NOT NULL DEFAULT 0,
    internet_reference_mode ENUM('disabled','per_request','workspace_enabled') NOT NULL DEFAULT 'per_request',
    provider_key VARCHAR(80) NOT NULL DEFAULT 'stub',
    retention_days INT UNSIGNED NOT NULL DEFAULT 30,
    updated_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (workspace_id),
    CONSTRAINT fk_v2_assistant_settings_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_internet_reference_lookups (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    source_row_id CHAR(36) DEFAULT NULL,
    provider_key VARCHAR(80) NOT NULL DEFAULT 'stub',
    provider_request_id VARCHAR(120) DEFAULT NULL,
    consent_source ENUM('request','workspace_setting') NOT NULL,
    sanitized_query VARCHAR(190) NOT NULL,
    query_hash CHAR(64) NOT NULL,
    masked_fields_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(masked_fields_json)),
    result_status ENUM('stub','ok','error','timeout') NOT NULL DEFAULT 'stub',
    latency_ms INT UNSIGNED NOT NULL DEFAULT 0,
    matches_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(matches_json)),
    selected_match_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (selected_match_json IS NULL OR json_valid(selected_match_json)),
    no_financial_mutation TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retention_delete_after DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_v2_reference_lookups_workspace (workspace_id, created_at),
    KEY idx_v2_reference_lookups_hash (workspace_id, query_hash),
    KEY idx_v2_reference_lookups_source_row (source_row_id),
    CONSTRAINT fk_v2_reference_lookups_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_v2_reference_lookups_row FOREIGN KEY (source_row_id) REFERENCES v2_import_rows (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS v2_quick_notes (
    id CHAR(36) NOT NULL,
    workspace_id CHAR(36) NOT NULL,
    created_by BIGINT UNSIGNED DEFAULT NULL,
    note_date DATE NOT NULL,
    title VARCHAR(190) NOT NULL,
    raw_text TEXT NOT NULL,
    status ENUM('draft','reviewed','converted') NOT NULL DEFAULT 'draft',
    smith_preview_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (smith_preview_json IS NULL OR json_valid(smith_preview_json)),
    converted_at DATETIME DEFAULT NULL,
    archived_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_v2_quick_notes_workspace_status (workspace_id, status, note_date),
    KEY idx_v2_quick_notes_created_by (created_by),
    CONSTRAINT fk_v2_quick_notes_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
        ON DELETE CASCADE ON UPDATE CASCADE
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
SELECT UUID(), NULL, 'non_commercial_income', '{"ru":"Некоммерческие поступления","en":"Non-commercial income"}', 'income', 16, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'non_commercial_income');
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
SELECT UUID(), NULL, 'service_water', '{"ru":"Сервисные работы","en":"Service works"}', 'expense', 50, 1, 1
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
SELECT UUID(), NULL, 'guest_trip_support', '{"ru":"Обеспечение гостей в походе","en":"Guest trip support"}', 'expense', 92, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'guest_trip_support');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'guest_cash_issued', '{"ru":"Выданные наличные гостям","en":"Cash issued to guests"}', 'expense', 94, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'guest_cash_issued');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'representation_expenses', '{"ru":"Представительские расходы","en":"Representation expenses"}', 'expense', 95, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'representation_expenses');
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
SELECT UUID(), NULL, 'transport_expenses', '{"ru":"Транспортные расходы","en":"Transport expenses"}', 'expense', 125, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'transport_expenses');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'admin_legal', '{"ru":"Админка / документы","en":"Admin and legal"}', 'expense', 130, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'admin_legal');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'current_boat_expenses', '{"ru":"Текущие лодочные расходы","en":"Current boat expenses"}', 'expense', 135, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'current_boat_expenses');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'cash_topup_from_card', '{"ru":"Пополнение cash с карты","en":"Cash top-up from card"}', 'movement', 140, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'cash_topup_from_card');
INSERT INTO v2_categories (id, workspace_id, code, name_json, direction, sort_order, is_system, is_active)
SELECT UUID(), NULL, 'other', '{"ru":"Другие расходы","en":"Other expenses"}', 'expense', 999, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM v2_categories WHERE workspace_id IS NULL AND code = 'other');
