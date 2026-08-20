# Storage Model

Date: 2026-08-20

## Target

Use Supabase Storage private buckets for documents, receipts, invoices, and report exports.

PostgreSQL stores metadata. Binary files do not live in PostgreSQL as the normal path.

## Required Metadata

`documents`:

- `id`
- `organization_id`
- `workspace_id`
- `uploaded_by`
- `original_filename`
- `mime_type`
- `size_bytes`
- `storage_bucket`
- `storage_key`
- `checksum`
- `document_type`
- `document_date`
- `status`
- `linked_entity_type`
- `linked_entity_id`
- `created_at`
- `updated_at`
- `deleted_at`

`document_versions`:

- `id`
- `document_id`
- `version_number`
- `storage_key`
- `checksum`
- `created_by`
- `created_at`

`document_extractions`:

- `id`
- `document_id`
- `provider`
- `model`
- `prompt_or_schema_version`
- `raw_reference`
- `normalized_json`
- `confidence_json`
- `user_correction_json`
- `status`

## Access

Files are served through authorized signed URL flows.

No permanent public file URLs for financial documents.
