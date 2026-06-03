#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const CANDIDATE_DIR = path.join(ROOT, 'storage', 'yacht-price-candidates');
const APPROVED_DIR = path.join(ROOT, 'storage', 'yacht-price-approved');
const REQUIRED_PHRASE = 'publish reviewed prices';

function latestCandidate() {
  if (!fs.existsSync(CANDIDATE_DIR)) return '';
  return fs.readdirSync(CANDIDATE_DIR)
    .filter((name) => name.endsWith('-candidate.json'))
    .map((name) => path.join(CANDIDATE_DIR, name))
    .sort()
    .at(-1) || '';
}

function parseArgs(argv) {
  const args = {
    candidate: '',
    review: false,
    approve: false,
    phrase: '',
    approver: 'Project Director',
    allowEstimatedDutyFree: false,
    allowWarnings: false,
  };

  for (const arg of argv.slice(2)) {
    if (arg === '--review') args.review = true;
    else if (arg === '--approve') args.approve = true;
    else if (arg === '--allow-estimated-duty-free') args.allowEstimatedDutyFree = true;
    else if (arg === '--allow-warnings') args.allowWarnings = true;
    else if (arg.startsWith('--candidate=')) args.candidate = arg.slice('--candidate='.length);
    else if (arg.startsWith('--phrase=')) args.phrase = arg.slice('--phrase='.length);
    else if (arg.startsWith('--approver=')) args.approver = arg.slice('--approver='.length);
    else if (arg === '--help' || arg === '-h') {
      console.log([
        'Usage:',
        '  node scripts/yacht_price_candidate_gate.cjs --review [--candidate=path]',
        '  node scripts/yacht_price_candidate_gate.cjs --approve --candidate=path --phrase="publish reviewed prices" [--allow-estimated-duty-free] [--allow-warnings]',
        '',
        'Approval writes an approved local catalog snapshot only. It does not update the Yacht UI.',
      ].join('\n'));
      process.exit(0);
    }
  }

  if (!args.review && !args.approve) args.review = true;
  return args;
}

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

function writeJson(file, data) {
  fs.mkdirSync(path.dirname(file), {recursive: true, mode: 0o775});
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`, {mode: 0o664});
}

function candidatePath(args) {
  return args.candidate || latestCandidate();
}

function compactReview(candidate, file) {
  return {
    ok: true,
    candidate: file,
    status: candidate.status || '',
    publish_allowed: !!candidate.publish_allowed,
    region: candidate.region || '',
    family: candidate.family || '',
    source_snapshot: candidate.source_snapshot || '',
    warnings: candidate.warnings || [],
    summary: candidate.summary || {},
    accepted_items: (candidate.accepted_items || []).map((item) => ({
      item_key: item.item_key,
      confidence: item.confidence,
      full_price_eur: item.full_price_eur,
      duty_free_price_eur: item.duty_free_price_eur,
      duty_free_basis: item.duty_free_basis,
      source_count: item.source_count,
    })),
    blocked_items: candidate.blocked_items || [],
  };
}

function approvalBlockers(candidate, args) {
  const blockers = [];
  const accepted = candidate.accepted_items || [];
  const blocked = candidate.blocked_items || [];
  const warnings = candidate.warnings || [];
  const estimatedDutyFree = accepted.filter((item) => item.duty_free_basis === 'estimated_discount');

  if (candidate.status !== 'pending_review') blockers.push('candidate_not_pending_review');
  if (!accepted.length) blockers.push('no_accepted_items');
  if (args.phrase !== REQUIRED_PHRASE) blockers.push('approval_phrase_missing');
  if (warnings.length && !args.allowWarnings) blockers.push('warnings_require_explicit_allowance');
  if (estimatedDutyFree.length && !args.allowEstimatedDutyFree) blockers.push('estimated_duty_free_requires_explicit_allowance');
  if (blocked.some((item) => item.final_full_price_eur > 0)) blockers.push('blocked_item_has_price');

  return blockers;
}

function approvedCatalog(candidate, file, args) {
  const approvedAt = new Date().toISOString();
  const accepted = candidate.accepted_items || [];
  const prices = {};

  for (const item of accepted) {
    prices[item.item_key] = {
      item_key: item.item_key,
      name: item.name || item.item_key,
      unit: item.unit || '',
      confidence: item.confidence,
      full_price_eur: item.full_price_eur,
      duty_free_price_eur: item.duty_free_price_eur,
      duty_free_basis: item.duty_free_basis,
      calculation_basis: item.calculation_basis,
      source_count: item.source_count,
      accepted_source_count: item.accepted_source_count,
      rejected_source_count: item.rejected_source_count,
      sources: item.sources || [],
    };
  }

  return {
    ok: true,
    type: 'yacht_price_approved_catalog',
    status: 'approved_local',
    approved_at: approvedAt,
    approver: args.approver,
    source_candidate: file,
    source_snapshot: candidate.source_snapshot || '',
    region: candidate.region || '',
    region_label: candidate.region_label || '',
    family: candidate.family || '',
    family_label: candidate.family_label || '',
    publish_target: candidate.publish_target || 'yacht_price_engine',
    ui_published: false,
    warnings: candidate.warnings || [],
    policy: candidate.policy || {},
    prices,
    blocked_items: candidate.blocked_items || [],
    approval_notes: {
      estimated_duty_free_allowed: !!args.allowEstimatedDutyFree,
      warnings_allowed: !!args.allowWarnings,
      phrase_required: REQUIRED_PHRASE,
    },
  };
}

const args = parseArgs(process.argv);
const file = candidatePath(args);

if (!file || !fs.existsSync(file)) {
  console.error(JSON.stringify({ok: false, error: 'candidate_not_found'}, null, 2));
  process.exit(1);
}

const candidate = readJson(file);

if (args.review && !args.approve) {
  console.log(JSON.stringify(compactReview(candidate, file), null, 2));
  process.exit(0);
}

const blockers = approvalBlockers(candidate, args);
if (blockers.length) {
  console.log(JSON.stringify({
    ok: false,
    error: 'approval_blocked',
    blockers,
    review: compactReview(candidate, file),
  }, null, 2));
  process.exit(2);
}

const approved = approvedCatalog(candidate, file, args);
const stamp = approved.approved_at.replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
const approvedFile = path.join(APPROVED_DIR, `${stamp}-${approved.region}-${approved.family}-approved.json`);
const activeFile = path.join(APPROVED_DIR, `active-${approved.region}-${approved.family}.json`);

writeJson(approvedFile, approved);
writeJson(activeFile, Object.assign({}, approved, {
  active_catalog: true,
  active_source: approvedFile,
}));

console.log(JSON.stringify({
  ok: true,
  approved: approvedFile,
  active: activeFile,
  accepted_items: Object.keys(approved.prices).length,
  blocked_items: approved.blocked_items.length,
  ui_published: false,
}, null, 2));
