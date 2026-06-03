# OpenAI Key Smoke Pass - 2026-06-03

Status: local OpenAI key installed and API smoke passed.

## Key Status

Terminal install completed through:

```bash
sh scripts/install_openai_key.sh
```

Status check:

```text
OpenAI key file: present (-rw-------)
```

Key storage:

- `storage/secrets/openai_api_key`;
- permissions: `600`;
- ignored by Git through `storage/`.

The key value was not printed, copied into docs, or committed.

## OpenAI API Smoke

Passed:

- `/v1/models` authentication check;
- account can see `gpt-5.4-mini`;
- Responses API structured-output smoke.

Responses smoke result:

```json
{
  "ok": true,
  "model": "gpt-5.4-mini-2026-03-17",
  "status": "completed",
  "output": "{\"status\":\"ok\",\"service\":\"openai\"}"
}
```

## What Was Not Run

- Yacht price PHP worker was not run because PHP CLI is not available in the current shell.
- No web search tool call was made.
- No Yacht catalog prices were published.
- No production deployment was made.

## Next Safe Step

Find or provide PHP CLI path, then run:

```bash
php scripts/yacht_price_ai_refresh.php
```

Expected first run:

- `dry_run`;
- no API calls;
- planned jobs only.

Then run one controlled real job:

```bash
php scripts/yacht_price_ai_refresh.php --family=fuel --region=adriatic_balkans --run
```

This should create a snapshot in:

```text
storage/yacht-price-catalog/
```

The snapshot must be reviewed before any catalog publication.
