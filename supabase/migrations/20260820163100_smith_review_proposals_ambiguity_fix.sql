-- SPRINT-103R follow-up: qualify quick_note_id references inside policies and
-- PL/pgSQL functions. PostgreSQL treats OUT parameters as function variables,
-- so unqualified column names can become ambiguous at runtime.

create table if not exists public.smith_entry_proposals (
  id uuid primary key default gen_random_uuid(),
  organization_id uuid not null references public.organizations(id) on delete cascade,
  workspace_id uuid not null references public.workspaces(id) on delete cascade,
  quick_note_id uuid references public.quick_notes(id) on delete cascade,
  account_code text not null,
  occurred_on date not null,
  source_channel text not null default 'quick_note',
  source_language text not null default 'ru',
  line_no integer not null,
  raw_text text not null,
  candidate_amount numeric(14,2),
  candidate_direction text check (candidate_direction in ('income', 'expense', 'neutral')),
  parser_reason text,
  duplicate_status text not null default 'clear' check (duplicate_status in ('clear', 'possible_duplicate')),
  duplicate_reason text,
  status text not null default 'pending' check (status in ('pending', 'accepted', 'rejected', 'converted', 'void')),
  transaction_id uuid references public.transactions(id) on delete set null,
  metadata jsonb not null default '{}'::jsonb,
  created_by uuid references auth.users(id),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists smith_entry_proposals_workspace_status_idx
  on public.smith_entry_proposals (workspace_id, status);
create index if not exists smith_entry_proposals_quick_note_idx
  on public.smith_entry_proposals (quick_note_id, line_no);
create index if not exists smith_entry_proposals_transaction_id_idx
  on public.smith_entry_proposals (transaction_id);

alter table public.smith_entry_proposals enable row level security;

drop policy if exists smith_entry_proposals_read on public.smith_entry_proposals;
create policy smith_entry_proposals_read on public.smith_entry_proposals
  for select to authenticated
  using (
    private.has_workspace_permission(workspace_id, 'ledger.read')
    or exists (
      select 1 from public.quick_notes q
      where q.id = smith_entry_proposals.quick_note_id
        and q.author_user_id = auth.uid()
    )
  );

drop policy if exists smith_entry_proposals_write on public.smith_entry_proposals;
create policy smith_entry_proposals_write on public.smith_entry_proposals
  for all to authenticated
  using (
    private.has_workspace_permission(workspace_id, 'ledger.write')
    or exists (
      select 1 from public.quick_notes q
      where q.id = smith_entry_proposals.quick_note_id
        and q.author_user_id = auth.uid()
    )
  )
  with check (
    private.has_workspace_permission(workspace_id, 'ledger.write')
    or exists (
      select 1 from public.quick_notes q
      where q.id = smith_entry_proposals.quick_note_id
        and q.author_user_id = auth.uid()
    )
  );

drop trigger if exists smith_entry_proposals_updated_at on public.smith_entry_proposals;
create trigger smith_entry_proposals_updated_at before update on public.smith_entry_proposals
  for each row execute function public.set_updated_at();

create or replace function public.prepare_quick_note_entry_proposals(
  p_note_id uuid,
  p_account_code text,
  p_occurred_on date,
  p_source_language text default 'ru'
)
returns table (
  quick_note_id uuid,
  proposal_count integer,
  review_count integer,
  duplicate_count integer
)
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_note record;
  v_account record;
  v_line text;
  v_line_no integer;
  v_signed_match text[];
  v_unsigned_match text[];
  v_candidate_amount numeric(14,2);
  v_candidate_direction text;
  v_parser_reason text;
  v_duplicate_status text;
  v_duplicate_reason text;
  v_proposal_count integer := 0;
  v_review_count integer := 0;
  v_duplicate_count integer := 0;
begin
  if auth.uid() is null then
    raise exception 'auth_required';
  end if;

  if p_occurred_on is null then
    raise exception 'occurred_on_required';
  end if;

  if not exists (
    select 1 from public.language_packs l
    where l.code = lower(btrim(coalesce(p_source_language, 'ru')))
      and l.is_active = true
  ) then
    raise exception 'unsupported_language:%', p_source_language;
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

  select a.id, a.organization_id, a.workspace_id, a.code, a.account_type
  into v_account
  from public.accounts a
  where a.workspace_id = v_note.workspace_id
    and a.code = btrim(coalesce(p_account_code, 'cash'))
    and a.is_active = true
  limit 1;

  if not found then
    raise exception 'account_not_found';
  end if;

  delete from public.smith_entry_proposals
  where smith_entry_proposals.quick_note_id = v_note.id
    and status in ('pending', 'rejected', 'void');

  for v_line, v_line_no in
    select btrim(parts.line), parts.ord::integer
    from regexp_split_to_table(v_note.body, E'\\r?\\n') with ordinality as parts(line, ord)
    where btrim(parts.line) <> ''
  loop
    v_signed_match := null;
    v_unsigned_match := null;
    v_candidate_amount := null;
    v_candidate_direction := null;
    v_parser_reason := null;
    v_duplicate_status := 'clear';
    v_duplicate_reason := null;

    select regexp_match(v_line, '^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)') into v_signed_match;

    if v_signed_match is not null then
      v_candidate_amount := replace(v_signed_match[2], ',', '.')::numeric(14,2);
      v_candidate_direction := case when v_signed_match[1] = '+' then 'income' else 'expense' end;
    else
      select regexp_match(v_line, '^([0-9]+(?:[.,][0-9]{1,2})?)') into v_unsigned_match;

      if v_unsigned_match is not null then
        v_candidate_amount := replace(v_unsigned_match[1], ',', '.')::numeric(14,2);
        v_parser_reason := 'missing_sign';
      else
        v_parser_reason := 'amount_missing';
      end if;
    end if;

    if v_parser_reason is not null then
      v_review_count := v_review_count + 1;
    end if;

    if v_candidate_amount is not null and exists (
      select 1
      from public.transactions t
      join public.ledger_entries le on le.transaction_id = t.id
      where t.workspace_id = v_note.workspace_id
        and t.account_id = v_account.id
        and t.occurred_on = p_occurred_on
        and t.status <> 'void'
        and le.amount = v_candidate_amount
        and (v_candidate_direction is null or le.direction = v_candidate_direction)
      limit 1
    ) then
      v_duplicate_status := 'possible_duplicate';
      v_duplicate_reason := 'same_date_account_amount';
      v_duplicate_count := v_duplicate_count + 1;
    end if;

    insert into public.smith_entry_proposals (
      organization_id,
      workspace_id,
      quick_note_id,
      account_code,
      occurred_on,
      source_channel,
      source_language,
      line_no,
      raw_text,
      candidate_amount,
      candidate_direction,
      parser_reason,
      duplicate_status,
      duplicate_reason,
      status,
      metadata,
      created_by
    ) values (
      v_note.organization_id,
      v_note.workspace_id,
      v_note.id,
      v_account.code,
      p_occurred_on,
      'quick_note',
      lower(btrim(coalesce(p_source_language, 'ru'))),
      v_line_no,
      v_line,
      v_candidate_amount,
      v_candidate_direction,
      v_parser_reason,
      v_duplicate_status,
      v_duplicate_reason,
      'pending',
      jsonb_build_object(
        'quick_note_body_hash', md5(v_note.body)
      ),
      auth.uid()
    );

    v_proposal_count := v_proposal_count + 1;
  end loop;

  if v_proposal_count = 0 then
    raise exception 'quick_note_has_no_lines';
  end if;

  update public.quick_notes
  set status = 'submitted_to_smith'
  where id = v_note.id;

  return query select
    v_note.id,
    v_proposal_count,
    v_review_count,
    v_duplicate_count;
end;
$$;

create or replace function public.convert_smith_entry_proposals(
  p_note_id uuid,
  p_proposal_ids uuid[]
)
returns table (
  quick_note_id uuid,
  transaction_ids uuid[],
  converted_count integer,
  review_count integer,
  rejected_count integer
)
language plpgsql
security invoker
set search_path = ''
as $$
declare
  v_note record;
  v_proposal record;
  v_created record;
  v_transaction_ids uuid[] := '{}';
  v_converted_count integer := 0;
  v_review_count integer := 0;
  v_rejected_count integer := 0;
  v_selected_ids uuid[] := coalesce(p_proposal_ids, '{}');
begin
  if auth.uid() is null then
    raise exception 'auth_required';
  end if;

  if cardinality(v_selected_ids) = 0 then
    raise exception 'no_proposals_selected';
  end if;

  select q.id, q.organization_id, q.workspace_id, q.author_user_id, q.status
  into v_note
  from public.quick_notes q
  where q.id = p_note_id
    and q.status = 'submitted_to_smith'
    and (
      q.author_user_id = auth.uid()
      or private.has_workspace_permission(q.workspace_id, 'ledger.write')
    )
  for update;

  if not found then
    raise exception 'quick_note_not_ready';
  end if;

  if not private.has_workspace_permission(v_note.workspace_id, 'ledger.write') then
    raise exception 'ledger_write_required';
  end if;

  for v_proposal in
    select *
    from public.smith_entry_proposals p
    where p.quick_note_id = v_note.id
      and p.status = 'pending'
      and p.id = any(v_selected_ids)
    order by p.line_no
  loop
    select *
    into v_created
    from public.create_operational_entry(
      v_note.workspace_id,
      v_proposal.account_code,
      v_proposal.occurred_on,
      v_proposal.raw_text,
      'quick_note',
      v_proposal.source_channel,
      v_proposal.source_language,
      v_note.id,
      jsonb_build_object(
        'quick_note_id', v_note.id,
        'proposal_id', v_proposal.id,
        'line_no', v_proposal.line_no
      ),
      jsonb_build_object(
        'smith_proposal_id', v_proposal.id,
        'duplicate_status', v_proposal.duplicate_status,
        'duplicate_reason', v_proposal.duplicate_reason
      )
    );

    update public.smith_entry_proposals
    set status = 'converted',
        transaction_id = v_created.transaction_id
    where id = v_proposal.id;

    v_transaction_ids := array_append(v_transaction_ids, v_created.transaction_id);
    v_converted_count := v_converted_count + 1;

    if not v_created.counted then
      v_review_count := v_review_count + 1;
    end if;
  end loop;

  if v_converted_count = 0 then
    raise exception 'no_pending_selected_proposals';
  end if;

  update public.smith_entry_proposals
  set status = 'rejected'
  where smith_entry_proposals.quick_note_id = v_note.id
    and status = 'pending'
    and not (id = any(v_selected_ids));

  get diagnostics v_rejected_count = row_count;

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
      'rejected_count', v_rejected_count,
      'proposal_ids', v_selected_ids
    )
  );

  return query select
    v_note.id,
    v_transaction_ids,
    v_converted_count,
    v_review_count,
    v_rejected_count;
end;
$$;

revoke execute on function public.prepare_quick_note_entry_proposals(
  uuid, text, date, text
) from public, anon;
grant execute on function public.prepare_quick_note_entry_proposals(
  uuid, text, date, text
) to authenticated;

revoke execute on function public.convert_smith_entry_proposals(
  uuid, uuid[]
) from public, anon;
grant execute on function public.convert_smith_entry_proposals(
  uuid, uuid[]
) to authenticated;
