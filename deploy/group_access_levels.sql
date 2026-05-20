ALTER TABLE group_members
    ADD COLUMN IF NOT EXISTS access_level ENUM('base','manager','advanced') NOT NULL DEFAULT 'base' AFTER role,
    ADD COLUMN IF NOT EXISTS permissions_json JSON DEFAULT NULL AFTER access_level,
    ADD COLUMN IF NOT EXISTS invited_by BIGINT UNSIGNED DEFAULT NULL AFTER permissions_json,
    ADD COLUMN IF NOT EXISTS invite_id BIGINT UNSIGNED DEFAULT NULL AFTER invited_by;

UPDATE group_members
SET access_level = 'advanced'
WHERE role = 'admin'
  AND access_level <> 'advanced';

ALTER TABLE group_invites
    ADD COLUMN IF NOT EXISTS invited_email VARCHAR(190) DEFAULT NULL AFTER token_hint,
    ADD COLUMN IF NOT EXISTS access_level ENUM('base','manager','advanced') NOT NULL DEFAULT 'base' AFTER invited_email,
    ADD COLUMN IF NOT EXISTS permissions_json JSON DEFAULT NULL AFTER access_level;

