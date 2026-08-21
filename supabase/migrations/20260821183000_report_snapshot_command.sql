-- SPRINT-113R: command-owned period report snapshot.
-- Reports are generated from operational entries. Client code may read report
-- tables, but creation/closure must happen through an audited RPC command.

drop policy if exists period_closures_write on public.period_closures;
drop policy if exists period_closures_write_insert on public.period_closures;
drop policy if exists period_closures_write_update on public.period_closures;
drop policy if exists period_closures_write_delete on public.period_closures;

drop policy if exists report_snapshots_write on public.report_snapshots;
drop policy if exists report_snapshots_write_insert on public.report_snapshots;
drop policy if exists report_snapshots_write_update on public.report_snapshots;
drop policy if exists report_snapshots_write_delete on public.report_snapshots;

drop policy if exists report_packages_write on public.report_packages;
drop policy if exists report_packages_write_insert on public.report_packages;
drop policy if exists report_packages_write_update on public.report_packages;
drop policy if exists report_packages_write_delete on public.report_packages;

drop policy if exists report_package_items_write on public.report_package_items;
drop policy if exists report_package_items_write_insert on public.report_package_items;
drop policy if exists report_package_items_write_update on public.report_package_items;
drop policy if exists report_package_items_write_delete on public.report_package_items;

revoke insert, update, delete on public.period_closures from public, anon, authenticated;
revoke insert, update, delete on public.report_snapshots from public, anon, authenticated;
revoke insert, update, delete on public.report_packages from public, anon, authenticated;
revoke insert, update, delete on public.report_package_items from public, anon, authenticated;

create or replace function private.guard_report_locked_transactions()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
begin
  if old.status in ('included_in_report', 'closed')
    and coalesce(current_setting('app.findesk_report_command', true), '') <> 'on'
  then
    raise exception 'report_locked_transaction';
  end if;

  return new;
end;
$$;

drop trigger if exists guard_report_locked_transactions on public.transactions;
create trigger guard_report_locked_transactions
  before update on public.transactions
  for each row
  execute function private.guard_report_locked_transactions();

create or replace function public.create_period_report_snapshot(
  p_workspace_id uuid,
  p_period_start date,
  p_period_end date,
  p_title text default null
)
returns table (
  report_snapshot_id uuid,
  period_closure_id uuid,
  included_count integer,
  review_count integer,
  income_total numeric(14,2),
  expense_total numeric(14,2),
  net_total numeric(14,2)
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_workspace record;
  v_user_id uuid := auth.uid();
  v_source_ids uuid[];
  v_title text;
  v_totals jsonb;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_workspace_id is null then
    raise exception 'workspace_required';
  end if;

  if p_period_start is null or p_period_end is null then
    raise exception 'period_required';
  end if;

  if p_period_end < p_period_start then
    raise exception 'invalid_period';
  end if;

  select w.id, w.organization_id, w.name, w.currency_code
  into v_workspace
  from public.workspaces w
  where w.id = p_workspace_id
    and w.status = 'active'
  limit 1;

  if not found then
    raise exception 'workspace_not_found';
  end if;

  if not private.has_workspace_permission(p_workspace_id, 'reports.manage') then
    raise exception 'reports_manage_required';
  end if;

  if not private.has_workspace_permission(p_workspace_id, 'period.close') then
    raise exception 'period_close_required';
  end if;

  select coalesce(array_agg(t.id order by t.row_no), '{}') into v_source_ids
  from public.transactions t
  where t.workspace_id = p_workspace_id
    and t.status in ('open', 'needs_review')
    and t.occurred_on between p_period_start and p_period_end;

  included_count := cardinality(v_source_ids);

  if included_count = 0 then
    raise exception 'no_report_entries';
  end if;

  select
    coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)::numeric(14,2),
    coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)::numeric(14,2),
    count(distinct t.id) filter (
      where t.status = 'needs_review'
        or coalesce(le.review_status, 'review') <> 'accepted'
    )::integer
  into income_total, expense_total, review_count
  from public.transactions t
  left join public.ledger_entries le on le.transaction_id = t.id
  where t.id = any(v_source_ids);

  net_total := (income_total - expense_total)::numeric(14,2);
  v_title := nullif(btrim(coalesce(p_title, '')), '');

  if v_title is null then
    v_title := 'Отчет от ' || to_char(current_date, 'DD.MM.YYYY')
      || ' · период ' || to_char(p_period_start, 'DD.MM.YYYY')
      || ' — ' || to_char(p_period_end, 'DD.MM.YYYY');
  end if;

  select jsonb_build_object(
    'currency', v_workspace.currency_code,
    'period_start', p_period_start,
    'period_end', p_period_end,
    'entry_count', included_count,
    'review_count', review_count,
    'income_total', income_total,
    'expense_total', expense_total,
    'net_total', net_total,
    'accounts', coalesce((
      select jsonb_agg(account_row order by account_row->>'account_code')
      from (
        select jsonb_build_object(
          'account_code', a.code,
          'label', a.label,
          'income_total', coalesce(sum(le.amount) filter (where le.direction = 'income'), 0)::numeric(14,2),
          'expense_total', coalesce(sum(le.amount) filter (where le.direction = 'expense'), 0)::numeric(14,2),
          'entry_count', count(distinct t.id)
        ) as account_row
        from public.transactions t
        left join public.accounts a on a.id = t.account_id
        left join public.ledger_entries le on le.transaction_id = t.id
        where t.id = any(v_source_ids)
        group by a.code, a.label
      ) account_rows
    ), '[]'::jsonb),
    'categories', coalesce((
      select jsonb_agg(category_row order by category_row->>'label')
      from (
        select jsonb_build_object(
          'category_code', coalesce(c.code, le.metadata->>'category_code', 'uncategorized'),
          'label', coalesce(c.label->>'ru', c.label->>'en', c.code, le.metadata->>'category_code', 'Без категории'),
          'direction', le.direction,
          'total', coalesce(sum(le.amount), 0)::numeric(14,2),
          'entry_count', count(distinct t.id),
          'review_count', count(distinct t.id) filter (where le.review_status <> 'accepted')
        ) as category_row
        from public.transactions t
        join public.ledger_entries le on le.transaction_id = t.id
        left join public.categories c on c.id = le.category_id
        where t.id = any(v_source_ids)
        group by coalesce(c.code, le.metadata->>'category_code', 'uncategorized'),
          coalesce(c.label->>'ru', c.label->>'en', c.code, le.metadata->>'category_code', 'Без категории'),
          le.direction
      ) category_rows
    ), '[]'::jsonb)
  ) into v_totals;

  insert into public.period_closures (
    organization_id,
    workspace_id,
    period_start,
    period_end,
    status,
    closed_by,
    closed_at,
    metadata
  ) values (
    v_workspace.organization_id,
    p_workspace_id,
    p_period_start,
    p_period_end,
    'closed',
    v_user_id,
    now(),
    jsonb_build_object(
      'source_transaction_ids', v_source_ids,
      'entry_count', included_count,
      'report_command', 'create_period_report_snapshot_v1'
    )
  )
  returning id into period_closure_id;

  insert into public.report_snapshots (
    organization_id,
    workspace_id,
    period_closure_id,
    title,
    period_start,
    period_end,
    status,
    source_transaction_ids,
    totals,
    content_hash,
    created_by
  ) values (
    v_workspace.organization_id,
    p_workspace_id,
    period_closure_id,
    v_title,
    p_period_start,
    p_period_end,
    'created',
    v_source_ids,
    v_totals,
    md5(v_totals::text),
    v_user_id
  )
  returning id into report_snapshot_id;

  perform set_config('app.findesk_report_command', 'on', true);

  update public.transactions
  set status = 'included_in_report',
      metadata = metadata || jsonb_build_object(
        'report_snapshot_id', report_snapshot_id,
        'period_closure_id', period_closure_id,
        'included_in_report_at', now(),
        'report_command', 'create_period_report_snapshot_v1'
      ),
      updated_at = now()
  where id = any(v_source_ids);

  perform set_config('app.findesk_report_command', 'off', true);

  insert into public.approval_events (
    organization_id,
    workspace_id,
    entity_type,
    entity_id,
    event_type,
    actor_user_id,
    metadata
  ) values (
    v_workspace.organization_id,
    p_workspace_id,
    'report_snapshot',
    report_snapshot_id,
    'report_snapshot_created',
    v_user_id,
    jsonb_build_object(
      'period_closure_id', period_closure_id,
      'period_start', p_period_start,
      'period_end', p_period_end,
      'source_transaction_ids', v_source_ids,
      'entry_count', included_count,
      'review_count', review_count,
      'income_total', income_total,
      'expense_total', expense_total,
      'net_total', net_total
    )
  );

  return next;
end;
$$;

revoke execute on function public.create_period_report_snapshot(
  uuid, date, date, text
) from public, anon;
grant execute on function public.create_period_report_snapshot(
  uuid, date, date, text
) to authenticated;
