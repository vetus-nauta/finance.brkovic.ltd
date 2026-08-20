-- SPRINT-101R security follow-up.
-- Keep the operational entry RPC callable by authenticated users without using
-- SECURITY DEFINER. RLS stays active for transaction, ledger, and audit writes.

drop policy if exists approval_events_operational_entry_write on public.approval_events;
create policy approval_events_operational_entry_write on public.approval_events
  for insert to authenticated
  with check (
    entity_type = 'transaction'
    and event_type in ('operational_entry_created', 'operational_entry_needs_review')
    and private.has_workspace_permission(workspace_id, 'ledger.write')
  );

alter function public.create_operational_entry(
  uuid, text, date, text, text, text, text, uuid, jsonb, jsonb
) security invoker;
