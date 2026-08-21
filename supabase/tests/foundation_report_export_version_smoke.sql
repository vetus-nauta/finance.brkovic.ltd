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
) values (
  'b1111111-1111-4111-8111-111111111111',
  'authenticated',
  'authenticated',
  'foundation-export-owner@example.invalid',
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
values (
  'b1111111-1111-4111-8111-111111111111',
  'foundation-export-owner@example.invalid',
  'Export Owner'
)
on conflict (id) do update
set email = excluded.email,
    display_name = excluded.display_name,
    updated_at = now();

insert into public.organizations (id, owner_user_id, name, slug, metadata)
values (
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'b1111111-1111-4111-8111-111111111111',
  'Foundation Export Organization',
  'foundation-export-organization',
  '{"foundation_export_smoke":true}'::jsonb
)
on conflict (id) do update
set name = excluded.name,
    metadata = excluded.metadata,
    updated_at = now();

insert into public.workspaces (id, organization_id, name, workspace_type, metadata)
values (
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'Foundation Export Yacht',
  'yacht',
  '{"foundation_export_smoke":true}'::jsonb
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
) values (
  'bc111111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'b1111111-1111-4111-8111-111111111111',
  'owner',
  'active',
  'workspace',
  now(),
  now()
)
on conflict (id) do update
set role_code = excluded.role_code,
    status = excluded.status,
    access_scope = excluded.access_scope,
    accepted_at = excluded.accepted_at,
    updated_at = now();

insert into public.report_snapshots (
  id,
  organization_id,
  workspace_id,
  title,
  period_start,
  period_end,
  status,
  source_transaction_ids,
  totals,
  created_by
) values (
  'bd111111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'Export report',
  '2026-08-01',
  '2026-08-10',
  'created',
  '{}',
  '{"entry_count":2,"income_total":1000,"expense_total":350,"net_total":650}'::jsonb,
  'b1111111-1111-4111-8111-111111111111'
)
on conflict (id) do update
set title = excluded.title,
    status = excluded.status,
    totals = excluded.totals,
    updated_at = now();

insert into public.report_packages (
  id,
  organization_id,
  workspace_id,
  title,
  status,
  created_by
) values (
  'be111111-1111-4111-8111-111111111111',
  'baaaaaaa-aaaa-4aaa-8aaa-111111111111',
  'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
  'Export package',
  'created',
  'b1111111-1111-4111-8111-111111111111'
)
on conflict (id) do update
set title = excluded.title,
    status = excluded.status,
    updated_at = now();

set local role authenticated;
set local request.jwt.claim.sub = 'b1111111-1111-4111-8111-111111111111';
set local request.jwt.claim.role = 'authenticated';
set local request.jwt.claims = '{"sub":"b1111111-1111-4111-8111-111111111111","role":"authenticated"}';

do $$
declare
  report_html_v1 record;
  report_html_v2 record;
  report_xls record;
  package_pdf record;
  document_count integer;
  link_count integer;
  audit_count integer;
begin
  select *
  into report_html_v1
  from public.create_report_export_version(
    'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
    'report_snapshot',
    'bd111111-1111-4111-8111-111111111111',
    'html',
    null
  );

  select *
  into report_html_v2
  from public.create_report_export_version(
    'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
    'report_snapshot',
    'bd111111-1111-4111-8111-111111111111',
    'html',
    null
  );

  if report_html_v1.document_id <> report_html_v2.document_id
    or report_html_v1.version_no <> 1
    or report_html_v2.version_no <> 2
  then
    raise exception 'html versions did not increment on one document';
  end if;

  select *
  into report_xls
  from public.create_report_export_version(
    'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
    'report_snapshot',
    'bd111111-1111-4111-8111-111111111111',
    'xls',
    null
  );

  if report_xls.document_id = report_html_v1.document_id
    or report_xls.format <> 'xls'
    or report_xls.download_path not like '%/excel'
  then
    raise exception 'xls version registry mismatch';
  end if;

  select *
  into package_pdf
  from public.create_report_export_version(
    'bbbbbbbb-bbbb-4bbb-8bbb-111111111111',
    'report_package',
    'be111111-1111-4111-8111-111111111111',
    'pdf',
    null
  );

  if package_pdf.format <> 'pdf'
    or package_pdf.download_path not like '%/report-packages/%'
  then
    raise exception 'package pdf version registry mismatch';
  end if;

  select count(*)
  into document_count
  from public.documents
  where workspace_id = 'bbbbbbbb-bbbb-4bbb-8bbb-111111111111'
    and bucket = 'findesk-generated-report-exports';

  if document_count <> 3 then
    raise exception 'expected 3 export documents, saw %', document_count;
  end if;

  select count(*)
  into link_count
  from public.document_links
  where workspace_id = 'bbbbbbbb-bbbb-4bbb-8bbb-111111111111'
    and entity_type in ('report_snapshot', 'report_package')
    and entity_id in (
      'bd111111-1111-4111-8111-111111111111',
      'be111111-1111-4111-8111-111111111111'
    );

  if link_count <> 3 then
    raise exception 'expected 3 document links, saw %', link_count;
  end if;

  select count(*)
  into audit_count
  from public.approval_events
  where workspace_id = 'bbbbbbbb-bbbb-4bbb-8bbb-111111111111'
    and event_type = 'report_export_version_created';

  if audit_count <> 4 then
    raise exception 'expected 4 export audit events, saw %', audit_count;
  end if;
end;
$$;

reset role;

select 'foundation report export version smoke ok' as result;

rollback;
