# SPRINT-40R — Claudia Z Beta Account Provisioning Gate

## Director Sprint Opening

Sprint:
SPRINT-40R — Claudia Z Beta Account Provisioning Gate

Date:
2026-07-09

Goal:
Prepare the beta user account and attach the Claudia Z workspace/history to it before the Claudia Z reconciliation audit.

Beta user:

```text
vetus.nauta@gmail.com
```

Rules:

- Do not create or store passwords.
- Do not store secrets.
- Use existing email-code authentication later.
- Keep Claudia Z as beta data, not universal product truth.
- Attach existing workspace with data instead of creating an empty duplicate.

## Implemented

Added:

```text
scripts/v2_provision_beta_account.php
npm run provision:v2:claudia-z-beta
```

Behavior:

- Creates or reactivates `users.email = vetus.nauta@gmail.com`.
- Uses display name `Vetus Nauta`.
- Finds existing non-archived `Claudia Z` workspace with the largest operational history.
- Adds/upgrades the beta user as `owner` in `v2_workspace_members`.
- If `Claudia Z` does not exist, creates it as a yacht workspace.
- If `Claudia Z Archive Raw History` exists, attaches the same beta user as owner.
- Does not create passwords or sessions.

Importer alignment:

- `scripts/v2_import_claudia_z_local.php` now defaults Claudia Z owner email to `vetus.nauta@gmail.com`.
- Override remains possible through:

```text
FINDESK_V2_CLAUDIA_Z_OWNER_EMAIL
```

## Local Provisioning Result

Command:

```text
php scripts/v2_provision_beta_account.php
```

Result:

```json
{
  "email": "vetus.nauta@gmail.com",
  "user_id": 79,
  "workspace": {
    "id": "0d4faca6-3138-4ffe-9805-a6a29895b7ed",
    "name": "Claudia Z",
    "type": "yacht",
    "currency": "EUR",
    "entries_count": 126,
    "import_sources_count": 3,
    "import_rows_count": 169
  },
  "workspace_created": false,
  "archive_workspace": {
    "id": "3bb2f598-540e-4878-9d92-aad24a7d12ac",
    "name": "Claudia Z Archive Raw History",
    "type": "yacht",
    "currency": "EUR",
    "entries_count": 0,
    "import_sources_count": 57,
    "import_rows_count": 3338
  }
}
```

Interpretation:

- Primary `Claudia Z` workspace already existed and was not duplicated.
- Current operational data is present in the primary workspace.
- Raw historical import material remains available in the archive workspace.
- Both are now accessible to `vetus.nauta@gmail.com` through workspace membership.
- Display name was normalized to `Vetus Nauta`.

## Local Schema Sync

Added:

```text
scripts/v2_apply_clean_core_schema.php
npm run schema:v2:apply
```

Purpose:

- Apply `CREATE TABLE IF NOT EXISTS` statements from the clean-core schema to the local beta DB.
- Do not mutate operational entries, balances, imports, reports, or users except via explicit provisioning.

Result:

```text
FinDesk v2 clean-core schema apply: OK
Create statements applied/idempotent: 15
```

Post-check:

```text
v2_dictionary_training_decisions=1
v2_workspace_assistant_settings=1
v2_internet_reference_lookups=1
v2_report_snapshots=1
```

## Verification

```text
php -l scripts/v2_provision_beta_account.php — PASS
php -l scripts/v2_import_claudia_z_local.php — PASS
php -l scripts/v2_apply_clean_core_schema.php — PASS
php scripts/v2_apply_clean_core_schema.php — PASS
php scripts/v2_provision_beta_account.php — PASS
git diff --check -- scripts/v2_provision_beta_account.php scripts/v2_import_claudia_z_local.php — PASS
```

## Director Acceptance

Status:
ACCEPT.

Next:
Run Claudia Z reconciliation from the beta account/workspace state.
