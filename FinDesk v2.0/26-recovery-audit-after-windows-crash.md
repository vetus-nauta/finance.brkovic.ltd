# 26 — Recovery Audit After Windows Crash

## Status

Audit date: 2026-07-05

Repository checked: `vetus-nauta/finance.brkovic.ltd`

Working package: `FinDesk v2.0/`

Google Drive folder checked: `FinDesk v2.0`

## Executive finding

Google Drive contains a longer recovered sprint documentation chain than GitHub.

GitHub currently contains the authoritative planning/spec package and Sprint 01-08 sprint contracts under `FinDesk v2.0/`, but the code evidence referenced by later Drive sprint reports is not present in GitHub at the checked paths.

Therefore, the safest position is:

```text
GitHub = current saved repository truth.
Google Drive = recovery/history/audit truth for sprint documentation and claimed local evidence.
Missing from GitHub = local implementation files that may have existed on Windows but were not committed or were lost.
```

## GitHub state found

Confirmed in GitHub:

- `FinDesk v2.0/README.md`
- `FinDesk v2.0/FULL_SPEC.md`
- `FinDesk v2.0/01-product-logic.md` through `20-definition-of-done.md`
- `FinDesk v2.0/21-sprint-plan.md`
- `FinDesk v2.0/22-sprint-handoff-protocol.md`
- `FinDesk v2.0/23-legacy-isolation-rule.md`
- `FinDesk v2.0/24-secrets-hosting-access-inventory.md`
- `FinDesk v2.0/25-final-repository-audit.md`
- `FinDesk v2.0/agents/`
- `FinDesk v2.0/schemas/`
- `FinDesk v2.0/sql/`
- `FinDesk v2.0/sprints/SPRINT-01` through `SPRINT-08`

GitHub repository code search is not indexed, so exact tree search is limited. Direct path checks were used for critical code files.

## GitHub gaps found

The following files are referenced by Google Drive Sprint 16 as evidence, but were not found in GitHub at the checked paths:

```text
app/v2/Database.php
app/v2/Api.php
public/v2-api.php
```

Sprint 16 also references:

```text
app/v2/Repository.php
app/v2/Support.php
FinDesk v2.0/sql/001-clean-core-mariadb.sql
```

These should be checked/recovered next as possible lost local files.

## Google Drive state found

Google Drive contains:

- canonical `FinDesk v2.0` folder;
- mirrored specification files;
- `agents`, `schemas`, `sql`, and `sprints` folders;
- Sprint 01 office reports;
- Sprint 02 office reports and handoff to Sprint 03;
- original Sprint 01-08 contracts;
- recovered Sprint 09-15 documentation chain;
- Sprint 16 evidence audit;
- Sprint 17 API/schema gap ledger;
- Sprint 18 director continuation gate;
- Director final report for recovered Sprints 09-15;
- Director final report for Sprints 16-18.

## Sprint chain interpretation

### Sprint 01

Completed as documentation/inventory handoff. Implementation was not started.

### Sprint 02

Completed as clean-core foundation according to Drive reports. Deployment remained gated.

### Sprint 03-08

Contracts exist. Based on later recovery docs, implementation evidence is not fully proved in GitHub.

### Sprint 09-15

Recovered documentation chain exists in Drive. Director final report says this was completed as documentation recovery, not as full feature implementation.

### Sprint 16

Completed as implementation evidence audit. It explicitly separates repository proof from documentation-only claims.

Sprint 16 accepted evidence:

- clean v2 PHP namespace was reported;
- public v2 API entrypoint was reported;
- repository foundation for workspaces, flows, entries, categories, audit writes was reported;
- MariaDB clean schema was reported;
- sprint chain through Sprint 15 existed.

But the referenced code files were not found in GitHub during this audit.

### Sprint 17

Completed as API/schema gap ledger.

It states that reports, month closure, import, attachments, parse preview, category rules write API, and delete entry are not implemented routes yet.

### Sprint 18

Completed as director continuation gate.

It names Sprint 19 as the next implementation sprint and says visible work must stay deferred until arithmetic and API behavior are proved.

## Agents identified from reports

From available Drive reports, these roles appear:

- Codex Director
- Financial Logic Engine Agent
- Data/Backend Core Agent
- iOS-Native UX Layout Agent
- QA, Audit, and Acceptance Agent
- Security/Privacy framing
- Localization/Linguistic framing
- Legacy Import framing

The later Sprint 09-18 chain is director/framing-heavy and should not be treated as complete implementation unless GitHub code evidence is recovered.

## Critical conclusion

Do not continue as if Sprint 16 means implementation is complete.

Current safe next step is a recovery sprint, not Sprint 19 feature build.

## Recommended next action

Run a Recovery Sprint before any new work:

```text
RECOVERY-SPRINT-A — Repository vs Drive Reconciliation
```

Tasks:

1. Search local Windows machine, backups, and Git reflog for missing files:
   - `app/v2/Database.php`
   - `app/v2/Api.php`
   - `app/v2/Repository.php`
   - `app/v2/Support.php`
   - `public/v2-api.php`
   - `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
2. If found, commit them to GitHub.
3. If not found, create a new implementation plan from Drive Sprint 16-18 evidence.
4. Do not accept reports/imports/attachments/month closure as implemented until routes exist and tests pass.
5. Start real Sprint 19 only after recovery is closed.

## Final audit position

The project is not lost, but the implementation state is not fully preserved in GitHub.

Planning and recovery documentation is preserved.

Some reported code evidence from Sprint 16 is currently missing from GitHub and must be recovered or rebuilt.
