# SPRINT-59R — Claudia Z Historical Chain Split

Date: 2026-08-12
Status: committed locally / post-commit evidence collected

## Director Intent

Restore Claudia Z history as an auditable operational chain instead of leaving it only as raw dictionary material.

The historical workspace must contain the full operational feed from the beginning through the last clean 2025 report. The main Claudia Z workspace must then receive the next operational segment before the already imported current rows, so the incoming/outgoing balance chain remains explainable.

## Source Of Truth

- Local database import tables: `v2_import_sources`, `v2_import_rows`
- Main workspace: `Claudia Z`
- Raw history workspace: `Claudia Z Archive Raw History`
- Existing parser behavior in `app/v2/Repository.php`
- Dry-run tool: `scripts/v2_claudia_z_history_split_dry_run.php`
- Apply tool: `scripts/v2_claudia_z_history_chain_apply.php`

## Boundary Decision For Dry Run

Default boundary: `2025-12-31`.

Observed clean chain:

- Last historical source report: `11.12.25.xlsx`
- First current-prepend source report: `20.01.26.xlsx`

This matches the product rule: history ends with the last clean 2025 report; the next report segment starts Claudia Z current history before the existing operational feed.

## Dry-Run Evidence

Artifact:

`storage/imports/claudia-z-history-split/dry-run-20260812-202653.json`

Summary:

- Archive history destination:
  - sources: 49
  - candidate entries: 1711
  - net entries after duplicate/already-linked filtering: 1237
  - already-linked raw rows: 17
  - date range: `2022-02-07` to `2025-12-11`
  - net cash income / expense: `924748.50 / 922480.27`
  - net card expense: `11986.71`
- Claudia Z current-prepend destination:
  - sources: 8
  - candidate entries: 144
  - net entries after duplicate/already-linked filtering: 24
  - already-linked raw rows: 0
  - date range: `2026-01-20` to `2026-06-15`
  - net cash income / expense: `12700.00 / 17845.00`
  - net cash effect: `-5145.00`
- Manual review destination:
  - sources: 0

## Important Findings

- Old clean report files are cumulative in places. They cannot be bulk-inserted as whole files.
- Migration must insert only net-new rows after duplicate filtering by date, flow, sign, amount, and normalized description.
- The current Claudia Z cash balance is the operational truth and must not change.
- The current flow opening balance must become a bridge value after adding January-April 2026 rows.
- Some raw rows contain invalid Excel dates and must stay excluded.
- Some raw rows remain unrecognized and must not block the chain migration, but must remain visible for later review.
- `15.06.2026.xlsx` must be treated as `2026-06-15`, not `2020-06-15`. The dry-run tool uses strict filename parsing plus `_date_context` fallback.

## Commit Evidence

Preview artifact:

`storage/imports/claudia-z-history-split/apply-preview-20260812-203955.json`

Commit artifact:

`storage/imports/claudia-z-history-split/apply-commit-20260812-204116.json`

Committed locally:

- Archive History entries created: `1237`
- Claudia Z current-prepend entries created: `24`
- Current-prepend cash net: `-5145.00`
- Claudia Z cash opening bridge: `2870.00 -> 8015.00`
- Expected current cash after commit: `3913.00`
- Actual current cash after commit: `3913.00`

Post-commit dry-run:

`storage/imports/claudia-z-history-split/dry-run-20260812-204133.json`

Post-commit result:

- Archive History net candidates: `0`
- Claudia Z current-prepend net candidates: `0`
- Re-run is idempotent at candidate level.

## Acceptance Criteria

- No DB writes occur during dry-run.
- Commit script must require explicit `--commit`.
- Commit must be idempotent:
  - re-run must not duplicate entries;
  - existing May/June rows must remain unchanged;
  - source row links must be preserved where rows are inserted.
- Archive History receives only net-new historical operational rows through `2025-12-11`.
- Claudia Z receives only net-new current-prepend rows before the existing May/June operational rows.
- Invalid-date rows are excluded and recorded in the artifact.
- Unrecognized rows remain in raw import review, not hidden.
- A post-commit reconciliation artifact must compare:
  - entry counts before/after;
  - source row links;
  - cash/card net movement;
  - first/last dates;
  - duplicate suspects.

## Explicit Non-Goals

- Do not change financial formulas.
- Do not change parser semantics in production.
- Do not create artificial income/expense rows to force balances.
- Do not delete old raw rows.
- Do not rewrite existing Claudia Z May-August records.

## Agent Notes

- Product Director: boundary and user-facing chain logic.
- Data Migration Agent: dry-run and idempotent commit tooling.
- Financial Logic Reviewer: opening/carry rows are control points, not operational income.
- QA/Audit Agent: artifacts, duplicate checks, post-commit reconciliation.

## Director Closure

SPRINT-59R is accepted for local state because the migration preserved the current Claudia Z cash truth while making the historical feed available for future report reconstruction.

Remaining follow-up:

- Review invalid-date and unrecognized raw rows later as a separate cleanup task.
- Decide how Archive History should display historical operational reports in the UI.
