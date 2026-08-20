-- SPRINT-102R: Smith intake command for quick notes.
-- A quick note stays human-readable; Smith converts each non-empty line into
-- an operational transaction through the same atomic entry command.

drop policy if exists approval_events_quick_note_conversion_write on public.approval_events;
create policy approval_events_quick_note_conversion_write on public.approval_events
  for insert to authenticated
  with check (
    entity_type = 'quick_note'
    and event_type = 'quick_note_converted'
    and private.has_workspace_permission(workspace_id, 'ledger.write')
  );

create or replace function public.convert_quick_note_to_operational_entries(
  p_note_id uuid,
  p_account_code text,
  p_occurred_on date,
  p_source_language text default 'ru'
)
returns table (
  quick_note_id uuid,
  transaction_ids uuid[],
  converted_count integer,
  review_count integer
)
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_note record;
  v_line text;
  v_line_no integer := 0;
  v_created record;
  v_transaction_ids uuid[] := '{}';
  v_converted_count integer := 0;
  v_review_count integer := 0;
begin
  if auth.uid() is null then
    raise exception 'auth_required';
  end if;

  if p_occurred_on is null then
    raise exception 'occurred_on_required';
  end if;

  select q.id, q.organization_id, q.workspace_id, q.author_user_id, q.body, q.status
  into v_note
  from public.quick_notes q
  where q.id = p_note_id
    and q.status in ('draft', 'submitted_to_smith')
    and (
      q.author_user_id = auth.uid()
      or private.has_workspace_permission(q.workspace_id, 'ledger.write')
    )
  for update;

  if not found then
    raise exception 'quick_note_not_found';
  end if;

  if not private.has_workspace_permission(v_note.workspace_id, 'ledger.write') then
    raise exception 'ledger_write_required';
  end if;

  for v_line in
    select btrim(line)
    from regexp_split_to_table(v_note.body, E'\\r?\\n') as line
    where btrim(line) <> ''
  loop
    v_line_no := v_line_no + 1;

    select *
    into v_created
    from public.create_operational_entry(
      v_note.workspace_id,
      p_account_code,
      p_occurred_on,
      v_line,
      'quick_note',
      'quick_note',
      p_source_language,
      v_note.id,
      jsonb_build_object(
        'quick_note_id', v_note.id,
        'line_no', v_line_no
      ),
      jsonb_build_object(
        'quick_note_line_no', v_line_no,
        'quick_note_body_hash', md5(v_note.body)
      )
    );

    v_transaction_ids := array_append(v_transaction_ids, v_created.transaction_id);
    v_converted_count := v_converted_count + 1;

    if not v_created.counted then
      v_review_count := v_review_count + 1;
    end if;
  end loop;

  if v_converted_count = 0 then
    raise exception 'quick_note_has_no_lines';
  end if;

  update public.quick_notes
  set status = 'converted',
      converted_transaction_ids = v_transaction_ids
  where id = v_note.id;

  insert into public.approval_events (
    organization_id,
    workspace_id,
    entity_type,
    entity_id,
    event_type,
    actor_user_id,
    metadata
  ) values (
    v_note.organization_id,
    v_note.workspace_id,
    'quick_note',
    v_note.id,
    'quick_note_converted',
    auth.uid(),
    jsonb_build_object(
      'transaction_ids', v_transaction_ids,
      'converted_count', v_converted_count,
      'review_count', v_review_count,
      'account_code', p_account_code,
      'source_language', p_source_language
    )
  );

  return query select
    v_note.id,
    v_transaction_ids,
    v_converted_count,
    v_review_count;
end;
$$;

revoke execute on function public.convert_quick_note_to_operational_entries(
  uuid, text, date, text
) from public, anon;
grant execute on function public.convert_quick_note_to_operational_entries(
  uuid, text, date, text
) to authenticated;
