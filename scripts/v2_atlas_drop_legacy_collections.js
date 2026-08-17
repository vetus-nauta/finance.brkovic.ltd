const fs = require('fs');
const path = require('path');
const { MongoClient } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');
const DB_NAME = process.env.FINDESK_MONGO_DB || 'finance_brkovic_ltd';
const CONFIRM_PHRASE = 'DROP_FINDESK_LEGACY_ATLAS_COLLECTIONS';
const LEGACY_COLLECTIONS = [
  'cash_sessions',
  'counters',
  'workspace_audit',
  'workspaces',
  'yacht_price_snapshots',
  'yacht_states',
];

function argValue(name) {
  const index = process.argv.indexOf(name);
  if (index === -1) return null;
  return process.argv[index + 1] || null;
}

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  return fs.readFileSync(URI_FILE, 'utf8').trim();
}

function assertBackup(pathValue) {
  if (!pathValue || !fs.existsSync(pathValue) || !pathValue.includes('/v2-atlas-backup-')) {
    throw new Error('fresh_v2_atlas_backup_required');
  }
}

async function main() {
  const commit = process.argv.includes('--commit');
  const backup = argValue('--backup');
  const confirm = argValue('--confirm');
  assertBackup(backup);
  if (commit && confirm !== CONFIRM_PHRASE) {
    throw new Error(`confirm_required:${CONFIRM_PHRASE}`);
  }

  const client = new MongoClient(readMongoUri(), {
    serverSelectionTimeoutMS: 30000,
    connectTimeoutMS: 30000,
    socketTimeoutMS: 30000,
  });
  await client.connect();
  try {
    const db = client.db(DB_NAME);
    const existing = await db.listCollections({}, { nameOnly: true }).toArray();
    const existingNames = new Set(existing.map((item) => item.name));
    const result = [];
    for (const name of LEGACY_COLLECTIONS) {
      if (!existingNames.has(name)) {
        result.push({ name, exists: false, dropped: false });
        continue;
      }
      if (commit) {
        await db.collection(name).drop();
      }
      result.push({ name, exists: true, dropped: commit });
    }
    console.log(JSON.stringify({
      ok: true,
      mode: commit ? 'commit' : 'dry_run',
      backup,
      database: DB_NAME,
      legacy_collections: result,
    }, null, 2));
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error(JSON.stringify({ ok: false, error: error.message }, null, 2));
  process.exit(1);
});
