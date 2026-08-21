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
  '81111111-1111-4111-8111-111111111111',
  'authenticated',
  'authenticated',
  'foundation-revision-owner@example.invalid',
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
  '81111111-1111-4111-8111-111111111111',
  'foundation-revision-owner@example.invalid',
  'Revision Owner'
)
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  '8aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '81111111-1111-4111-8111-111111111111',
  'Foundation Revision Organization',
  'foundation-revision-organization',
  '{"foundation_revision_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  '8bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '8aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'Foundation Revision Yacht',
  'yacht',
  '{"foundation_revision_smoke":true}'::jsonb
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
  '8c111111-1111-4111-8111-111111111111',
  '8aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '8bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '81111111-1111-4111-8111-111111111111',
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
  '8ddddddd-dddd-4ddd-8ddd-dddddddddddd',
  '8aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '8bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'cash',
  'Cash',
  'cash'
)
on conflict (id) do update
set label = excluded.label,
    account_type = excluded.account_type,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = '81111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"81111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  created_entry record;
  report record;
  revision record;
  snapshot_status text;
  closure_status text;
  audit_count integer;
  locked_update_blocked boolean := false;
begin
  select *
  into created_entry
  from public.create_operational_entry(
    '8bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'cash',
    '2026-08-21',
    '-120 продукты',
    'manual',
    'manual',
    'ru',
    null,
    '{}'::jsonb,
    '{}'::jsonb
  );

  select *
  into report
  from public.create_period_report_snapshot(
    '8bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '2026-08-01',
    '2026-08-31',
    'Revision report'
  );

  select *
  into revision
  from public.return_report_snapshot_for_revision(
    report.report_snapshot_id,
    'Need correction'
  );

  if revision.status <> 'returned_for_revision' then
    raise exception 'expected returned_for_revision status';
  end if;

  select status into snapshot_status
  from public.report_snapshots
  where id = report.report_snapshot_id;

  if snapshot_status <> 'returned_for_revision' then
    raise exception 'report snapshot status was not updated';
  end if;

  select status into closure_status
  from public.period_closures
  where id = report.period_closure_id;

  if closure_status <> 'returned_for_revision' then
    raise exception 'period closure status was not updated';
  end if;

  select count(*) into audit_count
  from public.approval_events
  where workspace_id = '8bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
    and entity_type = 'report_snapshot'
    and entity_id = report.report_snapshot_id
    and event_type = 'report_snapshot_returned_for_revision';

  if audit_count <> 1 then
    raise exception 'expected one revision audit event, saw %', audit_count;
  end if;

  begin
    perform *
    from public.update_operational_entry(
      created_entry.transaction_id,
      'cash',
      '2026-08-21',
      '-120 продукты исправлено',
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
    raise exception 'returned report source row was unexpectedly editable';
  end if;
end;
$$;

reset role;

select 'foundation report revision command smoke ok' as result;

rollback;
