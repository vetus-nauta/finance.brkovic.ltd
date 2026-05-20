CREATE TABLE IF NOT EXISTS company_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    profile_name VARCHAR(190) NOT NULL DEFAULT 'Default company',
    company_name VARCHAR(190) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    country VARCHAR(120) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(80) DEFAULT NULL,
    website VARCHAR(190) DEFAULT NULL,
    registration_number VARCHAR(120) DEFAULT NULL,
    vat_number VARCHAR(120) DEFAULT NULL,
    default_vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    default_discount_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    notes TEXT DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_company_profiles_user (user_id),
    KEY idx_company_profiles_default (user_id, is_default),
    CONSTRAINT fk_company_profiles_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    client_name VARCHAR(190) NOT NULL,
    contact_person VARCHAR(190) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(80) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    city VARCHAR(120) DEFAULT NULL,
    country VARCHAR(120) DEFAULT NULL,
    registration_number VARCHAR(120) DEFAULT NULL,
    vat_number VARCHAR(120) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_clients_user (user_id),
    KEY idx_clients_name (client_name),
    CONSTRAINT fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proformas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    company_profile_id BIGINT UNSIGNED DEFAULT NULL,
    client_id BIGINT UNSIGNED DEFAULT NULL,
    proforma_number VARCHAR(80) NOT NULL,
    title VARCHAR(190) NOT NULL DEFAULT 'Proforma',
    issue_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    discount_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','issued','archived') NOT NULL DEFAULT 'draft',
    public_note TEXT DEFAULT NULL,
    internal_note TEXT DEFAULT NULL,
    fiscal_note VARCHAR(255) NOT NULL DEFAULT 'This document is a proforma offer and is not a fiscal invoice.',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proforma_user_number (user_id, proforma_number),
    KEY idx_proformas_user (user_id),
    KEY idx_proformas_company (company_profile_id),
    KEY idx_proformas_client (client_id),
    KEY idx_proformas_status (status),
    KEY idx_proformas_issue_date (issue_date),
    CONSTRAINT fk_proformas_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_proformas_company FOREIGN KEY (company_profile_id) REFERENCES company_profiles(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_proformas_client FOREIGN KEY (client_id) REFERENCES clients(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proforma_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    proforma_id BIGINT UNSIGNED NOT NULL,
    item_order INT NOT NULL DEFAULT 1,
    item_name VARCHAR(255) NOT NULL,
    item_description TEXT DEFAULT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    unit_name VARCHAR(40) NOT NULL DEFAULT 'pcs',
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_proforma_items_proforma (proforma_id),
    KEY idx_proforma_items_order (proforma_id, item_order),
    CONSTRAINT fk_proforma_items_proforma FOREIGN KEY (proforma_id) REFERENCES proformas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
