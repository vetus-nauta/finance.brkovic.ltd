#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const STORAGE_DIR = path.join(ROOT, 'storage', 'yacht-price-catalog');
const STATE_PATH = path.join(STORAGE_DIR, 'ai-refresh-state-node.json');
const KEY_FILE = path.join(ROOT, 'storage', 'secrets', 'openai_api_key');
const MODEL = process.env.OPENAI_MODEL || 'gpt-5.4-mini';
const API_BASE = (process.env.OPENAI_API_BASE || 'https://api.openai.com/v1').replace(/\/+$/, '');

const REGIONS = {
  europe_basic: {
    label: 'Europe baseline',
    search_hint: 'EU supermarket wholesale and marina provisioning prices',
    policy: {tax_rate: 0.2, logistics_rate: 0.06, markup_rate: 0.18, duty_free_discount: {food: 0.25, fuel: 0.28}},
  },
  adriatic_balkans: {
    label: 'Adriatic / Balkans',
    search_hint: 'Montenegro Croatia Adriatic marina provisioning marine diesel prices',
    policy: {tax_rate: 0.21, logistics_rate: 0.08, markup_rate: 0.22, duty_free_discount: {food: 0.25, fuel: 0.3}},
  },
  mediterranean_west: {
    label: 'Western Mediterranean',
    search_hint: 'Spain France Italy Balearics yacht provisioning and marina fuel prices',
    policy: {tax_rate: 0.21, logistics_rate: 0.09, markup_rate: 0.24, duty_free_discount: {food: 0.25, fuel: 0.29}},
  },
  usa_coastal: {
    label: 'USA coastal states',
    search_hint: 'US coastal wholesale grocery and marina fuel dock prices',
    policy: {tax_rate: 0.08, logistics_rate: 0.09, markup_rate: 0.2, duty_free_discount: {food: 0.22, fuel: 0.25}},
  },
  asia_marina: {
    label: 'Asia marina hubs',
    search_hint: 'Singapore Thailand Malaysia marina provisioning and diesel prices',
    policy: {tax_rate: 0.09, logistics_rate: 0.12, markup_rate: 0.25, duty_free_discount: {food: 0.24, fuel: 0.28}},
  },
  caribbean_islands: {
    label: 'Caribbean islands',
    search_hint: 'Caribbean islands yacht provisioning and marina fuel prices',
    policy: {tax_rate: 0.12, logistics_rate: 0.18, markup_rate: 0.3, duty_free_discount: {food: 0.27, fuel: 0.3}},
  },
};

const FAMILIES = {
  food: {
    label: 'Food provisioning',
    interval_days: 90,
    source_goal: 'wholesale, supermarket, distributor, provisioning company or transparent online grocery source',
    items: [
      {item_key: 'bottled_water_pack', name: 'Bottled still water 1.5 L, pack of 6', unit: 'pack_6'},
      {item_key: 'soft_drinks_pack', name: 'Mixed soft drinks 0.5 L, pack of 24', unit: 'pack_24'},
      {item_key: 'coffee_tea_sugar', name: 'Coffee, tea and sugar weekly yacht starter bundle', unit: 'bundle'},
      {item_key: 'basic_dry_food_package', name: 'Basic dry food package for yacht provisioning', unit: 'bundle'},
      {item_key: 'fresh_fruit_vegetables_basket', name: 'Fresh fruit and vegetables basket', unit: 'basket'},
      {item_key: 'cleaning_supplies', name: 'Yacht cleaning supplies starter bundle', unit: 'bundle'},
      {item_key: 'paper_towels_napkins', name: 'Paper towels and napkins starter bundle', unit: 'bundle'},
    ],
  },
  fuel: {
    label: 'Marine fuel',
    interval_days: 30,
    source_goal: 'marina, bunker supplier, official pump price, fuel dock, port authority or fuel price service',
    items: [
      {item_key: 'marine_diesel_liter', name: 'Marine diesel', unit: 'liter'},
      {item_key: 'duty_free_marine_diesel_liter', name: 'Duty-free marine diesel for yachts', unit: 'liter'},
      {item_key: 'gasoline_liter', name: 'Gasoline / petrol at marina or coastal fuel dock', unit: 'liter'},
    ],
  },
};

function parseArgs(argv) {
  const args = {
    family: 'due',
    region: 'all',
    run: false,
    force: false,
    limitItems: 0,
    noWebSearch: false,
  };

  for (const arg of argv.slice(2)) {
    if (arg === '--run') args.run = true;
    else if (arg === '--force') args.force = true;
    else if (arg === '--no-web-search') args.noWebSearch = true;
    else if (arg.startsWith('--family=')) args.family = arg.slice('--family='.length);
    else if (arg.startsWith('--region=')) args.region = arg.slice('--region='.length);
    else if (arg.startsWith('--limit-items=')) args.limitItems = Math.max(0, Number.parseInt(arg.slice('--limit-items='.length), 10) || 0);
    else if (arg === '--help' || arg === '-h') {
      console.log([
        'Usage:',
        '  node scripts/yacht_price_ai_refresh.cjs [--family=food|fuel|all|due] [--region=key|all] [--run] [--force] [--limit-items=N] [--no-web-search]',
        '',
        'Default is dry-run. No OpenAI call is made without --run.',
      ].join('\n'));
      process.exit(0);
    }
  }

  return args;
}

function readJson(file, fallback = {}) {
  try {
    return JSON.parse(fs.readFileSync(file, 'utf8'));
  } catch (_error) {
    return fallback;
  }
}

function writeJson(file, data) {
  fs.mkdirSync(path.dirname(file), {recursive: true, mode: 0o775});
  fs.writeFileSync(file, `${JSON.stringify(data, null, 2)}\n`, {mode: 0o664});
}

function readApiKey() {
  const envKey = String(process.env.OPENAI_API_KEY || '').trim();
  if (envKey) return envKey;
  try {
    return fs.readFileSync(KEY_FILE, 'utf8').trim();
  } catch (_error) {
    return '';
  }
}

function daysSince(iso) {
  if (!iso) return null;
  const time = Date.parse(iso);
  if (!Number.isFinite(time)) return null;
  return Math.floor((Date.now() - time) / 86400000);
}

function selectedRegions(regionArg) {
  if (regionArg === 'all') return Object.entries(REGIONS);
  return REGIONS[regionArg] ? [[regionArg, REGIONS[regionArg]]] : [];
}

function selectedFamilies(familyArg) {
  if (familyArg === 'all' || familyArg === 'due') return Object.entries(FAMILIES);
  return FAMILIES[familyArg] ? [[familyArg, FAMILIES[familyArg]]] : [];
}

function buildPlan(args, state) {
  const jobs = [];
  for (const [region, regionConfig] of selectedRegions(args.region)) {
    for (const [family, familyConfig] of selectedFamilies(args.family)) {
      const key = `${region}:${family}`;
      const lastSuccessAt = state[key]?.last_success_at || '';
      const age = daysSince(lastSuccessAt);
      const due = args.force || age === null || age >= familyConfig.interval_days;
      if (args.family === 'due' && !due) continue;

      const items = args.limitItems > 0 ? familyConfig.items.slice(0, args.limitItems) : familyConfig.items;
      jobs.push({
        region,
        region_label: regionConfig.label,
        family,
        family_label: familyConfig.label,
        interval_days: familyConfig.interval_days,
        last_success_at: lastSuccessAt || null,
        days_since_success: age,
        due,
        items,
      });
    }
  }
  return jobs;
}

function observationSchema() {
  return {
    type: 'object',
    additionalProperties: false,
    required: ['region', 'family', 'currency', 'items', 'warnings'],
    properties: {
      region: {type: 'string'},
      family: {type: 'string'},
      currency: {type: 'string'},
      items: {
        type: 'array',
        items: {
          type: 'object',
          additionalProperties: false,
          required: ['item_key', 'name', 'unit', 'sources', 'notes'],
          properties: {
            item_key: {type: 'string'},
            name: {type: 'string'},
            unit: {type: 'string'},
            sources: {
              type: 'array',
              items: {
                type: 'object',
                additionalProperties: false,
                required: ['title', 'url', 'source_type', 'price_basis', 'observed_price', 'observed_currency', 'observed_unit', 'normalized_net_eur', 'date_seen', 'confidence', 'notes'],
                properties: {
                  title: {type: 'string'},
                  url: {type: 'string'},
                  source_type: {type: 'string'},
                  price_basis: {type: 'string'},
                  observed_price: {type: 'number'},
                  observed_currency: {type: 'string'},
                  observed_unit: {type: 'string'},
                  normalized_net_eur: {type: 'number'},
                  date_seen: {type: 'string'},
                  confidence: {type: 'string'},
                  notes: {type: 'string'},
                },
              },
            },
            notes: {type: 'string'},
          },
        },
      },
      warnings: {
        type: 'array',
        items: {type: 'string'},
      },
    },
  };
}

function buildPrompt(job) {
  const region = REGIONS[job.region];
  const family = FAMILIES[job.family];
  const items = job.items.map((item) => `- ${item.item_key}: ${item.name}; target unit: ${item.unit}`).join('\n');

  return [
    'Refresh yacht provisioning source observations.',
    `Region: ${region.label} (${job.region}).`,
    `Search hint: ${region.search_hint}.`,
    `Family: ${family.label} (${job.family}).`,
    `Preferred source types: ${family.source_goal}.`,
    '',
    'Items:',
    items,
    '',
    'Rules:',
    '- Return current public source observations where available.',
    '- Prefer at least 2 sources per item when possible.',
    '- Normalize each observation to EUR per target unit in normalized_net_eur.',
    '- Set price_basis exactly as one of: net_wholesale, supplier_net, bunker_net, duty_free_net, official_pump_tax_included, marina_pump_tax_included, public_retail_tax_included, retail_proxy, context_only.',
    '- If the source is public retail or pump price, do not pretend it is net. Use a tax_included/proxy price_basis and explain it in notes.',
    '- Do not compute final retail, markup or tax. Our code will do that based on price_basis.',
    '- Do not invent URLs or prices. If source confidence is weak, return fewer sources and add a warning.',
    '- For duty-free fuel, use only sources that explicitly support duty-free, bonded, tax-free, yacht bunker, or export fuel pricing.',
    '- Use today as date_seen when the page has no explicit date but the source is live.',
    '',
    'Return only structured JSON matching the schema.',
  ].join('\n');
}

function buildPayload(job, args) {
  const payload = {
    model: MODEL,
    instructions: 'You are a background price-source worker for yacht provisioning. You collect source observations only. You do not publish prices and do not decide final customer prices.',
    input: buildPrompt(job),
    max_output_tokens: 3500,
    text: {
      format: {
        type: 'json_schema',
        name: 'yacht_price_source_observations',
        strict: true,
        schema: observationSchema(),
      },
    },
  };

  if (!args.noWebSearch) {
    payload.tools = [{type: 'web_search'}];
    payload.tool_choice = 'auto';
  }

  return payload;
}

function median(values) {
  if (!values.length) return 0;
  const sorted = [...values].sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
}

function average(values) {
  if (!values.length) return 0;
  return values.reduce((sum, value) => sum + value, 0) / values.length;
}

function roundMoney(value) {
  return Math.max(0, Math.round(value * 100) / 100);
}

function normalizeSource(source) {
  const value = Number(source?.normalized_net_eur || 0);
  if (!Number.isFinite(value) || value <= 0) return null;
  return {
    title: String(source.title || '').slice(0, 240),
    url: String(source.url || '').slice(0, 500),
    source_type: String(source.source_type || '').slice(0, 80),
    price_basis: String(source.price_basis || 'retail_proxy').slice(0, 80),
    observed_price: Number(source.observed_price || 0),
    observed_currency: String(source.observed_currency || '').slice(0, 16),
    observed_unit: String(source.observed_unit || '').slice(0, 80),
    normalized_net_eur: roundMoney(value),
    date_seen: String(source.date_seen || '').slice(0, 40),
    confidence: String(source.confidence || 'low').slice(0, 40),
    notes: String(source.notes || '').slice(0, 500),
  };
}

function rejectOutliers(sources) {
  const values = sources.map((source) => source.normalized_net_eur);
  if (values.length < 3) {
    return {accepted: sources, rejected: []};
  }

  const med = median(values);
  const accepted = [];
  const rejected = [];
  for (const source of sources) {
    const value = source.normalized_net_eur;
    const keep = value >= med * 0.55 && value <= med * 1.8;
    (keep ? accepted : rejected).push(source);
  }
  return {accepted: accepted.length ? accepted : sources, rejected};
}

function sourceConfidenceLevel(source) {
  const value = String(source.confidence || '').toLowerCase();
  if (value.includes('high')) return 3;
  if (value.includes('medium')) return 2;
  if (value.includes('low')) return 1;
  return 1;
}

function confidenceFor(sources, rejected) {
  if (!sources.length) return 'missing';
  const levels = sources.map(sourceConfidenceLevel);
  const minLevel = Math.min(...levels);
  const hasRejected = rejected.length > 0;

  if (sources.length >= 3 && minLevel >= 3 && !hasRejected) return 'high';
  if (sources.length >= 2 && minLevel >= 2) return 'medium';
  if (sources.length >= 3 && minLevel >= 1) return 'medium';
  if (sources.length === 1) return 'low';
  return 'missing';
}

function sourceBasis(source) {
  return String(source.price_basis || '').toLowerCase();
}

function isNetLikeSource(source) {
  return ['net_wholesale', 'supplier_net', 'bunker_net', 'duty_free_net'].includes(sourceBasis(source));
}

function isDutyFreeSource(source) {
  return sourceBasis(source) === 'duty_free_net';
}

function calculationBasisFor(sources) {
  const netLike = sources.filter(isNetLikeSource).length;
  const priced = sources.length;
  if (priced > 0 && netLike >= Math.ceil(priced / 2)) return 'net_plus_policy';
  return 'tax_included_proxy_plus_service';
}

function adjustedConfidence(baseConfidence, calculationBasis) {
  if (baseConfidence === 'high' && calculationBasis !== 'net_plus_policy') return 'medium';
  return baseConfidence;
}

function computeFinalPrices(observations, job) {
  const policy = REGIONS[job.region].policy;
  const family = job.family;
  const discount = policy.duty_free_discount[family] ?? 0.27;

  const items = (observations.items || []).map((item) => {
    const sources = (item.sources || []).map(normalizeSource).filter(Boolean);
    const filtered = rejectOutliers(sources);
    const netAverage = roundMoney(average(filtered.accepted.map((source) => source.normalized_net_eur)));
    const calculationBasis = calculationBasisFor(filtered.accepted);
    const full = netAverage > 0 && calculationBasis === 'net_plus_policy'
      ? roundMoney(netAverage * (1 + policy.tax_rate + policy.logistics_rate) * (1 + policy.markup_rate))
      : (netAverage > 0 ? roundMoney(netAverage * (1 + policy.logistics_rate) * (1 + policy.markup_rate)) : 0);
    const dutyFreeSources = filtered.accepted.filter(isDutyFreeSource);
    const dutyFreeAverage = roundMoney(average(dutyFreeSources.map((source) => source.normalized_net_eur)));
    const dutyFree = dutyFreeAverage > 0
      ? roundMoney(dutyFreeAverage * (1 + policy.logistics_rate) * (1 + policy.markup_rate))
      : ((family === 'food' || family === 'fuel') && full > 0 ? roundMoney(full * (1 - discount)) : 0);
    const baseConfidence = confidenceFor(filtered.accepted, filtered.rejected);

    return {
      item_key: String(item.item_key || ''),
      name: String(item.name || ''),
      unit: String(item.unit || ''),
      calculation_basis: calculationBasis,
      source_count: sources.length,
      accepted_source_count: filtered.accepted.length,
      rejected_source_count: filtered.rejected.length,
      net_average_eur: netAverage,
      final_full_price_eur: full,
      final_duty_free_price_eur: dutyFree,
      duty_free_basis: dutyFreeAverage > 0 ? 'explicit_duty_free_source' : 'estimated_discount',
      confidence: adjustedConfidence(baseConfidence, calculationBasis),
      sources: filtered.accepted,
      rejected_sources: filtered.rejected,
      notes: String(item.notes || ''),
    };
  });

  return {
    policy: {
      tax_rate: policy.tax_rate,
      logistics_rate: policy.logistics_rate,
      markup_rate: policy.markup_rate,
      duty_free_discount: discount,
    },
    items,
  };
}

function extractText(response) {
  const chunks = [];
  for (const output of response.output || []) {
    for (const content of output.content || []) {
      if (typeof content.text === 'string') chunks.push(content.text);
    }
  }
  if (!chunks.length && typeof response.output_text === 'string') chunks.push(response.output_text);
  return chunks.join('\n').trim();
}

async function callOpenAI(apiKey, payload) {
  const response = await fetch(`${API_BASE}/responses`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });
  const text = await response.text();
  let json;
  try {
    json = JSON.parse(text);
  } catch (_error) {
    throw new Error(`bad_json_response status=${response.status} body=${text.slice(0, 800)}`);
  }
  if (!response.ok) {
    throw new Error(`openai_http_${response.status}: ${json.error?.message || text.slice(0, 800)}`);
  }
  return json;
}

async function runJob(apiKey, job, args) {
  const response = await callOpenAI(apiKey, buildPayload(job, args));
  const text = extractText(response);
  let observations;
  try {
    observations = JSON.parse(text);
  } catch (_error) {
    throw new Error(`model_output_json_failed response_id=${response.id || ''} text=${text.slice(0, 800)}`);
  }
  const computed = computeFinalPrices(observations, job);
  return {
    response_id: response.id || '',
    model: response.model || MODEL,
    observations,
    computed,
  };
}

async function main() {
  const args = parseArgs(process.argv);
  const state = readJson(STATE_PATH, {});
  const jobs = buildPlan(args, state);
  const keyPresent = Boolean(readApiKey());

  if (!jobs.length) {
    console.log(JSON.stringify({ok: true, mode: args.run ? 'run' : 'dry_run', message: 'no_due_jobs', key_present: keyPresent, jobs: []}, null, 2));
    return;
  }

  if (!args.run) {
    console.log(JSON.stringify({ok: true, mode: 'dry_run', message: 'no_api_calls_made', model: MODEL, key_present: keyPresent, jobs}, null, 2));
    return;
  }

  const apiKey = readApiKey();
  if (!apiKey) {
    console.log(JSON.stringify({ok: false, error: 'openai_key_missing'}, null, 2));
    process.exitCode = 1;
    return;
  }

  const runAt = new Date().toISOString();
  const results = [];
  for (const job of jobs) {
    const key = `${job.region}:${job.family}`;
    if (!job.due && !args.force) {
      results.push({ok: true, region: job.region, family: job.family, skipped: 'not_due'});
      continue;
    }

    try {
      const result = await runJob(apiKey, job, args);
      const stamp = runAt.replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');
      const file = path.join(STORAGE_DIR, `${stamp}-${job.region}-${job.family}-node.json`);
      const snapshot = {
        ok: true,
        generated_at: runAt,
        worker: 'node',
        region: job.region,
        region_label: job.region_label,
        family: job.family,
        family_label: job.family_label,
        model: result.model,
        response_id: result.response_id,
        observations: result.observations,
        computed: result.computed,
        publish_status: 'review_required',
      };
      writeJson(file, snapshot);
      state[key] = {
        last_success_at: runAt,
        last_snapshot: file,
        last_response_id: result.response_id,
      };
      results.push({
        ok: true,
        region: job.region,
        family: job.family,
        snapshot: file,
        item_count: result.computed.items.length,
        low_confidence_items: result.computed.items.filter((item) => item.confidence === 'low' || item.confidence === 'missing').length,
      });
    } catch (error) {
      state[key] = Object.assign({}, state[key] || {}, {
        last_error_at: runAt,
        last_error: error.message,
      });
      results.push({ok: false, region: job.region, family: job.family, error: error.message});
    }
  }
  writeJson(STATE_PATH, state);

  const ok = results.every((result) => result.ok);
  console.log(JSON.stringify({ok, mode: 'run', model: MODEL, results}, null, 2));
  if (!ok) process.exitCode = 1;
}

main().catch((error) => {
  console.error(JSON.stringify({ok: false, error: error.message}, null, 2));
  process.exit(1);
});
