const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { MongoClient, Decimal128, ObjectId } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');
const DB_NAME = process.env.FINDESK_MONGO_DB || 'finance_brkovic_ltd';

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  return fs.readFileSync(URI_FILE, 'utf8').trim();
}

function canonicalize(value) {
  if (value instanceof Decimal128) return { $decimal128: value.toString() };
  if (value instanceof ObjectId) return { $oid: value.toHexString() };
  if (value instanceof Date) return { $date: value.toISOString() };
  if (Array.isArray(value)) return value.map(canonicalize);
  if (value && typeof value === 'object') {
    return Object.keys(value).sort().reduce((acc, key) => {
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
  const dir = path.join(ROOT, 'storage', 'production-audits', `v2-atlas-backup-${stamp}`);
  fs.mkdirSync(dir, { recursive: true });
  return dir;
}

async function main() {
  const client = new MongoClient(readMongoUri(), {
    serverSelectionTimeoutMS: 8000,
    connectTimeoutMS: 8000,
  });
  await client.connect();

  try {
    const db = client.db(DB_NAME);
    const collectionInfos = await db.listCollections({}, { nameOnly: true }).toArray();
    const names = collectionInfos.map((item) => item.name).sort();
    const collections = {};
    const manifest = {};

    for (const name of names) {
      const docs = await db.collection(name).find({}).toArray();
      const indexes = await db.collection(name).indexes();
      const normalized = docs.map(canonicalize);
      collections[name] = normalized;
      manifest[name] = {
        count: normalized.length,
        hash: hash(normalized),
        indexes: indexes.map((index) => ({
          name: index.name,
          key: index.key,
          unique: Boolean(index.unique),
        })),
      };
    }

    const backup = {
      generated_at: new Date().toISOString(),
      mode: 'atlas_backup_export_read_only',
      database: DB_NAME,
      collections_count: names.length,
      collections: names,
      manifest,
      data: collections,
      backup_hash: hash(manifest),
    };

    const output = path.join(artifactDir(), 'atlas-backup.json');
    fs.writeFileSync(output, `${JSON.stringify(backup, null, 2)}\n`);

    console.log(`Atlas backup written: ${output}`);
    console.log(`Database: ${DB_NAME}`);
    console.log(`Collections: ${names.length}`);
    console.log(`Backup hash: ${backup.backup_hash}`);
    for (const name of names) {
      console.log(`${name}\t${manifest[name].count}`);
    }
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
