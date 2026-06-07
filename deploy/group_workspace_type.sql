ALTER TABLE groups
    ADD COLUMN workspace_type ENUM('team','yacht','home') NOT NULL DEFAULT 'team' AFTER description;

CREATE INDEX idx_groups_workspace_type ON groups (workspace_type);
