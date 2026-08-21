-- SPRINT-118R: ledger review guard for uncategorized operational entries.
-- Amount parsing alone is not enough for "accepted": if a ledger row has no
-- materialized category, it must stay visible as review until Smith/user fixes it.

begin;

create or replace function private.classify_uncategorized_ledger_entry()
returns trigger
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_transaction record;
  v_account record;
  v_classification record;
  v_category_id uuid;
begin
  if new.category_id is not null then
    return new;
  end if;

  select t.id, t.raw_text, t.status
  into v_transaction
  from public.transactions t
  where t.id = new.transaction_id
  limit 1;

  if not found or v_transaction.status = 'void' then
    return new;
  end if;

  perform private.ensure_workspace_default_categories(new.workspace_id);

  select a.code
  into v_account
  from public.accounts a
  where a.id = new.account_id
  limit 1;

  select *
  into v_classification
  from public.classify_foundation_entry(v_transaction.raw_text, new.direction, coalesce(v_account.code, 'cash'))
  limit 1;

  if v_classification.category_code is not null then
    select c.id
    into v_category_id
    from public.categories c
    where c.workspace_id = new.workspace_id
      and c.code = v_classification.category_code
      and c.is_active = true
      and (
        c.direction = 'neutral'
        or c.direction = new.direction
      )
    limit 1;
  end if;

  new.category_id := v_category_id;
  new.review_status := case
    when v_category_id is not null and v_classification.review_reason = 'accepted' then 'accepted'
    else 'review'
  end;
  new.metadata := coalesce(new.metadata, '{}'::jsonb) || jsonb_build_object(
    'category_code', v_classification.category_code,
    'category_confidence', v_classification.confidence,
    'review_reason', coalesce(v_classification.review_reason, 'no_category'),
    'matched_signals', coalesce(v_classification.matched_signals, '{}'),
    'blockers', coalesce(v_classification.blockers, '{}'),
    'semantic_markers', coalesce(v_classification.semantic_markers, '{}'),
    'category_materialized', v_category_id is not null,
    'category_source', 'auto_classifier'
  );

  return new;
end;
$$;

drop trigger if exists ledger_entries_auto_classify_uncategorized on public.ledger_entries;
create trigger ledger_entries_auto_classify_uncategorized
  before insert or update of category_id, direction, amount, account_id, transaction_id
  on public.ledger_entries
  for each row
  execute function private.classify_uncategorized_ledger_entry();

with classified as (
  select
    le.id as ledger_entry_id,
    c.id as category_id,
    cls.category_code,
    cls.confidence,
    cls.review_reason,
    cls.matched_signals,
    cls.blockers,
    cls.semantic_markers
  from public.ledger_entries le
  join public.transactions t on t.id = le.transaction_id
  join public.accounts a on a.id = le.account_id
  cross join lateral public.classify_foundation_entry(t.raw_text, le.direction, a.code) cls
  left join public.categories c on c.workspace_id = le.workspace_id
    and c.code = cls.category_code
    and c.is_active = true
    and (
      c.direction = 'neutral'
      or c.direction = le.direction
    )
  where le.category_id is null
    and t.status <> 'void'
)
update public.ledger_entries le
set
  category_id = classified.category_id,
  review_status = case
    when classified.category_id is not null and classified.review_reason = 'accepted' then 'accepted'
    else 'review'
  end,
  metadata = coalesce(le.metadata, '{}'::jsonb) || jsonb_build_object(
    'category_code', classified.category_code,
    'category_confidence', classified.confidence,
    'review_reason', coalesce(classified.review_reason, 'no_category'),
    'matched_signals', coalesce(classified.matched_signals, '{}'),
    'blockers', coalesce(classified.blockers, '{}'),
    'semantic_markers', coalesce(classified.semantic_markers, '{}'),
    'category_materialized', classified.category_id is not null,
    'category_source', 'auto_classifier_backfill'
  ),
  updated_at = now()
from classified
where le.id = classified.ledger_entry_id;

commit;
