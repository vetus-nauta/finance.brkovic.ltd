# Sprint 01 Handoff To Sprint 02 Director

Use this as the starting context for the next Director.

## Paste-To-New-Director-Chat

```text
Ты директор FinDesk v2.0 Sprint 02.

Репозиторий: vetus-nauta/finance.brkovic.ltd
Каноническая локальная папка для Codex:
C:\Users\Vetus Nauta\Мой диск\FOR CODEX\Интернет-проекты\06-finance.brkovic.ltd

Важно:
- Работать только в этой Google Drive-синхронизированной папке.
- Старый временный checkout `C:\Users\Vetus Nauta\Documents\finance.brkovic.ltd` больше не использовать как рабочую папку.
- Перед любыми действиями выполнить `git status --short --branch` именно в канонической папке.

Локальный путь прошлого офиса внутри repo:
FinDesk v2.0/sprints/SPRINT-01-OFFICE/

Истина продукта:
- Только FinDesk v2.0/
- Старый FinDesk в корне репозитория является infrastructure donor/archive only.
- docs/ и docs/AI_TEAM/ не являются v2 product truth.

Что сделал Sprint 01:
- Подтянул настоящий GitHub repo в локальный checkout.
- Перенес рабочий checkout в каноническую Google Drive папку для работы с любой машины.
- Создал офис Sprint 01 с отчетами директора и 7 сабагентов.
- Изолировал старую финансовую логику, старые таблицы, старые категории, старые отчеты, старый dashboard UX.
- Собрал infrastructure donor list.
- Собрал production access inventory без секретов.
- Подтвердил, что v2 docs/schema/schemas не завязаны на legacy ledger/on_the_go/cash_advances/findesk tables.

Текущий Git state после Sprint 01:
- branch: main tracking origin/main
- remote: https://github.com/vetus-nauta/finance.brkovic.ltd.git
- tracked code/SQL/runtime config не менялись
- Sprint 01 office docs пока untracked и должны быть reviewed/staged/committed владельцем следующего шага
- Google Drive service files вроде `desktop.ini` локально исключены через `.git/info/exclude` и не являются частью проекта

Главные файлы к прочтению:
- FinDesk v2.0/README.md
- FinDesk v2.0/FULL_SPEC.md
- FinDesk v2.0/21-sprint-plan.md
- FinDesk v2.0/22-sprint-handoff-protocol.md
- FinDesk v2.0/23-legacy-isolation-rule.md
- FinDesk v2.0/24-secrets-hosting-access-inventory.md
- FinDesk v2.0/sprints/SPRINT-02-clean-core-foundation.md
- FinDesk v2.0/sprints/SPRINT-01-OFFICE/10-DIRECTOR-FINAL-SPRINT-REPORT.md
- FinDesk v2.0/sprints/SPRINT-01-OFFICE/08-INFRASTRUCTURE-DONOR-AND-KEEP-REWRITE-DELETE.md
- FinDesk v2.0/sprints/SPRINT-01-OFFICE/09-PRODUCTION-ACCESS-INVENTORY.md

Approved donors:
- config/private override pattern from app/config.php + app/config.local.example.php + app/db.php;
- auth shell idea from app/auth.php after rewrite on clean v2 tables;
- .htaccess private path boundary after hosting revalidation;
- thin wrapper/router pattern only, not old public/api.php action list;
- PWA manifest/service-worker pattern after rewrite;
- visualViewport/safe-area/internal scroll/async guard/helper patterns after rewrite;
- i18n language switch shell only, not old translation copy;
- deployment discipline: narrow package, backup, DB preflight, smoke, rollback, no-secret evidence.

Rejected as v2 truth:
- app/ledger.php
- app/on_the_go.php
- app/advances.php
- app/findesk_phase2.php
- app/ai.php finance analysis
- server/findesk-atlas-server.js
- public/api.php old action list
- deploy/ledger_foundation.sql
- deploy/categories_foundation.sql
- deploy/on_the_go_foundation.sql
- deploy/on_the_go_sessions_runtime.sql
- deploy/advances_foundation.sql
- deploy/findesk_phase2_foundation.sql
- old ledger_entries, ledger_categories, on_the_go_*, cash_advances, findesk_* tables
- old report/final package formulas
- old cash/noncash semantics
- old dashboard/Captain/On the Go/Advanced/Business/Yacht UX as v2 direction.

Production facts:
- Production URL: https://finance.brkovic.ltd
- Live app path: /app.php
- Deploy path evidence: /home/brkovic/finance.brkovic.ltd
- FTP tree evidence: /finance.brkovic.ltd
- Production DB engine evidence: MariaDB 11.4.10-MariaDB-cll-lve-log
- Last confirmed production marker in old reports: 20260528-records-scroll1
- Later open-items release 20260528-open-sprint1 was local only, blocked by missing FINDESK_FTP_* and FINDESK_DB_GATE_URL.

Critical Sprint 02 decision:
- FinDesk v2.0/sql/clean-core-schema.sql is PostgreSQL-style.
- Production evidence is MariaDB/PDO MySQL.
- Default Director recommendation from Sprint 01: target MariaDB-compatible clean v2 schema unless owner explicitly provisions PostgreSQL.
- Do not use old MySQL legacy tables as v2 schema.

Sprint 02 must:
1. Work from the canonical Google Drive folder above.
2. Decide DB engine/migration discipline before writing backend code.
3. Create clean v2 runtime/API namespace; do not extend old public/api.php for v2 core.
4. Build clean foundation only from v2 entities:
   workspaces, workspace_members, flows, entries, categories, category_rules, actors, attachments, monthly_closures, import_sources, import_rows, audit_log.
5. Preserve Cash/Card funding-flow model exactly.
6. Keep operational journal as source of truth and summaries generated.
7. Commit no secrets.

Open access items:
- Confirm hosting provider/control panel/account owner.
- Confirm FTP/SFTP/SSH method and credential owner/storage.
- Confirm DNS provider/records and SSL renewal owner.
- Confirm backup/restore owner and DB migration channel.
- Locate real historical Excel/Google Sheet archive for future Sprint 05 import.
```

## Director Note

The next Director should start from the Sprint 02 file, but must not skip the DB decision gate. That gate is the difference between a clean v2 foundation and accidentally reviving the old system.
