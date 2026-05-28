CREATE TABLE IF NOT EXISTS on_the_go_captures (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  capture_type ENUM('cash_in','cash_out','noncash_out') NOT NULL,
  amount DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  description VARCHAR(255) NULL,
  review_status ENUM('needs_review','reviewed','archived') NOT NULL DEFAULT 'needs_review',
  reportable TINYINT(1) NOT NULL DEFAULT 0,
  recognition_status ENUM('none','pending','processed','failed') NOT NULL DEFAULT 'none',
  recognized_amount DECIMAL(12,2) NULL,
  recognized_currency CHAR(3) NULL,
  recognized_date DATE NULL,
  recognized_vendor VARCHAR(255) NULL,
  recognized_text MEDIUMTEXT NULL,
  recognition_confidence DECIMAL(5,2) NULL,
  recognition_error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_user_status_created (user_id, review_status, created_at),
  KEY idx_user_type_created (user_id, capture_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS on_the_go_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  capture_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  proof_role VARCHAR(40) NOT NULL DEFAULT 'attachment',
  proof_bundle_id VARCHAR(120) NULL,
  source_file_id BIGINT UNSIGNED NULL,
  file_hash_sha256 CHAR(64) NULL,
  metadata_json MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_capture (capture_id),
  KEY idx_user_created (user_id, created_at),
  KEY idx_otr_files_bundle (proof_bundle_id),
  KEY idx_otr_files_role (proof_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE on_the_go_files
  ADD COLUMN IF NOT EXISTS proof_role VARCHAR(40) NOT NULL DEFAULT 'attachment' AFTER size_bytes,
  ADD COLUMN IF NOT EXISTS proof_bundle_id VARCHAR(120) DEFAULT NULL AFTER proof_role,
  ADD COLUMN IF NOT EXISTS source_file_id BIGINT UNSIGNED DEFAULT NULL AFTER proof_bundle_id,
  ADD COLUMN IF NOT EXISTS file_hash_sha256 CHAR(64) DEFAULT NULL AFTER source_file_id,
  ADD COLUMN IF NOT EXISTS metadata_json MEDIUMTEXT NULL AFTER file_hash_sha256,
  ADD KEY IF NOT EXISTS idx_otr_files_bundle (proof_bundle_id),
  ADD KEY IF NOT EXISTS idx_otr_files_role (proof_role);
