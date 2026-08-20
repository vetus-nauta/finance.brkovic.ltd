-- SPRINT-104R: Smith linguistic classifier.
-- Adds an explainable category/semantic suggestion layer to quick-note review
-- without letting weak words or merchant aliases silently train accounting truth.

begin;

alter table public.smith_entry_proposals
  add column if not exists candidate_category_code text,
  add column if not exists confidence numeric(4,2),
  add column if not exists review_reason text,
  add column if not exists matched_signals text[] not null default '{}',
  add column if not exists blockers text[] not null default '{}',
  add column if not exists semantic_markers text[] not null default '{}';

create or replace function public.classify_foundation_entry(
  p_raw_text text,
  p_candidate_direction text default null,
  p_account_code text default 'cash'
)
returns table (
  category_code text,
  confidence numeric(4,2),
  review_reason text,
  matched_signals text[],
  blockers text[],
  semantic_markers text[]
)
language plpgsql
stable
security invoker
set search_path = ''
as $$
declare
  v_text text := lower(btrim(coalesce(p_raw_text, '')));
  v_desc text;
  v_direction text := lower(btrim(coalesce(p_candidate_direction, '')));
  v_account_code text := lower(btrim(coalesce(p_account_code, 'cash')));
  v_has_debt_marker boolean;
begin
  v_desc := btrim(regexp_replace(v_text, '^[+-]?\s*[0-9]+(?:[.,][0-9]{1,2})?\s*', ''));
  category_code := 'other';
  confidence := 0.30;
  review_reason := 'no_category';
  matched_signals := '{}';
  blockers := '{}';
  semantic_markers := '{}';

  if v_text = '' then
    blockers := array_append(blockers, 'empty_text');
    return next;
    return;
  end if;

  if v_desc ~ '(цоги\s*мар|цогимар|cogimar)' then
    blockers := array_append(blockers, 'merchant_alias_review');
    review_reason := 'merchant_alias_review';
    matched_signals := array_append(matched_signals, 'fish_shop_alias_not_universal_rule');
    return next;
    return;
  end if;

  if v_desc ~ '(сейф|сеф|из сейфа|из сефа|в сейф|в сеф)' then
    semantic_markers := array_append(semantic_markers, 'cash_location_safe');
  end if;

  if v_desc ~ '(долг|кредит|займ|заем|под\s*отчет|подотчет|вернул|возврат|рассроч)' then
    semantic_markers := array_append(semantic_markers, 'debt_or_return');
    v_has_debt_marker := true;
  else
    v_has_debt_marker := false;
  end if;

  if v_direction = 'income' then
    if v_desc ~ '(аренд[аы] яхт|ареда яхт|сдач[ауы] яхт|чартер|оплат[аы] чартера|charter|yacht rental|yacht booking|noleggio yacht|alquiler de yate|najam jahte)' then
      category_code := 'commercial_income';
      confidence := 0.92;
      review_reason := 'accepted';
      matched_signals := array_append(matched_signals, 'explicit_yacht_commercial_income');
      semantic_markers := array_append(semantic_markers, 'commercial_income_allowed');
      return next;
      return;
    end if;

    if v_desc ~ '(снял.*к(а|е)рт|снят.*к(а|е)рт|банкомат|atm|cash withdrawal|card to cash|кеш с карты|cash с карты)' then
      category_code := 'cash_topup_from_card';
      confidence := 0.90;
      review_reason := 'money_movement';
      matched_signals := array_append(matched_signals, 'card_to_cash_movement');
      semantic_markers := array_append(semantic_markers, 'money_movement');
      return next;
      return;
    end if;

    if v_account_code = 'card' then
      category_code := 'non_commercial_income';
      confidence := 0.40;
      review_reason := 'card_income_needs_guard';
      blockers := array_append(blockers, 'card_income_guard');
      semantic_markers := array_append(semantic_markers, 'owner_funding');
      return next;
      return;
    end if;

    category_code := 'non_commercial_income';
    confidence := 0.82;
    review_reason := 'owner_funding';
    matched_signals := array_append(matched_signals, 'cash_income_without_commercial_wording');
    semantic_markers := array_append(semantic_markers, 'owner_funding');
    return next;
    return;
  end if;

  if v_desc ~ '(мой|моя|мои|себе|для себя|домой|личн|с тему|temu|мото навигатор)' then
    category_code := 'admin_debt';
    confidence := 0.88;
    review_reason := 'admin_debt';
    matched_signals := array_append(matched_signals, 'administrator_personal_context');
    semantic_markers := array_append(semantic_markers, 'admin_debt');
    blockers := array_append(blockers, 'not_operational_category_total');
    return next;
    return;
  end if;

  if v_desc ~ '(подар|презент|розы|цветы|украшен.*др|день рожден|представительск|делов(ой|ая).*обед|делов(ой|ая).*ужин|обед с|ужин с|встреча с|business lunch|business dinner|hospitality)' then
    category_code := 'representation_expenses';
    confidence := 0.86;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'representation_or_business_hospitality');
    return next;
    return;
  end if;

  if v_desc ~ '(доставка.*(фильтр|запчаст|кабел|шланг|анод|помп|насос|датчик|реле|регулятор|компрессор)|запчаст|расходник|кабел|коротк.*кабел|старлинк кабел|анод|болт|шуруп|фильтр|аккум|клей|реле|датчик|проклад|переходник|шланг|навигац|шлиф|пылесос|трюмн|помп|подрульк|пордрульк|компрессор|диммер|гелькоут|кранц|кранец|швартов|регулятор давления|контролька|кондея|блок управления туалетом|петля холодильник|люки.*танк|ролики цепи|подстаканник)' then
    category_code := 'tech_parts';
    confidence := 0.90;
    review_reason := case when v_desc ~ 'доставка' then 'mixed_context' else 'accepted' end;
    matched_signals := array_append(matched_signals, 'technical_part_or_material');
    if v_desc ~ 'доставка' then
      semantic_markers := array_append(semantic_markers, 'mixed_dictionary_context');
    end if;
    return next;
    return;
  end if;

  if v_desc ~ '(сервис|ремонт|обслуж|диагност|мастер|консервац|опресн|спас плот|пересертифик|дайвер|водолаз|электрик|откачка|замена|монтаж|варка|генератор|тест систем|огнетуш|miele|стиралк|холодильник|лебедк)' then
    category_code := 'service_water';
    confidence := 0.82;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'service_work_context');
    return next;
    return;
  end if;

  if v_desc ~ '(сухой док|антифоулинг|под[ъь]?ем|спуск|haul out|launch)' then
    category_code := 'dry_dock';
    confidence := 0.88;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'dry_dock_context');
    return next;
    return;
  end if;

  if v_desc ~ '(топливо|заправ|дозаправ|бензин|дизел|fuel|diesel|petrol|gorivo|nafta)' then
    category_code := 'fuel';
    confidence := 0.90;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'fuel_context');
    if v_desc ~ '(тузик|тендер|dinghy|tender|williams)' then
      semantic_markers := array_append(semantic_markers, 'tender_related');
    end if;
    return next;
    return;
  end if;

  if v_desc ~ '(тузик|тендер|dinghy|tender|williams|outboard|seabob|сибоб)' then
    category_code := 'tender';
    confidence := 0.84;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'tender_context');
    return next;
    return;
  end if;

  if v_desc ~ '(стоянк|зимовк|гараж|склад|муринг|mooring|berth|vez|вода.*электрич|электрич.*вода|электричеств)' then
    category_code := 'berth';
    confidence := 0.86;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'berth_or_marina_utility_context');
    return next;
    return;
  end if;

  if v_desc ~ '(марин|причал|порт|паром|выход в море|переход коринф|проход через коринф|tepai|такса по входу|porto|luka|harbou?r|границ|транзит ?лог|transit log)' then
    category_code := 'marina_ports';
    confidence := 0.84;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'port_or_marine_formality_context');
    return next;
    return;
  end if;

  if v_desc ~ '(самокат|скутер|параплан|музыкант|нац.?парк|музе[йя]|снасти|маски|ласты|водные игрушки|зарядк.*шеф|айфон.*гост|iphone.*guest|отел|гостиниц)' then
    category_code := 'guest_trip_support';
    confidence := 0.82;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'guest_trip_support_context');
    return next;
    return;
  end if;

  if v_desc ~ '(^|\s)(лв|леонид владимирович)($|\s)|расходы лв|игра лв|передал лв|отдал лв|дал лв|выдал гост|дал гост|наличн.*гост' then
    category_code := 'guest_cash_issued';
    confidence := 0.88;
    review_reason := 'lower_accounting';
    matched_signals := array_append(matched_signals, 'guest_cash_handoff');
    semantic_markers := array_append(semantic_markers, 'guest_cash_issued');
    return next;
    return;
  end if;

  if v_desc ~ '(зп|зарплат|аванс.*зп|остаток.*зп|экипаж|капитан|хостесс|стюард|декхенд|матрос|повар|чаевые|работник в помощь|помощниц.*уборк)' then
    category_code := 'crew';
    confidence := 0.86;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'crew_payment_or_tip_context');
    return next;
    return;
  end if;

  if v_desc ~ '(такси|трансфер|аренда авто|рентакар|rentacar|билет|билеты|перел[её]т|авиа|самолет|самол[её]т|мест[ао].*самолет|поезд|автобус|air serbia|логистик|доставка|курьер|перевозк|taxi|transfer|car rental|tickets|delivery)' then
    category_code := 'transport_expenses';
    confidence := case when v_desc ~ '(доставка|курьер)' then 0.58 else 0.84 end;
    review_reason := case when v_desc ~ '(доставка|курьер)' then 'weak_only' else 'accepted' end;
    matched_signals := array_append(matched_signals, 'transport_or_delivery_context');
    if v_desc ~ '(доставка|курьер)' then
      semantic_markers := array_append(semantic_markers, 'weak_dictionary_context');
    end if;
    return next;
    return;
  end if;

  if v_desc ~ '(адвокат|юрист|тур.?регистрац|тамож|растамож|дьюти|документ|печать|ламинир|виза|виньет|радио ?лиценз|страховк|tax|налог)' then
    category_code := 'admin_legal';
    confidence := 0.86;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'admin_legal_context');
    return next;
    return;
  end if;

  if v_desc ~ '(химчист|уборк|cleaning|laundry|мойк|тряпк|салф|detergent|прачк|полирол|пенообразователь|керхер|мусор|вывоз мусора|душев.*принадлежн|химия)' then
    category_code := 'cleaning';
    confidence := 0.80;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'cleaning_context');
    return next;
    return;
  end if;

  if v_desc ~ '(ковр|текстил|полотен|обувь|судоч|нож|посуд|матрас|кухонн|кухн|утварь|интерьер|перешив подуш|подушк|чехл|скатерть|нарды|шезлонг|кофемаш|соковыжим|инвентарь)' then
    category_code := 'interior';
    confidence := 0.78;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'interior_household_context');
    return next;
    return;
  end if;

  if v_desc ~ '(netflix|apple|ivi|старлинк|starlink|сим|интернет|инет|wifi|telekom|картина тв|тв|телевиз|sonos|сонос|модем|подписк)' then
    category_code := 'media_comms';
    confidence := 0.82;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'media_or_communications_context');
    return next;
    return;
  end if;

  if v_desc ~ '(брендир|форма|спец ?одежд|агент|магазин|комисси.*банк|банковск.*комисс|забрал свои|bank fee|bank commission|офис|принтер)' then
    category_code := 'current_boat_expenses';
    confidence := case when v_desc ~ '(агент|магазин)' then 0.58 else 0.78 end;
    review_reason := case when v_desc ~ '(агент|магазин)' then 'weak_only' else 'accepted' end;
    matched_signals := array_append(matched_signals, 'current_boat_overhead_context');
    if v_desc ~ '(агент|магазин)' then
      semantic_markers := array_append(semantic_markers, 'weak_dictionary_context');
    end if;
    return next;
    return;
  end if;

  if v_desc ~ '(продукт|еда|обед|ужин|рыб|морепродукт|фрукт|овощ|хлеб|мяс|стейк|вино|пиво|напит|алкоголь|виски|водка|шампан|мед|приправ|кухня|провиз|вода|устриц|скамп|шкамп|краб|тунец|салмон|морож|яйц|рынок|аптек|лекарств|косметик|шампун)' then
    category_code := 'provisions';
    confidence := 0.86;
    review_reason := 'accepted';
    matched_signals := array_append(matched_signals, 'food_provisions_or_marine_pharmacy_context');
    return next;
    return;
  end if;

  if v_has_debt_marker then
    category_code := 'lower_accounting';
    confidence := 0.62;
    review_reason := 'lower_accounting';
    matched_signals := array_append(matched_signals, 'debt_or_accountable_without_operational_object');
    blockers := array_append(blockers, 'not_operational_category_total');
    return next;
    return;
  end if;

  if v_desc ~ '(айфон|iphone|планшет|ipad|обезналич|консьерж|книжка моряка|подставка под динги)' then
    category_code := 'other';
    confidence := 0.22;
    review_reason := 'other_review';
    blockers := array_append(blockers, 'ambiguous_object');
    return next;
    return;
  end if;

  return next;
end;
$$;

revoke execute on function public.classify_foundation_entry(text, text, text) from public, anon;
grant execute on function public.classify_foundation_entry(text, text, text) to authenticated;

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
  v_classification record;
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
    v_classification := null;

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

    select *
    into v_classification
    from public.classify_foundation_entry(v_line, v_candidate_direction, v_account.code)
    limit 1;

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

    if v_parser_reason is not null
      or v_duplicate_status = 'possible_duplicate'
      or coalesce(v_classification.review_reason, 'no_category') <> 'accepted'
    then
      v_review_count := v_review_count + 1;
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
      candidate_category_code,
      confidence,
      review_reason,
      matched_signals,
      blockers,
      semantic_markers,
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
      v_classification.category_code,
      v_classification.confidence,
      v_classification.review_reason,
      coalesce(v_classification.matched_signals, '{}'),
      coalesce(v_classification.blockers, '{}'),
      coalesce(v_classification.semantic_markers, '{}'),
      'pending',
      jsonb_build_object(
        'quick_note_body_hash', md5(v_note.body),
        'classification', jsonb_build_object(
          'category_code', v_classification.category_code,
          'confidence', v_classification.confidence,
          'review_reason', v_classification.review_reason,
          'matched_signals', coalesce(v_classification.matched_signals, '{}'),
          'blockers', coalesce(v_classification.blockers, '{}'),
          'semantic_markers', coalesce(v_classification.semantic_markers, '{}')
        )
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
        'duplicate_reason', v_proposal.duplicate_reason,
        'candidate_category_code', v_proposal.candidate_category_code,
        'confidence', v_proposal.confidence,
        'review_reason', v_proposal.review_reason,
        'matched_signals', v_proposal.matched_signals,
        'blockers', v_proposal.blockers,
        'semantic_markers', v_proposal.semantic_markers
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

commit;
