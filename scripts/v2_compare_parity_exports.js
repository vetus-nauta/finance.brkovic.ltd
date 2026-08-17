const fs = require('fs');

function readJson(path) {
  return JSON.parse(fs.readFileSync(path, 'utf8'));
}

function indexRows(rows = []) {
  const map = new Map();
  for (const row of rows) map.set(String(row.key), String(row.hash));
  return map;
}

function compareTable(name, left, right) {
  const issues = [];
  if (!left || !right) {
    issues.push({ type: 'table_missing', left: Boolean(left), right: Boolean(right) });
    return issues;
  }
  if (left.count !== right.count) {
    issues.push({ type: 'count_mismatch', left: left.count, right: right.count });
  }
  if (left.table_hash !== right.table_hash) {
    issues.push({ type: 'hash_mismatch', left: left.table_hash, right: right.table_hash });
  }

  const leftRows = indexRows(left.rows);
  const rightRows = indexRows(right.rows);
  const missingRight = [];
  const missingLeft = [];
  const changed = [];

  for (const [key, hash] of leftRows.entries()) {
    if (!rightRows.has(key)) missingRight.push(key);
    else if (rightRows.get(key) !== hash) changed.push(key);
  }
  for (const key of rightRows.keys()) {
    if (!leftRows.has(key)) missingLeft.push(key);
  }

  if (missingRight.length) issues.push({ type: 'missing_in_right', count: missingRight.length, examples: missingRight.slice(0, 20) });
  if (missingLeft.length) issues.push({ type: 'missing_in_left', count: missingLeft.length, examples: missingLeft.slice(0, 20) });
  if (changed.length) issues.push({ type: 'row_hash_mismatch', count: changed.length, examples: changed.slice(0, 20) });
  return issues;
}

function main() {
  const [leftPath, rightPath] = process.argv.slice(2);
  if (!leftPath || !rightPath) {
    console.error('Usage: node scripts/v2_compare_parity_exports.js <left-export.json> <right-export.json>');
    process.exit(2);
  }

  const left = readJson(leftPath);
  const right = readJson(rightPath);
  const tableNames = Array.from(new Set([
    ...Object.keys(left.tables || {}),
    ...Object.keys(right.tables || {}),
  ])).sort();

  const tables = {};
  for (const name of tableNames) {
    const issues = compareTable(name, left.tables[name], right.tables[name]);
    if (issues.length) tables[name] = issues;
  }

  const result = {
    compared_at: new Date().toISOString(),
    left: { source: left.source, path: leftPath },
    right: { source: right.source, path: rightPath },
    format: left.format === right.format ? left.format : 'format_mismatch',
    table_count: tableNames.length,
    mismatch_count: Object.keys(tables).length,
    tables,
  };

  console.log(JSON.stringify(result, null, 2));
  process.exit(result.mismatch_count === 0 ? 0 : 1);
}

main();
