-- SPRINT-106R: backfill materialized category links for Smith-classified ledger rows.

begin;

select private.ensure_workspace_default_categories(w.id)
from public.workspaces w
where w.status = 'active';

update public.ledger_entries le
set category_id = c.id,
    metadata = le.metadata || jsonb_build_object(
      'category_code', c.code,
      'category_materialized', true,
      'category_source', coalesce(le.metadata->>'category_source', 'smith_backfill')
    ),
    updated_at = now()
from public.transactions t
join public.categories c
  on c.workspace_id = t.workspace_id
  and c.is_active = true
where le.transaction_id = t.id
  and le.workspace_id = t.workspace_id
  and le.category_id is null
  and c.code = coalesce(
    le.metadata->>'category_code',
    t.metadata->>'candidate_category_code',
    t.metadata->>'category_code'
  )
  and (
    c.direction = 'neutral'
    or c.direction = le.direction
  );

commit;
