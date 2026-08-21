begin;

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
  '71111111-1111-4111-8111-111111111111',
  'authenticated',
  'authenticated',
  'foundation-package-owner@example.invalid',
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
  '71111111-1111-4111-8111-111111111111',
  'foundation-package-owner@example.invalid',
  'Package Owner'
)
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  '7aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '71111111-1111-4111-8111-111111111111',
  'Foundation Package Organization',
  'foundation-package-organization',
  '{"foundation_package_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '7aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'Foundation Package Yacht',
  'yacht',
  '{"foundation_package_smoke":true}'::jsonb
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
  '7c111111-1111-4111-8111-111111111111',
  '7aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '71111111-1111-4111-8111-111111111111',
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

insert into public.report_snapshots (
  id,
  organization_id,
  workspace_id,
  title,
  period_start,
  period_end,
  status,
  source_transaction_ids,
  totals,
  created_by
) values
(
  '7d111111-1111-4111-8111-111111111111',
  '7aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'Package report 1',
  '2026-08-01',
  '2026-08-10',
  'created',
  '{}',
  '{"entry_count":2,"income_total":1000,"expense_total":350,"net_total":650}'::jsonb,
  '71111111-1111-4111-8111-111111111111'
),
(
  '7d222222-2222-4222-8222-222222222222',
  '7aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'Package report 2',
  '2026-08-11',
  '2026-08-20',
  'created',
  '{}',
  '{"entry_count":3,"income_total":500,"expense_total":200,"net_total":300}'::jsonb,
  '71111111-1111-4111-8111-111111111111'
)
on conflict (id) do update
set title = excluded.title,
    totals = excluded.totals,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = '71111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"71111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  package record;
  item_count integer;
  first_item uuid;
  second_item uuid;
  audit_count integer;
  direct_insert_blocked boolean := false;
  invalid_snapshot_blocked boolean := false;
begin
  select *
  into package
  from public.create_report_package(
    '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    array[
      '7d222222-2222-4222-8222-222222222222',
      '7d111111-1111-4111-8111-111111111111',
      '7d222222-2222-4222-8222-222222222222'
    ]::uuid[],
    'Smoke package'
  );

  if package.report_package_id is null or package.included_count <> 2 then
    raise exception 'expected package with 2 unique reports';
  end if;

  select count(*)
  into item_count
  from public.report_package_items
  where report_package_id = package.report_package_id;

  if item_count <> 2 then
    raise exception 'expected 2 package items, saw %', item_count;
  end if;

  select report_snapshot_id
  into first_item
  from public.report_package_items
  where report_package_id = package.report_package_id
  order by position
  limit 1;

  select report_snapshot_id
  into second_item
  from public.report_package_items
  where report_package_id = package.report_package_id
  order by position
  offset 1
  limit 1;

  if first_item <> '7d222222-2222-4222-8222-222222222222'
    or second_item <> '7d111111-1111-4111-8111-111111111111'
  then
    raise exception 'package report order was not preserved';
  end if;

  begin
    insert into public.report_packages (
      organization_id,
      workspace_id,
      title,
      status,
      created_by
    ) values (
      '7aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      'Direct package spoof',
      'created',
      '71111111-1111-4111-8111-111111111111'
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
    raise exception 'direct report package insert was unexpectedly accepted';
  end if;

  begin
    perform *
    from public.create_report_package(
      '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      array['70000000-0000-4000-8000-000000000000']::uuid[],
      'Invalid package'
    );
  exception
    when others then
      if sqlerrm = 'report_snapshot_not_found' then
        invalid_snapshot_blocked := true;
      else
        raise;
      end if;
  end;

  if invalid_snapshot_blocked is not true then
    raise exception 'invalid report snapshot was unexpectedly accepted';
  end if;

  select count(*)
  into audit_count
  from public.approval_events
  where workspace_id = '7bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
    and entity_type = 'report_package'
    and entity_id = package.report_package_id
    and event_type = 'report_package_created';

  if audit_count <> 1 then
    raise exception 'expected one package audit event, saw %', audit_count;
  end if;
end;
$$;

reset role;

select 'foundation report package command smoke ok' as result;

rollback;
