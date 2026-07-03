-- FinDesk v2.0 Clean Core MVP schema for MariaDB 11.4+
-- Source of truth: entries operational journal.
-- Legacy FinDesk finance tables are intentionally not referenced here.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workspaces (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  name VARCHAR(255) NOT NULL,
  type VARCHAR(32) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  locale VARCHAR(16) NOT NULL DEFAULT 'ru',
  created_by CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  archived_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  CONSTRAINT chk_workspaces_type CHECK (type IN ('yacht', 'family', 'personal', 'business', 'trip', 'custom'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS workspace_members (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  role VARCHAR(32) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_workspace_members_workspace_user (workspace_id, user_id),
  KEY idx_workspace_members_user (user_id),
  CONSTRAINT fk_workspace_members_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT chk_workspace_members_role CHECK (role IN ('owner', 'admin', 'assistant', 'viewer'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flows (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(32) NOT NULL,
  has_live_balance TINYINT(1) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_flows_workspace_name (workspace_id, name),
  KEY idx_flows_workspace_type (workspace_id, type),
  CONSTRAINT fk_flows_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT chk_flows_type CHECK (type IN ('cash', 'card', 'assistant_journal')),
  CONSTRAINT chk_flows_has_live_balance CHECK (has_live_balance IN (0, 1)),
  CONSTRAINT chk_flows_is_default CHECK (is_default IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  workspace_scope_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci
    GENERATED ALWAYS AS (COALESCE(workspace_id, '00000000-0000-0000-0000-000000000000')) STORED,
  code VARCHAR(64) NOT NULL,
  name LONGTEXT NOT NULL,
  direction VARCHAR(16) NOT NULL DEFAULT 'expense',
  parent_code VARCHAR(64) NULL,
  sort_order INT NOT NULL DEFAULT 100,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_scope_code (workspace_scope_id, code),
  KEY idx_categories_workspace (workspace_id),
  KEY idx_categories_code (code),
  CONSTRAINT fk_categories_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT chk_categories_name_json CHECK (JSON_VALID(name)),
  CONSTRAINT chk_categories_direction CHECK (direction IN ('income', 'expense', 'movement', 'mixed')),
  CONSTRAINT chk_categories_is_system CHECK (is_system IN (0, 1)),
  CONSTRAINT chk_categories_is_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS actors (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  name VARCHAR(255) NOT NULL,
  actor_type VARCHAR(32) NOT NULL DEFAULT 'unknown',
  aliases LONGTEXT NOT NULL DEFAULT ('[]'),
  notes TEXT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  UNIQUE KEY uq_actors_workspace_name (workspace_id, name),
  CONSTRAINT fk_actors_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT chk_actors_type CHECK (actor_type IN ('person', 'role', 'supplier', 'company', 'unknown')),
  CONSTRAINT chk_actors_aliases_json CHECK (JSON_VALID(aliases))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_sources (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  source_type VARCHAR(32) NOT NULL,
  file_name VARCHAR(255) NULL,
  file_url TEXT NULL,
  file_id VARCHAR(255) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pending',
  include_decision VARCHAR(32) NOT NULL DEFAULT 'manual_review',
  reason TEXT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_import_sources_workspace (workspace_id),
  CONSTRAINT fk_import_sources_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT chk_import_sources_type CHECK (source_type IN ('google_drive', 'excel', 'legacy_db', 'manual_upload')),
  CONSTRAINT chk_import_sources_include_decision CHECK (include_decision IN ('included', 'excluded_by_title_marker', 'excluded_duplicate', 'included_partially', 'manual_review'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entries (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  flow_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  created_by CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  date DATE NOT NULL,
  raw_text TEXT NOT NULL,
  description TEXT NULL,
  sign VARCHAR(1) NULL,
  amount DECIMAL(14,2) NULL,
  direction VARCHAR(8) NOT NULL DEFAULT 'none',
  entry_type VARCHAR(32) NOT NULL,
  category_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  status VARCHAR(32) NOT NULL,
  balance_after DECIMAL(14,2) NULL,
  source_type VARCHAR(32) NOT NULL DEFAULT 'manual',
  source_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  source_row_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  notes TEXT NULL,
  confidence DECIMAL(4,3) NULL,
  matched_rules LONGTEXT NOT NULL DEFAULT ('[]'),
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  archived_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  KEY idx_entries_workspace_date (workspace_id, date),
  KEY idx_entries_flow_date (flow_id, date),
  KEY idx_entries_status (status),
  KEY idx_entries_category (category_id),
  KEY idx_entries_source (source_id),
  CONSTRAINT fk_entries_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_entries_flow FOREIGN KEY (flow_id) REFERENCES flows(id) ON DELETE RESTRICT,
  CONSTRAINT fk_entries_actor FOREIGN KEY (actor_id) REFERENCES actors(id) ON DELETE SET NULL,
  CONSTRAINT fk_entries_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_entries_source FOREIGN KEY (source_id) REFERENCES import_sources(id) ON DELETE SET NULL,
  CONSTRAINT chk_entries_sign CHECK (sign IN ('+', '-') OR sign IS NULL),
  CONSTRAINT chk_entries_amount CHECK (amount IS NULL OR amount >= 0),
  CONSTRAINT chk_entries_direction CHECK (direction IN ('in', 'out', 'none')),
  CONSTRAINT chk_entries_type CHECK (entry_type IN ('cash_income', 'cash_expense', 'card_expense', 'card_income', 'opening_balance', 'correction', 'info', 'unrecognized', 'assistant_pending')),
  CONSTRAINT chk_entries_status CHECK (status IN ('recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect')),
  CONSTRAINT chk_entries_source_type CHECK (source_type IN ('manual', 'import', 'assistant', 'correction')),
  CONSTRAINT chk_entries_confidence CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 1)),
  CONSTRAINT chk_entries_matched_rules_json CHECK (JSON_VALID(matched_rules))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_rules (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  category_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  pattern VARCHAR(255) NOT NULL,
  pattern_type VARCHAR(32) NOT NULL DEFAULT 'keyword',
  language VARCHAR(16) NOT NULL DEFAULT 'multi',
  weight INT NOT NULL DEFAULT 10,
  negative_weight INT NOT NULL DEFAULT 0,
  requires_any LONGTEXT NOT NULL DEFAULT ('[]'),
  excludes_any LONGTEXT NOT NULL DEFAULT ('[]'),
  created_by_user TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_category_rules_workspace (workspace_id),
  KEY idx_category_rules_category (category_id),
  KEY idx_category_rules_pattern (pattern),
  CONSTRAINT fk_category_rules_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT fk_category_rules_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT chk_category_rules_pattern_type CHECK (pattern_type IN ('keyword', 'phrase', 'regex', 'supplier', 'role')),
  CONSTRAINT chk_category_rules_language CHECK (language IN ('ru', 'en', 'it', 'es', 'de', 'bcms', 'multi')),
  CONSTRAINT chk_category_rules_created_by_user CHECK (created_by_user IN (0, 1)),
  CONSTRAINT chk_category_rules_is_active CHECK (is_active IN (0, 1)),
  CONSTRAINT chk_category_rules_requires_json CHECK (JSON_VALID(requires_any)),
  CONSTRAINT chk_category_rules_excludes_json CHECK (JSON_VALID(excludes_any))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_url TEXT NOT NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes BIGINT NULL,
  image_mode VARCHAR(32) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_attachments_entry (entry_id),
  CONSTRAINT fk_attachments_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE CASCADE,
  CONSTRAINT chk_attachments_size CHECK (size_bytes IS NULL OR size_bytes >= 0),
  CONSTRAINT chk_attachments_image_mode CHECK (image_mode IN ('original', 'compressed', 'grayscale_scan') OR image_mode IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS monthly_closures (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  year SMALLINT NOT NULL,
  month TINYINT NOT NULL,
  opening_balance DECIMAL(14,2) NULL,
  closing_balance DECIMAL(14,2) NULL,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  comment TEXT NULL,
  closed_by CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  closed_at DATETIME(6) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_monthly_closures_workspace_period (workspace_id, year, month),
  CONSTRAINT fk_monthly_closures_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT chk_monthly_closures_month CHECK (month BETWEEN 1 AND 12),
  CONSTRAINT chk_monthly_closures_is_closed CHECK (is_closed IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_rows (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  import_source_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  sheet_name VARCHAR(255) NULL,
  row_number INT NULL,
  raw_json LONGTEXT NOT NULL,
  entry_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  parse_status VARCHAR(32) NOT NULL DEFAULT 'pending',
  parse_notes TEXT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_import_rows_source (import_source_id),
  KEY idx_import_rows_entry (entry_id),
  CONSTRAINT fk_import_rows_source FOREIGN KEY (import_source_id) REFERENCES import_sources(id) ON DELETE CASCADE,
  CONSTRAINT fk_import_rows_entry FOREIGN KEY (entry_id) REFERENCES entries(id) ON DELETE SET NULL,
  CONSTRAINT chk_import_rows_raw_json CHECK (JSON_VALID(raw_json)),
  CONSTRAINT chk_import_rows_parse_status CHECK (parse_status IN ('pending', 'parsed', 'unrecognized', 'excluded', 'duplicate_suspect', 'error'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT (UUID()),
  workspace_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  action VARCHAR(64) NOT NULL,
  before_json LONGTEXT NULL,
  after_json LONGTEXT NULL,
  performed_by CHAR(36) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (id),
  KEY idx_audit_log_workspace_created (workspace_id, created_at),
  KEY idx_audit_log_entity (entity_type, entity_id),
  CONSTRAINT fk_audit_log_workspace FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
  CONSTRAINT chk_audit_log_before_json CHECK (before_json IS NULL OR JSON_VALID(before_json)),
  CONSTRAINT chk_audit_log_after_json CHECK (after_json IS NULL OR JSON_VALID(after_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
