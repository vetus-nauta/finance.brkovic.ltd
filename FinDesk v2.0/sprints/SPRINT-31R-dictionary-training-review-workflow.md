# SPRINT-31R — Dictionary Training Review Workflow

## Director Sprint Opening

Sprint:
SPRINT-31R — Dictionary Training Review Workflow

Date:
2026-07-08

Goal:
Turn dictionary review output into an explicit, audited training-decision workflow without letting review metadata mutate operational accounting.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/20-definition-of-done.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/06-dictionaries-and-localization.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/sql/001-clean-core-mariadb.sql`
- `app/v2/Api.php`
- `app/v2/Repository.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`

Agents assigned:

- Linguistic Rules Agent
- Backend/API Agent
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

Agent tasks:

- Linguistic Rules Agent: define safe decision types, required preserved evidence, and local-vs-universal boundaries.
- Backend/API Agent: find smallest route/schema/audit implementation path.
- Financial Logic Engine Agent: define red lines so decisions cannot affect entries, totals, reports, lower accounting, or import acceptance.
- QA/Audit Agent: define fixture/HTTP negative tests and security/audit risks.

Expected reports:

- ACCEPT/REJECT with concrete blockers.
- Required assertions and risks.
- Must-not-touch list.

Exit criteria:

- Dictionary review queue remains read-only.
- Training decision persistence is explicit and audited.
- Ordinary approval can create only workspace-local category rules.
- Blocked/personal/debt/money-movement/unclear-commercial rows cannot create category rules.
- Universal promotion is not available from normal queue review.
- Entry primitives, balances, reports, lower accounting, and import acceptance remain unchanged.
- Fixture and HTTP smoke cover success, rejection, blocked cases, viewer access, and report invariants.

Risks:

- Group approvals can over-train broad rules.
- Merchant or actor aliases can leak one workspace into product-wide logic.
- Weak or mixed rows can look correct but require `requires_any` / `excludes_any` discipline.
- Universal promotion is high risk and must stay outside this MVP path.

## Initial Agent Reports

### Linguistic Rules Agent

Accepted.

Key guidance:

- `defer`, `reject_training`, `approve_existing_guess_local`, `correct_category_local`, `mark_semantic_blocked`, and `propose_universal_candidate` are safe MVP decisions.
- `promote_universal` must be a separate audited admin/Director action and is outside normal queue review.
- All review-queue learned rules are workspace-local by default.
- Blocked rows must not train operational category rules.
- Source row ids and source snapshots must be stored immutably because group membership can change over time.

### Financial Logic Engine Agent

Accepted.

Red lines:

- Decisions must not change `amount`, `sign`, `flow`, `direction`, `entry_type`, `category_code`, `status`, or `balance_after`.
- Decisions must not change cash/card arithmetic, monthly reports, lower accounting, or import acceptance.
- Dictionary review queue remains read-only.
- Training decisions may write decision/audit records and, only for explicit local approval, workspace-local category rules.

### Backend/API Agent

Accepted.

Key guidance:

- Keep `GET /dictionary-review-queue` read-only.
- Add separate `POST /dictionary-training-decisions`.
- Persist current decision state outside audit log.
- Use existing `v2_category_rules` only for explicit accepted local training.
- Avoid nested transactions by extracting category-rule insert into an internal helper.
- Validate source rows against the archive workspace resolved by `dictionaryArchiveWorkspace()`.

### QA, Audit, and Acceptance Agent

Accepted.

Required checks:

- Queue GET remains read-only, including import source/row counts and training-decision counts.
- Unsupported queue methods create no rows.
- Decision API covers approval, rejection, blocked rows, universal-candidate audit-only behavior, viewer write denial, and CSRF denial.
- Decision API must not create entries, flows, actors, or monthly closures.
- Raw import exposure through review queue remains an explicit product/security consideration.

## Planned Implementation

Backend/API:

- Add `v2_dictionary_training_decisions`.
- Add `POST /api/workspaces/:workspaceId/dictionary-training-decisions`.
- Add `GET /api/workspaces/:workspaceId/dictionary-training-decisions`.
- `approve_existing_guess_local` and `correct_category_local` create a workspace-local `v2_category_rules` row.
- `defer`, `reject_training`, `mark_semantic_blocked`, and `propose_universal_candidate` record the decision only.
- `promote_universal` is rejected by this MVP endpoint.

Safety gates:

- writer role required;
- approved local rules require target category and pattern;
- approved local rules reject known blockers;
- universal candidates are audit-only;
- source snapshot is persisted with confidence, review reason, signals, blockers, markers, source provenance, and note.

## Implemented

Schema:

- Added `v2_dictionary_training_decisions`.

Backend/API:

- Added `GET /api/workspaces/:workspaceId/dictionary-training-decisions`.
- Added `POST /api/workspaces/:workspaceId/dictionary-training-decisions`.
- Added `decideDictionaryTraining()`.
- Added `listDictionaryTrainingDecisions()`.
- Extracted category-rule insert into a transaction-safe helper.
- `approve_existing_guess_local` and `correct_category_local` create workspace-local category rules only.
- `defer`, `reject_training`, `mark_semantic_blocked`, and `propose_universal_candidate` record a decision only.
- `promote_universal` returns `universal_promotion_not_supported`.
- Blocked rows return `dictionary_training_blocked` when approval tries to create a category rule.

Tests:

- Fixture coverage for:
  - archive-backed source row validation;
  - queue read-only counts;
  - local approval creates one rule and one decision;
  - duplicate approval updates the same decision and reuses the same rule;
  - reject creates no rule;
  - debt and unclear commercial rows cannot train rules;
  - universal candidate creates no rule;
  - viewer write is blocked.
- HTTP coverage for the same API behavior plus CSRF and unsupported queue methods.

Docs:

- Updated data model.
- Updated API contract.
- Updated dictionary/localization training rules.

## Verification

Commands:

```text
php -l app/v2/Repository.php
php -l app/v2/Api.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_http_api_smoke.php
php -l scripts/v2_clean_core_static_smoke.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Results:

```text
FinDesk v2 clean core static smoke: OK
FinDesk v2 fixture runner: PASS 21
FinDesk v2 HTTP API smoke: OK
FinDesk v2 operational UI smoke: OK
FinDesk v2 browser UI smoke: OK
```

Browser screenshots:

```text
test-results/v2-browser-smoke
```

## Director Final Handoff

Sprint:
SPRINT-31R — Dictionary Training Review Workflow

Status:
ACCEPT.

Accepted work:

- Explicit audited dictionary-training decision API.
- Workspace-local rule creation only for explicit local approvals.
- Blocked/universal rows cannot silently train product dictionaries.
- Queue remains read-only.

Rejected work:

- No universal promotion from this endpoint.
- No operational entry/report/import mutation from training decisions.

Risks:

- A future UI must make weak/mixed/blocker status visible before approval.
- Group-level training remains high risk and should require careful source-row selection.
- Universal promotion needs a separate admin/Director sprint.

Next sprint:
SPRINT-32R — Dictionary Training Review UI / Decision Console.
