const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const childProcess = require('child_process');
const { MongoClient } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const PORT = Number(process.env.FINDESK_V2_ATLAS_READ_PORT || 18965);
const HOST = process.env.FINDESK_HOST || '127.0.0.1';
const DB_NAME = process.env.FINDESK_MONGO_DB || 'finance_brkovic_ltd';
const URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');
const USER_ID = Number(process.env.FINDESK_V2_ATLAS_USER_ID || 1);
const MONGO_SERVER_SELECTION_TIMEOUT_MS = Number(process.env.FINDESK_V2_ATLAS_SERVER_SELECTION_TIMEOUT_MS || 2000);
const MONGO_CONNECT_TIMEOUT_MS = Number(process.env.FINDESK_V2_ATLAS_CONNECT_TIMEOUT_MS || 1000);
const HTTP_JSON_MAX_BYTES = Number(process.env.FINDESK_V2_ATLAS_HTTP_JSON_MAX_BYTES || 12 * 1024 * 1024);
const COUNTED_STATUSES = new Set(['recognized', 'other_review', 'imported', 'accepted', 'corrected']);
const ATTACHMENT_MAX_BYTES = 8388608;
const ATTACHMENT_ALLOWED_MIME_EXTENSIONS = {
  'application/pdf': 'pdf',
  'image/jpeg': 'jpg',
  'image/png': 'png',
  'image/webp': 'webp',
};
const DICTIONARY_CATEGORY_RULES = [
  ['cash_topup_from_card', /снял кеш|снял с карты|снятие с карты|банкомат|atm|cash withdrawal|card to cash/u],
  ['commercial_income', /чартер|оплата чартера|аренд[^,.;]*яхт|ареда яхты|яхт[^,.;]*аренд|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)/u],
  ['dry_dock', /сухой док|антифоулинг|подъем|подъём|подьем|спуск|haul.?out|launch/u],
  ['berth', /стоянк|зимовк|склад|гараж|электричеств|муринг|mooring|berth|vez/u],
  ['marina_ports', /марин|порт|паром|выход в море|переход коринф|проход через коринф|tepai|такс[аы] по вход|luka|harbou?r/u],
  ['service_water', /сервис|обслуж|мастер|ремонт|репарац|механик|токарь|водолаз|diver|диагност|опреснител|спас.?плот|пересертифик|дайвер|электрик|откачка серых вод|откачка черн[а-я]* танк|черн[а-я]* танк|откачк[а-я]* вод|откачк[а-я]* грязн[а-я]* вод|выкачк[а-я]* танк|замен|монтаж|варк|консервац|тест систем|огнетуш|(?:^|\s)то(?:\s|$)/u],
  ['tech_parts', /аккумулятор|аккум|кабел|насос|мотор|детал|запчаст|инструмент|фильтр|анод|клей|реле|навигац|шлиф|машинк|пылесос|шланг|сантехник|расходник|расходники|крюк|переходник|генератор|батаре[яи]|батарейк|материал[а-я]* по тику|пропитк[аеи]? тик|расходники? по тику|для тика|тик.?клинер|тик.?силер|трюмн|помп|подрульк|лебедк|компрессор|диммер|гелькоут|кранц|кранец|швартов|веревк|контрольк|конде[яй]?|плоттер|навионикс|удлинитель|хомут|адаптер|болт|крепеж|мультиметр|предохранитель|сикафлекс|sikaflex|шуруп/u],
  ['tender', /тузик|тендер|dinghy|tender|williams|outboard|seabob|сибоб|сапы?|sup/u],
  ['fuel', /заправ|топлив|дизел|бензин|fuel|diesel|petrol|gorivo|nafta/u],
  ['guest_trip_support', /самокат|скутер|параплан|музыкант|прогулк[а-я]* гост|нац парк|вход в музей|снаст|зарядк[а-я]* шефу|маски$|перья на сап|весло сап|набор для ныряния/u],
  ['guest_cash_issued', /(?:^|\s)(?:лв|леонид владимирович)(?:\s|$)|расходы лв|общая потраченная сумма лв|игра лв|(?:передал|отдал|дал)\s+(?:лв|арику?|саше?|гост)/u],
  ['representation_expenses', /представительск|подарок|подарк[а-я]* служб|презент|розы|сувенир|украшен[а-я]* др|делов\p{L}*[^,.;]*(обед|ужин|встреч)|(?:обед|ужин|ланч|встреча)[^,.;]*(?:\sс\s|\sдля\s)[\p{L}]|hospitality|business lunch|business dinner|lunch with|dinner with/u],
  ['provisions', /продукт|продуукт|рыб|стейк|мяс|баранин|хлеб|фрукт|овощ|напит|вино|пиво|кола|сок|сироп|сладост|коктел|коктейл|устриц|скамп|шкамп|краб|кальмар|лангустин|осминог|лосось|тунец|салмон|сыр|морож|инжир|яйц|орех|мед|соус|острог|перекус|еда|ресторан|цветы|алкоголь|виски|водк|шампан|рынок|обед|кафе|косметик|гигиен|шампун|аптечк|аптек|лекарств|отел|гостиниц|(?:^|\s)вода(?!\s+электричеств)(?:\s|$)/u],
  ['interior', /ковр|текстил|полотен|обувь|судоч|нож|посуд|матрас|игрушк|linen|towels|кухонн[^,.;]*принадлежн|кухонн[а-я]* расход|инвентарь по кухне|кухн[а-я]*.*интерьер|утварь.*кухн|подушк|чехл|скатерт|шезлонг|кофе[\s-]?машин|кофемашин|блендер|микроволновк|одеял|наволочк|контейнер|на кухню/u],
  ['cleaning', /хим|мойк|моющ[а-я]* средств[а-я]*|салф|тряпк|пена|полиров|уборк|химчист|clean|laundry|detergent|прачк|прачеч|полирол|керхер|мусор|вывоз мусора|отбеливател|плесен|грибк|распылител|щетк[а-я]*(?: для лодк)?/u],
  ['media_comms', /netflix|нетфликс|apple|ivi|иви|старлинк|starlink|hipo|сим.?карт|интернет|инет|wifi|связ|telekom|картина.?тв|\bтв\b|телевиз|sonos|сонос|модем|роуминг|домен|хостинг|погод|hdmi|шнур[а-я]* телефон|чехол телефон/u],
  ['current_boat_expenses', /брендир|(?:^|[\s-])форм[а-я]*|спец.?одеж|спецодеж|агент|магазин|хоз.?товар|принтер|(?:^|\s)инвентарь(?!\s+по\s+кухне)(?:\s|$)|банковск[а-я]* перевод|комисси[яи] банк|банковск[а-я]* комисс|забрал свои|bank fee|bank commission/u],
  ['transport_expenses', /такси|трансфер|аренда авто|арендованн[а-я]* авто|рентакар|билеты?|перел[её]т|авиа|поезд|автобус|самол[её]т|air serbia|логистик|забрал гостей|дорожн[а-я]* расход|запра[вк][а-я]* авто|парковк|курьер|доставк|почт[а-я]* в сербию|taxi|transfer|car rental|tickets|delivery/u],
  ['admin_legal', /тур.?регистрац|тамож|дьюти|документ|печат[ьи]|налог|ндс|страхов|регистрац|юрист|адвокат|license|insurance|customs|виньет|лиценз|санад|такса|траст компани|внж|крулист|crew.?list|виза|судебн[а-я]* перевод|открытие счета|флаг[а-я]*|границ/u],
  ['crew', /\bзп\b|зарплат|аванс|капитан|хостесс|помощник|экипаж|работник в помощь|sailor|crew|salary|повар|чаев/u],
  ['other', /айфон|iphone|планшет|обезналич|консьерж|книжк[а-я]* моряка|подставк[а-я]* под динги/u],
];
const WORKSPACE_ROLE_LABELS = {
  owner: 'Владелец',
  admin: 'Администратор',
  assistant: 'Финансист',
  finance: 'Финансист',
  employee: 'Сотрудник',
  viewer: 'Только просмотр',
};
const WORKSPACE_ADMIN_ROLES = new Set(['owner', 'admin']);
const WORKSPACE_WRITER_ROLES = new Set(['owner', 'admin', 'assistant', 'finance']);

let mongoClient;
let mongoDb;

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  return fs.readFileSync(URI_FILE, 'utf8').trim();
}

async function db() {
  if (mongoDb) return mongoDb;
  mongoClient = new MongoClient(readMongoUri(), {
    serverSelectionTimeoutMS: MONGO_SERVER_SELECTION_TIMEOUT_MS,
    connectTimeoutMS: MONGO_CONNECT_TIMEOUT_MS,
  });
  await mongoClient.connect();
  mongoDb = mongoClient.db(DB_NAME);
  return mongoDb;
}

async function closeDb() {
  if (mongoClient) {
    await mongoClient.close();
  }
  mongoClient = null;
  mongoDb = null;
}

function json(res, payload, status = 200) {
  res.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'cache-control': 'no-store',
  });
  res.end(`${JSON.stringify(payload)}\n`);
}

function amount(value) {
  if (value === null || value === undefined || value === '') return null;
  return Number(value);
}

function bool(value) {
  return value === true || value === 1 || value === '1';
}

function phpBool(value) {
  return !(value === false || value === 0 || value === '0' || value === '' || value === null || value === undefined);
}

function decodeJson(value, fallback) {
  if (value === null || value === undefined || value === '') return fallback;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(String(value));
  } catch {
    return fallback;
  }
}

function clampInt(value, fallback, min, max) {
  const parsed = Number.parseInt(String(value ?? ''), 10);
  const number = Number.isFinite(parsed) ? parsed : fallback;
  return Math.max(min, Math.min(max, number));
}

function optionalIntInput(input, key, fallback) {
  if (!Object.hasOwn(input, key) || input[key] === '') return fallback;
  const parsed = Number.parseInt(String(input[key]), 10);
  if (!Number.isFinite(parsed) || String(input[key]).trim() !== String(parsed)) {
    const error = new Error(`invalid_${key}`);
    error.status = 422;
    throw error;
  }
  return parsed;
}

function optionalStringInput(input, key, fallback, max) {
  if (!Object.hasOwn(input, key)) return fallback;
  const value = String(input[key]).trim();
  if (value === '') return fallback;
  return value.slice(0, max);
}

function optionalStringListInput(input, key) {
  if (!Object.hasOwn(input, key) || input[key] === null || input[key] === '') return [];
  if (!Array.isArray(input[key])) {
    const error = new Error(`invalid_${key}`);
    error.status = 422;
    throw error;
  }
  return [...new Set(input[key]
    .map((value) => String(value).trim().slice(0, 255))
    .filter((value) => value !== ''))];
}

function requireStringInput(input, key, max) {
  const value = String(input[key] ?? '').trim();
  if (value === '') {
    const error = new Error(`missing_${key}`);
    error.status = 422;
    throw error;
  }
  return value.slice(0, max);
}

function requireDateInput(input, key) {
  const value = requireStringInput(input, key, 10);
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const error = new Error(`invalid_${key}`);
    error.status = 422;
    throw error;
  }
  const date = new Date(`${value}T00:00:00Z`);
  if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== value) {
    const error = new Error(`invalid_${key}`);
    error.status = 422;
    throw error;
  }
  return value;
}

function optionalDateInput(input, key, fallback) {
  if (!Object.hasOwn(input, key) || input[key] === '' || input[key] === null || input[key] === undefined) {
    return fallback;
  }
  return requireDateInput(input, key);
}

function normalizeRequiredEmail(value) {
  const email = String(value ?? '').trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    const error = new Error('invalid_email');
    error.status = 422;
    throw error;
  }
  return email.slice(0, 190);
}

function enumInput(value, allowed, key) {
  if (!allowed.includes(value)) {
    const error = new Error(`invalid_${key}`);
    error.status = 422;
    throw error;
  }
  return value;
}

function nowSql() {
  return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

function uuid() {
  return crypto.randomUUID();
}

function tokenHash(token) {
  return crypto.createHash('sha256').update(token).digest('hex');
}

function nullableAmount(value) {
  if (value === null || value === undefined || value === '') return null;
  const normalized = String(value).trim().replace(/[ \u00a0]/g, '').replace(',', '.');
  if (!/^-?\d+(?:\.\d+)?$/.test(normalized)) {
    const error = new Error('invalid_amount');
    error.status = 422;
    throw error;
  }
  return Number(normalized).toFixed(2);
}

function normalizeRoute(rawRoute) {
  let route = String(rawRoute || '/api');
  if (!route.startsWith('/')) route = `/${route}`;
  return route.replace(/\/+$/, '') || '/api';
}

function flowRow(row) {
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    name: String(row.name),
    type: String(row.type),
    has_live_balance: bool(row.has_live_balance),
    opening_balance: amount(row.opening_balance) || 0,
    is_default: bool(row.is_default),
    created_at: row.created_at || null,
  };
}

function categoryRow(row) {
  return {
    id: String(row.id),
    workspace_id: row.workspace_id || null,
    code: String(row.code),
    name: decodeJson(row.name_json, {}),
    direction: String(row.direction),
    parent_code: row.parent_code || null,
    sort_order: Number(row.sort_order || 0),
    is_system: bool(row.is_system),
  };
}

function categoryRuleRow(row) {
  return {
    id: String(row.id),
    workspace_id: row.workspace_id || null,
    category_code: String(row.category_code),
    pattern: String(row.pattern),
    pattern_type: String(row.pattern_type),
    language: String(row.language),
    weight: Number(row.weight || 0),
    negative_weight: Number(row.negative_weight || 0),
    requires_any: decodeJson(row.requires_any_json, []),
    excludes_any: decodeJson(row.excludes_any_json, []),
    created_by_user: bool(row.created_by_user),
    is_active: bool(row.is_active),
    created_at: row.created_at || null,
  };
}

function workspaceRow(row, member = null) {
  const workspace = {
    id: String(row.id),
    name: String(row.name),
    type: String(row.type),
    currency: String(row.currency),
    locale: String(row.locale),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
    updated_at: row.updated_at || null,
  };
  if (member) {
    workspace.role = String(member.role);
    workspace.access_scope = String(member.access_scope || 'workspace');
    workspace.assigned_actor_id = member.assigned_actor_id || null;
    workspace.can_manage = ['owner', 'admin'].includes(workspace.role);
    workspace.can_write = ['owner', 'admin', 'assistant', 'finance'].includes(workspace.role);
  }
  return workspace;
}

function workspaceAccessFromMember(member) {
  const role = String(member.role || '');
  let scope = typeof member.access_scope === 'string' ? member.access_scope : null;
  if (role === 'employee') {
    scope = ['own_entries', 'assigned_actor', 'none'].includes(scope) ? scope : 'own_entries';
  } else {
    scope = scope || 'workspace';
  }
  const canWriteWorkspace = WORKSPACE_WRITER_ROLES.has(role);
  const canAdmin = WORKSPACE_ADMIN_ROLES.has(role);
  const canReadWorkspace = scope === 'workspace';
  return {
    role,
    role_label: WORKSPACE_ROLE_LABELS[role] || role,
    membership_status: 'active',
    access_scope: scope,
    assigned_actor_id: member.assigned_actor_id || null,
    can_read_workspace: canReadWorkspace,
    can_read_entries: canReadWorkspace || ['own_entries', 'assigned_actor'].includes(scope),
    can_write_workspace: canWriteWorkspace,
    can_write_scoped_entries: role === 'employee' && ['own_entries', 'assigned_actor'].includes(scope),
    can_write: canWriteWorkspace,
    can_admin: canAdmin,
  };
}

function semanticMarkersFromRules(rules) {
  if (!Array.isArray(rules)) return [];
  const markers = [];
  for (const rule of rules) {
    if (rule && Array.isArray(rule.semantic_markers)) {
      markers.push(...rule.semantic_markers);
    }
  }
  return markers;
}

function classificationDecisionFromRules(rules) {
  if (!Array.isArray(rules)) return {};
  const last = [...rules].reverse().find((rule) => rule && typeof rule.classification_decision === 'object');
  return last ? last.classification_decision : {};
}

function accountingClassification(categoryCode, markers, rawText) {
  const text = String(rawText || '').toLowerCase();
  if (markers.some((marker) => marker && marker.marker === 'money_movement')) {
    return { section: 'lower_accounting', type: 'money_movement', label: 'Money movement / accountable' };
  }
  if (markers.some((marker) => marker && marker.marker === 'debt_or_return') && /долг|за[еёе]м|кредит|под отчет|подотчет/i.test(text)) {
    return { section: 'lower_accounting', type: 'debt_or_return', label: 'Debt / loan / return / accountable' };
  }
  if (categoryCode === 'guest_cash_issued') {
    return { section: 'lower_accounting', type: 'guest_cash_issued', label: 'Guest cash issued' };
  }
  return { section: 'operational', type: 'operational', label: 'Operational' };
}

function lowerAccountingSettlementEntry(rawText, direction, value, accounting, actorName) {
  if (accounting.section !== 'lower_accounting' || value === null) {
    return { counterparty: actorName || null, effect: 0, direction: null };
  }
  return {
    counterparty: actorName || null,
    effect: direction === 'out' ? value : -value,
    direction: direction === 'out' ? 'issued' : 'returned',
  };
}

function entryRow(row, lockMap = new Map()) {
  const matchedRules = decodeJson(row.matched_rules_json, []);
  const markers = semanticMarkersFromRules(matchedRules);
  const decision = classificationDecisionFromRules(matchedRules);
  const accounting = accountingClassification(row.category_code || null, markers, row.raw_text);
  const settlement = lowerAccountingSettlementEntry(
    row.raw_text,
    String(row.direction),
    amount(row.amount),
    accounting,
    row.actor_name || null
  );
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    flow: {
      id: String(row.flow_id),
      type: String(row.flow_type),
      name: String(row.flow_name),
    },
    date: String(row.date),
    raw_text: String(row.raw_text),
    sign: row.sign || null,
    amount: amount(row.amount),
    direction: String(row.direction),
    entry_type: String(row.entry_type),
    actor: row.actor_id ? { id: String(row.actor_id), name: String(row.actor_name || '') } : null,
    category_code: row.category_code || null,
    category_name: decodeJson(row.category_name_json, null),
    status: String(row.status),
    balance_after: amount(row.balance_after),
    source_type: String(row.source_type),
    source_id: row.source_id || null,
    source_row_id: row.source_row_id || null,
    notes: row.notes || null,
    confidence: amount(row.confidence),
    review_reason: decision.review_reason || null,
    matched_signals: decision.matched_signals || [],
    blockers: decision.blockers || [],
    classification_decision: decision,
    accounting_section: accounting.section,
    accounting_type: accounting.type,
    accounting_label: accounting.label,
    settlement_counterparty: settlement.counterparty,
    settlement_effect: settlement.effect,
    settlement_direction: settlement.direction,
    settlement_archive_exception: null,
    semantic_markers: markers,
    matched_rules: matchedRules,
    report_lock: lockMap.get(String(row.id)) || null,
    created_seq: row.created_seq === null || row.created_seq === undefined ? null : Number(row.created_seq),
    created_at: row.created_at || null,
    updated_at: row.updated_at || null,
  };
}

function monthClosureRow(row) {
  if (!row) return null;
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    year: Number(row.year),
    month: Number(row.month),
    opening_balance: amount(row.opening_balance),
    closing_balance: amount(row.closing_balance),
    is_closed: bool(row.is_closed),
    comment: row.comment === null || row.comment === undefined || row.comment === '' ? null : String(row.comment),
    closed_by: row.closed_by === null || row.closed_by === undefined ? null : Number(row.closed_by),
    closed_at: row.closed_at || null,
  };
}

function reportBatchRow(row) {
  const summary = decodeJson(row.summary_json, []);
  const entrySnapshot = decodeJson(row.entry_snapshot_json, []);
  const sourceEntryIds = decodeJson(row.source_entry_ids_json, []);
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    report_type: String(row.batch_type),
    batch_type: String(row.batch_type),
    title: String(row.title),
    status: String(row.status),
    period: { from: String(row.start_date), to: String(row.end_date) },
    start_date: String(row.start_date),
    end_date: String(row.end_date),
    from_entry_id: row.from_entry_id || null,
    to_entry_id: row.to_entry_id || null,
    entry_count: Number(row.entry_count || 0),
    entries_count: Number(row.entry_count || 0),
    generated_at: row.generated_at || null,
    closed_at: row.closed_at || null,
    html_filename: row.html_filename || null,
    html_url: `/v2-report.php?id=${encodeURIComponent(String(row.id))}`,
    summary,
    snapshot: summary,
    source_trace: decodeJson(row.source_trace_json, []),
    source_entry_ids: Array.isArray(sourceEntryIds) ? sourceEntryIds : [],
    entry_snapshot: entrySnapshot,
    entries: entrySnapshot,
    content_hash: String(row.content_hash),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
    updated_at: row.updated_at || null,
  };
}

function reportSnapshotRow(row) {
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    report_type: String(row.report_type),
    year: Number(row.year),
    month: Number(row.month),
    version: Number(row.version),
    status: String(row.status),
    generated_at: row.generated_at || null,
    stored_at: row.stored_at || null,
    closed_at: row.closed_at || null,
    comment: row.comment === null || row.comment === undefined ? null : String(row.comment),
    summary: decodeJson(row.summary_json, []),
    source_trace: decodeJson(row.source_trace_json, []),
    source_entry_ids: decodeJson(row.source_entry_ids_json, []),
    correction_ids: decodeJson(row.correction_ids_json, []),
    attachment_refs: decodeJson(row.attachment_refs_json, []),
    forecast_snapshot: decodeJson(row.forecast_snapshot_json, null),
    content_hash: String(row.content_hash),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
  };
}

function reportPackageRow(row) {
  const summary = decodeJson(row.summary_json, []);
  const fragmentIds = decodeJson(row.fragment_ids_json, []);
  const sourceEntryIds = decodeJson(row.source_entry_ids_json, []);
  const entryCount = Number(row.entry_count || (summary && summary.header && summary.header.entries_count) || 0);
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    report_type: 'operational_package',
    package_type: String(row.package_type || 'operational_fragment_package'),
    title: String(row.title),
    status: String(row.status),
    period: { from: String(row.start_date), to: String(row.end_date) },
    start_date: String(row.start_date),
    end_date: String(row.end_date),
    fragment_count: Number(row.fragment_count || 0),
    entry_count: entryCount,
    entries_count: entryCount,
    generated_at: row.generated_at || null,
    closed_at: row.closed_at || null,
    comment: row.comment === null || row.comment === undefined ? null : String(row.comment),
    html_filename: row.html_filename || null,
    html_url: `/v2-report.php?type=package&id=${encodeURIComponent(String(row.id))}`,
    summary,
    snapshot: summary,
    fragment_ids: Array.isArray(fragmentIds) ? fragmentIds : [],
    source_entry_ids: Array.isArray(sourceEntryIds) ? sourceEntryIds : [],
    content_hash: String(row.content_hash),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
    updated_at: row.updated_at || null,
  };
}

function reportBatchHtmlSnapshotRow(row, includeHtml = false) {
  const snapshot = {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    batch_id: String(row.batch_id),
    version: Number(row.version || 0),
    status: String(row.status),
    generated_at: row.generated_at || null,
    stored_at: row.stored_at || null,
    html_filename: row.html_filename || null,
    html_size_bytes: Number(row.html_size_bytes || 0),
    html_hash: String(row.html_hash || ''),
    source_batch_hash: String(row.source_batch_hash || ''),
    comment: row.comment === null || row.comment === undefined ? null : String(row.comment),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
  };
  if (includeHtml) snapshot.html_content = String(row.html_content || '');
  return snapshot;
}

function reportVersionRow(row) {
  const type = String(row.report_type);
  const reportId = String(row.report_id);
  const version = Number(row.version || 0);
  return {
    id: String(row.id),
    report_type: type,
    report_id: reportId,
    version,
    format: String(row.format),
    status: String(row.status),
    html_filename: row.html_filename || null,
    html_url: type === 'operational_package'
      ? `/v2-report.php?type=package&id=${encodeURIComponent(reportId)}&version=${version}`
      : `/v2-report.php?id=${encodeURIComponent(reportId)}&version=${version}`,
    content_hash: String(row.content_hash || ''),
    created_at: row.created_at || null,
  };
}

function workspaceInviteRow(row) {
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    token_hint: String(row.token_hint),
    invited_email: row.invited_email === null || row.invited_email === undefined ? null : String(row.invited_email),
    invited_name: row.invited_name === null || row.invited_name === undefined ? null : String(row.invited_name),
    role: String(row.role),
    role_label: WORKSPACE_ROLE_LABELS[String(row.role)] || String(row.role),
    access_scope: String(row.access_scope),
    status: String(row.status),
    expires_at: row.expires_at || null,
    accepted_at: row.accepted_at || null,
    accepted_by: row.accepted_by === null || row.accepted_by === undefined ? null : Number(row.accepted_by),
    revoked_at: row.revoked_at || null,
    revoked_by: row.revoked_by === null || row.revoked_by === undefined ? null : Number(row.revoked_by),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
  };
}

function workspaceInviteAuditPayload(invite) {
  const payload = { ...invite };
  delete payload.token;
  delete payload.url;
  return payload;
}

function workspaceInviteUrl(token) {
  return `/v2.php?invite=${encodeURIComponent(token)}`;
}

function accountableOfferRow(row) {
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    employee_user_id: row.employee_user_id === null || row.employee_user_id === undefined ? null : Number(row.employee_user_id),
    employee_email: String(row.employee_email || ''),
    amount: amount(row.amount) || 0,
    currency: String(row.currency),
    purpose: row.purpose === null || row.purpose === undefined ? null : String(row.purpose),
    status: String(row.status),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
    accepted_at: row.accepted_at || null,
    accepted_by: row.accepted_by === null || row.accepted_by === undefined ? null : Number(row.accepted_by),
    cancelled_at: row.cancelled_at || null,
    cancelled_by: row.cancelled_by === null || row.cancelled_by === undefined ? null : Number(row.cancelled_by),
    no_financial_mutation: row.no_financial_mutation === undefined ? true : bool(row.no_financial_mutation),
  };
}

function accountableReportDataRow(row) {
  return {
    id: String(row.id),
    report_id: String(row.report_id),
    row_number: Number(row.row_number || 0),
    expense_date: String(row.expense_date),
    description: String(row.description),
    amount: amount(row.amount) || 0,
    currency: String(row.currency),
    category_code: row.category_code === null || row.category_code === undefined ? null : String(row.category_code),
    notes: row.notes === null || row.notes === undefined ? null : String(row.notes),
    review_status: row.review_status || 'pending_review',
    accepted_amount: row.accepted_amount === null || row.accepted_amount === undefined ? null : amount(row.accepted_amount),
    accepted_category_code: row.accepted_category_code === null || row.accepted_category_code === undefined ? null : String(row.accepted_category_code),
    payment_method: row.payment_method || null,
    review_note: row.review_note === null || row.review_note === undefined ? null : String(row.review_note),
    operational_entry_id: row.operational_entry_id === null || row.operational_entry_id === undefined ? null : String(row.operational_entry_id),
  };
}

function accountableSettlementRow(row) {
  if (!row) return null;
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    offer_id: String(row.offer_id),
    report_id: String(row.report_id),
    employee_user_id: Number(row.employee_user_id),
    issued_amount: amount(row.issued_amount) || 0,
    accepted_cash_expenses: amount(row.accepted_cash_expenses) || 0,
    accepted_noncash_expenses: amount(row.accepted_noncash_expenses) || 0,
    expected_remaining: amount(row.expected_remaining) || 0,
    actual_remaining: amount(row.actual_remaining) || 0,
    return_due_amount: amount(row.return_due_amount) || 0,
    reimburse_due_amount: amount(row.reimburse_due_amount) || 0,
    difference_amount: amount(row.difference_amount) || 0,
    status: String(row.status),
    resolution_status: row.resolution_status || 'open',
    resolved_amount: amount(row.resolved_amount) || 0,
    resolved_entry_id: row.resolved_entry_id || null,
    resolved_at: row.resolved_at || null,
    resolved_by: row.resolved_by === null || row.resolved_by === undefined ? null : Number(row.resolved_by),
    resolution_note: row.resolution_note || null,
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
    updated_at: row.updated_at || null,
  };
}

function accountableReportRow(row, rows = null, settlement = undefined) {
  const report = {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    offer_id: String(row.offer_id),
    employee_user_id: Number(row.employee_user_id),
    title: String(row.title),
    status: String(row.status),
    currency: String(row.currency),
    total_amount: amount(row.total_amount) || 0,
    row_count: Number(row.row_count || 0),
    submitted_at: row.submitted_at || null,
    submitted_by: row.submitted_by === null || row.submitted_by === undefined ? null : Number(row.submitted_by),
    reviewed_at: row.reviewed_at || null,
    reviewed_by: row.reviewed_by === null || row.reviewed_by === undefined ? null : Number(row.reviewed_by),
    review_note: row.review_note || null,
    accepted_total_amount: amount(row.accepted_total_amount) || 0,
    rejected_total_amount: amount(row.rejected_total_amount) || 0,
    accepted_cash_expenses: amount(row.accepted_cash_expenses) || 0,
    accepted_noncash_expenses: amount(row.accepted_noncash_expenses) || 0,
    settlement_status: row.settlement_status || null,
    materialized_at: row.materialized_at || null,
    ledger_materialization_status: row.ledger_materialization_status || 'not_materialized',
    ledger_materialized_at: row.ledger_materialized_at || null,
    ledger_materialized_by: row.ledger_materialized_by === null || row.ledger_materialized_by === undefined ? null : Number(row.ledger_materialized_by),
    ledger_materialization_hash: row.ledger_materialization_hash || null,
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
    updated_at: row.updated_at || null,
    no_financial_mutation: row.no_financial_mutation === undefined ? true : bool(row.no_financial_mutation),
  };
  if (Array.isArray(rows)) report.rows = rows;
  if (settlement !== undefined) report.settlement = settlement;
  return report;
}

function accountableDashboardOfferRow(offer) {
  return {
    id: String(offer.id),
    employee_user_id: offer.employee_user_id,
    amount: amount(offer.amount) || 0,
    currency: String(offer.currency),
    purpose: offer.purpose,
    status: String(offer.status),
    created_at: offer.created_at,
    accepted_at: offer.accepted_at,
    no_financial_mutation: bool(offer.no_financial_mutation),
  };
}

function accountableDashboardReportRow(report) {
  const settlement = report.settlement || null;
  return {
    id: String(report.id),
    offer_id: String(report.offer_id),
    employee_user_id: Number(report.employee_user_id),
    title: String(report.title),
    status: String(report.status),
    currency: String(report.currency),
    total_amount: amount(report.total_amount) || 0,
    row_count: Number(report.row_count || 0),
    submitted_at: report.submitted_at,
    reviewed_at: report.reviewed_at,
    accepted_total_amount: amount(report.accepted_total_amount) || 0,
    rejected_total_amount: amount(report.rejected_total_amount) || 0,
    accepted_cash_expenses: amount(report.accepted_cash_expenses) || 0,
    accepted_noncash_expenses: amount(report.accepted_noncash_expenses) || 0,
    settlement_status: report.settlement_status,
    ledger_materialization_status: report.ledger_materialization_status || 'not_materialized',
    ledger_materialized_at: report.ledger_materialized_at,
    settlement: settlement === null ? null : {
      issued_amount: settlement.issued_amount,
      expected_remaining: settlement.expected_remaining,
      return_due_amount: settlement.return_due_amount,
      reimburse_due_amount: settlement.reimburse_due_amount,
      status: settlement.status,
      resolution_status: settlement.resolution_status || 'open',
      resolved_amount: settlement.resolved_amount || 0,
      resolved_entry_id: settlement.resolved_entry_id || null,
      resolved_at: settlement.resolved_at || null,
    },
    no_financial_mutation: bool(report.no_financial_mutation),
  };
}

function accountableEmployeeKey(employeeUserId, employeeEmail) {
  if (employeeUserId !== null && employeeUserId !== undefined && Number(employeeUserId) > 0) return `user:${Number(employeeUserId)}`;
  const email = String(employeeEmail || '').trim().toLowerCase();
  return email ? `email:${email}` : 'unknown';
}

function emptyAccountableDashboardEmployee(employeeUserId, employeeEmail, currency) {
  const email = String(employeeEmail || '').trim().toLowerCase();
  return {
    employee_user_id: employeeUserId === null || employeeUserId === undefined ? null : Number(employeeUserId),
    employee_label: email || 'Сотрудник не указан',
    currency,
    offer_count: 0,
    report_count: 0,
    metrics: {
      pending_offer_total: 0,
      issued_total: 0,
      submitted_report_total: 0,
      accepted_report_total: 0,
      accepted_cash_expenses_total: 0,
      accepted_noncash_expenses_total: 0,
      not_materialized_total: 0,
      materialized_total: 0,
      return_due_total: 0,
      reimburse_due_total: 0,
      return_due_gross_total: 0,
      reimburse_due_gross_total: 0,
      settled_return_total: 0,
      settled_reimburse_total: 0,
      open_position_total: 0,
      submitted_report_count: 0,
      accepted_report_count: 0,
      not_materialized_report_count: 0,
      materialized_report_count: 0,
    },
    offers: [],
    reports: [],
  };
}

function attachmentRow(row) {
  return {
    id: String(row.id),
    entry_id: String(row.entry_id),
    file_name: String(row.file_name),
    file_url: String(row.file_url),
    mime_type: row.mime_type || null,
    size_bytes: row.size_bytes === null || row.size_bytes === undefined ? null : Number(row.size_bytes),
    image_mode: row.image_mode || null,
    created_at: row.created_at || null,
  };
}

function cleanAttachmentFileName(fileName) {
  const value = String(fileName || '').trim();
  if (value === '' || /[\/\\\x00-\x1F\x7F]/u.test(value)) {
    const error = new Error('invalid_file_name');
    error.status = 422;
    throw error;
  }
  return value.slice(0, 255);
}

function detectAttachmentMime(content) {
  if (content.length >= 8 && content.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'image/png';
  if (content.length >= 3 && content[0] === 0xff && content[1] === 0xd8 && content[2] === 0xff) return 'image/jpeg';
  if (content.length >= 5 && content.subarray(0, 5).toString('ascii') === '%PDF-') return 'application/pdf';
  if (content.length >= 12 && content.subarray(0, 4).toString('ascii') === 'RIFF' && content.subarray(8, 12).toString('ascii') === 'WEBP') return 'image/webp';
  const error = new Error('unsupported_attachment_type');
  error.status = 422;
  throw error;
}

function decodeAttachmentContent(input) {
  const rawEncoded = String(input.content_base64 || '').trim();
  if (rawEncoded === '') {
    const error = new Error('missing_content_base64');
    error.status = 422;
    throw error;
  }
  const encoded = rawEncoded.replace(/\s+/gu, '');
  if (encoded === '' || encoded.includes(',')) {
    const error = new Error('invalid_content_base64');
    error.status = 422;
    throw error;
  }
  if (encoded.length > Math.ceil(ATTACHMENT_MAX_BYTES * 1.4) + 16) {
    const error = new Error('attachment_too_large');
    error.status = 413;
    throw error;
  }
  const content = Buffer.from(encoded, 'base64');
  if (content.toString('base64').replace(/=+$/u, '') !== encoded.replace(/=+$/u, '')) {
    const error = new Error('invalid_content_base64');
    error.status = 422;
    throw error;
  }
  if (content.length <= 0) {
    const error = new Error('empty_attachment');
    error.status = 422;
    throw error;
  }
  if (content.length > ATTACHMENT_MAX_BYTES) {
    const error = new Error('attachment_too_large');
    error.status = 413;
    throw error;
  }
  return content;
}

function normalizeAttachmentPayload(input) {
  const fileName = cleanAttachmentFileName(requireStringInput(input, 'file_name', 255));
  const content = decodeAttachmentContent(input);
  const mimeType = detectAttachmentMime(content);
  const imageMode = Object.hasOwn(input, 'image_mode')
    ? enumInput(optionalStringInput(input, 'image_mode', null, 40), ['original', 'compressed', 'grayscale_scan'], 'image_mode')
    : null;
  return {
    file_name: fileName,
    content,
    mime_type: mimeType,
    size_bytes: content.length,
    image_mode: imageMode,
  };
}

function attachmentWritePath(relativePath) {
  if (!String(relativePath).startsWith('storage/v2/attachments/')) {
    const error = new Error('invalid_attachment_path');
    error.status = 500;
    throw error;
  }
  const absolutePath = path.join(ROOT, relativePath);
  const storageRoot = path.join(ROOT, 'storage', 'v2', 'attachments');
  const resolved = path.resolve(absolutePath);
  if (!resolved.startsWith(`${path.resolve(storageRoot)}${path.sep}`)) {
    const error = new Error('invalid_attachment_path');
    error.status = 500;
    throw error;
  }
  fs.mkdirSync(path.dirname(resolved), { recursive: true, mode: 0o775 });
  return resolved;
}

function deleteAttachmentFile(relativePath) {
  if (!String(relativePath).startsWith('storage/v2/attachments/')) {
    const error = new Error('invalid_attachment_path');
    error.status = 500;
    throw error;
  }
  const storageRoot = path.resolve(ROOT, 'storage', 'v2', 'attachments');
  const absolutePath = path.resolve(ROOT, relativePath);
  if (!absolutePath.startsWith(`${storageRoot}${path.sep}`)) {
    const error = new Error('invalid_attachment_path');
    error.status = 500;
    throw error;
  }
  if (!fs.existsSync(absolutePath)) return false;
  fs.unlinkSync(absolutePath);
  return true;
}

function dictionaryTrainingDecisionRow(row) {
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    archive_workspace_id: row.archive_workspace_id || null,
    source_id: row.source_id || null,
    source_row_id: row.source_row_id || null,
    decision_scope: String(row.decision_scope),
    group_key: row.group_key || null,
    source_row_ids: decodeJson(row.source_row_ids_json, []),
    decision_type: String(row.decision_type),
    current_rule_guess: row.current_rule_guess || null,
    target_category_code: row.category_code || null,
    category_rule_id: row.category_rule_id || null,
    pattern: row.pattern || null,
    pattern_type: row.pattern_type || null,
    language: String(row.language),
    weight: row.weight === null || row.weight === undefined ? null : Number(row.weight),
    negative_weight: row.negative_weight === null || row.negative_weight === undefined ? null : Number(row.negative_weight),
    requires_any: decodeJson(row.requires_any_json, []),
    excludes_any: decodeJson(row.excludes_any_json, []),
    confidence: row.confidence === null || row.confidence === undefined ? null : amount(row.confidence),
    review_reason: row.review_reason || null,
    blockers: decodeJson(row.blockers_json, []),
    matched_signals: decodeJson(row.matched_signals_json, []),
    semantic_markers: decodeJson(row.semantic_markers_json, []),
    source_snapshot: decodeJson(row.example_snapshot_json, {}),
    note: row.note || null,
    decided_by: row.decided_by === null || row.decided_by === undefined ? null : Number(row.decided_by),
    decided_at: row.decided_at || null,
    updated_at: row.updated_at || null,
  };
}

function workspaceAssistantSettingsDefaults(workspaceId) {
  return {
    workspace_id: workspaceId,
    mr_smith_enabled: false,
    internet_reference_mode: 'per_request',
    provider_key: 'stub',
    retention_days: 30,
    updated_by: null,
    created_at: null,
    updated_at: null,
  };
}

function workspaceAssistantSettingsRow(row, workspaceId) {
  if (!row) return workspaceAssistantSettingsDefaults(workspaceId);
  return {
    workspace_id: String(row.workspace_id),
    mr_smith_enabled: bool(row.mr_smith_enabled),
    internet_reference_mode: String(row.internet_reference_mode),
    provider_key: String(row.provider_key),
    retention_days: Number(row.retention_days || 30),
    updated_by: row.updated_by === null || row.updated_by === undefined ? null : Number(row.updated_by),
    created_at: row.created_at || null,
    updated_at: row.updated_at || null,
  };
}

function dictionaryInternetReferenceLookupRow(row) {
  return {
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    source_row_id: row.source_row_id === null || row.source_row_id === undefined ? null : String(row.source_row_id),
    provider_key: String(row.provider_key),
    provider_request_id: row.provider_request_id === null || row.provider_request_id === undefined ? null : String(row.provider_request_id),
    consent_source: String(row.consent_source),
    sanitized_query: String(row.sanitized_query || ''),
    query_hash: String(row.query_hash || ''),
    masked_fields: decodeJson(row.masked_fields_json, []),
    result_status: String(row.result_status),
    latency_ms: Number(row.latency_ms || 0),
    matches: decodeJson(row.matches_json, []),
    selected_match: decodeJson(row.selected_match_json, null),
    no_financial_mutation: bool(row.no_financial_mutation),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
    retention_delete_after: row.retention_delete_after || null,
  };
}

function cashBalanceDelta(entry) {
  if (entry.amount === null || entry.amount === undefined || !COUNTED_STATUSES.has(String(entry.status))) return null;
  const value = amount(entry.amount);
  if (String(entry.direction) === 'in' && ['cash_income', 'correction'].includes(String(entry.entry_type))) return value;
  if (String(entry.direction) === 'out' && ['cash_expense', 'correction'].includes(String(entry.entry_type))) return -value;
  return null;
}

function monthEndExclusive(year, month) {
  const date = new Date(Date.UTC(year, month, 1));
  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-01`;
}

async function requireWorkspace(database, workspaceId, userId = USER_ID, options = {}) {
  const [workspace, member] = await Promise.all([
    database.collection('v2_workspaces').findOne({ id: workspaceId, archived_at: null }, options),
    database.collection('v2_workspace_members').findOne({ workspace_id: workspaceId, user_id: userId }, options),
  ]);
  if (!workspace || !member) {
    const error = new Error('workspace_not_found');
    error.status = 404;
    throw error;
  }
  return { workspace, member };
}

async function workspaceAccess(database, workspaceId, userId = USER_ID, options = {}) {
  const { member } = await requireWorkspace(database, workspaceId, userId, options);
  return workspaceAccessFromMember(member);
}

async function requireWorkspaceAdmin(database, workspaceId, userId = USER_ID, options = {}) {
  const access = await workspaceAccess(database, workspaceId, userId, options);
  if (!access.can_admin) {
    const error = new Error('workspace_admin_required');
    error.status = 403;
    throw error;
  }
  return access;
}

async function requireWorkspaceWriter(database, workspaceId, userId = USER_ID, options = {}) {
  const access = await workspaceAccess(database, workspaceId, userId, options);
  if (!access.can_write_workspace) {
    const error = new Error('workspace_read_only');
    error.status = 403;
    throw error;
  }
  return access;
}

async function requireWorkspaceFullReader(database, workspaceId, userId = USER_ID, options = {}) {
  const { workspace, member } = await requireWorkspace(database, workspaceId, userId, options);
  const access = workspaceAccessFromMember(member);
  if (!access.can_read_workspace) {
    const error = new Error('workspace_reader_required');
    error.status = 403;
    throw error;
  }
  return { workspace, member, access };
}

async function dictionaryArchiveWorkspace(database, workspaceId, userId = USER_ID, options = {}) {
  const { workspace } = await requireWorkspaceFullReader(database, workspaceId, userId, options);
  const name = String(workspace.name || '');
  if (name.endsWith(' Archive Raw History')) return workspaceRow(workspace);

  const archive = await database.collection('v2_workspaces').findOne({
    name: `${name} Archive Raw History`,
    archived_at: null,
  }, options);
  if (!archive) return workspaceRow(workspace);

  const member = await database.collection('v2_workspace_members').findOne({
    workspace_id: archive.id,
    user_id: userId,
  }, options);
  return member ? workspaceRow(archive) : workspaceRow(workspace);
}

function normalizedRuleText(text) {
  return String(text || '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function dictionaryDescription(raw) {
  return String(raw['описание платежа'] ?? raw.description ?? raw['описание'] ?? '').trim();
}

function dictionaryAmount(value) {
  const text = String(value ?? '').trim();
  if (text === '') return null;
  const normalized = text.replace(/[ \u00a0]/g, '').replace(',', '.');
  return /^-?\d+(?:\.\d+)?$/.test(normalized) ? Math.abs(Number(normalized)) : null;
}

function dictionaryMoney(raw) {
  const amounts = [
    { flow_type: 'cash', sign: '+', amount: dictionaryAmount(raw['приход кеш'] ?? raw['приход кэш'] ?? raw['cash income'] ?? raw['приход']) },
    { flow_type: 'cash', sign: '-', amount: dictionaryAmount(raw['расход кеш'] ?? raw['расход кэш'] ?? raw['cash expense'] ?? raw['расход']) },
    { flow_type: 'card', sign: '+', amount: dictionaryAmount(raw['приход карта'] ?? raw['приход карты'] ?? raw['card income']) },
    { flow_type: 'card', sign: '-', amount: dictionaryAmount(raw['расход карта'] ?? raw['расход карты'] ?? raw['card expense']) },
  ].filter((item) => item.amount !== null && Math.abs(item.amount) > 0.0001);
  return amounts.length === 1 ? amounts[0] : null;
}

function dictionaryCategoryGuess(description, flowType, sign) {
  const text = normalizedRuleText(description);
  if (/цоги\s*мар|цогимар|cogimar/u.test(text)) return { category_code: null, pattern: null };
  for (const [code, pattern] of DICTIONARY_CATEGORY_RULES) {
    if (!pattern.test(text)) continue;
    if (code === 'fuel' && /авто|машин|car/u.test(text)) continue;
    if (sign === '+' && !['commercial_income', 'cash_topup_from_card'].includes(code)) continue;
    if (flowType === 'card' && sign === '+' && code !== 'cash_topup_from_card') continue;
    return { category_code: code, pattern: String(pattern) };
  }
  if (
    flowType === 'cash'
    && sign === '+'
    && !/под ?отчет|подотчет|пот отчет|accountable/u.test(text)
    && !/аренд|чартер|charter|сдач[аеи]?[^,.;]*яхт/u.test(text)
  ) {
    return { category_code: 'non_commercial_income', pattern: 'non_commercial_income' };
  }
  return { category_code: null, pattern: null };
}

function dictionarySemanticMarkers(description, categoryCode) {
  const text = normalizedRuleText(description);
  const markers = [];
  const push = (marker, label) => markers.push({ marker, label, source: 'atlas_read_inference' });
  if (/сейф|safe/u.test(text)) push('cash_location_safe', 'Safe / cash location');
  if (/евгени|волод|жен[яи]|экипаж|капитан|шеф|сотрудник|crew/u.test(text)) push('actor_context', 'Actor / source context');
  if (/пополн|внес|дал денег|owner|служебн[а-я]* карт/u.test(text)) push('owner_funding', 'Owner funding');
  if (/снял с карты|перелож|обезнал|перевод|возврат|вернул|вернула|остаток/u.test(text)) push('money_movement', 'Money movement / private settlement');
  if (/долг|за[еёе]м|займ|кредит|под ?отчет|подотчет|пот отчет/u.test(text)) push('debt_or_return', 'Debt / loan / credit');
  if (/для себя|порше|porsche|личн|для рф|катер рф/u.test(text)) push('non_yacht_or_personal', 'Non-yacht / personal context');
  if (categoryCode === 'commercial_income') push('commercial_income_allowed', 'Commercial income allowed');
  if (/тендер|тузик|dinghy/u.test(text)) push('tender_related', 'Tender related');
  if (!categoryCode && text !== '') push('weak_dictionary_context', 'Weak dictionary context');
  return markers;
}

function semanticMarkerArrayHas(markers, marker) {
  return markers.some((item) => item && item.marker === marker);
}

function dictionaryNeedsReviewOverride(description) {
  const text = normalizedRuleText(description);
  if (text === '') return false;
  return /цоги\s*мар|цогимар|cogimar|долг|возврат|вернул|под ?отчет|подотчет|пот отчет|кредит|займ|заем|рассрочк|порше|porsche|для рф|катер рф|для себя|brokerage|agency fee|сим[^,.;]*фрукт|фрукт[^,.;]*сим|тендер[^,.;]*остаток|остаток[^,.;]*тендер|айфон|iphone|планшет|обезналич|консьерж|книжк[а-я]* моряка|подставк[а-я]* под динги/u.test(text);
}

function dictionaryReviewGroup(markers, categoryCode, needsReview) {
  const markerLabels = {
    cash_location_safe: 'Safe / cash location',
    actor_context: 'Actor / source context',
    owner_funding: 'Owner funding',
    money_movement: 'Money movement / private settlement',
    debt_or_return: 'Debt / loan / credit',
    non_yacht_or_personal: 'Non-yacht / personal context',
    commercial_income_allowed: 'Commercial income allowed',
    tender_related: 'Tender related',
    weak_dictionary_context: 'Weak dictionary context',
    mixed_dictionary_context: 'Mixed dictionary context',
  };
  for (const marker of Object.keys(markerLabels)) {
    if (semanticMarkerArrayHas(markers, marker)) {
      return {
        key: `semantic:${marker}`,
        kind: 'semantic',
        label: markerLabels[marker],
        semantic_markers: [marker],
        current_rule_guess: categoryCode,
        needs_review: needsReview,
      };
    }
  }
  if (categoryCode) {
    return {
      key: `category:${categoryCode}`,
      kind: 'category_guess',
      label: `Category guess: ${categoryCode}`,
      semantic_markers: [],
      current_rule_guess: categoryCode,
      needs_review: needsReview,
    };
  }
  return {
    key: 'review:needs_review',
    kind: 'review',
    label: 'Needs review',
    semantic_markers: [],
    current_rule_guess: null,
    needs_review: true,
  };
}

function dictionaryReviewExample(row, raw, description, money, guess, markers) {
  return {
    description,
    flow_type: money ? money.flow_type : null,
    sign: money ? money.sign : null,
    amount: money ? money.amount : null,
    parse_status: String(row.parse_status || ''),
    parse_notes: row.parse_notes || null,
    date_context: raw._date_context || null,
    current_rule_guess: guess.category_code || null,
    matched_pattern: guess.pattern || null,
    semantic_markers: markers,
    confidence: guess.category_code ? 0.7 : null,
    review_reason: guess.category_code ? null : 'other_review',
    matched_signals: guess.category_code ? [{ type: 'category', category_code: guess.category_code, pattern: guess.pattern }] : [],
    blockers: [],
    classification_decision: null,
    source: {
      source_id: String(row.source_id),
      source_row_id: String(row.source_row_id),
      file_name: String(row.file_name || ''),
      sheet_name: String(row.sheet_name || ''),
      row_number: Number(row.row_number || 0),
    },
    raw,
  };
}

async function dictionaryReviewQueue(database, workspaceId, query) {
  const sourceWorkspace = (await requireWorkspaceFullReader(database, workspaceId)).workspace;
  const archiveWorkspace = await dictionaryArchiveWorkspace(database, workspaceId);
  const exampleLimit = clampInt(query.examples, 4, 1, 10);
  const groupLimit = clampInt(query.limit, 120, 1, 500);
  const needsReviewOnly = String(query.needs_review || '') === '1';
  const sources = await database.collection('v2_import_sources')
    .find({ workspace_id: archiveWorkspace.id, include_decision: 'included' })
    .project({ id: 1, file_name: 1 })
    .sort({ file_name: 1, created_at: 1 })
    .toArray();
  const sourceMap = new Map(sources.map((source) => [String(source.id), source]));
  const rows = sourceMap.size ? await database.collection('v2_import_rows')
    .find({ import_source_id: { $in: Array.from(sourceMap.keys()) } })
    .sort({ sheet_name: 1, row_number: 1 })
    .toArray() : [];

  const groups = new Map();
  let rowsWithMoney = 0;
  let rowsNeedsReview = 0;
  for (const row of rows) {
    const source = sourceMap.get(String(row.import_source_id)) || {};
    const raw = decodeJson(row.raw_json, {});
    const description = dictionaryDescription(raw);
    const money = dictionaryMoney(raw);
    const guess = money ? dictionaryCategoryGuess(description, money.flow_type, money.sign) : { category_code: null, pattern: null };
    const markers = money ? dictionarySemanticMarkers(description, guess.category_code) : [];
    const hasDictionaryReviewMarker = semanticMarkerArrayHas(markers, 'weak_dictionary_context')
      || semanticMarkerArrayHas(markers, 'mixed_dictionary_context');
    const needsReview = description === ''
      || money === null
      || guess.category_code === null
      || String(row.parse_status) === 'unrecognized'
      || hasDictionaryReviewMarker
      || dictionaryNeedsReviewOverride(description);
    if (money) rowsWithMoney += 1;
    if (needsReview) rowsNeedsReview += 1;
    if (needsReviewOnly && !needsReview) continue;

    const baseGroup = dictionaryReviewGroup(markers, guess.category_code, needsReview);
    if (!groups.has(baseGroup.key)) {
      groups.set(baseGroup.key, {
        ...baseGroup,
        count: 0,
        amount_abs_total: 0,
        cash_count: 0,
        card_count: 0,
        income_count: 0,
        expense_count: 0,
        examples: [],
      });
    }
    const group = groups.get(baseGroup.key);
    group.count += 1;
    group.needs_review = Boolean(group.needs_review || needsReview);
    if (money) {
      group.amount_abs_total += money.amount;
      group[`${money.flow_type}_count`] += 1;
      group[money.sign === '+' ? 'income_count' : 'expense_count'] += 1;
    }
    if (group.examples.length < exampleLimit) {
      group.examples.push(dictionaryReviewExample({
        ...row,
        source_id: source.id,
        source_row_id: row.id,
        file_name: source.file_name,
      }, raw, description, money, guess, markers));
    }
  }

  const sortedGroups = Array.from(groups.values())
    .sort((left, right) => (right.count - left.count)
      || (right.amount_abs_total - left.amount_abs_total)
      || String(left.label).localeCompare(String(right.label)))
    .slice(0, groupLimit);
  return {
    workspace_id: archiveWorkspace.id,
    workspace_name: archiveWorkspace.name,
    source_workspace_id: String(sourceWorkspace.id),
    source_workspace_name: String(sourceWorkspace.name),
    generated_at: new Date().toISOString(),
    purpose: 'Read-only dictionary training queue. Does not create operational entries.',
    note: 'Amounts are review metadata only and are not used as finance-report totals.',
    rows_total: rows.length,
    rows_with_money: rowsWithMoney,
    rows_needs_review: rowsNeedsReview,
    groups_total: sortedGroups.length,
    groups: sortedGroups,
  };
}

async function rawHistory(database, workspaceId, query) {
  const sourceWorkspace = (await requireWorkspaceFullReader(database, workspaceId)).workspace;
  const archiveWorkspace = await dictionaryArchiveWorkspace(database, workspaceId);
  const sourceLimit = clampInt(query.sources, 80, 1, 200);
  const sampleLimit = clampInt(query.samples, 3, 1, 10);
  const sourcesTotal = await database.collection('v2_import_sources').countDocuments({
    workspace_id: archiveWorkspace.id,
    include_decision: 'included',
  });
  const sources = await database.collection('v2_import_sources')
    .find({ workspace_id: archiveWorkspace.id, include_decision: 'included' })
    .sort({ file_name: 1, created_at: 1 })
    .limit(sourceLimit)
    .toArray();
  const sourceIds = sources.map((source) => String(source.id));
  const allIncludedSourceIds = (await database.collection('v2_import_sources')
    .find({ workspace_id: archiveWorkspace.id, include_decision: 'included' })
    .project({ id: 1 })
    .toArray()).map((source) => String(source.id));
  const allRows = sourceIds.length ? await database.collection('v2_import_rows')
    .find({ import_source_id: { $in: sourceIds } })
    .sort({ row_number: 1, id: 1 })
    .toArray() : [];
  const rowsBySource = new Map();
  for (const row of allRows) {
    const key = String(row.import_source_id);
    if (!rowsBySource.has(key)) rowsBySource.set(key, []);
    rowsBySource.get(key).push(row);
  }
  const rowsTotal = allIncludedSourceIds.length
    ? await database.collection('v2_import_rows').countDocuments({ import_source_id: { $in: allIncludedSourceIds } })
    : 0;
  return {
    workspace_id: archiveWorkspace.id,
    workspace_name: archiveWorkspace.name,
    source_workspace_id: String(sourceWorkspace.id),
    source_workspace_name: String(sourceWorkspace.name),
    purpose: 'Read-only imported raw history for user review and dictionary training.',
    sources_total: sourcesTotal,
    rows_total: rowsTotal,
    sources: sources.map((source) => {
      const rows = rowsBySource.get(String(source.id)) || [];
      const samples = rows.slice(0, sampleLimit).map((row) => {
        const raw = decodeJson(row.raw_json, {});
        const money = dictionaryMoney(raw);
        return {
          source_row_id: String(row.id),
          sheet_name: row.sheet_name === null || row.sheet_name === undefined ? null : String(row.sheet_name),
          row_number: row.row_number === null || row.row_number === undefined ? null : Number(row.row_number),
          description: dictionaryDescription(raw),
          flow_type: money ? money.flow_type : null,
          sign: money ? money.sign : null,
          amount: money ? money.amount : null,
          parse_status: String(row.parse_status),
          parse_notes: row.parse_notes === null || row.parse_notes === undefined ? null : String(row.parse_notes),
        };
      });
      return {
        id: String(source.id),
        file_name: source.file_name === null || source.file_name === undefined ? null : String(source.file_name),
        status: String(source.status),
        include_decision: String(source.include_decision),
        reason: source.reason === null || source.reason === undefined ? null : String(source.reason),
        created_at: String(source.created_at),
        row_count: rows.length,
        first_row: rows.length ? Number(rows[0].row_number) : null,
        last_row: rows.length ? Number(rows[rows.length - 1].row_number) : null,
        samples,
      };
    }),
  };
}

async function convertRawHistoryBatch(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const { workspace } = await requireWorkspace(database, workspaceId, userId, { session });
      await requireWorkspaceWriter(database, workspaceId, userId, { session });
      const archiveWorkspace = await dictionaryArchiveWorkspace(database, workspaceId, userId, { session });
      if (String(archiveWorkspace.id) === workspaceId) {
        const error = new Error('raw_history_requires_operational_workspace');
        error.status = 422;
        throw error;
      }
      const mode = enumInput(optionalStringInput(input, 'mode', 'preview', 20) || 'preview', ['preview', 'commit'], 'mode');
      const limit = clampInt(input.limit, 25, 1, 100);
      const sourceId = optionalStringInput(input, 'source_id', null, 36);
      const flows = await flowsByType(database, workspaceId, userId, { session });
      const seen = await existingLegacyEntryKeys(database, workspaceId, { session });
      const sourceFilter = {
        workspace_id: archiveWorkspace.id,
        include_decision: 'included',
      };
      if (sourceId !== null) sourceFilter.id = sourceId;
      const sources = await database.collection('v2_import_sources')
        .find(sourceFilter, { session })
        .sort({ file_name: 1, created_at: 1 })
        .toArray();
      const sourceIds = sources.map((source) => String(source.id));
      const sourceMap = new Map(sources.map((source) => [String(source.id), source]));
      const rows = sourceIds.length
        ? await database.collection('v2_import_rows').find({
          import_source_id: { $in: sourceIds },
          entry_id: null,
          $or: [
            { parse_status: null },
            { parse_status: { $nin: ['imported', 'duplicate_suspect', 'unrecognized', 'ignored', 'summary_ignored'] } },
          ],
        }, { session }).toArray()
        : [];
      rows.sort((left, right) => {
        const leftSource = sourceMap.get(String(left.import_source_id)) || {};
        const rightSource = sourceMap.get(String(right.import_source_id)) || {};
        return String(leftSource.file_name || '').localeCompare(String(rightSource.file_name || ''))
          || String(left.sheet_name || '').localeCompare(String(right.sheet_name || ''))
          || Number(left.row_number || 0) - Number(right.row_number || 0);
      });

      result = {
        mode,
        workspace_id: workspaceId,
        workspace_name: String(workspace.name),
        archive_workspace_id: String(archiveWorkspace.id),
        archive_workspace_name: String(archiveWorkspace.name),
        limit,
        scanned: 0,
        convertible: 0,
        converted: 0,
        duplicates: 0,
        unrecognized: 0,
        skipped: 0,
        rows: [],
      };

      for (const row of rows.slice(0, limit)) {
        const source = sourceMap.get(String(row.import_source_id)) || {};
        result.scanned += 1;
        const raw = decodeJson(row.raw_json, {});
        const parsed = parseLegacyImportRow(raw && typeof raw === 'object' ? raw : {}, row, seen);
        const entry = parsed.entry;
        const rowResult = {
          source_id: String(source.id),
          source_row_id: String(row.id),
          file_name: String(source.file_name || ''),
          sheet_name: row.sheet_name === null || row.sheet_name === undefined ? null : String(row.sheet_name),
          row_number: row.row_number === null || row.row_number === undefined ? null : Number(row.row_number),
          parse_status: String(parsed.parse_status),
          parse_notes: parsed.parse_notes,
          duplicate_suspect: Boolean(parsed.duplicate_suspect),
          entry_preview: entry,
          entry_id: null,
        };

        if (entry === null) {
          if (String(parsed.parse_status) === 'unrecognized') result.unrecognized += 1;
          else result.skipped += 1;
          if (mode === 'commit') {
            await updateLegacyImportRowStatus(database, String(row.id), String(parsed.parse_status), null, parsed.parse_notes, { session });
          }
          result.rows.push(rowResult);
          continue;
        }

        if (parsed.duplicate_suspect) {
          result.duplicates += 1;
          if (mode === 'commit') {
            await updateLegacyImportRowStatus(database, String(row.id), 'duplicate_suspect', null, parsed.parse_notes || 'duplicate suspect', { session });
          }
          result.rows.push(rowResult);
          continue;
        }

        const flow = flows[entry.flow_type] || null;
        if (flow === null) {
          result.unrecognized += 1;
          rowResult.parse_status = 'unrecognized';
          rowResult.parse_notes = 'missing flow';
          if (mode === 'commit') {
            await updateLegacyImportRowStatus(database, String(row.id), 'unrecognized', null, 'missing flow', { session });
          }
          result.rows.push(rowResult);
          continue;
        }

        result.convertible += 1;
        if (mode === 'commit') {
          const created = await createEntryInSession(database, workspaceId, {
            flow_id: flow.id,
            date: entry.date,
            raw_text: entry.raw_text,
            amount: Number(entry.amount).toFixed(2),
            category_code: entry.category_code,
            status: 'imported',
            source_type: 'import',
            source_id: String(source.id),
            source_row_id: String(row.id),
            closed_month_decision: 'recalculate_chain',
            matched_rules: [{
              source: 'raw_history_gradual_conversion',
              archive_workspace_id: String(archiveWorkspace.id),
              file_name: String(source.file_name || ''),
              sheet_name: row.sheet_name || null,
              row_number: Number(row.row_number || 0),
            }],
          }, userId, session);
          rowResult.entry_id = String(created.id);
          await updateLegacyImportRowStatus(database, String(row.id), 'imported', String(created.id), parsed.parse_notes, { session });
          result.converted += 1;
        }
        result.rows.push(rowResult);
      }

      if (mode === 'commit' && result.converted > 0) {
        await audit(database, workspaceId, 'raw_history', String(archiveWorkspace.id), 'raw_history_batch_convert', null, result, userId, { session });
      }
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function dictionaryTrainingDecisions(database, workspaceId, query) {
  await requireWorkspaceFullReader(database, workspaceId);
  const limit = clampInt(query.limit, 120, 1, 500);
  const rows = await database.collection('v2_dictionary_training_decisions')
    .find({ workspace_id: workspaceId })
    .sort({ decided_at: -1, updated_at: -1 })
    .limit(limit)
    .toArray();
  const categoryIds = rows.map((row) => row.category_id).filter(Boolean);
  const categories = categoryIds.length ? await database.collection('v2_categories')
    .find({ id: { $in: categoryIds } })
    .toArray() : [];
  const categoryMap = new Map(categories.map((category) => [String(category.id), String(category.code)]));
  return rows.map((row) => dictionaryTrainingDecisionRow({ ...row, category_code: categoryMap.get(String(row.category_id)) || null }));
}

function dictionaryTrainingDecisionType(input) {
  let raw = optionalStringInput(input, 'decision_type', null, 80) ?? optionalStringInput(input, 'decision', null, 80);
  if (raw === null) {
    const error = new Error('missing_decision_type');
    error.status = 422;
    throw error;
  }
  raw = ({ accept: 'approve_existing_guess_local', reject: 'reject_training', skip: 'defer' })[raw] || raw;
  if (raw === 'promote_universal') {
    const error = new Error('universal_promotion_not_supported');
    error.status = 422;
    throw error;
  }
  return enumInput(raw, [
    'defer',
    'reject_training',
    'approve_existing_guess_local',
    'correct_category_local',
    'mark_semantic_blocked',
    'propose_universal_candidate',
  ], 'decision_type');
}

function dictionaryTrainingStringList(value) {
  if (!Array.isArray(value)) return [];
  return [...new Set(value
    .map((item) => String(item).trim().slice(0, 190))
    .filter((item) => item !== ''))];
}

function assertDictionaryTrainingRuleAllowed(reviewReason, blockers) {
  if (blockers.length > 0 || [
    'blocked_by_personal',
    'blocked_by_debt',
    'private_money_movement',
    'commercial_income_unclear',
    'card_income_not_allowed',
  ].includes(reviewReason)) {
    const error = new Error('dictionary_training_blocked');
    error.status = 422;
    throw error;
  }
}

async function dictionaryTrainingSourceRow(database, archiveWorkspaceId, sourceRowId, options = {}) {
  const row = await database.collection('v2_import_rows').findOne({ id: sourceRowId }, options);
  if (!row) {
    const error = new Error('dictionary_source_row_not_found');
    error.status = 404;
    throw error;
  }
  const source = await database.collection('v2_import_sources').findOne({
    id: row.import_source_id,
    workspace_id: archiveWorkspaceId,
    include_decision: 'included',
  }, options);
  if (!source) {
    const error = new Error('dictionary_source_row_not_found');
    error.status = 404;
    throw error;
  }
  return {
    ...row,
    source_id: source.id,
    file_name: source.file_name,
    workspace_id: source.workspace_id,
    source_row_id: row.id,
  };
}

function dictionaryTrainingSnapshotFromSourceRow(row) {
  const raw = decodeJson(row.raw_json, {});
  const description = dictionaryDescription(raw);
  const money = dictionaryMoney(raw);
  const guess = money ? dictionaryCategoryGuess(description, money.flow_type, money.sign) : { category_code: null, pattern: null };
  const rawText = `${money ? money.sign : ''}${money ? Number(money.amount).toFixed(2) : ''} ${description}`.trim();
  const markers = money ? dictionarySemanticMarkers(rawText, guess.category_code) : [];
  return dictionaryReviewExample(row, raw, description, money, guess, markers);
}

async function getCategoryRule(database, workspaceId, ruleId, userId = USER_ID, options = {}) {
  await requireWorkspace(database, workspaceId, userId, options);
  const rule = await database.collection('v2_category_rules').findOne({
    id: ruleId,
    workspace_id: workspaceId,
  }, options);
  if (!rule) {
    const error = new Error('category_rule_not_found');
    error.status = 404;
    throw error;
  }
  const category = await database.collection('v2_categories').findOne({ id: rule.category_id }, options);
  return categoryRuleRow({ ...rule, category_code: category ? category.code : null });
}

async function dictionaryTrainingExistingRuleMatches(database, decision, ruleInput, workspaceId, userId = USER_ID, options = {}) {
  if (!decision.category_rule_id) return false;
  const rule = await getCategoryRule(database, workspaceId, String(decision.category_rule_id), userId, options);
  return rule.category_code === ruleInput.category_code
    && rule.pattern === ruleInput.pattern
    && rule.pattern_type === ruleInput.pattern_type
    && rule.language === ruleInput.language
    && Number(rule.weight || 0) === Number(ruleInput.weight || 0)
    && Number(rule.negative_weight || 0) === Number(ruleInput.negative_weight || 0)
    && JSON.stringify(rule.requires_any || []) === JSON.stringify(ruleInput.requires_any || [])
    && JSON.stringify(rule.excludes_any || []) === JSON.stringify(ruleInput.excludes_any || []);
}

async function dictionaryTrainingDecisionBySourceRow(database, workspaceId, sourceRowId, options = {}) {
  return database.collection('v2_dictionary_training_decisions').findOne({ workspace_id: workspaceId, source_row_id: sourceRowId }, options);
}

async function dictionaryTrainingDecisionById(database, workspaceId, decisionId, options = {}) {
  const decision = await database.collection('v2_dictionary_training_decisions').findOne({ id: decisionId, workspace_id: workspaceId }, options);
  if (!decision) {
    const error = new Error('dictionary_training_decision_not_found');
    error.status = 404;
    throw error;
  }
  const category = decision.category_id
    ? await database.collection('v2_categories').findOne({ id: decision.category_id }, options)
    : null;
  return dictionaryTrainingDecisionRow({ ...decision, category_code: category ? category.code : null });
}

async function dictionaryTrainingDecisionRowWithCategory(database, decision, options = {}) {
  if (!decision) return null;
  const category = decision.category_id
    ? await database.collection('v2_categories').findOne({ id: decision.category_id }, options)
    : null;
  return dictionaryTrainingDecisionRow({ ...decision, category_code: category ? category.code : null });
}

async function decideDictionaryTraining(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      await requireWorkspace(database, workspaceId, userId, { session });
      await requireWorkspaceWriter(database, workspaceId, userId, { session });
      const archiveWorkspace = await dictionaryArchiveWorkspace(database, workspaceId, userId, { session });
      const decisionType = dictionaryTrainingDecisionType(input);
      const decisionScope = enumInput(
        optionalStringInput(input, 'decision_scope', 'row', 20) || 'row',
        ['row', 'group'],
        'decision_scope'
      );
      const groupKey = optionalStringInput(input, 'group_key', null, 190);
      const sourceRowId = optionalStringInput(input, 'source_row_id', null, 36);
      let sourceRowIds = optionalStringListInput(input, 'source_row_ids');
      if (sourceRowId !== null && !sourceRowIds.includes(sourceRowId)) sourceRowIds.unshift(sourceRowId);
      sourceRowIds = [...new Set(sourceRowIds)];

      if (decisionScope === 'row' && sourceRowId === null) {
        const error = new Error('missing_source_row_id');
        error.status = 422;
        throw error;
      }
      if (decisionScope === 'group' && groupKey === null && sourceRowIds.length === 0) {
        const error = new Error('missing_group_key');
        error.status = 422;
        throw error;
      }

      let sourceRow = null;
      let snapshot = input.source_snapshot && typeof input.source_snapshot === 'object' && !Array.isArray(input.source_snapshot)
        ? input.source_snapshot
        : {};
      if (sourceRowId !== null) {
        sourceRow = await dictionaryTrainingSourceRow(database, archiveWorkspace.id, sourceRowId, { session });
        snapshot = dictionaryTrainingSnapshotFromSourceRow(sourceRow);
      }

      const classificationDecision = snapshot.classification_decision && typeof snapshot.classification_decision === 'object'
        ? snapshot.classification_decision
        : {};
      const blockers = dictionaryTrainingStringList(input.blockers ?? snapshot.blockers ?? classificationDecision.blockers ?? []);
      const matchedSignals = Array.isArray(input.matched_signals)
        ? Array.from(input.matched_signals)
        : (Array.isArray(snapshot.matched_signals) ? Array.from(snapshot.matched_signals) : (Array.isArray(classificationDecision.matched_signals) ? Array.from(classificationDecision.matched_signals) : []));
      const semanticMarkers = Array.isArray(input.semantic_markers)
        ? Array.from(input.semantic_markers)
        : (Array.isArray(snapshot.semantic_markers) ? Array.from(snapshot.semantic_markers) : []);
      const reviewReason = optionalStringInput(
        input,
        'review_reason',
        snapshot.review_reason ?? classificationDecision.review_reason ?? null,
        80
      );
      const currentRuleGuess = optionalStringInput(
        input,
        'current_rule_guess',
        snapshot.current_rule_guess ?? classificationDecision.category_code ?? null,
        80
      );
      const confidence = nullableAmount(input.confidence ?? snapshot.confidence ?? classificationDecision.confidence ?? null);
      const note = optionalStringInput(input, 'note', null, 2000);
      const pattern = optionalStringInput(input, 'pattern', null, 255);
      let patternType = optionalStringInput(input, 'pattern_type', null, 40);
      const language = enumInput(
        optionalStringInput(input, 'language', 'multi', 10) || 'multi',
        ['ru', 'en', 'it', 'es', 'de', 'bcms', 'multi'],
        'language'
      );
      const weight = Object.hasOwn(input, 'weight') ? optionalIntInput(input, 'weight', 10) : null;
      const negativeWeight = Object.hasOwn(input, 'negative_weight') ? optionalIntInput(input, 'negative_weight', 0) : null;
      const requiresAny = optionalStringListInput(input, 'requires_any');
      const excludesAny = optionalStringListInput(input, 'excludes_any');
      let targetCategoryCode = optionalStringInput(input, 'target_category_code', null, 80)
        ?? optionalStringInput(input, 'category_code', null, 80);

      const existing = sourceRowId === null ? null : await dictionaryTrainingDecisionBySourceRow(database, workspaceId, sourceRowId, { session });
      let categoryRule = null;
      let categoryRuleId = null;
      let categoryId = null;

      if (['approve_existing_guess_local', 'correct_category_local'].includes(decisionType)) {
        assertDictionaryTrainingRuleAllowed(reviewReason, blockers);
        if (targetCategoryCode === null && decisionType === 'approve_existing_guess_local') {
          targetCategoryCode = currentRuleGuess;
        }
        if (targetCategoryCode === null) {
          const error = new Error('missing_target_category_code');
          error.status = 422;
          throw error;
        }
        if (pattern === null) {
          const error = new Error('missing_pattern');
          error.status = 422;
          throw error;
        }
        patternType = enumInput(patternType || 'keyword', ['keyword', 'phrase', 'regex', 'supplier', 'role'], 'pattern_type');
        const category = await categoryByCode(database, workspaceId, targetCategoryCode, { session });
        categoryId = String(category.id);
        const ruleInput = {
          category_code: targetCategoryCode,
          pattern,
          pattern_type: patternType,
          language,
          weight: weight ?? 10,
          negative_weight: negativeWeight ?? 0,
          requires_any: requiresAny,
          excludes_any: excludesAny,
        };
        if (existing !== null && await dictionaryTrainingExistingRuleMatches(database, existing, ruleInput, workspaceId, userId, { session })) {
          categoryRuleId = String(existing.category_rule_id);
          categoryRule = await getCategoryRule(database, workspaceId, categoryRuleId, userId, { session });
        } else {
          categoryRule = await createCategoryRuleInSession(database, workspaceId, ruleInput, userId, session);
          categoryRuleId = categoryRule.id;
          await audit(database, workspaceId, 'category_rule', categoryRuleId, 'create', null, categoryRule, userId, { session });
        }
      } else if (decisionType === 'propose_universal_candidate') {
        targetCategoryCode = targetCategoryCode ?? currentRuleGuess;
        if (targetCategoryCode !== null) {
          const category = await categoryByCode(database, workspaceId, targetCategoryCode, { session });
          categoryId = String(category.id);
        }
      } else if (targetCategoryCode !== null) {
        const category = await categoryByCode(database, workspaceId, targetCategoryCode, { session });
        categoryId = String(category.id);
      }

      const decisionId = existing === null ? uuid() : String(existing.id);
      const sourceId = sourceRow ? sourceRow.source_id : null;
      const before = existing === null ? null : await dictionaryTrainingDecisionRowWithCategory(database, existing, { session });
      const row = {
        id: decisionId,
        workspace_id: workspaceId,
        archive_workspace_id: archiveWorkspace.id,
        source_id: sourceId,
        source_row_id: sourceRowId,
        decision_scope: decisionScope,
        group_key: groupKey,
        source_row_ids_json: JSON.stringify(sourceRowIds),
        decision_type: decisionType,
        current_rule_guess: currentRuleGuess,
        category_id: categoryId,
        category_rule_id: categoryRuleId,
        pattern,
        pattern_type: patternType,
        language,
        weight,
        negative_weight: negativeWeight,
        requires_any_json: JSON.stringify(requiresAny),
        excludes_any_json: JSON.stringify(excludesAny),
        confidence,
        review_reason: reviewReason,
        blockers_json: JSON.stringify(blockers),
        matched_signals_json: JSON.stringify(matchedSignals),
        semantic_markers_json: JSON.stringify(semanticMarkers),
        example_snapshot_json: JSON.stringify(snapshot),
        note,
        decided_by: userId,
        decided_at: existing ? existing.decided_at : nowSql(),
        updated_at: nowSql(),
      };

      if (existing === null) {
        await database.collection('v2_dictionary_training_decisions').insertOne(row, { session });
      } else {
        await database.collection('v2_dictionary_training_decisions').updateOne(
          { id: decisionId, workspace_id: workspaceId },
          {
            $set: {
              archive_workspace_id: row.archive_workspace_id,
              source_id: row.source_id,
              decision_scope: row.decision_scope,
              group_key: row.group_key,
              source_row_ids_json: row.source_row_ids_json,
              decision_type: row.decision_type,
              current_rule_guess: row.current_rule_guess,
              category_id: row.category_id,
              category_rule_id: row.category_rule_id,
              pattern: row.pattern,
              pattern_type: row.pattern_type,
              language: row.language,
              weight: row.weight,
              negative_weight: row.negative_weight,
              requires_any_json: row.requires_any_json,
              excludes_any_json: row.excludes_any_json,
              confidence: row.confidence,
              review_reason: row.review_reason,
              blockers_json: row.blockers_json,
              matched_signals_json: row.matched_signals_json,
              semantic_markers_json: row.semantic_markers_json,
              example_snapshot_json: row.example_snapshot_json,
              note: row.note,
              decided_by: row.decided_by,
              updated_at: row.updated_at,
            },
          },
          { session }
        );
      }

      const after = await dictionaryTrainingDecisionById(database, workspaceId, decisionId, { session });
      if (categoryRule !== null) after.category_rule = categoryRule;
      await audit(database, workspaceId, 'dictionary_training_decision', decisionId, existing === null ? 'create' : 'update', before, after, userId, { session });
      result = after;
    });
    return result;
  } finally {
    await session.endSession();
  }
}

function internetReferenceProviderKeys() {
  return ['stub'];
}

function sanitizeDictionaryInternetQuery(query) {
  return String(query || '')
    .replace(/[+-]?\s*\d+(?:[.,]\d+)?/gu, ' ')
    .replace(/\b\d{4}-\d{2}-\d{2}\b/gu, ' ')
    .replace(/\s+/gu, ' ')
    .trim()
    .slice(0, 190);
}

function assertSafeInternetReferenceInput(input, message) {
  for (const unsafeKey of ['raw_text', 'raw_row', 'source_snapshot', 'amount', 'balance', 'balance_after', 'report', 'entries', 'rows']) {
    if (Object.hasOwn(input, unsafeKey)) {
      const error = new Error(message);
      error.status = 422;
      throw error;
    }
  }
}

function stubInternetReferenceLookup(sanitizedQuery) {
  return {
    provider_key: 'stub',
    provider_request_id: null,
    result_status: 'stub',
    latency_ms: 0,
    matches: [{
      label: 'No external lookup performed',
      business_type: 'beta_stub',
      location: null,
      aliases: [],
      source_url: null,
      source_domain: null,
      source_type: 'stub',
      retrieved_at: new Date().toISOString(),
      confidence: '0.00',
      uncertainty_reason: 'Mr. Smith beta is consent/provenance preview only. Internet lookup is not enabled.',
      query_preview: sanitizedQuery,
    }],
  };
}

async function previewDictionaryInternetReference(database, workspaceId, input, userId = USER_ID) {
  const { workspace } = await requireWorkspaceFullReader(database, workspaceId, userId);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const settingsRaw = await database.collection('v2_workspace_assistant_settings').findOne({ workspace_id: workspaceId });
  const settings = workspaceAssistantSettingsRow(settingsRaw, workspaceId);
  const consent = input.lookup_consent === true || input.consent === true;
  const mode = String(settings.internet_reference_mode);
  if (mode === 'disabled') {
    const error = new Error('internet_reference_disabled');
    error.status = 422;
    throw error;
  }
  if (mode === 'per_request' && !consent) {
    const error = new Error('internet_reference_consent_required');
    error.status = 422;
    throw error;
  }
  if (mode === 'workspace_enabled' && !settings.mr_smith_enabled && !consent) {
    const error = new Error('internet_reference_consent_required');
    error.status = 422;
    throw error;
  }
  assertSafeInternetReferenceInput(input, 'unsafe_internet_reference_payload');

  const query = optionalStringInput(input, 'sanitized_query', null, 190)
    ?? optionalStringInput(input, 'query', null, 190);
  if (query === null) {
    const error = new Error('missing_sanitized_query');
    error.status = 422;
    throw error;
  }
  const sanitizedQuery = sanitizeDictionaryInternetQuery(query);
  if (sanitizedQuery === '') {
    const error = new Error('missing_sanitized_query');
    error.status = 422;
    throw error;
  }

  const sourceRowId = optionalStringInput(input, 'source_row_id', null, 36);
  if (sourceRowId !== null) {
    const archiveWorkspace = await dictionaryArchiveWorkspace(database, workspaceId, userId);
    await dictionaryTrainingSourceRow(database, archiveWorkspace.id, sourceRowId);
  }

  const providerKey = enumInput(settings.provider_key, internetReferenceProviderKeys(), 'provider_key');
  const providerResult = providerKey === 'stub'
    ? stubInternetReferenceLookup(sanitizedQuery)
    : stubInternetReferenceLookup(sanitizedQuery);
  const matches = Array.isArray(providerResult.matches) ? providerResult.matches : [];
  const resultStatus = enumInput(providerResult.result_status || 'stub', ['stub', 'ok', 'error', 'timeout'], 'result_status');
  const requestId = uuid();
  const queryHash = crypto
    .createHash('sha256')
    .update(`${workspaceId}|${sanitizedQuery.toLowerCase()}`)
    .digest('hex');
  const maskedFields = ['amounts', 'balances', 'raw_rows', 'private_notes'];
  const consentSource = consent ? 'request' : 'workspace_setting';
  const retentionDeleteAfter = new Date(Date.now() + Number(settings.retention_days || 30) * 86400000)
    .toISOString()
    .slice(0, 19)
    .replace('T', ' ');

  await database.collection('v2_internet_reference_lookups').insertOne({
    id: requestId,
    workspace_id: workspace.id,
    source_row_id: sourceRowId,
    provider_key: enumInput(providerResult.provider_key || providerKey, internetReferenceProviderKeys(), 'provider_key'),
    provider_request_id: providerResult.provider_request_id || null,
    consent_source: consentSource,
    sanitized_query: sanitizedQuery,
    query_hash: queryHash,
    masked_fields_json: JSON.stringify(maskedFields),
    result_status: resultStatus,
    latency_ms: Math.max(0, Number.parseInt(String(providerResult.latency_ms ?? 0), 10) || 0),
    matches_json: JSON.stringify(matches),
    selected_match_json: null,
    no_financial_mutation: 1,
    created_by: userId,
    created_at: nowSql(),
    retention_delete_after: retentionDeleteAfter,
  });

  return {
    request_id: requestId,
    lookup_id: requestId,
    workspace_id: workspace.id,
    source_row_id: sourceRowId,
    sanitized_query: sanitizedQuery,
    query_hash: queryHash,
    masked_fields: maskedFields,
    provider_key: providerResult.provider_key || providerKey,
    provider_request_id: providerResult.provider_request_id || null,
    result_status: resultStatus,
    consent_source: consentSource,
    matches,
    suggested_reviewer_question: 'Confirm this public resource manually before creating any training decision.',
    no_financial_mutation: true,
  };
}

async function updateDictionaryInternetReferenceFeedback(database, workspaceId, lookupId, input, userId = USER_ID) {
  await requireWorkspaceWriter(database, workspaceId, userId);
  assertSafeInternetReferenceInput(input, 'unsafe_internet_reference_feedback_payload');
  const lookup = await database.collection('v2_internet_reference_lookups').findOne({ workspace_id: workspaceId, id: lookupId });
  if (!lookup) {
    const error = new Error('internet_reference_lookup_not_found');
    error.status = 404;
    throw error;
  }
  const verdict = enumInput(requireStringInput(input, 'verdict', 40), ['useful', 'unclear', 'not_useful'], 'verdict');
  const matches = decodeJson(lookup.matches_json, []);
  const safeMatches = Array.isArray(matches) ? matches : [];
  const matchIndex = Math.max(0, Math.min(4, optionalIntInput(input, 'match_index', 0)));
  const selectedMatch = safeMatches[matchIndex] || null;
  const selection = {
    verdict,
    match_index: selectedMatch === null ? null : matchIndex,
    match: selectedMatch,
    note: optionalStringInput(input, 'note', null, 240),
    selected_at: new Date().toISOString(),
    selected_by: userId,
    no_financial_mutation: true,
    no_training_mutation: true,
  };
  await database.collection('v2_internet_reference_lookups').updateOne(
    { workspace_id: workspaceId, id: lookupId },
    { $set: { selected_match_json: JSON.stringify(selection) } }
  );
  const updated = await database.collection('v2_internet_reference_lookups').findOne({ workspace_id: workspaceId, id: lookupId });
  if (!updated) {
    const error = new Error('internet_reference_lookup_not_found');
    error.status = 404;
    throw error;
  }
  return dictionaryInternetReferenceLookupRow(updated);
}

async function audit(database, workspaceId, entityType, entityId, action, before, after, userId = USER_ID, options = {}) {
  await database.collection('v2_audit_log').insertOne({
    id: uuid(),
    workspace_id: workspaceId,
    entity_type: entityType,
    entity_id: entityId,
    action,
    before_json: before === null ? null : JSON.stringify(before),
    after_json: after === null ? null : JSON.stringify(after),
    performed_by: userId,
    created_at: nowSql(),
  }, options);
}

async function createDefaultFlow(database, workspaceId, name, type, hasLiveBalance, isDefault = true, openingBalance = '0.00', options = {}) {
  const row = {
    id: uuid(),
    workspace_id: workspaceId,
    name,
    type,
    has_live_balance: hasLiveBalance ? 1 : 0,
    opening_balance: openingBalance,
    is_default: isDefault ? 1 : 0,
    created_at: nowSql(),
  };
  await database.collection('v2_flows').insertOne(row, options);
  return flowRow(row);
}

async function createFlow(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let flow = null;
    await session.withTransaction(async () => {
      await requireWorkspace(database, workspaceId, userId, { session });
      await requireWorkspaceWriter(database, workspaceId, userId, { session });
      flow = await createDefaultFlow(
        database,
        workspaceId,
        requireStringInput(input, 'name', 120),
        enumInput(String(input.type || ''), ['cash', 'card', 'assistant_journal', 'accountable'], 'type'),
        phpBool(input.has_live_balance),
        phpBool(input.is_default),
        nullableAmount(input.opening_balance ?? null) || '0.00',
        { session }
      );
      await audit(database, workspaceId, 'flow', flow.id, 'create', null, flow, userId, { session });
    });
    return flow;
  } finally {
    await session.endSession();
  }
}

async function createCategoryRule(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let rule = null;
    await session.withTransaction(async () => {
      rule = await createCategoryRuleInSession(database, workspaceId, input, userId, session);
      await audit(database, workspaceId, 'category_rule', rule.id, 'create', null, rule, userId, { session });
    });
    return rule;
  } finally {
    await session.endSession();
  }
}

async function createCategoryRuleInSession(database, workspaceId, input, userId = USER_ID, session) {
  await requireWorkspace(database, workspaceId, userId, { session });
  await requireWorkspaceWriter(database, workspaceId, userId, { session });
  const category = await categoryByCode(
    database,
    workspaceId,
    requireStringInput(input, 'category_code', 80),
    { session }
  );
  const row = {
    id: uuid(),
    workspace_id: workspaceId,
    category_id: String(category.id),
    pattern: requireStringInput(input, 'pattern', 255),
    pattern_type: enumInput(
      optionalStringInput(input, 'pattern_type', 'keyword', 40) || 'keyword',
      ['keyword', 'phrase', 'regex', 'supplier', 'role'],
      'pattern_type'
    ),
    language: enumInput(
      optionalStringInput(input, 'language', 'multi', 10) || 'multi',
      ['ru', 'en', 'it', 'es', 'de', 'bcms', 'multi'],
      'language'
    ),
    weight: optionalIntInput(input, 'weight', 10),
    negative_weight: optionalIntInput(input, 'negative_weight', 0),
    requires_any_json: JSON.stringify(optionalStringListInput(input, 'requires_any')),
    excludes_any_json: JSON.stringify(optionalStringListInput(input, 'excludes_any')),
    created_by_user: 1,
    is_active: 1,
    created_at: nowSql(),
  };
  await database.collection('v2_category_rules').insertOne(row, { session });
  return categoryRuleRow({ ...row, category_code: category.code });
}

async function createWorkspace(database, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let workspace = null;
    await session.withTransaction(async () => {
      const workspaceId = uuid();
      const timestamp = nowSql();
      const row = {
        id: workspaceId,
        name: requireStringInput(input, 'name', 190),
        type: enumInput(optionalStringInput(input, 'type', 'yacht', 40), ['yacht', 'family', 'personal', 'business', 'trip', 'custom'], 'type'),
        currency: optionalStringInput(input, 'currency', 'EUR', 3).toUpperCase(),
        locale: optionalStringInput(input, 'locale', 'ru', 10),
        created_by: userId,
        created_at: timestamp,
        updated_at: null,
        archived_at: null,
      };
      await database.collection('v2_workspaces').insertOne(row, { session });
      await database.collection('v2_workspace_members').insertOne({
        id: uuid(),
        workspace_id: workspaceId,
        user_id: userId,
        role: 'owner',
        access_scope: 'workspace',
        assigned_actor_id: null,
        created_at: timestamp,
      }, { session });
      const openingCash = nullableAmount(input.opening_cash ?? input.opening_balance) || '0.00';
      await createDefaultFlow(database, workspaceId, 'Cash', 'cash', true, true, openingCash, { session });
      await createDefaultFlow(database, workspaceId, 'Card', 'card', false, true, '0.00', { session });
      const member = await database.collection('v2_workspace_members').findOne({ workspace_id: workspaceId, user_id: userId }, { session });
      workspace = workspaceRow(row, member);
      await audit(database, workspaceId, 'workspace', workspaceId, 'create', null, workspace, userId, { session });
    });
    return workspace;
  } finally {
    await session.endSession();
  }
}

async function updateWorkspace(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let after = null;
    await session.withTransaction(async () => {
      const { workspace, member } = await requireWorkspace(database, workspaceId, userId, { session });
      await requireWorkspaceWriter(database, workspaceId, userId, { session });
      const before = workspaceRow(workspace, member);
      const update = {
        name: optionalStringInput(input, 'name', before.name, 190),
        type: enumInput(optionalStringInput(input, 'type', before.type, 40), ['yacht', 'family', 'personal', 'business', 'trip', 'custom'], 'type'),
        currency: optionalStringInput(input, 'currency', before.currency, 3).toUpperCase(),
        locale: optionalStringInput(input, 'locale', before.locale, 10),
        updated_at: nowSql(),
      };
      await database.collection('v2_workspaces').updateOne({ id: workspaceId, archived_at: null }, { $set: update }, { session });
      const afterRaw = await database.collection('v2_workspaces').findOne({ id: workspaceId, archived_at: null }, { session });
      after = workspaceRow(afterRaw, member);
      await audit(database, workspaceId, 'workspace', workspaceId, 'update', before, after, userId, { session });
    });
    return after;
  } finally {
    await session.endSession();
  }
}

async function deleteWorkspace(database, workspaceId, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let after = null;
    await session.withTransaction(async () => {
      const { workspace, member } = await requireWorkspace(database, workspaceId, userId, { session });
      await requireWorkspaceAdmin(database, workspaceId, userId, { session });
      const before = workspaceRow(workspace, member);
      const timestamp = nowSql();
      await database.collection('v2_workspaces').updateOne(
        { id: workspaceId, archived_at: null },
        { $set: { archived_at: timestamp, updated_at: timestamp } },
        { session }
      );
      after = {
        ...before,
        archived: true,
        archived_at: new Date(`${timestamp}Z`).toISOString(),
        trash_retention_days: 60,
      };
      await audit(database, workspaceId, 'workspace', workspaceId, 'delete_to_trash', before, after, userId, { session });
    });
    return after;
  } finally {
    await session.endSession();
  }
}

async function workspaceMemberExists(database, workspaceId, userId = USER_ID, options = {}) {
  const member = await database.collection('v2_workspace_members').findOne({ workspace_id: workspaceId, user_id: userId }, options);
  return Boolean(member);
}

async function workspaceInviteByToken(database, token, options = {}) {
  const normalized = String(token ?? '').trim().toLowerCase();
  if (!/^[a-f0-9]{48}$/.test(normalized)) {
    const error = new Error('invalid_invite_token');
    error.status = 422;
    throw error;
  }
  const invite = await database.collection('v2_workspace_invites').findOne({ token_hash: tokenHash(normalized) }, options);
  if (!invite) {
    const error = new Error('invite_not_found');
    error.status = 404;
    throw error;
  }
  return { invite, token: normalized };
}

async function assertWorkspaceInvitePending(database, invite, options = {}) {
  const status = String(invite.status);
  if (status === 'accepted') {
    const error = new Error('invite_already_accepted');
    error.status = 409;
    throw error;
  }
  if (status === 'revoked') {
    const error = new Error('invite_revoked');
    error.status = 409;
    throw error;
  }
  if (status === 'expired') {
    const error = new Error('invite_expired');
    error.status = 409;
    throw error;
  }
  if (invite.expires_at && new Date(`${invite.expires_at}Z`) <= new Date()) {
    await database.collection('v2_workspace_invites').updateOne({ id: invite.id, status: 'pending' }, { $set: { status: 'expired' } }, options);
    const error = new Error('invite_expired');
    error.status = 409;
    throw error;
  }
}

async function previewWorkspaceInvite(database, input, userId = USER_ID, options = {}) {
  const { invite } = await workspaceInviteByToken(database, requireStringInput(input, 'token', 80), options);
  await assertWorkspaceInvitePending(database, invite, options);
  const workspace = await database.collection('v2_workspaces').findOne({ id: invite.workspace_id, archived_at: null }, options);
  if (!workspace) {
    const error = new Error('workspace_not_found');
    error.status = 404;
    throw error;
  }
  const email = await userEmail(database, userId);
  return {
    invite: workspaceInviteRow(invite),
    workspace: { id: String(workspace.id), name: String(workspace.name), type: String(workspace.type) },
    email_matches: invite.invited_email === null || email === String(invite.invited_email),
  };
}

async function createWorkspaceInvite(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const { workspace } = await requireWorkspace(database, workspaceId, userId, { session });
      await requireWorkspaceAdmin(database, workspaceId, userId, { session });
      const role = enumInput(optionalStringInput(input, 'role', 'employee', 40), ['employee'], 'role');
      const accessScope = enumInput(optionalStringInput(input, 'access_scope', 'own_entries', 40), ['own_entries'], 'access_scope');
      const emailKey = Object.hasOwn(input, 'invited_email') ? 'invited_email' : 'email';
      const email = normalizeRequiredEmail(requireStringInput(input, emailKey, 190));
      const invitedName = optionalStringInput(input, 'name', null, 190);
      const expiresDays = Math.max(1, Math.min(30, optionalIntInput(input, 'expires_days', 7)));
      const token = crypto.randomBytes(24).toString('hex');
      const inviteId = uuid();
      const expiresAt = new Date(Date.now() + expiresDays * 24 * 60 * 60 * 1000)
        .toISOString()
        .slice(0, 19)
        .replace('T', ' ');
      const row = {
        id: inviteId,
        workspace_id: workspaceId,
        token_hash: tokenHash(token),
        token_hint: token.slice(0, 8),
        invited_email: email,
        invited_name: invitedName,
        role,
        access_scope: accessScope,
        status: 'pending',
        expires_at: expiresAt,
        accepted_at: null,
        accepted_by: null,
        revoked_at: null,
        revoked_by: null,
        created_by: userId,
        created_at: nowSql(),
      };
      await database.collection('v2_workspace_invites').insertOne(row, { session });
      const invite = {
        ...workspaceInviteRow(row),
        token,
        url: workspaceInviteUrl(token),
      };
      await audit(database, workspaceId, 'workspace_invite', inviteId, 'create', null, workspaceInviteAuditPayload(invite), userId, { session });
      result = {
        ...invite,
        workspace: {
          id: String(workspace.id),
          name: String(workspace.name),
        },
      };
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function revokeWorkspaceInvite(database, workspaceId, inviteId, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      await requireWorkspaceAdmin(database, workspaceId, userId, { session });
      const row = await database.collection('v2_workspace_invites').findOne({ id: inviteId, workspace_id: workspaceId }, { session });
      if (!row) {
        const error = new Error('invite_not_found');
        error.status = 404;
        throw error;
      }
      if (String(row.status) !== 'pending') {
        const error = new Error('invite_not_pending');
        error.status = 409;
        throw error;
      }
      const before = workspaceInviteRow(row);
      const update = {
        status: 'revoked',
        revoked_at: nowSql(),
        revoked_by: userId,
      };
      await database.collection('v2_workspace_invites').updateOne({ id: inviteId, workspace_id: workspaceId }, { $set: update }, { session });
      const updated = { ...row, ...update };
      result = workspaceInviteRow(updated);
      await audit(database, workspaceId, 'workspace_invite', inviteId, 'revoke', workspaceInviteAuditPayload(before), workspaceInviteAuditPayload(result), userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function acceptWorkspaceInvite(database, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const { invite } = await workspaceInviteByToken(database, requireStringInput(input, 'token', 80), { session });
      await assertWorkspaceInvitePending(database, invite, { session });
      const workspaceId = String(invite.workspace_id);
      const email = await userEmail(database, userId);
      if (invite.invited_email !== null && invite.invited_email !== undefined && email !== String(invite.invited_email)) {
        const error = new Error('invite_email_mismatch');
        error.status = 403;
        throw error;
      }
      if (await workspaceMemberExists(database, workspaceId, userId, { session })) {
        const error = new Error('workspace_member_exists');
        error.status = 409;
        throw error;
      }
      await database.collection('v2_workspace_members').insertOne({
        id: uuid(),
        workspace_id: workspaceId,
        user_id: userId,
        role: 'employee',
        access_scope: 'own_entries',
        assigned_actor_id: null,
        created_at: nowSql(),
      }, { session });
      const before = workspaceInviteRow(invite);
      const update = {
        status: 'accepted',
        accepted_at: nowSql(),
        accepted_by: userId,
      };
      await database.collection('v2_workspace_invites').updateOne({ id: invite.id }, { $set: update }, { session });
      const updated = { ...invite, ...update };
      const { workspace, member } = await requireWorkspace(database, workspaceId, userId, { session });
      const inviteAfter = workspaceInviteRow(updated);
      result = {
        invite: inviteAfter,
        workspace: workspaceRow(workspace, member),
      };
      await audit(database, workspaceId, 'workspace_invite', String(invite.id), 'accept', workspaceInviteAuditPayload(before), workspaceInviteAuditPayload(inviteAfter), userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function workspaceEmployeeUserIdByEmail(database, workspaceId, email, options = {}) {
  const users = await database.collection('users')
    .find({ email, status: 'active', deleted_at: null }, options)
    .toArray();
  const userIds = users.map((user) => user.id);
  if (!userIds.length) return null;
  const member = await database.collection('v2_workspace_members').findOne({
    workspace_id: workspaceId,
    user_id: { $in: userIds },
    role: 'employee',
  }, options);
  return member ? Number(member.user_id) : null;
}

async function workspaceEmployeeTargetByUserId(database, workspaceId, employeeUserId, options = {}) {
  const member = await database.collection('v2_workspace_members').findOne({
    workspace_id: workspaceId,
    user_id: employeeUserId,
    role: 'employee',
  }, options);
  const user = await database.collection('users').findOne({
    id: employeeUserId,
    status: 'active',
    deleted_at: null,
  }, options);
  if (!member || !user || !user.email) {
    const error = new Error('employee_member_not_found');
    error.status = 422;
    throw error;
  }
  return {
    employee_user_id: employeeUserId,
    employee_email: normalizeRequiredEmail(user.email),
  };
}

async function normalizeAccountableOfferTarget(database, workspaceId, input, options = {}) {
  const hasEmployeeUserId = Object.hasOwn(input, 'employee_user_id') && input.employee_user_id !== null && input.employee_user_id !== '';
  const emailKey = ['employee_email', 'invited_email', 'email'].find((key) => Object.hasOwn(input, key) && String(input[key]).trim() !== '');
  if (hasEmployeeUserId && emailKey) {
    const error = new Error('ambiguous_accountable_offer_target');
    error.status = 422;
    throw error;
  }
  if (!hasEmployeeUserId && !emailKey) {
    const error = new Error('missing_accountable_offer_target');
    error.status = 422;
    throw error;
  }
  if (hasEmployeeUserId) {
    const employeeUserId = optionalIntInput(input, 'employee_user_id', 0);
    if (employeeUserId <= 0) {
      const error = new Error('invalid_employee_user_id');
      error.status = 422;
      throw error;
    }
    return workspaceEmployeeTargetByUserId(database, workspaceId, employeeUserId, options);
  }
  const email = normalizeRequiredEmail(requireStringInput(input, emailKey, 190));
  return {
    employee_user_id: await workspaceEmployeeUserIdByEmail(database, workspaceId, email, options),
    employee_email: email,
  };
}

async function accountableOfferById(database, offerId, options = {}) {
  const offer = await database.collection('v2_accountable_offers').findOne({ id: offerId }, options);
  if (!offer) {
    const error = new Error('accountable_offer_not_found');
    error.status = 404;
    throw error;
  }
  return offer;
}

async function createAccountableOffer(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const { workspace } = await requireWorkspace(database, workspaceId, userId, { session });
      await requireWorkspaceAdmin(database, workspaceId, userId, { session });
      const target = await normalizeAccountableOfferTarget(database, workspaceId, input, { session });
      const normalizedAmount = nullableAmount(input.amount);
      if (normalizedAmount === null || Number(normalizedAmount) <= 0) {
        const error = new Error('invalid_amount');
        error.status = 422;
        throw error;
      }
      const currency = optionalStringInput(input, 'currency', String(workspace.currency), 3).toUpperCase();
      if (!/^[A-Z]{3}$/.test(currency)) {
        const error = new Error('invalid_currency');
        error.status = 422;
        throw error;
      }
      if (!Object.hasOwn(input, 'purpose') && !Object.hasOwn(input, 'comment')) {
        const error = new Error('missing_purpose');
        error.status = 422;
        throw error;
      }
      const purposeKey = Object.hasOwn(input, 'purpose') ? 'purpose' : 'comment';
      const offerId = uuid();
      const row = {
        id: offerId,
        workspace_id: workspaceId,
        employee_user_id: target.employee_user_id,
        employee_email: target.employee_email,
        amount: normalizedAmount,
        currency,
        purpose: requireStringInput(input, purposeKey, 1000),
        status: 'pending_offer',
        created_by: userId,
        created_at: nowSql(),
        accepted_at: null,
        accepted_by: null,
        cancelled_at: null,
        cancelled_by: null,
        no_financial_mutation: 1,
        updated_at: null,
      };
      await database.collection('v2_accountable_offers').insertOne(row, { session });
      result = accountableOfferRow(row);
      await audit(database, workspaceId, 'accountable_offer', offerId, 'create', null, result, userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function assertAccountableOfferVisibleToEmployee(database, offer, userId = USER_ID, options = {}) {
  const workspaceId = String(offer.workspace_id);
  const access = await workspaceAccess(database, workspaceId, userId, options);
  if (access.role !== 'employee') {
    const error = new Error('accountable_offer_not_found');
    error.status = 404;
    throw error;
  }
  const employeeUserId = offer.employee_user_id === null || offer.employee_user_id === undefined ? null : Number(offer.employee_user_id);
  if (employeeUserId !== null) {
    if (employeeUserId === userId) return;
    const error = new Error('accountable_offer_not_found');
    error.status = 404;
    throw error;
  }
  const email = await userEmail(database, userId);
  if (String(offer.employee_email) === email) return;
  const error = new Error('accountable_offer_not_found');
  error.status = 404;
  throw error;
}

async function acceptAccountableOffer(database, offerId, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const offer = await accountableOfferById(database, offerId, { session });
      await assertAccountableOfferVisibleToEmployee(database, offer, userId, { session });
      if (String(offer.status) !== 'pending_offer') {
        const error = new Error('accountable_offer_not_pending');
        error.status = 409;
        throw error;
      }
      const before = accountableOfferRow(offer);
      const update = {
        status: 'accepted_by_employee',
        employee_user_id: offer.employee_user_id === null || offer.employee_user_id === undefined ? userId : offer.employee_user_id,
        accepted_at: nowSql(),
        accepted_by: userId,
        updated_at: nowSql(),
      };
      await database.collection('v2_accountable_offers').updateOne({ id: offerId }, { $set: update }, { session });
      const after = accountableOfferRow({ ...offer, ...update });
      result = after;
      await audit(database, String(offer.workspace_id), 'accountable_offer', offerId, 'accept_by_employee', before, after, userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

function accountableReportInputRows(input, defaultCurrency) {
  if (!Array.isArray(input.rows) || input.rows.length === 0) {
    const error = new Error('missing_rows');
    error.status = 422;
    throw error;
  }
  if (input.rows.length > 100) {
    const error = new Error('too_many_rows');
    error.status = 422;
    throw error;
  }
  return input.rows.map((row) => {
    if (!row || typeof row !== 'object' || Array.isArray(row)) {
      const error = new Error('invalid_rows');
      error.status = 422;
      throw error;
    }
    const dateKey = Object.hasOwn(row, 'expense_date') ? 'expense_date' : 'date';
    const normalizedAmount = nullableAmount(row.amount);
    if (normalizedAmount === null || Number(normalizedAmount) <= 0) {
      const error = new Error('invalid_amount');
      error.status = 422;
      throw error;
    }
    const currency = optionalStringInput(row, 'currency', defaultCurrency, 3).toUpperCase();
    if (!/^[A-Z]{3}$/.test(currency)) {
      const error = new Error('invalid_currency');
      error.status = 422;
      throw error;
    }
    return {
      date: requireDateInput(row, dateKey),
      description: requireStringInput(row, 'description', 1000),
      amount: normalizedAmount,
      currency,
      category_code: optionalStringInput(row, 'category_code', null, 80),
      notes: optionalStringInput(row, 'notes', null, 1000) ?? optionalStringInput(row, 'receipt_note', null, 1000),
    };
  });
}

async function assertAccountableReportOwnedByEmployee(database, report, userId = USER_ID, options = {}) {
  const access = await workspaceAccess(database, String(report.workspace_id), userId, options);
  if (access.role !== 'employee' || Number(report.employee_user_id) !== userId) {
    const error = new Error('accountable_report_not_found');
    error.status = 404;
    throw error;
  }
}

async function accountableReportById(database, reportId, options = {}) {
  const report = await database.collection('v2_accountable_reports').findOne({ id: reportId }, options);
  if (!report) {
    const error = new Error('accountable_report_not_found');
    error.status = 404;
    throw error;
  }
  return report;
}

async function createAccountableReport(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const access = await workspaceAccess(database, workspaceId, userId, { session });
      if (access.role !== 'employee') {
        const error = new Error('employee_scope_required');
        error.status = 403;
        throw error;
      }
      if (!access.can_write_scoped_entries) {
        const error = new Error('workspace_scope_required');
        error.status = 403;
        throw error;
      }
      const offerId = requireStringInput(input, 'offer_id', 36);
      const offer = await accountableOfferById(database, offerId, { session });
      if (String(offer.workspace_id) !== workspaceId) {
        const error = new Error('accountable_offer_not_found');
        error.status = 404;
        throw error;
      }
      await assertAccountableOfferVisibleToEmployee(database, offer, userId, { session });
      if (String(offer.status) !== 'accepted_by_employee') {
        const error = new Error('accountable_offer_not_accepted');
        error.status = 409;
        throw error;
      }
      const rows = accountableReportInputRows(input, String(offer.currency));
      const reportId = uuid();
      const title = optionalStringInput(input, 'title', null, 190)
        ?? optionalStringInput(input, 'comment', null, 190)
        ?? 'Accountable expense report';
      const total = rows.reduce((sum, row) => sum + Number(row.amount), 0).toFixed(2);
      const timestamp = nowSql();
      const report = {
        id: reportId,
        workspace_id: workspaceId,
        offer_id: offerId,
        employee_user_id: userId,
        title,
        status: 'draft',
        currency: String(offer.currency),
        total_amount: total,
        row_count: rows.length,
        submitted_at: null,
        submitted_by: null,
        reviewed_at: null,
        reviewed_by: null,
        review_note: null,
        accepted_total_amount: '0.00',
        rejected_total_amount: '0.00',
        accepted_cash_expenses: '0.00',
        accepted_noncash_expenses: '0.00',
        settlement_status: null,
        materialized_at: null,
        ledger_materialization_status: 'not_materialized',
        ledger_materialized_at: null,
        ledger_materialized_by: null,
        ledger_materialization_hash: null,
        created_by: userId,
        created_at: timestamp,
        updated_at: null,
        no_financial_mutation: 1,
      };
      await database.collection('v2_accountable_reports').insertOne(report, { session });
      const rowDocs = rows.map((row, index) => ({
        id: uuid(),
        report_id: reportId,
        row_number: index + 1,
        expense_date: row.date,
        description: row.description,
        amount: row.amount,
        currency: row.currency,
        category_code: row.category_code,
        notes: row.notes,
        review_status: 'pending_review',
        accepted_amount: null,
        accepted_category_code: null,
        payment_method: null,
        review_note: null,
        operational_entry_id: null,
        created_at: timestamp,
      }));
      await database.collection('v2_accountable_report_rows').insertMany(rowDocs, { session });
      result = accountableReportRow(report, rowDocs.map(accountableReportDataRow), null);
      await audit(database, workspaceId, 'accountable_report', reportId, 'create_draft', null, result, userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function submitAccountableReport(database, reportId, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const report = await accountableReportById(database, reportId, { session });
      await assertAccountableReportOwnedByEmployee(database, report, userId, { session });
      if (String(report.status) !== 'draft') {
        const error = new Error('accountable_report_not_draft');
        error.status = 409;
        throw error;
      }
      if (Number(report.row_count) < 1) {
        const error = new Error('accountable_report_empty');
        error.status = 422;
        throw error;
      }
      const before = await accountableReportDetail(database, report);
      const update = {
        status: 'submitted',
        submitted_at: nowSql(),
        submitted_by: userId,
        updated_at: nowSql(),
      };
      await database.collection('v2_accountable_reports').updateOne({ id: reportId }, { $set: update }, { session });
      const afterRaw = { ...report, ...update };
      const rows = await database.collection('v2_accountable_report_rows')
        .find({ report_id: reportId }, { session })
        .sort({ row_number: 1 })
        .toArray();
      result = accountableReportRow(afterRaw, rows.map(accountableReportDataRow), await accountableSettlementForReport(database, reportId));
      await audit(database, String(report.workspace_id), 'accountable_report', reportId, 'submit', before, result, userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function requireAccountableReportAdmin(database, report, userId = USER_ID, options = {}) {
  const access = await workspaceAccess(database, String(report.workspace_id), userId, options);
  if (!access.can_admin) {
    if (access.role === 'employee') {
      const error = new Error('accountable_report_not_found');
      error.status = 404;
      throw error;
    }
    const error = new Error('workspace_admin_required');
    error.status = 403;
    throw error;
  }
}

function moneyString(value) {
  return Number(value || 0).toFixed(2);
}

async function accountableReportReviewPlan(database, report, offer, input) {
  if (String(report.workspace_id) !== String(offer.workspace_id)) {
    const error = new Error('accountable_report_offer_mismatch');
    error.status = 422;
    throw error;
  }
  if (String(report.currency) !== String(offer.currency)) {
    const error = new Error('accountable_report_currency_mismatch');
    error.status = 422;
    throw error;
  }
  const decisionInput = new Map();
  if (input.rows !== undefined) {
    if (!Array.isArray(input.rows)) {
      const error = new Error('invalid_rows');
      error.status = 422;
      throw error;
    }
    for (const rowDecision of input.rows) {
      if (!rowDecision || typeof rowDecision !== 'object' || Array.isArray(rowDecision)) {
        const error = new Error('invalid_rows');
        error.status = 422;
        throw error;
      }
      decisionInput.set(requireStringInput(rowDecision, 'id', 36), rowDecision);
    }
  }
  const defaultPaymentMethod = enumInput(optionalStringInput(input, 'payment_method', 'cash', 40), ['cash', 'card', 'noncash', 'own_funds'], 'payment_method');
  const sourceRows = await accountableReportRows(database, String(report.id));
  const rows = [];
  let acceptedTotal = 0;
  let rejectedTotal = 0;
  let acceptedCash = 0;
  let acceptedNoncash = 0;
  for (const sourceRow of sourceRows) {
    const decision = decisionInput.get(sourceRow.id) || {};
    let reviewStatus = enumInput(optionalStringInput(decision, 'review_status', 'accepted', 40), ['accepted', 'adjusted', 'rejected'], 'review_status');
    const paymentMethod = enumInput(optionalStringInput(decision, 'payment_method', defaultPaymentMethod, 40), ['cash', 'card', 'noncash', 'own_funds'], 'payment_method');
    let acceptedAmount = reviewStatus === 'rejected'
      ? 0
      : Number(nullableAmount(decision.accepted_amount) ?? sourceRow.amount);
    if (acceptedAmount < 0 || acceptedAmount > Number(sourceRow.amount)) {
      const error = new Error('invalid_accepted_amount');
      error.status = 422;
      throw error;
    }
    if (reviewStatus === 'accepted' && Math.abs(acceptedAmount - Number(sourceRow.amount)) > 0.004) {
      reviewStatus = 'adjusted';
    }
    acceptedAmount = Number(acceptedAmount.toFixed(2));
    const rejectedAmount = Number((Number(sourceRow.amount) - acceptedAmount).toFixed(2));
    acceptedTotal += acceptedAmount;
    rejectedTotal += rejectedAmount;
    if (paymentMethod === 'cash') {
      acceptedCash += acceptedAmount;
    } else {
      acceptedNoncash += acceptedAmount;
    }
    rows.push({
      id: sourceRow.id,
      row_number: sourceRow.row_number,
      review_status: reviewStatus,
      accepted_amount: moneyString(acceptedAmount),
      rejected_amount: rejectedAmount,
      payment_method: paymentMethod,
      accepted_category_code: optionalStringInput(decision, 'category_code', sourceRow.category_code, 80),
      review_note: optionalStringInput(decision, 'review_note', null, 1000) ?? optionalStringInput(decision, 'note', null, 1000),
    });
  }
  if (acceptedTotal <= 0) {
    const error = new Error('accountable_report_no_accepted_rows');
    error.status = 422;
    throw error;
  }
  const issued = Number(offer.amount);
  const expectedRemaining = Number((issued - acceptedCash).toFixed(2));
  const actualRemaining = Number(nullableAmount(input.actual_remaining) ?? Math.max(expectedRemaining, 0));
  const returnDue = Number(Math.max(expectedRemaining, 0).toFixed(2));
  const reimburseDue = Number(Math.max(-expectedRemaining, 0).toFixed(2));
  let status = 'closed';
  if (reimburseDue > 0.004) {
    status = 'reimburse_due';
  } else if (returnDue > 0.004) {
    status = 'return_due';
  }
  return {
    report_id: String(report.id),
    offer_id: String(offer.id),
    currency: String(report.currency),
    accepted_total_amount: moneyString(acceptedTotal),
    rejected_total_amount: moneyString(rejectedTotal),
    accepted_cash_expenses: moneyString(acceptedCash),
    accepted_noncash_expenses: moneyString(acceptedNoncash),
    rows,
    settlement: {
      issued_amount: moneyString(issued),
      accepted_cash_expenses: moneyString(acceptedCash),
      accepted_noncash_expenses: moneyString(acceptedNoncash),
      expected_remaining: moneyString(expectedRemaining),
      actual_remaining: moneyString(actualRemaining),
      return_due_amount: moneyString(returnDue),
      reimburse_due_amount: moneyString(reimburseDue),
      difference_amount: moneyString(actualRemaining - expectedRemaining),
      status,
    },
  };
}

async function previewAccountableReportReview(database, reportId, input, userId = USER_ID) {
  const report = await accountableReportById(database, reportId);
  await requireAccountableReportAdmin(database, report, userId);
  const offer = await accountableOfferById(database, String(report.offer_id));
  return accountableReportReviewPlan(database, report, offer, input);
}

async function acceptAccountableReportByAdmin(database, reportId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const report = await accountableReportById(database, reportId, { session });
      await requireAccountableReportAdmin(database, report, userId, { session });
      if (String(report.status) !== 'submitted') {
        const error = new Error('accountable_report_not_submitted');
        error.status = 409;
        throw error;
      }
      const offer = await accountableOfferById(database, String(report.offer_id), { session });
      const before = await accountableReportDetail(database, report);
      const plan = await accountableReportReviewPlan(database, report, offer, input);
      const note = optionalStringInput(input, 'review_note', null, 1000) ?? optionalStringInput(input, 'note', null, 1000);
      const timestamp = nowSql();
      const reportUpdate = {
        status: 'accepted_by_admin',
        reviewed_at: timestamp,
        reviewed_by: userId,
        review_note: note,
        accepted_total_amount: plan.accepted_total_amount,
        rejected_total_amount: plan.rejected_total_amount,
        accepted_cash_expenses: plan.accepted_cash_expenses,
        accepted_noncash_expenses: plan.accepted_noncash_expenses,
        settlement_status: plan.settlement.status,
        materialized_at: timestamp,
        no_financial_mutation: 1,
        updated_at: timestamp,
      };
      await database.collection('v2_accountable_reports').updateOne({ id: reportId }, { $set: reportUpdate }, { session });
      for (const row of plan.rows) {
        await database.collection('v2_accountable_report_rows').updateOne(
          { id: row.id, report_id: reportId },
          {
            $set: {
              review_status: row.review_status,
              accepted_amount: row.accepted_amount,
              accepted_category_code: row.accepted_category_code,
              payment_method: row.payment_method,
              review_note: row.review_note,
            },
          },
          { session }
        );
      }
      const settlementId = uuid();
      const settlement = {
        id: settlementId,
        workspace_id: String(report.workspace_id),
        offer_id: String(report.offer_id),
        report_id: reportId,
        employee_user_id: Number(report.employee_user_id),
        issued_amount: plan.settlement.issued_amount,
        accepted_cash_expenses: plan.settlement.accepted_cash_expenses,
        accepted_noncash_expenses: plan.settlement.accepted_noncash_expenses,
        expected_remaining: plan.settlement.expected_remaining,
        actual_remaining: plan.settlement.actual_remaining,
        return_due_amount: plan.settlement.return_due_amount,
        reimburse_due_amount: plan.settlement.reimburse_due_amount,
        difference_amount: plan.settlement.difference_amount,
        status: plan.settlement.status,
        resolution_status: plan.settlement.status === 'closed' ? 'resolved' : 'open',
        resolved_amount: '0.00',
        resolved_entry_id: null,
        resolved_at: null,
        resolved_by: null,
        resolution_note: null,
        created_by: userId,
        created_at: timestamp,
        updated_at: null,
      };
      await database.collection('v2_accountable_settlements').insertOne(settlement, { session });
      const afterReport = {
        ...report,
        ...reportUpdate,
      };
      const rows = await database.collection('v2_accountable_report_rows')
        .find({ report_id: reportId }, { session })
        .sort({ row_number: 1 })
        .toArray();
      const after = accountableReportRow(afterReport, rows.map(accountableReportDataRow), accountableSettlementRow(settlement));
      result = {
        report: after,
        settlement: after.settlement || null,
        materialized_entries: [],
      };
      await audit(database, String(report.workspace_id), 'accountable_report', reportId, 'accept_by_admin', before, result, userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function updateWorkspaceAssistantSettings(database, workspaceId, input, userId = USER_ID) {
  await requireWorkspaceAdmin(database, workspaceId, userId);
  const session = mongoClient.startSession();
  try {
    let after = null;
    await session.withTransaction(async () => {
      const existing = await database.collection('v2_workspace_assistant_settings').findOne({ workspace_id: workspaceId }, { session });
      const before = workspaceAssistantSettingsRow(existing, workspaceId);
      const mode = enumInput(
        optionalStringInput(input, 'internet_reference_mode', before.internet_reference_mode, 40),
        ['disabled', 'per_request', 'workspace_enabled'],
        'internet_reference_mode'
      );
      const provider = enumInput(
        optionalStringInput(input, 'provider_key', before.provider_key, 80),
        internetReferenceProviderKeys(),
        'provider_key'
      );
      const retentionDays = Math.max(1, Math.min(365, optionalIntInput(input, 'retention_days', before.retention_days)));
      const enabled = Object.hasOwn(input, 'mr_smith_enabled') ? phpBool(input.mr_smith_enabled) : before.mr_smith_enabled;
      const timestamp = nowSql();

      await database.collection('v2_workspace_assistant_settings').updateOne(
        { workspace_id: workspaceId },
        {
          $set: {
            workspace_id: workspaceId,
            mr_smith_enabled: enabled ? 1 : 0,
            internet_reference_mode: mode,
            provider_key: provider,
            retention_days: retentionDays,
            updated_by: userId,
            updated_at: timestamp,
          },
          $setOnInsert: {
            created_at: timestamp,
          },
        },
        { upsert: true, session }
      );

      const afterRaw = await database.collection('v2_workspace_assistant_settings').findOne({ workspace_id: workspaceId }, { session });
      after = workspaceAssistantSettingsRow(afterRaw, workspaceId);
      await audit(database, workspaceId, 'workspace_assistant_settings', workspaceId, 'update', before, after, userId, { session });
    });
    return after;
  } finally {
    await session.endSession();
  }
}

async function legacyImportReview(database, workspaceId, importId, userId = USER_ID, options = {}) {
  await requireWorkspaceFullReader(database, workspaceId, userId, options);
  const source = await database.collection('v2_import_sources').findOne({ id: importId, workspace_id: workspaceId }, options);
  if (!source) {
    const error = new Error('import_not_found');
    error.status = 404;
    throw error;
  }
  const rows = await database.collection('v2_import_rows')
    .find({ import_source_id: importId }, options)
    .sort({ sheet_name: 1, row_number: 1 })
    .toArray();
  const entryIds = rows.map((row) => row.entry_id).filter(Boolean).map(String);
  const entries = entryIds.length ? await database.collection('v2_entries')
    .find({ id: { $in: entryIds } }, options)
    .toArray() : [];
  const flowIds = Array.from(new Set(entries.map((entry) => String(entry.flow_id)).filter(Boolean)));
  const flows = flowIds.length ? await database.collection('v2_flows')
    .find({ id: { $in: flowIds } }, options)
    .toArray() : [];
  const entryMap = new Map(entries.map((entry) => [String(entry.id), entry]));
  const flowMap = new Map(flows.map((flow) => [String(flow.id), flow]));
  const review = {
    import_id: String(source.id),
    source_file_name: source.file_name === null || source.file_name === undefined ? null : String(source.file_name),
    source_file_id: source.file_id === null || source.file_id === undefined ? null : String(source.file_id),
    source_file_url: source.file_url === null || source.file_url === undefined ? null : String(source.file_url),
    status: String(source.status),
    include_decision: String(source.include_decision),
    reason: source.reason === null || source.reason === undefined ? null : String(source.reason),
    files_detected: 1,
    files_included: String(source.include_decision) === 'included' ? 1 : 0,
    files_excluded: String(source.include_decision) === 'included' ? 0 : 1,
    final_version_decisions: [],
    sheets_scanned: 0,
    rows_scanned: rows.length,
    rows_parsed: 0,
    entries_created: 0,
    rows_ignored: 0,
    rows_unrecognized: 0,
    summary_rows_ignored: 0,
    cash_income_total: 0,
    cash_expense_total: 0,
    card_income_total: 0,
    card_expense_total: 0,
    source_totals: { cash_income: 0, cash_expense: 0, card_income: 0, card_expense: 0 },
    source_summary_totals: { cash_income: 0, cash_expense: 0, card_income: 0, card_expense: 0 },
    normalized_totals: { cash_income: 0, cash_expense: 0, card_income: 0, card_expense: 0 },
    source_total_comparison: { cash_income: 0, cash_expense: 0, card_income: 0, card_expense: 0 },
    months_covered: [],
    duplicate_suspects: [],
    row_traces: [],
  };
  const sheets = new Set();
  const months = new Set();
  const seen = new Set();
  for (const row of rows) {
    sheets.add(String(row.sheet_name));
    const raw = decodeJson(row.raw_json, {});
    const parsed = parseLegacyImportRow(raw, row, seen);
    const status = String(row.parse_status || 'pending');
    if (['parsed', 'imported', 'duplicate_suspect'].includes(status)) review.rows_parsed += 1;
    else if (status === 'summary_ignored') review.summary_rows_ignored += 1;
    else if (status === 'unrecognized') review.rows_unrecognized += 1;
    else review.rows_ignored += 1;
    for (const [sourceKey, sourceAmount] of Object.entries(parsed.source_totals || {})) {
      review[status === 'summary_ignored' ? 'source_summary_totals' : 'source_totals'][sourceKey] += Number(sourceAmount || 0);
    }
    const entry = row.entry_id ? entryMap.get(String(row.entry_id)) : null;
    if (entry) {
      review.entries_created += 1;
      if (!parsed.duplicate_suspect) {
        const flow = flowMap.get(String(entry.flow_id));
        const key = `${flow ? flow.type : 'cash'}_${String(entry.raw_text || '').trim().startsWith('-') ? 'expense' : 'income'}`;
        if (Object.hasOwn(review.normalized_totals, key)) review.normalized_totals[key] += amount(entry.amount) || 0;
        if (entry.date) months.add(String(entry.date).slice(0, 7));
      }
    }
    if (parsed.duplicate_suspect) {
      review.duplicate_suspects.push({
        sheet_name: String(row.sheet_name),
        row_number: Number(row.row_number || 0),
        reason: 'same date, flow, sign, amount, and description',
      });
    }
    review.row_traces.push({
      import_source_id: String(row.import_source_id),
      import_row_id: String(row.id),
      sheet_name: String(row.sheet_name),
      row_number: Number(row.row_number || 0),
      raw_row_data: raw,
      entry_id: row.entry_id === null || row.entry_id === undefined ? null : String(row.entry_id),
      parse_status: status,
      date_source: parsed.date_source || null,
      parse_notes: row.parse_notes || parsed.parse_notes || null,
    });
  }
  review.sheets_scanned = sheets.size;
  review.months_covered = Array.from(months).sort();
  for (const key of Object.keys(review.normalized_totals)) {
    review[`${key}_total`] = review.normalized_totals[key];
  }
  const comparisonSource = Object.values(review.source_summary_totals).reduce((sum, value) => sum + value, 0) > 0
    ? review.source_summary_totals
    : review.source_totals;
  for (const key of Object.keys(review.source_total_comparison)) {
    review.source_total_comparison[key] = comparisonSource[key] - review.normalized_totals[key];
  }
  return review;
}

async function storeLegacyImportRows(database, sourceId, sheets, fileName, fileUpdatedDate, options = {}) {
  const rows = [];
  const filenameDate = legacyFilenameDate(fileName);
  const seen = new Set();
  for (const sheet of sheets) {
    let headers = null;
    let lastDate = null;
    const rowEntries = Object.entries(sheet.rows || {}).sort((a, b) => Number(a[0]) - Number(b[0]));
    for (const [rowNumberText, cells] of rowEntries) {
      const rowNumber = Number(rowNumberText);
      if (headers === null) {
        const candidateHeaders = legacyHeaderMap(cells);
        if (!legacyLooksLikeHeader(candidateHeaders)) continue;
        headers = candidateHeaders;
        continue;
      }
      const raw = legacyRawRow(headers, cells);
      if (!Object.keys(raw).length) continue;
      raw._date_context = {
        inherited_previous_row_date: lastDate,
        filename_date: filenameDate,
        file_updated_date: fileUpdatedDate,
      };
      const date = legacyRowDate(raw, null);
      if (date !== null && String(raw['дата'] || '') !== '') lastDate = date;
      raw._date_context.inherited_previous_row_date = lastDate;
      const parsed = parseLegacyImportRow(raw, {
        id: null,
        sheet_name: sheet.name,
        row_number: rowNumber,
      }, seen);
      rows.push({
        id: uuid(),
        import_source_id: sourceId,
        sheet_name: sheet.name,
        row_number: rowNumber,
        raw_json: JSON.stringify(raw),
        normalized_json: null,
        entry_id: null,
        parse_status: parsed.parse_status,
        parse_notes: parsed.parse_notes,
        created_at: nowSql(),
      });
    }
  }
  if (rows.length) await database.collection('v2_import_rows').insertMany(rows, options);
  return rows;
}

async function createLegacyExcelImport(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let review = null;
    await session.withTransaction(async () => {
      await requireWorkspaceWriter(database, workspaceId, userId, { session });
      const fileName = requireStringInput(input, 'file_name', 255);
      const fileId = optionalStringInput(input, 'file_id', null, 190);
      const fileUrl = optionalStringInput(input, 'file_url', null, 2000);
      const fileUpdatedDate = optionalStringInput(input, 'file_updated_date', null, 20);
      const contentBase64 = requireStringInput(input, 'content_base64', 20000000);
      if (!fileName.toLowerCase().endsWith('.xlsx')) {
        const error = new Error('xlsx_required');
        error.status = 422;
        throw error;
      }
      const decoded = Buffer.from(contentBase64, 'base64');
      if (decoded.toString('base64').replace(/=+$/u, '') !== contentBase64.replace(/\s+/gu, '').replace(/=+$/u, '')) {
        const error = new Error('invalid_base64');
        error.status = 422;
        throw error;
      }
      const sourceId = uuid();
      const excludeReason = legacyExcludeReason(fileName);
      const includeDecision = excludeReason === null ? 'included' : 'excluded_by_title_marker';
      await database.collection('v2_import_sources').insertOne({
        id: sourceId,
        workspace_id: workspaceId,
        source_type: 'excel',
        file_name: fileName,
        file_url: fileUrl,
        file_id: fileId,
        status: excludeReason === null ? 'review_ready' : 'excluded',
        include_decision: includeDecision,
        reason: excludeReason,
        metadata_json: null,
        created_at: nowSql(),
        updated_at: null,
      }, { session });
      if (excludeReason === null) {
        const sheets = readLegacyXlsx(decoded);
        await storeLegacyImportRows(database, sourceId, sheets, fileName, fileUpdatedDate, { session });
      }
      review = await legacyImportReview(database, workspaceId, sourceId, userId, { session });
      await audit(database, workspaceId, 'import_source', sourceId, 'create_import', null, review, userId, { session });
    });
    return review;
  } finally {
    await session.endSession();
  }
}

async function acceptLegacyImport(database, workspaceId, importId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let review = null;
    await session.withTransaction(async () => {
      enumInput(optionalStringInput(input, 'decision', 'accept', 40), ['accept'], 'decision');
      await requireWorkspaceWriter(database, workspaceId, userId, { session });
      const source = await database.collection('v2_import_sources').findOne({ id: importId, workspace_id: workspaceId }, { session });
      if (!source) {
        const error = new Error('import_not_found');
        error.status = 404;
        throw error;
      }
      if (String(source.include_decision) !== 'included') {
        const error = new Error('import_excluded');
        error.status = 422;
        throw error;
      }
      const flows = await flowsByType(database, workspaceId, userId, { session });
      const rows = await database.collection('v2_import_rows')
        .find({ import_source_id: importId }, { session })
        .sort({ sheet_name: 1, row_number: 1 })
        .toArray();
      const seen = new Set();
      for (const row of rows) {
        if (row.entry_id !== null && row.entry_id !== undefined) continue;
        const raw = decodeJson(row.raw_json, {});
        const parsed = parseLegacyImportRow(raw, row, seen);
        if (parsed.entry === null) {
          await updateLegacyImportRowStatus(database, String(row.id), parsed.parse_status, null, parsed.parse_notes, { session });
          continue;
        }
        const flow = flows[parsed.entry.flow_type] || null;
        if (flow === null) {
          await updateLegacyImportRowStatus(database, String(row.id), 'unrecognized', null, 'missing flow', { session });
          continue;
        }
        const status = parsed.duplicate_suspect ? 'duplicate_suspect' : 'imported';
        const entryInput = {
          flow_id: flow.id,
          date: parsed.entry.date,
          raw_text: parsed.entry.raw_text,
          amount: Number(parsed.entry.amount).toFixed(2),
          status,
          source_type: 'import',
          source_id: importId,
          source_row_id: String(row.id),
          matched_rules: [{
            source: 'legacy_excel_import',
            sheet_name: row.sheet_name,
            row_number: Number(row.row_number || 0),
          }],
        };
        if (parsed.entry.category_code !== null && parsed.entry.category_code !== undefined) {
          entryInput.category_code = parsed.entry.category_code;
        }
        const created = await createEntryInSession(database, workspaceId, entryInput, userId, session);
        await updateLegacyImportRowStatus(database, String(row.id), status, String(created.id), parsed.parse_notes, { session });
      }
      await database.collection('v2_import_sources').updateOne(
        { id: importId },
        { $set: { status: 'accepted', updated_at: nowSql() } },
        { session }
      );
      review = await legacyImportReview(database, workspaceId, importId, userId, { session });
      await audit(database, workspaceId, 'import_source', importId, 'accept_import', source, review, userId, { session });
    });
    return review;
  } finally {
    await session.endSession();
  }
}

async function userEmail(database, userId = USER_ID) {
  const user = await database.collection('users').findOne({ id: userId, status: 'active', deleted_at: null });
  if (!user || !user.email) {
    const error = new Error('not_authenticated');
    error.status = 401;
    throw error;
  }
  return String(user.email).trim().toLowerCase();
}

async function reportLocks(database, workspaceId) {
  const batches = await database.collection('v2_report_batches')
    .find({ workspace_id: workspaceId, batch_type: 'operational_fragment', status: { $ne: 'superseded' } })
    .toArray();
  const map = new Map();
  for (const batch of batches) {
    for (const id of decodeJson(batch.source_entry_ids_json, [])) {
      if (!map.has(String(id))) {
        map.set(String(id), {
          report_id: batch.id,
          report_title: batch.title,
          status: batch.status,
          start_date: batch.start_date,
          end_date: batch.end_date,
        });
      }
    }
  }
  return map;
}

async function joinedEntries(database, workspaceId, query) {
  const filter = { workspace_id: workspaceId, archived_at: null };
  if (query.from || query.date_from || query.to || query.date_to) {
    filter.date = {};
    const from = query.from || query.date_from;
    const to = query.to || query.date_to;
    if (from) filter.date.$gte = String(from);
    if (to) filter.date.$lte = String(to);
  } else if (query.year) {
    const year = Number(query.year);
    const month = query.month ? Number(query.month) : null;
    if (month) {
      filter.date = { $gte: `${year}-${String(month).padStart(2, '0')}-01`, $lt: monthEndExclusive(year, month) };
    } else {
      filter.date = { $gte: `${year}-01-01`, $lt: `${year + 1}-01-01` };
    }
  }

  const [entries, flows, categories, actors, workspace, locks] = await Promise.all([
    database.collection('v2_entries').find(filter).sort({ date: 1, created_seq: 1 }).toArray(),
    database.collection('v2_flows').find({ workspace_id: workspaceId }).toArray(),
    database.collection('v2_categories').find({ $or: [{ workspace_id: null }, { workspace_id: workspaceId }] }).toArray(),
    database.collection('v2_actors').find({ workspace_id: workspaceId }).toArray(),
    database.collection('v2_workspaces').findOne({ id: workspaceId }),
    reportLocks(database, workspaceId),
  ]);
  const flowMap = new Map(flows.map((flow) => [String(flow.id), flow]));
  const categoryMap = new Map(categories.map((category) => [String(category.id), category]));
  const actorMap = new Map(actors.map((actor) => [String(actor.id), actor]));
  return entries.map((entry) => {
    const flow = flowMap.get(String(entry.flow_id)) || {};
    const category = entry.category_id ? categoryMap.get(String(entry.category_id)) : null;
    const actor = entry.actor_id ? actorMap.get(String(entry.actor_id)) : null;
    return {
      ...entry,
      workspace_name: workspace ? workspace.name : '',
      flow_type: flow.type || '',
      flow_name: flow.name || '',
      category_code: category ? category.code : null,
      category_name_json: category ? category.name_json : null,
      actor_name: actor ? actor.name : null,
    };
  }).map((entry) => entryRow(entry, locks));
}

async function otherExpenseQueue(database, workspaceId) {
  const entries = await joinedEntries(database, workspaceId, {});
  return entries.filter((entry) => entry.status === 'other_review'
    && entry.entry_type === 'cash_expense'
    && entry.category_code === 'other');
}

async function reportPackageItemRows(database, packageId) {
  const items = await database.collection('v2_report_package_items')
    .find({ package_id: packageId })
    .sort({ item_order: 1 })
    .toArray();
  return items.map((row) => ({
    batch_id: String(row.batch_id),
    html_snapshot_id: row.html_snapshot_id === null || row.html_snapshot_id === undefined ? null : String(row.html_snapshot_id),
    row_number: Number(row.item_order || 0),
    fragment: decodeJson(row.fragment_snapshot_json, []),
    html_snapshot: decodeJson(row.html_snapshot_json, null),
  }));
}

async function reportVersions(database, workspaceId, reportId, reportType) {
  const versions = await database.collection('v2_report_versions')
    .find({ workspace_id: workspaceId, report_type: reportType, report_id: reportId })
    .sort({ version: -1 })
    .toArray();
  return versions.map(reportVersionRow);
}

async function reportPackageDetail(database, row) {
  const details = reportPackageRow(row);
  const items = await reportPackageItemRows(database, String(row.id));
  details.items = items;
  details.fragments = items
    .map((item) => (item.fragment && typeof item.fragment === 'object' && !Array.isArray(item.fragment) ? item.fragment : null))
    .filter(Boolean);
  details.versions = await reportVersions(database, String(row.workspace_id), String(row.id), 'operational_package');
  return details;
}

async function categoryMatrixReport(database, workspaceId, query) {
  await requireWorkspace(database, workspaceId);
  const year = Number(query.year || new Date().getFullYear());
  const months = Object.fromEntries(Array.from({ length: 12 }, (_, index) => [String(index + 1), 0]));
  const rows = new Map();
  const categories = await database.collection('v2_categories')
    .find({ is_active: 1, $or: [{ workspace_id: null }, { workspace_id: workspaceId }] })
    .sort({ sort_order: 1, code: 1 })
    .toArray();
  for (const category of categories) {
    rows.set(String(category.code), {
      category_code: String(category.code),
      category_name: decodeJson(category.name_json, {}),
      direction: String(category.direction),
      months: { ...months },
      breakdown: {},
      total: 0,
    });
  }

  const entries = await joinedEntries(database, workspaceId, { year: String(year) });
  for (const entry of entries) {
    if (!COUNTED_STATUSES.has(entry.status) || entry.amount === null || !entry.category_code) continue;
    const row = rows.get(entry.category_code);
    if (!row) continue;
    const month = String(Number(String(entry.date).slice(5, 7)));
    const value = Number(entry.amount || 0);
    const key = `${entry.flow.type}:${entry.direction}`;
    row.months[month] += value;
    row.total += value;
    row.breakdown[month] = row.breakdown[month] || {};
    row.breakdown[month][key] = (row.breakdown[month][key] || 0) + value;
  }

  return {
    workspace_id: workspaceId,
    year,
    months: Array.from({ length: 12 }, (_, index) => index + 1),
    rows: Array.from(rows.values()),
  };
}

function legacyAmount(value) {
  const text = String(value === null || value === undefined ? '' : value).trim();
  if (!text) return null;
  const normalized = text.replace(/[\s\u00a0]/g, '').replace(',', '.');
  if (!/^[-+]?\d+(?:\.\d+)?$/.test(normalized)) return null;
  return Math.abs(Number(normalized));
}

function decodeXml(value) {
  return String(value || '')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&amp;/g, '&');
}

function xmlAttr(xml, name) {
  const match = String(xml || '').match(new RegExp(`\\s${name}="([^"]*)"`, 'u'));
  return match ? decodeXml(match[1]) : '';
}

function stripXmlTags(xml) {
  return decodeXml(String(xml || '').replace(/<[^>]+>/gu, ''));
}

function xlsxColumnIndex(column) {
  return String(column || '').toUpperCase().split('').reduce((index, char) => (index * 26) + (char.charCodeAt(0) - 64), 0) - 1;
}

function readZipText(dir, filePath) {
  const fullPath = path.join(dir, filePath);
  return fs.existsSync(fullPath) ? fs.readFileSync(fullPath, 'utf8') : '';
}

function xlsxSharedStrings(dir) {
  const xml = readZipText(dir, 'xl/sharedStrings.xml');
  if (!xml) return [];
  const strings = [];
  for (const match of xml.matchAll(/<si\b[^>]*>([\s\S]*?)<\/si>/gu)) {
    const item = match[1];
    const parts = [...item.matchAll(/<t\b[^>]*>([\s\S]*?)<\/t>/gu)].map((part) => decodeXml(part[1]));
    strings.push(parts.length ? parts.join('') : stripXmlTags(item));
  }
  return strings;
}

function xlsxWorkbookSheets(dir) {
  const workbook = readZipText(dir, 'xl/workbook.xml');
  const rels = readZipText(dir, 'xl/_rels/workbook.xml.rels');
  if (!workbook) {
    const error = new Error('xlsx_workbook_missing');
    error.status = 422;
    throw error;
  }
  if (!rels) {
    const error = new Error('xlsx_relations_missing');
    error.status = 422;
    throw error;
  }
  const relTargets = {};
  for (const rel of rels.matchAll(/<Relationship\b([^>]*)\/?>/gu)) {
    const attrs = rel[1];
    relTargets[xmlAttr(attrs, 'Id')] = xmlAttr(attrs, 'Target');
  }
  const sheets = [];
  for (const sheet of workbook.matchAll(/<sheet\b([^>]*)\/?>/gu)) {
    const attrs = sheet[1];
    const relationId = xmlAttr(attrs, 'r:id');
    const target = relTargets[relationId];
    if (!target) continue;
    sheets.push({
      name: xmlAttr(attrs, 'name'),
      path: target.startsWith('/') ? target.slice(1) : `xl/${target.replace(/^xl\//u, '')}`,
    });
  }
  return sheets;
}

function xlsxSheetRows(dir, sheetPath, sharedStrings) {
  const xml = readZipText(dir, sheetPath);
  if (!xml) return {};
  const rows = {};
  for (const rowMatch of xml.matchAll(/<row\b([^>]*)>([\s\S]*?)<\/row>/gu)) {
    const rowNumber = Number(xmlAttr(rowMatch[1], 'r') || 0);
    if (!rowNumber) continue;
    const cells = {};
    for (const cellMatch of rowMatch[2].matchAll(/<c\b([^>]*)>([\s\S]*?)<\/c>/gu)) {
      const attrs = cellMatch[1];
      const ref = xmlAttr(attrs, 'r');
      const column = ref.replace(/[0-9]/gu, '');
      if (!column) continue;
      const type = xmlAttr(attrs, 't');
      let value = '';
      const valueMatch = cellMatch[2].match(/<v\b[^>]*>([\s\S]*?)<\/v>/u);
      if (type === 's') {
        value = sharedStrings[Number(valueMatch ? valueMatch[1] : 0)] || '';
      } else if (type === 'inlineStr') {
        const inline = cellMatch[2].match(/<is\b[^>]*>([\s\S]*?)<\/is>/u);
        value = inline ? stripXmlTags(inline[1]) : '';
      } else {
        value = valueMatch ? decodeXml(valueMatch[1]) : stripXmlTags(cellMatch[2]);
      }
      const trimmed = String(value).trim();
      if (trimmed !== '') cells[xlsxColumnIndex(column)] = trimmed;
    }
    if (Object.keys(cells).length) rows[rowNumber] = cells;
  }
  return rows;
}

function readLegacyXlsx(bytes) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'findesk-v2-xlsx-'));
  const xlsxPath = path.join(dir, 'source.xlsx');
  try {
    fs.writeFileSync(xlsxPath, bytes);
    try {
      childProcess.execFileSync('unzip', ['-qq', xlsxPath, '-d', dir], { stdio: 'ignore' });
    } catch {
      const error = new Error('invalid_xlsx');
      error.status = 422;
      throw error;
    }
    const sharedStrings = xlsxSharedStrings(dir);
    return xlsxWorkbookSheets(dir).map((sheet) => ({
      name: sheet.name,
      rows: xlsxSheetRows(dir, sheet.path, sharedStrings),
    }));
  } finally {
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

function legacyHeaderMap(cells) {
  const headers = {};
  for (const [index, cell] of Object.entries(cells || {})) {
    const normalized = String(cell || '').trim().toLowerCase();
    if (normalized !== '') headers[index] = normalized;
  }
  return headers;
}

function legacyLooksLikeHeader(headers) {
  const values = new Set(Object.values(headers || {}));
  const hasDescription = values.has('описание платежа') || values.has('description') || values.has('описание');
  const hasOldMoneyColumn = ['приход кеш', 'приход кэш', 'расход кеш', 'расход кэш', 'приход карта', 'приход карты', 'расход карта', 'расход карты']
    .some((value) => values.has(value));
  const hasChronologyMoneyColumn = values.has('приход') || values.has('расход');
  return hasDescription && (hasOldMoneyColumn || hasChronologyMoneyColumn);
}

function legacyRawRow(headers, cells) {
  const raw = {};
  for (const [index, header] of Object.entries(headers || {})) {
    const value = String((cells || {})[index] || '').trim();
    if (value !== '') raw[header] = value;
  }
  return raw;
}

function legacyFilenameDate(fileName) {
  let match = String(fileName || '').match(/(20[0-9]{2})[-_. ]?([01]?[0-9])[-_. ]?([0-3]?[0-9])/u);
  if (match) return `${match[1]}-${match[2].padStart(2, '0')}-${match[3].padStart(2, '0')}`;
  match = String(fileName || '').match(/([0-3]?[0-9])[-_. ]([01]?[0-9])[-_. ]([0-9]{2}|20[0-9]{2})/u);
  if (!match) return null;
  const year = Number(match[3]) < 100 ? 2000 + Number(match[3]) : Number(match[3]);
  return `${year}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
}

function legacyExcludeReason(fileName) {
  const text = String(fileName || '').toLowerCase();
  for (const marker of ['не отправлял', 'не отправлено', 'не готово', 'не закончен', 'не закончено', 'не полный', 'неполный', 'черновик', 'draft', 'test']) {
    if (text.includes(marker)) return `title marker: ${marker}`;
  }
  return null;
}

function legacyNormalizeDate(value) {
  const text = String(value === null || value === undefined ? '' : value).trim();
  if (!text) return null;
  if (/^\d+$/.test(text)) {
    const base = Date.UTC(1899, 11, 30);
    const date = new Date(base + Number(text) * 86400000);
    return date.toISOString().slice(0, 10);
  }
  let match = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if (match) return `${match[1]}-${match[2].padStart(2, '0')}-${match[3].padStart(2, '0')}`;
  match = text.match(/^(\d{1,2})[./](\d{1,2})[./](\d{2}|\d{4})$/);
  if (!match) return null;
  const year = Number(match[3]) < 100 ? 2000 + Number(match[3]) : Number(match[3]);
  return `${year}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
}

function legacyRowDate(raw, fallback = null) {
  const candidates = [
    raw['дата'],
    raw.date,
    raw._date_context && raw._date_context.inherited_previous_row_date,
    raw._date_context && raw._date_context.filename_date,
    raw._date_context && raw._date_context.file_updated_date,
    fallback,
  ];
  for (const value of candidates) {
    const date = legacyNormalizeDate(value);
    if (date) return date;
  }
  return null;
}

function legacyDateSource(raw) {
  const sources = {
    row_date: raw['дата'] || raw.date,
    inherited_previous_row_date: raw._date_context && raw._date_context.inherited_previous_row_date,
    filename_date: raw._date_context && raw._date_context.filename_date,
    file_updated_date: raw._date_context && raw._date_context.file_updated_date,
  };
  for (const [source, value] of Object.entries(sources)) {
    if (legacyNormalizeDate(value)) return source;
  }
  return null;
}

function normalizedDictionaryText(text) {
  return String(text || '').toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim();
}

function parseLegacyImportRow(raw, row, seen = new Set()) {
  const description = dictionaryDescription(raw);
  const text = normalizedDictionaryText(description);
  const date = legacyRowDate(raw);
  const amounts = {
    cash_income: legacyAmount(raw['приход кеш'] || raw['приход кэш'] || raw['cash income'] || raw['приход']),
    cash_expense: legacyAmount(raw['расход кеш'] || raw['расход кэш'] || raw['cash expense'] || raw['расход']),
    card_income: legacyAmount(raw['приход карта'] || raw['приход карты'] || raw['card income']),
    card_expense: legacyAmount(raw['расход карта'] || raw['расход карты'] || raw['card expense']),
  };
  const nonZero = Object.entries(amounts).filter(([, value]) => value !== null && Math.abs(value) > 0.0001);
  const sourceTotals = Object.fromEntries(Object.entries(amounts).map(([key, value]) => [key, value || 0]));
  const parsed = (parseStatus, entry, duplicate, notes) => ({
    parse_status: duplicate ? 'duplicate_suspect' : parseStatus,
    entry,
    source_totals: sourceTotals,
    date_source: entry ? entry.date_source : null,
    duplicate_suspect: duplicate,
    parse_notes: notes || null,
  });
  if (/свод|summary/u.test(text) || raw['сводные данные'] !== undefined) return parsed('summary_ignored', null, false, 'summary row ignored');
  if (/информационная|не считается|comment|info|остаток|переход|opening balance|balance brought forward/u.test(text)) {
    return parsed('ignored', null, false, /остаток|переход/u.test(text) ? 'opening balance row ignored' : 'info row ignored');
  }
  if (!date || nonZero.length === 0) return parsed('unrecognized', null, false, 'missing date or amount');
  if (nonZero.length > 1) return parsed('unrecognized', null, false, 'multiple money columns in one row');
  const [kind, value] = nonZero[0];
  const flowType = kind.startsWith('card_') ? 'card' : 'cash';
  const sign = kind.endsWith('_expense') ? '-' : '+';
  const categoryCode = legacyCategoryCode(description, flowType, sign);
  const rawText = `${sign}${Number(value).toFixed(2)}${description ? ` ${description}` : ' imported row'}`;
  const duplicateKey = [date, flowType, sign, Number(value).toFixed(2), description.toLowerCase()].join('|');
  const duplicate = seen.has(duplicateKey);
  seen.add(duplicateKey);
  return parsed('parsed', {
    date,
    date_source: legacyDateSource(raw),
    flow_type: flowType,
    raw_text: rawText,
    amount: Number(value),
    category_code: categoryCode,
  }, duplicate, duplicate ? 'duplicate suspect' : null);
}

function legacyCategoryCode(description, flowType, sign) {
  const text = normalizedDictionaryText(description);
  if (text.includes('снял с карты') || text.includes('cash topup') || text.includes('topup from card')) return 'cash_topup_from_card';
  if (sign === '+' && /чартер|оплата чартера|аренд[^,.;]*яхт|ареда яхты|яхт[^,.;]*аренд|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)/u.test(text)) {
    return 'commercial_income';
  }
  if (sign === '+') return 'non_commercial_income';
  if (text.includes('netflix')) return 'media_comms';
  if (/заправ|топлив|fuel/u.test(text) && !/авто|машин|car/u.test(text)) return 'fuel';
  if (flowType === 'cash' && sign === '-' && (text.includes('какая-то штука') || text.includes('unknown'))) return 'other';
  return null;
}

async function existingLegacyEntryKeys(database, workspaceId, options = {}) {
  const entries = await database.collection('v2_entries')
    .find({
      workspace_id: workspaceId,
      archived_at: null,
      sign: { $ne: null },
      amount: { $ne: null },
    }, options)
    .toArray();
  const flowIds = [...new Set(entries.map((entry) => entry.flow_id).filter(Boolean))];
  const flows = flowIds.length
    ? await database.collection('v2_flows').find({ id: { $in: flowIds } }, options).toArray()
    : [];
  const flowMap = new Map(flows.map((flow) => [String(flow.id), flow]));
  const seen = new Set();
  for (const entry of entries) {
    const flow = flowMap.get(String(entry.flow_id));
    if (!flow) continue;
    const description = String(entry.raw_text || '').replace(/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?\s*/u, '').trim();
    seen.add([
      String(entry.date),
      String(flow.type),
      String(entry.sign),
      Number(entry.amount).toFixed(2),
      description.toLowerCase(),
    ].join('|'));
  }
  return seen;
}

async function flowsByType(database, workspaceId, userId = USER_ID, options = {}) {
  await requireWorkspaceFullReader(database, workspaceId, userId, options);
  const flows = await database.collection('v2_flows')
    .find({ workspace_id: workspaceId }, options)
    .sort({ is_default: -1, type: 1, name: 1 })
    .toArray();
  return Object.fromEntries(flows.map((flow) => [String(flow.type), flowRow(flow)]));
}

async function updateLegacyImportRowStatus(database, rowId, status, entryId, notes, options = {}) {
  await database.collection('v2_import_rows').updateOne(
    { id: rowId },
    { $set: { parse_status: status, entry_id: entryId, parse_notes: notes } },
    options
  );
}

async function accountableReportRows(database, reportId) {
  const rows = await database.collection('v2_accountable_report_rows')
    .find({ report_id: reportId })
    .sort({ row_number: 1 })
    .toArray();
  return rows.map(accountableReportDataRow);
}

async function accountableSettlementForReport(database, reportId) {
  const settlement = await database.collection('v2_accountable_settlements').findOne({ report_id: reportId });
  return accountableSettlementRow(settlement);
}

async function accountableReportDetail(database, report) {
  const [rows, settlement] = await Promise.all([
    accountableReportRows(database, String(report.id)),
    accountableSettlementForReport(database, String(report.id)),
  ]);
  return accountableReportRow(report, rows, settlement);
}

async function accountableReportEntryLinks(database, reportId, options = {}) {
  const links = await database.collection('v2_accountable_report_entry_links')
    .find({ report_id: reportId }, options)
    .sort({ created_at: 1, id: 1 })
    .toArray();
  return links.map((row) => ({
    id: String(row.id),
    workspace_id: String(row.workspace_id),
    report_id: String(row.report_id),
    report_row_id: String(row.report_row_id),
    entry_id: String(row.entry_id),
    idempotency_key: String(row.idempotency_key),
    cash_effect: String(row.cash_effect),
    payment_method: String(row.payment_method),
    accepted_amount: amount(row.accepted_amount) || 0,
    category_code: String(row.category_code),
    created_by: row.created_by === null || row.created_by === undefined ? null : Number(row.created_by),
    created_at: row.created_at || null,
  }));
}

async function accountableMaterializationResult(database, reportId, options = {}) {
  const report = await database.collection('v2_accountable_reports').findOne({ id: reportId }, options);
  if (!report) {
    const error = new Error('accountable_report_not_found');
    error.status = 404;
    throw error;
  }
  const links = await accountableReportEntryLinks(database, reportId, options);
  return {
    report_id: reportId,
    status: report.ledger_materialization_status || 'not_materialized',
    ledger_materialized_at: report.ledger_materialized_at || null,
    ledger_materialized_by: report.ledger_materialized_by === null || report.ledger_materialized_by === undefined ? null : Number(report.ledger_materialized_by),
    ledger_materialization_hash: report.ledger_materialization_hash || null,
    policy: 'cash_effect_none_category_projection',
    entry_count: links.length,
    entry_ids: links.map((link) => String(link.entry_id)).filter(Boolean),
    links,
  };
}

function accountableReportMaterializationIdempotencyKey(row, acceptedAmount, categoryCode, paymentMethod) {
  return crypto.createHash('sha256').update([
    'accountable_report_row',
    'v1',
    String(row.id),
    moneyString(acceptedAmount),
    String(categoryCode),
    String(paymentMethod),
    'cash_effect_none',
  ].join(':')).digest('hex');
}

async function accountableReportMaterializationPlan(database, report) {
  if (String(report.status) !== 'accepted_by_admin') {
    const error = new Error('accountable_report_not_accepted_by_admin');
    error.status = 409;
    throw error;
  }
  const rows = [];
  let eligibleCount = 0;
  let total = 0;
  for (const row of await accountableReportRows(database, String(report.id))) {
    const status = String(row.review_status || 'pending_review');
    const acceptedAmount = row.accepted_amount === null || row.accepted_amount === undefined ? 0 : Number(row.accepted_amount);
    const categoryCode = row.accepted_category_code || row.category_code || null;
    const paymentMethod = row.payment_method || null;
    const materializable = ['accepted', 'adjusted'].includes(status)
      && acceptedAmount > 0
      && categoryCode !== null
      && paymentMethod !== null;
    if (materializable) {
      eligibleCount += 1;
      total += acceptedAmount;
    }
    rows.push({
      id: row.id,
      row_number: row.row_number,
      expense_date: row.expense_date,
      description: row.description,
      review_status: status,
      accepted_amount: acceptedAmount,
      category_code: categoryCode,
      payment_method: paymentMethod,
      cash_effect: 'none',
      materializable,
      existing_entry_id: row.operational_entry_id || null,
      idempotency_key: accountableReportMaterializationIdempotencyKey(row, acceptedAmount, categoryCode, paymentMethod),
    });
  }

  return {
    report_id: String(report.id),
    workspace_id: String(report.workspace_id),
    currency: String(report.currency),
    policy: 'cash_effect_none_category_projection',
    eligible_row_count: eligibleCount,
    projected_total_amount: Number(total.toFixed(2)),
    cash_delta: 0,
    card_delta: 0,
    rows,
  };
}

async function previewAccountableReportMaterialization(database, reportId, userId = USER_ID) {
  const report = await accountableReportById(database, reportId);
  await requireAccountableReportAdmin(database, report, userId);
  return accountableReportMaterializationPlan(database, report);
}

async function categoryByCode(database, workspaceId, code, options = {}) {
  const categories = await database.collection('v2_categories')
    .find({ code, is_active: 1, $or: [{ workspace_id: null }, { workspace_id: workspaceId }] }, options)
    .toArray();
  categories.sort((a, b) => {
    const aLocal = String(a.workspace_id || '') === workspaceId ? 0 : 1;
    const bLocal = String(b.workspace_id || '') === workspaceId ? 0 : 1;
    return aLocal - bLocal;
  });
  if (!categories[0]) {
    const error = new Error('unknown_category');
    error.status = 422;
    throw error;
  }
  return categories[0];
}

function closedMonthRecalculationConfirmed(input) {
  const decision = optionalStringInput(input, 'closed_month_decision', null, 40);
  if (decision === null) return false;
  enumInput(decision, ['recalculate_chain'], 'closed_month_decision');
  return true;
}

async function guardWorkspaceMonthIsOpen(database, workspaceId, date, input = {}, options = {}) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(date))) {
    const error = new Error('invalid_date');
    error.status = 422;
    throw error;
  }
  const year = Number(String(date).slice(0, 4));
  const month = Number(String(date).slice(5, 7));
  const closure = await database.collection('v2_monthly_closures').findOne({ workspace_id: workspaceId, year, month }, options);
  if (!closure || !bool(closure.is_closed)) return;
  if (closedMonthRecalculationConfirmed(input)) return;
  const error = new Error(JSON.stringify({
    error: 'closed_month_requires_decision',
    year,
    month,
    choices: ['create_correction', 'recalculate_chain', 'cancel'],
  }));
  error.status = 409;
  throw error;
}

async function accountableProjectionFlowForWorkspace(database, workspaceId, userId = USER_ID, options = {}) {
  const existing = await database.collection('v2_flows')
    .find({ workspace_id: workspaceId, type: 'accountable' }, options)
    .sort({ is_default: -1, created_at: 1, id: 1 })
    .limit(1)
    .toArray();
  if (existing[0]) return flowRow(existing[0]);
  return createDefaultFlow(database, workspaceId, 'Accountable reports', 'accountable', false, false, '0.00', options);
}

async function nextEntrySeq(database, options = {}) {
  const latest = await database.collection('v2_entries')
    .find({}, options)
    .sort({ created_seq: -1 })
    .limit(1)
    .toArray();
  return Number((latest[0] && latest[0].created_seq) || 0) + 1;
}

async function cashFlowForWorkspace(database, workspaceId, userId = USER_ID, options = {}) {
  await requireWorkspaceFullReader(database, workspaceId, userId, options);
  const flows = await database.collection('v2_flows')
    .find({ workspace_id: workspaceId }, options)
    .sort({ is_default: -1, type: 1, name: 1 })
    .toArray();
  const row = flows.find((flow) => String(flow.type) === 'cash' && bool(flow.has_live_balance));
  return row ? flowRow(row) : null;
}

async function recalculateCashFlowBalance(database, flowId, options = {}) {
  const flow = await database.collection('v2_flows').findOne({ id: flowId }, options);
  if (!flow || String(flow.type) !== 'cash' || !bool(flow.has_live_balance)) {
    await database.collection('v2_entries').updateMany({ flow_id: flowId }, { $set: { balance_after: null } }, options);
    return;
  }
  let balance = amount(flow.opening_balance) || 0;
  const entries = await database.collection('v2_entries')
    .find({ flow_id: flowId, archived_at: null }, options)
    .sort({ date: 1, created_seq: 1 })
    .toArray();
  const operations = [];
  for (const entry of entries) {
    const delta = cashBalanceDelta(entry);
    const nextBalance = delta === null ? null : Number((balance + delta).toFixed(2));
    if (delta !== null) balance = nextBalance;
    const balanceAfter = nextBalance === null ? null : moneyString(nextBalance);
    if ((entry.balance_after ?? null) === balanceAfter) continue;
    operations.push({
      updateOne: {
        filter: { id: entry.id },
        update: { $set: { balance_after: balanceAfter } },
      },
    });
  }
  if (operations.length > 0) {
    await database.collection('v2_entries').bulkWrite(operations, { ...options, ordered: true });
  }
}

async function flowForWorkspace(database, workspaceId, flowId, options = {}) {
  const flow = await database.collection('v2_flows').findOne({ id: flowId, workspace_id: workspaceId }, options);
  if (!flow) {
    const error = new Error('flow_not_found');
    error.status = 404;
    throw error;
  }
  return flowRow(flow);
}

function normalizeRawEntryMoney(rawText) {
  const match = String(rawText || '').match(/^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)/u);
  if (!match) return null;
  return {
    sign: match[1],
    amount: moneyString(String(match[2]).replace(',', '.')),
  };
}

function assertValidMonth(year, month) {
  if (!Number.isInteger(year) || year < 2000 || year > 2100) {
    const error = new Error('invalid_year');
    error.status = 422;
    throw error;
  }
  if (!Number.isInteger(month) || month < 1 || month > 12) {
    const error = new Error('invalid_month');
    error.status = 422;
    throw error;
  }
}

function strictSignedAmount(rawText, key = 'amount') {
  const money = normalizeRawEntryMoney(rawText);
  if (!money) {
    const error = new Error(`invalid_${key}`);
    error.status = 422;
    throw error;
  }
  return {
    sign: money.sign,
    amount: money.amount,
    direction: money.sign === '+' ? 'in' : 'out',
  };
}

function classificationDecision(rawText, flowType, sign, categoryCode, status, matchedRules) {
  const matchedSignals = [];
  for (const rule of matchedRules) {
    if (rule && rule.category_code) matchedSignals.push({ type: 'category', category_code: rule.category_code, pattern: rule.pattern || null });
  }
  const blockers = [];
  if (!categoryCode && sign) blockers.push('category_missing');
  const confidence = categoryCode ? 0.7 : 0.3;
  return {
    confidence,
    status,
    category_code: categoryCode,
    review_reason: categoryCode ? null : 'other_review',
    matched_signals: matchedSignals,
    blockers,
    flow_type: flowType,
    sign,
    raw_text: rawText,
  };
}

function replaceClassificationDecision(rules, decision) {
  return rules
    .filter((rule) => !(rule && rule.classification_decision))
    .concat([{ source: 'atlas_entry_normalizer', classification_decision: decision }]);
}

function normalizeEntryInput(flow, input, fallback = {}) {
  const rawText = requireStringInput(input, 'raw_text', 2000);
  const sourceType = enumInput(optionalStringInput(input, 'source_type', fallback.source_type || 'manual', 40), ['manual', 'import', 'assistant', 'correction', 'accountable_report'], 'source_type');
  const money = normalizeRawEntryMoney(rawText);
  let sign = money ? money.sign : null;
  let parsedAmount = money ? money.amount : null;
  let direction = 'none';
  let entryType = 'unrecognized';
  let status = 'unrecognized';
  let categoryCode = Object.hasOwn(input, 'category_code') ? optionalStringInput(input, 'category_code', null, 80) : (fallback.category_code || null);
  let matchedRules = Array.isArray(input.matched_rules) ? input.matched_rules : (Array.isArray(fallback.matched_rules) ? fallback.matched_rules : []);

  if (sign !== null) {
    direction = sign === '+' ? 'in' : 'out';
    entryType = `${flow.type}:${sign}` === 'cash:+' ? 'cash_income'
      : `${flow.type}:${sign}` === 'cash:-' ? 'cash_expense'
        : `${flow.type}:${sign}` === 'card:+' ? 'card_income'
          : `${flow.type}:${sign}` === 'card:-' ? 'card_expense'
            : 'assistant_pending';
    status = flow.type === 'assistant_journal' ? 'assistant_pending' : 'recognized';
    if (flow.type === 'card' && sign === '+' && sourceType !== 'correction' && sourceType !== 'import') {
      parsedAmount = null;
      direction = 'none';
      entryType = 'unrecognized';
      status = 'unrecognized';
    }
  }

  if (sign !== null && !(flow.type === 'card' && sign === '+' && sourceType !== 'correction') && Object.hasOwn(input, 'amount')) {
    parsedAmount = nullableAmount(input.amount);
  }

  let category = null;
  if (categoryCode) {
    matchedRules = matchedRules.concat([{ source: 'manual_category', category_code: categoryCode }]);
  } else if (sign !== null && !(flow.type === 'card' && sign === '+' && sourceType !== 'correction')) {
    const guess = dictionaryCategoryGuess(rawText.replace(/^([+-])\s*[0-9]+(?:[.,][0-9]{1,2})?/u, '').trim(), flow.type, sign);
    if (guess.category_code) {
      categoryCode = guess.category_code;
      matchedRules = matchedRules.concat([{ source: 'atlas_dictionary_guess', category_code: categoryCode, pattern: guess.pattern }]);
    }
  }

  if (sign !== null) {
    const markers = dictionarySemanticMarkers(rawText, categoryCode);
    if (markers.length) matchedRules = matchedRules.concat([{ source: 'atlas_semantic_markers', semantic_markers: markers }]);
  }
  const finalStatus = sign === null || (flow.type === 'card' && sign === '+' && !['correction', 'import'].includes(sourceType))
    ? status
    : enumInput(optionalStringInput(input, 'status', fallback.status || status, 40), ['recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect'], 'status');
  const decision = sign === null ? null : classificationDecision(rawText, flow.type, sign, categoryCode, finalStatus, matchedRules);
  if (decision) matchedRules = replaceClassificationDecision(matchedRules, decision);
  const confidence = nullableAmount(input.confidence ?? fallback.confidence ?? (decision ? decision.confidence : null));
  return {
    date: Object.hasOwn(input, 'date') ? requireDateInput(input, 'date') : (fallback.date || optionalDateInput(input, 'date', new Date().toISOString().slice(0, 10))),
    raw_text: rawText,
    sign,
    amount: parsedAmount,
    direction,
    entry_type: entryType,
    actor_id: fallback.actor_id || null,
    actor_name: null,
    category_code: categoryCode,
    category,
    status: finalStatus,
    source_type: sourceType,
    source_id: optionalStringInput(input, 'source_id', fallback.source_id || null, 36),
    source_row_id: optionalStringInput(input, 'source_row_id', fallback.source_row_id || null, 36),
    notes: optionalStringInput(input, 'notes', fallback.notes || null, 2000),
    confidence,
    matched_rules: matchedRules,
  };
}

async function normalizeEntryInputWithCategory(database, workspaceId, flow, input, fallback = {}, options = {}) {
  const normalized = normalizeEntryInput(flow, input, fallback);
  if (normalized.category_code) {
    normalized.category = await categoryByCode(database, workspaceId, normalized.category_code, options);
  }
  return normalized;
}

function entryPreviewRow(workspaceId, flow, normalized) {
  const matchedRules = normalized.matched_rules || [];
  const markers = semanticMarkersFromRules(matchedRules);
  const decision = classificationDecisionFromRules(matchedRules);
  const accounting = accountingClassification(normalized.category_code || null, markers, normalized.raw_text);
  const settlement = lowerAccountingSettlementEntry(
    normalized.raw_text,
    normalized.direction,
    normalized.amount === null || normalized.amount === undefined ? null : Number(normalized.amount),
    accounting,
    normalized.actor_name || null
  );
  return {
    workspace_id: workspaceId,
    flow: { id: flow.id, type: flow.type, name: flow.name },
    date: normalized.date,
    raw_text: normalized.raw_text,
    sign: normalized.sign,
    amount: amount(normalized.amount),
    direction: normalized.direction,
    entry_type: normalized.entry_type,
    actor: normalized.actor_name ? { id: null, name: normalized.actor_name } : null,
    category_code: normalized.category_code || null,
    category_name: normalized.category ? decodeJson(normalized.category.name_json, null) : null,
    status: normalized.status,
    source_type: normalized.source_type,
    notes: normalized.notes || null,
    confidence: amount(normalized.confidence),
    review_reason: decision.review_reason || null,
    matched_signals: decision.matched_signals || [],
    blockers: decision.blockers || [],
    classification_decision: decision,
    accounting_section: accounting.section,
    accounting_type: accounting.type,
    accounting_label: accounting.label,
    settlement_counterparty: settlement.counterparty,
    settlement_effect: settlement.effect,
    settlement_direction: settlement.direction,
    semantic_markers: markers,
    matched_rules: matchedRules,
    will_save: false,
  };
}

async function createSettlementCashEntry(database, workspaceId, flow, input, userId = USER_ID, options = {}) {
  await requireWorkspaceWriter(database, workspaceId, userId, options);
  const date = optionalDateInput(input, 'date', new Date().toISOString().slice(0, 10));
  await guardWorkspaceMonthIsOpen(database, workspaceId, date, input, options);
  const rawText = requireStringInput(input, 'raw_text', 2000);
  const match = rawText.match(/^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)/u);
  if (!match) {
    const error = new Error('invalid_raw_text');
    error.status = 422;
    throw error;
  }
  const sign = match[1];
  const normalizedAmount = moneyString(String(match[2]).replace(',', '.'));
  const direction = sign === '+' ? 'in' : 'out';
  const entryType = sign === '+' ? 'cash_income' : 'cash_expense';
  const timestamp = nowSql();
  const matchedRules = Array.isArray(input.matched_rules) ? input.matched_rules : [];
  const entry = {
    id: uuid(),
    workspace_id: workspaceId,
    flow_id: flow.id,
    created_by: userId,
    actor_id: null,
    date,
    raw_text: rawText,
    sign,
    amount: normalizedAmount,
    direction,
    entry_type: entryType,
    category_id: null,
    status: enumInput(optionalStringInput(input, 'status', 'recognized', 40), ['recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect'], 'status'),
    source_type: enumInput(optionalStringInput(input, 'source_type', 'manual', 40), ['manual', 'import', 'assistant', 'correction', 'accountable_report'], 'source_type'),
    source_id: optionalStringInput(input, 'source_id', null, 36),
    source_row_id: optionalStringInput(input, 'source_row_id', null, 36),
    notes: optionalStringInput(input, 'notes', null, 2000),
    confidence: null,
    matched_rules_json: JSON.stringify(matchedRules),
    balance_after: null,
    archived_at: null,
    created_seq: await nextEntrySeq(database, options),
    created_at: timestamp,
    updated_at: null,
  };
  await database.collection('v2_entries').insertOne(entry, options);
  await recalculateCashFlowBalance(database, flow.id, options);
  const refreshed = await database.collection('v2_entries').findOne({ id: entry.id }, options);
  const row = entryRow({
    ...refreshed,
    flow_type: flow.type,
    flow_name: flow.name,
    category_code: null,
    category_name_json: null,
    actor_name: null,
  });
  await audit(database, workspaceId, 'entry', row.id, 'create', null, row, userId, options);
  return row;
}

async function accountableReportEntryLinksByRow(database, reportId, options = {}) {
  const links = await accountableReportEntryLinks(database, reportId, options);
  return new Map(links.map((link) => [String(link.report_row_id), link]));
}

async function materializeAccountableReport(database, reportId, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const report = await accountableReportById(database, reportId, { session });
      await requireAccountableReportAdmin(database, report, userId, { session });
      if (String(report.status) !== 'accepted_by_admin') {
        const error = new Error('accountable_report_not_accepted_by_admin');
        error.status = 409;
        throw error;
      }
      const before = await accountableMaterializationResult(database, reportId, { session });
      const plan = await accountableReportMaterializationPlan(database, report);
      if (plan.eligible_row_count < 1) {
        const error = new Error('accountable_report_no_materializable_rows');
        error.status = 422;
        throw error;
      }

      const existing = await accountableReportEntryLinksByRow(database, reportId, { session });
      const createdEntries = [];
      const timestamp = nowSql();
      const flow = await accountableProjectionFlowForWorkspace(database, String(report.workspace_id), userId, { session });
      for (const row of plan.rows) {
        if (!row.materializable || existing.has(String(row.id))) continue;
        const category = await categoryByCode(database, String(report.workspace_id), String(row.category_code), { session });
        await guardWorkspaceMonthIsOpen(database, String(report.workspace_id), String(row.expense_date), {}, { session });
        const entryId = uuid();
        const matchedRules = [{
          source: 'accountable_report_materialization',
          report_id: reportId,
          report_row_id: row.id,
          offer_id: String(report.offer_id),
          payment_method: row.payment_method,
          cash_effect: 'none',
        }];
        const entry = {
          id: entryId,
          workspace_id: String(report.workspace_id),
          flow_id: flow.id,
          created_by: userId,
          actor_id: null,
          date: String(row.expense_date),
          raw_text: `-${moneyString(row.accepted_amount)} ${String(row.description)}`,
          sign: '-',
          amount: moneyString(row.accepted_amount),
          direction: 'out',
          entry_type: 'accountable_expense',
          category_id: String(category.id),
          status: 'accepted',
          source_type: 'accountable_report',
          source_id: null,
          source_row_id: null,
          notes: 'Projection from employee accountable report. Cash effect: none.',
          confidence: '1.000',
          matched_rules_json: JSON.stringify(matchedRules),
          balance_after: null,
          archived_at: null,
          created_seq: await nextEntrySeq(database, { session }),
          created_at: timestamp,
          updated_at: null,
        };
        await database.collection('v2_entries').insertOne(entry, { session });
        await database.collection('v2_accountable_report_entry_links').insertOne({
          id: uuid(),
          workspace_id: String(report.workspace_id),
          report_id: reportId,
          report_row_id: row.id,
          entry_id: entryId,
          idempotency_key: row.idempotency_key,
          cash_effect: 'none',
          payment_method: row.payment_method,
          accepted_amount: moneyString(row.accepted_amount),
          category_code: String(row.category_code),
          created_by: userId,
          created_at: timestamp,
        }, { session });
        await database.collection('v2_accountable_report_rows').updateOne(
          { id: row.id, report_id: reportId },
          { $set: { operational_entry_id: entryId } },
          { session }
        );
        createdEntries.push(entryRow({
          ...entry,
          flow_type: flow.type,
          flow_name: flow.name,
          category_code: String(category.code),
          category_name_json: category.name_json,
          actor_name: null,
        }));
      }

      const afterLinks = await accountableReportEntryLinks(database, reportId, { session });
      const hash = crypto.createHash('sha256')
        .update(JSON.stringify(afterLinks.map((link) => String(link.idempotency_key))))
        .digest('hex');
      const reportUpdate = {
        ledger_materialization_status: 'materialized',
        ledger_materialized_at: report.ledger_materialized_at || timestamp,
        ledger_materialized_by: report.ledger_materialized_by || userId,
        ledger_materialization_hash: hash,
        updated_at: timestamp,
      };
      await database.collection('v2_accountable_reports').updateOne({ id: reportId }, { $set: reportUpdate }, { session });
      const after = await accountableMaterializationResult(database, reportId, { session });
      result = {
        materialization: after,
        created_entries: createdEntries,
      };
      await audit(database, String(report.workspace_id), 'accountable_report', reportId, 'ledger_project', before, after, userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function entryDetail(database, entryId, userId = USER_ID, options = {}) {
  const entry = await database.collection('v2_entries').findOne({ id: entryId, archived_at: null }, options);
  if (!entry) {
    const error = new Error('entry_not_found');
    error.status = 404;
    throw error;
  }
  const access = await workspaceAccess(database, String(entry.workspace_id), userId, options);
  if (!access.can_read_entries) {
    const error = new Error('entry_not_found');
    error.status = 404;
    throw error;
  }
  const [flow, category, actor, locks] = await Promise.all([
    database.collection('v2_flows').findOne({ id: entry.flow_id }, options),
    entry.category_id ? database.collection('v2_categories').findOne({ id: entry.category_id }, options) : null,
    entry.actor_id ? database.collection('v2_actors').findOne({ id: entry.actor_id }, options) : null,
    reportLocks(database, String(entry.workspace_id)),
  ]);
  return entryRow({
    ...entry,
    flow_type: flow ? flow.type : '',
    flow_name: flow ? flow.name : '',
    category_code: category ? category.code : null,
    category_name_json: category ? category.name_json : null,
    actor_name: actor ? actor.name : null,
  }, locks);
}

async function activeReportLockForEntry(database, entry, options = {}) {
  const batches = await database.collection('v2_report_batches')
    .find({
      workspace_id: String(entry.workspace_id),
      batch_type: 'operational_fragment',
      status: { $in: ['created', 'sent', 'requires_update', 'returned_for_revision'] },
    }, options)
    .sort({ start_date: 1, created_at: 1, id: 1 })
    .toArray();
  for (const batch of batches) {
    if (decodeJson(batch.source_entry_ids_json, []).map(String).includes(String(entry.id))) {
      return {
        report_id: String(batch.id),
        report_title: batch.title || null,
        status: String(batch.status),
        start_date: batch.start_date || null,
        end_date: batch.end_date || null,
      };
    }
  }
  return null;
}

function reportFragmentDecisionConfirmed(input) {
  const decision = optionalStringInput(input, 'report_fragment_decision', null, 40);
  if (decision === null) return false;
  enumInput(decision, ['recalculate_fragment'], 'report_fragment_decision');
  return true;
}

async function guardEntryReportLock(database, entry, input = {}, options = {}) {
  if (reportFragmentDecisionConfirmed(input)) return;
  const lock = await activeReportLockForEntry(database, entry, options);
  if (lock === null) return;
  const error = new Error(JSON.stringify({
    error: 'report_fragment_requires_decision',
    report: lock,
    choices: ['recalculate_fragment', 'cancel'],
  }));
  error.status = 409;
  throw error;
}

function guardAccountableProjectionEntryMutable(entry) {
  if (String(entry.source_type || '') !== 'accountable_report') return;
  const error = new Error('accountable_projection_entry_immutable');
  error.status = 409;
  throw error;
}

async function markOperationalReportsRequiringUpdateForEntry(database, entry, userId, reason, options = {}) {
  const workspaceId = String(entry.workspace_id || '');
  const entryDate = String(entry.date || '');
  if (!workspaceId || !entryDate) return [];
  const fragments = await database.collection('v2_report_batches')
    .find({
      workspace_id: workspaceId,
      batch_type: 'operational_fragment',
      status: { $in: ['created', 'sent'] },
      end_date: { $gte: entryDate },
    }, options)
    .project({ id: 1, title: 1, status: 1, start_date: 1, end_date: 1 })
    .sort({ start_date: 1, created_at: 1 })
    .toArray();
  if (!fragments.length) return [];
  const fragmentIds = fragments.map((fragment) => String(fragment.id));
  await database.collection('v2_report_batches').updateMany(
    { workspace_id: workspaceId, batch_type: 'operational_fragment', status: { $in: ['created', 'sent'] }, id: { $in: fragmentIds } },
    { $set: { status: 'requires_update', updated_at: nowSql() } },
    options
  );
  const packages = await database.collection('v2_report_packages')
    .find({ workspace_id: workspaceId, status: { $in: ['created', 'sent'] } }, options)
    .project({ id: 1, fragment_ids_json: 1 })
    .toArray();
  const fragmentLookup = new Set(fragmentIds);
  const packageIds = packages
    .filter((pack) => decodeJson(pack.fragment_ids_json, []).map(String).some((id) => fragmentLookup.has(id)))
    .map((pack) => String(pack.id));
  if (packageIds.length) {
    await database.collection('v2_report_packages').updateMany(
      { workspace_id: workspaceId, status: { $in: ['created', 'sent'] }, id: { $in: packageIds } },
      { $set: { status: 'requires_update', updated_at: nowSql() } },
      options
    );
  }
  const after = {
    entry_id: String(entry.id),
    entry_date: entryDate,
    reason,
    status: 'requires_update',
    fragment_ids: fragmentIds,
    package_ids: packageIds,
  };
  await audit(database, workspaceId, 'report_batch', String(entry.id), 'operational_reports_require_update', { entry, reports: fragments }, after, userId, options);
  return fragmentIds;
}

function entryCreateInput(input) {
  const allowed = new Set(['flow_id', 'date', 'raw_text', 'category_code', 'amount', 'closed_month_decision']);
  return Object.fromEntries(Object.entries(input || {}).filter(([key]) => allowed.has(key)));
}

async function previewEntryParse(database, workspaceId, input, userId = USER_ID) {
  await requireWorkspaceWriter(database, workspaceId, userId);
  const flow = await flowForWorkspace(database, workspaceId, requireStringInput(input, 'flow_id', 36));
  const normalized = await normalizeEntryInputWithCategory(database, workspaceId, flow, input);
  return entryPreviewRow(workspaceId, flow, normalized);
}

async function createEntry(database, workspaceId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let created = null;
    await session.withTransaction(async () => {
      created = await createEntryInSession(database, workspaceId, input, userId, session);
    });
    return created;
  } finally {
    await session.endSession();
  }
}

async function attachmentForUser(database, attachmentId, userId = USER_ID, options = {}) {
  const attachment = await database.collection('v2_attachments').findOne({ id: attachmentId }, options);
  if (!attachment) {
    const error = new Error('attachment_not_found');
    error.status = 404;
    throw error;
  }
  const entry = await database.collection('v2_entries').findOne({ id: attachment.entry_id, archived_at: null }, options);
  if (!entry) {
    const error = new Error('attachment_not_found');
    error.status = 404;
    throw error;
  }
  await requireWorkspaceFullReader(database, String(entry.workspace_id), userId, options);
  return { attachment: attachmentRow(attachment), entry };
}

async function createEntryAttachment(database, entryId, input, userId = USER_ID) {
  let absolutePath = null;
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const entryRaw = await database.collection('v2_entries').findOne({ id: entryId, archived_at: null }, { session });
      if (!entryRaw) {
        const error = new Error('entry_not_found');
        error.status = 404;
        throw error;
      }
      const entry = await entryDetail(database, entryId, userId, { session });
      await requireWorkspaceWriter(database, entry.workspace_id, userId, { session });
      await guardEntryReportLock(database, entry, input, { session });
      const payload = normalizeAttachmentPayload(input);
      const attachmentId = uuid();
      const extension = ATTACHMENT_ALLOWED_MIME_EXTENSIONS[payload.mime_type];
      const relativePath = `storage/v2/attachments/${entry.workspace_id}/${entryId}/${attachmentId}.${extension}`;
      absolutePath = attachmentWritePath(relativePath);
      fs.writeFileSync(absolutePath, payload.content);
      const row = {
        id: attachmentId,
        entry_id: entryId,
        file_name: payload.file_name,
        file_url: relativePath,
        mime_type: payload.mime_type,
        size_bytes: payload.size_bytes,
        image_mode: payload.image_mode,
        created_at: nowSql(),
      };
      await database.collection('v2_attachments').insertOne(row, { session });
      result = attachmentRow(row);
      await audit(database, entry.workspace_id, 'attachment', attachmentId, 'create', null, {
        attachment: result,
        closed_month: Boolean(entry.report_lock && entry.report_lock.status),
      }, userId, { session });
    });
    return result;
  } catch (error) {
    if (absolutePath !== null && fs.existsSync(absolutePath)) fs.rmSync(absolutePath, { force: true });
    throw error;
  } finally {
    await session.endSession();
  }
}

async function deleteAttachment(database, attachmentId, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const { attachment, entry: entryRaw } = await attachmentForUser(database, attachmentId, userId, { session });
      const entry = await entryDetail(database, String(entryRaw.id), userId, { session });
      await requireWorkspaceWriter(database, entry.workspace_id, userId, { session });
      await guardEntryReportLock(database, entry, {}, { session });
      const fileDeleted = deleteAttachmentFile(attachment.file_url);
      await database.collection('v2_attachments').deleteOne({ id: attachmentId }, { session });
      result = {
        id: attachmentId,
        entry_id: attachment.entry_id,
        deleted: true,
        file_deleted: fileDeleted,
        closed_month: Boolean(entry.report_lock && entry.report_lock.status),
      };
      await audit(database, entry.workspace_id, 'attachment', attachmentId, 'delete', attachment, result, userId, { session });
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function createEntryInSession(database, workspaceId, input, userId = USER_ID, session) {
  await requireWorkspaceWriter(database, workspaceId, userId, { session });
  const flow = await flowForWorkspace(database, workspaceId, requireStringInput(input, 'flow_id', 36), { session });
  const normalized = await normalizeEntryInputWithCategory(database, workspaceId, flow, input, {}, { session });
  await guardWorkspaceMonthIsOpen(database, workspaceId, normalized.date, input, { session });
  const timestamp = nowSql();
  const entry = {
    id: uuid(),
    workspace_id: workspaceId,
    flow_id: flow.id,
    created_by: userId,
    actor_id: normalized.actor_id,
    date: normalized.date,
    raw_text: normalized.raw_text,
    sign: normalized.sign,
    amount: normalized.amount,
    direction: normalized.direction,
    entry_type: normalized.entry_type,
    category_id: normalized.category ? String(normalized.category.id) : null,
    status: normalized.status,
    source_type: normalized.source_type,
    source_id: normalized.source_id,
    source_row_id: normalized.source_row_id,
    notes: normalized.notes,
    confidence: normalized.confidence,
    matched_rules_json: JSON.stringify(normalized.matched_rules),
    balance_after: null,
    archived_at: null,
    created_seq: await nextEntrySeq(database, { session }),
    created_at: timestamp,
    updated_at: null,
  };
  await database.collection('v2_entries').insertOne(entry, { session });
  await recalculateCashFlowBalance(database, flow.id, { session });
  const created = await entryDetail(database, entry.id, userId, { session });
  await audit(database, workspaceId, 'entry', entry.id, 'create', null, created, userId, { session });
  return created;
}

async function updateEntry(database, entryId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let after = null;
    await session.withTransaction(async () => {
      const beforeRaw = await database.collection('v2_entries').findOne({ id: entryId, archived_at: null }, { session });
      if (!beforeRaw) {
        const error = new Error('entry_not_found');
        error.status = 404;
        throw error;
      }
      const before = await entryDetail(database, entryId, userId, { session });
      await requireWorkspaceWriter(database, before.workspace_id, userId, { session });
      guardAccountableProjectionEntryMutable(before);
      await guardEntryReportLock(database, before, input, { session });
      await guardWorkspaceMonthIsOpen(database, before.workspace_id, before.date, input, { session });
      const flowId = optionalStringInput(input, 'flow_id', before.flow.id, 36) || before.flow.id;
      const flow = await flowForWorkspace(database, before.workspace_id, flowId, { session });
      const normalized = await normalizeEntryInputWithCategory(database, before.workspace_id, flow, {
        flow_id: flow.id,
        date: input.date ?? before.date,
        raw_text: input.raw_text ?? before.raw_text,
        category_code: input.category_code ?? before.category_code,
        status: input.status ?? before.status,
        source_type: input.source_type ?? before.source_type,
        source_id: input.source_id ?? before.source_id,
        source_row_id: input.source_row_id ?? before.source_row_id,
        notes: input.notes ?? before.notes,
        confidence: input.confidence ?? before.confidence,
        matched_rules: input.matched_rules ?? before.matched_rules,
        ...(Object.hasOwn(input, 'amount') ? { amount: input.amount } : {}),
      }, {}, { session });
      await guardWorkspaceMonthIsOpen(database, before.workspace_id, normalized.date, input, { session });
      await database.collection('v2_entries').updateOne(
        { id: entryId },
        {
          $set: {
            flow_id: flow.id,
            actor_id: normalized.actor_id,
            date: normalized.date,
            raw_text: normalized.raw_text,
            sign: normalized.sign,
            amount: normalized.amount,
            direction: normalized.direction,
            entry_type: normalized.entry_type,
            category_id: normalized.category ? String(normalized.category.id) : null,
            status: normalized.status,
            source_type: normalized.source_type,
            notes: normalized.notes,
            confidence: normalized.confidence,
            matched_rules_json: JSON.stringify(normalized.matched_rules),
            updated_at: nowSql(),
          },
        },
        { session }
      );
      await recalculateCashFlowBalance(database, flow.id, { session });
      if (before.flow.id !== flow.id) await recalculateCashFlowBalance(database, before.flow.id, { session });
      await markOperationalReportsRequiringUpdateForEntry(database, before, userId, 'entry_update', { session });
      after = await entryDetail(database, entryId, userId, { session });
      await audit(database, before.workspace_id, 'entry', entryId, 'update', before, after, userId, { session });
    });
    return after;
  } finally {
    await session.endSession();
  }
}

function entryCategoryStatus(categoryCode, input = {}) {
  if (Object.hasOwn(input, 'status')) {
    return enumInput(optionalStringInput(input, 'status', null, 40), ['recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect'], 'status');
  }
  if (!categoryCode || categoryCode === 'other') return 'other_review';
  return 'recognized';
}

async function updateEntryCategory(database, entryId, input, userId = USER_ID) {
  const categoryCode = optionalStringInput(input, 'category_code', null, 80);
  const patch = {
    category_code: categoryCode,
    status: entryCategoryStatus(categoryCode, input),
  };
  if (Object.hasOwn(input, 'closed_month_decision')) patch.closed_month_decision = input.closed_month_decision;
  if (Object.hasOwn(input, 'report_fragment_decision')) patch.report_fragment_decision = input.report_fragment_decision;
  return updateEntry(database, entryId, patch, userId);
}

async function decideClosedMonthEntryCategory(database, entryId, input, userId = USER_ID) {
  const decision = enumInput(requireStringInput(input, 'decision', 40), ['create_correction', 'recalculate_chain'], 'decision');
  const categoryCode = optionalStringInput(input, 'category_code', null, 80);
  if (decision === 'recalculate_chain') {
    return {
      decision,
      entry: await updateEntryCategory(database, entryId, {
        category_code: categoryCode,
        status: entryCategoryStatus(categoryCode, input),
        closed_month_decision: 'recalculate_chain',
        ...(Object.hasOwn(input, 'report_fragment_decision') ? { report_fragment_decision: input.report_fragment_decision } : {}),
      }, userId),
    };
  }

  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const entry = await entryDetail(database, entryId, userId, { session });
      await requireWorkspaceWriter(database, entry.workspace_id, userId, { session });
      guardAccountableProjectionEntryMutable(entry);
      await guardWorkspaceMonthIsOpen(database, entry.workspace_id, entry.date, { closed_month_decision: 'recalculate_chain' }, { session });
      const after = {
        decision,
        requested_category_code: categoryCode,
        reason: optionalStringInput(input, 'reason', null, 240),
        entry_id: entryId,
        original_category_code: entry.category_code || null,
        original_status: entry.status || null,
      };
      await audit(database, entry.workspace_id, 'entry', entryId, 'closed_month_category_correction_requested', entry, after, userId, { session });
      result = { decision, entry, recorded: true };
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function deleteEntry(database, entryId, input = {}, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const before = await entryDetail(database, entryId, userId, { session });
      await requireWorkspaceWriter(database, before.workspace_id, userId, { session });
      guardAccountableProjectionEntryMutable(before);
      await guardEntryReportLock(database, before, input, { session });
      await guardWorkspaceMonthIsOpen(database, before.workspace_id, before.date, input, { session });
      await database.collection('v2_entries').updateOne({ id: entryId }, { $set: { archived_at: nowSql() } }, { session });
      await recalculateCashFlowBalance(database, before.flow.id, { session });
      await markOperationalReportsRequiringUpdateForEntry(database, before, userId, 'entry_delete', { session });
      await audit(database, before.workspace_id, 'entry', entryId, 'delete', before, { archived: true }, userId, { session });
      result = { id: entryId, archived: true };
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function monthlyReportFor(database, workspaceId, year, month) {
  return (await handleApi('GET', `/api/workspaces/${workspaceId}/reports/monthly`, {
    year: String(year),
    month: String(month),
  })).report;
}

async function closeMonth(database, workspaceId, year, month, input = {}, userId = USER_ID) {
  assertValidMonth(year, month);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const beforeRaw = await database.collection('v2_monthly_closures').findOne({ workspace_id: workspaceId, year, month });
  const before = monthClosureRow(beforeRaw);
  const reportBeforeClose = await monthlyReportFor(database, workspaceId, year, month);
  const id = beforeRaw ? String(beforeRaw.id) : uuid();
  const timestamp = nowSql();
  const closurePatch = {
    opening_balance: reportBeforeClose.opening_cash === null ? null : moneyString(reportBeforeClose.opening_cash),
    closing_balance: reportBeforeClose.ending_cash === null ? null : moneyString(reportBeforeClose.ending_cash),
    is_closed: 1,
    comment: optionalStringInput(input, 'comment', null, 1000),
    closed_by: userId,
    closed_at: timestamp,
  };
  await database.collection('v2_monthly_closures').updateOne(
    { workspace_id: workspaceId, year, month },
    {
      $setOnInsert: { id, workspace_id: workspaceId, year, month },
      $set: closurePatch,
    },
    { upsert: true }
  );
  const after = monthClosureRow(await database.collection('v2_monthly_closures').findOne({ workspace_id: workspaceId, year, month }));
  await audit(database, workspaceId, 'month_closure', id, 'month_close', before, after, userId);
  return {
    closure: after,
    report: await monthlyReportFor(database, workspaceId, year, month),
  };
}

async function reopenMonth(database, workspaceId, year, month, input = {}, userId = USER_ID) {
  assertValidMonth(year, month);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const beforeRaw = await database.collection('v2_monthly_closures').findOne({ workspace_id: workspaceId, year, month });
  if (!beforeRaw || !bool(beforeRaw.is_closed)) {
    const error = new Error('month_not_closed');
    error.status = 422;
    throw error;
  }
  const before = monthClosureRow(beforeRaw);
  await database.collection('v2_monthly_closures').updateOne(
    { id: String(beforeRaw.id) },
    { $set: { is_closed: 0, comment: optionalStringInput(input, 'comment', null, 1000) } }
  );
  const after = monthClosureRow(await database.collection('v2_monthly_closures').findOne({ id: String(beforeRaw.id) }));
  await audit(database, workspaceId, 'month_closure', String(beforeRaw.id), 'month_reopen', before, after, userId);
  return {
    closure: after,
    report: await monthlyReportFor(database, workspaceId, year, month),
  };
}

async function createMonthCorrection(database, workspaceId, year, month, input = {}, userId = USER_ID) {
  assertValidMonth(year, month);
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      await requireWorkspaceWriter(database, workspaceId, userId, { session });
      const flow = await flowForWorkspace(database, workspaceId, requireStringInput(input, 'flow_id', 36), { session });
      const date = requireDateInput(input, 'date');
      if (String(date).slice(0, 7) !== `${year}-${String(month).padStart(2, '0')}`) {
        const error = new Error('invalid_correction_date');
        error.status = 422;
        throw error;
      }
      const rawText = requireStringInput(input, 'raw_text', 2000);
      const signed = strictSignedAmount(rawText, 'correction');
      const reason = optionalStringInput(input, 'reason', null, 1000)
        ?? optionalStringInput(input, 'comment', null, 1000);
      const referenceEntryId = optionalStringInput(input, 'reference_entry_id', null, 36);
      if (referenceEntryId !== null) {
        const reference = await entryDetail(database, referenceEntryId, userId, { session });
        if (reference.workspace_id !== workspaceId) {
          const error = new Error('entry_not_found');
          error.status = 404;
          throw error;
        }
      }
      const id = uuid();
      const matchedRules = [{
        source: 'month_correction',
        year,
        month,
        reference_entry_id: referenceEntryId,
      }];
      const timestamp = nowSql();
      await database.collection('v2_entries').insertOne({
        id,
        workspace_id: workspaceId,
        flow_id: flow.id,
        created_by: userId,
        actor_id: null,
        date,
        raw_text: rawText,
        sign: signed.sign,
        amount: signed.amount,
        direction: signed.direction,
        entry_type: 'correction',
        category_id: null,
        status: 'corrected',
        source_type: 'correction',
        source_id: null,
        source_row_id: null,
        notes: reason,
        confidence: null,
        matched_rules_json: JSON.stringify(matchedRules),
        balance_after: null,
        archived_at: null,
        created_seq: await nextEntrySeq(database, { session }),
        created_at: timestamp,
        updated_at: null,
      }, { session });
      await recalculateCashFlowBalance(database, flow.id, { session });
      const entry = await entryDetail(database, id, userId, { session });
      await audit(database, workspaceId, 'entry', id, 'month_correction_create', null, {
        entry,
        year,
        month,
        reference_entry_id: referenceEntryId,
      }, userId, { session });
      result = entry;
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function accountableSettlementById(database, settlementId, options = {}) {
  const settlement = await database.collection('v2_accountable_settlements').findOne({ id: settlementId }, options);
  if (!settlement) {
    const error = new Error('accountable_settlement_not_found');
    error.status = 404;
    throw error;
  }
  return settlement;
}

async function resolveAccountableSettlementWithCashMovement(database, settlementId, input, userId = USER_ID) {
  const session = mongoClient.startSession();
  try {
    let result = null;
    await session.withTransaction(async () => {
      const settlement = await accountableSettlementById(database, settlementId, { session });
      const workspaceId = String(settlement.workspace_id);
      await requireWorkspaceAdmin(database, workspaceId, userId, { session });

      if (String(settlement.resolution_status) === 'resolved') {
        result = {
          settlement: accountableSettlementRow(settlement),
          entry: null,
        };
        return;
      }

      const status = String(settlement.status);
      if (!['return_due', 'reimburse_due'].includes(status)) {
        const error = new Error('accountable_settlement_not_open');
        error.status = 409;
        throw error;
      }

      const cashFlow = await cashFlowForWorkspace(database, workspaceId, userId, { session });
      if (cashFlow === null) {
        const error = new Error('cash_flow_required');
        error.status = 422;
        throw error;
      }

      const expectedAmount = status === 'return_due'
        ? Number(settlement.return_due_amount || 0)
        : Number(settlement.reimburse_due_amount || 0);
      const sign = status === 'return_due' ? '+' : '-';
      const defaultText = status === 'return_due'
        ? 'возврат подотчетного остатка'
        : 'физическое возмещение перерасхода сотруднику';
      let rawText = optionalStringInput(input, 'raw_text', null, 2000) || `${sign}${moneyString(expectedAmount)} ${defaultText}`;
      if (!/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?/u.test(rawText)) {
        rawText = `${sign}${moneyString(expectedAmount)} ${rawText}`;
      }
      const entryInput = {
        flow_id: cashFlow.id,
        date: requireDateInput(input, 'date'),
        raw_text: rawText,
        status: 'recognized',
        notes: optionalStringInput(input, 'note', null, 1000)
          || optionalStringInput(input, 'resolution_note', null, 1000)
          || 'Physical cash settlement for accountable report.',
        matched_rules: [{
          source: 'accountable_settlement_resolution',
          settlement_id: settlementId,
          cash_effect: status === 'return_due' ? 'cash_in' : 'cash_out',
        }],
      };
      const entry = await createSettlementCashEntry(database, workspaceId, cashFlow, entryInput, userId, { session });
      if (entry.direction !== (status === 'return_due' ? 'in' : 'out')) {
        const error = new Error('settlement_entry_direction_mismatch');
        error.status = 422;
        throw error;
      }
      if (Math.abs(Number(entry.amount) - expectedAmount) > 0.004) {
        const error = new Error('settlement_entry_amount_mismatch');
        error.status = 422;
        throw error;
      }

      const before = accountableSettlementRow(settlement);
      const timestamp = nowSql();
      const note = optionalStringInput(input, 'note', null, 1000)
        || optionalStringInput(input, 'resolution_note', null, 1000);
      const update = {
        resolution_status: 'resolved',
        resolved_amount: moneyString(expectedAmount),
        resolved_entry_id: entry.id,
        resolved_at: timestamp,
        resolved_by: userId,
        resolution_note: note,
        updated_at: timestamp,
      };
      await database.collection('v2_accountable_settlements').updateOne({ id: settlementId }, { $set: update }, { session });
      const afterSettlement = accountableSettlementRow({ ...settlement, ...update });
      await audit(database, workspaceId, 'accountable_settlement', settlementId, 'resolve_physical_cash', before, {
        settlement: afterSettlement,
        entry: {
          id: entry.id,
          raw_text: entry.raw_text,
          amount: entry.amount,
          direction: entry.direction,
        },
      }, userId, { session });
      result = {
        settlement: afterSettlement,
        entry,
      };
    });
    return result;
  } finally {
    await session.endSession();
  }
}

async function listAccountableOffers(database, workspaceId, query = {}, userId = USER_ID) {
  const access = await workspaceAccess(database, workspaceId, userId);
  let filter = { workspace_id: workspaceId };
  if (!access.can_admin) {
    if (access.role !== 'employee') {
      const error = new Error('workspace_admin_required');
      error.status = 403;
      throw error;
    }
    const email = await userEmail(database, userId);
    filter = { workspace_id: workspaceId, $or: [{ employee_user_id: userId }, { employee_email: email }] };
  }
  const limit = access.can_admin ? 200 : 100;
  const offers = await database.collection('v2_accountable_offers')
    .find(filter)
    .sort({ created_at: -1, id: -1 })
    .limit(limit)
    .toArray();
  return offers.map(accountableOfferRow);
}

async function listAccountableReports(database, workspaceId, query = {}, userId = USER_ID) {
  const access = await workspaceAccess(database, workspaceId, userId);
  let filter = { workspace_id: workspaceId };
  let limit = 200;
  if (!access.can_admin) {
    if (access.role !== 'employee') {
      const error = new Error('workspace_admin_required');
      error.status = 403;
      throw error;
    }
    limit = 100;
    filter.employee_user_id = userId;
    if (query.status) {
      const status = String(query.status);
      if (!['draft', 'submitted', 'cancelled'].includes(status)) {
        const error = new Error('invalid_status');
        error.status = 422;
        throw error;
      }
      filter.status = status;
    }
  } else if (query.status) {
    const status = String(query.status);
    if (!['submitted', 'accepted_by_admin', 'hall_open'].includes(status)) {
      const error = new Error('invalid_status');
      error.status = 422;
      throw error;
    }
    filter.status = status === 'hall_open' ? { $in: ['submitted', 'accepted_by_admin'] } : status;
  } else {
    filter.status = 'submitted';
  }
  const reports = await database.collection('v2_accountable_reports')
    .find(filter)
    .sort({ submitted_at: -1, reviewed_at: -1, created_at: -1, id: -1 })
    .limit(limit)
    .toArray();
  return Promise.all(reports.map((report) => accountableReportDetail(database, report)));
}

async function accountableDashboard(database, workspaceId, userId = USER_ID) {
  const { workspace } = await requireWorkspace(database, workspaceId, userId);
  await requireWorkspaceAdmin(database, workspaceId, userId);
  const offers = (await database.collection('v2_accountable_offers')
    .find({ workspace_id: workspaceId })
    .sort({ created_at: -1, id: -1 })
    .limit(300)
    .toArray()).map(accountableOfferRow);
  const reportRows = await database.collection('v2_accountable_reports')
    .find({ workspace_id: workspaceId })
    .sort({ submitted_at: -1, reviewed_at: -1, created_at: -1, id: -1 })
    .limit(300)
    .toArray();
  const reports = await Promise.all(reportRows.map(async (row) => {
    const report = accountableReportRow(row, null, await accountableSettlementForReport(database, String(row.id)));
    return report;
  }));
  const employees = {};
  const offersById = new Map(offers.map((offer) => [String(offer.id), offer]));
  const currency = String(workspace.currency || 'EUR');
  const summary = {
    currency,
    policy: 'cash_card_effect_none_read_model',
    cash_delta: 0,
    card_delta: 0,
    pending_offer_total: 0,
    issued_total: 0,
    submitted_report_total: 0,
    accepted_report_total: 0,
    accepted_cash_expenses_total: 0,
    accepted_noncash_expenses_total: 0,
    not_materialized_total: 0,
    materialized_total: 0,
    return_due_total: 0,
    reimburse_due_total: 0,
    return_due_gross_total: 0,
    reimburse_due_gross_total: 0,
    settled_return_total: 0,
    settled_reimburse_total: 0,
    open_position_total: 0,
    offer_count: offers.length,
    report_count: reports.length,
    submitted_report_count: 0,
    accepted_report_count: 0,
    not_materialized_report_count: 0,
    materialized_report_count: 0,
  };

  for (const offer of offers) {
    const key = accountableEmployeeKey(offer.employee_user_id, offer.employee_email);
    employees[key] = employees[key] || emptyAccountableDashboardEmployee(offer.employee_user_id, offer.employee_email, currency);
    employees[key].offers.push(accountableDashboardOfferRow(offer));
    if (offer.status === 'pending_offer') {
      employees[key].metrics.pending_offer_total += offer.amount;
      summary.pending_offer_total += offer.amount;
    }
    if (offer.status === 'accepted_by_employee') {
      employees[key].metrics.issued_total += offer.amount;
      summary.issued_total += offer.amount;
    }
  }

  for (const report of reports) {
    const offer = offersById.get(String(report.offer_id)) || null;
    const key = accountableEmployeeKey(offer ? offer.employee_user_id : report.employee_user_id, offer ? offer.employee_email : '');
    employees[key] = employees[key] || emptyAccountableDashboardEmployee(offer ? offer.employee_user_id : report.employee_user_id, offer ? offer.employee_email : '', currency);
    employees[key].reports.push(accountableDashboardReportRow(report));
    if (report.status === 'submitted') {
      employees[key].metrics.submitted_report_total += report.total_amount;
      employees[key].metrics.submitted_report_count += 1;
      summary.submitted_report_total += report.total_amount;
      summary.submitted_report_count += 1;
    }
    if (report.status === 'accepted_by_admin') {
      const accepted = report.accepted_total_amount;
      employees[key].metrics.accepted_report_total += accepted;
      employees[key].metrics.accepted_cash_expenses_total += report.accepted_cash_expenses;
      employees[key].metrics.accepted_noncash_expenses_total += report.accepted_noncash_expenses;
      employees[key].metrics.accepted_report_count += 1;
      summary.accepted_report_total += accepted;
      summary.accepted_cash_expenses_total += report.accepted_cash_expenses;
      summary.accepted_noncash_expenses_total += report.accepted_noncash_expenses;
      summary.accepted_report_count += 1;
      if (report.ledger_materialization_status === 'materialized') {
        employees[key].metrics.materialized_total += accepted;
        employees[key].metrics.materialized_report_count += 1;
        summary.materialized_total += accepted;
        summary.materialized_report_count += 1;
      } else {
        employees[key].metrics.not_materialized_total += accepted;
        employees[key].metrics.not_materialized_report_count += 1;
        summary.not_materialized_total += accepted;
        summary.not_materialized_report_count += 1;
      }
    }
    const settlement = report.settlement || null;
    if (settlement) {
      const returnDueGross = settlement.return_due_amount || 0;
      const reimburseDueGross = settlement.reimburse_due_amount || 0;
      const resolvedAmount = settlement.resolution_status === 'resolved' ? (settlement.resolved_amount || 0) : 0;
      const settledReturn = settlement.status === 'return_due' ? Math.min(returnDueGross, resolvedAmount) : 0;
      const settledReimburse = settlement.status === 'reimburse_due' ? Math.min(reimburseDueGross, resolvedAmount) : 0;
      const returnDue = Math.max(returnDueGross - settledReturn, 0);
      const reimburseDue = Math.max(reimburseDueGross - settledReimburse, 0);
      employees[key].metrics.return_due_total += returnDue;
      employees[key].metrics.reimburse_due_total += reimburseDue;
      employees[key].metrics.return_due_gross_total += returnDueGross;
      employees[key].metrics.reimburse_due_gross_total += reimburseDueGross;
      employees[key].metrics.settled_return_total += settledReturn;
      employees[key].metrics.settled_reimburse_total += settledReimburse;
      summary.return_due_total += returnDue;
      summary.reimburse_due_total += reimburseDue;
      summary.return_due_gross_total += returnDueGross;
      summary.reimburse_due_gross_total += reimburseDueGross;
      summary.settled_return_total += settledReturn;
      summary.settled_reimburse_total += settledReimburse;
    }
  }

  const employeeRows = Object.values(employees);
  for (const employee of employeeRows) {
    employee.metrics.open_position_total = Math.max(
      employee.metrics.issued_total - employee.metrics.accepted_report_total - employee.metrics.settled_return_total,
      0
    );
    summary.open_position_total += employee.metrics.open_position_total;
    employee.offer_count = employee.offers.length;
    employee.report_count = employee.reports.length;
  }

  employeeRows.sort((left, right) => {
    const leftHot = (left.metrics.submitted_report_count * 1000)
      + (left.metrics.not_materialized_report_count * 100)
      + Math.round(left.metrics.open_position_total);
    const rightHot = (right.metrics.submitted_report_count * 1000)
      + (right.metrics.not_materialized_report_count * 100)
      + Math.round(right.metrics.open_position_total);
    return rightHot - leftHot;
  });

  return {
    workspace_id: workspaceId,
    workspace_name: String(workspace.name),
    currency,
    summary,
    employees: employeeRows,
  };
}

function sourceEntryIdsFromQuery(query) {
  const raw = query.ids || query.source_entry_ids || query.entry_ids || '';
  const rawIds = (Array.isArray(raw) ? raw : String(raw).split(','))
    .map((id) => String(id).trim())
    .filter(Boolean)
    .slice(0, 150);
  const invalid = rawIds.filter((id) => !/^[a-f0-9-]{36}$/i.test(id));
  if (invalid.length) {
    const error = new Error('invalid_ids');
    error.status = 422;
    throw error;
  }
  return Array.from(new Set(rawIds));
}

function categoryNameRu(entry) {
  if (!entry.category_name) return 'Без категории';
  return entry.category_name.ru || entry.category_name.en || entry.category_code || 'Без категории';
}

function appendUnique(list, value) {
  const id = String(value);
  if (!list.includes(id)) list.push(id);
}

function htmlEscape(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function formatMoney(value) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '—';
  return `${Number(value).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;
}

function contentHash(payload) {
  return crypto.createHash('sha256').update(JSON.stringify(payload)).digest('hex');
}

function operationalFragmentEntryIds(input) {
  let raw = input.entry_ids ?? input.ids ?? [];
  if (typeof raw === 'string') raw = raw.split(',').map((item) => item.trim());
  if (!Array.isArray(raw)) {
    const error = new Error('invalid_entry_ids');
    error.status = 422;
    throw error;
  }
  const ids = [];
  for (const candidate of raw) {
    const id = String(candidate || '').trim().toLowerCase();
    if (!id) continue;
    if (!/^[a-f0-9-]{36}$/i.test(id)) {
      const error = new Error('invalid_entry_ids');
      error.status = 422;
      throw error;
    }
    appendUnique(ids, id);
  }
  if (!ids.length) {
    const error = new Error('missing_entry_ids');
    error.status = 422;
    throw error;
  }
  if (ids.length > 250) {
    const error = new Error('too_many_entry_ids');
    error.status = 422;
    throw error;
  }
  return ids;
}

async function cashBalanceBeforeEntry(database, flowId, date, createdSeq) {
  const previous = await database.collection('v2_entries')
    .find({
      flow_id: flowId,
      archived_at: null,
      balance_after: { $ne: null },
      $or: [
        { date: { $lt: date } },
        { date, created_seq: { $lt: createdSeq } },
      ],
    })
    .sort({ date: -1, created_seq: -1 })
    .limit(1)
    .toArray();
  if (previous[0]) return amount(previous[0].balance_after);
  const flow = await database.collection('v2_flows').findOne({ id: flowId });
  return flow ? amount(flow.opening_balance) || 0 : null;
}

async function operationalFragmentRows(database, workspaceId, entryIds) {
  const entries = await joinedEntries(database, workspaceId, {});
  const byId = new Map(entries.map((entry) => [String(entry.id), entry]));
  const rows = [];
  for (const id of entryIds) {
    if (!byId.has(id)) {
      const error = new Error('report_fragment_entries_not_found');
      error.status = 422;
      throw error;
    }
    rows.push(byId.get(id));
  }
  rows.sort((left, right) => {
    const date = String(left.date).localeCompare(String(right.date));
    if (date !== 0) return date;
    return Number(left.created_seq || 0) - Number(right.created_seq || 0);
  });
  return rows;
}

async function buildOperationalFragmentReport(database, workspaceId, entryIds, userId = USER_ID, exceptReportId = null) {
  const { workspace } = await requireWorkspaceFullReader(database, workspaceId, userId);
  const rows = await operationalFragmentRows(database, workspaceId, entryIds);
  const first = rows[0];
  const last = rows[rows.length - 1];
  const startDate = String(first.date);
  const endDate = String(last.date);
  const sourceEntryIds = rows.map((entry) => String(entry.id));
  const lockedEntryIds = rows
    .filter((entry) => entry.report_lock && String(entry.report_lock.report_id) !== String(exceptReportId || ''))
    .map((entry) => String(entry.id));
  const cashFlow = await database.collection('v2_flows').findOne({ workspace_id: workspaceId, type: 'cash', has_live_balance: 1 });
  const firstCash = rows.find((entry) => entry.flow.type === 'cash') || null;
  const openingCash = firstCash && cashFlow
    ? await cashBalanceBeforeEntry(database, String(cashFlow.id), String(firstCash.date), Number(firstCash.created_seq || 0))
    : null;
  let selectedCashDelta = 0;
  let cardExpenseCount = 0;
  let cardReviewCount = 0;
  let unrecognizedCount = 0;
  const periodTotals = {
    cash_income: 0,
    cash_expense: 0,
    card_expense: 0,
    commercial_income: 0,
    cash_topup_from_card_cash_side: 0,
    cash_topup_from_card_card_side: 0,
    corrections: 0,
  };
  const sourceTrace = {
    fragment_entry_ids: sourceEntryIds,
    locked_entry_ids: lockedEntryIds,
    totals: {
      opening_cash: [],
      total_cash_income: [],
      cash_income: [],
      cash_expense: [],
      card_expense: [],
      commercial_income: [],
      other_review_total: [],
      lower_accounting_total: [],
      corrections_total: [],
      ending_cash: [],
    },
    categories: {},
    basis: {
      opening_cash: cashFlow ? {
        type: 'cash_balance_before_selected_fragment',
        flow_id: String(cashFlow.id),
        flow_name: String(cashFlow.name || 'Cash'),
        total: openingCash,
        period_start: startDate,
        period_end: endDate,
        label: 'Cash before selected report fragment',
      } : null,
    },
  };
  const categories = new Map();
  const categoryDocs = await database.collection('v2_categories')
    .find({ is_active: 1, $or: [{ workspace_id: null }, { workspace_id: workspaceId }] })
    .toArray();
  for (const category of categoryDocs) {
    categories.set(String(category.code), {
      category_code: String(category.code),
      category_name: decodeJson(category.name_json, { ru: String(category.code), en: String(category.code) }),
      direction: String(category.direction),
      cash_total: 0,
      card_total: 0,
      total: 0,
      entry_count: 0,
      review_count: 0,
      source_entry_ids: [],
    });
  }
  categories.set('uncategorized_review', {
    category_code: 'uncategorized_review',
    category_name: { ru: 'Без категории / проверка', en: 'Uncategorized / review' },
    direction: 'expense',
    cash_total: 0,
    card_total: 0,
    total: 0,
    entry_count: 0,
    review_count: 0,
    source_entry_ids: [],
  });
  const entries = [];
  const otherReviewEntries = [];
  const lowerAccountingEntries = [];
  const cardByCategory = new Map();

  rows.forEach((entry, index) => {
    const value = amount(entry.amount);
    const flowType = entry.flow.type;
    const counted = COUNTED_STATUSES.has(entry.status) && value !== null;
    const categoryCode = entry.category_code || null;
    const effectiveCategoryCode = categoryCode || 'uncategorized_review';
    const lowerAccounting = entry.accounting_section === 'lower_accounting';
    const snapshot = {
      ...entry,
      fragment_row_number: index + 1,
    };
    entries.push(snapshot);
    if (entry.status === 'unrecognized' || entry.status === 'duplicate_suspect') unrecognizedCount += 1;
    if (flowType === 'card' && entry.status === 'other_review') cardReviewCount += 1;
    const isOtherReview = (entry.status === 'other_review' && entry.entry_type === 'cash_expense' && categoryCode === 'other')
      || (counted && !lowerAccounting && categoryCode === null);
    if (isOtherReview) {
      otherReviewEntries.push(snapshot);
      appendUnique(sourceTrace.totals.other_review_total, entry.id);
    }
    if (!counted) return;
    if (lowerAccounting) {
      lowerAccountingEntries.push(snapshot);
      appendUnique(sourceTrace.totals.lower_accounting_total, entry.id);
    }
    if (flowType === 'cash') {
      const delta = cashBalanceDelta(entry);
      if (delta !== null) {
        selectedCashDelta += delta;
        appendUnique(sourceTrace.totals.ending_cash, entry.id);
      }
    }
    if (flowType === 'cash' && entry.direction === 'in' && entry.entry_type === 'cash_income') {
      if (categoryCode === 'commercial_income') {
        periodTotals.commercial_income += value;
        appendUnique(sourceTrace.totals.commercial_income, entry.id);
        appendUnique(sourceTrace.totals.total_cash_income, entry.id);
      } else if (categoryCode === 'cash_topup_from_card') {
        periodTotals.cash_topup_from_card_cash_side += value;
      } else {
        periodTotals.cash_income += value;
        appendUnique(sourceTrace.totals.cash_income, entry.id);
        appendUnique(sourceTrace.totals.total_cash_income, entry.id);
      }
    }
    if (flowType === 'cash' && entry.direction === 'out' && entry.entry_type === 'cash_expense') {
      periodTotals.cash_expense += value;
      appendUnique(sourceTrace.totals.cash_expense, entry.id);
    }
    if (flowType === 'card' && entry.direction === 'out' && entry.entry_type === 'card_expense') {
      periodTotals.card_expense += value;
      cardExpenseCount += 1;
      appendUnique(sourceTrace.totals.card_expense, entry.id);
      if (categoryCode === 'cash_topup_from_card') periodTotals.cash_topup_from_card_card_side += value;
    }
    if (entry.entry_type === 'correction') {
      periodTotals.corrections += entry.direction === 'out' ? -value : value;
      appendUnique(sourceTrace.totals.corrections_total, entry.id);
    }
    if (!lowerAccounting && categories.has(effectiveCategoryCode)) {
      const category = categories.get(effectiveCategoryCode);
      category.entry_count += 1;
      category.review_count += entry.status === 'other_review' || !categoryCode ? 1 : 0;
      category.total += value;
      if (flowType === 'cash') category.cash_total += value;
      if (flowType === 'card') category.card_total += value;
      appendUnique(category.source_entry_ids, entry.id);
      sourceTrace.categories[effectiveCategoryCode] = category.source_entry_ids;
      if (flowType === 'card' && entry.direction === 'out' && entry.entry_type === 'card_expense') {
        if (!cardByCategory.has(effectiveCategoryCode)) {
          cardByCategory.set(effectiveCategoryCode, {
            category_code: effectiveCategoryCode,
            category_name: category.category_name,
            total: 0,
            entry_count: 0,
            source_entry_ids: [],
          });
        }
        const cardCategory = cardByCategory.get(effectiveCategoryCode);
        cardCategory.total += value;
        cardCategory.entry_count += 1;
        appendUnique(cardCategory.source_entry_ids, entry.id);
      }
    }
  });

  const categoryRows = Array.from(categories.values())
    .filter((row) => row.entry_count > 0 || Math.abs(row.total) > 0.0001)
    .sort((left, right) => String(left.category_code).localeCompare(String(right.category_code)));
  const totalRow = categoryRows.reduce((acc, row) => {
    acc.cash_total += row.cash_total;
    acc.card_total += row.card_total;
    acc.total += row.total;
    acc.entry_count += row.entry_count;
    acc.review_count += row.review_count;
    acc.source_entry_ids.push(...row.source_entry_ids);
    return acc;
  }, { cash_total: 0, card_total: 0, total: 0, entry_count: 0, review_count: 0, source_entry_ids: [] });
  const endingCash = openingCash === null ? null : openingCash + selectedCashDelta;
  return {
    header: {
      report_type: 'operational_fragment',
      workspace: { id: String(workspace.id), name: String(workspace.name), type: String(workspace.type) },
      currency: workspace.currency || 'EUR',
      start_date: startDate,
      end_date: endDate,
      from_entry_id: String(first.id),
      to_entry_id: String(last.id),
      range_label: startDate === endDate ? startDate : `${startDate} - ${endDate}`,
      generated_at: new Date().toISOString(),
      entries_count: entries.length,
      review_count: otherReviewEntries.length,
      unrecognized_count: unrecognizedCount,
      locked_count: lockedEntryIds.length,
    },
    totals: {
      opening_cash: openingCash,
      total_cash_income: periodTotals.cash_income + periodTotals.commercial_income,
      cash_income: periodTotals.cash_income,
      cash_expense: periodTotals.cash_expense,
      card_expense: periodTotals.card_expense,
      commercial_income: periodTotals.commercial_income,
      other_review_total: otherReviewEntries.reduce((sum, entry) => sum + (amount(entry.amount) || 0), 0),
      lower_accounting_total: lowerAccountingEntries.reduce((sum, entry) => sum + (amount(entry.amount) || 0), 0),
      corrections_total: periodTotals.corrections,
      ending_cash: endingCash,
    },
    money_position: { physical_available: endingCash, cash_available: endingCash },
    blocks: {
      cash: {
        opening_cash: openingCash,
        cash_income: periodTotals.cash_income,
        cash_topup_from_card: periodTotals.cash_topup_from_card_cash_side,
        commercial_income: periodTotals.commercial_income,
        cash_expense: periodTotals.cash_expense,
        corrections_total: periodTotals.corrections,
        ending_cash: endingCash,
        source_entry_ids: sourceTrace.totals.ending_cash,
      },
      money_position: { physical_available: endingCash, cash_available: endingCash },
      card: {
        card_expense: periodTotals.card_expense,
        cash_topup_to_cash: periodTotals.cash_topup_from_card_card_side,
        entries_count: cardExpenseCount,
        review_count: cardReviewCount,
        by_category: Array.from(cardByCategory.values()),
        source_entry_ids: sourceTrace.totals.card_expense,
      },
      categories: { rows: categoryRows, total_row: totalRow },
      other_review: {
        count: otherReviewEntries.length,
        total: otherReviewEntries.reduce((sum, entry) => sum + (amount(entry.amount) || 0), 0),
        entries: otherReviewEntries,
        source_entry_ids: sourceTrace.totals.other_review_total,
      },
      lower_accounting: {
        count: lowerAccountingEntries.length,
        total: lowerAccountingEntries.reduce((sum, entry) => sum + (amount(entry.amount) || 0), 0),
        entries: lowerAccountingEntries,
        settlements: {},
        source_entry_ids: sourceTrace.totals.lower_accounting_total,
      },
    },
    entries,
    source_trace: sourceTrace,
  };
}

function operationalReportHtml(batch, workspace) {
  const summary = batch.summary || {};
  const rows = (summary.blocks && summary.blocks.categories && summary.blocks.categories.rows) || [];
  const entries = Array.isArray(batch.entry_snapshot) ? batch.entry_snapshot : [];
  const categoryHtml = rows.map((row) => `
      <details open>
        <summary><strong>${htmlEscape(row.category_name?.ru || row.category_code)}</strong><span>${formatMoney(row.total)}</span></summary>
        <table><tbody>${entries
          .filter((entry) => (row.source_entry_ids || []).includes(String(entry.id)))
          .map((entry) => `<tr><td>${htmlEscape(entry.date)}</td><td>${htmlEscape(entry.raw_text)}</td><td>${formatMoney(entry.amount)}</td></tr>`)
          .join('')}</tbody></table>
      </details>`).join('');
  return `<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${htmlEscape(batch.title)}</title>
<style>body{font-family:Inter,Arial,sans-serif;margin:0;padding:24px;color:#0f172a;background:#f8fafc}main{max-width:980px;margin:auto;background:#fff;border:1px solid #d9e2ef;border-radius:8px;padding:20px}h1{font-size:22px;margin:0 0 8px}.meta{color:#64748b;margin-bottom:18px}.totals{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;margin:12px 0}.tile{border:1px solid #d9e2ef;border-radius:6px;padding:10px}.tile b{display:block;font-size:18px}details{border:1px solid #d9e2ef;border-radius:6px;margin-top:8px;background:#fff}summary{display:flex;justify-content:space-between;gap:12px;padding:10px;cursor:pointer}table{width:100%;border-collapse:collapse}td{border-top:1px solid #e5edf7;padding:8px;font-size:14px}td:last-child{text-align:right;font-weight:700}</style>
</head><body><main>
<h1>${htmlEscape(batch.title)}</h1>
<div class="meta">${htmlEscape(workspace.name)} · ${htmlEscape(batch.start_date)} — ${htmlEscape(batch.end_date)} · ${Number(batch.entry_count || 0)} записей</div>
<section class="totals">
<div class="tile">Входящий остаток<b>${formatMoney(summary.totals?.opening_cash)}</b></div>
<div class="tile">Поступления<b>${formatMoney(summary.totals?.total_cash_income)}</b></div>
<div class="tile">Расходы<b>${formatMoney((summary.totals?.cash_expense || 0) + (summary.totals?.card_expense || 0))}</b></div>
<div class="tile">Конечный остаток<b>${formatMoney(summary.totals?.ending_cash)}</b></div>
</section>
<h2>Категории</h2>${categoryHtml || '<p>Нет категорий.</p>'}
<h2>Операционная лента</h2><table><tbody>${entries.map((entry) => `<tr><td>${htmlEscape(entry.date)}</td><td>${htmlEscape(entry.raw_text)}</td><td>${formatMoney(entry.amount)}</td></tr>`).join('')}</tbody></table>
</main></body></html>`;
}

function reportPackageHtml(reportPackage, workspace) {
  const fragments = Array.isArray(reportPackage.fragments) ? reportPackage.fragments : [];
  const rows = reportPackage.summary?.blocks?.categories?.rows || [];
  return `<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>${htmlEscape(reportPackage.title)}</title>
<style>body{font-family:Inter,Arial,sans-serif;margin:0;padding:24px;color:#0f172a;background:#f8fafc}main{max-width:980px;margin:auto;background:#fff;border:1px solid #d9e2ef;border-radius:8px;padding:20px}h1{font-size:22px;margin:0 0 8px}.meta{color:#64748b;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px}.tile,details{border:1px solid #d9e2ef;border-radius:6px;padding:10px;background:#fff}summary{cursor:pointer;font-weight:700}table{width:100%;border-collapse:collapse}td{border-top:1px solid #e5edf7;padding:8px;font-size:14px}td:last-child{text-align:right;font-weight:700}</style>
</head><body><main>
<h1>${htmlEscape(reportPackage.title)}</h1>
<div class="meta">${htmlEscape(workspace.name)} · ${htmlEscape(reportPackage.start_date)} — ${htmlEscape(reportPackage.end_date)} · ${Number(reportPackage.entry_count || 0)} записей</div>
<section class="grid"><div class="tile">Фрагментов<br><b>${Number(reportPackage.fragment_count || 0)}</b></div><div class="tile">Конечный остаток<br><b>${formatMoney(reportPackage.summary?.totals?.ending_cash)}</b></div><div class="tile">Поступления<br><b>${formatMoney(reportPackage.summary?.totals?.total_cash_income)}</b></div><div class="tile">Расходы<br><b>${formatMoney((reportPackage.summary?.totals?.cash_expense || 0) + (reportPackage.summary?.totals?.card_expense || 0))}</b></div></section>
<h2>Категории</h2>${rows.map((row) => `<details open><summary>${htmlEscape(row.category_name?.ru || row.category_code)} · ${formatMoney(row.total)}</summary></details>`).join('')}
<h2>Фрагменты</h2>${fragments.map((fragment) => `<details open><summary>${htmlEscape(fragment.title)} · ${htmlEscape(fragment.start_date)} — ${htmlEscape(fragment.end_date)}</summary><table><tbody>${(fragment.entry_snapshot || []).map((entry) => `<tr><td>${htmlEscape(entry.date)}</td><td>${htmlEscape(entry.raw_text)}</td><td>${formatMoney(entry.amount)}</td></tr>`).join('')}</tbody></table></details>`).join('')}
</main></body></html>`;
}

async function nextHtmlSnapshotVersion(database, workspaceId, batchId) {
  const rows = await database.collection('v2_report_batch_html_snapshots')
    .find({ workspace_id: workspaceId, batch_id: batchId })
    .sort({ version: -1 })
    .limit(1)
    .toArray();
  return rows[0] ? Number(rows[0].version || 0) + 1 : 1;
}

async function storeOperationalReportFragmentHtmlSnapshot(database, workspaceId, batch, workspace, userId, status = 'stored', comment = null) {
  const version = await nextHtmlSnapshotVersion(database, workspaceId, String(batch.id));
  const html = operationalReportHtml(batch, workspace);
  const hash = crypto.createHash('sha256').update(html).digest('hex');
  const row = {
    id: uuid(),
    workspace_id: workspaceId,
    batch_id: String(batch.id),
    version,
    status,
    generated_at: nowSql(),
    stored_at: nowSql(),
    html_filename: `storage/v2/report-batches/${workspaceId}/html-snapshots/${String(batch.id)}-v${version}.html`,
    html_content: html,
    html_size_bytes: Buffer.byteLength(html),
    html_hash: hash,
    source_batch_hash: String(batch.content_hash || ''),
    comment,
    created_by: userId,
    created_at: nowSql(),
  };
  await database.collection('v2_report_batch_html_snapshots').insertOne(row);
  return reportBatchHtmlSnapshotRow(row, false);
}

async function previewOperationalReportFragment(database, workspaceId, input, userId = USER_ID) {
  await requireWorkspaceFullReader(database, workspaceId, userId);
  const entryIds = operationalFragmentEntryIds(input);
  const report = await buildOperationalFragmentReport(database, workspaceId, entryIds, userId);
  return {
    report,
    entry_ids: report.source_trace.fragment_entry_ids || [],
    can_create: (report.source_trace.locked_entry_ids || []).length === 0,
  };
}

async function createOperationalReportFragment(database, workspaceId, input, userId = USER_ID) {
  const { workspace } = await requireWorkspaceFullReader(database, workspaceId, userId);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const entryIds = operationalFragmentEntryIds(input);
  const report = await buildOperationalFragmentReport(database, workspaceId, entryIds, userId);
  const locked = report.source_trace.locked_entry_ids || [];
  if (locked.length && !phpBool(input.allow_locked_entries)) {
    const error = new Error('report_fragment_contains_locked_entries');
    error.status = 409;
    error.locked_entry_ids = locked;
    throw error;
  }
  const header = report.header;
  const sourceEntryIds = report.source_trace.fragment_entry_ids || [];
  const title = optionalStringInput(input, 'title', `Отчетный фрагмент ${header.range_label}`, 190);
  const status = enumInput(optionalStringInput(input, 'status', 'created', 40), ['draft', 'created', 'sent'], 'status');
  let closedAt = null;
  if (Object.hasOwn(input, 'closed_date') || Object.hasOwn(input, 'close_date')) {
    const closedDate = optionalDateInput({ closed_date: input.closed_date ?? input.close_date }, 'closed_date', null);
    if (closedDate < header.start_date) {
      const error = new Error('invalid_closed_date');
      error.status = 422;
      throw error;
    }
    closedAt = `${closedDate} 23:59:59`;
  }
  const batchId = uuid();
  const payload = {
    report_type: 'operational_fragment',
    workspace_id: workspaceId,
    batch_id: batchId,
    title,
    status,
    closed_at: closedAt,
    summary: report,
    source_entry_ids: sourceEntryIds,
    entry_snapshot: report.entries,
  };
  const row = {
    id: batchId,
    workspace_id: workspaceId,
    batch_type: 'operational_fragment',
    title,
    status,
    start_date: header.start_date,
    end_date: header.end_date,
    from_entry_id: header.from_entry_id,
    to_entry_id: header.to_entry_id,
    entry_count: sourceEntryIds.length,
    generated_at: nowSql(),
    closed_at: closedAt,
    html_filename: `storage/v2/report-batches/${workspaceId}/${batchId}.html`,
    summary_json: JSON.stringify(report),
    source_trace_json: JSON.stringify(report.source_trace),
    source_entry_ids_json: JSON.stringify(sourceEntryIds),
    entry_snapshot_json: JSON.stringify(report.entries),
    content_hash: contentHash(payload),
    created_by: userId,
    created_at: nowSql(),
    updated_at: null,
  };
  await database.collection('v2_report_batches').insertOne(row);
  await database.collection('v2_report_batch_entries').insertMany(report.entries.map((entry, index) => ({
    id: uuid(),
    batch_id: batchId,
    entry_id: String(entry.id),
    row_number: index + 1,
    entry_snapshot_json: JSON.stringify(entry),
  })));
  const batch = reportBatchRow(row);
  await storeOperationalReportFragmentHtmlSnapshot(database, workspaceId, batch, workspace, userId, 'stored', 'Initial operational fragment HTML snapshot');
  await audit(database, workspaceId, 'report_batch', batchId, 'operational_fragment_create', null, batch, userId);
  return batch;
}

async function updateOperationalReportFragment(database, workspaceId, batchId, input, userId = USER_ID) {
  const { workspace } = await requireWorkspaceFullReader(database, workspaceId, userId);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const existing = await database.collection('v2_report_batches').findOne({ workspace_id: workspaceId, id: batchId, batch_type: 'operational_fragment' });
  if (!existing) {
    const error = new Error('report_fragment_not_found');
    error.status = 404;
    throw error;
  }
  const before = reportBatchRow(existing);
  let report = before.summary;
  let sourceEntryIds = before.source_entry_ids;
  let entrySnapshot = before.entry_snapshot;
  let status = Object.hasOwn(input, 'status')
    ? enumInput(optionalStringInput(input, 'status', before.status, 40), ['created', 'sent', 'requires_update', 'returned_for_revision', 'superseded'], 'status')
    : before.status;
  let title = optionalStringInput(input, 'title', before.title, 190);
  let closedAt = before.closed_at;
  if (Object.hasOwn(input, 'closed_date') || Object.hasOwn(input, 'close_date')) {
    const closedDate = optionalDateInput({ closed_date: input.closed_date ?? input.close_date }, 'closed_date', null);
    if (closedDate < before.start_date) {
      const error = new Error('invalid_closed_date');
      error.status = 422;
      throw error;
    }
    closedAt = `${closedDate} 23:59:59`;
  }
  if (phpBool(input.rebuild_from_entries)) {
    report = await buildOperationalFragmentReport(database, workspaceId, sourceEntryIds, userId, batchId);
    sourceEntryIds = report.source_trace.fragment_entry_ids || [];
    entrySnapshot = report.entries || [];
    status = status === 'superseded' ? 'superseded' : 'created';
    closedAt = null;
  }
  if (['returned_for_revision', 'superseded'].includes(status)) closedAt = null;
  const payload = {
    report_type: 'operational_fragment',
    workspace_id: workspaceId,
    batch_id: batchId,
    title,
    status,
    closed_at: closedAt,
    summary: report,
    source_entry_ids: sourceEntryIds,
    entry_snapshot: entrySnapshot,
  };
  const patch = {
    title,
    status,
    start_date: report.header?.start_date || before.start_date,
    end_date: report.header?.end_date || before.end_date,
    from_entry_id: report.header?.from_entry_id || before.from_entry_id,
    to_entry_id: report.header?.to_entry_id || before.to_entry_id,
    entry_count: sourceEntryIds.length,
    generated_at: nowSql(),
    closed_at: closedAt,
    summary_json: JSON.stringify(report),
    source_trace_json: JSON.stringify(report.source_trace || before.source_trace),
    source_entry_ids_json: JSON.stringify(sourceEntryIds),
    entry_snapshot_json: JSON.stringify(entrySnapshot),
    content_hash: contentHash(payload),
    updated_at: nowSql(),
  };
  await database.collection('v2_report_batches').updateOne(
    { workspace_id: workspaceId, id: batchId, batch_type: 'operational_fragment' },
    { $set: patch }
  );
  if (phpBool(input.rebuild_from_entries)) {
    await database.collection('v2_report_batch_entries').deleteMany({ batch_id: batchId });
    if (entrySnapshot.length) {
      await database.collection('v2_report_batch_entries').insertMany(entrySnapshot.map((entry, index) => ({
        id: uuid(),
        batch_id: batchId,
        entry_id: String(entry.id),
        row_number: index + 1,
        entry_snapshot_json: JSON.stringify(entry),
      })));
    }
  }
  const refreshed = await database.collection('v2_report_batches').findOne({ workspace_id: workspaceId, id: batchId, batch_type: 'operational_fragment' });
  const after = reportBatchRow(refreshed);
  await storeOperationalReportFragmentHtmlSnapshot(database, workspaceId, after, workspace, userId, 'stored', phpBool(input.rebuild_from_entries) ? 'Snapshot after report revision save' : 'Auto snapshot after operational fragment update');
  await audit(database, workspaceId, 'report_batch', batchId, phpBool(input.rebuild_from_entries) ? 'operational_fragment_rebuild' : 'operational_fragment_update', before, after, userId);
  return after;
}

async function createOperationalReportFragmentHtmlSnapshot(database, workspaceId, batchId, input, userId = USER_ID) {
  const { workspace } = await requireWorkspaceFullReader(database, workspaceId, userId);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const row = await database.collection('v2_report_batches').findOne({ workspace_id: workspaceId, id: batchId, batch_type: 'operational_fragment' });
  if (!row) {
    const error = new Error('report_fragment_not_found');
    error.status = 404;
    throw error;
  }
  const batch = reportBatchRow(row);
  const status = enumInput(optionalStringInput(input, 'status', batch.closed_at ? 'closed' : 'stored', 40), ['stored', 'closed'], 'status');
  const comment = optionalStringInput(input, 'comment', null, 1000);
  const snapshot = await storeOperationalReportFragmentHtmlSnapshot(database, workspaceId, batch, workspace, userId, status, comment);
  await audit(database, workspaceId, 'report_html_snapshot', snapshot.id, 'operational_fragment_html_snapshot_create', null, snapshot, userId);
  return snapshot;
}

async function latestOperationalHtmlSnapshotForBatch(database, workspaceId, batchId) {
  const rows = await database.collection('v2_report_batch_html_snapshots')
    .find({ workspace_id: workspaceId, batch_id: batchId })
    .sort({ version: -1, created_at: -1 })
    .limit(1)
    .toArray();
  return rows[0] ? reportBatchHtmlSnapshotRow(rows[0], false) : null;
}

async function reportPackageFragments(database, workspaceId, fragmentIds) {
  const rows = await database.collection('v2_report_batches')
    .find({ workspace_id: workspaceId, batch_type: 'operational_fragment', id: { $in: fragmentIds } })
    .toArray();
  if (rows.length !== fragmentIds.length) {
    const error = new Error('report_fragment_not_found');
    error.status = 404;
    throw error;
  }
  return rows.map(reportBatchRow).sort((left, right) => {
    const date = String(left.start_date).localeCompare(String(right.start_date));
    if (date !== 0) return date;
    return String(left.created_at || '').localeCompare(String(right.created_at || ''));
  });
}

function operationalPackageFragmentIds(input) {
  let raw = input.fragment_ids ?? input.report_ids ?? [];
  if (typeof raw === 'string') raw = raw.split(',').map((item) => item.trim());
  if (!Array.isArray(raw)) {
    const error = new Error('invalid_fragment_ids');
    error.status = 422;
    throw error;
  }
  const ids = [];
  for (const candidate of raw) {
    const id = String(candidate || '').trim().toLowerCase();
    if (!id) continue;
    if (!/^[a-f0-9-]{36}$/i.test(id)) {
      const error = new Error('invalid_fragment_ids');
      error.status = 422;
      throw error;
    }
    appendUnique(ids, id);
  }
  if (ids.length < 2) {
    const error = new Error('report_package_requires_multiple_fragments');
    error.status = 422;
    throw error;
  }
  return ids;
}

function operationalFragmentIsClosed(fragment) {
  return Boolean(fragment.closed_at) && !['draft', 'requires_update', 'returned_for_revision', 'superseded'].includes(String(fragment.status));
}

async function nextReportVersion(database, workspaceId, reportId, reportType) {
  const rows = await database.collection('v2_report_versions')
    .find({ workspace_id: workspaceId, report_id: reportId, report_type: reportType })
    .sort({ version: -1 })
    .limit(1)
    .toArray();
  return rows[0] ? Number(rows[0].version || 0) + 1 : 1;
}

async function storeReportVersion(database, workspaceId, reportId, reportType, status, htmlFilename, hash, payload, userId) {
  const row = {
    id: uuid(),
    workspace_id: workspaceId,
    report_type: reportType,
    report_id: reportId,
    version: await nextReportVersion(database, workspaceId, reportId, reportType),
    format: 'html',
    status,
    html_filename: htmlFilename,
    content_hash: hash,
    payload_json: JSON.stringify(payload),
    created_by: userId,
    created_at: nowSql(),
  };
  await database.collection('v2_report_versions').insertOne(row);
  return reportVersionRow(row);
}

function operationalReportPackageSummary(workspaceId, fragments, title, comment) {
  const sourceEntryIds = [];
  const categories = new Map();
  const totals = {
    opening_cash: fragments[0]?.summary?.totals?.opening_cash ?? null,
    total_cash_income: 0,
    cash_income: 0,
    cash_expense: 0,
    card_expense: 0,
    commercial_income: 0,
    other_review_total: 0,
    lower_accounting_total: 0,
    corrections_total: 0,
    ending_cash: fragments[fragments.length - 1]?.summary?.totals?.ending_cash ?? null,
  };
  for (const fragment of fragments) {
    for (const id of fragment.source_entry_ids || []) appendUnique(sourceEntryIds, id);
    for (const key of ['total_cash_income', 'cash_income', 'cash_expense', 'card_expense', 'commercial_income', 'other_review_total', 'lower_accounting_total', 'corrections_total']) {
      totals[key] += Number(fragment.summary?.totals?.[key] || 0);
    }
    for (const row of fragment.summary?.blocks?.categories?.rows || []) {
      if (!categories.has(row.category_code)) {
        categories.set(row.category_code, {
          category_code: row.category_code,
          category_name: row.category_name,
          direction: row.direction,
          cash_total: 0,
          card_total: 0,
          total: 0,
          entry_count: 0,
          review_count: 0,
          source_entry_ids: [],
        });
      }
      const category = categories.get(row.category_code);
      category.cash_total += Number(row.cash_total || 0);
      category.card_total += Number(row.card_total || 0);
      category.total += Number(row.total || 0);
      category.entry_count += Number(row.entry_count || 0);
      category.review_count += Number(row.review_count || 0);
      for (const id of row.source_entry_ids || []) appendUnique(category.source_entry_ids, id);
    }
  }
  return {
    header: {
      package_type: 'operational_fragment_package',
      workspace_id: workspaceId,
      title,
      start_date: fragments[0].start_date,
      end_date: fragments[fragments.length - 1].end_date,
      fragment_count: fragments.length,
      entries_count: sourceEntryIds.length,
      generated_at: new Date().toISOString(),
      closed_at: fragments[fragments.length - 1].closed_at,
      comment: comment || null,
    },
    totals,
    blocks: { categories: { rows: Array.from(categories.values()).sort((a, b) => String(a.category_code).localeCompare(String(b.category_code))) } },
    source_entry_ids: sourceEntryIds,
    fragment_ids: fragments.map((fragment) => fragment.id),
  };
}

async function createOperationalReportPackage(database, workspaceId, input, userId = USER_ID) {
  const { workspace } = await requireWorkspaceFullReader(database, workspaceId, userId);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const fragmentIds = operationalPackageFragmentIds(input);
  const fragments = await reportPackageFragments(database, workspaceId, fragmentIds);
  for (const fragment of fragments) {
    if (!operationalFragmentIsClosed(fragment)) {
      const error = new Error('report_package_requires_closed_fragments');
      error.status = 422;
      throw error;
    }
  }
  const seenEntryIds = new Set();
  for (const fragment of fragments) {
    for (const id of fragment.source_entry_ids || []) {
      if (seenEntryIds.has(String(id))) {
        const error = new Error('report_package_overlapping_fragments');
        error.status = 422;
        throw error;
      }
      seenEntryIds.add(String(id));
    }
  }
  const htmlSnapshots = {};
  for (const fragment of fragments) {
    let snapshot = await latestOperationalHtmlSnapshotForBatch(database, workspaceId, fragment.id);
    if (!snapshot) snapshot = await storeOperationalReportFragmentHtmlSnapshot(database, workspaceId, fragment, workspace, userId, 'closed', 'Package freeze HTML snapshot');
    htmlSnapshots[fragment.id] = snapshot;
  }
  const startDate = fragments[0].start_date;
  const endDate = fragments[fragments.length - 1].end_date;
  const title = optionalStringInput(input, 'title', `Пакет отчетных фрагментов ${startDate} - ${endDate}`, 190);
  const status = enumInput(optionalStringInput(input, 'status', 'created', 40), ['created', 'sent'], 'status');
  const comment = optionalStringInput(input, 'comment', null, 1000);
  const summary = operationalReportPackageSummary(workspaceId, fragments, title, comment);
  const packageId = uuid();
  const htmlFilename = `storage/v2/report-packages/${workspaceId}/${packageId}.html`;
  const payload = {
    package_type: 'operational_fragment_package',
    workspace_id: workspaceId,
    package_id: packageId,
    title,
    status,
    summary,
    fragment_ids: fragments.map((fragment) => fragment.id),
    html_snapshot_ids: Object.values(htmlSnapshots).map((snapshot) => snapshot.id),
    source_entry_ids: summary.source_entry_ids,
  };
  const hash = contentHash(payload);
  const row = {
    id: packageId,
    workspace_id: workspaceId,
    package_type: 'operational_fragment_package',
    title,
    status,
    start_date: startDate,
    end_date: endDate,
    fragment_count: fragments.length,
    entry_count: summary.source_entry_ids.length,
    generated_at: nowSql(),
    closed_at: summary.header.closed_at || null,
    comment,
    html_filename: htmlFilename,
    summary_json: JSON.stringify(summary),
    fragment_ids_json: JSON.stringify(fragments.map((fragment) => fragment.id)),
    source_entry_ids_json: JSON.stringify(summary.source_entry_ids),
    content_hash: hash,
    created_by: userId,
    created_at: nowSql(),
    updated_at: null,
  };
  await database.collection('v2_report_packages').insertOne(row);
  await database.collection('v2_report_package_items').insertMany(fragments.map((fragment, index) => ({
    id: uuid(),
    package_id: packageId,
    batch_id: fragment.id,
    html_snapshot_id: htmlSnapshots[fragment.id].id,
    item_order: index + 1,
    fragment_snapshot_json: JSON.stringify(fragment),
    html_snapshot_json: JSON.stringify(htmlSnapshots[fragment.id]),
  })));
  let details = await reportPackageDetail(database, row);
  details.html_content = reportPackageHtml(details, workspace);
  await storeReportVersion(database, workspaceId, packageId, 'operational_package', status, htmlFilename, hash, details, userId);
  details = await reportPackageDetail(database, row);
  await audit(database, workspaceId, 'report_package', packageId, 'operational_fragment_package_create', null, details, userId);
  return details;
}

async function layer1SummaryReport(database, workspaceId, query) {
  const { workspace } = await requireWorkspace(database, workspaceId);
  const year = Number(query.year || new Date().getFullYear());
  const month = Number(query.month || (new Date().getMonth() + 1));
  const fromYear = Number(query.from_year || year);
  const fromMonth = Number(query.from_month || month);
  const toYear = Number(query.to_year || year);
  const toMonth = Number(query.to_month || month);
  const start = `${fromYear}-${String(fromMonth).padStart(2, '0')}-01`;
  const end = monthEndExclusive(toYear, toMonth);
  const monthlyStart = (await handleApi('GET', `/api/workspaces/${workspaceId}/reports/monthly`, { year: String(fromYear), month: String(fromMonth) })).report;
  const monthlyEnd = (await handleApi('GET', `/api/workspaces/${workspaceId}/reports/monthly`, { year: String(toYear), month: String(toMonth) })).report;
  const entries = (await joinedEntries(database, workspaceId, { from: start, to: end })).filter((entry) => entry.date >= start && entry.date < end);
  const categories = new Map();
  const otherReviewEntries = [];
  const sourceTrace = {
    totals: {
      opening_cash: [],
      total_cash_income: [],
      cash_income: [],
      cash_expense: [],
      card_expense: [],
      commercial_income: [],
      other_review_total: [],
      lower_accounting_total: [],
      corrections_total: [],
      ending_cash: [],
    },
    categories: {},
    basis: { opening_cash: null },
  };
  const totals = {
    opening_cash: monthlyStart.opening_cash,
    total_cash_income: 0,
    cash_income: 0,
    cash_expense: 0,
    card_expense: 0,
    commercial_income: 0,
    other_review_total: 0,
    lower_accounting_total: 0,
    corrections_total: 0,
    ending_cash: monthlyEnd.ending_cash,
  };
  let cardExpenseCount = 0;
  let cardReviewCount = 0;
  const cardByCategory = new Map();

  for (const entry of entries) {
    if (entry.status === 'other_review' && entry.flow.type === 'card') cardReviewCount += 1;
    if (entry.status === 'other_review' && entry.entry_type === 'cash_expense' && entry.category_code === 'other') {
      otherReviewEntries.push(entry);
      totals.other_review_total += entry.amount || 0;
      sourceTrace.totals.other_review_total.push(entry.id);
    }
    if (!COUNTED_STATUSES.has(entry.status) || entry.amount === null) continue;
    const id = entry.id;
    const value = entry.amount;
    const code = entry.category_code || 'uncategorized_review';
    if (entry.accounting_section === 'lower_accounting') {
      totals.lower_accounting_total += value;
      sourceTrace.totals.lower_accounting_total.push(id);
      continue;
    }
    if (!categories.has(code)) {
      categories.set(code, {
        category_code: code,
        category_name: entry.category_name || { ru: categoryNameRu(entry), en: code },
        direction: entry.direction === 'in' ? 'income' : 'expense',
        cash_total: 0,
        card_total: 0,
        total: 0,
        entry_count: 0,
        review_count: 0,
        source_entry_ids: [],
      });
    }
    const category = categories.get(code);
    category.entry_count += 1;
    category.review_count += entry.status === 'other_review' || !entry.category_code ? 1 : 0;
    category.total += value;
    category.source_entry_ids.push(id);
    sourceTrace.categories[code] = category.source_entry_ids;
    if (entry.flow.type === 'cash') category.cash_total += value;
    if (entry.flow.type === 'card') category.card_total += value;

    if (entry.flow.type === 'cash' && entry.direction === 'in' && entry.entry_type === 'cash_income') {
      if (entry.category_code === 'commercial_income') {
        totals.commercial_income += value;
        sourceTrace.totals.commercial_income.push(id);
      } else if (entry.category_code !== 'cash_topup_from_card') {
        totals.cash_income += value;
        sourceTrace.totals.cash_income.push(id);
      }
      sourceTrace.totals.total_cash_income.push(id);
    }
    if (entry.flow.type === 'cash' && entry.direction === 'out' && entry.entry_type === 'cash_expense') {
      totals.cash_expense += value;
      sourceTrace.totals.cash_expense.push(id);
    }
    if (entry.flow.type === 'card' && entry.direction === 'out' && entry.entry_type === 'card_expense') {
      totals.card_expense += value;
      cardExpenseCount += 1;
      sourceTrace.totals.card_expense.push(id);
      if (!cardByCategory.has(code)) {
        cardByCategory.set(code, {
          category_code: code,
          category_name: category.category_name,
          total: 0,
          entry_count: 0,
          source_entry_ids: [],
        });
      }
      const cardCategory = cardByCategory.get(code);
      cardCategory.total += value;
      cardCategory.entry_count += 1;
      cardCategory.source_entry_ids.push(id);
    }
    if (entry.entry_type === 'correction') {
      totals.corrections_total += entry.direction === 'out' ? -value : value;
      sourceTrace.totals.corrections_total.push(id);
    }
  }
  totals.total_cash_income = totals.cash_income + totals.commercial_income;
  sourceTrace.totals.ending_cash = sourceTrace.totals.cash_income.concat(
    sourceTrace.totals.commercial_income,
    sourceTrace.totals.cash_expense,
    sourceTrace.totals.corrections_total
  );
  const rows = Array.from(categories.values()).sort((a, b) => String(a.category_code).localeCompare(String(b.category_code)));
  const totalRow = rows.reduce((acc, row) => {
    acc.cash_total += row.cash_total;
    acc.card_total += row.card_total;
    acc.total += row.total;
    acc.entry_count += row.entry_count;
    acc.review_count += row.review_count;
    acc.source_entry_ids.push(...row.source_entry_ids);
    return acc;
  }, { cash_total: 0, card_total: 0, total: 0, entry_count: 0, review_count: 0, source_entry_ids: [] });

  return {
    header: {
      workspace: { id: workspace.id, name: workspace.name, type: workspace.type },
      period: {
        year: fromYear,
        month: fromMonth,
        month_key: fromYear === toYear && fromMonth === toMonth
          ? `${fromYear}-${String(fromMonth).padStart(2, '0')}`
          : `${fromYear}-${String(fromMonth).padStart(2, '0')} - ${toYear}-${String(toMonth).padStart(2, '0')}`,
        from_year: fromYear,
        from_month: fromMonth,
        to_year: toYear,
        to_month: toMonth,
        from_month_key: `${fromYear}-${String(fromMonth).padStart(2, '0')}`,
        to_month_key: `${toYear}-${String(toMonth).padStart(2, '0')}`,
        start_date: start,
        end_date_exclusive: end,
        is_range: !(fromYear === toYear && fromMonth === toMonth),
      },
      currency: workspace.currency || 'EUR',
      status: monthlyEnd.is_closed ? 'closed' : 'open',
      is_closed: Boolean(monthlyEnd.is_closed),
      generated_at: new Date().toISOString(),
      entries_count: entries.length,
      review_count: otherReviewEntries.length,
    },
    totals,
    money_position: { physical_available: totals.ending_cash, cash_available: totals.ending_cash },
    blocks: {
      cash: {
        opening_cash: totals.opening_cash,
        opening_cash_basis: sourceTrace.basis.opening_cash,
        cash_income: totals.cash_income,
        cash_topup_from_card: 0,
        commercial_income: totals.commercial_income,
        cash_expense: totals.cash_expense,
        corrections_total: totals.corrections_total,
        ending_cash: totals.ending_cash,
        source_entry_ids: sourceTrace.totals.ending_cash,
      },
      money_position: { physical_available: totals.ending_cash, cash_available: totals.ending_cash },
      card: {
        card_expense: totals.card_expense,
        cash_topup_to_cash: 0,
        entries_count: cardExpenseCount,
        review_count: cardReviewCount,
        by_category: Array.from(cardByCategory.values()),
        source_entry_ids: sourceTrace.totals.card_expense,
      },
      categories: { rows, total_row: totalRow },
      other_review: {
        count: otherReviewEntries.length,
        total: totals.other_review_total,
        entries: otherReviewEntries,
        source_entry_ids: sourceTrace.totals.other_review_total,
      },
      lower_accounting: {
        count: 0,
        total: totals.lower_accounting_total,
        issued_total: totals.lower_accounting_total,
        entries: [],
        settlements: {},
        source_entry_ids: sourceTrace.totals.lower_accounting_total,
      },
    },
    source_trace: sourceTrace,
  };
}

function flattenSourceEntryIds(sourceTrace) {
  const ids = [];
  const walk = (value) => {
    if (Array.isArray(value)) {
      for (const item of value) walk(item);
      return;
    }
    if (value && typeof value === 'object') {
      for (const item of Object.values(value)) walk(item);
      return;
    }
    if (typeof value === 'string' && /^[a-f0-9-]{36}$/i.test(value)) appendUnique(ids, value);
  };
  walk(sourceTrace?.totals || {});
  walk(sourceTrace?.categories || {});
  return ids;
}

async function attachmentRefsForEntryIds(database, workspaceId, entryIds) {
  if (!entryIds.length) return [];
  const entries = await database.collection('v2_entries')
    .find({ workspace_id: workspaceId, id: { $in: entryIds }, archived_at: null })
    .project({ id: 1 })
    .toArray();
  const allowed = new Set(entries.map((entry) => String(entry.id)));
  if (!allowed.size) return [];
  const attachments = await database.collection('v2_attachments')
    .find({ entry_id: { $in: Array.from(allowed) } })
    .sort({ created_at: 1, id: 1 })
    .toArray();
  return attachments.map((attachment) => ({
    entry_id: String(attachment.entry_id),
    id: String(attachment.id),
    file_name: String(attachment.file_name),
    file_url: String(attachment.file_url),
    mime_type: attachment.mime_type || null,
    size_bytes: attachment.size_bytes === null || attachment.size_bytes === undefined ? null : Number(attachment.size_bytes),
    image_mode: attachment.image_mode || null,
  }));
}

async function nextReportSnapshotVersion(database, workspaceId, reportType, year, month) {
  const rows = await database.collection('v2_report_snapshots')
    .find({ workspace_id: workspaceId, report_type: reportType, year, month })
    .sort({ version: -1 })
    .limit(1)
    .toArray();
  return rows[0] ? Number(rows[0].version || 0) + 1 : 1;
}

async function closedAtForMonth(database, workspaceId, year, month) {
  const closure = await database.collection('v2_monthly_closures').findOne({ workspace_id: workspaceId, year, month, is_closed: 1 });
  return closure ? closure.closed_at || null : null;
}

async function createLayer1SummarySnapshot(database, workspaceId, input, userId = USER_ID) {
  await requireWorkspace(database, workspaceId, userId);
  await requireWorkspaceWriter(database, workspaceId, userId);
  const year = optionalIntInput(input, 'year', new Date().getFullYear());
  const month = optionalIntInput(input, 'month', new Date().getMonth() + 1);
  assertValidMonth(year, month);
  const report = await layer1SummaryReport(database, workspaceId, { year: String(year), month: String(month) });
  const sourceTrace = report.source_trace || {};
  const sourceEntryIds = flattenSourceEntryIds(sourceTrace);
  const correctionIds = Array.isArray(sourceTrace?.totals?.corrections_total)
    ? sourceTrace.totals.corrections_total.map(String)
    : [];
  const attachmentRefs = await attachmentRefsForEntryIds(database, workspaceId, sourceEntryIds);
  let status = optionalStringInput(input, 'status', null, 40);
  if (status === null) status = report.header?.is_closed ? 'closed' : 'stored';
  status = enumInput(status, ['draft', 'stored', 'closed'], 'status');
  if (status === 'closed' && !report.header?.is_closed) {
    const error = new Error('month_not_closed');
    error.status = 422;
    throw error;
  }
  const comment = optionalStringInput(input, 'comment', null, 1000);
  const version = await nextReportSnapshotVersion(database, workspaceId, 'layer1_summary', year, month);
  const snapshotId = uuid();
  const payload = {
    report_type: 'layer1_summary',
    workspace_id: workspaceId,
    year,
    month,
    version,
    status,
    summary: report,
    source_entry_ids: sourceEntryIds,
    correction_ids: correctionIds,
    attachment_refs: attachmentRefs,
    forecast_snapshot: null,
  };
  const row = {
    id: snapshotId,
    workspace_id: workspaceId,
    report_type: 'layer1_summary',
    year,
    month,
    version,
    status,
    generated_at: nowSql(),
    stored_at: nowSql(),
    closed_at: status === 'closed' ? await closedAtForMonth(database, workspaceId, year, month) : null,
    comment,
    summary_json: JSON.stringify(report),
    source_trace_json: JSON.stringify(sourceTrace),
    source_entry_ids_json: JSON.stringify(sourceEntryIds),
    correction_ids_json: JSON.stringify(correctionIds),
    attachment_refs_json: JSON.stringify(attachmentRefs),
    forecast_snapshot_json: null,
    content_hash: contentHash(payload),
    created_by: userId,
    created_at: nowSql(),
  };
  await database.collection('v2_report_snapshots').insertOne(row);
  const snapshot = reportSnapshotRow(row);
  await audit(database, workspaceId, 'report_snapshot', snapshotId, 'layer1_snapshot_create', null, snapshot, userId);
  return snapshot;
}

async function handleApi(method, route, query = {}, input = {}, userId = USER_ID) {
  const database = await db();
  const assistantSettingsWrite = method === 'PATCH'
    && /^\/api\/workspaces\/([a-f0-9-]{36})\/assistant-settings$/i.test(route);
  const workspaceCollectionWrite = (method === 'POST' && route === '/api/workspaces')
    || (['PATCH', 'DELETE'].includes(method) && /^\/api\/workspaces\/([a-f0-9-]{36})$/i.test(route));
  const flowWrite = method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/flows$/i.test(route);
  const categoryRuleWrite = method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/category-rules$/i.test(route);
  const dictionaryTrainingDecisionWrite = method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-training-decisions$/i.test(route);
  const dictionaryInternetReferenceWrite = (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-training-internet-reference$/i.test(route))
    || (method === 'PATCH' && /^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-training-internet-reference\/lookups\/([a-f0-9-]{36})$/i.test(route));
  const rawHistoryConvertWrite = method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/raw-history\/convert$/i.test(route);
  const legacyImportWrite = (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/imports\/excel$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/imports\/([a-f0-9-]{36})\/accept$/i.test(route));
  const workspaceInviteWrite = (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/invites$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/invites\/([a-f0-9-]{36})\/revoke$/i.test(route))
    || (method === 'POST' && route === '/api/workspace-invites/preview')
    || (method === 'POST' && route === '/api/workspace-invites/accept');
  const accountableOfferWrite = (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/accountable-offers$/i.test(route))
    || (method === 'POST' && /^\/api\/accountable-offers\/([a-f0-9-]{36})\/accept$/i.test(route));
  const accountableReportWrite = (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/accountable-reports$/i.test(route))
    || (method === 'POST' && /^\/api\/accountable-reports\/([a-f0-9-]{36})\/submit$/i.test(route))
    || (method === 'POST' && /^\/api\/accountable-reports\/([a-f0-9-]{36})\/review-preview$/i.test(route))
    || (method === 'POST' && /^\/api\/accountable-reports\/([a-f0-9-]{36})\/accept$/i.test(route))
    || (method === 'POST' && /^\/api\/accountable-reports\/([a-f0-9-]{36})\/materialization-preview$/i.test(route))
    || (method === 'POST' && /^\/api\/accountable-reports\/([a-f0-9-]{36})\/materialize$/i.test(route))
    || (method === 'POST' && /^\/api\/accountable-settlements\/([a-f0-9-]{36})\/cash-resolve$/i.test(route));
  const operationalEntryWrite = (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/entries$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/parse-preview$/i.test(route))
    || (method === 'POST' && route === '/api/parse-entry-preview')
    || (method === 'PATCH' && /^\/api\/entries\/([a-f0-9-]{36})$/i.test(route))
    || (method === 'PATCH' && /^\/api\/entries\/([a-f0-9-]{36})\/category$/i.test(route))
    || (method === 'POST' && /^\/api\/entries\/([a-f0-9-]{36})\/category\/closed-month-decision$/i.test(route))
    || (method === 'DELETE' && /^\/api\/entries\/([a-f0-9-]{36})$/i.test(route));
  const attachmentWrite = (method === 'POST' && /^\/api\/entries\/([a-f0-9-]{36})\/attachments$/i.test(route))
    || (method === 'DELETE' && /^\/api\/attachments\/([a-f0-9-]{36})$/i.test(route));
  const monthWrite = method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/months\/([0-9]{4})\/([0-9]{1,2})\/(close|reopen|correction)$/i.test(route);
  const reportWrite = (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/batch-preview$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/layer1-snapshots$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/batches$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments\/preview$/i.test(route))
    || (method === 'PATCH' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments\/([a-f0-9-]{36})$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments\/([a-f0-9-]{36})\/html-snapshots$/i.test(route))
    || (method === 'POST' && /^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-packages$/i.test(route));
  if (method !== 'GET' && !assistantSettingsWrite && !workspaceCollectionWrite && !flowWrite && !categoryRuleWrite && !dictionaryTrainingDecisionWrite && !dictionaryInternetReferenceWrite && !rawHistoryConvertWrite && !legacyImportWrite && !workspaceInviteWrite && !accountableOfferWrite && !accountableReportWrite && !operationalEntryWrite && !attachmentWrite && !monthWrite && !reportWrite) {
    const error = new Error('atlas_write_route_not_supported');
    error.status = 405;
    throw error;
  }

  if (method === 'POST' && route === '/api/workspaces') {
    return { ok: true, workspace: await createWorkspace(database, input, userId) };
  }

  if (route === '/api/workspaces') {
    const members = await database.collection('v2_workspace_members').find({ user_id: userId }).toArray();
    const ids = members.map((member) => member.workspace_id);
    const workspaces = await database.collection('v2_workspaces')
      .find({ id: { $in: ids }, archived_at: null })
      .sort({ name: 1 })
      .toArray();
    const memberMap = new Map(members.map((member) => [String(member.workspace_id), member]));
    return { ok: true, workspaces: workspaces.map((workspace) => workspaceRow(workspace, memberMap.get(String(workspace.id)))) };
  }

  let match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})$/i);
  if (match) {
    if (method === 'PATCH') {
      return { ok: true, workspace: await updateWorkspace(database, match[1], input, userId) };
    }
    if (method === 'DELETE') {
      return { ok: true, workspace: await deleteWorkspace(database, match[1], userId) };
    }
    const { workspace, member } = await requireWorkspace(database, match[1], userId);
    return { ok: true, workspace: workspaceRow(workspace, member) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/flows$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, flow: await createFlow(database, match[1], input, userId) };
    }
    await requireWorkspace(database, match[1]);
    const flows = await database.collection('v2_flows')
      .find({ workspace_id: match[1] })
      .sort({ is_default: -1, type: 1, name: 1 })
      .toArray();
    return { ok: true, flows: flows.map(flowRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/invites$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, invite: await createWorkspaceInvite(database, match[1], input, userId) };
    }
    await requireWorkspaceAdmin(database, match[1], userId);
    const invites = await database.collection('v2_workspace_invites')
      .find({ workspace_id: match[1] })
      .sort({ created_at: -1 })
      .limit(100)
      .toArray();
    return { ok: true, invites: invites.map(workspaceInviteRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/invites\/([a-f0-9-]{36})\/revoke$/i);
  if (match && method === 'POST') {
    return { ok: true, invite: await revokeWorkspaceInvite(database, match[1], match[2], userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/employee-mode$/i);
  if (match) {
    const { workspace, member } = await requireWorkspace(database, match[1]);
    const access = workspaceAccessFromMember(member);
    if (access.can_read_workspace) {
      const error = new Error('employee_mode_not_required');
      error.status = 422;
      throw error;
    }
    const offers = await listAccountableOffers(database, match[1], query);
    const reports = await listAccountableReports(database, match[1], query);
    return {
      ok: true,
      workspace: workspaceRow(workspace, member),
      offers,
      reports,
      summary: {
        pending_total: offers.filter((offer) => offer.status === 'pending_offer').reduce((sum, offer) => sum + offer.amount, 0),
        accepted_total: offers.filter((offer) => offer.status === 'accepted_by_employee').reduce((sum, offer) => sum + offer.amount, 0),
        draft_reports: reports.filter((report) => report.status === 'draft').length,
        submitted_reports: reports.filter((report) => report.status === 'submitted').length,
        open_offers: offers.filter((offer) => ['pending_offer', 'accepted_by_employee'].includes(offer.status)).length,
      },
    };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/accountable-dashboard$/i);
  if (match) {
    return { ok: true, dashboard: await accountableDashboard(database, match[1]) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/accountable-offers$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, offer: await createAccountableOffer(database, match[1], input, userId) };
    }
    return { ok: true, offers: await listAccountableOffers(database, match[1], query, userId) };
  }

  match = route.match(/^\/api\/accountable-offers\/([a-f0-9-]{36})\/accept$/i);
  if (match && method === 'POST') {
    return { ok: true, offer: await acceptAccountableOffer(database, match[1], userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/accountable-reports$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, report: await createAccountableReport(database, match[1], input, userId) };
    }
    return { ok: true, reports: await listAccountableReports(database, match[1], query, userId) };
  }

  match = route.match(/^\/api\/accountable-reports\/([a-f0-9-]{36})\/submit$/i);
  if (match && method === 'POST') {
    return { ok: true, report: await submitAccountableReport(database, match[1], userId) };
  }

  match = route.match(/^\/api\/accountable-reports\/([a-f0-9-]{36})\/review-preview$/i);
  if (match && method === 'POST') {
    return { ok: true, preview: await previewAccountableReportReview(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/accountable-reports\/([a-f0-9-]{36})\/accept$/i);
  if (match && method === 'POST') {
    return { ok: true, result: await acceptAccountableReportByAdmin(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/accountable-reports\/([a-f0-9-]{36})\/materialization-preview$/i);
  if (match && method === 'POST') {
    return { ok: true, preview: await previewAccountableReportMaterialization(database, match[1], userId) };
  }

  match = route.match(/^\/api\/accountable-reports\/([a-f0-9-]{36})\/materialize$/i);
  if (match && method === 'POST') {
    return { ok: true, result: await materializeAccountableReport(database, match[1], userId) };
  }

  match = route.match(/^\/api\/accountable-settlements\/([a-f0-9-]{36})\/cash-resolve$/i);
  if (match && method === 'POST') {
    return { ok: true, result: await resolveAccountableSettlementWithCashMovement(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/accountable-reports\/([a-f0-9-]{36})$/i);
  if (match) {
    const report = await database.collection('v2_accountable_reports').findOne({ id: match[1] });
    if (!report) {
      const error = new Error('accountable_report_not_found');
      error.status = 404;
      throw error;
    }
    const access = await workspaceAccess(database, String(report.workspace_id));
    if (!access.can_admin && !(access.role === 'employee' && Number(report.employee_user_id) === USER_ID)) {
      const error = new Error(access.role === 'employee' ? 'accountable_report_not_found' : 'workspace_admin_required');
      error.status = access.role === 'employee' ? 404 : 403;
      throw error;
    }
    return { ok: true, report: await accountableReportDetail(database, report) };
  }

  match = route.match(/^\/api\/accountable-reports\/([a-f0-9-]{36})\/materialization$/i);
  if (match) {
    const report = await database.collection('v2_accountable_reports').findOne({ id: match[1] });
    if (!report) {
      const error = new Error('accountable_report_not_found');
      error.status = 404;
      throw error;
    }
    await requireWorkspaceAdmin(database, String(report.workspace_id), userId);
    return { ok: true, materialization: await accountableMaterializationResult(database, match[1]) };
  }

  if (method === 'POST' && route === '/api/workspace-invites/preview') {
    return { ok: true, ...(await previewWorkspaceInvite(database, input, userId)) };
  }

  if (method === 'POST' && route === '/api/workspace-invites/accept') {
    return { ok: true, ...(await acceptWorkspaceInvite(database, input, userId)) };
  }

  match = route.match(/^\/api\/workspace-invites\/([a-f0-9]{48})$/i);
  if (match) {
    return { ok: true, ...(await previewWorkspaceInvite(database, { token: match[1] }, userId)) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/categories$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const categories = await database.collection('v2_categories')
      .find({ is_active: 1, $or: [{ workspace_id: null }, { workspace_id: match[1] }] })
      .sort({ sort_order: 1, code: 1 })
      .toArray();
    return { ok: true, categories: categories.map(categoryRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/category-rules$/i);
  if (match && method === 'POST') {
    return { ok: true, category_rule: await createCategoryRule(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/entries$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, entry: await createEntry(database, match[1], entryCreateInput(input), userId) };
    }
    await requireWorkspace(database, match[1]);
    return { ok: true, entries: await joinedEntries(database, match[1], query) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/parse-preview$/i);
  if (match && method === 'POST') {
    return { ok: true, preview: await previewEntryParse(database, match[1], input, userId) };
  }

  if (method === 'POST' && route === '/api/parse-entry-preview') {
    const workspaceId = requireStringInput(input, 'workspace_id', 36);
    return { ok: true, preview: await previewEntryParse(database, workspaceId, input, userId) };
  }

  match = route.match(/^\/api\/entries\/([a-f0-9-]{36})$/i);
  if (match) {
    if (method === 'PATCH') {
      return { ok: true, entry: await updateEntry(database, match[1], input, userId) };
    }
    if (method === 'DELETE') {
      return { ok: true, entry: await deleteEntry(database, match[1], input, userId) };
    }
  }

  match = route.match(/^\/api\/entries\/([a-f0-9-]{36})\/category$/i);
  if (match && method === 'PATCH') {
    return { ok: true, entry: await updateEntryCategory(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/entries\/([a-f0-9-]{36})\/category\/closed-month-decision$/i);
  if (match && method === 'POST') {
    return { ok: true, ...(await decideClosedMonthEntryCategory(database, match[1], input, userId)) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/months\/([0-9]{4})\/([0-9]{1,2})\/close$/i);
  if (match && method === 'POST') {
    return { ok: true, ...(await closeMonth(database, match[1], Number(match[2]), Number(match[3]), input, userId)) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/months\/([0-9]{4})\/([0-9]{1,2})\/reopen$/i);
  if (match && method === 'POST') {
    return { ok: true, ...(await reopenMonth(database, match[1], Number(match[2]), Number(match[3]), input, userId)) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/months\/([0-9]{4})\/([0-9]{1,2})\/correction$/i);
  if (match && method === 'POST') {
    return { ok: true, entry: await createMonthCorrection(database, match[1], Number(match[2]), Number(match[3]), input, userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/other-expenses$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    return { ok: true, entries: await otherExpenseQueue(database, match[1]) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/summary$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const flows = (await database.collection('v2_flows').find({ workspace_id: match[1] }).toArray()).map(flowRow);
    const cashFlow = flows.find((flow) => flow.type === 'cash' && flow.has_live_balance) || null;
    const cardFlowIds = flows.filter((flow) => flow.type === 'card').map((flow) => flow.id);
    let cashNow = cashFlow ? cashFlow.opening_balance : null;
    if (cashFlow) {
      const latest = await database.collection('v2_entries')
        .find({ flow_id: cashFlow.id, archived_at: null, balance_after: { $ne: null } })
        .sort({ date: -1, created_seq: -1 })
        .limit(1)
        .toArray();
      if (latest[0]) cashNow = amount(latest[0].balance_after);
    }
    const cardEntries = cardFlowIds.length
      ? await database.collection('v2_entries').find({
        flow_id: { $in: cardFlowIds },
        archived_at: null,
        direction: 'out',
        entry_type: 'card_expense',
        status: { $in: Array.from(COUNTED_STATUSES) },
        amount: { $ne: null },
      }).toArray()
      : [];
    const latestEntry = await database.collection('v2_entries')
      .find({ workspace_id: match[1], archived_at: null })
      .sort({ date: -1 })
      .limit(1)
      .toArray();
    return {
      ok: true,
      summary: {
        workspace_id: match[1],
        opening_cash: cashFlow ? cashFlow.opening_balance : null,
        cash_now: cashNow,
        card_expense_total: cardEntries.reduce((sum, entry) => sum + amount(entry.amount), 0),
        latest_entry_date: latestEntry[0] ? latestEntry[0].date : null,
      },
    };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/monthly$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const year = Number(query.year || new Date().getFullYear());
    const month = Number(query.month || (new Date().getMonth() + 1));
    const start = `${year}-${String(month).padStart(2, '0')}-01`;
    const end = monthEndExclusive(year, month);
    const flows = await database.collection('v2_flows').find({ workspace_id: match[1] }).toArray();
    const cashFlow = flows.find((flow) => flow.type === 'cash' && bool(flow.has_live_balance));
    const beforeEntries = cashFlow ? await database.collection('v2_entries')
      .find({ flow_id: cashFlow.id, archived_at: null, date: { $lt: start } })
      .sort({ date: 1, created_seq: 1 })
      .toArray() : [];
    const openingCash = cashFlow ? amount(cashFlow.opening_balance) + beforeEntries.reduce((sum, entry) => sum + (cashBalanceDelta(entry) || 0), 0) : null;
    const entries = await joinedEntries(database, match[1], { from: start, to: end.replace(/-\d{2}$/, '-31') });
    let monthDelta = 0;
    const report = {
      workspace_id: match[1],
      year,
      month,
      month_key: `${year}-${String(month).padStart(2, '0')}`,
      source_files: [],
      opening_cash: openingCash,
      discrepancy_with_previous: 0,
      external_cash_income: 0,
      commercial_income: 0,
      cash_expense: 0,
      card_expense: 0,
      cash_topup_from_card_card_side: 0,
      cash_topup_from_card_cash_side: 0,
      other_expenses: 0,
      corrections: 0,
      ending_cash: openingCash,
      comment: null,
      is_closed: false,
      counts: { entries: 0, counted: 0, unrecognized: 0, other_review: 0 },
    };
    for (const entry of entries.filter((item) => item.date >= start && item.date < end)) {
      report.counts.entries += 1;
      if (entry.status === 'unrecognized') report.counts.unrecognized += 1;
      if (entry.status === 'other_review') report.counts.other_review += 1;
      if (!COUNTED_STATUSES.has(entry.status) || entry.amount === null) continue;
      report.counts.counted += 1;
      const delta = entry.flow.type === 'cash' ? cashBalanceDelta(entry) : null;
      if (delta !== null) monthDelta += delta;
      if (entry.flow.type === 'cash' && entry.direction === 'in' && entry.entry_type === 'cash_income') {
        if (entry.category_code === 'commercial_income') report.commercial_income += entry.amount;
        else if (entry.category_code === 'cash_topup_from_card') report.cash_topup_from_card_cash_side += entry.amount;
        else report.external_cash_income += entry.amount;
      }
      if (entry.flow.type === 'cash' && entry.direction === 'out' && entry.entry_type === 'cash_expense') report.cash_expense += entry.amount;
      if (entry.flow.type === 'card' && entry.direction === 'out' && entry.entry_type === 'card_expense') report.card_expense += entry.amount;
      if (entry.direction === 'out' && entry.category_code === 'other') report.other_expenses += entry.amount;
      if (entry.entry_type === 'correction') report.corrections += entry.direction === 'out' ? -entry.amount : entry.amount;
    }
    if (openingCash !== null) report.ending_cash = openingCash + monthDelta;
    const closure = await database.collection('v2_monthly_closures').findOne({ workspace_id: match[1], year, month });
    if (closure) {
      report.is_closed = bool(closure.is_closed);
      report.comment = closure.comment || null;
    }
    return { ok: true, report };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/layer1-summary$/i);
  if (match) {
    return { ok: true, report: await layer1SummaryReport(database, match[1], query) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/layer1-source-entries$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const ids = sourceEntryIdsFromQuery(query);
    if (!ids.length) return { ok: true, entries: [], missing_ids: [] };
    const entries = await joinedEntries(database, match[1], {});
    const byId = new Map(entries.map((entry) => [String(entry.id), entry]));
    const found = [];
    const missing = [];
    for (const id of ids) {
      if (byId.has(id)) found.push(byId.get(id));
      else missing.push(id);
    }
    return { ok: true, entries: found, missing_ids: missing };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/layer1-snapshots$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, snapshot: await createLayer1SummarySnapshot(database, match[1], input, userId) };
    }
    await requireWorkspace(database, match[1]);
    const filter = { workspace_id: match[1], report_type: 'layer1_summary' };
    if (query.year !== undefined && query.year !== '') filter.year = Number(query.year);
    if (query.month !== undefined && query.month !== '') filter.month = Number(query.month);
    const snapshots = await database.collection('v2_report_snapshots')
      .find(filter)
      .sort({ year: -1, month: -1, version: -1, stored_at: -1 })
      .toArray();
    return { ok: true, snapshots: snapshots.map(reportSnapshotRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/batch-preview$/i);
  if (match && method === 'POST') {
    return { ok: true, preview: await previewOperationalReportFragment(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, fragment: await createOperationalReportFragment(database, match[1], input, userId) };
    }
    await requireWorkspace(database, match[1]);
    const limit = Math.max(1, Math.min(100, Number(query.limit || 30)));
    const fragments = await database.collection('v2_report_batches')
      .find({ workspace_id: match[1], batch_type: 'operational_fragment', status: { $ne: 'superseded' } })
      .sort({ created_at: -1 })
      .limit(limit)
      .toArray();
    return { ok: true, fragments: fragments.map(reportBatchRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments\/preview$/i);
  if (match && method === 'POST') {
    return { ok: true, ...(await previewOperationalReportFragment(database, match[1], input, userId)) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/batches$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, report: await createOperationalReportFragment(database, match[1], input, userId) };
    }
    await requireWorkspace(database, match[1]);
    const limit = Math.max(1, Math.min(100, Number(query.limit || 30)));
    const reports = await database.collection('v2_report_batches')
      .find({ workspace_id: match[1], batch_type: 'operational_fragment', status: { $ne: 'superseded' } })
      .sort({ created_at: -1 })
      .limit(limit)
      .toArray();
    return { ok: true, reports: reports.map(reportBatchRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/batches\/([a-f0-9-]{36})$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const report = await database.collection('v2_report_batches').findOne({
      workspace_id: match[1],
      id: match[2],
      batch_type: 'operational_fragment',
    });
    if (!report) {
      const error = new Error('report_fragment_not_found');
      error.status = 404;
      throw error;
    }
    return { ok: true, report: reportBatchRow(report) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments\/([a-f0-9-]{36})$/i);
  if (match) {
    if (method === 'PATCH') {
      return { ok: true, fragment: await updateOperationalReportFragment(database, match[1], match[2], input, userId) };
    }
    await requireWorkspace(database, match[1]);
    const fragment = await database.collection('v2_report_batches').findOne({
      workspace_id: match[1],
      id: match[2],
      batch_type: 'operational_fragment',
    });
    if (!fragment) {
      const error = new Error('report_fragment_not_found');
      error.status = 404;
      throw error;
    }
    return { ok: true, fragment: reportBatchRow(fragment) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments\/([a-f0-9-]{36})\/html-snapshots$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, snapshot: await createOperationalReportFragmentHtmlSnapshot(database, match[1], match[2], input, userId) };
    }
    await requireWorkspace(database, match[1]);
    const fragment = await database.collection('v2_report_batches').findOne({
      workspace_id: match[1],
      id: match[2],
      batch_type: 'operational_fragment',
    });
    if (!fragment) {
      const error = new Error('report_fragment_not_found');
      error.status = 404;
      throw error;
    }
    const limit = Math.max(1, Math.min(100, Number(query.limit || 30)));
    const snapshots = await database.collection('v2_report_batch_html_snapshots')
      .find({ workspace_id: match[1], batch_id: match[2] })
      .sort({ version: -1, created_at: -1 })
      .limit(limit)
      .toArray();
    return { ok: true, snapshots: snapshots.map((snapshot) => reportBatchHtmlSnapshotRow(snapshot, false)) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-fragments\/([a-f0-9-]{36})\/html-snapshots\/([a-f0-9-]{36})$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const fragment = await database.collection('v2_report_batches').findOne({
      workspace_id: match[1],
      id: match[2],
      batch_type: 'operational_fragment',
    });
    if (!fragment) {
      const error = new Error('report_fragment_not_found');
      error.status = 404;
      throw error;
    }
    const snapshot = await database.collection('v2_report_batch_html_snapshots').findOne({
      id: match[3],
      workspace_id: match[1],
      batch_id: match[2],
    });
    if (!snapshot) {
      const error = new Error('report_html_snapshot_not_found');
      error.status = 404;
      throw error;
    }
    return { ok: true, snapshot: reportBatchHtmlSnapshotRow(snapshot, true) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-packages$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, package: await createOperationalReportPackage(database, match[1], input, userId) };
    }
    await requireWorkspace(database, match[1]);
    const limit = Math.max(1, Math.min(100, Number(query.limit || 30)));
    const packages = await database.collection('v2_report_packages')
      .find({ workspace_id: match[1], package_type: 'operational_fragment_package' })
      .sort({ created_at: -1 })
      .limit(limit)
      .toArray();
    return { ok: true, packages: packages.map(reportPackageRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/operational-packages\/([a-f0-9-]{36})$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const packageRow = await database.collection('v2_report_packages').findOne({
      workspace_id: match[1],
      id: match[2],
    });
    if (!packageRow) {
      const error = new Error('report_package_not_found');
      error.status = 404;
      throw error;
    }
    return { ok: true, package: await reportPackageDetail(database, packageRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/category-matrix$/i);
  if (match) {
    return { ok: true, matrix: await categoryMatrixReport(database, match[1], query) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/reports\/other-review$/i);
  if (match) {
    await requireWorkspace(database, match[1]);
    const entries = await otherExpenseQueue(database, match[1]);
    return {
      ok: true,
      report: {
        workspace_id: match[1],
        count: entries.length,
        total: entries.reduce((sum, entry) => sum + Number(entry.amount || 0), 0),
        entries,
      },
    };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-review-queue$/i);
  if (match) {
    return { ok: true, queue: await dictionaryReviewQueue(database, match[1], query) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/raw-history$/i);
  if (match) {
    return { ok: true, history: await rawHistory(database, match[1], query) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/raw-history\/convert$/i);
  if (match && method === 'POST') {
    return { ok: true, conversion: await convertRawHistoryBatch(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-training-decisions$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, decision: await decideDictionaryTraining(database, match[1], input, userId) };
    }
    return { ok: true, decisions: await dictionaryTrainingDecisions(database, match[1], query) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/assistant-settings$/i);
  if (match) {
    if (method === 'PATCH') {
    return { ok: true, settings: await updateWorkspaceAssistantSettings(database, match[1], input, userId) };
  }
    await requireWorkspaceFullReader(database, match[1]);
    const settings = await database.collection('v2_workspace_assistant_settings').findOne({ workspace_id: match[1] });
    return { ok: true, settings: workspaceAssistantSettingsRow(settings, match[1]) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-training-internet-reference\/lookups$/i);
  if (match) {
    await requireWorkspaceFullReader(database, match[1]);
    const limit = clampInt(query.limit, 50, 1, 200);
    const lookups = await database.collection('v2_internet_reference_lookups')
      .find({ workspace_id: match[1] })
      .sort({ created_at: -1 })
      .limit(limit)
      .toArray();
    return { ok: true, lookups: lookups.map(dictionaryInternetReferenceLookupRow) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-training-internet-reference$/i);
  if (match && method === 'POST') {
    return { ok: true, reference: await previewDictionaryInternetReference(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/dictionary-training-internet-reference\/lookups\/([a-f0-9-]{36})$/i);
  if (match && method === 'PATCH') {
    return { ok: true, lookup: await updateDictionaryInternetReferenceFeedback(database, match[1], match[2], input, userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/imports\/([a-f0-9-]{36})\/review$/i);
  if (match) {
    return { ok: true, review: await legacyImportReview(database, match[1], match[2], userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/imports\/excel$/i);
  if (match && method === 'POST') {
    return { ok: true, import: await createLegacyExcelImport(database, match[1], input, userId) };
  }

  match = route.match(/^\/api\/workspaces\/([a-f0-9-]{36})\/imports\/([a-f0-9-]{36})\/accept$/i);
  if (match && method === 'POST') {
    return { ok: true, review: await acceptLegacyImport(database, match[1], match[2], input, userId) };
  }

  match = route.match(/^\/api\/entries\/([a-f0-9-]{36})\/attachments$/i);
  if (match) {
    if (method === 'POST') {
      return { ok: true, attachment: await createEntryAttachment(database, match[1], input, userId) };
    }
    const entry = await database.collection('v2_entries').findOne({ id: match[1], archived_at: null });
    if (!entry) {
      const error = new Error('entry_not_found');
      error.status = 404;
      throw error;
    }
    await requireWorkspace(database, String(entry.workspace_id));
    const attachments = await database.collection('v2_attachments')
      .find({ entry_id: match[1] })
      .sort({ created_at: 1, id: 1 })
      .toArray();
    return { ok: true, attachments: attachments.map(attachmentRow) };
  }

  match = route.match(/^\/api\/attachments\/([a-f0-9-]{36})$/i);
  if (match && method === 'DELETE') {
    return { ok: true, attachment: await deleteAttachment(database, match[1], userId) };
  }

  const error = new Error('route_not_found');
  error.status = 404;
  throw error;
}

function requestBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', (chunk) => {
      body += chunk;
      if (body.length > HTTP_JSON_MAX_BYTES) {
        reject(Object.assign(new Error('request_body_too_large'), { status: 413 }));
        req.destroy();
      }
    });
    req.on('end', () => {
      if (body.trim() === '') {
        resolve({});
        return;
      }
      try {
        resolve(JSON.parse(body));
      } catch {
        reject(Object.assign(new Error('invalid_json'), { status: 400 }));
      }
    });
    req.on('error', reject);
  });
}

async function serve(req, res) {
  const url = new URL(req.url, `http://${req.headers.host || `${HOST}:${PORT}`}`);
  try {
    const route = normalizeRoute(url.searchParams.get('route') || url.pathname);
    const query = Object.fromEntries(url.searchParams.entries());
    delete query.route;
    const method = String(req.method || 'GET').toUpperCase();
    const input = method === 'GET' ? {} : await requestBody(req);
    const payload = await handleApi(method, route, query, input);
    json(res, payload);
  } catch (error) {
    json(res, { ok: false, error: error.message || 'atlas_read_error' }, error.status || 500);
  }
}

if (require.main === module) {
  const server = http.createServer(serve);
  server.listen(PORT, HOST, async () => {
    console.log(`FinDesk v2 Atlas read server http://${HOST}:${PORT}/api`);
    try {
      await db();
      console.log(`MongoDB Atlas connected: ${DB_NAME}`);
    } catch (error) {
      console.error(`MongoDB Atlas connection failed: ${error.message}`);
    }
  });
}

module.exports = { handleApi, db, closeDb };
