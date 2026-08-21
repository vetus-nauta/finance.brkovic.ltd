-- SPRINT-125R: command-owned registry for generated report export versions.
-- The binary export is still generated from immutable report data at read time;
-- this command records the user-visible version in the document registry.

create or replace function public.create_report_export_version(
  p_workspace_id uuid,
  p_entity_type text,
  p_entity_id uuid,
  p_format text,
  p_title text default null
)
returns table (
  document_id uuid,
  document_version_id uuid,
  version_no integer,
  format text,
  download_path text
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_workspace record;
  v_report record;
  v_package record;
  v_user_id uuid := auth.uid();
  v_entity_type text := lower(btrim(coalesce(p_entity_type, '')));
  v_format text := lower(btrim(coalesce(p_format, '')));
  v_title text := nullif(btrim(coalesce(p_title, '')), '');
  v_bucket text := 'findesk-generated-report-exports';
  v_document_key text;
  v_version_key text;
  v_filename text;
  v_extension text;
  v_mime_type text;
  v_document_id uuid;
  v_document_version_id uuid;
  v_version_no integer;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if p_workspace_id is null then
    raise exception 'workspace_required';
  end if;

  if p_entity_id is null then
    raise exception 'report_entity_required';
  end if;

  if v_entity_type not in ('report_snapshot', 'report_package') then
    raise exception 'unsupported_report_entity:%', v_entity_type;
  end if;

  if v_format not in ('html', 'xls', 'pdf') then
    raise exception 'unsupported_report_export_format:%', v_format;
  end if;

  select w.id, w.organization_id
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

  if not private.has_workspace_permission(p_workspace_id, 'documents.write') then
    raise exception 'documents_write_required';
  end if;

  if v_entity_type = 'report_snapshot' then
    select rs.id, rs.title, rs.status
    into v_report
    from public.report_snapshots rs
    where rs.id = p_entity_id
      and rs.workspace_id = p_workspace_id
      and rs.status <> 'void'
    limit 1;

    if not found then
      raise exception 'report_snapshot_not_found';
    end if;

    v_title := coalesce(v_title, v_report.title);
    download_path := '/workspaces/' || p_workspace_id || '/reports/' || p_entity_id ||
      case when v_format = 'xls' then '/excel' else '' end;
  else
    select rp.id, rp.title, rp.status
    into v_package
    from public.report_packages rp
    where rp.id = p_entity_id
      and rp.workspace_id = p_workspace_id
      and rp.status <> 'void'
    limit 1;

    if not found then
      raise exception 'report_package_not_found';
    end if;

    v_title := coalesce(v_title, v_package.title);
    download_path := '/workspaces/' || p_workspace_id || '/report-packages/' || p_entity_id ||
      case when v_format = 'xls' then '/excel' else '' end;
  end if;

  if v_format = 'html' then
    v_extension := 'html';
    v_mime_type := 'text/html';
  elsif v_format = 'xls' then
    v_extension := 'xls';
    v_mime_type := 'application/vnd.ms-excel';
  else
    v_extension := 'pdf';
    v_mime_type := 'application/pdf';
  end if;

  v_filename := regexp_replace(v_title, '[\\/:*?"<>|]+', ' ', 'g');
  v_filename := regexp_replace(v_filename, '\s+', ' ', 'g');
  v_filename := btrim(left(v_filename, 80));

  if v_filename = '' then
    v_filename := 'findesk-report';
  end if;

  v_filename := v_filename || '.' || v_extension;
  v_document_key := 'generated/' || p_workspace_id || '/' || v_entity_type || '/' || p_entity_id || '/' || v_format || '/document';

  insert into public.documents (
    organization_id,
    workspace_id,
    bucket,
    object_key,
    original_filename,
    mime_type,
    status,
    uploaded_by
  ) values (
    v_workspace.organization_id,
    p_workspace_id,
    v_bucket,
    v_document_key,
    v_filename,
    v_mime_type,
    'active',
    v_user_id
  )
  on conflict (bucket, object_key) do update
  set original_filename = excluded.original_filename,
      mime_type = excluded.mime_type,
      status = 'active',
      uploaded_by = excluded.uploaded_by,
      updated_at = now()
  returning id into v_document_id;

  select coalesce(max(dv.version_no), 0) + 1
  into v_version_no
  from public.document_versions dv
  where dv.document_id = v_document_id;

  v_version_key := 'generated/' || p_workspace_id || '/' || v_entity_type || '/' || p_entity_id || '/' || v_format || '/v' || v_version_no || '.' || v_extension;

  insert into public.document_versions (
    organization_id,
    workspace_id,
    document_id,
    version_no,
    bucket,
    object_key,
    created_by
  ) values (
    v_workspace.organization_id,
    p_workspace_id,
    v_document_id,
    v_version_no,
    v_bucket,
    v_version_key,
    v_user_id
  )
  returning id into v_document_version_id;

  insert into public.document_links (
    organization_id,
    workspace_id,
    document_id,
    entity_type,
    entity_id
  )
  select
    v_workspace.organization_id,
    p_workspace_id,
    v_document_id,
    v_entity_type,
    p_entity_id
  where not exists (
    select 1
    from public.document_links dl
    where dl.document_id = v_document_id
      and dl.entity_type = v_entity_type
      and dl.entity_id = p_entity_id
  );

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
    v_entity_type,
    p_entity_id,
    'report_export_version_created',
    v_user_id,
    jsonb_build_object(
      'document_id', v_document_id,
      'document_version_id', v_document_version_id,
      'version_no', v_version_no,
      'format', v_format,
      'download_path', download_path,
      'object_key', v_version_key
    )
  );

  document_id := v_document_id;
  document_version_id := v_document_version_id;
  version_no := v_version_no;
  format := v_format;
  return next;
end;
$$;

revoke execute on function public.create_report_export_version(
  uuid, text, uuid, text, text
) from public, anon;
grant execute on function public.create_report_export_version(
  uuid, text, uuid, text, text
) to authenticated;
