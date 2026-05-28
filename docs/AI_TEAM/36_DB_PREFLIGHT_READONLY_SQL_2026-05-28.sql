-- FinDesk deploy preflight, read-only.
-- Candidate: docs/AI_TEAM/34_LIMITED_SCANNER_UX_BACKEND_DEPLOY_CANDIDATE_2026-05-28.md
-- Purpose: verify production schema before scanner/UX/backend upload.
-- This file must not mutate data.

SELECT
  DATABASE() AS database_name,
  VERSION() AS db_version,
  @@version_comment AS version_comment;

SELECT
  table_name,
  column_name,
  column_type,
  is_nullable,
  column_default
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'on_the_go_files' AND column_name IN (
      'proof_role',
      'proof_bundle_id',
      'source_file_id',
      'file_hash_sha256',
      'metadata_json'
    ))
    OR
    (table_name = 'on_the_go_upload_states' AND column_name IN (
      'proof_role',
      'proof_bundle_id',
      'file_hash_sha256',
      'metadata_json'
    ))
    OR
    (table_name = 'groups' AND column_name IN (
      'status',
      'archived_at',
      'updated_at'
    ))
    OR
    (table_name = 'group_members' AND column_name IN (
      'status',
      'left_at',
      'updated_at'
    ))
    OR
    (table_name = 'group_invites' AND column_name IN (
      'status',
      'revoked_at',
      'updated_at'
    ))
  )
ORDER BY table_name, column_name;

SELECT
  'on_the_go_files' AS table_name,
  SUM(column_name = 'proof_role') AS has_proof_role,
  SUM(column_name = 'proof_bundle_id') AS has_proof_bundle_id,
  SUM(column_name = 'source_file_id') AS has_source_file_id,
  SUM(column_name = 'file_hash_sha256') AS has_file_hash_sha256,
  SUM(column_name = 'metadata_json') AS has_metadata_json
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'on_the_go_files';

SELECT
  'on_the_go_upload_states' AS table_name,
  SUM(column_name = 'proof_role') AS has_proof_role,
  SUM(column_name = 'proof_bundle_id') AS has_proof_bundle_id,
  SUM(column_name = 'file_hash_sha256') AS has_file_hash_sha256,
  SUM(column_name = 'metadata_json') AS has_metadata_json
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'on_the_go_upload_states';

SELECT
  table_name,
  index_name,
  column_name,
  non_unique
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'on_the_go_files' AND index_name IN (
      'idx_otr_files_bundle',
      'idx_otr_files_role'
    ))
  )
ORDER BY table_name, index_name, seq_in_index;

SELECT
  'groups_optional_columns' AS check_name,
  SUM(column_name = 'archived_at') AS has_archived_at,
  SUM(column_name = 'updated_at') AS has_updated_at
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'groups';

SELECT
  'group_invites_optional_columns' AS check_name,
  SUM(column_name = 'revoked_at') AS has_revoked_at,
  SUM(column_name = 'updated_at') AS has_updated_at
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'group_invites';

SELECT
  'group_members_optional_columns' AS check_name,
  SUM(column_name = 'left_at') AS has_left_at,
  SUM(column_name = 'updated_at') AS has_updated_at
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'group_members';
