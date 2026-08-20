-- SPRINT-101R: atomic operational entry command and future input ports.
-- The app sends an intent; the database creates the transaction, optional ledger
-- entry, row number, and audit event as one transaction-safe command.

create table if not exists public.input_channels (
  code text primary key,
  label jsonb not null default '{}'::jsonb,
  description text,
  is_system boolean not null default true,
  is_active boolean not null default true,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create table if not exists public.language_packs (
  code text primary key,
  label_native text not null,
  label_ru text not null,
  is_system boolean not null default true,
  is_active boolean not null default true,
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

insert into public.input_channels (code, label, description, metadata) values
  ('manual', '{"ru":"Ручной ввод","en":"Manual input"}', 'Typed directly in the operational journal.', '{"port":"ledger"}'),
  ('quick_note', '{"ru":"Быстрая заметка","en":"Quick note"}', 'Free-form note reviewed by Smith before ledger conversion.', '{"port":"smith"}'),
  ('scanner', '{"ru":"Скан чека","en":"Receipt scan"}', 'Future receipt and invoice capture with document matching.', '{"port":"documents","future":true}'),
  ('telegram', '{"ru":"Telegram","en":"Telegram"}', 'Future Telegram bot input channel.', '{"port":"bot","future":true}'),
  ('voice', '{"ru":"Голос","en":"Voice"}', 'Future voice note transcription channel.', '{"port":"speech","future":true}'),
  ('import', '{"ru":"Импорт","en":"Import"}', 'File or external ledger import.', '{"port":"import"}'),
  ('api', '{"ru":"API","en":"API"}', 'External integration input.', '{"port":"api","future":true}')
on conflict (code) do update set
  label = excluded.label,
  description = excluded.description,
  metadata = excluded.metadata,
  is_active = true;

insert into public.language_packs (code, label_native, label_ru, metadata) values
  ('ru', 'Русский', 'Русский', '{"default":true}'),
  ('en', 'English', 'Английский', '{}'),
  ('it', 'Italiano', 'Итальянский', '{}'),
  ('hr', 'Hrvatski', 'Хорватский', '{}'),
  ('sr', 'Srpski', 'Сербский', '{}'),
  ('de', 'Deutsch', 'Немецкий', '{}'),
  ('es', 'Español', 'Испанский', '{}'),
  ('fr', 'Français', 'Французский', '{}'),
  ('zh', '中文普通话', 'Китайский мандарин', '{}')
on conflict (code) do update set
  label_native = excluded.label_native,
  label_ru = excluded.label_ru,
  metadata = excluded.metadata,
  is_active = true;

alter table public.input_channels enable row level security;
alter table public.language_packs enable row level security;

drop policy if exists reference_input_channels_read on public.input_channels;
create policy reference_input_channels_read on public.input_channels
  for select to authenticated using (is_active = true);

drop policy if exists reference_language_packs_read on public.language_packs;
create policy reference_language_packs_read on public.language_packs
  for select to authenticated using (is_active = true);

create or replace function public.create_operational_entry(
  p_workspace_id uuid,
  p_account_code text,
  p_occurred_on date,
  p_raw_text text,
  p_source_type text default 'manual',
  p_source_channel text default 'manual',
  p_source_language text default 'ru',
  p_source_id uuid default null,
  p_source_ref jsonb default '{}'::jsonb,
  p_metadata jsonb default '{}'::jsonb
)
returns table (
  transaction_id uuid,
  ledger_entry_id uuid,
  row_no integer,
  counted boolean,
  transaction_status text,
  review_status text
)
language plpgsql
security definer
set search_path = ''
as $$
declare
  v_account record;
  v_raw text := btrim(coalesce(p_raw_text, ''));
  v_source_type text := lower(btrim(coalesce(p_source_type, 'manual')));
  v_source_channel text := lower(btrim(coalesce(p_source_channel, 'manual')));
  v_source_language text := lower(btrim(coalesce(p_source_language, 'ru')));
  v_source_ref jsonb := coalesce(p_source_ref, '{}'::jsonb);
  v_metadata jsonb := coalesce(p_metadata, '{}'::jsonb);
  v_user_id uuid := auth.uid();
  v_signed_match text[];
  v_unsigned_match text[];
  v_sign text;
  v_amount numeric(14,2);
  v_direction text;
  v_row_no integer;
  v_transaction_id uuid;
  v_ledger_entry_id uuid;
  v_counted boolean := false;
  v_transaction_status text := 'needs_review';
  v_review_status text := null;
  v_parser_reason text := null;
begin
  if v_user_id is null then
    raise exception 'auth_required';
  end if;

  if v_raw = '' then
    raise exception 'raw_text_required';
  end if;

  if p_occurred_on is null then
    raise exception 'occurred_on_required';
  end if;

  if v_source_type not in ('manual', 'quick_note', 'import', 'expense_report', 'correction', 'system') then
    raise exception 'unsupported_source_type:%', v_source_type;
  end if;

  if not exists (
    select 1 from public.input_channels c
    where c.code = v_source_channel and c.is_active = true
  ) then
    raise exception 'unsupported_input_channel:%', v_source_channel;
  end if;

  if not exists (
    select 1 from public.language_packs l
    where l.code = v_source_language and l.is_active = true
  ) then
    raise exception 'unsupported_language:%', v_source_language;
  end if;

  if not private.has_workspace_permission(p_workspace_id, 'ledger.write') then
    raise exception 'ledger_write_required';
  end if;

  select a.id, a.organization_id, a.workspace_id, a.account_type, a.currency_code
  into v_account
  from public.accounts a
  join public.workspaces w on w.id = a.workspace_id
  where a.workspace_id = p_workspace_id
    and a.code = btrim(coalesce(p_account_code, 'cash'))
    and a.is_active = true
    and w.status = 'active'
  limit 1;

  if not found then
    raise exception 'account_not_found';
  end if;

  select regexp_match(v_raw, '^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)') into v_signed_match;

  if v_signed_match is not null then
    v_sign := v_signed_match[1];
    v_amount := replace(v_signed_match[2], ',', '.')::numeric(14,2);
    v_direction := case when v_sign = '+' then 'income' else 'expense' end;
    v_counted := true;
    v_transaction_status := 'open';
    v_review_status := 'accepted';
  else
    select regexp_match(v_raw, '^([0-9]+(?:[.,][0-9]{1,2})?)') into v_unsigned_match;

    if v_unsigned_match is not null then
      v_amount := replace(v_unsigned_match[1], ',', '.')::numeric(14,2);
      v_parser_reason := 'missing_sign';
    else
      v_parser_reason := 'amount_missing';
    end if;
  end if;

  if v_amount is not null and v_amount <= 0 then
    raise exception 'amount_must_be_positive';
  end if;

  if v_counted
    and v_account.account_type = 'card'
    and v_direction = 'income'
    and v_source_type = 'manual'
  then
    raise exception 'manual_card_income_blocked';
  end if;

  perform pg_advisory_xact_lock(hashtext('findesk_operational_row:' || p_workspace_id::text));

  select coalesce(max(t.row_no), 0) + 1
  into v_row_no
  from public.transactions t
  where t.workspace_id = p_workspace_id;

  insert into public.transactions (
    organization_id,
    workspace_id,
    account_id,
    source_type,
    source_id,
    occurred_on,
    row_no,
    raw_text,
    status,
    metadata,
    created_by
  ) values (
    v_account.organization_id,
    p_workspace_id,
    v_account.id,
    v_source_type,
    p_source_id,
    p_occurred_on,
    v_row_no,
    v_raw,
    v_transaction_status,
    v_metadata || jsonb_build_object(
      'account_code', btrim(coalesce(p_account_code, 'cash')),
      'source_channel', v_source_channel,
      'source_language', v_source_language,
      'source_ref', v_source_ref,
      'parser', 'foundation_operational_entry_rpc_v1',
      'counted', v_counted,
      'candidate_amount', v_amount,
      'candidate_direction', v_direction,
      'parser_reason', v_parser_reason
    ),
    v_user_id
  )
  returning id into v_transaction_id;

  if v_counted then
    insert into public.ledger_entries (
      organization_id,
      workspace_id,
      transaction_id,
      account_id,
      direction,
      amount,
      currency_code,
      review_status,
      metadata
    ) values (
      v_account.organization_id,
      p_workspace_id,
      v_transaction_id,
      v_account.id,
      v_direction,
      v_amount,
      v_account.currency_code,
      v_review_status,
      jsonb_build_object(
        'account_code', btrim(coalesce(p_account_code, 'cash')),
        'source_channel', v_source_channel,
        'source_language', v_source_language,
        'parser', 'foundation_operational_entry_rpc_v1'
      )
    )
    returning id into v_ledger_entry_id;
  end if;

  insert into public.approval_events (
    organization_id,
    workspace_id,
    entity_type,
    entity_id,
    event_type,
    actor_user_id,
    note,
    metadata
  ) values (
    v_account.organization_id,
    p_workspace_id,
    'transaction',
    v_transaction_id,
    case when v_counted then 'operational_entry_created' else 'operational_entry_needs_review' end,
    v_user_id,
    null,
    jsonb_build_object(
      'row_no', v_row_no,
      'account_code', btrim(coalesce(p_account_code, 'cash')),
      'source_type', v_source_type,
      'source_channel', v_source_channel,
      'source_language', v_source_language,
      'counted', v_counted,
      'candidate_amount', v_amount,
      'candidate_direction', v_direction,
      'parser_reason', v_parser_reason
    )
  );

  return query select
    v_transaction_id,
    v_ledger_entry_id,
    v_row_no,
    v_counted,
    v_transaction_status,
    v_review_status;
end;
$$;

revoke execute on function public.create_operational_entry(
  uuid, text, date, text, text, text, text, uuid, jsonb, jsonb
) from public, anon;
grant execute on function public.create_operational_entry(
  uuid, text, date, text, text, text, text, uuid, jsonb, jsonb
) to authenticated;
