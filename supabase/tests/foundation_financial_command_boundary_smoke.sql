begin;

do $$
declare
  mutable_policy_count integer;
begin
  select count(*) into mutable_policy_count
  from pg_policies
  where schemaname = 'public'
    and tablename in ('transactions', 'ledger_entries')
    and cmd <> 'SELECT';

  if mutable_policy_count <> 0 then
    raise exception 'financial fact tables must not expose direct mutable policies, saw %', mutable_policy_count;
  end if;
end;
$$;

select 'foundation financial command boundary smoke ok' as result;

rollback;
