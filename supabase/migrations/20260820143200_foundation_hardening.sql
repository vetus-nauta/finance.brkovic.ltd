-- Foundation-02 hardening.
-- Keep this migration separate from the core schema so remote history stays auditable.

create or replace function public.set_updated_at()
returns trigger
language plpgsql
set search_path = ''
as $$
begin
  new.updated_at = now();
  return new;
end;
$$;

do $$
declare
  fk record;
  index_name text;
begin
  for fk in
    with fk_cols as (
      select
        c.conrelid,
        cls.relname as table_name,
        c.conkey,
        string_agg(a.attname, '_' order by k.ord) as columns_name,
        string_agg(format('%I', a.attname), ', ' order by k.ord) as columns_sql
      from pg_constraint c
      join pg_class cls on cls.oid = c.conrelid
      join pg_namespace n on n.oid = cls.relnamespace
      join lateral unnest(c.conkey) with ordinality as k(attnum, ord) on true
      join pg_attribute a on a.attrelid = c.conrelid and a.attnum = k.attnum
      where c.contype = 'f'
        and n.nspname = 'public'
      group by c.conrelid, cls.relname, c.conkey
    )
    select table_name, columns_name, columns_sql, conrelid, conkey
    from fk_cols f
    where not exists (
      select 1
      from pg_index i
      where i.indrelid = f.conrelid
        and i.indpred is null
        and (i.indkey::int2[])[0:cardinality(f.conkey)-1] = f.conkey
    )
    order by table_name, columns_name
  loop
    index_name := left(fk.table_name || '_' || fk.columns_name || '_fk_idx', 63);
    execute format('create index if not exists %I on public.%I (%s)', index_name, fk.table_name, fk.columns_sql);
  end loop;
end;
$$;

do $$
declare
  p record;
begin
  for p in
    select tablename, policyname, qual, with_check
    from pg_policies
    where schemaname = 'public'
      and cmd = 'ALL'
    order by tablename, policyname
  loop
    execute format('drop policy if exists %I on public.%I', p.policyname, p.tablename);

    execute format(
      'create policy %I on public.%I as permissive for insert to public with check (%s)',
      left(p.policyname || '_insert', 63),
      p.tablename,
      p.with_check
    );

    execute format(
      'create policy %I on public.%I as permissive for update to public using (%s) with check (%s)',
      left(p.policyname || '_update', 63),
      p.tablename,
      p.qual,
      p.with_check
    );

    execute format(
      'create policy %I on public.%I as permissive for delete to public using (%s)',
      left(p.policyname || '_delete', 63),
      p.tablename,
      p.qual
    );
  end loop;
end;
$$;
