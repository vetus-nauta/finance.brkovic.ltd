begin;

do $$
declare
  mutable_policy_count integer;
begin
  select count(*) into mutable_policy_count
  from pg_policies
  where schemaname = 'public'
    and tablename in ('period_closures', 'report_snapshots', 'report_packages', 'report_package_items')
    and cmd <> 'SELECT';

  if mutable_policy_count <> 0 then
    raise exception 'report tables must not expose direct mutable policies, saw %', mutable_policy_count;
  end if;

  if has_table_privilege('authenticated', 'public.period_closures', 'INSERT')
    or has_table_privilege('authenticated', 'public.report_snapshots', 'INSERT')
    or has_table_privilege('authenticated', 'public.report_packages', 'INSERT')
    or has_table_privilege('authenticated', 'public.report_package_items', 'INSERT')
  then
    raise exception 'report tables direct insert privileges must be revoked from authenticated';
  end if;
end;
$$;

insert into auth.users (
  id,
  aud,
  role,
  email,
  encrypted_password,
  email_confirmed_at,
  raw_app_meta_data,
  raw_user_meta_data,
  created_at,
  updated_at,
  is_sso_user,
  is_anonymous
) values (
  '61111111-1111-4111-8111-111111111111',
  'authenticated',
  'authenticated',
  'foundation-report-owner@example.invalid',
  '',
  now(),
  '{"provider":"email","providers":["email"]}'::jsonb,
  '{}'::jsonb,
  now(),
  now(),
  false,
  false
)
on conflict (id) do update
set email = excluded.email,
    updated_at = now();

insert into public.profiles (id, email, display_name)
values (
  '61111111-1111-4111-8111-111111111111',
  'foundation-report-owner@example.invalid',
  'Report Owner'
)
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  '6aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '61111111-1111-4111-8111-111111111111',
  'Foundation Report Organization',
  'foundation-report-organization',
  '{"foundation_report_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '6aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'Foundation Report Yacht',
  'yacht',
  '{"foundation_report_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.memberships (
  id,
  organization_id,
  workspace_id,
  user_id,
  role_code,
  status,
  access_scope,
  invited_at,
  accepted_at
) values (
  '6c111111-1111-4111-8111-111111111111',
  '6aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '61111111-1111-4111-8111-111111111111',
  'owner',
  'active',
  'workspace',
  now(),
  now()
)
on conflict (id) do update
set role_code = excluded.role_code,
    status = excluded.status,
    access_scope = excluded.access_scope,
    accepted_at = excluded.accepted_at,
    updated_at = now();

insert into public.accounts (id, organization_id, workspace_id, code, label, account_type)
values (
  '6ddddddd-dddd-4ddd-8ddd-dddddddddddd',
  '6aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'cash',
  'Cash',
  'cash'
)
on conflict (id) do update
set label = excluded.label,
    account_type = excluded.account_type,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = '61111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"61111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  expense_entry record;
  income_entry record;
  review_entry record;
  report record;
  direct_insert_blocked boolean := false;
  locked_update_blocked boolean := false;
  transaction_count integer;
  audit_count integer;
begin
  select * into expense_entry
  from public.create_operational_entry(
    '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'cash',
    '2026-08-21',
    '-350 продукты',
    'manual',
    'manual',
    'ru',
    null,
    '{}'::jsonb,
    '{}'::jsonb
  );

  select * into income_entry
  from public.create_operational_entry(
    '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'cash',
    '2026-08-21',
    '+1000 поступило от судовладельца',
    'manual',
    'manual',
    'ru',
    null,
    '{}'::jsonb,
    '{}'::jsonb
  );

  select * into review_entry
  from public.create_operational_entry(
    '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'cash',
    '2026-08-21',
    'без суммы на проверку',
    'manual',
    'manual',
    'ru',
    null,
    '{}'::jsonb,
    '{}'::jsonb
  );

  select * into report
  from public.create_period_report_snapshot(
    '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '2026-08-01',
    '2026-08-31',
    'Smoke report'
  );

  if report.included_count <> 3
    or report.review_count <> 1
    or report.income_total <> 1000.00
    or report.expense_total <> 350.00
    or report.net_total <> 650.00
  then
    raise exception 'unexpected report command result: %', row_to_json(report);
  end if;

  select count(*) into transaction_count
  from public.transactions
  where id in (expense_entry.transaction_id, income_entry.transaction_id, review_entry.transaction_id)
    and status = 'included_in_report';

  if transaction_count <> 3 then
    raise exception 'expected 3 transactions included in report, saw %', transaction_count;
  end if;

  begin
    perform *
    from public.update_operational_entry(
      expense_entry.transaction_id,
      'cash',
      '2026-08-21',
      '-300 продукты исправлено',
      'manual',
      'ru',
      '{}'::jsonb
    );
  exception
    when others then
      if sqlerrm = 'report_locked_transaction' then
        locked_update_blocked := true;
      else
        raise;
      end if;
  end;

  if locked_update_blocked is not true then
    raise exception 'report-locked operational update was unexpectedly accepted';
  end if;

  begin
    insert into public.report_snapshots (
      organization_id,
      workspace_id,
      title,
      period_start,
      period_end,
      status,
      created_by
    ) values (
      '6aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      'Direct report spoof',
      '2026-08-01',
      '2026-08-31',
      'created',
      '61111111-1111-4111-8111-111111111111'
    );
  exception
    when insufficient_privilege or check_violation then
      direct_insert_blocked := true;
    when others then
      if sqlerrm like '%row-level security%' then
        direct_insert_blocked := true;
      else
        raise;
      end if;
  end;

  if direct_insert_blocked is not true then
    raise exception 'direct report snapshot insert was unexpectedly accepted';
  end if;

  select count(*) into audit_count
  from public.approval_events
  where workspace_id = '6bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
    and entity_type = 'report_snapshot'
    and entity_id = report.report_snapshot_id
    and event_type = 'report_snapshot_created';

  if audit_count <> 1 then
    raise exception 'expected one report audit event, saw %', audit_count;
  end if;
end;
$$;

reset role;

select 'foundation report snapshot command smoke ok' as result;

rollback;
