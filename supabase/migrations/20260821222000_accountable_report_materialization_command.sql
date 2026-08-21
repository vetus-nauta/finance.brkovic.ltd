-- SPRINT-122R: command-owned accountable report materialization.
-- Accepted employee reports become ordinary operational ledger rows only
-- through this audited RPC command. The command is idempotent for already
-- closed reports and guarded against duplicate materialization per item.

create unique index if not exists expense_report_ledger_links_one_projection_per_item_idx
  on public.expense_report_ledger_links (expense_item_id)
  where expense_item_id is not null
    and link_type = 'materialized_projection';

create unique index if not exists expense_items_one_materialized_transaction_idx
  on public.expense_items (transaction_id)
  where transaction_id is not null;

create or replace function public.materialize_expense_report(
  p_expense_report_id uuid
)
returns table (
  expense_report_id uuid,
  materialized_count integer,
  transaction_ids uuid[],
  expense_total numeric(14,2)
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_user_id uuid := auth.uid();
  v_report record;
  v_account record;
  v_item record;
  v_created record;
  v_clean_text text;
  v_raw_text text;
  v_transaction_ids uuid[] := '{}'::uuid[];
  v_materialized_count integer := 0;
  v_expense_total numeric(14,2) := 0;
  v_existing_count integer := 0;
  v_existing_total numeric(14,2) := 0;
  v_existing_transaction_ids uuid[] := '{}'::uuid[];
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_expense_report_id is null then
    raise exception 'expense_report_required';
  end if;

  select
    er.id,
    er.organization_id,
    er.workspace_id,
    er.cash_advance_id,
    er.submitted_by,
    er.status,
    er.total_amount,
    er.currency_code,
    ca.account_id,
    ca.amount as advance_amount,
    ca.status as advance_status
  into v_report
  from public.expense_reports er
  left join public.cash_advances ca on ca.id = er.cash_advance_id
  join public.workspaces w on w.id = er.workspace_id
  where er.id = p_expense_report_id
    and w.status = 'active'
  limit 1;

  if not found then
    raise exception 'expense_report_not_found';
  end if;

  if not private.has_workspace_permission(v_report.workspace_id, 'accountable.approve') then
    raise exception 'accountable_approve_required';
  end if;

  if not private.has_workspace_permission(v_report.workspace_id, 'ledger.write') then
    raise exception 'ledger_write_required';
  end if;

  perform pg_advisory_xact_lock(hashtext('findesk_expense_report_materialize:' || p_expense_report_id::text));

  select
    count(*)::integer,
    coalesce(sum(ei.amount), 0)::numeric(14,2),
    coalesce(array_agg(erl.transaction_id order by t.row_no), '{}'::uuid[])
  into v_existing_count, v_existing_total, v_existing_transaction_ids
  from public.expense_report_ledger_links erl
  join public.expense_items ei on ei.id = erl.expense_item_id
  join public.transactions t on t.id = erl.transaction_id
  where erl.expense_report_id = p_expense_report_id
    and erl.link_type = 'materialized_projection'
    and t.status <> 'void';

  if v_report.status = 'closed' then
    expense_report_id := p_expense_report_id;
    materialized_count := v_existing_count;
    transaction_ids := v_existing_transaction_ids;
    expense_total := v_existing_total;
    return next;
    return;
  end if;

  if v_report.status <> 'approved' then
    raise exception 'expense_report_not_approved';
  end if;

  if v_existing_count > 0 then
    raise exception 'expense_report_partially_materialized';
  end if;

  if v_report.cash_advance_id is null then
    raise exception 'cash_advance_required';
  end if;

  select a.id, a.code, a.currency_code
  into v_account
  from public.accounts a
  where a.workspace_id = v_report.workspace_id
    and a.is_active = true
    and (
      a.id = v_report.account_id
      or (
        v_report.account_id is null
        and a.code = 'cash'
      )
    )
  order by case when a.id = v_report.account_id then 0 else 1 end
  limit 1;

  if not found then
    raise exception 'account_not_found';
  end if;

  if exists (
    select 1
    from public.expense_items ei
    where ei.expense_report_id = p_expense_report_id
      and ei.workspace_id <> v_report.workspace_id
  ) then
    raise exception 'expense_report_workspace_mismatch';
  end if;

  if not exists (
    select 1
    from public.expense_items ei
    where ei.expense_report_id = p_expense_report_id
      and ei.status <> 'rejected'
  ) then
    raise exception 'expense_report_empty';
  end if;

  for v_item in
    select ei.id, ei.occurred_on, ei.raw_text, ei.amount, ei.currency_code
    from public.expense_items ei
    where ei.expense_report_id = p_expense_report_id
      and ei.status <> 'rejected'
      and ei.transaction_id is null
    order by ei.occurred_on, ei.created_at, ei.id
  loop
    v_clean_text := nullif(
      btrim(regexp_replace(v_item.raw_text, '^\s*[+-]?\s*[0-9]+([,.][0-9]{1,2})?\s*', '')),
      ''
    );
    v_raw_text := '-' || v_item.amount::text || ' ' || coalesce(v_clean_text, 'расход сотрудника');

    select *
    into v_created
    from public.create_operational_entry(
      v_report.workspace_id,
      v_account.code,
      v_item.occurred_on,
      v_raw_text,
      'expense_report',
      'manual',
      'ru',
      v_item.id,
      jsonb_build_object(
        'expense_report_id', p_expense_report_id,
        'cash_advance_id', v_report.cash_advance_id,
        'submitted_by', v_report.submitted_by
      ),
      jsonb_build_object(
        'expense_report_id', p_expense_report_id,
        'expense_item_id', v_item.id,
        'cash_advance_id', v_report.cash_advance_id,
        'submitted_by', v_report.submitted_by,
        'materialized_by', v_user_id,
        'materialization_command', 'materialize_expense_report_v1'
      )
    );

    if v_created.transaction_id is null or v_created.ledger_entry_id is null then
      raise exception 'expense_item_materialization_failed';
    end if;

    update public.expense_items
    set
      transaction_id = v_created.transaction_id,
      status = 'accepted',
      metadata = metadata || jsonb_build_object(
        'materialized_at', now(),
        'materialized_by', v_user_id,
        'ledger_entry_id', v_created.ledger_entry_id,
        'row_no', v_created.row_no
      ),
      updated_at = now()
    where id = v_item.id
      and transaction_id is null;

    insert into public.expense_report_ledger_links (
      organization_id,
      workspace_id,
      expense_report_id,
      expense_item_id,
      transaction_id,
      ledger_entry_id,
      link_type
    ) values (
      v_report.organization_id,
      v_report.workspace_id,
      p_expense_report_id,
      v_item.id,
      v_created.transaction_id,
      v_created.ledger_entry_id,
      'materialized_projection'
    );

    v_transaction_ids := array_append(v_transaction_ids, v_created.transaction_id);
    v_materialized_count := v_materialized_count + 1;
    v_expense_total := (v_expense_total + v_item.amount)::numeric(14,2);
  end loop;

  update public.expense_reports
  set
    status = 'closed',
    metadata = metadata || jsonb_build_object(
      'materialized_at', now(),
      'materialized_by', v_user_id,
      'materialized_count', v_materialized_count,
      'materialized_total', v_expense_total,
      'materialized_transaction_ids', v_transaction_ids,
      'cash_advance_balance_after_report', (coalesce(v_report.advance_amount, 0) - v_report.total_amount)::numeric(14,2),
      'materialization_command', 'materialize_expense_report_v1'
    ),
    updated_at = now()
  where id = p_expense_report_id
    and status = 'approved';

  update public.cash_advances
  set
    metadata = metadata || jsonb_build_object(
      'last_materialized_expense_report_id', p_expense_report_id,
      'last_materialized_at', now(),
      'last_materialized_total', v_expense_total
    ),
    updated_at = now()
  where id = v_report.cash_advance_id;

  insert into public.approval_events (
    organization_id,
    workspace_id,
    entity_type,
    entity_id,
    event_type,
    actor_user_id,
    metadata
  ) values (
    v_report.organization_id,
    v_report.workspace_id,
    'expense_report',
    p_expense_report_id,
    'expense_report_materialized',
    v_user_id,
    jsonb_build_object(
      'materialized_count', v_materialized_count,
      'expense_total', v_expense_total,
      'transaction_ids', v_transaction_ids
    )
  );

  expense_report_id := p_expense_report_id;
  materialized_count := v_materialized_count;
  transaction_ids := v_transaction_ids;
  expense_total := v_expense_total;
  return next;
end;
$$;

revoke execute on function public.materialize_expense_report(uuid) from public, anon;
grant execute on function public.materialize_expense_report(uuid) to authenticated;
