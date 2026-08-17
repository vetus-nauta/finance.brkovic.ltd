const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { MongoClient } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');
const DB_NAME = process.env.FINDESK_MONGO_DB || 'finance_brkovic_ltd';
const CONFIRM_PHRASE = 'WRITE_V2_TO_ATLAS_20260813';

function argValue(name) {
  const index = process.argv.indexOf(name);
  if (index === -1) return null;
  return process.argv[index + 1] || null;
}

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  return fs.readFileSync(URI_FILE, 'utf8').trim();
}

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function latestArtifact(prefix, fileName) {
  const root = path.join(ROOT, 'storage', 'production-audits');
  if (!fs.existsSync(root)) return null;
  const candidates = fs.readdirSync(root)
    .filter((name) => name.startsWith(prefix))
    .map((name) => path.join(root, name, fileName))
    .filter((candidate) => fs.existsSync(candidate))
    .sort((a, b) => fs.statSync(b).mtimeMs - fs.statSync(a).mtimeMs);
  return candidates[0] || null;
}

function canonicalize(value) {
  if (Array.isArray(value)) return value.map(canonicalize);
  if (value && typeof value === 'object') {
    return Object.keys(value).sort().reduce((acc, key) => {
      if (key === '_id') return acc;
      acc[key] = canonicalize(value[key]);
      return acc;
    }, {});
  }
  return value;
}

function hash(value) {
  return crypto.createHash('sha256').update(JSON.stringify(canonicalize(value))).digest('hex');
}

function artifactDir() {
  const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
  const dir = path.join(ROOT, 'storage', 'production-audits', `v2-atlas-commit-plan-${stamp}`);
  fs.mkdirSync(dir, { recursive: true });
  return dir;
}

function assertSafeCollectionName(name, allowUsersUpdate) {
  if (name === 'users' && allowUsersUpdate) return;
  if (/^v2_[a-z0-9_]+$/.test(name)) return;
  throw new Error(`unsafe_collection_target:${name}`);
}

function validatePayloadCollections(payload, allowUsersUpdate) {
  const collections = payload.collections || {};
  for (const name of Object.keys(collections)) {
    assertSafeCollectionName(name, allowUsersUpdate);
  }
}

function documentKey(document) {
  if (document && document.id !== undefined && document.id !== null) return String(document.id);
  throw new Error('payload_document_missing_id');
}

async function currentMap(db, collectionName) {
  const exists = await db.listCollections({ name: collectionName }, { nameOnly: true }).hasNext();
  if (!exists) return new Map();
  const docs = await db.collection(collectionName).find({}).toArray();
  const map = new Map();
  for (const doc of docs) {
    if (doc.id !== undefined && doc.id !== null) {
      map.set(String(doc.id), hash(doc));
    }
  }
  return map;
}

async function buildPlan(db, payload, allowUsersUpdate) {
  const collections = payload.collections || {};
  const plan = {};
  for (const [name, documents] of Object.entries(collections)) {
    assertSafeCollectionName(name, allowUsersUpdate);
    const target = await currentMap(db, name);
    let insert = 0;
    let update = 0;
    let noop = 0;
    const examples = { insert: [], update: [] };
    for (const document of documents) {
      const key = documentKey(document);
      const documentHash = hash(document);
      if (!target.has(key)) {
        insert += 1;
        if (examples.insert.length < 10) examples.insert.push(key);
      } else if (target.get(key) !== documentHash) {
        update += 1;
        if (examples.update.length < 10) examples.update.push(key);
      } else {
        noop += 1;
      }
    }
    plan[name] = {
      payload_count: documents.length,
      insert,
      update,
      noop,
      examples,
    };
  }
  return plan;
}

function summarizePlan(plan) {
  return Object.values(plan).reduce((acc, item) => {
    acc.insert += item.insert;
    acc.update += item.update;
    acc.noop += item.noop;
    acc.documents += item.payload_count;
    return acc;
  }, { documents: 0, insert: 0, update: 0, noop: 0 });
}

function writePlanArtifact(payload) {
  const output = path.join(artifactDir(), 'atlas-commit-plan.json');
  fs.writeFileSync(output, `${JSON.stringify(payload, null, 2)}\n`);
  return output;
}

async function applyCommit(db, payload, plan, allowUsersUpdate) {
  for (const [name, documents] of Object.entries(payload.collections || {})) {
    assertSafeCollectionName(name, allowUsersUpdate);
    if (!documents.length) continue;
    const operations = documents.map((document) => ({
      replaceOne: {
        filter: { id: document.id },
        replacement: document,
        upsert: true,
      },
    }));
    for (let index = 0; index < operations.length; index += 500) {
      await db.collection(name).bulkWrite(operations.slice(index, index + 500), { ordered: true });
    }
    await db.collection(name).createIndex({ id: 1 }, { unique: true });
  }
  return summarizePlan(plan);
}

async function main() {
  const commit = process.argv.includes('--commit');
  const payloadPath = argValue('--payload') || latestArtifact('v2-atlas-payload-', 'atlas-payload.json');
  const backupPath = argValue('--backup');
  const confirm = argValue('--confirm');
  const allowUsersUpdate = process.argv.includes('--allow-users-update');

  if (!payloadPath) {
    console.error('Missing payload. Run npm run payload:v2:atlas first or pass --payload.');
    process.exit(2);
  }
  if (commit && backupPath === null) {
    console.error('Commit requires --backup <atlas-backup.json>.');
    process.exit(2);
  }
  if (commit && confirm !== CONFIRM_PHRASE) {
    console.error(`Commit requires --confirm ${CONFIRM_PHRASE}.`);
    process.exit(2);
  }
  if (commit && (!fs.existsSync(backupPath) || !backupPath.includes('/v2-atlas-backup-'))) {
    console.error('Commit requires a valid fresh Atlas backup artifact path.');
    process.exit(2);
  }

  const payload = readJson(payloadPath);
  if (payload.format !== 'findesk_v2_atlas_payload_v1') {
    console.error('Unsupported payload format.');
    process.exit(2);
  }
  try {
    validatePayloadCollections(payload, allowUsersUpdate);
  } catch (error) {
    console.error(error.message);
    process.exit(2);
  }

  const client = new MongoClient(readMongoUri(), {
    serverSelectionTimeoutMS: 30000,
    connectTimeoutMS: 30000,
    socketTimeoutMS: 30000,
  });
  await client.connect();

  try {
    const db = client.db(DB_NAME);
    const plan = await buildPlan(db, payload, allowUsersUpdate);
    const totals = summarizePlan(plan);
    const planPayload = {
      generated_at: new Date().toISOString(),
      mode: commit ? 'commit_requested' : 'dry_run_no_writes',
      database: DB_NAME,
      payload: payloadPath,
      backup: backupPath || null,
      commit_enabled: commit,
      allow_users_update: allowUsersUpdate,
      confirm_phrase_required: CONFIRM_PHRASE,
      totals,
      plan,
    };
    const planPath = writePlanArtifact(planPayload);

    console.log(`Atlas commit plan written: ${planPath}`);
    console.log(`Mode: ${planPayload.mode}`);
    console.log(`Documents: ${totals.documents}`);
    console.log(`Insert: ${totals.insert}`);
    console.log(`Update: ${totals.update}`);
    console.log(`Noop: ${totals.noop}`);

    if (!commit) {
      console.log('No Atlas writes performed.');
      return;
    }

    const applied = await applyCommit(db, payload, plan, allowUsersUpdate);
    console.log(`Atlas commit applied. Documents planned: ${applied.documents}`);
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error(JSON.stringify({
    ok: false,
    name: error.name,
    message: String(error.message || '').split('\n')[0],
  }, null, 2));
  process.exit(1);
});
