-- SPRINT-122R: command-owned send/accept lifecycle for reports and packages.
-- Status transitions are audited and never performed by direct client DML.

create or replace function public.set_report_snapshot_delivery_status(
  p_report_snapshot_id uuid,
  p_next_status text,
  p_note text default null
)
returns table (
  report_snapshot_id uuid,
  previous_status text,
  status text
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_report record;
  v_user_id uuid := auth.uid();
  v_next_status text := lower(btrim(coalesce(p_next_status, '')));
  v_note text := nullif(btrim(coalesce(p_note, '')), '');
  v_event_type text;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_report_snapshot_id is null then
    raise exception 'report_snapshot_required';
  end if;

  if v_next_status not in ('sent', 'accepted') then
    raise exception 'unsupported_report_status:%', v_next_status;
  end if;

  select rs.id, rs.organization_id, rs.workspace_id, rs.status
  into v_report
  from public.report_snapshots rs
  where rs.id = p_report_snapshot_id
    and rs.status <> 'void'
  limit 1;

  if not found then
    raise exception 'report_snapshot_not_found';
  end if;

  if not private.has_workspace_permission(v_report.workspace_id, 'reports.manage') then
    raise exception 'reports_manage_required';
  end if;

  if v_report.status = v_next_status then
    report_snapshot_id := v_report.id;
    previous_status := v_report.status;
    status := v_report.status;
    return next;
    return;
  end if;

  if v_next_status = 'sent' and v_report.status not in ('created', 'returned_for_revision') then
    raise exception 'invalid_report_status_transition:%:%', v_report.status, v_next_status;
  end if;

  if v_next_status = 'accepted' and v_report.status <> 'sent' then
    raise exception 'invalid_report_status_transition:%:%', v_report.status, v_next_status;
  end if;

  update public.report_snapshots
  set status = v_next_status,
      totals = totals || jsonb_build_object(
        v_next_status || '_at', now(),
        v_next_status || '_by', v_user_id
      ),
      updated_at = now()
  where id = v_report.id;

  v_event_type := case
    when v_next_status = 'sent' then 'report_snapshot_sent'
    else 'report_snapshot_accepted'
  end;

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
    v_report.organization_id,
    v_report.workspace_id,
    'report_snapshot',
    v_report.id,
    v_event_type,
    v_user_id,
    v_note,
    jsonb_build_object(
      'previous_status', v_report.status,
      'next_status', v_next_status
    )
  );

  report_snapshot_id := v_report.id;
  previous_status := v_report.status;
  status := v_next_status;
  return next;
end;
$$;

create or replace function public.set_report_package_delivery_status(
  p_report_package_id uuid,
  p_next_status text,
  p_note text default null
)
returns table (
  report_package_id uuid,
  previous_status text,
  status text
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_package record;
  v_user_id uuid := auth.uid();
  v_next_status text := lower(btrim(coalesce(p_next_status, '')));
  v_note text := nullif(btrim(coalesce(p_note, '')), '');
  v_event_type text;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_report_package_id is null then
    raise exception 'report_package_required';
  end if;

  if v_next_status not in ('sent', 'accepted') then
    raise exception 'unsupported_report_status:%', v_next_status;
  end if;

  select rp.id, rp.organization_id, rp.workspace_id, rp.status
  into v_package
  from public.report_packages rp
  where rp.id = p_report_package_id
    and rp.status <> 'void'
  limit 1;

  if not found then
    raise exception 'report_package_not_found';
  end if;

  if not private.has_workspace_permission(v_package.workspace_id, 'reports.manage') then
    raise exception 'reports_manage_required';
  end if;

  if v_package.status = v_next_status then
    report_package_id := v_package.id;
    previous_status := v_package.status;
    status := v_package.status;
    return next;
    return;
  end if;

  if v_next_status = 'sent' and v_package.status <> 'created' then
    raise exception 'invalid_report_package_status_transition:%:%', v_package.status, v_next_status;
  end if;

  if v_next_status = 'accepted' and v_package.status <> 'sent' then
    raise exception 'invalid_report_package_status_transition:%:%', v_package.status, v_next_status;
  end if;

  update public.report_packages
  set status = v_next_status,
      updated_at = now()
  where id = v_package.id;

  v_event_type := case
    when v_next_status = 'sent' then 'report_package_sent'
    else 'report_package_accepted'
  end;

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
    v_package.organization_id,
    v_package.workspace_id,
    'report_package',
    v_package.id,
    v_event_type,
    v_user_id,
    v_note,
    jsonb_build_object(
      'previous_status', v_package.status,
      'next_status', v_next_status
    )
  );

  report_package_id := v_package.id;
  previous_status := v_package.status;
  status := v_next_status;
  return next;
end;
$$;

revoke execute on function public.set_report_snapshot_delivery_status(
  uuid, text, text
) from public, anon;
grant execute on function public.set_report_snapshot_delivery_status(
  uuid, text, text
) to authenticated;

revoke execute on function public.set_report_package_delivery_status(
  uuid, text, text
) from public, anon;
grant execute on function public.set_report_package_delivery_status(
  uuid, text, text
) to authenticated;
