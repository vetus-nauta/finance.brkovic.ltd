-- SPRINT-117R: command-owned report packages.
-- A package combines existing immutable report snapshots for sending/review.

create or replace function public.create_report_package(
  p_workspace_id uuid,
  p_report_snapshot_ids uuid[],
  p_title text default null
)
returns table (
  report_package_id uuid,
  included_count integer
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_workspace record;
  v_user_id uuid := auth.uid();
  v_title text;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_workspace_id is null then
    raise exception 'workspace_required';
  end if;

  if p_report_snapshot_ids is null or cardinality(p_report_snapshot_ids) = 0 then
    raise exception 'report_snapshots_required';
  end if;

  select w.id, w.organization_id, w.name
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

  select count(*)::integer
  into included_count
  from (
    select distinct source.report_snapshot_id
    from unnest(p_report_snapshot_ids) with ordinality as source(report_snapshot_id, position)
  ) source
  join public.report_snapshots rs on rs.id = source.report_snapshot_id
  where rs.workspace_id = p_workspace_id
    and rs.status <> 'void';

  if included_count <> (
    select count(*)::integer
    from (
      select distinct source.report_snapshot_id
      from unnest(p_report_snapshot_ids) with ordinality as source(report_snapshot_id, position)
    ) source
  ) then
    raise exception 'report_snapshot_not_found';
  end if;

  v_title := nullif(btrim(coalesce(p_title, '')), '');

  if v_title is null then
    v_title := 'Пакет отчетов от ' || to_char(current_date, 'DD.MM.YYYY') || ' · ' || included_count || ' отчетов';
  end if;

  insert into public.report_packages (
    organization_id,
    workspace_id,
    title,
    status,
    created_by
  ) values (
    v_workspace.organization_id,
    p_workspace_id,
    v_title,
    'created',
    v_user_id
  )
  returning id into report_package_id;

  insert into public.report_package_items (
    organization_id,
    workspace_id,
    report_package_id,
    report_snapshot_id,
    position
  )
  select
    v_workspace.organization_id,
    p_workspace_id,
    report_package_id,
    source.report_snapshot_id,
    min(source.position)::integer
  from unnest(p_report_snapshot_ids) with ordinality as source(report_snapshot_id, position)
  join public.report_snapshots rs on rs.id = source.report_snapshot_id
  where rs.workspace_id = p_workspace_id
    and rs.status <> 'void'
  group by source.report_snapshot_id
  order by min(source.position);

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
    'report_package',
    report_package_id,
    'report_package_created',
    v_user_id,
    jsonb_build_object(
      'report_snapshot_ids', p_report_snapshot_ids,
      'included_count', included_count
    )
  );

  return next;
end;
$$;

revoke execute on function public.create_report_package(
  uuid, uuid[], text
) from public, anon;
grant execute on function public.create_report_package(
  uuid, uuid[], text
) to authenticated;
