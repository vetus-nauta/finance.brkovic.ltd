# Yacht Price Approval Gate - 2026-06-03

Status: local approval gate implemented; active storage catalog approved; UI not published.

## Purpose

AI price snapshots must not move into the active Yacht price layer without review.

The approval gate enforces:

- candidate review before approval;
- required approval phrase;
- explicit allowance for warnings;
- explicit allowance for estimated duty-free prices;
- blocked items remain blocked;
- approved catalog is stored separately;
- UI publication remains false.

## Implemented

Added:

- `scripts/yacht_price_candidate_gate.cjs`

Supported modes:

```bash
node scripts/yacht_price_candidate_gate.cjs --review --candidate=<candidate.json>
```

```bash
node scripts/yacht_price_candidate_gate.cjs --approve \
  --candidate=<candidate.json> \
  --phrase="publish reviewed prices" \
  --allow-warnings \
  --allow-estimated-duty-free \
  --approver="Project Director"
```

## Gate Test

Candidate reviewed:

- `storage/yacht-price-candidates/20260603T131613Z-adriatic_balkans-fuel-candidate.json`

Approval without required controls was blocked.

Blockers:

- `approval_phrase_missing`;
- `warnings_require_explicit_allowance`;
- `estimated_duty_free_requires_explicit_allowance`.

This confirms accidental approval is blocked.

## Approved Local Catalog

Approved file:

- `storage/yacht-price-approved/20260603T132215Z-adriatic_balkans-fuel-approved.json`

Active pointer:

- `storage/yacht-price-approved/active-adriatic_balkans-fuel.json`

Status:

- `approved_local`;
- `active_catalog: true`;
- `ui_published: false`.

## Approved Prices

| Item | Full | Duty-free | Confidence | Duty-free basis |
|---|---:|---:|---|---|
| `marine_diesel_liter` | EUR 2.24 | EUR 1.57 | medium | estimated discount |
| `gasoline_liter` | EUR 2.13 | EUR 1.49 | medium | estimated discount |

Blocked:

| Item | Reason |
|---|---|
| `duty_free_marine_diesel_liter` | confidence below medium; no public priced source found |

## Warnings Carried Forward

- Public sources did not expose visible duty-free marine diesel price per liter.
- Some sources are national averages or public-retail proxies.
- No bunker-supplier net quote was visible in accessible public pages.

## Product Meaning

The approved storage catalog is usable as a planning price layer after review.

It is not yet visible in the Yacht UI.

The next implementation step is a read-only API or frontend bridge that can load approved active prices into the Yacht price engine, with a visible source/version indicator and without changing printed/archived orders.

## Verification

Passed:

- `node --check scripts/yacht_price_candidate_gate.cjs`;
- candidate review command;
- approval-block test;
- approval command with required phrase and flags;
- active approved catalog readback;
- `git diff --check`.

Not done:

- UI publication;
- production deployment;
- direct supplier duty-free quote integration.
