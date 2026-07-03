# Sprint 02 QA/Audit Acceptance Report

Sprint: Sprint 02 - Clean Core Foundation
Date: 2026-07-03
Status: Accepted with environment caveat.

## Contract Checks

Passed:

- MariaDB-compatible clean schema exists.
- Required v2 tables exist in `001-clean-core-mariadb.sql`.
- Category seed exists and includes MVP category codes.
- Cash/Card default flow seed exists.
- Clean v2 runtime namespace exists under `app/v2`.
- Clean API entry exists at `public/v2-api.php`.
- Legacy `public/api.php` was not modified.
- Old root FinDesk is still donor/archive only.
- No real secrets were added.

## Static Commands Run

```text
git status --short --branch
rg --files
rg -n "CREATE TABLE IF NOT EXISTS (workspaces|workspace_members|flows|entries|categories|category_rules|actors|attachments|monthly_closures|import_sources|import_rows|audit_log)" "FinDesk v2.0/sql/001-clean-core-mariadb.sql"
rg -n "(Cash|Card|cash_topup_from_card|commercial_income|provisions|media_comms|other)" "FinDesk v2.0/sql/002-seed-mvp-categories.sql" "FinDesk v2.0/sql/003-seed-default-workspace-flows.sql"
git diff -- public/api.php api.php app.php index.php
git diff --check
```

## Runtime Checks Not Run Locally

`php` was not available in PATH on this machine:

```text
php : The term 'php' is not recognized as the name of a cmdlet, function, script file, or operable program.
```

Must run on PHP-enabled local/server environment:

```text
php -l app/v2/Database.php
php -l app/v2/Support.php
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l public/v2-api.php
php "FinDesk v2.0/sprints/SPRINT-02-OFFICE/v2-api-smoke.php"
```

## MariaDB Apply Checks To Run

```text
mysql --host="$DB_HOST" --user="$DB_USER" --password --database="$DB_NAME" < "FinDesk v2.0/sql/001-clean-core-mariadb.sql"
mysql --host="$DB_HOST" --user="$DB_USER" --password --database="$DB_NAME" < "FinDesk v2.0/sql/002-seed-mvp-categories.sql"
mysql --host="$DB_HOST" --user="$DB_USER" --password --database="$DB_NAME" < "FinDesk v2.0/sql/003-seed-default-workspace-flows.sql"
```

## Acceptance Decision

Sprint 02 is accepted for repository foundation. Production/staging deployment remains gated by:

- PHP lint/smoke on a PHP-enabled environment.
- MariaDB migration apply on clean staging database.
- Owner confirmation of production migration channel and backup/restore owner.

