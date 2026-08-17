-- FinDesk v2.0 Clean Core MVP schema
-- Source of truth: entries operational journal
-- Summary reports are generated from entries

create extension if not exists pgcrypto;

create table if not exists workspaces (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  type text not null check (type in ('yacht', 'family', 'personal', 'business', 'trip', 'custom')),
  currency text not null default 'EUR',
  locale text not null default 'ru',
  created_by uuid,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  archived_at timestamptz
);

create table if not exists workspace_members (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  user_id uuid not null,
  role text not null check (role in ('owner', 'admin', 'assistant', 'finance', 'employee', 'viewer')),
  access_scope text not null default 'workspace' check (access_scope in ('workspace', 'own_entries', 'assigned_actor', 'none')),
  assigned_actor_id uuid,
  created_at timestamptz not null default now(),
  unique (workspace_id, user_id)
);

create index if not exists idx_workspace_members_assigned_actor on workspace_members(assigned_actor_id);

create table if not exists workspace_liability_openings (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  liability_type text not null default 'admin_debt' check (liability_type in ('admin_debt')),
  counterparty text not null,
  amount numeric(14,2) not null,
  currency text not null default 'EUR',
  basis_date date not null,
  title text not null,
  note text,
  source_json jsonb,
  created_by uuid,
  created_at timestamptz not null default now(),
  archived_at timestamptz
);

create index if not exists idx_liability_openings_workspace on workspace_liability_openings(workspace_id, liability_type, archived_at, basis_date);

create table if not exists workspace_invites (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  token_hash text not null unique,
  token_hint text not null,
  invited_email text,
  invited_name text,
  role text not null default 'employee' check (role in ('employee')),
  access_scope text not null default 'own_entries' check (access_scope in ('own_entries')),
  status text not null default 'pending' check (status in ('pending', 'accepted', 'revoked', 'expired')),
  expires_at timestamptz not null,
  accepted_at timestamptz,
  accepted_by uuid,
  revoked_at timestamptz,
  revoked_by uuid,
  created_by uuid,
  created_at timestamptz not null default now()
);

create index if not exists idx_workspace_invites_workspace on workspace_invites(workspace_id, status, created_at);
create index if not exists idx_workspace_invites_email on workspace_invites(invited_email);

create table if not exists accountable_offers (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  employee_user_id uuid,
  employee_email text not null,
  amount numeric(14,2) not null,
  currency text not null default 'EUR',
  purpose text,
  status text not null default 'pending_offer' check (status in ('pending_offer', 'accepted_by_employee', 'cancelled')),
  created_by uuid,
  created_at timestamptz not null default now(),
  accepted_at timestamptz,
  accepted_by uuid,
  cancelled_at timestamptz,
  cancelled_by uuid,
  no_financial_mutation boolean not null default true,
  updated_at timestamptz
);

create index if not exists idx_accountable_offers_workspace on accountable_offers(workspace_id, status, created_at);
create index if not exists idx_accountable_offers_employee_user on accountable_offers(employee_user_id);
create index if not exists idx_accountable_offers_employee_email on accountable_offers(employee_email);

create table if not exists accountable_reports (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  offer_id uuid not null references accountable_offers(id) on delete restrict,
  employee_user_id uuid not null,
  title text not null,
  status text not null default 'draft' check (status in ('draft', 'submitted', 'accepted_by_admin', 'rework_requested', 'rejected', 'cancelled')),
  currency text not null default 'EUR',
  total_amount numeric(14,2) not null default 0.00,
  row_count integer not null default 0,
  submitted_at timestamptz,
  submitted_by uuid,
  reviewed_at timestamptz,
  reviewed_by uuid,
  review_note text,
  accepted_total_amount numeric(14,2) not null default 0.00,
  rejected_total_amount numeric(14,2) not null default 0.00,
  accepted_cash_expenses numeric(14,2) not null default 0.00,
  accepted_noncash_expenses numeric(14,2) not null default 0.00,
  settlement_status text check (settlement_status is null or settlement_status in ('pending', 'closed', 'return_due', 'reimburse_due', 'discrepancy')),
  materialized_at timestamptz,
  ledger_materialization_status text not null default 'not_materialized' check (ledger_materialization_status in ('not_materialized', 'materialized', 'partial', 'revoked')),
  ledger_materialized_at timestamptz,
  ledger_materialized_by uuid,
  ledger_materialization_hash text,
  created_by uuid,
  created_at timestamptz not null default now(),
  updated_at timestamptz,
  no_financial_mutation boolean not null default true
);

create index if not exists idx_accountable_reports_workspace on accountable_reports(workspace_id, status, created_at);
create index if not exists idx_accountable_reports_offer on accountable_reports(offer_id);
create index if not exists idx_accountable_reports_employee on accountable_reports(workspace_id, employee_user_id, status);

create table if not exists accountable_report_rows (
  id uuid primary key default gen_random_uuid(),
  report_id uuid not null references accountable_reports(id) on delete cascade,
  row_number integer not null,
  expense_date date not null,
  description text not null,
  amount numeric(14,2) not null,
  currency text not null default 'EUR',
  category_code text,
  notes text,
  review_status text not null default 'pending_review' check (review_status in ('pending_review', 'accepted', 'adjusted', 'rejected')),
  accepted_amount numeric(14,2),
  accepted_category_code text,
  payment_method text check (payment_method is null or payment_method in ('cash', 'card', 'noncash', 'own_funds')),
  review_note text,
  operational_entry_id uuid,
  created_at timestamptz not null default now(),
  unique (report_id, row_number)
);

create index if not exists idx_accountable_report_rows_report on accountable_report_rows(report_id);

create table if not exists accountable_settlements (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  offer_id uuid not null references accountable_offers(id) on delete restrict,
  report_id uuid not null references accountable_reports(id) on delete restrict,
  employee_user_id uuid not null,
  issued_amount numeric(14,2) not null default 0.00,
  accepted_cash_expenses numeric(14,2) not null default 0.00,
  accepted_noncash_expenses numeric(14,2) not null default 0.00,
  expected_remaining numeric(14,2) not null default 0.00,
  actual_remaining numeric(14,2) not null default 0.00,
  return_due_amount numeric(14,2) not null default 0.00,
  reimburse_due_amount numeric(14,2) not null default 0.00,
  difference_amount numeric(14,2) not null default 0.00,
  status text not null check (status in ('closed', 'return_due', 'reimburse_due', 'discrepancy')),
  created_by uuid,
  created_at timestamptz not null default now(),
  updated_at timestamptz,
  unique (report_id)
);

create index if not exists idx_accountable_settlements_workspace on accountable_settlements(workspace_id, status, created_at);
create index if not exists idx_accountable_settlements_offer on accountable_settlements(offer_id);

create table if not exists flows (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text not null,
  type text not null check (type in ('cash', 'card', 'assistant_journal', 'accountable')),
  has_live_balance boolean not null default false,
  is_default boolean not null default false,
  created_at timestamptz not null default now()
);

create table if not exists categories (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid references workspaces(id) on delete cascade,
  code text not null,
  name jsonb not null,
  direction text not null default 'expense' check (direction in ('income', 'expense', 'movement', 'mixed')),
  parent_code text,
  sort_order integer not null default 100,
  is_system boolean not null default false,
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  unique (workspace_id, code)
);

create table if not exists actors (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text not null,
  actor_type text not null check (actor_type in ('person', 'role', 'supplier', 'company', 'unknown')) default 'unknown',
  aliases jsonb not null default '[]'::jsonb,
  notes text,
  created_at timestamptz not null default now(),
  unique (workspace_id, name)
);

create table if not exists import_sources (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  source_type text not null check (source_type in ('google_drive', 'excel', 'legacy_db', 'manual_upload', 'quick_note')),
  file_name text,
  file_url text,
  file_id text,
  status text not null default 'pending',
  include_decision text not null default 'manual_review' check (include_decision in ('included', 'excluded_by_title_marker', 'excluded_duplicate', 'included_partially', 'manual_review')),
  reason text,
  created_at timestamptz not null default now()
);

create table if not exists entries (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  flow_id uuid not null references flows(id) on delete restrict,
  created_by uuid,
  actor_id uuid references actors(id) on delete set null,
  date date not null,
  raw_text text not null,
  sign text check (sign in ('+', '-') or sign is null),
  amount numeric(14,2),
  direction text not null check (direction in ('in', 'out', 'none')) default 'none',
  entry_type text not null check (entry_type in ('cash_income', 'cash_expense', 'card_expense', 'card_income', 'opening_balance', 'correction', 'info', 'unrecognized', 'assistant_pending', 'accountable_expense')),
  category_id uuid references categories(id) on delete set null,
  status text not null check (status in ('recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect')),
  balance_after numeric(14,2),
  source_type text not null default 'manual' check (source_type in ('manual', 'import', 'assistant', 'correction', 'accountable_report')),
  source_id uuid references import_sources(id) on delete set null,
  source_row_id uuid,
  notes text,
  confidence numeric(4,3),
  matched_rules jsonb not null default '[]'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  archived_at timestamptz
);

create index if not exists idx_entries_workspace_date on entries(workspace_id, date);
create index if not exists idx_entries_flow_date on entries(flow_id, date);
create index if not exists idx_entries_status on entries(status);
create index if not exists idx_entries_category on entries(category_id);

create table if not exists accountable_report_entry_links (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  report_id uuid not null references accountable_reports(id) on delete cascade,
  report_row_id uuid not null references accountable_report_rows(id) on delete cascade,
  entry_id uuid not null references entries(id) on delete restrict,
  idempotency_key text not null,
  cash_effect text not null default 'none' check (cash_effect in ('none')),
  payment_method text not null check (payment_method in ('cash', 'card', 'noncash', 'own_funds')),
  accepted_amount numeric(14,2) not null,
  category_code text not null,
  created_by uuid,
  created_at timestamptz not null default now(),
  unique (report_row_id),
  unique (entry_id),
  unique (idempotency_key)
);

create index if not exists idx_accountable_report_entry_links_workspace on accountable_report_entry_links(workspace_id, created_at);
create index if not exists idx_accountable_report_entry_links_report on accountable_report_entry_links(report_id);

create table if not exists category_rules (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid references workspaces(id) on delete cascade,
  category_id uuid not null references categories(id) on delete cascade,
  pattern text not null,
  pattern_type text not null check (pattern_type in ('keyword', 'phrase', 'regex', 'supplier', 'role')) default 'keyword',
  language text not null default 'multi' check (language in ('ru', 'en', 'it', 'es', 'de', 'bcms', 'multi')),
  weight integer not null default 10,
  negative_weight integer not null default 0,
  requires_any jsonb not null default '[]'::jsonb,
  excludes_any jsonb not null default '[]'::jsonb,
  created_by_user boolean not null default false,
  is_active boolean not null default true,
  created_at timestamptz not null default now()
);

create table if not exists attachments (
  id uuid primary key default gen_random_uuid(),
  entry_id uuid not null references entries(id) on delete cascade,
  file_name text not null,
  file_url text not null,
  mime_type text,
  size_bytes bigint,
  image_mode text check (image_mode in ('original', 'compressed', 'grayscale_scan') or image_mode is null),
  created_at timestamptz not null default now()
);

create table if not exists monthly_closures (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  year integer not null,
  month integer not null check (month between 1 and 12),
  opening_balance numeric(14,2),
  closing_balance numeric(14,2),
  is_closed boolean not null default false,
  comment text,
  closed_by uuid,
  closed_at timestamptz,
  unique (workspace_id, year, month)
);

create table if not exists report_batches (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  batch_type text not null default 'operational_fragment' check (batch_type in ('operational_fragment')),
  title text not null,
  status text not null default 'created' check (status in ('draft', 'created', 'sent', 'superseded')),
  start_date date not null,
  end_date date not null,
  from_entry_id uuid references entries(id) on delete set null,
  to_entry_id uuid references entries(id) on delete set null,
  entry_count integer not null default 0,
  generated_at timestamptz not null,
  closed_at timestamptz,
  html_filename text,
  summary_json jsonb not null,
  source_trace_json jsonb not null,
  source_entry_ids_json jsonb not null,
  entry_snapshot_json jsonb not null,
  content_hash char(64) not null,
  created_by uuid,
  created_at timestamptz not null default now(),
  updated_at timestamptz
);

create index if not exists idx_report_batches_workspace on report_batches(workspace_id, created_at);
create index if not exists idx_report_batches_period on report_batches(workspace_id, start_date, end_date);
create index if not exists idx_report_batches_status on report_batches(status);

create table if not exists report_batch_entries (
  id uuid primary key default gen_random_uuid(),
  batch_id uuid not null references report_batches(id) on delete cascade,
  entry_id uuid not null references entries(id) on delete cascade,
  row_number integer not null,
  entry_snapshot_json jsonb not null,
  created_at timestamptz not null default now(),
  unique (batch_id, entry_id)
);

create index if not exists idx_report_batch_entries_entry on report_batch_entries(entry_id);

create table if not exists report_batch_html_snapshots (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  batch_id uuid not null references report_batches(id) on delete cascade,
  version integer not null,
  status text not null default 'stored' check (status in ('stored', 'closed', 'superseded')),
  generated_at timestamptz not null,
  stored_at timestamptz not null default now(),
  html_filename text,
  html_content text not null,
  html_size_bytes bigint not null default 0,
  html_hash char(64) not null,
  source_batch_hash char(64) not null,
  comment text,
  created_by uuid,
  created_at timestamptz not null default now(),
  unique (workspace_id, batch_id, version)
);

create index if not exists idx_report_batch_html_snapshots_batch on report_batch_html_snapshots(batch_id, version);
create index if not exists idx_report_batch_html_snapshots_workspace on report_batch_html_snapshots(workspace_id, created_at);

create table if not exists report_versions (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  report_id uuid not null,
  report_type text not null check (report_type in ('operational_fragment', 'operational_package')),
  version integer not null,
  format text not null default 'html' check (format in ('html')),
  status text not null default 'created' check (status in ('stored', 'closed', 'created', 'sent', 'superseded')),
  html_filename text,
  content_hash char(64) not null,
  snapshot_json jsonb not null,
  created_by uuid,
  created_at timestamptz not null default now(),
  unique (workspace_id, report_type, report_id, version, format)
);

create index if not exists idx_report_versions_workspace on report_versions(workspace_id, created_at);
create index if not exists idx_report_versions_report_latest on report_versions(report_type, report_id, created_at);

create table if not exists report_packages (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  package_type text not null default 'operational_fragment_package' check (package_type in ('operational_fragment_package')),
  title text not null,
  status text not null default 'created' check (status in ('draft', 'created', 'sent', 'superseded')),
  start_date date not null,
  end_date date not null,
  fragment_count integer not null default 0,
  entry_count integer not null default 0,
  generated_at timestamptz not null,
  closed_at timestamptz,
  comment text,
  html_filename text,
  summary_json jsonb not null,
  fragment_ids_json jsonb not null,
  source_entry_ids_json jsonb not null,
  content_hash char(64) not null,
  created_by uuid,
  created_at timestamptz not null default now(),
  updated_at timestamptz
);

create index if not exists idx_report_packages_workspace on report_packages(workspace_id, created_at);
create index if not exists idx_report_packages_period on report_packages(workspace_id, start_date, end_date);
create index if not exists idx_report_packages_status on report_packages(status);

create table if not exists report_package_items (
  id uuid primary key default gen_random_uuid(),
  package_id uuid not null references report_packages(id) on delete cascade,
  batch_id uuid not null references report_batches(id) on delete cascade,
  html_snapshot_id uuid references report_batch_html_snapshots(id) on delete set null,
  item_order integer not null,
  fragment_snapshot_json jsonb not null,
  html_snapshot_json jsonb,
  created_at timestamptz not null default now(),
  unique (package_id, batch_id)
);

create index if not exists idx_report_package_items_batch on report_package_items(batch_id);
create index if not exists idx_report_package_items_html_snapshot on report_package_items(html_snapshot_id);

create table if not exists import_rows (
  id uuid primary key default gen_random_uuid(),
  import_source_id uuid not null references import_sources(id) on delete cascade,
  sheet_name text,
  row_number integer,
  raw_json jsonb not null,
  entry_id uuid references entries(id) on delete set null,
  parse_status text not null default 'pending',
  parse_notes text,
  created_at timestamptz not null default now()
);

create table if not exists workspace_assistant_settings (
  workspace_id uuid primary key references workspaces(id) on delete cascade,
  mr_smith_enabled boolean not null default false,
  internet_reference_mode text not null default 'per_request' check (internet_reference_mode in ('disabled', 'per_request', 'workspace_enabled')),
  provider_key text not null default 'stub',
  retention_days integer not null default 30,
  updated_by uuid,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists internet_reference_lookups (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  source_row_id uuid references import_rows(id) on delete set null,
  provider_key text not null default 'stub',
  provider_request_id text,
  consent_source text not null check (consent_source in ('request', 'workspace_setting')),
  sanitized_query text not null,
  query_hash text not null,
  masked_fields jsonb not null default '[]'::jsonb,
  result_status text not null default 'stub' check (result_status in ('stub', 'ok', 'error', 'timeout')),
  latency_ms integer not null default 0,
  matches jsonb not null default '[]'::jsonb,
  selected_match jsonb,
  no_financial_mutation boolean not null default true,
  created_by uuid,
  created_at timestamptz not null default now(),
  retention_delete_after timestamptz
);

create index if not exists idx_reference_lookups_workspace on internet_reference_lookups(workspace_id, created_at);
create index if not exists idx_reference_lookups_hash on internet_reference_lookups(workspace_id, query_hash);
create index if not exists idx_reference_lookups_source_row on internet_reference_lookups(source_row_id);

create table if not exists audit_log (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid references workspaces(id) on delete cascade,
  entity_type text not null,
  entity_id uuid,
  action text not null,
  before_json jsonb,
  after_json jsonb,
  performed_by uuid,
  created_at timestamptz not null default now()
);
