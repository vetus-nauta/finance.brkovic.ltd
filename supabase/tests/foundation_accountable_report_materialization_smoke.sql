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
  'b1111111-1111-4111-8111-111111111111',
  'authenticated',
  'authenticated',
  'accountable-materialize-owner@example.invalid',
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
  'b2222222-2222-4222-8222-222222222222',
  'authenticated',
  'authenticated',
  'accountable-materialize-employee@example.invalid',
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
(
  'b1111111-1111-4111-8111-111111111111',
  'accountable-materialize-owner@example.invalid',
  'Materialize Owner'
),
(
  'b2222222-2222-4222-8222-222222222222',
  'accountable-materialize-employee@example.invalid',
  'Materialize Employee'
)
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'b1111111-1111-4111-8111-111111111111',
  'Accountable Materialize Organization',
  'accountable-materialize-organization',
  '{"accountable_materialize_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'Accountable Materialize Yacht',
  'yacht',
  '{"accountable_materialize_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.accounts (
  id,
  organization_id,
  workspace_id,
  code,
  label,
  account_type,
  currency_code
) values (
  'bc111111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'cash',
  'Кеш',
  'cash',
  'EUR'
)
on conflict (workspace_id, code) do update
set label = excluded.label,
    account_type = excluded.account_type,
    currency_code = excluded.currency_code,
    is_active = true,
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
  'bd111111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'b1111111-1111-4111-8111-111111111111',
  'owner',
  'active',
  'workspace',
  now(),
  now()
),
(
  'bd222222-2222-4222-8222-222222222222',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'b2222222-2222-4222-8222-222222222222',
  'employee',
  'active',
  'own_reports',
  now(),
  now()
)
on conflict (workspace_id, user_id) do update
set role_code = excluded.role_code,
    status = excluded.status,
    access_scope = excluded.access_scope,
    accepted_at = excluded.accepted_at,
    updated_at = now();

insert into public.cash_advances (
  id,
  organization_id,
  workspace_id,
  issued_to,
  account_id,
  amount,
  currency_code,
  status,
  issued_by,
  issued_at,
  accepted_at
) values (
  'be111111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'b2222222-2222-4222-8222-222222222222',
  'bc111111-1111-4111-8111-111111111111',
  500,
  'EUR',
  'accepted',
  'b1111111-1111-4111-8111-111111111111',
  now(),
  now()
)
on conflict (id) do update
set amount = excluded.amount,
    status = excluded.status,
    account_id = excluded.account_id,
    updated_at = now();

insert into public.expense_reports (
  id,
  organization_id,
  workspace_id,
  cash_advance_id,
  submitted_by,
  status,
  total_amount,
  currency_code,
  submitted_at,
  approved_by,
  approved_at
) values (
  'bf111111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'be111111-1111-4111-8111-111111111111',
  'b2222222-2222-4222-8222-222222222222',
  'approved',
  280,
  'EUR',
  now(),
  'b1111111-1111-4111-8111-111111111111',
  now()
)
on conflict (id) do update
set status = excluded.status,
    total_amount = excluded.total_amount,
    submitted_at = excluded.submitted_at,
    approved_by = excluded.approved_by,
    approved_at = excluded.approved_at,
    updated_at = now();

insert into public.expense_items (
  id,
  organization_id,
  workspace_id,
  expense_report_id,
  occurred_on,
  raw_text,
  amount,
  currency_code,
  status,
  transaction_id,
  metadata
) values
(
  'bf211111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'bf111111-1111-4111-8111-111111111111',
  '2026-08-20',
  'продукты',
  200,
  'EUR',
  'draft',
  null,
  '{}'::jsonb
),
(
  'bf222222-2222-4222-8222-222222222222',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'bf111111-1111-4111-8111-111111111111',
  '2026-08-21',
  '-80 стоянка',
  80,
  'EUR',
  'draft',
  null,
  '{}'::jsonb
)
on conflict (id) do update
set raw_text = excluded.raw_text,
    amount = excluded.amount,
    status = excluded.status,
    transaction_id = null,
    metadata = '{}'::jsonb,
    updated_at = now();

set local role authenticated;
set local request.jwt.claims = '{"sub":"b1111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  result record;
  second_result record;
  transaction_count integer;
  link_count integer;
  item_count integer;
  ledger_total numeric(14,2);
  report_status text;
  employee_blocked boolean := false;
begin
  select * into result
  from public.materialize_expense_report('bf111111-1111-4111-8111-111111111111');

  if result.materialized_count <> 2 or result.expense_total <> 280 then
    raise exception 'unexpected materialization result: %', row_to_json(result);
  end if;

  select count(*), coalesce(sum(le.amount), 0)::numeric(14,2)
  into transaction_count, ledger_total
  from public.transactions t
  join public.ledger_entries le on le.transaction_id = t.id
  where t.workspace_id = 'bbbbbbbb-bbbb-4bbb-8bbb-111111111111'
    and t.source_type = 'expense_report'
    and t.source_id in (
      'bf211111-1111-4111-8111-111111111111',
      'bf222222-2222-4222-8222-222222222222'
    );

  if transaction_count <> 2 or ledger_total <> 280 then
    raise exception 'expected two materialized expense transactions totaling 280, saw %, %', transaction_count, ledger_total;
  end if;

  select count(*) into link_count
  from public.expense_report_ledger_links
  where expense_report_id = 'bf111111-1111-4111-8111-111111111111'
    and link_type = 'materialized_projection';

  if link_count <> 2 then
    raise exception 'expected two expense report ledger links, saw %', link_count;
  end if;

  select count(*) into item_count
  from public.expense_items
  where expense_report_id = 'bf111111-1111-4111-8111-111111111111'
    and status = 'accepted'
    and transaction_id is not null;

  if item_count <> 2 then
    raise exception 'expected two accepted linked expense items, saw %', item_count;
  end if;

  select status into report_status
  from public.expense_reports
  where id = 'bf111111-1111-4111-8111-111111111111';

  if report_status <> 'closed' then
    raise exception 'expected closed report after materialization, saw %', report_status;
  end if;

  select * into second_result
  from public.materialize_expense_report('bf111111-1111-4111-8111-111111111111');

  if second_result.materialized_count <> 2 or second_result.expense_total <> 280 then
    raise exception 'idempotent materialization returned unexpected result: %', row_to_json(second_result);
  end if;

  select count(*) into transaction_count
  from public.transactions
  where workspace_id = 'bbbbbbbb-bbbb-4bbb-8bbb-111111111111'
    and source_type = 'expense_report';

  if transaction_count <> 2 then
    raise exception 'idempotent call created duplicate transactions, saw %', transaction_count;
  end if;

  perform set_config(
    'request.jwt.claims',
    '{"sub":"b2222222-2222-4222-8222-222222222222","role":"authenticated"}',
    true
  );

  begin
    perform * from public.materialize_expense_report('bf111111-1111-4111-8111-111111111111');
  exception
    when others then
      if sqlerrm = 'accountable_approve_required' then
        employee_blocked := true;
      else
        raise;
      end if;
  end;

  if employee_blocked is not true then
    raise exception 'employee unexpectedly materialized an expense report';
  end if;
end;
$$;

rollback;
