-- RLS policy scope hardening.
-- App data policies must run for authenticated users, not for the implicit public role.
-- Helper functions stay in the private schema and are not exposed through the Data API.

grant usage on schema private to authenticated;
grant execute on function private.is_workspace_member(uuid) to authenticated;
grant execute on function private.has_workspace_permission(uuid, text) to authenticated;
grant execute on function private.is_own_report(uuid, uuid) to authenticated;

do $$
declare
  p record;
begin
  for p in
    select tablename, policyname, cmd, qual, with_check
    from pg_policies
    where schemaname = 'public'
      and roles = array['public']::name[]
    order by tablename, policyname
  loop
    execute format('drop policy if exists %I on public.%I', p.policyname, p.tablename);

    if p.cmd = 'SELECT' then
      execute format(
        'create policy %I on public.%I as permissive for select to authenticated using (%s)',
        p.policyname,
        p.tablename,
        p.qual
      );
    elsif p.cmd = 'INSERT' then
      execute format(
        'create policy %I on public.%I as permissive for insert to authenticated with check (%s)',
        p.policyname,
        p.tablename,
        p.with_check
      );
    elsif p.cmd = 'UPDATE' then
      execute format(
        'create policy %I on public.%I as permissive for update to authenticated using (%s) with check (%s)',
        p.policyname,
        p.tablename,
        p.qual,
        p.with_check
      );
    elsif p.cmd = 'DELETE' then
      execute format(
        'create policy %I on public.%I as permissive for delete to authenticated using (%s)',
        p.policyname,
        p.tablename,
        p.qual
      );
    end if;
  end loop;
end;
$$;
