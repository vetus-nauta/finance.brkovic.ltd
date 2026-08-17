const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const AUDIT_ROOT = path.join(ROOT, 'storage', 'production-audits');

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function latestExport(prefix, fileName) {
  if (!fs.existsSync(AUDIT_ROOT)) return null;
  const candidates = fs.readdirSync(AUDIT_ROOT)
    .filter((name) => name.startsWith(prefix))
    .map((name) => path.join(AUDIT_ROOT, name, fileName))
    .filter((candidate) => fs.existsSync(candidate))
    .sort((a, b) => fs.statSync(b).mtimeMs - fs.statSync(a).mtimeMs);
  return candidates[0] || null;
}

function argValue(name) {
  const index = process.argv.indexOf(name);
  if (index === -1) return null;
  return process.argv[index + 1] || null;
}

function rowMap(table) {
  const map = new Map();
  for (const row of table?.rows || []) {
    map.set(String(row.key), String(row.hash));
  }
  return map;
}

function compareRows(sourceTable, targetTable) {
  const source = rowMap(sourceTable);
  const target = rowMap(targetTable);
  let insert = 0;
  let update = 0;
  let noop = 0;
  let targetOnly = 0;
  const examples = {
    insert: [],
    update: [],
    target_only: [],
  };

  for (const [key, sourceHash] of source.entries()) {
    if (!target.has(key)) {
      insert += 1;
      if (examples.insert.length < 10) examples.insert.push(key);
    } else if (target.get(key) !== sourceHash) {
      update += 1;
      if (examples.update.length < 10) examples.update.push(key);
    } else {
      noop += 1;
    }
  }
  for (const key of target.keys()) {
    if (!source.has(key)) {
      targetOnly += 1;
      if (examples.target_only.length < 10) examples.target_only.push(key);
    }
  }

  return {
    source_count: sourceTable?.count || 0,
    target_count: targetTable?.count || 0,
    insert,
    insert_count: insert,
    update,
    update_count: update,
    noop,
    noop_count: noop,
    target_only: targetOnly,
    extra_target_count: targetOnly,
    missing_dependency_count: 0,
    blocked_count: 0,
    source_hash: sourceTable?.table_hash || null,
    target_hash: targetTable?.table_hash || null,
    status: insert === 0 && update === 0 && targetOnly === 0 ? 'in_sync' : 'migration_needed',
    examples,
  };
}

function writeArtifact(payload) {
  const stamp = new Date().toISOString().replace(/\D/g, '').slice(0, 14);
  const dir = path.join(AUDIT_ROOT, `v2-atlas-migration-dry-run-${stamp}`);
  fs.mkdirSync(dir, { recursive: true });
  const output = path.join(dir, 'migration-dry-run.json');
  fs.writeFileSync(output, `${JSON.stringify(payload, null, 2)}\n`);
  return output;
}

function main() {
  if (process.argv.includes('--commit')) {
    console.error('Commit is intentionally disabled in SPRINT-62R dry-run planner.');
    process.exit(2);
  }

  const mysqlPath = argValue('--mysql') || latestExport('v2-parity-export-', 'mysql-parity-export.json');
  const atlasPath = argValue('--atlas') || latestExport('v2-parity-export-atlas-', 'atlas-parity-export.json');

  if (!mysqlPath || !atlasPath) {
    console.error('Missing parity exports. Run npm run parity:v2:mysql and npm run parity:v2:atlas first.');
    process.exit(2);
  }

  const mysql = readJson(mysqlPath);
  const atlas = readJson(atlasPath);
  const collectionPlan = {};
  const blockers = [];
  const warnings = [];

  if (mysql.format !== 'findesk_v2_parity_export_v1' || atlas.format !== 'findesk_v2_parity_export_v1') {
    blockers.push('parity_export_format_mismatch');
  }
  if (mysql.source !== 'mysql') {
    blockers.push('left_export_is_not_mysql');
  }
  if (atlas.source !== 'atlas') {
    blockers.push('right_export_is_not_atlas');
  }

  const tableNames = Object.keys(mysql.tables || {}).sort();
  const integrityIssueCount = Number(mysql.integrity?.total_issues || 0);
  if (integrityIssueCount > 0) {
    blockers.push(`source_integrity_issues_${integrityIssueCount}`);
  }

  for (const name of tableNames) {
    collectionPlan[name] = compareRows(mysql.tables[name], atlas.tables?.[name]);
    if (collectionPlan[name].target_only > 0) {
      warnings.push(`${name}: target has ${collectionPlan[name].target_only} rows not present in MySQL export`);
    }
  }

  const legacyCollections = atlas.legacy_collections_present || [];
  const legacyConflict = legacyCollections.filter((name) => ['workspaces', 'users'].includes(name));
  if (legacyConflict.length) {
    warnings.push(`legacy Atlas shell collections present: ${legacyConflict.join(', ')}`);
  }

  const totals = Object.values(collectionPlan).reduce((acc, item) => {
    acc.insert += item.insert;
    acc.update += item.update;
    acc.noop += item.noop;
    acc.target_only += item.target_only;
    return acc;
  }, { insert: 0, update: 0, noop: 0, target_only: 0 });

  const criticalTables = ['users', 'v2_workspaces', 'v2_flows', 'v2_entries', 'v2_report_batches', 'v2_report_batch_entries'];
  for (const name of criticalTables) {
    if (!collectionPlan[name]) {
      blockers.push(`missing_plan_for_${name}`);
    }
  }

  const payload = {
    generated_at: new Date().toISOString(),
    mode: 'dry_run_no_writes',
    source_export: mysqlPath,
    target_export: atlasPath,
    requires_backup: true,
    requires_explicit_commit_flag: true,
    commit_enabled: false,
    ready_for_commit: false,
    expected_after_commit_parity: blockers.length === 0,
    money_policy: {
      source: 'MySQL DECIMAL strings',
      target_allowed: ['Decimal128', 'canonical decimal string'],
      forbidden: ['JavaScript Number for persisted money'],
    },
    totals,
    source_integrity: mysql.integrity || null,
    blockers,
    warnings,
    collection_plan: collectionPlan,
    decision: blockers.length ? 'blocked' : totals.insert || totals.update || totals.target_only ? 'migration_required' : 'already_in_sync',
  };

  const output = writeArtifact(payload);
  console.log(`Atlas migration dry-run written: ${output}`);
  console.log(`Decision: ${payload.decision}`);
  console.log(`Insert: ${totals.insert}`);
  console.log(`Update: ${totals.update}`);
  console.log(`Noop: ${totals.noop}`);
  console.log(`Target-only: ${totals.target_only}`);
  if (warnings.length) console.log(`Warnings: ${warnings.length}`);
  if (blockers.length) {
    console.log(`Blockers: ${blockers.join(', ')}`);
    process.exit(1);
  }
}

main();
