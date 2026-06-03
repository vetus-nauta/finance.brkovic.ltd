# Yacht Fuel Price Candidate - 2026-06-03

Status: local candidate created; not published.

## Scope

Region:

- `adriatic_balkans`

Family:

- `fuel`

Source snapshot:

- `storage/yacht-price-catalog/20260603T131456Z-adriatic_balkans-fuel-node.json`

Candidate:

- `storage/yacht-price-candidates/20260603T131613Z-adriatic_balkans-fuel-candidate.json`

## Implemented

Added candidate builder:

- `scripts/yacht_price_candidate_from_snapshot.cjs`

Purpose:

- read an AI price snapshot;
- accept only items with sufficient confidence and price;
- block weak/missing items;
- create a review-only candidate;
- keep `publish_allowed: false`.

## Full Fuel Run Result

OpenAI worker run:

```bash
node scripts/yacht_price_ai_refresh.cjs --family=fuel --region=adriatic_balkans --force --run
```

Result:

- `ok: true`;
- item count: `3`;
- low/missing confidence items: `1`;
- publish status: `review_required`.

## Candidate Result

Accepted:

| Item | Confidence | Full | Duty-free | Basis |
|---|---:|---:|---:|---|
| `marine_diesel_liter` | medium | EUR 2.24 | EUR 1.57 | tax-included proxy + service |
| `gasoline_liter` | medium | EUR 2.13 | EUR 1.49 | tax-included proxy + service |

Blocked:

| Item | Reason |
|---|---|
| `duty_free_marine_diesel_liter` | confidence below medium; no public priced source found |

## Important Warnings

- Public sources did not expose a visible duty-free marine diesel price per liter.
- Some sources are national averages or public-retail proxies, not marina-specific dock prices.
- No bunker-supplier net quote was visible in accessible public pages.
- Duty-free values for accepted items are estimates, not explicit supplier quotes.

## Product Meaning

This is enough for a planning estimate.

This is not enough for automatic publication as a final yacht supply/fuel price.

Publication must wait for a review gate, because:

- source basis is mixed;
- duty-free is estimated;
- marina/bunker supplier net prices are not yet confirmed.

## Commands

Create candidate from a snapshot:

```bash
node scripts/yacht_price_candidate_from_snapshot.cjs --snapshot=storage/yacht-price-catalog/20260603T131456Z-adriatic_balkans-fuel-node.json
```

Review latest snapshot:

```bash
node scripts/yacht_price_snapshot_review.cjs
```

## Verification

Passed:

- full `adriatic_balkans/fuel` OpenAI run;
- candidate creation;
- candidate summary review;
- `node --check` for candidate script;
- `git diff --check`;
- no OpenAI key found in new scripts or candidate storage.

Not done:

- UI publication;
- admin approval screen;
- supplier-direct duty-free quote integration;
- production deployment.
