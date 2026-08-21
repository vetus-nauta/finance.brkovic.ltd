-- SPRINT-111R: financial command boundary hardening.
-- Operational transactions and ledger rows must be written through audited
-- database commands, not directly through client table mutations.

-- Remove broad direct client write access left from the early foundation stage.
drop policy if exists transactions_write on public.transactions;
drop policy if exists transactions_write_insert on public.transactions;
drop policy if exists transactions_write_update on public.transactions;
drop policy if exists transactions_write_delete on public.transactions;
drop policy if exists transactions_command_insert on public.transactions;
drop policy if exists transactions_command_update on public.transactions;
drop policy if exists transactions_command_delete on public.transactions;

drop policy if exists ledger_entries_write on public.ledger_entries;
drop policy if exists ledger_entries_write_insert on public.ledger_entries;
drop policy if exists ledger_entries_write_update on public.ledger_entries;
drop policy if exists ledger_entries_write_delete on public.ledger_entries;
drop policy if exists ledger_entries_command_insert on public.ledger_entries;
drop policy if exists ledger_entries_command_update on public.ledger_entries;
drop policy if exists ledger_entries_command_delete on public.ledger_entries;

-- The command functions already perform explicit auth and permission checks:
-- auth_required, ledger_write_required, active workspace/account validation,
-- parser/card-income guards, row numbering, and audit insertion. They are made
-- SECURITY DEFINER so those checked commands can write after direct table DML is
-- closed for ordinary authenticated clients.
alter function public.create_operational_entry(
  uuid, text, date, text, text, text, text, uuid, jsonb, jsonb
) security definer;

alter function public.update_operational_entry(
  uuid, text, date, text, text, text, jsonb
) security definer;

alter function public.void_operational_entry(uuid) security definer;

-- Keep the public surface as RPC-only for authenticated users. Direct table DML
-- remains blocked by the absence of insert/update/delete policies on the two
-- financial fact tables.
revoke execute on function public.create_operational_entry(
  uuid, text, date, text, text, text, text, uuid, jsonb, jsonb
) from public, anon;
grant execute on function public.create_operational_entry(
  uuid, text, date, text, text, text, text, uuid, jsonb, jsonb
) to authenticated;

revoke execute on function public.update_operational_entry(
  uuid, text, date, text, text, text, jsonb
) from public, anon;
grant execute on function public.update_operational_entry(
  uuid, text, date, text, text, text, jsonb
) to authenticated;

revoke execute on function public.void_operational_entry(uuid) from public, anon;
grant execute on function public.void_operational_entry(uuid) to authenticated;
