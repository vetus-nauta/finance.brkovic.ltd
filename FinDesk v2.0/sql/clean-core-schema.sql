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
  role text not null check (role in ('owner', 'admin', 'assistant', 'viewer')),
  created_at timestamptz not null default now(),
  unique (workspace_id, user_id)
);

create table if not exists flows (
  id uuid primary key default gen_random_uuid(),
  workspace_id uuid not null references workspaces(id) on delete cascade,
  name text not null,
  type text not null check (type in ('cash', 'card', 'assistant_journal')),
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
  source_type text not null check (source_type in ('google_drive', 'excel', 'legacy_db', 'manual_upload')),
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
  entry_type text not null check (entry_type in ('cash_income', 'cash_expense', 'card_expense', 'card_income', 'opening_balance', 'correction', 'info', 'unrecognized', 'assistant_pending')),
  category_id uuid references categories(id) on delete set null,
  status text not null check (status in ('recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect')),
  balance_after numeric(14,2),
  source_type text not null default 'manual' check (source_type in ('manual', 'import', 'assistant', 'correction')),
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
