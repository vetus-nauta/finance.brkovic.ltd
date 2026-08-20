-- Wrap auth.uid() calls in RLS policies with scalar subqueries so PostgreSQL
-- can initialize the value once per statement instead of once per row.

do $$
declare
  p record;
  new_qual text;
  new_with_check text;
begin
  for p in
    select tablename, policyname, cmd, qual, with_check
    from pg_policies
    where schemaname = 'public'
      and (
        (qual is not null and qual like '%auth.uid()%')
        or (with_check is not null and with_check like '%auth.uid()%')
      )
    order by tablename, policyname
  loop
    new_qual := replace(p.qual, 'auth.uid()', '(select auth.uid())');
    new_with_check := replace(p.with_check, 'auth.uid()', '(select auth.uid())');

    execute format('drop policy if exists %I on public.%I', p.policyname, p.tablename);

    if p.cmd = 'SELECT' then
      execute format(
        'create policy %I on public.%I as permissive for select to public using (%s)',
        p.policyname,
        p.tablename,
        new_qual
      );
    elsif p.cmd = 'INSERT' then
      execute format(
        'create policy %I on public.%I as permissive for insert to public with check (%s)',
        p.policyname,
        p.tablename,
        new_with_check
      );
    elsif p.cmd = 'UPDATE' then
      execute format(
        'create policy %I on public.%I as permissive for update to public using (%s) with check (%s)',
        p.policyname,
        p.tablename,
        new_qual,
        new_with_check
      );
    elsif p.cmd = 'DELETE' then
      execute format(
        'create policy %I on public.%I as permissive for delete to public using (%s)',
        p.policyname,
        p.tablename,
        new_qual
      );
    end if;
  end loop;
end;
$$;
