CREATE TABLE IF NOT EXISTS ledger_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    group_id BIGINT UNSIGNED DEFAULT NULL,
    category_type ENUM('income','expense') NOT NULL,
    name VARCHAR(190) NOT NULL,
    color VARCHAR(30) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_categories_user (user_id),
    KEY idx_categories_group (group_id),
    KEY idx_categories_type (category_type),
    KEY idx_categories_default (is_default),
    CONSTRAINT fk_categories_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_categories_group FOREIGN KEY (group_id) REFERENCES groups(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ledger_entries
    ADD COLUMN IF NOT EXISTS category_id BIGINT UNSIGNED DEFAULT NULL AFTER money_type;

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'income', 'Charter', '#DFF5E7', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='income' AND name='Charter');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'income', 'Owner cash', '#DFF5E7', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='income' AND name='Owner cash');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'income', 'Management fee', '#DFF5E7', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='income' AND name='Management fee');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'income', 'Training', '#DFF5E7', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='income' AND name='Training');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'income', 'Delivery', '#DFF5E7', 50, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='income' AND name='Delivery');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'income', 'Other income', '#DFF5E7', 999, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='income' AND name='Other income');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Fuel', '#FFF1C7', 10, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Fuel');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Marina', '#FFF1C7', 20, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Marina');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Service', '#FFF1C7', 30, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Service');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Parts', '#FFF1C7', 40, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Parts');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Provisioning', '#FFF1C7', 50, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Provisioning');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Transport', '#FFF1C7', 60, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Transport');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Crew', '#FFF1C7', 70, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Crew');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Documents', '#FFF1C7', 80, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Documents');

INSERT INTO ledger_categories (category_type, name, color, sort_order, is_default)
SELECT 'expense', 'Other expense', '#FFF1C7', 999, 1
WHERE NOT EXISTS (SELECT 1 FROM ledger_categories WHERE user_id IS NULL AND group_id IS NULL AND category_type='expense' AND name='Other expense');
