-- SPRINT-105R: materialize Smith-approved categories into ledger entries.

begin;

create or replace function private.ensure_workspace_default_categories(target_workspace_id uuid)
returns void
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_workspace record;
begin
  select w.id, w.organization_id
  into v_workspace
  from public.workspaces w
  where w.id = target_workspace_id
    and w.status = 'active';

  if not found then
    raise exception 'workspace_not_found';
  end if;

  insert into public.categories (
    organization_id,
    workspace_id,
    code,
    direction,
    label,
    is_system,
    metadata
  )
  select
    v_workspace.organization_id,
    v_workspace.id,
    seed.code,
    seed.direction,
    seed.label,
    true,
    seed.metadata
  from (
    values
      ('crew', 'expense', '{"ru":"Экипаж","en":"Crew"}'::jsonb, '{}'::jsonb),
      ('commercial_income', 'income', '{"ru":"Коммерческий приход","en":"Commercial income"}'::jsonb, '{}'::jsonb),
      ('non_commercial_income', 'income', '{"ru":"Некоммерческие поступления","en":"Non-commercial income"}'::jsonb, '{}'::jsonb),
      ('dry_dock', 'expense', '{"ru":"Сухой док","en":"Dry dock"}'::jsonb, '{}'::jsonb),
      ('berth', 'expense', '{"ru":"Стоянка","en":"Berth"}'::jsonb, '{}'::jsonb),
      ('marina_ports', 'expense', '{"ru":"Марины и портовые","en":"Marinas and port fees"}'::jsonb, '{}'::jsonb),
      ('service_water', 'expense', '{"ru":"Сервисные работы","en":"Service works"}'::jsonb, '{}'::jsonb),
      ('tech_parts', 'expense', '{"ru":"Техчасть и запчасти","en":"Technical parts"}'::jsonb, '{}'::jsonb),
      ('tender', 'expense', '{"ru":"Тендер / тузик","en":"Tender"}'::jsonb, '{}'::jsonb),
      ('fuel', 'expense', '{"ru":"Топливо","en":"Fuel"}'::jsonb, '{}'::jsonb),
      ('provisions', 'expense', '{"ru":"Продукты и гости","en":"Provisions and guests"}'::jsonb, '{}'::jsonb),
      ('guest_trip_support', 'expense', '{"ru":"Обеспечение гостей в походе","en":"Guest trip support"}'::jsonb, '{}'::jsonb),
      ('guest_cash_issued', 'neutral', '{"ru":"Выданные наличные гостям","en":"Cash issued to guests"}'::jsonb, '{"category_kind":"accounting_block"}'::jsonb),
      ('representation_expenses', 'expense', '{"ru":"Представительские расходы","en":"Representation expenses"}'::jsonb, '{}'::jsonb),
      ('interior', 'expense', '{"ru":"Интерьер и быт","en":"Interior and household"}'::jsonb, '{}'::jsonb),
      ('cleaning', 'expense', '{"ru":"Клининг и химия","en":"Cleaning and chemicals"}'::jsonb, '{}'::jsonb),
      ('media_comms', 'expense', '{"ru":"Мультимедиа и связь","en":"Media and communications"}'::jsonb, '{}'::jsonb),
      ('transport_expenses', 'expense', '{"ru":"Транспортные расходы","en":"Transport expenses"}'::jsonb, '{}'::jsonb),
      ('admin_legal', 'expense', '{"ru":"Админка / документы","en":"Admin and legal"}'::jsonb, '{}'::jsonb),
      ('current_boat_expenses', 'expense', '{"ru":"Текущие лодочные расходы","en":"Current boat expenses"}'::jsonb, '{}'::jsonb),
      ('cash_topup_from_card', 'neutral', '{"ru":"Пополнение кеша с карты","en":"Cash top-up from card"}'::jsonb, '{"category_kind":"money_movement"}'::jsonb),
      ('admin_debt', 'neutral', '{"ru":"Долг администратора","en":"Administrator debt"}'::jsonb, '{"category_kind":"accounting_block"}'::jsonb),
      ('lower_accounting', 'neutral', '{"ru":"Подотчет / долг","en":"Accountable / debt"}'::jsonb, '{"category_kind":"accounting_block"}'::jsonb),
      ('other', 'expense', '{"ru":"Другие расходы","en":"Other expenses"}'::jsonb, '{}'::jsonb)
  ) as seed(code, direction, label, metadata)
  on conflict (organization_id, workspace_id, code) do update
  set direction = excluded.direction,
      label = excluded.label,
      is_system = true,
      is_active = true,
      metadata = public.categories.metadata || excluded.metadata,
      updated_at = now();
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
  v_category_id uuid;
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

  perform private.ensure_workspace_default_categories(v_note.workspace_id);

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
        'duplicate_reason', v_proposal.duplicate_reason,
        'candidate_category_code', v_proposal.candidate_category_code,
        'confidence', v_proposal.confidence,
        'review_reason', v_proposal.review_reason,
        'matched_signals', v_proposal.matched_signals,
        'blockers', v_proposal.blockers,
        'semantic_markers', v_proposal.semantic_markers
      )
    );

    v_category_id := null;

    if v_created.ledger_entry_id is not null and v_proposal.candidate_category_code is not null then
      select c.id
      into v_category_id
      from public.categories c
      where c.workspace_id = v_note.workspace_id
        and c.code = v_proposal.candidate_category_code
        and c.is_active = true
        and (
          c.direction = 'neutral'
          or c.direction = v_proposal.candidate_direction
        )
      limit 1;

      update public.ledger_entries
      set category_id = v_category_id,
          metadata = metadata || jsonb_build_object(
            'category_code', v_proposal.candidate_category_code,
            'category_materialized', v_category_id is not null,
            'category_source', 'smith_review'
          )
      where id = v_created.ledger_entry_id;
    end if;

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

revoke execute on function public.convert_smith_entry_proposals(
  uuid, uuid[]
) from public, anon;
grant execute on function public.convert_smith_entry_proposals(
  uuid, uuid[]
) to authenticated;

select private.ensure_workspace_default_categories(w.id)
from public.workspaces w
where w.status = 'active';

commit;
