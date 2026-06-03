# OpenAI Key Terminal Install Ready - 2026-06-03

Status: terminal install path prepared; superseded by successful install.

## What Changed

- `app/openai_provider.php` now supports `api_key_file`.
- `app/config.local.php` is configured to enable OpenAI locally and read the key from:
  - `storage/secrets/openai_api_key`.
- `app/config.local.example.php` documents `api_key_file`.
- Added terminal installer:
  - `scripts/install_openai_key.sh`.

## Why This Pattern

The OpenAI key must not be pasted into chat, committed to Git, written into reports, or exposed in browser code.

The key is stored only in an ignored local secret file:

```text
storage/secrets/openai_api_key
```

The repository `.gitignore` already ignores:

- `app/config.local.php`;
- `storage/`.

## Terminal Commands

Install key:

```bash
sh scripts/install_openai_key.sh
```

The script prompts:

```text
OpenAI API key:
```

Input is hidden by terminal echo suppression.

Check status:

```bash
sh scripts/install_openai_key.sh --status
```

Remove key:

```bash
sh scripts/install_openai_key.sh --remove
```

## Current Status

Original local check before terminal install:

```text
OpenAI key file: absent
```

Meaning:

- OpenAI config was ready;
- key had not been installed through the terminal yet.

Current key status is tracked in:

- `docs/AI_TEAM/81_OPENAI_KEY_SMOKE_PASS_2026-06-03.md`.

## Verification

Passed:

- installer shell syntax check:
  - `sh -n scripts/install_openai_key.sh`;
- installer status command;
- `api_key_file` visible in config/provider;
- no key value printed.

Not run:

- real key install;
- real OpenAI API request;
- PHP CLI syntax check, because PHP CLI is unavailable in the current shell;
- production deployment.
