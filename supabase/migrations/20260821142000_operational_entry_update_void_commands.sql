-- SPRINT-110R: operational journal edit/delete command slice.
-- Keep edit/delete on the same command boundary as create so the web UI does
-- not need to manually reshape transactions and ledger entries.

drop policy if exists approval_events_operational_entry_write on public.approval_events;
create policy approval_events_operational_entry_write on public.approval_events
  for insert to authenticated
  with check (
    entity_type = 'transaction'
    and event_type in (
      'operational_entry_created',
      'operational_entry_needs_review',
      'operational_entry_updated',
      'operational_entry_voided'
    )
    and private.has_workspace_permission(workspace_id, 'ledger.write')
  );

create or replace function public.update_operational_entry(
  p_transaction_id uuid,
  p_account_code text,
  p_occurred_on date,
  p_raw_text text,
  p_source_channel text default 'manual',
  p_source_language text default 'ru',
  p_metadata jsonb default '{}'::jsonb
)
returns table (
  transaction_id uuid,
  ledger_entry_id uuid,
  row_no integer,
  counted boolean,
  transaction_status text,
  review_status text
)
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_existing record;
  v_account record;
  v_raw text := btrim(coalesce(p_raw_text, ''));
  v_source_channel text := lower(btrim(coalesce(p_source_channel, 'manual')));
  v_source_language text := lower(btrim(coalesce(p_source_language, 'ru')));
  v_metadata jsonb := coalesce(p_metadata, '{}'::jsonb);
  v_user_id uuid := auth.uid();
  v_signed_match text[];
  v_unsigned_match text[];
  v_sign text;
  v_amount numeric(14,2);
  v_direction text;
  v_ledger_entry_id uuid;
  v_counted boolean := false;
  v_transaction_status text := 'needs_review';
  v_review_status text := null;
  v_parser_reason text := null;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_transaction_id is null then
    raise exception 'transaction_required';
  end if;

  if v_raw = '' then
    raise exception 'raw_text_required';
  end if;

  if p_occurred_on is null then
    raise exception 'occurred_on_required';
  end if;

  if not exists (
    select 1 from public.input_channels c
    where c.code = v_source_channel and c.is_active = true
  ) then
    raise exception 'unsupported_input_channel:%', v_source_channel;
  end if;

  if not exists (
    select 1 from public.language_packs l
    where l.code = v_source_language and l.is_active = true
  ) then
    raise exception 'unsupported_language:%', v_source_language;
  end if;

  select t.id, t.organization_id, t.workspace_id, t.row_no, t.status
  into v_existing
  from public.transactions t
  join public.workspaces w on w.id = t.workspace_id
  where t.id = p_transaction_id
    and t.status <> 'void'
    and w.status = 'active'
  limit 1;

  if not found then
    raise exception 'transaction_not_found';
  end if;

  if not private.has_workspace_permission(v_existing.workspace_id, 'ledger.write') then
    raise exception 'ledger_write_required';
  end if;

  select a.id, a.organization_id, a.workspace_id, a.account_type, a.currency_code
  into v_account
  from public.accounts a
  where a.workspace_id = v_existing.workspace_id
    and a.code = btrim(coalesce(p_account_code, 'cash'))
    and a.is_active = true
  limit 1;

  if not found then
    raise exception 'account_not_found';
  end if;

  select regexp_match(v_raw, '^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)') into v_signed_match;

  if v_signed_match is not null then
    v_sign := v_signed_match[1];
    v_amount := replace(v_signed_match[2], ',', '.')::numeric(14,2);
    v_direction := case when v_sign = '+' then 'income' else 'expense' end;
    v_counted := true;
    v_transaction_status := 'open';
    v_review_status := 'accepted';
  else
    select regexp_match(v_raw, '^([0-9]+(?:[.,][0-9]{1,2})?)') into v_unsigned_match;

    if v_unsigned_match is not null then
      v_amount := replace(v_unsigned_match[1], ',', '.')::numeric(14,2);
      v_parser_reason := 'missing_sign';
    else
      v_parser_reason := 'amount_missing';
    end if;
  end if;

  if v_amount is not null and v_amount <= 0 then
    raise exception 'amount_must_be_positive';
  end if;

  if v_counted and v_account.account_type = 'card' and v_direction = 'income' then
    raise exception 'manual_card_income_blocked';
  end if;

  update public.transactions
  set
    account_id = v_account.id,
    occurred_on = p_occurred_on,
    raw_text = v_raw,
    status = v_transaction_status,
    metadata = v_metadata || jsonb_build_object(
      'account_code', btrim(coalesce(p_account_code, 'cash')),
      'source_channel', v_source_channel,
      'source_language', v_source_language,
      'parser', 'foundation_operational_entry_update_rpc_v1',
      'counted', v_counted,
      'candidate_amount', v_amount,
      'candidate_direction', v_direction,
      'parser_reason', v_parser_reason
    ),
    updated_at = now()
  where id = v_existing.id;

  if v_counted then
    select le.id into v_ledger_entry_id
    from public.ledger_entries le
    where le.transaction_id = v_existing.id
    order by le.created_at asc
    limit 1;

    if v_ledger_entry_id is null then
      insert into public.ledger_entries (
        organization_id,
        workspace_id,
        transaction_id,
        account_id,
        direction,
        amount,
        currency_code,
        review_status,
        metadata
      ) values (
        v_existing.organization_id,
        v_existing.workspace_id,
        v_existing.id,
        v_account.id,
        v_direction,
        v_amount,
        v_account.currency_code,
        v_review_status,
        jsonb_build_object(
          'account_code', btrim(coalesce(p_account_code, 'cash')),
          'source_channel', v_source_channel,
          'source_language', v_source_language,
          'parser', 'foundation_operational_entry_update_rpc_v1'
        )
      )
      returning id into v_ledger_entry_id;
    else
      update public.ledger_entries
      set
        account_id = v_account.id,
        direction = v_direction,
        amount = v_amount,
        currency_code = v_account.currency_code,
        review_status = v_review_status,
        metadata = metadata || jsonb_build_object(
          'account_code', btrim(coalesce(p_account_code, 'cash')),
          'source_channel', v_source_channel,
          'source_language', v_source_language,
          'parser', 'foundation_operational_entry_update_rpc_v1'
        ),
        updated_at = now()
      where id = v_ledger_entry_id;
    end if;

    delete from public.ledger_entries
    where public.ledger_entries.transaction_id = v_existing.id
      and id <> v_ledger_entry_id;
  else
    delete from public.ledger_entries
    where public.ledger_entries.transaction_id = v_existing.id;
  end if;

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
    v_existing.organization_id,
    v_existing.workspace_id,
    'transaction',
    v_existing.id,
    'operational_entry_updated',
    v_user_id,
    null,
    jsonb_build_object(
      'row_no', v_existing.row_no,
      'account_code', btrim(coalesce(p_account_code, 'cash')),
      'source_channel', v_source_channel,
      'source_language', v_source_language,
      'counted', v_counted,
      'candidate_amount', v_amount,
      'candidate_direction', v_direction,
      'parser_reason', v_parser_reason
    )
  );

  return query select
    v_existing.id,
    v_ledger_entry_id,
    v_existing.row_no,
    v_counted,
    v_transaction_status,
    v_review_status;
end;
$$;

create or replace function public.void_operational_entry(
  p_transaction_id uuid
)
returns table (
  transaction_id uuid,
  row_no integer,
  voided boolean
)
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_existing record;
  v_user_id uuid := auth.uid();
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_transaction_id is null then
    raise exception 'transaction_required';
  end if;

  select t.id, t.organization_id, t.workspace_id, t.row_no, t.status
  into v_existing
  from public.transactions t
  join public.workspaces w on w.id = t.workspace_id
  where t.id = p_transaction_id
    and t.status <> 'void'
    and w.status = 'active'
  limit 1;

  if not found then
    raise exception 'transaction_not_found';
  end if;

  if not private.has_workspace_permission(v_existing.workspace_id, 'ledger.write') then
    raise exception 'ledger_write_required';
  end if;

  delete from public.ledger_entries
  where public.ledger_entries.transaction_id = v_existing.id;

  update public.transactions
  set
    status = 'void',
    metadata = metadata || jsonb_build_object(
      'voided_by', v_user_id,
      'voided_at', now(),
      'parser', 'foundation_operational_entry_void_rpc_v1'
    ),
    updated_at = now()
  where id = v_existing.id;

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
    v_existing.organization_id,
    v_existing.workspace_id,
    'transaction',
    v_existing.id,
    'operational_entry_voided',
    v_user_id,
    null,
    jsonb_build_object('row_no', v_existing.row_no)
  );

  return query select v_existing.id, v_existing.row_no, true;
end;
$$;

revoke execute on function public.update_operational_entry(
  uuid, text, date, text, text, text, jsonb
) from public, anon;
grant execute on function public.update_operational_entry(
  uuid, text, date, text, text, text, jsonb
) to authenticated;

revoke execute on function public.void_operational_entry(uuid) from public, anon;
grant execute on function public.void_operational_entry(uuid) to authenticated;
