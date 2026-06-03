# Yacht AI Price Cycle - 2026-06-03

Status: local AI price cycle implemented and tested.

## Director Decision

The AI must not directly publish prices into the Yacht UI.

The correct model:

```text
AI finds source observations
        ↓
Code normalizes and audits sources
        ↓
Code computes final full / duty-free prices
        ↓
Snapshot is stored for review
        ↓
Human/product gate decides whether to publish
```

This keeps traffic controlled, prevents casual UI refresh abuse, and avoids AI silently changing business-critical numbers.

## Implemented

Added Node worker because PHP CLI is unavailable in the current shell:

- `scripts/yacht_price_ai_refresh.cjs`

Added snapshot review helper:

- `scripts/yacht_price_snapshot_review.cjs`

The old PHP worker remains available for environments where PHP CLI exists:

- `scripts/yacht_price_ai_refresh.php`

## What The AI Now Knows

The worker instructs AI to collect source observations only.

Each source must declare:

- title;
- URL;
- source type;
- `price_basis`;
- observed price;
- observed currency;
- observed unit;
- normalized EUR value;
- date seen;
- confidence;
- notes.

Allowed `price_basis` values:

- `net_wholesale`;
- `supplier_net`;
- `bunker_net`;
- `duty_free_net`;
- `official_pump_tax_included`;
- `marina_pump_tax_included`;
- `public_retail_tax_included`;
- `retail_proxy`;
- `context_only`.

This prevents public retail or pump prices from being treated as hidden net/wholesale prices.

## Deterministic Price Logic

For net-like sources:

```text
final_full = net_average * (1 + tax + logistics) * (1 + markup)
```

For tax-included retail/pump proxies:

```text
final_full = observed_average * (1 + logistics) * (1 + markup)
```

Duty-free:

- if explicit duty-free source exists, calculate from that source;
- otherwise use region discount estimate and mark it as `estimated_discount`.

Outlier control:

- values are filtered against median range;
- rejected sources remain in the snapshot;
- confidence accounts for both source count and source-level confidence.

## Commands

Dry run, no API calls:

```bash
node scripts/yacht_price_ai_refresh.cjs --family=fuel --region=adriatic_balkans --limit-items=1
```

Real controlled run:

```bash
node scripts/yacht_price_ai_refresh.cjs --family=fuel --region=adriatic_balkans --limit-items=1 --run
```

Review latest snapshot:

```bash
node scripts/yacht_price_snapshot_review.cjs
```

Review specific snapshot:

```bash
node scripts/yacht_price_snapshot_review.cjs storage/yacht-price-catalog/<file>.json
```

## First Successful Snapshot

Latest reviewed snapshot:

- `storage/yacht-price-catalog/20260603T130305Z-adriatic_balkans-fuel-node.json`

Result:

- region: `adriatic_balkans`;
- family: `fuel`;
- item: `marine_diesel_liter`;
- model: `gpt-5.4-mini-2026-03-17`;
- publish status: `review_required`;
- source count: `3`;
- calculation basis: `tax_included_proxy_plus_service`;
- confidence: `medium`;
- net/observed average: `EUR 1.52`;
- final full price: `EUR 2.00`;
- estimated duty-free price: `EUR 1.40`;
- duty-free basis: `estimated_discount`.

Source types found:

- marina pump tax-included;
- public retail tax-included.

Warnings:

- public duty-free/bunker sources are limited;
- some sources are proxies, not strict net bunker prices.

## Guardrails Confirmed

- Key is read from `storage/secrets/openai_api_key`.
- Key was not printed.
- Key was not committed.
- Worker default is dry-run.
- `--run` is required for API calls.
- Snapshot is stored with `publish_status: review_required`.
- Active Yacht UI prices are not overwritten.
- FinDesk accounting is not touched.

## Verification

Passed:

- `node --check scripts/yacht_price_ai_refresh.cjs`;
- `node --check scripts/yacht_price_snapshot_review.cjs`;
- dry-run with key present and no API call;
- OpenAI real run for one fuel item;
- snapshot review helper;
- `git diff --check`;
- grep check found no OpenAI-style key in the new scripts or price snapshots.

Not done:

- full fuel family refresh;
- food refresh;
- all-region refresh;
- backend publication endpoint;
- UI catalog publication;
- production deployment.

## Next Sprint

1. Run full fuel family for `adriatic_balkans`.
2. Review all three fuel items.
3. Add publication candidate JSON separate from raw AI snapshots.
4. Add UI/admin gate for publishing selected values.
5. Run food family after source strategy is narrowed, because food has more noisy retail/provisioning sources.
