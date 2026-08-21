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
  '91111111-1111-4111-8111-111111111111',
  'authenticated',
  'authenticated',
  'foundation-correction-owner@example.invalid',
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
  '91111111-1111-4111-8111-111111111111',
  'foundation-correction-owner@example.invalid',
  'Correction Owner'
)
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  '9aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '91111111-1111-4111-8111-111111111111',
  'Foundation Correction Organization',
  'foundation-correction-organization',
  '{"foundation_correction_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  '9bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '9aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'Foundation Correction Yacht',
  'yacht',
  '{"foundation_correction_smoke":true}'::jsonb
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
  '9c111111-1111-4111-8111-111111111111',
  '9aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '9bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '91111111-1111-4111-8111-111111111111',
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
  '9ddddddd-dddd-4ddd-8ddd-dddddddddddd',
  '9aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '9bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'cash',
  'Cash',
  'cash'
)
on conflict (id) do update
set label = excluded.label,
    account_type = excluded.account_type,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = '91111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"91111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  original record;
  report record;
  correction record;
  original_raw_text text;
  correction_source_type text;
  correction_count integer;
  audit_count integer;
  direct_correction_insert_blocked boolean := false;
  locked_update_blocked boolean := false;
begin
  select *
  into original
  from public.create_operational_entry(
    '9bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'cash',
    '2026-08-21',
    '-100 fuel',
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
    '9bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '2026-08-01',
    '2026-08-31',
    'Correction report'
  );

  begin
    perform *
    from public.update_operational_entry(
      original.transaction_id,
      'cash',
      '2026-08-21',
      '-80 fuel fixed',
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
    raise exception 'report-locked source row was unexpectedly editable';
  end if;

  select *
  into correction
  from public.create_report_locked_correction(
    original.transaction_id,
    'cash',
    '2026-08-21',
    '+20 fuel correction',
    'Correct original fuel amount from 100 to 80'
  );

  if correction.correction_transaction_id is null or correction.correction_row_no <= original.row_no then
    raise exception 'correction transaction was not created after original row';
  end if;

  if correction.counted is not true or correction.transaction_status <> 'open' then
    raise exception 'correction transaction was not accepted as counted open row';
  end if;

  select raw_text
  into original_raw_text
  from public.transactions
  where id = original.transaction_id;

  if original_raw_text <> '-100 fuel' then
    raise exception 'original raw text was mutated by correction';
  end if;

  select source_type
  into correction_source_type
  from public.transactions
  where id = correction.correction_transaction_id;

  if correction_source_type <> 'correction' then
    raise exception 'correction transaction source type mismatch';
  end if;

  select count(*)
  into correction_count
  from public.corrections
  where id = correction.correction_id
    and original_transaction_id = original.transaction_id
    and correction_transaction_id = correction.correction_transaction_id
    and status = 'applied';

  if correction_count <> 1 then
    raise exception 'correction link was not created';
  end if;

  begin
    insert into public.corrections (
      organization_id,
      workspace_id,
      original_transaction_id,
      correction_transaction_id,
      reason,
      status,
      created_by
    ) values (
      '9aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      '9bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      original.transaction_id,
      correction.correction_transaction_id,
      'direct spoof',
      'applied',
      '91111111-1111-4111-8111-111111111111'
    );
  exception
    when insufficient_privilege or check_violation then
      direct_correction_insert_blocked := true;
    when others then
      if sqlerrm like '%row-level security%' then
        direct_correction_insert_blocked := true;
      else
        raise;
      end if;
  end;

  if direct_correction_insert_blocked is not true then
    raise exception 'direct correction insert was unexpectedly accepted';
  end if;

  select count(*)
  into audit_count
  from public.approval_events
  where workspace_id = '9bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
    and entity_type = 'correction'
    and entity_id = correction.correction_id
    and event_type = 'report_locked_correction_created';

  if audit_count <> 1 then
    raise exception 'expected one correction audit event, saw %', audit_count;
  end if;
end;
$$;

reset role;

select 'foundation report locked correction command smoke ok' as result;

rollback;
