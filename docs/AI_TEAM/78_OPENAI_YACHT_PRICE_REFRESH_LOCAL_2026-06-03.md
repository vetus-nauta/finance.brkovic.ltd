# OpenAI Yacht Price Refresh - 2026-06-03

Status: local background scaffold complete.

## Director Decision

OpenAI must not be exposed as a direct user-facing price button.

For Yacht provisioning, OpenAI is a background normalization worker:

- food prices: refresh every 90 days;
- fuel prices: refresh every 30 days;
- no refresh from casual UI taps;
- no automatic overwrite of printed orders;
- no automatic overwrite of FinDesk accounting data;
- every run produces a snapshot and state record.

## Key Search

Checked locally:

- `app/config.local.php`;
- `app/config.local.example.php`;
- `app/ai.php`;
- `public/api.php`;
- project references to `OPENAI`, `OpenAI`, `openai`, `OPENAI_API_KEY`, `ai_analysis_run`, `responses`.

Result:

- no active OpenAI API key found in the project files;
- current shell has no `OPENAI_API_KEY`;
- no OpenAI external call found in the current AI module;
- existing `app/ai.php` is local FinDesk analysis only;
- no secret value was printed into reports.

## Implemented Locally

- Added server-side provider:
  - `app/openai_provider.php`.
- Added config template:
  - `app/config.local.example.php`.
- Added CLI-only worker:
  - `scripts/yacht_price_ai_refresh.php`.

The worker default mode is `dry-run`.

It makes no API call unless `--run` is passed and OpenAI is explicitly enabled in config.

## Safe Config Pattern

Preferred production setup:

```php
'openai' => [
    'enabled' => true,
    'api_key' => '',
    'model' => 'gpt-5.4-mini',
    'endpoint' => 'https://api.openai.com/v1',
    'timeout_seconds' => 90,
    'max_output_tokens' => 1800,
    'web_search_enabled' => true,
    'web_search_tool' => 'web_search',
],
```

Set the real key in the server environment:

```bash
OPENAI_API_KEY=...
```

Do not commit the key into Git.

## Commands

Dry run, no API calls:

```bash
php scripts/yacht_price_ai_refresh.php
```

Fuel only, monthly run:

```bash
php scripts/yacht_price_ai_refresh.php --family=fuel --run
```

Food only, quarterly run:

```bash
php scripts/yacht_price_ai_refresh.php --family=food --run
```

One region only:

```bash
php scripts/yacht_price_ai_refresh.php --family=fuel --region=adriatic_balkans --run
```

Forced run:

```bash
php scripts/yacht_price_ai_refresh.php --family=fuel --force --run
```

## Cron Draft

Fuel, monthly:

```cron
15 3 1 * * cd /path/to/finance.brkovic.ltd && /usr/bin/php scripts/yacht_price_ai_refresh.php --family=fuel --run >> storage/logs/yacht-price-ai.log 2>&1
```

Food, every 3 months:

```cron
45 3 1 */3 * cd /path/to/finance.brkovic.ltd && /usr/bin/php scripts/yacht_price_ai_refresh.php --family=food --run >> storage/logs/yacht-price-ai.log 2>&1
```

## Storage

Snapshots:

- `storage/yacht-price-catalog/YYYYMMDD-HHMMSS-region-family.json`

State:

- `storage/yacht-price-catalog/ai-refresh-state.json`

The script currently writes snapshots only. It does not publish prices into the user-facing Yacht order catalog.

## Why Responses API

OpenAI official docs describe the Responses API as the endpoint for model responses, structured JSON output and built-in tools. The web search guide describes web search as a way to let models access up-to-date internet information with citations.

This matches the intended background workflow:

- search current public sources;
- normalize into strict JSON;
- store a reviewable snapshot;
- keep final publication under product control.

## Guardrails

- CLI-only script.
- No web UI route.
- OpenAI disabled by default.
- Key is not committed.
- `dry-run` by default.
- `--run` required for API calls.
- Food and fuel have separate intervals.
- Snapshot does not overwrite existing orders.
- Printed/archived orders stay immutable.
- Missing key or disabled config returns a controlled error.

## Open Items

- Run PHP syntax check where PHP CLI is available.
- Connect approved real source list per region.
- Add source confidence and outlier rejection before catalog publication.
- Add manual director approval step before replacing the active frontend catalog.
- Add backend tables if snapshots should move from JSON files into DB.
- Add production cron only after release audit.

## Local Verification

Passed:

- no active OpenAI key found in project files;
- current shell reports `OPENAI_API_KEY` absent;
- no hardcoded `sk-...` key added;
- `git diff --check` passed for the new files;
- OpenAI provider does not expose secret values in status output;
- worker defaults to `dry_run`.

Not run:

- `php -l`, because PHP CLI is not available in the current shell;
- real OpenAI API call;
- production cron;
- production deployment.
