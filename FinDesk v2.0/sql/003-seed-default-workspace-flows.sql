-- FinDesk v2.0 default workspace flow seed for MariaDB.
-- Optional before running:
--   SET @findesk_seed_workspace_id = 'your-workspace-uuid';
-- If no workspace id is provided, an idempotent Sprint 02 acceptance workspace is created.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @findesk_seed_workspace_id = COALESCE(
  @findesk_seed_workspace_id,
  '20000000-0000-0000-0000-000000000100'
);

INSERT INTO workspaces (id, name, type, currency, locale)
VALUES (@findesk_seed_workspace_id, 'Sprint 02 Acceptance Workspace', 'custom', 'EUR', 'ru')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  type = VALUES(type),
  currency = VALUES(currency),
  locale = VALUES(locale);

INSERT INTO flows (workspace_id, name, type, has_live_balance, is_default)
VALUES
  (@findesk_seed_workspace_id, 'Cash', 'cash', 1, 1),
  (@findesk_seed_workspace_id, 'Card', 'card', 0, 1),
  (@findesk_seed_workspace_id, 'Assistant Journal', 'assistant_journal', 0, 0)
ON DUPLICATE KEY UPDATE
  type = VALUES(type),
  has_live_balance = VALUES(has_live_balance),
  is_default = VALUES(is_default);
