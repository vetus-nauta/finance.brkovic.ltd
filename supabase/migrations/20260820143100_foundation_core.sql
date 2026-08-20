-- FinDesk / brkovic.app foundation core
-- Date: 2026-08-20
-- Purpose: first PostgreSQL/Supabase schema and RLS skeleton.
-- This migration is not a production cutover.

begin;

create extension if not exists pgcrypto;

drop function if exists public.rls_auto_enable();

create schema if not exists private;
revoke all on schema private from public;

create or replace function public.set_updated_at()
returns trigger
language plpgsql
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  email text not null unique,
  display_name text,
  locale text not null default 'ru',
  timezone text not null default 'Europe/Podgorica',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.organizations (
  id uuid primary key default gen_random_uuid(),
  owner_user_id uuid not null references auth.users(id),
  name text not null,
  slug text unique,
  status text not null default 'active' check (status in ('active', 'suspended', 'deleted')),
  metadata jsonb not null default '{}'::jsonb,
  deleted_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.workspaces (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  name text not null,
  workspace_type text not null default 'yacht',
  currency_code char(3) not null default 'EUR',
  locale text not null default 'ru',
  status text not null default 'active' check (status in ('active', 'archived', 'deleted')),
  metadata jsonb not null default '{}'::jsonb,
  deleted_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.roles (
  code text primary key,
  label text not null,
  rank integer not null,
  created_at timestamptz not null default now()
);

create table public.permissions (
  code text primary key,
  description text not null,
  created_at timestamptz not null default now()
);

create table public.role_permissions (
  role_code text not null references public.roles(code) on delete cascade,
  permission_code text not null references public.permissions(code) on delete cascade,
  primary key (role_code, permission_code)
);

create table public.memberships (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  user_id uuid not null references auth.users(id) on delete cascade,
  role_code text not null references public.roles(code),
  status text not null default 'active' check (status in ('invited', 'active', 'revoked', 'left')),
  access_scope text not null default 'workspace' check (access_scope in ('workspace', 'own_reports')),
  invited_at timestamptz,
  accepted_at timestamptz,
  revoked_at timestamptz,
  left_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (workspace_id, user_id)
);

create table public.invitations (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  email text not null,
  role_code text not null references public.roles(code),
  status text not null default 'pending' check (status in ('pending', 'accepted', 'revoked', 'expired')),
  token_hash text not null unique,
  invited_by uuid not null references auth.users(id),
  expires_at timestamptz not null,
  accepted_by uuid references auth.users(id),
  accepted_at timestamptz,
  revoked_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.accounts (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  code text not null,
  label text not null,
  account_type text not null check (account_type in ('cash', 'card', 'bank', 'accountable', 'adjustment')),
  currency_code char(3) not null default 'EUR',
  is_active boolean not null default true,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (workspace_id, code)
);

create table public.categories (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  code text not null,
  direction text not null check (direction in ('income', 'expense', 'neutral')),
  label jsonb not null default '{}'::jsonb,
  parent_id uuid references public.categories(id),
  is_system boolean not null default false,
  is_active boolean not null default true,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (organization_id, workspace_id, code)
);

create table public.category_rules (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  category_id uuid references public.categories(id),
  rule_scope text not null default 'workspace' check (rule_scope in ('workspace', 'organization', 'global_candidate')),
  rule_type text not null check (rule_type in ('phrase', 'keyword', 'regex', 'blocker')),
  language text not null default 'ru',
  pattern text not null,
  weight integer not null default 10,
  required_words text[] not null default '{}',
  excluded_words text[] not null default '{}',
  status text not null default 'active' check (status in ('active', 'review', 'blocked', 'retired')),
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.counterparties (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  display_name text not null,
  counterparty_type text not null default 'unknown',
  aliases text[] not null default '{}',
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.transactions (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  account_id uuid references public.accounts(id),
  source_type text not null default 'manual' check (source_type in ('manual', 'quick_note', 'import', 'expense_report', 'correction', 'system')),
  source_id uuid,
  occurred_on date not null,
  row_no integer,
  raw_text text not null,
  status text not null default 'open' check (status in ('open', 'included_in_report', 'closed', 'void', 'needs_review')),
  metadata jsonb not null default '{}'::jsonb,
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (workspace_id, row_no)
);

create table public.ledger_entries (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  transaction_id uuid not null references public.transactions(id) on delete cascade,
  account_id uuid references public.accounts(id),
  category_id uuid references public.categories(id),
  counterparty_id uuid references public.counterparties(id),
  direction text not null check (direction in ('income', 'expense', 'neutral')),
  amount numeric(14,2) not null check (amount >= 0),
  currency_code char(3) not null default 'EUR',
  review_status text not null default 'accepted' check (review_status in ('accepted', 'review', 'blocked')),
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.period_closures (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  period_start date not null,
  period_end date not null,
  status text not null default 'closed' check (status in ('closed', 'reopened', 'returned_for_revision', 'void')),
  closed_by uuid references auth.users(id),
  closed_at timestamptz,
  reopened_by uuid references auth.users(id),
  reopened_at timestamptz,
  reason text,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  check (period_end >= period_start)
);

create table public.corrections (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  original_transaction_id uuid references public.transactions(id),
  correction_transaction_id uuid references public.transactions(id),
  reason text not null,
  status text not null default 'created' check (status in ('created', 'applied', 'void')),
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now()
);

create table public.report_definitions (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  code text not null,
  label jsonb not null default '{}'::jsonb,
  definition jsonb not null default '{}'::jsonb,
  is_system boolean not null default false,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (organization_id, workspace_id, code)
);

create table public.report_snapshots (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  period_closure_id uuid references public.period_closures(id),
  title text not null,
  period_start date not null,
  period_end date not null,
  status text not null default 'draft' check (status in ('draft', 'created', 'sent', 'accepted', 'returned_for_revision', 'void')),
  source_transaction_ids uuid[] not null default '{}',
  totals jsonb not null default '{}'::jsonb,
  content_hash text,
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  check (period_end >= period_start)
);

create table public.report_packages (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  title text not null,
  status text not null default 'draft' check (status in ('draft', 'created', 'sent', 'accepted', 'void')),
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.report_package_items (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  report_package_id uuid not null references public.report_packages(id) on delete cascade,
  report_snapshot_id uuid not null references public.report_snapshots(id),
  position integer not null default 0,
  created_at timestamptz not null default now(),
  unique (report_package_id, report_snapshot_id)
);

create table public.cash_advances (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  issued_to uuid not null references auth.users(id),
  account_id uuid references public.accounts(id),
  amount numeric(14,2) not null check (amount >= 0),
  currency_code char(3) not null default 'EUR',
  status text not null default 'offered' check (status in ('offered', 'accepted', 'declined', 'closed', 'void')),
  issued_by uuid references auth.users(id),
  issued_at timestamptz,
  accepted_at timestamptz,
  closed_at timestamptz,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.approval_events (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  entity_type text not null,
  entity_id uuid not null,
  event_type text not null,
  actor_user_id uuid references auth.users(id),
  note text,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create table public.expense_reports (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  cash_advance_id uuid references public.cash_advances(id),
  submitted_by uuid not null references auth.users(id),
  status text not null default 'draft' check (status in ('draft', 'submitted', 'approved', 'returned', 'closed', 'void')),
  total_amount numeric(14,2) not null default 0 check (total_amount >= 0),
  currency_code char(3) not null default 'EUR',
  submitted_at timestamptz,
  approved_by uuid references auth.users(id),
  approved_at timestamptz,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.expense_items (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  expense_report_id uuid not null references public.expense_reports(id) on delete cascade,
  occurred_on date not null,
  raw_text text not null,
  amount numeric(14,2) not null check (amount >= 0),
  currency_code char(3) not null default 'EUR',
  category_id uuid references public.categories(id),
  transaction_id uuid references public.transactions(id),
  status text not null default 'draft' check (status in ('draft', 'accepted', 'rejected')),
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.expense_report_ledger_links (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  expense_report_id uuid not null references public.expense_reports(id) on delete cascade,
  expense_item_id uuid references public.expense_items(id) on delete cascade,
  transaction_id uuid not null references public.transactions(id),
  ledger_entry_id uuid not null references public.ledger_entries(id),
  link_type text not null default 'materialized_projection' check (link_type in ('materialized_projection', 'settlement', 'correction')),
  created_at timestamptz not null default now(),
  unique (expense_item_id, ledger_entry_id)
);

create table public.settlements (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  cash_advance_id uuid references public.cash_advances(id),
  expense_report_id uuid references public.expense_reports(id),
  settlement_type text not null check (settlement_type in ('return_to_admin', 'reimburse_employee', 'write_off', 'correction')),
  amount numeric(14,2) not null check (amount >= 0),
  currency_code char(3) not null default 'EUR',
  transaction_id uuid references public.transactions(id),
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now()
);

create table public.documents (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  bucket text not null,
  object_key text not null,
  original_filename text,
  mime_type text,
  byte_size bigint,
  checksum_sha256 text,
  status text not null default 'active' check (status in ('active', 'deleted', 'quarantined')),
  uploaded_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (bucket, object_key)
);

create table public.document_versions (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  document_id uuid not null references public.documents(id) on delete cascade,
  version_no integer not null,
  bucket text not null,
  object_key text not null,
  checksum_sha256 text,
  byte_size bigint,
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  unique (document_id, version_no),
  unique (bucket, object_key)
);

create table public.document_links (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  document_id uuid not null references public.documents(id) on delete cascade,
  entity_type text not null,
  entity_id uuid not null,
  created_at timestamptz not null default now()
);

create table public.document_extractions (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  document_id uuid not null references public.documents(id) on delete cascade,
  provider text not null,
  status text not null default 'queued' check (status in ('queued', 'processing', 'completed', 'failed', 'void')),
  extracted_text text,
  extracted_data jsonb not null default '{}'::jsonb,
  confidence numeric(5,4),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.quick_notes (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  author_user_id uuid not null references auth.users(id),
  body text not null,
  status text not null default 'draft' check (status in ('draft', 'submitted_to_smith', 'converted', 'void')),
  converted_transaction_ids uuid[] not null default '{}',
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.comments (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  entity_type text not null,
  entity_id uuid not null,
  body text not null,
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.messages (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  sender_user_id uuid references auth.users(id),
  recipient_user_id uuid references auth.users(id),
  subject text,
  body text not null,
  status text not null default 'sent' check (status in ('draft', 'sent', 'read', 'archived', 'void')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.notifications (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  user_id uuid not null references auth.users(id) on delete cascade,
  notification_type text not null,
  title text not null,
  body text,
  read_at timestamptz,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create table public.ai_jobs (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  job_type text not null,
  provider text,
  status text not null default 'queued' check (status in ('queued', 'processing', 'completed', 'failed', 'void')),
  input_ref jsonb not null default '{}'::jsonb,
  output_ref jsonb not null default '{}'::jsonb,
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.ai_provider_events (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  ai_job_id uuid references public.ai_jobs(id) on delete cascade,
  provider text not null,
  event_type text not null,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create table public.ocr_jobs (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  document_id uuid references public.documents(id) on delete cascade,
  provider text,
  status text not null default 'queued' check (status in ('queued', 'processing', 'completed', 'failed', 'void')),
  result jsonb not null default '{}'::jsonb,
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.user_corrections (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  user_id uuid references auth.users(id),
  source_text text not null,
  previous_value jsonb not null default '{}'::jsonb,
  corrected_value jsonb not null default '{}'::jsonb,
  status text not null default 'active' check (status in ('active', 'blocked', 'retired')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.subscriptions (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  provider text,
  provider_customer_id text,
  provider_subscription_id text,
  plan_code text not null default 'free',
  status text not null default 'trial' check (status in ('trial', 'active', 'past_due', 'canceled', 'paused')),
  current_period_start timestamptz,
  current_period_end timestamptz,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.entitlements (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  code text not null,
  value jsonb not null default '{}'::jsonb,
  starts_at timestamptz,
  ends_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (organization_id, code)
);

create table public.usage_events (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  user_id uuid references auth.users(id),
  event_type text not null,
  quantity numeric(14,4) not null default 1,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create table public.audit_log (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid references public.organizations(id) on delete cascade,
  workspace_id uuid references public.workspaces(id) on delete cascade,
  actor_user_id uuid references auth.users(id),
  event_type text not null,
  entity_type text,
  entity_id uuid,
  before_data jsonb,
  after_data jsonb,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

insert into public.roles (code, label, rank) values
  ('owner', 'Owner', 100),
  ('admin', 'Admin', 90),
  ('finance', 'Finance', 70),
  ('assistant', 'Assistant', 50),
  ('viewer', 'Viewer', 20),
  ('employee', 'Employee', 10)
on conflict (code) do nothing;

insert into public.permissions (code, description) values
  ('workspace.read', 'Read workspace'),
  ('workspace.manage', 'Manage workspace settings'),
  ('members.read', 'Read workspace members'),
  ('members.manage', 'Invite and revoke workspace members'),
  ('ledger.read', 'Read operational ledger'),
  ('ledger.write', 'Create and update operational ledger entries'),
  ('ledger.correct', 'Create corrections for closed periods'),
  ('period.close', 'Close and reopen periods'),
  ('reports.read', 'Read reports'),
  ('reports.manage', 'Create, send, accept, and return reports'),
  ('accountable.issue', 'Issue accountable money'),
  ('accountable.approve', 'Approve accountable reports'),
  ('documents.read', 'Read linked documents'),
  ('documents.write', 'Upload and link documents'),
  ('dictionary.manage', 'Manage category rules and training decisions')
on conflict (code) do nothing;

insert into public.role_permissions (role_code, permission_code)
select 'owner', code from public.permissions
on conflict do nothing;

insert into public.role_permissions (role_code, permission_code)
select 'admin', code from public.permissions
where code <> 'workspace.manage'
on conflict do nothing;

insert into public.role_permissions (role_code, permission_code) values
  ('finance', 'workspace.read'),
  ('finance', 'members.read'),
  ('finance', 'ledger.read'),
  ('finance', 'ledger.write'),
  ('finance', 'ledger.correct'),
  ('finance', 'period.close'),
  ('finance', 'reports.read'),
  ('finance', 'reports.manage'),
  ('finance', 'accountable.issue'),
  ('finance', 'accountable.approve'),
  ('finance', 'documents.read'),
  ('finance', 'documents.write'),
  ('finance', 'dictionary.manage'),
  ('assistant', 'workspace.read'),
  ('assistant', 'ledger.read'),
  ('assistant', 'ledger.write'),
  ('assistant', 'reports.read'),
  ('assistant', 'documents.read'),
  ('assistant', 'documents.write'),
  ('viewer', 'workspace.read'),
  ('viewer', 'ledger.read'),
  ('viewer', 'reports.read'),
  ('viewer', 'documents.read'),
  ('employee', 'workspace.read'),
  ('employee', 'documents.read'),
  ('employee', 'documents.write')
on conflict do nothing;

create or replace function private.is_workspace_member(target_workspace_id uuid)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
  select exists (
    select 1
    from public.memberships m
    where m.workspace_id = target_workspace_id
      and m.user_id = auth.uid()
      and m.status = 'active'
      and m.accepted_at is not null
      and m.revoked_at is null
      and m.left_at is null
  );
$$;

create or replace function private.has_workspace_permission(target_workspace_id uuid, target_permission_code text)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
  select exists (
    select 1
    from public.memberships m
    join public.role_permissions rp on rp.role_code = m.role_code
    where m.workspace_id = target_workspace_id
      and m.user_id = auth.uid()
      and m.status = 'active'
      and m.accepted_at is not null
      and m.revoked_at is null
      and m.left_at is null
      and rp.permission_code = target_permission_code
  );
$$;

create or replace function private.is_own_report(target_workspace_id uuid, target_user_id uuid)
returns boolean
language sql
stable
security definer
set search_path = ''
as $$
  select target_user_id = auth.uid()
    and exists (
      select 1
      from public.memberships m
      where m.workspace_id = target_workspace_id
        and m.user_id = auth.uid()
        and m.status = 'active'
        and m.accepted_at is not null
        and m.revoked_at is null
        and m.left_at is null
    );
$$;

revoke execute on function private.is_workspace_member(uuid) from public, anon, authenticated, service_role;
revoke execute on function private.has_workspace_permission(uuid, text) from public, anon, authenticated, service_role;
revoke execute on function private.is_own_report(uuid, uuid) from public, anon, authenticated, service_role;

create index profiles_email_idx on public.profiles (email);
create index organizations_owner_user_id_idx on public.organizations (owner_user_id);
create index workspaces_organization_id_idx on public.workspaces (organization_id);
create index memberships_workspace_user_status_idx on public.memberships (workspace_id, user_id, status);
create index memberships_organization_user_status_idx on public.memberships (organization_id, user_id, status);
create index memberships_role_code_idx on public.memberships (role_code);
create index invitations_workspace_status_email_idx on public.invitations (workspace_id, status, email);
create index invitations_invited_by_idx on public.invitations (invited_by);
create index accounts_workspace_id_idx on public.accounts (workspace_id);
create index categories_workspace_code_idx on public.categories (workspace_id, code);
create index categories_parent_id_idx on public.categories (parent_id);
create index category_rules_workspace_status_idx on public.category_rules (workspace_id, status);
create index category_rules_category_id_idx on public.category_rules (category_id);
create index counterparties_workspace_id_idx on public.counterparties (workspace_id);
create index transactions_workspace_occurred_row_idx on public.transactions (workspace_id, occurred_on, row_no);
create index transactions_account_id_idx on public.transactions (account_id);
create index transactions_created_by_idx on public.transactions (created_by);
create index ledger_entries_workspace_transaction_idx on public.ledger_entries (workspace_id, transaction_id);
create index ledger_entries_account_id_idx on public.ledger_entries (account_id);
create index ledger_entries_category_id_idx on public.ledger_entries (category_id);
create index ledger_entries_counterparty_id_idx on public.ledger_entries (counterparty_id);
create index period_closures_workspace_period_idx on public.period_closures (workspace_id, period_start, period_end);
create index corrections_workspace_original_idx on public.corrections (workspace_id, original_transaction_id);
create index report_definitions_workspace_code_idx on public.report_definitions (workspace_id, code);
create index report_snapshots_workspace_period_idx on public.report_snapshots (workspace_id, period_start, period_end);
create index report_snapshots_period_closure_id_idx on public.report_snapshots (period_closure_id);
create index report_packages_workspace_status_idx on public.report_packages (workspace_id, status);
create index report_package_items_package_id_idx on public.report_package_items (report_package_id);
create index report_package_items_snapshot_id_idx on public.report_package_items (report_snapshot_id);
create index cash_advances_workspace_issued_to_idx on public.cash_advances (workspace_id, issued_to, status);
create index cash_advances_account_id_idx on public.cash_advances (account_id);
create index approval_events_workspace_entity_idx on public.approval_events (workspace_id, entity_type, entity_id);
create index expense_reports_workspace_submitted_idx on public.expense_reports (workspace_id, submitted_by, status);
create index expense_reports_cash_advance_id_idx on public.expense_reports (cash_advance_id);
create index expense_items_report_id_idx on public.expense_items (expense_report_id);
create index expense_items_workspace_date_idx on public.expense_items (workspace_id, occurred_on);
create index expense_items_category_id_idx on public.expense_items (category_id);
create index expense_items_transaction_id_idx on public.expense_items (transaction_id);
create index expense_report_ledger_links_report_id_idx on public.expense_report_ledger_links (expense_report_id);
create index expense_report_ledger_links_transaction_id_idx on public.expense_report_ledger_links (transaction_id);
create index expense_report_ledger_links_ledger_entry_id_idx on public.expense_report_ledger_links (ledger_entry_id);
create index settlements_workspace_report_idx on public.settlements (workspace_id, expense_report_id);
create index settlements_cash_advance_id_idx on public.settlements (cash_advance_id);
create index settlements_transaction_id_idx on public.settlements (transaction_id);
create index documents_workspace_status_idx on public.documents (workspace_id, status);
create index document_versions_document_id_idx on public.document_versions (document_id);
create index document_links_workspace_entity_idx on public.document_links (workspace_id, entity_type, entity_id);
create index document_links_document_id_idx on public.document_links (document_id);
create index document_extractions_document_id_idx on public.document_extractions (document_id);
create index quick_notes_workspace_author_status_idx on public.quick_notes (workspace_id, author_user_id, status);
create index comments_workspace_entity_idx on public.comments (workspace_id, entity_type, entity_id);
create index messages_workspace_sender_idx on public.messages (workspace_id, sender_user_id);
create index messages_recipient_idx on public.messages (recipient_user_id, status);
create index notifications_user_read_idx on public.notifications (user_id, read_at);
create index ai_jobs_workspace_status_idx on public.ai_jobs (workspace_id, status);
create index ai_provider_events_job_id_idx on public.ai_provider_events (ai_job_id);
create index ocr_jobs_document_id_idx on public.ocr_jobs (document_id);
create index user_corrections_workspace_status_idx on public.user_corrections (workspace_id, status);
create index subscriptions_organization_id_idx on public.subscriptions (organization_id);
create index entitlements_organization_code_idx on public.entitlements (organization_id, code);
create index usage_events_workspace_type_idx on public.usage_events (workspace_id, event_type);
create index audit_log_workspace_event_idx on public.audit_log (workspace_id, event_type, created_at);

alter table public.profiles enable row level security;
alter table public.organizations enable row level security;
alter table public.workspaces enable row level security;
alter table public.roles enable row level security;
alter table public.permissions enable row level security;
alter table public.role_permissions enable row level security;
alter table public.memberships enable row level security;
alter table public.invitations enable row level security;
alter table public.accounts enable row level security;
alter table public.categories enable row level security;
alter table public.category_rules enable row level security;
alter table public.counterparties enable row level security;
alter table public.transactions enable row level security;
alter table public.ledger_entries enable row level security;
alter table public.period_closures enable row level security;
alter table public.corrections enable row level security;
alter table public.report_definitions enable row level security;
alter table public.report_snapshots enable row level security;
alter table public.report_packages enable row level security;
alter table public.report_package_items enable row level security;
alter table public.cash_advances enable row level security;
alter table public.approval_events enable row level security;
alter table public.expense_reports enable row level security;
alter table public.expense_items enable row level security;
alter table public.expense_report_ledger_links enable row level security;
alter table public.settlements enable row level security;
alter table public.documents enable row level security;
alter table public.document_versions enable row level security;
alter table public.document_links enable row level security;
alter table public.document_extractions enable row level security;
alter table public.quick_notes enable row level security;
alter table public.comments enable row level security;
alter table public.messages enable row level security;
alter table public.notifications enable row level security;
alter table public.ai_jobs enable row level security;
alter table public.ai_provider_events enable row level security;
alter table public.ocr_jobs enable row level security;
alter table public.user_corrections enable row level security;
alter table public.subscriptions enable row level security;
alter table public.entitlements enable row level security;
alter table public.usage_events enable row level security;
alter table public.audit_log enable row level security;

create policy profiles_self_read on public.profiles
  for select using (id = auth.uid());

create policy profiles_self_update on public.profiles
  for update using (id = auth.uid()) with check (id = auth.uid());

create policy reference_roles_read on public.roles
  for select to authenticated using (true);

create policy reference_permissions_read on public.permissions
  for select to authenticated using (true);

create policy reference_role_permissions_read on public.role_permissions
  for select to authenticated using (true);

create policy organizations_member_read on public.organizations
  for select using (
    exists (
      select 1 from public.memberships m
      where m.organization_id = organizations.id
        and m.user_id = auth.uid()
        and m.status = 'active'
        and m.accepted_at is not null
        and m.revoked_at is null
        and m.left_at is null
    )
  );

create policy organizations_owner_update on public.organizations
  for update using (owner_user_id = auth.uid()) with check (owner_user_id = auth.uid());

create policy workspaces_member_read on public.workspaces
  for select using (private.is_workspace_member(id));

create policy workspaces_manage_update on public.workspaces
  for update using (private.has_workspace_permission(id, 'workspace.manage'))
  with check (private.has_workspace_permission(id, 'workspace.manage'));

create policy memberships_self_or_manager_read on public.memberships
  for select using (
    user_id = auth.uid()
    or private.has_workspace_permission(workspace_id, 'members.read')
  );

create policy memberships_manager_write on public.memberships
  for all using (private.has_workspace_permission(workspace_id, 'members.manage'))
  with check (private.has_workspace_permission(workspace_id, 'members.manage'));

create policy invitations_manager_read on public.invitations
  for select using (private.has_workspace_permission(workspace_id, 'members.read'));

create policy invitations_manager_write on public.invitations
  for all using (private.has_workspace_permission(workspace_id, 'members.manage'))
  with check (private.has_workspace_permission(workspace_id, 'members.manage'));

create policy accounts_read on public.accounts
  for select using (private.has_workspace_permission(workspace_id, 'ledger.read'));

create policy accounts_write on public.accounts
  for all using (private.has_workspace_permission(workspace_id, 'workspace.manage'))
  with check (private.has_workspace_permission(workspace_id, 'workspace.manage'));

create policy categories_read on public.categories
  for select using (
    workspace_id is null
    or private.has_workspace_permission(workspace_id, 'ledger.read')
  );

create policy categories_write on public.categories
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy category_rules_read on public.category_rules
  for select using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'ledger.read')
  );

create policy category_rules_write on public.category_rules
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy counterparties_read on public.counterparties
  for select using (
    workspace_id is null
    or private.has_workspace_permission(workspace_id, 'ledger.read')
  );

create policy counterparties_write on public.counterparties
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'ledger.write')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'ledger.write')
  );

create policy transactions_read on public.transactions
  for select using (private.has_workspace_permission(workspace_id, 'ledger.read'));

create policy transactions_write on public.transactions
  for all using (private.has_workspace_permission(workspace_id, 'ledger.write'))
  with check (private.has_workspace_permission(workspace_id, 'ledger.write'));

create policy ledger_entries_read on public.ledger_entries
  for select using (private.has_workspace_permission(workspace_id, 'ledger.read'));

create policy ledger_entries_write on public.ledger_entries
  for all using (private.has_workspace_permission(workspace_id, 'ledger.write'))
  with check (private.has_workspace_permission(workspace_id, 'ledger.write'));

create policy period_closures_read on public.period_closures
  for select using (private.has_workspace_permission(workspace_id, 'reports.read'));

create policy period_closures_write on public.period_closures
  for all using (private.has_workspace_permission(workspace_id, 'period.close'))
  with check (private.has_workspace_permission(workspace_id, 'period.close'));

create policy corrections_read on public.corrections
  for select using (private.has_workspace_permission(workspace_id, 'ledger.read'));

create policy corrections_write on public.corrections
  for all using (private.has_workspace_permission(workspace_id, 'ledger.correct'))
  with check (private.has_workspace_permission(workspace_id, 'ledger.correct'));

create policy report_definitions_read on public.report_definitions
  for select using (
    workspace_id is null
    or private.has_workspace_permission(workspace_id, 'reports.read')
  );

create policy report_definitions_write on public.report_definitions
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'reports.manage')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'reports.manage')
  );

create policy report_snapshots_read on public.report_snapshots
  for select using (private.has_workspace_permission(workspace_id, 'reports.read'));

create policy report_snapshots_write on public.report_snapshots
  for all using (private.has_workspace_permission(workspace_id, 'reports.manage'))
  with check (private.has_workspace_permission(workspace_id, 'reports.manage'));

create policy report_packages_read on public.report_packages
  for select using (private.has_workspace_permission(workspace_id, 'reports.read'));

create policy report_packages_write on public.report_packages
  for all using (private.has_workspace_permission(workspace_id, 'reports.manage'))
  with check (private.has_workspace_permission(workspace_id, 'reports.manage'));

create policy report_package_items_read on public.report_package_items
  for select using (private.has_workspace_permission(workspace_id, 'reports.read'));

create policy report_package_items_write on public.report_package_items
  for all using (private.has_workspace_permission(workspace_id, 'reports.manage'))
  with check (private.has_workspace_permission(workspace_id, 'reports.manage'));

create policy cash_advances_read on public.cash_advances
  for select using (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or private.is_own_report(workspace_id, issued_to)
  );

create policy cash_advances_write on public.cash_advances
  for all using (private.has_workspace_permission(workspace_id, 'accountable.issue'))
  with check (private.has_workspace_permission(workspace_id, 'accountable.issue'));

create policy approval_events_read on public.approval_events
  for select using (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or private.has_workspace_permission(workspace_id, 'reports.manage')
  );

create policy approval_events_write on public.approval_events
  for all using (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or private.has_workspace_permission(workspace_id, 'reports.manage')
  ) with check (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or private.has_workspace_permission(workspace_id, 'reports.manage')
  );

create policy expense_reports_read on public.expense_reports
  for select using (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or private.is_own_report(workspace_id, submitted_by)
  );

create policy expense_reports_owner_insert on public.expense_reports
  for insert with check (private.is_own_report(workspace_id, submitted_by));

create policy expense_reports_owner_or_approver_update on public.expense_reports
  for update using (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or private.is_own_report(workspace_id, submitted_by)
  ) with check (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or private.is_own_report(workspace_id, submitted_by)
  );

create policy expense_items_read on public.expense_items
  for select using (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or exists (
      select 1 from public.expense_reports er
      where er.id = expense_items.expense_report_id
        and er.submitted_by = auth.uid()
    )
  );

create policy expense_items_write on public.expense_items
  for all using (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or exists (
      select 1 from public.expense_reports er
      where er.id = expense_items.expense_report_id
        and er.submitted_by = auth.uid()
        and er.status in ('draft', 'returned')
    )
  ) with check (
    private.has_workspace_permission(workspace_id, 'accountable.approve')
    or exists (
      select 1 from public.expense_reports er
      where er.id = expense_items.expense_report_id
        and er.submitted_by = auth.uid()
        and er.status in ('draft', 'returned')
    )
  );

create policy expense_report_ledger_links_read on public.expense_report_ledger_links
  for select using (private.has_workspace_permission(workspace_id, 'accountable.approve'));

create policy expense_report_ledger_links_write on public.expense_report_ledger_links
  for all using (private.has_workspace_permission(workspace_id, 'accountable.approve'))
  with check (private.has_workspace_permission(workspace_id, 'accountable.approve'));

create policy settlements_read on public.settlements
  for select using (private.has_workspace_permission(workspace_id, 'accountable.approve'));

create policy settlements_write on public.settlements
  for all using (private.has_workspace_permission(workspace_id, 'accountable.approve'))
  with check (private.has_workspace_permission(workspace_id, 'accountable.approve'));

create policy documents_read on public.documents
  for select using (private.has_workspace_permission(workspace_id, 'documents.read'));

create policy documents_write on public.documents
  for all using (private.has_workspace_permission(workspace_id, 'documents.write'))
  with check (private.has_workspace_permission(workspace_id, 'documents.write'));

create policy document_versions_read on public.document_versions
  for select using (private.has_workspace_permission(workspace_id, 'documents.read'));

create policy document_versions_write on public.document_versions
  for all using (private.has_workspace_permission(workspace_id, 'documents.write'))
  with check (private.has_workspace_permission(workspace_id, 'documents.write'));

create policy document_links_read on public.document_links
  for select using (private.has_workspace_permission(workspace_id, 'documents.read'));

create policy document_links_write on public.document_links
  for all using (private.has_workspace_permission(workspace_id, 'documents.write'))
  with check (private.has_workspace_permission(workspace_id, 'documents.write'));

create policy document_extractions_read on public.document_extractions
  for select using (private.has_workspace_permission(workspace_id, 'documents.read'));

create policy document_extractions_write on public.document_extractions
  for all using (private.has_workspace_permission(workspace_id, 'documents.write'))
  with check (private.has_workspace_permission(workspace_id, 'documents.write'));

create policy quick_notes_read on public.quick_notes
  for select using (
    author_user_id = auth.uid()
    or private.has_workspace_permission(workspace_id, 'ledger.read')
  );

create policy quick_notes_owner_write on public.quick_notes
  for all using (
    author_user_id = auth.uid()
    or private.has_workspace_permission(workspace_id, 'ledger.write')
  ) with check (
    author_user_id = auth.uid()
    or private.has_workspace_permission(workspace_id, 'ledger.write')
  );

create policy comments_read on public.comments
  for select using (private.is_workspace_member(workspace_id));

create policy comments_write on public.comments
  for all using (private.is_workspace_member(workspace_id))
  with check (private.is_workspace_member(workspace_id));

create policy messages_read on public.messages
  for select using (
    sender_user_id = auth.uid()
    or recipient_user_id = auth.uid()
    or private.has_workspace_permission(workspace_id, 'workspace.manage')
  );

create policy messages_write on public.messages
  for all using (
    sender_user_id = auth.uid()
    or private.has_workspace_permission(workspace_id, 'workspace.manage')
  ) with check (
    sender_user_id = auth.uid()
    or private.has_workspace_permission(workspace_id, 'workspace.manage')
  );

create policy notifications_owner_read on public.notifications
  for select using (user_id = auth.uid());

create policy notifications_owner_update on public.notifications
  for update using (user_id = auth.uid()) with check (user_id = auth.uid());

create policy ai_jobs_read on public.ai_jobs
  for select using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy ai_jobs_write on public.ai_jobs
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy ai_provider_events_read on public.ai_provider_events
  for select using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy ai_provider_events_write on public.ai_provider_events
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy ocr_jobs_read on public.ocr_jobs
  for select using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'documents.read')
  );

create policy ocr_jobs_write on public.ocr_jobs
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'documents.write')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'documents.write')
  );

create policy user_corrections_read on public.user_corrections
  for select using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy user_corrections_write on public.user_corrections
  for all using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  ) with check (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'dictionary.manage')
  );

create policy subscriptions_read on public.subscriptions
  for select using (
    exists (
      select 1 from public.memberships m
      where m.organization_id = subscriptions.organization_id
        and m.user_id = auth.uid()
        and m.status = 'active'
        and m.accepted_at is not null
        and m.revoked_at is null
        and m.left_at is null
    )
  );

create policy entitlements_read on public.entitlements
  for select using (
    exists (
      select 1 from public.memberships m
      where m.organization_id = entitlements.organization_id
        and m.user_id = auth.uid()
        and m.status = 'active'
        and m.accepted_at is not null
        and m.revoked_at is null
        and m.left_at is null
    )
  );

create policy usage_events_read on public.usage_events
  for select using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'workspace.manage')
  );

create policy audit_log_read on public.audit_log
  for select using (
    workspace_id is not null and private.has_workspace_permission(workspace_id, 'workspace.manage')
  );

create trigger profiles_updated_at before update on public.profiles
  for each row execute function public.set_updated_at();
create trigger organizations_updated_at before update on public.organizations
  for each row execute function public.set_updated_at();
create trigger workspaces_updated_at before update on public.workspaces
  for each row execute function public.set_updated_at();
create trigger memberships_updated_at before update on public.memberships
  for each row execute function public.set_updated_at();
create trigger invitations_updated_at before update on public.invitations
  for each row execute function public.set_updated_at();
create trigger accounts_updated_at before update on public.accounts
  for each row execute function public.set_updated_at();
create trigger categories_updated_at before update on public.categories
  for each row execute function public.set_updated_at();
create trigger category_rules_updated_at before update on public.category_rules
  for each row execute function public.set_updated_at();
create trigger counterparties_updated_at before update on public.counterparties
  for each row execute function public.set_updated_at();
create trigger transactions_updated_at before update on public.transactions
  for each row execute function public.set_updated_at();
create trigger ledger_entries_updated_at before update on public.ledger_entries
  for each row execute function public.set_updated_at();
create trigger period_closures_updated_at before update on public.period_closures
  for each row execute function public.set_updated_at();
create trigger report_definitions_updated_at before update on public.report_definitions
  for each row execute function public.set_updated_at();
create trigger report_snapshots_updated_at before update on public.report_snapshots
  for each row execute function public.set_updated_at();
create trigger report_packages_updated_at before update on public.report_packages
  for each row execute function public.set_updated_at();
create trigger cash_advances_updated_at before update on public.cash_advances
  for each row execute function public.set_updated_at();
create trigger expense_reports_updated_at before update on public.expense_reports
  for each row execute function public.set_updated_at();
create trigger expense_items_updated_at before update on public.expense_items
  for each row execute function public.set_updated_at();
create trigger documents_updated_at before update on public.documents
  for each row execute function public.set_updated_at();
create trigger document_extractions_updated_at before update on public.document_extractions
  for each row execute function public.set_updated_at();
create trigger quick_notes_updated_at before update on public.quick_notes
  for each row execute function public.set_updated_at();
create trigger comments_updated_at before update on public.comments
  for each row execute function public.set_updated_at();
create trigger messages_updated_at before update on public.messages
  for each row execute function public.set_updated_at();
create trigger ai_jobs_updated_at before update on public.ai_jobs
  for each row execute function public.set_updated_at();
create trigger ocr_jobs_updated_at before update on public.ocr_jobs
  for each row execute function public.set_updated_at();
create trigger user_corrections_updated_at before update on public.user_corrections
  for each row execute function public.set_updated_at();
create trigger subscriptions_updated_at before update on public.subscriptions
  for each row execute function public.set_updated_at();
create trigger entitlements_updated_at before update on public.entitlements
  for each row execute function public.set_updated_at();

commit;
