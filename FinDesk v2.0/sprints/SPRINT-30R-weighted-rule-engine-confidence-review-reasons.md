# SPRINT-30R — Weighted Rule Engine / Confidence / Review Reasons

## Director Sprint Opening

Sprint:
SPRINT-30R — Weighted Rule Engine / Confidence / Review Reasons

Date:
2026-07-08

Goal:
Add an explainable confidence and review-reason layer over FinDesk v2.0 parsing/classification, so dictionary training can distinguish strong rules, weak context, mixed context, personal/debt blockers, and unclear commercial-income wording.

Non-goals:

- Do not change financial formulas.
- Do not change parser primitives.
- Do not change deploy behavior.
- Do not let confidence alter `amount`, `sign`, `flow`, `direction`, `entry_type`, `category_code`, `status`, `balance_after`, or report totals.
- Do not train universal categories from Claudia Z-specific merchant aliases.

Required files read:

- `FinDesk v2.0/03-parsing-and-rules-engine.md`
- `FinDesk v2.0/06-dictionaries-and-localization.md`
- `FinDesk v2.0/14-calculation-contract.md`
- `FinDesk v2.0/16-api-contract.md`
- `FinDesk v2.0/sprints/SPRINT-29R-universal-linguistic-classification-layer.md`
- `app/v2/Repository.php`
- `scripts/v2_fixture_runner.php`
- `scripts/v2_http_api_smoke.php`

Agents assigned:

- Linguistic Rule Engine Agent
- Financial Logic Engine Agent
- QA, Audit, and Acceptance Agent

## Agent Reports

### Linguistic Rule Engine Agent

Accepted.

Recommendations implemented:

- Add `classification_decision` as metadata in `matched_rules`.
- Keep existing category/status decisions intact.
- Expose public `confidence`, `review_reason`, `matched_signals`, and `blockers`.
- Use deterministic review priority:
  - personal/non-yacht blocker
  - debt/return blocker
  - private money movement
  - unclear commercial income
  - mixed dictionary context
  - weak dictionary context
  - other/no-category fallback
- Keep weak/mixed rows reviewable for dictionary training.

### Financial Logic Engine Agent

Accepted.

Red lines preserved:

- `classification_decision`, `confidence`, and `review_reason` are explainability metadata only.
- Monthly totals still use entry primitives only.
- Low confidence does not demote category/status.
- Lower accounting remains a derived reporting view.
- Commercial income remains category-based and requires explicit yacht/charter wording.
- Dictionary review queue remains read-only and not a report input.

### QA, Audit, and Acceptance Agent

Accepted by automated fixture/API evidence.

Manual note:
One early QA pass looked in the wrong local root and was not used as acceptance evidence.

## Implemented

Backend:

- Added `classification_decision` metadata to normalized entries.
- Added public entry/preview fields:
  - `confidence`
  - `review_reason`
  - `matched_signals`
  - `blockers`
  - `classification_decision`
- Added the same explainability fields to dictionary-review examples.
- Added blocker `missing_yacht_charter_phrase` for generic agency/brokerage/commission income wording without yacht/charter context.
- Kept owner funding as external income metadata, not `commercial_income` and not `no_category` review noise.

Confidence/review behavior:

```text
-50 агент -> current_boat_expenses, weak_only, 0.48
-15 доставка -> transport_expenses, weak_only, 0.48
-15 доставка фильтра -> tech_parts, mixed_context, 0.64
-50 обед с агентом -> representation_expenses, no review, 0.92
-100 Порше топливо -> blocked_by_personal, 0.20
-250 долг за гараж -> berth, operational, no review, 0.92
-150 долг таможне дьюти -> admin_legal, operational, no review, 0.92
+750 агентские -> commercial_income_unclear, missing_yacht_charter_phrase, 0.30
+5525 ареда яхты -> commercial_income, no review, 0.92
```

Docs:

- Updated parsing contract with classification decision metadata.
- Updated dictionary/localization contract with confidence and review-reason rules.
- Updated API contract with entry and dictionary-review fields.

## Verification

Commands:

```text
php -l app/v2/Repository.php
php -l scripts/v2_fixture_runner.php
php -l scripts/v2_http_api_smoke.php
npm run test:v2:fixtures
npm run smoke:v2:http
npm run smoke:v2
npm run smoke:v2:ui
npm run smoke:v2:browser
git diff --check
```

Fixture result:

```text
PASS 20
```

Smoke results:

```text
FinDesk v2 HTTP API smoke: OK
FinDesk v2 clean core static smoke: OK
FinDesk v2 operational UI smoke: OK
FinDesk v2 browser UI smoke: OK
```

Browser screenshots:

```text
test-results/v2-browser-smoke
```

Final status:

ACCEPT.
