# SPRINT-29R — Universal Linguistic Classification Layer

## Director Sprint Opening

Sprint:
SPRINT-29R — Universal Linguistic Classification Layer

Goal:
Improve FinDesk v2.0 record recognition as a product-wide linguistic system, using Claudia Z only as beta corpus and real-world stress data, not as product truth.

Required files read:

- `FinDesk v2.0/33-director-agent-orchestration-protocol.md`
- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/06-dictionaries-and-localization.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/schemas/categories.seed.json`
- `app/v2/Repository.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`

Agents assigned:

- Localization / Linguistic Rules Agent
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

Exit criteria:

- Do not create new categories.
- Do not make Claudia Z local names product truth.
- Preserve physical balances and counted statuses.
- Preserve strict commercial-income boundary.
- Preserve lower accounting as derived reporting view.
- Add universal weak/mixed dictionary markers for training/review.
- Add fixture and HTTP coverage for actor/role/category priority.

## Agent Reports Received

### Localization / Linguistic Rules Agent

Accepted.

Key recommendations:

- Use rule tiers instead of a flat word-to-category map.
- Separate strong category words, weak context words, blockers, review triggers, actor names, and workspace aliases.
- Weak words such as `агент`, `магазин`, `доставка`, `обед`, and `инвентарь` must not train universal rules alone.
- Strong phrase should beat weak role word.
- Specific object should beat delivery/movement word.
- Merchant aliases should be supplier/workspace metadata unless approved as universal category rules.

### Financial Logic Engine Agent

Accepted.

Red lines:

- Semantic markers must not change `amount`, `sign`, `flow`, `direction`, `entry_type`, `status`, `balance_after`, or physical totals.
- `commercial_income` stays narrow and requires explicit yacht/charter/rental wording on income rows.
- Lower accounting remains a derived reporting layer.
- Dictionary review remains read-only metadata.
- Closed-month changes still require explicit user decision.

### QA, Audit, and Acceptance Agent

Accepted after re-review.

Initial report rejected the sprint because:

- weak/mixed dictionary markers did not yet force dictionary queue `needs_review`;
- `обед с агентом` could receive a false weak marker;
- commercial-income documentation still listed generic brokerage/agency/commission as strong examples.

Fixes accepted:

- `weak_dictionary_context` and `mixed_dictionary_context` now force dictionary review.
- `обед с агентом` resolves to `representation_expenses` without `weak_dictionary_context`.
- Commercial-income docs and seed keywords now require explicit yacht/charter/rental context.
- Tests cover preview, persisted entries, dictionary guard, and `needs_review=1` filtering.

## Implemented

Backend:

- Added semantic marker `weak_dictionary_context`.
- Added semantic marker `mixed_dictionary_context`.
- Added weak dictionary detection for generic context words.
- Added mixed dictionary detection for entries containing more than one category family.
- Weak/mixed dictionary markers now force dictionary review queue `needs_review`.
- Adjusted live parser priority so representation phrase `обед с агентом` beats weak role word `агент`.
- Added `фильтр` to the live technical-parts object rule so `доставка фильтра` is not reduced to plain transport.

Tests:

- Fixture parse-preview now checks:
  - `обед с агентом -> representation_expenses`
  - `агент -> current_boat_expenses + weak_dictionary_context`
  - `доставка фильтра -> tech_parts + mixed_dictionary_context`
- HTTP parse-preview now checks:
  - weak marker on `агент`
  - weak marker on plain `доставка`
  - mixed marker on `доставка фильтра`
  - representation priority for `обед с агентом`
- HTTP persisted entries now check:
  - amount/sign/entry type remain intact for weak/mixed/strong phrase rows
  - intended markers are present or absent after save
- HTTP dictionary guard import now includes the same cases in read-only dictionary review queue.
- HTTP dictionary guard `needs_review=1` filter includes weak/mixed rows.

Docs:

- Added universal rule tiers to parsing and dictionary contracts.
- Added new marker ids to API contract.
- Removed generic brokerage/agency/commission as strong commercial-income examples.

## Verification

Commands run:

```bash
php -l app/v2/Repository.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_http_api_smoke.php
npm run smoke:v2
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Results:

```text
php -l: OK
npm run smoke:v2: OK
npm run test:v2:fixtures: PASS (20)
npm run smoke:v2:http: OK
npm run smoke:v2:ui: OK
npm run smoke:v2:browser: OK
git diff --check: OK
```

## Acceptance

ACCEPTED locally.

The sprint adds the first product-wide linguistic safety layer: weak/mixed words are explainable and reviewable, without becoming automatic universal training rules.

## Risks

- This sprint adds metadata markers, not a full weighted rules engine.
- Weak/mixed markers are MVP training signals; later work should add explicit review reason codes.
- Actor extraction is still conservative and not yet generalized beyond existing cases.

## Next Sprint

Build a true weighted rule engine with explicit confidence/review reason codes, while keeping current regex behavior covered by tests.
