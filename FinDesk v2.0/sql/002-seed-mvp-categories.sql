-- FinDesk v2.0 MVP system category seed for MariaDB.
-- Categories are global by default: workspace_id is NULL.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO categories (id, workspace_id, code, name, direction, parent_code, sort_order, is_system, is_active)
VALUES
  ('20000000-0000-0000-0000-000000000001', NULL, 'crew', JSON_OBJECT('en', 'Crew'), 'expense', NULL, 10, 1, 1),
  ('20000000-0000-0000-0000-000000000002', NULL, 'commercial_income', JSON_OBJECT('en', 'Commercial income'), 'income', NULL, 20, 1, 1),
  ('20000000-0000-0000-0000-000000000003', NULL, 'dry_dock', JSON_OBJECT('en', 'Dry dock'), 'expense', NULL, 30, 1, 1),
  ('20000000-0000-0000-0000-000000000004', NULL, 'berth', JSON_OBJECT('en', 'Berth'), 'expense', NULL, 40, 1, 1),
  ('20000000-0000-0000-0000-000000000005', NULL, 'marina_ports', JSON_OBJECT('en', 'Marina and ports'), 'expense', NULL, 50, 1, 1),
  ('20000000-0000-0000-0000-000000000006', NULL, 'service_water', JSON_OBJECT('en', 'Service and water'), 'expense', NULL, 60, 1, 1),
  ('20000000-0000-0000-0000-000000000007', NULL, 'tech_parts', JSON_OBJECT('en', 'Technical parts'), 'expense', NULL, 70, 1, 1),
  ('20000000-0000-0000-0000-000000000008', NULL, 'tender', JSON_OBJECT('en', 'Tender'), 'expense', NULL, 80, 1, 1),
  ('20000000-0000-0000-0000-000000000009', NULL, 'fuel', JSON_OBJECT('en', 'Fuel'), 'expense', NULL, 90, 1, 1),
  ('20000000-0000-0000-0000-000000000010', NULL, 'provisions', JSON_OBJECT('en', 'Provisions'), 'expense', NULL, 100, 1, 1),
  ('20000000-0000-0000-0000-000000000011', NULL, 'interior', JSON_OBJECT('en', 'Interior'), 'expense', NULL, 110, 1, 1),
  ('20000000-0000-0000-0000-000000000012', NULL, 'cleaning', JSON_OBJECT('en', 'Cleaning'), 'expense', NULL, 120, 1, 1),
  ('20000000-0000-0000-0000-000000000013', NULL, 'media_comms', JSON_OBJECT('en', 'Media and communications'), 'expense', NULL, 130, 1, 1),
  ('20000000-0000-0000-0000-000000000014', NULL, 'admin_legal', JSON_OBJECT('en', 'Admin and legal'), 'expense', NULL, 140, 1, 1),
  ('20000000-0000-0000-0000-000000000015', NULL, 'cash_topup_from_card', JSON_OBJECT('en', 'Cash top-up from card'), 'movement', NULL, 150, 1, 1),
  ('20000000-0000-0000-0000-000000000016', NULL, 'other', JSON_OBJECT('en', 'Other'), 'expense', NULL, 160, 1, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  direction = VALUES(direction),
  parent_code = VALUES(parent_code),
  sort_order = VALUES(sort_order),
  is_system = VALUES(is_system),
  is_active = VALUES(is_active);
