const fs = require('fs');
const path = require('path');
const { MongoClient } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');
const DB_NAME = process.env.FINDESK_MONGO_DB || 'finance_brkovic_ltd';

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  return fs.readFileSync(URI_FILE, 'utf8').trim();
}

function artifactDir() {
  const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
  const dir = path.join(ROOT, 'storage', 'production-audits', `v2-persistence-foundation-atlas-${stamp}`);
  fs.mkdirSync(dir, { recursive: true });
  return dir;
}

function sampleKeys(doc) {
  if (!doc || typeof doc !== 'object') return [];
  return Object.keys(doc).filter((key) => key !== '_id').sort();
}

async function main() {
  const client = new MongoClient(readMongoUri(), {
    serverSelectionTimeoutMS: 8000,
    connectTimeoutMS: 8000,
  });
  await client.connect();

  try {
    const db = client.db(DB_NAME);
    const collections = await db.listCollections({}, { nameOnly: true }).toArray();
    const summary = [];

    for (const collectionInfo of collections.sort((a, b) => a.name.localeCompare(b.name))) {
      const collection = db.collection(collectionInfo.name);
      const [count, indexes, sample] = await Promise.all([
        collection.countDocuments({}),
        collection.indexes(),
        collection.findOne({}, { projection: { _id: 0 } }),
      ]);
      summary.push({
        name: collectionInfo.name,
        count,
        sample_keys: sampleKeys(sample),
        indexes: indexes.map((index) => ({
          name: index.name,
          key: index.key,
          unique: Boolean(index.unique),
        })),
      });
    }

    const audit = {
      generated_at: new Date().toISOString(),
      source: 'mongodb_atlas',
      scope: 'read_only_collection_model',
      database: DB_NAME,
      collections_count: summary.length,
      collections: summary,
    };

    const output = path.join(artifactDir(), 'atlas-model-audit.json');
    fs.writeFileSync(output, `${JSON.stringify(audit, null, 2)}\n`);
    console.log(`Atlas model audit written: ${output}`);
    console.log(`Database: ${DB_NAME}`);
    console.log(`Collections: ${summary.length}`);
    for (const item of summary) {
      console.log(`${item.name}\t${item.count}`);
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
