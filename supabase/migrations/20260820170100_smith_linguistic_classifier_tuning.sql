-- SPRINT-104R follow-up: tune clear owner funding and birthday decoration wording.

begin;

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
      review_reason := 'accepted';
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
    review_reason := 'accepted';
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

  if v_desc ~ '(подар|презент|розы|цветы|украшен|дн[юяеь]* рожден|день рожден|представительск|делов(ой|ая).*обед|делов(ой|ая).*ужин|обед с|ужин с|встреча с|business lunch|business dinner|hospitality)' then
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

commit;
