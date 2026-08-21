-- SPRINT-112R: approval/audit command boundary hardening.
-- Approval events are audit evidence. Clients may read them through RLS, but
-- must not create, edit, or delete audit rows directly.

drop policy if exists approval_events_write on public.approval_events;
drop policy if exists approval_events_write_insert on public.approval_events;
drop policy if exists approval_events_write_update on public.approval_events;
drop policy if exists approval_events_write_delete on public.approval_events;
drop policy if exists approval_events_operational_entry_write on public.approval_events;
drop policy if exists approval_events_quick_note_conversion_write on public.approval_events;

revoke insert, update, delete on public.approval_events from public, anon, authenticated;

-- These commands already perform explicit auth and workspace permission checks.
-- They are made SECURITY DEFINER so their audited writes keep working after
-- direct approval_events DML is closed for ordinary authenticated clients.
alter function public.convert_quick_note_to_operational_entries(
  uuid, text, date, text
) security definer;

alter function public.convert_smith_entry_proposals(
  uuid, uuid[]
) security definer;

revoke execute on function public.convert_quick_note_to_operational_entries(
  uuid, text, date, text
) from public, anon;
grant execute on function public.convert_quick_note_to_operational_entries(
  uuid, text, date, text
) to authenticated;

revoke execute on function public.convert_smith_entry_proposals(
  uuid, uuid[]
) from public, anon;
grant execute on function public.convert_smith_entry_proposals(
  uuid, uuid[]
) to authenticated;
