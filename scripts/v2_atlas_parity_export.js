const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { MongoClient, Decimal128, ObjectId } = require('mongodb');

const ROOT = path.resolve(__dirname, '..');
const URI_FILE = process.env.FINDESK_MONGO_URI_FILE || path.join(ROOT, 'storage', 'secrets', 'mongodb_uri');
const DB_NAME = process.env.FINDESK_MONGO_DB || 'finance_brkovic_ltd';

const EXPECTED_COLLECTIONS = [
  'users',
  'v2_accountable_offers',
  'v2_accountable_report_entry_links',
  'v2_accountable_report_rows',
  'v2_accountable_reports',
  'v2_accountable_settlements',
  'v2_actors',
  'v2_attachments',
  'v2_audit_log',
  'v2_categories',
  'v2_category_rules',
  'v2_dictionary_training_decisions',
  'v2_entries',
  'v2_flows',
  'v2_import_rows',
  'v2_import_sources',
  'v2_internet_reference_lookups',
  'v2_monthly_closures',
  'v2_quick_notes',
  'v2_report_batch_entries',
  'v2_report_batch_html_snapshots',
  'v2_report_batches',
  'v2_report_package_items',
  'v2_report_packages',
  'v2_report_snapshots',
  'v2_report_versions',
  'v2_workspace_assistant_settings',
  'v2_workspace_invites',
  'v2_workspace_liability_openings',
  'v2_workspace_members',
  'v2_workspaces',
];

function readMongoUri() {
  if (process.env.FINDESK_MONGO_URI) return process.env.FINDESK_MONGO_URI.trim();
  return fs.readFileSync(URI_FILE, 'utf8').trim();
}

function canonicalize(value) {
  if (value instanceof Decimal128) return value.toString();
  if (value instanceof ObjectId) return value.toHexString();
  if (value instanceof Date) return value.toISOString();
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

function rawSha256(value) {
  return crypto.createHash('sha256').update(String(value)).digest('hex');
}

function artifactDir() {
  const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
  const dir = path.join(ROOT, 'storage', 'production-audits', `v2-parity-export-atlas-${stamp}`);
  fs.mkdirSync(dir, { recursive: true });
  return dir;
}

function rowKey(doc) {
  if (doc && doc.id !== undefined && doc.id !== null) return String(doc.id);
  if (doc && doc.created_seq !== undefined && doc.created_seq !== null) return String(doc.created_seq);
  if (doc && doc._id !== undefined && doc._id !== null) return canonicalize(doc._id);
  return hash(doc);
}

function compareRowKeys(a, b) {
  if (/^\d+$/.test(a.key) && /^\d+$/.test(b.key)) {
    return Number(a.key) - Number(b.key);
  }
  return a.key.localeCompare(b.key);
}

function redactedDoc(collectionName, doc) {
  const copy = { ...doc };
  if (collectionName === 'users') {
    if (copy.email) {
      copy.email_hash = rawSha256(String(copy.email).toLowerCase());
      delete copy.email;
    }
    delete copy.password_hash;
  }
  delete copy._id;
  return copy;
}

async function exportCollection(db, name) {
  const exists = await db.listCollections({ name }, { nameOnly: true }).hasNext();
  if (!exists) {
    return { count: 0, table_hash: hash([]), rows: [], missing_collection: true };
  }
  const docs = await db.collection(name).find({}).toArray();
  const rows = docs
    .map((doc) => redactedDoc(name, doc))
    .map((doc) => ({ key: rowKey(doc), hash: hash(doc) }))
    .sort(compareRowKeys);
  return { count: rows.length, table_hash: hash(rows), rows };
}

async function main() {
  const client = new MongoClient(readMongoUri(), {
    serverSelectionTimeoutMS: 8000,
    connectTimeoutMS: 8000,
  });
  await client.connect();

  try {
    const db = client.db(DB_NAME);
    const tables = {};
    for (const name of EXPECTED_COLLECTIONS) {
      tables[name] = await exportCollection(db, name);
    }

    const allCollections = await db.listCollections({}, { nameOnly: true }).toArray();
    const legacyCollections = allCollections
      .map((item) => item.name)
      .filter((name) => !EXPECTED_COLLECTIONS.includes(name))
      .sort();

    const payload = {
      generated_at: new Date().toISOString(),
      source: 'atlas',
      database: DB_NAME,
      format: 'findesk_v2_parity_export_v1',
      redaction: {
        'users.email': 'sha256_lowercase',
        'users.password_hash': 'omitted',
      },
      table_count: Object.keys(tables).length,
      tables,
      legacy_collections_present: legacyCollections,
    };

    const output = path.join(artifactDir(), 'atlas-parity-export.json');
    fs.writeFileSync(output, `${JSON.stringify(payload, null, 2)}\n`);
    console.log(`Atlas parity export written: ${output}`);
    console.log(`Expected v2 collections: ${Object.keys(tables).length}`);
    console.log(`v2_entries count: ${tables.v2_entries.count}`);
    console.log(`Legacy collections present: ${legacyCollections.join(', ') || 'none'}`);
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
