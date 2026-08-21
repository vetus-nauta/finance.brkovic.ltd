-- SPRINT-119R: command-owned report return for revision.
-- A returned report remains visible and auditable; source rows stay report-locked.

create or replace function public.return_report_snapshot_for_revision(
  p_report_snapshot_id uuid,
  p_reason text default null
)
returns table (
  report_snapshot_id uuid,
  period_closure_id uuid,
  status text
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_report record;
  v_user_id uuid := auth.uid();
  v_reason text := nullif(btrim(coalesce(p_reason, '')), '');
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_report_snapshot_id is null then
    raise exception 'report_snapshot_required';
  end if;

  select rs.id, rs.organization_id, rs.workspace_id, rs.period_closure_id, rs.status
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

  if v_report.status = 'returned_for_revision' then
    report_snapshot_id := v_report.id;
    period_closure_id := v_report.period_closure_id;
    status := v_report.status;
    return next;
    return;
  end if;

  update public.report_snapshots
  set status = 'returned_for_revision',
      totals = totals || jsonb_build_object(
        'returned_for_revision_at', now(),
        'returned_for_revision_by', v_user_id,
        'return_reason', v_reason
      ),
      updated_at = now()
  where id = v_report.id;

  if v_report.period_closure_id is not null then
    update public.period_closures
    set status = 'returned_for_revision',
        metadata = metadata || jsonb_build_object(
          'returned_for_revision_at', now(),
          'returned_for_revision_by', v_user_id,
          'return_reason', v_reason
        ),
        updated_at = now()
    where id = v_report.period_closure_id;
  end if;

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
    'report_snapshot',
    v_report.id,
    'report_snapshot_returned_for_revision',
    v_user_id,
    jsonb_build_object(
      'period_closure_id', v_report.period_closure_id,
      'previous_status', v_report.status,
      'reason', v_reason
    )
  );

  report_snapshot_id := v_report.id;
  period_closure_id := v_report.period_closure_id;
  status := 'returned_for_revision';
  return next;
end;
$$;

revoke execute on function public.return_report_snapshot_for_revision(
  uuid, text
) from public, anon;
grant execute on function public.return_report_snapshot_for_revision(
  uuid, text
) to authenticated;
