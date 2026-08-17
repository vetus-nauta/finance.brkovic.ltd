# SPRINT-22R — Semantic Markers Engine

## Director Sprint Opening

Sprint:
SPRINT-22R — Semantic Markers Engine

Goal:
Add a minimal semantic marker engine for FinDesk v2 entries and parse preview without changing financial formulas, category totals, current/archive import separation, or Claudia Z current balance.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/sprints/SPRINT-21R-claudia-z-dictionary-linguistic-training.md`
- `FinDesk v2.0/38-claudia-z-dictionary-candidate-rules.json`
- `app/v2/Repository.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`
- `scripts/v2_export_claudia_z_dictionary_corpus.php`

Agents assigned:

- Financial Logic Safety Agent
- Localization / Linguistic Rules Agent
- QA Acceptance Agent

Agent tasks:

- Financial Logic Safety Agent: decide safe storage and fields not to touch.
- Localization / Linguistic Rules Agent: propose MVP marker regexes and priority.
- QA Acceptance Agent: provide acceptance checklist and smoke commands.

Exit criteria:

- `semantic_markers` is returned by entry and parse-preview responses.
- Markers are stored as explainable metadata in `matched_rules_json`.
- No SQL migration is required for MVP.
- No category count increase.
- No change to `amount`, `sign`, `flow_id`, `direction`, `entry_type`, `category_id`, `status`, `balance_after`.
- `сейф` is cash context only.
- Non-commercial income becomes `owner_funding`, not `commercial_income`.
- `аренда авто` does not become `commercial_income`.
- `ареда яхты` / yacht-charter wording can become `commercial_income`.

## Agent Reports Received

### Financial Logic Safety Agent

Accepted recommendation:

- MVP storage: `v2_entries.matched_rules_json`.
- API exposes extracted `semantic_markers`.
- Raw import-only markers can live in corpus/export artifacts; do not create operational entries.

Do not touch:

```text
v2_entries.amount
v2_entries.sign
v2_entries.direction
v2_entries.entry_type
v2_entries.status
v2_entries.category_id
v2_entries.flow_id
v2_entries.date
v2_entries.balance_after
v2_flows.opening_balance
v2_monthly_closures.*
```

### Localization / Linguistic Rules Agent

Accepted MVP marker rules:

```text
cash_location_safe:
\bсейф(?:а|е|у|ом|ы)?\b

commercial_income_allowed:
чартер / оплата чартера / аренда яхты / сдача яхты / charter / yacht rental / yacht booking

owner_funding:
sign === "+" and commercial_income_allowed is false and cash_topup_from_card is false

debt_or_return:
долг / возврат / вернул / отдал / под отчет / подотчет

tender_related:
тузик / тендер / dinghy / tender / Williams / outboard
```

False positive warning:

- `аренда авто`, `аренда тендера`, `аренда тузика` are not `commercial_income`.

### QA Acceptance Agent

Acceptance commands:

```bash
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
php scripts/v2_export_claudia_z_dictionary_corpus.php storage/imports/claudia-z-dictionary
```

Expected Claudia Z invariants:

- `Claudia Z` current balance remains `15262.00`.
- `Claudia Z Archive Raw History` remains raw-only with `0` operational entries.
- Category count remains `16`.

## Implemented

Backend:

- Added semantic marker inference in `app/v2/Repository.php`.
- Added `semantic_markers` to entry rows and parse preview rows.
- Kept markers inside `matched_rules_json`.

Frontend:

- Entry details now show `semantic_markers`.
- Parse preview now shows marker ids.

Tests:

- Added fixture checks for:
  - `+6000 из сейфа`
  - `+5000 от Александра`
  - `+5525 ареда яхты`
  - `+100 аренда авто`
- Added HTTP smoke checks for parse-preview semantic markers.

Docs:

- Updated parser contract.
- Updated API contract.

## Risks

- `matched_rules_json` now stores both category explanation and semantic markers. This is acceptable for MVP but should become a side table later if marker backfill/edit workflows grow.
- Existing closed-month entries are not backfilled by this sprint.
- `source_actor` is marker metadata, not `actor_id`, unless parser can confidently create a canonical actor later.

## Verification

Commands run:

```bash
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
php scripts/v2_import_claudia_z_local.php "/home/alexey/GoogleDrive/Claudia Z/Бухгалтерия/Бухгалтерия" storage/imports/claudia-z-current --reset --mode=current
php scripts/v2_import_claudia_z_local.php "/home/alexey/GoogleDrive/Claudia Z/Бухгалтерия/Бухгалтерия" storage/imports/claudia-z-archive --reset --mode=archive
php scripts/v2_export_claudia_z_dictionary_corpus.php storage/imports/claudia-z-dictionary
```

Results:

```text
FinDesk v2 clean core static smoke: OK
FinDesk v2 fixture runner: PASS
PASS (19)
FinDesk v2 HTTP API smoke: OK
Categories: 16

Claudia Z current:
cash Cash balance=15262.00
card Card balance=0.00
rows_created=126

Claudia Z archive:
rows_created=0
cash Cash balance=0.00

Dictionary corpus:
rows_total=3338
unique_descriptions=1192
owner_funding=137
cash_location_safe=57
debt_or_return=55
commercial_income_allowed=1
```

Readback example:

```text
+4100.00 через крипту => owner_funding:crypto
+19050.00 из крипты => owner_funding:crypto
```

## Director Final Handoff

Sprint:
SPRINT-22R — Semantic Markers Engine

Status:
Accepted locally.

Agents assigned:

- Financial Logic Safety Agent
- Localization / Linguistic Rules Agent
- QA Acceptance Agent

Agent reports received:
All received.

Accepted work:

- Metadata-only semantic marker engine.
- API/UI visibility for markers.
- Strict commercial-income boundary.
- Claudia Z corpus readback.

Rejected work:

- SQL migration.
- New categories.
- Backfilling closed/historical entries.
- Treating `сейф` as category or flow.

Next sprint:
Dictionary Review Queue / Source Actor Review UI, or backend side-table design if markers need editable lifecycle.
