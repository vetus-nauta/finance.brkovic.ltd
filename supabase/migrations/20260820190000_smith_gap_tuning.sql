-- SPRINT-109R: close observed Mr. Smith linguistic gaps without private yacht-name rules.
-- This wrapper keeps the previous classifier as fallback and only intercepts
-- high-signal wording that was repeatedly corrected in the beta corpus.

begin;

do $$
begin
  if to_regprocedure('public.classify_foundation_entry_base_20260820(text,text,text)') is null then
    alter function public.classify_foundation_entry(text, text, text)
      rename to classify_foundation_entry_base_20260820;
  end if;
end;
$$;

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
  v_has_service_action boolean;
begin
  v_desc := btrim(regexp_replace(v_text, '^[+-]?\s*[0-9]+(?:[.,][0-9]{1,2})?\s*', ''));
  v_has_service_action := v_desc ~ '(сервис|ремонт|обслуж|диагност|замена|монтаж|мастер|электрик|варка|тест|пересертифик|откачка)';

  if v_desc = '' then
    return query
      select *
      from public.classify_foundation_entry_base_20260820(p_raw_text, p_candidate_direction, p_account_code);
    return;
  end if;

  if (v_direction = 'income' or v_text ~ '^\+')
    and v_desc ~ '(аренд[аы] яхт|ареда яхт|сдач[ауы] яхт|чартер|оплат[аы] чартера|charter|yacht rental|yacht booking|noleggio yacht|alquiler de yate|najam jahte)'
  then
    category_code := 'commercial_income';
    confidence := 0.92;
    review_reason := 'accepted';
    matched_signals := array['explicit_yacht_commercial_income'];
    blockers := '{}';
    semantic_markers := array['commercial_income_allowed'];
    return next;
    return;
  end if;

  if (v_direction = 'income' or v_text ~ '^\+')
    and v_desc ~ '(судовлад|владелец|owner funding|owner top.?up|пополн.*владельц|пополн.*судовлад|от владельц|от судовлад)'
  then
    category_code := 'non_commercial_income';
    confidence := 0.88;
    review_reason := 'owner_funding';
    matched_signals := array['explicit_owner_funding_income'];
    blockers := '{}';
    semantic_markers := array['owner_funding'];
    return next;
    return;
  end if;

  if v_desc ~ '(оплат.*карт.*сво.*вернул.*кеш|сво.*расход.*вернул.*кеш|вернул.*кеш.*сво.*расход)' then
    category_code := 'cash_topup_from_card';
    confidence := 0.84;
    review_reason := 'accepted';
    matched_signals := array['admin_personal_card_expense_cash_repaid'];
    blockers := '{}';
    semantic_markers := array['money_movement'];
    return next;
    return;
  end if;

  if v_desc ~ '(снял.*(кеш|налич)|снятие.*(кеш|налич)|банкомат|atm|cash withdrawal|кеш с карты|cash с карты|card to cash)' then
    category_code := 'cash_topup_from_card';
    confidence := 0.90;
    review_reason := 'accepted';
    matched_signals := array['cash_withdrawal_money_movement'];
    blockers := '{}';
    semantic_markers := array['money_movement'];
    return next;
    return;
  end if;

  if v_desc ~ '(форма|спец ?одежд|одежд.*экипаж|экипаж.*одежд|униформ)' then
    category_code := 'current_boat_expenses';
    confidence := 0.84;
    review_reason := 'accepted';
    matched_signals := array['crew_uniform_is_current_boat_expense'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '(зп|зарплат|salary|wage|аванс.*зп|остаток.*зп|сотрудник|сотруднику|экипаж|капитан|хостесс|стюард|декхенд|матрос|повар|помощник|помощниц|чаевые)' then
    category_code := 'crew';
    confidence := 0.88;
    review_reason := 'accepted';
    matched_signals := array['crew_salary_tip_or_staff_context'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '^[[:alpha:]а-яё]+[,[:space:]]+помощь$' then
    category_code := 'crew';
    confidence := 0.78;
    review_reason := 'accepted';
    matched_signals := array['person_help_temp_staff_context'];
    blockers := '{}';
    semantic_markers := array['actor_context'];
    return next;
    return;
  end if;

  if v_desc ~ '(расходник|креп[её]ж|креплен|магнит|клипс|радиостанц|icom|инструмент|сверл|чертеж.*3д|3d принтер|3д принтер|проводк.*кабел|кабел|шнур.*лодк|к2|sikaflex|сикафлекс|тик.*(силер|клинер|брайтнер)|тиксилер|средство для тика|пропитк.*тик|абразив.*тик|образив.*тик|консервант.*тик|дезинфектор.*тик)' then
    category_code := 'tech_parts';
    confidence := 0.88;
    review_reason := 'accepted';
    matched_signals := array['technical_consumable_part_material_or_tool'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '(тузик|тендер|динги|dinghy).*(спуск|под[ъь]?ем|подьем)|(спуск|под[ъь]?ем|подьем).*(тузик|тендер|динги|dinghy)' then
    category_code := 'tender';
    confidence := 0.86;
    review_reason := 'accepted';
    matched_signals := array['tender_launch_haul_context'];
    blockers := '{}';
    semantic_markers := array['tender_related'];
    return next;
    return;
  end if;

  if not v_has_service_action
    and v_desc ~ '(холодильник|кофемаш|блендер|соковыжим|микроволнов|посуда|нож|свеч|брызгалк|душ.*принадлежн|судоч|кухонн|кухн|утварь|пошив.*подуш|подуш.*пошив)'
  then
    category_code := 'interior';
    confidence := 0.82;
    review_reason := 'accepted';
    matched_signals := array['household_appliance_tableware_or_interior_object'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '(картина тв|тв пристав|приставк|динамик|sonos|сонос|подписк.*навигац|навигац.*подписк|фейсбук|facebook|инстаграм|instagram|реклам|зарядник.*телефон|шнур.*телефон)' then
    category_code := 'media_comms';
    confidence := 0.84;
    review_reason := 'accepted';
    matched_signals := array['media_navigation_subscription_or_device_context'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '(отпорн.*крюк|крыло.*сап|(^|\s)сап($|\s)|sup board|огни.*тузик|шнур.*тузик|динги|dinghy)' then
    category_code := 'tender';
    confidence := 0.84;
    review_reason := 'accepted';
    matched_signals := array['tender_or_water_toy_equipment_context'];
    blockers := '{}';
    semantic_markers := array['tender_related'];
    return next;
    return;
  end if;

  if v_desc ~ '(экспресс почт|почта|почтов|dhl|fedex|ups|курьер)' then
    category_code := 'transport_expenses';
    confidence := 0.82;
    review_reason := 'accepted';
    matched_signals := array['postal_courier_transport_context'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '(нотариус|переводчик|нотариальн|апостил|apostille)' then
    category_code := 'admin_legal';
    confidence := 0.86;
    review_reason := 'accepted';
    matched_signals := array['notary_translation_document_context'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '(докер|докеры|портов.*рабоч)' then
    category_code := 'current_boat_expenses';
    confidence := 0.78;
    review_reason := 'accepted';
    matched_signals := array['dock_worker_current_boat_expense'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '(перевод.*денег.*банк|банк.*перевод.*денег|швейцарск.*банк|банковск.*перевод)' then
    category_code := 'current_boat_expenses';
    confidence := 0.76;
    review_reason := 'accepted';
    matched_signals := array['bank_transfer_current_boat_expense'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  if v_desc ~ '^чай[[:space:]]+[[:alpha:]а-яё]+$' then
    category_code := 'current_boat_expenses';
    confidence := 0.70;
    review_reason := 'accepted';
    matched_signals := array['actor_tea_context_owner_confirmed_current_expense'];
    blockers := '{}';
    semantic_markers := array['actor_context'];
    return next;
    return;
  end if;

  if v_desc ~ '(мест[ао].*(самолет|самол[её]т|авиа|рейс|перел[её]т)|(?:самолет|самол[её]т|авиа|рейс|перел[её]т).*мест[ао]|^[[:alpha:]а-яё]+[[:space:]]+мест[ао]$)' then
    category_code := 'guest_trip_support';
    confidence := 0.78;
    review_reason := 'accepted';
    matched_signals := array['guest_flight_seat_context'];
    blockers := '{}';
    semantic_markers := '{}';
    return next;
    return;
  end if;

  return query
    select *
    from public.classify_foundation_entry_base_20260820(p_raw_text, p_candidate_direction, p_account_code);
end;
$$;

revoke execute on function public.classify_foundation_entry(text, text, text) from public, anon;
grant execute on function public.classify_foundation_entry(text, text, text) to authenticated;

commit;
