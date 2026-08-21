begin;

do $$
declare
  mutable_policy_count integer;
  invoker_audit_writer_count integer;
begin
  select count(*) into mutable_policy_count
  from pg_policies
  where schemaname = 'public'
    and tablename = 'approval_events'
    and cmd <> 'SELECT';

  if mutable_policy_count <> 0 then
    raise exception 'approval_events must not expose direct mutable policies, saw %', mutable_policy_count;
  end if;

  if has_table_privilege('anon', 'public.approval_events', 'INSERT')
    or has_table_privilege('anon', 'public.approval_events', 'UPDATE')
    or has_table_privilege('anon', 'public.approval_events', 'DELETE')
    or has_table_privilege('authenticated', 'public.approval_events', 'INSERT')
    or has_table_privilege('authenticated', 'public.approval_events', 'UPDATE')
    or has_table_privilege('authenticated', 'public.approval_events', 'DELETE')
  then
    raise exception 'approval_events direct mutable privileges must be revoked from client roles';
  end if;

  select count(*) into invoker_audit_writer_count
  from pg_proc p
  join pg_namespace n on n.oid = p.pronamespace
  where n.nspname = 'public'
    and p.prokind = 'f'
    and p.prosecdef is not true
    and pg_get_functiondef(p.oid) like '%insert into public.approval_events%';

  if invoker_audit_writer_count <> 0 then
    raise exception 'approval_events writers must be SECURITY DEFINER, saw % invoker writer(s)', invoker_audit_writer_count;
  end if;
end;
$$;

select 'foundation approval audit boundary smoke ok' as result;

rollback;
