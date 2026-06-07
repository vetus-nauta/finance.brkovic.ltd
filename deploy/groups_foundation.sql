CREATE TABLE IF NOT EXISTS groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    description TEXT DEFAULT NULL,
    workspace_type ENUM('team','yacht','home') NOT NULL DEFAULT 'team',
    created_by BIGINT UNSIGNED NOT NULL,
    status ENUM('active','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_groups_created_by (created_by),
    KEY idx_groups_status (status),
    KEY idx_groups_workspace_type (workspace_type),
    CONSTRAINT fk_groups_created_by FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    display_name VARCHAR(190) DEFAULT NULL,
    role ENUM('admin','member') NOT NULL DEFAULT 'member',
    access_level ENUM('base','manager','advanced') NOT NULL DEFAULT 'base',
    permissions_json JSON DEFAULT NULL,
    invited_by BIGINT UNSIGNED DEFAULT NULL,
    invite_id BIGINT UNSIGNED DEFAULT NULL,
    status ENUM('active','left','removed') NOT NULL DEFAULT 'active',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    left_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_group_user (group_id, user_id),
    KEY idx_group_members_group (group_id),
    KEY idx_group_members_user (user_id),
    KEY idx_group_members_role (role),
    CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES groups(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_invites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    invited_by BIGINT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    token_hint VARCHAR(24) DEFAULT NULL,
    invited_email VARCHAR(190) DEFAULT NULL,
    access_level ENUM('base','manager','advanced') NOT NULL DEFAULT 'base',
    permissions_json JSON DEFAULT NULL,
    channel ENUM('email','whatsapp','viber','telegram','copy','qr') NOT NULL DEFAULT 'copy',
    max_uses INT UNSIGNED NOT NULL DEFAULT 1,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    status ENUM('active','used','revoked','expired') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_group_invite_token_hash (token_hash),
    KEY idx_group_invites_group (group_id),
    KEY idx_group_invites_status (status),
    CONSTRAINT fk_group_invites_group FOREIGN KEY (group_id) REFERENCES groups(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_group_invites_by FOREIGN KEY (invited_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invite_share_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invite_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('email','whatsapp','viber','telegram','copy','qr') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_invite_share_invite (invite_id),
    KEY idx_invite_share_user (user_id),
    CONSTRAINT fk_invite_share_invite FOREIGN KEY (invite_id) REFERENCES group_invites(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_invite_share_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ledger_entries
    ADD COLUMN IF NOT EXISTS group_id BIGINT UNSIGNED DEFAULT NULL AFTER user_id;
