const http = require('http');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { MongoClient } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const PUBLIC_ROOT = path.join(ROOT, 'public');
const PORT = Number(process.env.FINDESK_PORT || 18889);
const HOST = process.env.FINDESK_HOST || '127.0.0.1';
const MONGO_DB = process.env.FINDESK_MONGO_DB || 'finance_brkovic_ltd';
const MONGO_URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');
const TRASH_RETENTION_DAYS = 60;
const PRICE_REGISTRY_FILE = path.join(ROOT, 'app', 'data', 'yacht_price_sources.json');
const PRICE_REGIONS = ['europe_basic', 'adriatic_balkans', 'mediterranean_west', 'usa_coastal', 'asia_marina', 'caribbean_islands'];
const PRICE_FAMILIES = ['fuel', 'food'];
const PRICE_ITEMS = {
  food: ['Вода питьевая', 'Продукты базовые', 'Кофе, чай, сахар', 'Бытовая химия', 'Полотенца бумажные / салфетки'],
  fuel: ['Дизель'],
};
const PRICE_BASELINES = {
  europe_basic: {
    tax_rate: 0.19,
    markup_rate: 0.18,
    logistics_rate: 0.04,
    duty_free_discount: { food: 0.25, fuel: 0.28 },
    sources: {
      'Вода питьевая': [0.68, 0.71, 0.73, 0.70, 0.72],
      'Продукты базовые': [270, 285, 292, 282, 288],
      'Кофе, чай, сахар': [58, 62, 65, 61, 63],
      'Бытовая химия': [78, 82, 86, 81, 84],
      'Полотенца бумажные / салфетки': [34, 36, 38, 35, 37],
      'Дизель': [1.16, 1.2, 1.23, 1.19, 1.22],
    },
  },
  adriatic_balkans: {
    tax_rate: 0.21,
    markup_rate: 0.15,
    logistics_rate: 0.03,
    duty_free_discount: { food: 0.26, fuel: 0.30 },
    sources: {
      'Вода питьевая': [0.55, 0.58, 0.61, 0.57, 0.60],
      'Продукты базовые': [238, 248, 256, 244, 252],
      'Кофе, чай, сахар': [52, 55, 58, 54, 57],
      'Бытовая химия': [68, 72, 75, 70, 74],
      'Полотенца бумажные / салфетки': [28, 30, 31, 29, 30.5],
      'Дизель': [1.05, 1.09, 1.12, 1.08, 1.10],
    },
  },
  mediterranean_west: {
    tax_rate: 0.20,
    markup_rate: 0.20,
    logistics_rate: 0.05,
    duty_free_discount: { food: 0.25, fuel: 0.27 },
    sources: {
      'Вода питьевая': [0.70, 0.74, 0.77, 0.73, 0.76],
      'Продукты базовые': [275, 290, 302, 286, 296],
      'Кофе, чай, сахар': [60, 65, 68, 63, 66],
      'Бытовая химия': [82, 86, 90, 85, 88],
      'Полотенца бумажные / салфетки': [36, 38, 40, 37, 39],
      'Дизель': [1.18, 1.22, 1.26, 1.21, 1.24],
    },
  },
  usa_coastal: {
    tax_rate: 0.08,
    markup_rate: 0.24,
    logistics_rate: 0.06,
    duty_free_discount: { food: 0.22, fuel: 0.25 },
    sources: {
      'Вода питьевая': [0.82, 0.86, 0.90, 0.84, 0.88],
      'Продукты базовые': [320, 335, 348, 330, 342],
      'Кофе, чай, сахар': [78, 82, 86, 80, 84],
      'Бытовая химия': [95, 100, 106, 98, 103],
      'Полотенца бумажные / салфетки': [46, 49, 52, 48, 51],
      'Дизель': [0.95, 0.98, 1.02, 0.97, 1.00],
    },
  },
  asia_marina: {
    tax_rate: 0.10,
    markup_rate: 0.18,
    logistics_rate: 0.07,
    duty_free_discount: { food: 0.28, fuel: 0.30 },
    sources: {
      'Вода питьевая': [0.48, 0.50, 0.54, 0.51, 0.53],
      'Продукты базовые': [220, 230, 238, 226, 234],
      'Кофе, чай, сахар': [44, 47, 50, 46, 49],
      'Бытовая химия': [62, 66, 70, 65, 68],
      'Полотенца бумажные / салфетки': [26, 28, 30, 27, 29],
      'Дизель': [0.72, 0.75, 0.78, 0.74, 0.77],
    },
  },
  caribbean_islands: {
    tax_rate: 0.15,
    markup_rate: 0.28,
    logistics_rate: 0.12,
    duty_free_discount: { food: 0.25, fuel: 0.30 },
    sources: {
      'Вода питьевая': [0.90, 0.96, 1.02, 0.94, 1.00],
      'Продукты базовые': [350, 370, 390, 365, 382],
      'Кофе, чай, сахар': [88, 94, 100, 92, 98],
      'Бытовая химия': [110, 118, 126, 116, 122],
      'Полотенца бумажные / салфетки': [52, 56, 60, 55, 58],
      'Дизель': [1.02, 1.08, 1.14, 1.06, 1.12],
    },
  },
};

const MIME = {
  '.php': 'text/html; charset=utf-8',
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.webmanifest': 'application/manifest+json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.svg': 'image/svg+xml; charset=utf-8',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
  '.txt': 'text/plain; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
};

let mongoClient;
let mongoDb;

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  if (fs.existsSync(MONGO_URI_FILE)) return fs.readFileSync(MONGO_URI_FILE, 'utf8').trim();
  return '';
}

async function db() {
  if (mongoDb) return mongoDb;
  const uri = readMongoUri();
  if (!uri) throw new Error('missing_mongo_uri');
  mongoClient = new MongoClient(uri, { serverSelectionTimeoutMS: 10000 });
  await mongoClient.connect();
  mongoDb = mongoClient.db(MONGO_DB);
  await ensureIndexes(mongoDb);
  return mongoDb;
}

async function ensureIndexes(database) {
  await Promise.all([
    database.collection('users').createIndex({ id: 1 }, { unique: true }),
    database.collection('workspaces').createIndex({ id: 1 }, { unique: true }),
    database.collection('workspaces').createIndex({ 'members.user_id': 1, status: 1, created_at: -1 }),
    database.collection('yacht_states').createIndex({ workspace_id: 1 }, { unique: true }),
    database.collection('yacht_states').createIndex({ updated_at: -1 }),
    database.collection('yacht_price_snapshots').createIndex({ region: 1, family: 1, created_at: -1 }),
    database.collection('yacht_price_snapshots').createIndex({ active_catalog: 1, region: 1, family: 1 }),
    database.collection('workspace_audit').createIndex({ workspace_id: 1, created_at: -1 }),
    database.collection('workspace_audit').createIndex({ created_at: -1 }),
    database.collection('cash_sessions').createIndex({ id: 1 }, { unique: true }),
    database.collection('cash_sessions').createIndex({ owner_user_id: 1, workspace_id: 1, status: 1, updated_at: -1 }),
    database.collection('cash_sessions').createIndex({ workspace_id: 1, status: 1, updated_at: -1 }),
  ]);
}

async function nextId(database, name) {
  const counter = await database.collection('counters').findOneAndUpdate(
    { _id: name },
    { $inc: { seq: 1 } },
    { upsert: true, returnDocument: 'after' }
  );
  return Number(counter && counter.seq ? counter.seq : Date.now());
}

function now() {
  return new Date();
}

function normalizeWorkspaceType(value, name = '') {
  const raw = String(value || '').trim().toLowerCase();
  if (['team', 'yacht', 'home'].includes(raw)) return raw;
  const lowerName = String(name || '').trim().toLowerCase();
  if (lowerName.startsWith('yacht:') || lowerName.includes('yacht')) return 'yacht';
  if (lowerName === 'dom' || lowerName === 'home' || lowerName.startsWith('home:') || lowerName.includes('house')) return 'home';
  return 'team';
}

function defaultPermissions(accessLevel = 'base') {
  if (accessLevel === 'advanced') {
    return {
      mode: 'advanced',
      can_use_on_the_go: true,
      can_use_captain_fin: true,
      can_moderate: true,
      can_view_group_reports: true,
      can_write_group_ledger: true,
      can_manage_money: true,
      can_manage_members: true,
    };
  }
  if (accessLevel === 'manager') {
    return {
      mode: 'manager',
      can_use_on_the_go: true,
      can_use_captain_fin: true,
      can_moderate: true,
      can_view_group_reports: true,
      can_write_group_ledger: true,
      can_manage_money: false,
      can_manage_members: false,
    };
  }
  return {
    mode: 'base',
    can_use_on_the_go: true,
    can_use_captain_fin: false,
    can_moderate: false,
    can_view_group_reports: false,
    can_write_group_ledger: false,
    can_manage_money: false,
    can_manage_members: false,
  };
}

function publicUser(user) {
  return {
    id: user.id,
    email: user.email,
    name: user.name || user.display_name || user.email,
    display_name: user.display_name || user.name || user.email,
  };
}

async function currentUser(database) {
  const users = database.collection('users');
  const existing = await users.findOne({ id: 1 });
  if (existing) return existing;
  const user = {
    id: 1,
    email: 'local@findesk.test',
    name: 'Local QA',
    display_name: 'Local QA',
    status: 'active',
    created_at: now(),
    updated_at: now(),
  };
  await users.insertOne(user);
  return user;
}

function trashDaysLeft(workspace) {
  const archivedAt = workspace.archived_at || workspace.updated_at || workspace.created_at || now();
  const archivedMs = new Date(archivedAt).getTime();
  const elapsed = Number.isFinite(archivedMs) ? Math.floor((Date.now() - archivedMs) / 86400000) : 0;
  return Math.max(0, TRASH_RETENTION_DAYS - elapsed);
}

function publicWorkspace(workspace, userId = 1) {
  const member = Array.isArray(workspace.members)
    ? workspace.members.find((item) => Number(item.user_id) === Number(userId) && item.status !== 'left')
    : null;
  const accessLevel = member && member.access_level ? member.access_level : 'advanced';
  return {
    id: workspace.id,
    name: workspace.name,
    description: workspace.description || null,
    workspace_type: normalizeWorkspaceType(workspace.workspace_type, workspace.name),
    created_by: workspace.created_by || userId,
    status: workspace.status || 'active',
    created_at: workspace.created_at,
    updated_at: workspace.updated_at,
    archived_at: workspace.archived_at || null,
    role: member && member.role ? member.role : 'admin',
    access_level: accessLevel,
    permissions: member && member.permissions ? member.permissions : defaultPermissions(accessLevel),
    member_display_name: member && member.display_name ? member.display_name : 'Local QA',
    member_count: Array.isArray(workspace.members) ? workspace.members.filter((item) => item.status !== 'left').length : 1,
    trash_days_left: workspace.status === 'archived' ? trashDaysLeft(workspace) : undefined,
  };
}

async function audit(database, action, workspace, payload = {}) {
  await database.collection('workspace_audit').insertOne({
    action,
    workspace_id: workspace.id,
    workspace_name: workspace.name,
    payload,
    created_at: now(),
  });
}

async function workspaceForUser(database, workspaceId, userId, options = {}) {
  const id = Number(workspaceId || 0);
  if (!id) return null;
  const status = options.includeArchived ? { $in: ['active', 'archived'] } : 'active';
  return database.collection('workspaces').findOne({
    id,
    status,
    members: { $elemMatch: { user_id: userId, status: 'active' } },
  });
}

async function purgeExpiredTrash(database) {
  const threshold = new Date(Date.now() - TRASH_RETENTION_DAYS * 86400000);
  const result = await database.collection('workspaces').updateMany(
    { status: 'archived', archived_at: { $lt: threshold } },
    { $set: { status: 'purged', purged_at: now(), updated_at: now() } }
  );
  return result.modifiedCount || 0;
}

async function groupList(database) {
  const user = await currentUser(database);
  await purgeExpiredTrash(database);
  const groups = await database.collection('workspaces')
    .find({ status: 'active', members: { $elemMatch: { user_id: user.id, status: 'active' } } })
    .sort({ created_at: -1, id: -1 })
    .toArray();
  return { ok: true, groups: groups.map((group) => publicWorkspace(group, user.id)) };
}

async function groupTrashList(database) {
  const user = await currentUser(database);
  await purgeExpiredTrash(database);
  const groups = await database.collection('workspaces')
    .find({ status: 'archived', members: { $elemMatch: { user_id: user.id, status: 'active' } } })
    .sort({ archived_at: -1, id: -1 })
    .toArray();
  return { ok: true, groups: groups.map((group) => publicWorkspace(group, user.id)), retention_days: TRASH_RETENTION_DAYS };
}

async function groupCreate(database, input) {
  const user = await currentUser(database);
  const name = String(input.name || '').trim();
  if (!name) return { ok: false, error: 'empty_group_name' };
  if (name.length > 190) return { ok: false, error: 'group_name_too_long' };

  const id = await nextId(database, 'workspaces');
  const workspace = {
    id,
    name,
    description: String(input.description || '').trim() || null,
    workspace_type: normalizeWorkspaceType(input.workspace_type || '', name),
    created_by: user.id,
    status: 'active',
    created_at: now(),
    updated_at: now(),
    members: [{
      user_id: user.id,
      display_name: user.display_name || user.email,
      role: 'admin',
      access_level: 'advanced',
      permissions: defaultPermissions('advanced'),
      status: 'active',
      joined_at: now(),
    }],
  };
  await database.collection('workspaces').insertOne(workspace);
  await audit(database, 'workspace_created', workspace, { workspace_type: workspace.workspace_type });
  return { ok: true, group: publicWorkspace(workspace, user.id) };
}

async function groupTrash(database, input) {
  const user = await currentUser(database);
  const groupId = Number(input.group_id || input.id || 0);
  if (!groupId) return { ok: false, error: 'invalid_group_id' };
  const workspace = await database.collection('workspaces').findOne({ id: groupId, status: 'active', members: { $elemMatch: { user_id: user.id, role: 'admin', status: 'active' } } });
  if (!workspace) return { ok: false, error: 'group_not_found_or_admin_required' };
  await database.collection('workspaces').updateOne(
    { id: groupId },
    { $set: { status: 'archived', archived_at: now(), updated_at: now() } }
  );
  await audit(database, 'workspace_moved_to_trash', workspace, { retention_days: TRASH_RETENTION_DAYS });
  return { ok: true, group_id: groupId, status: 'archived', retention_days: TRASH_RETENTION_DAYS };
}

async function groupRestore(database, input) {
  const user = await currentUser(database);
  const groupId = Number(input.group_id || input.id || 0);
  if (!groupId) return { ok: false, error: 'invalid_group_id' };
  const workspace = await database.collection('workspaces').findOne({ id: groupId, status: 'archived', members: { $elemMatch: { user_id: user.id, role: 'admin', status: 'active' } } });
  if (!workspace) return { ok: false, error: 'group_not_found_or_admin_required' };
  if (trashDaysLeft(workspace) < 1) return { ok: false, error: 'restore_window_expired' };
  await database.collection('workspaces').updateOne(
    { id: groupId },
    { $set: { status: 'active', updated_at: now() }, $unset: { archived_at: '' } }
  );
  const restored = await database.collection('workspaces').findOne({ id: groupId });
  await audit(database, 'workspace_restored_from_trash', restored || workspace);
  return { ok: true, group: publicWorkspace(restored || workspace, user.id) };
}

function yachtDefaultState(workspace = null) {
  const yachtName = workspace && workspace.name ? String(workspace.name).replace(/^Yacht:\s*/i, '') : '';
  return {
    profile: {
      name: yachtName,
      marina: '',
      berth: '',
      customer: '',
      reg_number: '',
      model: '',
      hull_number: '',
      length: '',
      beam: '',
      year: '',
      logo: '',
      engines: '',
      generators: '',
      watermaker: '',
      windlass: '',
      passerelle: '',
      custom_fields: '',
    },
    crew_roles: {},
    order: {},
  };
}

function sanitizeYachtState(input, workspace = null) {
  const state = input && typeof input === 'object' ? input : {};
  const defaults = yachtDefaultState(workspace);
  const profile = Object.assign({}, defaults.profile, state.profile && typeof state.profile === 'object' ? state.profile : {});
  const crewRoles = state.crew_roles && typeof state.crew_roles === 'object' ? state.crew_roles : {};
  const order = state.order && typeof state.order === 'object' ? state.order : {};
  return {
    profile,
    crew_roles: crewRoles,
    order,
  };
}

async function yachtStateGet(database, input) {
  const user = await currentUser(database);
  const workspaceId = Number(input.workspace_id || input.group_id || 0);
  if (!workspaceId) return { ok: false, error: 'invalid_workspace_id' };
  const workspace = await workspaceForUser(database, workspaceId, user.id);
  if (!workspace) return { ok: false, error: 'workspace_not_found' };
  if (normalizeWorkspaceType(workspace.workspace_type, workspace.name) !== 'yacht') {
    return { ok: false, error: 'not_yacht_workspace' };
  }
  const stored = await database.collection('yacht_states').findOne({ workspace_id: workspaceId });
  const state = stored && stored.state ? sanitizeYachtState(stored.state, workspace) : yachtDefaultState(workspace);
  return {
    ok: true,
    workspace_id: workspaceId,
    state,
    updated_at: stored && stored.updated_at ? stored.updated_at : null,
  };
}

async function yachtStateSave(database, input) {
  const user = await currentUser(database);
  const workspaceId = Number(input.workspace_id || input.group_id || 0);
  if (!workspaceId) return { ok: false, error: 'invalid_workspace_id' };
  const workspace = await workspaceForUser(database, workspaceId, user.id);
  if (!workspace) return { ok: false, error: 'workspace_not_found' };
  if (normalizeWorkspaceType(workspace.workspace_type, workspace.name) !== 'yacht') {
    return { ok: false, error: 'not_yacht_workspace' };
  }
  const state = sanitizeYachtState(input.state, workspace);
  const updatedAt = now();
  await database.collection('yacht_states').updateOne(
    { workspace_id: workspaceId },
    {
      $set: {
        workspace_id: workspaceId,
        state,
        updated_by: user.id,
        updated_at: updatedAt,
      },
      $setOnInsert: {
        created_at: updatedAt,
      },
    },
    { upsert: true }
  );
  await audit(database, 'yacht_state_saved', workspace, {
    has_profile_name: !!String(state.profile && state.profile.name || '').trim(),
    fuel_rows: Array.isArray(state.order && state.order.rows) ? state.order.rows.length : 0,
    product_rows: Array.isArray(state.order && state.order.product_rows) ? state.order.product_rows.length : 0,
  });
  return { ok: true, workspace_id: workspaceId, updated_at: updatedAt, state };
}

function provisionJson(name) {
  const file = path.join(ROOT, 'app', 'data', 'yacht_provisioning', name);
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (error) {
    return Array.isArray(name) ? [] : {};
  }
}

function boolValue(value, fallback) {
  if (value === undefined || value === null) return fallback;
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  const text = String(value).trim().toLowerCase();
  if (['1', 'true', 'yes', 'on'].includes(text)) return true;
  if (['0', 'false', 'no', 'off'].includes(text)) return false;
  return fallback;
}

function listValue(value) {
  return Array.isArray(value)
    ? Array.from(new Set(value.map((item) => String(item || '').trim()).filter(Boolean)))
    : [];
}

function provisionFilters(input) {
  const filters = input && typeof input.filters === 'object' ? input.filters : {};
  const storage = ['small_fridge', 'normal_yacht', 'large_yacht'].includes(String(filters.storage || ''))
    ? String(filters.storage)
    : 'normal_yacht';
  return {
    include_categories: listValue(filters.include_categories),
    exclude_categories: listValue(filters.exclude_categories),
    include_household: boolValue(filters.include_household, true),
    include_hygiene: boolValue(filters.include_hygiene, true),
    include_alcohol: boolValue(filters.include_alcohol, false),
    include_bbq: boolValue(filters.include_bbq, true),
    include_children: boolValue(filters.include_children, false),
    dietary: listValue(filters.dietary),
    storage,
    route_restock_possible: boolValue(filters.route_restock_possible, true),
    perishable_only: boolValue(filters.perishable_only, false),
    long_storage_only: boolValue(filters.long_storage_only, false),
  };
}

function provisionMealMultiplier(mealPlan, categoryKey) {
  const plans = {
    breakfast_only: { breakfast: 1.0, water_drinks: 1.0, dairy: 0.8, fruit: 0.7, snacks_antipasti: 0.6, sweets: 0.6, household: 0.8, hygiene_first_aid: 1.0 },
    breakfast_lunch: { breakfast: 1.0, water_drinks: 1.0, dairy: 0.9, fruit: 0.9, vegetables_herbs: 0.75, dry_goods_sides: 0.75, meat_fish_protein: 0.65, snacks_antipasti: 0.8, household: 0.9, hygiene_first_aid: 1.0 },
    full_onboard: { water_drinks: 1.1, breakfast: 1.15, meat_fish_protein: 1.25, vegetables_herbs: 1.25, fruit: 1.15, dry_goods_sides: 1.25, dairy: 1.15, snacks_antipasti: 1.15, canned_emergency: 1.1, oils_sauces_spices: 1.15, sweets: 1.1, household: 1.15, hygiene_first_aid: 1.0 },
  };
  if (mealPlan === 'breakfast_onboard_lunch_light_dinner_mixed') return 1.0;
  return Number(plans[mealPlan] && plans[mealPlan][categoryKey] || 0.5);
}

function provisionRound(quantity, rounding) {
  if (rounding === 'manual') return null;
  if (rounding === 'ceil_kg_0_5') return Math.ceil(quantity * 2) / 2;
  const packMatch = String(rounding || '').match(/^ceil_pack_(\d+)$/);
  if (packMatch) {
    const pack = Math.max(1, Number(packMatch[1] || 1));
    return Math.ceil(quantity / pack) * pack;
  }
  if (rounding === 'ceil_integer') return Math.ceil(quantity);
  if (rounding === 'round_1_decimal') return Math.round(quantity * 10) / 10;
  return Math.ceil(quantity);
}

function provisionUnitLabel(unit, language) {
  const labels = {
    bottle_1_5l: { ru: 'бут. 1.5 л', en: '1.5 L bottles' },
    bottle_0_5l: { ru: 'бут. 0.5 л', en: '0.5 L bottles' },
    bottle_5l: { ru: 'бут. 5 л', en: '5 L bottles' },
    kg: { ru: 'кг', en: 'kg' },
    g: { ru: 'г', en: 'g' },
    piece: { ru: 'шт.', en: 'pcs' },
    pack: { ru: 'уп.', en: 'packs' },
    jar: { ru: 'бан.', en: 'jars' },
    bottle: { ru: 'бут.', en: 'bottles' },
    liter: { ru: 'л', en: 'L' },
    roll: { ru: 'рул.', en: 'rolls' },
    box: { ru: 'кор.', en: 'boxes' },
  };
  return labels[unit] && labels[unit][language] ? labels[unit][language] : unit;
}

function provisionDisplayQuantity(quantity, unit, rounding, language) {
  if (quantity === null || quantity === undefined) {
    return language === 'ru' ? 'по ситуации' : 'as needed';
  }
  const value = Number(quantity);
  const text = Number.isInteger(value) ? String(value) : String(value.toFixed(1)).replace(/\.0$/, '');
  return `${text} ${provisionUnitLabel(unit, language)}`;
}

function provisionItemAllowed(item, filters) {
  const category = String(item.category_key || '');
  if (!category) return false;
  if (filters.include_categories.length && !filters.include_categories.includes(category)) return false;
  if (filters.exclude_categories.includes(category)) return false;
  if (category === 'household' && !filters.include_household) return false;
  if (category === 'hygiene_first_aid' && !filters.include_hygiene) return false;
  if (category === 'alcohol' && !filters.include_alcohol) return false;
  if (category === 'children' && !filters.include_children) return false;
  const itemFilters = Array.isArray(item.filters) ? item.filters : [];
  if (itemFilters.includes('bbq') && !filters.include_bbq) return false;
  if (itemFilters.includes('children') && !filters.include_children) return false;
  if (itemFilters.includes('alcohol') && !filters.include_alcohol) return false;
  if (filters.dietary.includes('no_pork') && itemFilters.includes('exclude_if_no_pork')) return false;
  if (filters.dietary.includes('no_seafood') && itemFilters.includes('exclude_if_no_seafood')) return false;
  const perishable = !!item.perishable;
  if (filters.perishable_only && !perishable) return false;
  if (filters.long_storage_only && perishable) return false;
  return true;
}

function provisionWaterLiters(peopleCount, days, profile) {
  const multiplier = profile === 'light' ? 2.5 : (profile === 'onboard_full' ? 3.5 : 3.0);
  return Math.ceil(peopleCount * days * multiplier);
}

function yachtProvisionCalculate(input) {
  const peopleCount = Number(input.people_count || 0);
  if (peopleCount < 1) return { ok: false, error: { code: 'INVALID_PEOPLE_COUNT', message: 'people_count must be greater than 0' } };
  const days = Number(input.days || 0);
  if (days < 1) return { ok: false, error: { code: 'INVALID_DAYS', message: 'days must be greater than 0' } };
  const filtersData = provisionJson('filters.json');
  const profileKeys = Object.keys(filtersData.profiles || {});
  let profile = String(input.profile || 'balanced');
  if (!profileKeys.includes(profile)) profile = 'balanced';
  const mealPlanKeys = Object.keys(filtersData.meal_plans || {});
  let mealPlan = String(input.meal_plan || 'breakfast_onboard_lunch_light_dinner_mixed');
  if (mealPlanKeys.length && !mealPlanKeys.includes(mealPlan)) mealPlan = 'breakfast_onboard_lunch_light_dinner_mixed';
  const language = ['ru', 'en'].includes(String(input.language || 'ru')) ? String(input.language || 'ru') : 'ru';
  const filters = provisionFilters(input);
  const categoriesData = provisionJson('categories.json');
  const catalog = provisionJson('provision_catalog.json');
  const items = Array.isArray(catalog.items) ? catalog.items : [];
  if (!Array.isArray(categoriesData) || !categoriesData.length || !items.length) {
    return { ok: false, error: { code: 'CATALOG_UNAVAILABLE', message: 'Yacht provision catalog is unavailable' } };
  }

  const categories = {};
  for (const category of categoriesData) {
    const key = String(category.key || '');
    if (!key) continue;
    categories[key] = {
      category_key: key,
      title: String(category[language === 'ru' ? 'title_ru' : 'title_en'] || key),
      priority: Number(category.priority || 999),
      items: [],
    };
  }

  const profileMultiplier = Number(filtersData.profiles && filtersData.profiles[profile] && filtersData.profiles[profile].multiplier || 1);
  const warnings = [];
  let hasRestockPerishables = false;
  for (const item of items) {
    if (!item || typeof item !== 'object' || !provisionItemAllowed(item, filters)) continue;
    const categoryKey = String(item.category_key || '');
    if (!categories[categoryKey]) continue;
    const raw = Number(item.base_quantity || 0) + Number(item.per_person_per_day || 0) * peopleCount * days;
    const quantity = provisionRound(Math.max(0, raw * profileMultiplier * provisionMealMultiplier(mealPlan, categoryKey)), String(item.rounding || 'ceil_integer'));
    const unit = String(item.unit || 'piece');
    const perishable = !!item.perishable;
    const routeRestock = !!item.route_restock_recommended;
    if (perishable && routeRestock && filters.route_restock_possible) hasRestockPerishables = true;
    categories[categoryKey].items.push({
      item_key: String(item.item_key || ''),
      title: String(item[language === 'ru' ? 'title_ru' : 'title_en'] || item.item_key || ''),
      title_en: String(item.title_en || ''),
      title_ru: String(item.title_ru || ''),
      quantity,
      unit,
      unit_label: provisionUnitLabel(unit, language),
      display_quantity: provisionDisplayQuantity(quantity, unit, String(item.rounding || ''), language),
      note: String(item[language === 'ru' ? 'note_ru' : 'note_en'] || ''),
      priority: String(item.priority || 'standard'),
      perishable,
      optional: !!item.optional,
      route_restock_recommended: routeRestock,
      filters: Array.isArray(item.filters) ? item.filters : [],
    });
  }

  const priorityOrder = { essential: 1, important: 2, standard: 3, optional: 4 };
  const outCategories = Object.values(categories)
    .filter((category) => category.items.length)
    .sort((a, b) => a.priority - b.priority)
    .map((category) => {
      category.items.sort((a, b) => (priorityOrder[a.priority] || 99) - (priorityOrder[b.priority] || 99));
      delete category.priority;
      return category;
    });
  const totalItems = outCategories.reduce((sum, category) => sum + category.items.length, 0);
  if (hasRestockPerishables) {
    warnings.push(language === 'ru'
      ? 'Свежие фрукты, овощи, хлеб и рыбу лучше дозакупать по маршруту, если это возможно.'
      : 'Fresh fruit, vegetables, bread and fish are better restocked during the route if possible.');
  }
  if (peopleCount >= 20) {
    warnings.push(language === 'ru'
      ? 'Для большой группы проверьте холодильники и разделите скоропортящиеся продукты на первую загрузку и дозакупку.'
      : 'For a large crew, check fridge capacity and split perishables into first load and route restock.');
  }

  return {
    ok: true,
    meta: {
      people_count: peopleCount,
      days,
      currency: 'EUR',
      profile,
      meal_plan: mealPlan,
      language,
      catalog_version: String(catalog.version || ''),
      filters,
    },
    warnings: Array.from(new Set(warnings)),
    summary: {
      total_categories: outCategories.length,
      total_items: totalItems,
      water_liters_estimated: provisionWaterLiters(peopleCount, days, profile),
    },
    categories: outCategories,
  };
}

function readPriceRegistry() {
  try {
    const parsed = JSON.parse(fs.readFileSync(PRICE_REGISTRY_FILE, 'utf8'));
    return parsed && typeof parsed === 'object' ? parsed : { regions: {}, policy: {} };
  } catch (error) {
    return { regions: {}, policy: {} };
  }
}

function normalizePriceRegion(value) {
  const region = String(value || '').trim().toLowerCase().replace(/[^a-z0-9_]/g, '');
  return PRICE_REGIONS.includes(region) ? region : 'adriatic_balkans';
}

function normalizePriceFamily(value) {
  const family = String(value || '').trim().toLowerCase().replace(/[^a-z0-9_]/g, '');
  return PRICE_FAMILIES.includes(family) ? family : 'fuel';
}

function average(values) {
  const numeric = (Array.isArray(values) ? values : []).map(Number).filter((value) => Number.isFinite(value) && value > 0);
  if (!numeric.length) return null;
  return numeric.reduce((sum, value) => sum + value, 0) / numeric.length;
}

function roundedMoney(value) {
  return Math.max(0, Math.round(Number(value || 0) * 100) / 100);
}

function signedMoney(value) {
  const number = Number(value || 0);
  return Math.round(number * 100) / 100;
}

function cashPreset(value, workspace = null) {
  const raw = String(value || '').trim().toLowerCase();
  if (['base', 'yacht', 'home', 'family', 'road', 'personal', 'team'].includes(raw)) return raw;
  if (workspace) {
    const kind = normalizeWorkspaceType(workspace.workspace_type, workspace.name);
    if (['yacht', 'home'].includes(kind)) return kind;
  }
  return 'base';
}

function cashMode(value, preset) {
  const raw = String(value || '').trim().toLowerCase();
  if (['personal', 'group'].includes(raw)) return raw;
  return preset === 'personal' ? 'personal' : 'group';
}

function cashEntryKind(value) {
  return ['contribution', 'expense', 'note', 'adjustment'].includes(String(value || '')) ? String(value) : 'note';
}

function parseCashNotebook(text) {
  const lines = String(text || '').replace(/\r/g, '').split('\n');
  const entries = [];
  lines.forEach((line, index) => {
    const raw = String(line || '').trim();
    if (!raw) return;
    const clean = raw.replace(/^\s*[✓✔]\s*/u, '').trim();
    const contributionMatch = clean.match(/^\+\s*(?:€|eur\s*)?(\d+(?:[.,]\d+)?)(?:\s+(.+))?$/i);
    const expenseMatch = clean.match(/^-\s*(?:€|eur\s*)?(\d+(?:[.,]\d+)?)(?:\s+(.+))?$/i);
    const match = contributionMatch || expenseMatch;
    if (!match) {
      entries.push({
        id: `entry_${Date.now()}_${index}`,
        line_index: index,
        raw_text: raw,
        note: raw,
        amount: 0,
        entry_kind: 'note',
        created_at: now(),
      });
      return;
    }
    const amount = Number(String(match[1] || '0').replace(',', '.'));
    const kind = contributionMatch ? 'contribution' : 'expense';
    entries.push({
      id: `entry_${Date.now()}_${index}`,
      line_index: index,
      raw_text: clean,
      note: String(match[2] || '').trim() || clean,
      amount: signedMoney(kind === 'contribution' ? Math.abs(amount) : -Math.abs(amount)),
      entry_kind: kind,
      created_at: now(),
    });
  });
  return entries;
}

function cashReportId(value = '') {
  const raw = String(value || '').trim();
  if (!raw || raw === 'null' || raw === 'undefined') return null;
  return raw.replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 80) || null;
}

function cashRecordCards(session) {
  return Array.isArray(session && session.record_cards) ? session.record_cards : [];
}

function cashReports(session) {
  return Array.isArray(session && session.cash_reports) ? session.cash_reports : [];
}

function cashReportStatus(value, fallback = 'active') {
  const raw = String(value || '').trim().toLowerCase();
  if (['active', 'fixed', 'archived'].includes(raw)) return raw;
  return fallback;
}

function publicCashReport(report) {
  if (!report || typeof report !== 'object') return null;
  return {
    id: cashReportId(report.id),
    title: String(report.title || 'Отчет').trim() || 'Отчет',
    opening_amount: signedMoney(report.opening_amount || 0),
    status: String(report.status || 'active'),
    created_at: report.created_at || null,
    started_at: report.started_at || report.created_at || null,
    fixed_at: report.fixed_at || null,
    archived_at: report.archived_at || null,
  };
}

function publicCashRecordCard(card) {
  if (!card || typeof card !== 'object') return null;
  const entries = Array.isArray(card.entries) ? card.entries : parseCashNotebook(card.raw_text || card.draft_text || '');
  const contribution = entries.reduce((sum, entry) => (
    cashEntryKind(entry.entry_kind) === 'contribution' ? sum + Math.abs(Number(entry.amount || 0)) : sum
  ), 0);
  const expense = entries.reduce((sum, entry) => (
    cashEntryKind(entry.entry_kind) === 'expense' ? sum + Math.abs(Number(entry.amount || 0)) : sum
  ), 0);
  return {
    id: String(card.id || '').trim(),
    report_id: cashReportId(card.report_id),
    participant_id: cashParticipantId(card.participant_id || 'owner') || 'owner',
    participant_display_name: String(card.participant_display_name || 'Участник'),
    title: String(card.title || 'Запись').trim() || 'Запись',
    status: String(card.status || 'draft'),
    raw_text: String(card.raw_text || card.draft_text || '').replace(/\r/g, ''),
    entries,
    totals: {
      contributions: signedMoney(contribution),
      expenses: signedMoney(expense),
      balance: signedMoney(contribution - expense),
      lines: entries.length,
    },
    source_batch_id: card.source_batch_id || null,
    created_at: card.created_at || null,
    updated_at: card.updated_at || null,
    fixed_at: card.fixed_at || null,
  };
}

function cashNotebookDraftRecordId(session, participantId) {
  const notebooks = session && session.notebooks && typeof session.notebooks === 'object' ? session.notebooks : {};
  const notebook = notebooks[participantId] || {};
  return String(notebook.active_record_id || '').trim();
}

function cashDraftRecordFromText(session, participant, input, updatedAt) {
  const participantId = String(participant.id || 'owner');
  const text = String(input.draft_text || input.notebook_text || '').replace(/\r/g, '');
  const reportId = cashReportId(input.report_id);
  const recordId = cashNotebookDraftRecordId(session, participantId)
    || `record_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`;
  const existing = cashRecordCards(session).find((card) => String(card.id || '') === recordId) || {};
  const titleSource = String(input.record_title || existing.title || '').trim();
  return {
    id: recordId,
    report_id: reportId,
    participant_id: participantId,
    participant_display_name: participant.display_name,
    title: titleSource || `Активная запись ${updatedAt.toLocaleString('ru-RU')}`,
    status: 'draft',
    raw_text: text,
    entries: parseCashNotebook(text),
    source: String(input.source || existing.source || 'manual'),
    created_at: existing.created_at || updatedAt,
    updated_at: updatedAt,
  };
}

function cashUpsertRecordCard(cards, nextCard) {
  const list = Array.isArray(cards) ? cards.slice() : [];
  const index = list.findIndex((card) => String(card.id || '') === String(nextCard.id || ''));
  if (index >= 0) list[index] = Object.assign({}, list[index], nextCard);
  else list.push(nextCard);
  return list;
}

function cashReportById(session, reportId) {
  const id = cashReportId(reportId);
  if (!id) return null;
  return cashReports(session).find((report) => cashReportId(report.id) === id) || null;
}

function cashRecordCardFromBatch(batch, reportId = null, updatedAt = now()) {
  return {
    id: String(batch && batch.id || `legacy_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`),
    report_id: cashReportId(reportId),
    participant_id: cashParticipantId(batch && batch.participant_id || 'owner') || 'owner',
    participant_display_name: String(batch && batch.participant_display_name || 'Участник'),
    title: 'Зафиксированная запись',
    status: 'fixed',
    raw_text: String(batch && batch.raw_text || ''),
    entries: Array.isArray(batch && batch.entries) ? batch.entries : parseCashNotebook(batch && batch.raw_text || ''),
    source: String(batch && batch.source || 'legacy_batch'),
    source_batch_id: batch && batch.id || null,
    created_at: batch && batch.created_at || updatedAt,
    updated_at: updatedAt,
    fixed_at: batch && batch.created_at || updatedAt,
  };
}

function defaultCashParticipant(user, mode = 'group') {
  return {
    id: 'owner',
    user_id: user.id,
    display_name: user.display_name || user.name || user.email || 'Owner',
    role: mode === 'personal' ? 'owner' : 'treasurer',
    invite_token: cashInviteToken(),
    included_in_split: true,
    active: true,
    created_at: now(),
  };
}

function cashRole(value, fallback = 'participant') {
  const raw = String(value || '').trim().toLowerCase();
  if (['owner', 'treasurer', 'manager', 'participant', 'viewer'].includes(raw)) return raw;
  return fallback;
}

function cashParticipantId(value = '') {
  return String(value || '').trim().replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 64);
}

function cashParticipantName(value, fallback = 'Participant') {
  const name = String(value || '').trim().replace(/\s+/g, ' ');
  return name ? name.slice(0, 120) : fallback;
}

function cashInviteToken(value = '') {
  const raw = String(value || '').trim();
  if (/^cpt_[a-f0-9]{24,80}$/i.test(raw)) return raw;
  return `cpt_${crypto.randomBytes(18).toString('hex')}`;
}

function normalizeCashParticipant(input, fallback = {}) {
  const base = fallback && typeof fallback === 'object' ? fallback : {};
  const id = cashParticipantId(input.id || base.id || `part_${Date.now()}`);
  return {
    id,
    user_id: input.user_id || base.user_id || null,
    display_name: cashParticipantName(input.display_name || input.name || base.display_name, 'Participant'),
    role: cashRole(input.role || base.role, id === 'owner' ? 'treasurer' : 'participant'),
    email: String(input.email || base.email || '').trim().slice(0, 190),
    invite_token: cashInviteToken(input.invite_token || base.invite_token || ''),
    included_in_split: boolValue(input.included_in_split, base.included_in_split !== false),
    active: boolValue(input.active, base.active !== false),
    created_at: base.created_at || now(),
    updated_at: now(),
  };
}

function activeCashParticipants(session) {
  return (Array.isArray(session.participants) ? session.participants : []).filter((item) => item && item.active !== false);
}

function cashParticipantById(session, participantId) {
  const id = cashParticipantId(participantId || 'owner') || 'owner';
  return activeCashParticipants(session).find((item) => item.id === id) || null;
}

function cashParticipantByToken(session, token) {
  const inviteToken = String(token || '').trim();
  if (!inviteToken) return null;
  return activeCashParticipants(session).find((item) => String(item.invite_token || '') === inviteToken) || null;
}

function cashParticipantTotals(session) {
  const participants = activeCashParticipants(session);
  const totals = {};
  participants.forEach((participant) => {
    totals[participant.id] = {
      participant_id: participant.id,
      display_name: participant.display_name || 'Participant',
      role: participant.role || 'participant',
      included_in_split: participant.included_in_split !== false,
      contributions: 0,
      expenses: 0,
      notes: 0,
      balance: 0,
    };
  });
  const batches = Array.isArray(session.batches) ? session.batches : [];
  batches.forEach((batch) => {
    const participantId = batch.participant_id || 'owner';
    if (!totals[participantId]) return;
    (Array.isArray(batch.entries) ? batch.entries : []).forEach((entry) => {
      const kind = cashEntryKind(entry.entry_kind);
      if (kind === 'contribution') totals[participantId].contributions += Math.abs(Number(entry.amount || 0));
      else if (kind === 'expense') totals[participantId].expenses += Math.abs(Number(entry.amount || 0));
      else totals[participantId].notes += 1;
    });
  });
  const included = Object.values(totals).filter((item) => item.included_in_split);
  const totalExpenses = Object.values(totals).reduce((sum, item) => sum + Number(item.expenses || 0), 0);
  const totalContributions = Object.values(totals).reduce((sum, item) => sum + Number(item.contributions || 0), 0);
  const share = included.length ? signedMoney(totalExpenses / included.length) : 0;
  Object.keys(totals).forEach((id) => {
    const item = totals[id];
    const ownShare = item.included_in_split ? share : 0;
    item.contributions = signedMoney(item.contributions);
    item.expenses = signedMoney(item.expenses);
    item.balance = signedMoney(item.contributions + item.expenses - ownShare);
  });
  return {
    participant_count: included.length,
    total_contributions: signedMoney(totalContributions),
    total_expenses: signedMoney(totalExpenses),
    share,
    participants: totals,
  };
}

function cashSettlementLines(totals) {
  const creditors = [];
  const debtors = [];
  Object.values(totals.participants || {}).forEach((item) => {
    const balance = signedMoney(item.balance);
    if (balance > 0.009) creditors.push({ id: item.participant_id, name: item.display_name, amount: balance });
    if (balance < -0.009) debtors.push({ id: item.participant_id, name: item.display_name, amount: Math.abs(balance) });
  });
  const lines = [];
  let creditorIndex = 0;
  let debtorIndex = 0;
  while (creditorIndex < creditors.length && debtorIndex < debtors.length) {
    const creditor = creditors[creditorIndex];
    const debtor = debtors[debtorIndex];
    const amount = signedMoney(Math.min(creditor.amount, debtor.amount));
    if (amount > 0) {
      lines.push({
        kind: 'preview_transfer',
        from_participant_id: debtor.id,
        from_display_name: debtor.name,
        to_participant_id: creditor.id,
        to_display_name: creditor.name,
        amount,
      });
    }
    creditor.amount = signedMoney(creditor.amount - amount);
    debtor.amount = signedMoney(debtor.amount - amount);
    if (creditor.amount <= 0.009) creditorIndex += 1;
    if (debtor.amount <= 0.009) debtorIndex += 1;
  }
  return lines;
}

function publicCashSession(session) {
  if (!session) return null;
  const totals = cashParticipantTotals(session);
  const notebooks = session.notebooks && typeof session.notebooks === 'object' ? session.notebooks : {};
  const recordCards = cashRecordCards(session).map(publicCashRecordCard).filter(Boolean);
  const reports = cashReports(session).map(publicCashReport).filter(Boolean);
  return {
    id: session.id,
    workspace_id: session.workspace_id,
    title: session.title,
    preset: session.preset,
    mode: session.mode,
    status: session.status,
    currency: session.currency || 'EUR',
    participants: activeCashParticipants(session),
    notebooks,
    draft_text: session.notebooks && session.notebooks.owner ? String(session.notebooks.owner.draft_text || '') : '',
    batches: Array.isArray(session.batches) ? session.batches : [],
    record_cards: recordCards,
    cash_reports: reports,
    totals,
    settlement_preview: {
      audit_status: 'preview_not_final',
      lines: cashSettlementLines(totals),
    },
    created_at: session.created_at,
    updated_at: session.updated_at,
  };
}

function cashArchiveSnapshot(session, closedByUserId = null) {
  const totals = cashParticipantTotals(session);
  const lines = cashSettlementLines(totals);
  const recordCards = cashRecordCards(session).map(publicCashRecordCard).filter(Boolean);
  const reports = cashReports(session).map(publicCashReport).filter(Boolean);
  return {
    schema_version: 1,
    audit_status: 'preview_not_final',
    session_id: session.id,
    workspace_id: session.workspace_id,
    title: session.title,
    preset: session.preset,
    mode: session.mode,
    currency: session.currency || 'EUR',
    participants: activeCashParticipants(session),
    batches: Array.isArray(session.batches) ? session.batches : [],
    record_cards: recordCards,
    cash_reports: reports,
    totals,
    settlement_preview: {
      audit_status: 'preview_not_final',
      lines,
    },
    closed_by_user_id: closedByUserId,
    closed_at: now(),
  };
}

function publicCashArchiveItem(session) {
  const snapshot = session.archive_snapshot && typeof session.archive_snapshot === 'object'
    ? session.archive_snapshot
    : cashArchiveSnapshot(session, session.owner_user_id || null);
  return {
    id: session.id,
    workspace_id: session.workspace_id,
    title: session.title,
    preset: session.preset,
    mode: session.mode,
    status: session.status,
    currency: session.currency || 'EUR',
    created_at: session.created_at,
    closed_at: session.closed_at || snapshot.closed_at || session.updated_at,
    updated_at: session.updated_at,
    audit_status: snapshot.audit_status || 'preview_not_final',
    summary: {
      participant_count: snapshot.totals && snapshot.totals.participant_count || 0,
      total_contributions: snapshot.totals && snapshot.totals.total_contributions || 0,
      total_expenses: snapshot.totals && snapshot.totals.total_expenses || 0,
      share: snapshot.totals && snapshot.totals.share || 0,
      batch_count: Array.isArray(snapshot.batches) ? snapshot.batches.length : 0,
      transfer_count: snapshot.settlement_preview && Array.isArray(snapshot.settlement_preview.lines) ? snapshot.settlement_preview.lines.length : 0,
    },
    snapshot,
  };
}

function publicCashParticipantPayload(session, participant) {
  const participantId = String(participant.id || '');
  const totals = cashParticipantTotals(session);
  const ownTotals = totals.participants && totals.participants[participantId]
    ? totals.participants[participantId]
    : {
        participant_id: participantId,
        display_name: participant.display_name || 'Participant',
        role: participant.role || 'participant',
        included_in_split: participant.included_in_split !== false,
        contributions: 0,
        expenses: 0,
        notes: 0,
        balance: 0,
      };
  const notebooks = session.notebooks && typeof session.notebooks === 'object' ? session.notebooks : {};
  const ownBatches = (Array.isArray(session.batches) ? session.batches : []).filter((batch) => String(batch.participant_id || '') === participantId);
  const ownRecordCards = cashRecordCards(session)
    .filter((card) => String(card.participant_id || '') === participantId)
    .map(publicCashRecordCard)
    .filter(Boolean);
  const previewLines = cashSettlementLines(totals).filter((line) => (
    String(line.from_participant_id || '') === participantId || String(line.to_participant_id || '') === participantId
  ));
  return {
    ok: true,
    session: {
      id: session.id,
      title: session.title,
      preset: session.preset,
      mode: session.mode,
      status: session.status,
      currency: session.currency || 'EUR',
      audit_status: 'preview_not_final',
      updated_at: session.updated_at,
    },
    participant: {
      id: participant.id,
      display_name: participant.display_name,
      role: participant.role,
      included_in_split: participant.included_in_split !== false,
      invite_token: participant.invite_token,
      draft_text: notebooks[participantId] ? String(notebooks[participantId].draft_text || '') : '',
      batches: ownBatches,
      record_cards: ownRecordCards,
      totals: ownTotals,
      settlement_preview: {
        audit_status: 'preview_not_final',
        lines: previewLines,
      },
    },
  };
}

async function cashWorkspaceContext(database, input, user) {
  const workspaceId = Number(input.workspace_id || input.group_id || 0);
  if (!workspaceId) return { workspace: null, workspace_id: 0, preset: cashPreset(input.preset || 'personal'), mode: 'personal' };
  const workspace = await workspaceForUser(database, workspaceId, user.id);
  if (!workspace) return null;
  const preset = cashPreset(input.preset || workspace.workspace_type, workspace);
  return { workspace, workspace_id: workspaceId, preset, mode: cashMode(input.mode, preset) };
}

async function cashSessionGetOrCreate(database, input) {
  const user = await currentUser(database);
  const context = await cashWorkspaceContext(database, input, user);
  if (!context) return { ok: false, error: 'workspace_not_found' };
  const existing = await database.collection('cash_sessions')
    .find({ owner_user_id: user.id, workspace_id: context.workspace_id, status: 'active' })
    .sort({ updated_at: -1, id: -1 })
    .limit(1)
    .next();
  if (existing) return { ok: true, session: publicCashSession(existing), created: false };

  const id = await nextId(database, 'cash_sessions');
  const title = String(input.title || '').trim()
    || (context.workspace ? `${context.workspace.name} · ЖЗ` : 'Личный ЖЗ');
  const session = {
    id,
    workspace_id: context.workspace_id,
    workspace_type: context.workspace ? normalizeWorkspaceType(context.workspace.workspace_type, context.workspace.name) : 'solo',
    owner_user_id: user.id,
    title,
    preset: context.preset,
    mode: context.mode,
    status: 'active',
    currency: String(input.currency || 'EUR').trim() || 'EUR',
    participants: [defaultCashParticipant(user, context.mode)],
    notebooks: { owner: { draft_text: '', updated_at: null } },
    batches: [],
    record_cards: [],
    cash_reports: [],
    created_at: now(),
    updated_at: now(),
  };
  await database.collection('cash_sessions').insertOne(session);
  if (context.workspace) await audit(database, 'cash_session_created', context.workspace, { session_id: id, preset: context.preset });
  return { ok: true, session: publicCashSession(session), created: true };
}

async function cashSessionSaveDraft(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  const participantId = cashParticipantId(input.participant_id || 'owner') || 'owner';
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  if (!cashParticipantById(session, participantId)) return { ok: false, error: 'cash_participant_not_found' };
  const updatedAt = now();
  const notebookKey = `notebooks.${participantId}`;
  const draftText = String(input.draft_text || input.notebook_text || '').replace(/\r/g, '');
  const participant = cashParticipantById(session, participantId);
  const draftRecord = draftText.trim() ? cashDraftRecordFromText(session, participant, Object.assign({}, input, { draft_text: draftText }), updatedAt) : null;
  const setPayload = {
    [`${notebookKey}.draft_text`]: draftText,
    [`${notebookKey}.updated_at`]: updatedAt,
    updated_at: updatedAt,
  };
  if (draftRecord) setPayload[`${notebookKey}.active_record_id`] = draftRecord.id;
  const updatePayload = { $set: setPayload };
  if (draftRecord) updatePayload.$set.record_cards = cashUpsertRecordCard(session.record_cards, draftRecord);
  const result = await database.collection('cash_sessions').findOneAndUpdate(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    updatePayload,
    { returnDocument: 'after' }
  );
  if (!result) return { ok: false, error: 'cash_session_not_found' };
  return { ok: true, session: publicCashSession(result) };
}

async function cashSessionSubmitDraft(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  const participantId = cashParticipantId(input.participant_id || 'owner') || 'owner';
  const participant = cashParticipantById(session, participantId);
  if (!participant) return { ok: false, error: 'cash_participant_not_found' };
  const notebook = session.notebooks && session.notebooks[participantId] ? session.notebooks[participantId] : {};
  const text = String(input.draft_text || input.notebook_text || notebook.draft_text || '').replace(/\r/g, '').trim();
  if (!text) return { ok: false, error: 'empty_notebook' };
  const entries = parseCashNotebook(text);
  const activeRecordId = cashNotebookDraftRecordId(session, participantId)
    || `record_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`;
  const batch = {
    id: `batch_${Date.now()}`,
    participant_id: participantId,
    participant_display_name: participant.display_name,
    raw_text: text,
    entries,
    source: String(input.source || 'manual'),
    created_at: now(),
  };
  const updatedAt = now();
  const existingCard = cashRecordCards(session).find((card) => String(card.id || '') === activeRecordId) || {};
  const fixedRecord = {
    id: activeRecordId,
    report_id: cashReportId(input.report_id || existingCard.report_id),
    participant_id: participantId,
    participant_display_name: participant.display_name,
    title: String(input.record_title || existingCard.title || '').trim() || `Запись ${updatedAt.toLocaleString('ru-RU')}`,
    status: 'fixed',
    raw_text: text,
    entries,
    source: String(input.source || existingCard.source || 'manual'),
    source_batch_id: batch.id,
    created_at: existingCard.created_at || updatedAt,
    updated_at: updatedAt,
    fixed_at: updatedAt,
  };
  await database.collection('cash_sessions').updateOne(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    {
      $push: { batches: batch },
      $set: {
        record_cards: cashUpsertRecordCard(session.record_cards, fixedRecord),
        [`notebooks.${participantId}.draft_text`]: '',
        [`notebooks.${participantId}.updated_at`]: updatedAt,
        [`notebooks.${participantId}.active_record_id`]: '',
        updated_at: updatedAt,
      },
    }
  );
  const updated = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id });
  return { ok: true, session: publicCashSession(updated), batch };
}

async function cashReportCreate(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  const title = String(input.title || input.report_title || '').trim().replace(/\s+/g, ' ').slice(0, 140);
  if (!title) return { ok: false, error: 'empty_report_title' };
  const updatedAt = now();
  const report = {
    id: cashReportId(input.report_id) || `report_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`,
    title,
    opening_amount: signedMoney(input.opening_amount || input.incoming_amount || 0),
    status: 'active',
    created_at: updatedAt,
    started_at: updatedAt,
    updated_at: updatedAt,
  };
  const reports = cashReports(session).concat([report]);
  await database.collection('cash_sessions').updateOne(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    { $set: { cash_reports: reports, updated_at: updatedAt } }
  );
  const updated = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id });
  return { ok: true, report: publicCashReport(report), session: publicCashSession(updated) };
}

async function cashReportSetStatus(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  const reportId = cashReportId(input.report_id);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  if (!reportId) return { ok: false, error: 'invalid_report_id' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  const updatedAt = now();
  let found = null;
  const nextStatus = cashReportStatus(input.status || input.report_status || input.action, 'active');
  const reports = cashReports(session).map((report) => {
    if (cashReportId(report.id) !== reportId) return report;
    const next = Object.assign({}, report, {
      status: nextStatus,
      updated_at: updatedAt,
    });
    if (nextStatus === 'fixed') next.fixed_at = updatedAt;
    if (nextStatus === 'archived') next.archived_at = updatedAt;
    if (nextStatus === 'active') {
      next.restored_at = updatedAt;
      next.fixed_at = null;
      next.archived_at = null;
    }
    found = next;
    return next;
  });
  if (!found) return { ok: false, error: 'cash_report_not_found' };
  await database.collection('cash_sessions').updateOne(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    { $set: { cash_reports: reports, updated_at: updatedAt } }
  );
  const updated = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id });
  return { ok: true, report: publicCashReport(found), session: publicCashSession(updated) };
}

async function cashRecordAssign(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  const recordId = String(input.record_id || input.card_id || '').trim();
  const reportId = cashReportId(input.report_id);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  if (!recordId) return { ok: false, error: 'invalid_record_id' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  if (reportId && !cashReportById(session, reportId)) return { ok: false, error: 'cash_report_not_found' };
  const updatedAt = now();
  let found = null;
  let cards = cashRecordCards(session).map((card) => {
    if (String(card.id || '') !== recordId && String(card.source_batch_id || '') !== recordId) return card;
    const next = Object.assign({}, card, { report_id: reportId, updated_at: updatedAt });
    found = next;
    return next;
  });
  if (!found) {
    const batch = (Array.isArray(session.batches) ? session.batches : []).find((item) => String(item.id || '') === recordId);
    if (batch) {
      found = cashRecordCardFromBatch(batch, reportId, updatedAt);
      cards = cashUpsertRecordCard(cards, found);
    }
  }
  if (!found) return { ok: false, error: 'cash_record_not_found' };
  await database.collection('cash_sessions').updateOne(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    { $set: { record_cards: cards, updated_at: updatedAt } }
  );
  const updated = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id });
  return { ok: true, record: publicCashRecordCard(found), session: publicCashSession(updated) };
}

async function cashParticipantUpsert(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  const participants = Array.isArray(session.participants) ? session.participants.slice() : [];
  const incomingId = cashParticipantId(input.participant_id || input.id_participant || input.participant && input.participant.id || '');
  const index = incomingId ? participants.findIndex((item) => item && item.id === incomingId) : -1;
  const generatedId = incomingId || `part_${await nextId(database, 'cash_participants')}`;
  const payload = Object.assign({}, input.participant && typeof input.participant === 'object' ? input.participant : {}, input, { id: generatedId });
  const participant = normalizeCashParticipant(payload, index >= 0 ? participants[index] : {});
  if (participant.id === 'owner') participant.role = cashRole(participant.role, 'treasurer');
  if (index >= 0) participants[index] = participant;
  else participants.push(participant);
  const updatedAt = now();
  await database.collection('cash_sessions').updateOne(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    { $set: { participants, updated_at: updatedAt } }
  );
  const updated = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id });
  return { ok: true, session: publicCashSession(updated), participant };
}

async function cashParticipantRemove(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  const participantId = cashParticipantId(input.participant_id || '');
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  if (!participantId || participantId === 'owner') return { ok: false, error: 'cannot_remove_owner' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  const participants = (Array.isArray(session.participants) ? session.participants : []).map((item) => {
    if (!item || item.id !== participantId) return item;
    return Object.assign({}, item, { active: false, updated_at: now() });
  });
  await database.collection('cash_sessions').updateOne(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    { $set: { participants, updated_at: now() } }
  );
  const updated = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id });
  return { ok: true, session: publicCashSession(updated), removed_participant_id: participantId };
}

async function cashParticipantSessionByToken(database, token) {
  const inviteToken = String(token || '').trim();
  if (!inviteToken) return null;
  return database.collection('cash_sessions').findOne({
    status: 'active',
    participants: { $elemMatch: { invite_token: inviteToken, active: { $ne: false } } },
  });
}

async function cashParticipantView(database, input) {
  const token = String(input.token || input.invite_token || '').trim();
  const session = await cashParticipantSessionByToken(database, token);
  if (!session) return { ok: false, error: 'cash_participant_not_found' };
  const participant = cashParticipantByToken(session, token);
  if (!participant) return { ok: false, error: 'cash_participant_not_found' };
  return publicCashParticipantPayload(session, participant);
}

async function cashParticipantSaveDraft(database, input) {
  const token = String(input.token || input.invite_token || '').trim();
  const session = await cashParticipantSessionByToken(database, token);
  if (!session) return { ok: false, error: 'cash_participant_not_found' };
  const participant = cashParticipantByToken(session, token);
  if (!participant) return { ok: false, error: 'cash_participant_not_found' };
  const participantId = String(participant.id || '');
  const updatedAt = now();
  const draftText = String(input.draft_text || input.notebook_text || '').replace(/\r/g, '');
  const draftRecord = draftText.trim() ? cashDraftRecordFromText(session, participant, Object.assign({}, input, { draft_text: draftText, source: input.source || 'participant' }), updatedAt) : null;
  const setPayload = {
    [`notebooks.${participantId}.draft_text`]: draftText,
    [`notebooks.${participantId}.updated_at`]: updatedAt,
    updated_at: updatedAt,
  };
  if (draftRecord) {
    setPayload[`notebooks.${participantId}.active_record_id`] = draftRecord.id;
    setPayload.record_cards = cashUpsertRecordCard(session.record_cards, draftRecord);
  }
  await database.collection('cash_sessions').updateOne(
    { id: session.id, status: 'active', 'participants.invite_token': token },
    { $set: setPayload }
  );
  const updated = await cashParticipantSessionByToken(database, token);
  return publicCashParticipantPayload(updated, cashParticipantByToken(updated, token));
}

async function cashParticipantSubmitDraft(database, input) {
  const token = String(input.token || input.invite_token || '').trim();
  const session = await cashParticipantSessionByToken(database, token);
  if (!session) return { ok: false, error: 'cash_participant_not_found' };
  const participant = cashParticipantByToken(session, token);
  if (!participant) return { ok: false, error: 'cash_participant_not_found' };
  const participantId = String(participant.id || '');
  const notebooks = session.notebooks && typeof session.notebooks === 'object' ? session.notebooks : {};
  const text = String(input.draft_text || input.notebook_text || (notebooks[participantId] && notebooks[participantId].draft_text) || '').replace(/\r/g, '').trim();
  if (!text) return { ok: false, error: 'empty_notebook' };
  const entries = parseCashNotebook(text);
  const activeRecordId = cashNotebookDraftRecordId(session, participantId)
    || `record_${Date.now()}_${crypto.randomBytes(4).toString('hex')}`;
  const batch = {
    id: `batch_${Date.now()}`,
    participant_id: participantId,
    participant_display_name: participant.display_name,
    raw_text: text,
    entries,
    source: String(input.source || 'participant'),
    created_at: now(),
  };
  const updatedAt = now();
  const existingCard = cashRecordCards(session).find((card) => String(card.id || '') === activeRecordId) || {};
  const fixedRecord = {
    id: activeRecordId,
    report_id: cashReportId(input.report_id || existingCard.report_id),
    participant_id: participantId,
    participant_display_name: participant.display_name,
    title: String(input.record_title || existingCard.title || '').trim() || `Запись ${updatedAt.toLocaleString('ru-RU')}`,
    status: 'fixed',
    raw_text: text,
    entries,
    source: String(input.source || existingCard.source || 'participant'),
    source_batch_id: batch.id,
    created_at: existingCard.created_at || updatedAt,
    updated_at: updatedAt,
    fixed_at: updatedAt,
  };
  await database.collection('cash_sessions').updateOne(
    { id: session.id, status: 'active', 'participants.invite_token': token },
    {
      $push: { batches: batch },
      $set: {
        record_cards: cashUpsertRecordCard(session.record_cards, fixedRecord),
        [`notebooks.${participantId}.draft_text`]: '',
        [`notebooks.${participantId}.updated_at`]: updatedAt,
        [`notebooks.${participantId}.active_record_id`]: '',
        updated_at: updatedAt,
      },
    }
  );
  const updated = await cashParticipantSessionByToken(database, token);
  return Object.assign(publicCashParticipantPayload(updated, cashParticipantByToken(updated, token)), { batch });
}

async function cashSessionClose(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'active' });
  if (!session) return { ok: false, error: 'cash_session_not_found' };
  const snapshot = cashArchiveSnapshot(session, user.id);
  const closedAt = snapshot.closed_at;
  await database.collection('cash_sessions').updateOne(
    { id: sessionId, owner_user_id: user.id, status: 'active' },
    {
      $set: {
        status: 'closed',
        closed_at: closedAt,
        archive_snapshot: snapshot,
        updated_at: closedAt,
      },
    }
  );
  const closed = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id });
  const workspace = session.workspace_id ? await database.collection('workspaces').findOne({ id: Number(session.workspace_id || 0) }) : null;
  if (workspace) {
    await audit(database, 'cash_session_closed', workspace, {
      session_id: sessionId,
      audit_status: snapshot.audit_status,
      batch_count: snapshot.batches.length,
      participant_count: snapshot.totals.participant_count,
    });
  }
  return { ok: true, closed: publicCashArchiveItem(closed) };
}

async function cashSessionArchiveList(database, input) {
  const user = await currentUser(database);
  const workspaceId = Number(input.workspace_id || input.group_id || 0);
  const query = { owner_user_id: user.id, status: 'closed' };
  if (workspaceId) query.workspace_id = workspaceId;
  const archives = await database.collection('cash_sessions')
    .find(query)
    .sort({ closed_at: -1, updated_at: -1, id: -1 })
    .limit(Math.min(100, Math.max(1, Number(input.limit || 50))))
    .toArray();
  return { ok: true, archives: archives.map(publicCashArchiveItem) };
}

async function cashSessionArchiveGet(database, input) {
  const user = await currentUser(database);
  const sessionId = Number(input.session_id || input.id || 0);
  if (!sessionId) return { ok: false, error: 'invalid_session_id' };
  const session = await database.collection('cash_sessions').findOne({ id: sessionId, owner_user_id: user.id, status: 'closed' });
  if (!session) return { ok: false, error: 'cash_archive_not_found' };
  return { ok: true, archive: publicCashArchiveItem(session) };
}

function sourceObservations(region, family, itemKey, registry) {
  const regionSources = registry.regions && registry.regions[region] && Array.isArray(registry.regions[region][family])
    ? registry.regions[region][family]
    : [];
  const baseline = PRICE_BASELINES[region] || PRICE_BASELINES.adriatic_balkans;
  const netValues = baseline.sources[itemKey] || [];
  return regionSources.map((source, index) => {
    const net = Number(netValues[index % netValues.length] || 0);
    return {
      id: source.id || `${family}_source_${index + 1}`,
      label: source.label || source.id || `Source ${index + 1}`,
      url: source.url || '',
      type: source.type || '',
      available: net > 0,
      normalized_net_eur: net > 0 ? roundedMoney(net) : null,
      checked_at: now(),
    };
  });
}

function catalogPriceKeyForItem(family, itemKey) {
  if (family === 'fuel' && itemKey === 'Дизель') return 'marine_diesel_liter';
  return itemKey;
}

function buildSnapshot(region, family) {
  const registry = readPriceRegistry();
  const regionMeta = registry.regions && registry.regions[region] ? registry.regions[region] : {};
  const baseline = PRICE_BASELINES[region] || PRICE_BASELINES.adriatic_balkans;
  const items = PRICE_ITEMS[family] || [];
  const prices = {};
  const sourceDetails = {};
  const warnings = [];
  let availableSources = 0;
  let failedSources = 0;
  let totalSources = 0;

  for (const itemKey of items) {
    const observations = sourceObservations(region, family, itemKey, registry);
    sourceDetails[itemKey] = observations;
    totalSources += observations.length;
    const available = observations.filter((source) => source.available && Number(source.normalized_net_eur || 0) > 0);
    availableSources += available.length;
    failedSources += observations.length - available.length;
    const net = average(available.map((source) => source.normalized_net_eur));
    if (net === null) {
      warnings.push(`No available source values for ${itemKey}; keeping item without computed price.`);
      continue;
    }
    const full = roundedMoney(net * (1 + Number(baseline.tax_rate || 0) + Number(baseline.logistics_rate || 0)) * (1 + Number(baseline.markup_rate || 0)));
    const discount = Number(baseline.duty_free_discount && baseline.duty_free_discount[family] || 0);
    const dutyFree = roundedMoney(full * (1 - discount));
    const payload = {
      item_key: itemKey,
      family,
      currency: 'EUR',
      unit: family === 'fuel' ? 'liter' : 'line',
      net_average_eur: roundedMoney(net),
      full_price_eur: full,
      duty_free_price_eur: dutyFree,
      duty_free_rule: family === 'fuel'
        ? 'fallback regional discount estimate; verify eligibility and port documents'
        : 'fallback regional discount estimate',
      source_count: observations.length,
      available_source_count: available.length,
      failed_source_count: observations.length - available.length,
    };
    prices[itemKey] = payload;
    const alias = catalogPriceKeyForItem(family, itemKey);
    if (alias !== itemKey) prices[alias] = Object.assign({}, payload, { item_key: alias, source_item_key: itemKey });
  }

  if (failedSources > 0 && availableSources > 0) {
    warnings.push(`Some sources failed or had no usable values: ${failedSources}. Average uses remaining sources: ${availableSources}.`);
  }
  if (totalSources > 0 && availableSources === 0) {
    warnings.push('All configured sources are unavailable; no fresh average could be computed.');
  }
  if (family === 'fuel') {
    warnings.push('Duty-free is not a universal 35% discount; current value is a regional fallback estimate until verified supplier data is approved.');
  }

  const createdAt = now();
  return {
    status: 'approved_local_atlas',
    active_catalog: true,
    ui_published: true,
    approved_at: createdAt,
    approver: 'local-atlas-price-engine',
    region,
    region_label: regionMeta.label || region,
    family,
    family_label: family === 'fuel' ? 'Fuel' : 'Food',
    source_candidate: 'atlas-generated-from-source-registry',
    source_snapshot: `${region}:${family}:${createdAt.toISOString()}`,
    registry_version: registry.version || '',
    warnings,
    policy: Object.assign({}, registry.policy || {}, {
      family_refresh_interval_days: family === 'fuel'
        ? Number(registry.policy && registry.policy.fuel_refresh_interval_days || 30)
        : Number(registry.policy && registry.policy.food_refresh_interval_days || 90),
      minimum_sources_per_region: Number(registry.policy && registry.policy.minimum_sources_per_region || 5),
      available_sources: availableSources,
      failed_sources: failedSources,
      total_sources: totalSources,
    }),
    source_details: sourceDetails,
    prices,
    blocked_items: [],
    created_at: createdAt,
    updated_at: createdAt,
  };
}

function publicPriceCatalog(snapshot) {
  if (!snapshot) return null;
  return {
    status: String(snapshot.status || ''),
    active_catalog: !!snapshot.active_catalog,
    ui_published: !!snapshot.ui_published,
    approved_at: snapshot.approved_at || snapshot.created_at || '',
    approver: String(snapshot.approver || ''),
    region: String(snapshot.region || ''),
    region_label: String(snapshot.region_label || ''),
    family: String(snapshot.family || ''),
    family_label: String(snapshot.family_label || ''),
    source_candidate: String(snapshot.source_candidate || ''),
    source_snapshot: String(snapshot.source_snapshot || ''),
    registry_version: String(snapshot.registry_version || ''),
    warnings: Array.isArray(snapshot.warnings) ? snapshot.warnings : [],
    policy: snapshot.policy && typeof snapshot.policy === 'object' ? snapshot.policy : {},
    source_details: snapshot.source_details && typeof snapshot.source_details === 'object' ? snapshot.source_details : {},
    prices: snapshot.prices && typeof snapshot.prices === 'object' ? snapshot.prices : {},
    blocked_items: Array.isArray(snapshot.blocked_items) ? snapshot.blocked_items : [],
  };
}

async function yachtPriceSnapshotRefresh(database, input) {
  await currentUser(database);
  const region = normalizePriceRegion(input.region || 'adriatic_balkans');
  const family = normalizePriceFamily(input.family || 'fuel');
  const snapshot = buildSnapshot(region, family);
  const existing = await database.collection('yacht_price_snapshots')
    .find({ region, family, active_catalog: true })
    .sort({ created_at: -1 })
    .limit(1)
    .next();
  if (snapshot.policy && Number(snapshot.policy.total_sources || 0) > 0 && Number(snapshot.policy.available_sources || 0) === 0 && existing) {
    existing.warnings = Array.from(new Set([...(Array.isArray(existing.warnings) ? existing.warnings : []), 'All sources are currently unavailable; using the last good Atlas snapshot until a new source refresh succeeds.']));
    existing.policy = Object.assign({}, existing.policy || {}, {
      last_refresh_failed_at: now(),
      last_refresh_failed_reason: 'all_sources_unavailable',
    });
    return { ok: true, catalog: publicPriceCatalog(existing), refreshed: false, fallback_to_last_good: true };
  }
  await database.collection('yacht_price_snapshots').updateMany(
    { region, family, active_catalog: true },
    { $set: { active_catalog: false, superseded_at: now() } }
  );
  await database.collection('yacht_price_snapshots').insertOne(snapshot);
  return { ok: true, catalog: publicPriceCatalog(snapshot), refreshed: true };
}

async function yachtPriceApprovedCatalog(database, input) {
  await currentUser(database);
  const region = normalizePriceRegion(input.region || 'adriatic_balkans');
  const family = normalizePriceFamily(input.family || 'fuel');
  let snapshot = await database.collection('yacht_price_snapshots')
    .find({ region, family, active_catalog: true })
    .sort({ created_at: -1 })
    .limit(1)
    .next();
  if (!snapshot) {
    const refreshed = await yachtPriceSnapshotRefresh(database, { region, family });
    return Object.assign({}, refreshed, { refreshed: true });
  }
  return { ok: true, catalog: publicPriceCatalog(snapshot), refreshed: false };
}

function staticApiResponse(action) {
  const ok = { ok: true };
  return ({
    company_profile_get: { ok: true, profile: { company_name: 'FinDesk / brkovic.ltd', email: 'office@brkovic.ltd', website: 'finance.brkovic.ltd' } },
    group_members: { ok: true, members: [] },
    category_list: { ok: true, categories: [] },
    ledger_list: { ok: true, ledgers: [] },
    on_the_go_tape_list: { ok: true, tapes: [] },
    on_the_go_list: { ok: true, items: [] },
    on_the_go_session_list: { ok: true, sessions: [] },
    advance_list: { ok: true, advances: [] },
    findesk_transfer_list: { ok: true, transfers: [] },
    on_the_go_card_list: { ok: true, cards: [] },
    ledger_balance: { ok: true, balance: { cash: 0, card: 0, total: 0 } },
    findesk_report_assembly_get: { ok: true, assembly: { summary: {}, ready_items: [], attached_items: [] } },
    findesk_report_list: { ok: true, reports: [] },
    message_unread: { ok: true, unread: 0, messages: [] },
    client_list: { ok: true, clients: [] },
    proforma_list: { ok: true, proformas: [] },
    findesk_workspace_set: ok,
  })[action] || ok;
}

async function readBody(req) {
  return new Promise((resolve) => {
    let raw = '';
    req.setEncoding('utf8');
    req.on('data', (chunk) => { raw += chunk; });
    req.on('end', () => {
      if (!raw) return resolve({});
      try {
        resolve(JSON.parse(raw));
      } catch (error) {
        const params = new URLSearchParams(raw);
        const body = {};
        for (const [key, value] of params.entries()) body[key] = value;
        resolve(body);
      }
    });
  });
}

async function api(action, input) {
  const database = await db();
  if (action === 'current_user') return { ok: true, user: publicUser(await currentUser(database)) };
  if (action === 'group_list') return groupList(database);
  if (action === 'group_create') return groupCreate(database, input);
  if (action === 'group_trash') return groupTrash(database, input);
  if (action === 'group_trash_list') return groupTrashList(database);
  if (action === 'group_restore') return groupRestore(database, input);
  if (action === 'group_trash_purge_expired') return { ok: true, purged: await purgeExpiredTrash(database), retention_days: TRASH_RETENTION_DAYS };
  if (action === 'yacht_state_get') return yachtStateGet(database, input);
  if (action === 'yacht_state_save') return yachtStateSave(database, input);
  if (action === 'yacht_provision_calculate') return yachtProvisionCalculate(input);
  if (action === 'yacht_price_approved_catalog') return yachtPriceApprovedCatalog(database, input);
  if (action === 'yacht_price_snapshot_refresh') return yachtPriceSnapshotRefresh(database, input);
  if (action === 'cash_session_get_or_create') return cashSessionGetOrCreate(database, input);
  if (action === 'cash_session_save_draft') return cashSessionSaveDraft(database, input);
  if (action === 'cash_session_submit_draft') return cashSessionSubmitDraft(database, input);
  if (action === 'cash_report_create') return cashReportCreate(database, input);
  if (action === 'cash_report_set_status') return cashReportSetStatus(database, input);
  if (action === 'cash_record_assign') return cashRecordAssign(database, input);
  if (action === 'cash_participant_upsert') return cashParticipantUpsert(database, input);
  if (action === 'cash_participant_remove') return cashParticipantRemove(database, input);
  if (action === 'cash_participant_view') return cashParticipantView(database, input);
  if (action === 'cash_participant_save_draft') return cashParticipantSaveDraft(database, input);
  if (action === 'cash_participant_submit_draft') return cashParticipantSubmitDraft(database, input);
  if (action === 'cash_session_close') return cashSessionClose(database, input);
  if (action === 'cash_session_archive_list') return cashSessionArchiveList(database, input);
  if (action === 'cash_session_archive_get') return cashSessionArchiveGet(database, input);
  return staticApiResponse(action);
}

function safeFile(urlPath) {
  let pathname = decodeURIComponent((urlPath || '').split('?')[0]);
  if (pathname === '/' || pathname === '') pathname = '/app.php';
  const resolved = path.resolve(PUBLIC_ROOT, '.' + pathname);
  return resolved.startsWith(PUBLIC_ROOT) ? resolved : null;
}

function sendJson(res, status, payload) {
  res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store' });
  res.end(JSON.stringify(payload));
}

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${HOST}:${PORT}`);
    if (url.pathname === '/api.php') {
      const input = await readBody(req);
      const payload = await api(url.searchParams.get('action') || '', input);
      sendJson(res, 200, payload);
      return;
    }

    const file = safeFile(url.pathname);
    if (!file) {
      res.writeHead(403);
      res.end('Forbidden');
      return;
    }
    fs.readFile(file, (err, data) => {
      if (err) {
        res.writeHead(404);
        res.end('Not found');
        return;
      }
      let body = data;
      if (path.extname(file) === '.php') {
        body = Buffer.from(String(data).replace(/^<\?php[\s\S]*?\?>\s*/m, ''), 'utf8');
      }
      res.writeHead(200, { 'Content-Type': MIME[path.extname(file)] || 'application/octet-stream', 'Cache-Control': 'no-store' });
      res.end(body);
    });
  } catch (error) {
    sendJson(res, 500, { ok: false, error: 'server_error', message: error.message });
  }
});

process.on('SIGINT', async () => {
  if (mongoClient) await mongoClient.close();
  process.exit(0);
});

function startServer() {
  server.listen(PORT, HOST, async () => {
    console.log(`FinDesk Atlas server http://${HOST}:${PORT}/app.php?build=routes42`);
    try {
      await db();
      console.log(`MongoDB Atlas connected: ${MONGO_DB}`);
    } catch (error) {
      console.error(`MongoDB Atlas connection failed: ${error.message}`);
    }
  });
}

if (require.main === module) {
  startServer();
}

module.exports = {
  parseCashNotebook,
  cashParticipantTotals,
  cashSettlementLines,
  publicCashSession,
  cashArchiveSnapshot,
  startServer,
};
