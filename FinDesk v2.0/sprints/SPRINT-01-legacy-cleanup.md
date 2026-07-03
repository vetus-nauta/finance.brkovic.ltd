# Sprint 01 — Legacy Cleanup and Infrastructure Donor Extraction

## Goal

Clean old FinDesk and keep only safe foundational infrastructure for FinDesk v2.0.

This sprint must not build new finance logic. It prepares clean ground.

## Critical legacy rule

Read `../23-legacy-isolation-rule.md` before inspecting old FinDesk.

Old FinDesk is not product truth. Its documentation, finance logic, disciplines, tables, entities, reports, categories, dashboards, and calculations must not be used as the basis for v2.0.

The old project can only donate infrastructure carcass parts after classification and Director approval.

## Hosting and production access rule

Read `../24-secrets-hosting-access-inventory.md` before inspecting env, hosting, FTP, SFTP, SSH, database, deployment, domain, or server-related files.

Sprint 01 must extract a production access inventory from the old project/repository, but must not commit real secret values.

## Scope

- inspect old FinDesk project structure;
- identify environment/secrets/config needed by new project;
- inventory hosting/server/account access clues;
- inventory FTP/SFTP/SSH/deployment clues;
- inventory domain/DNS/SSL clues;
- identify reusable infrastructure;
- classify old parts as donor/safe/unsafe/unknown;
- isolate old business logic;
- create clean v2.0 project namespace/module if needed;
- confirm old tables/entities/calculations are not reused.

## Reusable from old FinDesk

Allowed if clean and documented:

- env/secrets handling;
- DB connection method;
- auth shell;
- backend server shell;
- admin shell base;
- deployment config;
- file upload base;
- user/session handling;
- generic helpers after review.

## Classification required

Every old FinDesk part considered for reuse must be classified as:

```text
INFRASTRUCTURE_DONOR
GENERIC_HELPER
UNSAFE_LEGACY_LOGIC
UNKNOWN_REQUIRES_DIRECTOR
```

## Forbidden

- old finance tables;
- old finance entities;
- old calculations;
- old dashboards;
- old report logic;
- old category logic;
- old UX screens as product direction;
- old documentation as product truth;
- old project discipline as v2.0 discipline;
- any database mixing;
- committing real FTP/SFTP/SSH/DB/API passwords or private keys.

## Required outputs

1. Infrastructure donor report.
2. Keep/rewrite/delete list.
3. Secrets/env inventory without secret values.
4. Production access inventory report.
5. Hosting/deployment/domain/DNS notes.
6. Old logic isolation report.
7. Clean namespace readiness report.
8. Explicit legacy documentation rejection note.
9. QA confirmation.

## Exit criteria

Sprint 01 is complete only if:

- safe reusable infrastructure is documented;
- required production access items are inventoried;
- missing access items are listed;
- unsafe old logic is documented;
- old documentation is explicitly rejected as v2.0 truth;
- no v2.0 finance core uses old tables/entities;
- no real secrets are committed;
- clean project area is ready;
- Director files final sprint report.

## Handoff to Sprint 02

Handoff must include:

- exact reusable infrastructure list;
- exact files/modules not to touch;
- environment variable names needed;
- where real secrets are stored;
- database connection notes;
- hosting/control panel notes;
- FTP/SFTP/SSH/deployment notes;
- domain/DNS/SSL notes;
- rejected legacy logic list;
- rejected legacy documentation list;
- blockers.
