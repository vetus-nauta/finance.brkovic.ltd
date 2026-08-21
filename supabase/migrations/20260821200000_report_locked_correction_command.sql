-- SPRINT-121R: command-owned corrections for report-locked operational rows.
-- A closed/report-included row is never mutated directly; a correction creates
-- a new operational row and links it to the original transaction.

drop policy if exists corrections_write on public.corrections;
drop policy if exists corrections_write_insert on public.corrections;
drop policy if exists corrections_write_update on public.corrections;
drop policy if exists corrections_write_delete on public.corrections;

revoke insert, update, delete on public.corrections from public, anon, authenticated;

create or replace function public.create_report_locked_correction(
  p_original_transaction_id uuid,
  p_account_code text,
  p_occurred_on date,
  p_raw_text text,
  p_reason text
)
returns table (
  correction_id uuid,
  original_transaction_id uuid,
  correction_transaction_id uuid,
  correction_row_no integer,
  counted boolean,
  transaction_status text,
  review_status text
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_original record;
  v_created record;
  v_user_id uuid := auth.uid();
  v_reason text := btrim(coalesce(p_reason, ''));
  v_account_code text;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_original_transaction_id is null then
    raise exception 'original_transaction_required';
  end if;

  if btrim(coalesce(p_raw_text, '')) = '' then
    raise exception 'raw_text_required';
  end if;

  if p_occurred_on is null then
    raise exception 'occurred_on_required';
  end if;

  if v_reason = '' then
    raise exception 'correction_reason_required';
  end if;

  select
    t.id,
    t.organization_id,
    t.workspace_id,
    t.account_id,
    t.row_no,
    t.status,
    a.code as account_code
  into v_original
  from public.transactions t
  join public.workspaces w on w.id = t.workspace_id
  left join public.accounts a on a.id = t.account_id
  where t.id = p_original_transaction_id
    and t.status <> 'void'
    and w.status = 'active'
  limit 1;

  if not found then
    raise exception 'original_transaction_not_found';
  end if;

  if v_original.status not in ('included_in_report', 'closed') then
    raise exception 'original_transaction_not_report_locked';
  end if;

  if not private.has_workspace_permission(v_original.workspace_id, 'ledger.correct') then
    raise exception 'ledger_correct_required';
  end if;

  if not private.has_workspace_permission(v_original.workspace_id, 'ledger.write') then
    raise exception 'ledger_write_required';
  end if;

  v_account_code := nullif(btrim(coalesce(p_account_code, '')), '');

  if v_account_code is null then
    v_account_code := coalesce(v_original.account_code, 'cash');
  end if;

  select *
  into v_created
  from public.create_operational_entry(
    v_original.workspace_id,
    v_account_code,
    p_occurred_on,
    p_raw_text,
    'correction',
    'manual',
    'ru',
    v_original.id,
    jsonb_build_object(
      'original_transaction_id', v_original.id,
      'original_row_no', v_original.row_no,
      'reason', v_reason
    ),
    jsonb_build_object(
      'correction_for_transaction_id', v_original.id,
      'correction_for_row_no', v_original.row_no,
      'correction_reason', v_reason,
      'parser', 'foundation_report_locked_correction_rpc_v1'
    )
  );

  insert into public.corrections (
    organization_id,
    workspace_id,
    original_transaction_id,
    correction_transaction_id,
    reason,
    status,
    created_by
  ) values (
    v_original.organization_id,
    v_original.workspace_id,
    v_original.id,
    v_created.transaction_id,
    v_reason,
    'applied',
    v_user_id
  )
  returning id into correction_id;

  update public.transactions
  set metadata = metadata || jsonb_build_object(
    'correction_id', correction_id,
    'correction_for_transaction_id', v_original.id,
    'correction_for_row_no', v_original.row_no,
    'correction_reason', v_reason
  ),
  updated_at = now()
  where id = v_created.transaction_id;

  insert into public.approval_events (
    organization_id,
    workspace_id,
    entity_type,
    entity_id,
    event_type,
    actor_user_id,
    metadata
  ) values (
    v_original.organization_id,
    v_original.workspace_id,
    'correction',
    correction_id,
    'report_locked_correction_created',
    v_user_id,
    jsonb_build_object(
      'original_transaction_id', v_original.id,
      'original_row_no', v_original.row_no,
      'correction_transaction_id', v_created.transaction_id,
      'correction_row_no', v_created.row_no,
      'reason', v_reason,
      'counted', v_created.counted
    )
  );

  original_transaction_id := v_original.id;
  correction_transaction_id := v_created.transaction_id;
  correction_row_no := v_created.row_no;
  counted := v_created.counted;
  transaction_status := v_created.transaction_status;
  review_status := v_created.review_status;

  return next;
end;
$$;

revoke execute on function public.create_report_locked_correction(
  uuid, text, date, text, text
) from public, anon;
grant execute on function public.create_report_locked_correction(
  uuid, text, date, text, text
) to authenticated;
