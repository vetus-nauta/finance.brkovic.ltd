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
    '51111111-1111-4111-8111-111111111111',
    'authenticated',
    'authenticated',
    'foundation-command-owner@example.invalid',
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
    '52222222-2222-4222-8222-222222222222',
    'authenticated',
    'authenticated',
    'foundation-command-employee@example.invalid',
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
  ('51111111-1111-4111-8111-111111111111', 'foundation-command-owner@example.invalid', 'Command Owner'),
  ('52222222-2222-4222-8222-222222222222', 'foundation-command-employee@example.invalid', 'Command Employee')
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  '51111111-1111-4111-8111-111111111111',
  'Foundation Command Organization',
  'foundation-command-organization',
  '{"foundation_command_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
  'Foundation Command Yacht',
  'yacht',
  '{"foundation_command_smoke":true}'::jsonb
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
    '5c111111-1111-4111-8111-111111111111',
    '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '51111111-1111-4111-8111-111111111111',
    'owner',
    'active',
    'workspace',
    now(),
    now()
  ),
  (
    '5c222222-2222-4222-8222-222222222222',
    '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '52222222-2222-4222-8222-222222222222',
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
values
  (
    '5ddddddd-dddd-4ddd-8ddd-dddddddddddd',
    '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'cash',
    'Cash',
    'cash'
  ),
  (
    '5eeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
    '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'card',
    'Card',
    'card'
  )
on conflict (id) do update
set label = excluded.label,
    account_type = excluded.account_type,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = '51111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"51111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  cash_expense record;
  cash_expense_update record;
  cash_income record;
  card_expense record;
  no_sign record;
  quick_note_conversion record;
  transaction_count integer;
  ledger_count integer;
  audit_count integer;
  quick_note_audit_count integer;
  updated_amount numeric(14,2);
  voided_card_status text;
  direct_insert_blocked boolean := false;
  direct_ledger_insert_blocked boolean := false;
  direct_audit_insert_blocked boolean := false;
  direct_audit_update_blocked boolean := false;
  direct_audit_delete_blocked boolean := false;
  direct_row_count integer;
  manual_card_income_blocked boolean := false;
begin
  select * into cash_expense
  from public.create_operational_entry(
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
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

  if cash_expense.row_no <> 1
    or cash_expense.counted is not true
    or cash_expense.ledger_entry_id is null
    or cash_expense.transaction_status <> 'open'
    or cash_expense.review_status <> 'accepted'
  then
    raise exception 'cash expense command returned unexpected result: %', row_to_json(cash_expense);
  end if;

  select * into cash_income
  from public.create_operational_entry(
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
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

  if cash_income.row_no <> 2 or cash_income.counted is not true or cash_income.ledger_entry_id is null then
    raise exception 'cash income command returned unexpected result: %', row_to_json(cash_income);
  end if;

  select * into card_expense
  from public.create_operational_entry(
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'card',
    '2026-08-21',
    '-40 топливо',
    'manual',
    'manual',
    'ru',
    null,
    '{}'::jsonb,
    '{}'::jsonb
  );

  if card_expense.row_no <> 3 or card_expense.counted is not true or card_expense.ledger_entry_id is null then
    raise exception 'card expense command returned unexpected result: %', row_to_json(card_expense);
  end if;

  begin
    perform *
    from public.create_operational_entry(
      '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      'card',
      '2026-08-21',
      '+100 ручное поступление на карту',
      'manual',
      'manual',
      'ru',
      null,
      '{}'::jsonb,
      '{}'::jsonb
    );
    raise exception 'manual card income was unexpectedly accepted';
  exception
    when others then
      if sqlerrm = 'manual_card_income_blocked' then
        manual_card_income_blocked := true;
      else
        raise;
      end if;
  end;

  if manual_card_income_blocked is not true then
    raise exception 'manual card income guard did not fire';
  end if;

  select * into no_sign
  from public.create_operational_entry(
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'cash',
    '2026-08-21',
    '350 продукты без знака',
    'manual',
    'manual',
    'ru',
    null,
    '{}'::jsonb,
    '{}'::jsonb
  );

  if no_sign.row_no <> 4
    or no_sign.counted is not false
    or no_sign.ledger_entry_id is not null
    or no_sign.transaction_status <> 'needs_review'
    or no_sign.review_status is not null
  then
    raise exception 'no-sign command returned unexpected result: %', row_to_json(no_sign);
  end if;

  select * into cash_expense_update
  from public.update_operational_entry(
    cash_expense.transaction_id,
    'cash',
    '2026-08-21',
    '-300 продукты исправлено',
    'manual',
    'ru',
    '{}'::jsonb
  );

  if cash_expense_update.row_no <> 1
    or cash_expense_update.counted is not true
    or cash_expense_update.ledger_entry_id is null
    or cash_expense_update.transaction_status <> 'open'
    or cash_expense_update.review_status <> 'accepted'
  then
    raise exception 'cash expense update returned unexpected result: %', row_to_json(cash_expense_update);
  end if;

  select le.amount into updated_amount
  from public.ledger_entries le
  where le.transaction_id = cash_expense.transaction_id;

  if updated_amount <> 300.00 then
    raise exception 'expected updated ledger amount 300.00, saw %', updated_amount;
  end if;

  perform *
  from public.void_operational_entry(card_expense.transaction_id);

  select t.status into voided_card_status
  from public.transactions t
  where t.id = card_expense.transaction_id;

  if voided_card_status <> 'void' then
    raise exception 'expected voided card transaction status, saw %', voided_card_status;
  end if;

  select count(*) into transaction_count
  from public.transactions
  where workspace_id = '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

  select count(*) into ledger_count
  from public.ledger_entries
  where workspace_id = '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

  select count(*) into audit_count
  from public.approval_events
  where workspace_id = '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
    and entity_type = 'transaction'
    and event_type in (
      'operational_entry_created',
      'operational_entry_needs_review',
      'operational_entry_updated',
      'operational_entry_voided'
    );

  if transaction_count <> 4 then
    raise exception 'expected 4 saved transactions after rejected card income, saw %', transaction_count;
  end if;

  if ledger_count <> 2 then
    raise exception 'expected 2 live counted ledger entries after voiding card expense, saw %', ledger_count;
  end if;

  if audit_count <> 6 then
    raise exception 'expected 6 audit events, saw %', audit_count;
  end if;

  begin
    insert into public.approval_events (
      organization_id,
      workspace_id,
      entity_type,
      entity_id,
      event_type,
      actor_user_id,
      note,
      metadata
    ) values (
      '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      'transaction',
      cash_income.transaction_id,
      'operational_entry_created',
      '51111111-1111-4111-8111-111111111111',
      'direct audit spoof',
      '{}'::jsonb
    );
  exception
    when insufficient_privilege or check_violation then
      direct_audit_insert_blocked := true;
    when others then
      if sqlerrm like '%row-level security%' then
        direct_audit_insert_blocked := true;
      else
        raise;
      end if;
  end;

  if direct_audit_insert_blocked is not true then
    raise exception 'direct approval event insert spoof was unexpectedly accepted';
  end if;

  begin
    update public.approval_events
    set note = 'direct audit update spoof'
    where workspace_id = '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
      and entity_id = cash_income.transaction_id;

    get diagnostics direct_row_count = row_count;
    if direct_row_count = 0 then
      direct_audit_update_blocked := true;
    end if;
  exception
    when insufficient_privilege or check_violation then
      direct_audit_update_blocked := true;
    when others then
      if sqlerrm like '%row-level security%' then
        direct_audit_update_blocked := true;
      else
        raise;
      end if;
  end;

  if direct_audit_update_blocked is not true then
    raise exception 'direct approval event update spoof was unexpectedly accepted';
  end if;

  begin
    delete from public.approval_events
    where workspace_id = '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
      and entity_id = cash_income.transaction_id;

    get diagnostics direct_row_count = row_count;
    if direct_row_count = 0 then
      direct_audit_delete_blocked := true;
    end if;
  exception
    when insufficient_privilege or check_violation then
      direct_audit_delete_blocked := true;
    when others then
      if sqlerrm like '%row-level security%' then
        direct_audit_delete_blocked := true;
      else
        raise;
      end if;
  end;

  if direct_audit_delete_blocked is not true then
    raise exception 'direct approval event delete spoof was unexpectedly accepted';
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
      '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      '5ddddddd-dddd-4ddd-8ddd-dddddddddddd',
      '2026-08-21',
      99,
      '-999 direct transaction bypass',
      '51111111-1111-4111-8111-111111111111'
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
    raise exception 'direct transaction insert bypass was unexpectedly accepted';
  end if;

  update public.transactions
  set raw_text = '-1 direct transaction update bypass'
  where id = cash_income.transaction_id;
  get diagnostics direct_row_count = row_count;

  if direct_row_count <> 0 then
    raise exception 'direct transaction update bypass affected % rows', direct_row_count;
  end if;

  delete from public.transactions
  where id = cash_income.transaction_id;
  get diagnostics direct_row_count = row_count;

  if direct_row_count <> 0 then
    raise exception 'direct transaction delete bypass affected % rows', direct_row_count;
  end if;

  begin
    insert into public.ledger_entries (
      organization_id,
      workspace_id,
      transaction_id,
      account_id,
      direction,
      amount
    ) values (
      '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
      '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      cash_income.transaction_id,
      '5ddddddd-dddd-4ddd-8ddd-dddddddddddd',
      'expense',
      999.00
    );
  exception
    when insufficient_privilege or check_violation then
      direct_ledger_insert_blocked := true;
    when others then
      if sqlerrm like '%row-level security%' then
        direct_ledger_insert_blocked := true;
      else
        raise;
      end if;
  end;

  if direct_ledger_insert_blocked is not true then
    raise exception 'direct ledger insert bypass was unexpectedly accepted';
  end if;

  update public.ledger_entries
  set amount = 1.00
  where transaction_id = cash_income.transaction_id;
  get diagnostics direct_row_count = row_count;

  if direct_row_count <> 0 then
    raise exception 'direct ledger update bypass affected % rows', direct_row_count;
  end if;

  delete from public.ledger_entries
  where transaction_id = cash_income.transaction_id;
  get diagnostics direct_row_count = row_count;

  if direct_row_count <> 0 then
    raise exception 'direct ledger delete bypass affected % rows', direct_row_count;
  end if;

  insert into public.quick_notes (
    id,
    organization_id,
    workspace_id,
    author_user_id,
    body,
    status
  ) values (
    '5f555555-5555-4555-8555-555555555555',
    '5aaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    '51111111-1111-4111-8111-111111111111',
    '-20 продукты' || chr(10) || '+100 поступило от судовладельца',
    'draft'
  );

  select * into quick_note_conversion
  from public.convert_quick_note_to_operational_entries(
    '5f555555-5555-4555-8555-555555555555',
    'cash',
    '2026-08-21',
    'ru'
  );

  if quick_note_conversion.converted_count <> 2
    or cardinality(quick_note_conversion.transaction_ids) <> 2
  then
    raise exception 'quick note conversion returned unexpected result: %', row_to_json(quick_note_conversion);
  end if;

  select count(*) into quick_note_audit_count
  from public.approval_events
  where workspace_id = '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'
    and entity_type = 'quick_note'
    and entity_id = '5f555555-5555-4555-8555-555555555555'
    and event_type = 'quick_note_converted';

  if quick_note_audit_count <> 1 then
    raise exception 'expected one quick note conversion audit event, saw %', quick_note_audit_count;
  end if;
end;
$$;

reset role;

set local role authenticated;
set local request.jwt.claim.sub = '52222222-2222-4222-8222-222222222222';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"52222222-2222-4222-8222-222222222222","role":"authenticated"}';

do $$
declare
  rejected boolean := false;
begin
  begin
    perform *
    from public.create_operational_entry(
      '5bbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
      'cash',
      '2026-08-21',
      '-10 employee forbidden operational write',
      'manual',
      'manual',
      'ru',
      null,
      '{}'::jsonb,
      '{}'::jsonb
    );
    raise exception 'employee operational write was unexpectedly accepted';
  exception
    when others then
      if sqlerrm = 'ledger_write_required' then
        rejected := true;
      else
        raise;
      end if;
  end;

  if rejected is not true then
    raise exception 'employee ledger.write rejection did not fire';
  end if;
end;
$$;

reset role;

select 'foundation operational entry command smoke ok' as result;

rollback;
