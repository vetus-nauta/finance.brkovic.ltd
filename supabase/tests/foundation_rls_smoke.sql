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
) values
  (
    '11111111-1111-4111-8111-111111111111',
    'authenticated',
    'authenticated',
    'foundation-owner@example.invalid',
    '',
    now(),
    '{"provider":"email","providers":["email"]}'::jsonb,
    '{}'::jsonb,
    now(),
    now(),
    false,
    false
  ),
  (
    '22222222-2222-4222-8222-222222222222',
    'authenticated',
    'authenticated',
    'foundation-employee@example.invalid',
    '',
    now(),
    '{"provider":"email","providers":["email"]}'::jsonb,
    '{}'::jsonb,
    now(),
    now(),
    false,
    false
  ),
  (
    '33333333-3333-4333-8333-333333333333',
    'authenticated',
    'authenticated',
    'foundation-outsider@example.invalid',
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
values
  ('11111111-1111-4111-8111-111111111111', 'foundation-owner@example.invalid', 'Foundation Owner'),
  ('22222222-2222-4222-8222-222222222222', 'foundation-employee@example.invalid', 'Foundation Employee'),
  ('33333333-3333-4333-8333-333333333333', 'foundation-outsider@example.invalid', 'Foundation Outsider')
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '11111111-1111-4111-8111-111111111111',
  'Foundation Smoke Organization',
  'foundation-smoke-organization',
  '{"foundation_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'Foundation Smoke Yacht',
  'yacht',
  '{"foundation_smoke":true}'::jsonb
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
) values
  (
    'c1111111-1111-4111-8111-111111111111',
    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '11111111-1111-4111-8111-111111111111',
    'owner',
    'active',
    'workspace',
    now(),
    now()
  ),
  (
    'c2222222-2222-4222-8222-222222222222',
    'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '22222222-2222-4222-8222-222222222222',
    'employee',
    'active',
    'own_reports',
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
  'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'cash',
  'Cash',
  'cash'
)
on conflict (id) do update
set label = excluded.label,
    updated_at = now();

insert into public.transactions (
  id,
  organization_id,
  workspace_id,
  account_id,
  occurred_on,
  row_no,
  raw_text,
  created_by
) values (
  'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
  '2026-08-20',
  1,
  '-100 marina smoke test',
  '11111111-1111-4111-8111-111111111111'
)
on conflict (id) do update
set raw_text = excluded.raw_text,
    updated_at = now();

insert into public.ledger_entries (
  id,
  organization_id,
  workspace_id,
  transaction_id,
  account_id,
  direction,
  amount
) values (
  'ffffffff-ffff-4fff-8fff-ffffffffffff',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
  'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
  'expense',
  100.00
)
on conflict (id) do update
set amount = excluded.amount,
    updated_at = now();

insert into public.cash_advances (
  id,
  organization_id,
  workspace_id,
  issued_to,
  account_id,
  amount,
  status,
  issued_by,
  issued_at,
  accepted_at
) values (
  '12121212-1212-4121-8121-121212121212',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '22222222-2222-4222-8222-222222222222',
  'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
  400.00,
  'accepted',
  '11111111-1111-4111-8111-111111111111',
  now(),
  now()
)
on conflict (id) do update
set amount = excluded.amount,
    status = excluded.status,
    updated_at = now();

insert into public.expense_reports (
  id,
  organization_id,
  workspace_id,
  cash_advance_id,
  submitted_by,
  status,
  total_amount
) values (
  '23232323-2323-4232-8232-232323232323',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '12121212-1212-4121-8121-121212121212',
  '22222222-2222-4222-8222-222222222222',
  'draft',
  200.00
)
on conflict (id) do update
set total_amount = excluded.total_amount,
    updated_at = now();

insert into public.expense_items (
  id,
  organization_id,
  workspace_id,
  expense_report_id,
  occurred_on,
  raw_text,
  amount
) values (
  '34343434-3434-4343-8343-343434343434',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '23232323-2323-4232-8232-232323232323',
  '2026-08-20',
  '-200 provisions smoke test',
  200.00
)
on conflict (id) do update
set amount = excluded.amount,
    updated_at = now();

insert into public.quick_notes (
  id,
  organization_id,
  workspace_id,
  author_user_id,
  body,
  status
) values (
  '45454545-4545-4454-8454-454545454545',
  'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '22222222-2222-4222-8222-222222222222',
  '-200 продукты\n-50 стоянка в марине',
  'draft'
)
on conflict (id) do update
set body = excluded.body,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = '11111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"11111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  visible_transactions integer;
  visible_expense_reports integer;
begin
  select count(*) into visible_transactions from public.transactions;
  select count(*) into visible_expense_reports from public.expense_reports;

  if visible_transactions <> 1 then
    raise exception 'Owner should see 1 transaction, saw %', visible_transactions;
  end if;

  if visible_expense_reports <> 1 then
    raise exception 'Owner should see 1 expense report, saw %', visible_expense_reports;
  end if;
end;
$$;

reset role;

set local role authenticated;
set local request.jwt.claim.sub = '22222222-2222-4222-8222-222222222222';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"22222222-2222-4222-8222-222222222222","role":"authenticated"}';

do $$
declare
  visible_transactions integer;
  visible_cash_advances integer;
  visible_expense_reports integer;
  visible_expense_items integer;
  visible_quick_notes integer;
begin
  select count(*) into visible_transactions from public.transactions;
  select count(*) into visible_cash_advances from public.cash_advances;
  select count(*) into visible_expense_reports from public.expense_reports;
  select count(*) into visible_expense_items from public.expense_items;
  select count(*) into visible_quick_notes from public.quick_notes;

  if visible_transactions <> 0 then
    raise exception 'Employee should not see operational transactions, saw %', visible_transactions;
  end if;

  if visible_cash_advances <> 1 then
    raise exception 'Employee should see own cash advance, saw %', visible_cash_advances;
  end if;

  if visible_expense_reports <> 1 then
    raise exception 'Employee should see own expense report, saw %', visible_expense_reports;
  end if;

  if visible_expense_items <> 1 then
    raise exception 'Employee should see own expense item, saw %', visible_expense_items;
  end if;

  if visible_quick_notes <> 1 then
    raise exception 'Employee should see own quick note, saw %', visible_quick_notes;
  end if;

  begin
    insert into public.transactions (
      organization_id,
      workspace_id,
      account_id,
      occurred_on,
      row_no,
      raw_text,
      created_by
    ) values (
      'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
      '2026-08-20',
      2,
      '-999 forbidden employee ledger write',
      '22222222-2222-4222-8222-222222222222'
    );
    raise exception 'Employee unexpectedly inserted an operational transaction';
  exception
    when insufficient_privilege then
      null;
  end;
end;
$$;

reset role;

set local role authenticated;
set local request.jwt.claim.sub = '33333333-3333-4333-8333-333333333333';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"33333333-3333-4333-8333-333333333333","role":"authenticated"}';

do $$
declare
  visible_workspaces integer;
  visible_transactions integer;
  visible_cash_advances integer;
  visible_quick_notes integer;
begin
  select count(*) into visible_workspaces from public.workspaces;
  select count(*) into visible_transactions from public.transactions;
  select count(*) into visible_cash_advances from public.cash_advances;
  select count(*) into visible_quick_notes from public.quick_notes;

  if visible_workspaces <> 0 then
    raise exception 'Outsider should see 0 workspaces, saw %', visible_workspaces;
  end if;

  if visible_transactions <> 0 then
    raise exception 'Outsider should see 0 transactions, saw %', visible_transactions;
  end if;

  if visible_cash_advances <> 0 then
    raise exception 'Outsider should see 0 cash advances, saw %', visible_cash_advances;
  end if;

  if visible_quick_notes <> 0 then
    raise exception 'Outsider should see 0 quick notes, saw %', visible_quick_notes;
  end if;
end;
$$;

reset role;

select 'foundation rls smoke ok' as result;

rollback;
