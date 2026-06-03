#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SNAPSHOT_DIR = path.join(ROOT, 'storage', 'yacht-price-catalog');
const CANDIDATE_DIR = path.join(ROOT, 'storage', 'yacht-price-candidates');

const CONFIDENCE_ORDER = {
  missing: 0,
  low: 1,
  medium: 2,
  high: 3,
};

function latestSnapshot() {
  if (!fs.existsSync(SNAPSHOT_DIR)) return '';
  return fs.readdirSync(SNAPSHOT_DIR)
    .filter((name) => name.endsWith('-node.json'))
    .map((name) => path.join(SNAPSHOT_DIR, name))
    .sort()
    .at(-1) || '';
}

function parseArgs(argv) {
  const args = {
    snapshot: '',
    minConfidence: 'medium',
    includeEstimatedDutyFree: true,
  };

  for (const arg of argv.slice(2)) {
    if (arg.startsWith('--snapshot=')) args.snapshot = arg.slice('--snapshot='.length);
    else if (arg.startsWith('--min-confidence=')) args.minConfidence = arg.slice('--min-confidence='.length);
    else if (arg === '--no-estimated-duty-free') args.includeEstimatedDutyFree = false;
    else if (arg === '--help' || arg === '-h') {
      console.log([
        'Usage:',
        '  node scripts/yacht_price_candidate_from_snapshot.cjs [--snapshot=path] [--min-confidence=medium|high] [--no-estimated-duty-free]',
        '',
        'Creates a review-only publication candidate. It does not update the UI catalog.',
      ].join('\n'));
      process.exit(0);
    }
  }

  if (!CONFIDENCE_ORDER[args.minConfidence]) {
    args.minConfidence = 'medium';
  }

  return args;
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function writeJson(file, data) {
  fs.mkdirSync(path.dirname(file), {recursive: true, mode: 0o775});
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`, {mode: 0o664});
}

function confidenceOk(confidence, minConfidence) {
  return (CONFIDENCE_ORDER[confidence] || 0) >= (CONFIDENCE_ORDER[minConfidence] || 2);
}

function blockReason(item, minConfidence, includeEstimatedDutyFree) {
  if (!item.item_key) return 'missing_item_key';
  if (!confidenceOk(item.confidence, minConfidence)) return `confidence_below_${minConfidence}`;
  if (!(Number(item.final_full_price_eur) > 0)) return 'missing_full_price';
  if (!includeEstimatedDutyFree && item.duty_free_basis === 'estimated_discount') return 'estimated_duty_free_disabled';
  return '';
}

function sourceRefs(item) {
  return (item.sources || []).map((source) => ({
    title: source.title || '',
    url: source.url || '',
    price_basis: source.price_basis || '',
    normalized_net_eur: source.normalized_net_eur || 0,
    confidence: source.confidence || '',
  }));
}

function buildCandidate(snapshot, snapshotPath, args) {
  const createdAt = new Date().toISOString();
  const accepted = [];
  const blocked = [];

  for (const item of snapshot.computed?.items || []) {
    const reason = blockReason(item, args.minConfidence, args.includeEstimatedDutyFree);
    if (reason) {
      blocked.push({
        item_key: item.item_key || '',
        confidence: item.confidence || 'missing',
        reason,
        final_full_price_eur: item.final_full_price_eur || 0,
        final_duty_free_price_eur: item.final_duty_free_price_eur || 0,
        source_count: item.source_count || 0,
      });
      continue;
    }

    accepted.push({
      item_key: item.item_key,
      name: item.name || item.item_key,
      unit: item.unit || '',
      confidence: item.confidence,
      calculation_basis: item.calculation_basis || '',
      full_price_eur: item.final_full_price_eur,
      duty_free_price_eur: item.duty_free_basis === 'estimated_discount' && !args.includeEstimatedDutyFree
        ? 0
        : item.final_duty_free_price_eur,
      duty_free_basis: item.duty_free_basis || '',
      source_count: item.source_count || 0,
      accepted_source_count: item.accepted_source_count || 0,
      rejected_source_count: item.rejected_source_count || 0,
      sources: sourceRefs(item),
      notes: item.notes || '',
    });
  }

  return {
    ok: true,
    type: 'yacht_price_publication_candidate',
    status: 'pending_review',
    created_at: createdAt,
    source_snapshot: snapshotPath,
    source_generated_at: snapshot.generated_at || '',
    region: snapshot.region || '',
    region_label: snapshot.region_label || '',
    family: snapshot.family || '',
    family_label: snapshot.family_label || '',
    model: snapshot.model || '',
    min_confidence: args.minConfidence,
    publish_target: 'yacht_price_engine',
    publish_allowed: false,
    warnings: snapshot.observations?.warnings || [],
    policy: snapshot.computed?.policy || {},
    accepted_items: accepted,
    blocked_items: blocked,
    summary: {
      accepted_items: accepted.length,
      blocked_items: blocked.length,
      estimated_duty_free_items: accepted.filter((item) => item.duty_free_basis === 'estimated_discount').length,
    },
  };
}

const args = parseArgs(process.argv);
const snapshotPath = args.snapshot || latestSnapshot();

if (!snapshotPath) {
  console.error(JSON.stringify({ok: false, error: 'snapshot_not_found'}, null, 2));
  process.exit(1);
}

const snapshot = readJson(snapshotPath);
const candidate = buildCandidate(snapshot, snapshotPath, args);
const stamp = candidate.created_at.replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
const file = path.join(CANDIDATE_DIR, `${stamp}-${candidate.region}-${candidate.family}-candidate.json`);
writeJson(file, candidate);

console.log(JSON.stringify({
  ok: true,
  candidate: file,
  summary: candidate.summary,
  status: candidate.status,
  publish_allowed: candidate.publish_allowed,
}, null, 2));
