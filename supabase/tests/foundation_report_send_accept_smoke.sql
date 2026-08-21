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
  'a1111111-1111-4111-8111-111111111111',
  'authenticated',
  'authenticated',
  'foundation-send-owner@example.invalid',
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
  'a1111111-1111-4111-8111-111111111111',
  'foundation-send-owner@example.invalid',
  'Send Owner'
)
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  'aaaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'a1111111-1111-4111-8111-111111111111',
  'Foundation Send Organization',
  'foundation-send-organization',
  '{"foundation_send_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  'abbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'aaaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'Foundation Send Yacht',
  'yacht',
  '{"foundation_send_smoke":true}'::jsonb
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
  'ac111111-1111-4111-8111-111111111111',
  'aaaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'abbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'a1111111-1111-4111-8111-111111111111',
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
  'ad111111-1111-4111-8111-111111111111',
  'aaaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'abbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'Send report 1',
  '2026-08-01',
  '2026-08-10',
  'created',
  '{}',
  '{"entry_count":2,"income_total":1000,"expense_total":350,"net_total":650}'::jsonb,
  'a1111111-1111-4111-8111-111111111111'
),
(
  'ad222222-2222-4222-8222-222222222222',
  'aaaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'abbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'Send report 2',
  '2026-08-11',
  '2026-08-20',
  'created',
  '{}',
  '{"entry_count":3,"income_total":500,"expense_total":200,"net_total":300}'::jsonb,
  'a1111111-1111-4111-8111-111111111111'
)
on conflict (id) do update
set title = excluded.title,
    status = excluded.status,
    totals = excluded.totals,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = 'a1111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"a1111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  package record;
  report_status record;
  package_status record;
  invalid_accept_blocked boolean := false;
  audit_count integer;
begin
  begin
    perform *
    from public.set_report_snapshot_delivery_status(
      'ad111111-1111-4111-8111-111111111111',
      'accepted',
      'too early'
    );
  exception
    when others then
      if sqlerrm like 'invalid_report_status_transition:%' then
        invalid_accept_blocked := true;
      else
        raise;
      end if;
  end;

  if invalid_accept_blocked is not true then
    raise exception 'report accepted before sent was unexpectedly allowed';
  end if;

  select *
  into report_status
  from public.set_report_snapshot_delivery_status(
    'ad111111-1111-4111-8111-111111111111',
    'sent',
    'sent smoke'
  );

  if report_status.previous_status <> 'created' or report_status.status <> 'sent' then
    raise exception 'report send transition mismatch';
  end if;

  select *
  into report_status
  from public.set_report_snapshot_delivery_status(
    'ad111111-1111-4111-8111-111111111111',
    'accepted',
    'accepted smoke'
  );

  if report_status.previous_status <> 'sent' or report_status.status <> 'accepted' then
    raise exception 'report accept transition mismatch';
  end if;

  select *
  into package
  from public.create_report_package(
    'abbbbbbb-bbbb-4bbb-8bbb-111111111111',
    array[
      'ad111111-1111-4111-8111-111111111111',
      'ad222222-2222-4222-8222-222222222222'
    ]::uuid[],
    'Send package'
  );

  select *
  into package_status
  from public.set_report_package_delivery_status(
    package.report_package_id,
    'sent',
    'package sent smoke'
  );

  if package_status.previous_status <> 'created' or package_status.status <> 'sent' then
    raise exception 'package send transition mismatch';
  end if;

  select *
  into package_status
  from public.set_report_package_delivery_status(
    package.report_package_id,
    'accepted',
    'package accepted smoke'
  );

  if package_status.previous_status <> 'sent' or package_status.status <> 'accepted' then
    raise exception 'package accept transition mismatch';
  end if;

  select count(*) into audit_count
  from public.approval_events
  where workspace_id = 'abbbbbbb-bbbb-4bbb-8bbb-111111111111'
    and (
      (entity_type = 'report_snapshot' and entity_id = 'ad111111-1111-4111-8111-111111111111' and event_type in ('report_snapshot_sent', 'report_snapshot_accepted'))
      or
      (entity_type = 'report_package' and entity_id = package.report_package_id and event_type in ('report_package_sent', 'report_package_accepted'))
    );

  if audit_count <> 4 then
    raise exception 'expected 4 lifecycle audit events, saw %', audit_count;
  end if;
end;
$$;

reset role;

select 'foundation report send accept smoke ok' as result;

rollback;
