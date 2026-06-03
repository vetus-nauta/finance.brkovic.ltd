#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const STORAGE_DIR = path.join(ROOT, 'storage', 'yacht-price-catalog');

function latestSnapshot() {
  if (!fs.existsSync(STORAGE_DIR)) return '';
  const files = fs.readdirSync(STORAGE_DIR)
    .filter((name) => name.endsWith('-node.json') && name !== 'ai-refresh-state-node.json')
    .map((name) => path.join(STORAGE_DIR, name))
    .sort();
  return files[files.length - 1] || '';
}

const file = process.argv[2] || latestSnapshot();
if (!file) {
  console.error('No snapshot file found.');
  process.exit(1);
}

const snapshot = JSON.parse(fs.readFileSync(file, 'utf8'));
const items = snapshot.computed?.items || [];

const summary = {
  file,
  generated_at: snapshot.generated_at || '',
  region: snapshot.region || '',
  family: snapshot.family || '',
  model: snapshot.model || '',
  publish_status: snapshot.publish_status || '',
  warnings: snapshot.observations?.warnings || [],
  items: items.map((item) => ({
    item_key: item.item_key,
    confidence: item.confidence,
    calculation_basis: item.calculation_basis,
    duty_free_basis: item.duty_free_basis,
    net_average_eur: item.net_average_eur,
    final_full_price_eur: item.final_full_price_eur,
    final_duty_free_price_eur: item.final_duty_free_price_eur,
    source_count: item.source_count,
    accepted_source_count: item.accepted_source_count,
    rejected_source_count: item.rejected_source_count,
    sources: (item.sources || []).map((source) => ({
      title: source.title,
      price_basis: source.price_basis,
      normalized_net_eur: source.normalized_net_eur,
      confidence: source.confidence,
      url: source.url,
    })),
  })),
};

console.log(JSON.stringify(summary, null, 2));
